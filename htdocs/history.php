<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_conductor.php");
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'conductor') {
    header("Location: dashboardpa.php");
    exit;
}

$driverId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT t.id, t.completed_at, t.fare, t.pickup_address, t.destination_address,
           t.rating, t.service_type, t.payment_method, t.payment_status, u.name AS passenger_name
    FROM trips t
    JOIN users u ON t.passenger_id = u.id
    WHERE t.driver_id = ? AND t.status = 'completado'
    ORDER BY t.completed_at DESC
");
$stmt->bind_param("i", $driverId);
$stmt->execute();
$result = $stmt->get_result();
$trips = $result->fetch_all(MYSQLI_ASSOC);

$svcLabels = ['economico' => 'Economico', 'confort' => 'Confort'];
$payLabelsHist = ['efectivo' => 'Efectivo', 'tarjeta_debito' => 'T. Debito', 'tarjeta_credito' => 'T. Credito'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Historial de Viajes - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 480px; margin: 0 auto; min-height: 100vh; background: var(--ri-surface-2); }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; gap: 12px;
    padding: 14px 20px;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--ri-line);
  }
  .topbar__back {
    width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--ri-line);
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--ri-ink); cursor: pointer; text-decoration: none;
  }
  .topbar__title { font-size: 17px; font-weight: 700; }

  .content { padding: 20px; }
  .content h2 { font-size: 22px; font-weight: 800; margin: 0 0 16px; letter-spacing: -0.02em; }

  .trip-card {
    background: #fff; border: 1px solid var(--ri-line);
    border-radius: 16px; padding: 16px; margin-bottom: 12px;
  }
  .trip-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .trip-card__date { font-size: 13px; color: var(--ri-mute); font-weight: 500; }

  .trip-card__route { margin-bottom: 10px; }
  .trip-card__row { display: flex; align-items: flex-start; gap: 8px; }
  .trip-card__dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0; }
  .trip-card__dot--green { background: var(--ri-green); }
  .trip-card__dot--blue { background: var(--ri-blue); }
  .trip-card__line { width: 2px; height: 12px; background: var(--ri-line); margin: -2px 0 -2px 3px; }
  .trip-card__addr { font-size: 13px; color: var(--ri-ink-2); word-break: break-word; }

  .trip-card__footer { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid var(--ri-line-2); flex-wrap: wrap; gap: 6px; }
  .trip-card__fare { font-size: 16px; font-weight: 800; color: var(--ri-green); }
  .trip-card__passenger { font-size: 12px; color: var(--ri-mute); }
  .trip-card__stars { color: #f59e0b; font-size: 14px; }

  .empty-state {
    text-align: center; padding: 60px 20px;
  }
  .empty-state__icon { color: var(--ri-mute); margin-bottom: 12px; }
  .empty-state p { font-size: 14px; color: var(--ri-mute); }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboard.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Historial</span>
  </div>

  <div class="content">
    <h2>Tus viajes</h2>

    <?php if (count($trips) > 0): ?>
      <?php foreach ($trips as $trip): ?>
        <div class="trip-card">
          <div class="trip-card__head">
            <span class="trip-card__date">
              <?php echo !empty($trip['completed_at']) ? date("d/m/Y", strtotime($trip['completed_at'])) : "Sin fecha"; ?>
            </span>
            <span class="ri-chip <?php echo ($trip['service_type'] ?? 'economico') === 'confort' ? 'ri-chip--blue' : ''; ?>" style="font-size:11px">
              <?php echo htmlspecialchars($svcLabels[$trip['service_type'] ?? 'economico'] ?? 'Economico'); ?>
            </span>
          </div>

          <div class="trip-card__route">
            <div class="trip-card__row">
              <div class="trip-card__dot trip-card__dot--green"></div>
              <div class="trip-card__addr"><?php echo htmlspecialchars($trip['pickup_address'] ?? 'No disponible'); ?></div>
            </div>
            <div style="margin-left:3px"><div class="trip-card__line"></div></div>
            <div class="trip-card__row">
              <div class="trip-card__dot trip-card__dot--blue"></div>
              <div class="trip-card__addr"><?php echo htmlspecialchars($trip['destination_address'] ?? 'No disponible'); ?></div>
            </div>
          </div>

          <div class="trip-card__footer">
            <div>
              <div class="trip-card__fare">$<?php echo number_format($trip['fare'], 2); ?></div>
              <div class="trip-card__passenger">
                <?php echo htmlspecialchars($trip['passenger_name']); ?>
                · <?php echo htmlspecialchars($payLabelsHist[$trip['payment_method'] ?? 'efectivo'] ?? 'Efectivo'); ?>
                <?php if (($trip['payment_status'] ?? '') === 'pagado'): ?>
                  <span class="ri-badge ri-badge--green" style="font-size:10px">Pagado</span>
                <?php endif; ?>
              </div>
            </div>
            <?php if (!empty($trip['rating'])): ?>
              <div class="trip-card__stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <span style="opacity:<?php echo $i <= (int)$trip['rating'] ? '1' : '0.2'; ?>">&#9733;</span>
                <?php endfor; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state">
        <div class="empty-state__icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        </div>
        <p>No tienes viajes completados aun.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
