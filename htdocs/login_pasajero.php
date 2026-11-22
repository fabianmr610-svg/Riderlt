<?php
include 'db.php';
include 'csrf.php';
session_start();

define('REMEMBER_COOKIE_NAME', 'rideit_remember');
define('REMEMBER_COOKIE_LIFETIME', 60 * 60 * 24 * 7);

function setRememberMe($user_id, $conn) {
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
    $stmt->bind_param("si", $token_hash, $user_id);
    $stmt->execute();
    setcookie(REMEMBER_COOKIE_NAME, $token, [
        'expires' => time() + REMEMBER_COOKIE_LIFETIME,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

function checkRememberMe($conn) {
    if (isset($_SESSION['user_id'])) return true;
    if (!isset($_COOKIE[REMEMBER_COOKIE_NAME])) return false;
    $token = $_COOKIE[REMEMBER_COOKIE_NAME];
    $token_hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id, name, role FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $user = $result->fetch_assoc()) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return false;
}

if (!isset($_SESSION['user_id'])) {
    checkRememberMe($conn);
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'conductor') {
        header("Location: dashboard.php");
        exit;
    } elseif ($role === 'admin') {
        header("Location: admin_panel.php");
        exit;
    }
    include 'bienvenida.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido. Recarga la pagina.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            $error = "Por favor, completa todos los campos.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password FROM users WHERE email = ? AND role = 'pasajero'");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_role'] = 'pasajero';
                        setRememberMe($user['id'], $conn);
                        include 'bienvenida.php';
                        exit;
                    } else {
                        $error = "Contrasena incorrecta. Intentalo de nuevo.";
                    }
                } else {
                    $error = "Correo no registrado o el rol no es 'pasajero'. Por favor, verifica.";
                }
            } else {
                $error = "Error en el sistema. Intenta mas tarde.";
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Iniciar sesion — RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
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
  .forgot { display: block; text-align: right; font-size: 13px; font-weight: 600; color: var(--ri-blue); margin: 14px 0 0; text-decoration: none; }
  .divider { display: flex; align-items: center; gap: 12px; margin: 28px 0; }
  .divider hr { flex: 1; border: 0; height: 1px; background: var(--ri-line); }
  .divider span { font-size: 12px; color: var(--ri-mute); font-weight: 600; }
  .social { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  .social__btn { height: 50px; border-radius: 999px; background: #fff; border: 1.5px solid var(--ri-line); font: inherit; font-weight: 600; font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; color: var(--ri-ink); }
  .social__btn--dark { background: var(--ri-ink); color: #fff; border: none; }
  .footer { text-align: center; font-size: 14px; color: var(--ri-ink-2); margin-top: 32px; }
  .footer a { color: var(--ri-blue); font-weight: 600; text-decoration: none; }
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
    <a href="login_conductor.php">Soy conductor →</a>
  </header>

  <h1>Hola,<br/>bienvenido.</h1>
  <p class="lede">Inicia sesion para pedir tu viaje.</p>

  <?php if (isset($_GET['success']) && $_GET['success'] === 'registered'): ?>
    <div class="ri-alert ri-alert--success" style="margin-bottom:16px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
      Registro exitoso. Inicia sesion con tus credenciales.
    </div>
  <?php endif; ?>
  <?php if (isset($error)): ?>
    <div class="ri-alert ri-alert--error" style="margin-bottom:16px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="login_pasajero.php" autocomplete="off">
    <?php echo csrfField(); ?>
    <div class="field">
      <label for="email">Correo</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        <input id="email" name="email" type="email" placeholder="tu@correo.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
    </div>

    <div class="field">
      <label for="pwd">Contrasena</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="pwd" name="password" type="password" placeholder="••••••••" required>
        <button type="button" onclick="(function(){var i=document.getElementById('pwd');i.type=i.type==='password'?'text':'password';})()" style="background:none;border:none;cursor:pointer;color:var(--ri-mute)" aria-label="Mostrar contrasena">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
    </div>

    <a href="recuperar_password.php" class="forgot">¿Olvidaste tu contrasena?</a>

    <button type="submit" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:28px">
      Iniciar sesion
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </button>
  </form>

  <div class="divider"><hr><span>o continuar con</span><hr></div>

  <div class="social">
    <button class="social__btn" type="button">
      <svg width="16" height="16" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.49h4.84a4.14 4.14 0 0 1-1.8 2.71v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.81 5.96-2.18l-2.92-2.26c-.8.54-1.83.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.32A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 0 1 3.68 9c0-.6.1-1.18.29-1.72V4.96H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.04l3-2.32z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58A9 9 0 0 0 9 0 9 9 0 0 0 .96 4.96l3 2.32C4.68 5.16 6.66 3.58 9 3.58z"/></svg>
      Google
    </button>
    <button class="social__btn social__btn--dark" type="button">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="#fff"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
      Apple
    </button>
  </div>

  <p class="footer">
    ¿No tienes cuenta? <a href="register_pasajero.php">Crear cuenta</a>
  </p>
</div>

<script src="modal.js"></script>
</body>
</html>
