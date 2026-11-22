<?php
include 'db.php';
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

$tripId = $_GET['trip_id'] ?? null;
$userId = $_SESSION['user_id'];

if (!$tripId) {
    die("ID de viaje no especificado.");
}

$stmt = $conn->prepare("
    SELECT t.pickup_address, t.destination_address, t.distance, t.fare, t.status,
           t.service_type, t.driver_id, d.photo AS driver_photo
    FROM trips t
    LEFT JOIN users d ON t.driver_id = d.id
    WHERE t.id = ? AND t.passenger_id = ?
");
$stmt->bind_param("ii", $tripId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$trip = $result->fetch_assoc();

if (!$trip) {
    die("Viaje no encontrado o no tienes acceso.");
}

$mapsApiKey = GOOGLE_MAPS_API_KEY;
$svcLabels = ['economico' => 'Economico', 'confort' => 'Confort'];
$svcType = $trip['service_type'] ?? 'economico';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Seguimiento del Viaje - RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapsApiKey); ?>&libraries=places"></script>
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; margin: 0; }
  .shell { max-width: 480px; margin: 0 auto; min-height: 100vh; background: var(--ri-surface-2); position: relative; display: flex; flex-direction: column; }

  .topbar {
    position: absolute; top: 0; left: 0; right: 0; z-index: 10;
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
  .topbar__title { font-size: 17px; font-weight: 700; flex: 1; }

  #map { width: 100%; height: 45vh; min-height: 250px; }

  .sheet {
    flex: 1; background: #fff;
    border-radius: 24px 24px 0 0;
    margin-top: -20px;
    position: relative; z-index: 5;
    padding: 12px 20px 32px;
    box-shadow: 0 -10px 40px rgba(0,0,0,.08);
  }
  .sheet__grip { width: 40px; height: 4px; background: var(--ri-line); border-radius: 999px; margin: 0 auto 16px; }

  .status-banner {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 14px;
    border-radius: 14px;
    margin-bottom: 16px;
    font-size: 14px; font-weight: 600;
    transition: all .3s ease;
  }
  .status-banner--pending { background: #fef3c7; color: #92400e; }
  .status-banner--assigned { background: var(--ri-blue-soft); color: var(--ri-blue); }
  .status-banner--outside { background: #fff7ed; color: #c2410c; }
  .status-banner--enroute { background: #f3e8ff; color: #7c3aed; }
  .status-banner--done { background: var(--ri-green-soft); color: var(--ri-green); }
  .status-banner__icon { width: 32px; height: 32px; border-radius: 50%; background: rgba(0,0,0,.06); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 16px; }

  .trip-detail { margin-bottom: 16px; }
  .trip-detail__row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; }
  .trip-detail__dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
  .trip-detail__dot--green { background: var(--ri-green); }
  .trip-detail__dot--blue { background: var(--ri-blue); }
  .trip-detail__line { width: 2px; height: 16px; background: var(--ri-line); margin: -4px 0 -4px 4px; }
  .trip-detail__text { font-size: 14px; color: var(--ri-ink-2); flex: 1; }
  .trip-detail__label { font-size: 11px; color: var(--ri-mute); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }

  .info-chips { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }

  .driver-card {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    background: var(--ri-surface-3);
    border-radius: 14px;
    margin-bottom: 16px;
  }
  .driver-card__avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--ri-surface-2); border: 2px solid var(--ri-line);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
  }
  .driver-card__avatar img { width: 100%; height: 100%; object-fit: cover; }
  .driver-card__name { font-size: 15px; font-weight: 700; }
  .driver-card__plate { font-size: 12px; color: var(--ri-mute); }

  .sos-btn {
    width: 100%;
    height: 50px;
    border-radius: var(--ri-r-full);
    background: var(--ri-red);
    color: #fff;
    border: none;
    font: inherit;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: opacity .15s;
  }
  .sos-btn:hover { opacity: .9; }

  .sos-confirm {
    display: none;
    background: var(--ri-red-soft);
    border: 1px solid rgba(196,37,74,.2);
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    margin-top: 12px;
  }
  .sos-confirm p { font-size: 14px; font-weight: 600; color: var(--ri-red); margin: 0 0 12px; }
  .sos-confirm__actions { display: flex; gap: 8px; justify-content: center; }

  .sos-sent {
    display: none;
    background: var(--ri-green-soft);
    border-radius: 14px;
    padding: 16px;
    text-align: center;
    margin-top: 12px;
  }
  .sos-sent p { margin: 0; }
  .sos-sent p:first-child { font-size: 14px; font-weight: 700; color: var(--ri-green); }
  .sos-sent p:last-child { font-size: 12px; color: var(--ri-mute); margin-top: 4px; }

  @media (max-width: 480px) {
    #map { height: 38vh; min-height: 220px; }
    .sheet { padding: 10px 16px 28px; }
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboardpa.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Seguimiento</span>
    <span class="ri-chip <?php echo $svcType === 'confort' ? 'ri-chip--blue' : ''; ?>">
      <?php echo htmlspecialchars($svcLabels[$svcType] ?? 'Economico'); ?>
    </span>
  </div>

  <div id="map"></div>

  <section class="sheet">
    <div class="sheet__grip"></div>

    <!-- Status banner -->
    <div id="status-banner" class="status-banner status-banner--pending">
      <div class="status-banner__icon" id="status-icon">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>
      </div>
      <span id="status"><?php
        $statusTexts = [
            'pendiente' => 'Esperando a que un conductor tome tu solicitud...',
            'asignado' => 'El conductor esta en camino al punto de recogida.',
            'afuera' => 'El conductor ha llegado y esta esperando afuera.',
            'en_destino' => 'Viaje en curso. Dirigiendose al destino.',
            'completado' => 'Viaje finalizado.'
        ];
        echo htmlspecialchars($statusTexts[$trip['status']] ?? 'Estado desconocido');
      ?></span>
    </div>

    <!-- Trip details -->
    <div class="trip-detail">
      <div class="trip-detail__row">
        <div class="trip-detail__dot trip-detail__dot--green"></div>
        <div>
          <div class="trip-detail__label">Origen</div>
          <div class="trip-detail__text" id="pickup"><?php echo htmlspecialchars($trip['pickup_address'] ?? 'No disponible'); ?></div>
        </div>
      </div>
      <div style="margin-left:4px"><div class="trip-detail__line"></div></div>
      <div class="trip-detail__row">
        <div class="trip-detail__dot trip-detail__dot--blue"></div>
        <div>
          <div class="trip-detail__label">Destino</div>
          <div class="trip-detail__text" id="destination"><?php echo htmlspecialchars($trip['destination_address'] ?? 'No disponible'); ?></div>
        </div>
      </div>
    </div>

    <div class="info-chips">
      <span class="ri-chip">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/></svg>
        <span id="distance"><?php echo htmlspecialchars($trip['distance'] ?? '0'); ?> km</span>
      </span>
      <span class="ri-chip ri-chip--green">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        $<span id="fare"><?php echo htmlspecialchars($trip['fare'] ?? '0.00'); ?></span>
      </span>
    </div>

    <!-- Driver info -->
    <div class="driver-card" id="driver-info">
      <div class="driver-card__avatar" id="driver-photo-container">
        <?php if (!empty($trip['driver_photo'])): ?>
          <img src="<?php echo htmlspecialchars($trip['driver_photo']); ?>" alt="Conductor">
        <?php else: ?>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--ri-mute)" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a8 8 0 00-8 8h16a8 8 0 00-8-8z"/></svg>
        <?php endif; ?>
      </div>
      <div style="flex:1">
        <div class="driver-card__name" id="driver">Esperando asignacion...</div>
        <div class="driver-card__plate" id="driverPlate">Placa: N/A</div>
      </div>
    </div>

    <!-- SOS -->
    <button id="sos-button" onclick="activarSOS()" class="sos-btn">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      SOS - Boton de Panico
    </button>

    <div class="sos-confirm" id="sos-confirm">
      <p>¿Estas seguro de enviar una alerta de emergencia?</p>
      <div class="sos-confirm__actions">
        <button onclick="enviarSOS()" class="ri-btn" style="--bg:var(--ri-red);font-size:14px;height:42px">Si, enviar alerta</button>
        <button onclick="cancelarSOS()" class="ri-btn ri-btn--ghost" style="font-size:14px;height:42px">Cancelar</button>
      </div>
    </div>

    <div class="sos-sent" id="sos-sent">
      <p>Alerta enviada. La central ha sido notificada.</p>
      <p>Tu ubicacion GPS ha sido compartida con el equipo de seguridad.</p>
    </div>
  </section>
</div>

<script>
let map, driverMarker, previousStatus = null;

const statusConfig = {
    'pendiente':   { text: 'Esperando a que un conductor tome tu solicitud...', cls: 'status-banner--pending',  icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>' },
    'asignado':    { text: 'El conductor esta en camino al punto de recogida.', cls: 'status-banner--assigned', icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>' },
    'afuera':      { text: 'El conductor ha llegado y esta esperando afuera.', cls: 'status-banner--outside',   icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>' },
    'en_destino':  { text: 'Viaje en curso. Dirigiendose al destino.', cls: 'status-banner--enroute',           icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M13 5l7 7-7 7"/></svg>' },
    'completado':  { text: 'Viaje finalizado. Gracias por viajar con RideIt!', cls: 'status-banner--done',       icon: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>' }
};

function initMap() {
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 28.1973, lng: -105.4702 },
        zoom: 14,
        disableDefaultUI: true,
        zoomControl: true
    });
    driverMarker = new google.maps.Marker({ map, title: "Conductor", icon: "http://maps.google.com/mapfiles/ms/icons/taxi.png" });
    updateTripStatus();
}

function updateTripStatus() {
    const tripId = <?php echo json_encode($tripId); ?>;
    fetch(`trip_status.php?trip_id=${tripId}`)
        .then(r => r.json())
        .then(data => {
            const banner = document.getElementById("status-banner");
            const statusEl = document.getElementById("status");
            const iconEl = document.getElementById("status-icon");
            const driverEl = document.getElementById("driver");
            const plateEl = document.getElementById("driverPlate");
            const cfg = statusConfig[data.status] || statusConfig['pendiente'];

            statusEl.textContent = cfg.text;
            iconEl.innerHTML = cfg.icon;
            banner.className = 'status-banner ' + cfg.cls;

            if (data.driver_name && data.driver_name !== "No asignado") {
                driverEl.textContent = data.driver_name;
                plateEl.textContent = 'Placa: ' + (data.driver_plate || 'N/A');

                if (data.driver_photo) {
                    const container = document.getElementById('driver-photo-container');
                    const img = document.createElement('img');
                    img.src = data.driver_photo;
                    img.alt = 'Conductor';
                    img.style.cssText = 'width:100%;height:100%;object-fit:cover';
                    container.textContent = '';
                    container.appendChild(img);
                }
            }

            if (data.driver_location) {
                driverMarker.setPosition(data.driver_location);
                map.setCenter(data.driver_location);
            }

            if (data.status !== previousStatus && previousStatus !== null) {
                if (data.status === "asignado") {
                    RideIt.toast("Conductor asignado: " + data.driver_name, "success");
                } else if (data.status === "afuera") {
                    RideIt.toast("Tu conductor ha llegado y esta esperando afuera.", "warning");
                } else if (data.status === "en_destino") {
                    RideIt.toast("Viaje iniciado. En camino al destino.", "info");
                } else if (data.status === "completado") {
                    RideIt.toast("Viaje finalizado. Redirigiendo al pago...", "success");
                    setTimeout(() => { window.location.href = "pagar_viaje.php?trip_id=" + tripId; }, 3000);
                    return;
                }
            }
            previousStatus = data.status;
        })
        .catch(e => console.error("Error al actualizar:", e));
    setTimeout(updateTripStatus, 3000);
}

function activarSOS() { document.getElementById('sos-confirm').style.display = 'block'; }
function cancelarSOS() { document.getElementById('sos-confirm').style.display = 'none'; }

function enviarSOS() {
    document.getElementById('sos-confirm').style.display = 'none';
    document.getElementById('sos-button').style.display = 'none';
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            fetch('sos_alert.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ trip_id: <?php echo json_encode($tripId); ?>, lat: position.coords.latitude, lng: position.coords.longitude })
            }).then(() => { document.getElementById('sos-sent').style.display = 'block'; });
        }, function() { document.getElementById('sos-sent').style.display = 'block'; });
    } else {
        document.getElementById('sos-sent').style.display = 'block';
    }
}

window.onload = initMap;
</script>
<script src="modal.js"></script>
</body>
</html>
