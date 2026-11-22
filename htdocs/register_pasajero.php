<?php
include 'db.php';
include 'csrf.php';
session_start();

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido. Recarga la pagina.";
    } else {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
    } elseif (!preg_match('/^[0-9]{7,15}$/', $phone)) {
        $error = "Numero de telefono invalido.";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "La contrasena debe tener al menos 8 caracteres, incluir letras y numeros.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El correo electronico no es valido.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Este correo ya esta registrado.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'pasajero')");
            $stmt->bind_param("ssss", $name, $email, $phone, $hashedPassword);

            if ($stmt->execute()) {
                header("Location: login_pasajero.php?success=registered");
                exit;
            } else {
                $error = "Hubo un error en el registro. Intenta de nuevo.";
            }
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
<title>Crear cuenta — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: #fff; display: flex; flex-direction: column; }
  .container { max-width: 420px; margin: 0 auto; width: 100%; padding: 24px 28px 48px; flex: 1; display: flex; flex-direction: column; }
  .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 36px; }
  .topbar a { color: var(--ri-mute); text-decoration: none; font-size: 14px; font-weight: 600; }
  h1 { font-size: 34px; font-weight: 800; letter-spacing: -0.03em; line-height: 1.04; margin: 0 0 8px; }
  .lede { font-size: 15px; color: var(--ri-mute); margin: 0 0 28px; }
  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .icon { color: var(--ri-mute); flex: none; }
  .strength { margin-top: 8px; display: none; }
  .strength__bar { width: 100%; height: 4px; background: var(--ri-line); border-radius: 999px; overflow: hidden; }
  .strength__fill { height: 100%; border-radius: 999px; transition: width .3s, background .3s; width: 0; }
  .strength__text { font-size: 12px; margin-top: 4px; color: var(--ri-mute); }
  .terms { font-size: 12px; color: var(--ri-mute); text-align: center; margin: 16px 0 0; line-height: 1.5; }
  .terms a { color: var(--ri-blue); text-decoration: none; font-weight: 500; }
  .footer { text-align: center; font-size: 14px; color: var(--ri-ink-2); margin-top: 28px; }
  .footer a { color: var(--ri-blue); font-weight: 600; text-decoration: none; }
  @media (min-width: 760px) {
    .container { max-width: 480px; padding-top: 48px; }
    h1 { font-size: 40px; }
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
    <a href="login_pasajero.php">← Iniciar sesion</a>
  </header>

  <h1>Crea tu cuenta.</h1>
  <p class="lede">Registrate y pide tu primer viaje en segundos.</p>

  <?php if (!empty($error)): ?>
    <div class="ri-alert ri-alert--error" style="margin-bottom:16px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
      <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form action="register_pasajero.php" method="POST" autocomplete="off">
    <?php echo csrfField(); ?>

    <div class="field">
      <label for="name">Nombre completo</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
        <input id="name" name="name" type="text" placeholder="Tu nombre completo" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
      </div>
    </div>

    <div class="field">
      <label for="email">Correo electronico</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 7l10 7 10-7"/></svg>
        <input id="email" name="email" type="email" placeholder="tu@correo.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
      </div>
    </div>

    <div class="field">
      <label for="phone">Telefono</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
        <input id="phone" name="phone" type="text" placeholder="Ej. 6394851234" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
      </div>
    </div>

    <div class="field">
      <label for="password">Contrasena</label>
      <div class="ri-input">
        <svg class="icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
        <input id="password" name="password" type="password" placeholder="Minimo 8 caracteres" required>
        <button type="button" onclick="(function(){var i=document.getElementById('password');i.type=i.type==='password'?'text':'password';})()" style="background:none;border:none;cursor:pointer;color:var(--ri-mute)" aria-label="Mostrar contrasena">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>
      <div class="strength" id="strength">
        <div class="strength__bar"><div class="strength__fill" id="strength-bar"></div></div>
        <div class="strength__text" id="strength-text"></div>
      </div>
    </div>

    <p class="terms">
      Al registrarte, aceptas nuestros
      <a href="terminos.php">Terminos y Condiciones</a> y
      <a href="privacidad.php">Aviso de Privacidad</a>.
    </p>

    <button type="submit" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:20px">
      Crear cuenta
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
    </button>
  </form>

  <p class="footer">
    ¿Ya tienes cuenta? <a href="login_pasajero.php">Iniciar sesion</a>
  </p>
</div>

<script>
const passInput = document.getElementById('password');
const strengthDiv = document.getElementById('strength');
const strengthBar = document.getElementById('strength-bar');
const strengthText = document.getElementById('strength-text');

passInput.addEventListener('input', function() {
    const val = this.value;
    if (val.length === 0) { strengthDiv.style.display = 'none'; return; }
    strengthDiv.style.display = 'block';

    let score = 0;
    let msgs = [];
    if (val.length >= 8) { score++; } else { msgs.push('Minimo 8 caracteres'); }
    if (/[A-Za-z]/.test(val)) { score++; } else { msgs.push('Incluir letras'); }
    if (/[0-9]/.test(val)) { score++; } else { msgs.push('Incluir numeros'); }

    const colors = ['var(--ri-red)', '#f97316', 'var(--ri-yellow)', 'var(--ri-green)'];
    const labels = ['Muy debil', 'Debil', 'Aceptable', 'Segura'];
    const pct = [25, 50, 75, 100];

    strengthBar.style.width = pct[score] + '%';
    strengthBar.style.backgroundColor = colors[score];
    strengthText.textContent = labels[score] + (msgs.length ? ' — ' + msgs.join(', ') : '');
    strengthText.style.color = colors[score];
});
</script>
</body>
</html>
