<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login_pasajero.php");
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bienvenido — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--ri-surface); }
  .welcome {
    text-align: center; padding: 48px 32px;
    animation: fadeUp .6s ease;
  }
  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .welcome__logo {
    width: 64px; height: 64px; border-radius: 18px;
    background: var(--ri-ink); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 32px; margin: 0 auto 24px;
    position: relative;
  }
  .welcome__logo::after {
    content: ""; position: absolute; right: -4px; bottom: -4px;
    width: 14px; height: 14px; border-radius: 50%;
    background: var(--ri-blue); outline: 3px solid var(--ri-surface);
  }
  .welcome__title { font-size: 28px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 8px; }
  .welcome__sub { font-size: 15px; color: var(--ri-mute); margin: 0 0 28px; }
  .spinner {
    width: 32px; height: 32px; border: 3px solid var(--ri-line);
    border-top-color: var(--ri-blue); border-radius: 50%;
    animation: spin .8s linear infinite; margin: 0 auto;
  }
  @keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
  setTimeout(function() {
    window.location.replace("<?php echo ($_SESSION['user_role'] ?? '') === 'conductor' ? 'dashboard.php' : 'dashboardpa.php'; ?>");
  }, 1300);
</script>
</head>
<body>
  <div class="welcome">
    <div class="welcome__logo">R</div>
    <h1 class="welcome__title">¡Bienvenido!</h1>
    <p class="welcome__sub">Redirigiendo a tu panel...</p>
    <div class="spinner"></div>
  </div>
</body>
</html>
