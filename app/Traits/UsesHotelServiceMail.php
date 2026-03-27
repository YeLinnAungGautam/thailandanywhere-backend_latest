<?php

namespace App\Traits;

use Illuminate\Support\Facades\Config;

trait UsesHotelServiceMail
{
    /**
     * Prepare the mailable for delivery and configure the hotel service mailer.
     *
     * @return void
     */
    protected function prepareMailableForDelivery()
    {
        $this->configureHotelServiceMailer();

        // Hydrates all data (envelope, content, etc.)
        parent::prepareMailableForDelivery();

        // Dynamically override from address for this mailable
        if (config('hotel_service_mail.from.address')) {
            $this->from = []; // Clear existing from addresses
            $this->from(
                config('hotel_service_mail.from.address'),
                config('hotel_service_mail.from.name')
            );
        }
    }

    /**
     * Set up the dynamic hotel service mailer configuration.
     *
     * @return void
     */
    protected function configureHotelServiceMailer()
    {
        $configKey = 'mail.mailers.hotel_service';

        // Set the mailer configuration dynamically using the specified config variables
        Config::set($configKey, [
            'transport' => config('hotel_service_mail.driver', config('mail.default', 'smtp')),
            'host' => config('hotel_service_mail.host'),
            'port' => config('hotel_service_mail.port'),
            'encryption' => config('hotel_service_mail.encryption'),
            'username' => config('hotel_service_mail.username'),
            'password' => config('hotel_service_mail.password'),
            'timeout' => null,
        ]);

        // Instruct the mailable to use the newly configured mailer
        $this->mailer = 'hotel_service';
    }
}
