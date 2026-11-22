<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_pasajero.php");
    exit;
}
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    header("Location: admin_panel.php");
    exit;
}
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'conductor') {
    header("Location: dashboard.php");
    exit;
}

$userName = $_SESSION['user_name'];
$userId = $_SESSION['user_id'];

date_default_timezone_set('America/Mexico_City');
$hour = date("H");
if ($hour >= 0 && $hour < 6) { $greeting = "Buenas madrugadas"; }
elseif ($hour >= 6 && $hour < 12) { $greeting = "Buenos dias"; }
elseif ($hour >= 12 && $hour < 19) { $greeting = "Buenas tardes"; }
else { $greeting = "Buenas noches"; }

$mensaje = "";
if (isset($_GET['bienvenido'])) $mensaje = "¡Registro exitoso! Bienvenido a RideIt, $userName.";
if (isset($_GET['cambio'])) $mensaje = "¡Contrasena actualizada correctamente!";

$totalViajes = 0; $totalGastado = 0; $viajesPendientes = 0; $calificacionPromedio = null;

$stmt = $conn->prepare("SELECT COUNT(*) AS total, IFNULL(SUM(fare),0) AS gastado FROM trips WHERE passenger_id = ?");
if ($stmt) { $stmt->bind_param("i", $userId); $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) { $totalViajes = $row['total'] ?? 0; $totalGastado = $row['gastado'] ?? 0; } $stmt->close(); }

$stmt = $conn->prepare("SELECT COUNT(*) AS pendientes FROM trips WHERE passenger_id = ? AND status = 'pendiente'");
if ($stmt) { $stmt->bind_param("i", $userId); $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) { $viajesPendientes = $row['pendientes'] ?? 0; } $stmt->close(); }

$hasRating = false;
$colCheck = $conn->query("SHOW COLUMNS FROM trips LIKE 'rating'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasRating = true;
    $stmt = $conn->prepare("SELECT AVG(rating) AS promedio FROM trips WHERE passenger_id = ? AND rating IS NOT NULL");
    if ($stmt) { $stmt->bind_param("i", $userId); $stmt->execute(); $res = $stmt->get_result(); $row = $res->fetch_assoc(); if ($row && !is_null($row['promedio'])) { $calificacionPromedio = round($row['promedio'],1); } $stmt->close(); }
}

$recentTrips = [];
if ($hasRating) {
    $stmt = $conn->prepare("SELECT id, destination_address, fare, status, rating, DATE_FORMAT(completed_at, '%d/%m/%Y') AS completed_date FROM trips WHERE passenger_id = ? ORDER BY completed_at DESC LIMIT 4");
} else {
    $stmt = $conn->prepare("SELECT id, destination_address, fare, status, DATE_FORMAT(completed_at, '%d/%m/%Y') AS completed_date FROM trips WHERE passenger_id = ? ORDER BY completed_at DESC LIMIT 4");
}
if ($stmt) { $stmt->bind_param("i", $userId); $stmt->execute(); $result = $stmt->get_result(); $recentTrips = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); }

