<?php
require 'db.php';
require 'includes/recommendation.php';
require_role('jobseeker');

$uid = current_user_id() ?? 0;
$accountId = current_account_id() ?? 0;
$profileMsg = "";
$profileType = "";
$passMsg = "";
$passType = "";
$deleteMsg = "";
$deleteType = "";
$jobCategories = require __DIR__ . '/includes/categories.php';
$experienceLevels = require __DIR__ . '/includes/experience_levels.php';
$legacyCategoryWarning = false;
$preferredValue = '';
$hasExperienceColumn = false;
$hasSkillsColumn = false;

$checkExperience = $conn->query("SHOW COLUMNS FROM users LIKE 'experience_level'");
if ($checkExperience) {
    $hasExperienceColumn = $checkExperience->num_rows > 0;
    $checkExperience->close();
}

$checkSkills = $conn->query("SHOW COLUMNS FROM users LIKE 'skills'");
if ($checkSkills) {
    $hasSkillsColumn = $checkSkills->num_rows > 0;
    $checkSkills->close();
}

// Fetch current user details
$userSelect = "SELECT name, email, phone, preferred_category, cv_path, profile_image";
if ($hasExperienceColumn) {
    $userSelect .= ", experience_level";
}
$userSelect .= $hasSkillsColumn ? ", skills" : ", '' AS skills";
$userSelect .= " FROM users WHERE id = ?";
$user = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'preferred_category' => '',
    'cv_path' => '',
    'profile_image' => '',
    'experience_level' => '',
    'skills' => '',
];

$userStmt = $conn->prepare($userSelect);
if ($userStmt) {
    $userStmt->bind_param("i", $uid);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc() ?: $user;
    $userStmt->close();
}

$cvSchemaResult = jobhub_cv_ensure_library_schema($conn);
$userCvLibrary = !empty($cvSchemaResult['success'])
    ? jobhub_user_cv_list($conn, $uid)
    : [];
$defaultUserCv = !empty($cvSchemaResult['success'])
    ? jobhub_user_default_cv($conn, $uid)
    : null;
if (is_array($defaultUserCv) && !empty($defaultUserCv['cv_path'])) {
    $user['cv_path'] = (string) $defaultUserCv['cv_path'];
}

$preferenceProfile = function_exists('get_user_preferences') ? get_user_preferences($conn, $uid) : [];
if (!empty($preferenceProfile['preferred_category'])) {
    $user['preferred_category'] = $preferenceProfile['preferred_category'];
}
$preferredValue = $user['preferred_category'] ?? '';
if ($preferredValue !== '' && !in_array($preferredValue, $jobCategories, true)) {
    $legacyCategoryWarning = true;
    $preferredValue = 'Other';
}

