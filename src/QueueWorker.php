<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use Throwable;

final class QueueWorker
{
    private bool $running = true;

    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {}

    public function stop(): void
    {
        $this->running = false;
    }

    public function run(bool $continuous = true, int $maxEmptyPolls = 3): void
    {
        echo "Worker started. Listening for messages on: {$this->queueUrl}...\n";
        $emptyPolls = 0;

        while ($this->running) {
            try {
                $result = $this->sqs->receiveMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MaxNumberOfMessages' => 10,
                    'WaitTimeSeconds' => 20,
                    'VisibilityTimeout' => 180,
                ]);

                $messages = $result->get('Messages');

                if (empty($messages)) {
                    $emptyPolls++;
                    echo "Queue empty (poll {$emptyPolls})...\n";
                    if (!$continuous && $emptyPolls >= $maxEmptyPolls) {
                        echo "Worker finished batch processing.\n";
                        break;
                    }
                    continue;
                }

                $emptyPolls = 0;

                foreach ($messages as $message) {
                    $sqsMessageId = (string) ($message['MessageId'] ?? '');
                    $receiptHandle = (string) ($message['ReceiptHandle'] ?? '');

                    // Decode message body
                    $job = null;
                    try {
                        $job = json_decode((string) $message['Body'], true, 512, JSON_THROW_ON_ERROR);
                    } catch (Throwable $e) {
                        $this->logger->record('received', ['sqsMessageId' => $sqsMessageId]);
                        $this->logger->record('processing_failed', [
                            'sqsMessageId' => $sqsMessageId,
                            'error' => 'Malformed JSON payload: ' . $e->getMessage(),
                            'phase' => 'decode',
                        ]);
                        echo "Malformed JSON payload for message {$sqsMessageId}. Leaving message in queue.\n";
                        continue;
                    }

                    // Record received event with available job identifiers
                    $this->logger->record('received', [
                        'sqsMessageId' => $sqsMessageId,
                        'requestId' => $job['requestId'] ?? null,
                        'campaignId' => $job['campaignId'] ?? null,
                        'customerId' => $job['customerId'] ?? null,
                    ]);

                    // Handle message
                    $this->handle($message, $job);
                }
            } catch (Throwable $e) {
                echo "Worker error: {$e->getMessage()}\n";
                sleep(2);
            }
        }
    }

    public function handle(array $message, array $job): void
    {
        $sqsMessageId = (string) ($message['MessageId'] ?? '');
        $receiptHandle = (string) ($message['ReceiptHandle'] ?? '');

        try {
            // TODO W1: Invoke the injected processor with this job.
            $this->processor->process($job);

            // TODO M3: Change the operation phase to 'delete' before acknowledgment.
            $phase = 'delete';

            // TODO W2: Delete the SQS message using the queue URL and ReceiptHandle.
            $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);

            // TODO M3: Log 'deleted' only after DeleteMessage succeeds.
            $this->logger->record('deleted', [
                'sqsMessageId' => $sqsMessageId,
                'requestId' => $job['requestId'] ?? null,
                'campaignId' => $job['campaignId'] ?? null,
                'customerId' => $job['customerId'] ?? null,
            ]);

            echo "Acknowledged and deleted SQS message: {$sqsMessageId}\n";
        } catch (Throwable $e) {
            if (isset($phase) && $phase === 'delete') {
                $this->logger->record('ack_failed', [
                    'sqsMessageId' => $sqsMessageId,
                    'error' => $e->getMessage(),
                ]);
                echo "Acknowledgment failed for SQS message {$sqsMessageId}: {$e->getMessage()}\n";
            } else {
                $this->logger->record('processing_failed', [
                    'sqsMessageId' => $sqsMessageId,
                    'requestId' => $job['requestId'] ?? null,
                    'campaignId' => $job['campaignId'] ?? null,
                    'customerId' => $job['customerId'] ?? null,
                    'error' => $e->getMessage(),
                    'phase' => 'process',
                ]);
                echo "Processing failed for SQS message {$sqsMessageId}: {$e->getMessage()}. Leaving message undeleted.\n";
            }
        }
    }
}
