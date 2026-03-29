<?php
function extract_csrf(string $html): string {
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES);
    }
    return '';
}

function request_page($ch, string $url): array {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, []);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [substr($response, 0, $headerSize), substr($response, $headerSize)];
}

function post_form($ch, string $url, $fields, array $headers = []): string {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return substr($response, $headerSize);
}

function get_profile_form($ch, string $base): array {
    [, $html] = request_page($ch, $base . '/user-account.php');
    return [$html, extract_csrf($html)];
}

require __DIR__ . '/db.php';

$base = 'http://localhost/JobHub';
$email = 'codex_profile_test_1774785345@example.com';
$password = 'Test1234';
$validPdf = __DIR__ . '/uploads/cv/cv_1_1767013700.pdf';
$invalidFile = __DIR__ . '/README.md';
$cookie = __DIR__ . '/tmp_jobhub_cookie.txt';
@unlink($cookie);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_COOKIEJAR => $cookie,
    CURLOPT_COOKIEFILE => $cookie,
    CURLOPT_HEADER => true,
]);

[, $loginHtml] = request_page($ch, $base . '/login.php');
$loginCsrf = extract_csrf($loginHtml);
post_form($ch, $base . '/login.php', http_build_query([
    'csrf_token' => $loginCsrf,
    'email' => $email,
    'password' => $password,
]), ['Content-Type: application/x-www-form-urlencoded']);

list($accountHtml, $accountCsrf) = get_profile_form($ch, $base);
$initialCvShown = strpos($accountHtml, 'Current CV:') !== false ? 'yes' : 'no';

$results = [];

$skillsOnlyHtml = post_form($ch, $base . '/user-account.php', http_build_query([
    'action' => 'profile',
    'csrf_token' => $accountCsrf,
    'name' => 'Codex Tester',
    'email' => $email,
    'phone' => '9812345678',
    'preferred_category' => 'Information Technology',
    'experience_level' => 'Fresher',
    'skills' => 'PHP, MySQL, Laravel',
]), ['Content-Type: application/x-www-form-urlencoded']);
$results['skills_only_message'] = strpos($skillsOnlyHtml, 'Skills updated successfully.') !== false ? 'ok' : 'missing';

