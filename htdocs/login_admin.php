<?php
include 'db.php';
include 'csrf.php';
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido.";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        if (empty($email) || empty($password)) {
            $error = "Completa todos los campos.";
        } else {
            $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role = 'admin'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = 'admin';
                header("Location: admin_panel.php");
                exit;
            } else {
                $error = "Credenciales invalidas o no eres administrador.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Admin - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: var(--ri-ink); display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .card {
    max-width: 400px; width: 100%; margin: 20px;
    background: #fff; border-radius: 24px;
    padding: 40px 32px; text-align: center;
    box-shadow: var(--ri-shadow-lg);
  }
  .card__icon {
    width: 56px; height: 56px; border-radius: 16px;
    background: var(--ri-ink); color: #fff;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
  }
  .card h1 { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 4px; }
  .card .lede { font-size: 14px; color: var(--ri-mute); margin: 0 0 28px; }

  .field { margin-bottom: 12px; text-align: left; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .icon { color: var(--ri-mute); flex: none; }

  .back-link { display: block; margin-top: 20px; font-size: 14px; color: rgba(255,255,255,.5); text-decoration: none; }
  .back-link:hover { color: rgba(255,255,255,.8); }
</style>
</head>
<body>

<div class="card">
  <div class="card__icon">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
  </div>
  <h1>Panel Admin</h1>
  <p class="lede">Acceso exclusivo para administradores</p>

  <?php if (!empty($error)): ?>
    <div class="ri-alert ri-alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form action="login_admin.php" method="POST" autocomplete="off">
    <?php echo csrfField(); ?>

    <div class="field">
      <label for="email">Correo</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        <input id="email" name="email" type="email" placeholder="admin@rideit.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
    </div>

    <div class="field">
      <label for="password">Contrasena</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="password" name="password" type="password" placeholder="Tu contrasena" required>
      </div>
    </div>

    <button type="submit" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:20px">
      Iniciar Sesion
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </button>
  </form>
</div>

<a href="index.html" class="back-link">&larr; Volver al inicio</a>

</body>
</html>
