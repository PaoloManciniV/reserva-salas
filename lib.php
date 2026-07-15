<?php
// lib.php la caja de herramientas
// aqui viven las funciones que usan los demas archivos
// nadie visita este archivo por url solo se presta
// tiene 4 grupos
// 1 configuracion y base de datos
// 2 fechas y horas en espanol
// 3 el cerebro de la disponibilidad
// 4 el cartero o sea los correos y sus plantillas

// ---------------------------------------------------------------- configuracion
// cfg abre la caja de config.php una sola vez y la guarda en memoria
// static hace que la variable sobreviva entre llamadas
// asi aunque 20 funciones pidan la config el archivo se lee una sola vez
function cfg(): array {
    static $cfg = null;
    if ($cfg === null) { // primera vez que alguien la pide
        $cfg = require __DIR__ . '/config.php'; // trae el arreglo
        date_default_timezone_set($cfg['zona']); // desde aqui todas las fechas son hora colombia
    }
    return $cfg;
}

// ---------------------------------------------------------------- base de datos
// db entrega la conexion a mysql tambien una sola vez por peticion
// pdo es la forma moderna y segura de hablar con la base en php
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) { // primera vez conectamos
        $b = cfg()['bd']; // datos de conexion desde config
        // el dsn es la direccion completa de la base con juego de caracteres utf8mb4 que soporta tildes y emojis
        $dsn = 'mysql:host=' . $b['host'] . ';dbname=' . $b['nombre'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $b['usuario'], $b['clave'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,          // si algo falla que lance error y no silencio
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // los resultados llegan como arreglo con nombres de columna
        ]);
        // crea la tabla si no existe asi no toca montarla a mano
        // inicio y fin se guardan como timestamps o sea numeros de segundos
        // token es el codigo secreto unico de cada reserva para poder cancelarla
        // estado arranca en confirmada y pasa a cancelada si la cancelan
        // recordada 0 significa que aun no se ha enviado el recordatorio
        // el indice idx_busqueda acelera las consultas de choques y del tablero
        $pdo->exec("CREATE TABLE IF NOT EXISTS reservas_salas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sala VARCHAR(20) NOT NULL,
            inicio INT UNSIGNED NOT NULL,
            fin INT UNSIGNED NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            correo VARCHAR(150) NOT NULL,
            token CHAR(32) NOT NULL UNIQUE,
            estado VARCHAR(15) NOT NULL DEFAULT 'confirmada',
            recordada TINYINT NOT NULL DEFAULT 0,
            creada INT UNSIGNED NOT NULL,
            KEY idx_busqueda (sala, estado, inicio, fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    return $pdo;
}

// ---------------------------------------------------------------- fechas en espanol
// php no trae los nombres de dias y meses en espanol por defecto
// asi que los escribimos nosotros y listo

// convierte un timestamp en algo como martes 14 de julio de 2026
function fecha_larga(int $ts): string {
    $dias  = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo']; // el hueco inicial es porque date N cuenta desde 1
    $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
              'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']; // arranca en 1 y sigue solo
    return $dias[(int)date('N', $ts)] . ', ' . (int)date('j', $ts) . ' de '
         . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
}

// convierte un timestamp en hora de 12 como 2:30 p m
function hora_12(int $ts): string {
    $ap = date('a', $ts) === 'am' ? 'a. m.' : 'p. m.'; // date a dice am o pm
    return date('g:i', $ts) . ' ' . $ap; // g es la hora sin cero adelante
}

// convierte minutos en texto como 1 hora o 2 horas o 1 h 30 min
function dur_txt(int $min): string {
    $h = intdiv($min, 60); // division entera para sacar las horas
    $m = $min % 60;        // el resto son los minutos sueltos
    if ($h > 0 && $m > 0) return $h . ' h ' . $m . ' min'; // hay de ambos
    if ($h > 0) return $h === 1 ? '1 hora' : $h . ' horas'; // horas exactas en singular o plural
    return $m . ' minutos'; // solo minutos
}

// ---------------------------------------------------------------- disponibilidad
// aqui esta el cerebro del sistema

// rangos_del_dia devuelve los bloques del dia pedido como timestamps
// por ejemplo un martes devuelve dos bloques el de la manana y el de la tarde
// cada bloque trae inicio fin y una etiqueta manana o tarde
function rangos_del_dia(string $fecha): array {
    $cfg = cfg();
    $tz  = new DateTimeZone($cfg['zona']);
    // armamos la medianoche de ese dia solo para saber que dia de la semana es
    $dt  = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' 00:00', $tz);
    if (!$dt) return []; // fecha rara devolvemos vacio
    $n   = (int)$dt->format('N'); // numero del dia 1 lunes a 7 domingo
    $out = [];
    // recorremos los bloques que config tiene para ese dia si no tiene queda lista vacia
    foreach (($cfg['horario'][$n] ?? []) as $rg) {
        // convertimos las horas de texto tipo 08:30 a timestamps de ese dia exacto
        $ini = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $rg[0], $tz);
        $fin = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $rg[1], $tz);
        if (!$ini || !$fin) continue; // si algo no se pudo convertir saltamos ese bloque
        // si el bloque arranca antes de la 1 de la tarde lo llamamos manana si no tarde
        $etq = ((int)explode(':', $rg[0])[0] < 13) ? 'Mañana' : 'Tarde';
        $out[] = [$ini->getTimestamp(), $fin->getTimestamp(), $etq];
    }
    return $out;
}

