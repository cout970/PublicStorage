<?php

use Slim\Psr7\Request;
use Slim\Psr7\Response;

function register_accounts($app) {
  // Create account
  $app->post('/api/v1/account', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $name = (string)$body['name'];
    $password = (string)$body['password'];

    check($name, 'Empty name', 400);
    check($password, 'Empty password', 400);

    $exists = query_assoc('select 1 from accounts where name = :name', [':name' => $name])->as_field();

    check(!$exists, 'Account already exists', 400);

    // Create account
    $hashed_password = generate_password_hash($password);

    query_assoc(
      'insert into accounts (name, hashed_password, created_at, updated_at) values (:name, :hashed_password, :created_at, :updated_at)', [
        ':name' => $name,
        ':hashed_password' => $hashed_password,
        ':created_at' => REQUEST_TIME,
        ':updated_at' => REQUEST_TIME
      ]
    );

    // Generate session token
    $id = get_connection()->lastInsertId();
    $token = get_or_create_token($id);

    $response->getBody()->write(to_json(['token' => $token]));
    return $response->withStatus(201, 'Created');
  });

  // Get session token
  $app->post('/api/v1/account/login', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $name = (string)$body['name'];
    $password = (string)$body['password'];

    check($name, 'Empty name', 400);
    check($password, 'Empty password', 400);

    $account = query_assoc(
      'select id, hashed_password from accounts where name = :name', [':name' => $name]
    )->as_row();

    check($account, 'User not found', 404);
    check(check_password_hash($account['hashed_password'], $password), 'Invalid password', 403);

    $token = get_or_create_token($account['id']);

    $response->getBody()->write(to_json(['token' => $token]));
    return $response->withStatus(200, 'Ok');
  });

  // Invalidate session token
  $app->post('/api/v1/account/logout', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $token = (string)$body['token'];

    validate_token($token);

    query_assoc('delete from account_tokens where token = :token', [':token' => $token]);
    return $response->withStatus(200, 'Ok');
  });

  // Update password
  $app->put('/api/v1/account', function (Request $request, Response $response, array $args) {
    $body = $request->getParsedBody();
    $token = (string)$body['token'];
    $password = (string)$body['password'];

    $account = validate_token($token);

    // Update password
    $hashed_password = generate_password_hash($password);

    query_assoc(
      'update accounts set hashed_password = :hashed_password, updated_at = :created_at where id = :account', [
        ':hashed_password' => $hashed_password,
        ':created_at' => REQUEST_TIME,
        ':account' => $account,
      ]
    );

    // Invalidate sessions
    query_assoc('delete from account_tokens where account = :account', [':account' => $account]);

    $token = get_or_create_token($account);

    $response->getBody()->write(to_json(['token' => $token]));
    return $response->withStatus(200, 'Ok');
  });

  // Delete account
  $app->delete('/api/v1/account', function (Request $request, Response $response, array $args) {
    $token = $request->getParsedBody()['token'];

    $account = validate_token($token);

    $exists = query_assoc(
      'select 1 from accounts where id = :id', [':id' => $account]
    )->as_field();

    check($exists, 'Not found', 404);

    query_assoc('delete from accounts where id = :id', [':id' => $account]);
    query_assoc('delete from account_tokens where account = :id', [':id' => $account]);
    query_assoc('delete from pastes where account = :id', [':id' => $account]);

    return $response->withStatus(204, 'Deleted');
  });
}

/**
 * Reuses or creates a new session token
 * @param $account
 * @return false|mixed|string|null
 * @throws Exception
 */
function get_or_create_token($account) {
  // Reuse previous token if still valid
  $token = query_assoc(
    'select token from account_tokens where account = :account and created_at > :timeout_limit', [
      ':account' => $account,
      ':timeout_limit' => REQUEST_TIME - (config()['session_timeout']),
    ]
  )->as_field();

  if (empty($token)) {
    // Create new token
    $token = bin2hex(random_bytes(16));

    query_assoc(
      'insert into account_tokens (token, account, created_at) values (:token, :account, :created_at)', [
        ':token' => $token,
        ':account' => $account,
        ':created_at' => REQUEST_TIME,
      ]
    );
  }
  return $token;
}

/**
 * Gets the user of a valid token or throw a validation exception if the token is not valid
 * @param $token
 * @return int
 */
function validate_token($token) {
  check($token, 'Missing token', 403);

  $account = query_assoc(
    'select account from account_tokens where token = :token and created_at > :timeout_limit', [
      ':token' => $token,
      ':timeout_limit' => REQUEST_TIME - (config()['session_timeout']),
    ]
  )->as_field();

  check($account, 'Invalid token', 403);
  return $account;
}

/**
 * Generate a password hash with salt
 * @param string $password
 * @return string
 * @throws Exception
 */
function generate_password_hash(string $password): string {
  $salt = bin2hex(random_bytes(8));
  $base = $password . ':' . $salt;
  $newHash = hash('sha512', $base);

  // Make the computation slower
  for ($i = 0; $i < 1024; $i++) {
    $newHash = hash('sha512', $newHash);
  }

  return $newHash . ':' . $salt;
}

/**
 * Check if a password matches with previous hash
 * @param string $hash
 * @param string $password
 * @return bool
 */
function check_password_hash(string $hash, string $password): bool {
  [$originalHash, $salt] = explode(':', $hash);
  $base = $password . ':' . $salt;
  $newHash = hash('sha512', $base);

  // Make the computation slower
  for ($i = 0; $i < 1024; $i++) {
    $newHash = hash('sha512', $newHash);
  }

  return $originalHash === $newHash;
}