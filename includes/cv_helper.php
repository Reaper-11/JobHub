<?php

if (!defined('JOBHUB_CV_DIR')) {
    define('JOBHUB_CV_DIR', __DIR__ . '/../uploads/cv');
}

if (!defined('JOBHUB_CV_RELATIVE_DIR')) {
    define('JOBHUB_CV_RELATIVE_DIR', 'uploads/cv/');
}

if (!defined('JOBHUB_CV_MAX_SIZE')) {
    define('JOBHUB_CV_MAX_SIZE', 5 * 1024 * 1024);
}

function jobhub_cv_allowed_types(): array
{
    return [
        'pdf' => ['application/pdf', 'application/x-pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-word', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    ];
}

function jobhub_cv_is_stored_path(?string $path): bool
{
    if (!is_string($path) || $path === '') {
        return false;
    }

    if (strpos($path, JOBHUB_CV_RELATIVE_DIR) !== 0) {
        return false;
    }

    $fileName = basename($path);
    return $fileName !== '' && $fileName === substr($path, strlen(JOBHUB_CV_RELATIVE_DIR));
}

function jobhub_cv_absolute_path(string $path): ?string
{
    if (!jobhub_cv_is_stored_path($path)) {
        return null;
    }

    return JOBHUB_CV_DIR . '/' . basename($path);
}

function jobhub_cv_file_name(?string $path): string
{
    return $path ? basename($path) : '';
}

function jobhub_cv_upload(array $file, int $userId, ?string &$error = null): ?string
{
    $error = null;

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadErrorMap = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server limit.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form limit.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload folder is missing.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload.',
            UPLOAD_ERR_NO_FILE => 'Please choose a CV file to upload.',
        ];
        $error = $uploadErrorMap[(int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)] ?? 'Could not upload the selected CV.';
        return null;
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Invalid upload request.';
        return null;
    }

    $fileSize = (int) ($file['size'] ?? 0);
    if ($fileSize <= 0 || $fileSize > JOBHUB_CV_MAX_SIZE) {
        $error = 'CV must be 5MB or smaller.';
        return null;
    }

    $originalName = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedTypes = jobhub_cv_allowed_types();
    if (!isset($allowedTypes[$extension])) {
        $error = 'Only PDF, DOC, and DOCX files are allowed.';
        return null;
    }

    $mimeType = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mimeType = (string) finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    if ($mimeType !== '' && !in_array($mimeType, $allowedTypes[$extension], true)) {
        $error = 'The uploaded file type is not allowed.';
        return null;
    }

    if (!is_dir(JOBHUB_CV_DIR) && !mkdir(JOBHUB_CV_DIR, 0755, true)) {
        $error = 'Upload folder is not available.';
        return null;
    }

    try {
        $randomPart = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        $randomPart = (string) mt_rand(100000, 999999);
    }

    $fileName = 'cv_' . $userId . '_' . time() . '_' . $randomPart . '.' . $extension;
    $destination = JOBHUB_CV_DIR . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        $error = 'Could not save CV file.';
        return null;
    }

    return JOBHUB_CV_RELATIVE_DIR . $fileName;
}

function jobhub_cv_output_download(string $path): void
{
    $absolutePath = jobhub_cv_absolute_path($path);
    if ($absolutePath === null || !is_file($absolutePath)) {
        http_response_code(404);
        echo 'CV file not found.';
        exit;
    }

    $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
    $contentTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
    $disposition = $extension === 'pdf' ? 'inline' : 'attachment';

    header('Content-Description: File Transfer');
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($absolutePath));
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode(basename($absolutePath)) . '"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=0, must-revalidate');

    readfile($absolutePath);
    exit;
}
