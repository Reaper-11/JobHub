<?php
require 'db.php';

update_expired_jobs($conn);

if (!function_exists('jobhub_browse_jobs_icon')) {
    function jobhub_browse_jobs_icon(string $name): string
    {
        return match ($name) {
            'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 4.75a5.75 5.75 0 1 0 0 11.5a5.75 5.75 0 0 0 0-11.5Zm0-1.5a7.25 7.25 0 1 1 0 14.5a7.25 7.25 0 0 1 0-14.5Zm6.14 12.33 4.11 4.11-1.06 1.06-4.11-4.11 1.06-1.06Z" fill="currentColor"/></svg>',
            'filter' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6.25A1.25 1.25 0 0 1 5.25 5h13.5a1.25 1.25 0 1 1 0 2.5H5.25A1.25 1.25 0 0 1 4 6.25Zm3 5.75A1.25 1.25 0 0 1 8.25 10h7.5a1.25 1.25 0 1 1 0 2.5h-7.5A1.25 1.25 0 0 1 7 12Zm3 5.75A1.25 1.25 0 0 1 11.25 16h1.5a1.25 1.25 0 1 1 0 2.5h-1.5A1.25 1.25 0 0 1 10 17.75Z" fill="currentColor"/></svg>',
            'sort' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.25 4a.75.75 0 0 1 .75.75v11.69l2.47-2.47 1.06 1.06-3.75 3.75a.75.75 0 0 1-1.06 0l-3.75-3.75 1.06-1.06L6.5 16.44V4.75A.75.75 0 0 1 7.25 4Zm9.5 16a.75.75 0 0 1-.75-.75V7.56l-2.47 2.47-1.06-1.06 3.75-3.75a.75.75 0 0 1 1.06 0l3.75 3.75-1.06 1.06L17.5 7.56v11.69a.75.75 0 0 1-.75.75Z" fill="currentColor"/></svg>',
            'location' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.75A6.25 6.25 0 0 0 5.75 10c0 4.49 5.08 9.31 5.29 9.51a1.39 1.39 0 0 0 1.92 0c.21-.2 5.29-5.02 5.29-9.51A6.25 6.25 0 0 0 12 3.75Zm0 14.4C10.31 16.46 7.25 12.98 7.25 10a4.75 4.75 0 1 1 9.5 0c0 2.98-3.06 6.46-4.75 8.15Zm0-10.4A2.25 2.25 0 1 0 14.25 10A2.25 2.25 0 0 0 12 7.75Z" fill="currentColor"/></svg>',
            'briefcase' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 4.75A2.75 2.75 0 0 1 11.75 2h.5A2.75 2.75 0 0 1 15 4.75V6h2.75A2.25 2.25 0 0 1 20 8.25v8.5A2.25 2.25 0 0 1 17.75 19h-11.5A2.25 2.25 0 0 1 4 16.75v-8.5A2.25 2.25 0 0 1 6.25 6H9V4.75Zm1.5 1.25h3V4.75a1.25 1.25 0 0 0-1.25-1.25h-.5A1.25 1.25 0 0 0 10.5 4.75V6Zm-4.25 1.5a.75.75 0 0 0-.75.75v1.9c1.73.84 4.08 1.35 6.5 1.35s4.77-.51 6.5-1.35v-1.9a.75.75 0 0 0-.75-.75h-11.5Zm12.25 4.26c-1.9.83-4.17 1.29-6.5 1.29s-4.6-.46-6.5-1.29v4.99c0 .41.34.75.75.75h11.5c.41 0 .75-.34.75-.75v-4.99Z" fill="currentColor"/></svg>',
            'salary' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c.41 0 .75.34.75.75V5h.85A4.4 4.4 0 0 1 18 9.4a.75.75 0 0 1-1.5 0A2.9 2.9 0 0 0 13.6 6.5h-3.2A2.9 2.9 0 0 0 10.4 12h3.2a4.4 4.4 0 1 1 0 8.8h-.85v1.45a.75.75 0 0 1-1.5 0V20.8h-.85A4.4 4.4 0 0 1 6 16.4a.75.75 0 0 1 1.5 0A2.9 2.9 0 0 0 10.4 19h3.2a2.9 2.9 0 0 0 0-5.8h-3.2a4.4 4.4 0 0 1 0-8.8h.85V3.75c0-.41.34-.75.75-.75Z" fill="currentColor"/></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="currentColor" opacity=".18"/><path d="M12 7.25a.75.75 0 0 1 .75.75v3.25H16a.75.75 0 0 1 0 1.5h-3.25V16a.75.75 0 0 1-1.5 0v-3.25H8a.75.75 0 0 1 0-1.5h3.25V8a.75.75 0 0 1 .75-.75Z" fill="currentColor"/></svg>',
        };
    }
}

