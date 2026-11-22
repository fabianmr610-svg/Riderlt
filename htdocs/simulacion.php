<?php
include 'db.php';
include 'csrf.php';
require_once 'config.php';
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
$mensaje = "";
$error = "";

// Obtener viajes pendientes/asignados del usuario
$stmt = $conn->prepare("
    SELECT t.id, t.pickup_address, t.destination_address, t.distance, t.fare, t.status,
           d.name AS driver_name, d.plate AS driver_plate, d.phone AS driver_phone
    FROM trips t
    LEFT JOIN users d ON t.driver_id = d.id
    WHERE t.passenger_id = ? AND t.status IN ('pendiente', 'asignado', 'en_destino', 'afuera')
    ORDER BY t.created_at DESC LIMIT 5
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$activeTrips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener conductores disponibles
$drivers = [];
$res = $conn->query("SELECT id, name, plate, phone FROM users WHERE role = 'conductor' LIMIT 10");
if ($res) $drivers = $res->fetch_all(MYSQLI_ASSOC);

// Accion: Simular asignacion de conductor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $error = "Token invalido.";
    } else {
        $action = $_POST['action'];
        $tripId = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;

        if ($action === 'assign_driver' && $tripId > 0) {
            $driverId = (int)$_POST['driver_id'];
            $stmt = $conn->prepare("UPDATE trips SET status = 'asignado', driver_id = ? WHERE id = ? AND passenger_id = ?");
            $stmt->bind_param("iii", $driverId, $tripId, $userId);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $mensaje = "Conductor asignado al viaje #$tripId.";
            } else {
                $error = "No se pudo asignar el conductor.";
            }
        }

        if ($action === 'change_status' && $tripId > 0) {
            $newStatus = $_POST['new_status'];
            $allowed = ['asignado', 'en_destino', 'afuera', 'completado'];
            if (in_array($newStatus, $allowed)) {
                if ($newStatus === 'completado') {
                    $stmt = $conn->prepare("UPDATE trips SET status = ?, completed_at = NOW() WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE trips SET status = ? WHERE id = ?");
                }
                $stmt->bind_param("si", $newStatus, $tripId);
                if ($stmt->execute()) {
                    $mensaje = "Viaje #$tripId actualizado a: $newStatus";
                }
            }
        }

        if ($action === 'create_test_trip') {
            $pickup = "Av. Tecnologico 80, Cd. Delicias, Chihuahua";
            $destination = "Plaza Encanto, Delicias, Chihuahua";
            $distance = 3.5;
            $tarifaBase = defined('TARIFA_BASE') ? TARIFA_BASE : 15;
            $tarifaPorKm = defined('TARIFA_POR_KM') ? TARIFA_POR_KM : 10;
            $fare = $tarifaBase + ($tarifaPorKm * $distance);
            $stmt = $conn->prepare("INSERT INTO trips (passenger_id, pickup_address, destination_address, distance, fare, status) VALUES (?, ?, ?, ?, ?, 'pendiente')");
            $stmt->bind_param("issdd", $userId, $pickup, $destination, $distance, $fare);
            if ($stmt->execute()) {
                $mensaje = "Viaje de prueba creado (ID: " . $conn->insert_id . "). Tarifa: $" . number_format($fare, 2);
            }
        }

        // Recargar viajes
        header("Location: simulacion.php" . ($mensaje ? "?msg=" . urlencode($mensaje) : "") . ($error ? "?err=" . urlencode($error) : ""));
        exit;
    }
}

