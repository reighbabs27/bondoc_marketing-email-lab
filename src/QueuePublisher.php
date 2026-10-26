<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use Throwable;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): int
    {
        $selectStmt = $this->db->query("
            SELECT request_id, campaign_id, customer_id, promo_code, subject, body 
            FROM marketing_outbox 
            WHERE status = 'PENDING' 
            ORDER BY created_at ASC
        ");
        $pending = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pending)) {
            echo "No PENDING requests found to publish.\n";
            return 0;
        }

        echo "Found " . count($pending) . " PENDING requests to publish...\n";
        $publishedCount = 0;

        $updateStmt = $this->db->prepare("
            UPDATE marketing_outbox 
            SET status = 'QUEUED', queued_at = ?, sqs_message_id = ? 
            WHERE request_id = ?
        ");

        foreach ($pending as $row) {
            $payload = [
                'requestId' => $row['request_id'],
                'campaignId' => $row['campaign_id'],
                'customerId' => $row['customer_id'],
                'promoCode' => $row['promo_code'],
                'subject' => $row['subject'],
                'body' => $row['body'],
            ];

            try {
                // TODO Q1: Call the injected SQS client's sendMessage() method.
                // Supply QueueUrl and a JSON-encoded MessageBody, using JSON_THROW_ON_ERROR.
                $result = $this->sqs->sendMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
                ]);

                $sqsMessageId = (string) ($result['MessageId'] ?? '');

                // TODO Q2: Only after acknowledgment, execute the supplied UPDATE statement
                // with queued_at, the returned MessageId, and the local request_id.
                $queuedAt = gmdate('Y-m-d H:i:s');
                $updateStmt->execute([
                    $queuedAt,
                    $sqsMessageId,
                    $row['request_id'],
                ]);

                // TODO M1: After sending and updating the row, record a queued log event
                // with the request, campaign, customer, and SQS identifiers.
                $this->logger->record('queued', [
                    'requestId' => $row['request_id'],
                    'campaignId' => $row['campaign_id'],
                    'customerId' => $row['customer_id'],
                    'sqsMessageId' => $sqsMessageId,
                ]);

                $publishedCount++;
                echo "Published request {$row['request_id']} (Customer: {$row['customer_id']}, SQS Message ID: {$sqsMessageId})\n";
            } catch (Throwable $e) {
                $this->logger->record('enqueue_failed', [
                    'requestId' => $row['request_id'],
                    'campaignId' => $row['campaign_id'],
                    'customerId' => $row['customer_id'],
                    'error' => $e->getMessage(),
                ]);
                echo "Failed to publish request {$row['request_id']}: {$e->getMessage()}\n";
            }
        }

        echo "Finished publishing. Total sent: {$publishedCount}\n";
        return $publishedCount;
    }
}
