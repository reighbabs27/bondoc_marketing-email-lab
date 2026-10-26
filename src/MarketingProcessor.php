<?php
declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

final class MarketingProcessor
{
    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $sendDelay = 0
    ) {}

    public function process(array $job): void
    {
        // Validate required job fields
        $requiredFields = ['requestId', 'campaignId', 'customerId', 'promoCode', 'subject', 'body'];
        foreach ($requiredFields as $field) {
            if (!isset($job[$field]) || trim((string) $job[$field]) === '') {
                throw new InvalidArgumentException("Missing or empty required job field: {$field}");
            }
        }

        $requestId = (string) $job['requestId'];
        $campaignId = (string) $job['campaignId'];
        $customerId = (string) $job['customerId'];
        $promoCode = (string) $job['promoCode'];
        $subject = (string) $job['subject'];

        // Task M2: Record processing_started
        $this->logger->record('processing_started', [
            'requestId' => $requestId,
            'campaignId' => $campaignId,
            'customerId' => $customerId,
        ]);

        // Task P1: Use EmailHistory::isComplete() to return early for an already SENT or SKIPPED pair.
        // Record duplicate_skipped before returning.
        if ($this->history->isComplete($campaignId, $customerId)) {
            $this->logger->record('duplicate_skipped', [
                'requestId' => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
            ]);
            echo "Skipping duplicate request {$requestId} (Campaign: {$campaignId}, Customer: {$customerId}).\n";
            return;
        }

        // Task P2: Look up the local customer using a prepared SELECT and the job's customerId.
        $stmt = $this->db->prepare("
            SELECT customer_id, full_name, email, marketing_opt_in 
            FROM customers 
            WHERE customer_id = :customer_id
        ");
        $stmt->execute([':customer_id' => $customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = gmdate('Y-m-d H:i:s');

        // Task P3: Save SKIPPED and return if the customer is missing or not opted in. Include the reason.
        if (!$customer) {
            $reason = "Customer {$customerId} not found in database";
            $this->history->saveSkipped($campaignId, $customerId, $requestId, $promoCode, null, $subject, $reason, $now);
            $this->logger->record('skipped', [
                'requestId' => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'reason' => $reason,
            ]);
            echo "Skipped: {$reason}.\n";
            return;
        }

        if ((int) $customer['marketing_opt_in'] !== 1) {
            $reason = "Customer {$customerId} has not opted in (marketing_opt_in = 0)";
            $this->history->saveSkipped($campaignId, $customerId, $requestId, $promoCode, $customer['email'], $subject, $reason, $now);
            $this->logger->record('skipped', [
                'requestId' => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'reason' => $reason,
            ]);
            echo "Skipped: {$reason}.\n";
            return;
        }

        // Apply send delay if configured
        if ($this->sendDelay > 0) {
            sleep($this->sendDelay);
        }

        try {
            // Task P4: Call the injected EmailSender and retain the returned MIME Message-ID.
            $messageId = $this->sender->send($customer, $job);
            $sentAt = gmdate('Y-m-d H:i:s');
            $processedAt = $sentAt;

            // Task P5: Save SENT with recipient, provider, Message-ID, and UTC sent_at.
            $this->history->saveSent(
                $campaignId,
                $customerId,
                $requestId,
                $promoCode,
                $customer['email'],
                $subject,
                'Mailtrap',
                $messageId,
                $sentAt,
                $processedAt
            );

            // Task M2: Log sent only after the corresponding local outcome is saved.
            $this->logger->record('sent', [
                'requestId' => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'messageId' => $messageId,
            ]);

            echo "Successfully sent email to {$customer['email']} (Customer: {$customerId}, MIME ID: {$messageId}).\n";
        } catch (Throwable $e) {
            // FAILED-save/rethrow pattern: preserve failure and rethrow so worker leaves message undeleted
            $processedAt = gmdate('Y-m-d H:i:s');
            $this->history->saveFailed(
                $campaignId,
                $customerId,
                $requestId,
                $promoCode,
                $customer['email'] ?? null,
                $subject,
                $e->getMessage(),
                $processedAt
            );

            $this->logger->record('processing_failed', [
                'requestId' => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
