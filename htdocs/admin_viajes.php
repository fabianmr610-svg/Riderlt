<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$filtro = $_GET['estado'] ?? 'todos';
$where = "";
if ($filtro === 'pendiente') $where = "WHERE t.status = 'pendiente'";
elseif ($filtro === 'asignado') $where = "WHERE t.status = 'asignado'";
elseif ($filtro === 'completado') $where = "WHERE t.status = 'completado'";
elseif ($filtro === 'en_destino') $where = "WHERE t.status = 'en_destino'";

$result = $conn->query("
    SELECT t.id, t.pickup_address, t.destination_address, t.distance, t.fare, t.status,
           t.service_type, t.payment_method, t.payment_status,
           t.rating, t.created_at, t.completed_at,
           p.name AS pasajero, d.name AS conductor
    FROM trips t
    LEFT JOIN users p ON t.passenger_id = p.id
    LEFT JOIN users d ON t.driver_id = d.id
    $where
    ORDER BY t.created_at DESC
    LIMIT 100
");
$viajes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// SOS pendientes para badge
$sosPendientes = 0;
$tableCheck = $conn->query("SHOW TABLES LIKE 'sos_alerts'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $sosPendientes = $conn->query("SELECT COUNT(*) AS c FROM sos_alerts WHERE status='pendiente'")->fetch_assoc()['c'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Viajes - Admin RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .admin-wrap { display: flex; min-height: 100vh; }

  /* ── Sidebar ── */
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
  .page-header .count { font-size: 13px; color: var(--ri-mute); font-weight: 600; }

  /* Filter tabs */
  .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
  .filter-tab {
    padding: 8px 18px; border-radius: 99px; font-size: 13px; font-weight: 700;
    text-decoration: none; border: 1.5px solid var(--ri-line); color: var(--ri-mute);
    background: #fff; transition: all .2s;
  }
  .filter-tab:hover { border-color: var(--ri-blue); color: var(--ri-blue); }
  .filter-tab.active { background: var(--ri-ink); color: #fff; border-color: var(--ri-ink); }
  .filter-tab.active--yellow { background: #f59e0b; border-color: #f59e0b; color: #fff; }
  .filter-tab.active--blue { background: var(--ri-blue); border-color: var(--ri-blue); color: #fff; }
  .filter-tab.active--purple { background: #8b5cf6; border-color: #8b5cf6; color: #fff; }
  .filter-tab.active--green { background: var(--ri-green); border-color: var(--ri-green); color: #fff; }

  /* Table */
  .table-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    overflow: hidden;
  }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead { background: var(--ri-surface-2); }
  th { padding: 12px 14px; text-align: left; font-weight: 700; color: var(--ri-mute); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
  td { padding: 14px 14px; border-top: 1px solid var(--ri-line); color: var(--ri-ink-2); }
  tr:hover td { background: var(--ri-surface); }
  .td-name { font-weight: 700; color: var(--ri-ink); }
  .td-addr { font-size: 12px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .td-fare { color: var(--ri-green); font-weight: 700; }

  .status-chip {
    display: inline-block; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
  }
  .status-chip--pendiente { background: #fef3c7; color: #92400e; }
  .status-chip--asignado { background: var(--ri-blue-soft); color: var(--ri-blue); }
  .status-chip--afuera { background: #fff7ed; color: #c2410c; }
  .status-chip--en_destino { background: #f5f3ff; color: #7c3aed; }
  .status-chip--completado { background: var(--ri-green-soft); color: var(--ri-green); }
  .status-chip--rechazado { background: #fef2f2; color: var(--ri-red); }

  .rating-stars { color: #f59e0b; font-weight: 700; }

  .empty-state { padding: 48px 16px; text-align: center; color: var(--ri-mute); }
  .empty-state svg { margin: 0 auto 12px; opacity: .4; }

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
  .mobile-nav a.active { color: #8b5cf6; }
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
  }
</style>
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
      <a href="admin_alertas.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Alertas SOS
        <?php if ($sosPendientes > 0): ?><span class="nav-badge"><?php echo $sosPendientes; ?></span><?php endif; ?>
      </a>
      <a href="admin_tarifas.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        Tarifas
      </a>
      <a href="admin_viajes.php" class="nav-link active">
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
      <h1>Historial de Viajes</h1>
      <span class="count"><?php echo count($viajes); ?> viajes</span>
    </div>

    <!-- Filter tabs -->
    <div class="filter-tabs">
      <a href="admin_viajes.php?estado=todos" class="filter-tab <?php echo $filtro==='todos'?'active':''; ?>">Todos</a>
      <a href="admin_viajes.php?estado=pendiente" class="filter-tab <?php echo $filtro==='pendiente'?'active active--yellow':''; ?>">Pendientes</a>
      <a href="admin_viajes.php?estado=asignado" class="filter-tab <?php echo $filtro==='asignado'?'active active--blue':''; ?>">Asignados</a>
      <a href="admin_viajes.php?estado=en_destino" class="filter-tab <?php echo $filtro==='en_destino'?'active active--purple':''; ?>">En Destino</a>
      <a href="admin_viajes.php?estado=completado" class="filter-tab <?php echo $filtro==='completado'?'active active--green':''; ?>">Completados</a>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Pasajero</th>
              <th>Conductor</th>
              <th>Origen</th>
              <th>Destino</th>
              <th>Dist.</th>
              <th>Tarifa</th>
              <th>Estado</th>
              <th>Calif.</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($viajes)): ?>
              <tr><td colspan="10">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                  <p>No hay viajes registrados.</p>
                </div>
              </td></tr>
            <?php else: ?>
              <?php foreach ($viajes as $v): ?>
                <tr>
                  <td style="color:var(--ri-mute)"><?php echo $v['id']; ?></td>
                  <td class="td-name"><?php echo htmlspecialchars($v['pasajero'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($v['conductor'] ?? 'Sin asignar'); ?></td>
                  <td class="td-addr" title="<?php echo htmlspecialchars($v['pickup_address']); ?>"><?php echo htmlspecialchars($v['pickup_address']); ?></td>
                  <td class="td-addr" title="<?php echo htmlspecialchars($v['destination_address']); ?>"><?php echo htmlspecialchars($v['destination_address']); ?></td>
                  <td><?php echo $v['distance']; ?> km</td>
                  <td class="td-fare">$<?php echo number_format($v['fare'], 2); ?></td>
                  <td>
                    <span class="status-chip status-chip--<?php echo $v['status']; ?>">
                      <?php echo ucfirst(str_replace('_',' ',$v['status'])); ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($v['rating']): ?>
                      <span class="rating-stars"><?php echo $v['rating']; ?></span>
                    <?php else: ?>
                      <span style="color:var(--ri-line)">-</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px;color:var(--ri-mute)"><?php echo date('d/m/Y H:i', strtotime($v['created_at'])); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
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
    <a href="admin_alertas.php" style="position:relative">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      SOS
      <?php if ($sosPendientes > 0): ?><span class="m-badge"><?php echo $sosPendientes; ?></span><?php endif; ?>
    </a>
    <a href="admin_tarifas.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Tarifas
    </a>
    <a href="admin_viajes.php" class="active">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
      Viajes
    </a>
  </div>
</div>

</body>
</html>
