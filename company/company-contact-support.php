<?php
// company/company-contact-support.php
require '../db.php';
require_role('company');
require_once __DIR__ . '/../includes/support_helper.php';

$context = support_require_contact_access($conn);

$pageTitle = 'Contact Support';
$bodyClass = '';

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

require 'company-header.php';
?>

<div class="d-flex flex-column" style="gap: 0;">
    <!-- Contact Form Card -->
    <div class="card mt-0 mb-0" style="border-radius: 0.375rem 0.375rem 0 0; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-headset me-2 text-muted"></i>Contact Support</h5>
        </div>
        <div class="card-body pb-4">
            <p class="text-muted mb-3">Send your question or issue to the JobHub support team. We'll get back to you as soon as possible.</p>

            <?php if (!support_table_exists($conn)): ?>
                <div class="alert alert-warning mb-4">
                    <strong>Notice:</strong> Support module database table is missing. Please contact the administrator.
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> mb-4">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="../contact-support-process.php">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="row g-3 mb-4">
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
                            readonly
                            value="<?= htmlspecialchars($form['sender_email'] ?? '') ?>"
                        >
                        <small class="text-muted d-block mt-1">This email is linked to your account and cannot be changed.</small>
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
                            placeholder="Describe your issue or question..."
                        ><?= htmlspecialchars($form['message'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="mb-0">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                    <small class="text-muted d-block mt-2">
                        Your message will be stored in the JobHub support inbox and reviewed by our team.
                    </small>
                </div>
            </form>
        </div>
    </div>

    <!-- Support Information Section -->
    <div style="background-color: transparent; padding: 2rem 0;">
        <div class="container-lg">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="d-flex flex-column h-100" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.375rem; padding: 1.5rem; border-top: 4px solid #0d6efd;">
                        <h6 style="color: #0d6efd; font-weight: 600; margin-bottom: 1rem;">
                            <i class="fas fa-clock me-2"></i>Support Hours & Response
                        </h6>
                        <div>
                            <p class="mb-2" style="font-size: 0.95rem; color: #e0e0e0;">
                                <strong style="color: #ffffff;">Email:</strong> 
                                <a href="mailto:<?= htmlspecialchars(JOBHUB_SUPPORT_FROM_EMAIL) ?>" class="text-primary text-decoration-none" style="font-weight: 500;"><?= htmlspecialchars(JOBHUB_SUPPORT_FROM_EMAIL) ?></a>
                            </p>
                            <p class="mb-2" style="font-size: 0.95rem; color: #e0e0e0;">
                                <strong style="color: #ffffff;">Hours:</strong> 
                                <span style="color: #b0b0b0;">Sunday to Friday, 10:00 AM - 5:00 PM</span>
                            </p>
                            <p class="mb-0" style="font-size: 0.95rem; color: #e0e0e0;">
                                <strong style="color: #ffffff;">Response:</strong> 
                                <span style="color: #b0b0b0;">Within 24-48 hours</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="d-flex flex-column h-100" style="background-color: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 0.375rem; padding: 1.5rem; border-top: 4px solid #28a745;">
                        <h6 style="color: #28a745; font-weight: 600; margin-bottom: 1rem;">
                            <i class="fas fa-lightbulb me-2"></i>Tips for Better Support
                        </h6>
                        <div>
                            <ul style="list-style-position: inside; padding-left: 0; font-size: 0.95rem; margin-bottom: 0;">
                                <li style="color: #e0e0e0; margin-bottom: 0.5rem;">Be clear and specific about your issue</li>
                                <li style="color: #e0e0e0; margin-bottom: 0.5rem;">Mention the exact page or feature affected</li>
                                <li style="color: #e0e0e0; margin-bottom: 0.5rem;">Include relevant details (job IDs, dates, etc.)</li>
                                <li style="color: #e0e0e0; margin-bottom: 0;">Use your registered company email</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../footer.php'; ?>
