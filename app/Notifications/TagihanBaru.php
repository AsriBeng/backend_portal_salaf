<?php

namespace App\Notifications;

use App\Models\Tagihan;
use App\Notifications\Channels\FcmChannel;
use App\Support\Rupiah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TagihanBaru extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Tagihan $tagihan) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        // Sebutkan juga total tagihan yang belum lunas supaya tunggakan lama tidak terlupa
        $belumLunas = $this->tagihan->santri->tagihan()->belumLunas()->get();
        $total = $belumLunas->sum(fn (Tagihan $t) => $t->sisa());

        $isi = "Tagihan {$this->tagihan->judul} sebesar ".Rupiah::format($this->tagihan->nominal).' telah terbit.';

        if ($belumLunas->count() > 1) {
            $isi .= ' Total tagihan belum lunas: '.Rupiah::format($total)." ({$belumLunas->count()} tagihan).";
        }

        return [
            'tipe' => 'tagihan',
            'ref_id' => $this->tagihan->id,
            'judul' => 'Tagihan baru',
            'isi' => $isi,
        ];
    }
}
