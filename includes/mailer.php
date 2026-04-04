<?php

require_once __DIR__ . '/support_mail_config.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

function jobhub_log_mail_error(string $context, string $detail): void
{
    error_log('[JobHub Mailer][' . $context . '] ' . $detail);
}

function jobhub_support_mail_config(): array
{
    return [
        'smtp_enabled' => (bool) JOBHUB_SUPPORT_SMTP_ENABLED,
        'host' => trim((string) JOBHUB_SUPPORT_SMTP_HOST),
        'port' => (int) JOBHUB_SUPPORT_SMTP_PORT,
        'secure' => strtolower(trim((string) JOBHUB_SUPPORT_SMTP_SECURE)),
        'username' => trim((string) JOBHUB_SUPPORT_SMTP_USERNAME),
        'password' => trim((string) JOBHUB_SUPPORT_SMTP_PASSWORD),
        'from_email' => trim((string) JOBHUB_SUPPORT_FROM_EMAIL),
        'from_name' => trim((string) JOBHUB_SUPPORT_FROM_NAME),
    ];
}

function jobhub_support_mail_has_placeholder(string $value): bool
{
    $value = strtolower(trim($value));
    if ($value === '') {
        return true;
    }

    $placeholderFragments = [
        'your-email@',
        'yourgmail@gmail.com',
        'replace-with-',
        'replace_with_my_real_app_password',
        'your-gmail-app-password',
        'your-16-char-app-password',
        'example.com',
        'example.org',
        'example.net',
        'example password',
        'smtp password',
    ];

    foreach ($placeholderFragments as $fragment) {
        if (str_contains($value, $fragment)) {
            return true;
        }
    }

    return false;
}

function jobhub_support_mail_config_valid(array $config, ?string &$reason = null): bool
{
    if (empty($config['smtp_enabled'])) {
        $reason = 'SMTP reply email is disabled in includes/support_mail_config.php.';
        return false;
    }

    if ($config['host'] === '') {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_SMTP_HOST is empty.';
        return false;
    }

    if ($config['port'] <= 0 || $config['port'] > 65535) {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_SMTP_PORT is invalid.';
        return false;
    }

    if (!in_array($config['secure'], ['', 'tls', 'ssl'], true)) {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_SMTP_SECURE must be tls, ssl, or empty.';
        return false;
    }

    if (jobhub_support_mail_has_placeholder($config['username'])) {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_SMTP_USERNAME still uses a placeholder value.';
        return false;
    }

    if (jobhub_support_mail_has_placeholder($config['password'])) {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_SMTP_PASSWORD still uses a placeholder value.';
        return false;
    }

    if (!filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) || jobhub_support_mail_has_placeholder($config['from_email'])) {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_FROM_EMAIL is not configured with a real email address.';
        return false;
    }

    if ($config['from_name'] === '') {
        $reason = 'SMTP is enabled, but JOBHUB_SUPPORT_FROM_NAME is empty.';
        return false;
    }

    $reason = 'SMTP is configured and PHPMailer is available.';
    return true;
}

