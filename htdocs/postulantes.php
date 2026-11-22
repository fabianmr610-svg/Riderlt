<?php
require_once 'config.php';
include 'csrf.php';
session_start();

$conn = new mysqli(DB_HOST_REMOTE, DB_USER_REMOTE, DB_PASS_REMOTE, DB_NAME_REMOTE);

if ($conn->connect_error) {
    die("Error de conexion.");
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM applications WHERE full_name LIKE ?");
    $searchParam = "%" . $search . "%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM applications");
}

$msg = "";
$msgType = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['status'])) {
    if (!validateCsrfToken()) {
        $msg = "Token de seguridad invalido.";
        $msgType = "error";
    } else {
        $id = (int)$_POST['id'];
        $status = $_POST['status'];

        $allowedStatuses = ['Aceptado', 'Rechazado', 'Pendiente'];
        if (in_array($status, $allowedStatuses)) {
            $stmt = $conn->prepare("UPDATE applications SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);

            if ($stmt->execute()) {
                $msg = "Estado actualizado correctamente.";
                $msgType = "success";
            } else {
                $msg = "Error al actualizar el estado.";
                $msgType = "error";
            }
            $stmt->close();
        }
    }
}

if (isset($_GET['view_id'])) {
    $view_id = (int)$_GET['view_id'];
    $view_query = $conn->prepare("SELECT * FROM applications WHERE id = ?");
    $view_query->bind_param("i", $view_id);
    $view_query->execute();
    $application_details = $view_query->get_result()->fetch_assoc();
    $view_query->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Postulantes - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 900px; margin: 0 auto; padding: 0 16px 48px; }

  .topbar {
    position: sticky; top: 0; z-index: 10;
    display: flex; align-items: center; gap: 12px;
    padding: 14px 0; margin-bottom: 20px;
    background: rgba(255,255,255,.82);
    backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
  }
  .topbar__back {
    width: 38px; height: 38px; border-radius: 50%; border: 1.5px solid var(--ri-line);
    background: #fff; display: flex; align-items: center; justify-content: center;
    color: var(--ri-ink); cursor: pointer; text-decoration: none;
  }
  .topbar__title { font-size: 17px; font-weight: 700; }

  /* Search */
  .search-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 16px 20px; margin-bottom: 20px;
  }
  .search-form { display: flex; gap: 10px; }
  .search-form .ri-input { flex: 1; }

  /* Table */
  .table-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    overflow: hidden; margin-bottom: 20px;
  }
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead { background: var(--ri-surface-2); }
  th { padding: 12px 16px; text-align: left; font-weight: 700; color: var(--ri-mute); font-size: 11px; text-transform: uppercase; letter-spacing: .05em; }
  td { padding: 14px 16px; border-top: 1px solid var(--ri-line); color: var(--ri-ink-2); }
  tr:hover td { background: var(--ri-surface); }
  .td-name { font-weight: 700; color: var(--ri-ink); }

  .status-chip {
    display: inline-block; padding: 3px 10px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
  }
  .status-chip--Pendiente { background: #fef3c7; color: #92400e; }
  .status-chip--Aceptado { background: var(--ri-green-soft); color: var(--ri-green); }
  .status-chip--Rechazado { background: #fef2f2; color: var(--ri-red); }

  .action-btns { display: flex; gap: 6px; align-items: center; }
  .action-btn {
    padding: 6px 12px; border-radius: 8px; border: none;
    font-size: 12px; font-weight: 700; cursor: pointer; transition: opacity .2s;
  }
  .action-btn:hover { opacity: .8; }
  .action-btn--accept { background: var(--ri-green); color: #fff; }
  .action-btn--reject { background: var(--ri-red); color: #fff; }
  .action-btn--view { background: var(--ri-blue-soft); color: var(--ri-blue); text-decoration: none; display: inline-block; }

  /* Detail card */
  .detail-card {
    background: #fff; border: 1px solid var(--ri-line); border-radius: 16px;
    padding: 24px; margin-bottom: 20px;
  }
  .detail-card h2 { font-size: 18px; font-weight: 800; margin: 0 0 16px; }
  .detail-card p { font-size: 14px; color: var(--ri-ink-2); margin-bottom: 8px; line-height: 1.5; }
  .detail-card p strong { color: var(--ri-mute); font-weight: 600; }

  .empty-state { padding: 48px 16px; text-align: center; color: var(--ri-mute); }

  .ri-footer { padding: 24px 20px; text-align: center; font-size: 13px; color: var(--ri-mute); }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="admin_panel.php" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Gestion de Postulantes</span>
  </div>

  <?php if ($msg): ?>
    <div class="ri-alert ri-alert--<?php echo $msgType; ?>" style="margin-bottom:16px"><?php echo htmlspecialchars($msg); ?></div>
  <?php endif; ?>

  <!-- Search -->
  <div class="search-card">
    <form method="GET" class="search-form">
      <div class="ri-input" style="flex:1">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--ri-mute);flex:none"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input type="text" name="search" placeholder="Buscar por nombre..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
      <button type="submit" class="ri-btn ri-btn--blue" style="height:48px">Buscar</button>
    </form>
  </div>

  <?php if (isset($application_details)): ?>
    <div class="detail-card">
      <h2>Detalles de la Aplicacion</h2>
      <p><strong>Nombre:</strong> <?php echo htmlspecialchars($application_details['full_name']); ?></p>
      <p><strong>Telefono:</strong> <?php echo htmlspecialchars($application_details['phone']); ?></p>
      <p><strong>Correo:</strong> <?php echo htmlspecialchars($application_details['email']); ?></p>
      <p><strong>Auto propio:</strong> <?php echo htmlspecialchars($application_details['own_car']); ?></p>
      <p><strong>Marca y modelo:</strong> <?php echo htmlspecialchars($application_details['car_details']); ?></p>
      <p><strong>Ano del auto:</strong> <?php echo htmlspecialchars($application_details['car_year']); ?></p>
      <p><strong>Licencia:</strong> <?php echo htmlspecialchars($application_details['license']); ?></p>
      <p><strong>Por que seria buen conductor:</strong> <?php echo htmlspecialchars($application_details['trustworthy']); ?></p>
      <p><strong>Responsabilidad:</strong> <?php echo htmlspecialchars($application_details['reliability']); ?></p>
      <a href="postulantes.php" class="ri-btn" style="margin-top:12px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Cerrar detalles
      </a>
    </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="table-card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Telefono</th>
            <th>Correo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php $st = $row['status'] ?? 'Pendiente'; ?>
              <tr>
                <td class="td-name"><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td>
                  <span class="status-chip status-chip--<?php echo $st; ?>">
                    <?php echo htmlspecialchars($st); ?>
                  </span>
                </td>
                <td>
                  <div class="action-btns">
                    <form method="POST" style="display:inline">
                      <?php echo csrfField(); ?>
                      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                      <button type="submit" name="status" value="Aceptado" class="action-btn action-btn--accept">Aceptar</button>
                      <button type="submit" name="status" value="Rechazado" class="action-btn action-btn--reject">Rechazar</button>
                    </form>
                    <a href="?view_id=<?php echo (int)$row['id']; ?>" class="action-btn action-btn--view">Ver</a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5">
              <div class="empty-state">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;opacity:.4;display:block"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="17" y1="11" x2="23" y2="11"/></svg>
                <p>No se encontraron aplicaciones.</p>
              </div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="ri-footer">&copy; 2025 RideIt. Todos los derechos reservados.</div>

</body>
</html>
