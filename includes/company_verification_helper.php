<?php

function get_company_verification_record(mysqli $conn, int $companyId): ?array
{
    $stmt = $conn->prepare("
        SELECT id, name, email, location, is_approved, operational_state,
               verification_company_name, verification_registration_number,
               verification_phone, verification_address, verification_document_path,
               verification_status, verification_admin_remarks, verification_submitted_at,
               verification_verified_at, verification_verified_by_admin_id
        FROM companies
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $companyId);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $record;
}

function get_company_verification_status(?array $record): string
{
    if (!$record) {
        return 'not_submitted';
    }

    $status = $record['verification_status'] ?? null;
    if ($status === 'approved' || $status === 'pending' || $status === 'rejected') {
        return $status;
    }

    return 'not_submitted';
}

function is_company_verified(?array $record): bool
{
    return get_company_verification_status($record) === 'approved';
}

function company_verification_badge_class(string $status): string
{
    return match ($status) {
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'pending' => 'bg-warning text-dark',
        default => 'bg-secondary',
    };
}

function company_verification_label(string $status): string
{
    return match ($status) {
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        default => 'Not Submitted',
    };
}
?>
