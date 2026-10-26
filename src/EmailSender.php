<?php
declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    public function __construct(private array $settings)
    {
    }

    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->settings['host'] ?? 'sandbox.smtp.mailtrap.io';
        $mail->Port = (int) ($this->settings['port'] ?? 2525);
        $mail->SMTPAuth = true;
        $mail->Username = $this->settings['username'] ?? '';
        $mail->Password = $this->settings['password'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet = 'UTF-8';

        $fromAddress = $this->settings['from_address'] ?? 'marketing@example.com';
        $mail->setFrom($fromAddress, 'Marketing Campaign');

        // TODO E1: Add the customer's email and name as the recipient.
        $mail->addAddress($customer['email'], $customer['full_name']);

        // TODO E2: Set the campaign subject and a personalized plain-text body that clearly displays the persisted promoCode from the job.
        $mail->Subject = $job['subject'];
        $mail->isHTML(false);
        $mail->Body = sprintf(
            "Hello %s,\n\n%s\n\nYour promotional code is: %s\n\nBest regards,\nMarketing Team",
            $customer['full_name'],
            $job['body'],
            $job['promoCode']
        );

        $mail->send();

        // Keep the provided send call and Message-ID return value.
        // Do not catch and suppress SMTP exceptions here; the processor must know when sending fails.
        return $mail->getLastMessageID();
    }
}
