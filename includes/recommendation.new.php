<?php
// includes/recommendation.php
// Content-based job recommendation logic (deterministic, transparent, and viva-friendly)

$RECOMMENDATION_CONFIG = [
    'weights' => [
        'category_match' => 40,
        'experience_match' => 20,
        'skill_match' => 12,
        'text_match' => 4,
        'applied_category_match' => 6,
        'applied_experience_match' => 4,
        'applied_skill_match' => 3,
        'recency_boost' => 6,
    ],
    'candidate_days' => 90,
    'activity_days' => 60,
    'cache_ttl' => 600,
    'max_keywords' => 8,
    'max_categories' => 5,
    'max_overlap' => 5,
];

if (!function_exists('recommendJobs')) {
    function recommendJobs($pdo, $userId, $limit = 10): array {
        global $RECOMMENDATION_CONFIG;

        $limit = max(1, (int) $limit);
        $userId = (int) $userId;

        if ($userId <= 0 || !$pdo) {
            return [];
        }

        if (!recommend_is_job_seeker($pdo, $userId)) {
            return [];
        }

        $userColumns = recommend_table_columns($pdo, 'users');
        $jobColumns = recommend_table_columns($pdo, 'jobs');
        $profile = recommend_load_user_content_profile($pdo, $userId, $userColumns, $jobColumns, $RECOMMENDATION_CONFIG);

        $cacheKey = 'recommend_cache_' . $userId . '_' . md5(json_encode([
            'category' => $profile['preferred_category_key'] ?? '',
            'experience' => strtolower((string) ($profile['experience_level'] ?? '')),
            'skills' => $profile['skills'] ?? [],
            'application_profile' => $profile['application_profile'] ?? [],
        ]));
        $cached = $_SESSION[$cacheKey] ?? null;
        if (is_array($cached)) {
            $age = time() - (int) ($cached['ts'] ?? 0);
            if ($age >= 0 && $age < (int) $RECOMMENDATION_CONFIG['cache_ttl']) {
                return $cached['data'] ?? [];
            }
        }

        if (!recommend_profile_has_primary_content($profile)) {
            $fallback = recommend_fallback_jobs($pdo, $userId, $limit, $jobColumns);
            $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $fallback];
            return $fallback;
        }

        $rows = recommend_fetch_candidate_jobs($pdo, $userId, $jobColumns, (int) $RECOMMENDATION_CONFIG['candidate_days']);
        $scored = [];

        foreach ($rows as $job) {
            if (recommend_is_expired($job)) {
                continue;
            }

            $match = recommend_score_job($job, $profile, $RECOMMENDATION_CONFIG);
            if (($match['score'] ?? 0) <= 0) {
                continue;
            }

            $job['score'] = (int) ($match['score'] ?? 0);
            $job['reasons'] = $match['reasons'] ?? [];
            $job['reason_text'] = $match['reason_text'] ?? '';
            $scored[] = $job;
        }

        usort($scored, function ($a, $b) {
            if (($a['score'] ?? 0) === ($b['score'] ?? 0)) {
                return strtotime((string) ($b['created_at'] ?? '')) <=> strtotime((string) ($a['created_at'] ?? ''));
            }
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        $result = !empty($scored)
            ? array_slice($scored, 0, $limit)
            : recommend_fallback_jobs($pdo, $userId, $limit, $jobColumns);

        $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $result];
        return $result;
    }
}

function recommend_load_user_content_profile($pdo, $userId, $userColumns, $jobColumns, $config): array {
    $user = recommend_fetch_user_profile($pdo, $userId, $userColumns);
    $preferredCategory = trim((string) ($user['preferred_category'] ?? ''));
    $experienceLevel = trim((string) ($user['experience_level'] ?? ''));
    $skills = recommend_get_user_skills($pdo, $userId, $userColumns);
    $applicationProfile = recommend_fetch_application_profile($pdo, $userId, (int) ($config['activity_days'] ?? 60), $config, $jobColumns);

    return [
        'preferred_category' => $preferredCategory,
        'preferred_category_key' => $preferredCategory !== '' ? strtolower($preferredCategory) : '',
        'experience_level' => $experienceLevel,
        'skills' => $skills,
        'keywords' => recommend_build_profile_keywords($preferredCategory, $experienceLevel, $skills, (int) ($config['max_keywords'] ?? 8)),
        'application_profile' => $applicationProfile,
    ];
}