// validar_consulta revisa sala fecha y duracion contra las reglas de config
// devuelve null si todo esta bien o el mensaje de error si algo falla
function validar_consulta(?string $sala, ?string $fecha, $dur): ?string {
    $cfg = cfg();
    // la sala tiene que existir en config o sea grande o pequena
    if (!isset($cfg['salas'][$sala])) return 'Sala no válida.';
    // la fecha tiene que ser texto con forma de 4 digitos guion 2 guion 2
    if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return 'Fecha no válida.';
    $hoy = date('Y-m-d'); // hoy en el mismo formato
    $max = date('Y-m-d', strtotime('+' . $cfg['max_dias_adelante'] . ' days')); // el tope del calendario
    // comparar fechas como texto funciona porque el formato va de mayor a menor o sea ano mes dia
    if ($fecha < $hoy) return 'La fecha ya pasó.';
    if ($fecha > $max) return 'Solo se puede reservar hasta ' . $cfg['max_dias_adelante'] . ' días adelante.';
    $dur = (int)$dur; // por si llega como texto
    // si la duracion pedida es menor que dur_min o mayor que dur_max o no es multiplo de 15 se rechaza
    if ($dur < $cfg['dur_min'] || $dur > $cfg['dur_max'] || $dur % 15 !== 0) return 'Duración no válida.';
    return null; // todo bien
}

