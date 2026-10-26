<?php
declare(strict_types=1);

namespace Lab;

use Faker\Generator;
use PDO;

final class CustomerSeeder
{
    public function __construct(
        private PDO $db,
        private Generator $faker
    ) {}

    public function seed(int $total = 10000): void
    {
        $existingCount = (int) $this->db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
        if ($existingCount >= $total) {
            echo "Customers table already contains {$existingCount} records. Preserving existing records.\n";
            return;
        }

        echo "Seeding {$total} customers...\n";

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO customers (customer_id, full_name, email, marketing_opt_in, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $this->db->beginTransaction();

        try {
            $now = gmdate('Y-m-d H:i:s');
            for ($i = 1; $i <= $total; $i++) {
                $customerId = sprintf('CUST-%05d', $i);
                $email = sprintf('customer%05d@example.com', $i);
                
                // TODO S1: Generate a name using the injected Faker object
                $name = $this->faker->name();

                // TODO S2: Assign consent 1 for customers 1-8,000 and 0 for customers 8,001-10,000.
                // This produces 8,000 eligible and 2,000 ineligible customers.
                $consent = ($i <= 8000) ? 1 : 0;

                $stmt->execute([
                    $customerId,
                    $name,
                    $email,
                    $consent,
                    $now,
                ]);
            }
            $this->db->commit();
            echo "Successfully seeded customers.\n";
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