function recommend_profile_has_primary_content($profile): bool {
    return (
        trim((string) ($profile['preferred_category'] ?? '')) !== '' ||
        trim((string) ($profile['experience_level'] ?? '')) !== '' ||
        !empty($profile['skills'])
    );
}

function recommend_fetch_candidate_jobs($pdo, $userId, $jobColumns, $candidateDays): array {
    $salarySelect = in_array('salary', $jobColumns, true) ? ", j.salary" : ", '' AS salary";
    $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
    $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";

    $sql = "
        SELECT
            j.id, j.title, COALESCE(c.name, j.company) AS company, j.company_id, j.location, j.type, j.category,
            j.experience_level, j.description, j.created_at, j.application_duration
            {$salarySelect}
            {$deadlineSelect}
            {$skillsRequiredSelect},
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        LEFT JOIN job_skills js ON js.job_id = j.id
        LEFT JOIN skills s ON s.id = js.skill_id
        LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
        WHERE j.status = 'active'
          AND (j.company_id IS NULL OR c.is_approved = 1)
          AND j.is_approved = 1
          AND a.id IS NULL
          AND j.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY j.id
        ORDER BY j.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ii", $userId, $candidateDays);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

function recommend_fallback_jobs($pdo, $userId, $limit, $jobColumns): array {
    $salarySelect = in_array('salary', $jobColumns, true) ? ", j.salary" : ", '' AS salary";
    $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
    $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
    $fetchLimit = max(((int) $limit) * 3, 12);

    $sql = "
        SELECT
            j.id, j.title, COALESCE(c.name, j.company) AS company, j.company_id, j.location, j.type, j.category,
            j.experience_level, j.description, j.created_at, j.application_duration
            {$salarySelect}
            {$deadlineSelect}
            {$skillsRequiredSelect},
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
        FROM jobs j
        LEFT JOIN companies c ON c.id = j.company_id
        LEFT JOIN job_skills js ON js.job_id = j.id
        LEFT JOIN skills s ON s.id = js.skill_id
        LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
        WHERE j.status = 'active'
          AND (j.company_id IS NULL OR c.is_approved = 1)
          AND j.is_approved = 1
          AND a.id IS NULL
        GROUP BY j.id
        ORDER BY j.created_at DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ii", $userId, $fetchLimit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $fallback = [];
    foreach ($rows as $job) {
        if (recommend_is_expired($job)) {
            continue;
        }

        $job['score'] = 0;
        $job['reasons'] = ['Latest approved jobs'];
        $job['reason_text'] = 'Latest approved jobs';
        $fallback[] = $job;

        if (count($fallback) >= (int) $limit) {
            break;
        }
    }

    return $fallback;
}

function recommend_is_job_seeker($pdo, $userId): bool {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return ($row['role'] ?? 'seeker') === 'seeker';
}

function recommend_fetch_application_profile($pdo, $userId, $days, $config, $jobColumns): array {
    $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
    $sql = "
        SELECT j.category, j.experience_level
            {$skillsRequiredSelect},
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        LEFT JOIN job_skills js ON js.job_id = j.id
        LEFT JOIN skills s ON s.id = js.skill_id
        WHERE a.user_id = ? AND a.applied_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY a.job_id
        ORDER BY a.applied_at DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ii", $userId, $days);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $categoryCounts = [];
    $experienceCounts = [];
    $skillCounts = [];

    foreach ($rows as $row) {
        $category = trim((string) ($row['category'] ?? ''));
        if ($category !== '') {
            $categoryKey = strtolower($category);
            $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
        }

        $experience = trim((string) ($row['experience_level'] ?? ''));
        if ($experience !== '') {
            $experienceKey = strtolower($experience);
            $experienceCounts[$experienceKey] = ($experienceCounts[$experienceKey] ?? 0) + 1;
        }

        foreach (recommend_get_job_skills($row) as $skill) {
            $skillCounts[$skill] = ($skillCounts[$skill] ?? 0) + 1;
        }
    }

    return [
        'categories' => recommend_top_keys($categoryCounts, (int) ($config['max_categories'] ?? 5)),
        'experience_levels' => recommend_top_keys($experienceCounts, (int) ($config['max_categories'] ?? 5)),
        'skills' => recommend_top_keys($skillCounts, (int) ($config['max_keywords'] ?? 8)),
    ];
}

function recommend_top_keys($counts, $limit): array {
    if (empty($counts)) {
        return [];
    }

    arsort($counts);
    return array_slice(array_keys($counts), 0, (int) $limit);
}

function recommend_tokenize($text): array {
    $text = strtolower((string) $text);
    if ($text === '') {
        return [];
    }

    $stopWords = [
        'and' => true,
        'the' => true,
        'for' => true,
        'with' => true,
        'from' => true,
        'job' => true,
        'jobs' => true,
        'role' => true,
        'level' => true,
    ];

    $text = preg_replace('/[^a-z0-9\s]+/i', ' ', $text);
    $parts = preg_split('/\s+/', $text);
    $tokens = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || (strlen($part) < 2 && !ctype_digit($part)) || isset($stopWords[$part])) {
            continue;
        }
        $tokens[] = $part;
    }

    return array_values(array_unique($tokens));
}

