<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(public ServiceRequest $job) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'invitation',
            'title'      => 'A client invited you',
            'body'       => "You've been invited to a {$this->job->servicesSummary()} job in {$this->job->city}. Open your invitations to accept.",
            'booking_id' => $this->job->id,
        ];
    }
}
