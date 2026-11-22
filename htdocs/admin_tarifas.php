<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

require_once 'config.php';
$mensaje = "";
$error = "";

// Leer valores actuales del .env
$envFile = __DIR__ . '/.env';
$envContent = file_exists($envFile) ? file_get_contents($envFile) : '';

$tarifaBase = TARIFA_BASE;
$tarifaPorKm = TARIFA_POR_KM;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token invalido.";
    } else {
        $nuevaBase = (float)$_POST['tarifa_base'];
        $nuevaPorKm = (float)$_POST['tarifa_por_km'];

        if ($nuevaBase < 0 || $nuevaPorKm < 0) {
            $error = "Las tarifas no pueden ser negativas.";
        } elseif ($nuevaBase > 1000 || $nuevaPorKm > 500) {
            $error = "Las tarifas exceden los limites permitidos.";
        } else {
            // Actualizar .env
            if (file_exists($envFile)) {
                $content = file_get_contents($envFile);
                $content = preg_replace('/^TARIFA_BASE=.*/m', 'TARIFA_BASE=' . $nuevaBase, $content);
                $content = preg_replace('/^TARIFA_POR_KM=.*/m', 'TARIFA_POR_KM=' . $nuevaPorKm, $content);
                file_put_contents($envFile, $content);
                $tarifaBase = $nuevaBase;
                $tarifaPorKm = $nuevaPorKm;
                $mensaje = "Tarifas actualizadas correctamente. Base: \$$nuevaBase, Por km: \$$nuevaPorKm";
            } else {
                $error = "No se encontro el archivo de configuracion.";
            }
        }
    }
}