$recommendedJobs = recommendJobs($conn, $uid, 10);
$debugUpload = isset($_GET['debug_upload']) || isset($_POST['debug_upload']);
$profileDebug = [];
$uploadError = '';
$uploadedCvEntries = [];
$cvMoved = null;
$dbPrepareOk = null;
$dbExecuteOk = null;
$dbStmtError = '';
$dbAffected = null;
$finalCvPath = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES)) {
        $profileMsg = "Upload failed before processing. The request was likely too large for the server limits.";
        $profileType = "alert-danger";
        $profileMsg .= " (upload_max_filesize=" . ini_get('upload_max_filesize') . ", post_max_size=" . ini_get('post_max_size') . ")";
    }

    $action = $_POST['action'] ?? '';
    if ($action === '' && $profileMsg === '') {
        $profileMsg = "No action received. Please try saving your profile again.";
        $profileType = "alert-danger";
    } elseif ($profileMsg === '' && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        if ($action === 'password') {
            $passMsg = "Invalid request. Please try again.";
            $passType = "alert-danger";
        } elseif ($action === 'delete') {
            $deleteMsg = "Invalid request. Please try again.";
            $deleteType = "alert-danger";
        } else {
            $profileMsg = "Invalid request. Please try again.";
            $profileType = "alert-danger";
        }
    }

    if ($action === 'delete_cv' && $profileType === '') {
        if (empty($cvSchemaResult['success'])) {
            $profileMsg = (string) ($cvSchemaResult['message'] ?? 'CV storage could not be prepared.');
            $profileType = "alert-danger";
        } else {
            $cvId = (int) ($_POST['cv_id'] ?? 0);
            $deleteCvError = '';
            if (jobhub_user_cv_remove($conn, $uid, $cvId, $deleteCvError)) {
                $profileMsg = "CV deleted successfully.";
                $profileType = "alert-success";
                $userCvLibrary = jobhub_user_cv_list($conn, $uid);
                $defaultUserCv = jobhub_user_default_cv($conn, $uid);
                $user['cv_path'] = $defaultUserCv['cv_path'] ?? '';
            } else {
                $profileMsg = $deleteCvError !== '' ? $deleteCvError : "Could not delete the selected CV.";
                $profileType = "alert-danger";
            }
        }
    } elseif ($action === 'profile' && $profileType === '') {
        $existingUser = $user;
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $preferred_category = trim($_POST['preferred_category'] ?? '');
        $experience_level = trim($_POST['experience_level'] ?? '');
        $skills = recommend_normalize_skill_string($_POST['skills'] ?? '');
        $uploadedCvPath = null;
        if ($preferred_category !== '') {
            $preferredValue = $preferred_category;
            $legacyCategoryWarning = false;
        }

        $currentCv = (string) ($existingUser['cv_path'] ?? '');
        $newCvPath = $currentCv;

        // Validation
        if ($name === '' || $email === '' || $preferred_category === '') {
            $profileMsg = "Name, email, and category are required.";
            $profileType = "alert-danger";
        } elseif ($nameError = jobhub_validate_person_name($name)) {
            $profileMsg = $nameError;
            $profileType = "alert-danger";
        }

        if ($profileMsg === '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $profileMsg = "Please enter a valid email address.";
            $profileType = "alert-danger";
        }

        if ($profileMsg === '' && $phone !== '') {
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) === 13 && substr($digits, 0, 3) === '977') {
                $digits = substr($digits, 3);
            }
            if (strlen($digits) !== 10) {
                $profileMsg = "Phone number must be exactly 10 digits.";
                $profileType = "alert-danger";
            } else {
                $phone = $digits;
            }
        }

        if ($profileMsg === '' && !in_array($preferred_category, $jobCategories, true)) {
            $profileMsg = "Invalid job category selected.";
            $profileType = "alert-danger";
        }

        if ($profileMsg === '' && $hasExperienceColumn && $experience_level !== '' && !in_array($experience_level, $experienceLevels, true)) {
            $profileMsg = "Invalid experience level selected.";
            $profileType = "alert-danger";
        }

        // Upload (only if validation passed)
        if ($profileMsg === '') {
            $cvUploads = [];
            if (isset($_FILES['cv_files'])) {
                $cvUploads = jobhub_cv_normalize_uploads($_FILES['cv_files']);
            } elseif (isset($_FILES['cv_file'])) {
                $cvUploads = jobhub_cv_normalize_uploads($_FILES['cv_file']);
            }

            $hasCvUpload = false;
            foreach ($cvUploads as $cvUpload) {
                if ((int) ($cvUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $hasCvUpload = true;
                    break;
                }
            }

            if ($hasCvUpload && empty($cvSchemaResult['success'])) {
                $profileMsg = (string) ($cvSchemaResult['message'] ?? 'CV storage could not be prepared.');
                $profileType = "alert-danger";
            } elseif ($hasCvUpload) {
                foreach ($cvUploads as $cvUpload) {
                    if ((int) ($cvUpload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $uploadedCvEntry = jobhub_cv_upload_details($cvUpload, $uid, $uploadError);
                    if ($uploadedCvEntry === null) {
                        $cvMoved = false;
                        break;
                    }

                    $uploadedCvEntries[] = $uploadedCvEntry;
                }

                if (!empty($uploadedCvEntries)) {
                    $newCvPath = (string) ($uploadedCvEntries[count($uploadedCvEntries) - 1]['path'] ?? $currentCv);
                    $uploadedCvPath = $newCvPath;
                    $cvMoved = true;
                }
            }
        }

        if (!empty($uploadError)) {
            $profileMsg = $uploadError;
            $profileType = "alert-danger";
        }

        // DB update (only if validation/upload passed)
        if ($profileMsg === '') {
            if (jobhub_email_exists($conn, $email, $accountId)) {
                $profileMsg = "That email is already in use.";
                $profileType = "alert-danger";
            } else {
                $phoneVal = $phone === '' ? null : $phone;
                if ($hasExperienceColumn && $hasSkillsColumn) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, experience_level = ?, skills = ?, cv_path = ?, updated_at = NOW() WHERE id = ?");
                } elseif ($hasExperienceColumn) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, experience_level = ?, cv_path = ?, updated_at = NOW() WHERE id = ?");
                } elseif ($hasSkillsColumn) {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, skills = ?, cv_path = ?, updated_at = NOW() WHERE id = ?");
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, preferred_category = ?, cv_path = ?, updated_at = NOW() WHERE id = ?");
                }
                $dbPrepareOk = $stmt ? true : false;
                if (!$stmt) {
                    $dbStmtError = $conn->error ?? '';
                    $profileMsg = "Could not update profile. Please try again.";
                    $profileType = "alert-danger";
                } else {
                    if ($hasExperienceColumn && $hasSkillsColumn) {
                        $stmt->bind_param("sssssssi", $name, $email, $phoneVal, $preferred_category, $experience_level, $skills, $newCvPath, $uid);
                    } elseif ($hasExperienceColumn) {
                        $stmt->bind_param("ssssssi", $name, $email, $phoneVal, $preferred_category, $experience_level, $newCvPath, $uid);
                    } elseif ($hasSkillsColumn) {
                        $stmt->bind_param("ssssssi", $name, $email, $phoneVal, $preferred_category, $skills, $newCvPath, $uid);
                    } else {
                        $stmt->bind_param("sssssi", $name, $email, $phoneVal, $preferred_category, $newCvPath, $uid);
                    }

                    $conn->begin_transaction();

                    try {
                        if (!jobhub_update_account_identity($conn, $accountId, $name, $email)) {
                            throw new RuntimeException('Could not update account identity.');
                        }

                        $dbExecuteOk = $stmt->execute();
                        $dbAffected = $stmt->affected_rows;
                        $finalCvPath = $newCvPath;
                        if (!$dbExecuteOk) {
                            throw new RuntimeException($stmt->error ?: 'Could not update profile.');
                        }

                        if (jobhub_table_exists($conn, 'user_preferences')) {
                            $prefStmt = $conn->prepare("INSERT INTO user_preferences (user_id, preferred_category) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferred_category = VALUES(preferred_category), updated_at = CURRENT_TIMESTAMP");
                            if ($prefStmt) {
                                $prefStmt->bind_param("is", $uid, $preferred_category);
                                $prefStmt->execute();
                                $prefStmt->close();
                            }
                        }

                        if (!empty($uploadedCvEntries)) {
                            $cvInsertStmt = $conn->prepare("
                                INSERT INTO user_cvs (user_id, cv_path, original_name, created_at, updated_at)
                                VALUES (?, ?, ?, NOW(), NOW())
                            ");
                            if (!$cvInsertStmt) {
                                throw new RuntimeException('Could not save uploaded CV details.');
                            }

                            foreach ($uploadedCvEntries as $uploadedCvEntry) {
                                $cvPathToInsert = (string) ($uploadedCvEntry['path'] ?? '');
                                $cvOriginalName = jobhub_cv_display_name(
                                    (string) ($uploadedCvEntry['original_name'] ?? ''),
                                    $cvPathToInsert
                                );
                                $cvInsertStmt->bind_param("iss", $uid, $cvPathToInsert, $cvOriginalName);
                                if (!$cvInsertStmt->execute()) {
                                    $error = $cvInsertStmt->error;
                                    $cvInsertStmt->close();
                                    throw new RuntimeException($error !== '' ? $error : 'Could not save uploaded CV details.');
                                }
                            }

                            $cvInsertStmt->close();
                        }

                        $conn->commit();

                        $phoneDisplay = $phone === '' ? '' : $phone;
                        $experienceChanged = $hasExperienceColumn
                            && $experience_level !== (string) ($existingUser['experience_level'] ?? '');
                        $skillsChanged = $hasSkillsColumn
                            && $skills !== (string) ($existingUser['skills'] ?? '');
                        $uploadedCvCount = count($uploadedCvEntries);
                        $cvChanged = $uploadedCvCount > 0;
                        $profileFieldsChanged = (
                            $name !== (string) ($existingUser['name'] ?? '')
                            || $email !== strtolower((string) ($existingUser['email'] ?? ''))
                            || $phoneDisplay !== (string) ($existingUser['phone'] ?? '')
                            || $preferred_category !== (string) ($existingUser['preferred_category'] ?? '')
                            || $experienceChanged
                        );

                        if ($cvChanged && $skillsChanged && !$profileFieldsChanged) {
                            $profileMsg = $uploadedCvCount === 1
                                ? "Skills and CV updated successfully."
                                : "Skills and {$uploadedCvCount} CVs updated successfully.";
                        } elseif ($cvChanged && !$profileFieldsChanged && !$skillsChanged) {
                            $profileMsg = $uploadedCvCount === 1
                                ? "CV uploaded successfully."
                                : "{$uploadedCvCount} CVs uploaded successfully.";
                        } elseif ($skillsChanged && !$profileFieldsChanged && !$cvChanged) {
                            $profileMsg = "Skills updated successfully.";
                        } elseif (!$profileFieldsChanged && !$skillsChanged && !$cvChanged) {
                            $profileMsg = "No profile changes were made.";
                        } elseif ($cvChanged) {
                            $profileMsg = $uploadedCvCount === 1
                                ? "Profile updated successfully and 1 CV was uploaded."
                                : "Profile updated successfully and {$uploadedCvCount} CVs were uploaded.";
                        } else {
                            $profileMsg = "Profile updated successfully.";
                        }

                        $profileType = "alert-success";
                        $_SESSION['preferred_category'] = $preferred_category;
                        $user['name'] = $name;
                        $user['email'] = $email;
                        $user['phone'] = $phone;
                        $user['preferred_category'] = $preferred_category;
                        if ($hasExperienceColumn) {
                            $user['experience_level'] = $experience_level;
                        }
                        if ($hasSkillsColumn) {
                            $user['skills'] = $skills;
                        }
                        $user['cv_path'] = $newCvPath;
                        $userCvLibrary = jobhub_user_cv_list($conn, $uid);
                        $defaultUserCv = jobhub_user_default_cv($conn, $uid);
                    } catch (Throwable $e) {
                        $conn->rollback();
                        $dbStmtError = $e->getMessage();
                        $profileMsg = "Could not update profile. Please try again.";
                        $profileType = "alert-danger";

                        $refreshedAccount = jobhub_fetch_account_by_id($conn, $accountId);
                        if ($refreshedAccount) {
                            jobhub_sync_session_from_account($refreshedAccount);
                        }
                    }

                    $stmt->close();
                }
            }
        }

        // If the upload succeeded but the profile update did not, remove the
        // new file so the database and filesystem do not drift apart.
        if ($profileType === 'alert-danger' && !empty($uploadedCvEntries)) {
            foreach ($uploadedCvEntries as $uploadedCvEntry) {
                $uploadedCvPathToRemove = (string) ($uploadedCvEntry['path'] ?? '');
                if (!jobhub_cv_is_stored_path($uploadedCvPathToRemove)) {
                    continue;
                }

                $uploadedAbsolutePath = jobhub_cv_absolute_path($uploadedCvPathToRemove);
                if ($uploadedAbsolutePath !== null && is_file($uploadedAbsolutePath)) {
                    @unlink($uploadedAbsolutePath);
                }
            }
        }

        if ($profileMsg === '') {
            $profileMsg = "Profile save did not complete. Check the debug panel for details.";
            $profileType = "alert-danger";
        }

        if ($debugUpload) {
            $profileDebug = [
                'action' => $action,
                'name' => $name,
                'email' => $email,
                'preferred_category' => $preferred_category,
                'experience_level' => $experience_level,
                'skills' => $skills,
                'upload_error' => $uploadError,
                'profile_msg' => $profileMsg,
                'profile_type' => $profileType,
                'db_error' => $conn->error ?? '',
                'cv_moved' => $cvMoved === null ? 'n/a' : ($cvMoved ? 'yes' : 'no'),
                'db_prepare_ok' => $dbPrepareOk === null ? 'n/a' : ($dbPrepareOk ? 'yes' : 'no'),
                'db_execute_ok' => $dbExecuteOk === null ? 'n/a' : ($dbExecuteOk ? 'yes' : 'no'),
                'db_affected_rows' => $dbAffected === null ? 'n/a' : (string) $dbAffected,
                'final_cv_path' => $finalCvPath,
                'user_cv_path' => $user['cv_path'] ?? '',
                'conn_errno' => (string) ($conn->errno ?? ''),
            ];
        }
    } elseif ($action === 'password' && $passType === '') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($old === '' || $new === '' || $confirm === '') {
            $passMsg = "All fields are required.";
            $passType = "alert-danger";
        } elseif ($new !== $confirm) {
            $passMsg = "New password and confirmation do not match.";
            $passType = "alert-danger";
        } elseif ($passwordError = jobhub_validate_password_strength($new)) {
            $passMsg = $passwordError;
            $passType = "alert-danger";
        } else {
            $stmt = $conn->prepare("SELECT password FROM accounts WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            $storedHash = $row['password'] ?? '';
            $validOld = $row
                ? jobhub_verify_password_with_upgrade($conn, 'accounts', $accountId, $old, $storedHash)
                : false;

            if (!$row || !$validOld) {
                $passMsg = "Old password is incorrect.";
                $passType = "alert-danger";
            } else {
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                $conn->begin_transaction();

                try {
                    if (!jobhub_update_account_password($conn, $accountId, $newHash)) {
                        throw new RuntimeException('Could not update account password.');
                    }

                    if (jobhub_column_exists($conn, 'users', 'password')) {
                        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if (!$stmt) {
                            throw new RuntimeException('Could not prepare legacy password update.');
                        }

                        $stmt->bind_param("si", $newHash, $uid);
                        if (!$stmt->execute()) {
                            $error = $stmt->error;
                            $stmt->close();
                            throw new RuntimeException($error !== '' ? $error : 'Could not update legacy password.');
                        }
                        $stmt->close();
                    }

                    $conn->commit();
                    $passMsg = "Password updated successfully.";
                    $passType = "alert-success";
                } catch (Throwable $e) {
                    $conn->rollback();
                    $passMsg = "Could not update password. Please try again.";
                    $passType = "alert-danger";
                }
            }
        }
    } elseif ($action === 'delete' && $deleteType === '') {
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if ($confirmPassword === '') {
            $deleteMsg = "Password is required to delete your account.";
            $deleteType = "alert-danger";
        } else {
            $stmt = $conn->prepare("SELECT password FROM accounts WHERE id = ? LIMIT 1");
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
            $storedHash = $row['password'] ?? '';
            $validConfirm = $row
                ? jobhub_verify_password_with_upgrade($conn, 'accounts', $accountId, $confirmPassword, $storedHash)
                : false;

            if (!$row || !$validConfirm) {
                $deleteMsg = "Password is incorrect.";
                $deleteType = "alert-danger";
            } else {
                $deleteUserId = (int) $uid;
                $cvPathsForCleanup = jobhub_collect_user_cv_paths($conn, $uid);
                $deleteRecipientEmail = strtolower(trim((string) ($user['email'] ?? '')));
                $deleteRecipientName = trim((string) ($user['name'] ?? ''));
                $conn->begin_transaction();

                try {
                    if (!jobhub_delete_account($conn, $accountId)) {
                        throw new RuntimeException('Could not delete account.');
                    }

                    $conn->commit();
                    jobhub_log_self_delete_activity(
                        $conn,
                        'jobseeker',
                        $deleteUserId,
                        $deleteRecipientName,
                        $deleteRecipientEmail
                    );

                    if ($deleteRecipientEmail !== '') {
                        try {
                            $mailResult = jobhub_send_account_removed_email(
                                $deleteRecipientEmail,
                                $deleteRecipientName,
                                'jobseeker'
                            );

                            if (empty($mailResult['success'])) {
                                $mailMessage = trim((string) ($mailResult['message'] ?? ''));
                                jobhub_log_mail_error(
                                    'account-removed',
                                    'Self-delete email failed for ' . $deleteRecipientEmail . ': '
                                    . ($mailMessage !== '' ? $mailMessage : 'Unknown mail error.')
                                );
                            }
                        } catch (Throwable $mailException) {
                            jobhub_log_mail_error(
                                'account-removed',
                                'Self-delete email threw an exception for ' . $deleteRecipientEmail . ': ' . $mailException->getMessage()
                            );
                        }
                    } else {
                        jobhub_log_mail_error(
                            'account-removed',
                            'Self-delete email skipped for user account #' . $accountId . ' because no recipient email was available.'
                        );
                    }

                    jobhub_cleanup_cv_paths($conn, $cvPathsForCleanup);

                    logout_user();
                    header("Location: index.php");
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('[JobHub Delete Account] User account deletion failed for account #' . $accountId . ': ' . $e->getMessage());
                    $deleteMsg = "Could not delete account. Please try again.";
                    $deleteType = "alert-danger";
                }
            }
        }
    }
}