function jobhub_support_load_phpmailer(): bool
{
    static $loaded = null;
    if ($loaded !== null) {
        return $loaded;
    }

    if (class_exists(PHPMailer::class)) {
        $loaded = true;
        return true;
    }

    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (is_file($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (!class_exists(PHPMailer::class)) {
        $baseDirs = [
            __DIR__ . '/../vendor/phpmailer/phpmailer/src',
            __DIR__ . '/../PHPMailer/src',
        ];

        foreach ($baseDirs as $baseDir) {
            $exceptionFile = $baseDir . '/Exception.php';
            $phpMailerFile = $baseDir . '/PHPMailer.php';
            $smtpFile = $baseDir . '/SMTP.php';

            if (is_file($exceptionFile) && is_file($phpMailerFile) && is_file($smtpFile)) {
                require_once $exceptionFile;
                require_once $phpMailerFile;
                require_once $smtpFile;
                break;
            }
        }
    }

    $loaded = class_exists(PHPMailer::class);
    if (!$loaded) {
        jobhub_log_mail_error('bootstrap', 'PHPMailer could not be loaded from vendor/autoload.php or fallback source files.');
    }

    return $loaded;
}

function jobhub_support_mail_status(): array
{
    $config = jobhub_support_mail_config();
    $enabled = $config['smtp_enabled'];
    $libraryLoaded = jobhub_support_load_phpmailer();
    $configValid = false;
    $message = 'SMTP reply email is disabled in includes/support_mail_config.php.';

    if ($enabled) {
        $configValid = jobhub_support_mail_config_valid($config, $message);
    }

    if ($enabled && !$libraryLoaded) {
        $message = 'SMTP is enabled, but PHPMailer could not be loaded from Composer autoload.';
    } elseif ($enabled && $libraryLoaded && $configValid) {
        $message = 'SMTP is configured and ready to send.';
    }

    return [
        'enabled' => $enabled,
        'library_loaded' => $libraryLoaded,
        'config_valid' => $configValid,
        'can_send' => $enabled && $libraryLoaded && $configValid,
        'message' => $message,
        'config' => $config,
    ];
}

function jobhub_mail_encryption_mode(string $secure): string
{
    return match (strtolower(trim($secure))) {
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        'ssl' => PHPMailer::ENCRYPTION_SMTPS,
        default => '',
    };
}

function jobhub_mail_html_to_text(string $html): string
{
    $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $html);
    $text = preg_replace('/<\s*\/p\s*>/i', "\n\n", (string) $text);
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\n{3,}/", "\n\n", (string) $text);

    return trim((string) $text);
}

function jobhub_mail_split_addresses(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[;,]+/', $value) ?: [];
    $addresses = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $addresses[] = $part;
        }
    }

    return $addresses;
}

function jobhub_mail_normalize_recipients(mixed $value, mixed $names = null): array
{
    $recipients = [];

    if (is_array($value)) {
        foreach ($value as $index => $item) {
            if (is_array($item)) {
                $email = trim((string) ($item['email'] ?? $item['address'] ?? ''));
                $name = trim((string) ($item['name'] ?? ''));
            } else {
                $email = trim((string) $item);
                $name = is_array($names) && array_key_exists($index, $names)
                    ? trim((string) $names[$index])
                    : (is_string($names) ? trim($names) : '');
            }

            foreach (jobhub_mail_split_addresses($email) as $splitEmail) {
                $recipients[] = [
                    'email' => strtolower(trim((string) $splitEmail)),
                    'name' => $name,
                ];
            }
        }

        return $recipients;
    }

    $emails = jobhub_mail_split_addresses($value);
    foreach ($emails as $index => $email) {
        $name = is_array($names) && array_key_exists($index, $names)
            ? trim((string) $names[$index])
            : (is_string($names) ? trim($names) : '');

        $recipients[] = [
            'email' => strtolower(trim((string) $email)),
            'name' => $name,
        ];
    }

    return $recipients;
}

