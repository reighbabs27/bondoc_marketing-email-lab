<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$campaignId = $argv[1] ?? 'CAMP-001';
$promoCode = $argv[2] ?? 'PROMO15';

try {
    $db = (new Lab\Database())->connect();
    $preparer = new Lab\CampaignPreparer($db);
    $preparer->prepare($campaignId, $promoCode);
} catch (\Throwable $e) {
    fwrite(STDERR, "Campaign preparation error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
