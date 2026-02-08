<?php
// includes/recommendation.php
// Context-based job recommendation logic (deterministic & explainable)

$RECOMMENDATION_CONFIG = [
    'weights' => [
        'category_match' => 40,
        'skill_keyword_match' => 6,
        'location_match' => 15,
        'job_type_match' => 10,
        'search_keyword_match' => 4,
        'viewed_similar_jobs' => 12,
        'applied_similar_jobs' => 14,
        'recency_boost' => 10,
    ],
    'candidate_days' => 90,
    'activity_days' => 60,
    'cache_ttl' => 600,
    'max_keywords' => 10,
    'max_categories' => 5,
    'max_locations' => 5,
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
        $hasSearchLogs = recommend_table_exists($pdo, 'job_search_logs');
        $hasViewLogs = recommend_table_exists($pdo, 'job_view_logs');

        $user = recommend_fetch_user_profile($pdo, $userId, $userColumns);

        $preferredCategories = [];
        if (!empty($user['preferred_category'])) {
            $preferredCategories[] = $user['preferred_category'];
        }
        $preferredCategories = array_values(array_unique(array_filter(array_map('trim', $preferredCategories))));
        $preferredCategoryKeys = array_values(array_unique(array_map('strtolower', $preferredCategories)));

        $preferredLocation = trim($user['preferred_location'] ?? '');
        $preferredJobType = trim($user['preferred_job_type'] ?? '');

        $userSkills = recommend_get_user_skills($pdo, $userId, $userColumns);

        $cacheKey = 'recommend_cache_' . $userId . '_' . md5(json_encode([
            'cats' => $preferredCategoryKeys,
            'loc' => $preferredLocation,
            'type' => $preferredJobType,
        ]));
        $cached = $_SESSION[$cacheKey] ?? null;
        if (is_array($cached)) {
            $age = time() - (int) ($cached['ts'] ?? 0);
            if ($age >= 0 && $age < (int) $RECOMMENDATION_CONFIG['cache_ttl']) {
                return $cached['data'] ?? [];
            }
        }

        $activityDays = (int) $RECOMMENDATION_CONFIG['activity_days'];
        $searchSignals = $hasSearchLogs ? recommend_fetch_search_signals($pdo, $userId, $activityDays, $RECOMMENDATION_CONFIG) : [];
        $viewSignals = $hasViewLogs ? recommend_fetch_view_signals($pdo, $userId, $activityDays, $RECOMMENDATION_CONFIG) : [];
        $appliedCategories = recommend_fetch_applied_categories($pdo, $userId, $activityDays, $RECOMMENDATION_CONFIG);

        $searchCategories = $searchSignals['categories'] ?? [];
        $searchKeywords = $searchSignals['keywords'] ?? [];
        $searchLocations = $searchSignals['locations'] ?? [];

        $viewCategories = $viewSignals['categories'] ?? [];

        $hasContext = (
            !empty($preferredCategories) ||
            $preferredLocation !== '' ||
            $preferredJobType !== '' ||
            !empty($userSkills) ||
            !empty($searchCategories) ||
            !empty($searchKeywords) ||
            !empty($viewCategories) ||
            !empty($appliedCategories)
        );

        if (!$hasContext) {
            $fallback = recommend_trending_jobs($pdo, $userId, $limit, $hasViewLogs, $jobColumns);
            $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $fallback];
            return $fallback;
        }

        $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
        $candidateDays = (int) $RECOMMENDATION_CONFIG['candidate_days'];

        $sql = "
            SELECT
                j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
                j.description, j.created_at, j.application_duration
                {$deadlineSelect},
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
            FROM jobs j
            LEFT JOIN job_skills js ON js.job_id = j.id
            LEFT JOIN skills s ON s.id = js.skill_id
            LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
            WHERE j.status = 'active'
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

        $weights = $RECOMMENDATION_CONFIG['weights'];
        $scored = [];

        foreach ($rows as $job) {
            if (recommend_is_expired($job)) {
                continue;
            }

            $score = 0;
            $reasonScores = [];

            $jobCategory = trim($job['category'] ?? '');
            if (!empty($preferredCategoryKeys)) {
                $jobCategoryKey = strtolower($jobCategory);
                if ($jobCategoryKey === '' || !in_array($jobCategoryKey, $preferredCategoryKeys, true)) {
                    continue;
                }
            }
            if ($jobCategory !== '') {
                $jobCategoryKey = strtolower($jobCategory);
                if (!empty($preferredCategoryKeys) && in_array($jobCategoryKey, $preferredCategoryKeys, true)) {
                    $score += $weights['category_match'];
                    $reasonScores[] = [$weights['category_match'], "Matches your preferred category: {$jobCategory}"];
                } elseif (in_array($jobCategoryKey, $searchCategories, true)) {
                    $score += $weights['category_match'];
                    $reasonScores[] = [$weights['category_match'], "Matches your recent searches: {$jobCategory}"];
                }
            }

            $skillMatches = recommend_keyword_overlap($userSkills, $job, $RECOMMENDATION_CONFIG['max_overlap']);
            if ($skillMatches > 0) {
                $skillScore = $skillMatches * $weights['skill_keyword_match'];
                $score += $skillScore;
                $sample = implode(', ', array_slice(recommend_overlap_samples($userSkills, $job), 0, 3));
                if ($sample !== '') {
                    $reasonScores[] = [$skillScore, "Matches your skills: {$sample}"];
                }
            }

            $jobLocation = trim($job['location'] ?? '');
            if ($jobLocation !== '') {
                if ($preferredLocation !== '' && recommend_location_exact($preferredLocation, $jobLocation)) {
                    $score += $weights['location_match'];
                    $reasonScores[] = [$weights['location_match'], "Same location: {$jobLocation}"];
                } elseif (!empty($searchLocations) && in_array(strtolower($jobLocation), $searchLocations, true)) {
                    $score += $weights['location_match'];
                    $reasonScores[] = [$weights['location_match'], "Matches your searched location: {$jobLocation}"];
                }
            }

            $jobType = trim($job['type'] ?? '');
            if ($preferredJobType !== '' && $jobType !== '' && strcasecmp($jobType, $preferredJobType) === 0) {
                $score += $weights['job_type_match'];
                $reasonScores[] = [$weights['job_type_match'], "Matches your preferred job type: {$jobType}"];
            }

            $searchKeywordMatches = recommend_keyword_match_count($searchKeywords, $job, $RECOMMENDATION_CONFIG['max_overlap']);
            if ($searchKeywordMatches > 0) {
                $searchScore = $searchKeywordMatches * $weights['search_keyword_match'];
                $score += $searchScore;
                $reasonScores[] = [$searchScore, "Matches your searches"];
            }

            if ($jobCategory !== '' && in_array(strtolower($jobCategory), $viewCategories, true)) {
                $score += $weights['viewed_similar_jobs'];
                $reasonScores[] = [$weights['viewed_similar_jobs'], "Similar to jobs you viewed"];
            }

            if ($jobCategory !== '' && in_array(strtolower($jobCategory), $appliedCategories, true)) {
                $score += $weights['applied_similar_jobs'];
                $reasonScores[] = [$weights['applied_similar_jobs'], "Similar to jobs you applied to"];
            }

            $recency = recommend_recency_bonus($job['created_at'] ?? '', $candidateDays, $weights['recency_boost']);
            if ($recency > 0) {
                $score += $recency;
                $reasonScores[] = [$recency, "Recently posted"];
            }

            usort($reasonScores, function($a, $b) {
                return $b[0] <=> $a[0];
            });

            $topReasons = [];
            foreach ($reasonScores as $idx => $pair) {
                if ($idx >= 3) {
                    break;
                }
                $topReasons[] = $pair[1];
            }

            $job['score'] = $score;
            $job['reasons'] = $topReasons;
            $job['reason_text'] = implode(' | ', $topReasons);
            $scored[] = $job;
        }

        usort($scored, function($a, $b) {
            if ($a['score'] === $b['score']) {
                return strtotime($b['created_at']) <=> strtotime($a['created_at']);
            }
            return $b['score'] <=> $a['score'];
        });

        $result = array_slice($scored, 0, $limit);
        $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $result];
        return $result;
    }
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
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return ($row['role'] ?? 'seeker') === 'seeker';
}