// slots_disponibles calcula las horas libres de una sala un dia con cierta duracion
// la idea es simple
// toma los bloques del dia
// genera posibles arranques cada paso_minutos
// descarta los que no caben antes del cierre del bloque
// descarta los que ya pasaron o estan muy encima
// descarta los que chocan con reservas existentes
// lo que sobrevive son las horas que ve el usuario
function slots_disponibles(string $sala, string $fecha, int $dur): array {
    $cfg    = cfg();
    $rangos = rangos_del_dia($fecha);
    if (!$rangos) return []; // dia cerrado por ejemplo domingo

    // limites del dia para traer solo las reservas que tocan ese dia
    $diaIni = $rangos[0][0];
    $diaFin = $rangos[count($rangos) - 1][1];

    // traemos inicio y fin de las reservas confirmadas de esa sala que caen en el dia
    // los signos de interrogacion son huecos que pdo llena de forma segura
    // eso evita la inyeccion sql o sea que alguien meta codigo malicioso por un campo
    $st = db()->prepare("SELECT inicio, fin FROM reservas_salas
                         WHERE sala = ? AND estado = 'confirmada' AND fin > ? AND inicio < ?");
    $st->execute([$sala, $diaIni, $diaFin]);
    $ocupadas = $st->fetchAll();

    $paso   = $cfg['paso_minutos'] * 60;        // el paso pasado a segundos
    $durSeg = $dur * 60;                         // la duracion pedida en segundos
    $lead   = time() + $cfg['antelacion_min'] * 60; // el momento minimo permitido o sea ahora mas el colchon

    $grupos = [];
    foreach ($rangos as [$ini, $fin, $etq]) { // por cada bloque del dia
        $horas = [];
        // t arranca al inicio del bloque y avanza de paso en paso
        // la condicion t mas duracion menor o igual que fin garantiza que la reserva completa quepa en el bloque
        for ($t = $ini; $t + $durSeg <= $fin; $t += $paso) {
            if ($t < $lead) continue; // muy pronto o ya paso lo saltamos
            $libre = true;
            foreach ($ocupadas as $o) {
                // dos rangos chocan si uno empieza antes de que el otro termine y viceversa
                // esta doble comparacion es la formula clasica de solapamiento
                if ($t < (int)$o['fin'] && ($t + $durSeg) > (int)$o['inicio']) { $libre = false; break; }
            }
            if ($libre) $horas[] = date('H:i', $t); // sobrevivio lo agregamos con forma de hora
        }
        if (!isset($grupos[$etq])) $grupos[$etq] = []; // creamos el grupo si no existia
        $grupos[$etq] = array_merge($grupos[$etq], $horas); // sumamos las horas del bloque a su grupo
    }

    // pasamos de arreglo con etiquetas a lista simple que el javascript recorre facil
    $out = [];
    foreach ($grupos as $etq => $horas) $out[] = ['etiqueta' => $etq, 'horas' => $horas];
    return $out;
}

// crear_reserva guarda la reserva con candado anti choques
// el problema que resuelve
// dos personas pueden pedir la misma hora en el mismo segundo
// solucion una transaccion con for update
// la transaccion es un bloque todo o nada
// el for update congela las filas que chocan hasta que terminemos
// asi el segundo en llegar espera y cuando revisa ya ve la reserva del primero y recibe error
function crear_reserva(string $sala, int $ini, int $fin, string $nombre, string $correo): array {
    $pdo = db();
    try {
        $pdo->beginTransaction(); // abrimos el bloque todo o nada
        // buscamos choques con candado
        $st = $pdo->prepare("SELECT id FROM reservas_salas
                             WHERE sala = ? AND estado = 'confirmada' AND inicio < ? AND fin > ?
                             FOR UPDATE");
        $st->execute([$sala, $fin, $ini]);
        if ($st->fetch()) { // aparecio un choque
            $pdo->rollBack(); // deshacemos todo
            return ['ok' => false, 'error' => 'Ese horario acaba de ser reservado por otra persona. Elige otro.'];
        }
        // generamos el token secreto de 32 caracteres al azar
        // random_bytes es azar de verdad del sistema y bin2hex lo vuelve texto
        $token = bin2hex(random_bytes(16));
        $st = $pdo->prepare("INSERT INTO reservas_salas (sala, inicio, fin, nombre, correo, token, estado, recordada, creada)
                             VALUES (?, ?, ?, ?, ?, ?, 'confirmada', 0, ?)");
        $st->execute([$sala, $ini, $fin, $nombre, $correo, $token, time()]);
        $pdo->commit(); // confirmamos el bloque la reserva quedo firme
        return ['ok' => true, 'reserva' => [
            'sala' => $sala, 'inicio' => $ini, 'fin' => $fin,
            'nombre' => $nombre, 'correo' => $correo, 'token' => $token,
        ]];
    } catch (Throwable $e) {
        // cualquier falla a mitad de camino deshacemos para no dejar datos a medias
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Error guardando la reserva. Intenta de nuevo.'];
    }
}

// ---------------------------------------------------------------- correo
// mismo metodo del sistema de empleados o sea phpmailer con zoho

// busca la carpeta de phpmailer en este orden
// 1 la que diga config si dice alguna
// 2 salas/PHPMailer
// 3 ../empleados/PHPMailer que es la que ya existe en el servidor
function phpmailer_dir(): ?string {
    $cfg = cfg();
    $cands = [];
    if (!empty($cfg['phpmailer_dir'])) $cands[] = $cfg['phpmailer_dir'];
    $cands[] = __DIR__ . '/PHPMailer';
    $cands[] = dirname(__DIR__) . '/empleados/PHPMailer';
    foreach ($cands as $c) {
        if (is_file($c . '/src/PHPMailer.php')) return $c; // encontrada
    }
    return null; // no aparecio en ningun lado
}

// anota una linea en el archivo de registro con fecha y hora
// el archivo termina en .php y arranca con un exit
// asi si alguien lo abre por url no ve nada pero por ftp se lee normal
function log_correo(string $msg): void {
    $f = __DIR__ . '/data/correo.log.php';
    if (!is_dir(__DIR__ . '/data')) @mkdir(__DIR__ . '/data', 0755, true); // crea la carpeta si falta
    if (!is_file($f)) @file_put_contents($f, "<?php exit; ?>\n"); // crea el archivo con su guardian
    @file_put_contents($f, date('Y-m-d H:i:s') . '  ' . $msg . "\n", FILE_APPEND); // append agrega al final sin borrar
}

// envia un correo html a una lista de destinatarios
// puede llevar un archivo de calendario ics adjunto
// nunca lanza error hacia afuera si falla anota en el log y devuelve false
// asi un problema de correo jamas tumba una reserva
function enviar_correo(array $para, string $asunto, string $html, ?string $ics = null, ?string $replyTo = null): bool {
    $cfg = cfg();
    $c   = $cfg['correo'];
    // si el metodo no es smtp no se envia solo se anota
    if (($c['metodo'] ?? 'off') !== 'smtp') {
        log_correo('OMITIDO (metodo=' . ($c['metodo'] ?? 'off') . '): ' . $asunto . ' -> ' . implode(',', $para));
        return false;
    }
    $dir = phpmailer_dir();
    if ($dir === null) { // sin libreria no hay envio
        log_correo('ERROR: no se encontró la carpeta PHPMailer. ' . $asunto);
        return false;
    }
    // cargamos las tres piezas de phpmailer
    require_once $dir . '/src/Exception.php';
    require_once $dir . '/src/PHPMailer.php';
    require_once $dir . '/src/SMTP.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true); // true hace que lance errores capturables
        $mail->isSMTP();                                  // enviar por smtp no por el mail del servidor
        $mail->Host       = $c['smtp_host'];              // servidor de zoho
        $mail->SMTPAuth   = true;                         // con usuario y clave
        $mail->Username   = $c['smtp_user'];
        $mail->Password   = $c['smtp_pass'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // conexion cifrada desde el inicio
        $mail->Port       = (int)$c['smtp_port'];         // puerto 465
        $mail->CharSet    = 'UTF-8';                      // tildes y enes bien
        $mail->setFrom($c['from'], $c['from_nombre']);    // remitente
        foreach ($para as $p) $mail->addAddress($p);      // cada destinatario de la lista
        if ($replyTo) $mail->addReplyTo($replyTo);        // si contestan el correo le llega a esta direccion
        $mail->isHTML(true);                              // el cuerpo es html
        $mail->Subject = $asunto;
        $mail->Body    = $html;
        if ($ics !== null) {
            // adjuntamos el evento de calendario como archivo reserva.ics
            $mail->addStringAttachment($ics, 'reserva.ics', 'base64', 'text/calendar');
        }
        $mail->send(); // fuego
        log_correo('OK: ' . $asunto . ' -> ' . implode(',', $para));
        return true;
    } catch (Throwable $e) {
        log_correo('ERROR: ' . $asunto . ' -> ' . implode(',', $para) . ' :: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------- plantillas
// el marco es la tarjeta blanca con encabezado amarillo que envuelve todos los correos
// recibe el titulo el contenido y de forma opcional un boton con su enlace
function correo_marco(string $titulo, string $contenido, ?string $btnTxt = null, ?string $btnUrl = null, string $btnColor = '#FFCD00'): string {
    $boton = '';
    if ($btnTxt && $btnUrl) { // solo se arma si mandaron texto y enlace
        // htmlspecialchars convierte caracteres peligrosos en inofensivos
        // se usa en todo lo que venga de un usuario para evitar que inyecten html
        $boton = "<div style='text-align:center;margin-top:35px;'>
            <a href='" . htmlspecialchars($btnUrl) . "' style='background-color:$btnColor;color:#000;padding:16px 28px;text-decoration:none;border-radius:10px;font-weight:bold;display:inline-block;'>" . htmlspecialchars($btnTxt) . "</a>
        </div>";
    }
    // los estilos van en linea porque los programas de correo no leen hojas de estilo aparte
    return "
    <div style='background-color:#f4f4f4;padding:20px;font-family:sans-serif;'>
      <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border:2px solid #FFCD00;border-radius:20px;overflow:hidden;'>
        <div style='background-color:#FFCD00;color:#000;padding:25px;text-align:center;'>
          <h2 style='margin:0;text-transform:uppercase;font-weight:800;'>" . htmlspecialchars($titulo) . "</h2>
        </div>
        <div style='padding:30px;color:#222222;line-height:1.7;'>
          $contenido
          $boton
          <p style='margin-top:35px;font-size:12px;color:#999;text-align:center;'>Agro-Costa · Reserva de Salas de Reuniones</p>
        </div>
      </div>
    </div>";
}

// arma las 4 lineas con los datos de la reserva que se repiten en todos los correos
// sala fecha hora y duracion cada una con su cuadrito amarillo
function tabla_reserva(array $r): string {
    $cfg   = cfg();
    $salaN = $cfg['salas'][$r['sala']]['nombre'] ?? $r['sala']; // nombre bonito y si no existe la etiqueta cruda
    $dur   = (int)(($r['fin'] - $r['inicio']) / 60); // la duracion se calcula restando timestamps
    return "
      <p><span style='color:#FFCD00;'>&#9646;</span> <strong style='color:#111;'>Sala:</strong> " . htmlspecialchars($salaN) . "</p>
      <p><span style='color:#FFCD00;'>&#9646;</span> <strong style='color:#111;'>Fecha:</strong> " . fecha_larga((int)$r['inicio']) . "</p>
      <p><span style='color:#FFCD00;'>&#9646;</span> <strong style='color:#111;'>Hora:</strong> " . hora_12((int)$r['inicio']) . ' a ' . hora_12((int)$r['fin']) . "</p>
      <p><span style='color:#FFCD00;'>&#9646;</span> <strong style='color:#111;'>Duración:</strong> " . dur_txt($dur) . "</p>";
}

// arma el archivo ics que es el formato universal de eventos de calendario
// outlook gmail y el celular lo abren y crean el evento
function ics_texto(array $r): string {
    $cfg   = cfg();
    $salaN = $cfg['salas'][$r['sala']]['nombre'] ?? $r['sala'];
    // las horas van en utc que es la hora universal por eso gmdate
    // colombia esta 5 horas detras de utc y no cambia en el ano asi que la conversion es fija
    $l = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//Agro-Costa//Reserva de Salas//ES',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $r['token'] . '@agro-costa.com', // identificador unico del evento
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),         // cuando se creo
        'DTSTART:' . gmdate('Ymd\THis\Z', (int)$r['inicio']), // cuando empieza
        'DTEND:' . gmdate('Ymd\THis\Z', (int)$r['fin']),      // cuando termina
        'SUMMARY:Reserva — ' . $salaN,             // titulo del evento
        'LOCATION:Agro-Costa',                     // lugar
        // en ics las comas se escapan con barra por eso el reemplazo
        'DESCRIPTION:Reservada por ' . str_replace(["\r", "\n", ','], [' ', ' ', '\,'], $r['nombre']),
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", $l); // el formato exige lineas separadas con retorno y salto
}

// correos que salen cuando alguien reserva
// son dos uno para quien reservo y otro para los encargados
function correos_nueva_reserva(array $r): void {
    $cfg   = cfg();
    $salaN = $cfg['salas'][$r['sala']]['nombre'] ?? $r['sala'];
    $ics   = ics_texto($r); // el evento de calendario adjunto
    $urlCancelar = $cfg['url_base'] . '/cancelar.php?t=' . $r['token']; // enlace unico de cancelacion

    // 1 al que reservo
    // lleva sus datos el aviso del recordatorio el ics y el boton de cancelar
    $html = correo_marco('Reserva confirmada',
        "<p style='font-size:16px;'>Hola <strong>" . htmlspecialchars($r['nombre']) . "</strong>, tu sala quedó reservada:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r) . "
         <div style='background:#FFF9E6;border:1px solid #F0E2A8;color:#333;padding:15px;border-radius:10px;margin-top:15px;'>
           ⏰ Te enviaremos un <strong style='color:#111;'>recordatorio 1 hora antes</strong>.<br>
           📎 Adjuntamos el evento para tu calendario (reserva.ics).
         </div>
         <p style='margin-top:20px;font-size:13px;color:#777;'>¿Ya no la necesitas? Cancela con el botón para liberar la sala.</p>",
        'Cancelar esta reserva', $urlCancelar);
    enviar_correo([$r['correo']], "Reserva confirmada: $salaN — " . fecha_larga((int)$r['inicio']), $html, $ics);

    // 2 a los encargados con opcion de cancelar tambien
    // el reply to va con el correo del usuario asi si responden le escriben directo a el
    $html = correo_marco('Nueva reserva de sala',
        "<p style='font-size:16px;'><strong>" . htmlspecialchars($r['nombre']) . "</strong> (" . htmlspecialchars($r['correo']) . ") reservó una sala:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r) . "
         <p style='margin-top:20px;font-size:13px;color:#777;'>Si necesitan liberar la sala, pueden cancelar esta reserva (se le avisa por correo a quien reservó).</p>",
        'Cancelar esta reserva', $urlCancelar);
    enviar_correo($cfg['encargados'], "Nueva reserva: $salaN — " . fecha_larga((int)$r['inicio']) . ' ' . hora_12((int)$r['inicio']), $html, $ics, $r['correo']);
}

// correos de recordatorio que dispara el cron una hora antes
function correos_recordatorio(array $r): void {
    $cfg   = cfg();
    $salaN = $cfg['salas'][$r['sala']]['nombre'] ?? $r['sala'];

    // al que reservo
    $html = correo_marco('Tu reserva empieza pronto',
        "<p style='font-size:16px;'>Hola <strong>" . htmlspecialchars($r['nombre']) . "</strong>, en aproximadamente <strong style='color:#FFCD00;'>1 hora</strong> empieza tu reserva:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r));
    enviar_correo([$r['correo']], "⏰ En 1 hora: $salaN a las " . hora_12((int)$r['inicio']), $html);

    // a los encargados
    $html = correo_marco('Reserva en 1 hora',
        "<p style='font-size:16px;'>Recordatorio: <strong>" . htmlspecialchars($r['nombre']) . "</strong> (" . htmlspecialchars($r['correo']) . ") tiene reserva en 1 hora:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r));
    enviar_correo($cfg['encargados'], "⏰ En 1 hora: $salaN — " . hora_12((int)$r['inicio']) . ' (' . $r['nombre'] . ')', $html);
}

// correos cuando se cancela una reserva
// el texto es neutro porque puede cancelar el usuario o un encargado
function correos_cancelacion(array $r): void {
    $cfg   = cfg();
    $salaN = $cfg['salas'][$r['sala']]['nombre'] ?? $r['sala'];

    // al que habia reservado
    $html = correo_marco('Reserva cancelada',
        "<p style='font-size:16px;'>Hola <strong>" . htmlspecialchars($r['nombre']) . "</strong>, tu reserva quedó cancelada y la sala liberada:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r));
    enviar_correo([$r['correo']], "Reserva cancelada: $salaN — " . fecha_larga((int)$r['inicio']), $html);

    // a los encargados
    $html = correo_marco('Reserva cancelada',
        "<p style='font-size:16px;'>La reserva de <strong>" . htmlspecialchars($r['nombre']) . "</strong> (" . htmlspecialchars($r['correo']) . ") fue cancelada. La sala quedó libre:</p>
         <hr style='border:0;border-top:1px solid #e8e8e3;'>" . tabla_reserva($r));
    enviar_correo($cfg['encargados'], "Cancelada: $salaN — " . fecha_larga((int)$r['inicio']) . ' ' . hora_12((int)$r['inicio']), $html);
}
