<?php
require 'db.php';
require_once __DIR__ . '/includes/support_helper.php';

$context = support_require_contact_access($conn);

$pageTitle = 'Contact Support - JobHub';
$bodyClass = 'user-ui';

$flash = support_get_flash('public');
$oldInput = support_get_old_input('public');

$form = array_merge(
    [
        'sender_name' => $context['sender_name'],
        'sender_email' => $context['sender_email'],
        'sender_phone' => $context['sender_phone'],
        'subject' => '',
        'message' => '',
    ],
    $oldInput
);

require 'header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                    <div>
                        <h1 class="h3 mb-2">Contact Support</h1>
                        <p class="text-muted mb-0">Send your question or issue to the JobHub support team.</p>
                    </div>
                    <div>
                        <span class="badge text-bg-secondary">Source: <?= htmlspecialchars(support_role_label($context['sender_role'])) ?></span>
                    </div>
                </div>

                <?php if (!support_table_exists($conn)): ?>
                    <div class="alert alert-warning mb-4">
                        Support module database table is missing. Run the support SQL first, then reload this page.
                    </div>
                <?php endif; ?>

                <?php if ($flash): ?>
                    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-4">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="contact-support-process.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="sender_name"
                                class="form-control"
                                maxlength="120"
                                required
                                value="<?= htmlspecialchars($form['sender_name'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input
                                type="email"
                                name="sender_email"
                                class="form-control"
                                maxlength="120"
                                required
                                value="<?= htmlspecialchars($form['sender_email'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input
                                type="text"
                                name="sender_phone"
                                class="form-control"
                                maxlength="30"
                                placeholder="Optional"
                                value="<?= htmlspecialchars($form['sender_phone'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="subject"
                                class="form-control"
                                maxlength="200"
                                required
                                value="<?= htmlspecialchars($form['subject'] ?? '') ?>"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea
                                name="message"
                                class="form-control"
                                rows="7"
                                maxlength="5000"
                                required
                            ><?= htmlspecialchars($form['message'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                        <small class="text-muted">
                            Your message will be stored in the JobHub support inbox and reviewed by admin.
                        </small>
                        <button type="submit" class="btn btn-primary">Send Support Message</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Support Details</h2>
                        <p class="mb-2"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars(JOBHUB_SUPPORT_FROM_EMAIL) ?>"><?= htmlspecialchars(JOBHUB_SUPPORT_FROM_EMAIL) ?></a></p>
                        <p class="mb-2"><strong>Hours:</strong> Sunday to Friday, 10:00 AM to 5:00 PM</p>
                        <p class="mb-0"><strong>Access:</strong> Available only to logged-in users and companies.</p>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Before You Submit</h2>
                        <ul class="mb-0">
                            <li>Log in from the main JobHub login page before contacting support.</li>
                            <li>Explain the issue clearly and include the page or action where it happened.</li>
                            <li>For company verification problems, submit using your registered company email.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
