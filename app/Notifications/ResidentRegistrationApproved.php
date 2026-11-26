<?php

namespace App\Notifications;

use App\Models\Resident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResidentRegistrationApproved extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected Resident $resident
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $household = $this->resident->household;

        return (new MailMessage)
            ->subject('BOAConnect - Resident Registration Approved')
            ->greeting('Hello ' . $this->resident->full_name . '!')
            ->line('Good news! Your resident registration has been reviewed and approved by your Barangay Secretary.')
            ->line('Your resident account is now verified.')
            ->line('Your household information is as follows:')
            ->line('Household Number: ' . ($household?->household_no ?? 'Not available'))
            ->line(
                'Household Role: ' .
                ($this->resident->is_household_head
                    ? 'Household Head'
                    : 'Household Member')
            )
            ->line('You may now access the official resident services available in BOAConnect.')
            ->line('Thank you for using BOAConnect.')
            ->salutation('BOAConnect Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