function recommend_fetch_search_signals($pdo, $userId, $days, $config): array {
    $sql = "
        SELECT keyword, category, location
        FROM job_search_logs
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY created_at DESC
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
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $keywordCounts = [];
    $categoryCounts = [];
    $locationCounts = [];

    foreach ($rows as $row) {
        $keyword = trim((string) ($row['keyword'] ?? ''));
        if ($keyword !== '') {
            foreach (recommend_tokenize($keyword) as $token) {
                $keywordCounts[$token] = ($keywordCounts[$token] ?? 0) + 1;
            }
        }

        $category = trim((string) ($row['category'] ?? ''));
        if ($category !== '') {
            $categoryKey = strtolower($category);
            $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
        }

        $location = trim((string) ($row['location'] ?? ''));
        if ($location !== '') {
            $locationKey = strtolower($location);
            $locationCounts[$locationKey] = ($locationCounts[$locationKey] ?? 0) + 1;
        }
    }

    return [
        'keywords' => recommend_top_keys($keywordCounts, $config['max_keywords']),
        'categories' => recommend_top_keys($categoryCounts, $config['max_categories']),
        'locations' => recommend_top_keys($locationCounts, $config['max_locations']),
    ];
}

function recommend_fetch_view_signals($pdo, $userId, $days, $config): array {
    $sql = "
        SELECT j.category
        FROM job_view_logs v
        INNER JOIN jobs j ON j.id = v.job_id
        WHERE v.user_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY v.created_at DESC
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
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $categoryCounts = [];
    foreach ($rows as $row) {
        $category = trim((string) ($row['category'] ?? ''));
        if ($category !== '') {
            $categoryKey = strtolower($category);
            $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
        }
    }

    return [
        'categories' => recommend_top_keys($categoryCounts, $config['max_categories']),
    ];
}

