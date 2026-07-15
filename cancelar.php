<?php
// cancelar.php la pagina del boton cancelar de los correos
// se llega con la direccion cancelar.php?t=token
// el token es el codigo secreto de 32 caracteres unico de cada reserva
// sin el token correcto no se puede cancelar nada
// la pagina primero muestra los datos y pide confirmacion
// solo cancela cuando la persona confirma con el boton rojo

require __DIR__ . '/lib.php'; // caja de herramientas

// el token puede venir por url en el primer paso o por formulario al confirmar
$token = $_GET['t'] ?? ($_POST['t'] ?? '');
// vista dice cual de las 4 pantallas se muestra abajo
// error confirmar hecho o ya
$vista = 'error';
$msg   = 'Enlace no válido.'; // mensaje por defecto si algo falla
$reserva = null;

// el token valido es exactamente 32 caracteres de letras a a f y numeros
// si no tiene esa forma ni siquiera consultamos la base
if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    // buscamos la reserva duena de ese token
    $st = db()->prepare("SELECT * FROM reservas_salas WHERE token = ?");
    $st->execute([$token]);
    $reserva = $st->fetch();

    if (!$reserva) {
        // token con buena forma pero no existe en la base
        $msg = 'No encontramos esta reserva.';
    } elseif ($reserva['estado'] !== 'confirmada') {
        // ya estaba cancelada de antes
        $vista = 'ya';
    } elseif ((int)$reserva['inicio'] <= time()) {
        // ya empezo o ya paso no tiene sentido cancelarla
        $msg = 'Esta reserva ya empezó o ya pasó; no se puede cancelar.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirmar'] ?? '') === '1') {
        // llego la confirmacion del boton rojo entonces si cancelamos
        // el and estado confirmada del final evita cancelar dos veces si le dan doble clic
        $up = db()->prepare("UPDATE reservas_salas SET estado = 'cancelada' WHERE id = ? AND estado = 'confirmada'");
        $up->execute([$reserva['id']]);
        correos_cancelacion($reserva); // avisamos por correo a ambas partes
        $vista = 'hecho';
    } else {
        // primer paso solo mostramos los datos y pedimos confirmacion
        $vista = 'confirmar';
    }
}

$cfg   = cfg();
// nombre bonito de la sala para mostrar
$salaN = $reserva ? ($cfg['salas'][$reserva['sala']]['nombre'] ?? $reserva['sala']) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cancelar reserva — Agro-Costa</title>
<style>
  /* colores de la marca */
  :root { --amarillo:#FFCD00; --negro:#111; }
  * { box-sizing:border-box; margin:0; padding:0; }
  /* centra la tarjeta en la pantalla */
  body { font-family:'Segoe UI',system-ui,sans-serif; background:#f4f4f4; min-height:100vh;
         display:flex; align-items:center; justify-content:center; padding:20px; }
  /* la tarjeta amarilla con borde negro */
  .tarjeta { background:var(--amarillo); border:2px solid #111; border-radius:20px;
             max-width:480px; width:100%; overflow:hidden; }
  /* franja negra de arriba con el titulo */
  .cabecera { background:#111; color:var(--amarillo); padding:22px; text-align:center;
              font-weight:800; text-transform:uppercase; font-size:18px; }
  .cuerpo { padding:30px; color:#111; line-height:1.7; }
  .cuerpo p { margin-bottom:10px; }
  .dato strong { color:#111; }
  .acciones { text-align:center; margin-top:28px; }
  /* botones negros con letra amarilla */
  button, .btn { background:#111; color:var(--amarillo); border:0; padding:15px 26px; font-size:15px;
           border-radius:10px; font-weight:bold; cursor:pointer; text-decoration:none; display:inline-block; }
  /* el de confirmar cancelacion es rojo para que se piense dos veces */
  button.rojo { background:#e5484d; color:#fff; }
  .nota { font-size:12px; color:#444; text-align:center; margin-top:26px; }
</style>
</head>
<body>
  <div class="tarjeta">
    <?php if ($vista === 'confirmar'): ?>
      <!-- pantalla 1 mostrar datos y pedir confirmacion -->
      <div class="cabecera">¿Cancelar esta reserva?</div>
      <div class="cuerpo">
        <p class="dato"><strong>Sala:</strong> <?= htmlspecialchars($salaN) ?></p>
        <p class="dato"><strong>Fecha:</strong> <?= fecha_larga((int)$reserva['inicio']) ?></p>
        <p class="dato"><strong>Hora:</strong> <?= hora_12((int)$reserva['inicio']) ?> a <?= hora_12((int)$reserva['fin']) ?></p>
        <p class="dato"><strong>A nombre de:</strong> <?= htmlspecialchars($reserva['nombre']) ?></p>
        <div class="acciones">
          <!-- el formulario reenvia el token mas la senal de confirmar -->
          <form method="post">
            <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
            <input type="hidden" name="confirmar" value="1">
            <button type="submit" class="rojo">Sí, cancelar la reserva</button>
          </form>
        </div>
        <p class="nota">Si no quieres cancelarla, simplemente cierra esta página.</p>
      </div>
    <?php elseif ($vista === 'hecho'): ?>
      <!-- pantalla 2 cancelacion realizada -->
      <div class="cabecera">Reserva cancelada</div>
      <div class="cuerpo" style="text-align:center;">
        <p style="font-size:44px;">✅</p>
        <p>La sala quedó libre. Enviamos la confirmación por correo.</p>
        <div class="acciones"><a class="btn" href="index.php">Hacer otra reserva</a></div>
      </div>
    <?php elseif ($vista === 'ya'): ?>
      <!-- pantalla 3 ya estaba cancelada -->
      <div class="cabecera">Ya estaba cancelada</div>
      <div class="cuerpo" style="text-align:center;">
        <p>Esta reserva ya había sido cancelada antes.</p>
        <div class="acciones"><a class="btn" href="index.php">Hacer otra reserva</a></div>
      </div>
    <?php else: ?>
      <!-- pantalla 4 algo fallo token malo reserva inexistente o ya paso -->
      <div class="cabecera">No se pudo</div>
      <div class="cuerpo" style="text-align:center;">
        <p><?= htmlspecialchars($msg) ?></p>
        <div class="acciones"><a class="btn" href="index.php">Ir a reservas</a></div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
