<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

$mensaje = "";
$filtro = $_GET['filtro'] ?? 'todos';

// Eliminar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $mensaje = "Token invalido.";
    } else {
        $targetId = (int)$_POST['user_id'];

        if ($_POST['action'] === 'eliminar') {
            // No eliminar admins
            $check = $conn->prepare("SELECT role FROM users WHERE id = ?");
            $check->bind_param("i", $targetId);
            $check->execute();
            $r = $check->get_result()->fetch_assoc();
            if ($r && $r['role'] !== 'admin') {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("i", $targetId);
                $stmt->execute();
                $mensaje = "Usuario eliminado correctamente.";
            }
        } elseif ($_POST['action'] === 'cambiar_rol') {
            $nuevoRol = $_POST['nuevo_rol'];
            if (in_array($nuevoRol, ['pasajero', 'conductor'])) {
                $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
                $stmt->bind_param("si", $nuevoRol, $targetId);
                $stmt->execute();
                $mensaje = "Rol actualizado.";
            }
        }
    }
}

// Buscar usuarios
$buscar = trim($_GET['buscar'] ?? '');
$whereClause = "WHERE role != 'admin'";
if ($filtro === 'pasajeros') $whereClause .= " AND role = 'pasajero'";
if ($filtro === 'conductores') $whereClause .= " AND role = 'conductor'";
if (!empty($buscar)) $whereClause .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";

$sql = "SELECT id, name, email, phone, role, plate, created_at FROM users $whereClause ORDER BY created_at DESC";

