<?php
include 'db.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_conductor.php");
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'conductor') {
    header("Location: dashboardpa.php");
    exit;
}

$tripId = $_GET['trip_id'] ?? null;
$driverId = $_SESSION['user_id'];

if (!$tripId) {
    echo "ID de viaje no especificado.";
    exit;
}

$stmt = $conn->prepare("
    SELECT
        trips.pickup_address AS pickup,
        trips.destination_address AS destination,
        trips.distance,
        trips.fare,
        trips.status,
        trips.service_type,
        users.name AS passenger_name,
        users.phone AS passenger_phone,
        users.photo AS passenger_photo
    FROM trips
    INNER JOIN users ON trips.passenger_id = users.id
    WHERE trips.id = ? AND trips.driver_id = ?
");

$stmt->bind_param("ii", $tripId, $driverId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $trip = $result->fetch_assoc();
} else {
    echo "No tienes acceso a este viaje.";
    exit;
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
<title>Seguimiento - RideIt Conductor</title>
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

  .voice-btn {
    position: absolute; top: 76px; right: 20px; z-index: 11;
    width: 42px; height: 42px; border-radius: 50%;
    background: #fff; border: 1.5px solid var(--ri-line);
    box-shadow: var(--ri-shadow-md);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--ri-ink); font-size: 18px;
  }

  .sheet {
    flex: 1; background: #fff;
    border-radius: 24px 24px 0 0;
    margin-top: -20px;
    position: relative; z-index: 5;
    padding: 12px 20px 32px;
    box-shadow: 0 -10px 40px rgba(0,0,0,.08);
  }
  .sheet__grip { width: 40px; height: 4px; background: var(--ri-line); border-radius: 999px; margin: 0 auto 16px; }

  .section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
  .section-head h2 { font-size: 18px; font-weight: 800; margin: 0; letter-spacing: -0.02em; }

  .passenger-card {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px;
    background: var(--ri-surface-3);
    border-radius: 14px;
    margin-bottom: 16px;
  }
  .passenger-card__avatar {
    width: 48px; height: 48px; border-radius: 50%;
    background: var(--ri-surface-2); border: 2px solid var(--ri-blue);
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
  }
  .passenger-card__avatar img { width: 100%; height: 100%; object-fit: cover; }
  .passenger-card__name { font-size: 15px; font-weight: 700; }
  .passenger-card__phone { font-size: 13px; color: var(--ri-blue); text-decoration: none; }
  .passenger-card__phone:hover { text-decoration: underline; }

  .trip-detail { margin-bottom: 16px; }
  .trip-detail__row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; }
  .trip-detail__dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }
  .trip-detail__dot--green { background: var(--ri-green); }
  .trip-detail__dot--blue { background: var(--ri-blue); }
  .trip-detail__line { width: 2px; height: 16px; background: var(--ri-line); margin: -4px 0 -4px 4px; }
  .trip-detail__text { font-size: 14px; color: var(--ri-ink-2); flex: 1; }
  .trip-detail__label { font-size: 11px; color: var(--ri-mute); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }

  .info-chips { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }

  #voice-on-icon, #voice-off-icon { display: none; }
  .voice-on #voice-on-icon { display: block; }
  .voice-on #voice-off-icon { display: none; }
  .voice-off #voice-on-icon { display: none; }
  .voice-off #voice-off-icon { display: block; }

  @media (max-width: 480px) {
    #map { height: 38vh; min-height: 220px; }
    .sheet { padding: 10px 16px 28px; }
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboard.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Navegacion</span>
    <span class="ri-chip <?php echo $svcType === 'confort' ? 'ri-chip--blue' : ''; ?>">
      <?php echo htmlspecialchars($svcLabels[$svcType] ?? 'Economico'); ?>
    </span>
  </div>

  <button id="voice-control" class="voice-btn voice-on" title="Activar/Desactivar Voz">
    <svg id="voice-on-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>
    <svg id="voice-off-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
  </button>

  <div id="map"></div>

  <section class="sheet">
    <div class="sheet__grip"></div>

    <div class="section-head">
      <h2>Detalles del viaje</h2>
      <span class="ri-chip ri-chip--green" style="font-size:11px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        En curso
      </span>
    </div>

    <!-- Passenger info -->
    <div class="passenger-card">
      <div class="passenger-card__avatar">
        <?php if (!empty($trip['passenger_photo'])): ?>
          <img src="<?php echo htmlspecialchars($trip['passenger_photo']); ?>" alt="Pasajero">
        <?php else: ?>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="var(--ri-mute)" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a8 8 0 00-8 8h16a8 8 0 00-8-8z"/></svg>
        <?php endif; ?>
      </div>
      <div style="flex:1">
        <div class="passenger-card__name"><?php echo htmlspecialchars($trip['passenger_name']); ?></div>
        <a href="tel:<?php echo htmlspecialchars($trip['passenger_phone']); ?>" class="passenger-card__phone">
          <?php echo htmlspecialchars($trip['passenger_phone']); ?>
        </a>
      </div>
    </div>

    <!-- Route -->
    <div class="trip-detail">
      <div class="trip-detail__row">
        <div class="trip-detail__dot trip-detail__dot--green"></div>
        <div>
          <div class="trip-detail__label">Recoger en</div>
          <div class="trip-detail__text" id="pickup"><?php echo htmlspecialchars($trip['pickup']); ?></div>
        </div>
      </div>
      <div style="margin-left:4px"><div class="trip-detail__line"></div></div>
      <div class="trip-detail__row">
        <div class="trip-detail__dot trip-detail__dot--blue"></div>
        <div>
          <div class="trip-detail__label">Destino</div>
          <div class="trip-detail__text" id="destination"><?php echo htmlspecialchars($trip['destination']); ?></div>
        </div>
      </div>
    </div>

    <div class="info-chips">
      <span class="ri-chip">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/></svg>
        <?php echo htmlspecialchars($trip['distance']); ?> km
      </span>
      <span class="ri-chip ri-chip--green">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        $<?php echo htmlspecialchars($trip['fare']); ?>
      </span>
    </div>

    <button id="action-button" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:var(--ri-green)">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 22s-8-4.5-8-11.8A8 8 0 0 1 12 2a8 8 0 0 1 8 8.2c0 7.3-8 11.8-8 11.8z"/><circle cx="12" cy="10" r="3"/></svg>
      Estoy afuera
    </button>
  </section>
</div>

<script>
let map, directionsRenderer, directionsService, driverMarker, synth, isVoiceActive = true;
let currentAction = "pickup";

function initMap() {
    directionsRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: false, preserveViewport: true });
    directionsService = new google.maps.DirectionsService();
    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 28.1973, lng: -105.4702 },
        zoom: 14,
        disableDefaultUI: true,
        zoomControl: true
    });
    directionsRenderer.setMap(map);
    driverMarker = new google.maps.Marker({ map, title: "Tu ubicacion", icon: "http://maps.google.com/mapfiles/ms/icons/taxi.png" });

    setupVoice();
    setupVoiceControl();
    trackDriverLocation();
    setupActionButton();
}