function jobhub_mail_find_invalid_recipient(array $recipients): ?string
{
    foreach ($recipients as $recipient) {
        $email = trim((string) ($recipient['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
    }

    return null;
}

function jobhub_mail_add_recipients(PHPMailer $mail, array $recipients, string $type): void
{
    foreach ($recipients as $recipient) {
        $email = (string) ($recipient['email'] ?? '');
        $name = trim((string) ($recipient['name'] ?? ''));

        match ($type) {
            'cc' => $mail->addCC($email, $name),
            'bcc' => $mail->addBCC($email, $name),
            default => $mail->addAddress($email, $name),
        };
    }
}

function jobhub_mail_recipient_summary(array $recipients): string
{
    return implode(', ', array_map(
        static fn(array $recipient): string => (string) ($recipient['email'] ?? ''),
        $recipients
    ));
}

function jobhub_send_email_message(array $message): array
{
    $mailStatus = jobhub_support_mail_status();
    if (!$mailStatus['can_send']) {
        return [
            'success' => false,
            'message' => $mailStatus['message'],
        ];
    }

    $config = $mailStatus['config'];
    $toRecipients = jobhub_mail_normalize_recipients(
        $message['to'] ?? ($message['to_email'] ?? []),
        $message['to_name'] ?? null
    );
    $ccRecipients = jobhub_mail_normalize_recipients(
        $message['cc'] ?? ($message['cc_email'] ?? []),
        $message['cc_name'] ?? null
    );
    $bccRecipients = jobhub_mail_normalize_recipients(
        $message['bcc'] ?? ($message['bcc_email'] ?? []),
        $message['bcc_name'] ?? null
    );
    $subject = trim((string) ($message['subject'] ?? ''));
    $htmlBody = (string) ($message['html_body'] ?? '');
    $textBody = trim((string) ($message['text_body'] ?? ''));
    $fromEmail = trim((string) ($message['from_email'] ?? $config['from_email']));
    $fromName = trim((string) ($message['from_name'] ?? $config['from_name']));
    $replyToEmail = trim((string) ($message['reply_to_email'] ?? ''));
    $replyToName = trim((string) ($message['reply_to_name'] ?? ''));

    if ($toRecipients === []) {
        return [
            'success' => false,
            'message' => 'At least one recipient email address is required.',
        ];
    }

    $invalidToRecipient = jobhub_mail_find_invalid_recipient($toRecipients);
    if ($invalidToRecipient !== null) {
        return [
            'success' => false,
            'message' => 'Recipient email address is invalid: ' . $invalidToRecipient,
        ];
    }

    $invalidCcRecipient = jobhub_mail_find_invalid_recipient($ccRecipients);
    if ($invalidCcRecipient !== null) {
        return [
            'success' => false,
            'message' => 'CC email address is invalid: ' . $invalidCcRecipient,
        ];
    }

    $invalidBccRecipient = jobhub_mail_find_invalid_recipient($bccRecipients);
    if ($invalidBccRecipient !== null) {
        return [
            'success' => false,
            'message' => 'BCC email address is invalid: ' . $invalidBccRecipient,
        ];
    }

    if ($subject === '') {
        return [
            'success' => false,
            'message' => 'Email subject is required.',
        ];
    }

    if ($htmlBody === '' && $textBody === '') {
        return [
            'success' => false,
            'message' => 'Email body is required.',
        ];
    }

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Sender email address is invalid.',
        ];
    }

    if ($htmlBody === '' && $textBody !== '') {
        $htmlBody = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">'
            . nl2br(htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8'))
            . '</div>';
    }

    if ($textBody === '') {
        $textBody = jobhub_mail_html_to_text($htmlBody);
    }

    try {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Timeout = 20;
        $mail->isSMTP();
        $mail->Host = $config['host'];
        $mail->Port = $config['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        $encryption = jobhub_mail_encryption_mode($config['secure']);
        if ($encryption !== '') {
            $mail->SMTPSecure = $encryption;
        }

        $mail->setFrom($fromEmail, $fromName);
        if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
        }

        jobhub_mail_add_recipients($mail, $toRecipients, 'to');
        jobhub_mail_add_recipients($mail, $ccRecipients, 'cc');
        jobhub_mail_add_recipients($mail, $bccRecipients, 'bcc');
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();

        return [
            'success' => true,
            'message' => 'Email sent successfully.',
        ];
    } catch (PHPMailerException $e) {
        $recipientSummary = jobhub_mail_recipient_summary(array_merge($toRecipients, $ccRecipients, $bccRecipients));
        jobhub_log_mail_error(
            'send',
            'PHPMailer send failed for ' . $recipientSummary . ' with subject "' . $subject . '": ' . $e->getMessage()
        );

        return [
            'success' => false,
            'message' => 'Email could not be sent. Check the SMTP settings and PHP error log.',
        ];
    } catch (\Throwable $e) {
        $recipientSummary = jobhub_mail_recipient_summary(array_merge($toRecipients, $ccRecipients, $bccRecipients));
        jobhub_log_mail_error(
            'send',
            'Unexpected mail send failure for ' . $recipientSummary . ' with subject "' . $subject . '": ' . $e->getMessage()
        );

        return [
            'success' => false,
            'message' => 'Email could not be sent. Check the SMTP settings and PHP error log.',
        ];
    }
}

function jobhub_mail_recipient_name(string $name, string $fallback): string
{
    $name = trim($name);
    return $name !== '' ? $name : $fallback;
}

function jobhub_mail_safe_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jobhub_mail_safe_multiline_html(string $value): string
{
    return nl2br(jobhub_mail_safe_html($value));
}

function jobhub_mail_signature_html(): string
{
    return jobhub_mail_safe_html(JOBHUB_SUPPORT_FROM_NAME);
}

function jobhub_mail_signature_text(): string
{
    return JOBHUB_SUPPORT_FROM_NAME;
}

function jobhub_mail_wrap_html(string $content): string
{
    return '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
            ' . $content . '
            <p style="margin-top: 16px;">Regards,<br>' . jobhub_mail_signature_html() . '</p>
        </div>
    ';
}

function jobhub_mail_optional_html_block(string $label, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return '<p><strong>' . jobhub_mail_safe_html($label) . ':</strong><br>' . jobhub_mail_safe_multiline_html($value) . '</p>';
}

function jobhub_mail_optional_text_block(string $label, string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return "\n\n{$label}:\n{$value}";
}

function jobhub_send_account_created_email(string $toEmail, string $toName, string $accountType): array
{
    $accountType = strtolower(trim($accountType));
    if (!in_array($accountType, ['jobseeker', 'company'], true)) {
        return [
            'success' => false,
            'message' => 'Invalid account type supplied for account created email.',
        ];
    }

    $recipientName = jobhub_mail_recipient_name(
        $toName,
        $accountType === 'company' ? 'JobHub company' : 'JobHub member'
    );
    $safeName = jobhub_mail_safe_html($recipientName);

    if ($accountType === 'company') {
        $subject = 'JobHub Company Account Created';
        $htmlBody = jobhub_mail_wrap_html(
            '<p>Hello ' . $safeName . ',</p>
            <p>Your JobHub company account has been created successfully.</p>
            <p>Your company account may still require admin approval and/or company verification before jobs can be posted publicly.</p>
            <p>You can sign in to complete your company profile and submit any required verification details.</p>'
        );
        $textBody = "Hello {$recipientName},\n\n"
            . "Your JobHub company account has been created successfully.\n\n"
            . "Your company account may still require admin approval and/or company verification before jobs can be posted publicly.\n\n"
            . "You can sign in to complete your company profile and submit any required verification details.\n\n"
            . "Regards,\n" . jobhub_mail_signature_text();
    } else {
        $subject = 'JobHub Account Created';
        $htmlBody = jobhub_mail_wrap_html(
            '<p>Hello ' . $safeName . ',</p>
            <p>Your JobHub account has been created successfully.</p>
            <p>You can now sign in to complete your profile, upload your CV, and start applying for jobs.</p>'
        );
        $textBody = "Hello {$recipientName},\n\n"
            . "Your JobHub account has been created successfully.\n\n"
            . "You can now sign in to complete your profile, upload your CV, and start applying for jobs.\n\n"
            . "Regards,\n" . jobhub_mail_signature_text();
    }

    try {
        return jobhub_send_email_message([
            'to_email' => $toEmail,
            'to_name' => $recipientName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => 'Account created email could not be prepared: ' . $e->getMessage(),
        ];
    }
}

function jobhub_send_company_approval_email(
    string $toEmail,
    string $toName,
    string $companyName,
    string $action,
    string $remarks = ''
): array {
    $action = strtolower(trim($action));
    $config = match ($action) {
        'approved' => [
            'subject' => 'JobHub Company Account Approved',
            'summary' => 'Your company account has been approved by JobHub.',
            'details' => 'You can now access your company dashboard and manage job postings, subject to any verification requirements.',
        ],
        'rejected' => [
            'subject' => 'JobHub Company Account Rejected',
            'summary' => 'Your company account has been rejected by JobHub.',
            'details' => 'Please review the remarks below and update your information before contacting support or requesting another review.',
        ],
        'unapproved' => [
            'subject' => 'JobHub Company Approval Removed',
            'summary' => 'Your company account approval has been removed.',
            'details' => 'Public job posting may be unavailable until your account is approved again.',
        ],
        'hold' => [
            'subject' => 'JobHub Company Account On Hold',
            'summary' => 'Your company account has been placed on hold.',
            'details' => 'Please review the remarks below. Some company actions may be temporarily restricted.',
        ],
        'suspended' => [
            'subject' => 'JobHub Company Account Suspended',
            'summary' => 'Your company account has been suspended.',
            'details' => 'Please review the remarks below. Access or job posting may remain restricted until admin review is complete.',
        ],
        'activated' => [
            'subject' => 'JobHub Company Account Activated',
            'summary' => 'Your company account has been activated again.',
            'details' => 'You can continue using your company account on JobHub.',
        ],
        default => null,
    };

    if ($config === null) {
        return [
            'success' => false,
            'message' => 'Invalid company approval action supplied for email notification.',
        ];
    }

    $recipientName = jobhub_mail_recipient_name($toName, 'Company representative');
    $companyName = trim($companyName) !== '' ? trim($companyName) : 'your company';
    $safeName = jobhub_mail_safe_html($recipientName);
    $safeCompanyName = jobhub_mail_safe_html($companyName);
    $remarks = trim($remarks);

    $htmlBody = jobhub_mail_wrap_html(
        '<p>Hello ' . $safeName . ',</p>
        <p>' . jobhub_mail_safe_html($config['summary']) . '</p>
        <p><strong>Company:</strong> ' . $safeCompanyName . '</p>
        <p>' . jobhub_mail_safe_html($config['details']) . '</p>'
        . jobhub_mail_optional_html_block('Remarks', $remarks)
    );

    $textBody = "Hello {$recipientName},\n\n"
        . $config['summary'] . "\n\n"
        . "Company: {$companyName}\n\n"
        . $config['details']
        . jobhub_mail_optional_text_block('Remarks', $remarks)
        . "\n\nRegards,\n" . jobhub_mail_signature_text();

    try {
        return jobhub_send_email_message([
            'to_email' => $toEmail,
            'to_name' => $recipientName,
            'subject' => $config['subject'],
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => 'Company account review email could not be prepared: ' . $e->getMessage(),
        ];
    }
}

function jobhub_send_company_verification_email(
    string $toEmail,
    string $toName,
    string $companyName,
    string $status,
    string $remarks = ''
): array {
    $status = strtolower(trim($status));
    $config = match ($status) {
        'approved' => [
            'subject' => 'JobHub Company Verification Approved',
            'summary' => 'Your company verification request has been approved.',
            'details' => 'Your verification result is now marked as approved on JobHub.',
        ],
        'rejected' => [
            'subject' => 'JobHub Company Verification Rejected',
            'summary' => 'Your company verification request has been rejected.',
            'details' => 'Please review the remarks below and update your verification details before resubmitting.',
        ],
        default => null,
    };

    if ($config === null) {
        return [
            'success' => false,
            'message' => 'Invalid company verification status supplied for email notification.',
        ];
    }

    $recipientName = jobhub_mail_recipient_name($toName, 'Company representative');
    $companyName = trim($companyName) !== '' ? trim($companyName) : 'your company';
    $safeName = jobhub_mail_safe_html($recipientName);
    $safeCompanyName = jobhub_mail_safe_html($companyName);
    $remarks = trim($remarks);

    $htmlBody = jobhub_mail_wrap_html(
        '<p>Hello ' . $safeName . ',</p>
        <p>' . jobhub_mail_safe_html($config['summary']) . '</p>
        <p><strong>Company:</strong> ' . $safeCompanyName . '</p>
        <p>' . jobhub_mail_safe_html($config['details']) . '</p>'
        . jobhub_mail_optional_html_block('Remarks', $remarks)
    );

    $textBody = "Hello {$recipientName},\n\n"
        . $config['summary'] . "\n\n"
        . "Company: {$companyName}\n\n"
        . $config['details']
        . jobhub_mail_optional_text_block('Remarks', $remarks)
        . "\n\nRegards,\n" . jobhub_mail_signature_text();

    try {
        return jobhub_send_email_message([
            'to_email' => $toEmail,
            'to_name' => $recipientName,
            'subject' => $config['subject'],
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => 'Company verification email could not be prepared: ' . $e->getMessage(),
        ];
    }
}

function jobhub_send_account_removed_email(
    string $toEmail,
    string $toName,
    string $accountType,
    string $remarks = ''
): array {
    $accountType = strtolower(trim($accountType));
    if (!in_array($accountType, ['jobseeker', 'company'], true)) {
        return [
            'success' => false,
            'message' => 'Invalid account type supplied for account removal email.',
        ];
    }

    $recipientName = jobhub_mail_recipient_name(
        $toName,
        $accountType === 'company' ? 'Company representative' : 'JobHub member'
    );
    $remarks = trim($remarks);
    $safeName = jobhub_mail_safe_html($recipientName);

    if ($accountType === 'company') {
        $subject = 'JobHub Company Account Removed';
        $summary = 'Your JobHub company account has been removed.';
        $details = 'Access to the company dashboard and public job posting tools is no longer available for this account.';
    } else {
        $subject = 'JobHub Account Removed';
        $summary = 'Your JobHub account has been removed.';
        $details = 'You can no longer sign in or use job seeker features with this account.';
    }

    $htmlBody = jobhub_mail_wrap_html(
        '<p>Hello ' . $safeName . ',</p>
        <p>' . jobhub_mail_safe_html($summary) . '</p>
        <p>' . jobhub_mail_safe_html($details) . '</p>'
        . jobhub_mail_optional_html_block('Remarks', $remarks)
    );

    $textBody = "Hello {$recipientName},\n\n"
        . $summary . "\n\n"
        . $details
        . jobhub_mail_optional_text_block('Remarks', $remarks)
        . "\n\nRegards,\n" . jobhub_mail_signature_text();

    try {
        return jobhub_send_email_message([
            'to_email' => $toEmail,
            'to_name' => $recipientName,
            'subject' => $subject,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    } catch (\Throwable $e) {
        jobhub_log_mail_error(
            'account-removed',
            'Account removal email could not be prepared for ' . $toEmail . ': ' . $e->getMessage()
        );

        return [
            'success' => false,
            'message' => 'Account removal email could not be prepared: ' . $e->getMessage(),
        ];
    }
}

function jobhub_send_job_review_email(
    string $toEmail,
    string $toName,
    string $companyName,
    string $jobTitle,
    string $action,
    string $remarks = ''
): array {
    $action = strtolower(trim($action));
    $config = match ($action) {
        'approved' => [
            'subject_prefix' => 'JobHub Job Approved',
            'summary' => 'Your job posting has been approved by JobHub.',
            'details' => 'If the job is still within its application window, it is now visible to job seekers on JobHub.',
        ],
        'rejected' => [
            'subject_prefix' => 'JobHub Job Rejected',
            'summary' => 'Your job posting has been rejected during JobHub review.',
            'details' => 'Please review the remarks below, update the listing, and resubmit when ready.',
        ],
        default => null,
    };

    if ($config === null) {
        return [
            'success' => false,
            'message' => 'Invalid job review action supplied for email notification.',
        ];
    }

    $recipientName = jobhub_mail_recipient_name($toName, 'Company representative');
    $companyName = trim($companyName) !== '' ? trim($companyName) : 'your company';
    $jobTitle = trim($jobTitle) !== '' ? trim($jobTitle) : 'Untitled job';
    $remarks = trim($remarks);
    $safeName = jobhub_mail_safe_html($recipientName);
    $safeCompanyName = jobhub_mail_safe_html($companyName);
    $safeJobTitle = jobhub_mail_safe_html($jobTitle);

    $htmlBody = jobhub_mail_wrap_html(
        '<p>Hello ' . $safeName . ',</p>
        <p>' . jobhub_mail_safe_html($config['summary']) . '</p>
        <p><strong>Company:</strong> ' . $safeCompanyName . '<br><strong>Job Title:</strong> ' . $safeJobTitle . '</p>
        <p>' . jobhub_mail_safe_html($config['details']) . '</p>'
        . jobhub_mail_optional_html_block('Remarks', $remarks)
    );

    $textBody = "Hello {$recipientName},\n\n"
        . $config['summary'] . "\n\n"
        . "Company: {$companyName}\n"
        . "Job Title: {$jobTitle}\n\n"
        . $config['details']
        . jobhub_mail_optional_text_block('Remarks', $remarks)
        . "\n\nRegards,\n" . jobhub_mail_signature_text();

    try {
        return jobhub_send_email_message([
            'to_email' => $toEmail,
            'to_name' => $recipientName,
            'subject' => $config['subject_prefix'] . ' - ' . $jobTitle,
            'html_body' => $htmlBody,
            'text_body' => $textBody,
        ]);
    } catch (\Throwable $e) {
        return [
            'success' => false,
            'message' => 'Job review email could not be prepared: ' . $e->getMessage(),
        ];
    }
}

function jobhub_send_support_reply_email(string $toEmail, string $toName, string $subject, string $replyMessage): array
{
    $recipientName = trim($toName) !== '' ? trim($toName) : 'JobHub user';
    $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safeReply = nl2br(htmlspecialchars($replyMessage, ENT_QUOTES, 'UTF-8'));
    $safeFromName = htmlspecialchars(JOBHUB_SUPPORT_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $safeSubject = trim($subject) !== '' ? trim($subject) : 'JobHub Support Reply';

    $htmlBody = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
            <p>Hello ' . $safeName . ',</p>
            <p>Our support team has replied to your message.</p>
            <div style="padding: 14px; background: #f5f7ff; border-left: 4px solid #1a237e;">
                ' . $safeReply . '
            </div>
            <p style="margin-top: 16px;">Regards,<br>' . $safeFromName . '</p>
        </div>
    ';

    $textBody = "Hello {$recipientName},\n\n"
        . "Our support team has replied to your message.\n\n"
        . $replyMessage . "\n\n"
        . "Regards,\n" . JOBHUB_SUPPORT_FROM_NAME;

    return jobhub_send_email_message([
        'to_email' => $toEmail,
        'to_name' => $recipientName,
        'subject' => $safeSubject,
        'html_body' => $htmlBody,
        'text_body' => $textBody,
    ]);
}

function jobhub_send_application_status_update_email(
    string $toEmail,
    string $toName,
    string $jobTitle,
    string $status,
    string $responseMessage
): array {
    $recipientName = trim($toName) !== '' ? trim($toName) : 'JobHub applicant';
    $jobTitle = trim($jobTitle) !== '' ? trim($jobTitle) : 'Job Application';
    $statusLabel = ucwords(str_replace('_', ' ', strtolower(trim($status))));
    $responseMessage = trim($responseMessage);

    if ($responseMessage === '') {
        return [
            'success' => false,
            'message' => 'Application status email requires a response message.',
        ];
    }

    $safeName = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $safeJobTitle = htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8');
    $safeStatus = htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8');
    $safeResponse = nl2br(htmlspecialchars($responseMessage, ENT_QUOTES, 'UTF-8'));
    $safeFromName = htmlspecialchars(JOBHUB_SUPPORT_FROM_NAME, ENT_QUOTES, 'UTF-8');
    $subject = 'Application Update - ' . $jobTitle;

    $htmlBody = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #222;">
            <p>Hello ' . $safeName . ',</p>
            <p>Your application status has been updated.</p>
            <p><strong>Status:</strong> ' . $safeStatus . '</p>
            <p><strong>Message from Company:</strong><br>' . $safeResponse . '</p>
            <p>Please login to JobHub for more details.</p>
            <p>Regards,<br>' . $safeFromName . '</p>
        </div>
    ';

    $textBody = "Hello {$recipientName},\n\n"
        . "Your application status has been updated.\n\n"
        . "Status: {$statusLabel}\n\n"
        . "Message from Company:\n{$responseMessage}\n\n"
        . "Please login to JobHub for more details.\n\n"
        . "Regards,\n" . JOBHUB_SUPPORT_FROM_NAME;

    return jobhub_send_email_message([
        'to_email' => $toEmail,
        'to_name' => $recipientName,
        'subject' => $subject,
        'html_body' => $htmlBody,
        'text_body' => $textBody,
    ]);
}
