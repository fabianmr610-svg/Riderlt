<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Terminos y Condiciones - RideIt</title>
<link rel="stylesheet" href="brand.css">
<style>
  body { background: var(--ri-surface-2); min-height: 100vh; }
  .shell { max-width: 680px; margin: 0 auto; min-height: 100vh; background: var(--ri-surface-2); }

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

  .content {
    background: #fff;
    margin: 20px;
    border-radius: 18px;
    border: 1px solid var(--ri-line);
    padding: 28px 24px;
  }
  .content h2 { font-size: 24px; font-weight: 800; letter-spacing: -0.02em; margin: 0 0 4px; }
  .content .date { font-size: 13px; color: var(--ri-mute); margin-bottom: 24px; }

  .content section { margin-bottom: 20px; }
  .content h3 { font-size: 15px; font-weight: 700; color: var(--ri-blue); margin: 0 0 8px; }
  .content p, .content li { font-size: 14px; color: var(--ri-ink-2); line-height: 1.6; }
  .content ul { padding-left: 20px; margin: 6px 0; }
  .content li { margin-bottom: 4px; }

  .ri-footer { padding: 24px 20px; text-align: center; font-size: 13px; color: var(--ri-mute); }
</style>
</head>
<body>

<div class="shell">
  <div class="topbar">
    <a href="javascript:history.back()" class="topbar__back">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>
    <span class="topbar__title">Terminos y Condiciones</span>
  </div>

  <div class="content">
    <h2>Terminos y Condiciones de Uso</h2>
    <p class="date">Ultima actualizacion: Mayo 2026</p>

    <section>
      <h3>1. Aceptacion de los terminos</h3>
      <p>Al registrarte y utilizar RideIt, aceptas estos terminos y condiciones. Si no estas de acuerdo, no utilices la plataforma.</p>
    </section>

    <section>
      <h3>2. Descripcion del servicio</h3>
      <p>RideIt es una plataforma que conecta pasajeros con conductores para servicios de transporte. RideIt no es una empresa de transporte, sino un intermediario tecnologico.</p>
    </section>

    <section>
      <h3>3. Requisitos de registro</h3>
      <ul>
        <li>Ser mayor de 18 anios</li>
        <li>Proporcionar datos veridicos y actualizados</li>
        <li>Mantener la confidencialidad de tu cuenta</li>
      </ul>
    </section>

    <section>
      <h3>4. Tarifas y pagos</h3>
      <p>Las tarifas se calculan automaticamente con base en la distancia del recorrido. La tarifa mostrada al momento de solicitar el viaje es estimada y puede variar si se modifica el destino.</p>
    </section>

    <section>
      <h3>5. Cancelaciones</h3>
      <p>El pasajero puede cancelar un viaje sin costo antes de que el conductor llegue al punto de recogida. Cancelaciones frecuentes pueden resultar en restricciones de la cuenta.</p>
    </section>

    <section>
      <h3>6. Conducta del usuario</h3>
      <p>Los usuarios se comprometen a tratar con respeto a conductores y demas usuarios. Cualquier comportamiento inapropiado podra resultar en la suspension de la cuenta.</p>
    </section>

    <section>
      <h3>7. Seguridad</h3>
      <p>RideIt cuenta con un boton de panico (SOS) disponible durante los viajes activos. En caso de emergencia, la plataforma enviara una alerta con tu ubicacion.</p>
    </section>

    <section>
      <h3>8. Contacto</h3>
      <p>Para dudas o aclaraciones, utiliza la seccion de Ayuda dentro de la aplicacion o escribe a soporte@rideit.com</p>
    </section>
  </div>

  <div class="ri-footer">&copy; 2025 RideIt. Todos los derechos reservados.</div>
</div>

</body>
</html>
