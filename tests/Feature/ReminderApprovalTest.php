<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ReminderApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReminderApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_approves_the_displayed_draft_once_without_dispatching_mail(): void
    {
        Mail::fake();
        Queue::fake();
        [$company, $owner, $invoice] = $this->makeDraft();
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);
        $this->actingAs($owner);

        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('canApprove', true)
            ->where('gross', 120_000)
            ->where('outstanding', 80_000)
            ->where('approval', null));
        $payload = $this->shownDraft($preview);

        $this->post($approve, $payload)->assertRedirect($preview);
        $this->travel(5)->minutes();
        $this->post($approve, $payload)->assertRedirect($preview);

        $approval = $invoice->reminderApprovals()->sole();
        $this->assertDatabaseHas('reminder_approvals', [
            'id' => $approval->id,
            'approved_by' => $owner->id,
            'invoice_number' => '00042',
            'outstanding_amount' => 80_000,
            'currency' => 'HUF',
            'template_version' => 'v1',
        ]);
        $this->assertTrue($approval->approved_at->equalTo(now()->subMinutes(5)));
        $this->assertTrue($approval->expires_at->equalTo(now()->addMinutes(25)));

        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('approval.status', 'active')
            ->where('approval.approvedBy', $owner->name));

        Mail::assertNothingOutgoing();
        Queue::assertNothingPushed();
    }

    public function test_approval_rejects_a_malformed_fingerprint_and_missing_or_invalid_previous_id(): void
    {
        [$company, $owner, $invoice] = $this->makeDraft();
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);

        $this->actingAs($owner)->post($approve, ['fingerprint' => ['bad']])
            ->assertSessionHasErrors(['fingerprint', 'previous_approval_id']);
        $this->post($approve, ['fingerprint' => str_repeat('a', 64), 'previous_approval_id' => 0])
            ->assertSessionHasErrors('previous_approval_id');
        $this->assertDatabaseCount('reminder_approvals', 0);
    }

    public function test_stale_preview_is_rejected_and_a_changed_balance_invalidates_an_existing_approval(): void
    {
        [$company, $owner, $invoice] = $this->makeDraft();
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);
        $balance = route('organizations.invoices.balance.update', [$company, $invoice]);
        $this->actingAs($owner);
        $initial = $this->shownDraft($preview);

        $this->patch($balance, ['outstanding_amount' => 70_000, 'revision' => 0])->assertSessionHasNoErrors();
        $this->post($approve, $initial)->assertSessionHasErrors('fingerprint');
        $this->assertDatabaseCount('reminder_approvals', 0);

        $this->post($approve, $this->shownDraft($preview))->assertSessionHasNoErrors();
        $this->patch($balance, ['outstanding_amount' => 0, 'revision' => 1])->assertSessionHasNoErrors();

        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('approval.status', 'invalidated')
            ->where('message', null)
            ->where('fingerprint', null));
        $this->assertDatabaseCount('reminder_approvals', 1);
    }

    public function test_expired_approval_requires_a_fresh_preview_and_old_rows_are_pruned(): void
    {
        [$company, $owner, $invoice] = $this->makeDraft();
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);
        $this->actingAs($owner);
        $payload = $this->shownDraft($preview);

        $this->post($approve, $payload)->assertSessionHasNoErrors();
        $this->travel(30)->minutes();
        $this->get($preview)->assertInertia(fn (Assert $page) => $page->where('approval.status', 'expired'));
        $this->post($approve, $payload)->assertSessionHasErrors('fingerprint');
        $this->post($approve, $this->shownDraft($preview))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reminder_approvals', 2);

        $this->travel(90)->days();
        $this->artisan('model:prune', ['--model' => [ReminderApproval::class]])->assertSuccessful();
        $this->assertDatabaseCount('reminder_approvals', 0);
    }

    public function test_recipient_change_and_owner_revocation_invalidate_approval(): void
    {
        [$company, $owner, $invoice] = $this->makeDraft();
        $anotherOwner = User::factory()->create();
        $anotherOwner->organizations()->attach($company, ['status' => 'active', 'role' => 'owner']);
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);
        $this->actingAs($owner);
        $this->post($approve, $this->shownDraft($preview))->assertSessionHasNoErrors();

        $contact = [$company, $invoice->customer];
        $this->patch(route('organizations.customers.billing-contact.update', $contact), [
            'revision' => 0,
            'billing_email' => 'new-billing@example.test',
        ])->assertSessionHasNoErrors();
        $this->post(route('organizations.customers.billing-contact.confirm', $contact), ['revision' => 1])
            ->assertSessionHasNoErrors();

        $this->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('message.recipient', 'new-billing@example.test')
            ->where('approval.status', 'invalidated'));

        $this->post($approve, $this->shownDraft($preview))->assertSessionHasNoErrors();
        $owner->organizations()->updateExistingPivot($company->id, ['status' => 'inactive']);
        $this->actingAs($anotherOwner)->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('approval.status', 'invalidated'));
    }

    public function test_members_cannot_approve_and_invoices_are_scoped_to_the_organization(): void
    {
        [$company, $owner, $invoice] = $this->makeDraft();
        [$other, , $foreignInvoice] = $this->makeDraft();
        $member = User::factory()->create();
        $member->organizations()->attach($company, ['status' => 'active', 'role' => 'member']);
        $preview = route('organizations.invoices.reminder-preview', [$company, $invoice]);
        $approve = route('organizations.invoices.reminder-approval', [$company, $invoice]);
        $payload = $this->actingAs($owner)->shownDraft($preview);

        $this->actingAs($member)->get($preview)->assertInertia(fn (Assert $page) => $page
            ->where('canApprove', false)
            ->where('fingerprint', null));
        $this->post($approve, $payload)->assertForbidden();
        $this->actingAs($owner)->post(route('organizations.invoices.reminder-approval', [$company, $foreignInvoice]), $payload)
            ->assertNotFound();
        $this->post(route('organizations.invoices.reminder-approval', [$other, $foreignInvoice]), $payload)
            ->assertNotFound();
        $this->assertDatabaseCount('reminder_approvals', 0);
    }

    /** @return array{fingerprint: mixed, previous_approval_id: mixed} */
    private function shownDraft(string $url): array
    {
        $response = $this->get($url)->assertOk();

        return [
            'fingerprint' => $response->inertiaProps('fingerprint'),
            'previous_approval_id' => $response->inertiaProps('approval.id'),
        ];
    }

    /** @return array{Organization, User, Invoice} */
    private function makeDraft(): array
    {
        $this->travelTo(Carbon::parse('2026-12-02 10:00:00', 'Europe/Budapest'));
        $company = Organization::factory()->create();
        $owner = User::factory()->create();
        $owner->organizations()->attach($company, ['status' => 'active', 'role' => 'owner']);
        $customer = $company->customers()->create(['display_name' => 'Minta Ügyfél Kft.']);
        $customer->forceFill([
            'billing_email' => 'billing@example.test',
            'billing_email_confirmed_at' => now(),
            'billing_email_confirmed_by' => $owner->id,
        ])->save();
        $invoice = $company->invoices()->create([
            'customer_id' => $customer->id,
            'invoice_number' => '00042',
            'due_date' => '2026-12-01',
            'gross_amount' => 120_000,
        ]);
        $invoice->forceFill([
            'status' => 'partially_paid',
            'outstanding_amount' => 80_000,
            'status_checked_at' => now(),
            'checked_by' => $owner->id,
        ])->save();

        return [$company, $owner, $invoice];
    }
}
