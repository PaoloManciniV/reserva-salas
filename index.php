<?php
// index.php la pagina que ve la gente
// aqui no hay logica de negocio solo pintar y preguntar
// el javascript de abajo le pregunta todo a api.php
// este pedacito de php solo trae la config para pintar capacidades y fechas limite
$cfg = require __DIR__ . '/config.php';
// hoy y el tope del calendario en formato ano mes dia
$hoy = (new DateTime('now', new DateTimeZone($cfg['zona'])))->format('Y-m-d');
$max = (new DateTime('now', new DateTimeZone($cfg['zona'])))->modify('+' . $cfg['max_dias_adelante'] . ' days')->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reserva de Salas de Reuniones — Agro-Costa</title>
<style>
  /* variables de color y medidas para usar en toda la hoja */
  :root {
    --amarillo: #FFCD00;
    --negro: #111111;
    --gris: #6b6b66;
    --borde: #e6e6e1;
    --fondo: #f5f5f2;
    --radio: 18px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; background: var(--fondo);
         color: var(--negro); padding: 24px 16px 60px; }
  .contenedor { max-width: 1080px; margin: 0 auto; }
  /* disposicion es la rejilla de dos columnas panel a la izquierda formulario a la derecha */
  .disposicion { display: block; } /* en celular todo va apilado */
  @media (min-width: 980px) {
    /* en pantalla grande 350px para el panel y el resto para el formulario */
    .disposicion { display: grid; grid-template-columns: 350px 1fr; gap: 18px; align-items: start; }
    /* sticky hace que el panel se quede pegado mientras haces scroll */
    .tablero { position: sticky; top: 16px; margin-bottom: 0 !important; }
  }

  /* ---------- cabecera amarilla ---------- */
  .hero { background: var(--amarillo); border-radius: var(--radio); padding: 34px 30px; color: #111;
          border: 2px solid #111; margin-bottom: 22px; }
  .marca { color: #111; font-weight: 800; letter-spacing: 2px; font-size: 13px;
           text-transform: uppercase; }
  .hero h1 { font-size: 28px; margin-top: 6px; font-weight: 800; }
  .hero p { color: #333; margin-top: 8px; font-size: 14px; line-height: 1.6; }
  /* las pastillas negras con el horario */
  .hero .horario { display: inline-block; background: #111; border: 1px solid #111; color: var(--amarillo);
                   padding: 6px 12px; border-radius: 999px; font-size: 12.5px; margin-top: 12px; margin-right: 6px; }

  /* ---------- tarjetas de cada paso ---------- */
  .paso { background: #fff; border: 1px solid var(--borde); border-radius: var(--radio);
          padding: 24px; margin-bottom: 16px; }
  .paso h2 { font-size: 16px; display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
  /* el circulo amarillo con el numero del paso */
  .num { background: var(--amarillo); color: #000; width: 26px; height: 26px; border-radius: 50%;
         display: inline-flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; }

  /* ---------- paso 1 tarjetas de salas ---------- */
  .salas { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .sala { border: 2px solid var(--borde); border-radius: 14px; padding: 18px; cursor: pointer;
          transition: all .15s; background: #fff; text-align: left; font-family: inherit; }
  .sala:hover { border-color: #bbb; }
  /* activa es la elegida borde amarillo y fondo cremita */
  .sala.activa { border-color: var(--amarillo); background: #FFFBEA; }
  .sala .icono { font-size: 26px; }
  .sala .nombre { font-weight: 700; font-size: 15px; margin-top: 8px; }
  .sala .cap { color: var(--gris); font-size: 13px; margin-top: 3px; }

  /* ---------- paso 2 pastillas de duracion ---------- */
  .chips { display: flex; flex-wrap: wrap; gap: 10px; }
  .chip { border: 2px solid var(--borde); background: #fff; border-radius: 999px; padding: 10px 18px;
          font-size: 14px; font-weight: 600; cursor: pointer; transition: all .15s; font-family: inherit; }
  .chip:hover { border-color: #bbb; }
  /* la elegida se pinta negra con letra amarilla */
  .chip.activa { background: var(--negro); color: var(--amarillo); border-color: var(--negro); }
  /* el bloque de otra duracion arranca oculto */
  .personalizar { display: none; margin-top: 16px; align-items: center; gap: 14px; }
  .personalizar.visible { display: flex; }
  /* el contador con los botones menos y mas */
  .stepper { display: flex; align-items: center; gap: 0; border: 2px solid var(--borde); border-radius: 12px; overflow: hidden; }
  .stepper button { width: 44px; height: 44px; border: 0; background: #fafaf8; font-size: 20px; cursor: pointer; font-weight: 700; }
  .stepper button:hover { background: #f0f0ec; }
  .stepper .valor { min-width: 110px; text-align: center; font-weight: 700; font-size: 15px; }
  .ayuda { color: var(--gris); font-size: 12.5px; }

  /* ---------- paso 3 fecha ---------- */
  input[type="date"] { border: 2px solid var(--borde); border-radius: 12px; padding: 12px 14px;
                       font-size: 15px; font-family: inherit; width: 100%; max-width: 260px; background: #fff; }
  input[type="date"]:focus { outline: none; border-color: var(--amarillo); }

  /* ---------- paso 4 horas ---------- */
  .grupo-horas { margin-top: 14px; }
  .grupo-horas .etiqueta { font-size: 13px; font-weight: 700; color: var(--gris); text-transform: uppercase;
                           letter-spacing: 1px; margin-bottom: 8px; }
  /* rejilla flexible las pastillas se acomodan solas */
  .horas { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 8px; }
  .hora { border: 2px solid var(--borde); background: #fff; border-radius: 10px; padding: 10px 4px;
          font-size: 14px; font-weight: 600; cursor: pointer; text-align: center; transition: all .12s; font-family: inherit; }
  .hora:hover { border-color: #bbb; }
  .hora.activa { background: var(--negro); color: var(--amarillo); border-color: var(--negro); }
  .vacio { color: var(--gris); font-size: 14px; padding: 12px 0; }
  .cargando { color: var(--gris); font-size: 14px; padding: 12px 0; }

  /* ---------- paso 5 datos ---------- */
  .campos { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .campo label { font-size: 13px; font-weight: 700; display: block; margin-bottom: 6px; }
  .campo input { width: 100%; border: 2px solid var(--borde); border-radius: 12px; padding: 12px 14px;
                 font-size: 15px; font-family: inherit; }
  .campo input:focus { outline: none; border-color: var(--amarillo); }
  /* la cajita punteada con el resumen de lo elegido */
  .resumen { background: #FFFBEA; border: 1px dashed var(--amarillo); border-radius: 12px;
             padding: 12px 16px; font-size: 14px; margin-top: 16px; display: none; }
  .resumen.visible { display: block; }

  /* boton grande de confirmar */
  .btn-reservar { width: 100%; margin-top: 16px; background: var(--amarillo); color: #000; border: 0;
                  border-radius: 12px; padding: 17px; font-size: 16px; font-weight: 800; cursor: pointer;
                  font-family: inherit; transition: transform .1s, opacity .15s; }
  .btn-reservar:hover:not(:disabled) { transform: translateY(-1px); }
  /* deshabilitado se ve palido hasta que este todo completo */
  .btn-reservar:disabled { opacity: .45; cursor: not-allowed; }

  /* cajita roja de errores */
  .error-box { display: none; background: #FDECEC; border: 1px solid #f3b4b4; color: #a12622;
               border-radius: 12px; padding: 12px 16px; font-size: 14px; margin-top: 14px; }
  .error-box.visible { display: block; }

  /* ---------- pantalla de exito ---------- */
  #exito { display: none; background: var(--amarillo); border: 2px solid #111; border-radius: var(--radio);
           color: #111; padding: 40px 30px; text-align: center; }
  #exito .check { font-size: 54px; }
  #exito h2 { margin-top: 10px; font-size: 24px; }
  #exito .detalle { background: #fff; border-radius: 14px; padding: 18px; margin-top: 20px;
                    text-align: left; font-size: 15px; line-height: 2; }
  #exito .detalle strong { color: #111; }
  #exito .nota { color: #333; font-size: 13.5px; margin-top: 18px; line-height: 1.7; }
  #exito button { margin-top: 22px; background: #111; color: var(--amarillo); border: 0; border-radius: 12px;
                  padding: 14px 26px; font-weight: 800; font-size: 15px; cursor: pointer; font-family: inherit; }

  /* ---------- panel tipo pantalla de aeropuerto ---------- */
  .tablero { background: var(--amarillo); border: 2px solid #111; border-radius: var(--radio);
             padding: 18px; margin-bottom: 16px; font-family: Consolas, 'Courier New', monospace;
             position: relative; overflow: hidden; }
  /* lineas horizontales suaves encima para dar efecto de pantalla */
  .tablero::after { content: ''; position: absolute; inset: 0; pointer-events: none;
                    background: repeating-linear-gradient(0deg, rgba(0,0,0,.045) 0 1px, transparent 1px 3px); }
  .tab-cab { display: flex; align-items: center; gap: 9px; color: #111; font-weight: 700;
             letter-spacing: 2px; font-size: 13px; }
  /* el puntico verde que parpadea como luz de encendido */
  .tab-dot { width: 8px; height: 8px; border-radius: 50%; background: #0a7a3d; animation: parpadeo 1.6s infinite; flex: none; }
  @keyframes parpadeo { 50% { opacity: .2; } }
  /* la pastilla en vivo negra con letra amarilla */
  .tab-fecha { margin-left: auto; color: var(--amarillo); background: #111; border: 1px solid #111;
               padding: 3px 10px; border-radius: 6px; font-size: 12px; letter-spacing: 1px; }
  /* encabezado de columnas del panel */
  .tab-cols { color: #6b5c00; font-size: 10.5px; letter-spacing: 2px; margin: 14px 0 2px;
              border-bottom: 1px dashed rgba(0,0,0,.35); padding-bottom: 7px; }
  /* cada fila es una reserva */
  .tab-fila { padding: 11px 2px; border-bottom: 1px dashed rgba(0,0,0,.22); text-transform: uppercase; }
  .tab-linea1 { display: flex; align-items: center; gap: 8px; font-size: 12.5px; letter-spacing: .5px; }
  .tab-dia { color: #6b5c00; }
  .tab-hora { color: #111; font-weight: 700; }
  /* la sala va en pastilla negra con letra blanca */
  .tab-sala { background: #111; color: #fff; padding: 1px 8px; border-radius: 5px; font-size: 11px; }
  .tab-estado { margin-left: auto; font-size: 10px; padding: 2px 8px; border-radius: 4px; border: 1px solid; letter-spacing: 1px; }
  /* programada borde negro y en curso rellena negra con letra verde */
  .e-prog  { color: #111; border-color: #111; }
  .e-curso { background: #111; color: #3ddc84; border-color: #111; }
  .tab-nombre { margin-top: 5px; font-size: 14.5px; font-weight: 700; letter-spacing: 1.5px; color: #111; }
  .tab-vacio { color: #6b5c00; padding: 16px 0; letter-spacing: 2px; font-size: 12px; }

  /* en pantallas angostas las rejillas de dos columnas pasan a una */
  @media (max-width: 560px) {
    .salas, .campos { grid-template-columns: 1fr; }
    .hero h1 { font-size: 22px; }
  }
</style>
</head>
<body>
<div class="contenedor">

  <!-- cabecera amarilla con titulo y horarios -->
  <div class="hero">
    <div class="marca">Agro-Costa</div>
    <h1>Reserva de Salas de Reuniones</h1>
    <p>Elige la sala, el tiempo que la necesitas y la hora. Te llega la confirmación al correo y un recordatorio 1 hora antes.</p>
    <span class="horario">Lun–Vie · 8:30 a.m.–12:00 m. y 2:00–6:00 p.m.</span>
    <span class="horario">Sáb · 8:30 a.m.–1:00 p.m.</span>
  </div>

  <!-- desde aqui las dos columnas -->
  <div class="disposicion">

  <!-- columna izquierda el panel que todos ven -->
  <aside class="tablero" aria-label="Panel de reservas">
    <div class="tab-cab"><span class="tab-dot"></span> PANEL DE SALAS <span class="tab-fecha">EN VIVO</span></div>
    <div class="tab-cols">DÍA · HORA · SALA · RESERVADO POR</div>
    <!-- aqui el javascript inyecta las filas -->
    <div id="tab-filas"><div class="tab-vacio">CARGANDO…</div></div>
  </aside>

  <!-- columna derecha el formulario -->
  <div class="principal">

  <div id="formulario">

    <!-- paso 1 elegir sala -->
    <div class="paso">
      <h2><span class="num">1</span> Elige la sala</h2>
      <div class="salas">
        <!-- data-sala guarda la etiqueta interna que se manda a la api -->
        <button type="button" class="sala" data-sala="grande">
          <div class="icono">🏢</div>
          <div class="nombre">Sala de Reuniones Grande</div>
          <div class="cap">Hasta <?= (int)$cfg['salas']['grande']['capacidad'] ?> personas</div>
        </button>
        <button type="button" class="sala" data-sala="pequena">
          <div class="icono">👥</div>
          <div class="nombre">Sala de Reuniones Pequeña</div>
          <div class="cap">Hasta <?= (int)$cfg['salas']['pequena']['capacidad'] ?> personas</div>
        </button>
      </div>
    </div>

    <!-- paso 2 duracion -->
    <div class="paso">
      <h2><span class="num">2</span> ¿Cuánto tiempo la necesitas?</h2>
      <div class="chips">
        <!-- data-min son los minutos de cada opcion rapida -->
        <button type="button" class="chip chip-dur activa" data-min="60">1 hora</button>
        <button type="button" class="chip chip-dur" data-min="120">2 horas</button>
        <button type="button" class="chip chip-dur" data-min="180">3 horas</button>
        <button type="button" class="chip" id="chip-otra">Otra duración…</button>
      </div>
      <!-- contador para duracion personalizada oculto hasta tocar otra duracion -->
      <div class="personalizar" id="personalizar">
        <div class="stepper">
          <button type="button" id="menos">−</button>
          <div class="valor" id="dur-valor">1 h 00 min</div>
          <button type="button" id="mas">+</button>
        </div>
        <span class="ayuda">De 30 min a 4 horas, en pasos de 15 min</span>
      </div>
    </div>

    <!-- paso 3 fecha con limites que vienen de config -->
    <div class="paso">
      <h2><span class="num">3</span> ¿Qué día?</h2>
      <input type="date" id="fecha" min="<?= $hoy ?>" max="<?= $max ?>" value="<?= $hoy ?>">
      <div class="ayuda" style="margin-top:8px;">Los domingos no hay servicio.</div>
    </div>

    <!-- paso 4 horas libres las llena el javascript -->
    <div class="paso">
      <h2><span class="num">4</span> Elige la hora</h2>
      <div id="zona-horas"><div class="vacio">Primero elige la sala.</div></div>
    </div>

    <!-- paso 5 datos personales y confirmar -->
    <div class="paso">
      <h2><span class="num">5</span> Tus datos</h2>
      <div class="campos">
        <div class="campo">
          <label for="nombre">Nombre completo</label>
          <input type="text" id="nombre" maxlength="100" placeholder="Ej: María Pérez" autocomplete="name">
        </div>
        <div class="campo">
          <label for="correo">Correo electrónico</label>
          <input type="email" id="correo" maxlength="150" placeholder="tucorreo@empresa.com" autocomplete="email">
        </div>
      </div>
      <!-- trampa anti bots campo invisible que un humano nunca llena -->
      <input type="text" id="web" name="web" value="" style="position:absolute;left:-9999px;" tabindex="-1" autocomplete="off" aria-hidden="true">

      <div class="resumen" id="resumen"></div>
      <div class="error-box" id="error-box"></div>
      <!-- arranca deshabilitado hasta que todo este completo -->
      <button type="button" class="btn-reservar" id="btn-reservar" disabled>Confirmar reserva</button>
    </div>

  </div>

  <!-- pantalla de exito oculta hasta que la reserva quede guardada -->
  <div id="exito">
    <div class="check">✅</div>
    <h2>¡Sala reservada!</h2>
    <div class="detalle" id="exito-detalle"></div>
    <p class="nota">📧 Te enviamos la confirmación al correo (revisa spam la primera vez).<br>
       ⏰ Recibirás un recordatorio 1 hora antes de tu reserva.</p>
    <button type="button" onclick="location.reload()">Hacer otra reserva</button>
  </div>

  </div><!-- /principal -->

  </div><!-- /disposicion -->

</div>

<script>
// todo el javascript va envuelto en una funcion que se ejecuta sola
// asi sus variables no chocan con nada mas de la pagina
(function () {
  var API = 'api.php'; // a donde se hacen todas las preguntas
  // estado guarda lo que el usuario lleva elegido
  // arranca con duracion 1 hora y la fecha de hoy
  var estado = { sala: null, dur: 60, fecha: document.getElementById('fecha').value, hora: null };
  // nombres bonitos para pintar el resumen sin preguntarle al servidor
  var NOMBRES = { grande: 'Sala de Reuniones Grande', pequena: 'Sala de Reuniones Pequeña' };

  // atajo para no escribir document.getElementById a cada rato
  var $ = function (id) { return document.getElementById(id); };
  var zonaHoras = $('zona-horas'), btn = $('btn-reservar'), errBox = $('error-box');

  // ---------- utilidades ----------
  // convierte 14:30 en 2:30 pm
  function fmt12(hhmm) {
    var p = hhmm.split(':'), h = parseInt(p[0], 10), m = p[1];
    var ap = h < 12 ? 'a.m.' : 'p.m.'; // antes de las 12 am despues pm
    var h12 = h % 12; if (h12 === 0) h12 = 12; // las 0 y las 12 se muestran como 12
    return h12 + ':' + m + ' ' + ap;
  }
  // convierte minutos en texto tipo 1 h 30 min
  function durTxt(min) {
    var h = Math.floor(min / 60), m = min % 60;
    if (h && m) return h + ' h ' + (m < 10 ? '0' + m : m) + ' min';
    if (h) return h === 1 ? '1 hora' : h + ' horas';
    return m + ' min';
  }
  // convierte 2026-07-15 en miercoles 15 de julio
  function fechaTxt(iso) {
    // el t12 del medio evita que el navegador corra la fecha un dia por zona horaria
    var d = new Date(iso + 'T12:00:00');
    var dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
    var meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return dias[d.getDay()] + ' ' + d.getDate() + ' de ' + meses[d.getMonth()];
  }
  function esDomingo(iso) { return new Date(iso + 'T12:00:00').getDay() === 0; }
  function mostrarError(msg) { errBox.textContent = msg; errBox.classList.add('visible'); }
  function limpiarError() { errBox.classList.remove('visible'); }

  // ---------- paso 1 sala ----------
  // a cada tarjeta de sala le ponemos oido al clic
  document.querySelectorAll('.sala').forEach(function (el) {
    el.addEventListener('click', function () {
      // primero le quitamos activa a todas y luego se la ponemos a la tocada
      document.querySelectorAll('.sala').forEach(function (s) { s.classList.remove('activa'); });
      el.classList.add('activa');
      estado.sala = el.dataset.sala; // guardamos grande o pequena
      cargarHoras(); // con sala nueva hay que recalcular horas
    });
  });

  // ---------- paso 2 duracion ----------
  document.querySelectorAll('.chip-dur').forEach(function (el) {
    el.addEventListener('click', function () {
      document.querySelectorAll('.chips .chip').forEach(function (c) { c.classList.remove('activa'); });
      el.classList.add('activa');
      $('personalizar').classList.remove('visible'); // ocultamos el contador si estaba abierto
      estado.dur = parseInt(el.dataset.min, 10); // los minutos de la pastilla
      cargarHoras();
    });
  });
  // la pastilla otra duracion abre el contador
  $('chip-otra').addEventListener('click', function () {
    document.querySelectorAll('.chips .chip').forEach(function (c) { c.classList.remove('activa'); });
    this.classList.add('activa');
    $('personalizar').classList.add('visible');
    pintarDur();
    cargarHoras();
  });
  function pintarDur() { $('dur-valor').textContent = durTxt(estado.dur); }
  // menos y mas mueven de a 15 con topes 30 y 240
  $('menos').addEventListener('click', function () {
    if (estado.dur > 30) { estado.dur -= 15; pintarDur(); cargarHoras(); }
  });
  $('mas').addEventListener('click', function () {
    if (estado.dur < 240) { estado.dur += 15; pintarDur(); cargarHoras(); }
  });

  // ---------- paso 3 fecha ----------
  $('fecha').addEventListener('change', function () {
    estado.fecha = this.value;
    cargarHoras();
  });

  // ---------- paso 4 horas ----------
  // pedidoActual evita un problema clasico
  // si haces dos consultas rapidas la respuesta vieja puede llegar despues de la nueva
  // cada pedido lleva su numero y solo se pinta el ultimo
  var pedidoActual = 0;
  function cargarHoras() {
    estado.hora = null; // al recalcular se pierde la hora elegida
    actualizar();
    // sin sala o sin fecha no hay nada que consultar
    if (!estado.sala) { zonaHoras.innerHTML = '<div class="vacio">Primero elige la sala.</div>'; return; }
    if (!estado.fecha) { zonaHoras.innerHTML = '<div class="vacio">Elige una fecha.</div>'; return; }
    if (esDomingo(estado.fecha)) { zonaHoras.innerHTML = '<div class="vacio">Los domingos no hay servicio. Elige otro día.</div>'; return; }

    zonaHoras.innerHTML = '<div class="cargando">Buscando horarios disponibles…</div>';
    var pedido = ++pedidoActual; // numeramos este pedido
    // armamos la direccion con los tres datos encodeURIComponent protege caracteres raros
    var url = API + '?action=slots&sala=' + encodeURIComponent(estado.sala) +
              '&fecha=' + encodeURIComponent(estado.fecha) + '&dur=' + estado.dur;

    // fetch hace la consulta sin recargar la pagina
    fetch(url).then(function (r) { return r.json(); }).then(function (d) {
      if (pedido !== pedidoActual) return; // respuesta vieja la ignoramos
      if (!d.ok) { zonaHoras.innerHTML = '<div class="vacio">' + (d.error || 'Error consultando.') + '</div>'; return; }

      // armamos el html de los grupos manana y tarde
      var html = '', hay = false;
      d.grupos.forEach(function (g) {
        if (!g.horas.length) return; // grupo sin horas no se pinta
        hay = true;
        html += '<div class="grupo-horas"><div class="etiqueta">' + g.etiqueta + '</div><div class="horas">';
        g.horas.forEach(function (h) {
          // cada hora es un boton data-h guarda la hora en formato 24 que es el que entiende la api
          html += '<button type="button" class="hora" data-h="' + h + '">' + fmt12(h) + '</button>';
        });
        html += '</div></div>';
      });
      if (!hay) html = '<div class="vacio">No quedan horarios libres para ' + durTxt(estado.dur) + ' ese día. Prueba otra fecha u otra duración.</div>';
      zonaHoras.innerHTML = html;

      // ahora si le ponemos oido a cada boton de hora recien creado
      zonaHoras.querySelectorAll('.hora').forEach(function (el) {
        el.addEventListener('click', function () {
          zonaHoras.querySelectorAll('.hora').forEach(function (x) { x.classList.remove('activa'); });
          el.classList.add('activa');
          estado.hora = el.dataset.h;
          actualizar();
        });
      });
    }).catch(function () {
      // catch atrapa fallas de red
      if (pedido === pedidoActual) zonaHoras.innerHTML = '<div class="vacio">No se pudo consultar. Revisa tu conexión.</div>';
    });
  }

  // ---------- paso 5 resumen y enviar ----------
  // cada letra que escriban en nombre o correo revisa si ya se puede confirmar
  ['nombre', 'correo'].forEach(function (id) {
    $(id).addEventListener('input', actualizar);
  });

  // completo dice si ya hay todo lo necesario para reservar
  function completo() {
    return estado.sala && estado.fecha && estado.hora &&
           $('nombre').value.trim().length >= 3 &&
           /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($('correo').value.trim()); // forma basica de correo algo arroba algo punto algo
  }

  // actualizar refresca el resumen y prende o apaga el boton
  function actualizar() {
    var r = $('resumen');
    if (estado.sala && estado.fecha && estado.hora) {
      r.innerHTML = '📌 <strong>' + NOMBRES[estado.sala] + '</strong> · ' + fechaTxt(estado.fecha) +
                    ' · ' + fmt12(estado.hora) + ' · ' + durTxt(estado.dur);
      r.classList.add('visible');
    } else {
      r.classList.remove('visible');
    }
    btn.disabled = !completo(); // el boton solo vive cuando todo esta listo
  }

  // el clic del boton confirmar
  btn.addEventListener('click', function () {
    if (!completo()) return; // doble seguro
    limpiarError();
    btn.disabled = true; // evita doble clic y doble reserva
    btn.textContent = 'Reservando…';

    // empacamos los datos como formulario
    var datos = new URLSearchParams();
    datos.set('action', 'reservar');
    datos.set('sala', estado.sala);
    datos.set('fecha', estado.fecha);
    datos.set('hora', estado.hora);
    datos.set('dur', estado.dur);
    datos.set('nombre', $('nombre').value.trim());
    datos.set('correo', $('correo').value.trim());
    datos.set('web', $('web').value); // la trampa anti bots viaja vacia si es un humano

    fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: datos.toString() })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok && d.resumen) {
          // reserva guardada pintamos la pantalla de exito con el resumen del servidor
          $('exito-detalle').innerHTML =
            '<strong>Sala:</strong> ' + d.resumen.sala + '<br>' +
            '<strong>Fecha:</strong> ' + d.resumen.fecha + '<br>' +
            '<strong>Hora:</strong> ' + d.resumen.hora + '<br>' +
            '<strong>Duración:</strong> ' + d.resumen.dur + '<br>' +
            '<strong>Correo:</strong> ' + d.resumen.correo;
          $('formulario').style.display = 'none'; // se esconde el formulario
          $('exito').style.display = 'block';     // y aparece el exito
          window.scrollTo({ top: 0, behavior: 'smooth' });
          cargarTablero(); // el panel se refresca ya mismo con la reserva nueva
        } else {
          // el servidor dijo que no mostramos el motivo y recargamos las horas
          mostrarError(d.error || 'No se pudo reservar. Intenta de nuevo.');
          btn.textContent = 'Confirmar reserva';
          cargarHoras();
        }
      })
      .catch(function () {
        mostrarError('Error de conexión. Intenta de nuevo.');
        btn.disabled = false;
        btn.textContent = 'Confirmar reserva';
      });
  });

  // ---------- panel tipo aeropuerto ----------
  var tabFilas = $('tab-filas');

  // esc limpia texto que escribio un usuario antes de meterlo al html
  // convierte simbolos peligrosos en inofensivos para que nadie inyecte codigo con su nombre
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // pide a la api todas las reservas proximas y pinta las filas
  function cargarTablero() {
    fetch(API + '?action=tablero')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { tabFilas.innerHTML = '<div class="tab-vacio">SIN DATOS</div>'; return; }
        if (!d.items.length) {
          tabFilas.innerHTML = '<div class="tab-vacio">— SIN RESERVAS PRÓXIMAS —</div>';
          return;
        }
        var html = '';
        d.items.forEach(function (it) {
          var est, cls;
          // comparamos contra la hora del servidor que viene en d.ahora
          // si ya empezo y no ha terminado esta en curso si no esta programada
          if (it.ini_ts <= d.ahora && d.ahora < it.fin_ts) { est = 'EN CURSO'; cls = 'e-curso'; }
          else { est = 'PROGRAMADA'; cls = 'e-prog'; }
          html += '<div class="tab-fila">' +
                    '<div class="tab-linea1">' +
                      '<span class="tab-dia">' + (it.es_hoy ? 'HOY' : it.dia) + '</span>' +
                      '<span class="tab-hora">' + it.ini + '–' + it.fin + '</span>' +
                      '<span class="tab-sala">' + (it.sala === 'grande' ? 'GRANDE' : 'PEQUEÑA') + '</span>' +
                      '<span class="tab-estado ' + cls + '">' + est + '</span>' +
                    '</div>' +
                    '<div class="tab-nombre">' + esc(it.nombre) + '</div>' + // el nombre pasa por esc siempre
                  '</div>';
        });
        tabFilas.innerHTML = html;
      })
      .catch(function () { tabFilas.innerHTML = '<div class="tab-vacio">SIN CONEXIÓN</div>'; });
  }

  cargarTablero(); // primera carga apenas abre la pagina
  setInterval(cargarTablero, 15000); // y despues solita cada 15 segundos
  // ademas se refresca al instante cuando vuelven a esta pestana del navegador
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) cargarTablero();
  });

})();
</script>
</body>
</html>