$stmt = $conn->prepare("SELECT skills, cv_path FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: ['skills' => null, 'cv_path' => null];
$stmt->close();
$cvAfterSkillsOnly = (string) ($row['cv_path'] ?? '');
$results['skills_only_db_skills'] = (string) ($row['skills'] ?? '');
$results['skills_only_db_cv'] = $cvAfterSkillsOnly === '' ? '(empty)' : $cvAfterSkillsOnly;

[, $accountCsrf] = get_profile_form($ch, $base);
$cvOnlyHtml = post_form($ch, $base . '/user-account.php', [
    'action' => 'profile',
    'csrf_token' => $accountCsrf,
    'name' => 'Codex Tester',
    'email' => $email,
    'phone' => '9812345678',
    'preferred_category' => 'Information Technology',
    'experience_level' => 'Fresher',
    'skills' => 'PHP, MySQL, Laravel',
    'cv_file' => new CURLFile($validPdf, 'application/pdf', 'sample.pdf'),
]);
$results['cv_only_message'] = strpos($cvOnlyHtml, 'CV uploaded successfully.') !== false ? 'ok' : 'missing';
$results['cv_only_visible'] = strpos($cvOnlyHtml, 'Current CV:') !== false ? 'yes' : 'no';

$stmt = $conn->prepare("SELECT skills, cv_path FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: ['skills' => null, 'cv_path' => null];
$stmt->close();
$cvAfterUpload = (string) ($row['cv_path'] ?? '');
$results['cv_only_db_skills'] = (string) ($row['skills'] ?? '');
$results['cv_only_db_cv'] = $cvAfterUpload === '' ? '(empty)' : $cvAfterUpload;

[, $accountCsrf] = get_profile_form($ch, $base);
$bothHtml = post_form($ch, $base . '/user-account.php', [
    'action' => 'profile',
    'csrf_token' => $accountCsrf,
    'name' => 'Codex Tester',
    'email' => $email,
    'phone' => '9812345678',
    'preferred_category' => 'Information Technology',
    'experience_level' => 'Fresher',
    'skills' => 'PHP, MySQL, Tailwind CSS',
    'cv_file' => new CURLFile($validPdf, 'application/pdf', 'sample.pdf'),
]);
$results['both_message'] = strpos($bothHtml, 'Skills and CV updated successfully.') !== false ? 'ok' : 'missing';

$stmt = $conn->prepare("SELECT skills, cv_path FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: ['skills' => null, 'cv_path' => null];
$stmt->close();
$cvAfterBoth = (string) ($row['cv_path'] ?? '');
$results['both_db_skills'] = (string) ($row['skills'] ?? '');
$results['both_db_cv'] = $cvAfterBoth === '' ? '(empty)' : $cvAfterBoth;
$results['cv_changed_on_both'] = ($cvAfterBoth !== '' && $cvAfterBoth !== $cvAfterUpload) ? 'yes' : 'no';

[, $accountCsrf] = get_profile_form($ch, $base);
$invalidHtml = post_form($ch, $base . '/user-account.php', [
    'action' => 'profile',
    'csrf_token' => $accountCsrf,
    'name' => 'Codex Tester',
    'email' => $email,
    'phone' => '9812345678',
    'preferred_category' => 'Information Technology',
    'experience_level' => 'Fresher',
    'skills' => 'PHP, MySQL, Tailwind CSS',
    'cv_file' => new CURLFile($invalidFile, 'text/plain', 'bad.txt'),
]);
$results['invalid_file_message'] = strpos($invalidHtml, 'Only PDF, DOC, and DOCX files are allowed.') !== false ? 'ok' : 'missing';

$stmt = $conn->prepare("SELECT cv_path FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: ['cv_path' => null];
$stmt->close();
$cvAfterInvalid = (string) ($row['cv_path'] ?? '');
$results['cv_unchanged_after_invalid'] = $cvAfterInvalid === $cvAfterBoth ? 'yes' : 'no';

[, $accountCsrf] = get_profile_form($ch, $base);
$skillsAfterCvHtml = post_form($ch, $base . '/user-account.php', http_build_query([
    'action' => 'profile',
    'csrf_token' => $accountCsrf,
    'name' => 'Codex Tester',
    'email' => $email,
    'phone' => '9812345678',
    'preferred_category' => 'Information Technology',
    'experience_level' => 'Fresher',
    'skills' => 'PHP, MySQL, Alpine.js',
]), ['Content-Type: application/x-www-form-urlencoded']);
$results['skills_after_cv_message'] = strpos($skillsAfterCvHtml, 'Skills updated successfully.') !== false ? 'ok' : 'missing';

$stmt = $conn->prepare("SELECT skills, cv_path FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc() ?: ['skills' => null, 'cv_path' => null];
$stmt->close();
$results['skills_after_cv_db_skills'] = (string) ($row['skills'] ?? '');
$results['cv_preserved_without_new_upload'] = ((string) ($row['cv_path'] ?? '')) === $cvAfterBoth ? 'yes' : 'no';

list($reloadHtml,) = get_profile_form($ch, $base);
$results['reload_shows_latest_skills'] = strpos($reloadHtml, 'PHP, MySQL, Alpine.js') !== false ? 'yes' : 'no';
$results['reload_shows_current_cv'] = strpos($reloadHtml, 'Current CV:') !== false ? 'yes' : 'no';
$results['initial_cv_was_visible'] = $initialCvShown;

curl_close($ch);
@unlink($cookie);

foreach ($results as $key => $value) {
    echo $key, '=', $value, PHP_EOL;
}
?>