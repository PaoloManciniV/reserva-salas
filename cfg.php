<?php
// cfg.php plantilla de configuracion sin claves para el repositorio
// para instalar el sistema copia este archivo con el nombre config.php y llena los datos reales
// el config.php real nunca se sube a github porque esta bloqueado en el .gitignore

return [

    // ---------- salas ----------
    // grande y pequena son las etiquetas internas no se cambian
    // el nombre y la capacidad si se pueden ajustar
    'salas' => [
        'grande'  => ['nombre' => 'Sala de Reuniones Grande',  'capacidad' => 8],
        'pequena' => ['nombre' => 'Sala de Reuniones Pequeña', 'capacidad' => 3],
    ],

    // ---------- horario ----------
    // 1 lunes hasta 7 domingo cada dia con sus bloques en formato 24h
    // lista vacia significa dia cerrado
    'horario' => [
        1 => [['08:30', '12:00'], ['14:00', '18:00']],
        2 => [['08:30', '12:00'], ['14:00', '18:00']],
        3 => [['08:30', '12:00'], ['14:00', '18:00']],
        4 => [['08:30', '12:00'], ['14:00', '18:00']],
        5 => [['08:30', '12:00'], ['14:00', '18:00']],
        6 => [['08:30', '13:00']],
        7 => [], // domingo cerrado
    ],

    // ---------- reglas de reserva ----------
    'paso_minutos'      => 30,   // cada cuanto arranca un turno
    'dur_min'           => 30,   // duracion minima en minutos
    'dur_max'           => 240,  // duracion maxima en minutos
    'antelacion_min'    => 30,   // colchon minimo para reservar
    'max_dias_adelante' => 60,   // hasta cuantos dias hacia el futuro

    // ---------- base de datos ----------
    // la tabla reservas_salas se crea sola en el primer uso
    'bd' => [
        'host'    => 'localhost',
        'nombre'  => 'NOMBRE_DE_LA_BASE',
        'usuario' => 'USUARIO_DE_MYSQL',
        'clave'   => 'CLAVE_DE_MYSQL',
    ],

    // ---------- correos ----------
    // los encargados reciben aviso de cada reserva recordatorio y cancelacion
    'encargados' => ['encargado1@tuempresa.com', 'encargado2@tuempresa.com'],

    // envio por phpmailer con smtp
    // metodo smtp es enviar de verdad y off es solo anotar en el log
    'correo' => [
        'metodo'      => 'smtp',
        'smtp_host'   => 'smtp.zoho.com',
        'smtp_user'   => 'tucuenta@zohomail.com',
        'smtp_pass'   => 'CLAVE_DEL_CORREO',
        'smtp_port'   => 465,
        'from'        => 'tucuenta@zohomail.com',
        'from_nombre' => 'Reserva de Salas',
    ],

    // carpeta de phpmailer null la busca sola
    // primero en salas/PHPMailer y si no en ../empleados/PHPMailer
    'phpmailer_dir' => null,

    // ---------- sitio ----------
    'url_base' => 'https://tudominio.com/salas', // sin barra al final
    'zona'     => 'America/Bogota',

    // clave del cron de recordatorios inventa una larga y unica
    'cron_key' => 'PON_AQUI_UNA_CLAVE_LARGA',
];
