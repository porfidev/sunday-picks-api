<?php

use App\Controllers\DatabaseInitController;
use App\Controllers\HealthController;
use App\Controllers\AuthController;
use App\Controllers\UsersController;
use App\Controllers\WeeksController;
use App\Controllers\SeasonsController;
use App\Controllers\TeamsController;
use App\Controllers\GamesController;
use App\Controllers\PicksController;
use App\Controllers\GameResultsController;
use App\Middleware\AuthMiddleware;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use App\Database;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    Database::class => \DI\autowire(),
]);
$container = $containerBuilder->build();
AppFactory::setContainer($container);
$app = AppFactory::create();

// CORS configuration
$allowedOriginsEnv = $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*';
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsEnv))));
$allowAnyOrigin = $allowedOrigins === [] || in_array('*', $allowedOrigins, true);
$allowCredentials = in_array(
    strtolower((string)($_ENV['CORS_ALLOW_CREDENTIALS'] ?? 'false')),
    ['1', 'true', 'yes'],
    true
);

$app->options('/{routes:.+}', function (Request $request, Response $response): Response {
    return $response;
});

$app->add(function (Request $request, RequestHandlerInterface $handler) use ($allowedOrigins, $allowAnyOrigin, $allowCredentials): Response {
    $origin = trim($request->getHeaderLine('Origin'));
    $response = $handler->handle($request);
    $corsOrigin = null;

    if ($allowAnyOrigin && !$allowCredentials) {
        $corsOrigin = '*';
    } elseif ($origin !== '' && ($allowAnyOrigin || in_array($origin, $allowedOrigins, true))) {
        $corsOrigin = $origin;
    }

    if ($corsOrigin !== null) {
        $response = $response->withHeader('Access-Control-Allow-Origin', $corsOrigin);
        $response = $response->withHeader('Vary', 'Origin');
    }

    if ($allowCredentials && $corsOrigin !== null && $corsOrigin !== '*') {
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    }

    $requestHeaders = trim($request->getHeaderLine('Access-Control-Request-Headers'));
    $allowHeaders = $requestHeaders !== '' ? $requestHeaders : 'Content-Type, Authorization, X-Requested-With';

    return $response
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', $allowHeaders);
});

$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->get('/', function(Request $request, Response $response) {
   $html = file_get_contents(__DIR__ . '/home.html');
   $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
   $version = $composer['version'] ?? 'unreleased';
   $html = str_replace('{{version}}', $version, $html);

   $response->getBody()->write($html);
   return $response->withHeader('Content-Type', 'text/html');
});
$app->get('/version', HealthController::class. ':version');
$app->get('/ping', HealthController::class. ':ping');
$app->get('/init-db',DatabaseInitController::class. ':init');

$app->group('/users', function($group) {
    $group->post('/', UsersController::class . ':register');
    $group->patch('/{id}/password', UsersController::class . ':changePassword');
    $group->put('/{id}', UsersController::class . ':update');
    $group->delete('/{id}', UsersController::class . ':delete');
    $group->get('/', UsersController::class . ':index');
    $group->get('/{id}', UsersController::class . ':show');
})->add(AuthMiddleware::class);

$app->group('/auth', function($group) {
    $group->post('/login', AuthController::class . ':login');
    $group->post('/refresh', AuthController::class . ':refresh');
    $group->post('/logout', AuthController::class . ':logout')->add(AuthMiddleware::class);
    $group->patch('/password', AuthController::class . ':changePassword')->add(AuthMiddleware::class);
});

$app->group('/weeks', function($group) {
    $group->post('/', WeeksController::class . ':register');
    $group->put('/{id}', WeeksController::class . ':update');
    $group->delete('/{id}', WeeksController::class . ':delete');
    $group->get('/', WeeksController::class . ':index');
    $group->get('/{id}', WeeksController::class . ':show');
})->add(AuthMiddleware::class);

$app->group('/seasons', function($group) {
    $group->post('/', SeasonsController::class . ':register');
    $group->put('/{id}', SeasonsController::class . ':update');
    $group->delete('/{id}', SeasonsController::class . ':delete');
    $group->get('/', SeasonsController::class . ':index');
    $group->get('/{id}', SeasonsController::class . ':show');
})->add(AuthMiddleware::class);

$app->group('/teams', function($group) {
    $group->post('/', TeamsController::class . ':register');
    $group->post('/{id}', TeamsController::class . ':update');
    $group->delete('/{id}', TeamsController::class . ':delete');
    $group->get('/', TeamsController::class . ':index');
    $group->get('/{id}', TeamsController::class . ':show');
})->add(AuthMiddleware::class);

$app->group('/games', function($group) {
    $group->post('/', GamesController::class . ':register');
    $group->put('/{id}', GamesController::class . ':update');
    $group->get('/', GamesController::class . ':index');
    $group->get('/{id}', GamesController::class . ':show');
})->add(AuthMiddleware::class);

$app->group('/picks', function($group) {
    $group->post('/', PicksController::class . ':register');
    $group->put('/{id}', PicksController::class . ':update');
})->add(AuthMiddleware::class);

$app->group('/game-results', function($group) {
    $group->post('/', GameResultsController::class . ':register');
    $group->put('/{id}', GameResultsController::class . ':update');
    $group->get('/', GameResultsController::class . ':index');
    $group->get('/{id}', GameResultsController::class . ':show');
})->add(AuthMiddleware::class);

$app->run();
