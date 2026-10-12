<?php

namespace App\Notifications\Channels;

use App\Services\FcmService;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private FcmService $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = $notifiable->routeNotificationFor('fcm', $notification) ?? [];

        if ($tokens === []) {
            return;
        }

        $message = $notification->toArray($notifiable);

        $this->fcm->send(
            $tokens,
            $message['judul'],
            $message['isi'],
            ['tipe' => $message['tipe'], 'ref_id' => (string) ($message['ref_id'] ?? '')],
        );
    }
}
