<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Aviso de Privacidad - RideIt</title>
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
    <span class="topbar__title">Aviso de Privacidad</span>
  </div>

  <div class="content">
    <h2>Aviso de Privacidad</h2>
    <p class="date">Ultima actualizacion: Mayo 2026</p>

    <section>
      <h3>1. Responsable del tratamiento</h3>
      <p>RideIt, con domicilio en Cd. Delicias, Chihuahua, Mexico, es responsable del tratamiento de tus datos personales.</p>
    </section>

    <section>
      <h3>2. Datos que recopilamos</h3>
      <ul>
        <li>Nombre completo</li>
        <li>Correo electronico</li>
        <li>Numero de telefono</li>
        <li>Ubicacion GPS (solo durante el uso activo de la aplicacion)</li>
        <li>Historial de viajes</li>
        <li>Calificaciones y comentarios</li>
      </ul>
    </section>

    <section>
      <h3>3. Finalidad del tratamiento</h3>
      <p>Tus datos personales seran utilizados para:</p>
      <ul>
        <li>Crear y administrar tu cuenta de usuario</li>
        <li>Conectarte con conductores disponibles</li>
        <li>Calcular rutas, tarifas y tiempos estimados de llegada</li>
        <li>Garantizar la seguridad durante los viajes</li>
        <li>Enviar notificaciones sobre el estado de tus viajes</li>
        <li>Mejorar la calidad del servicio</li>
      </ul>
    </section>

    <section>
      <h3>4. Uso de la ubicacion</h3>
      <p>RideIt solicita permiso para acceder a la ubicacion de tu dispositivo. Este permiso es necesario para mostrar tu posicion en el mapa y calcular rutas. Tu ubicacion solo se utiliza mientras la aplicacion esta activa y no se almacena de forma permanente.</p>
    </section>

    <section>
      <h3>5. Proteccion de datos</h3>
      <p>Implementamos medidas de seguridad tecnicas y administrativas para proteger tus datos personales, incluyendo:</p>
      <ul>
        <li>Cifrado de contrasenas</li>
        <li>Conexiones seguras (HTTPS)</li>
        <li>Tokens de seguridad contra ataques CSRF</li>
        <li>Acceso restringido a la base de datos</li>
      </ul>
    </section>

    <section>
      <h3>6. Derechos ARCO</h3>
      <p>Tienes derecho a Acceder, Rectificar, Cancelar u Oponerte al tratamiento de tus datos personales. Para ejercer estos derechos, contactanos a traves de la seccion de Ayuda en la aplicacion.</p>
    </section>

    <section>
      <h3>7. Contacto</h3>
      <p>Para cualquier duda sobre este aviso de privacidad, contactanos en: soporte@rideit.com</p>
    </section>
  </div>

  <div class="ri-footer">&copy; 2025 RideIt. Todos los derechos reservados.</div>
</div>

</body>
</html>
