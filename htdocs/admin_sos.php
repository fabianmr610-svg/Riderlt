<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

// Verificar que la tabla existe
$tableExists = $conn->query("SHOW TABLES LIKE 'sos_alerts'");
$alerts = [];

if ($tableExists && $tableExists->num_rows > 0) {
    $result = $conn->query("
        SELECT s.id, s.trip_id, s.lat, s.lng, s.status, s.created_at,
               u.name AS usuario, u.phone AS telefono,
               t.pickup_address, t.destination_address
        FROM sos_alerts s
        JOIN users u ON s.user_id = u.id
        JOIN trips t ON s.trip_id = t.id
        ORDER BY s.created_at DESC
    ");
    if ($result) {
        $alerts = $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Alertas SOS - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 700px; margin: 0 auto; padding: 0 16px 48px; }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; gap: 12px;
    padding: 14px 0;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  }
  .topbar__back {
    width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--ri-line);
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--ri-ink); cursor: pointer; text-decoration: none;
  }
  .topbar__title { font-size: 17px; font-weight: 700; }

  .alert-list { display: flex; flex-direction: column; gap: 12px; margin-top: 16px; }

  .sos-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 20px; border-left: 4px solid var(--ri-red);
  }
  .sos-card__head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px;
  }
  .sos-card__id { font-size: 16px; font-weight: 800; color: var(--ri-ink); }
  .sos-status {
    display: inline-block; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
  }
  .sos-status--pendiente { background: #fef3c7; color: #92400e; }
  .sos-status--atendida { background: var(--ri-green-soft); color: var(--ri-green); }

  .sos-card__body { font-size: 13px; color: var(--ri-ink-2); }
  .sos-card__body p { margin-bottom: 6px; }
  .sos-card__body strong { color: var(--ri-mute); font-weight: 600; }

  .sos-btn--map {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px;
    background: var(--ri-blue-soft); color: var(--ri-blue);
    font-size: 13px; font-weight: 700; text-decoration: none;
    margin-top: 10px;
  }

  .empty-state {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 48px 20px; text-align: center; margin-top: 16px;
  }
  .empty-state svg { margin: 0 auto 12px; color: var(--ri-green); }
  .empty-state p { color: var(--ri-mute); font-size: 15px; }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="admin_panel.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Alertas SOS</span>
  </div>

  <?php if (empty($alerts)): ?>
    <div class="empty-state">
      <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <p>No hay alertas SOS registradas.</p>
    </div>
  <?php else: ?>
    <div class="alert-list">
      <?php foreach ($alerts as $alert): ?>
        <div class="sos-card">
          <div class="sos-card__head">
            <span class="sos-card__id">Alerta #<?php echo $alert['id']; ?></span>
            <span class="sos-status sos-status--<?php echo $alert['status']; ?>">
              <?php echo ucfirst($alert['status']); ?>
            </span>
          </div>
          <div class="sos-card__body">
            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($alert['usuario']); ?></p>
            <p><strong>Telefono:</strong> <?php echo htmlspecialchars($alert['telefono']); ?></p>
            <p><strong>Viaje:</strong> <?php echo htmlspecialchars($alert['pickup_address']); ?> &rarr; <?php echo htmlspecialchars($alert['destination_address']); ?></p>
            <p><strong>GPS:</strong> <?php echo $alert['lat']; ?>, <?php echo $alert['lng']; ?></p>
            <p><strong>Fecha:</strong> <?php echo $alert['created_at']; ?></p>
          </div>
          <a href="https://maps.google.com/?q=<?php echo $alert['lat']; ?>,<?php echo $alert['lng']; ?>" target="_blank" class="sos-btn--map">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Ver en Mapa
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

</body>
</html>
