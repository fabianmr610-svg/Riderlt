<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_pasajero.php");
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'pasajero') {
    header("Location: dashboard.php");
    exit;
}

$userName = $_SESSION['user_name'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Mark - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 480px; margin: 0 auto; min-height: 100vh; background: var(--ri-surface-2); }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--ri-line);
  }
  .topbar__back {
    width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--ri-line);
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--ri-ink); cursor: pointer; text-decoration: none;
  }
  .topbar__title { font-size: 17px; font-weight: 700; }

  .hero {
    text-align: center;
    padding: 40px 24px 28px;
  }
  .hero__icon {
    width: 72px; height: 72px; border-radius: 20px;
    background: var(--ri-ink); color: #fff;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
  }
  .hero h2 { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
  .hero p { font-size: 14px; color: var(--ri-mute); margin: 0; }

  .coming-soon {
    margin: 0 20px 20px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 14px;
    padding: 14px 16px;
    text-align: center;
  }
  .coming-soon__title { font-size: 14px; font-weight: 700; color: #b45309; margin: 0 0 4px; }
  .coming-soon__sub { font-size: 12px; color: #92400e; margin: 0; }

  .features { padding: 0 20px; }
  .feature-card {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid var(--ri-line);
    border-radius: 14px;
    margin-bottom: 10px;
  }
  .feature-card__icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--ri-surface-3); color: var(--ri-ink);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
  }
  .feature-card__name { font-size: 14px; font-weight: 700; margin: 0 0 2px; }
  .feature-card__desc { font-size: 12px; color: var(--ri-mute); margin: 0; }

  .back-home {
    padding: 24px 20px 40px;
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboardpa.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Mark</span>
  </div>

  <div class="hero">
    <div class="hero__icon">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8L12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
    </div>
    <h2>Mark</h2>
    <p>Compra en tus tiendas favoritas y recibe tus productos</p>
  </div>

  <div class="coming-soon">
    <p class="coming-soon__title">Proximamente</p>
    <p class="coming-soon__sub">Esta funcion estara disponible en la proxima actualizacion de RideIt.</p>
  </div>

  <div class="features">
    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><path d="M9 22V12h6v10"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Tiendas de conveniencia</p>
        <p class="feature-card__desc">Productos del dia a dia en minutos</p>
      </div>
    </div>

    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Supermercados</p>
        <p class="feature-card__desc">Tu despensa completa a domicilio</p>
      </div>
    </div>

    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 8h1a4 4 0 010 8h-1"/><path d="M2 8h16v9a4 4 0 01-4 4H6a4 4 0 01-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Restaurantes</p>
        <p class="feature-card__desc">Comida de tus lugares favoritos</p>
      </div>
    </div>
  </div>

  <div class="back-home">
    <a href="dashboardpa.php" class="ri-btn ri-btn--lg ri-btn--block ri-btn--ghost">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver al inicio
    </a>
  </div>
</div>

</body>
</html>
