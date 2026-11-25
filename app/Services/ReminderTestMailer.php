<?php

namespace App\Services;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class ReminderTestMailer
{
    public function enabled(): bool
    {
        return app()->environment('local', 'testing', 'closed-pilot');
    }

    public function accepts(string $recipient): bool
    {
        return $this->enabled() && str_ends_with(strtolower($recipient), '.test');
    }

    public function send(string $id, string $recipient, string $subject, string $body): bool
    {
        if (! $this->accepts($recipient)) {
            return false;
        }

        return Mail::mailer('reminder-test')->raw($body, static function (Message $message) use ($id, $recipient, $subject): void {
            $message->from('reminders@munkacash.test', 'MunkaCash');
            $message->to($recipient);
            $message->subject($subject);
            $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $id.'@munkacash.test');
        }) !== null;
    }
}
