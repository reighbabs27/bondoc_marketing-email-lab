# Hybrid Promotional Campaign Laboratory

**Systems Administration and Maintenance**  
**Student Name:** Reighfer Bondoc  
**Student ID:** 19-1290-764  

---

## Architecture Overview

This project implements an enterprise-grade hybrid message queue and email promotional workflow:
1. **Local MySQL Database (`marketing_lab`)**:
   - `customers`: Stores 10,000 fictional customer profiles generated with Faker (8,000 opted in, 2,000 opted out).
   - `marketing_outbox`: Stages campaign promotional offers locally (`PENDING` -> `QUEUED`) with persistent 32-character hexadecimal request IDs.
   - `sent_marketing_emails`: Audit history recording terminal outcomes (`SENT`, `SKIPPED`, `FAILED`), MIME Message-IDs, and UTC timestamps.
2. **Amazon Simple Queue Service (SQS)**:
   - **Main Queue (`MarketingEmailQueue-s19-1290-764`)**: Receives decoupled email delivery jobs with 180s visibility timeout, 4-day retention, and 20s receive wait time.
   - **Dead-Letter Queue (`MarketingEmailDLQ-s19-1290-764`)**: Captures repeated processing failures after 3 unsuccessful attempts (`maxReceiveCount = 3`) with 14-day retention.
   - **Restricted IAM Policy (`MarketingLabSQS-s19-1290-764`)**: Restricts the worker identity to queue actions only (`sqs:SendMessage`, `sqs:ReceiveMessage`, `sqs:DeleteMessage`, `sqs:GetQueueAttributes`, `sqs:GetQueueUrl`).
3. **Mailtrap Email Sandbox**:
   - Safely captures outbound promotional emails via SMTP with TLS and rate limiting.
4. **Structured Operational Logging**:
   - Events are written as JSON lines to `logs/events.jsonl` tracking `queued`, `received`, `processing_started`, `sent`, `skipped`, `deleted`, `processing_failed`, and `ack_failed`.

---

## Installation & Setup

### 1. Requirements
- PHP 8.2+ with `pdo_mysql`, `OpenSSL`, and `mbstring` extensions enabled.
- Composer.
- MySQL 8.0+ / MariaDB 10.4+.
- AWS CLI v2.

### 2. Install Dependencies
```bash
composer install
composer dump-autoload
```

### 3. Environment Configuration
Create a `.env` file or export environment variables:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=marketing_lab
DB_USER=root
DB_PASSWORD=

AWS_REGION=us-east-1
AWS_PROFILE=marketing-lab
SQS_QUEUE_URL=https://sqs.us-east-1.amazonaws.com/408852558600/MarketingEmailQueue-s19-1290-764

MAILTRAP_HOST=sandbox.smtp.mailtrap.io
MAILTRAP_PORT=2525
MAILTRAP_USERNAME=YOUR_MAILTRAP_USERNAME
MAILTRAP_PASSWORD=YOUR_MAILTRAP_PASSWORD
MAIL_FROM_ADDRESS=marketing@example.com
MAILTRAP_DELAY_SECONDS=11
```

### 4. Database Setup & Seeding
```bash
# Initialize schema
php init-db.php

# Seed 10,000 customers (8,000 opted in, 2,000 opted out)
php seed-customers.php

# Inspect database
php inspect.php
```

### 5. Running Campaign Workflow
```bash
# Prepare 50 random recipients
php prepare-campaign.php CAMP-001 PROMO15

# Publish jobs to AWS SQS
php enqueue.php

# Process queue and send emails via Mailtrap
php worker.php

# Verify results
php inspect.php
```
