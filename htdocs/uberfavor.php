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
<title>Favor - RideIt</title>
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
    background: var(--ri-blue); color: #fff;
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
    background: var(--ri-blue-soft); color: var(--ri-blue);
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
    <span class="topbar__title">Favor</span>
  </div>

  <div class="hero">
    <div class="hero__icon">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1.5 13H7.5L6 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
    </div>
    <h2>Favor</h2>
    <p>Pide ayuda con cualquier tarea o recado</p>
  </div>

  <div class="coming-soon">
    <p class="coming-soon__title">Proximamente</p>
    <p class="coming-soon__sub">Esta funcion estara disponible en la proxima actualizacion de RideIt.</p>
  </div>

  <div class="features">
    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 8l-2 2m0 0l-2-2m2 2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2v-4"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Envio de paquetes</p>
        <p class="feature-card__desc">Envia y recibe paquetes en la ciudad</p>
      </div>
    </div>

    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 14l2 2 4-4"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Tramites y recados</p>
        <p class="feature-card__desc">Alguien hace tus recados por ti</p>
      </div>
    </div>

    <div class="feature-card">
      <div class="feature-card__icon">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 3h18v18H3zM9 3v18M21 9H3M21 15H3"/></svg>
      </div>
      <div>
        <p class="feature-card__name">Recoger medicinas</p>
        <p class="feature-card__desc">Recogemos tus medicinas en la farmacia</p>
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
