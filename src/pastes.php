<?php


use Slim\Psr7\Request;
use Slim\Psr7\Response;

function register_pastes($app) {
  // Create paste
  $app->post('/api/v1/paste', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $token = (string)$body['token'];
    $contents = (string)$body['contents'];

    $account = validate_token($token);

    check($contents, 'Empty contents', 400);

    query_assoc(
      'insert into pastes (account, contents, created_at, updated_at) values (:account, :contents, :created_at, :updated_at)', [
        ':account' => $account,
        ':contents' => $contents,
        ':created_at' => REQUEST_TIME,
        ':updated_at' => REQUEST_TIME,
      ]
    );
    $id = get_connection()->lastInsertId();

    return $response->withHeader('Location', '/api/paste/' . $id)->withStatus(201, 'Created');
  });

  // Delete paste
  $app->delete('/api/v1/paste/{id}', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $token = (string)$body['token'];

    $account = validate_token($token);

    $exists = query_assoc(
      'select 1 from pastes where id = :id and account = :account', [
        ':id' => $args['id'],
        ':account' => $account,
      ]
    )->as_field();

    check($exists, 'Not found', 404);

    query_assoc('delete from pastes where id = :id', [':id' => $args['id']]);
    return $response->withStatus(204, 'Deleted');
  });

  // Update paste
  $app->put('/api/v1/paste/{id}', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $token = (string)$body['token'];
    $contents = (string)$body['contents'];

    $account = validate_token($token);

    check($contents, 'Empty contents', 400);

    $exists = query_assoc(
      'select 1 from pastes where id = :id and account = :account', [
        ':id' => $args['id'],
        ':account' => $account,
      ]
    )->as_field();

    check($exists, 'Not found', 404);

    query_assoc(
      'update pastes set contents = :contents, updated_at = :updated_at where id = :id', [
        ':contents' => $contents,
        ':id' => $args['id'],
        ':updated_at' => REQUEST_TIME,
      ]
    );
    return $response->withStatus(200, 'Updated');
  });

  // Get paste
  $app->get('/api/v1/paste/{id}', function (Request $request, Response $response, array $args) {
    $paste = query_assoc(
      'select contents, created_at from pastes where id = :id', [':id' => $args['id']]
    )->as_row();

    if ($paste === NULL) {
      return $response->withStatus(404, 'Not found');
    }

    $filename = date(DATE_ATOM, $paste['created_at']) . '.txt';
    $file_length = strlen($paste['contents']);

    $response->getBody()->write($paste['contents']);
    return $response
      ->withHeader('Content-Disposition', 'attachment; filename=' . $filename)
      ->withHeader('Content-Type', 'application/octet-stream')
      ->withHeader('Content-Transfer-Encoding', 'binary')
      ->withHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
      ->withHeader('Pragma', 'no-cache')
      ->withHeader('Expires', '0')
      ->withHeader('Content-Length', $file_length);
  });
}