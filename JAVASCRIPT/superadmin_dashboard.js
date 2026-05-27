console.log("Superadmin Dashboard JS loaded successfully");

const adminRole = "<?php echo $adminRole; ?>";
let downloadLink = null;

// Loading Overlay
function showLoadingOverlay(message = "Processing...") {
  // Remove existing overlay
  const existingOverlay = document.querySelector(".loading-overlay");
  if (existingOverlay) existingOverlay.remove();

  const overlay = document.createElement("div");
  overlay.className = "loading-overlay";
  overlay.innerHTML = `
    <div class="loading-content">
      <div class="loading-spinner">
        <div class="spinner-ring"></div>
        <div class="spinner-ring"></div>
        <div class="spinner-ring"></div>
      </div>
      <p class="loading-message">${message}</p>
    </div>
  `;

  document.body.appendChild(overlay);
  setTimeout(() => overlay.classList.add("show"), 10);
}

function hideLoadingOverlay() {
  const overlay = document.querySelector(".loading-overlay");
  if (overlay) {
    overlay.classList.remove("show");
    setTimeout(() => overlay.remove(), 300);
  }
}

// Enhanced Due Accounts Helper Functions
function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function showNotification(message, type = "info", duration = 4000) {
  const container =
    document.getElementById("notificationContainer") ||
    createNotificationContainer();
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.innerHTML = `
    <i class="fas fa-${
      type === "success"
        ? "check-circle"
        : type === "error"
        ? "exclamation-circle"
        : "info-circle"
    }"></i>
    <span>${escapeHtml(message)}</span>
  `;
  container.appendChild(notification);

  setTimeout(() => {
    notification.classList.add("fade-out");
    setTimeout(() => notification.remove(), 300);
  }, duration);
}

function createNotificationContainer() {
  const container = document.createElement("div");
  container.id = "notificationContainer";
  container.style.cssText =
    "position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;";
  document.body.appendChild(container);
  return container;
}

function generateDueAccountsTable(dueAccounts) {
  if (dueAccounts.length === 0) {
    return `
      <div class="empty-state">
        <i class="fas fa-circle-check"></i>
        <p>No due accounts found. All payments are on track!</p>
      </div>
    `;
  }

  let tableHTML = `
    <table class="due-accounts-table" role="grid" aria-describedby="due-accounts-info">
      <thead>
        <tr>
          <th scope="col">Due Date</th>
          <th scope="col">Account Holder</th>
          <th scope="col">Amount</th>
          <th scope="col">Status</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>
      <tbody>
  `;

  dueAccounts.forEach((due) => {
    const dueDate = new Date(due.due_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    dueDate.setHours(0, 0, 0, 0);

    const daysUntilDue = Math.floor((dueDate - today) / (1000 * 60 * 60 * 24));
    let urgencyClass = "normal";
    let urgencyBadge = "";

    if (daysUntilDue <= 0) {
      urgencyClass = "urgent";
      urgencyBadge =
        '<span class="urgency-badge urgent"><i class="fas fa-exclamation-circle"></i> Overdue</span>';
    } else if (daysUntilDue <= 7) {
      urgencyClass = "urgent";
      urgencyBadge =
        '<span class="urgency-badge urgent"><i class="fas fa-exclamation"></i> Urgent</span>';
    } else if (daysUntilDue <= 15) {
      urgencyClass = "warning";
      urgencyBadge =
        '<span class="urgency-badge warning"><i class="fas fa-clock"></i> Due Soon</span>';
    }

    const formattedDate = dueDate.toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });

    tableHTML += `
      <tr class="due-row ${urgencyClass}">
        <td data-label="Due Date">
          <span class="date-badge">${escapeHtml(formattedDate)}</span>
        </td>
        <td data-label="Account Holder">
          <div class="account-holder-info">
            <strong>${escapeHtml(due.first_name + " " + due.last_name)}</strong>
            <span class="account-id">ID: ${escapeHtml(
              due.application_id
            )}</span>
          </div>
        </td>
        <td data-label="Amount">
          <span class="amount-badge">₱${parseFloat(due.amount).toLocaleString(
            "en-US",
            { minimumFractionDigits: 2 }
          )}</span>
        </td>
        <td data-label="Status">
          ${urgencyBadge}
        </td>
        <td data-label="Actions">
          <div class="action-buttons">
            <button class="action-btn view-btn" onclick="openLoanDetailsModal(${
              due.application_id
            })" title="View Loan Details">
              <i class="fas fa-eye"></i> View
            </button>
            <button class="action-btn reminder-btn" onclick="sendPaymentReminder(${
              due.payment_id
            })" title="Send Payment Reminder">
              <i class="fas fa-bell"></i> Remind
            </button>
          </div>
        </td>
      </tr>
    `;
  });

  tableHTML += `
      </tbody>
    </table>
  `;

  return tableHTML;
}

// Throttle for due accounts refresh
let dueAccountsLastFetch = 0;
let dueAccountsThrottleDelay = 3000; // Only refresh every 3 seconds minimum

function refreshDueAccountsTable() {
  const now = Date.now();
  if (now - dueAccountsLastFetch < dueAccountsThrottleDelay) {
    return; // Skip if throttled
  }
  dueAccountsLastFetch = now;

  const container = document.getElementById("dueAccountsContainer");
  const refreshBtn = document.querySelector(".refresh-btn");

  if (!container) {
    return;
  }

  // Add spinning animation to refresh button
  if (refreshBtn) {
    refreshBtn.classList.add("spinning");
  }

  fetch("Superadmin_dashboard.php?action=get_due_accounts", {
    cache: "no-store",
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (refreshBtn) {
        refreshBtn.classList.remove("spinning");
      }

      if (data.success) {
        container.innerHTML = generateDueAccountsTable(data.due_accounts || []);
      } else {
        container.innerHTML = `
          <div class="error-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>${escapeHtml(data.message || "Error loading due accounts")}</p>
          </div>
        `;
      }
    })
    .catch((error) => {
      if (refreshBtn) {
        refreshBtn.classList.remove("spinning");
      }

      container.innerHTML = `
        <div class="error-state">
          <i class="fas fa-exclamation-triangle"></i>
          <p>Error loading due accounts.</p>
        </div>
      `;
      console.error("Fetch error:", error);
    });
}