function recommend_build_profile_keywords($preferredCategory, $experienceLevel, $skills, $limit): array {
    $keywordCounts = [];

    foreach (recommend_tokenize($preferredCategory) as $token) {
        $keywordCounts[$token] = ($keywordCounts[$token] ?? 0) + 2;
    }

    foreach (recommend_tokenize($experienceLevel) as $token) {
        $keywordCounts[$token] = ($keywordCounts[$token] ?? 0) + 1;
    }

    foreach ($skills as $skill) {
        foreach (recommend_tokenize($skill) as $token) {
            $keywordCounts[$token] = ($keywordCounts[$token] ?? 0) + 3;
        }
    }

    return recommend_top_keys($keywordCounts, $limit);
}

function recommend_text_overlap($profileKeywords, $job, $cap = 5): array {
    if (empty($profileKeywords)) {
        return [];
    }

    $jobText = trim((string) ($job['title'] ?? '') . ' ' . (string) ($job['description'] ?? ''));
    if ($jobText === '') {
        return [];
    }

    $jobTokens = recommend_tokenize($jobText);
    if (empty($jobTokens)) {
        return [];
    }

    $jobLookup = array_fill_keys($jobTokens, true);
    $matches = [];

    foreach ($profileKeywords as $keyword) {
        $keyword = strtolower(trim((string) $keyword));
        if ($keyword !== '' && isset($jobLookup[$keyword])) {
            $matches[] = recommend_format_term($keyword);
            if (count($matches) >= (int) $cap) {
                break;
            }
        }
    }

    return array_values(array_unique($matches));
}

