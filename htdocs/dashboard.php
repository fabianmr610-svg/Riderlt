<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_conductor.php"); exit; }
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') { header("Location: admin_panel.php"); exit; }
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'pasajero') { header("Location: dashboardpa.php"); exit; }

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

date_default_timezone_set('America/Mexico_City');
$hour = (int)date("H");
if ($hour >= 0 && $hour < 6) { $greeting = "Buenas madrugadas"; }
elseif ($hour >= 6 && $hour < 12) { $greeting = "Buenos dias"; }
elseif ($hour >= 12 && $hour < 19) { $greeting = "Buenas tardes"; }
else { $greeting = "Buenas noches"; }

$stmt = $conn->prepare("SELECT COUNT(*) AS completed_trips, COALESCE(SUM(fare), 0) AS daily_earnings FROM trips WHERE driver_id = ? AND status = 'completado' AND DATE(completed_at) = CURDATE()");
$stmt->bind_param("i", $userId); $stmt->execute(); $stats = $stmt->get_result()->fetch_assoc();
$completedTrips = $stats['completed_trips'] ?? 0;
$dailyEarnings = $stats['daily_earnings'] ?? 0.00;

$stmtRating = $conn->prepare("SELECT ROUND(AVG(rating),1) AS promedio, COUNT(rating) AS total FROM trips WHERE driver_id = ? AND rating IS NOT NULL");
$stmtRating->bind_param("i", $userId); $stmtRating->execute(); $ratingData = $stmtRating->get_result()->fetch_assoc();
$promedioRating = $ratingData['promedio'] ?? 0;
$totalRatings = $ratingData['total'] ?? 0;

$driverPhoto = '';
$stmtPhoto = $conn->prepare("SELECT photo FROM users WHERE id = ?");
$stmtPhoto->bind_param("i", $userId); $stmtPhoto->execute(); $photoRes = $stmtPhoto->get_result();
if ($photoRow = $photoRes->fetch_assoc()) { $driverPhoto = $photoRow['photo'] ?? ''; }

$stmt = $conn->prepare("SELECT trips.id, trips.pickup_address AS pickup, trips.destination_address AS destination, trips.distance, trips.fare, trips.service_type, trips.payment_method, users.name AS passenger_name FROM trips INNER JOIN users ON trips.passenger_id = users.id WHERE trips.status = 'pendiente'");
$stmt->execute(); $result = $stmt->get_result(); $trips = $result->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Conductor — RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 480px; margin: 0 auto; padding: 0 0 100px; }

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
    border: 2px solid var(--ri-green); background: var(--ri-surface-3);
  }
  .dash-top__avatar-placeholder {
    width: 40px; height: 40px; border-radius: 50%; background: var(--ri-surface-3);
    border: 2px solid var(--ri-line); display: flex; align-items: center; justify-content: center; color: var(--ri-mute);
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

  .dash-content { padding: 20px; }
  .greeting { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
  .greeting-sub { font-size: 14px; color: var(--ri-mute); margin: 0 0 20px; }

  /* Online status */
  .online-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--ri-green-soft); color: var(--ri-green);
    padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
    margin-bottom: 20px;
  }
  .online-badge__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ri-green); animation: pulse 2s infinite; }
  @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.4; } }

  .stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 24px; }
  .stat {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 14px; padding: 14px; text-align: center;
  }
  .stat__val { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; }
  .stat__label { font-size: 11px; color: var(--ri-mute); font-weight: 500; margin-top: 2px; }

  .section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .section-head h3 { font-size: 16px; font-weight: 700; margin: 0; }

  /* Request card */
  .request {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 16px; padding: 16px; margin-bottom: 10px;
  }
  .request__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .request__passenger { font-size: 15px; font-weight: 700; }
  .request__detail { font-size: 13px; color: var(--ri-mute); margin-bottom: 4px; display: flex; align-items: flex-start; gap: 8px; }
  .request__detail strong { color: var(--ri-ink); font-weight: 600; }
  .request__actions { display: flex; gap: 8px; margin-top: 14px; }
  .request__actions form { flex: 1; }
  .request__actions button { width: 100%; }

  .bottom-nav {
    position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
    width: 100%; max-width: 480px;
    background: var(--ri-surface); border-top: 1px solid var(--ri-line);
    display: flex; justify-content: space-around; padding: 10px 0 18px; z-index: 20;
  }
  .bottom-nav a {
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    text-decoration: none; color: var(--ri-mute); font-size: 11px; font-weight: 600;
  }
  .bottom-nav a.active { color: var(--ri-ink); }
