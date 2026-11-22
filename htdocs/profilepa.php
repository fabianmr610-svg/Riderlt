<?php
include 'db.php';
include 'csrf.php';
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) { header("Location: login_pasajero.php"); exit; }
if (($_SESSION['user_role'] ?? '') !== 'pasajero') { header("Location: dashboard.php"); exit; }

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT name, description, photo, phone, payment_method FROM users WHERE id = ?");
$stmt->bind_param("i", $userId); $stmt->execute(); $result = $stmt->get_result();
if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $userName = $user['name'];
    $userDescription = $user['description'] ?? "Pasajero desde " . date("Y");
    $userPhoto = $user['photo'] ?? "";
    $userPhone = $user['phone'] ?? "No registrado";
    $userPaymentMethod = $user['payment_method'] ?? 'efectivo';
} else { session_destroy(); header("Location: login_pasajero.php"); exit; }

$photoNotification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if (!validateCsrfToken()) { $photoNotification = "Token de seguridad invalido."; }
    else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileType = mime_content_type($_FILES['photo']['tmp_name']);
        $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($fileType, $allowedTypes) || !in_array($fileExt, $allowedExtensions)) { $photoNotification = "Solo se permiten imagenes JPG, PNG, GIF o WebP."; }
        elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) { $photoNotification = "La imagen no debe superar 5MB."; }
        else {
            $photoDir = 'uploads/';
            if (!is_dir($photoDir)) mkdir($photoDir, 0755, true);
            $photoName = 'pa_' . $userId . '_' . uniqid() . '.' . $fileExt;
            $photoPath = $photoDir . $photoName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                $stmt = $conn->prepare("UPDATE users SET photo = ? WHERE id = ?");
                $stmt->bind_param("si", $photoPath, $userId);
                if ($stmt->execute()) { $photoNotification = "Foto actualizada correctamente."; $userPhoto = $photoPath; }
                else { $photoNotification = "Error al guardar la foto."; }
            } else { $photoNotification = "Error al subir la foto."; }
        }
    }
}

$phoneNotification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['phone']) && !isset($_POST['payment_method']) && !isset($_POST['type'])) {
    if (!validateCsrfToken()) { $phoneNotification = "Token de seguridad invalido."; }
    else {
        $newPhone = trim($_POST['phone']);
        if (!empty($newPhone) && preg_match('/^[0-9]{7,15}$/', $newPhone)) {
            $stmt = $conn->prepare("UPDATE users SET phone = ? WHERE id = ?");
            $stmt->bind_param("si", $newPhone, $userId);
            if ($stmt->execute()) { $phoneNotification = "Telefono actualizado correctamente."; $userPhone = $newPhone; }
            else { $phoneNotification = "Error al actualizar el telefono."; }
        } else { $phoneNotification = "Numero invalido (solo digitos, 7-15 caracteres)."; }
    }
}

$paymentNotification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && !isset($_POST['type'])) {
    if (!validateCsrfToken()) { $paymentNotification = "Token de seguridad invalido."; }
    else {
        $newPayment = trim($_POST['payment_method']);
        $validMethods = ['efectivo', 'tarjeta_debito', 'tarjeta_credito'];
        if (in_array($newPayment, $validMethods)) {
            $stmt = $conn->prepare("UPDATE users SET payment_method = ? WHERE id = ?");
            $stmt->bind_param("si", $newPayment, $userId);
            if ($stmt->execute()) { $paymentNotification = "Metodo de pago actualizado."; $userPaymentMethod = $newPayment; }
            else { $paymentNotification = "Error al actualizar."; }
        } else { $paymentNotification = "Metodo de pago no valido."; }
    }
}

$notification = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'], $_POST['address'])) {
    if (!validateCsrfToken()) { $notification = "Token de seguridad invalido."; }
    else {
        $type = trim($_POST['type']); $address = trim($_POST['address']);
        if (!empty($type) && !empty($address)) {
            $apiKey = GOOGLE_MAPS_API_KEY;
            $geoUrl = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=$apiKey";
            $response = file_get_contents($geoUrl);
            $geoData = json_decode($response, true);
            if ($geoData['status'] === 'OK') {
                $lat = $geoData['results'][0]['geometry']['location']['lat'];
                $lng = $geoData['results'][0]['geometry']['location']['lng'];
                $stmt = $conn->prepare("INSERT INTO preferences (user_id, type, address, lat, lng) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issdd", $userId, $type, $address, $lat, $lng);
                $notification = $stmt->execute() ? "Direccion agregada correctamente!" : "Error al agregar la direccion.";
            } else { $notification = "No se pudieron calcular las coordenadas."; }
        } else { $notification = "Completa todos los campos."; }
    }
}

