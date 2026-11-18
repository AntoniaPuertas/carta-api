<?php
// Punto de entrada de la API: todas las peticiones pasan por aquí
require 'ayudas.php';
require 'conexion.php';
require 'platos.php';

$config = require 'config.php';

// 1. ¿Qué piden? El método y la ruta, sin la carpeta donde está la API
$metodo  = $_SERVER['REQUEST_METHOD'];                                  // GET, POST, PUT, DELETE…
//carpeta dónde está la api
$base    = dirname($_SERVER['SCRIPT_NAME']);                            // /carta/api
//ruta completa que ha pedido el navegador 
$camino  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);            // /carta/api/platos/3

/*
 * $partes: lo que hay DESPUÉS de la carpeta de la API, dividido por las barras.
 * La línea encadena cuatro funciones; se lee de dentro hacia fuera.
 * Ejemplo con $base = '/carta/api' y $camino = '/carta/api/platos/3':
 *
 *   1. strlen($base)         → 10
 *      Cuenta los caracteres de la carpeta. Solo sirve para saber cuántos hay que saltarse.
 *
 *   2. substr($camino, 10)   → '/platos/3'
 *      Devuelve el texto a partir de la posición 10 (la primera es la 0):
 *
 *          /carta/api/platos/3
 *          0123456789↑
 *                    posición 10
 *
 *      AQUÍ desaparecen 'carta' y 'api': se recortan antes de dividir la ruta.
 *
 *   3. trim(..., '/')        → 'platos/3'
 *      Quita las barras de los extremos. Sin este paso, la barra inicial haría que
 *      explode() devolviera un primer elemento vacío: ['', 'platos', '3'].
 *
 *   4. explode('/', ...)     → ['platos', '3']
 *      Divide el texto por las barras y devuelve un array.
 *
 */
$partes  = explode('/', trim(substr($camino, strlen($base)), '/'));     // ['platos', '3']

$recurso = $partes[0];                                                  // 'platos'
$id      = $partes[1] ?? null;                                          // '3' o null
//var_dump($metodo, $base, $camino, $partes, $recurso, $id); exit; // DEPURACIÓN: quitar después

// 2. Las operaciones que cambian datos (las «no seguras») exigen la clave
if (!in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true)) {
    exigirClave($config['clave_api']);
}

// 2. Reparto: cada ruta, a su respuesta
if ($recurso === '' || $recurso === 'index.php') {
    responder(200, ['api' => 'La carta de La Alacena', 'version' => '1.0']);
}

$pdo = conectar($config);

// TODO 1: si el recurso es 'platos' y no hay más partes → {"ruta":"la lista de platos","metodo":…}
if ($recurso === 'platos' && count($partes) === 1) {
    //responder(200, ['ruta' => 'la lista de platos', 'metodo' => $metodo]);
    match ($metodo) {
        'GET' => listarPlatos($pdo),
        'POST'  => crearPlato($pdo, $base),
        default => metodoNoPermitido('GET')
    };
}
// TODO 2: si es 'platos', hay exactamente 2 partes y el id son solo cifras (ctype_digit)
//         → {"ruta":"un plato","id":…,"metodo":…}
if ($recurso === 'platos' && count($partes) === 2 && ctype_digit($id)) {
    // responder(200, ['ruta' => 'un plato', 'id' => (int) $id, 'metodo' => $metodo]);
    match ($metodo) {
        'GET'   => verPlato($pdo, (int) $id),
        'PUT'    => sustituirPlato($pdo, (int) $id),
        'DELETE' => borrarPlato($pdo, (int) $id),
        default => metodoNoPermitido('GET'),
    };
}
// TODO 3: en cualquier otro caso → error 404 "Esa ruta no existe"
error(405, 'Esa ruta no existe');
