<?php

namespace App\Notifications;

use App\Enums\PembayaranStatus;
use App\Models\Pembayaran;
use App\Notifications\Channels\FcmChannel;
use App\Support\Rupiah;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PembayaranDiverifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Pembayaran $pembayaran) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $p = $this->pembayaran;
        $nominal = Rupiah::format($p->nominal);
        $judulTagihan = $p->tagihan->judul;

        [$judul, $isi] = $p->status === PembayaranStatus::Disetujui
            ? ['Pembayaran diterima', "Pembayaran {$judulTagihan} sebesar {$nominal} telah diterima. Sisa tagihan: ".Rupiah::format($p->tagihan->sisa()).'.']
            : ['Pembayaran ditolak', "Pembayaran {$judulTagihan} sebesar {$nominal} ditolak. Alasan: {$p->alasan_tolak}"];

        return [
            'tipe' => 'pembayaran',
            'ref_id' => $p->id,
            'judul' => $judul,
            'isi' => $isi,
        ];
    }
}
