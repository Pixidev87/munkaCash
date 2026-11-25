"""Run an isolated browser, SMTP and restore acceptance check with fictitious data."""

import json
import os
import re
import smtplib
import socket
import sqlite3
import subprocess
import time
from contextlib import AbstractContextManager, closing
from pathlib import Path
from tempfile import TemporaryDirectory
from urllib.error import URLError
from urllib.request import ProxyHandler, build_opener

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parent.parent
OWNER = 'hars-tulajdonos@munkacash.test'
OTHER_OWNER = 'duna-tulajdonos@munkacash.test'
RECIPIENT = 'tolgy-penzugy@munkacash.test'
HTTP = build_opener(ProxyHandler({}))


def available_ports():
    sockets = [socket.socket() for _ in range(3)]
    try:
        for binding in sockets:
            binding.bind(('127.0.0.1', 0))
        return [binding.getsockname()[1] for binding in sockets]
    finally:
        for binding in sockets:
            binding.close()


class Pilot(AbstractContextManager):
    def __init__(self, directory):
        self.directory = directory
        self.processes = []
        self.web_port, self.smtp_port, self.mail_port = available_ports()
        self.web = f'http://127.0.0.1:{self.web_port}'
        self.mail = f'http://127.0.0.1:{self.mail_port}'
        ini = directory / 'php'
        ini.mkdir()
        clock = directory / 'clock.php'
        autoload = str(ROOT / 'vendor/autoload.php').replace(chr(92), chr(92) * 2).replace("'", chr(92) + "'")
        clock.write_text(f"""<?php
require_once '{autoload}';
\\Carbon\\CarbonImmutable::setTestNow(\\Carbon\\CarbonImmutable::parse('2026-12-02 10:00:00', 'Europe/Budapest'));
""")
        (ini / 'closed-pilot-smoke.ini').write_text(f'auto_prepend_file="{clock}"\n')
        scan_dirs = os.environ.get('PHP_INI_SCAN_DIR')
        self.env = {
            **os.environ,
            'PHP_INI_SCAN_DIR': str(ini) if scan_dirs == '' else f'{scan_dirs or ""}:{ini}',
            'PILOT_STATE_DIR': str(directory / 'live'),
            'PILOT_WEB_PORT': str(self.web_port),
            'PILOT_SMTP_PORT': str(self.smtp_port),
            'PILOT_MAIL_PORT': str(self.mail_port),
        }

    def command(self, *args, state=None, acknowledge_failure=False):
        result = subprocess.run(
            ['bash', 'bin/pilot', *args],
            cwd=ROOT, env=self.environment(state), text=True,
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30,
        )
        if acknowledge_failure:
            assert result.returncode != 0, f'{args[0]} should have failed'
        elif result.returncode:
            raise RuntimeError(f'Pilot command {args[0]} failed with status {result.returncode}.')
        return result.stdout

    def environment(self, state):
        return self.env if state is None else {**self.env, 'PILOT_STATE_DIR': str(state)}

    def start(self, name):
        with (self.directory / f'{name}.log').open('w') as logfile:
            self.processes.append(subprocess.Popen(
                ['bash', 'bin/pilot', name], cwd=ROOT, env=self.env, stdout=logfile, stderr=logfile,
            ))

    def __exit__(self, *_):
        for process in reversed(self.processes):
            if process.poll() is None:
                process.terminate()
            try:
                process.wait(timeout=8)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()


def wait_for_http(url):
    for _ in range(60):
        try:
            with HTTP.open(url, timeout=1):
                return
        except URLError:
            time.sleep(0.25)
    raise RuntimeError(f'Local pilot did not start at {url}.')


def log_in(page, base, fixture, email):
    password = re.search(re.escape(email) + r'\s*\|\s*(\S+)', fixture).group(1)
    page.goto(base + '/login')
    assert page.get_by_role('link', name='Sign up').count() == 0
    page.locator('input[name=email]').fill(email)
    page.locator('input[name=password]').fill(password)
    page.locator('[data-test=login-button]').click()
    page.wait_for_url(base + '/dashboard')


def upload_demo_invoice(page, base, fixture):
    page.goto(base + '/organizations/1/invoice-imports/new')
    lines = fixture.splitlines()
    start = next(i for i, line in enumerate(lines) if line.startswith('customer_id;invoice_number;'))
    contents = '\n'.join(lines[start:start + 2]).encode()
    page.locator('input[type=file]').set_input_files({
        'name': 'smoke.csv', 'mimeType': 'text/csv', 'buffer': contents,
    })
    page.get_by_role('button', name='Előnézet készítése').click()
    expect(page.get_by_role('heading', name='Import előnézete')).to_be_visible()
    page.get_by_role('button', name='Import megerősítése').click()
    expect(page.get_by_role('heading', name='Import eredménye')).to_be_visible()