function recommend_score_job($job, $profile, $config): array {
    $weights = $config['weights'] ?? [];
    $maxOverlap = (int) ($config['max_overlap'] ?? 5);

    $score = 0;
    $reasonScores = [];
    $primaryScore = 0;

    $preferredCategoryKey = (string) ($profile['preferred_category_key'] ?? '');
    $preferredExperienceLevel = trim((string) ($profile['experience_level'] ?? ''));
    $userSkills = $profile['skills'] ?? [];
    $profileKeywords = $profile['keywords'] ?? [];

    $jobCategory = trim((string) ($job['category'] ?? ''));
    $jobCategoryKey = $jobCategory !== '' ? strtolower($jobCategory) : '';
    $jobExperienceLevel = trim((string) ($job['experience_level'] ?? ''));
    $jobSkills = recommend_get_job_skills($job);

    // This is a content-based algorithm.
    // Category, experience, and skills are the main matching signals.
    if ($preferredCategoryKey !== '' && $jobCategoryKey !== '' && $preferredCategoryKey === $jobCategoryKey) {
        $primaryScore += (int) ($weights['category_match'] ?? 0);
        $reasonScores[] = [(int) ($weights['category_match'] ?? 0), 'Matches your preferred category'];
    }

    if ($preferredExperienceLevel !== '' && $jobExperienceLevel !== '' && strcasecmp($preferredExperienceLevel, $jobExperienceLevel) === 0) {
        $primaryScore += (int) ($weights['experience_match'] ?? 0);
        $reasonScores[] = [(int) ($weights['experience_match'] ?? 0), 'Matches your experience level'];
    }

    $userSkillMatches = recommend_skill_overlap($userSkills, $jobSkills, $maxOverlap);
    if (!empty($userSkillMatches)) {
        $skillScore = count($userSkillMatches) * (int) ($weights['skill_match'] ?? 0);
        $primaryScore += $skillScore;
        $reasonScores[] = [$skillScore, 'Matches your skills: ' . implode(', ', array_slice($userSkillMatches, 0, 3))];
    }

    $textMatches = recommend_text_overlap($profileKeywords, $job, $maxOverlap);
    if (!empty($textMatches)) {
        $textScore = count($textMatches) * (int) ($weights['text_match'] ?? 0);
        $primaryScore += $textScore;
        $reasonScores[] = [$textScore, 'Relevant to your profile: ' . implode(', ', array_slice($textMatches, 0, 3))];
    }

    if ($primaryScore <= 0) {
        return [
            'score' => 0,
            'reasons' => [],
            'reason_text' => '',
            'primary_match' => false,
        ];
    }

    $score += $primaryScore;

    // Previous applications are only a small supporting signal.
    $applicationProfile = $profile['application_profile'] ?? [];
    $supportScore = 0;
    $appliedCategories = $applicationProfile['categories'] ?? [];
    $appliedExperienceLevels = $applicationProfile['experience_levels'] ?? [];
    $appliedSkills = $applicationProfile['skills'] ?? [];

    if ($jobCategoryKey !== '' && in_array($jobCategoryKey, $appliedCategories, true)) {
        $supportScore += (int) ($weights['applied_category_match'] ?? 0);
    }

    if ($jobExperienceLevel !== '' && in_array(strtolower($jobExperienceLevel), $appliedExperienceLevels, true)) {
        $supportScore += (int) ($weights['applied_experience_match'] ?? 0);
    }

    $appliedSkillMatches = recommend_skill_overlap($appliedSkills, $jobSkills, $maxOverlap);
    if (!empty($appliedSkillMatches)) {
        $supportScore += count($appliedSkillMatches) * (int) ($weights['applied_skill_match'] ?? 0);
    }

    if ($supportScore > 0) {
        $score += $supportScore;
        $reasonScores[] = [$supportScore, 'Related to your previous applications'];
    }

    $recency = recommend_recency_bonus($job['created_at'] ?? '', (int) ($config['candidate_days'] ?? 90), (int) ($weights['recency_boost'] ?? 0));
    if ($recency > 0) {
        $score += $recency;
        $reasonScores[] = [$recency, 'Recently posted'];
    }

    usort($reasonScores, function ($a, $b) {
        return ($b[0] ?? 0) <=> ($a[0] ?? 0);
    });

    $topReasons = recommend_unique_reasons($reasonScores, 3);

    return [
        'score' => $score,
        'reasons' => $topReasons,
        'reason_text' => implode(' | ', $topReasons),
        'primary_match' => true,
    ];
}

function recommend_fetch_user_profile($pdo, $userId, $userColumns): array {
    $select = [];
    $select[] = in_array('preferred_category', $userColumns, true) ? 'preferred_category' : "'' AS preferred_category";
    $select[] = in_array('experience_level', $userColumns, true) ? 'experience_level' : "'' AS experience_level";

    $sql = "SELECT " . implode(', ', $select) . " FROM users WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : [];
    $stmt->close();
    return $row ?: [];
}

