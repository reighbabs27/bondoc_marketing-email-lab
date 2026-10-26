<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
    $db = (new Lab\Database())->connect();
    $faker = Faker\Factory::create('en_US');
    $faker->seed(2026);

    $seeder = new Lab\CustomerSeeder($db, $faker);
    $seeder->seed();
} catch (\Throwable $e) {
    fwrite(STDERR, "Seeder error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
