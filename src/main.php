<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/util/config.php';
require __DIR__ . '/util/logger.php';
require __DIR__ . '/util/sql.php';
require __DIR__ . '/pastes.php';
require __DIR__ . '/accounts.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

define('REQUEST_TIME', time());

function main() {
  apply_migrations();

  $app = AppFactory::create();
  $app->addBodyParsingMiddleware();
  $app->addErrorMiddleware(config()['debug'], true, true, logger());

  // CORS
  $app->add(function ($request, $handler) {

    $response = $handler->handle($request);
    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Location')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
      ->withHeader('Access-Control-Expose-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization, Location');
  });

  register_pastes($app);
  register_accounts($app);

  $app->get('/', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('Hello, world <br> ' . date(DATE_ATOM));
    return $response;
  });

  /**
   * Catch-all route to serve a 404 Not Found page if none of the routes match
   * NOTE: make sure this route is defined last
   */
  $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    throw new HttpNotFoundException($request, 'Invalid path: '. $request->getUri());
  });

  $serverRequestCreator = ServerRequestCreatorFactory::create();
  $request = $serverRequestCreator->createServerRequestFromGlobals();
  $GLOBALS['request'] = $request;
  $app->run($request);
}
