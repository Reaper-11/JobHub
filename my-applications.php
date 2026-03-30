<?php
// my-applications.php
require 'db.php';
require_role('jobseeker');
$user_id = current_user_id() ?? 0;
$bodyClass = 'user-ui';
require 'header.php';

$sql = "SELECT a.id, a.job_id, a.status, a.response_message, a.cover_letter, a.applied_at,
               j.title, j.company, j.location, j.type
         FROM applications a
         JOIN jobs j ON a.job_id = j.id
         WHERE a.user_id = ?
        ORDER BY a.applied_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
    .view-message-btn {
        white-space: nowrap;
    }

    .message-modal[hidden] {
        display: none !important;
    }

    .message-modal {
        position: fixed;
        inset: 0;
        z-index: 1050;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: rgba(15, 23, 42, 0.68);
    }

    .message-modal-content {
        position: relative;
        width: min(100%, 620px);
        max-height: calc(100vh - 40px);
        overflow-y: auto;
        padding: 24px;
        border-radius: 16px;
        background: #ffffff;
        box-shadow: 0 24px 60px rgba(15, 23, 42, 0.22);
    }

    .message-modal-close {
        position: absolute;
        top: 12px;
        right: 14px;
        border: 0;
        background: transparent;
        color: #6c757d;
        font-size: 28px;
        line-height: 1;
        cursor: pointer;
    }

    .message-modal-close:hover,
    .message-modal-close:focus {
        color: #212529;
    }

    .message-modal-meta {
        margin-bottom: 10px;
    }

    .message-modal-label {
        color: #6c757d;
    }

    .message-modal-text {
        margin-top: 8px;
        padding: 12px 14px;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        background: #f8f9fa;
        white-space: pre-wrap;
        word-break: break-word;
    }

    @media (max-width: 576px) {
        .message-modal {
            padding: 12px;
        }

        .message-modal-content {
            padding: 20px 18px;
            border-radius: 12px;
        }
    }
</style>

<h1 class="mb-4">My Applications</h1>

<?php if (empty($applications)): ?>
    <div class="alert alert-info">
        You haven't applied to any jobs yet.
        <a href="index.php" class="alert-link">Browse jobs</a>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Job Title</th>
                    <th>Company</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Applied On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $app): ?>
                <tr>
                    <?php
                    $status = strtolower($app['status'] ?? 'pending');
                    $statusLabel = ucfirst($status);
                    $badge = match($status) {
                        'pending'     => 'bg-warning',
                        'shortlisted' => 'bg-primary',
                        'interview'   => 'bg-info',
                        'approved'    => 'bg-success',
                        'rejected'    => 'bg-danger',
                        default       => 'bg-secondary'
                    };
                    $responseMessage = trim((string)($app['response_message'] ?? ''));
                    $modalMessage = $responseMessage !== '' ? $responseMessage : 'No response from company yet.';
                    $modalMessageJson = htmlspecialchars(json_encode($modalMessage, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                    ?>
                    <td>
                        <a href="job-detail.php?id=<?= $app['job_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($app['title']) ?>
                        </a>
                    </td>
                    <td><?= htmlspecialchars($app['company']) ?></td>
                    <td><?= htmlspecialchars($app['location']) ?></td>
                    <td>
                        <span class="badge <?= $badge ?>"><?= htmlspecialchars($statusLabel) ?></span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['applied_at'])) ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary view-message-btn"
                                data-job-title="<?= htmlspecialchars($app['title'], ENT_QUOTES, 'UTF-8') ?>"
                                data-company-name="<?= htmlspecialchars($app['company'], ENT_QUOTES, 'UTF-8') ?>"
                                data-status="<?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>"
                                data-status-class="<?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?>"
                                data-message="<?= $modalMessageJson ?>"
                            >
                                View Message
                            </button>
                            <a href="my-application-edit.php?id=<?= $app['id'] ?>"
                               class="btn btn-sm btn-outline-primary">Edit Cover Letter</a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<div id="messageModal" class="message-modal" hidden>
    <div class="message-modal-content" role="dialog" aria-modal="true" aria-labelledby="messageModalTitle">
        <button type="button" class="message-modal-close" id="closeMessageModal" aria-label="Close message popup">&times;</button>
        <h3 id="messageModalTitle" class="h4 mb-3">Application Message</h3>
        <p class="message-modal-meta mb-2"><strong class="message-modal-label">Job Title:</strong> <span id="modalJobTitle"></span></p>
        <p class="message-modal-meta mb-2"><strong class="message-modal-label">Company:</strong> <span id="modalCompanyName"></span></p>
        <p class="message-modal-meta mb-0">
            <strong class="message-modal-label">Status:</strong>
            <span id="modalStatus" class="badge bg-secondary"></span>
        </p>
        <div class="mt-3">
            <strong class="message-modal-label">Message:</strong>
            <div id="modalMessageText" class="message-modal-text"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("messageModal");
    const closeButton = document.getElementById("closeMessageModal");
    const modalJobTitle = document.getElementById("modalJobTitle");
    const modalCompanyName = document.getElementById("modalCompanyName");
    const modalStatus = document.getElementById("modalStatus");
    const modalMessageText = document.getElementById("modalMessageText");
    const viewButtons = document.querySelectorAll(".view-message-btn");

    if (!modal || !closeButton || viewButtons.length === 0) {
        return;
    }

    const closeModal = function () {
        modal.setAttribute("hidden", "");
        document.body.classList.remove("overflow-hidden");
    };

    viewButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            let message = "No response from company yet.";

            try {
                message = JSON.parse(button.dataset.message || "\"No response from company yet.\"");
            } catch (error) {
                message = "No response from company yet.";
            }

            modalJobTitle.textContent = button.dataset.jobTitle || "Application Message";
            modalCompanyName.textContent = button.dataset.companyName || "Company";
            modalStatus.textContent = button.dataset.status || "Pending";
            modalStatus.className = "badge " + (button.dataset.statusClass || "bg-secondary");
            modalMessageText.textContent = message;
            modal.removeAttribute("hidden");
            document.body.classList.add("overflow-hidden");
        });
    });

    closeButton.addEventListener("click", closeModal);

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !modal.hasAttribute("hidden")) {
            closeModal();
        }
    });
});
</script>

<?php require 'footer.php'; ?>