$bodyClass = 'account-page user-ui';
require 'header.php';
?>
<h1 class="mb-3">Account</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Profile</h2>
        <?php if ($profileMsg): ?>
            <div class="alert <?php echo $profileType; ?>"><?php echo htmlspecialchars($profileMsg); ?></div>
        <?php elseif ($legacyCategoryWarning): ?>
            <div class="alert alert-warning">Your previous job category is no longer available. Please confirm a new category.</div>
        <?php endif; ?>
        <?php if ($debugUpload): ?>
            <?php
                $uploadDir = JOBHUB_CV_DIR;
                $tmpDir = ini_get('upload_tmp_dir');
                $tmpDir = $tmpDir !== '' ? $tmpDir : sys_get_temp_dir();
                $debugCvFiles = [];
                if (isset($_FILES['cv_files'])) {
                    $debugCvFiles = jobhub_cv_normalize_uploads($_FILES['cv_files']);
                } elseif (isset($_FILES['cv_file'])) {
                    $debugCvFiles = jobhub_cv_normalize_uploads($_FILES['cv_file']);
                }
                $debugCvFiles = array_values(array_filter($debugCvFiles, static function (array $file): bool {
                    return (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
                }));
            ?>
            <div class="alert alert-secondary small">
                <div>Debug upload: enabled</div>
                <div>request method: <?php echo htmlspecialchars($_SERVER['REQUEST_METHOD']); ?></div>
                <div>file_uploads: <?php echo ini_get('file_uploads'); ?></div>
                <div>upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?></div>
                <div>post_max_size: <?php echo ini_get('post_max_size'); ?></div>
                <div>upload_tmp_dir: <?php echo htmlspecialchars($tmpDir); ?></div>
                <div>cv upload dir: <?php echo htmlspecialchars($uploadDir); ?></div>
                <div>cv dir exists: <?php echo is_dir($uploadDir) ? 'yes' : 'no'; ?></div>
                <div>cv dir writable: <?php echo is_writable($uploadDir) ? 'yes' : 'no'; ?></div>
                <div>cv_files selected: <?php echo count($debugCvFiles); ?></div>
                <?php foreach ($debugCvFiles as $debugCvIndex => $debugCvFile): ?>
                    <div>cv_file_<?php echo $debugCvIndex + 1; ?> name: <?php echo htmlspecialchars($debugCvFile['name'] ?? ''); ?></div>
                    <div>cv_file_<?php echo $debugCvIndex + 1; ?> size: <?php echo (int) ($debugCvFile['size'] ?? 0); ?></div>
                    <div>cv_file_<?php echo $debugCvIndex + 1; ?> error: <?php echo (int) ($debugCvFile['error'] ?? UPLOAD_ERR_NO_FILE); ?></div>
                <?php endforeach; ?>
                <?php if (!empty($profileDebug)): ?>
                    <div>action: <?php echo htmlspecialchars($profileDebug['action'] ?? ''); ?></div>
                    <div>name: <?php echo htmlspecialchars($profileDebug['name'] ?? ''); ?></div>
                    <div>email: <?php echo htmlspecialchars($profileDebug['email'] ?? ''); ?></div>
                    <div>preferred_category: <?php echo htmlspecialchars($profileDebug['preferred_category'] ?? ''); ?></div>
                    <div>experience_level: <?php echo htmlspecialchars($profileDebug['experience_level'] ?? ''); ?></div>
                    <div>skills: <?php echo htmlspecialchars($profileDebug['skills'] ?? ''); ?></div>
                    <div>upload_error: <?php echo htmlspecialchars($profileDebug['upload_error'] ?? ''); ?></div>
                    <div>profile_msg: <?php echo htmlspecialchars($profileDebug['profile_msg'] ?? ''); ?></div>
                    <div>profile_type: <?php echo htmlspecialchars($profileDebug['profile_type'] ?? ''); ?></div>
                    <div>db_error: <?php echo htmlspecialchars($profileDebug['db_error'] ?? ''); ?></div>
                    <div>conn_errno: <?php echo htmlspecialchars($profileDebug['conn_errno'] ?? ''); ?></div>
                    <div>cv_moved: <?php echo htmlspecialchars($profileDebug['cv_moved'] ?? ''); ?></div>
                    <div>db_prepare_ok: <?php echo htmlspecialchars($profileDebug['db_prepare_ok'] ?? ''); ?></div>
                    <div>db_execute_ok: <?php echo htmlspecialchars($profileDebug['db_execute_ok'] ?? ''); ?></div>
                    <div>db_affected_rows: <?php echo htmlspecialchars($profileDebug['db_affected_rows'] ?? ''); ?></div>
                    <div>final_cv_path: <?php echo htmlspecialchars($profileDebug['final_cv_path'] ?? ''); ?></div>
                    <div>user_cv_path: <?php echo htmlspecialchars($profileDebug['user_cv_path'] ?? ''); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>">
            <input type="hidden" name="action" value="profile">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <?php if ($debugUpload): ?>
                <input type="hidden" name="debug_upload" value="1">
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Full Name*</label>
                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Email*</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required readonly>
                <small class="text-muted d-block mt-1">This email is linked to your account and cannot be changed.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Phone (optional)</label>
                <input
                    type="tel"
                    class="form-control"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                    inputmode="numeric"
                    maxlength="10"
                    pattern="[0-9]{10}"
                    oninput="this.value=this.value.replace(/\D/g,'').slice(0,10);"
                    placeholder="98XXXXXXXX"
                >
                <div class="form-text">Must be exactly 10 digits.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Job Preference*</label>
                <select name="preferred_category" class="form-select" required>
                    <option value="">Prefer Job Category</option>
                    <?php foreach ($jobCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"
                            <?php echo ($preferredValue === $cat) ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($hasExperienceColumn): ?>
                <div class="mb-3">
                    <label class="form-label">Experience Level (optional)</label>
                    <select name="experience_level" class="form-select">
                        <option value="">Select experience level</option>
                        <?php foreach ($experienceLevels as $level): ?>
                            <option value="<?php echo htmlspecialchars($level); ?>"
                                <?php echo (($user['experience_level'] ?? '') === $level) ? "selected" : ""; ?>>
                                <?php echo htmlspecialchars($level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($hasSkillsColumn): ?>
                <div class="mb-3">
                    <label class="form-label">Skills (optional)</label>
                    <textarea name="skills" class="form-control" rows="3" placeholder="PHP, MySQL, HTML, CSS"><?php echo htmlspecialchars($user['skills'] ?? ''); ?></textarea>
                    <div class="form-text">Enter comma-separated skills. Multiple separators like new lines or slashes will be normalized.</div>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label">CV / Resume</label>
                <input type="file" class="form-control" name="cv_files[]" accept=".pdf,.doc,.docx" multiple>
                <div class="form-text">Upload one or more CVs. Allowed: PDF, DOC, DOCX. Maximum size: 5MB each. You can choose specific CVs while applying for a job.</div>
                <?php if (!empty($userCvLibrary)): ?>
                    <div class="mt-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?= count($userCvLibrary) ?> CV<?= count($userCvLibrary) === 1 ? '' : 's' ?> saved</span>
                    </div>
                    <?php if ($defaultUserCv): ?>
                        <div class="form-text">
                            Default CV:
                            <?php echo htmlspecialchars($defaultUserCv['display_name'] ?? jobhub_cv_file_name($defaultUserCv['cv_path'] ?? '')); ?>
                            <a class="link-primary text-decoration-none ms-1" href="cv-download.php?scope=profile&cv_id=<?= (int) ($defaultUserCv['id'] ?? 0) ?>" target="_blank" rel="noopener">View</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="form-text text-warning">No CV uploaded yet.</div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>

        <?php if (!empty($userCvLibrary)): ?>
            <div class="mt-4">
                <h3 class="h6 mb-3">Saved CVs</h3>
                <div class="list-group">
                    <?php foreach ($userCvLibrary as $savedCv): ?>
                        <div class="list-group-item d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($savedCv['display_name'] ?? jobhub_cv_file_name($savedCv['cv_path'] ?? '')) ?>
                                    <?php if (!empty($savedCv['is_default'])): ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-2">Default</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    Uploaded <?= !empty($savedCv['created_at']) ? htmlspecialchars(date('M d, Y H:i', strtotime((string) $savedCv['created_at']))) : 'recently' ?>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-sm btn-outline-secondary" href="cv-download.php?scope=profile&cv_id=<?= (int) ($savedCv['id'] ?? 0) ?>" target="_blank" rel="noopener">View</a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this CV from your account?');">
                                    <input type="hidden" name="action" value="delete_cv">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="cv_id" value="<?= (int) ($savedCv['id'] ?? 0) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Change Password</h2>
        <?php if ($passMsg): ?>
            <div class="alert <?php echo $passType; ?>"><?php echo htmlspecialchars($passMsg); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label">Old Password*</label>
                <div class="password-toggle-group">
                    <input type="password" class="form-control" name="old_password" placeholder="Old Password" required>
                    <button type="button" class="btn password-toggle-button" data-password-toggle aria-label="Show password" aria-pressed="false"></button>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">New Password*</label>
                <div class="password-toggle-group">
                    <input type="password" class="form-control" name="new_password" placeholder="New Password" required minlength="8">
                    <button type="button" class="btn password-toggle-button" data-password-toggle aria-label="Show password" aria-pressed="false"></button>
                </div>
                <div class="form-text">Use at least 8 characters with one letter and one number.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Confirm Password*</label>
                <div class="password-toggle-group">
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm Password" required>
                    <button type="button" class="btn password-toggle-button" data-password-toggle aria-label="Show password" aria-pressed="false"></button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h2 class="h5">Delete Account</h2>
        <?php if ($deleteMsg): ?>
            <div class="alert <?php echo $deleteType; ?>"><?php echo htmlspecialchars($deleteMsg); ?></div>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('This will permanently delete your account. Continue?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="mb-3">
                <label class="form-label">Confirm Password*</label>
                <div class="password-toggle-group">
                    <input type="password" class="form-control" name="confirm_password" required>
                    <button type="button" class="btn password-toggle-button" data-password-toggle aria-label="Show password" aria-pressed="false"></button>
                </div>
            </div>
            <button type="submit" class="btn btn-danger">Delete Account</button>
        </form>
    </div>
</div>
<?php require 'footer.php'; ?>
