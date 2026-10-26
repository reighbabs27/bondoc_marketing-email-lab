<?php
declare(strict_types=1);

namespace Lab;

final class ActivityLogger
{
    private string $logFilePath;

    public function __construct(?string $logFilePath = null)
    {
        $this->logFilePath = $logFilePath ?? dirname(__DIR__) . '/logs/events.jsonl';
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    public function record(string $event, array $data = []): void
    {
        // Strip out private data if present to ensure compliance
        $disallowedKeys = ['full_name', 'name', 'email', 'recipient_email', 'body', 'password', 'credentials'];
        $cleanData = array_diff_key($data, array_flip($disallowedKeys));

        $record = array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'event' => $event,
        ], $cleanData);

        file_put_contents(
            $this->logFilePath,
            json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
