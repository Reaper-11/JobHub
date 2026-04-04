<?php
require '../db.php';
require_role('admin');
$allowed = ['jobs', 'users', 'applications', 'bookmarks', 'companies'];
$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$table = isset($input['table']) ? $input['table'] : '';
$id = isset($input['id']) ? (int) $input['id'] : 0;
$return = isset($input['return']) ? $input['return'] : 'admin-dashboard.php';
$remarks = trim((string) ($input['remarks'] ?? ''));
$adminId = current_admin_id() ?? 0;

if (in_array($table, $allowed) && $id > 0) {
    if ($table === 'users') {
        $user = db_query_all("SELECT id, account_id, name, email FROM users WHERE id = ? LIMIT 1", "i", [$id])[0] ?? null;
        if ($user) {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE users SET account_status = 'removed', is_active = 0, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                $ok = $stmt->execute();
                $stmt->close();

                if ($ok && !empty($user['account_id'])) {
                    $ok = jobhub_update_account_status($conn, (int) $user['account_id'], 'inactive');
                }

                if ($ok) {
                    $conn->commit();
                    log_activity(
                        $conn,
                        $adminId,
                        'admin',
                        'user_removed',
                        'Admin removed user account ' . ($user['name'] ?? '#' . $id),
                        'user',
                        $id
                    );

                    $userEmail = trim((string) ($user['email'] ?? ''));
                    if ($userEmail !== '') {
                        try {
                            $mailResult = jobhub_send_account_removed_email(
                                $userEmail,
                                (string) ($user['name'] ?? ''),
                                'jobseeker',
                                $remarks
                            );

                            if (empty($mailResult['success'])) {
                                $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                jobhub_log_mail_error(
                                    'account-removed',
                                    'Job seeker removal email failed for ' . $userEmail . ': '
                                    . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                );
                            }
                        } catch (Throwable $mailException) {
                            jobhub_log_mail_error(
                                'account-removed',
                                'Job seeker removal email threw an exception for ' . $userEmail . ': ' . $mailException->getMessage()
                            );
                        }
                    }
                } else {
                    $conn->rollback();
                }
            }
        }
    } elseif ($table === 'companies') {
        $company = db_query_all("SELECT id, account_id, name, email FROM companies WHERE id = ? LIMIT 1", "i", [$id])[0] ?? null;

        if ($company) {
            $conn->begin_transaction();
            $ok = false;

            if (jobhub_column_exists($conn, 'companies', 'is_active')) {
                $setClauses = ["is_active = 0"];
                $types = '';
                $params = [];

                if (jobhub_column_exists($conn, 'companies', 'operational_state')) {
                    $setClauses[] = "operational_state = 'suspended'";
                }
                if (jobhub_column_exists($conn, 'companies', 'restriction_reason')) {
                    $setClauses[] = "restriction_reason = NULLIF(?, '')";
                    $types .= 's';
                    $params[] = $remarks;
                }
                if (jobhub_column_exists($conn, 'companies', 'restricted_at')) {
                    $setClauses[] = "restricted_at = NOW()";
                }
                if (jobhub_column_exists($conn, 'companies', 'restricted_by_admin_id')) {
                    $setClauses[] = "restricted_by_admin_id = ?";
                    $types .= 'i';
                    $params[] = $adminId;
                }
                if (jobhub_column_exists($conn, 'companies', 'updated_at')) {
                    $setClauses[] = "updated_at = NOW()";
                }

                $sql = "UPDATE companies SET " . implode(', ', $setClauses) . " WHERE id = ?";
                $types .= 'i';
                $params[] = $id;
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $ok = $stmt->execute();
                    $stmt->close();
                }

                if ($ok && !empty($company['account_id'])) {
                    $ok = jobhub_update_account_status($conn, (int) $company['account_id'], 'inactive');
                }
            } else {
                $ok = !empty($company['account_id'])
                    ? jobhub_delete_account($conn, (int) $company['account_id'])
                    : false;
            }

            if ($ok) {
                $conn->commit();
                log_activity(
                    $conn,
                    $adminId,
                    'admin',
                    'company_removed',
                    'Admin removed or deactivated company account ' . ($company['name'] ?? '#' . $id),
                    'company',
                    $id
                );

                $companyEmail = trim((string) ($company['email'] ?? ''));
                if ($companyEmail !== '') {
                    try {
                        $mailResult = jobhub_send_account_removed_email(
                            $companyEmail,
                            (string) ($company['name'] ?? ''),
                            'company',
                            $remarks
                        );

                        if (empty($mailResult['success'])) {
                            $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                            jobhub_log_mail_error(
                                'account-removed',
                                'Company removal email failed for ' . $companyEmail . ': '
                                . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                            );
                        }
                    } catch (Throwable $mailException) {
                        jobhub_log_mail_error(
                            'account-removed',
                            'Company removal email threw an exception for ' . $companyEmail . ': ' . $mailException->getMessage()
                        );
                    }
                }
            } else {
                $conn->rollback();
            }
        }
    } else {
        $conn->query("DELETE FROM $table WHERE id=$id");
    }
}
header("Location: " . $return);
exit;
