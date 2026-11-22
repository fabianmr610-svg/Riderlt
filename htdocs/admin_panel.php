<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$adminName = $_SESSION['user_name'];

// Estadisticas generales
$totalPasajeros = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='pasajero'")->fetch_assoc()['c'];
$totalConductores = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='conductor'")->fetch_assoc()['c'];
$totalViajes = $conn->query("SELECT COUNT(*) AS c FROM trips")->fetch_assoc()['c'];
$viajesCompletados = $conn->query("SELECT COUNT(*) AS c FROM trips WHERE status='completado'")->fetch_assoc()['c'];
$viajesPendientes = $conn->query("SELECT COUNT(*) AS c FROM trips WHERE status='pendiente'")->fetch_assoc()['c'];

$ingresosTotales = $conn->query("SELECT COALESCE(SUM(fare),0) AS t FROM trips WHERE status='completado'")->fetch_assoc()['t'];

// Alertas SOS pendientes
$sosPendientes = 0;
$tableCheck = $conn->query("SHOW TABLES LIKE 'sos_alerts'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $sosPendientes = $conn->query("SELECT COUNT(*) AS c FROM sos_alerts WHERE status='pendiente'")->fetch_assoc()['c'];
}

// Tarifas actuales
require_once 'config.php';
$tarifaBase = TARIFA_BASE;
$tarifaPorKm = TARIFA_POR_KM;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Admin - RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
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
    transition: background .2s;
  }
  .sidebar__logout:hover { background: rgba(239,68,68,.25); }

  /* ── Main ── */
  .admin-main { flex: 1; padding: 32px; overflow-y: auto; }
  .admin-main h1 { font-size: 26px; font-weight: 800; letter-spacing: -.02em; margin: 0 0 4px; }
  .admin-main .sub { font-size: 14px; color: var(--ri-mute); margin-bottom: 28px; }

  /* Stats */
  .stats { display: grid; grid-template-columns: repeat(auto-fill,minmax(160px,1fr)); gap: 16px; margin-bottom: 32px; }
  .stat {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 20px; text-align: center; transition: transform .15s;
  }
  .stat:hover { transform: translateY(-2px); }
  .stat__val { font-size: 32px; font-weight: 800; letter-spacing: -.02em; }
  .stat__label { font-size: 12px; color: var(--ri-mute); font-weight: 600; margin-top: 2px; }
  .stat--blue .stat__val { color: var(--ri-blue); }
  .stat--green .stat__val { color: var(--ri-green); }
  .stat--purple .stat__val { color: #8b5cf6; }
  .stat--emerald .stat__val { color: #10b981; }
  .stat--amber .stat__val { color: #f59e0b; }
  .stat--cyan .stat__val { color: #06b6d4; }

  /* Quick actions */
  .section-title { font-size: 16px; font-weight: 700; margin-bottom: 16px; }
  .actions { display: grid; grid-template-columns: repeat(auto-fill,minmax(240px,1fr)); gap: 16px; margin-bottom: 32px; }
  .action {
    display: flex; align-items: center; gap: 16px;
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 20px; text-decoration: none; color: var(--ri-ink);
    transition: border-color .2s, box-shadow .2s;
  }
  .action:hover { border-color: var(--ri-blue); box-shadow: 0 4px 16px rgba(0,0,0,.06); }
  .action__icon {
    width: 48px; height: 48px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center; flex: none;
  }
  .action__icon--blue { background: var(--ri-blue-soft); color: var(--ri-blue); }
  .action__icon--red { background: #fef2f2; color: var(--ri-red); }
  .action__icon--green { background: var(--ri-green-soft); color: var(--ri-green); }
  .action__icon--purple { background: #f5f3ff; color: #8b5cf6; }
  .action__title { font-size: 15px; font-weight: 700; }
  .action__desc { font-size: 12px; color: var(--ri-mute); }

  /* Tarifa card */
  .tarifa-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 24px; margin-bottom: 32px;
  }
  .tarifa-grid { display: flex; gap: 32px; flex-wrap: wrap; margin-top: 12px; }
  .tarifa-item { }
  .tarifa-item__label { font-size: 12px; color: var(--ri-mute); font-weight: 600; }
  .tarifa-item__val { font-size: 28px; font-weight: 800; color: var(--ri-green); }
  .tarifa-item--example .tarifa-item__val { color: var(--ri-blue); }

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
  .mobile-nav a.active { color: var(--ri-blue); }
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
      <p><?php echo htmlspecialchars($adminName); ?></p>
    </div>

    <nav>
      <a href="admin_panel.php" class="nav-link active">
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
        <?php if ($sosPendientes > 0): ?>
          <span class="nav-badge"><?php echo $sosPendientes; ?></span>
        <?php endif; ?>
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

    <button onclick="confirmAdminLogout()" class="sidebar__logout">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
      Cerrar sesion
    </button>
  </aside>

  <!-- Main -->
  <main class="admin-main">
    <h1>Dashboard General</h1>
    <p class="sub">Resumen de la plataforma RideIt</p>

    <!-- Stats -->
    <div class="stats">
      <div class="stat stat--blue">
        <div class="stat__val"><?php echo $totalPasajeros; ?></div>
        <div class="stat__label">Pasajeros</div>
      </div>
      <div class="stat stat--green">
        <div class="stat__val"><?php echo $totalConductores; ?></div>
        <div class="stat__label">Conductores</div>
      </div>
      <div class="stat stat--purple">
        <div class="stat__val"><?php echo $totalViajes; ?></div>
        <div class="stat__label">Viajes Total</div>
      </div>
      <div class="stat stat--emerald">
        <div class="stat__val"><?php echo $viajesCompletados; ?></div>
        <div class="stat__label">Completados</div>
      </div>
      <div class="stat stat--amber">
        <div class="stat__val"><?php echo $viajesPendientes; ?></div>
        <div class="stat__label">Pendientes</div>
      </div>
      <div class="stat stat--cyan">
        <div class="stat__val">$<?php echo number_format($ingresosTotales, 2); ?></div>
        <div class="stat__label">Ingresos</div>
      </div>
    </div>

    <!-- Quick actions -->
    <h2 class="section-title">Acceso Rapido</h2>
    <div class="actions">
      <a href="admin_usuarios.php" class="action">
        <div class="action__icon action__icon--blue">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div class="action__title">Gestionar Usuarios</div>
          <div class="action__desc">Pasajeros y conductores</div>
        </div>
      </a>
      <a href="admin_alertas.php" class="action">
        <div class="action__icon action__icon--red">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
          <div class="action__title">Alertas SOS</div>
          <div class="action__desc"><?php echo $sosPendientes; ?> pendientes</div>
        </div>
      </a>
      <a href="admin_tarifas.php" class="action">
        <div class="action__icon action__icon--green">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div>
          <div class="action__title">Tarifas</div>
          <div class="action__desc">Base: $<?php echo $tarifaBase; ?> + $<?php echo $tarifaPorKm; ?>/km</div>
        </div>
      </a>
      <a href="admin_viajes.php" class="action">
        <div class="action__icon action__icon--purple">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        </div>
        <div>
          <div class="action__title">Ver Viajes</div>
          <div class="action__desc">Historial completo</div>
        </div>
      </a>
    </div>

    <!-- Tarifas actuales -->
    <div class="tarifa-card">
      <h2 class="section-title" style="margin-bottom:0">Tarifas Actuales</h2>
      <div class="tarifa-grid">
        <div class="tarifa-item">
          <div class="tarifa-item__label">Tarifa Base</div>
          <div class="tarifa-item__val">$<?php echo number_format($tarifaBase, 2); ?></div>
        </div>
        <div class="tarifa-item">
          <div class="tarifa-item__label">Por Kilometro</div>
          <div class="tarifa-item__val">$<?php echo number_format($tarifaPorKm, 2); ?></div>
        </div>
        <div class="tarifa-item tarifa-item--example">
          <div class="tarifa-item__label">Ejemplo 10 km</div>
          <div class="tarifa-item__val">$<?php echo number_format($tarifaBase + $tarifaPorKm * 10, 2); ?></div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Mobile nav -->
<div class="mobile-nav">
  <div class="mobile-nav__inner">
    <a href="admin_panel.php" class="active">
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
    <a href="admin_viajes.php">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
      Viajes
    </a>
  </div>
</div>

<script src="modal.js"></script>
<script>
    function confirmAdminLogout() {
        RideIt.confirm({
            title: 'Cerrar sesion de administrador',
            message: '¿Deseas cerrar tu sesion de administrador?',
            type: 'warning',
            confirmText: 'Cerrar sesion',
            confirmClass: 'btn-danger',
            onConfirm: () => window.location.href = 'logout_admin.php'
        });
    }
</script>
</body>
</html>
