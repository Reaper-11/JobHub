<?php

function get_company_verification_record(mysqli $conn, int $companyId): ?array
{
    $stmt = $conn->prepare("
        SELECT id, name, email, location, is_approved, is_active, rejection_reason,
               operational_state, restriction_reason, restricted_at,
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

function jobhub_company_final_status(?array $record): string
{
    $approvalValue = (int)($record['is_approved'] ?? 0);

    if ($approvalValue === -1) {
        return 'rejected';
    }

    if ($approvalValue === 1) {
        return is_company_verified($record) ? 'active' : 'approved_incomplete';
    }

    return 'pending';
}

function company_final_status_badge_class(string $status): string
{
    return match ($status) {
        'active' => 'bg-success',
        'approved_incomplete' => 'bg-info text-dark',
        'rejected' => 'bg-danger',
        default => 'bg-warning text-dark',
    };
}

function company_final_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Active',
        'approved_incomplete' => 'Approved but Incomplete',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

function jobhub_company_can_post_jobs(?array $record): bool
{
    if (jobhub_company_final_status($record) !== 'active') {
        return false;
    }

    if ((int)($record['is_active'] ?? 1) !== 1) {
        return false;
    }

    return strtolower(trim((string)($record['operational_state'] ?? 'active'))) === 'active';
}

function jobhub_company_posting_block_message(?array $record): string
{
    $finalStatus = jobhub_company_final_status($record);

    if ($finalStatus === 'rejected') {
        $message = 'Your company account is rejected. You cannot post jobs until admin re-approves your company.';
        $reason = trim((string)($record['rejection_reason'] ?? ''));
        if ($reason !== '') {
            $message .= ' Reason: ' . $reason;
        }

        return $message;
    }

    if ($finalStatus === 'pending') {
        return 'Your company account is pending admin approval. You cannot post jobs until approval and verification are completed.';
    }

    if ($finalStatus === 'approved_incomplete') {
        $message = 'Your company is approved, but verification is incomplete. You cannot post jobs until verification is approved.';
        $verificationStatus = get_company_verification_status($record);
        $remarks = trim((string)($record['verification_admin_remarks'] ?? ''));

        if ($verificationStatus === 'rejected' && $remarks !== '') {
            $message .= ' Admin remarks: ' . $remarks;
        }

        return $message;
    }

    if ((int)($record['is_active'] ?? 1) !== 1) {
        return 'Your company account is inactive. Please contact admin.';
    }

    $operationalState = strtolower(trim((string)($record['operational_state'] ?? 'active')));
    $restrictionReason = trim((string)($record['restriction_reason'] ?? ''));

    if ($operationalState === 'on_hold') {
        $message = 'Your company is currently on hold. You cannot post jobs until the hold is lifted.';
        if ($restrictionReason !== '') {
            $message .= ' Reason: ' . $restrictionReason;
        }

        return $message;
    }

    if ($operationalState === 'suspended') {
        $message = 'Your company account is suspended. You cannot post jobs due to policy restrictions.';
        if ($restrictionReason !== '') {
            $message .= ' Reason: ' . $restrictionReason;
        }

        return $message;
    }

    return '';
}

function jobhub_company_public_job_clause(string $alias = 'c'): string
{
    $safeAlias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($safeAlias === '') {
        $safeAlias = 'c';
    }

    return "({$safeAlias}.is_approved = 1
        AND LOWER(COALESCE({$safeAlias}.verification_status, '')) = 'approved'
        AND COALESCE({$safeAlias}.is_active, 1) = 1
        AND COALESCE({$safeAlias}.operational_state, 'active') = 'active')";
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