if (!empty($buscar)) {
    $stmt = $conn->prepare($sql);
    $like = "%$buscar%";
    $stmt->bind_param("sss", $like, $like, $like);
} else {
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener promedios de calificacion para conductores
$ratings = [];
$ratingResult = $conn->query("
    SELECT driver_id, ROUND(AVG(rating),1) AS promedio, COUNT(rating) AS total
    FROM trips WHERE rating IS NOT NULL AND driver_id IS NOT NULL
    GROUP BY driver_id
");
if ($ratingResult) {
    while ($row = $ratingResult->fetch_assoc()) {
        $ratings[$row['driver_id']] = $row;
    }
}

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
<title>Gestion de Usuarios - Admin RideIt</title>
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
  .page-header .count { font-size: 13px; color: var(--ri-mute); font-weight: 600; }

  /* Search card */
  .search-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 16px 20px; margin-bottom: 20px;
  }
  .search-form { display: flex; gap: 10px; flex-wrap: wrap; }
  .search-form .ri-input { flex: 1; min-width: 200px; }
  .search-form select {
    padding: 0 16px; height: 48px; border-radius: 12px;
    border: 1.5px solid var(--ri-line); background: #fff;
    font-size: 14px; font-weight: 600; color: var(--ri-ink);
    cursor: pointer;
  }
  .search-form select:focus { border-color: var(--ri-blue); outline: none; }

  /* Table card */
  .table-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    overflow: hidden;
  }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead { background: var(--ri-surface-2); }
  th { padding: 12px 16px; text-align: left; font-weight: 700; color: var(--ri-mute); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
  td { padding: 14px 16px; border-top: 1px solid var(--ri-line); color: var(--ri-ink-2); }
  tr:hover td { background: var(--ri-surface); }
  .td-name { font-weight: 700; color: var(--ri-ink); }

  .role-chip {
    display: inline-block; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
  }
  .role-chip--conductor { background: var(--ri-green-soft); color: var(--ri-green); }
  .role-chip--pasajero { background: var(--ri-blue-soft); color: var(--ri-blue); }

  .rating-stars { color: #f59e0b; font-weight: 700; font-size: 13px; }
  .rating-count { color: var(--ri-mute); font-size: 11px; }

  .action-btns { display: flex; gap: 6px; justify-content: center; }
  .action-btn {
    width: 32px; height: 32px; border-radius: 8px; border: none;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: opacity .2s;
  }
  .action-btn:hover { opacity: .8; }
  .action-btn--role { background: var(--ri-blue-soft); color: var(--ri-blue); }
  .action-btn--delete { background: #fef2f2; color: var(--ri-red); }

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
      <p><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
    </div>
    <nav>
      <a href="admin_panel.php" class="nav-link">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </a>
      <a href="admin_usuarios.php" class="nav-link active">
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
      <h1>Gestion de Usuarios</h1>
      <span class="count"><?php echo count($usuarios); ?> usuarios encontrados</span>
    </div>

    <?php if ($mensaje): ?>
      <div class="ri-alert ri-alert--success" style="margin-bottom:16px"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>

    <!-- Search -->
    <div class="search-card">
      <form method="GET" class="search-form">
        <div class="ri-input" style="flex:1;min-width:200px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--ri-mute);flex:none"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" name="buscar" placeholder="Buscar por nombre, email o telefono..." value="<?php echo htmlspecialchars($buscar); ?>">
        </div>
        <select name="filtro">
          <option value="todos" <?php echo $filtro==='todos'?'selected':''; ?>>Todos</option>
          <option value="pasajeros" <?php echo $filtro==='pasajeros'?'selected':''; ?>>Solo Pasajeros</option>
          <option value="conductores" <?php echo $filtro==='conductores'?'selected':''; ?>>Solo Conductores</option>
        </select>
        <button type="submit" class="ri-btn ri-btn--blue" style="height:48px">
          Buscar
        </button>
      </form>
    </div>

    <!-- Table -->
    <div class="table-card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Telefono</th>
              <th>Rol</th>
              <th>Placa</th>
              <th>Calificacion</th>
              <th>Registro</th>
              <th style="text-align:center">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($usuarios)): ?>
              <tr><td colspan="9">
                <div class="empty-state">
                  <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                  <p>No se encontraron usuarios.</p>
                </div>
              </td></tr>
            <?php else: ?>
              <?php foreach ($usuarios as $u): ?>
                <tr>
                  <td style="color:var(--ri-mute)"><?php echo $u['id']; ?></td>
                  <td class="td-name"><?php echo htmlspecialchars($u['name']); ?></td>
                  <td><?php echo htmlspecialchars($u['email']); ?></td>
                  <td><?php echo htmlspecialchars($u['phone']); ?></td>
                  <td>
                    <span class="role-chip role-chip--<?php echo $u['role']; ?>">
                      <?php echo ucfirst($u['role']); ?>
                    </span>
                  </td>
                  <td style="color:var(--ri-mute)"><?php echo htmlspecialchars($u['plate'] ?? '-'); ?></td>
                  <td>
                    <?php if ($u['role'] === 'conductor' && isset($ratings[$u['id']])): ?>
                      <span class="rating-stars"><?php echo $ratings[$u['id']]['promedio']; ?></span>
                      <span class="rating-count">(<?php echo $ratings[$u['id']]['total']; ?>)</span>
                    <?php elseif ($u['role'] === 'conductor'): ?>
                      <span style="color:var(--ri-mute);font-size:12px">Sin calif.</span>
                    <?php else: ?>
                      <span style="color:var(--ri-line)">-</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px;color:var(--ri-mute)"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                  <td>
                    <div class="action-btns">
                      <form id="role-<?php echo $u['id']; ?>" method="POST" style="display:inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="action" value="cambiar_rol">
                        <input type="hidden" name="nuevo_rol" value="<?php echo $u['role']==='pasajero'?'conductor':'pasajero'; ?>">
                        <button type="button" onclick="confirmChangeRole(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name'])); ?>', '<?php echo $u['role']==='pasajero'?'conductor':'pasajero'; ?>')" class="action-btn action-btn--role" title="Cambiar a <?php echo $u['role']==='pasajero'?'conductor':'pasajero'; ?>">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                        </button>
                      </form>
                      <form id="delete-<?php echo $u['id']; ?>" method="POST" style="display:inline">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                        <input type="hidden" name="action" value="eliminar">
                        <button type="button" onclick="confirmDeleteUser(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['name'])); ?>')" class="action-btn action-btn--delete" title="Eliminar usuario">
                          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
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
    <a href="admin_usuarios.php" class="active">
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
    function confirmChangeRole(userId, userName, nuevoRol) {
        RideIt.confirm({
            title: 'Cambiar rol de usuario',
            message: '¿Deseas cambiar el rol de <strong>' + userName + '</strong> a <strong>' + nuevoRol + '</strong>?',
            type: 'warning',
            confirmText: 'Cambiar rol',
            onConfirm: () => document.getElementById('role-' + userId).submit()
        });
    }

    function confirmDeleteUser(userId, userName) {
        RideIt.confirm({
            title: 'Eliminar usuario',
            message: '¿Estas seguro de eliminar a <strong>' + userName + '</strong>? Esta accion es <strong>irreversible</strong>.',
            type: 'danger',
            confirmText: 'Eliminar',
            onConfirm: () => document.getElementById('delete-' + userId).submit()
        });
    }
</script>
</body>
</html>
