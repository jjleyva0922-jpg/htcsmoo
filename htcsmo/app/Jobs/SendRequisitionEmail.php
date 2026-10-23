<?php

namespace App\Jobs;

use App\Mail\RequisitionAlertMail;
use App\Models\EmailLog;
use App\Models\Requisition;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendRequisitionEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public string $recipientEmail,
        public ?int $userId,
        public int $requisitionId,
        public string $subject,
        public string $headline,
        public string $message,
        public string $accentColor,
    ) {
        $this->afterCommit();
        $this->onQueue('mail');
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $requisition = Requisition::findOrFail($this->requisitionId);
        Mail::to($this->recipientEmail)->send(new RequisitionAlertMail(
            $requisition, $this->subject, $this->headline, $this->message, $this->accentColor,
        ));
        $this->log('sent');
    }

    public function failed(?Throwable $exception): void
    {
        $this->log('failed', $exception?->getMessage());
    }

    private function log(string $status, ?string $error = null): void
    {
        EmailLog::create([
            'user_id' => $this->userId,
            'recipient_email' => $this->recipientEmail,
            'subject' => $this->subject,
            'status' => $status,
            'error_message' => $error,
            'sent_at' => now(),
        ]);
    }
}
