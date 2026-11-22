<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_pasajero.php");
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'pasajero') {
    header("Location: dashboard.php");
    exit;
}

$userId = $_SESSION['user_id'];
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;

// Verificar que el viaje existe, pertenece al usuario y esta completado
$stmt = $conn->prepare("
    SELECT t.id, t.pickup_address, t.destination_address, t.distance, t.fare,
           t.service_type, t.payment_method, t.payment_status, t.completed_at,
           t.driver_id, d.name AS driver_name
    FROM trips t
    LEFT JOIN users d ON t.driver_id = d.id
    WHERE t.id = ? AND t.passenger_id = ? AND t.status = 'completado'
");
$stmt->bind_param("ii", $tripId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();

if (!$trip) {
    header("Location: dashboardpa.php");
    exit;
}

// Si ya esta pagado, redirigir al recibo
if ($trip['payment_status'] === 'pagado') {
    header("Location: recibo_viaje.php?trip_id=$tripId");
    exit;
}

$paymentLabels = [
    'efectivo' => 'Efectivo',
    'tarjeta_debito' => 'Tarjeta de Debito',
    'tarjeta_credito' => 'Tarjeta de Credito'
];
$serviceLabels = [
    'economico' => 'Economico',
    'confort' => 'Confort'
];

$error = '';

// Procesar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido.";
    } else {
        // Marcar como pagado
        $stmtPay = $conn->prepare("UPDATE trips SET payment_status = 'pagado' WHERE id = ? AND passenger_id = ?");
        $stmtPay->bind_param("ii", $tripId, $userId);
        if ($stmtPay->execute() && $stmtPay->affected_rows > 0) {
            header("Location: recibo_viaje.php?trip_id=$tripId");
            exit;
        } else {
            $error = "Error al procesar el pago. Intenta de nuevo.";
        }
    }
}

$payIcon = match($trip['payment_method']) {
    'tarjeta_debito', 'tarjeta_credito' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
    default => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Pago del Viaje - RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
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
  .topbar__title { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }

  .pay-card {
    margin: 24px 20px;
    background: #fff;
    border-radius: 20px;
    border: 1px solid var(--ri-line);
    overflow: hidden;
  }

  .pay-header {
    background: var(--ri-ink);
    color: #fff;
    padding: 28px 24px;
    text-align: center;
  }
  .pay-header__icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
  }
  .pay-header h2 { font-size: 20px; font-weight: 800; margin: 0 0 4px; letter-spacing: -0.02em; }
  .pay-header p { font-size: 13px; opacity: .7; margin: 0; }

  .pay-body { padding: 20px 24px; }

  .pay-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--ri-line-2);
    gap: 12px;
  }
  .pay-row:last-of-type { border-bottom: none; }
  .pay-row__label { font-size: 13px; color: var(--ri-mute); flex-shrink: 0; }
  .pay-row__value { font-size: 14px; font-weight: 600; text-align: right; word-break: break-word; }

  .pay-total {
    margin: 16px 0 0;
    background: var(--ri-green-soft);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex; justify-content: space-between; align-items: center;
  }
  .pay-total__label { font-size: 15px; font-weight: 700; color: var(--ri-ink); }
  .pay-total__amount { font-size: 26px; font-weight: 800; color: var(--ri-green); letter-spacing: -0.02em; }

  .pay-actions { padding: 0 24px 24px; }

  .route-dots { display: flex; align-items: center; gap: 8px; }
  .route-dots__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  .route-dots__dot--green { background: var(--ri-green); }
  .route-dots__dot--blue { background: var(--ri-blue); }
  .route-dots__line { width: 16px; height: 2px; background: var(--ri-line); }

  @media (max-width: 480px) {
    .pay-header { padding: 22px 18px; }
    .pay-body { padding: 16px 18px; }
    .pay-actions { padding: 0 18px 20px; }
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboardpa.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Pago del viaje</span>
  </div>

  <?php if ($error): ?>
    <div style="margin:16px 20px 0">
      <div class="ri-alert ri-alert--error"><?php echo htmlspecialchars($error); ?></div>
    </div>
  <?php endif; ?>

  <div class="pay-card">
    <div class="pay-header">
      <div class="pay-header__icon"><?php echo $payIcon; ?></div>
      <h2>Confirma tu pago</h2>
      <p>Revisa el resumen y confirma</p>
    </div>

    <div class="pay-body">
      <div class="pay-row">
        <span class="pay-row__label">Servicio</span>
        <span class="pay-row__value">
          <span class="ri-chip <?php echo ($trip['service_type'] ?? 'economico') === 'confort' ? 'ri-chip--blue' : ''; ?>">
            <?php echo htmlspecialchars($serviceLabels[$trip['service_type']] ?? 'Economico'); ?>
          </span>
        </span>
      </div>
      <div class="pay-row">
        <span class="pay-row__label">Conductor</span>
        <span class="pay-row__value"><?php echo htmlspecialchars($trip['driver_name'] ?? 'No asignado'); ?></span>
      </div>
      <div class="pay-row" style="flex-direction:column;align-items:flex-start;gap:8px">
        <div style="display:flex;align-items:center;gap:8px;width:100%">
          <span class="route-dots__dot route-dots__dot--green"></span>
          <span style="font-size:13px;color:var(--ri-ink-2);flex:1"><?php echo htmlspecialchars($trip['pickup_address']); ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;width:100%">
          <span class="route-dots__dot route-dots__dot--blue"></span>
          <span style="font-size:13px;color:var(--ri-ink-2);flex:1"><?php echo htmlspecialchars($trip['destination_address']); ?></span>
        </div>
      </div>
      <div class="pay-row">
        <span class="pay-row__label">Distancia</span>
        <span class="pay-row__value" style="color:var(--ri-blue)"><?php echo number_format($trip['distance'], 2); ?> km</span>
      </div>
      <div class="pay-row">
        <span class="pay-row__label">Metodo de pago</span>
        <span class="pay-row__value"><?php echo htmlspecialchars($paymentLabels[$trip['payment_method']] ?? 'Efectivo'); ?></span>
      </div>

      <div class="pay-total">
        <span class="pay-total__label">Total</span>
        <span class="pay-total__amount">$<?php echo number_format($trip['fare'], 2); ?></span>
      </div>
    </div>

    <div class="pay-actions">
      <form action="pagar_viaje.php?trip_id=<?php echo $tripId; ?>" method="POST" id="payment-form">
        <?php echo csrfField(); ?>
        <button type="button" onclick="confirmPayment()" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:var(--ri-green);height:54px;font-size:16px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
          Pagar $<?php echo number_format($trip['fare'], 2); ?>
        </button>
      </form>
    </div>
  </div>
</div>

<script src="modal.js"></script>
<script>
function confirmPayment() {
    const method = '<?php echo htmlspecialchars($paymentLabels[$trip['payment_method']] ?? 'Efectivo'); ?>';
    const amount = '$<?php echo number_format($trip['fare'], 2); ?>';

    RideIt.confirm({
        title: 'Confirmar pago',
        message: '¿Confirmas el pago de <strong>' + amount + '</strong> con <strong>' + method + '</strong>?',
        type: 'info',
        confirmText: 'Confirmar pago',
        confirmClass: 'btn-success',
        onConfirm: () => document.getElementById('payment-form').submit()
    });
}
</script>
</body>
</html>
