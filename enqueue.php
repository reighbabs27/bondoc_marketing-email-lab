<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $publisher = new Lab\QueuePublisher(
        (new Lab\Database())->connect(),
        Lab\Settings::queue(),
        Lab\Settings::required('SQS_QUEUE_URL'),
        new Lab\ActivityLogger()
    );
    $publisher->publishPending();
} catch (\Throwable $e) {
    fwrite(STDERR, "Enqueue error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
