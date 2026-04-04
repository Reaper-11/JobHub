<?php

require_once __DIR__ . '/includes/mailer.php';

$mailStatus = jobhub_support_mail_status();
$targetEmail = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetEmail = trim((string) ($_POST['target_email'] ?? ''));
    $result = jobhub_send_email_message([
        'to_email' => $targetEmail,
        'to_name' => 'JobHub SMTP Test',
        'subject' => 'JobHub SMTP Test Email',
        'html_body' => '
            <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
                <h2 style="margin-bottom: 12px;">JobHub SMTP Test</h2>
                <p>This is a temporary local test email from your JobHub project.</p>
                <p>If you received this message, PHPMailer SMTP is configured correctly.</p>
            </div>
        ',
        'text_body' => "JobHub SMTP Test\n\nThis is a temporary local test email from your JobHub project.\nIf you received this message, PHPMailer SMTP is configured correctly.",
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobHub Mail Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            margin: 0;
            padding: 32px 16px;
        }

        .card {
            max-width: 680px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .alert {
            border-radius: 8px;
            padding: 12px 14px;
            margin-bottom: 16px;
        }

        .alert-success {
            background: #ecfdf3;
            color: #166534;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-sizing: border-box;
            margin-bottom: 14px;
        }

        button {
            background: #1d4ed8;
            color: #fff;
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            cursor: pointer;
        }

        code {
            background: #f1f5f9;
            padding: 2px 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>JobHub Mail Test</h1>
        <p>This is a temporary local SMTP test utility. Remove <code>test-mail.php</code> after you finish verifying email delivery.</p>

        <div class="alert alert-info">
            <strong>SMTP status:</strong> <?= htmlspecialchars($mailStatus['message']) ?>
        </div>

        <?php if ($result !== null): ?>
            <div class="alert <?= !empty($result['success']) ? 'alert-success' : 'alert-danger' ?>">
                <?= htmlspecialchars($result['message']) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <label for="target_email">Send test email to</label>
            <input
                type="text"
                id="target_email"
                name="target_email"
                required
                value="<?= htmlspecialchars($targetEmail) ?>"
                placeholder="one@example.com, two@example.com"
            >

            <button type="submit">Send Test Email</button>
        </form>
    </div>
</body>
</html>
