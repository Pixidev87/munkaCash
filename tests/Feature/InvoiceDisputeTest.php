<?php

namespace Tests\Feature;

use App\Jobs\SendReminderTest;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderApproval;
use App\Models\User;
use App\Services\ReminderPreview;
use App\Services\ReminderTestMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceDisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_dispute_keeps_the_balance_and_stops_only_its_queued_delivery(): void
    {
        $company = Organization::factory()->create(['reminders_enabled' => true]);
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);
        $customer->forceFill(['billing_email' => 'penzugy@example.test', 'billing_email_confirmed_at' => now()])->save();
        $invoice = $this->createInvoice($company, $customer, '00042');
        $otherInvoice = $this->createInvoice($company, $customer, '00043');
        $approval = $this->approve($company, $invoice, $owner);
        $delivery = $approval->testDelivery()->create(['status' => 'pending']);
        $otherDelivery = $this->approve($company, $otherInvoice, $owner)->testDelivery()->create(['status' => 'pending']);

        $dispute = route('organizations.invoices.dispute.update', [$company, $invoice]);
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);

        $this->actingAs($owner)->patch($dispute, ['is_disputed' => '1', 'revision' => 0])->assertSessionHasNoErrors();
        $this->patch($dispute, ['is_disputed' => true, 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id, 'is_disputed' => true, 'outstanding_amount' => 80_000,
            'status' => 'partially_paid', 'revision' => 1,
        ]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked', 'reason' => 'invoice_disputed']);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $otherDelivery->id, 'status' => 'pending']);
        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('deliveryBlocker', 'invoice_disputed')
            ->where('message', null)
            ->where('approval.status', 'invalidated'));
        $this->post($approve, ['fingerprint' => $approval->fingerprint, 'previous_approval_id' => $approval->id])
            ->assertSessionHasErrors('fingerprint');
        $this->post(route('organizations.invoices.reminder-test-delivery', [$company, $invoice]), ['approval_id' => $approval->id])
            ->assertSessionHasErrors('delivery');

        $this->patch($dispute, ['is_disputed' => '0', 'revision' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'is_disputed' => false, 'revision' => 2]);
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked']);
        $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('approval.status', 'invalidated'));
        $this->post($approve, ['fingerprint' => $approval->fingerprint, 'previous_approval_id' => $approval->id])
            ->assertSessionHasErrors('fingerprint');

        $newFingerprint = app(ReminderPreview::class)->prepare($company, $invoice->refresh()->load('customer'))['fingerprint'];
        $this->post($approve, ['fingerprint' => $newFingerprint, 'previous_approval_id' => $approval->id])->assertSessionHasNoErrors();
        $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('approval.status', 'active')->where('testDelivery', null));
    }

    public function test_dispute_updates_require_current_membership_and_invoice_revision(): void
    {
        $company = Organization::factory()->create();
        $other = Organization::factory()->create();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['role' => 'member', 'status' => 'active']);
        $invoice = $this->createInvoice($company, $company->customers()->create(['display_name' => 'Ügyfél']), '00042');
        $foreign = $this->createInvoice($other, $other->customers()->create(['display_name' => 'Másik ügyfél']), '00042');
        $url = route('organizations.invoices.dispute.update', [$company, $invoice]);
        $data = ['is_disputed' => true, 'revision' => 0];

        $this->patch($url, $data)->assertRedirect(route('login'));
        $this->actingAs($member)->patch(route('organizations.invoices.dispute.update', [$company, $foreign]), $data)->assertNotFound();
        $this->patch($url, ['is_disputed' => 'invalid'])->assertSessionHasErrorsIn('dispute-'.$invoice->id, ['is_disputed', 'revision']);
        $this->patch($url, $data)->assertSessionHasNoErrors();
        $this->patch($url, ['is_disputed' => false, 'revision' => 0])->assertSessionHasErrorsIn('dispute-'.$invoice->id, 'revision');
        $this->patch(route('organizations.invoices.balance.update', [$company, $invoice]), ['outstanding_amount' => 70_000, 'revision' => 1])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'is_disputed' => true, 'outstanding_amount' => 70_000, 'revision' => 2]);
        $this->assertFalse($foreign->refresh()->is_disputed);
    }

    public function test_worker_checks_dispute_again_before_sending(): void
    {
        $company = Organization::factory()->create(['reminders_enabled' => true]);
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['role' => 'owner', 'status' => 'active']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél']);
        $customer->forceFill(['billing_email' => 'penzugy@example.test', 'billing_email_confirmed_at' => now()])->save();
        $invoice = $this->createInvoice($company, $customer, '00042');
        $delivery = $this->approve($company, $invoice, $owner)->testDelivery()->create(['status' => 'pending']);

        $invoice->forceFill(['is_disputed' => true])->save();
        $this->mock(ReminderTestMailer::class)->shouldNotReceive('send');
        app()->call((new SendReminderTest($delivery->id))->handle(...));
        $this->assertDatabaseHas('reminder_test_deliveries', ['id' => $delivery->id, 'status' => 'blocked', 'reason' => 'invoice_disputed']);
    }

    private function createInvoice(Organization $company, Customer $customer, string $number): Invoice
    {
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => $number,
            'due_date' => today()->subWeek()->toDateString(),
            'gross_amount' => 120_000,
        ]);
        $invoice->forceFill(['outstanding_amount' => 80_000, 'status' => 'partially_paid', 'status_checked_at' => now()])->save();

        return $invoice;
    }

    private function approve(Organization $company, Invoice $invoice, User $owner): ReminderApproval
    {
        $fingerprint = app(ReminderPreview::class)->prepare($company, $invoice->refresh()->load('customer'))['fingerprint'];

        return $invoice->reminderApprovals()->create([
            'approved_by' => $owner->id,
            'fingerprint' => $fingerprint,
            'invoice_number' => $invoice->invoice_number,
            'currency' => 'HUF',
            'outstanding_amount' => 80_000,
            'template_version' => ReminderPreview::TEMPLATE_VERSION,
            'approved_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }
}
