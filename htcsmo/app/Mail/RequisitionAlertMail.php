<?php

namespace App\Mail;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequisitionAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $requisition;
    public $emailSubject;
    public $headline;
    public $actionMessage;
    public $accentColor;

    /**
     * Create a new message instance.
     */
    public function __construct(Requisition $requisition, string $emailSubject, string $headline, string $actionMessage, string $accentColor = '#185b3b')
    {
        $this->requisition = $requisition;
        $this->emailSubject = $emailSubject;
        $this->headline = $headline;
        $this->actionMessage = $actionMessage;
        $this->accentColor = $accentColor;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "HTC SMO Alert: {$this->emailSubject} [{$this->requisition->tracking_code}]",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.requisition-alert',
        );
    }
}
