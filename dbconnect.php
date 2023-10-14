<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'myshift');
define('DB_USER', 'root');
define('DB_PASS', '');

function connect() {
  $host = DB_HOST;
  $db   = DB_NAME;
  $user = DB_USER;
  $pass = DB_PASS;

  $dsn = "mysql:host=$host;dbname=$db;charset=utf8";

  try {
    $pdo = new PDO($dsn, $user, $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
  } catch(PDOException $e) {
    echo '接続失敗です' . $e->getMessage();
    exit;
  }
}