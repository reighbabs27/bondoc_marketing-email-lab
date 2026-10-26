<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = (new Lab\Database())->connect();
    $tools = new Lab\LocalDatabaseTools($db);
    $campaignId = $argv[1] ?? 'CAMP-001';
    $tools->inspect($campaignId);
} catch (\Throwable $e) {
    fwrite(STDERR, "Inspection error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
