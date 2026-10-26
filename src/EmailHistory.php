<?php
declare(strict_types=1);

namespace Lab;

use PDO;

final class EmailHistory
{
    public function __construct(private PDO $db)
    {
    }

    public function isComplete(string $campaignId, string $customerId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM sent_marketing_emails 
            WHERE campaign_id = :campaign_id 
              AND customer_id = :customer_id 
              AND status IN ('SENT', 'SKIPPED')
            LIMIT 1
        ");
        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':customer_id' => $customerId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function saveSent(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        string $recipientEmail,
        string $subject,
        string $provider,
        string $messageId,
        string $sentAt,
        string $processedAt
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO sent_marketing_emails (
                campaign_id, customer_id, request_id, promo_code, recipient_email,
                subject, status, provider, message_id, reason, processed_at, sent_at
            ) VALUES (
                :campaign_id, :customer_id, :request_id, :promo_code, :recipient_email,
                :subject, 'SENT', :provider, :message_id, NULL, :processed_at, :sent_at
            )
            ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'SENT',
                provider = VALUES(provider),
                message_id = VALUES(message_id),
                reason = NULL,
                processed_at = VALUES(processed_at),
                sent_at = VALUES(sent_at)
        ");

        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':customer_id' => $customerId,
            ':request_id' => $requestId,
            ':promo_code' => $promoCode,
            ':recipient_email' => $recipientEmail,
            ':subject' => $subject,
            ':provider' => $provider,
            ':message_id' => $messageId,
            ':processed_at' => $processedAt,
            ':sent_at' => $sentAt,
        ]);
    }

    public function saveSkipped(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        ?string $recipientEmail,
        string $subject,
        string $reason,
        string $processedAt
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO sent_marketing_emails (
                campaign_id, customer_id, request_id, promo_code, recipient_email,
                subject, status, provider, message_id, reason, processed_at, sent_at
            ) VALUES (
                :campaign_id, :customer_id, :request_id, :promo_code, :recipient_email,
                :subject, 'SKIPPED', NULL, NULL, :reason, :processed_at, NULL
            )
            ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'SKIPPED',
                provider = NULL,
                message_id = NULL,
                reason = VALUES(reason),
                processed_at = VALUES(processed_at),
                sent_at = NULL
        ");

        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':customer_id' => $customerId,
            ':request_id' => $requestId,
            ':promo_code' => $promoCode,
            ':recipient_email' => $recipientEmail,
            ':subject' => $subject,
            ':reason' => $reason,
            ':processed_at' => $processedAt,
        ]);
    }

    public function saveFailed(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        ?string $recipientEmail,
        string $subject,
        string $reason,
        string $processedAt
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO sent_marketing_emails (
                campaign_id, customer_id, request_id, promo_code, recipient_email,
                subject, status, provider, message_id, reason, processed_at, sent_at
            ) VALUES (
                :campaign_id, :customer_id, :request_id, :promo_code, :recipient_email,
                :subject, 'FAILED', NULL, NULL, :reason, :processed_at, NULL
            )
            ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = 'FAILED',
                provider = NULL,
                message_id = NULL,
                reason = VALUES(reason),
                processed_at = VALUES(processed_at),
                sent_at = NULL
        ");

        $stmt->execute([
            ':campaign_id' => $campaignId,
            ':customer_id' => $customerId,
            ':request_id' => $requestId,
            ':promo_code' => $promoCode,
            ':recipient_email' => $recipientEmail,
            ':subject' => $subject,
            ':reason' => $reason,
            ':processed_at' => $processedAt,
        ]);
    }
}
