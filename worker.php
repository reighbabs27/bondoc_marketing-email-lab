<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = (new Lab\Database())->connect();
    $history = new Lab\EmailHistory($db);
    $smtpSettings = Lab\Settings::smtp();
    $sender = new Lab\EmailSender($smtpSettings);
    $logger = new Lab\ActivityLogger();
    $delay = (int) ($smtpSettings['delay_seconds'] ?? 11);

    $processor = new Lab\MarketingProcessor($db, $history, $sender, $logger, $delay);

    $sqs = Lab\Settings::queue();
    $queueUrl = Lab\Settings::required('SQS_QUEUE_URL');

    $worker = new Lab\QueueWorker($sqs, $queueUrl, $processor, $logger);

    // Default runs continuously unless --batch flag is specified
    $continuous = !in_array('--batch', $argv, true);
    $worker->run($continuous);
} catch (\Throwable $e) {
    fwrite(STDERR, "Worker fatal error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
