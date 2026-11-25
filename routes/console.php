<?php

use App\Models\InvoiceImport;
use App\Models\OrganizationInvitation;
use App\Models\ReminderApproval;
use App\Services\ReminderTestQueue;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [InvoiceImport::class]])->everyTenMinutes();

Schedule::command('model:prune', ['--model' => [ReminderApproval::class, OrganizationInvitation::class]])->daily();

Schedule::call(fn () => app(ReminderTestQueue::class)->dispatchPending())
    ->name('reminder-test-delivery')
    ->environments(['local', 'testing'])
    ->everyMinute();
