<?php

use App\Models\CustomerBillingContactEvent;
use App\Models\InvoiceBalanceEvent;
use App\Models\InvoiceDeadlineEvent;
use App\Models\InvoiceImport;
use App\Models\OrganizationAccessEvent;
use App\Models\OrganizationInvitation;
use App\Models\ReminderApproval;
use App\Services\ClosedPilotHeartbeat;
use App\Services\ReminderTestQueue;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [InvoiceImport::class]])->everyTenMinutes();

Schedule::command('model:prune', ['--model' => [
    ReminderApproval::class,
    OrganizationInvitation::class,
    OrganizationAccessEvent::class,
    InvoiceBalanceEvent::class,
    InvoiceDeadlineEvent::class,
    CustomerBillingContactEvent::class,
]])->daily();

Schedule::call(function (): void {
    app(ReminderTestQueue::class)->dispatchPending();

    if (app()->environment('closed-pilot')) {
        app(ClosedPilotHeartbeat::class)->mark(ClosedPilotHeartbeat::SCHEDULER);
    }
})
    ->name('reminder-test-delivery')
    ->environments(['local', 'testing', 'closed-pilot'])
    ->everyMinute();
