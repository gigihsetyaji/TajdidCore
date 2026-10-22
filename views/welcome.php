<?php use Tajdid\Core\View; ?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="TajdidCore — framework PHP ringan dengan alur eksplisit, keamanan bawaan, dan dokumentasi untuk mulai membangun.">
    <title><?= View::escape($name) ?></title>
    <meta name="theme-color" content="#005477">
    <link rel="icon" type="image/png" href="/images/tajdidcore-logo.png">
    <link rel="stylesheet" href="/app.css">
</head>
<body class="welcome-page">
<header class="site-header">
    <a class="brand" href="/" aria-label="<?= View::escape($name) ?> — Beranda">
        <img class="brand-logo" src="/images/tajdidcore-logo.png" alt="" width="64" height="64">
        <span><?= View::escape($name) ?></span>
    </a>
    <nav class="header-links" aria-label="Navigasi utama"><a href="/docs">Dokumentasi</a><a href="/health">Status sistem <span class="status-dot" aria-hidden="true"></span></a></nav>
</header>
<main class="welcome-content">
    <section class="hero" aria-labelledby="welcome-title">
    <div class="hero-copy">
    <small>PHP FRAMEWORK / 0.3.0</small>
    <h1 id="welcome-title">Fondasi baru.<br><span>Untuk ide besar.</span></h1>
    <p>Framework PHP ringan dengan alur yang eksplisit, action yang fokus, dan ruang untuk tumbuh.</p>
    <nav class="actions" aria-label="Mulai menggunakan framework">
        <a class="button" href="/docs">Baca dokumentasi <span aria-hidden="true">→</span></a>
        <a class="text-link" href="/docs#mulai">Mulai dalam beberapa langkah</a>
    </nav>
    <div class="hero-meta"><span>PHP 8.4+</span><span>Tanpa dependency runtime tambahan</span></div>
    </div>
    <div class="hero-visual">
        <img src="/images/tajdidcore-logo.png" alt="Logo TajdidCore, monogram TC berwarna teal dan biru" width="1254" height="1254" fetchpriority="high">
        <span class="visual-caption">Building Technology, Inspiring Progress.</span>
    </div>
    </section>
    <div class="section-heading"><small>DIBANGUN UNTUK PENGEMBANG</small><h2>Sederhana untuk dimulai. Jelas untuk dikembangkan.</h2></div>
    <div class="grid">
        <div class="card"><h2>Rute jelas</h2><p>Hubungkan URL ke action melalui <code>routes/web.php</code>.</p><a href="/docs#routing">Pelajari routing →</a></div>
        <div class="card"><h2>Komponen mandiri</h2><p>Container, middleware, view, dan database bekerja bersama tanpa facade global.</p><a href="/docs#container">Kenali container →</a></div>
        <div class="card"><h2>Mulai membangun</h2><p>Buat action pertama dengan <code>php tajdid make:action</code>.</p><a href="/docs#action">Buat action pertama →</a></div>
    </div>
    <a href="/health">Periksa kesehatan aplikasi →</a>
    <footer><p>TajdidCore · Dirancang untuk mudah dipahami dan dikembangkan.</p></footer>
</main>
</body>
</html>
