<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Aplicacion Conductor - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: #fff; display: flex; flex-direction: column; }
  .container { max-width: 480px; margin: 0 auto; width: 100%; padding: 24px 28px 48px; flex: 1; display: flex; flex-direction: column; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; }
  .topbar a { color: var(--ri-mute); text-decoration: none; font-size: 14px; font-weight: 600; }

  .conductor-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--ri-green-soft); color: var(--ri-green);
    padding: 4px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 700; margin-bottom: 16px;
  }
  h1 { font-size: 28px; font-weight: 800; letter-spacing: -.02em; margin: 0 0 6px; }
  .lede { font-size: 14px; color: var(--ri-mute); margin: 0 0 28px; }

  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .field label .req { color: var(--ri-red); }
  .icon { color: var(--ri-mute); flex: none; }
  .field select {
    width: 100%; padding: 0 16px; height: 48px; border-radius: 12px;
    border: 1.5px solid var(--ri-line); background: #fff;
    font-size: 14px; color: var(--ri-ink); cursor: pointer;
  }
  .field select:focus { border-color: var(--ri-blue); outline: none; }
  .field textarea {
    width: 100%; padding: 12px 16px; border: 1.5px solid var(--ri-line);
    border-radius: 12px; font: inherit; font-size: 14px; color: var(--ri-ink);
    resize: vertical; min-height: 80px; background: #fff;
  }
  .field textarea:focus { border-color: var(--ri-blue); outline: none; }

  .ri-footer { padding: 24px 20px; text-align: center; font-size: 13px; color: var(--ri-mute); }
</style>
</head>
<body>

<?php
require_once 'config.php';
include 'csrf.php';
session_start();

$conn = new mysqli(DB_HOST_REMOTE, DB_USER_REMOTE, DB_PASS_REMOTE, DB_NAME_REMOTE);

if ($conn->connect_error) {
    die("Error de conexion.");
}

$msg = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $msg = "Token de seguridad invalido.";
        $msgType = "error";
    } else {
        $full_name = trim($_POST['full_name']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $own_car = trim($_POST['own_car']);
        $car_details = trim($_POST['car_details']);
        $car_year = trim($_POST['car_year']);
        $license = trim($_POST['license']);
        $trustworthy = trim($_POST['trustworthy']);
        $reliability = trim($_POST['reliability']);

        $stmt = $conn->prepare("INSERT INTO applications (full_name, phone, email, own_car, car_details, car_year, license, trustworthy, reliability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $full_name, $phone, $email, $own_car, $car_details, $car_year, $license, $trustworthy, $reliability);

        if ($stmt->execute()) {
            $msg = "Aplicacion enviada exitosamente. Te contactaremos pronto.";
            $msgType = "success";
        } else {
            $msg = "Error al enviar la aplicacion. Intenta de nuevo.";
            $msgType = "error";
        }

        $stmt->close();
    }
}

$conn->close();
?>

<div class="container">
  <header class="topbar">
    <a class="ri-logo" href="index.html" style="text-decoration:none">
      <span class="ri-logo__mark">R</span>
      <span>RideIt</span>
    </a>
    <a href="login_conductor.php">Ya tengo cuenta &rarr;</a>
  </header>

  <div class="conductor-badge">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
    Aplicacion conductor
  </div>

  <h1>Se parte de RideIt.</h1>
  <p class="lede">Completa el formulario para aplicar como conductor.</p>

  <?php if ($msg): ?>
    <div class="ri-alert ri-alert--<?php echo $msgType; ?>" style="margin-bottom:16px"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <form action="" method="POST" autocomplete="off">
    <?php echo csrfField(); ?>

    <div class="field">
      <label for="full_name">Nombre completo <span class="req">*</span></label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a8 8 0 00-8 8h16a8 8 0 00-8-8z"/></svg>
        <input id="full_name" name="full_name" type="text" placeholder="Tu nombre completo" required>
      </div>
    </div>

    <div class="field">
      <label for="phone">Telefono <span class="req">*</span></label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        <input id="phone" name="phone" type="tel" placeholder="6391234567" required pattern="[0-9]{7,15}">
      </div>
    </div>

    <div class="field">
      <label for="email">Correo electronico <span class="req">*</span></label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        <input id="email" name="email" type="email" placeholder="tu@correo.com" required>
      </div>
    </div>

    <div class="field">
      <label for="own_car">Tienes auto propio? <span class="req">*</span></label>
      <select name="own_car" id="own_car" required>
        <option value="">Selecciona una opcion</option>
        <option value="yes">Si</option>
        <option value="no">No</option>
      </select>
    </div>

    <div class="field">
      <label for="car_details">Marca y modelo del auto <span class="req">*</span></label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        <input id="car_details" name="car_details" type="text" placeholder="Ej. Nissan Versa" required>
      </div>
    </div>

    <div class="field">
      <label for="car_year">Ano del auto <span class="req">*</span></label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <input id="car_year" name="car_year" type="number" placeholder="2020" required min="2000">
      </div>
    </div>

    <div class="field">
      <label for="license">Tienes licencia de conducir vigente? <span class="req">*</span></label>
      <select name="license" id="license" required>
        <option value="">Selecciona una opcion</option>
        <option value="yes">Si</option>
        <option value="no">No</option>
      </select>
    </div>

    <div class="field">
      <label for="trustworthy">Por que serias un buen conductor? <span class="req">*</span></label>
      <textarea id="trustworthy" name="trustworthy" placeholder="Escribe tu respuesta..." required></textarea>
    </div>

    <div class="field">
      <label for="reliability">Como describirias tu nivel de responsabilidad? <span class="req">*</span></label>
      <textarea id="reliability" name="reliability" placeholder="Escribe tu respuesta..." required></textarea>
    </div>

    <button type="submit" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:20px;--bg:var(--ri-green)">
      Enviar aplicacion
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </button>
  </form>
</div>

<div class="ri-footer">&copy; 2025 RideIt. Todos los derechos reservados.</div>

</body>
</html>
