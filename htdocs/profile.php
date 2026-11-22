<?php
include 'db.php';
include 'csrf.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login_conductor.php");
    exit;
}
if (($_SESSION['user_role'] ?? '') !== 'conductor') {
    header("Location: dashboardpa.php");
    exit;
}

$userId = $_SESSION['user_id'];
$message = "";
$msgType = "";

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = "Token de seguridad invalido.";
        $msgType = "error";
    } else {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $phone = trim($_POST['phone']);
        $plate = trim($_POST['plate']);
        $photo = "";

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileType = mime_content_type($_FILES['photo']['tmp_name']);
            $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));

            if (!in_array($fileType, $allowedTypes) || !in_array($fileExt, $allowedExtensions)) {
                $message = "Solo se permiten imagenes JPG, PNG, GIF o WebP.";
                $msgType = "error";
            } elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
                $message = "La imagen no debe superar 5MB.";
                $msgType = "error";
            } else {
                $photoDir = 'uploads/';
                if (!is_dir($photoDir)) {
                    mkdir($photoDir, 0755, true);
                }
                $photoName = uniqid() . "." . $fileExt;
                $photoPath = $photoDir . $photoName;

                if (move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                    $photo = $photoPath;
                } else {
                    $message = "Error al cargar la foto de perfil.";
                    $msgType = "error";
                }
            }
        }

        if (empty($message)) {
            if ($photo) {
                $stmt = $conn->prepare("UPDATE users SET name = ?, description = ?, phone = ?, plate = ?, photo = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $name, $description, $phone, $plate, $photo, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name = ?, description = ?, phone = ?, plate = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $name, $description, $phone, $plate, $userId);
            }

            if ($stmt->execute()) {
                $message = "Perfil actualizado correctamente.";
                $msgType = "success";
            } else {
                $message = "Error al actualizar el perfil.";
                $msgType = "error";
            }
        }
    }
}

