<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_pasajero.php"); exit; }
if (($_SESSION['user_role'] ?? '') !== 'pasajero') { header("Location: dashboard.php"); exit; }

$userId = $_SESSION['user_id'];
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

$stmt = $conn->prepare("SELECT t.id, t.pickup_address, t.destination_address, t.distance, t.fare, t.service_type, t.payment_method, t.payment_status, t.completed_at, t.created_at, t.driver_id, d.name AS driver_name FROM trips t LEFT JOIN users d ON t.driver_id = d.id WHERE t.id = ? AND t.passenger_id = ? AND t.status = 'completado'");
$stmt->bind_param("ii", $tripId, $userId); $stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
if (!$trip) { header("Location: dashboardpa.php"); exit; }

$stmtUser = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId); $stmtUser->execute();
$passengerName = ($stmtUser->get_result()->fetch_assoc())['name'] ?? 'Pasajero';

$completedDate = $trip['completed_at'] ? date('Ymd', strtotime($trip['completed_at'])) : date('Ymd');
$folio = 'RI-' . $completedDate . '-' . str_pad($trip['id'], 4, '0', STR_PAD_LEFT);

$paymentLabels = ['efectivo' => 'Efectivo', 'tarjeta_debito' => 'Tarjeta de Debito', 'tarjeta_credito' => 'Tarjeta de Credito'];
$serviceLabels = ['economico' => 'Economico', 'confort' => 'Confort'];
$isPaid = $trip['payment_status'] === 'pagado';
$fromHistory = isset($_GET['from']) && $_GET['from'] === 'history';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Recibo — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { min-height: 100vh; background: var(--ri-surface-2); display: flex; align-items: center; justify-content: center; padding: 20px; }
  .receipt {
    background: var(--ri-surface); border-radius: 20px; max-width: 420px; width: 100%;
    box-shadow: var(--ri-shadow-lg); position: relative; overflow: hidden; padding: 32px 24px;
  }
  .receipt::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(to right, var(--ri-blue), var(--ri-blue-deep));
  }
  .receipt__header { text-align: center; margin-bottom: 24px; }
  .receipt__logo { font-size: 24px; font-weight: 800; letter-spacing: -0.03em; margin: 0 0 4px; }
  .receipt__sub { font-size: 13px; color: var(--ri-mute); margin: 0 0 12px; }
  .receipt__folio {
    display: inline-block; background: var(--ri-blue-soft); color: var(--ri-blue-ink);
    font-family: var(--ri-mono); font-weight: 700; font-size: 13px;
    padding: 6px 14px; border-radius: 8px;
  }
  .receipt__row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px dashed var(--ri-line-2);
    font-size: 13px;
  }
  .receipt__row:last-child { border-bottom: none; }
  .receipt__label { color: var(--ri-mute); }
  .receipt__value { font-weight: 600; color: var(--ri-ink); text-align: right; max-width: 60%; }
  .receipt__total {
    background: var(--ri-green-soft); border: 2px solid var(--ri-green);
    border-radius: 14px; padding: 16px; text-align: center; margin: 20px 0;
  }
  .receipt__total-label { font-size: 13px; color: var(--ri-mute); margin: 0 0 4px; }
  .receipt__total-amount { font-size: 32px; font-weight: 800; color: var(--ri-green); letter-spacing: -0.02em; }
  .receipt__footer { text-align: center; font-size: 12px; color: var(--ri-mute); margin-bottom: 20px; }
  .stamp {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%) rotate(-25deg);
    font-size: 56px; font-weight: 900; color: rgba(5,122,85,.08);
    text-transform: uppercase; pointer-events: none; letter-spacing: 8px;
  }
  @media print { .no-print { display: none !important; } body { background: white; } }
</style>
</head>
<body>

<div class="receipt">
  <?php if ($isPaid): ?><div class="stamp">Pagado</div><?php endif; ?>

  <div class="receipt__header">
    <div class="receipt__logo">RideIt</div>
    <p class="receipt__sub">Comprobante de viaje</p>
    <span class="receipt__folio"><?php echo htmlspecialchars($folio); ?></span>
  </div>

  <div>
    <div class="receipt__row"><span class="receipt__label">ID Viaje</span><span class="receipt__value">#<?php echo $trip['id']; ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Fecha</span><span class="receipt__value"><?php echo $trip['completed_at'] ? date('d/m/Y H:i', strtotime($trip['completed_at'])) : 'N/A'; ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Pasajero</span><span class="receipt__value"><?php echo htmlspecialchars($passengerName); ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Conductor</span><span class="receipt__value"><?php echo htmlspecialchars($trip['driver_name'] ?? 'No asignado'); ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Servicio</span><span class="receipt__value"><?php echo htmlspecialchars($serviceLabels[$trip['service_type']] ?? 'Economico'); ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Origen</span><span class="receipt__value" style="font-size:12px"><?php echo htmlspecialchars($trip['pickup_address']); ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Destino</span><span class="receipt__value" style="font-size:12px"><?php echo htmlspecialchars($trip['destination_address']); ?></span></div>
    <div class="receipt__row"><span class="receipt__label">Distancia</span><span class="receipt__value"><?php echo number_format($trip['distance'], 2); ?> km</span></div>
    <div class="receipt__row"><span class="receipt__label">Pago</span><span class="receipt__value" style="color:var(--ri-blue)"><?php echo htmlspecialchars($paymentLabels[$trip['payment_method']] ?? 'Efectivo'); ?></span></div>
  </div>

  <div class="receipt__total">
    <p class="receipt__total-label">Monto total</p>
    <p class="receipt__total-amount">$<?php echo number_format($trip['fare'], 2); ?></p>
    <?php if ($isPaid): ?>
      <span class="ri-badge ri-badge--green" style="margin-top:8px">PAGADO</span>
    <?php else: ?>
      <span class="ri-badge ri-badge--yellow" style="margin-top:8px">PENDIENTE</span>
    <?php endif; ?>
  </div>

  <div class="receipt__footer">
    <p>Gracias por viajar con RideIt</p>
    <p>Este comprobante es valido como recibo de pago.</p>
  </div>

  <div class="no-print" style="display:flex;flex-direction:column;gap:8px">
    <?php if (!$fromHistory && $trip['payment_status'] === 'pagado'): ?>
      <a href="calificar_viaje.php?trip_id=<?php echo $tripId; ?>" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:#f59e0b">Calificar viaje</a>
    <?php endif; ?>
    <?php if ($fromHistory): ?>
      <a href="historial_pasajero.php" class="ri-btn ri-btn--lg ri-btn--block ri-btn--blue">Volver al historial</a>
    <?php else: ?>
      <a href="dashboardpa.php" class="ri-btn ri-btn--lg ri-btn--block">Ir al inicio</a>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
