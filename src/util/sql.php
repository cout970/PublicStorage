<?php

function get_connection(): PDO {
  static $con = NULL;

  if ($con === NULL) {
    // Make sure the database file can be created
    $dir = dirname(config()['db_path']);
    if (!file_exists($dir) && !mkdir($dir, 775, true) && !is_dir($dir)) {
      throw new \RuntimeException(sprintf('Directory "%s" it\'s necessary and cannot be created', $dir));
    }

    // Create/read the database file
    $con = new PDO('sqlite:' . config()['db_path'], NULL, NULL, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
  }

  return $con;
}

function query_list(string $sql, array $args = []) {
  $index = 0;
  $assoc = [];
  foreach ($args as $arg) {
    $name = ':n' . $index;
    $assoc[$name] = $arg;
    $sql = (string)preg_replace('/\?/', $name, $sql, 1);
    $index++;
  }
  return query_assoc($sql, $assoc);
}

function query_assoc(string $sql, array $args = [], ?int $limit = NULL, ?int $offset = NULL) {
  $con = get_connection();

  if (isset($limit)) {
    if (isset($offset)) {
      $sql .= ' LIMIT ' . $offset . ', ' . $limit;
    } else {
      $sql .= ' LIMIT ' . $limit;
    }
  }

  try {
    $stm = $con->prepare($sql);
    $stm->execute($args);
    return new DbResponse($stm);
  } catch (PDOException $e) {
    logger()->error((string)$e);
    throw $e;
  }
}

/**
 * ['a' => 1, 'b' => 2] => [':a' => 1, ':b' => 2]
 * @param $array
 * @return array
 */
function keys_to_params($array) {
  $new_array = [];
  foreach ($array as $key => $value) {
    $new_array[':' . $key] = $value;
  }
  return $new_array;
}

/**
 * Make sure all available migrations are applied
 */
function apply_migrations() {
  $last_id = get_last_migration();
  $migrations = get_migrations();
  $next_id = $last_id + 1;

  while (isset($migrations[$next_id])) {
    $sql = $migrations[$next_id];

    try {
      query_assoc('begin transaction');
      foreach ($sql as $line) {
        query_assoc($line);
      }
      query_assoc('insert into migrations (id) values (:id)', [':id' => $next_id]);
      query_assoc('commit');
      logger()->info('Executed DB migrations: migration id ' . $next_id);
      $next_id++;
    } catch (Throwable $e) {
      query_assoc('rollback');
      logger()->emergency('Failed migration: ' . $e);
      return;
    }
  }
}

/**
 * Returns the id of the last applied migration or 0 if none where applied
 */
function get_last_migration(): int {
  $has_first = query_assoc(
    "SELECT 1 FROM sqlite_master WHERE type='table' AND name='migrations' limit 1;"
  )->as_field();

  if (!$has_first) {
    return 0;
  }

  return (int)(query_assoc('select id from migrations order by id desc limit 1')->as_field() ?? 1);
}

class DbResponse {
  /** @var PDOStatement $stm */
  private PDOStatement $stm;

  /**
   * DbResponse constructor.
   * @param $stm
   */
  public function __construct($stm) {
    $this->stm = $stm;
  }

  public function as_table(): array {
    return $this->stm->fetchAll(PDO::FETCH_ASSOC);
  }

  public function as_map(): array {
    $table = $this->stm->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($table as $row) {
      $map[reset($row)] = end($row);
    }
    return $map;
  }

  public function as_column(): array {
    $column = $this->stm->fetchAll(PDO::FETCH_COLUMN);
    return $column === FALSE ? [] : $column;
  }

  public function as_row(): ?array {
    $row = $this->stm->fetch(PDO::FETCH_ASSOC);
    return $row === FALSE ? NULL : $row;
  }

  public function as_field() {
    $row = $this->stm->fetch(PDO::FETCH_ASSOC);
    return empty($row) ? NULL : reset($row);
  }
}

/**
 * List of available migrations
 *
 * Seeds of sqlite database tables
 * @return string[][]
 */
function get_migrations() {
  return [
    '1' => ['create table migrations(id integer primary key)'],
    '2' => ['
        create table pastes(
            id integer primary key not null,
            account integer not null,
            contents text not null,
            created_at integer not null,
            updated_at integer not null
        )
    '],
    '3' => ['
        create table accounts(
            id integer primary key not null,
            name text unique not null,
            hashed_password text not null,
            created_at integer not null,
            updated_at integer not null
        )
    '],
    '4' => ['
        create table account_tokens(
            id integer primary key not null,
            token text unique not null,
            account integer not null,
            created_at integer not null
        )
    '],
  ];
}