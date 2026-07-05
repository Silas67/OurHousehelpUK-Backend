<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;

class JobAcceptedNotification extends Notification
{
    public function __construct(
        public ServiceRequest $job,
        public User $staff
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'        => 'job_accepted',
            'title'       => 'New staff interested',
            'body'        => "{$this->staff->name} has accepted your {$this->job->servicesSummary()} job.",
            'booking_id'  => $this->job->id,
            'staff_id'    => $this->staff->id,
            'staff_name'  => $this->staff->name,
        ];
    }
}
