<?php
// admin/admin-dashboard.php
require '../db.php';
require_once '../includes/support_helper.php';
require_role('admin');

$supportCounts = support_fetch_counts($conn);

$stats = [
    'jobs' => db_query_value("SELECT COUNT(*) FROM jobs"),
    'users' => db_query_value("SELECT COUNT(*) FROM users"),
    'blocked_users' => db_query_value("SELECT COUNT(*) FROM users WHERE account_status = 'blocked'"),
    'applications' => db_query_value("SELECT COUNT(*) FROM applications"),
    'companies' => db_query_value("SELECT COUNT(*) FROM companies"),
    'pending' => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = 0"),
    'approved' => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = 1"),
    'rejected' => db_query_value("SELECT COUNT(*) FROM companies WHERE is_approved = -1"),
    'verification_pending' => db_query_value("SELECT COUNT(*) FROM companies WHERE verification_status = 'pending'"),
    'pending_jobs' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 0"),
    'approved_jobs' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = 1"),
    'rejected_jobs' => db_query_value("SELECT COUNT(*) FROM jobs WHERE is_approved = -1"),
    'recent_activities' => db_query_value("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", '', [], 0),
    'support_messages' => (int)$supportCounts['total'],
    'support_unread' => (int)$supportCounts['unread'],
];
?>

<?php require 'admin-header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon blue"><i class="fas fa-briefcase"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Total Jobs</div>
                <div class="stat-value"><?= number_format($stats['jobs']) ?></div>
                <div class="stat-sub">Pending: <?= (int)$stats['pending_jobs'] ?> &nbsp;·&nbsp; Approved: <?= (int)$stats['approved_jobs'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon green"><i class="fas fa-users"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Registered Users</div>
                <div class="stat-value"><?= number_format($stats['users']) ?></div>
                <div class="stat-sub">Blocked: <?= (int)$stats['blocked_users'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon purple"><i class="fas fa-file-alt"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Applications</div>
                <div class="stat-value"><?= number_format($stats['applications']) ?></div>
                <div class="stat-sub">All time</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon amber"><i class="fas fa-building"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Companies</div>
                <div class="stat-value"><?= number_format($stats['companies']) ?></div>
                <div class="stat-sub">Pending: <?= (int)$stats['pending'] ?> &nbsp;·&nbsp; Approved: <?= (int)$stats['approved'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon teal"><i class="fas fa-headset"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Support Messages</div>
                <div class="stat-value"><?= number_format($stats['support_messages']) ?></div>
                <div class="stat-sub">Unread: <?= (int)$stats['support_unread'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-card-icon red"><i class="fas fa-circle-exclamation"></i></div>
            <div class="stat-card-body">
                <div class="stat-label">Verification Pending</div>
                <div class="stat-value"><?= number_format($stats['verification_pending']) ?></div>
                <div class="stat-sub">Awaiting review</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-4">
    <div class="col-md-4">
        <a href="admin-jobs.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Jobs</h5>
                    <p class="text-muted small">Review pending jobs and approve or reject them</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin-companies.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Companies</h5>
                    <p class="text-muted small">Approve / reject company registrations</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="admin-users.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Manage Users</h5>
                    <p class="text-muted small">View and manage job seekers</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="company-verifications.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Review Verifications</h5>
                    <p class="text-muted small">Approve or reject company verification requests</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="activity-monitor.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Activity Monitor</h5>
                    <p class="text-muted small">Recent platform events in the last 7 days: <?= (int)$stats['recent_activities'] ?></p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="support-messages.php" class="text-decoration-none">
            <div class="card shadow-sm h-100 border-0 hover-lift">
                <div class="card-body">
                    <h5>Support Messages</h5>
                    <p class="text-muted small">Review support requests, reply to senders, and resolve issues.</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php require '../footer.php'; ?>
