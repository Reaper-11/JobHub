<?php
// includes/recommendation.php
// Context-based job recommendation logic (deterministic & explainable)

if (!function_exists('recommendJobs')) {
    function recommendJobs($pdo, $userId, $limit = 10): array {
        $limit = max(1, (int) $limit);
        $userId = (int) $userId;

        if ($userId <= 0 || !$pdo) {
            return [];
        }

        $userColumns = recommend_table_columns($pdo, 'users');
        $jobColumns = recommend_table_columns($pdo, 'jobs');
        $hasJobViews = recommend_table_exists($pdo, 'job_views');

        $user = recommend_fetch_user_profile($pdo, $userId, $userColumns);

        $preferredCategories = [];
        if (!empty($user['preferred_category'])) {
            $preferredCategories[] = $user['preferred_category'];
        }

        $sessionCategory = trim($_SESSION['last_selected_category'] ?? '');
        if ($sessionCategory !== '') {
            $preferredCategories[] = $sessionCategory;
        }
        $preferredCategories = array_values(array_unique(array_filter($preferredCategories)));

        $preferredLocation = trim($user['preferred_location'] ?? '');
        $preferredJobType = trim($user['preferred_job_type'] ?? '');

        $userSkills = recommend_get_user_skills($pdo, $userId, $userColumns);

        $sessionKeyword = trim($_SESSION['last_search_keyword'] ?? '');

        $recentViewCategories = [];
        if ($hasJobViews) {
            $recentViewCategories = recommend_recent_view_categories($pdo, $userId);
        }
        $dominantCategory = recommend_most_frequent($recentViewCategories);

        $hasContext = (
            !empty($preferredCategories) ||
            $preferredLocation !== '' ||
            $preferredJobType !== '' ||
            !empty($userSkills) ||
            $sessionKeyword !== '' ||
            $dominantCategory !== ''
        );

        if (!$hasContext) {
            return recommend_trending_jobs($pdo, $userId, $limit, $hasJobViews, $jobColumns);
        }

        $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";

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
            GROUP BY j.id
            ORDER BY j.created_at DESC
        ";

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
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        $scored = [];
        foreach ($rows as $job) {
            if (recommend_is_expired($job)) {
                continue;
            }

            $score = 0;
            $reasonScores = [];

            $jobCategory = trim($job['category'] ?? '');
            if ($jobCategory !== '') {
                if (in_array($jobCategory, $preferredCategories, true)) {
                    $score += 40;
                    $reasonScores[] = [40, "Matches your preferred category: {$jobCategory}"];
                } elseif ($dominantCategory !== '' && $jobCategory === $dominantCategory) {
                    $score += 40;
                    $reasonScores[] = [40, "Popular in your recent views: {$jobCategory}"];
                }
            }

            $jobSkills = recommend_parse_skills($job['skill_list'] ?? '');
            $skillOverlap = recommend_skill_overlap($userSkills, $jobSkills);
            if (!empty($skillOverlap)) {
                $score += 30;
                $sample = implode(', ', array_slice($skillOverlap, 0, 3));
                $reasonScores[] = [30, "Matches your skills: {$sample}"];
            }

            $jobLocation = trim($job['location'] ?? '');
            if ($preferredLocation !== '' && $jobLocation !== '') {
                if (recommend_location_exact($preferredLocation, $jobLocation)) {
                    $score += 15;
                    $reasonScores[] = [15, "Same location: {$jobLocation}"];
                } elseif (recommend_location_country_match($preferredLocation, $jobLocation)) {
                    $score += 8;
                    $reasonScores[] = [8, "Same country: {$jobLocation}"];
                }
            }

            $jobType = trim($job['type'] ?? '');
            if ($preferredJobType !== '' && $jobType !== '' && strcasecmp($jobType, $preferredJobType) === 0) {
                $score += 10;
                $reasonScores[] = [10, "Matches your preferred job type: {$jobType}"];
            }

            if ($sessionKeyword !== '') {
                $keywordMatch = recommend_keyword_match($sessionKeyword, $job);
                if ($keywordMatch) {
                    $score += 10;
                    $reasonScores[] = [10, "Matches your recent search: {$sessionKeyword}"];
                }
            }

            $recency = recommend_recency_bonus($job['created_at'] ?? '');
            if ($recency > 0) {
                $score += $recency;
                if ($recency >= 7) {
                    $reasonScores[] = [$recency, "Recently posted"];
                }
            }

            usort($reasonScores, function($a, $b) {
                return $b[0] <=> $a[0];
            });

            $topReasons = [];
            foreach ($reasonScores as $idx => $pair) {
                if ($idx >= 2) {
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

        return array_slice($scored, 0, $limit);
    }
}

function recommend_trending_jobs($pdo, $userId, $limit, $hasJobViews, $jobColumns): array {
    $deadlineSelect = in_array('deadline', $jobColumns, true) ? ", j.deadline" : "";
    $rows = [];

    if ($hasJobViews) {
        $sql = "
            SELECT
                j.id, j.title, j.company, j.company_id, j.location, j.type, j.category,
                j.description, j.created_at, j.application_duration
                {$deadlineSelect},
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ',') AS skill_list,
                COUNT(v.id) AS view_count
            FROM jobs j
            LEFT JOIN job_views v ON v.job_id = j.id AND v.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
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

function recommend_recent_view_categories($pdo, $userId): array {
    $sql = "
        SELECT v.job_id, j.category
        FROM job_views v
        INNER JOIN jobs j ON j.id = v.job_id
        WHERE v.user_id = ?
        ORDER BY v.viewed_at DESC
        LIMIT 20
    ";
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
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    $categories = [];
    foreach ($rows as $row) {
        $cat = trim($row['category'] ?? '');
        if ($cat !== '') {
            $categories[] = $cat;
        }
    }
    return $categories;
}

function recommend_most_frequent($items): string {
    if (empty($items)) {
        return '';
    }
    $counts = array_count_values($items);
    arsort($counts);
    return (string) array_key_first($counts);
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

function recommend_skill_overlap($userSkills, $jobSkills): array {
    if (empty($userSkills) || empty($jobSkills)) {
        return [];
    }
    $userMap = array_fill_keys($userSkills, true);
    $overlap = [];
    foreach ($jobSkills as $skill) {
        $s = strtolower($skill);
        if (isset($userMap[$s])) {
            $overlap[] = $s;
        }
    }
    return array_values(array_unique($overlap));
}

function recommend_location_exact($preferred, $jobLocation): bool {
    return strcasecmp(trim($preferred), trim($jobLocation)) === 0;
}

function recommend_location_country_match($preferred, $jobLocation): bool {
    $preferredCountry = recommend_extract_country($preferred);
    $jobCountry = recommend_extract_country($jobLocation);
    if ($preferredCountry === '' || $jobCountry === '') {
        return false;
    }
    return strcasecmp($preferredCountry, $jobCountry) === 0;
}

function recommend_extract_country($location): string {
    $parts = array_map('trim', explode(',', (string) $location));
    $parts = array_values(array_filter($parts));
    if (empty($parts)) {
        return '';
    }
    return (string) $parts[count($parts) - 1];
}

function recommend_keyword_match($keyword, $job): bool {
    $keyword = strtolower($keyword);
    if ($keyword === '') {
        return false;
    }

    $haystack = strtolower(
        ($job['title'] ?? '') . ' ' .
        ($job['company'] ?? '') . ' ' .
        ($job['description'] ?? '')
    );

    return strpos($haystack, $keyword) !== false;
}

function recommend_recency_bonus($createdAt): int {
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
    $days = min($days, 30);
    $bonus = (int) round(10 * ((30 - $days) / 30));
    return max(0, min(10, $bonus));
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