function sendPaymentReminder(paymentId) {
  // Disable button during request
  const button = event.target.closest(".reminder-btn");
  if (!button) return;

  const originalContent = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

  const formData = new FormData();
  formData.append("action", "send_payment_reminder");
  formData.append("payment_id", paymentId);

  // Show loading overlay
  showLoadingOverlay(
    "<div style='text-align: center;'><i class='fas fa-envelope fa-2x' style='margin-bottom: 10px; display: block;'></i><span>Sending payment reminder...</span></div>"
  );

  fetch("Superadmin_dashboard.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.text();
    })
    .then((text) => {
      hideLoadingOverlay();
      button.disabled = false;
      button.innerHTML = originalContent;

      try {
        const data = JSON.parse(text);
        if (data.success) {
          showNotification(
            `✓ Reminder sent successfully to ${data.recipient || "customer"}`,
            "success"
          );
          // Visual feedback on button
          button.classList.add("success");
          setTimeout(() => button.classList.remove("success"), 2000);
        } else {
          showNotification("✗ " + data.message, "error");
          button.classList.add("error");
          setTimeout(() => button.classList.remove("error"), 2000);
        }
      } catch (e) {
        console.error("Invalid JSON response:", text, e);
        showNotification("✗ Server error: Invalid response", "error");
        button.classList.add("error");
        setTimeout(() => button.classList.remove("error"), 2000);
      }
    })
    .catch((error) => {
      hideLoadingOverlay();
      button.disabled = false;
      button.innerHTML = originalContent;
      console.error("Fetch error:", error);
      showNotification("✗ Failed to send reminder: " + error.message, "error");
      button.classList.add("error");
      setTimeout(() => button.classList.remove("error"), 2000);
    });
}

function toggleDropdown() {
  var dropdown = document.getElementById("dropdown");
  dropdown.classList.toggle("show");
}

window.onclick = function (event) {
  if (
    !event.target.matches(".profile") &&
    !event.target.closest(".profile-container")
  ) {
    var dropdowns = document.getElementsByClassName("dropdown-menu");
    for (var i = 0; i < dropdowns.length; i++) {
      var openDropdown = dropdowns[i];
      if (openDropdown.classList.contains("show")) {
        openDropdown.classList.remove("show");
      }
    }
  }
};

