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

$stmtPay = $conn->prepare("SELECT payment_method FROM users WHERE id = ?");
$stmtPay->bind_param("i", $userId);
$stmtPay->execute();
$payResult = $stmtPay->get_result();
$payRow = $payResult->fetch_assoc();
$userPaymentMethod = $payRow['payment_method'] ?? 'efectivo';

$stmt = $conn->prepare("SELECT type, address, lat, lng FROM preferences WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$savedLocations = [];
while ($row = $result->fetch_assoc()) {
    $savedLocations[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Solicitar un Viaje - RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars(GOOGLE_MAPS_API_KEY); ?>&libraries=places"></script>
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
  .topbar__title { font-size: 17px; font-weight: 700; }

  #map { width: 100%; height: 38vh; min-height: 220px; }

  .content {
    flex: 1; background: #fff;
    border-radius: 24px 24px 0 0;
    margin-top: -20px;
    position: relative; z-index: 5;
    padding: 12px 20px 100px;
    box-shadow: 0 -10px 40px rgba(0,0,0,.08);
  }
  .content__grip { width: 40px; height: 4px; background: var(--ri-line); border-radius: 999px; margin: 0 auto 18px; }

  /* Location fields */
  .loc-section { margin-bottom: 20px; }
  .loc-section label { display: block; font-size: 12px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
  .loc-section select,
  .loc-section input[type="text"] {
    width: 100%; height: 48px;
    padding: 0 14px;
    border: 1.5px solid var(--ri-line);
    border-radius: var(--ri-r-md);
    background: var(--ri-surface-3);
    font: inherit; font-size: 14px;
    color: var(--ri-ink);
    transition: border-color .15s;
  }
  .loc-section select:focus,
  .loc-section input:focus {
    outline: none;
    border-color: var(--ri-ink);
    background: #fff;
  }
  .loc-section input::placeholder { color: var(--ri-mute); }

  /* Route dots for fields */
  .loc-fields { display: flex; gap: 10px; align-items: stretch; }
  .loc-dots { display: flex; flex-direction: column; align-items: center; padding: 14px 0; gap: 2px; }
  .loc-dots__dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .loc-dots__dot--green { background: var(--ri-green); }
  .loc-dots__dot--blue { background: var(--ri-blue); }
  .loc-dots__line { width: 2px; flex: 1; background: var(--ri-line); min-height: 20px; }
  .loc-inputs { flex: 1; display: flex; flex-direction: column; gap: 10px; }

  /* Service selector */
  .svc-section { margin-bottom: 20px; }
  .svc-section__label { font-size: 12px; font-weight: 600; color: var(--ri-mute); margin-bottom: 10px; text-transform: uppercase; letter-spacing: .05em; }
  .svc-card {
    width: 100%; padding: 14px 16px;
    border-radius: 14px;
    border: 2px solid var(--ri-line);
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    display: flex; align-items: flex-start; gap: 12px;
    margin-bottom: 8px;
    font: inherit; color: var(--ri-ink);
  }
  .svc-card:hover { border-color: var(--ri-ink-2); }
  .svc-card--active { border-color: var(--ri-ink); background: var(--ri-surface-3); }
  .svc-card--confort.svc-card--active { border-color: var(--ri-blue); background: var(--ri-blue-soft); }
  .svc-card__icon { width: 40px; height: 40px; border-radius: 10px; background: var(--ri-surface-3); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .svc-card--active .svc-card__icon { background: var(--ri-ink); color: #fff; }
  .svc-card--confort.svc-card--active .svc-card__icon { background: var(--ri-blue); color: #fff; }
  .svc-card__name { font-size: 15px; font-weight: 700; }
  .svc-card__desc { font-size: 12px; color: var(--ri-mute); margin-top: 2px; }
  .svc-card__tags { display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
  .svc-card__tag { font-size: 11px; padding: 2px 8px; border-radius: 999px; background: var(--ri-surface-3); color: var(--ri-ink-2); }

  .svc-card__head { display: flex; justify-content: space-between; align-items: center; width: 100%; }
  .svc-card__mult { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 700; }
  .svc-card__mult--eco { background: var(--ri-green-soft); color: var(--ri-green); }
  .svc-card__mult--com { background: var(--ri-blue-soft); color: var(--ri-blue); }

  /* Payment select */
  .pay-select { margin-bottom: 20px; }
  .pay-select label { display: block; font-size: 12px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .05em; }
  .pay-select select {
    width: 100%; height: 48px;
    padding: 0 14px;
    border: 1.5px solid var(--ri-line);
    border-radius: var(--ri-r-md);
    background: var(--ri-surface-3);
    font: inherit; font-size: 14px;
    color: var(--ri-ink);
  }

  /* Trip summary */
  .trip-summary {
    display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;
    margin-bottom: 20px;
  }
  .trip-summary__item {
    background: var(--ri-surface-3);
    border-radius: 12px;
    padding: 12px 10px;
    text-align: center;
  }
  .trip-summary__value { font-size: 16px; font-weight: 800; color: var(--ri-ink); letter-spacing: -0.01em; }
  .trip-summary__label { font-size: 11px; color: var(--ri-mute); margin-top: 2px; }
  .trip-summary__value--green { color: var(--ri-green); }
  .trip-summary__value--blue { color: var(--ri-blue); }

  /* Bottom fixed */
  .bottom-bar {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 20;
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(16px);
    border-top: 1px solid var(--ri-line);
    padding: 12px 20px;
    max-width: 480px; margin: 0 auto;
  }

  @media (max-width: 480px) {
    #map { height: 30vh; }
    .content { padding: 10px 16px 100px; }
  }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboardpa.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Solicitar viaje</span>
  </div>

  <div id="map"></div>

  <section class="content">
    <div class="content__grip"></div>

    <!-- Saved locations -->
    <div class="loc-section">
      <label for="saved-locations">Ubicaciones guardadas</label>
      <select id="saved-locations">
        <option value="">Selecciona una ubicacion</option>
        <?php foreach ($savedLocations as $location): ?>
          <option value='<?php echo htmlspecialchars(json_encode($location), ENT_QUOTES); ?>'>
            <?php echo htmlspecialchars($location['type']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Pickup / Destination -->
    <div class="loc-section">
      <div class="loc-fields">
        <div class="loc-dots">
          <div class="loc-dots__dot loc-dots__dot--green"></div>
          <div class="loc-dots__line"></div>
          <div class="loc-dots__dot loc-dots__dot--blue"></div>
        </div>
        <div class="loc-inputs">
          <input type="text" id="pickup" placeholder="Mueve el marcador para actualizar" readonly>
          <input type="text" id="destination" placeholder="¿A donde vas?">
        </div>
      </div>
    </div>

    <!-- Service type -->
    <div class="svc-section">
      <div class="svc-section__label">Tipo de servicio</div>
      <button type="button" onclick="selectServiceType('economico')" id="btn-economico" class="svc-card svc-card--active">
        <div class="svc-card__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 13l2-6h14l2 6v6H3v-6z"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
        </div>
        <div style="flex:1">
          <div class="svc-card__head">
            <span class="svc-card__name">Economico</span>
            <span class="svc-card__mult svc-card__mult--eco">x1.0</span>
          </div>
          <div class="svc-card__desc">Viajes rapidos y accesibles. Hasta 4 pasajeros.</div>
          <div class="svc-card__tags">
            <span class="svc-card__tag">4 pasajeros</span>
            <span class="svc-card__tag">Estandar</span>
          </div>
        </div>
      </button>
      <button type="button" onclick="selectServiceType('confort')" id="btn-confort" class="svc-card svc-card--confort">
        <div class="svc-card__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z"/></svg>
        </div>
        <div style="flex:1">
          <div class="svc-card__head">
            <span class="svc-card__name">Confort</span>
            <span class="svc-card__mult svc-card__mult--com">x1.35</span>
          </div>
          <div class="svc-card__desc">Mayor espacio y comodidad. A/C garantizado.</div>
          <div class="svc-card__tags">
            <span class="svc-card__tag">Mayor espacio</span>
            <span class="svc-card__tag">A/C</span>
            <span class="svc-card__tag">★4.85+</span>
          </div>
        </div>
      </button>
    </div>

    <!-- Payment method -->
    <div class="pay-select">
      <label for="payment-method">Metodo de pago</label>
      <select id="payment-method">
        <option value="efectivo" <?php echo $userPaymentMethod === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
        <option value="tarjeta_debito" <?php echo $userPaymentMethod === 'tarjeta_debito' ? 'selected' : ''; ?>>Tarjeta de Debito</option>
        <option value="tarjeta_credito" <?php echo $userPaymentMethod === 'tarjeta_credito' ? 'selected' : ''; ?>>Tarjeta de Credito</option>
      </select>
    </div>

    <!-- Trip summary -->
    <div class="trip-summary">
      <div class="trip-summary__item">
        <div class="trip-summary__value trip-summary__value--green" id="estimated-fare">$0</div>
        <div class="trip-summary__label">Tarifa</div>
      </div>
      <div class="trip-summary__item">
        <div class="trip-summary__value trip-summary__value--blue" id="distance">0 km</div>
        <div class="trip-summary__label">Distancia</div>
      </div>
      <div class="trip-summary__item">
        <div class="trip-summary__value" id="eta">-- min</div>
        <div class="trip-summary__label">Tiempo est.</div>
      </div>
    </div>
  </section>

  <!-- Bottom fixed confirm -->
  <div class="bottom-bar">
    <form action="request_trip.php" method="POST">
      <?php echo csrfField(); ?>
      <input type="hidden" name="pickup" id="pickup-hidden">
      <input type="hidden" name="destination" id="destination-hidden">
      <input type="hidden" name="distance" id="distance-hidden">
      <input type="hidden" name="fare" id="fare-hidden">
      <input type="hidden" name="service_type" id="service-type-hidden" value="economico">
      <input type="hidden" name="payment_method" id="payment-method-hidden" value="<?php echo htmlspecialchars($userPaymentMethod); ?>">
      <button type="submit" id="confirm-ride" class="ri-btn ri-btn--lg ri-btn--block">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
        Confirmar Viaje
      </button>
    </form>
  </div>
</div>

<script>
let map, directionsRenderer, directionsService, pickupMarker, destinationMarker;

function initMap() {
    directionsRenderer = new google.maps.DirectionsRenderer();
    directionsService = new google.maps.DirectionsService();

    map = new google.maps.Map(document.getElementById("map"), {
        center: { lat: 28.1973, lng: -105.4702 },
        zoom: 14,
        disableDefaultUI: true,
        zoomControl: true
    });

    directionsRenderer.setMap(map);

    pickupMarker = new google.maps.Marker({
        map,
        draggable: true,
        title: "Ubicacion de recogida",
        icon: "http://maps.google.com/mapfiles/ms/icons/green-dot.png",
    });

    destinationMarker = new google.maps.Marker({
        map,
        draggable: false,
        title: "Destino",
        icon: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png",
    });

    loadSavedLocations();

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                var userLocation = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                };
                map.setCenter(userLocation);
                pickupMarker.setPosition(userLocation);
                updatePickupLocation(userLocation);
            },
            function(error) {
                console.error("Error al obtener ubicacion:", error);
                RideIt.alert({ title: 'GPS no disponible', message: 'No se pudo obtener tu ubicacion. Asegurate de habilitar el GPS en tu dispositivo.', type: 'warning' });
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        RideIt.alert({ title: 'Navegador incompatible', message: 'Tu navegador no soporta geolocalizacion.', type: 'danger' });
    }

    pickupMarker.addListener("dragend", function() {
        var position = pickupMarker.getPosition();
        updatePickupLocation({ lat: position.lat(), lng: position.lng() });
    });

    var destinationInput = document.getElementById("destination");
    var autocomplete = new google.maps.places.Autocomplete(destinationInput);
    autocomplete.addListener("place_changed", function() {
        var place = autocomplete.getPlace();
        if (place.geometry && place.geometry.location) {
            destinationMarker.setPosition(place.geometry.location);
            map.setCenter(place.geometry.location);
            calculateRoute();
        }
    });

    document.getElementById("saved-locations").addEventListener("change", function(e) {
        var locationData = e.target.value;
        if (locationData) {
            var location = JSON.parse(locationData);
            var latLng = { lat: parseFloat(location.lat), lng: parseFloat(location.lng) };
            map.setCenter(latLng);
            pickupMarker.setPosition(latLng);
            updatePickupLocation(latLng);
        }
    });

    document.getElementById("confirm-ride").addEventListener("click", function(event) {
        event.preventDefault();
        var pickup = document.getElementById("pickup").value;
        var destination = document.getElementById("destination").value;
        var dist = document.getElementById("distance").textContent.replace(" km", "");
        var fare = document.getElementById("estimated-fare").textContent.replace("$", "");
        var serviceType = document.getElementById("service-type-hidden").value;
        var paymentMethod = document.getElementById("payment-method").value;
        var serviceLabels = { economico: 'Economico', confort: 'Confort' };
        var payLabels = { efectivo: 'Efectivo', tarjeta_debito: 'Tarjeta de Debito', tarjeta_credito: 'Tarjeta de Credito' };

        if (!pickup || !destination) {
            RideIt.alert({ title: 'Ubicaciones requeridas', message: 'Debes seleccionar un punto de recogida y un destino antes de confirmar el viaje.', type: 'warning' });
            return;
        }
        if (parseFloat(dist) <= 0) {
            RideIt.alert({ title: 'Ruta no calculada', message: 'Espera a que se calcule la ruta antes de confirmar.', type: 'warning' });
            return;
        }

        RideIt.confirm({
            title: 'Confirmar viaje',
            message: '<strong>Servicio:</strong> ' + (serviceLabels[serviceType] || 'Economico') + '<br><strong>Destino:</strong> ' + destination + '<br><strong>Distancia:</strong> ' + dist + ' km<br><strong>Tarifa:</strong> $' + parseFloat(fare).toFixed(2) + '<br><strong>Pago:</strong> ' + (payLabels[paymentMethod] || 'Efectivo') + '<br><br>¿Deseas solicitar este viaje?',
            type: 'info',
            confirmText: 'Solicitar viaje',
            confirmClass: 'btn-success',
            onConfirm: function() {
                document.getElementById("pickup-hidden").value = pickup;
                document.getElementById("destination-hidden").value = destination;
                document.getElementById("distance-hidden").value = parseFloat(dist);
                document.getElementById("fare-hidden").value = parseFloat(fare);
                document.getElementById("payment-method-hidden").value = paymentMethod;
                document.querySelector('form[action="request_trip.php"]').submit();
            }
        });
    });
}

function loadSavedLocations() {
    var savedLocations = <?php echo json_encode($savedLocations); ?>;
    var select = document.getElementById("saved-locations");
    savedLocations.forEach(function(location) {
        var option = document.createElement("option");
        option.value = JSON.stringify(location);
        option.textContent = location.type;
        select.appendChild(option);
    });
}

function updatePickupLocation(location) {
    var geocoder = new google.maps.Geocoder();
    geocoder.geocode({ location: location }, function(results, status) {
        if (status === "OK" && results[0]) {
            document.getElementById("pickup").value = results[0].formatted_address;
            calculateRoute();
        }
    });
}

// RF14: Zona de cobertura
var COVERAGE_CENTER = { lat: 28.1973, lng: -105.4702 };
var COVERAGE_RADIUS_KM = 30;

function isInsideCoverage(lat, lng) {
    var R = 6371;
    var dLat = (lat - COVERAGE_CENTER.lat) * Math.PI / 180;
    var dLng = (lng - COVERAGE_CENTER.lng) * Math.PI / 180;
    var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(COVERAGE_CENTER.lat * Math.PI / 180) * Math.cos(lat * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return (R * c) <= COVERAGE_RADIUS_KM;
}

function calculateRoute() {
    var pickup = pickupMarker.getPosition();
    var destination = destinationMarker.getPosition();

    if (pickup && destination) {
        if (!isInsideCoverage(pickup.lat(), pickup.lng())) {
            RideIt.alert({ title: 'Fuera de cobertura', message: 'La ubicacion de recogida esta fuera de la zona de cobertura de RideIt (Delicias y alrededores, radio de 30 km).', type: 'warning' });
            document.getElementById("confirm-ride").disabled = true;
            document.getElementById("confirm-ride").style.opacity = '0.5';
            return;
        }
        if (!isInsideCoverage(destination.lat(), destination.lng())) {
            RideIt.alert({ title: 'Fuera de cobertura', message: 'El destino esta fuera de la zona de cobertura de RideIt (Delicias y alrededores, radio de 30 km).', type: 'warning' });
            document.getElementById("confirm-ride").disabled = true;
            document.getElementById("confirm-ride").style.opacity = '0.5';
            return;
        }
        document.getElementById("confirm-ride").disabled = false;
        document.getElementById("confirm-ride").style.opacity = '1';

        directionsService.route(
            { origin: pickup, destination: destination, travelMode: google.maps.TravelMode.DRIVING },
            function(result, status) {
                if (status === google.maps.DirectionsStatus.OK) {
                    directionsRenderer.setDirections(result);
                    var dist = result.routes[0].legs[0].distance.value / 1000;
                    var duration = result.routes[0].legs[0].duration.text;
                    var tarifaPorKm = <?php echo json_encode(TARIFA_POR_KM); ?>;
                    var tarifaBase = <?php echo json_encode(TARIFA_BASE); ?>;
                    var multiplier = SERVICE_MULTIPLIER[selectedServiceType] || 1.0;
                    var fare = (tarifaBase + (tarifaPorKm * dist)) * multiplier;

                    document.getElementById("distance").textContent = dist.toFixed(2) + ' km';
                    document.getElementById("estimated-fare").textContent = '$' + fare.toFixed(2);
                    document.getElementById("eta").textContent = duration;
                }
            }
        );
    }
}

var selectedServiceType = 'economico';
var SERVICE_MULTIPLIER = { economico: 1.0, confort: 1.35 };

function selectServiceType(type) {
    selectedServiceType = type;
    document.getElementById('service-type-hidden').value = type;

    var ecoBtn = document.getElementById('btn-economico');
    var comBtn = document.getElementById('btn-confort');

    ecoBtn.className = 'svc-card' + (type === 'economico' ? ' svc-card--active' : '');
    comBtn.className = 'svc-card svc-card--confort' + (type === 'confort' ? ' svc-card--active' : '');

    calculateRoute();
}

document.addEventListener('DOMContentLoaded', function() {
    var paySelect = document.getElementById('payment-method');
    if (paySelect) {
        paySelect.addEventListener('change', function() {
            document.getElementById('payment-method-hidden').value = this.value;
        });
    }
});

window.onload = initMap;
</script>
<script src="modal.js"></script>
</body>
</html>
