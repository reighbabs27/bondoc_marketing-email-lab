<?php
declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;

final class CampaignPreparer
{
    public function __construct(private PDO $db)
    {
    }

    public function prepare(
        string $campaignId,
        string $promoCode,
        string $subject = 'Laboratory promotion',
        string $body = 'You have been selected to receive our promotional offer.'
    ): void {
        // TODO C3: Before selecting, check existing outbox rows for the campaign.
        // If 50 already exist, keep the original selection unchanged.
        // If an unexpected partial selection exists, report the inconsistency rather than silently adding different winners.
        $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = :campaign_id");
        $checkStmt->execute([':campaign_id' => $campaignId]);
        $existingCount = (int) $checkStmt->fetchColumn();

        if ($existingCount === 50) {
            echo "Campaign '{$campaignId}' already has 50 prepared requests. Original selection preserved.\n";
            return;
        }

        if ($existingCount > 0) {
            throw new RuntimeException(
                "Campaign '{$campaignId}' has an unexpected partial selection of {$existingCount} outbox rows. " .
                "Cannot proceed without resolving the inconsistency."
            );
        }

        // TODO C2: Select exactly 50 distinct customers at random from those with marketing_opt_in=1.
        // Sample without replacement.
        $selectStmt = $this->db->query("
            SELECT customer_id 
            FROM customers 
            WHERE marketing_opt_in = 1 
            ORDER BY RAND() 
            LIMIT 50
        ");
        $selectedCustomers = $selectStmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($selectedCustomers) !== 50) {
            throw new RuntimeException("Could not find 50 eligible opted-in customers to select.");
        }

        // Prepare insert statement
        $insertStmt = $this->db->prepare("
            INSERT INTO marketing_outbox (
                request_id, campaign_id, customer_id, promo_code, subject, body, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?)
        ");

        $this->db->beginTransaction();

        try {
            $createdAt = gmdate('Y-m-d H:i:s');

            foreach ($selectedCustomers as $customerId) {
                // Generate a 32-character hexadecimal request ID using random_bytes() and bin2hex()
                $requestId = bin2hex(random_bytes(16));

                // TODO C1: Execute its prepared insert with seven values in order:
                // request ID, campaign ID, customer ID, promo code, subject, body, and creation time.
                $insertStmt->execute([
                    $requestId,
                    $campaignId,
                    $customerId,
                    $promoCode,
                    $subject,
                    $body,
                    $createdAt,
                ]);
            }

            $this->db->commit();
            echo "Successfully prepared 50 campaign requests for campaign '{$campaignId}' with promo '{$promoCode}'.\n";
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
