<?php
/**
 * Example Integration: Notifications in Applicant Page
 * 
 * This file demonstrates how to integrate notifications
 * into the admin applicant management page
 */

// At the top of applicant.php, after other includes:

require_once 'AdminNotificationIntegration.php';

// Initialize notification handler
$notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

// ============================================================================
// EXAMPLE 1: When an applicant is created/registered
// ============================================================================

function handleNewApplicant($conn, $adminId, $adminRole, $user_id, $applicant_name, $application_id)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'applicant_update',
        'Application Received',
        'We have received your loan application. Our team is reviewing your information.',
        'user_dashboard.php',
        'View Application Status',
        'normal'
    );

    // Notify admins
    $notificationHandler->broadcastToAdminRole(
        'admin1',
        'applicant_update',
        'New Application: ' . $applicant_name,
        'New loan application received from ' . $applicant_name,
        'applicant.php?id=' . $application_id,
        'Review Application',
        'high'
    );
}

// ============================================================================
// EXAMPLE 2: When applicant status changes (e.g., to "Under Review")
// ============================================================================

function handleApplicantStatusChange($conn, $adminId, $adminRole, $user_id, $applicant_name, $application_id, $old_status, $new_status)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $message_map = [
        'Under Review' => 'Your application is now under review by our credit team.',
        'Approved' => 'Congratulations! Your application has been approved. Proceeding to next steps.',
        'Rejected' => 'Your application has been reviewed. Please contact us for more information.',
        'Pending' => 'Your application is pending additional information or verification.'
    ];

    $user_message = $message_map[$new_status] ?? 'Your application status has been updated to: ' . $new_status;

    // Notify user
    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'applicant_update',
        'Application Status Changed',
        $user_message,
        'active_records.php',
        'View Details',
        $new_status === 'Approved' ? 'high' : 'normal'
    );

    // Log activity
    logActivity(
        $conn,
        $adminId,
        $adminRole,
        'update',
        'loan_application',
        "Changed applicant status from '$old_status' to '$new_status'",
        $application_id
    );

    // Notify audit
    $notificationHandler->notifyAuditTrail(
        'Admin User',
        'update',
        'applicant_status',
        $application_id
    );
}

// ============================================================================
// EXAMPLE 3: When applicant documents are requested
// ============================================================================

function handleDocumentRequest($conn, $adminId, $adminRole, $user_id, $applicant_name, $application_id, $missing_docs)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $doc_list = implode(', ', $missing_docs);

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'admin_alert',
        'Additional Documents Required',
        'We need the following documents to proceed with your application: ' . $doc_list,
        'user_dashboard.php?tab=documents',
        'Upload Documents',
        'high'
    );
}

// ============================================================================
// EXAMPLE 4: When applicant is rejected
// ============================================================================

function handleApplicantRejection($conn, $adminId, $adminRole, $user_id, $applicant_name, $application_id, $reason)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'admin_alert',
        'Application Status Update',
        'Your application could not be approved at this time. Reason: ' . $reason .
        ' You may reapply after 90 days.',
        'user_dashboard.php',
        'View Details',
        'normal'
    );

    // Notify admins of rejection
    $notificationHandler->broadcastToAdminRole(
        'admin1',
        'applicant_update',
        'Application Rejected: ' . $applicant_name,
        'Application rejected. Reason: ' . $reason,
        'applicant.php?id=' . $application_id,
        'View Application',
        'normal'
    );
}

// ============================================================================
// EXAMPLE 5: Real-time notification badge in applicant table
// ============================================================================

function getApplicantNotificationBadge($conn, $user_id)
{
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count
        FROM user_notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    $count = $data['unread_count'] ?? 0;

    if ($count > 0) {
        return '<span class="notification-badge" data-count="' . $count . '">' . $count . '</span>';
    }
    return '';
}

// ============================================================================
// USAGE IN APPLICANT PAGE (HTML)
// ============================================================================