// Calcular ejemplos
$ejemplos = [5, 10, 15, 20, 30, 50];

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
<title>Configurar Tarifas - Admin RideIt</title>
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
  }
  .sidebar__logout:hover { background: rgba(239,68,68,.25); }

  /* ── Main ── */
  .admin-main { flex: 1; padding: 32px; overflow-y: auto; max-width: 800px; }
  .page-header h1 { font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin: 0 0 24px; }

  /* Current tariff card */
  .current-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 24px; margin-bottom: 20px;
  }
  .current-card h2 { font-size: 15px; font-weight: 700; margin: 0 0 16px; }
  .current-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  .current-item { text-align: center; }
  .current-item__label { font-size: 12px; color: var(--ri-mute); font-weight: 600; margin-bottom: 4px; }
  .current-item__val { font-size: 36px; font-weight: 800; color: var(--ri-green); }
  .current-item__desc { font-size: 11px; color: var(--ri-mute); margin-top: 2px; }

  /* Edit form card */
  .edit-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 24px; margin-bottom: 20px;
  }
  .edit-card h2 { font-size: 15px; font-weight: 700; margin: 0 0 16px; }
  .edit-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px; }
  .edit-field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .edit-field input {
    width: 100%; padding: 12px 16px; border: 1.5px solid var(--ri-line); border-radius: 12px;
    font-size: 18px; font-weight: 700; color: var(--ri-ink); background: #fff;
    transition: border-color .2s;
  }
  .edit-field input:focus { border-color: var(--ri-green); outline: none; box-shadow: 0 0 0 3px rgba(34,197,94,.12); }
  .formula { font-size: 12px; color: var(--ri-mute); margin-bottom: 16px; }

  /* Simulator table */
  .sim-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    overflow: hidden;
  }
  .sim-card h2 { font-size: 15px; font-weight: 700; padding: 20px 24px 0; margin: 0; }
  .sim-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
  .sim-table thead { background: var(--ri-surface-2); }
  .sim-table th { padding: 10px 16px; text-align: left; font-weight: 700; color: var(--ri-mute); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
  .sim-table td { padding: 12px 16px; border-top: 1px solid var(--ri-line); }
  .sim-table .val-green { color: var(--ri-green); font-weight: 700; }
  .sim-table .val-mute { color: var(--ri-mute); }

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
  .mobile-nav a.active { color: var(--ri-green); }
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
    .current-grid { grid-template-columns: 1fr; }
    .edit-grid { grid-template-columns: 1fr; }
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
      <a href="admin_tarifas.php" class="nav-link active">
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
      <h1>Configurar Tarifas</h1>
    </div>

    <?php if ($mensaje): ?>
      <div class="ri-alert ri-alert--success" style="margin-bottom:16px"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="ri-alert ri-alert--error" style="margin-bottom:16px"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- Current tariffs -->
    <div class="current-card">
      <h2>Tarifas Actuales</h2>
      <div class="current-grid">
        <div class="current-item">
          <div class="current-item__label">Tarifa Base</div>
          <div class="current-item__val">$<?php echo number_format($tarifaBase, 2); ?></div>
          <div class="current-item__desc">Se cobra al iniciar el viaje</div>
        </div>
        <div class="current-item">
          <div class="current-item__label">Tarifa por Kilometro</div>
          <div class="current-item__val">$<?php echo number_format($tarifaPorKm, 2); ?></div>
          <div class="current-item__desc">Se cobra por cada km recorrido</div>
        </div>
      </div>
    </div>

    <!-- Edit form -->
    <div class="edit-card">
      <h2>Modificar Tarifas</h2>
      <form method="POST" id="tarifaForm">
        <?php echo csrfField(); ?>
        <div class="edit-grid">
          <div class="edit-field">
            <label for="tarifa_base">Tarifa Base ($)</label>
            <input type="number" name="tarifa_base" id="tarifa_base" step="0.50" min="0" max="1000"
                   value="<?php echo $tarifaBase; ?>" oninput="actualizarEjemplos()">
          </div>
          <div class="edit-field">
            <label for="tarifa_por_km">Tarifa por Kilometro ($)</label>
            <input type="number" name="tarifa_por_km" id="tarifa_por_km" step="0.50" min="0" max="500"
                   value="<?php echo $tarifaPorKm; ?>" oninput="actualizarEjemplos()">
          </div>
        </div>
        <p class="formula">Formula: Tarifa Total = Base + (Kilometros x Tarifa por Km)</p>
        <button type="button" onclick="confirmSaveTarifas()" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:var(--ri-green)">
          Guardar Nuevas Tarifas
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
        </button>
      </form>
    </div>

    <!-- Simulator -->
    <div class="sim-card">
      <h2>Simulador de Precios</h2>
      <table class="sim-table">
        <thead>
          <tr>
            <th>Distancia</th>
            <th>Base</th>
            <th>Recorrido</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody id="tabla-ejemplos">
          <?php foreach ($ejemplos as $km): ?>
            <tr>
              <td><?php echo $km; ?> km</td>
              <td class="val-mute">$<?php echo number_format($tarifaBase, 2); ?></td>
              <td class="val-mute">$<?php echo number_format($km * $tarifaPorKm, 2); ?></td>
              <td class="val-green">$<?php echo number_format($tarifaBase + $km * $tarifaPorKm, 2); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
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
    <a href="admin_tarifas.php" class="active">
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
    var kms = [5, 10, 15, 20, 30, 50];
    function actualizarEjemplos() {
        var base = parseFloat(document.getElementById('tarifa_base').value) || 0;
        var pkm = parseFloat(document.getElementById('tarifa_por_km').value) || 0;
        var tbody = document.getElementById('tabla-ejemplos');
        // Clear existing rows safely
        while (tbody.firstChild) { tbody.removeChild(tbody.firstChild); }
        kms.forEach(function(km) {
            var recorrido = km * pkm;
            var total = base + recorrido;
            var tr = document.createElement('tr');
            var td1 = document.createElement('td');
            td1.textContent = km + ' km';
            var td2 = document.createElement('td');
            td2.className = 'val-mute';
            td2.textContent = '$' + base.toFixed(2);
            var td3 = document.createElement('td');
            td3.className = 'val-mute';
            td3.textContent = '$' + recorrido.toFixed(2);
            var td4 = document.createElement('td');
            td4.className = 'val-green';
            td4.textContent = '$' + total.toFixed(2);
            tr.appendChild(td1);
            tr.appendChild(td2);
            tr.appendChild(td3);
            tr.appendChild(td4);
            tbody.appendChild(tr);
        });
    }

    function confirmSaveTarifas() {
        var base = parseFloat(document.getElementById('tarifa_base').value) || 0;
        var pkm = parseFloat(document.getElementById('tarifa_por_km').value) || 0;

        if (base < 0 || pkm < 0) {
            RideIt.alert({ title: 'Valor invalido', message: 'Las tarifas no pueden ser negativas.', type: 'danger' });
            return;
        }
        if (base > 1000 || pkm > 500) {
            RideIt.alert({ title: 'Valor excedido', message: 'Las tarifas exceden los limites permitidos (Base max $1000, Por km max $500).', type: 'danger' });
            return;
        }

        var ejemplo10 = (base + pkm * 10).toFixed(2);
        RideIt.confirm({
            title: 'Actualizar tarifas',
            message: '<strong>Tarifa base:</strong> $' + base.toFixed(2) + '<br><strong>Por kilometro:</strong> $' + pkm.toFixed(2) + '<br><strong>Ejemplo 10 km:</strong> $' + ejemplo10 + '<br><br>¿Confirmas estos nuevos valores?',
            type: 'warning',
            confirmText: 'Guardar tarifas',
            confirmClass: 'btn-success',
            onConfirm: function() { document.getElementById('tarifaForm').submit(); }
        });
    }
</script>
</body>
</html>