$stmt = $conn->prepare("SELECT name, description, phone, plate, photo FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Perfil del Conductor - RideIt</title>
<link rel="stylesheet" href="brand.css">
<link rel="stylesheet" href="modal.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 480px; margin: 0 auto; min-height: 100vh; background: var(--ri-surface-2); }

  .topbar {
    position: sticky; top: 0; z-index: 10;
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

  .content { padding: 20px; }

  .photo-section { text-align: center; margin-bottom: 24px; }
  .photo-section__img {
    width: 96px; height: 96px; border-radius: 50%;
    border: 3px solid var(--ri-blue);
    object-fit: cover; display: block; margin: 0 auto 12px;
  }
  .photo-section__placeholder {
    width: 96px; height: 96px; border-radius: 50%;
    border: 3px solid var(--ri-line);
    background: var(--ri-surface-3);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px; color: var(--ri-mute);
  }

  .form-card {
    background: #fff; border: 1px solid var(--ri-line);
    border-radius: 18px; padding: 24px; margin-bottom: 16px;
  }
  .form-card h3 { font-size: 16px; font-weight: 700; margin: 0 0 16px; }

  .field { margin-bottom: 14px; }
  .field label { display: block; font-size: 13px; font-weight: 600; color: var(--ri-mute); margin-bottom: 6px; }
  .field label .req { color: var(--ri-red); }
  .field input, .field textarea {
    width: 100%; padding: 12px 14px;
    border: 1.5px solid var(--ri-line);
    border-radius: var(--ri-r-md);
    background: var(--ri-surface-3);
    font: inherit; font-size: 14px; color: var(--ri-ink);
    transition: border-color .15s;
  }
  .field input:focus, .field textarea:focus {
    outline: none; border-color: var(--ri-ink); background: #fff;
  }
  .field textarea { resize: vertical; min-height: 80px; }
  .field__hint { font-size: 12px; color: var(--ri-mute); margin-top: 4px; }

  .danger-zone {
    background: #fff; border: 1px solid var(--ri-red-soft);
    border-radius: 18px; padding: 24px; margin-bottom: 40px;
  }
  .danger-zone h3 { font-size: 16px; font-weight: 700; color: var(--ri-red); margin: 0 0 8px; }
  .danger-zone p { font-size: 13px; color: var(--ri-mute); margin: 0 0 16px; }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="dashboard.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Perfil del Conductor</span>
  </div>

  <div class="content">
    <?php if (!empty($message)): ?>
      <div class="ri-alert ri-alert--<?php echo $msgType === 'error' ? 'error' : 'success'; ?>" style="margin-bottom:16px">
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php endif; ?>

    <form action="profile.php" method="POST" enctype="multipart/form-data">
      <?php echo csrfField(); ?>

      <div class="photo-section">
        <?php if (!empty($user['photo'])): ?>
          <img id="profile-pic" src="<?php echo htmlspecialchars($user['photo']); ?>" alt="Foto de perfil" class="photo-section__img">
        <?php else: ?>
          <div class="photo-section__placeholder" id="profile-pic-placeholder">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a8 8 0 00-8 8h16a8 8 0 00-8-8z"/></svg>
          </div>
          <img id="profile-pic" src="" alt="Foto de perfil" class="photo-section__img" style="display:none">
        <?php endif; ?>
        <label for="photo" class="ri-btn ri-btn--ghost" style="height:36px;font-size:13px;cursor:pointer">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
          Cambiar foto
        </label>
        <input id="photo" type="file" name="photo" style="display:none" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewPhoto(event)">
      </div>

      <div class="form-card">
        <h3>Informacion personal</h3>

        <div class="field">
          <label for="name">Nombre <span class="req">*</span></label>
          <input id="name" name="name" type="text" required minlength="3" maxlength="100" value="<?php echo htmlspecialchars($user['name']); ?>">
        </div>

        <div class="field">
          <label for="description">Descripcion</label>
          <textarea id="description" name="description" maxlength="500"><?php echo htmlspecialchars($user['description']); ?></textarea>
          <div class="field__hint"><span id="desc-count">0</span>/500 caracteres</div>
        </div>

        <div class="field">
          <label for="phone">Telefono <span class="req">*</span></label>
          <input id="phone" name="phone" type="tel" required pattern="[0-9]{7,15}" value="<?php echo htmlspecialchars($user['phone']); ?>">
          <div class="field__hint">Solo numeros, 7-15 digitos</div>
        </div>

        <div class="field">
          <label for="plate">Placa del Vehiculo <span class="req">*</span></label>
          <input id="plate" name="plate" type="text" required maxlength="20" value="<?php echo htmlspecialchars($user['plate']); ?>">
        </div>

        <button type="button" onclick="confirmSaveProfile()" class="ri-btn ri-btn--lg ri-btn--block" style="--bg:var(--ri-green)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M20 6L9 17l-5-5"/></svg>
          Guardar Cambios
        </button>
      </div>
    </form>

    <div class="danger-zone">
      <h3>Zona de peligro</h3>
      <p>Al eliminar tu cuenta se borraran todos tus datos permanentemente. Los viajes completados se anonimizaran.</p>
      <button type="button" onclick="confirmDeleteAccount()" class="ri-btn ri-btn--block" style="--bg:var(--ri-red);height:44px;font-size:14px">
        Eliminar mi cuenta
      </button>
    </div>
  </div>
</div>

<script src="modal.js"></script>
<script>
function previewPhoto(event) {
    var file = event.target.files[0];
    if (!file) return;
    var validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (validTypes.indexOf(file.type) === -1) {
        RideIt.alert({ title: 'Formato no valido', message: 'Solo se permiten imagenes JPG, PNG, GIF o WebP.', type: 'danger' });
        event.target.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        RideIt.alert({ title: 'Archivo muy grande', message: 'La imagen no debe superar 5MB.', type: 'danger' });
        event.target.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function() {
        var img = document.getElementById('profile-pic');
        img.src = reader.result;
        img.style.display = 'block';
        var placeholder = document.getElementById('profile-pic-placeholder');
        if (placeholder) placeholder.style.display = 'none';
        RideIt.toast('Foto seleccionada. Guarda los cambios para aplicarla.', 'info');
    };
    reader.readAsDataURL(file);
}

var descEl = document.getElementById('description');
var descCount = document.getElementById('desc-count');
if (descEl && descCount) {
    descCount.textContent = descEl.value.length;
    descEl.addEventListener('input', function() { descCount.textContent = descEl.value.length; });
}

function confirmSaveProfile() {
    var name = document.getElementById('name').value.trim();
    var phone = document.getElementById('phone').value.trim();
    var plate = document.getElementById('plate').value.trim();

    if (!name || name.length < 3) {
        RideIt.alert({ title: 'Nombre requerido', message: 'El nombre debe tener al menos 3 caracteres.', type: 'warning' });
        return;
    }
    if (!phone || !/^[0-9]{7,15}$/.test(phone)) {
        RideIt.alert({ title: 'Telefono invalido', message: 'Ingresa un numero de telefono valido (solo numeros, 7-15 digitos).', type: 'warning' });
        return;
    }
    if (!plate) {
        RideIt.alert({ title: 'Placa requerida', message: 'Debes ingresar la placa de tu vehiculo.', type: 'warning' });
        return;
    }

    RideIt.confirm({
        title: 'Guardar cambios',
        message: '¿Deseas guardar los cambios en tu perfil?',
        type: 'info',
        confirmText: 'Guardar',
        confirmClass: 'btn-success',
        onConfirm: function() { document.querySelector('form[action="profile.php"]').submit(); }
    });
}

function confirmDeleteAccount() {
    RideIt.confirm({
        title: 'Eliminar cuenta',
        message: 'Esta accion es <strong>irreversible</strong>. Se eliminaran todos tus datos personales y preferencias. ¿Deseas continuar?',
        type: 'danger',
        confirmText: 'Si, continuar',
        onConfirm: function() {
            RideIt.prompt({
                title: 'Confirmar eliminacion',
                message: 'Ingresa tu contraseña actual para confirmar la eliminacion de tu cuenta.',
                placeholder: 'Tu contraseña',
                inputType: 'password',
                type: 'danger',
                confirmText: 'Eliminar cuenta',
                onConfirm: function(password) {
                    if (!password) {
                        RideIt.alert({ title: 'Error', message: 'Debes ingresar tu contraseña.', type: 'danger' });
                        return;
                    }
                    var formData = new FormData();
                    formData.append('password', password);
                    formData.append('csrf_token', '<?php echo htmlspecialchars(generateCsrfToken()); ?>');

                    fetch('delete_account.php', { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.success) {
                                RideIt.alert({ title: 'Cuenta eliminada', message: data.message, type: 'success', onClose: function() { window.location.href = data.redirect; } });
                            } else {
                                RideIt.alert({ title: 'Error', message: data.message, type: 'danger' });
                            }
                        })
                        .catch(function() {
                            RideIt.alert({ title: 'Error', message: 'Error de conexion. Intenta de nuevo.', type: 'danger' });
                        });
                }
            });
        }
    });
}
</script>
</body>
</html>
