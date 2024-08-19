<?php

namespace App\Notifications;

use App\Models\BookingItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSlipUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public BookingItem $bookingItem)
    {
        //
    }

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
        $crm_id = $this->bookingItem->crm_id;
        $main_text = "A payment slip is updated for the reservation ($crm_id). You can notify the customer now.";

        $url = 'https://sales-admin.thanywhere.com/reservation/update/' . $this->bookingItem->id . '/' . $this->bookingItem->crm_id;

        return (new MailMessage)
            ->line($main_text)
            ->action('Click to check reservation', $url)
            ->line('Thank you for using our application!');
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
