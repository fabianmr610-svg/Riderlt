<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_pasajero.php"); exit; }
if (($_SESSION['user_role'] ?? '') !== 'pasajero') { header("Location: dashboard.php"); exit; }

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Usuario';
$mensaje = "";
$error = "";

$stmt = $conn->prepare("SELECT id, destination_address, DATE_FORMAT(created_at, '%d/%m/%Y') AS fecha FROM trips WHERE passenger_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $userId); $stmt->execute();
$userTrips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $error = "Token de seguridad invalido. Recarga la pagina.";
    } else {
        $categoria = trim($_POST['categoria'] ?? '');
        $tripIdReport = isset($_POST['trip_id']) ? (int)$_POST['trip_id'] : 0;
        $descripcion = trim($_POST['descripcion'] ?? '');
        $categoriasValidas = ['pago', 'conductor', 'aplicacion', 'seguridad', 'otro'];
        if (empty($categoria) || !in_array($categoria, $categoriasValidas)) {
            $error = "Selecciona una categoria valida.";
        } elseif (empty($descripcion)) {
            $error = "Describe tu problema para poder ayudarte.";
        } else {
            $folio = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $mensaje = "Tu reporte ha sido enviado. Folio: $folio. Nuestro equipo dara seguimiento.";
        }
    }
}