</style>
<script>setInterval(() => { window.location.reload(); }, 5000);</script>
</head>
<body>

<div class="shell">
  <header class="dash-top">
    <div class="dash-top__left">
      <?php if ($driverPhoto): ?>
        <img src="<?php echo htmlspecialchars($driverPhoto); ?>" alt="Perfil" class="dash-top__avatar">
      <?php else: ?>
        <div class="dash-top__avatar-placeholder">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
        </div>
      <?php endif; ?>
      <span class="dash-top__name"><?php echo htmlspecialchars($userName); ?></span>
    </div>
    <div class="dash-top__right">
      <a href="profile.php" class="dash-top__btn" title="Perfil">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
      </a>
      <button type="button" onclick="confirmLogout()" class="dash-top__btn" title="Cerrar sesion" style="color:var(--ri-red)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      </button>
    </div>
  </header>

  <div class="dash-content">
    <div class="online-badge">
      <span class="online-badge__dot"></span>
      En linea · Modo conductor
    </div>

    <h1 class="greeting"><?php echo htmlspecialchars("$greeting, $userName"); ?> 🚗</h1>
    <p class="greeting-sub">Administra tus viajes y ganancias.</p>

    <div class="stats">
      <div class="stat">
        <div class="stat__val" style="color:var(--ri-green)"><?php echo $completedTrips; ?></div>
        <div class="stat__label">Viajes hoy</div>
      </div>
      <div class="stat">
        <div class="stat__val" style="color:var(--ri-green)">$<?php echo number_format($dailyEarnings, 0); ?></div>
        <div class="stat__label">Ganancias</div>
      </div>
      <a href="mis_calificaciones.php" class="stat" style="text-decoration:none;color:inherit">
        <div class="stat__val" style="color:var(--ri-yellow)">★ <?php echo $promedioRating ?: '0.0'; ?></div>
        <div class="stat__label"><?php echo $totalRatings; ?> calif.</div>
      </a>
    </div>

    <div class="section-head">
      <h3>Solicitudes pendientes</h3>
      <span class="ri-badge ri-badge--blue"><?php echo count($trips); ?> activas</span>
    </div>

    <?php if (count($trips) > 0): ?>
      <?php foreach ($trips as $trip): ?>
        <?php
          $svcLabels = ['economico' => 'Economico', 'confort' => 'Confort'];
          $payLabels = ['efectivo' => 'Efectivo', 'tarjeta_debito' => 'T. Debito', 'tarjeta_credito' => 'T. Credito'];
        ?>
        <div class="request">
          <div class="request__header">
            <span class="request__passenger"><?php echo htmlspecialchars($trip['passenger_name']); ?></span>
            <span class="ri-badge ri-badge--blue"><?php echo htmlspecialchars($svcLabels[$trip['service_type'] ?? 'economico'] ?? 'Economico'); ?></span>
          </div>
          <div class="request__detail">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ri-green)" stroke-width="2.5" style="flex:none;margin-top:1px"><circle cx="12" cy="12" r="4"/></svg>
            <span><?php echo htmlspecialchars($trip['pickup']); ?></span>
          </div>
          <div class="request__detail">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ri-blue)" stroke-width="2.5" style="flex:none;margin-top:1px"><path d="M12 22s-8-7.5-8-13a8 8 0 1 1 16 0c0 5.5-8 13-8 13z"/></svg>
            <span><?php echo htmlspecialchars($trip['destination']); ?></span>
          </div>
          <div style="display:flex;gap:16px;margin-top:8px;font-size:13px;color:var(--ri-mute)">
            <span><?php echo $trip['distance']; ?> km</span>
            <span>$<?php echo $trip['fare']; ?></span>
            <span><?php echo htmlspecialchars($payLabels[$trip['payment_method'] ?? 'efectivo'] ?? 'Efectivo'); ?></span>
          </div>
          <div class="request__actions">
            <form id="accept-<?php echo (int)$trip['id']; ?>" action="accept_trip.php" method="POST">
              <?php echo csrfField(); ?>
              <input type="hidden" name="trip_id" value="<?php echo (int)$trip['id']; ?>">
              <button type="button" onclick="confirmAccept(<?php echo (int)$trip['id']; ?>, '<?php echo htmlspecialchars(addslashes($trip['passenger_name'])); ?>')" class="ri-btn ri-btn--green ri-btn--block" style="height:44px;font-size:14px">
                Aceptar
              </button>
            </form>
            <form id="reject-<?php echo (int)$trip['id']; ?>" action="reject_trip.php" method="POST">
              <?php echo csrfField(); ?>
              <input type="hidden" name="trip_id" value="<?php echo (int)$trip['id']; ?>">
              <button type="button" onclick="confirmReject(<?php echo (int)$trip['id']; ?>, '<?php echo htmlspecialchars(addslashes($trip['passenger_name'])); ?>')" class="ri-btn ri-btn--ghost ri-btn--block" style="height:44px;font-size:14px;color:var(--ri-red);border-color:var(--ri-red-soft)">
                Rechazar
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding:40px 0; color:var(--ri-mute); font-size:14px;">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--ri-line)" stroke-width="1.5" style="margin:0 auto 12px;display:block"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
        No hay solicitudes pendientes.<br>
        <span style="font-size:12px">Se actualiza automaticamente cada 5 segundos.</span>
      </div>
    <?php endif; ?>
  </div>

  <nav class="bottom-nav">
    <a href="dashboard.php" class="active">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8M5 9.5V21h14V9.5"/></svg>
      Inicio
    </a>
    <a href="history.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
      Historial
    </a>
    <a href="mis_calificaciones.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15 8.5 22 9.3 17 14 18.2 21 12 17.5 5.8 21 7 14 2 9.3 9 8.5 12 2"/></svg>
      Calificaciones
    </a>
    <a href="profile.php">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
      Perfil
    </a>
  </nav>
</div>

<script src="modal.js"></script>
<script>
function confirmAccept(tripId, passengerName) {
    RideIt.confirm({
        title: 'Aceptar viaje',
        message: '¿Deseas aceptar el viaje de <strong>' + passengerName + '</strong>? Seras redirigido al seguimiento.',
        type: 'success', confirmText: 'Aceptar viaje', confirmClass: 'btn-success',
        onConfirm: () => document.getElementById('accept-' + tripId).submit()
    });
}
function confirmReject(tripId, passengerName) {
    RideIt.confirm({
        title: 'Rechazar viaje',
        message: '¿Estas seguro de rechazar el viaje de <strong>' + passengerName + '</strong>?',
        type: 'danger', confirmText: 'Rechazar',
        onConfirm: () => document.getElementById('reject-' + tripId).submit()
    });
}
function confirmLogout() {
    RideIt.confirm({
        title: 'Cerrar sesion', message: '¿Deseas cerrar tu sesion?',
        type: 'warning', confirmText: 'Cerrar sesion', confirmClass: 'btn-danger',
        onConfirm: () => window.location.href = 'logout.php'
    });
}
</script>
</body>
</html>
