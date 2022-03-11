<?php

/**
 * Application settings
 *
 * @return array
 */
function config(): array {
  static $config = NULL;

  if ($config === NULL) {
    $path = '../default.config.json';

    if (file_exists('../data/config.json')) {
      $path = '../data/config.json';
    }

    $json = file_get_contents($path);
    try {
      $config = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
      print $e;
      die;
    }
  }

  return $config;
}

/**
 * Encode json
 * @param $value
 */
function to_json($value): string {
  return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * Decode json
 * @param $value
 * @param bool $throw_on_error
 * @return mixed
 */
function from_json($value, bool $throw_on_error = FALSE) {
  return json_decode($value, TRUE, 512, $throw_on_error ? JSON_THROW_ON_ERROR : 0);
}

/**
 * @param $condition
 * @param $msg
 * @param $status
 * @return void
 * @throws \Slim\Exception\HttpException
 */
function check($condition, string $msg, int $status): void {
  if (!$condition) {
    $request = $GLOBALS['request'];
    $e = new \Slim\Exception\HttpException($request, $msg, $status);
    $e->setTitle($status . ' ' . $msg);
    throw $e;
  }
}