$profilePhoto = "";
$colPhoto = $conn->query("SHOW COLUMNS FROM users LIKE 'photo'");
if ($colPhoto && $colPhoto->num_rows > 0) {
    $stmt = $conn->prepare("SELECT photo FROM users WHERE id = ?");
    if ($stmt) { $stmt->bind_param("i", $userId); $stmt->execute(); $res = $stmt->get_result(); if ($row = $res->fetch_assoc()) { $profilePhoto = $row['photo'] ?? ''; } $stmt->close(); }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Hola, <?php echo htmlspecialchars($userName); ?> — RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 480px; margin: 0 auto; padding: 0 0 100px; }

  /* Top bar */
  .dash-top {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; position: sticky; top: 0; z-index: 10;
    background: rgba(250,250,250,.88); backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--ri-line-2);
  }
  .dash-top__left { display: flex; align-items: center; gap: 12px; }
  .dash-top__avatar {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
    border: 2px solid var(--ri-blue); background: var(--ri-surface-3);
  }
  .dash-top__avatar-placeholder {
    width: 40px; height: 40px; border-radius: 50%; background: var(--ri-surface-3);
    border: 2px solid var(--ri-line); display: flex; align-items: center; justify-content: center;
    color: var(--ri-mute);
  }
  .dash-top__name { font-size: 15px; font-weight: 700; }
  .dash-top__right { display: flex; gap: 8px; }
  .dash-top__btn {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid var(--ri-line); background: var(--ri-surface);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--ri-ink); text-decoration: none;
  }
  .dash-top__btn:hover { background: var(--ri-surface-3); }

  /* Content */
  .dash-content { padding: 20px; }

  /* Greeting */
  .greeting { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
  .greeting-sub { font-size: 14px; color: var(--ri-mute); margin: 0 0 20px; }

  /* Search bar */
  .search-bar {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 16px; margin-bottom: 20px; cursor: pointer;
    text-decoration: none; color: inherit; box-shadow: var(--ri-shadow-sm);
  }
  .search-bar:hover { box-shadow: var(--ri-shadow-md); }
  .search-bar__placeholder { flex: 1; font-size: 15px; color: var(--ri-mute); }
  .search-bar__when {
    display: flex; align-items: center; gap: 6px; padding: 4px 10px;
    border-radius: 999px; background: var(--ri-surface-3);
    font-size: 12px; font-weight: 600; color: var(--ri-ink);
  }

  /* Service cards */
  .services { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 24px; }
  .svc {
    border-radius: 14px; padding: 16px 14px; min-height: 90px;
    display: flex; flex-direction: column; justify-content: space-between;
    text-decoration: none; color: inherit; cursor: pointer;
    transition: transform .08s ease;
  }
  .svc:hover { transform: translateY(-1px); }
  .svc--ink { background: var(--ri-ink); color: #fff; }
  .svc--blue { background: var(--ri-blue); color: #fff; }
  .svc--soft { background: var(--ri-surface); color: var(--ri-ink); border: 1px solid var(--ri-line); }
  .svc__name { font-size: 14px; font-weight: 700; margin-top: 10px; }

  /* Stats */
  .stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 24px; }
  .stat {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 14px; padding: 14px; text-align: center;
  }
  .stat__val { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: var(--ri-ink); }
  .stat__label { font-size: 11px; color: var(--ri-mute); font-weight: 500; margin-top: 2px; }

  /* Section */
  .section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .section-head h3 { font-size: 16px; font-weight: 700; margin: 0; }
  .section-head a { font-size: 13px; font-weight: 600; color: var(--ri-blue); text-decoration: none; }

  /* Trip card */
  .trip {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 14px; padding: 14px 16px; margin-bottom: 8px;
    display: flex; align-items: center; gap: 14px;
  }
  .trip__icon {
    width: 40px; height: 40px; border-radius: 10px; background: var(--ri-surface-3);
    display: flex; align-items: center; justify-content: center; flex: none;
    color: var(--ri-ink);
  }
  .trip__info { flex: 1; min-width: 0; }
  .trip__dest { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .trip__meta { font-size: 12px; color: var(--ri-mute); margin-top: 2px; }
  .trip__right { text-align: right; flex: none; }
  .trip__fare { font-size: 14px; font-weight: 700; }

  /* Bottom nav */
  .bottom-nav {
    position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 100%; max-width: 480px;
    background: var(--ri-surface); border-top: 1px solid var(--ri-line);
    display: flex; justify-content: space-around; padding: 10px 0 18px;
    z-index: 20;
  }
  .bottom-nav a {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    text-decoration: none; color: var(--ri-mute); font-size: 11px; font-weight: 600;
  }
  .bottom-nav a.active { color: var(--ri-ink); }
</style>
</head>
<body>

<div class="shell">
  <!-- Top bar -->
  <header class="dash-top">
    <div class="dash-top__left">
      <?php if ($profilePhoto): ?>
        <img src="<?php echo htmlspecialchars($profilePhoto); ?>" alt="Perfil" class="dash-top__avatar">
      <?php else: ?>
        <div class="dash-top__avatar-placeholder">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
        </div>
      <?php endif; ?>
      <span class="dash-top__name"><?php echo htmlspecialchars($userName); ?></span>
      <?php if (!is_null($calificacionPromedio)): ?>
        <span class="ri-chip ri-chip--yellow" style="font-size:11px; height:22px; padding:0 8px;">★ <?php echo $calificacionPromedio; ?></span>
      <?php endif; ?>
    </div>
    <div class="dash-top__right">
      <a href="help.php" class="dash-top__btn" title="Ayuda">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 015.12 1.5c0 1.5-2.12 2-2.12 2"/><circle cx="12" cy="17" r=".5" fill="currentColor"/></svg>
      </a>
      <button type="button" onclick="confirmLogout()" class="dash-top__btn" title="Cerrar sesion" style="color:var(--ri-red)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      </button>
    </div>
  </header>

  <div class="dash-content">
    <!-- Flash message -->
    <?php if ($mensaje): ?>
      <div class="ri-alert ri-alert--success" style="margin-bottom:16px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
        <?php echo htmlspecialchars($mensaje); ?>
      </div>
    <?php endif; ?>

    <!-- Greeting -->
    <h1 class="greeting"><?php echo "$greeting, " . htmlspecialchars($userName); ?> 👋</h1>
    <p class="greeting-sub">¿Que puedo hacer por ti hoy?</p>

    <!-- Search -->
    <a href="uberx.php" class="search-bar">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--ri-mute)" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      <span class="search-bar__placeholder">¿A donde vas?</span>
      <span class="search-bar__when">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
        Ahora
      </span>
    </a>

    <!-- Services -->
    <div class="services">
      <a href="uberx.php" class="svc svc--ink">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        <span class="svc__name">Viaje</span>
      </a>
      <a href="uberfavor.php" class="svc svc--blue">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1.5 13H7.5L6 7z"/><path d="M9 7V5a3 3 0 0 1 6 0v2"/></svg>
        <span class="svc__name">Favor</span>
      </a>
      <a href="ubermark.php" class="svc svc--soft">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8L12 3 3 8v8l9 5 9-5V8z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
        <span class="svc__name">Mark</span>
      </a>
    </div>

    <!-- Stats -->
    <div class="stats">
      <div class="stat">
        <div class="stat__val"><?php echo $totalViajes; ?></div>
        <div class="stat__label">Viajes</div>
      </div>
      <div class="stat">
        <div class="stat__val" style="color:var(--ri-green)">$<?php echo number_format($totalGastado, 0); ?></div>
        <div class="stat__label">Gastado</div>
      </div>
      <div class="stat">
        <div class="stat__val" style="color:var(--ri-yellow)"><?php echo $viajesPendientes; ?></div>
        <div class="stat__label">Pendientes</div>
      </div>
    </div>

    <!-- Recent trips -->
    <div class="section-head">
      <h3>Viajes recientes</h3>
      <a href="historial_pasajero.php">Ver todo</a>
    </div>

    <?php if (!empty($recentTrips)): ?>
      <?php foreach ($recentTrips as $trip): ?>
        <div class="trip">
          <div class="trip__icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s-8-7.5-8-13a8 8 0 1 1 16 0c0 5.5-8 13-8 13z"/><circle cx="12" cy="9" r="3"/></svg>
          </div>
          <div class="trip__info">
            <div class="trip__dest"><?php echo htmlspecialchars($trip['destination_address'] ?? 'Sin direccion'); ?></div>
            <div class="trip__meta"><?php echo htmlspecialchars($trip['completed_date'] ?? 'Sin fecha'); ?> ·
              <?php
              $statusLabel = 'Desconocido';
              $statusClass = 'ri-badge--mute';
              switch($trip['status']) {
                case 'completado': $statusLabel = 'Completado'; $statusClass = 'ri-badge--green'; break;
                case 'pendiente': $statusLabel = 'Pendiente'; $statusClass = 'ri-badge--yellow'; break;
                case 'rechazado': $statusLabel = 'Rechazado'; $statusClass = 'ri-badge--red'; break;
                case 'asignado': $statusLabel = 'Asignado'; $statusClass = 'ri-badge--blue'; break;
              }
              ?>
              <span class="ri-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
            </div>
          </div>
          <div class="trip__right">
            <div class="trip__fare">$<?php echo number_format($trip['fare'] ?? 0, 2); ?></div>
            <?php if ($hasRating && $trip['status'] === 'completado' && isset($trip['id'])): ?>
              <?php if (!isset($trip['rating']) || is_null($trip['rating'])): ?>
                <a href="calificar_viaje.php?trip_id=<?php echo urlencode($trip['id']); ?>" class="ri-badge ri-badge--yellow" style="margin-top:4px;text-decoration:none;font-size:11px">Calificar</a>
              <?php else: ?>
                <span class="ri-badge ri-badge--yellow" style="margin-top:4px;font-size:11px">★ <?php echo htmlspecialchars($trip['rating']); ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding:32px 0; color:var(--ri-mute); font-size:14px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--ri-line)" stroke-width="1.5" style="margin:0 auto 12px;display:block"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        No tienes viajes recientes.<br>
        <a href="uberx.php" style="color:var(--ri-blue);font-weight:600;text-decoration:none">Pide tu primer viaje →</a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Bottom nav -->
  <nav class="bottom-nav">
    <a href="dashboardpa.php" class="active">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8M5 9.5V21h14V9.5"/></svg>
      Inicio
    </a>
    <a href="uberx.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      Viaje
    </a>
    <a href="historial_pasajero.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
      Historial
    </a>
    <a href="profilepa.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
      Perfil
    </a>
  </nav>
</div>

<script src="modal.js"></script>
<script>
function confirmLogout() {
    RideIt.confirm({
        title: 'Cerrar sesion',
        message: '¿Deseas cerrar tu sesion? Tendras que volver a iniciar sesion para solicitar viajes.',
        type: 'warning',
        confirmText: 'Cerrar sesion',
        confirmClass: 'btn-danger',
        onConfirm: () => window.location.href = 'logoutpa.php'
    });
}
</script>
</body>
</html>
