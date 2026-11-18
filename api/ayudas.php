<?php

/*
 * ayudas.php — funciones auxiliares de la API
 * -------------------------------------------
 * Este archivo reúne las funciones que usan todos los endpoints para responder al cliente.
 * Así cada endpoint no repite el mismo código de cabeceras, JSON y gestión de errores.
 *
 * Sobre el tipo de retorno `never` (PHP 8.1+)
 * -------------------------------------------
 * `responder()` y `error()` declaran `never` como tipo de retorno. Significa que la función
 * NUNCA vuelve al código que la llamó: termina el programa con `exit` o lanza una excepción.
 *
 *   - `void`  → la función no devuelve nada, pero el programa sigue en la línea siguiente.
 *   - `never` → la función no vuelve: la línea siguiente a la llamada no se ejecuta.
 *
 * Ventajas:
 *   1. Quien lee el código sabe que, tras llamar a `error()`, la ejecución se detiene:
 *
 *        if (!$plato) {
 *            error(404, 'Plato no encontrado');
 *        }
 *        // aquí $plato existe seguro: no hace falta else ni return
 *
 *   2. PHP lo comprueba: si una función `never` llega al final sin `exit` ni `throw`,
 *      se produce un TypeError ("never-returning function must not implicitly return").
 */


/**
 * Envía la respuesta HTTP al cliente y termina el programa.
 *
 * Pasos:
 *   1. Fija el código de estado HTTP (200, 201, 404, 500…) con http_response_code().
 *   2. Si hay datos, indica que la respuesta es JSON (cabecera Content-Type)
 *      y los envía codificados con json_encode().
 *      JSON_UNESCAPED_UNICODE hace que las tildes y la ñ se vean tal cual ("Albóndigas")
 *      en lugar de como secuencias del tipo ó.
 *   3. Termina el programa con exit. Por eso el tipo de retorno es `never`.
 *
 * Ejemplos:
 *   responder(200, $platos);   // 200 OK con la lista de platos en JSON
 *   responder(204);            // 204 No Content: solo el código, sin cuerpo
 *
 * @param int   $estado Código de estado HTTP.
 * @param mixed $datos  Lo que se enviará como JSON (array, objeto…). Si es null, no se envía cuerpo.
 * @return never        No vuelve nunca: siempre termina con exit.
 */
function responder(int $estado, mixed $datos = null): never
{
    // TODO 1: el código de estado
    http_response_code($estado);
    // TODO 2: si $datos no es null, la cabecera Content-Type y el JSON (JSON_UNESCAPED_UNICODE)
    if ($datos !== null) {
        header(('Content-Type: application/json; charset=utf-8'));
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
    exit; // termina el programa: esta función nunca vuelve (never)
}


/**
 * Envía una respuesta de error con un formato común para toda la API.
 *
 * El cuerpo siempre tiene la clave "error" con el mensaje y, opcionalmente,
 * más datos que ayuden al cliente (por ejemplo, qué campos son incorrectos).
 *
 * Ejemplos:
 *   error(404, 'Plato no encontrado');
 *     → 404  {"error": "Plato no encontrado"}
 *
 *   error(422, 'Datos no válidos', ['campos' => ['precio' => 'Debe ser mayor que 0']]);
 *     → 422  {"error": "Datos no válidos", "campos": {"precio": "Debe ser mayor que 0"}}
 *
 * El operador + une dos arrays: ['error' => …] va primero y después se añaden las claves de
 * $extra (si $extra trajera también una clave 'error', se conservaría la del primer array).
 *
 * Es `never` porque delega en responder(), que también es `never` y termina con exit.
 *
 * @param int    $estado  Código de estado HTTP de error (400, 404, 422, 500…).
 * @param string $mensaje Texto que explica el error al cliente.
 * @param array  $extra   Datos adicionales que se añaden después de "error".
 * @return never          No vuelve nunca.
 */
function error(int $estado, string $mensaje, array $extra = []): never
{
    // TODO 3: responde con ['error' => $mensaje] seguido de los datos de $extra
    responder($estado, ['error' => $mensaje] + $extra);
}


/*
 * Manejador global de excepciones
 * -------------------------------
 * set_exception_handler() registra una función que PHP ejecuta cuando se lanza una excepción
 * que ningún try/catch captura (por ejemplo, un fallo de conexión con la base de datos).
 *
 * Hace dos cosas:
 *   1. Guarda el mensaje real en el registro de errores del servidor con error_log(),
 *      para que el programador pueda consultarlo (en WAMP: wamp64/logs/php_error.log).
 *   2. Responde al cliente con un 500 genérico, SIN detalles técnicos: mostrar rutas,
 *      consultas SQL o contraseñas al cliente sería un problema de seguridad.
 *
 * Throwable incluye tanto las excepciones (Exception) como los errores graves (Error).
 * Esta función anónima se declara `void` porque es la firma que espera set_exception_handler();
 * en la práctica no vuelve, porque error() termina el programa.
 */
set_exception_handler(function (Throwable $e): void {
    // TODO 4: error_log() con el mensaje de la excepción y error() con el 500
    error_log('API de la carta: ' . $e->getMessage());
    error(500, 'Error interno del servidor');
});

/** La ruta existe, pero no admite ese método: 405 con la lista de los que sí admite. */
function metodoNoPermitido(string $permitidos): never
{
    header("Allow: $permitidos");
    error(405, 'Método no permitido');
}

/** Devuelve el cuerpo de la petición como array. Si no es un objeto JSON, responde 400. */
function leerJson(): array
{
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) {
        error(400, 'El cuerpo debe ser un objeto JSON válido');
    }
    return $datos;
}

/** Corta la petición con un 401 si no trae la cabecera Authorization: Bearer <clave>. */
function exigirClave(string $claveApi): void
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!hash_equals('Bearer ' . $claveApi, $cabecera)) {
        header('WWW-Authenticate: Bearer');
        error(401, 'Falta la clave de la API o no es válida');
    }
}
