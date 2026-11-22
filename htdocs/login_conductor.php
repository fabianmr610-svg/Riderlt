<?php
include 'csrf.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'pasajero') {
        header("Location: dashboardpa.php");
        exit;
    } elseif ($role === 'admin') {
        header("Location: admin_panel.php");
        exit;
    } else {
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Conductor — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: #fff; display: flex; flex-direction: column; }
  .container { max-width: 420px; margin: 0 auto; width: 100%; padding: 24px 28px 48px; flex: 1; display: flex; flex-direction: column; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
  .topbar a { color: var(--ri-mute); text-decoration: none; font-size: 14px; font-weight: 600; }
  h1 { font-size: 36px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.04; margin: 0 0 8px; }
  .lede { font-size: 15px; color: var(--ri-mute); margin: 0 0 32px; }
  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .icon { color: var(--ri-mute); flex: none; }
  .footer { text-align: center; font-size: 14px; color: var(--ri-ink-2); margin-top: 32px; }
  .footer a { color: var(--ri-blue); font-weight: 600; text-decoration: none; }
  .driver-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--ri-green-soft); color: var(--ri-green); padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; margin-bottom: 24px; }
  @media (min-width: 760px) {
    .container { max-width: 480px; padding-top: 56px; }
    h1 { font-size: 42px; }
  }
</style>
</head>
<body>

<div class="container">
  <header class="topbar">
    <a class="ri-logo" href="landing.php" style="text-decoration:none">
      <span class="ri-logo__mark">R</span>
      <span>RideIt</span>
    </a>
    <a href="login_pasajero.php">← Soy pasajero</a>
  </header>

  <div class="driver-badge">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
    Modo conductor
  </div>

  <h1>Empieza a<br/>manejar.</h1>
  <p class="lede">Inicia sesion para recibir viajes y ganar.</p>

  <?php if (isset($_GET['success']) && $_GET['success'] === 'registered'): ?>
    <div class="ri-alert ri-alert--success" style="margin-bottom:16px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
      Registro exitoso. Inicia sesion con tus credenciales.
    </div>
  <?php endif; ?>
  <?php if (isset($_GET['error'])): ?>
    <div class="ri-alert ri-alert--error" style="margin-bottom:16px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      <?php
      $err = $_GET['error'];
      if ($err === 'empty_fields') echo "Por favor, completa todos los campos.";
      elseif ($err === 'user_not_found') echo "Usuario no encontrado. Verifica tu correo.";
      elseif ($err === 'invalid_password') echo "Contrasena incorrecta. Intentalo de nuevo.";
      elseif ($err === 'invalid_token') echo "Token de seguridad invalido. Recarga la pagina.";
      else echo "Error desconocido.";
      ?>
    </div>
  <?php endif; ?>

  <form action="login.php" method="POST" autocomplete="off">
    <?php echo csrfField(); ?>
    <div class="field">
      <label for="email">Correo</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        <input id="email" name="email" type="email" placeholder="tu@correo.com" required>
      </div>
    </div>

    <div class="field">
      <label for="password">Contrasena</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="password" name="password" type="password" placeholder="••••••••" required>
        <button type="button" onclick="(function(){var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';})()" style="background:none;border:none;cursor:pointer;color:var(--ri-mute)" aria-label="Mostrar contrasena">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <button type="submit" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:28px">
      Iniciar sesion
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </button>
  </form>

  <p class="footer">
    ¿No tienes cuenta? <a href="registrocon.php">Registrate como conductor</a>
  </p>
</div>

</body>
</html>
