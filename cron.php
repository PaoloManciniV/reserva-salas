<?php
// cron.php recordatorios una hora antes
// este archivo no lo abre la gente
// el hosting lo ejecuta solo cada 5 minutos mediante un trabajo cron
// cada pasada pregunta hay alguna reserva confirmada que empiece dentro de los proximos 60 minutos y que no haya sido avisada
// si la hay manda recordatorio a quien reservo y a los encargados y la marca como avisada
// gracias a esa marca cada reserva recibe un solo recordatorio aunque el cron corra mil veces

require __DIR__ . '/lib.php'; // traemos la caja de herramientas con bd y correos

$cfg = cfg(); // cargamos la configuracion

// seguridad
// solo se permite ejecutar desde la linea de comandos del servidor
// o por url si trae la clave correcta que esta en config
// asi un extrano que descubra la direccion no puede dispararlo
if (php_sapi_name() !== 'cli' && (($_GET['key'] ?? '') !== $cfg['cron_key'])) {
    http_response_code(403); // codigo prohibido
    exit('No autorizado');   // corta aqui mismo
}

header('Content-Type: text/plain; charset=utf-8'); // la respuesta es texto simple

$ahora = time();          // este instante medido en segundos
$hasta = $ahora + 3600;   // el instante de aqui a una hora

// buscamos reservas confirmadas con recordada en 0 o sea sin avisar
// cuyo inicio caiga despues de ahora pero antes de una hora
$st = db()->prepare("SELECT * FROM reservas_salas
                     WHERE estado = 'confirmada' AND recordada = 0
                       AND inicio > ? AND inicio <= ?");
$st->execute([$ahora, $hasta]);
$filas = $st->fetchAll(); // todas las que cumplen

$enviados = 0; // contador para el resumen final
foreach ($filas as $r) {
    correos_recordatorio($r); // manda los dos correos el del usuario y el de los encargados
    // marcamos recordada en 1 para que la proxima pasada del cron no la vuelva a avisar
    $up = db()->prepare("UPDATE reservas_salas SET recordada = 1 WHERE id = ?");
    $up->execute([$r['id']]);
    $enviados++;
}

// imprime fecha y cuantos se mandaron
// asi si abres la url en el navegador ves que esta vivo
echo date('Y-m-d H:i:s') . " — recordatorios enviados: $enviados\n";
