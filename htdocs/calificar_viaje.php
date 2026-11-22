<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_pasajero.php"); exit; }
if (($_SESSION['user_role'] ?? '') !== 'pasajero') { header("Location: dashboard.php"); exit; }

$userId = $_SESSION['user_id'];
$tripId = isset($_GET['trip_id']) ? (int)$_GET['trip_id'] : 0;
$mensaje = ""; $error = "";

$stmt = $conn->prepare("SELECT id, destination_address, fare, rating, driver_id FROM trips WHERE id = ? AND passenger_id = ? AND status = 'completado'");
$stmt->bind_param("ii", $tripId, $userId); $stmt->execute();
$trip = $stmt->get_result()->fetch_assoc();
if (!$trip) { header("Location: dashboardpa.php"); exit; }
if (!is_null($trip['rating'])) { $error = "Este viaje ya fue calificado."; }

$driverName = "Desconocido";
if ($trip['driver_id']) {
    $stmt2 = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt2->bind_param("i", $trip['driver_id']); $stmt2->execute();
    if ($row2 = $stmt2->get_result()->fetch_assoc()) { $driverName = $row2['name']; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_null($trip['rating'])) {
    if (!validateCsrfToken()) { $error = "Token de seguridad invalido."; }
    else {
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = trim($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) { $error = "Selecciona una calificacion valida (1-5 estrellas)."; }
        else {
            $stmt = $conn->prepare("UPDATE trips SET rating = ?, comment = ? WHERE id = ? AND passenger_id = ?");
            $stmt->bind_param("isii", $rating, $comment, $tripId, $userId);
            if ($stmt->execute()) { $mensaje = "Gracias por tu calificacion."; $trip['rating'] = $rating; }
            else { $error = "Error al guardar la calificacion."; }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Calificar viaje — RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { min-height: 100vh; background: var(--ri-surface); display: flex; flex-direction: column; }
  .container { max-width: 420px; margin: 0 auto; width: 100%; padding: 24px 28px 48px; flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .topbar { width: 100%; display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
  .topbar__back {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid var(--ri-line); background: var(--ri-surface);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: var(--ri-ink);
  }
  .topbar__title { font-size: 17px; font-weight: 700; }
  .rate-card {
    width: 100%; background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 20px; padding: 32px 24px; text-align: center;
    box-shadow: var(--ri-shadow-md);
  }
  .rate-card h1 { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 8px; }
  .rate-card .dest { font-size: 14px; color: var(--ri-mute); margin: 0 0 4px; }
  .rate-card .info { font-size: 13px; color: var(--ri-ink-2); margin: 0 0 24px; }
  .stars { display: flex; justify-content: center; gap: 8px; margin-bottom: 8px; }
  .star {
    cursor: pointer; font-size: 36px; color: var(--ri-line);
    transition: color .15s, transform .1s; user-select: none;
  }
  .star.active, .star:hover { color: #f59e0b; transform: scale(1.15); }
  .rating-text { font-size: 13px; color: var(--ri-mute); margin-bottom: 20px; min-height: 18px; }
  .field { text-align: left; margin-bottom: 16px; width: 100%; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .field textarea {
    width: 100%; min-height: 80px; padding: 12px 16px;
    background: var(--ri-surface-3); border: 1.5px solid transparent;
    border-radius: 12px; font: inherit; font-size: 14px; color: var(--ri-ink);
    resize: vertical;
  }
  .field textarea:focus { outline: none; border-color: var(--ri-ink); background: var(--ri-surface); }
  .field textarea::placeholder { color: var(--ri-mute); }
</style>
</head>
<body>

<div class="container">
  <div class="topbar">
    <a href="dashboardpa.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Calificar viaje</span>
  </div>

  <div class="rate-card">
    <h1>¿Como estuvo tu viaje?</h1>
    <p class="dest"><?php echo htmlspecialchars($trip['destination_address']); ?></p>
    <p class="info"><?php echo htmlspecialchars($driverName); ?> · $<?php echo number_format($trip['fare'], 2); ?></p>

    <?php if ($mensaje): ?>
      <div class="ri-alert ri-alert--success" style="margin-bottom:16px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
        <?php echo htmlspecialchars($mensaje); ?>
      </div>
      <a href="dashboardpa.php" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:12px">Volver al inicio</a>
    <?php elseif ($error): ?>
      <div class="ri-alert ri-alert--error" style="margin-bottom:16px">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        <?php echo htmlspecialchars($error); ?>
      </div>
      <a href="dashboardpa.php" class="ri-btn ri-btn--lg ri-btn--block" style="margin-top:12px">Volver al inicio</a>
    <?php else: ?>
      <form action="calificar_viaje.php?trip_id=<?php echo $tripId; ?>" method="POST">
        <?php echo csrfField(); ?>
        <div class="stars" id="stars">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star" data-value="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">&#9733;</span>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-input" value="0">
        <div class="rating-text" id="rating-text">Selecciona una calificacion</div>

        <div class="field">
          <label for="comment">Comentario (opcional)</label>
          <textarea name="comment" id="comment" placeholder="¿Como fue tu experiencia?"></textarea>
        </div>

        <button type="button" onclick="confirmRating()" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:#f59e0b;margin-top:8px">
          Enviar calificacion
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<script src="modal.js"></script>
<script>
const labels = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];
function setRating(value) {
    document.getElementById('rating-input').value = value;
    document.getElementById('rating-text').textContent = labels[value];
    document.querySelectorAll('.star').forEach(s => {
        s.classList.toggle('active', parseInt(s.dataset.value) <= value);
    });
}
function confirmRating() {
    const rating = parseInt(document.getElementById('rating-input').value);
    if (rating < 1 || rating > 5) {
        RideIt.alert({ title: 'Selecciona una calificacion', message: 'Debes seleccionar de 1 a 5 estrellas.', type: 'warning' });
        return;
    }
    const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
    RideIt.confirm({
        title: 'Enviar calificacion',
        message: '<div style="font-size:1.5rem;color:#f59e0b;letter-spacing:4px;">' + stars + '</div><br>' + labels[rating] + '<br><br>¿Deseas enviar esta calificacion?',
        type: 'info', confirmText: 'Enviar', confirmClass: 'btn-success',
        onConfirm: () => document.querySelector('form').submit()
    });
}
</script>
</body>
</html>