if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (!hash_equals(($_SESSION['csrf_token'] ?? ''), $_GET['token'])) { header("Location: profilepa.php"); exit; }
    $stmt = $conn->prepare("DELETE FROM preferences WHERE user_id = ? AND type = ?");
    $stmt->bind_param("is", $userId, $_GET['delete']); $stmt->execute();
    header("Location: profilepa.php"); exit;
}

$stmt = $conn->prepare("SELECT type, address, lat, lng FROM preferences WHERE user_id = ?");
$stmt->bind_param("i", $userId); $stmt->execute();
$preferences = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$methodLabels = ['efectivo' => 'Efectivo', 'tarjeta_debito' => 'Tarjeta de Debito', 'tarjeta_credito' => 'Tarjeta de Credito'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Perfil — RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 520px; margin: 0 auto; padding: 0 0 48px; }
  .top {
    display: flex; align-items: center; gap: 12px;
    padding: 16px 20px; position: sticky; top: 0; z-index: 10;
    background: rgba(250,250,250,.88); backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--ri-line-2);
  }
  .top__back {
    width: 40px; height: 40px; border-radius: 50%;
    border: 1.5px solid var(--ri-line); background: var(--ri-surface);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; color: var(--ri-ink);
  }
  .top__title { font-size: 17px; font-weight: 700; }

  .content { padding: 20px; }

  /* Profile header */
  .profile-header { text-align: center; margin-bottom: 28px; }
  .profile-header__pic {
    width: 80px; height: 80px; border-radius: 50%; object-fit: cover;
    border: 3px solid var(--ri-blue); margin: 0 auto 12px; display: block;
    background: var(--ri-surface-3);
  }
  .profile-header__pic-placeholder {
    width: 80px; height: 80px; border-radius: 50%;
    border: 3px solid var(--ri-line); margin: 0 auto 12px;
    background: var(--ri-surface-3); display: flex; align-items: center; justify-content: center;
    color: var(--ri-mute);
  }
  .profile-header__name { font-size: 22px; font-weight: 800; letter-spacing: -0.01em; margin: 0 0 4px; }
  .profile-header__desc { font-size: 13px; color: var(--ri-mute); margin: 0 0 6px; }
  .profile-header__phone { font-size: 13px; color: var(--ri-ink-2); margin: 0; }

  /* Section */
  .section { margin-bottom: 28px; }
  .section__title { font-size: 16px; font-weight: 700; margin: 0 0 12px; }

  /* Card */
  .p-card {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 14px; padding: 16px; margin-bottom: 10px;
  }
  .field { margin-bottom: 12px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .field input, .field select {
    width: 100%; height: 48px; padding: 0 16px;
    background: var(--ri-surface-3); border: 1.5px solid transparent;
    border-radius: 12px; font: inherit; font-size: 14px; color: var(--ri-ink);
  }
  .field input:focus, .field select:focus { outline: none; border-color: var(--ri-ink); background: var(--ri-surface); }

  /* Address card */
  .addr {
    background: var(--ri-surface); border: 1px solid var(--ri-line);
    border-radius: 12px; padding: 12px 16px; margin-bottom: 8px;
    display: flex; align-items: center; gap: 12px;
  }
  .addr__icon {
    width: 36px; height: 36px; border-radius: 10px; background: var(--ri-surface-3);
    display: flex; align-items: center; justify-content: center; flex: none; color: var(--ri-ink);
  }
  .addr__info { flex: 1; min-width: 0; }
  .addr__type { font-size: 14px; font-weight: 600; }
  .addr__text { font-size: 12px; color: var(--ri-mute); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

  /* Danger zone */
  .danger { margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--ri-line); }
  .danger h3 { font-size: 15px; font-weight: 700; color: var(--ri-red); margin: 0 0 6px; }
  .danger p { font-size: 13px; color: var(--ri-mute); margin: 0 0 14px; }
</style>
</head>
<body>

<div class="shell">
  <header class="top">
    <a href="dashboardpa.php" class="top__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="top__title">Mi perfil</span>
  </header>

  <div class="content">
    <!-- Profile Header -->
    <div class="profile-header">
      <?php if ($userPhoto): ?>
        <img id="profile-pic" src="<?php echo htmlspecialchars($userPhoto); ?>" alt="Perfil" class="profile-header__pic">
      <?php else: ?>
        <div class="profile-header__pic-placeholder" id="profile-pic-placeholder">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
        </div>
      <?php endif; ?>

      <form action="" method="POST" enctype="multipart/form-data" id="photo-form" style="margin-bottom:8px">
        <?php echo csrfField(); ?>
        <label for="photo-input" class="ri-btn ri-btn--ghost" style="height:32px;padding:0 16px;font-size:12px;cursor:pointer;display:inline-flex">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
          Cambiar foto
        </label>
        <input id="photo-input" type="file" name="photo" style="display:none" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAndSubmitPhoto(event)">
      </form>

      <?php if ($photoNotification): ?>
        <div class="ri-alert <?php echo (strpos($photoNotification, 'Error') !== false || strpos($photoNotification, 'Solo') !== false || strpos($photoNotification, 'superar') !== false) ? 'ri-alert--error' : 'ri-alert--success'; ?>" style="margin:8px 0;text-align:left;font-size:13px">
          <?php echo htmlspecialchars($photoNotification); ?>
        </div>
      <?php endif; ?>

      <h2 class="profile-header__name"><?php echo htmlspecialchars($userName); ?></h2>
      <p class="profile-header__desc"><?php echo htmlspecialchars($userDescription); ?></p>
      <p class="profile-header__phone">📞 <?php echo htmlspecialchars($userPhone); ?></p>
    </div>

    <!-- Phone Update -->
    <div class="section">
      <h3 class="section__title">Actualizar telefono</h3>
      <div class="p-card">
        <form action="" method="POST">
          <?php echo csrfField(); ?>
          <div class="field">
            <label for="phone">Nuevo numero</label>
            <input type="text" name="phone" id="phone" placeholder="Ej. 6394851234" required pattern="[0-9]{7,15}">
          </div>
          <button type="submit" class="ri-btn ri-btn--block" style="height:44px;font-size:14px">Actualizar telefono</button>
        </form>
      </div>
      <?php if ($phoneNotification): ?>
        <div class="ri-alert <?php echo strpos($phoneNotification, 'Error') !== false || strpos($phoneNotification, 'invalido') !== false ? 'ri-alert--error' : 'ri-alert--success'; ?>" style="margin-top:8px;font-size:13px">
          <?php echo htmlspecialchars($phoneNotification); ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Payment Method -->
    <div class="section">
      <h3 class="section__title">Metodo de pago</h3>
      <div class="p-card">
        <p style="font-size:13px;color:var(--ri-mute);margin:0 0 10px">
          Actual: <span style="color:var(--ri-blue);font-weight:600"><?php echo htmlspecialchars($methodLabels[$userPaymentMethod] ?? 'Efectivo'); ?></span>
        </p>
        <form action="" method="POST">
          <?php echo csrfField(); ?>
          <div class="field">
            <label>Nuevo metodo</label>
            <select name="payment_method">
              <option value="efectivo" <?php echo $userPaymentMethod === 'efectivo' ? 'selected' : ''; ?>>Efectivo</option>
              <option value="tarjeta_debito" <?php echo $userPaymentMethod === 'tarjeta_debito' ? 'selected' : ''; ?>>Tarjeta de Debito</option>
              <option value="tarjeta_credito" <?php echo $userPaymentMethod === 'tarjeta_credito' ? 'selected' : ''; ?>>Tarjeta de Credito</option>
            </select>
          </div>
          <button type="submit" class="ri-btn ri-btn--block" style="height:44px;font-size:14px">Actualizar pago</button>
        </form>
      </div>
      <?php if ($paymentNotification): ?>
        <div class="ri-alert ri-alert--success" style="margin-top:8px;font-size:13px"><?php echo htmlspecialchars($paymentNotification); ?></div>
      <?php endif; ?>
    </div>

    <!-- Saved Addresses -->
    <div class="section">
      <h3 class="section__title">Direcciones guardadas</h3>
      <?php foreach ($preferences as $preference): ?>
        <div class="addr">
          <div class="addr__icon">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s-8-7.5-8-13a8 8 0 1 1 16 0c0 5.5-8 13-8 13z"/><circle cx="12" cy="9" r="3"/></svg>
          </div>
          <div class="addr__info">
            <div class="addr__type"><?php echo htmlspecialchars($preference['type']); ?></div>
            <div class="addr__text"><?php echo htmlspecialchars($preference['address']); ?></div>
          </div>
          <button type="button" onclick="confirmDeleteAddress('<?php echo htmlspecialchars(addslashes($preference['type'])); ?>')" class="ri-btn ri-btn--ghost" style="height:32px;padding:0 12px;font-size:11px;color:var(--ri-red);border-color:var(--ri-red-soft)">
            Eliminar
          </button>
        </div>
      <?php endforeach; ?>

      <div class="p-card" style="margin-top:12px">
        <form action="" method="POST">
          <?php echo csrfField(); ?>
          <div class="field">
            <label>Nombre (Hogar, Trabajo...)</label>
            <input type="text" name="type" placeholder="Ej. Hogar" required>
          </div>
          <div class="field">
            <label>Direccion</label>
            <input type="text" name="address" placeholder="Ej. Av. Cuauhtemoc 421" required>
          </div>
          <button type="submit" class="ri-btn ri-btn--green ri-btn--block" style="height:44px;font-size:14px">
            Agregar direccion
          </button>
        </form>
      </div>
      <?php if ($notification): ?>
        <div class="ri-alert <?php echo strpos($notification, 'Error') !== false || strpos($notification, 'Completa') !== false || strpos($notification, 'coordenadas') !== false ? 'ri-alert--error' : 'ri-alert--success'; ?>" style="margin-top:8px;font-size:13px">
          <?php echo htmlspecialchars($notification); ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Danger Zone -->
    <div class="danger">
      <h3>Zona de peligro</h3>
      <p>Al eliminar tu cuenta se borraran todos tus datos permanentemente.</p>
      <button type="button" onclick="confirmDeleteAccount()" class="ri-btn ri-btn--red ri-btn--block" style="height:44px;font-size:14px">
        Eliminar mi cuenta
      </button>
    </div>
  </div>
</div>

<script src="modal.js"></script>
<script>
function previewAndSubmitPhoto(event) {
    const file = event.target.files[0];
    if (!file) return;
    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        RideIt.alert({ title: 'Formato no valido', message: 'Solo se permiten imagenes JPG, PNG, GIF o WebP.', type: 'danger' });
        event.target.value = ''; return;
    }
    if (file.size > 5 * 1024 * 1024) {
        RideIt.alert({ title: 'Archivo muy grande', message: 'La imagen no debe superar 5MB.', type: 'danger' });
        event.target.value = ''; return;
    }
    const reader = new FileReader();
    reader.onload = function() {
        const pic = document.getElementById('profile-pic');
        if (pic) pic.src = reader.result;
        RideIt.confirm({
            title: 'Actualizar foto', message: '¿Guardar esta foto como tu foto de perfil?',
            type: 'info', confirmText: 'Guardar', confirmClass: 'btn-success',
            onConfirm: () => document.getElementById('photo-form').submit(),
            onCancel: () => { event.target.value = ''; if (pic) pic.src = '<?php echo htmlspecialchars($userPhoto); ?>'; }
        });
    };
    reader.readAsDataURL(file);
}
function confirmDeleteAddress(type) {
    RideIt.confirm({
        title: 'Eliminar direccion',
        message: '¿Eliminar <strong>"' + type + '"</strong>? No se puede deshacer.',
        type: 'danger', confirmText: 'Eliminar',
        onConfirm: () => window.location.href = '?delete=' + encodeURIComponent(type) + '&token=<?php echo urlencode($_SESSION['csrf_token'] ?? ''); ?>'
    });
}
function confirmDeleteAccount() {
    RideIt.confirm({
        title: 'Eliminar cuenta', message: 'Esta accion es <strong>irreversible</strong>. ¿Deseas continuar?',
        type: 'danger', confirmText: 'Si, continuar',
        onConfirm: () => {
            RideIt.prompt({
                title: 'Confirmar', message: 'Ingresa tu contrasena actual.',
                placeholder: 'Tu contrasena', inputType: 'password', type: 'danger', confirmText: 'Eliminar cuenta',
                onConfirm: (password) => {
                    if (!password) { RideIt.alert({ title: 'Error', message: 'Debes ingresar tu contrasena.', type: 'danger' }); return; }
                    const formData = new FormData();
                    formData.append('password', password);
                    formData.append('csrf_token', '<?php echo htmlspecialchars(generateCsrfToken()); ?>');
                    fetch('delete_account.php', { method: 'POST', body: formData })
                        .then(r => r.json()).then(data => {
                            if (data.success) { RideIt.alert({ title: 'Cuenta eliminada', message: data.message, type: 'success', onClose: () => window.location.href = data.redirect }); }
                            else { RideIt.alert({ title: 'Error', message: data.message, type: 'danger' }); }
                        }).catch(() => { RideIt.alert({ title: 'Error', message: 'Error de conexion.', type: 'danger' }); });
                }
            });
        }
    });
}
</script>
</body>
</html>