function setupVoice() {
    if ('speechSynthesis' in window) { synth = window.speechSynthesis; }
}

function narrate(text) {
    if (synth && isVoiceActive) {
        const utterance = new SpeechSynthesisUtterance(text);
        synth.speak(utterance);
    }
}

function setupVoiceControl() {
    var btn = document.getElementById("voice-control");
    btn.addEventListener("click", function() {
        isVoiceActive = !isVoiceActive;
        btn.className = 'voice-btn ' + (isVoiceActive ? 'voice-on' : 'voice-off');
    });
}

function trackDriverLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.watchPosition(
            function(position) {
                var loc = { lat: position.coords.latitude, lng: position.coords.longitude };
                driverMarker.setPosition(loc);
                map.setCenter(loc);
                var dest = currentAction === "pickup"
                    ? <?php echo json_encode($trip['pickup']); ?>
                    : <?php echo json_encode($trip['destination']); ?>;
                calculateAndDisplayRoute(loc, dest);
            },
            function(error) { console.error("Error al obtener ubicacion:", error); },
            { enableHighAccuracy: true }
        );
    }
}

function calculateAndDisplayRoute(origin, destinationAddress) {
    var geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: destinationAddress }, function(results, status) {
        if (status === "OK") {
            directionsService.route(
                { origin: origin, destination: results[0].geometry.location, travelMode: google.maps.TravelMode.DRIVING },
                function(result, status) {
                    if (status === google.maps.DirectionsStatus.OK) {
                        directionsRenderer.setDirections(result);
                        var steps = result.routes[0].legs[0].steps;
                        steps.forEach(function(step, index) {
                            setTimeout(function() {
                                narrate(step.instructions.replace(/<[^>]*>/g, '') + ' en ' + Math.round(step.distance.value) + ' metros.');
                            }, index * 5000);
                        });
                    }
                }
            );
        }
    });
}

function setupActionButton() {
    var actionButton = document.getElementById("action-button");
    actionButton.addEventListener("click", function() {
        var tripId = <?php echo json_encode($tripId); ?>;
        if (currentAction === "pickup") {
            RideIt.confirm({
                title: 'Llegaste al punto de recogida',
                message: '¿Confirmas que ya estas en el punto de recogida del pasajero?',
                type: 'info',
                confirmText: 'Si, estoy aqui',
                confirmClass: 'btn-success',
                onConfirm: function() {
                    currentAction = "destination";
                    actionButton.textContent = 'Pedido finalizado';
                    actionButton.style.setProperty('--bg', 'var(--ri-blue)');
                    fetch("update_trip_status.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ trip_id: tripId, status: "en_destino" }),
                    });
                    RideIt.toast('Estado actualizado. Navegando al destino.', 'success');
                }
            });
        } else {
            RideIt.confirm({
                title: 'Finalizar viaje',
                message: '¿Confirmas que el pasajero ha llegado a su destino y el viaje ha terminado?',
                type: 'success',
                confirmText: 'Finalizar viaje',
                confirmClass: 'btn-success',
                onConfirm: function() {
                    fetch("update_trip_status.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ trip_id: tripId, status: "completado" }),
                    }).then(function() {
                        RideIt.alert({
                            title: 'Viaje completado',
                            message: 'El viaje se ha registrado como completado exitosamente.',
                            type: 'success',
                            onClose: function() { window.location.href = "dashboard.php"; }
                        });
                    });
                }
            });
        }
    });
}

window.onload = initMap;
</script>
<script src="modal.js"></script>
</body>
</html>
