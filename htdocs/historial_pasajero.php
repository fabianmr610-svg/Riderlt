<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_pasajero.php"); exit; }
if (($_SESSION['user_role'] ?? '') !== 'pasajero') { header("Location: dashboard.php"); exit; }

$passengerId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT t.id, t.completed_at, t.fare, t.status, t.pickup_address, t.destination_address, t.rating, t.service_type, t.payment_method, t.payment_status, d.name AS driver_name FROM trips t LEFT JOIN users d ON t.driver_id = d.id WHERE t.passenger_id = ? ORDER BY t.completed_at DESC");
$stmt->bind_param("i", $passengerId); $stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Historial — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 560px; margin: 0 auto; padding: 0 0 48px; }
  .top {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; position: sticky; top: 0; z-index: 10;
    background: rgba(250,250,250,.88); backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--ri-line-2);
  }
  .top__back {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid var(--ri-line); background: var(--ri-surface);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: var(--ri-ink);
  }
  .top__title { font-size: 17px; font-weight: 700; }
  .content { padding: 20px; }
  .content h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 16px; }

  .trip-card {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 14px; padding: 16px; margin-bottom: 10px;
  }
  .trip-card__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
  .trip-card__date { font-size: 13px; color: var(--ri-mute); }
  .trip-card__route { font-size: 13px; color: var(--ri-ink-2); margin-bottom: 4px; display: flex; gap: 8px; align-items: flex-start; }
  .trip-card__route svg { flex: none; margin-top: 2px; }
  .trip-card__footer { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--ri-line-2); }
  .trip-card__fare { font-size: 16px; font-weight: 700; }
  .trip-card__driver { font-size: 12px; color: var(--ri-mute); }
  .trip-card__actions { display: flex; gap: 8px; align-items: center; }
</style>
</head>
<body>

<div class="shell">
  <header class="top">
    <a href="dashboardpa.php" class="top__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="top__title">Historial de viajes</span>
  </header>

  <div class="content">
    <h1>Tus viajes</h1>

    <?php if (count($trips) > 0): ?>
      <?php foreach ($trips as $trip): ?>
        <?php
          $serviceLabels = ['economico' => 'Economico', 'confort' => 'Confort'];
          $payLabels = ['efectivo' => 'Efectivo', 'tarjeta_debito' => 'T. Debito', 'tarjeta_credito' => 'T. Credito'];
          $statusLabel = ucfirst($trip['status']);
          $statusClass = 'ri-badge--mute';
          if ($trip['status'] === 'completado') $statusClass = 'ri-badge--green';
          elseif ($trip['status'] === 'pendiente') $statusClass = 'ri-badge--yellow';
          elseif ($trip['status'] === 'rechazado') $statusClass = 'ri-badge--red';
          elseif ($trip['status'] === 'asignado') $statusClass = 'ri-badge--blue';
        ?>
        <div class="trip-card">
          <div class="trip-card__header">
            <span class="trip-card__date">
              <?php echo !empty($trip['completed_at']) ? date("d/m/Y", strtotime($trip['completed_at'])) : "Sin fecha"; ?>
            </span>
            <div style="display:flex;gap:6px">
              <span class="ri-badge ri-badge--blue"><?php echo htmlspecialchars($serviceLabels[$trip['service_type'] ?? 'economico'] ?? 'Economico'); ?></span>
              <span class="ri-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
            </div>
          </div>

          <div class="trip-card__route">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ri-green)" stroke-width="2.5"><circle cx="12" cy="12" r="4"/></svg>
            <span><?php echo htmlspecialchars($trip['pickup_address'] ?? 'No disponible'); ?></span>
          </div>
          <div class="trip-card__route">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ri-blue)" stroke-width="2.5"><path d="M12 22s-8-7.5-8-13a8 8 0 1 1 16 0c0 5.5-8 13-8 13z"/></svg>
            <span><?php echo htmlspecialchars($trip['destination_address'] ?? 'No disponible'); ?></span>
          </div>

          <div class="trip-card__footer">
            <div>
              <div class="trip-card__fare">$<?php echo number_format($trip['fare'], 2); ?></div>
              <div class="trip-card__driver">
                <?php echo htmlspecialchars($trip['driver_name'] ?? 'Sin asignar'); ?>
                · <?php echo htmlspecialchars($payLabels[$trip['payment_method'] ?? 'efectivo'] ?? 'Efectivo'); ?>
                <?php if (($trip['payment_status'] ?? '') === 'pagado'): ?>
                  <span class="ri-badge ri-badge--green" style="font-size:10px;height:18px;padding:0 6px">Pagado</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="trip-card__actions">
              <?php if (!empty($trip['rating'])): ?>
                <span class="ri-badge ri-badge--yellow">★ <?php echo (int)$trip['rating']; ?></span>
              <?php endif; ?>
              <?php if ($trip['status'] === 'completado'): ?>
                <a href="recibo_viaje.php?trip_id=<?php echo (int)$trip['id']; ?>&from=history" class="ri-btn ri-btn--ghost" style="height:32px;padding:0 14px;font-size:12px">
                  Ver recibo
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:48px 0;color:var(--ri-mute)">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--ri-line)" stroke-width="1.5" style="margin:0 auto 12px;display:block"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
        <p style="font-size:14px">No tienes viajes en tu historial.</p>
        <a href="uberx.php" style="color:var(--ri-blue);font-weight:600;text-decoration:none;font-size:14px">Pide tu primer viaje →</a>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