function openLoanDetailsModal(applicationId) {
  console.log("Opening modal for applicationId:", applicationId);

  const modal = document.getElementById("loanDetailsModal");
  const content = document.getElementById("loanDetailsContent");

  if (!modal || !content) {
    console.error("Modal elements not found - check HTML IDs");
    return;
  }

  content.innerHTML = "<p>Loading loan details...</p>";
  modal.style.display = "flex";
  setTimeout(() => {
    modal.classList.add("show");
    console.log("Modal displayed and 'show' class added");
  }, 10);

  fetch(
    `Superadmin_dashboard.php?action=get_loan_details&application_id=${applicationId}`,
    { cache: "no-store" }
  )
    .then((response) => {
      console.log("Fetch response status:", response.status);
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Fetch data:", data);
      if (data.success) {
        const loan = data.loan;
        const documents = data.documents;
        const remarks = data.remarks;
        const logs = data.logs || [];

        // Format User Information
        const userInfo = `
          <div class="modal-section">
            <h3><i class="fas fa-user"></i> User Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label"><i class="fas fa-signature"></i> Full Name</span>
                <span class="info-value">${loan.first_name} ${
          loan.middle_name || ""
        } ${loan.last_name}</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                <span class="info-value">${loan.email}</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-birthday-cake"></i> Birthday</span>
                <span class="info-value">${
                  loan.birthday
                    ? new Date(loan.birthday).toLocaleDateString()
                    : "Not provided"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-phone"></i> Contact Number</span>
                <span class="info-value">${
                  loan.contact || "Not provided"
                }</span>
              </div>
            </div>
          </div>
        `;

        // Format Loan Information
        const loanInfo = `
          <div class="modal-section">
            <h3><i class="fas fa-file-invoice-dollar"></i> Loan Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label"><i class="fas fa-tag"></i> Loan Type</span>
                <span class="info-value">${loan.type_name}</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-money-bill-wave"></i> Amount Applied</span>
                <span class="info-value amount-highlight">₱${parseFloat(
                  loan.amount_applied
                ).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-info-circle"></i> Loan Status</span>
                <span class="status-badge badge-${loan.status.toLowerCase()}">${
          loan.status
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-check-circle"></i> Pre-Approval Status</span>
                <span class="status-badge badge-${loan.pre_approval_status.toLowerCase()}">${
          loan.pre_approval_status
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-clipboard-check"></i> Credit Investigation Status</span>
                <span class="status-badge badge-${loan.credit_investigation_status.toLowerCase()}">${
          loan.credit_investigation_status
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar"></i> Term Length</span>
                <span class="info-value">${
                  loan.term_length || "N/A"
                } months</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-redo"></i> Repayment Frequency</span>
                <span class="info-value">${
                  loan.repayment_frequency || "N/A"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-bullseye"></i> Purpose</span>
                <span class="info-value">${loan.purpose || "N/A"} ${
          loan.purpose === "Others" && loan.others_text
            ? " - " + loan.others_text
            : ""
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-project-diagram"></i> Project Type</span>
                <span class="info-value">${loan.project_type || "N/A"}</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-file-alt"></i> Project Description</span>
                <span class="info-value">${
                  loan.project_description || "N/A"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label"><i class="fas fa-calendar-plus"></i> Submission Date</span>
                <span class="info-value">${new Date(
                  loan.created_at
                ).toLocaleDateString()}</span>
              </div>
            </div>
          </div>
        `;

        // Format financial information with admin2 style
        const financialInfo = loan.net_income
          ? `
            <div class="modal-section">
              <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
              <div class="financial-grid">
                <div class="financial-card">
                  <h4><i class="fas fa-money-bill-wave"></i> Income Sources</h4>
                  <div class="financial-items">
                    <div class="financial-row">
                      <span>Business Income:</span>
                      <strong>₱${parseFloat(
                        loan.business_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Salary Income:</span>
                      <strong>₱${parseFloat(
                        loan.salary_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Remittance Income:</span>
                      <strong>₱${parseFloat(
                        loan.remittance_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Other Income:</span>
                      <strong>₱${parseFloat(
                        loan.other_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Business 2 Income:</span>
                      <strong>₱${parseFloat(
                        loan.business2_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Salary 2 Income:</span>
                      <strong>₱${parseFloat(
                        loan.salary2_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row total-row">
                      <span>Net Income:</span>
                      <strong class="highlight-green">₱${parseFloat(
                        loan.net_income || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                  </div>
                </div>
                
                <div class="financial-card">
                  <h4><i class="fas fa-receipt"></i> Monthly Expenses</h4>
                  <div class="financial-items">
                    <div class="financial-row">
                      <span>Food Allowance:</span>
                      <strong>₱${parseFloat(
                        loan.food_allowance || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Electricity Bill:</span>
                      <strong>₱${parseFloat(
                        loan.electricity_bill || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Water Bill:</span>
                      <strong>₱${parseFloat(
                        loan.water_bill || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Internet Bill:</span>
                      <strong>₱${parseFloat(
                        loan.internet_bill || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Gas Bill:</span>
                      <strong>₱${parseFloat(loan.gas_bill || 0).toLocaleString(
                        "en-US",
                        {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        }
                      )}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Educational Allowance:</span>
                      <strong>₱${parseFloat(
                        loan.educational_allowance || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Car Amortization:</span>
                      <strong>₱${parseFloat(
                        loan.car_amortization || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Insurance:</span>
                      <strong>₱${parseFloat(loan.insurance || 0).toLocaleString(
                        "en-US",
                        {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        }
                      )}</strong>
                    </div>
                    <div class="financial-row">
                      <span>Other Expenses:</span>
                      <strong>₱${parseFloat(
                        loan.other_expense || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row total-row">
                      <span>Total Expenditures:</span>
                      <strong class="highlight-red">₱${parseFloat(
                        loan.total_expenditures || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                  </div>
                </div>
                
                <div class="financial-card financial-summary">
                  <h4><i class="fas fa-calculator"></i> Financial Summary</h4>
                  <div class="financial-items">
                    <div class="financial-row">
                      <span>Expected Monthly Amortization:</span>
                      <strong>₱${parseFloat(
                        loan.expected_monthly_amortization || 0
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</strong>
                    </div>
                    <div class="financial-row total-row">
                      <span>Remaining Income:</span>
                      <strong class="highlight-${
                        parseFloat(loan.remaining_income || 0) >= 0
                          ? "green"
                          : "red"
                      }">₱${parseFloat(
              loan.remaining_income || 0
            ).toLocaleString("en-US", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            })}</strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          `
          : `<div class="modal-section">
              <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
              <p class="no-data"><i class="fas fa-info-circle"></i> No financial information available.</p>
            </div>`;

        // Format documents with improved table styling
        const documentsTable =
          documents.length > 0
            ? `
            <div class="modal-section">
              <h3><i class="fas fa-file-alt"></i> Submitted Documents</h3>
              <div class="documents-table-wrapper">
                <table class="modal-documents-table">
                  <thead>
                    <tr>
                      <th><i class="fas fa-paperclip"></i> Document</th>
                      <th><i class="fas fa-info-circle"></i> Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    ${documents
                      .map(
                        (doc) => `
                      <tr>
                        <td class="doc-name">
                          <a href="${
                            doc.file_path
                          }" target="_blank" class="doc-link">
                            <i class="fas fa-file-pdf"></i> ${doc.document_name}
                          </a>
                        </td>
                        <td>
                          <span class="status-badge status-${doc.status.toLowerCase()}">${
                          doc.status
                        }</span>
                        </td>
                      </tr>
                    `
                      )
                      .join("")}
                  </tbody>
                </table>
              </div>
            </div>
          `
            : `<div class="modal-section">
                <h3><i class="fas fa-file-alt"></i> Submitted Documents</h3>
                <p class="no-data"><i class="fas fa-info-circle"></i> No documents submitted.</p>
              </div>`;

        // Format remarks with improved styling
        const remarksSection =
          remarks.length > 0
            ? `
            <div class="modal-section">
              <h3><i class="fas fa-comment-dots"></i> Remarks History</h3>
              <div class="remarks-timeline">
                ${remarks
                  .map(
                    (remark) => `
                  <div class="remark-item">
                    <div class="remark-header">
                      <span class="remark-author"><i class="fas fa-user-circle"></i> ${
                        remark.admin_name || "Admin"
                      }</span>
                      <span class="remark-date"><i class="far fa-clock"></i> ${new Date(
                        remark.created_at
                      ).toLocaleString("en-US", {
                        month: "short",
                        day: "numeric",
                        year: "numeric",
                        hour: "2-digit",
                        minute: "2-digit",
                      })}</span>
                    </div>
                    <div class="remark-content">
                      ${remark.remarks}
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
          `
            : `<div class="modal-section">
                <h3><i class="fas fa-comment-dots"></i> Remarks History</h3>
                <p class="no-data"><i class="fas fa-info-circle"></i> No remarks available.</p>
              </div>`;

        // Format activity logs with improved styling
        const logsSection =
          logs.length > 0
            ? `
            <div class="modal-section">
              <h3><i class="fas fa-history"></i> Activity Logs</h3>
              <div class="logs-timeline">
                ${logs
                  .map(
                    (log) => `
                  <div class="log-item">
                    <div class="log-indicator"></div>
                    <div class="log-content">
                      <div class="log-header">
                        <span class="log-time"><i class="far fa-clock"></i> ${new Date(
                          log.created_at
                        ).toLocaleString("en-US", {
                          month: "short",
                          day: "numeric",
                          year: "numeric",
                          hour: "2-digit",
                          minute: "2-digit",
                        })}</span>
                        <span class="log-role badge-${log.user_role.toLowerCase()}">${
                      log.user_role
                    }</span>
                      </div>
                      <div class="log-description">
                        <strong>${log.action_type}:</strong> ${log.description}
                      </div>
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            </div>
          `
            : `<div class="modal-section">
                <h3><i class="fas fa-history"></i> Activity Logs</h3>
                <p class="no-data"><i class="fas fa-info-circle"></i> No activity logs available.</p>
              </div>`;

        // Check if loan status is editable
        const isLoanStatusEditable =
          loan.pre_approval_status.toLowerCase() === "approved" &&
          loan.credit_investigation_status.toLowerCase() === "completed";

        // Superadmin update form
        const updateForm = `
          <div class="modal-section update-section">
            <h3><i class="fas fa-edit"></i> Update Status & Add Remarks</h3>
            <form id="statusUpdateForm" class="status-update-form">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="application_id" value="${
                loan.application_id
              }">
              
              <div class="form-group">
                <label for="loanStatus"><i class="fas fa-info-circle"></i> Loan Status</label>
                <select name="status" id="loanStatus" class="form-select" ${
                  !isLoanStatusEditable ? "disabled" : ""
                }>
                  <option value="">-- Select Status --</option>
                  <option value="Pending" ${
                    loan.status === "Pending" ? "selected" : ""
                  }>Pending</option>
                  <option value="Active" ${
                    loan.status === "Active" ? "selected" : ""
                  }>Active</option>
                  <option value="Completed" ${
                    loan.status === "Completed" ? "selected" : ""
                  }>Completed</option>
                  <option value="New" ${
                    loan.status === "New" ? "selected" : ""
                  }>New</option>
                  <option value="Renewal" ${
                    loan.status === "Renewal" ? "selected" : ""
                  }>Renewal</option>
                </select>
                ${
                  !isLoanStatusEditable
                    ? '<p class="form-note"><i class="fas fa-exclamation-triangle"></i> Loan status can only be changed when Pre-Approval is Approved and Credit Investigation is Completed.</p>'
                    : ""
                }
              </div>
              
              <div class="form-group">
                <label for="remarksText"><i class="fas fa-comment"></i> Add Remarks (Optional)</label>
                <textarea name="remarks" id="remarksText" class="form-textarea" rows="4" placeholder="Enter your remarks here..."></textarea>
              </div>
              
              <button type="submit" class="submit-btn">
                <i class="fas fa-save"></i> Update Application
              </button>
            </form>
            <div id="updateMessage" class="update-message"></div>
          </div>
        `;

        // Combine all sections
        content.innerHTML = `
          ${userInfo}
          ${loanInfo}
          ${financialInfo}
          ${documentsTable}
          ${remarksSection}
          ${logsSection}
          ${updateForm}
        `;

        // Handle status update form submission
        document
          .getElementById("statusUpdateForm")
          .addEventListener("submit", function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            const messageDiv = document.getElementById("updateMessage");

            // Show loading overlay
            showLoadingOverlay("Updating application status...");

            fetch("Superadmin_dashboard.php", {
              method: "POST",
              body: formData,
            })
              .then((response) => response.json())
              .then((data) => {
                hideLoadingOverlay();
                if (data.success) {
                  messageDiv.innerHTML = `<p class="success"><i class="fas fa-check-circle"></i> ${data.message}</p>`;
                  setTimeout(() => location.reload(), 1500);
                } else {
                  messageDiv.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.message}</p>`;
                }
              })
              .catch((error) => {
                hideLoadingOverlay();
                messageDiv.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: ${error.message}</p>`;
              });
          });
      } else {
        content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${
          data.message || "Failed to load loan details."
        }</p>`;
      }
    })
    .catch((error) => {
      content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: ${error.message}</p>`;
    });
}

function submitStatusAndRemarks(applicationId) {
  if (!Number.isInteger(applicationId) || applicationId <= 0) {
    alert("Invalid application ID.");
    return;
  }
  const messageDiv = document.createElement("div");
  messageDiv.className = "message";
  document.getElementById("loanDetailsContent").prepend(messageDiv);

  const formData = new FormData();
  formData.append("action", "update_status");
  formData.append("application_id", applicationId);
  formData.append("status", document.getElementById("loan-status").value);
  formData.append("remarks", document.getElementById("remarks").value);

  fetch("Superadmin_dashboard.php", {
    method: "POST",
    body: formData,
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      messageDiv.className = `message ${data.success ? "success" : "error"}`;
      messageDiv.innerHTML = `<i class="fas ${
        data.success ? "fa-check-circle" : "fa-exclamation-circle"
      }"></i> ${data.message}`;
      if (data.success) {
        document.getElementById("loanDetailsContent").innerHTML =
          "<p>Loading...</p>";
        setTimeout(() => openLoanDetailsModal(applicationId), 1500);
      } else {
        alert("Failed to update status: " + data.message);
      }
    })
    .catch((error) => {
      messageDiv.className = "message error";
      messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error updating status: ${error.message}`;
      alert("Error updating status: " + error.message);
    });
}

function openCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  const interestRateDisplay = document.getElementById("interestRateDisplay");
  const interestRateInput = document.getElementById("interestRate");

  modal.style.display = "block";
  setTimeout(() => modal.classList.add("show"), 10);
  document.getElementById("calculatorForm").reset();
  document.getElementById("result").innerHTML = "";

  fetch("Superadmin_dashboard.php?action=get_current_interest_rate", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        interestRateDisplay.textContent = `${parseFloat(
          data.interest_rate
        ).toFixed(2)}%`;
        interestRateInput.value = data.interest_rate;
      } else {
        interestRateDisplay.textContent = "Error loading rate";
        console.error("Error fetching interest rate:", data.message);
      }
    })
    .catch((error) => {
      interestRateDisplay.textContent = "Error loading rate";
      console.error("Fetch error:", error);
    });
}

function closeCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

function calculateLoan() {
  const loanAmount = parseFloat(document.getElementById("loanAmount").value);
  const annualInterestRate =
    parseFloat(document.getElementById("interestRate").value) / 100;
  const monthlyInterestRate = annualInterestRate / 12;
  const loanTerm = parseFloat(document.getElementById("loanTerm").value);
  const termType = document.getElementById("termType").value;

  if (isNaN(loanAmount) || loanAmount <= 0) {
    document.getElementById("result").innerHTML =
      "Please enter a valid loan amount.";
    return;
  }
  if (isNaN(loanTerm) || loanTerm <= 0) {
    document.getElementById("result").innerHTML =
      "Please enter a valid loan term.";
    return;
  }

  const totalMonths = termType === "years" ? loanTerm * 12 : loanTerm;
  const monthlyPayment =
    (loanAmount * monthlyInterestRate) /
    (1 - Math.pow(1 + monthlyInterestRate, -totalMonths));
  const totalPayment = monthlyPayment * totalMonths;
  const totalInterest = totalPayment - loanAmount;

  if (
    !isNaN(monthlyPayment) &&
    monthlyPayment !== Infinity &&
    monthlyPayment > 0
  ) {
    document.getElementById("result").innerHTML = `
                    <p><strong>Monthly Payment:</strong> ₱${monthlyPayment.toFixed(
                      2
                    )}</p>
                    <p><strong>Total Payment:</strong> ₱${totalPayment.toFixed(
                      2
                    )}</p>
                    <p><strong>Total Interest:</strong> ₱${totalInterest.toFixed(
                      2
                    )}</p>
                `;
  } else {
    document.getElementById("result").innerHTML = "Please enter valid values.";
  }
}

function openManageInterestRateModal() {
  const modal = document.getElementById("manageInterestRateModal");
  const messageDiv = document.getElementById("interestRateMessage");
  const historyTable = document.getElementById("interestRateHistoryTable");
  const ratesGrid = document.getElementById("ratesGrid");
  const historyLoading = document.getElementById("historyLoading");

  // Clear messages and show loading
  messageDiv.innerHTML = "";
  ratesGrid.innerHTML =
    '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
  historyTable.innerHTML =
    '<tr><td colspan="5"><div class="spinner"></div></td></tr>';
  historyLoading.style.display = "block";

  modal.style.display = "block";
  setTimeout(() => modal.classList.add("show"), 10);

  // Hide the update form initially
  const updateFormCard = document.getElementById("updateFormSection");
  if (updateFormCard) {
    updateFormCard.style.display = "none";
  }

  // Load current interest rates
  fetch("Superadmin_dashboard.php?action=get_all_interest_rates", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const rates = data.rates;

        // Update stats
        updateInterestRateStats(rates);

        // Render rate cards
        if (rates.length > 0) {
          ratesGrid.innerHTML = rates
            .map((item) => {
              const updatedDate = new Date(item.updated_at);
              const formattedDate = updatedDate.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric",
              });

              return `
                <div class="rate-card" onclick="editRate('${
                  item.term_length
                }', '${item.interest_rate}')">
                    <div class="edit-overlay">
                        <i class="fas fa-edit" style="color: var(--green1);"></i>
                    </div>
                    <div class="term-badge">${item.term_length} Months</div>
                    <div class="rate-display">
                        ${parseFloat(item.interest_rate).toFixed(2)}
                        <span class="percent-sign">%</span>
                    </div>
                    <div class="updated-info">
                        Updated: ${formattedDate}
                    </div>
                </div>
              `;
            })
            .join("");
        } else {
          ratesGrid.innerHTML = `
            <div class="rate-card-skeleton">
                <i class="fas fa-info-circle" style="font-size: 2rem; color: #9ca3af; margin-bottom: 10px;"></i>
                <p>No rates configured yet.</p>
                <button class="action-btn-small" onclick="showUpdateForm()">
                    <i class="fas fa-plus"></i> Add First Rate
                </button>
            </div>
          `;
        }
      } else {
        ratesGrid.innerHTML = `
          <div class="rate-card-skeleton">
              <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i>
              <p>Error: ${data.message}</p>
          </div>
        `;
      }
    })
    .catch((error) => {
      ratesGrid.innerHTML = `
        <div class="rate-card-skeleton">
            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i>
            <p>Error loading rates: ${error.message}</p>
        </div>
      `;
      console.error("Fetch error:", error);
    });

  // Load interest rate history
  fetch("Superadmin_dashboard.php?action=get_interest_rate_history", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      historyLoading.style.display = "none";
      if (data.success) {
        const history = data.history;
        if (history.length > 0) {
          historyTable.innerHTML = history
            .map(
              (item) => `
                <tr>
                    <td>${item.id}</td>
                    <td>${item.term_length} Months</td>
                    <td>${parseFloat(item.interest_rate).toFixed(2)}%</td>
                    <td>${new Date(item.updated_at).toLocaleString()}</td>
                    <td>${item.updated_by || "System"}</td>
                </tr>
            `
            )
            .join("");
        } else {
          historyTable.innerHTML =
            '<tr><td colspan="5">No history available.</td></tr>';
        }
      } else {
        historyTable.innerHTML = `<tr><td colspan="5">Error: ${data.message}</td></tr>`;
      }
    })
    .catch((error) => {
      historyLoading.style.display = "none";
      historyTable.innerHTML = `<tr><td colspan="5">Error loading history: ${error.message}</td></tr>`;
      console.error("Fetch error:", error);
    });
}

function updateInterestRateStats(rates) {
  // Calculate stats
  const configuredTerms = rates.length;
  const totalTerms = 5; // 6, 12, 18, 24, 36 months

  let avgRate = 0;
  if (rates.length > 0) {
    const sum = rates.reduce(
      (acc, item) => acc + parseFloat(item.interest_rate),
      0
    );
    avgRate = (sum / rates.length).toFixed(2);
  }

  let lastUpdated = "Never";
  if (rates.length > 0) {
    const dates = rates.map((item) => new Date(item.updated_at));
    const mostRecent = new Date(Math.max(...dates));
    lastUpdated = mostRecent.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  }

  // Update stat displays - match IDs from HTML
  const configElement = document.getElementById("configuredTermsCount");
  const avgRateElement = document.getElementById("averageRateValue");
  const lastUpdatedElement = document.getElementById("lastUpdatedValue");

  if (configElement)
    configElement.textContent = `${configuredTerms}/${totalTerms}`;
  if (avgRateElement) avgRateElement.textContent = avgRate + "%";
  if (lastUpdatedElement) lastUpdatedElement.textContent = lastUpdated;
}

function editRate(termLength, currentRate) {
  // Show the update form
  showUpdateForm();

  // Populate form fields
  document.getElementById("termLength").value = termLength;
  document.getElementById("newInterestRate").value =
    parseFloat(currentRate).toFixed(2);

  // Scroll to form
  setTimeout(() => {
    const formCard = document.querySelector(".interest-rate-card");
    if (formCard) {
      formCard.scrollIntoView({ behavior: "smooth", block: "nearest" });
      document.getElementById("newInterestRate").focus();
    }
  }, 100);
}

function showUpdateForm() {
  const updateFormCard = document.getElementById("updateFormSection");
  if (updateFormCard) {
    updateFormCard.style.display = "block";
    // Smooth scroll to form
    setTimeout(() => {
      updateFormCard.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }, 100);
  }
}

function hideUpdateForm() {
  const updateFormCard = document.getElementById("updateFormSection");
  if (updateFormCard) {
    updateFormCard.style.display = "none";

    // Clear form
    document.getElementById("termLength").value = "";
    document.getElementById("newInterestRate").value = "";

    // Clear any validation messages
    const messageDiv = document.getElementById("interestRateMessage");
    if (messageDiv) {
      messageDiv.innerHTML = "";
    }
  }
}

function closeManageInterestRateModal() {
  const modal = document.getElementById("manageInterestRateModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);

  // Reset form
  document.getElementById("interestRateForm").reset();
  document.getElementById("interestRateMessage").innerHTML = "";
}

function updateInterestRate() {
  const interestRate = document.getElementById("newInterestRate").value;
  const termLength = document.getElementById("termLength").value;
  const messageDiv = document.getElementById("interestRateMessage");
  const updateButton = document.querySelector("#interestRateForm .btn-primary");

  if (!termLength) {
    messageDiv.className = "message error";
    messageDiv.innerHTML =
      '<i class="fas fa-exclamation-circle"></i> Please select a term length.';
    return;
  }

  if (!interestRate || parseFloat(interestRate) <= 0) {
    messageDiv.className = "message error";
    messageDiv.innerHTML =
      '<i class="fas fa-exclamation-circle"></i> Please enter a valid interest rate.';
    return;
  }

  updateButton.disabled = true;
  updateButton.innerHTML = '<span class="loading-spinner"></span> Updating...';

  const formData = new FormData();
  formData.append("action", "update_interest_rate");
  formData.append("interest_rate", interestRate);
  formData.append("term_length", termLength);

  fetch("Superadmin_dashboard.php", {
    method: "POST",
    body: formData,
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      updateButton.disabled = false;
      updateButton.innerHTML =
        '<i class="fas fa-save"></i> Update Interest Rate';
      messageDiv.className = `message ${data.success ? "success" : "error"}`;
      messageDiv.innerHTML = `<i class="fas ${
        data.success ? "fa-check-circle" : "fa-exclamation-circle"
      }"></i> ${data.message}`;
      if (data.success) {
        document.getElementById("interestRateForm").reset();
        hideUpdateForm();
        setTimeout(() => {
          messageDiv.innerHTML = "";
          openManageInterestRateModal();
        }, 1500);
      }
    })
    .catch((error) => {
      updateButton.disabled = false;
      updateButton.innerHTML =
        '<i class="fas fa-save"></i> Update Interest Rate';
      messageDiv.className = "message error";
      messageDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error updating interest rate: ${error.message}`;
      console.error("Fetch error:", error);
    });
}

// Ensure functions are globally accessible
window.openLoanDetailsModal = openLoanDetailsModal;
window.closeLoanDetailsModal = closeLoanDetailsModal;

function closeLoanDetailsModal() {
  const modal = document.getElementById("loanDetailsModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

window.addEventListener("click", (event) => {
  const loanDetailsModal = document.getElementById("loanDetailsModal");
  const calculatorModal = document.getElementById("loanCalculatorModal");
  const manageInterestRateModal = document.getElementById(
    "manageInterestRateModal"
  );
  if (event.target === loanDetailsModal) {
    closeLoanDetailsModal();
  }
  if (event.target === calculatorModal) {
    closeCalculatorModal();
  }
  if (event.target === manageInterestRateModal) {
    closeManageInterestRateModal();
  }
});

// Sort direction tracker
const sortDirection = {};

// Function to sort loan applicants table
function sortLoanApplicantsTable(column) {
  const table = document.querySelector(".loan-table");
  const tbody = document.getElementById("loanApplicantsTableBody");
  const rows = Array.from(tbody.querySelectorAll("tr"));
  const th = table.querySelector(`th[data-sort="${column}"]`);

  // Toggle sort direction
  sortDirection[column] = sortDirection[column] === "asc" ? "desc" : "asc";

  // Remove sort classes from all headers
  table.querySelectorAll("th").forEach((header) => {
    header.classList.remove("sort-asc", "sort-desc");
  });

  // Add sort class to current header
  th.classList.add(`sort-${sortDirection[column]}`);

  // Sort rows
  rows.sort((a, b) => {
    let aValue, bValue;
    if (column === "name") {
      aValue = a.cells[0].textContent.toLowerCase();
      bValue = b.cells[0].textContent.toLowerCase();
    } else if (column === "loan_type") {
      aValue = a.cells[1].textContent.toLowerCase();
      bValue = b.cells[1].textContent.toLowerCase();
    } else if (column === "amount_applied") {
      aValue = parseFloat(a.cells[2].textContent.replace(/[₱,]/g, ""));
      bValue = parseFloat(b.cells[2].textContent.replace(/[₱,]/g, ""));
    } else if (column === "final_loan_amount") {
      aValue = a.cells[3].textContent.includes("Not set")
        ? 0
        : parseFloat(a.cells[3].textContent.replace(/[₱,]/g, ""));
      bValue = b.cells[3].textContent.includes("Not set")
        ? 0
        : parseFloat(b.cells[3].textContent.replace(/[₱,]/g, ""));
    } else if (column === "status") {
      aValue = a.cells[4].textContent.toLowerCase();
      bValue = b.cells[4].textContent.toLowerCase();
    } else if (column === "pre_approval_status") {
      aValue = a.cells[5].textContent.toLowerCase();
      bValue = b.cells[5].textContent.toLowerCase();
    } else if (column === "credit_investigation_status") {
      aValue = a.cells[6].textContent.toLowerCase();
      bValue = b.cells[6].textContent.toLowerCase();
    } else if (column === "created_at") {
      aValue = new Date(a.cells[7].textContent).getTime();
      bValue = new Date(b.cells[7].textContent).getTime();
    }

    if (sortDirection[column] === "asc") {
      return aValue > bValue ? 1 : -1;
    } else {
      return aValue < bValue ? 1 : -1;
    }
  });

  // Re-append sorted rows
  tbody.innerHTML = "";
  rows.forEach((row) => tbody.appendChild(row));
}

// Function to filter loan applicants table
function filterLoanApplicantsTable() {
  const filterName = document
    .getElementById("filter-name")
    .value.trim()
    .toLowerCase();
  const filterLoanType = document.getElementById("filter-loan-type").value;
  const filterLoanStatus = document.getElementById("filter-loan-status").value;
  const filterPreApproval = document.getElementById(
    "filter-pre-approval"
  ).value;
  const filterCreditStatus = document.getElementById(
    "filter-credit-status"
  ).value;

  const tableBody = document.getElementById("loanApplicantsTableBody");
  const rows = Array.from(tableBody.querySelectorAll("tr"));

  rows.forEach((row) => {
    const name = row.cells[0].textContent.toLowerCase();
    const loanType = row.cells[1].textContent;
    const loanStatus = row.cells[4].textContent;
    const preApprovalStatus = row.cells[5].textContent;
    const creditStatus = row.cells[6].textContent;

    const matchesName = name.includes(filterName);
    const matchesLoanType =
      filterLoanType === "" || loanType === filterLoanType;
    const matchesLoanStatus =
      filterLoanStatus === "" || loanStatus === filterLoanStatus;
    const matchesPreApproval =
      filterPreApproval === "" || preApprovalStatus === filterPreApproval;
    const matchesCreditStatus =
      filterCreditStatus === "" || creditStatus === filterCreditStatus;

    if (
      matchesName &&
      matchesLoanType &&
      matchesLoanStatus &&
      matchesPreApproval &&
      matchesCreditStatus
    ) {
      row.style.display = "";
    } else {
      row.style.display = "none";
    }
  });

  // Show message if no rows are visible
  const visibleRows = rows.filter((row) => row.style.display !== "none");
  if (visibleRows.length === 0) {
    tableBody.innerHTML =
      '<tr><td colspan="9">No loan applicants match the selected filters.</td></tr>';
  }
}

// Function to clear search input
function clearSearch() {
  const searchInput = document.getElementById("filter-name");
  const clearIcon = document.querySelector(".clear-search");

  searchInput.value = "";
  if (clearIcon) {
    clearIcon.style.display = "none";
  }
  filterLoanApplicantsTable();
}

// Function to clear all filters
function clearAllFilters() {
  document.getElementById("filter-name").value = "";
  document.getElementById("filter-loan-type").value = "";
  document.getElementById("filter-loan-status").value = "";
  document.getElementById("filter-pre-approval").value = "";
  document.getElementById("filter-credit-status").value = "";

  const clearIcon = document.querySelector(".clear-search");
  if (clearIcon) {
    clearIcon.style.display = "none";
  }

  filterLoanApplicantsTable();
}

// Show/hide clear search icon based on input
document.addEventListener("DOMContentLoaded", function () {
  // ==================== BURGER MENU TOGGLE ====================
  const burger = document.querySelector(".burger");
  const nav = document.querySelector("nav");
  const navContainer = document.querySelector(".nav-container");

  if (burger && nav) {
    burger.addEventListener("click", function () {
      // Toggle active class on burger
      burger.classList.toggle("active");

      // Toggle open class on nav
      nav.classList.toggle("open");

      // Toggle expanded class on nav-container
      navContainer.classList.toggle("expanded");

      // Add/remove body overflow hidden (optional, prevents background scroll)
      if (nav.classList.contains("open")) {
        document.body.style.overflow = "hidden";
      } else {
        document.body.style.overflow = "";
      }
    });

    // Close menu when clicking outside
    document.addEventListener("click", function (e) {
      if (!navContainer.contains(e.target) && nav.classList.contains("open")) {
        burger.classList.remove("active");
        nav.classList.remove("open");
        navContainer.classList.remove("expanded");
        document.body.style.overflow = "";
      }
    });

    // Close menu when clicking on a nav link (mobile)
    const navLinks = nav.querySelectorAll("a");
    navLinks.forEach((link) => {
      link.addEventListener("click", function () {
        if (window.innerWidth <= 768) {
          burger.classList.remove("active");
          nav.classList.remove("open");
          navContainer.classList.remove("expanded");
          document.body.style.overflow = "";
        }
      });
    });
  }

  // ==================== SEARCH FUNCTIONALITY ====================
  const searchInput = document.getElementById("filter-name");
  const clearIcon = document.querySelector(".clear-search");

  if (searchInput && clearIcon) {
    searchInput.addEventListener("input", function () {
      clearIcon.style.display = this.value.trim() !== "" ? "block" : "none";
    });
  }
});

// Function to refresh loan applicants table
// Throttle for loan applicants refresh
let loanApplicantsLastFetch = 0;
let loanApplicantsThrottleDelay = 3000; // Only refresh every 3 seconds minimum

function refreshLoanApplicantsTable() {
  const now = Date.now();
  if (now - loanApplicantsLastFetch < loanApplicantsThrottleDelay) {
    return; // Skip if throttled
  }
  loanApplicantsLastFetch = now;

  const table = document.querySelector(".loan-table");
  if (!table) return;
  table.classList.add("table-loading");
  fetch("Superadmin_dashboard.php?action=get_loan_applicants", {
    cache: "no-store",
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      table.classList.remove("table-loading");
      if (data.success) {
        const tableBody = document.querySelector("#loanApplicantsTableBody");
        tableBody.innerHTML = "";
        if (data.applicants.length === 0) {
          tableBody.innerHTML =
            '<tr><td colspan="9">No loan applicants found.</td></tr>';
        } else {
          data.applicants.forEach((applicant) => {
            const row = document.createElement("tr");
            row.innerHTML = `
              <td data-label="Applicant Name">${applicant.first_name} ${
              applicant.last_name
            }</td>
              <td data-label="Loan Type">${applicant.type_name}</td>
              <td data-label="Amount Applied" class="amount-cell">
                <span class="amount-value">₱${parseFloat(
                  applicant.amount_applied
                ).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })}</span>
              </td>
              <td data-label="Final Loan Amount" class="amount-cell">${
                applicant.final_loan_amount
                  ? '<span class="amount-value">₱' +
                    parseFloat(applicant.final_loan_amount).toLocaleString(
                      "en-US",
                      {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      }
                    ) +
                    "</span>"
                  : '<span class="amount-not-set">Not set</span>'
              }</td>
              <td data-label="Loan Status">
                <span class="status-badge badge-${applicant.status.toLowerCase()}">${
              applicant.status
            }</span>
              </td>
              <td data-label="Pre-Approval Status">
                <span class="status-badge badge-${applicant.pre_approval_status.toLowerCase()}">${
              applicant.pre_approval_status
            }</span>
              </td>
              <td data-label="Credit Investigation Status">
                <span class="status-badge badge-${applicant.credit_investigation_status.toLowerCase()}">${
              applicant.credit_investigation_status
            }</span>
              </td>
              <td data-label="Submission Date">${new Date(
                applicant.created_at
              ).toLocaleDateString()}</td>
              <td data-label="Action">
                <a href="#" class="view-btn" onclick="openLoanDetailsModal('${
                  applicant.application_id
                }'); return false;">View</a>
              </td>
            `;
            tableBody.appendChild(row);
          });
        }
      } else {
        console.error("Failed to refresh loan applicants: " + data.message);
      }
    })
    .catch((error) => {
      table.classList.remove("table-loading");
      console.error("Error refreshing loan applicants:", error);
    });
}

function exportToCSV() {
  const table = document.querySelector(".loan-table");
  if (!table) {
    alert("Table with class 'loan-table' not found!");
    return;
  }

  const rows = table.querySelectorAll("tbody tr");
  if (rows.length === 0 || rows[0].cells.length <= 1) {
    alert("No data available to export.");
    return;
  }

  showExportModal("preparing");

  setTimeout(() => {
    try {
      // === Build CSV (unchanged) ===
      const headers = Array.from(table.querySelectorAll("thead th"))
        .map((th) => th.innerText.trim())
        .filter((h) => h !== "");

      const data = Array.from(rows).map((row) =>
        Array.from(row.cells)
          .map((cell) => cell.innerText.trim())
          .slice(0, headers.length)
      );

      const csvLines = [headers, ...data]
        .map((row) =>
          row.map((cell) => `"${(cell + "").replace(/"/g, '""')}"`).join(",")
        )
        .join("\r\n");

      const blob = new Blob(["\uFEFF" + csvLines], {
        type: "text/csv;charset=utf-8;",
      });
      const url = URL.createObjectURL(blob);
      downloadLink = url;

      const filename = `Loan_Records_${new Date()
        .toISOString()
        .slice(0, 10)}.csv`;
      document.getElementById("filenameDisplay").innerText = filename;

      // Trigger download
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);

      // SUCCESS → show message + auto-close after 3 seconds (blur disappears!)
      showExportModal("success");
      setTimeout(closeExportModal, 3000); // ← THIS REMOVES THE BLUR

      // Clean up blob after 1 minute
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (err) {
      console.error(err);
      showExportModal("failed");
    }
  }, 800);
}

