<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = (new Lab\Database())->connect();
    $tools = new Lab\LocalDatabaseTools($db);
    $tools->init(__DIR__ . '/schema.sql');
} catch (\Throwable $e) {
    fwrite(STDERR, "Initialization error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
