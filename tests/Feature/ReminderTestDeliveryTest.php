<?php

namespace Tests\Feature;

use App\Jobs\SendReminderTest;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderApproval;
use App\Models\User;
use App\Services\ReminderTestMailer;
use App\Services\ReminderTestQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ReminderTestDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_is_idempotent_and_worker_sends_the_exact_approved_test_email_once(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        $url = route('organizations.invoices.reminder-test-delivery', [$company, $invoice]);
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $this->actingAs($owner)->post($url, ['approval_id' => $approval->id])->assertRedirect($preview);
        $this->post($url, ['approval_id' => $approval->id])->assertRedirect($preview);
        $delivery = $approval->testDelivery()->sole();
        $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('testDelivery.status', 'pending'));

        Queue::fake();
        app(ReminderTestQueue::class)->dispatchPending();
        Queue::assertPushedOn(ReminderTestQueue::NAME, SendReminderTest::class, fn (SendReminderTest $job): bool => $job->deliveryId === $delivery->id);

        Config::set('mail.mailers.reminder-test', ['transport' => 'array']);
        $job = new SendReminderTest($delivery->id);
        app()->call($job->handle(...));
        app()->call($job->handle(...));

        $transport = Mail::mailer('reminder-test')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(1, $transport->messages());
        $message = $transport->messages()->sole();
        $this->assertSame($delivery->id.'@munkacash.test', $message->getMessageId());
        $email = $message->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertSame('billing@example.test', $email->getTo()[0]->getAddress());
        $this->assertSame('Fizetési emlékeztető', $email->getSubject());
        $this->assertStringContainsString("80 000\u{00A0}Ft", (string) $email->getTextBody());
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'accepted']);
    }

    public function test_worker_blocks_expired_approvals_and_drafts_that_change_after_queueing(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        [, , , $secondApproval] = $this->approvedDraft();
        $otherDelivery = $secondApproval->testDelivery()->create(['status' => 'pending']);
        [, , $changedInvoice, $changedApproval] = $this->approvedDraft();
        $changedDelivery = $changedApproval->testDelivery()->create(['status' => 'pending']);
        $changedInvoice->forceFill(['outstanding_amount' => 70_000, 'revision' => 1])->save();
        $owner->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);

        $mailer = $this->mock(ReminderTestMailer::class);
        $mailer->shouldNotReceive('send');
        app()->call((new SendReminderTest($delivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked', 'reason' => 'approval_changed']);

        app()->call((new SendReminderTest($changedDelivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $changedDelivery->id, 'status' => 'blocked', 'reason' => 'approval_changed']);

        $this->travel(30)->minutes();
        app()->call((new SendReminderTest($otherDelivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $otherDelivery->id, 'status' => 'blocked', 'reason' => 'approval_expired']);
    }

    public function test_changing_a_billing_address_blocks_only_the_affected_customer_deliveries(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        [, , , $otherApproval] = $this->approvedDraft();
        $otherDelivery = $otherApproval->testDelivery()->create(['status' => 'pending']);
        $customer = $invoice->customer;
        $url = route('organizations.customers.billing-contact.update', [$company, $customer]);

        $this->actingAs($owner)->patch($url, ['billing_email' => $customer->billing_email, 'revision' => 0])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'pending']);

        $this->patch($url, ['billing_email' => 'changed@example.test', 'revision' => 0])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id,
            'status' => 'blocked',
            'reason' => 'billing_contact_changed',
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $otherDelivery->id, 'status' => 'pending']);
        $this->assertNull($customer->fresh()->billing_email_confirmed_at);
    }

    public function test_uncertain_transport_and_abandoned_claims_are_not_retried(): void
    {
        [, , , $approval] = $this->approvedDraft();
        $first = $approval->testDelivery()->create(['status' => 'pending']);
        [, , , $otherApproval] = $this->approvedDraft();
        $second = $otherApproval->testDelivery()->create(['status' => 'sending']);
        $mailer = $this->mock(ReminderTestMailer::class);
        $mailer->shouldReceive('accepts')->once()->andReturn(true);
        $mailer->shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP response lost'));

        $job = new SendReminderTest($first->id);
        app()->call($job->handle(...));
        app()->call($job->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $first->id, 'status' => 'unknown']);

        $this->travel(2)->minutes();
        Queue::fake();
        app(ReminderTestQueue::class)->dispatchPending();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $second->id, 'status' => 'unknown']);
    }

    public function test_endpoint_rejects_non_test_addresses_members_foreign_invoices_and_production(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft('billing@example.com');
        [, , $foreignInvoice] = $this->approvedDraft();
        $url = route('organizations.invoices.reminder-test-delivery', [$company, $invoice]);
        $payload = ['approval_id' => $approval->id];

        $this->actingAs($owner)->post($url, $payload)->assertSessionHasErrors('delivery');
        $this->post(route('organizations.invoices.reminder-test-delivery', [$company, $foreignInvoice]), $payload)->assertNotFound();

        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($member)->post($url, $payload)->assertForbidden();
        $this->app->instance('env', 'production');
        $this->actingAs($owner)->withSession(['_token' => 'known-token'])
            ->post($url, [...$payload, '_token' => 'known-token'])->assertNotFound();
        $this->assertDatabaseCount('reminder_test_deliveries', 0);
    }

    public function test_resetting_a_balance_blocks_waiting_delivery_and_requires_a_new_approval(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        [, , , $otherApproval] = $this->approvedDraft();
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        $otherDelivery = $otherApproval->testDelivery()->create(['status' => 'pending']);
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active', 'role' => 'member']);
        $reset = route('organizations.invoices.balance.destroy', [$company, $invoice]);
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);

        $this->actingAs($member)->delete($reset, ['confirm' => true, 'revision' => 0])->assertSessionHasNoErrors();
        $this->delete($reset, ['confirm' => true, 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id, 'gross_amount' => 120_000, 'status' => 'unchecked',
            'outstanding_amount' => null, 'status_checked_at' => null, 'checked_by' => null, 'status_source' => null,
            'revision' => 1,
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id, 'status' => 'blocked', 'reason' => 'balance_unverified',
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $otherDelivery->id, 'status' => 'pending']);

        $this->actingAs($owner)->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('outstanding', null)
            ->where('approval.status', 'invalidated')
            ->where('message', null));
        $this->patch(route('organizations.invoices.balance.update', [$company, $invoice]), [
            'outstanding_amount' => 80_000,
            'revision' => 1,
        ])->assertSessionHasNoErrors();
        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('outstanding', 80_000)
            ->where('approval.status', 'invalidated'));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked']);
    }

    public function test_company_pause_blocks_its_queued_delivery_and_resuming_does_not_requeue_it(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        [, , , $otherApproval] = $this->approvedDraft();
        $otherDelivery = $otherApproval->testDelivery()->create(['status' => 'pending']);
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);

        $settings = route('organizations.reminder-delivery-settings', $company);
        $this->actingAs($owner)->patch($settings, ['enabled' => '0'])->assertRedirect(route('organizations.show', $company));

        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id, 'status' => 'blocked', 'reason' => 'organization_paused',
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $otherDelivery->id, 'status' => 'pending']);

        $this->post(route('organizations.invoices.reminder-test-delivery', [$company, $invoice]), [
            'approval_id' => $approval->id,
        ])->assertSessionHasErrors('delivery');
        $this->patch($settings, ['enabled' => '1'])->assertSessionHasNoErrors();

        $this->mock(ReminderTestMailer::class)->shouldNotReceive('send');
        app()->call((new SendReminderTest($delivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked']);
    }

    public function test_customer_restriction_records_the_actor_and_blocks_only_that_customers_pending_delivery(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft();
        $customer = $invoice->customer;
        $target = $approval->testDelivery()->create(['status' => 'pending']);
        $otherCustomer = $company->customers()->create(['display_name' => 'Másik ügyfél']);
        $otherInvoice = $company->invoices()->create([
            'customer_id' => $otherCustomer->id,
            'invoice_number' => '00043',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
        $otherApproval = $otherInvoice->reminderApprovals()->create([
            'approved_by' => $owner->id, 'fingerprint' => $approval->fingerprint,
            'invoice_number' => '00043', 'currency' => 'HUF', 'outstanding_amount' => 80_000,
            'template_version' => 'v1', 'approved_at' => now(), 'expires_at' => now()->addMinutes(30),
        ]);
        $other = $otherApproval->testDelivery()->create(['status' => 'pending']);

        $url = route('organizations.customers.contact-restriction', [$company, $customer]);
        $this->actingAs($owner)->patch($url, ['do_not_contact' => '1'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id, 'do_not_contact' => true, 'do_not_contact_updated_by' => $owner->id,
            'do_not_contact_updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $target->id, 'status' => 'blocked', 'reason' => 'customer_do_not_contact',
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $other->id, 'status' => 'pending']);

        $this->get(route('organizations.invoices.reminder-preview', [$company, $invoice]))
            ->assertInertia(fn (Assert $page) => $page->where('deliveryBlocker', 'customer_do_not_contact'));
        $this->patch($url, ['do_not_contact' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'do_not_contact' => false]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $target->id, 'status' => 'blocked']);
    }

    public function test_settings_default_to_paused_and_reject_invalid_or_unauthorized_updates(): void
    {
        [$company, $owner, $invoice] = $this->approvedDraft();
        [, , $foreignInvoice] = $this->approvedDraft();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active', 'role' => 'member']);
        $settings = route('organizations.reminder-delivery-settings', $company);
        $customer = route('organizations.customers.contact-restriction', [$company, $invoice->customer]);

        $this->assertFalse(Organization::factory()->create()->refresh()->reminders_enabled);
        $this->actingAs($owner)->patch($settings, ['enabled' => 'invalid'])->assertSessionHasErrors('enabled');
        $this->patch($customer, [])->assertSessionHasErrors('do_not_contact');
        $this->patch(route('organizations.customers.contact-restriction', [$company, $foreignInvoice->customer]), [
            'do_not_contact' => true,
        ])->assertNotFound();
        $this->actingAs($member)->patch($settings, ['enabled' => false])->assertForbidden();
        $this->patch($customer, ['do_not_contact' => true])->assertForbidden();
        $this->assertTrue($company->refresh()->reminders_enabled);
        $this->assertFalse($invoice->customer->refresh()->do_not_contact);
    }

    public function test_delivery_waits_for_opening_if_the_approval_will_still_be_active(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft(at: '2026-12-02 07:45:00');
        $url = route('organizations.invoices.reminder-test-delivery', [$company, $invoice]);

        $this->actingAs($owner)->post($url, ['approval_id' => $approval->id])->assertSessionHasNoErrors();
        $delivery = $approval->testDelivery()->sole();
        $this->assertSame('2026-12-02T07:00:00.000000Z', $delivery->available_at?->toISOString());

        Queue::fake();
        app(ReminderTestQueue::class)->dispatchPending();
        Queue::assertNothingPushed();
        app()->call((new SendReminderTest($delivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'pending']);

        $this->travel(15)->minutes();
        app(ReminderTestQueue::class)->dispatchPending();
        Queue::assertPushedOn(ReminderTestQueue::NAME, SendReminderTest::class);
    }

    public function test_worker_rechecks_the_window_before_handing_off_the_message(): void
    {
        [$company, $owner, $invoice, $approval] = $this->approvedDraft(at: '2026-12-02 17:50:00');
        $this->actingAs($owner)->post(
            route('organizations.invoices.reminder-test-delivery', [$company, $invoice]),
            ['approval_id' => $approval->id],
        )->assertSessionHasNoErrors();
        $delivery = $approval->testDelivery()->sole();

        $this->travel(10)->minutes();
        $this->mock(ReminderTestMailer::class)->shouldNotReceive('send');
        app()->call((new SendReminderTest($delivery->id))->handle(...));

        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id, 'status' => 'blocked', 'reason' => 'outside_window',
        ]);
    }

    public function test_calendar_allows_reassigned_workdays_and_blocks_holidays_evenings_and_unknown_years(): void
    {
        foreach ([
            ['2026-08-08 10:00:00', true],
            ['2026-08-21 10:00:00', false],
            ['2026-12-12 10:00:00', false],
            ['2026-12-24 10:00:00', false],
            ['2026-12-02 18:00:00', false],
            ['2027-01-04 10:00:00', false],
        ] as [$at, $allowed]) {
            [$company, $owner, $invoice, $approval] = $this->approvedDraft(at: $at);
            $response = $this->actingAs($owner)->post(
                route('organizations.invoices.reminder-test-delivery', [$company, $invoice]),
                ['approval_id' => $approval->id],
            );

            if ($allowed) {
                $response->assertSessionHasNoErrors();
            } else {
                $response->assertSessionHasErrors('delivery');
            }

            $this->assertSame($allowed, $approval->testDelivery()->exists());
        }
    }

    public function test_worker_rechecks_the_adjusted_deadline_before_sending_a_queued_reminder(): void
    {
        [, , , $approval] = $this->approvedDraft(dueDate: '2026-12-01');
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        config(['reminder-calendar.2026.days_off' => ['12-01']]);

        $this->mock(ReminderTestMailer::class)->shouldNotReceive('send');
        app()->call((new SendReminderTest($delivery->id))->handle(...));

        $this->assertDatabaseHas('reminder_test_deliveries', [
            'id' => $delivery->id,
            'status' => 'blocked',
            'reason' => 'approval_changed',
        ]);
    }

    public function test_previous_accepted_and_uncertain_attempts_count_toward_the_seven_day_limit(): void
    {
        foreach (['accepted', 'unknown'] as $priorStatus) {
            [$company, $owner, $invoice, $previous] = $this->approvedDraft(at: '2026-12-02 10:00:00');
            $previous->testDelivery()->create(['status' => $priorStatus]);
            $this->travel(6)->days();

            $approval = $previous->replicate();
            $approval->forceFill(['approved_at' => now(), 'expires_at' => now()->addMinutes(30)])->save();
            $url = route('organizations.invoices.reminder-test-delivery', [$company, $invoice]);

            $this->actingAs($owner)->post($url, ['approval_id' => $approval->id])->assertSessionHasErrors('delivery');
            $this->assertFalse($approval->testDelivery()->exists());

            $this->travel(1)->day();
            $approval->forceFill(['approved_at' => now(), 'expires_at' => now()->addMinutes(30)])->save();
            $this->post($url, ['approval_id' => $approval->id])->assertSessionHasNoErrors();
        }
    }

    /** @return array{Organization, User, Invoice, ReminderApproval} */
    private function approvedDraft(string $recipient = 'billing@example.test', string $at = '2026-12-02 10:00:00', ?string $dueDate = null): array
    {
        $this->travelTo(Carbon::parse($at, 'Europe/Budapest'));
        $company = Organization::factory()->create(['reminders_enabled' => true]);
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['status' => 'active', 'role' => 'owner']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél Kft.']);
        $customer->forceFill([
            'billing_email' => $recipient,
            'billing_email_confirmed_at' => now(),
        ])->save();
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => $dueDate ?? now('Europe/Budapest')->subWeek()->toDateString(),
            'gross_amount' => 120_000,
        ]);
        $invoice->forceFill([
            'status' => 'partially_paid',
            'outstanding_amount' => 80_000,
            'status_checked_at' => now(),
        ])->save();

        $response = $this->actingAs($owner)->get(route('organizations.invoices.reminder-preview', [$company, $invoice]));
        $this->post(route('organizations.invoices.reminder-approval', [$company, $invoice]), [
            'fingerprint' => $response->inertiaProps('fingerprint'),
            'previous_approval_id' => null,
        ])->assertSessionHasNoErrors();

        return [$company, $owner, $invoice, $invoice->reminderApprovals()->sole()];
    }
}
