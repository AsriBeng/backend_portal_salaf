<?php

namespace App\Notifications;

use App\Models\Pengumuman;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PengumumanBaru extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Pengumuman $pengumuman) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipe' => 'pengumuman',
            'ref_id' => $this->pengumuman->id,
            'judul' => 'Pengumuman: '.$this->pengumuman->judul,
            'isi' => Str::limit(strip_tags($this->pengumuman->isi), 120),
        ];
    }
}
