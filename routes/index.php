<?php
require_once 'helpers/response.php';
require_once 'controllers/AuthController.php';
require_once 'controllers/FoodController.php';
require_once 'controllers/CartController.php';
require_once 'controllers/OrderController.php';

$method = $_SERVER['REQUEST_METHOD'];
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// Strip project prefix if running in subfolder
$uri = preg_replace('#^chuks-kitchen/?#', '', $uri);
$parts = explode('/', $uri);
$resource = $parts[0] ?? '';
$param    = $parts[1] ?? null;
$action   = $parts[2] ?? null;

$body = json_decode(file_get_contents("php://input"), true) ?? [];

if ($resource === '') {
    respond(true, "Welcome to Chuks Kitchen API 🚀");
    exit;
}

match(true) {
    $resource === 'signup'  && $method === 'POST' => AuthController::signup($body),
    $resource === 'verify'  && $method === 'POST' => AuthController::verify($body),

    $resource === 'foods'   && $method === 'GET'  => FoodController::list(),
    $resource === 'foods'   && $method === 'POST' => FoodController::add($body),

    $resource === 'cart'    && $method === 'POST' => CartController::add($body),
    $resource === 'cart'    && $method === 'GET'  && $param => CartController::view($param),
    $resource === 'cart'    && $param === 'clear' && $method === 'DELETE' && $action => CartController::clear($action),

    $resource === 'orders'  && $method === 'POST' => OrderController::create($body),
    $resource === 'orders'  && $method === 'GET'  && $param => OrderController::get($param),
    $resource === 'orders'  && $method === 'PUT'  && $param && $action === 'cancel' => OrderController::cancel($param),

    default => respond(false, "Route not found", null, 404)
};