// ────── MODAL CONTROL (improved) ──────
function showExportModal(step) {
  const modal = document.getElementById("exportModal");
  const preparing = document.getElementById("preparingStep");
  const success = document.getElementById("successStep");
  const failed = document.getElementById("failedStep");

  // Reset all steps first
  preparing.style.display = "none";
  success.style.display = "none";
  failed.style.display = "none";

  // Show the requested step
  if (step === "preparing") preparing.style.display = "block";
  if (step === "success") success.style.display = "block";
  if (step === "failed") failed.style.display = "block";

  // Progress bar animation (only for preparing)
  if (step === "preparing") {
    const bar = document.querySelector(".progress-fill");
    bar.style.width = "0%";
    setTimeout(() => (bar.style.width = "90%"), 100);
  }

  modal.style.display = "flex";
}

function closeExportModal() {
  document.getElementById("exportModal").style.display = "none";
  // Optional: reset for next use
  setTimeout(() => {
    document.getElementById("preparingStep").style.display = "block";
    document.getElementById("successStep").style.display = "none";
    document.getElementById("failedStep").style.display = "none";
  }, 300);
}

function retryExport() {
  closeExportModal();
  setTimeout(exportToCSV, 200); // tiny delay feels smoother
}

function openDownloadedFile() {
  if (downloadLink) window.open(downloadLink, "_blank");
  closeExportModal(); // ← also removes blur immediately
}
