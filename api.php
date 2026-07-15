<?php
// api.php la ventanilla del sistema
// la pagina index.php no habla directo con la base de datos
// su javascript le manda peticiones a este archivo y este responde en formato json
// json es texto con estructura que el navegador entiende facil
// hay tres tramites
// slots devuelve las horas libres
// reservar valida todo guarda y manda los correos
// tablero devuelve las reservas proximas para el panel

require __DIR__ . '/lib.php'; // caja de herramientas

header('Content-Type: application/json; charset=utf-8'); // avisamos que la respuesta es json
header('Cache-Control: no-store'); // que el navegador no guarde copia vieja de las respuestas

// funcion de salida unica
// convierte el arreglo a json lo imprime y corta la ejecucion ahi mismo
function salida(array $arr): void {
    echo json_encode($arr, JSON_UNESCAPED_UNICODE); // unescaped unicode deja las tildes legibles
    exit;
}

// la accion pedida puede venir por url o por formulario si no viene queda vacia
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {

    // ---------------------------------------------------- horas disponibles
    if ($action === 'slots') {
        // recogemos lo que pide el navegador con valores por defecto vacios
        $sala  = $_GET['sala']  ?? '';
        $fecha = $_GET['fecha'] ?? '';
        $dur   = (int)($_GET['dur'] ?? 0); // int convierte el texto a numero entero

        // validamos sala fecha y duracion contra las reglas de config
        // si algo esta mal devuelve el mensaje y si todo bien devuelve null
        $err = validar_consulta($sala, $fecha, $dur);
        if ($err !== null) salida(['ok' => false, 'error' => $err]);

        // todo bien entonces calculamos y devolvemos los grupos de horas libres
        salida(['ok' => true, 'grupos' => slots_disponibles($sala, $fecha, $dur)]);
    }

    // ---------------------------------------------------- crear reserva
    if ($action === 'reservar') {
        // solo se acepta por post que es el metodo de los formularios
        // por get seria muy facil disparar reservas con un simple enlace
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') salida(['ok' => false, 'error' => 'Método no permitido.']);

        // trampa anti bots
        // el formulario tiene un campo invisible llamado web
        // una persona no lo ve y lo deja vacio
        // un robot que llena todo lo llena tambien
        // si viene lleno respondemos ok falso o sea le decimos listo pero no guardamos nada
        if (trim($_POST['web'] ?? '') !== '') salida(['ok' => true]);

        // recogemos los datos del formulario
        $sala   = $_POST['sala']   ?? '';
        $fecha  = $_POST['fecha']  ?? '';
        $hora   = $_POST['hora']   ?? '';
        $dur    = (int)($_POST['dur'] ?? 0);
        $nombre = trim(strip_tags($_POST['nombre'] ?? '')); // strip_tags quita etiquetas html y trim quita espacios de los bordes
        $correo = trim($_POST['correo'] ?? '');

        // primera tanda de validaciones las mismas reglas de config
        $err = validar_consulta($sala, $fecha, $dur);
        if ($err !== null) salida(['ok' => false, 'error' => $err]);
        // la hora debe verse como dos digitos dos puntos dos digitos
        if (!preg_match('/^\d{2}:\d{2}$/', $hora)) salida(['ok' => false, 'error' => 'Hora no válida.']);
        // el nombre debe medir entre 3 y 100 letras mb_strlen cuenta bien las tildes y la ene
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) salida(['ok' => false, 'error' => 'Escribe tu nombre completo.']);
        // filter_var revisa que el correo tenga forma de correo de verdad
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) salida(['ok' => false, 'error' => 'El correo no es válido.']);

        // segunda validacion clave
        // la hora pedida tiene que estar dentro de las horas libres calculadas por el servidor
        // asi nadie puede inventarse una hora manipulando la pagina
        $valida = false;
        foreach (slots_disponibles($sala, $fecha, $dur) as $g) {
            // in_array pregunta si la hora esta en la lista del grupo
            if (in_array($hora, $g['horas'], true)) { $valida = true; break; }
        }
        if (!$valida) salida(['ok' => false, 'error' => 'Ese horario ya no está disponible. Elige otro.']);

        // convertimos fecha y hora a un timestamp
        // timestamp es un numero de segundos unico que no depende de formatos
        $cfg = cfg();
        $tz  = new DateTimeZone($cfg['zona']); // zona horaria de colombia
        $ini = DateTime::createFromFormat('Y-m-d H:i', "$fecha $hora", $tz)->getTimestamp();
        $fin = $ini + $dur * 60; // el fin es inicio mas la duracion pasada a segundos

        // intentamos guardar con el candado anti choques que vive en lib
        $res = crear_reserva($sala, $ini, $fin, $nombre, $correo);
        if (!$res['ok']) salida(['ok' => false, 'error' => $res['error']]);

        // guardado listo ahora los correos
        // si el correo fallara la reserva igual queda guardada y el error queda en el log
        correos_nueva_reserva($res['reserva']);

        // devolvemos el resumen bonito que la pagina muestra en la pantalla de exito
        $r = $res['reserva'];
        salida(['ok' => true, 'resumen' => [
            'sala'  => $cfg['salas'][$sala]['nombre'],
            'fecha' => fecha_larga($ini),
            'hora'  => hora_12($ini) . ' a ' . hora_12($fin),
            'dur'   => dur_txt($dur),
            'correo'=> $correo,
        ]]);
    }

    // ---------------------------------------------------- tablero o sea el panel tipo aeropuerto
    if ($action === 'tablero') {
        cfg(); // esto de paso fija la zona horaria para que las horas salgan en hora colombia

        // trae hasta 40 reservas confirmadas cuyo fin sea despues de ahora
        // o sea las que estan en curso y las que vienen ordenadas por hora de inicio
        $st = db()->prepare("SELECT sala, nombre, inicio, fin FROM reservas_salas
                             WHERE estado = 'confirmada' AND fin > ?
                             ORDER BY inicio ASC
                             LIMIT 40");
        $st->execute([time()]);

        $hoy = date('Y-m-d'); // fecha de hoy para marcar cuales reservas son de hoy
        $items = [];
        foreach ($st->fetchAll() as $r) {
            $ini = (int)$r['inicio'];
            $items[] = [
                'sala'   => $r['sala'],                        // etiqueta grande o pequena
                'nombre' => $r['nombre'],                      // quien reservo
                'dia'    => date('d/m', $ini),                 // dia y mes para mostrar en el panel
                'es_hoy' => (date('Y-m-d', $ini) === $hoy),    // verdadero si la reserva es de hoy
                'ini'    => date('H:i', $ini),                 // hora de inicio para mostrar
                'fin'    => date('H:i', (int)$r['fin']),       // hora de fin para mostrar
                'ini_ts' => $ini,                              // los timestamps van para que el panel
                'fin_ts' => (int)$r['fin'],                    // calcule si esta en curso o programada
            ];
        }
        // ahora es la hora del servidor el panel la usa como reloj confiable
        salida(['ok' => true, 'ahora' => time(), 'items' => $items]);
    }

    // si llego hasta aqui pidieron una accion que no existe
    salida(['ok' => false, 'error' => 'Acción desconocida.']);

} catch (Throwable $e) {
    // cualquier error inesperado cae aqui
    // se anota el detalle en el log y al usuario se le da un mensaje generico
    log_correo('ERROR API: ' . $e->getMessage());
    salida(['ok' => false, 'error' => 'Error del servidor. Intenta de nuevo en un momento.']);
}
