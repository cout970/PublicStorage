<?php

use Psr\Log\AbstractLogger;

/**
 * Application logger
 * @return AppLogger
 */
function logger(): AppLogger {
  static $logger = NULL;

  if ($logger === NULL) {
    $logger = new AppLogger();
  }

  return $logger;
}

class AppLogger extends AbstractLogger {
  /** @noinspection PhpHierarchyChecksInspection */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $logs = config()['log_path'];

    if (empty($logs)) {
      // Log to console
      /** @noinspection ForgottenDebugOutputInspection */
      @error_log($message, 4);
      return;
    }

    $message = trim((string)$message);

    // Add space to separate message and prefix
    if (!str_starts_with($message, '[')) {
      $message = ' ' . $message;
    }

    // Add indentation to multiline messages
    $message = str_replace(PHP_EOL, PHP_EOL . '  ', $message);

    $msg = '[' . date(DATE_ATOM) . '][' . $level . ']' . $message . PHP_EOL;
    @file_put_contents($logs, $msg, FILE_APPEND);
  }
}