$faqs = [
    'General' => [
        '¿Como solicito un viaje?' => 'Desde el panel principal, haz clic en "Solicitar un Viaje". Ingresa tu destino, revisa la tarifa estimada y confirma.',
        '¿Cuanto tarda en llegar el conductor?' => 'El tiempo depende de la disponibilidad de conductores en tu zona. Normalmente entre 3 y 10 minutos.',
        '¿Puedo cancelar un viaje?' => 'Si, puedes cancelar un viaje antes de que el conductor llegue al punto de recogida sin costo adicional.',
    ],
    'Pagos' => [
        '¿Como se calcula la tarifa?' => 'La tarifa se calcula con base en la distancia del recorrido. Incluye una tarifa base mas un costo por kilometro.',
        '¿Que metodos de pago aceptan?' => 'Actualmente aceptamos pagos en efectivo, tarjeta de debito y tarjeta de credito.',
        '¿La tarifa puede cambiar durante el viaje?' => 'La tarifa estimada puede variar si cambias el destino durante el recorrido.',
    ],
    'Seguridad' => [
        '¿Que hago en caso de emergencia?' => 'Durante un viaje activo, usa el boton SOS para enviar una alerta inmediata con tu ubicacion.',
        '¿Los conductores estan verificados?' => 'Si, todos los conductores pasan por un proceso de verificacion antes de ser aceptados.',
        '¿Mis datos personales estan seguros?' => 'Si, tu informacion esta protegida. Consulta nuestro Aviso de Privacidad para mas detalles.',
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Ayuda — RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 560px; margin: 0 auto; padding: 0 0 48px; }

  .help-top {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; position: sticky; top: 0; z-index: 10;
    background: rgba(250,250,250,.88); backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--ri-line-2);
  }
  .help-top__back {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid var(--ri-line); background: var(--ri-surface);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: var(--ri-ink);
  }
  .help-top__title { font-size: 17px; font-weight: 700; }

  .content { padding: 20px; }
  .content h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 6px; }
  .content .sub { font-size: 14px; color: var(--ri-mute); margin: 0 0 20px; }

  /* Search */
  .faq-search {
    width: 100%; padding: 0 18px; height: 48px;
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 12px; font: inherit; font-size: 14px; color: var(--ri-ink);
    margin-bottom: 24px;
  }
  .faq-search:focus { outline: none; border-color: var(--ri-ink); }
  .faq-search::placeholder { color: var(--ri-mute); }

  /* FAQ */
  .faq-category { margin-bottom: 20px; }
  .faq-category__title { font-size: 13px; font-weight: 700; color: var(--ri-blue); text-transform: uppercase; letter-spacing: .06em; margin: 0 0 8px; }
  .faq-item { margin-bottom: 4px; }
  .faq-question {
    width: 100%; text-align: left; font: inherit; font-size: 14px; font-weight: 600;
    color: var(--ri-ink); background: var(--ri-surface); border: 1px solid var(--ri-line);
    padding: 12px 16px; border-radius: 12px; cursor: pointer;
    display: flex; justify-content: space-between; align-items: center;
    transition: background .15s;
  }
  .faq-question:hover { background: var(--ri-surface-3); }
  .faq-icon { color: var(--ri-mute); font-weight: 600; font-size: 18px; }
  .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.3s ease; }
  .faq-answer.open { max-height: 200px; }
  .faq-answer p { padding: 10px 16px; font-size: 13px; color: var(--ri-ink-2); line-height: 1.5; }

  /* Form */
  .form-section { margin-top: 32px; }
  .form-section h2 { font-size: 18px; font-weight: 700; margin: 0 0 16px; }
  .field { margin-bottom: 14px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .field select, .field textarea {
    width: 100%; padding: 12px 16px; background: var(--ri-surface);
    border: 1px solid var(--ri-line); border-radius: 12px;
    font: inherit; font-size: 14px; color: var(--ri-ink);
  }
  .field select:focus, .field textarea:focus { outline: none; border-color: var(--ri-ink); }
  .field textarea { min-height: 100px; resize: vertical; }

  .links { margin-top: 24px; text-align: center; }
  .links a { font-size: 13px; color: var(--ri-blue); text-decoration: none; font-weight: 500; }
  .links span { color: var(--ri-mute); margin: 0 8px; }
</style>
</head>
<body>

<div class="shell">
  <header class="help-top">
    <a href="dashboardpa.php" class="help-top__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="help-top__title">Ayuda y soporte</span>
  </header>

  <div class="content">
    <h1>¿Como podemos ayudarte?</h1>
    <p class="sub">Busca en preguntas frecuentes o contacta a soporte.</p>

    <input type="text" id="faq-search" class="faq-search" placeholder="Buscar en preguntas frecuentes...">

    <?php foreach ($faqs as $category => $questions): ?>
      <div class="faq-category">
        <div class="faq-category__title"><?php echo htmlspecialchars($category); ?></div>
        <?php foreach ($questions as $question => $answer): ?>
          <div class="faq-item">
            <button onclick="toggleFaq(this)" class="faq-question">
              <span><?php echo htmlspecialchars($question); ?></span>
              <span class="faq-icon">+</span>
            </button>
            <div class="faq-answer">
              <p><?php echo htmlspecialchars($answer); ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="form-section">
      <h2>Contactar soporte</h2>

      <?php if ($mensaje): ?>
        <div class="ri-alert ri-alert--success" style="margin-bottom:16px">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
          <?php echo htmlspecialchars($mensaje); ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="ri-alert ri-alert--error" style="margin-bottom:16px">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          <?php echo htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form action="help.php" method="POST">
        <?php echo csrfField(); ?>
        <div class="field">
          <label>Categoria del problema</label>
          <select name="categoria" required>
            <option value="">Selecciona una categoria</option>
            <option value="pago">Pago</option>
            <option value="conductor">Conductor</option>
            <option value="aplicacion">Aplicacion</option>
            <option value="seguridad">Seguridad</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="field">
          <label>Viaje relacionado (opcional)</label>
          <select name="trip_id">
            <option value="0">Ninguno</option>
            <?php foreach ($userTrips as $t): ?>
              <option value="<?php echo $t['id']; ?>">Viaje a <?php echo htmlspecialchars($t['destination_address']); ?> (<?php echo $t['fecha']; ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Describe tu problema</label>
          <textarea name="descripcion" placeholder="Describe que paso..." required></textarea>
        </div>
        <button type="submit" class="ri-btn ri-btn--lg ri-btn--block">
          Enviar reporte
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </button>
      </form>
    </div>

    <div class="links">
      <a href="privacidad.php">Aviso de Privacidad</a>
      <span>|</span>
      <a href="terminos.php">Terminos y Condiciones</a>
    </div>
  </div>
</div>

<script>
function toggleFaq(btn) {
    const answer = btn.nextElementSibling;
    const icon = btn.querySelector('.faq-icon');
    answer.classList.toggle('open');
    icon.textContent = answer.classList.contains('open') ? '−' : '+';
}
document.getElementById('faq-search').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    document.querySelectorAll('.faq-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(query) ? 'block' : 'none';
    });
});
</script>
</body>
</html>
