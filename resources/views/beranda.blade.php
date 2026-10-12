@extends('layouts.publik')

@section('title', 'Verifikasi Kartu Santri')
@section('label', 'Verifikasi Kartu Santri')

@push('styles')
<style>
    .ikon-besar {
        width: 56px; height: 56px;
        border-radius: 16px;
        background: var(--hijau-muda);
        color: var(--hijau);
        display: grid; place-items: center;
        margin-bottom: 16px;
    }
    .ikon-besar svg { width: 30px; height: 30px; }

    .langkah { list-style: none; padding: 0; margin: 20px 0 0; display: grid; gap: 14px; }
    .langkah li { display: flex; gap: 14px; align-items: flex-start; }
    .langkah .no {
        flex-shrink: 0;
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--hijau);
        color: #fff;
        font-weight: 700; font-size: 14px;
        display: grid; place-items: center;
    }
    .langkah strong { display: block; font-size: 15px; }
    .langkah span { color: var(--teks-2); font-size: 14px; }

    .catatan {
        display: flex; gap: 12px;
        margin-top: 20px;
        padding: 14px 16px;
        border-radius: 14px;
        background: var(--emas-muda);
        border: 1px solid #f5e08a;
        font-size: 14px;
        color: #6b5500;
    }
    .catatan svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 2px; }

    .web { text-align: center; }
    .web h2 { margin-bottom: 4px; }
    .web p { margin-bottom: 18px; }
</style>
@endpush

@section('content')
    <section class="card">
        <div class="card-body">
            <div class="ikon-besar" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <h2>Sistem Pengecekan Keaslian Kartu Santri</h2>
            <p>
                Sistem ini digunakan untuk memeriksa keaslian kartu santri
                <strong>{{ $pondok['nama_pondok'] }}</strong>.
                Setiap kartu santri resmi memiliki kode QR yang terhubung langsung ke data pondok.
            </p>

            <ol class="langkah">
                <li>
                    <div class="no">1</div>
                    <div>
                        <strong>Pindai kode QR</strong>
                        <span>Arahkan kamera HP ke kode QR yang ada di kartu santri.</span>
                    </div>
                </li>
                <li>
                    <div class="no">2</div>
                    <div>
                        <strong>Buka tautan yang muncul</strong>
                        <span>Halaman verifikasi akan terbuka otomatis di situs ini.</span>
                    </div>
                </li>
                <li>
                    <div class="no">3</div>
                    <div>
                        <strong>Lihat hasil pemeriksaan</strong>
                        <span>Ditampilkan nama, NIS, foto, dan status keaktifan santri.</span>
                    </div>
                </li>
            </ol>

            <div class="catatan">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <div>Kartu dinyatakan asli hanya jika halaman verifikasi berada di alamat <strong>{{ request()->getHost() }}</strong> dan menampilkan status <strong>Santri Aktif</strong>.</div>
            </div>
        </div>
    </section>

    <section class="card web">
        <div class="card-body">
            <h2>Website Resmi Pondok</h2>
            <p>Informasi lengkap tentang {{ $pondok['nama_pondok'] }} dapat diakses melalui website resmi.</p>
            <a class="btn btn-emas" href="{{ $pondok['website_pondok'] }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Kunjungi {{ preg_replace('#^https?://#', '', rtrim($pondok['website_pondok'], '/')) }}
            </a>
        </div>
    </section>
@endsection
