<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b6b3a">
    @stack('meta')
    <title>@yield('title') - {{ $pondok['nama_pondok'] }}</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --hijau-tua: #064e2a;
            --hijau: #0b6b3a;
            --hijau-muda: #e8f5ee;
            --emas: #f2c300;
            --emas-muda: #fff8d6;
            --merah: #c62828;
            --merah-muda: #fdecea;
            --oranye: #b45309;
            --oranye-muda: #fff4e5;
            --teks: #14231b;
            --teks-2: #5b6b62;
            --garis: #e3ebe6;
            --latar: #f4f7f5;
            --kartu: #ffffff;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: "Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--latar);
            color: var(--teks);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }

        /* Hero hijau dengan pola */
        .hero {
            position: relative;
            background:
                radial-gradient(circle at 15% 20%, rgba(242,195,0,.18) 0, transparent 35%),
                radial-gradient(circle at 85% 0%, rgba(255,255,255,.10) 0, transparent 40%),
                linear-gradient(160deg, var(--hijau) 0%, var(--hijau-tua) 100%);
            color: #fff;
            padding: 40px 16px 120px;
            text-align: center;
            overflow: hidden;
        }
        .hero::after {
            content: "";
            position: absolute; inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='48' height='48' viewBox='0 0 48 48'%3E%3Cpath d='M24 4l5 15h15l-12 9 5 15-13-9-13 9 5-15-12-9h15z' fill='none' stroke='%23ffffff' stroke-opacity='.06'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        .hero > * { position: relative; z-index: 1; }
        .logo {
            display: block;
            margin: 0 auto;
            width: 112px; height: 112px;
            background: #fff;
            border-radius: 50%;
            padding: 10px;
            box-shadow: 0 0 0 6px rgba(242,195,0,.35), 0 12px 32px rgba(0,0,0,.25);
            object-fit: contain;
        }
        .hero .label {
            display: inline-block;
            margin-top: 20px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--emas);
            background: rgba(0,0,0,.18);
            border: 1px solid rgba(242,195,0,.35);
            padding: 4px 12px;
            border-radius: 999px;
        }
        .hero h1 {
            font-size: clamp(22px, 5vw, 32px);
            line-height: 1.25;
            font-weight: 800;
            margin: 12px auto 6px;
            max-width: 640px;
        }
        .hero p.alamat { margin: 0; opacity: .85; font-size: 14px; }

        main {
            flex: 1;
            width: 100%;
            max-width: 560px;
            margin: -88px auto 0;
            padding: 0 16px 32px;
            position: relative;
            z-index: 2;
        }
        .card {
            background: var(--kartu);
            border-radius: 20px;
            box-shadow: 0 1px 2px rgba(6,78,42,.06), 0 12px 32px rgba(6,78,42,.10);
            overflow: hidden;
        }
        .card + .card { margin-top: 16px; }
        .card-body { padding: 24px; }
        .card h2 { font-size: 18px; margin: 0 0 8px; line-height: 1.35; }
        .card p { margin: 0; color: var(--teks-2); font-size: 15px; }
        .card p + p { margin-top: 10px; }

        .btn {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%;
            padding: 14px 20px;
            border-radius: 14px;
            font-weight: 700; font-size: 15px;
            text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .btn:active { transform: scale(.98); }
        .btn-emas { background: var(--emas); color: var(--hijau-tua); box-shadow: 0 6px 16px rgba(242,195,0,.35); }
        .btn-emas:hover { background: #ffd21a; }
        .btn-garis { border: 1.5px solid var(--garis); color: var(--hijau); background: #fff; }
        .btn-garis:hover { background: var(--hijau-muda); }
        .btn svg { width: 18px; height: 18px; flex-shrink: 0; }

        footer {
            text-align: center;
            font-size: 13px;
            color: var(--teks-2);
            padding: 8px 16px 28px;
        }
        footer strong { color: var(--hijau); }

        @media (min-width: 640px) {
            .hero { padding-top: 56px; }
            .card-body { padding: 28px 32px; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <header class="hero">
        <img class="logo" src="{{ url('/logo') }}" alt="Logo {{ $pondok['nama_pondok'] }}" width="112" height="112">
        <div class="label">@yield('label')</div>
        <h1>{{ $pondok['nama_pondok'] }}</h1>
        @if ($pondok['alamat_pondok'])
            <p class="alamat">{{ $pondok['alamat_pondok'] }}</p>
        @endif
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        &copy; {{ now()->year }} <strong>{{ $pondok['nama_pondok'] }}</strong><br>
        Sistem Portal Santri
    </footer>
</body>
</html>
