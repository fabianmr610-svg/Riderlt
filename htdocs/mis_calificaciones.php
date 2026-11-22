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

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT ROUND(AVG(rating), 1) AS promedio, COUNT(rating) AS total,
           SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) AS r5,
           SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) AS r4,
           SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) AS r3,
           SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) AS r2,
           SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) AS r1
    FROM trips WHERE driver_id = ? AND rating IS NOT NULL
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$promedio = $stats['promedio'] ?? 0;
$total = $stats['total'] ?? 0;
$r5 = $stats['r5'] ?? 0;
$r4 = $stats['r4'] ?? 0;
$r3 = $stats['r3'] ?? 0;
$r2 = $stats['r2'] ?? 0;
$r1 = $stats['r1'] ?? 0;

$stmt2 = $conn->prepare("
    SELECT t.rating, t.comment, t.destination_address, t.fare, t.completed_at, u.name AS pasajero
    FROM trips t
    JOIN users u ON t.passenger_id = u.id
    WHERE t.driver_id = ? AND t.rating IS NOT NULL
    ORDER BY t.completed_at DESC
    LIMIT 50
");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$calificaciones = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$starsLabels = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Mis Calificaciones - RideIt</title>
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

  .summary-card {
    background: #fff; border: 1px solid var(--ri-line);
    border-radius: 18px; padding: 28px; text-align: center;
    margin-bottom: 16px;
  }
  .summary-card__score { font-size: 56px; font-weight: 800; color: var(--ri-ink); letter-spacing: -0.04em; margin: 0; line-height: 1; }
  .summary-card__stars { display: flex; justify-content: center; gap: 4px; margin: 10px 0 6px; }
  .summary-card__stars .star { font-size: 24px; color: #e5e7eb; }
  .summary-card__stars .star--filled { color: #f59e0b; }
  .summary-card__total { font-size: 13px; color: var(--ri-mute); }

  .dist-card {
    background: #fff; border: 1px solid var(--ri-line);
    border-radius: 18px; padding: 20px; margin-bottom: 16px;
  }
  .dist-card h3 { font-size: 15px; font-weight: 700; margin: 0 0 14px; }
  .dist-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
  .dist-row__label { font-size: 13px; color: #f59e0b; width: 28px; text-align: right; font-weight: 600; }
  .dist-row__bar { flex: 1; height: 8px; background: var(--ri-surface-3); border-radius: 999px; overflow: hidden; }
  .dist-row__fill { height: 100%; background: #f59e0b; border-radius: 999px; transition: width .3s; }
  .dist-row__count { font-size: 12px; color: var(--ri-mute); width: 56px; }

  .reviews-card {
    background: #fff; border: 1px solid var(--ri-line);
    border-radius: 18px; padding: 20px; margin-bottom: 40px;
  }
  .reviews-card h3 { font-size: 15px; font-weight: 700; margin: 0 0 14px; }

  .review-item {
    padding: 12px 0;
    border-bottom: 1px solid var(--ri-line-2);
  }
  .review-item:last-child { border-bottom: none; }
  .review-item__head { display: flex; justify-content: space-between; align-items: flex-start; }
  .review-item__name { font-size: 14px; font-weight: 600; }
  .review-item__dest { font-size: 12px; color: var(--ri-mute); margin-top: 2px; }
  .review-item__comment { font-size: 13px; color: var(--ri-blue); font-style: italic; margin-top: 6px; }
  .review-item__date { font-size: 11px; color: var(--ri-mute); margin-top: 4px; }
  .review-item__stars { display: flex; gap: 2px; }
  .review-item__stars .star { font-size: 16px; color: #e5e7eb; }
  .review-item__stars .star--filled { color: #f59e0b; }
  .review-item__label { font-size: 11px; color: var(--ri-mute); text-align: right; }

  .empty-state { text-align: center; padding: 40px 20px; color: var(--ri-mute); font-size: 14px; }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboard.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Mis Calificaciones</span>
  </div>

  <div class="content">
    <!-- Summary -->
    <div class="summary-card">
      <p class="summary-card__score"><?php echo $promedio ?: '0.0'; ?></p>
      <div class="summary-card__stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <span class="star <?php echo $i <= round($promedio) ? 'star--filled' : ''; ?>">&#9733;</span>
        <?php endfor; ?>
      </div>
      <p class="summary-card__total"><?php echo $total; ?> calificaciones en total</p>

      <?php if ($promedio >= 4.5): ?>
        <div style="margin-top:12px"><span class="ri-chip ri-chip--green">Conductor Destacado</span></div>
      <?php elseif ($promedio >= 3.5): ?>
        <div style="margin-top:12px"><span class="ri-chip ri-chip--blue">Buen Conductor</span></div>
      <?php elseif ($total > 0): ?>
        <div style="margin-top:12px"><span class="ri-chip" style="background:#fef3c7;color:#92400e;border-color:#fde68a">Puede mejorar</span></div>
      <?php endif; ?>
    </div>

    <!-- Distribution -->
    <div class="dist-card">
      <h3>Distribucion</h3>
      <?php
      $barras = [5 => $r5, 4 => $r4, 3 => $r3, 2 => $r2, 1 => $r1];
      foreach ($barras as $star => $count):
          $pct = $total > 0 ? round(($count / $total) * 100) : 0;
      ?>
        <div class="dist-row">
          <span class="dist-row__label"><?php echo $star; ?>&#9733;</span>
          <div class="dist-row__bar">
            <div class="dist-row__fill" style="width:<?php echo $pct; ?>%"></div>
          </div>
          <span class="dist-row__count"><?php echo $count; ?> (<?php echo $pct; ?>%)</span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Recent reviews -->
    <div class="reviews-card">
      <h3>Calificaciones recientes</h3>
      <?php if (empty($calificaciones)): ?>
        <div class="empty-state">Aun no tienes calificaciones.</div>
      <?php else: ?>
        <?php foreach ($calificaciones as $cal): ?>
          <div class="review-item">
            <div class="review-item__head">
              <div>
                <div class="review-item__name"><?php echo htmlspecialchars($cal['pasajero']); ?></div>
                <div class="review-item__dest"><?php echo htmlspecialchars($cal['destination_address']); ?></div>
                <?php if (!empty($cal['comment'])): ?>
                  <div class="review-item__comment">"<?php echo htmlspecialchars($cal['comment']); ?>"</div>
                <?php endif; ?>
                <div class="review-item__date"><?php echo $cal['completed_at'] ? date('d/m/Y', strtotime($cal['completed_at'])) : '-'; ?></div>
              </div>
              <div>
                <div class="review-item__stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?php echo $i <= $cal['rating'] ? 'star--filled' : ''; ?>">&#9733;</span>
                  <?php endfor; ?>
                </div>
                <div class="review-item__label"><?php echo $starsLabels[$cal['rating']]; ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>
