@extends('layouts.publik')

@section('title', 'Hasil Verifikasi Kartu')
@section('label', 'Hasil Verifikasi Kartu Santri')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@push('styles')
<style>
    .status {
        display: flex; align-items: center; gap: 14px;
        padding: 20px 24px;
        border-bottom: 1px solid var(--garis);
    }
    .status .ikon {
        flex-shrink: 0;
        width: 52px; height: 52px;
        border-radius: 50%;
        display: grid; place-items: center;
    }
    .status .ikon svg { width: 28px; height: 28px; }
    .status strong { display: block; font-size: 17px; font-weight: 800; letter-spacing: .3px; line-height: 1.3; }
    .status span { font-size: 14px; color: var(--teks-2); }
    .status.valid { background: var(--hijau-muda); }
    .status.valid .ikon { background: var(--hijau); color: #fff; }
    .status.valid strong { color: var(--hijau); }
    .status.invalid { background: var(--oranye-muda); }
    .status.invalid .ikon { background: var(--oranye); color: #fff; }
    .status.invalid strong { color: var(--oranye); }
    .status.lulus { background: #e8eefd; }
    .status.lulus .ikon { background: #1d4ed8; color: #fff; }
    .status.lulus strong { color: #1d4ed8; }
    .status.notfound { background: var(--merah-muda); }

    .keterangan-status {
        display: inline-block;
        margin-top: 6px;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
    }
    .keterangan-status.aktif { background: var(--hijau-muda); color: var(--hijau); }
    .keterangan-status.lulus { background: #e8eefd; color: #1d4ed8; }
    .keterangan-status.keluar { background: var(--oranye-muda); color: var(--oranye); }
    .status.notfound .ikon { background: var(--merah); color: #fff; }
    .status.notfound strong { color: var(--merah); }

    .profil { display: flex; gap: 20px; align-items: flex-start; }
    .foto {
        width: 104px; height: 138px;
        border-radius: 14px;
        object-fit: cover;
        background: var(--hijau-muda);
        border: 3px solid #fff;
        box-shadow: 0 4px 14px rgba(6,78,42,.15);
        flex-shrink: 0;
        display: grid; place-items: center;
        color: var(--hijau);
    }
    .foto svg { width: 44px; height: 44px; opacity: .5; }
    dl { margin: 0; flex: 1; min-width: 0; }
    dt { color: var(--teks-2); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .6px; }
    dd { margin: 2px 0 12px; font-weight: 700; font-size: 15px; word-wrap: break-word; }
    dd:last-child { margin-bottom: 0; }

    .waktu {
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px dashed var(--garis);
        font-size: 13px;
        color: var(--teks-2);
        text-align: center;
    }
    .aksi { display: grid; gap: 10px; margin-top: 16px; }

    @media (max-width: 380px) {
        .profil { flex-direction: column; align-items: center; text-align: center; }
    }
</style>
@endpush

@section('content')
    <section class="card">
        @if (! $santri)
            <div class="status notfound">
                <div class="ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></div>
                <div>
                    <strong>KARTU TIDAK DITEMUKAN</strong>
                    <span>Kode kartu tidak terdaftar di sistem pondok. Kartu ini kemungkinan tidak asli.</span>
                </div>
            </div>
        @else
            {{-- Kartu terdaftar = kartu asli. Statusnya: aktif, lulus, atau keluar. --}}
            @php
                $status = $santri->status->value;
                $tanggalKeluar = $santri->tanggal_keluar?->translatedFormat('d F Y');
                [$kelas, $judul, $keterangan] = match ($status) {
                    'lulus' => [
                        'lulus',
                        'KARTU ASLI &ndash; SANTRI TELAH LULUS',
                        'Kartu terdaftar resmi. Pemilik kartu adalah alumni yang telah lulus dari pondok'.($tanggalKeluar ? " pada {$tanggalKeluar}" : '').'.',
                    ],
                    'keluar' => [
                        'invalid',
                        'KARTU ASLI &ndash; SANTRI SUDAH KELUAR',
                        'Kartu terdaftar resmi, tetapi pemilik kartu sudah keluar dari pondok'.($tanggalKeluar ? " sejak {$tanggalKeluar}" : '').' dan bukan lagi santri aktif.',
                    ],
                    default => [
                        'valid',
                        'KARTU ASLI &ndash; SANTRI AKTIF',
                        'Kartu terdaftar resmi dan santri masih aktif di pondok.',
                    ],
                };
            @endphp
            <div class="status {{ $kelas }}">
                <div class="ikon">
                    @if ($status === 'keluar')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg>
                    @elseif ($status === 'lulus')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                    @endif
                </div>
                <div>
                    <strong>{!! $judul !!}</strong>
                    <span>{{ $keterangan }}</span>
                </div>
            </div>
        @endif

        <div class="card-body">
            @if ($santri)
                <div class="profil">
                    @if ($santri->foto_profil)
                        <img class="foto" src="{{ url('/kartu/'.$token.'/foto') }}" alt="Foto {{ $santri->nama_lengkap }}">
                    @else
                        <div class="foto" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>
                        </div>
                    @endif
                    <dl>
                        <dt>Nama</dt>
                        <dd>{{ $santri->nama_lengkap }}</dd>
                        <dt>NIS</dt>
                        <dd>{{ $santri->nis }}</dd>
                        <dt>Tempat, tanggal lahir</dt>
                        <dd>{{ $santri->tempat_lahir }}, {{ $santri->tanggal_lahir->translatedFormat('d F Y') }}</dd>
                        <dt>Asal</dt>
                        <dd>{{ $santri->kabupaten }}, {{ $santri->provinsi }}</dd>
                        <dt>Status</dt>
                        <dd>
                            <span class="keterangan-status {{ $santri->status->value }}">
                                {{ match ($santri->status->value) { 'lulus' => 'Alumni / Lulus', 'keluar' => 'Sudah keluar', default => 'Santri aktif' } }}
                            </span>
                        </dd>
                    </dl>
                </div>
            @else
                <p>Pastikan Anda memindai kode QR langsung dari kartu santri. Jika ragu, hubungi pengurus pondok untuk konfirmasi.</p>
            @endif

            <div class="waktu">Diperiksa pada {{ now()->translatedFormat('l, d F Y \p\u\k\u\l H:i') }} WIB</div>
        </div>
    </section>

    <div class="aksi">
        <a class="btn btn-emas" href="{{ $pondok['website_pondok'] }}" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            Website Resmi Pondok
        </a>
        <a class="btn btn-garis" href="{{ url('/') }}">Tentang Verifikasi Kartu</a>
    </div>
@endsection