if (isset($_GET['msg'])) $mensaje = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Simulacion de Viaje - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 700px; margin: 0 auto; padding: 0 16px 48px; }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 0;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  }
  .topbar__left { display: flex; align-items: center; gap: 12px; }
  .topbar__back {
    width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--ri-line);
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--ri-ink); cursor: pointer; text-decoration: none;
  }
  .topbar__title { font-size: 17px; font-weight: 700; }
  .topbar__links { display: flex; gap: 8px; }

  .badge-test {
    display: inline-flex; align-items: center; gap: 6px;
    background: #f5f3ff; color: #7c3aed;
    padding: 4px 12px; border-radius: 999px;
    font-size: 12px; font-weight: 700; margin: 16px 0;
  }

  /* Cards */
  .sim-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
  }
  .sim-card h2 { font-size: 16px; font-weight: 700; margin: 0 0 12px; }
  .sim-card p { font-size: 13px; color: var(--ri-ink-2); margin-bottom: 8px; }

  /* Flow steps */
  .flow { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .flow-step {
    padding: 4px 12px; border-radius: 99px; font-size: 11px; font-weight: 700;
  }
  .flow-step--done { background: var(--ri-green-soft); color: var(--ri-green); }
  .flow-step--active { background: var(--ri-blue); color: #fff; }
  .flow-step--pending { background: var(--ri-surface-2); color: var(--ri-mute); }
  .flow-arrow { color: var(--ri-mute); font-size: 12px; }

  /* Trip card */
  .trip-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 20px; margin-bottom: 12px;
    border-left: 4px solid var(--ri-blue);
  }
  .trip-card--pending { border-left-color: #f59e0b; }
  .trip-card--completed { border-left-color: var(--ri-green); }

  .trip-card__head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .trip-card__id { font-weight: 800; font-size: 15px; }
  .trip-card__status { font-size: 12px; font-weight: 700; }
  .trip-card__status--pendiente { color: #f59e0b; }
  .trip-card__status--asignado { color: var(--ri-blue); }
  .trip-card__status--en_destino { color: #7c3aed; }
  .trip-card__status--afuera { color: #f97316; }
  .trip-card__status--completado { color: var(--ri-green); }

  .trip-info { font-size: 13px; color: var(--ri-ink-2); margin-bottom: 12px; }
  .trip-info p { margin-bottom: 4px; }

  .driver-info {
    background: var(--ri-surface); border-radius: 10px;
    padding: 10px 14px; font-size: 13px; margin-bottom: 12px;
  }
  .driver-info p { margin-bottom: 4px; color: var(--ri-ink-2); }
  .driver-info strong { color: var(--ri-mute); font-weight: 600; }

  .trip-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .trip-actions select {
    padding: 0 12px; height: 36px; border-radius: 10px;
    border: 1.5px solid var(--ri-line); font-size: 13px; font-weight: 600;
    background: #fff; color: var(--ri-ink);
  }

  /* Links grid */
  .links-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .link-item {
    display: block; padding: 12px; border-radius: 12px;
    background: var(--ri-surface); text-decoration: none;
    font-size: 13px; color: var(--ri-ink-2); transition: background .2s;
  }
  .link-item:hover { background: var(--ri-blue-soft); }
  .link-item strong { color: var(--ri-blue); font-size: 11px; display: block; margin-bottom: 2px; }

  /* Guide */
  .guide ol { font-size: 13px; color: var(--ri-ink-2); padding-left: 20px; }
  .guide li { margin-bottom: 8px; line-height: 1.5; }
  .guide li strong { color: var(--ri-ink); }

  .empty-state {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 32px 20px; text-align: center; color: var(--ri-mute); font-size: 14px;
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <div class="topbar__left">
      <a href="dashboardpa.php" class="topbar__back">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      </a>
      <span class="topbar__title">Simulacion</span>
    </div>
    <div class="topbar__links">
      <a href="dashboardpa.php" class="ri-btn ri-btn--sm ri-btn--blue">Dashboard</a>
    </div>
  </div>

  <div class="badge-test">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
    Modo pruebas
  </div>

  <?php if ($mensaje): ?>
    <div class="ri-alert ri-alert--success" style="margin-bottom:12px"><?php echo htmlspecialchars($mensaje); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="ri-alert ri-alert--error" style="margin-bottom:12px"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <!-- Flujo -->
  <div class="sim-card">
    <h2>Flujo del viaje (Casos de Prueba)</h2>
    <div class="flow">
      <span class="flow-step flow-step--done">1. Registro (RF02)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--done">2. Login (RF01)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--done">3. Solicitar (RF04)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--done">4. Tarifa (RF05)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--active">5. Asignar (RF06)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--pending">6. Tracking (RF07)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--pending">7. Completar (RF09)</span>
      <span class="flow-arrow">&rarr;</span>
      <span class="flow-step flow-step--pending">8. Calificar (RF10)</span>
    </div>
  </div>

  <!-- Crear viaje de prueba -->
  <div class="sim-card">
    <h2>Paso 1: Crear viaje de prueba</h2>
    <p>Crea un viaje automatico de Tec de Delicias a Plaza Encanto, o usa uberx.php para uno real.</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form action="simulacion.php" method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="create_test_trip">
        <button type="submit" class="ri-btn ri-btn--blue ri-btn--sm">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Crear viaje de prueba
        </button>
      </form>
      <a href="uberx.php" class="ri-btn ri-btn--sm" style="--bg:#7c3aed">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
        Solicitar viaje real
      </a>
    </div>
  </div>

  <!-- Viajes activos -->
  <?php if (!empty($activeTrips)): ?>
    <?php foreach ($activeTrips as $trip): ?>
      <?php $st = $trip['status']; ?>
      <div class="trip-card <?php echo $st==='pendiente'?'trip-card--pending':($st==='completado'?'trip-card--completed':''); ?>">
        <div class="trip-card__head">
          <span class="trip-card__id">Viaje #<?php echo $trip['id']; ?></span>
          <span class="trip-card__status trip-card__status--<?php echo $st; ?>"><?php echo ucfirst(str_replace('_',' ',$st)); ?></span>
        </div>

        <div class="trip-info">
          <p><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ri-green)" stroke-width="2.5" style="vertical-align:middle"><circle cx="12" cy="12" r="3"/></svg> <?php echo htmlspecialchars($trip['pickup_address']); ?></p>
          <p><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ri-red)" stroke-width="2.5" style="vertical-align:middle"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> <?php echo htmlspecialchars($trip['destination_address']); ?></p>
          <p style="color:var(--ri-mute);font-size:12px;margin-top:4px"><?php echo $trip['distance']; ?> km &middot; $<?php echo number_format($trip['fare'], 2); ?></p>
        </div>

        <!-- Progress -->
        <div class="flow" style="margin-bottom:12px">
          <?php
          $statusOrder = ['pendiente'=>1,'asignado'=>2,'en_destino'=>3,'afuera'=>3,'completado'=>4];
          $current = $statusOrder[$st] ?? 0;
          $steps = ['Pendiente','Asignado','En curso','Completado'];
          foreach ($steps as $i => $label):
            $step = $i + 1;
            $cls = $current > $step ? 'flow-step--done' : ($current == $step ? 'flow-step--active' : 'flow-step--pending');
          ?>
            <?php if ($i > 0): ?><span class="flow-arrow">&rarr;</span><?php endif; ?>
            <span class="flow-step <?php echo $cls; ?>"><?php echo $label; ?></span>
          <?php endforeach; ?>
        </div>

        <?php if ($trip['driver_name']): ?>
          <div class="driver-info">
            <p><strong>Conductor:</strong> <?php echo htmlspecialchars($trip['driver_name']); ?></p>
            <p><strong>Placas:</strong> <?php echo htmlspecialchars($trip['driver_plate'] ?? 'N/A'); ?></p>
            <p><strong>Telefono:</strong> <?php echo htmlspecialchars($trip['driver_phone'] ?? 'N/A'); ?></p>
          </div>
        <?php endif; ?>

        <div class="trip-actions">
          <?php if ($st === 'pendiente'): ?>
            <form action="simulacion.php" method="POST" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="assign_driver">
              <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
              <select name="driver_id" required>
                <option value="">Seleccionar conductor</option>
                <?php foreach ($drivers as $d): ?>
                  <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['plate'] ?? 'Sin placa'); ?>)</option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="ri-btn ri-btn--sm" style="--bg:var(--ri-green)">Asignar</button>
            </form>
          <?php endif; ?>

          <?php if ($st === 'asignado'): ?>
            <form action="simulacion.php" method="POST" style="display:inline">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
              <input type="hidden" name="new_status" value="afuera">
              <button type="submit" class="ri-btn ri-btn--sm" style="--bg:#f97316">Conductor llego</button>
            </form>
            <a href="trackingpa.php?trip_id=<?php echo $trip['id']; ?>" class="ri-btn ri-btn--sm ri-btn--blue">Ver tracking</a>
          <?php endif; ?>

          <?php if ($st === 'afuera'): ?>
            <form action="simulacion.php" method="POST" style="display:inline">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
              <input type="hidden" name="new_status" value="en_destino">
              <button type="submit" class="ri-btn ri-btn--sm" style="--bg:#7c3aed">Iniciar viaje</button>
            </form>
          <?php endif; ?>

          <?php if ($st === 'en_destino'): ?>
            <form action="simulacion.php" method="POST" style="display:inline">
              <?php echo csrfField(); ?>
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="trip_id" value="<?php echo $trip['id']; ?>">
              <input type="hidden" name="new_status" value="completado">
              <button type="submit" class="ri-btn ri-btn--sm" style="--bg:var(--ri-green)">Completar viaje</button>
            </form>
            <a href="trackingpa.php?trip_id=<?php echo $trip['id']; ?>" class="ri-btn ri-btn--sm ri-btn--blue">Tracking + SOS</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">No hay viajes activos. Crea uno arriba para comenzar.</div>
  <?php endif; ?>

  <!-- Links -->
  <div class="sim-card">
    <h2>Todas las vistas (Casos de Prueba)</h2>
    <div class="links-grid">
      <a href="login_pasajero.php" class="link-item"><strong>RF01</strong>Login Pasajero</a>
      <a href="login_conductor.php" class="link-item"><strong>RF01</strong>Login Conductor</a>
      <a href="register_pasajero.php" class="link-item"><strong>RF02</strong>Registro Pasajero</a>
      <a href="registrocon.php" class="link-item"><strong>RF02</strong>Registro Conductor</a>
      <a href="profilepa.php" class="link-item"><strong>RF03</strong>Perfil Pasajero</a>
      <a href="uberx.php" class="link-item"><strong>RF04/RF05</strong>Solicitar viaje</a>
      <a href="historial_pasajero.php" class="link-item"><strong>RF12</strong>Historial viajes</a>
      <a href="help.php" class="link-item"><strong>RF13</strong>Ayuda y FAQ</a>
      <a href="privacidad.php" class="link-item"><strong>RNF05</strong>Aviso Privacidad</a>
      <a href="terminos.php" class="link-item"><strong>RNF05</strong>Terminos</a>
      <a href="admin_sos.php" class="link-item"><strong>RF08</strong>Alertas SOS</a>
      <a href="dashboardpa.php" class="link-item"><strong>Panel</strong>Dashboard Pasajero</a>
    </div>
  </div>

  <!-- Guide -->
  <div class="sim-card guide">
    <h2>Guia de presentacion para la docente</h2>
    <ol>
      <li><strong>RF01 - Login:</strong> Ir a login_pasajero.php, ingresar credenciales.</li>
      <li><strong>RF02 - Registro:</strong> Ir a register_pasajero.php, mostrar validaciones.</li>
      <li><strong>RF03 - Perfil:</strong> Ir a profilepa.php, cambiar datos.</li>
      <li><strong>RF04 - Solicitar viaje:</strong> Ir a uberx.php, usar mapa.</li>
      <li><strong>RF05 - Tarifa/ETA:</strong> En uberx.php ver tarifa y tiempo.</li>
      <li><strong>RF06 - Asignar conductor:</strong> Aqui en simulacion.php.</li>
      <li><strong>RF07 - Tracking:</strong> Clic en "Ver tracking en mapa".</li>
      <li><strong>RF08 - SOS:</strong> En trackingpa.php presionar SOS.</li>
      <li><strong>RF09 - Pago:</strong> Tarifa registrada al completar.</li>
      <li><strong>RF10 - Calificar:</strong> En dashboardpa.php, "Calificar viaje".</li>
      <li><strong>RF12 - Historial:</strong> En historial_pasajero.php.</li>
      <li><strong>RF13 - Ayuda:</strong> En help.php, buscar FAQ.</li>
      <li><strong>RNF04 - Seguridad:</strong> HTTPS, CSRF en formularios.</li>
      <li><strong>RNF05 - Privacidad:</strong> Aviso de privacidad.</li>
      <li><strong>RNF07 - Codigo:</strong> README.md en GitHub.</li>
      <li><strong>RF01 - Logout:</strong> Cerrar sesion y verificar acceso.</li>
    </ol>
  </div>
</div>

</body>
</html>