function recommend_get_user_skills($pdo, $userId, $userColumns): array {
    $skills = [];

    if (in_array('skills', $userColumns, true)) {
        $stmt = $pdo->prepare("SELECT skills FROM users WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $row = $result ? $result->fetch_assoc() : null;
                if (!empty($row['skills'])) {
                    $skills = recommend_parse_skills($row['skills']);
                }
            }
            $stmt->close();
        }
    }

    if (empty($skills) && recommend_table_exists($pdo, 'user_skills') && recommend_table_exists($pdo, 'skills')) {
        $sql = "
            SELECT s.name
            FROM user_skills us
            INNER JOIN skills s ON s.id = us.skill_id
            WHERE us.user_id = ?
        ";
        $stmt = $pdo->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
                foreach ($rows as $row) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        $skills[] = strtolower($name);
                    }
                }
            }
            $stmt->close();
        }
    }

    return array_values(array_unique(array_filter($skills)));
}

function recommend_get_job_skills($job): array {
    $skills = [];

    if (!empty($job['skills_required'])) {
        $skills = array_merge($skills, recommend_parse_skills($job['skills_required']));
    }

    if (!empty($job['skill_list'])) {
        $skills = array_merge($skills, recommend_parse_skills($job['skill_list']));
    }

    return array_values(array_unique(array_filter($skills)));
}

function recommend_parse_skills($skillList): array {
    $skillList = (string) $skillList;
    if (trim($skillList) === '') {
        return [];
    }

    $normalized = str_replace(["\r\n", "\r", "\n", ";", "|"], ',', $skillList);
    $normalized = preg_replace('/\s*\/\s*/', ',', $normalized);
    $parts = preg_split('/,/', $normalized);

    $skills = [];
    foreach ($parts as $part) {
        $part = strtolower(trim(preg_replace('/\s+/', ' ', (string) $part)));
        if ($part !== '') {
            $skills[] = $part;
        }
    }

    return array_values(array_unique($skills));
}

function recommend_normalize_skill_string($skillList): string {
    $skills = recommend_parse_skills($skillList);
    if (empty($skills)) {
        return '';
    }

    $formatted = [];
    foreach ($skills as $skill) {
        $formatted[] = recommend_format_term($skill);
    }

    return implode(', ', $formatted);
}

function recommend_skill_overlap($sourceSkills, $targetSkills, $cap = 5): array {
    if (empty($sourceSkills) || empty($targetSkills)) {
        return [];
    }

    $targetLookup = array_fill_keys(array_map('strtolower', $targetSkills), true);
    $matches = [];

    foreach ($sourceSkills as $skill) {
        $key = strtolower((string) $skill);
        if ($key !== '' && isset($targetLookup[$key])) {
            $matches[] = recommend_format_term($key);
            if (count($matches) >= (int) $cap) {
                break;
            }
        }
    }

    return array_values(array_unique($matches));
}

function recommend_unique_reasons($reasonScores, $limit = 3): array {
    $reasons = [];
    $seen = [];

    foreach ($reasonScores as $pair) {
        $reason = trim((string) ($pair[1] ?? ''));
        if ($reason === '') {
            continue;
        }

        $key = strtolower($reason);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $reasons[] = $reason;
        if (count($reasons) >= (int) $limit) {
            break;
        }
    }

    return $reasons;
}

function recommend_format_term($value): string {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return '';
    }

    $special = [
        'api' => 'API',
        'apis' => 'APIs',
        'css' => 'CSS',
        'html' => 'HTML',
        'ios' => 'iOS',
        'js' => 'JS',
        'mysql' => 'MySQL',
        'php' => 'PHP',
        'postgresql' => 'PostgreSQL',
        'qa' => 'QA',
        'sql' => 'SQL',
        'ui' => 'UI',
        'ux' => 'UX',
    ];

    $parts = preg_split('/\s+/', $value);
    $formatted = [];
    foreach ($parts as $part) {
        $formatted[] = $special[$part] ?? ucfirst($part);
    }

    return implode(' ', $formatted);
}