function recommend_fetch_applied_categories($pdo, $userId, $days, $config): array {
    $sql = "
        SELECT j.category
        FROM applications a
        INNER JOIN jobs j ON j.id = a.job_id
        WHERE a.user_id = ? AND a.applied_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
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
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $categoryCounts = [];
    foreach ($rows as $row) {
        $category = trim((string) ($row['category'] ?? ''));
        if ($category !== '') {
            $categoryKey = strtolower($category);
            $categoryCounts[$categoryKey] = ($categoryCounts[$categoryKey] ?? 0) + 1;
        }
    }

    return recommend_top_keys($categoryCounts, $config['max_categories']);
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
    $text = preg_replace('/[^a-z0-9\s]+/i', ' ', $text);
    $parts = preg_split('/\s+/', $text);
    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && strlen($part) >= 2) {
            $tokens[] = $part;
        }
    }
    return array_values(array_unique($tokens));
}

function recommend_keyword_overlap($userSkills, $job, $cap = 5): int {
    if (empty($userSkills)) {
        return 0;
    }
    $text = strtolower((string) ($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['skill_list'] ?? ''));
    $count = 0;
    foreach ($userSkills as $skill) {
        if ($skill !== '' && strpos($text, strtolower($skill)) !== false) {
            $count++;
            if ($count >= $cap) {
                break;
            }
        }
    }
    return $count;
}

function recommend_overlap_samples($userSkills, $job): array {
    $samples = [];
    if (empty($userSkills)) {
        return $samples;
    }
    $text = strtolower((string) ($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['skill_list'] ?? ''));
    foreach ($userSkills as $skill) {
        if ($skill !== '' && strpos($text, strtolower($skill)) !== false) {
            $samples[] = $skill;
        }
    }
    return $samples;
}

function recommend_keyword_match_count($keywords, $job, $cap = 5): int {
    if (empty($keywords)) {
        return 0;
    }
    $text = strtolower((string) ($job['title'] ?? '') . ' ' . ($job['description'] ?? '') . ' ' . ($job['company'] ?? ''));
    $count = 0;
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && strpos($text, strtolower($keyword)) !== false) {
            $count++;
            if ($count >= $cap) {
                break;
            }
        }
    }
    return $count;
}

