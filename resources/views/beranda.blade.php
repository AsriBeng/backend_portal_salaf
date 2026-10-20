@extends('layouts.publik')

@section('title', 'Selamat Datang di Portal Salaf')
@section('label', 'Portal Salaf')

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

    .sambutan { text-align: center; }
    .sambutan .ikon-besar { margin: 0 auto 16px; }
    .sambutan p { margin-bottom: 20px; }
    .fitur { list-style: none; padding: 0; margin: 0 0 22px; display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
    .fitur li {
        font-size: 13px; font-weight: 600;
        color: var(--hijau);
        background: var(--hijau-muda);
        padding: 5px 12px;
        border-radius: 999px;
    }
    .pasang {
        margin-top: 16px;
        font-size: 13px;
        color: var(--teks-2);
        text-align: left;
        border-top: 1px dashed var(--garis);
        padding-top: 14px;
    }
    .pasang summary { cursor: pointer; font-weight: 700; color: var(--hijau); list-style: none; text-align: center; }
    .pasang summary::-webkit-details-marker { display: none; }
    .pasang ol { margin: 10px 0 0; padding-left: 20px; }
    .pasang li + li { margin-top: 4px; }

    .web { text-align: center; }
    .web h2 { margin-bottom: 4px; }
    .web p { margin-bottom: 18px; }
</style>
@endpush

@section('content')
    <section class="card sambutan">
        <div class="card-body">
            <div class="ikon-besar" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <h2>Selamat Datang di Portal Salaf</h2>
            <p>
                Portal santri {{ $pondok['nama_pondok'] }} untuk santri, wali santri, dan pengurus.
                Bisa dibuka langsung dari browser di HP Android, iPhone, maupun komputer.
            </p>

            <ul class="fitur">
                <li>Data Santri</li>
                <li>Kartu Santri</li>
                <li>Tagihan &amp; Pembayaran</li>
                <li>Pengumuman</li>
            </ul>

            <a class="btn btn-emas" href="{{ url('/') }}/app/">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/></svg>
                Masuk Portal Salaf
            </a>

            <details class="pasang">
                <summary>Pasang di layar utama HP</summary>
                <ol>
                    <li><strong>iPhone (Safari):</strong> buka Portal Salaf, ketuk tombol Bagikan, lalu pilih <em>Tambahkan ke Layar Utama</em>.</li>
                    <li><strong>Android (Chrome):</strong> buka Portal Salaf, ketuk menu titik tiga, lalu pilih <em>Tambahkan ke layar utama</em>.</li>
                </ol>
            </details>
        </div>
    </section>

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
            <a class="btn btn-garis" href="{{ $pondok['website_pondok'] }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Kunjungi {{ preg_replace('#^https?://#', '', rtrim($pondok['website_pondok'], '/')) }}
            </a>
        </div>
    </section>
@endsection
