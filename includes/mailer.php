<?php
require_once __DIR__ . '/support_mail_config.php';

function jobhub_support_load_phpmailer(): bool
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $loaded = true;
        return true;
    }

    $baseDirs = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src',
        __DIR__ . '/../PHPMailer/src',
    ];

    foreach ($baseDirs as $baseDir) {
        $phpMailerFile = $baseDir . '/PHPMailer.php';
        $smtpFile = $baseDir . '/SMTP.php';
        $exceptionFile = $baseDir . '/Exception.php';

        if (is_file($phpMailerFile) && is_file($smtpFile) && is_file($exceptionFile)) {
            require_once $exceptionFile;
            require_once $phpMailerFile;
            require_once $smtpFile;
            break;
        }
    }

    $loaded = class_exists('\PHPMailer\PHPMailer\PHPMailer');
    return $loaded;
}

function jobhub_support_mail_status(): array
{
    $enabled = JOBHUB_SUPPORT_SMTP_ENABLED;
    $libraryLoaded = jobhub_support_load_phpmailer();
    $canSend = $enabled && $libraryLoaded;
    $message = 'SMTP reply email is disabled in support mail config.';

    if ($canSend) {
        $message = 'SMTP is configured and PHPMailer is available.';
    } elseif ($enabled && !$libraryLoaded) {
        $message = 'SMTP is enabled, but PHPMailer files were not found.';
    }

    return [
        'enabled' => $enabled,
        'library_loaded' => $libraryLoaded,
        'can_send' => $canSend,
        'message' => $message,
    ];
}

function jobhub_send_support_reply_email(string $toEmail, string $toName, string $subject, string $replyMessage): array
{
    $mailStatus = jobhub_support_mail_status();
    if (!$mailStatus['enabled']) {
        return [
            'success' => false,
            'message' => $mailStatus['message'],
        ];
    }

    if (!$mailStatus['library_loaded']) {
        return [
            'success' => false,
            'message' => $mailStatus['message'],
        ];
    }

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Recipient email is not valid.',
        ];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = JOBHUB_SUPPORT_SMTP_HOST;
        $mail->Port = (int)JOBHUB_SUPPORT_SMTP_PORT;
        $mail->SMTPAuth = true;
        $mail->Username = JOBHUB_SUPPORT_SMTP_USERNAME;
        $mail->Password = JOBHUB_SUPPORT_SMTP_PASSWORD;

        $secure = trim((string)JOBHUB_SUPPORT_SMTP_SECURE);
        if ($secure !== '') {
            $mail->SMTPSecure = $secure;
        }

        $mail->setFrom(JOBHUB_SUPPORT_FROM_EMAIL, JOBHUB_SUPPORT_FROM_NAME);
        $mail->addAddress($toEmail, $toName !== '' ? $toName : $toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $safeReply = nl2br(htmlspecialchars($replyMessage, ENT_QUOTES, 'UTF-8'));
        $mail->Body = "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.6; color: #222;\">
                <p>Hello " . htmlspecialchars($toName !== '' ? $toName : 'JobHub user', ENT_QUOTES, 'UTF-8') . ",</p>
                <p>Our support team has replied to your message:</p>
                <div style=\"padding: 14px; background: #f5f7ff; border-left: 4px solid #1a237e;\">
                    {$safeReply}
                </div>
                <p style=\"margin-top: 16px;\">Regards,<br>" . htmlspecialchars(JOBHUB_SUPPORT_FROM_NAME, ENT_QUOTES, 'UTF-8') . "</p>
            </div>
        ";
        $mail->AltBody = "Hello " . ($toName !== '' ? $toName : 'JobHub user') . ",\n\n"
            . "Our support team has replied to your message:\n\n"
            . $replyMessage . "\n\n"
            . "Regards,\n" . JOBHUB_SUPPORT_FROM_NAME;

        $mail->send();

        return [
            'success' => true,
            'message' => 'Reply email sent successfully.',
        ];
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => $e->getMessage(),
        ];
    }
}
