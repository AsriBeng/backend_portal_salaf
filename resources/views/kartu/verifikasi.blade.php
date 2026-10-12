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
    .status.notfound { background: var(--merah-muda); }
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
        @elseif ($valid)
            <div class="status valid">
                <div class="ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg></div>
                <div>
                    <strong>KARTU ASLI &ndash; SANTRI AKTIF</strong>
                    <span>Kartu terdaftar resmi dan santri masih aktif di pondok.</span>
                </div>
            </div>
        @else
            <div class="status invalid">
                <div class="ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 8v5M12 17h.01"/><circle cx="12" cy="12" r="10"/></svg></div>
                <div>
                    <strong>KARTU TIDAK BERLAKU</strong>
                    <span>Kartu pernah terdaftar, tetapi santri sudah tidak aktif di pondok.</span>
                </div>
            </div>
        @endif

        <div class="card-body">
            @if ($santri)
                <div class="profil">
                    @if ($santri->foto_profil && $valid)
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
                        @if ($valid)
                            <dt>Tempat, tanggal lahir</dt>
                            <dd>{{ $santri->tempat_lahir }}, {{ $santri->tanggal_lahir->translatedFormat('d F Y') }}</dd>
                            <dt>Asal</dt>
                            <dd>{{ $santri->kabupaten }}, {{ $santri->provinsi }}</dd>
                        @endif
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
