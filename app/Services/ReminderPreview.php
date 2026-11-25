<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;

class ReminderPreview
{
    public const string TEMPLATE_VERSION = 'v1';

    public function __construct(private readonly HungarianWorkCalendar $calendar) {}

    /** @return array{effectiveDueDate: string|null, blockers: list<string>, message: array{recipient: string, subject: string, body: string}|null, fingerprint: string|null} */
    public function prepare(Organization $company, Invoice $invoice): array
    {
        $originalDueDate = $invoice->due_date->toDateString();
        $effectiveDueDate = $this->calendar->effectiveDeadline($invoice->due_date)?->toDateString();
        $blockers = $this->blockers($invoice, $effectiveDueDate);
        $recipient = $invoice->customer->billing_email;
        $outstanding = $invoice->outstanding_amount;

        $message = $blockers === [] && $recipient !== null && $outstanding !== null
            ? $this->render($company, $invoice, $recipient, $outstanding)
            : null;

        return [
            'effectiveDueDate' => $effectiveDueDate,
            'blockers' => $blockers,
            'message' => $message,
            'fingerprint' => $message === null ? null : hash_hmac('sha256', json_encode([
                'reminder-approval',
                self::TEMPLATE_VERSION,
                $company->id,
                $invoice->id,
                $invoice->revision,
                $invoice->status->value,
                $originalDueDate,
                $invoice->currency,
                $invoice->gross_amount,
                $invoice->outstanding_amount,
                $invoice->status_checked_at?->toISOString(),
                $invoice->customer_id,
                $invoice->customer->billing_email_revision,
                $message,
                // Preserve existing approvals when the calendar leaves the deadline unchanged.
                ...($effectiveDueDate === $originalDueDate ? [] : [$effectiveDueDate]),
            ], JSON_THROW_ON_ERROR), Config::string('app.key')),
        ];
    }

    /** @return list<string> */
    private function blockers(Invoice $invoice, ?string $effectiveDueDate): array
    {
        $messages = [];

        if ($invoice->is_disputed) {
            $messages[] = 'A számla vitatott. Emlékeztető csak a vita feloldása után hagyható jóvá.';
        }

        if ($effectiveDueDate === null) {
            $messages[] = 'A fizetési határidőhöz még nincs ellenőrzött munkanaptár.';
        } elseif ($effectiveDueDate >= today(HungarianWorkCalendar::TIMEZONE)->toDateString()) {
            $messages[] = 'A számla fizetési határideje még nem járt le.';
        }

        if ($invoice->status === InvoiceStatus::Paid || $invoice->outstanding_amount === 0) {
            $messages[] = 'A számla teljesen rendezett.';
        } elseif (
            ! in_array($invoice->status, [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid], true)
            || $invoice->status_checked_at === null
            || $invoice->outstanding_amount === null
            || $invoice->outstanding_amount < 0
            || $invoice->outstanding_amount > $invoice->gross_amount
        ) {
            $messages[] = 'Erősítsd meg a fennálló összeget a Számlák oldalon.';
        }

        if ($invoice->customer->billing_email === null || $invoice->customer->billing_email_confirmed_at === null) {
            $messages[] = 'Rögzítsd és erősítsd meg a pénzügyi címzettet.';
        }

        return $messages;
    }

    /** @return array{recipient: string, subject: string, body: string} */
    private function render(Organization $company, Invoice $invoice, string $recipient, int $outstanding): array
    {
        $amount = number_format($outstanding, 0, ',', ' ')."\u{00A0}Ft";
        $number = $this->singleLine($invoice->invoice_number);
        $companyName = $this->singleLine($company->name);

        return [
            'recipient' => $recipient,
            'subject' => 'Fizetési emlékeztető',
            'body' => <<<TEXT
                Tisztelt Ügyfelünk!

                Nyilvántartásunk szerint a(z) {$number} számú számla fennálló összege {$amount}.

                Kérjük, ellenőrizze a számla állapotát. Ha időközben rendezte, vagy eltérést tapasztal, kérjük, jelezze a megszokott ügyintézési csatornán.

                Üdvözlettel
                {$companyName}

                Adatkezelő: {$companyName}
                A cím forrása: a vállalkozás által rögzített pénzügyi kapcsolattartói adat.
                Adatkezelési kérdéssel a vállalkozás meglévő ügyintézési elérhetőségén fordulhat hozzánk.
                TEXT,
        ];
    }

    private function singleLine(string $value): string
    {
        return trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? $value);
    }
}
