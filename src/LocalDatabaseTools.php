<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class LocalDatabaseTools
{
    public function __construct(private PDO $db)
    {
    }

    public function init(string $schemaFile): void
    {
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException("Schema file not found: {$schemaFile}");
        }

        $sql = file_get_contents($schemaFile);
        $this->db->exec($sql);
        echo "Database schema initialized successfully from {$schemaFile}.\n";
    }

    public function inspect(?string $campaignId = 'CAMP-001'): void
    {
        echo "============================================================\n";
        echo "                DATABASE INSPECTION REPORT                  \n";
        echo "============================================================\n\n";

        // 1. Customers Summary
        echo "--- CUSTOMERS SUMMARY ---\n";
        $totalCust = (int) $this->db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
        echo "Total Customers: {$totalCust}\n";

        $optInStmt = $this->db->query("SELECT marketing_opt_in, COUNT(*) AS count FROM customers GROUP BY marketing_opt_in");
        while ($row = $optInStmt->fetch()) {
            $status = ((int) $row['marketing_opt_in'] === 1) ? 'Opted-In (1)' : 'Opted-Out (0)';
            echo "  - {$status}: {$row['count']}\n";
        }

        echo "\nSample Customers (first 5):\n";
        $sampleStmt = $this->db->query("SELECT customer_id, full_name, email, marketing_opt_in FROM customers ORDER BY customer_id LIMIT 5");
        while ($row = $sampleStmt->fetch()) {
            echo sprintf("  %-12s | %-25s | %-32s | Opt-in: %d\n", $row['customer_id'], $row['full_name'], $row['email'], $row['marketing_opt_in']);
        }

        // 2. Outbox Summary
        echo "\n--- MARKETING OUTBOX SUMMARY ---\n";
        $outboxTotal = (int) $this->db->query("SELECT COUNT(*) FROM marketing_outbox")->fetchColumn();
        echo "Total Outbox Rows: {$outboxTotal}\n";

        $outboxStatusStmt = $this->db->query("SELECT status, COUNT(*) AS count FROM marketing_outbox GROUP BY status");
        while ($row = $outboxStatusStmt->fetch()) {
            echo "  - {$row['status']}: {$row['count']}\n";
        }

        if ($campaignId !== null) {
            $campStmt = $this->db->prepare("
                SELECT COUNT(*) AS total_req,
                       COUNT(DISTINCT customer_id) AS distinct_recipients
                FROM marketing_outbox
                WHERE campaign_id = :campaign_id
            ");
            $campStmt->execute([':campaign_id' => $campaignId]);
            $campRow = $campStmt->fetch();
            echo "Campaign '{$campaignId}' Stats:\n";
            echo "  - Total Requests: {$campRow['total_req']}\n";
            echo "  - Distinct Recipients: {$campRow['distinct_recipients']}\n";

            $ineligibleStmt = $this->db->prepare("
                SELECT COUNT(*) AS ineligible_recipients
                FROM marketing_outbox o
                JOIN customers c ON c.customer_id = o.customer_id
                WHERE o.campaign_id = :campaign_id AND c.marketing_opt_in <> 1
            ");
            $ineligibleStmt->execute([':campaign_id' => $campaignId]);
            $ineligibleCount = (int) $ineligibleStmt->fetchColumn();
            echo "  - Ineligible Recipients (opt-in <> 1): {$ineligibleCount}\n";
        }

        // 3. Sent Emails Summary
        echo "\n--- SENT MARKETING EMAILS HISTORY ---\n";
        $sentTotal = (int) $this->db->query("SELECT COUNT(*) FROM sent_marketing_emails")->fetchColumn();
        echo "Total Sent/History Rows: {$sentTotal}\n";

        $sentStatusStmt = $this->db->query("SELECT status, COUNT(*) AS count FROM sent_marketing_emails GROUP BY status");
        while ($row = $sentStatusStmt->fetch()) {
            echo "  - {$row['status']}: {$row['count']}\n";
        }

        if ($campaignId !== null) {
            $campSentStmt = $this->db->prepare("
                SELECT status, COUNT(*) AS count 
                FROM sent_marketing_emails 
                WHERE campaign_id = :campaign_id 
                GROUP BY status
            ");
            $campSentStmt->execute([':campaign_id' => $campaignId]);
            echo "Campaign '{$campaignId}' History:\n";
            while ($row = $campSentStmt->fetch()) {
                echo "  - {$row['status']}: {$row['count']}\n";
            }
        }

        echo "\nSample History Rows (last 5):\n";
        $sampleSentStmt = $this->db->query("SELECT campaign_id, customer_id, promo_code, status, provider, reason, processed_at FROM sent_marketing_emails ORDER BY processed_at DESC LIMIT 5");
        while ($row = $sampleSentStmt->fetch()) {
            echo sprintf("  %-10s | %-12s | %-10s | %-8s | %-10s | %s\n", 
                $row['campaign_id'], $row['customer_id'], $row['promo_code'], $row['status'], $row['provider'] ?? 'N/A', $row['reason'] ?? 'OK');
        }

        echo "\n============================================================\n";
    }
}
