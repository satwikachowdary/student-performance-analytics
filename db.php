<?php
// db.php — SQLite connection (creates sleepstudy.sqlite in this folder)
$DB_PATH = __DIR__ . '/sleepstudy.sqlite';

try {
    $pdo = new PDO('sqlite:' . $DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (PDOException $e) {
    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
}
