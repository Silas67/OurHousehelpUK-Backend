<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Notification;

class JobFilledNotification extends Notification
{
    public function __construct(public ServiceRequest $job) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'job_filled',
            'title'      => 'Job no longer available',
            'body'       => "The {$this->job->servicesSummary()} job in {$this->job->city} has been filled by another candidate.",
            'booking_id' => $this->job->id,
        ];
    }
}