def verify_restore(pilot, snapshot):
    live = pilot.directory / 'live'
    restored = pilot.directory / 'restored'
    pilot.command('restore', str(snapshot), str(live / 'pilot.env'), state=restored)
    pilot.command('init', state=restored)
    with closing(sqlite3.connect(restored / 'closed-pilot.sqlite')) as db:
        assert db.execute('SELECT count(*) FROM invoices').fetchone() == (4,)
        assert db.execute('SELECT count(*) FROM organization_user').fetchone() == (3,)
        assert db.execute('SELECT count(*) FROM organizations WHERE reminders_enabled = 1').fetchone() == (0,)
        assert db.execute('SELECT status, reason FROM reminder_test_deliveries').fetchone() == ('blocked', 'pilot_restored')
        assert db.execute('SELECT count(*) FROM jobs').fetchone() == (0,)
        assert db.execute('SELECT count(*) FROM sessions').fetchone() == (0,)
    pilot.command('restore', str(snapshot), str(live / 'pilot.env'), state=restored, acknowledge_failure=True)


def run(pilot, playwright):
    pilot.command('init')
    fixture = pilot.command('demo')
    pilot.command('check', acknowledge_failure=True)
    pilot.start('mail')
    pilot.start('web')
    pilot.start('worker')
    wait_for_http(pilot.web + '/up')
    wait_for_http(pilot.mail + '/api/v1/messages')
    with smtplib.SMTP('127.0.0.1', pilot.smtp_port, timeout=2) as smtp:
        assert smtp.ehlo()[0] == 250
        assert smtp.mail('smoke@munkacash.test')[0] == 250
        assert smtp.rcpt('blocked@example.invalid')[0] >= 500

    browser = playwright.chromium.launch(
        executable_path=os.environ.get('PILOT_CHROMIUM_PATH'),
        args=[arg for arg in os.environ.get('PILOT_CHROMIUM_ARGS', '').split() if arg],
    )
    page = browser.new_page()
    registration = page.request.get(pilot.web + '/register')
    assert registration.status == 404, f'Registration returned HTTP {registration.status}.'
    log_in(page, pilot.web, fixture, OWNER)
    upload_demo_invoice(page, pilot.web, fixture)
    expect(page.get_by_text('Létrehozva: 1 számla')).to_be_visible()
    upload_demo_invoice(page, pilot.web, fixture)
    expect(page.get_by_text('Változatlan: 1 számla')).to_be_visible()

    page.goto(pilot.web + '/organizations/1')
    page.get_by_role('button', name='Küldés engedélyezése').click()
    expect(page.get_by_role('status').filter(has_text='Küldés engedélyezve')).to_be_visible()
    page.goto(pilot.web + '/organizations/1/invoices/1/reminder-preview')
    page.get_by_role('button', name='Levéltervezet jóváhagyása').click()
    expect(page.get_by_role('status').filter(has_text='Jóváhagyva')).to_be_visible()
    page.get_by_role('button', name='Tesztlevél sorba állítása').click()
    expect(page.get_by_text('Tesztlevél sorban áll')).to_be_visible()

    backup = pilot.directory / 'snapshot.sqlite'
    pilot.command('backup', str(backup))
    pilot.command('tick')
    messages = []
    deadline = time.monotonic() + 15
    while time.monotonic() < deadline:
        with HTTP.open(pilot.mail + '/api/v1/messages', timeout=2) as response:
            messages = json.load(response)['messages']
        if messages:
            break
        time.sleep(0.25)
    assert len(messages) == 1
    assert [person['Address'] for person in messages[0]['To']] == [RECIPIENT]
    assert messages[0]['Subject'] == 'Fizetési emlékeztető'
    with HTTP.open(pilot.mail + '/api/v1/message/' + messages[0]['ID'], timeout=2) as response:
        message = json.load(response)
    assert '80 000' in message['Text']
    page.reload()
    expect(page.get_by_text('A Mailpit átvette a tesztlevelet')).to_be_visible()
    pilot.command('check')

    page.context.clear_cookies()
    log_in(page, pilot.web, fixture, OTHER_OWNER)
    assert page.request.get(pilot.web + '/organizations/1/invoices').status == 404
    expect(page.get_by_role('link', name='MunkaCash Demó · Duna Stúdió', exact=True)).to_be_visible()
    browser.close()
    verify_restore(pilot, backup)
    print('PASS: import, duplicate handling, tenant isolation, Mailpit delivery, monitoring and isolated restore.')


if __name__ == '__main__':
    with TemporaryDirectory(prefix='munkacash-pilot-smoke-') as folder:
        with Pilot(Path(folder)) as pilot, sync_playwright() as playwright:
            run(pilot, playwright)