if (!function_exists('jobhub_browse_jobs_initials')) {
    function jobhub_browse_jobs_initials(string $label): string
    {
        $clean = trim(preg_replace('/[^A-Za-z0-9 ]+/', ' ', $label));
        if ($clean === '') {
            return 'JB';
        }

        $parts = preg_split('/\s+/', $clean) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $initials .= strtoupper(substr($part, 0, 1));
            if (strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : strtoupper(substr($clean, 0, 2));
    }
}

if (!function_exists('jobhub_browse_jobs_posted_label')) {
    function jobhub_browse_jobs_posted_label(?string $dateValue): string
    {
        if ($dateValue === null || trim($dateValue) === '') {
            return 'Recently posted';
        }

        try {
            $postedAt = new DateTimeImmutable($dateValue);
            $now = new DateTimeImmutable('now');
            $diff = $postedAt->diff($now);
        } catch (Throwable $exception) {
            return 'Posted recently';
        }

        if ($diff->y > 0) {
            return 'Posted ' . $postedAt->format('M d, Y');
        }

        if ($diff->m > 0) {
            return 'Posted ' . $diff->m . ' month' . ($diff->m === 1 ? '' : 's') . ' ago';
        }

        if ($diff->d > 0) {
            return 'Posted ' . $diff->d . ' day' . ($diff->d === 1 ? '' : 's') . ' ago';
        }

        if ($diff->h > 0) {
            return 'Posted ' . $diff->h . ' hour' . ($diff->h === 1 ? '' : 's') . ' ago';
        }

        if ($diff->i > 0) {
            return 'Posted ' . $diff->i . ' minute' . ($diff->i === 1 ? '' : 's') . ' ago';
        }

        return 'Posted just now';
    }
}

if (!function_exists('jobhub_browse_jobs_page_url')) {
    function jobhub_browse_jobs_page_url(array $params, int $pageNumber): string
    {
        $params['page'] = $pageNumber;
        $queryString = http_build_query($params);

        return 'jobs.php' . ($queryString !== '' ? '?' . $queryString : '');
    }
}

$filters = jobhub_collect_job_filters();
$keyword = $filters['keyword'];
$activeLocation = $filters['activeLocation'];
$selectedCategory = $filters['selectedCategory'];
$selectedExperience = $filters['selectedExperience'];
$selectedJobType = $filters['selectedJobType'];
$activeSalary = $filters['activeSalary'];
$jobTypes = $filters['jobTypes'];
$isFilterActive = $filters['isFilterActive'];

$sortOptions = [
    'newest' => 'Newest First',
    'oldest' => 'Oldest First',
];

$activeSort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));
if (!array_key_exists($activeSort, $sortOptions)) {
    $activeSort = 'newest';
}

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$limit = 20;

jobhub_log_job_search($conn, current_user_id(), $filters);

$sortKey = $activeSort === 'oldest' ? 'oldest' : 'latest';
$totalJobs = jobhub_count_browse_jobs($filters);
$totalPages = $totalJobs > 0 ? (int) ceil($totalJobs / $limit) : 0;

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;
$jobs = jobhub_fetch_browse_jobs($filters, $limit, $sortKey, $offset);
$jobsCount = count($jobs);
$resultsStart = $totalJobs > 0 ? ($offset + 1) : 0;
$resultsEnd = $totalJobs > 0 ? ($offset + $jobsCount) : 0;
$paginationParams = array_filter(
    $_GET,
    static function ($value): bool {
        if (is_array($value)) {
            return !empty($value);
        }

        return $value !== '' && $value !== null;
    }
);
unset($paginationParams['page']);

$locationRows = db_query_all(
    "SELECT DISTINCT TRIM(j.location) AS location
     FROM jobs j
     LEFT JOIN companies c ON j.company_id = c.id
     WHERE (j.company_id IS NULL OR c.is_approved = 1)
       AND j.is_approved = 1
       AND j.status = 'active'
       AND j.location IS NOT NULL
       AND TRIM(j.location) <> ''
     ORDER BY location ASC"
);

