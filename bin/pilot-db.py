import argparse
import os
import re
import sqlite3
from contextlib import closing
from pathlib import Path
from tempfile import TemporaryDirectory

PILOT_BACKUP_ID = 0x4D43504C


def connect_read_only(path):
    return sqlite3.connect(path.resolve(strict=True).as_uri() + '?mode=ro', uri=True)


def snapshot(source, destination):
    with closing(connect_read_only(source)) as original:
        with closing(sqlite3.connect(destination)) as copy:
            original.backup(copy)
            if copy.execute('PRAGMA integrity_check').fetchall() != [('ok',)]:
                raise ValueError('The SQLite snapshot failed its integrity check.')
            if copy.execute('PRAGMA foreign_key_check').fetchone() is not None:
                raise ValueError('The SQLite snapshot has broken foreign keys.')


def backup(database, destination):
    with TemporaryDirectory(dir=destination.parent) as folder:
        copy = Path(folder) / 'snapshot.sqlite'
        snapshot(database, copy)
        with closing(sqlite3.connect(copy)) as db:
            db.execute(f'PRAGMA application_id = {PILOT_BACKUP_ID}')
        copy.chmod(0o600)
        os.link(copy, destination)


def restore(database, key_file, source, source_key):
    if database.exists() or key_file.exists():
        raise ValueError('Restore requires a new pilot state directory.')
    credentials = source_key.read_bytes()
    if not re.fullmatch(rb'APP_KEY=base64:[A-Za-z0-9+/]{43}=\n?', credentials):
        raise ValueError('The supplied file is not a pilot application key.')

    with TemporaryDirectory(dir=database.parent) as folder:
        copy = Path(folder) / 'restored.sqlite'
        key_copy = Path(folder) / 'pilot.env'
        snapshot(source, copy)
        with closing(sqlite3.connect(copy)) as db, db:
            if db.execute('PRAGMA application_id').fetchone() != (PILOT_BACKUP_ID,):
                raise ValueError('This is not a closed pilot backup.')
            db.execute('UPDATE organizations SET reminders_enabled = 0')
            db.execute("""UPDATE reminder_test_deliveries SET status = 'blocked', reason = 'pilot_restored'
                          WHERE status = 'pending'""")
            db.execute("""UPDATE reminder_test_deliveries SET status = 'unknown', reason = 'transport_unconfirmed'
                          WHERE status = 'sending'""")
            for statement in (
                'DELETE FROM jobs',
                'DELETE FROM failed_jobs',
                'DELETE FROM sessions',
                'DELETE FROM password_reset_tokens',
                'DELETE FROM cache',
                'DELETE FROM cache_locks',
                'UPDATE users SET remember_token = NULL',
            ):
                db.execute(statement)

        copy.chmod(0o600)
        key_copy.write_bytes(credentials)
        key_copy.chmod(0o600)
        os.link(key_copy, key_file)
        try:
            os.link(copy, database)
        except OSError:
            key_file.unlink()
            raise


def main():
    parser = argparse.ArgumentParser()
    commands = parser.add_subparsers(dest='command', required=True)
    save = commands.add_parser('backup')
    save.add_argument('database', type=Path)
    save.add_argument('destination', type=Path)
    load = commands.add_parser('restore')
    for argument in ('database', 'key_file', 'source', 'source_key'):
        load.add_argument(argument, type=Path)
    args = parser.parse_args()

    os.umask(0o077)
    try:
        if args.command == 'backup':
            backup(args.database, args.destination)
            print('Pilot SQLite snapshot verified and saved.')
        else:
            restore(args.database, args.key_file, args.source, args.source_key)
            print('Pilot restored in a new state directory. Run bash bin/pilot init before starting it.')
    except (OSError, ValueError, sqlite3.Error) as error:
        parser.exit(1, f'Pilot database operation failed: {error}\n')


if __name__ == '__main__':
    main()