function recommend_recency_bonus($createdAt, $rangeDays, $maxBoost): int {
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '' || $maxBoost <= 0) {
        return 0;
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return 0;
    }

    $days = (int) floor((time() - $timestamp) / 86400);
    if ($days < 0) {
        $days = 0;
    }

    $rangeDays = max(1, (int) $rangeDays);
    $days = min($days, $rangeDays);
    $bonus = (int) round($maxBoost * (($rangeDays - $days) / $rangeDays));
    return max(0, min((int) $maxBoost, $bonus));
}

function recommend_is_expired($job): bool {
    $deadline = $job['deadline'] ?? '';
    if ($deadline !== '') {
        $deadlineTs = strtotime((string) $deadline);
        if ($deadlineTs !== false && $deadlineTs < time()) {
            return true;
        }
    }

    if (function_exists('job_expiration_timestamp')) {
        $expires = job_expiration_timestamp($job['created_at'] ?? '', $job['application_duration'] ?? '');
        return $expires !== null && time() > $expires;
    }

    return false;
}

function recommend_table_exists($pdo, $table): bool {
    $table = trim((string) $table);
    if ($table === '') {
        return false;
    }

    $safe = $pdo->real_escape_string($table);
    $result = $pdo->query("SHOW TABLES LIKE '{$safe}'");
    return $result && $result->num_rows > 0;
}

function recommend_table_columns($pdo, $table): array {
    $columns = [];
    $result = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    return $columns;
}

if (!function_exists('recommend_job_match_for_user')) {
    function recommend_job_match_for_user($pdo, $userId, $job): array {
        global $RECOMMENDATION_CONFIG;

        $userId = (int) $userId;
        if ($userId <= 0 || !$pdo || empty($job)) {
            return ['matched' => false, 'score' => 0, 'reasons' => []];
        }

        $userColumns = recommend_table_columns($pdo, 'users');
        $jobColumns = recommend_table_columns($pdo, 'jobs');
        $profile = recommend_load_user_content_profile($pdo, $userId, $userColumns, $jobColumns, $RECOMMENDATION_CONFIG);
        if (!recommend_profile_has_primary_content($profile)) {
            return ['matched' => false, 'score' => 0, 'reasons' => []];
        }

        $result = recommend_score_job($job, $profile, $RECOMMENDATION_CONFIG);

        return [
            'matched' => !empty($result['primary_match']),
            'score' => (int) ($result['score'] ?? 0),
            'reasons' => $result['reasons'] ?? [],
        ];
    }
}

if (!function_exists('recommend_matching_seekers_for_job')) {
    function recommend_matching_seekers_for_job($pdo, $jobId): array {
        $jobId = (int) $jobId;
        if ($jobId <= 0 || !$pdo) {
            return [];
        }

        $jobColumns = recommend_table_columns($pdo, 'jobs');
        $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
        $job = db_query_all("
            SELECT
                j.id, j.title, j.category, j.experience_level, j.description, j.created_at, j.application_duration
                {$skillsRequiredSelect},
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
            FROM jobs j
            LEFT JOIN job_skills js ON js.job_id = j.id
            LEFT JOIN skills s ON s.id = js.skill_id
            WHERE j.id = ?
            GROUP BY j.id
            LIMIT 1
        ", "i", [$jobId])[0] ?? null;

        if (!$job) {
            return [];
        }

        $users = db_query_all("
            SELECT id
            FROM users
            WHERE role = 'seeker'
              AND account_status = 'active'
              AND is_active = 1
            ORDER BY id ASC
        ");

        $matches = [];
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $existingApplication = db_query_value(
                "SELECT id FROM applications WHERE user_id = ? AND job_id = ? LIMIT 1",
                "ii",
                [$userId, $jobId],
                null
            );
            if ($existingApplication) {
                continue;
            }

            $result = recommend_job_match_for_user($pdo, $userId, $job);
            if (!empty($result['matched'])) {
                $matches[] = [
                    'user_id' => $userId,
                    'score' => (int) ($result['score'] ?? 0),
                    'reasons' => $result['reasons'] ?? [],
                ];
            }
        }

        usort($matches, function ($a, $b) {
            return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
        });

        return $matches;
    }
}