$locationOptions = [];
foreach ($locationRows as $locationRow) {
    $locationLabel = trim((string)($locationRow['location'] ?? ''));
    if ($locationLabel === '') {
        continue;
    }

    $locationOptions[strtolower($locationLabel)] = $locationLabel;
}
$locationOptions = array_values($locationOptions);

if ($activeLocation !== '' && !in_array($activeLocation, $locationOptions, true)) {
    array_unshift($locationOptions, $activeLocation);
}

$pageTitle = 'Browse Jobs | JobHub';
$bodyClass = 'user-ui browse-jobs-body';
require 'header.php';
?>

<div class="browse-jobs-page">
    <section class="browse-jobs-hero">
        <div class="browse-jobs-hero-copy">
            <span class="browse-jobs-eyebrow">Career Opportunities</span>
            <h1>Browse Jobs</h1>
            <p>Find your next career move among active listings from approved employers.</p>
        </div>
        <div class="browse-jobs-hero-badge">
            <span class="browse-jobs-hero-badge__label">Active listings</span>
            <strong><?= (int)$totalJobs ?></strong>
        </div>
    </section>

    <form method="get" action="jobs.php" class="browse-jobs-form">
        <?php if ($selectedCategory !== ''): ?>
            <input type="hidden" name="filter" value="<?= htmlspecialchars($selectedCategory) ?>">
        <?php endif; ?>
        <?php if ($selectedExperience !== ''): ?>
            <input type="hidden" name="experience" value="<?= htmlspecialchars($selectedExperience) ?>">
        <?php endif; ?>
        <?php if ($activeSalary !== ''): ?>
            <input type="hidden" name="salary" value="<?= htmlspecialchars($activeSalary) ?>">
        <?php endif; ?>

        <div class="browse-jobs-layout">
            <aside class="jobs-filter-card" aria-label="Job filters">
                <div class="jobs-filter-card__header">
                    <span class="jobs-filter-card__icon" aria-hidden="true"><?= jobhub_browse_jobs_icon('filter') ?></span>
                    <div>
                        <h2>Filters</h2>
                        <p>Narrow the list by job type and preferred location.</p>
                    </div>
                </div>

                <div class="jobs-filter-fields">
                    <div class="jobs-filter-field">
                        <label for="browse-jobs-type">Job Type</label>
                        <select id="browse-jobs-type" name="job_type" class="form-select">
                            <option value="">All job types</option>
                            <?php foreach ($jobTypes as $jobType): ?>
                                <option value="<?= htmlspecialchars($jobType) ?>" <?= $selectedJobType === $jobType ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jobType) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="jobs-filter-field">
                        <label for="browse-jobs-location">Location</label>
                        <select id="browse-jobs-location" name="location" class="form-select">
                            <option value="">All locations</option>
                            <?php foreach ($locationOptions as $locationOption): ?>
                                <option value="<?= htmlspecialchars($locationOption) ?>" <?= $activeLocation === $locationOption ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($locationOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="jobs-filter-actions">
                    <button type="submit" class="jobs-filter-apply">Apply Filters</button>
                    <a href="jobs.php" class="jobs-filter-reset">Reset</a>
                </div>
            </aside>

            <section class="jobs-results-area">
                <div class="jobs-search-panel">
                    <div class="jobs-search-row">
                        <label class="jobs-search-box" for="browse-jobs-keyword">
                            <span class="jobs-search-box__icon" aria-hidden="true"><?= jobhub_browse_jobs_icon('search') ?></span>
                            <input
                                type="search"
                                id="browse-jobs-keyword"
                                name="q"
                                value="<?= htmlspecialchars($keyword) ?>"
                                class="form-control"
                                placeholder="Search by title, company, keyword">
                        </label>

                        <label class="jobs-sort-btn" for="browse-jobs-sort">
                            <span class="jobs-sort-btn__icon" aria-hidden="true"><?= jobhub_browse_jobs_icon('sort') ?></span>
                            <select id="browse-jobs-sort" name="sort" class="form-select">
                                <?php foreach ($sortOptions as $sortValue => $sortLabel): ?>
                                    <option value="<?= htmlspecialchars($sortValue) ?>" <?= $activeSort === $sortValue ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sortLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <button type="submit" class="jobs-search-submit">Search</button>
                    </div>

                    <div class="jobs-results-summary">
                        <div>
                            <h2><?= $isFilterActive ? 'Matching Jobs' : 'Available Jobs' ?></h2>
                            <p>
                                <?= $isFilterActive
                                    ? 'Showing approved active roles that match your current search.'
                                    : 'Showing approved active roles that are open right now.' ?>
                            </p>
                        </div>
                        <span class="jobs-results-count">
                            <?php if ($totalJobs > 0): ?>
                                Showing <?= (int)$resultsStart ?>-<?= (int)$resultsEnd ?> of <?= (int)$totalJobs ?>
                            <?php else: ?>
                                0 results
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($jobs)): ?>
                    <div class="jobs-empty-card">
                        <h3>No jobs found matching your filters.</h3>
                        <p>Try a broader keyword, a different location, or reset the filters to see more active listings.</p>
                        <a href="jobs.php" class="jobs-filter-reset jobs-filter-reset--inline">Reset Filters</a>
                    </div>
                <?php else: ?>
                    <div class="jobs-results-list">
                        <?php foreach ($jobs as $job): ?>
                            <?php
                            $companyName = trim((string)($job['company'] ?? '')) !== '' ? trim((string)$job['company']) : 'Company not specified';
                            $locationLabel = trim((string)($job['location'] ?? '')) !== '' ? trim((string)$job['location']) : 'Location not specified';
                            $jobTypeLabel = trim((string)($job['type'] ?? '')) !== '' ? trim((string)$job['type']) : 'Job type not specified';
                            $salaryLabel = jobhub_salary_display_value($job, 'Salary not specified');
                            $postedLabel = jobhub_browse_jobs_posted_label((string)($job['created_at'] ?? ''));
                            $description = trim((string)($job['description'] ?? ''));
                            $summary = $description === ''
                                ? 'No description provided for this role.'
                                : (function_exists('mb_substr') ? mb_substr($description, 0, 170) : substr($description, 0, 170));

                            if ($description !== '' && $summary !== $description) {
                                $summary .= '...';
                            }
                            ?>
                            <article class="job-list-card">
                                <div class="job-card-left">
                                    <div class="job-card-icon" aria-hidden="true"><?= htmlspecialchars(jobhub_browse_jobs_initials($companyName)) ?></div>

                                    <div class="job-card-content">
                                        <div class="job-card-heading">
                                            <h3 class="job-card-title">
                                                <a href="job-detail.php?id=<?= (int)$job['id'] ?>"><?= htmlspecialchars($job['title']) ?></a>
                                            </h3>
                                            <p class="job-card-company"><?= htmlspecialchars($companyName) ?></p>
                                        </div>

                                        <div class="job-card-meta">
                                            <span>
                                                <i aria-hidden="true"><?= jobhub_browse_jobs_icon('location') ?></i>
                                                <?= htmlspecialchars($locationLabel) ?>
                                            </span>
                                            <span>
                                                <i aria-hidden="true"><?= jobhub_browse_jobs_icon('briefcase') ?></i>
                                                <?= htmlspecialchars($jobTypeLabel) ?>
                                            </span>
                                            <span>
                                                <i aria-hidden="true"><?= jobhub_browse_jobs_icon('salary') ?></i>
                                                <?= htmlspecialchars($salaryLabel) ?>
                                            </span>
                                        </div>

                                        <p class="job-card-summary"><?= htmlspecialchars($summary) ?></p>
                                    </div>
                                </div>

                                <div class="job-card-actions">
                                    <span class="job-card-posted"><?= htmlspecialchars($postedLabel) ?></span>
                                    <a href="job-detail.php?id=<?= (int)$job['id'] ?>" class="job-card-link">View Details</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <nav class="jobs-pagination" aria-label="Browse jobs pagination">
                            <div class="jobs-pagination-list">
                                <?php if ($page > 1): ?>
                                    <a href="<?= htmlspecialchars(jobhub_browse_jobs_page_url($paginationParams, $page - 1)) ?>" class="jobs-page-link prev-link">Previous</a>
                                <?php endif; ?>

                                <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                                    <a
                                        href="<?= htmlspecialchars(jobhub_browse_jobs_page_url($paginationParams, $pageNumber)) ?>"
                                        class="jobs-page-link<?= $pageNumber === $page ? ' active' : '' ?>"
                                        <?= $pageNumber === $page ? 'aria-current="page"' : '' ?>>
                                        <?= (int)$pageNumber ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="<?= htmlspecialchars(jobhub_browse_jobs_page_url($paginationParams, $page + 1)) ?>" class="jobs-page-link next-link">Next</a>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </form>
</div>

<?php require 'footer.php'; ?>
