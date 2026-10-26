<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class Settings
{
    public static function required(string $key): string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            throw new RuntimeException("Missing required environment variable: {$key}");
        }
        return $value;
    }

    public static function optional(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false || trim($value) === '') {
            return $default;
        }
        return $value;
    }

    public static function queue(): SqsClient
    {
        $region = self::optional('AWS_REGION', 'us-east-1');
        $profile = self::optional('AWS_PROFILE', 'marketing-lab');

        $config = [
            'region' => $region,
            'version' => 'latest',
        ];

        if ($profile !== null) {
            $config['profile'] = $profile;
        }

        return new SqsClient($config);
    }

    public static function smtp(): array
    {
        return [
            'host' => self::required('MAILTRAP_HOST'),
            'port' => (int) self::optional('MAILTRAP_PORT', '2525'),
            'username' => self::required('MAILTRAP_USERNAME'),
            'password' => self::required('MAILTRAP_PASSWORD'),
            'from_address' => self::optional('MAIL_FROM_ADDRESS', 'marketing@example.com'),
            'delay_seconds' => (int) self::optional('MAILTRAP_DELAY_SECONDS', '11'),
        ];
    }
}
