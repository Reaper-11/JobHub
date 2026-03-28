<?php
// includes/recommendation.php
// Context-based job recommendation logic (deterministic & explainable)

$RECOMMENDATION_CONFIG = [
    'weights' => [
        'category_match' => 35,
        'experience_match' => 16,
        'skill_match' => 10,
        'location_match' => 15,
        'job_type_match' => 10,
        'search_keyword_match' => 4,
        'viewed_similar_jobs' => 12,
        'applied_category_match' => 16,
        'applied_experience_match' => 8,
        'applied_skill_match' => 8,
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
        $preferredCategory = trim((string) ($user['preferred_category'] ?? ''));
        $preferredCategoryKey = $preferredCategory !== '' ? strtolower($preferredCategory) : '';
        $preferredLocation = trim((string) ($user['preferred_location'] ?? ''));
        $preferredJobType = trim((string) ($user['preferred_job_type'] ?? ''));
        $preferredExperienceLevel = trim((string) ($user['experience_level'] ?? ''));
        $userSkills = recommend_get_user_skills($pdo, $userId, $userColumns);

        $cacheKey = 'recommend_cache_' . $userId . '_' . md5(json_encode([
            'category' => $preferredCategoryKey,
            'location' => $preferredLocation,
            'type' => $preferredJobType,
            'experience' => $preferredExperienceLevel,
            'skills' => $userSkills,
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
        $applicationProfile = recommend_fetch_application_profile($pdo, $userId, $activityDays, $RECOMMENDATION_CONFIG, $jobColumns);

        $searchCategories = $searchSignals['categories'] ?? [];
        $searchKeywords = $searchSignals['keywords'] ?? [];
        $searchLocations = $searchSignals['locations'] ?? [];
        $viewCategories = $viewSignals['categories'] ?? [];
        $appliedCategories = $applicationProfile['categories'] ?? [];
        $appliedExperienceLevels = $applicationProfile['experience_levels'] ?? [];
        $appliedSkills = $applicationProfile['skills'] ?? [];

        $hasContext = (
            $preferredCategoryKey !== '' ||
            $preferredLocation !== '' ||
            $preferredJobType !== '' ||
            $preferredExperienceLevel !== '' ||
            !empty($userSkills) ||
            !empty($searchCategories) ||
            !empty($searchKeywords) ||
            !empty($searchLocations) ||
            !empty($viewCategories) ||
            !empty($appliedCategories) ||
            !empty($appliedExperienceLevels) ||
            !empty($appliedSkills)
        );

        if (!$hasContext) {
            $fallback = recommend_trending_jobs($pdo, $userId, $limit, $hasViewLogs, $jobColumns);
            $_SESSION[$cacheKey] = ['ts' => time(), 'data' => $fallback];
            return $fallback;
        }

        $salarySelect = in_array('salary', $jobColumns, true) ? ", j.salary" : ", '' AS salary";
        $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
        $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
        $candidateDays = (int) $RECOMMENDATION_CONFIG['candidate_days'];

        $sql = "
            SELECT
                j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
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

        $weights = $RECOMMENDATION_CONFIG['weights'];
        $scored = [];

        foreach ($rows as $job) {
            if (recommend_is_expired($job)) {
                continue;
            }

            $score = 0;
            $reasonScores = [];
            $jobCategory = trim((string) ($job['category'] ?? ''));
            $jobCategoryKey = $jobCategory !== '' ? strtolower($jobCategory) : '';
            $jobExperienceLevel = trim((string) ($job['experience_level'] ?? ''));
            $jobSkills = recommend_get_job_skills($job);

            if ($jobCategoryKey !== '') {
                if ($preferredCategoryKey !== '' && $jobCategoryKey === $preferredCategoryKey) {
                    $score += $weights['category_match'];
                    $reasonScores[] = [$weights['category_match'], "Matches your preferred category"];
                } elseif (in_array($jobCategoryKey, $searchCategories, true)) {
                    $score += $weights['category_match'];
                    $reasonScores[] = [$weights['category_match'], "Matches your recent searches"];
                }

                if (in_array($jobCategoryKey, $viewCategories, true)) {
                    $score += $weights['viewed_similar_jobs'];
                    $reasonScores[] = [$weights['viewed_similar_jobs'], "Similar to jobs you viewed"];
                }

                if (in_array($jobCategoryKey, $appliedCategories, true)) {
                    $score += $weights['applied_category_match'];
                    $reasonScores[] = [$weights['applied_category_match'], "Similar to jobs you applied for before"];
                }
            }

            if ($preferredExperienceLevel !== '' && $jobExperienceLevel !== '' && strcasecmp($preferredExperienceLevel, $jobExperienceLevel) === 0) {
                $score += $weights['experience_match'];
                $reasonScores[] = [$weights['experience_match'], "Matches your experience level"];
            }

            if ($jobExperienceLevel !== '' && in_array(strtolower($jobExperienceLevel), $appliedExperienceLevels, true)) {
                $score += $weights['applied_experience_match'];
                $reasonScores[] = [$weights['applied_experience_match'], "Similar to your previous applications"];
            }

            $userSkillMatches = recommend_skill_overlap($userSkills, $jobSkills, $RECOMMENDATION_CONFIG['max_overlap']);
            if (!empty($userSkillMatches)) {
                $skillScore = count($userSkillMatches) * $weights['skill_match'];
                $score += $skillScore;
                $reasonScores[] = [$skillScore, "Matches your skills: " . implode(', ', array_slice($userSkillMatches, 0, 3))];
            }

            $appliedSkillMatches = recommend_skill_overlap($appliedSkills, $jobSkills, $RECOMMENDATION_CONFIG['max_overlap']);
            if (!empty($appliedSkillMatches)) {
                $appliedSkillScore = count($appliedSkillMatches) * $weights['applied_skill_match'];
                $score += $appliedSkillScore;
                $reasonScores[] = [$appliedSkillScore, "Uses skills from jobs you applied to"];
            }

            $jobLocation = trim((string) ($job['location'] ?? ''));
            if ($jobLocation !== '') {
                if ($preferredLocation !== '' && recommend_location_exact($preferredLocation, $jobLocation)) {
                    $score += $weights['location_match'];
                    $reasonScores[] = [$weights['location_match'], "Same location"];
                } elseif (!empty($searchLocations) && in_array(strtolower($jobLocation), $searchLocations, true)) {
                    $score += $weights['location_match'];
                    $reasonScores[] = [$weights['location_match'], "Matches your searched location"];
                }
            }

            $jobType = trim((string) ($job['type'] ?? ''));
            if ($preferredJobType !== '' && $jobType !== '' && strcasecmp($jobType, $preferredJobType) === 0) {
                $score += $weights['job_type_match'];
                $reasonScores[] = [$weights['job_type_match'], "Matches your preferred job type"];
            }

            $searchKeywordMatches = recommend_keyword_match_count($searchKeywords, $job, $RECOMMENDATION_CONFIG['max_overlap']);
            if ($searchKeywordMatches > 0) {
                $searchScore = $searchKeywordMatches * $weights['search_keyword_match'];
                $score += $searchScore;
                $reasonScores[] = [$searchScore, "Matches your searches"];
            }

            $recency = recommend_recency_bonus($job['created_at'] ?? '', $candidateDays, $weights['recency_boost']);
            if ($recency > 0) {
                $score += $recency;
                $reasonScores[] = [$recency, "Recently posted"];
            }

            if ($score <= 0) {
                continue;
            }

            usort($reasonScores, function ($a, $b) {
                return $b[0] <=> $a[0];
            });

            $topReasons = recommend_unique_reasons($reasonScores, 3);
            $job['score'] = $score;
            $job['reasons'] = $topReasons;
            $job['reason_text'] = implode(' | ', $topReasons);
            $scored[] = $job;
        }

        usort($scored, function ($a, $b) {
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
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
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
        'categories' => recommend_top_keys($categoryCounts, $config['max_categories']),
        'experience_levels' => recommend_top_keys($experienceCounts, $config['max_categories']),
        'skills' => recommend_top_keys($skillCounts, $config['max_keywords']),
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
    $salarySelect = in_array('salary', $jobColumns, true) ? ", j.salary" : ", '' AS salary";
    $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
    $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
    $rows = [];

    if ($hasViewLogs) {
        $sql = "
            SELECT
                j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
                j.experience_level, j.description, j.created_at, j.application_duration
                {$salarySelect}
                {$deadlineSelect}
                {$skillsRequiredSelect},
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list,
                COUNT(v.id) AS view_count
            FROM jobs j
            LEFT JOIN companies c ON c.id = j.company_id
            LEFT JOIN job_view_logs v ON v.job_id = j.id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            LEFT JOIN job_skills js ON js.job_id = j.id
            LEFT JOIN skills s ON s.id = js.skill_id
            LEFT JOIN applications a ON a.job_id = j.id AND a.user_id = ?
            WHERE j.status = 'active'
              AND (j.company_id IS NULL OR c.is_approved = 1)
              AND j.is_approved = 1
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
    if (in_array('experience_level', $userColumns, true)) {
        $select[] = 'experience_level';
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
        $formatted[] = ucwords($skill);
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
            $matches[] = ucwords($key);
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

if (!function_exists('recommend_job_match_for_user')) {
    function recommend_job_match_for_user($pdo, $userId, $job): array {
        global $RECOMMENDATION_CONFIG;

        $userId = (int)$userId;
        if ($userId <= 0 || !$pdo || empty($job)) {
            return ['matched' => false, 'score' => 0, 'reasons' => []];
        }

        $userColumns = recommend_table_columns($pdo, 'users');
        $user = recommend_fetch_user_profile($pdo, $userId, $userColumns);
        if (empty($user)) {
            return ['matched' => false, 'score' => 0, 'reasons' => []];
        }

        $weights = $RECOMMENDATION_CONFIG['weights'];
        $score = 0;
        $reasons = [];

        $preferredCategory = strtolower(trim((string)($user['preferred_category'] ?? '')));
        $preferredExperience = trim((string)($user['experience_level'] ?? ''));
        $preferredLocation = trim((string)($user['preferred_location'] ?? ''));
        $preferredJobType = trim((string)($user['preferred_job_type'] ?? ''));
        $userSkills = recommend_get_user_skills($pdo, $userId, $userColumns);

        $jobCategory = strtolower(trim((string)($job['category'] ?? '')));
        $jobExperience = trim((string)($job['experience_level'] ?? ''));
        $jobLocation = trim((string)($job['location'] ?? ''));
        $jobType = trim((string)($job['type'] ?? ''));
        $jobSkills = recommend_get_job_skills($job);

        if ($preferredCategory !== '' && $jobCategory !== '' && $preferredCategory === $jobCategory) {
            $score += $weights['category_match'];
            $reasons[] = 'preferred category';
        }

        if ($preferredExperience !== '' && $jobExperience !== '' && strcasecmp($preferredExperience, $jobExperience) === 0) {
            $score += $weights['experience_match'];
            $reasons[] = 'experience level';
        }

        if ($preferredLocation !== '' && $jobLocation !== '' && recommend_location_exact($preferredLocation, $jobLocation)) {
            $score += $weights['location_match'];
            $reasons[] = 'preferred location';
        }

        if ($preferredJobType !== '' && $jobType !== '' && strcasecmp($preferredJobType, $jobType) === 0) {
            $score += $weights['job_type_match'];
            $reasons[] = 'job type';
        }

        $skillMatches = recommend_skill_overlap($userSkills, $jobSkills, $RECOMMENDATION_CONFIG['max_overlap']);
        if (!empty($skillMatches)) {
            $score += count($skillMatches) * $weights['skill_match'];
            $reasons[] = 'skills: ' . implode(', ', array_slice($skillMatches, 0, 3));
        }

        $matched = false;
        if ($preferredCategory !== '' && $jobCategory !== '' && $preferredCategory === $jobCategory) {
            $matched = true;
        } elseif (!empty($skillMatches)) {
            $matched = true;
        } elseif (
            $preferredExperience !== '' &&
            $jobExperience !== '' &&
            strcasecmp($preferredExperience, $jobExperience) === 0 &&
            (
                ($preferredCategory !== '' && $jobCategory !== '' && $preferredCategory === $jobCategory) ||
                !empty($skillMatches)
            )
        ) {
            $matched = true;
        }

        return [
            'matched' => $matched,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }
}

if (!function_exists('recommend_matching_seekers_for_job')) {
    function recommend_matching_seekers_for_job($pdo, $jobId): array {
        $jobId = (int)$jobId;
        if ($jobId <= 0 || !$pdo) {
            return [];
        }

        $jobColumns = recommend_table_columns($pdo, 'jobs');
        $skillsRequiredSelect = in_array('skills_required', $jobColumns, true) ? ", j.skills_required" : ", '' AS skills_required";
        $job = db_query_all("
            SELECT
                j.id, j.title, j.category, j.location, j.type, j.experience_level
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
            $userId = (int)($user['id'] ?? 0);
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
                    'score' => (int)($result['score'] ?? 0),
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
