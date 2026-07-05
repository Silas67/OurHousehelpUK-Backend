<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Notification;

class StaffConfirmedNotification extends Notification
{
    public function __construct(public ServiceRequest $job) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $client = $this->job->client;

        return [
            'type'           => 'staff_confirmed',
            'title'          => 'You got the job!',
            'body'           => "You have been selected for the {$this->job->servicesSummary()} job in {$this->job->city}. Contact {$client->name} to arrange the start date.",
            'booking_id'     => $this->job->id,
            'client_name'    => $client->name,
            'client_phone'   => $client->phone,
            'client_email'   => $client->email,
            'client_address' => trim(implode(', ', array_filter([
                $this->job->address_line_1,
                $this->job->address_line_2,
                $this->job->city,
                $this->job->postcode,
            ]))),
        ];
    }
}
