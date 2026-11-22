<?php
include 'db.php';
include 'csrf.php';
session_start();

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido. Recarga la pagina.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Ingresa un correo electronico valido.";
        } else {
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            $mensaje = "Si el correo esta registrado, recibiras instrucciones para restablecer tu contrasena. Revisa tu bandeja de entrada y la carpeta de spam.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Recuperar Contrasena - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: #fff; display: flex; flex-direction: column; }
  .container { max-width: 420px; margin: 0 auto; width: 100%; padding: 24px 28px 48px; flex: 1; display: flex; flex-direction: column; justify-content: center; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
  .topbar a { color: var(--ri-mute); text-decoration: none; font-size: 14px; font-weight: 600; }

  .icon-circle {
    width: 64px; height: 64px; border-radius: 50%;
    background: var(--ri-blue-soft); color: var(--ri-blue);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
  }
  h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; text-align: center; margin: 0 0 6px; }
  .lede { font-size: 14px; color: var(--ri-mute); text-align: center; margin: 0 0 28px; line-height: 1.5; }

  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .icon { color: var(--ri-mute); flex: none; }

  .back-link { display: block; text-align: center; margin-top: 24px; font-size: 14px; color: var(--ri-blue); text-decoration: none; font-weight: 600; }
</style>
</head>
<body>

<div class="container">
  <header class="topbar">
    <a class="ri-logo" href="index.html" style="text-decoration:none">
      <span class="ri-logo__mark">R</span>
      <span>RideIt</span>
    </a>
  </header>

  <div class="icon-circle">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
  </div>
  <h1>Recuperar contrasena</h1>
  <p class="lede">Ingresa tu correo y te enviaremos instrucciones para restablecer tu contrasena.</p>

  <?php if ($mensaje): ?>
    <div class="ri-alert ri-alert--success" style="margin-bottom:16px"><?php echo htmlspecialchars($mensaje); ?></div>
    <a href="login_pasajero.php" class="ri-btn ri-btn--lg ri-btn--block ri-btn--blue">
      Volver al login
    </a>
  <?php else: ?>

    <?php if ($error): ?>
      <div class="ri-alert ri-alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form action="recuperar_password.php" method="POST" autocomplete="off">
      <?php echo csrfField(); ?>

      <div class="field">
        <label for="email">Correo electronico</label>
        <div class="ri-input">
          <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
          <input id="email" name="email" type="email" placeholder="correo@ejemplo.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>
      </div>

      <button type="submit" class="ri-btn ri-btn--lg ri-btn--block ri-btn--blue" style="margin-top:20px">
        Enviar instrucciones
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </button>
    </form>

    <a href="login_pasajero.php" class="back-link">&larr; Volver al inicio de sesion</a>
  <?php endif; ?>
</div>

</body>
</html>
