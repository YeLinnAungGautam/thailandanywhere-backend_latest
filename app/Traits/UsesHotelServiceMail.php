<?php

namespace App\Traits;

use Illuminate\Support\Facades\Config;

trait UsesHotelServiceMail
{
    /**
     * Send the message using the given mailer.
     *
     * @param  \Illuminate\Contracts\Mail\Factory|\Illuminate\Contracts\Mail\Mailer  $mailer
     * @return \Illuminate\Mail\SentMessage|null
     */
    public function send($mailer)
    {
        // 1. Guarantee the configuration is set right before resolving the mailer
        $this->configureHotelServiceMailer();

        // 2. We MUST explicitly resolve our new 'hotel_service' mailer instance instead of
        // relying on the passed $mailer. When a user uses `Mail::to(...)->send(...)`,
        // Laravel passes the instantiated *default* mailer into this method, 
        // completely bypassing Mailable's internal `$this->mailer` resolution logic.
        $hotelMailer = app(\Illuminate\Contracts\Mail\Factory::class)->mailer('hotel_service');

        // 3. Send the email pushing our explicit mailer to the parent
        $result = parent::send($hotelMailer);

        // 4. Fetch and save the newly sent email to DB immediately
        sleep(2);
        \Illuminate\Support\Facades\Artisan::call('gmail:sync', ['max' => 5]);

        return $result;
    }

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