function recommend_trending_jobs($pdo, $userId, $limit, $hasViewLogs, $jobColumns): array {
    $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
    $rows = [];

    if ($hasViewLogs) {
        $sql = "
            SELECT
                j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
                j.description, j.created_at, j.application_duration
                {$deadlineSelect},
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list,
                COUNT(v.id) AS view_count
            FROM jobs j
            LEFT JOIN job_view_logs v ON v.job_id = j.id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN job_skills js ON js.job_id = j.id
            LEFT JOIN skills s ON s.id = js.skill_id
            LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
            WHERE j.status = 'active'
              AND a.id IS NULL
            GROUP BY j.id
            ORDER BY view_count DESC, j.created_at DESC
            LIMIT ?
        ";
        $stmt = $pdo->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $userId, $limit);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
            }
            $stmt->close();
        }
    }

    $filtered = [];
    foreach ($rows as $job) {
        if (recommend_is_expired($job)) {
            continue;
        }
        $job['score'] = 0;
        $job['reasons'] = ["Trending this week"];
        $job['reason_text'] = "Trending this week";
        $filtered[] = $job;
    }

    if (!empty($filtered)) {
        return $filtered;
    }

    $sqlNewest = "
        SELECT
            j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
            j.description, j.created_at, j.application_duration
            {$deadlineSelect},
            GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list
        FROM jobs j
        LEFT JOIN job_skills js ON js.job_id = j.id
        LEFT JOIN skills s ON s.id = js.skill_id
        LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
        WHERE j.status = 'active'
          AND a.id IS NULL
        GROUP BY j.id
        ORDER BY j.created_at DESC
        LIMIT ?
    ";
    $stmt = $pdo->prepare($sqlNewest);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("ii", $userId, $limit);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $newest = [];
    foreach ($rows as $job) {
        if (recommend_is_expired($job)) {
            continue;
        }
        $job['score'] = 0;
        $job['reasons'] = ["Newest jobs"];
        $job['reason_text'] = "Newest jobs";
        $newest[] = $job;
    }

    return $newest;
}

function recommend_fetch_user_profile($pdo, $userId, $userColumns): array {
    $select = ['preferred_category'];

    if (in_array('preferred_location', $userColumns, true)) {
        $select[] = 'preferred_location';
    }
    if (in_array('preferred_job_type', $userColumns, true)) {
        $select[] = 'preferred_job_type';
    }
    if (in_array('skills', $userColumns, true)) {
        $select[] = 'skills';
    }

    $fields = implode(', ', array_unique($select));
    $sql = "SELECT {$fields} FROM users WHERE id = ? LIMIT 1";

    $stmt = $pdo->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : [];
    $stmt->close();
    return $row ?: [];
}

function recommend_get_user_skills($pdo, $userId, $userColumns): array {
    $skills = [];

    if (in_array('skills', $userColumns, true)) {
        $sql = "SELECT skills FROM users WHERE id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
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
                $res = $stmt->get_result();
                $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                foreach ($rows as $row) {
                    $name = trim($row['name'] ?? '');
                    if ($name !== '') {
                        $skills[] = strtolower($name);
                    }
                }
            }
            $stmt->close();
        }
    }

    $skills = array_values(array_unique(array_filter($skills)));
    return $skills;
}

function recommend_parse_skills($skillList): array {
    $skillList = strtolower((string) $skillList);
    if ($skillList === '') {
        return [];
    }

    $parts = preg_split('/[,\/]+/', $skillList);
    $tokens = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $sub = preg_split('/\s+/', $part);
        foreach ($sub as $t) {
            $t = trim($t);
            if ($t !== '' && strlen($t) >= 2) {
                $tokens[] = $t;
            }
        }
    }
    return array_values(array_unique($tokens));
}

function recommend_location_exact($preferred, $jobLocation): bool {
    return strcasecmp(trim($preferred), trim($jobLocation)) === 0;
}

function recommend_recency_bonus($createdAt, $rangeDays, $maxBoost): int {
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '') {
        return 0;
    }
    $ts = strtotime($createdAt);
    if ($ts === false) {
        return 0;
    }
    $days = (int) floor((time() - $ts) / 86400);
    if ($days < 0) {
        $days = 0;
    }
    $days = min($days, max(1, (int) $rangeDays));
    $bonus = (int) round($maxBoost * ((($rangeDays - $days) / $rangeDays)));
    return max(0, min($maxBoost, $bonus));
}

function recommend_is_expired($job): bool {
    $deadline = $job['deadline'] ?? '';
    if ($deadline !== '') {
        $deadlineTs = strtotime($deadline);
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
    $res = $pdo->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

function recommend_table_columns($pdo, $table): array {
    $cols = [];
    $res = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $cols[] = $row['Field'];
        }
    }
    return $cols;
}
