<?php

use App\Controllers\DatabaseInitController;
use App\Controllers\HealthController;
use App\Controllers\UsersController;
use App\Controllers\WeeksController;
use App\Controllers\SeasonsController;
use App\Controllers\TeamsController;
use App\Controllers\GamesController;
use App\Controllers\PicksController;
use App\Controllers\GameResultsController;
use DI\ContainerBuilder;
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

$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);
$app->get('/version', HealthController::class. ':version');
$app->get('/ping', HealthController::class. ':ping');
$app->get('/init-db',DatabaseInitController::class. ':init');

$app->group('/users', function($group) {
    $group->post('/register', UsersController::class . ':register');
    $group->put('/{id}', UsersController::class . ':update');
    $group->delete('/{id}', UsersController::class . ':delete');
    $group->get('/', UsersController::class . ':index');
    $group->get('/{id}', UsersController::class . ':show');
});

$app->group('/weeks', function($group) {
    $group->post('/', WeeksController::class . ':register');
    $group->put('/{id}', WeeksController::class . ':update');
    $group->delete('/{id}', WeeksController::class . ':delete');
    $group->get('/', WeeksController::class . ':index');
    $group->get('/{id}', WeeksController::class . ':show');
});

$app->group('/seasons', function($group) {
    $group->post('/', SeasonsController::class . ':register');
    $group->put('/{id}', SeasonsController::class . ':update');
    $group->delete('/{id}', SeasonsController::class . ':delete');
    $group->get('/', SeasonsController::class . ':index');
    $group->get('/{id}', SeasonsController::class . ':show');
});

$app->group('/teams', function($group) {
    $group->post('/', TeamsController::class . ':register');
    $group->post('/{id}', TeamsController::class . ':update');
    $group->delete('/{id}', TeamsController::class . ':delete');
    $group->get('/', TeamsController::class . ':index');
    $group->get('/{id}', TeamsController::class . ':show');
});

$app->group('/games', function($group) {
    $group->post('/', GamesController::class . ':register');
    $group->put('/{id}', GamesController::class . ':update');
    $group->get('/', GamesController::class . ':index');
    $group->get('/{id}', GamesController::class . ':show');
});

$app->group('/picks', function($group) {
    $group->post('/', PicksController::class . ':register');
    $group->put('/{id}', PicksController::class . ':update');
});

$app->group('/game-results', function($group) {
    $group->post('/', GameResultsController::class . ':register');
    $group->put('/{id}', GameResultsController::class . ':update');
    $group->get('/', GameResultsController::class . ':index');
    $group->get('/{id}', GameResultsController::class . ':show');
});

$app->run();