?>

<!-- Example HTML structure for applicant table with notifications -->

<div class="applicant-row">
    <div class="applicant-name">
        <?php echo htmlspecialchars($applicant_name); ?>
        <?php echo getApplicantNotificationBadge($conn, $user_id); ?>
    </div>

    <div class="applicant-status">
        <select
            onchange="updateApplicantStatus(<?php echo $application_id; ?>, this.value, '<?php echo htmlspecialchars($applicant_name); ?>', '<?php echo $current_status; ?>')">
            <option value="Pending" <?php echo $current_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="Under Review" <?php echo $current_status === 'Under Review' ? 'selected' : ''; ?>>Under Review
            </option>
            <option value="Approved" <?php echo $current_status === 'Approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="Rejected" <?php echo $current_status === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
        </select>
    </div>

    <div class="applicant-actions">
        <button onclick="viewApplicantDetails(<?php echo $application_id; ?>)" class="btn btn-small">
            <i class="fas fa-eye"></i> View
        </button>
        <button
            onclick="sendDocumentRequest(<?php echo $application_id; ?>, '<?php echo htmlspecialchars($applicant_name); ?>')"
            class="btn btn-small">
            <i class="fas fa-file"></i> Request Docs
        </button>
    </div>
</div>

<script>
    // ============================================================================
    // JavaScript Functions for Applicant Page Notifications
    // ============================================================================

    function updateApplicantStatus(applicationId, newStatus, applicantName, oldStatus) {
        if (confirm('Update applicant status to ' + newStatus + '?')) {
            // AJAX call to update status
            fetch('process_applicant_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=update_status&application_id=' + applicationId +
                    '&old_status=' + oldStatus + '&new_status=' + newStatus +
                    '&applicant_name=' + encodeURIComponent(applicantName)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Status updated successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
        }
    }

    function sendDocumentRequest(applicationId, applicantName) {
        // Show modal to select documents
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
        <div class="modal-content">
            <h3>Request Documents from ${applicantName}</h3>
            <div class="document-checklist">
                <label>
                    <input type="checkbox" value="ID" name="docs"> Valid ID
                </label>
                <label>
                    <input type="checkbox" value="Proof of Income" name="docs"> Proof of Income
                </label>
                <label>
                    <input type="checkbox" value="Bank Statement" name="docs"> Bank Statement
                </label>
                <label>
                    <input type="checkbox" value="Employment Letter" name="docs"> Employment Letter
                </label>
            </div>
            <button onclick="submitDocumentRequest(${applicationId}, '${applicantName}')">Send Request</button>
            <button onclick="this.parentElement.parentElement.remove()">Cancel</button>
        </div>
    `;
        document.body.appendChild(modal);
    }

    function submitDocumentRequest(applicationId, applicantName) {
        const selectedDocs = Array.from(document.querySelectorAll('input[name="docs"]:checked'))
            .map(el => el.value);

        if (selectedDocs.length === 0) {
            alert('Please select at least one document');
            return;
        }

        fetch('process_applicant_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=request_documents&application_id=' + applicationId +
                '&docs=' + encodeURIComponent(JSON.stringify(selectedDocs)) +
                '&applicant_name=' + encodeURIComponent(applicantName)
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Document request sent to ' + applicantName, 'success');
                    document.querySelector('.modal').remove();
                } else {
                    showNotification('Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            });
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = 'toast-notification ' + type;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 3000);
    }
</script>

<?php
/**
 * CSS for notification badge
 */
?>

<style>
    .notification-badge {
        display: inline-block;
        background: #ef5350;
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-left: 8px;
    }

    .modal {
        display: flex;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        max-width: 400px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .document-checklist {
        margin: 20px 0;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .document-checklist label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }

    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 3000;
        animation: slideIn 0.3s ease;
    }

    .toast-notification.success {
        background: #2e7d32;
    }

    .toast-notification.error {
        background: #ef5350;
    }

    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>