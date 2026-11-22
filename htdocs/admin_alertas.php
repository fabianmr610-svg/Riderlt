<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$mensaje = "";

// Crear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS sos_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    user_id INT NOT NULL,
    lat DOUBLE,
    lng DOUBLE,
    status VARCHAR(20) DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Cambiar estado de alerta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alert_id'])) {
    if (validateCsrfToken()) {
        $alertId = (int)$_POST['alert_id'];
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, ['atendida', 'pendiente', 'falsa_alarma'])) {
            $stmt = $conn->prepare("UPDATE sos_alerts SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $newStatus, $alertId);
            $stmt->execute();
            $mensaje = "Estado de alerta actualizado.";
        }
    }
}

// Obtener alertas
$filtroEstado = $_GET['estado'] ?? 'todos';
$where = "";
if ($filtroEstado === 'pendiente') $where = "WHERE s.status = 'pendiente'";
elseif ($filtroEstado === 'atendida') $where = "WHERE s.status = 'atendida'";

$alerts = [];
$result = $conn->query("
    SELECT s.id, s.trip_id, s.lat, s.lng, s.status, s.created_at,
           u.name AS usuario, u.phone AS telefono, u.email,
           t.pickup_address, t.destination_address, t.status AS trip_status,
           d.name AS conductor_name
    FROM sos_alerts s
    JOIN users u ON s.user_id = u.id
    JOIN trips t ON s.trip_id = t.id
    LEFT JOIN users d ON t.driver_id = d.id
    $where
    ORDER BY s.created_at DESC
");
if ($result) {
    $alerts = $result->fetch_all(MYSQLI_ASSOC);
}

$totalPendientes = $conn->query("SELECT COUNT(*) AS c FROM sos_alerts WHERE status='pendiente'")->fetch_assoc()['c'];
$totalAtendidas = $conn->query("SELECT COUNT(*) AS c FROM sos_alerts WHERE status='atendida'")->fetch_assoc()['c'];
$sosPendientes = $totalPendientes;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Alertas SOS - Admin RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .admin-wrap { display: flex; min-height: 100vh; }

  /* ── Sidebar (shared) ── */
  .sidebar {
    width: 260px; background: var(--ri-ink); color: #fff;
    display: flex; flex-direction: column; padding: 28px 20px;
    position: sticky; top: 0; height: 100vh;
  }
  .sidebar__brand { text-align: center; margin-bottom: 32px; }
  .sidebar__icon {
    width: 52px; height: 52px; border-radius: 16px;
    background: var(--ri-blue); color: #fff;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 10px;
  }
  .sidebar__brand h2 { font-size: 18px; font-weight: 800; }
  .sidebar__brand p { font-size: 12px; color: rgba(255,255,255,.5); }
  .nav-link {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-radius: 12px;
    font-size: 14px; font-weight: 600; color: rgba(255,255,255,.55);
    text-decoration: none; transition: all .2s; margin-bottom: 4px;
  }
  .nav-link:hover { background: rgba(255,255,255,.08); color: #fff; }
  .nav-link.active { background: rgba(255,255,255,.12); color: #fff; }
  .nav-link svg { flex: none; opacity: .7; }
  .nav-link.active svg { opacity: 1; }
  .nav-badge {
    margin-left: auto; background: var(--ri-red); color: #fff;
    font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px;
  }
  .sidebar__logout {
    margin-top: auto; padding: 12px; border-radius: 12px;
    background: rgba(239,68,68,.15); color: #f87171;
    border: none; font-size: 14px; font-weight: 700; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .sidebar__logout:hover { background: rgba(239,68,68,.25); }

  /* ── Main ── */
  .admin-main { flex: 1; padding: 32px; overflow-y: auto; }

  .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
  .page-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
  .header-badges { display: flex; gap: 10px; }
  .header-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 99px; font-size: 12px; font-weight: 700;
  }
  .header-badge--pending { background: #fef3c7; color: #92400e; }
  .header-badge--done { background: var(--ri-green-soft); color: var(--ri-green); }

  /* Filter tabs */
  .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; }
  .filter-tab {
    padding: 8px 18px; border-radius: 99px; font-size: 13px; font-weight: 700;
    text-decoration: none; border: 1.5px solid var(--ri-line); color: var(--ri-mute);
    background: #fff; transition: all .2s;
  }
  .filter-tab:hover { border-color: var(--ri-blue); color: var(--ri-blue); }
  .filter-tab.active { background: var(--ri-ink); color: #fff; border-color: var(--ri-ink); }
  .filter-tab.active--pending { background: #f59e0b; border-color: #f59e0b; color: #fff; }
  .filter-tab.active--done { background: var(--ri-green); border-color: var(--ri-green); color: #fff; }

  /* Alert cards */
  .alert-list { display: flex; flex-direction: column; gap: 16px; }

  .sos-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 24px; border-left: 4px solid var(--ri-green);
    transition: box-shadow .2s;
  }
  .sos-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.06); }
  .sos-card--pending { border-left-color: var(--ri-red); }
  .sos-card--false { border-left-color: var(--ri-mute); }

  @keyframes pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .4; } }
  .sos-card--pending .sos-card__head::before {
    content: ''; width: 10px; height: 10px; border-radius: 50%;
    background: var(--ri-red); display: inline-block; margin-right: 8px;
    animation: pulse-dot 1.5s infinite;
  }

  .sos-card__head {
    display: flex; align-items: center; gap: 8px; margin-bottom: 14px; flex-wrap: wrap;
  }
  .sos-card__id { font-size: 16px; font-weight: 800; color: var(--ri-ink); }
  .sos-status {
    display: inline-block; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
  }
  .sos-status--pendiente { background: #fef3c7; color: #92400e; }
  .sos-status--atendida { background: var(--ri-green-soft); color: var(--ri-green); }
  .sos-status--falsa_alarma { background: var(--ri-surface-2); color: var(--ri-mute); }

  .sos-card__body { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; font-size: 13px; color: var(--ri-ink-2); }
  .sos-card__body strong { color: var(--ri-mute); font-weight: 600; }

  .sos-card__actions {
    display: flex; gap: 10px; margin-top: 16px; flex-wrap: wrap;
    padding-top: 16px; border-top: 1px solid var(--ri-line);
  }
  .sos-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px; border: none;
    font-size: 13px; font-weight: 700; cursor: pointer;
    text-decoration: none; transition: opacity .2s;
  }
  .sos-btn:hover { opacity: .85; }
  .sos-btn--map { background: var(--ri-blue-soft); color: var(--ri-blue); }
  .sos-btn--attend { background: var(--ri-green); color: #fff; }
  .sos-btn--false { background: var(--ri-surface-2); color: var(--ri-mute); }

  .empty-state {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 48px 20px; text-align: center;
  }
  .empty-state svg { margin: 0 auto 12px; color: var(--ri-green); }
  .empty-state p { color: var(--ri-mute); font-size: 15px; }

  /* ── Mobile bottom nav ── */
  .mobile-nav {
    display: none; position: fixed; bottom: 0; left: 0; right: 0;
    background: #fff; border-top: 1px solid var(--ri-line);
    z-index: 100; padding: 6px 0 env(safe-area-inset-bottom,8px);
  }
  .mobile-nav__inner { display: flex; justify-content: space-around; }
  .mobile-nav a {
    display: flex; flex-direction: column; align-items: center; gap: 2px;
    font-size: 10px; font-weight: 600; color: var(--ri-mute); text-decoration: none;
    padding: 6px 8px; position: relative;
  }
  .mobile-nav a.active { color: var(--ri-red); }
  .mobile-nav .m-badge {
    position: absolute; top: 0; right: 2px;
    background: var(--ri-red); color: #fff;
    font-size: 9px; font-weight: 700; width: 16px; height: 16px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
  }

  @media (max-width: 768px) {
    .sidebar { display: none; }
    .mobile-nav { display: block; }
    .admin-main { padding: 20px 16px 100px; }
    .sos-card__body { grid-template-columns: 1fr; }
  }
</style>
<script>setInterval(() => { window.location.reload(); }, 15000);</script>
</head>
<body>

<div class="admin-wrap">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar__brand">
      <div class="sidebar__icon">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      </div>
      <h2>RideIt Admin</h2>
      <p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
    </div>
    <nav>
      <a href="admin_panel.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="admin_usuarios.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Usuarios
      </a>
      <a href="admin_alertas.php" class="nav-link active">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Alertas SOS
        <?php if ($sosPendientes > 0): ?><span class="nav-badge"><?php echo $sosPendientes; ?></span><?php endif; ?>
      </a>
      <a href="admin_tarifas.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Tarifas
      </a>
      <a href="admin_viajes.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        Viajes
      </a>
    </nav>
    <button onclick="window.location.href='logout_admin.php'" class="sidebar__logout">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Cerrar sesion
    </button>
  </aside>

  <!-- Main -->
  <main class="admin-main">
    <div class="page-header">
      <h1>Monitoreo de Alertas SOS</h1>
      <div class="header-badges">
        <span class="header-badge header-badge--pending">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?php echo $totalPendientes; ?> pendientes
        </span>
        <span class="header-badge header-badge--done">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          <?php echo $totalAtendidas; ?> atendidas
        </span>
      </div>
    </div>

    <?php if ($mensaje): ?>
      <div class="ri-alert ri-alert--success" style="margin-bottom:16px"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <!-- Filter tabs -->
    <div class="filter-tabs">
      <a href="admin_alertas.php?estado=todos" class="filter-tab <?php echo $filtroEstado==='todos'?'active':''; ?>">Todas</a>
      <a href="admin_alertas.php?estado=pendiente" class="filter-tab <?php echo $filtroEstado==='pendiente'?'active active--pending':''; ?>">Pendientes</a>
      <a href="admin_alertas.php?estado=atendida" class="filter-tab <?php echo $filtroEstado==='atendida'?'active active--done':''; ?>">Atendidas</a>
    </div>

    <!-- Alerts -->
    <?php if (empty($alerts)): ?>
      <div class="empty-state">
        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <p>No hay alertas SOS registradas.</p>
      </div>
    <?php else: ?>
      <div class="alert-list">
        <?php foreach ($alerts as $alert): ?>
          <div class="sos-card <?php echo $alert['status']==='pendiente'?'sos-card--pending':($alert['status']==='falsa_alarma'?'sos-card--false':''); ?>">
            <div class="sos-card__head">
              <span class="sos-card__id">Alerta #<?php echo $alert['id']; ?></span>
              <span class="sos-status sos-status--<?php echo $alert['status']; ?>">
                <?php echo ucfirst(str_replace('_',' ',$alert['status'])); ?>
              </span>
            </div>

            <div class="sos-card__body">
              <p><strong>Pasajero:</strong> <?php echo htmlspecialchars($alert['usuario']); ?></p>
              <p><strong>Telefono:</strong> <?php echo htmlspecialchars($alert['telefono']); ?></p>
              <p><strong>Conductor:</strong> <?php echo htmlspecialchars($alert['conductor_name'] ?? 'No asignado'); ?></p>
              <p><strong>GPS:</strong> <?php echo $alert['lat']; ?>, <?php echo $alert['lng']; ?></p>
              <p><strong>Ruta:</strong> <?php echo htmlspecialchars($alert['pickup_address']); ?> &rarr; <?php echo htmlspecialchars($alert['destination_address']); ?></p>
              <p><strong>Fecha:</strong> <?php echo $alert['created_at']; ?></p>
            </div>

            <div class="sos-card__actions">
              <a href="https://maps.google.com/?q=<?php echo $alert['lat']; ?>,<?php echo $alert['lng']; ?>" target="_blank" class="sos-btn sos-btn--map">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Ver en Mapa
              </a>
              <?php if ($alert['status'] === 'pendiente'): ?>
                <form id="attend-<?php echo $alert['id']; ?>" method="POST" style="display:inline">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                  <input type="hidden" name="new_status" value="atendida">
                  <button type="button" onclick="confirmAttend(<?php echo $alert['id']; ?>)" class="sos-btn sos-btn--attend">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Marcar Atendida
                  </button>
                </form>
                <form id="false-<?php echo $alert['id']; ?>" method="POST" style="display:inline">
                  <?php echo csrfField(); ?>
                  <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                  <input type="hidden" name="new_status" value="falsa_alarma">
                  <button type="button" onclick="confirmFalseAlarm(<?php echo $alert['id']; ?>)" class="sos-btn sos-btn--false">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    Falsa Alarma
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</div>

<!-- Mobile nav -->
<div class="mobile-nav">
  <div class="mobile-nav__inner">
    <a href="admin_panel.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
      Inicio
    </a>
    <a href="admin_usuarios.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Usuarios
    </a>
    <a href="admin_alertas.php" class="active" style="position:relative">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      SOS
      <?php if ($sosPendientes > 0): ?><span class="m-badge"><?php echo $sosPendientes; ?></span><?php endif; ?>
    </a>
    <a href="admin_tarifas.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Tarifas
    </a>
    <a href="admin_viajes.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
      Viajes
    </a>
  </div>
</div>

<script src="modal.js"></script>
<script>
    function confirmAttend(alertId) {
        RideIt.confirm({
            title: 'Marcar como atendida',
            message: '¿Confirmas que la alerta SOS #' + alertId + ' ha sido atendida correctamente?',
            type: 'success',
            confirmText: 'Confirmar',
            confirmClass: 'btn-success',
            onConfirm: () => document.getElementById('attend-' + alertId).submit()
        });
    }

    function confirmFalseAlarm(alertId) {
        RideIt.confirm({
            title: 'Marcar como falsa alarma',
            message: '¿Estas seguro de que la alerta #' + alertId + ' es una falsa alarma?',
            type: 'warning',
            confirmText: 'Si, es falsa alarma',
            onConfirm: () => document.getElementById('false-' + alertId).submit()
        });
    }
</script>
</body>
</html>
