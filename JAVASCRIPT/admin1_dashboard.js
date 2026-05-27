const adminRole = "<?php echo $adminRole; ?>";

// ==================== NOTIFICATION SYSTEM ====================
function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `custom-notification notification-${type}`;

  const icons = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };

  notification.innerHTML = `
        <i class="fas ${icons[type]}"></i>
        <span>${message}</span>
    `;

  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => notification.classList.add("show"), 10);

  // Auto remove after 5 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => notification.remove(), 300);
  }, 5000);
}

// ==================== LOADING OVERLAY ====================
function showLoadingOverlay(message = "Processing...") {
  let overlay = document.getElementById("loadingOverlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "loadingOverlay";
    overlay.className = "loading-overlay";
    overlay.innerHTML = `
            <div class="loading-content">
                <div class="spinner">
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                    <div class="spinner-ring"></div>
                </div>
                <p class="loading-text">${message}</p>
            </div>
        `;
    document.body.appendChild(overlay);
  } else {
    overlay.querySelector(".loading-text").textContent = message;
  }
  setTimeout(() => overlay.classList.add("show"), 10);
}

function hideLoadingOverlay() {
  const overlay = document.getElementById("loadingOverlay");
  if (overlay) {
    overlay.classList.remove("show");
    setTimeout(() => overlay.remove(), 300);
  }
}

// Mapping for action types and modules to user-friendly labels
const labelMappings = {
  action_type: {
    send_reminder: "Send Reminder",
    update: "Update",
    create: "Create",
    delete: "Delete",
    login: "Login",
    logout: "Logout",
    // Add other action types as needed
  },
  module: {
    payment_schedule: "Payment Schedule",
    loan_application: "Loan Application",
    remarks: "Remarks",
    user: "User",
    admin1: "Admin 1",
    admin2: "Admin 2",
    // Add other modules as needed
  },
};

// Function to format raw labels to user-friendly text
function formatLabel(type, value) {
  // Check if the value exists in the mappings
  if (labelMappings[type] && labelMappings[type][value]) {
    return labelMappings[type][value];
  }
  // Fallback: Convert underscore-separated string to title case
  return value
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");
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

// Function to refresh the loan applicants table
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
  if (!table) return; // Exit if table doesn't exist

  table.classList.add("table-loading");
  fetch("admin1_dashboard.php?action=get_loan_applicants", {
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
        if (!tableBody) {
          console.error("Table body element not found");
          return;
        }
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
                <span class="status-badge badge-${applicant.status.toLowerCase()}"> ${
              applicant.status
            }</span>
              </td>
              <td data-label="Pre-Approval Status">
                <span class="status-badge badge-${applicant.pre_approval_status.toLowerCase()}"> ${
              applicant.pre_approval_status
            }</span>
              </td>
              <td data-label="Credit Investigation Status">
                <span class="status-badge badge-${applicant.credit_investigation_status.toLowerCase()}"> ${
              applicant.credit_investigation_status
            }</span>
              </td>
              <td data-label="Submission Date"> ${new Date(
                applicant.created_at
              ).toLocaleDateString()}</td>
              <td data-label="Action">
                <a href="#" class="view-btn" onclick="openLoanDetailsModal('${
                  applicant.application_id
                }')"><i class="fas fa-eye"></i> View</a>
              </td>
            `;
            tableBody.appendChild(row);
          });
        }
        filterLoanApplicantsTable(); // Apply filters after refreshing
      } else {
        console.error("Failed to refresh table:", data.message);
      }
    })
    .catch((error) => {
      table.classList.remove("table-loading");
      console.error("Fetch error:", error);
    });
}

// Function to sort the loan applicants table
let sortDirection = {};
function sortLoanApplicantsTable(column) {
  const table = document.querySelector(".loan-table");
  const tbody = document.getElementById("loanApplicantsTableBody");

  // Null checks
  if (!table || !tbody) {
    console.warn("Table or tbody not found");
    return;
  }

  const rows = Array.from(tbody.querySelectorAll("tr"));
  const th = table.querySelector(`th[data-sort="${column}"]`);

  if (!th) {
    console.warn(`Header for column '${column}' not found`);
    return;
  }

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
      aValue = parseFloat(a.cells[2].textContent.replace(" PHP", ""));
      bValue = parseFloat(b.cells[2].textContent.replace(" PHP", ""));
    } else if (column === "final_loan_amount") {
      aValue = a.cells[3].textContent.includes("Not set")
        ? 0
        : parseFloat(a.cells[3].textContent.replace(" PHP", ""));
      bValue = b.cells[3].textContent.includes("Not set")
        ? 0
        : parseFloat(b.cells[3].textContent.replace(" PHP", ""));
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

// Function to filter the loan applicants table
function filterLoanApplicantsTable() {
  const filterNameEl = document.getElementById("filter-name");
  const filterLoanTypeEl = document.getElementById("filter-loan-type");
  const filterLoanStatusEl = document.getElementById("filter-loan-status");
  const filterPreApprovalEl = document.getElementById("filter-pre-approval");
  const filterCreditStatusEl = document.getElementById("filter-credit-status");

  // Guard against null elements
  if (
    !filterNameEl ||
    !filterLoanTypeEl ||
    !filterLoanStatusEl ||
    !filterPreApprovalEl ||
    !filterCreditStatusEl
  ) {
    console.warn("Filter elements not found in DOM");
    return;
  }

  const filterName = filterNameEl.value.trim().toLowerCase();
  const filterLoanType = filterLoanTypeEl.value;
  const filterLoanStatus = filterLoanStatusEl.value;
  const filterPreApproval = filterPreApprovalEl.value;
  const filterCreditStatus = filterCreditStatusEl.value;

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

// Function to clear individual search field
function clearSearch(inputId) {
  const input = document.getElementById(inputId);
  if (input) {
    input.value = "";
    filterLoanApplicantsTable();
  }
}

// Function to clear all filters
function clearAllFilters() {
  document.getElementById("filter-name").value = "";
  document.getElementById("filter-loan-type").value = "";
  document.getElementById("filter-loan-status").value = "";
  document.getElementById("filter-pre-approval").value = "";
  document.getElementById("filter-credit-status").value = "";
  filterLoanApplicantsTable();
  showNotification("All filters cleared", "info");
}

// Function to refresh the due accounts table
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

  // Add loading state to refresh button
  if (refreshBtn) {
    refreshBtn.classList.add("spinning");
    const originalHTML = refreshBtn.innerHTML;
    refreshBtn.innerHTML = "Loading...";
    refreshBtn.dataset.originalHTML = originalHTML;
  }

  fetch("admin1_dashboard.php?action=get_due_accounts", {
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
        refreshBtn.innerHTML =
          refreshBtn.dataset.originalHTML ||
          '<i class="fas fa-sync-alt"></i> Refresh';
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
        refreshBtn.innerHTML =
          refreshBtn.dataset.originalHTML ||
          '<i class="fas fa-sync-alt"></i> Refresh';
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

// Sorting function for due accounts table
function sortDueAccountsTable(column) {
  const table = document.querySelector(".due-accounts-table");
  const tbody = document.getElementById("dueAccountsTableBody");
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
    if (column === "due_date") {
      aValue = new Date(a.cells[0].textContent).getTime();
      bValue = new Date(b.cells[0].textContent).getTime();
    } else if (column === "account_holder") {
      aValue = a.cells[1].textContent.toLowerCase();
      bValue = b.cells[1].textContent.toLowerCase();
    } else if (column === "amount") {
      aValue = parseFloat(a.cells[2].textContent.replace("₱", ""));
      bValue = parseFloat(b.cells[2].textContent.replace("₱", ""));
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

// Debounce function to limit refresh rate
function debounce(func, wait) {
  let timeout;
  return function (...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

// Function to map action types to display names
function getActionTypeDisplay(actionType, actionTypeDisplay) {
  if (actionTypeDisplay) {
    return actionTypeDisplay;
  }

  // Map of action types to display names
  const actionTypeMap = {
    password_reset_requested: "Password Reset Request",
    password_reset_completed: "Password Reset Completed",
    approved_applicant: "Applicant Approved",
    rejected_applicant: "Applicant Rejected",
    loan_created: "Loan Created",
    loan_updated: "Loan Updated",
    payment_received: "Payment Received",
    rate_changed: "Rate Changed",
    admin_login: "Admin Login",
    admin_logout: "Admin Logout",
    credit_check: "Credit Check",
    document_upload: "Document Upload",
  };

  return actionTypeMap[actionType] || actionType;
}

// Function to refresh the activity logs table
// Throttle for activity logs refresh
let activityLogsLastFetch = 0;
let activityLogsThrottleDelay = 3000; // Only refresh every 3 seconds minimum

function refreshActivityLogsTable() {
  const now = Date.now();
  if (now - activityLogsLastFetch < activityLogsThrottleDelay) {
    return; // Skip if throttled
  }
  activityLogsLastFetch = now;

  const container = document.querySelector(".activity_logs");
  if (!container) {
    return;
  }
  container.classList.add("table-loading");
  fetch("admin1_dashboard.php?action=get_activity_logs", {
    cache: "no-store",
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      container.classList.remove("table-loading");
      const tableBody = document.querySelector(".activity-logs-table tbody");
      if (data.success) {
        if (data.activity_logs.length === 0) {
          container.innerHTML =
            '<h2 class="activity_header">Recent Activity Logs</h2><p class="no-activity-logs">No activity logs found.</p>';
        } else {
          if (!document.querySelector(".activity-logs-table")) {
            container.innerHTML = `
              <h2 class="activity_header">Recent Activity Logs</h2>
              <div class="activity-logs-container">
                <table class="activity-logs-table" role="grid" aria-describedby="activity-logs-info">
                  <thead>
                    <tr>
                      <th scope="col">Date & Time</th>
                      <th scope="col">User</th>
                      <th scope="col">Role</th>
                      <th scope="col">Action</th>
                      <th scope="col">Module</th>
                      <th scope="col">Description</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            `;
          }
          const newTableBody = document.querySelector(
            ".activity-logs-table tbody"
          );
          newTableBody.innerHTML = "";
          data.activity_logs.forEach((log) => {
            const row = document.createElement("tr");
            row.innerHTML = `
              <td data-label="DATE & TIME" style="font-weight: 600;">
                  ${new Date(log.created_at).toLocaleString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                    hour: "2-digit",
                    minute: "2-digit",
                    hour12: true,
                  })}
                </td>
              <td data-label="USER" style="font-weight: 600;"> ${
                log.first_name && log.last_name
                  ? log.first_name + " " + log.last_name
                  : "Unknown"
              }</td>
              <td data-label="ROLE" style="font-weight: 600;" class="status-${log.user_role.toLowerCase()}"><span class="status-badge badge-${log.user_role.toLowerCase()}">${
              log.user_role
            }</span></td>
              <td data-label="ACTION" style="font-weight: 600;" class="status-${log.action_type.toLowerCase()}"><span class="status-badge badge-${log.action_type.toLowerCase()}">${getActionTypeDisplay(
              log.action_type,
              log.action_type_display
            )}</span></td>
              <td data-label="MODULE" style="font-weight: 600;">${
                log.module_display
              }</td>
              <td data-label="DESCRIPTION" style="font-weight: 600;">${escapeHtml(
                log.description || ""
              )}</td>
            `;
            newTableBody.appendChild(row);
          });
        }
      } else {
        console.error("Failed to refresh activity logs table:", data.message);
        container.innerHTML =
          '<h2 class="activity_header">Recent Activity Logs</h2><p class="no-activity-logs">Error loading activity logs.</p>';
      }
    })
    .catch((error) => {
      container.classList.remove("table-loading");
      console.error("Fetch error:", error);
      container.innerHTML =
        '<h2 class="activity_header">Recent Activity Logs</h2><p class="no-activity-logs">Error loading activity logs.</p>';
    });
}

// ⚠️ DISABLED: Auto-polling now handled by PollingManager in PHP
// Original JavaScript polling intervals have been replaced with server-side polling
// Set up periodic refresh intervals - prevent duplicates
let loanApplicantsRefreshInterval = null;
let dueAccountsRefreshInterval = null;
let activityLogsRefreshInterval = null;

// DISABLED - PollingManager in PHP handles this now
/*
if (!loanApplicantsRefreshInterval) {
  loanApplicantsRefreshInterval = setInterval(refreshLoanApplicantsTable, 5000);
}
if (
  document.getElementById("dueAccountsContainer") &&
  !dueAccountsRefreshInterval
) {
  dueAccountsRefreshInterval = setInterval(
    debouncedRefreshDueAccountsTable,
    5000
  );
}
if (document.querySelector(".activity_logs") && !activityLogsRefreshInterval) {
  activityLogsRefreshInterval = setInterval(refreshActivityLogsTable, 5000);
}
*/

// Function to stop and restart all refresh intervals
function restartRefreshInterval() {
  // Use PollingManager if available
  if (typeof PollingManager !== "undefined" && PollingManager.resumePolling) {
    PollingManager.resumePolling();
    return;
  }
  // Fallback to old method
  if (typeof refreshInterval !== "undefined") {
    clearInterval(refreshInterval);
    if (typeof refreshLoanApplicantsTable === "function") {
      refreshInterval = setInterval(refreshLoanApplicantsTable, 5000);
    }
  }
}

function restartDueAccountsRefreshInterval() {
  if (document.getElementById("dueAccountsContainer")) {
    // Use PollingManager if available
    if (typeof PollingManager !== "undefined" && PollingManager.resumePolling) {
      PollingManager.resumePolling();
      return;
    }
    // Fallback
    if (typeof dueAccountsRefreshInterval !== "undefined") {
      clearInterval(dueAccountsRefreshInterval);
      if (typeof refreshDueAccountsTable === "function") {
        dueAccountsRefreshInterval = setInterval(refreshDueAccountsTable, 5000);
      }
    }
  }
}

function restartActivityLogsRefreshInterval() {
  // Use PollingManager if available
  if (typeof PollingManager !== "undefined" && PollingManager.resumePolling) {
    PollingManager.resumePolling();
    return;
  }
  // Fallback
  if (typeof activityLogsRefreshInterval !== "undefined") {
    clearInterval(activityLogsRefreshInterval);
    if (typeof refreshActivityLogsTable === "function") {
      activityLogsRefreshInterval = setInterval(refreshActivityLogsTable, 5000);
    }
  }
}

function openLoanDetailsModal(applicationId) {
  console.log("Opening modal for applicationId:", applicationId);

  // Pause polling when modal opens (PollingManager handles this)
  if (typeof PollingManager !== "undefined") {
    PollingManager.pausePolling();
  } else {
    // Fallback for old polling intervals if they exist
    if (
      typeof loanApplicantsRefreshInterval !== "undefined" &&
      loanApplicantsRefreshInterval
    ) {
      clearInterval(loanApplicantsRefreshInterval);
    }
    if (window.dueAccountsRefreshInterval) {
      clearInterval(dueAccountsRefreshInterval);
    }
    if (
      typeof activityLogsRefreshInterval !== "undefined" &&
      activityLogsRefreshInterval
    ) {
      clearInterval(activityLogsRefreshInterval);
    }
  }

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
    `admin1_dashboard.php?action=get_loan_details&application_id=${applicationId}`,
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
        const isCreditStatusEditable =
          loan.pre_approval_status.toLowerCase() === "approved";

        // Format financial information with better organization
        const financialInfo = loan.net_income
          ? `
            <div class="modal-section">
              <h3><i class="fa-solid fa-coins"></i> Financial Information</h3>
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
                        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
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
                        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
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

        // Admin 1-specific update form with improved styling
        const updateForm = `
          <div class="modal-section update-section">
            <h3><i class="fas fa-edit"></i> Credit Investigation Decision</h3>
            <form id="statusUpdateForm" class="status-update-form">
              <input type="hidden" name="action" value="update_status">
              <input type="hidden" name="application_id" value="${
                loan.application_id
              }">
              
              <div class="form-section">
                <div class="form-group">
                  <label for="credit_investigation_status"><i class="fas fa-clipboard-check"></i> Investigation Status:</label>
                  <select name="credit_investigation_status" id="credit_investigation_status" class="form-select" ${
                    isCreditStatusEditable ? "" : "disabled"
                  }>
                    <option value="">-- Select Status --</option>
                    <option value="Pending" ${
                      loan.credit_investigation_status === "Pending"
                        ? "selected"
                        : ""
                    }><i class="fas fa-clock"></i> Pending</option>
                    <option value="Completed" ${
                      loan.credit_investigation_status === "Completed"
                        ? "selected"
                        : ""
                    }><i class="fas fa-check-circle"></i> Completed</option>
                    <option value="Failed" ${
                      loan.credit_investigation_status === "Failed"
                        ? "selected"
                        : ""
                    }><i class="fas fa-times-circle"></i> Failed</option>
                  </select>
                  ${
                    !isCreditStatusEditable
                      ? '<p class="status-warning"><i class="fas fa-exclamation-triangle"></i> Status can only be changed when Pre-Approval is Approved.</p>'
                      : ""
                  }
                </div>
              </div>

              <div class="form-section" id="loan-amount-section" style="display: ${
                isCreditStatusEditable ? "block" : "none"
              };">
                <div class="section-label"><i class="fas fa-coins"></i> Loan Amount Decision</div>
                <div class="form-group">
                  <label for="final_loan_amount"><i class="fas fa-money-check-alt"></i> Final Loan Amount (PHP):</label>
                  <div class="amount-input-wrapper">
                    <input type="number" name="final_loan_amount" id="final_loan_amount" min="10000" step="0.01" value="${
                      loan.final_loan_amount || ""
                    }" placeholder="Enter amount based on your investigation" class="form-input amount-input">
                    <div class="amount-help-text">
                      <small><strong>Applied:</strong> ₱${parseFloat(
                        loan.amount_applied
                      ).toLocaleString("en-US", {
                        minimumFractionDigits: 2,
                      })}</small>
                      <small><strong>Min:</strong> ₱10,000</small>
                      <small><strong>Note:</strong> Investigator may approve an amount higher than applied based on findings</small>
                    </div>
                  </div>
                </div>
              </div>

              <div class="form-section">
                <div class="form-group">
                  <label for="remarks"><i class="fas fa-comment"></i> Remarks/Notes:</label>
                  <textarea name="remarks" id="remarks" placeholder="Add any notes about this credit investigation..." rows="3" class="form-textarea"></textarea>
                </div>
              </div>
              
              <button type="submit" class="submit-btn" ${
                !isCreditStatusEditable ? "disabled" : ""
              }>
                <i class="fas fa-paper-plane"></i> Save Credit Investigation
              </button>
            </form>
          </div>
        `;

        // Populate modal content with improved layout
        content.innerHTML = `
          <div class="loan-details-wrapper">
            <!-- Applicant Information Section -->
            <div class="modal-section applicant-info-section">
              <h3><i class="fas fa-user"></i> Applicant Information</h3>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-id-card"></i> Full Name:</span>
                  <span class="info-value">${loan.first_name} ${
          loan.last_name
        }</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-envelope"></i> Email:</span>
                  <span class="info-value">${loan.email}</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-phone"></i> Contact:</span>
                  <span class="info-value">${
                    loan.contact || "Not provided"
                  }</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-birthday-cake"></i> Birthday:</span>
                  <span class="info-value">${
                    loan.birthday
                      ? new Date(loan.birthday).toLocaleDateString("en-US", {
                          month: "long",
                          day: "numeric",
                          year: "numeric",
                        })
                      : "Not provided"
                  }</span>
                </div>
              </div>
            </div>

            <!-- Loan Details Section -->
            <div class="modal-section loan-info-section">
              <h3><i class="fa-solid fa-rectangle-list"></i> Loan Details</h3>
              <div class="info-grid">
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-tag"></i> Loan Type:</span>
                  <span class="info-value">${loan.type_name}</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-money-bill"></i> Amount Applied:</span>
                  <span class="info-value amount">₱${parseFloat(
                    loan.amount_applied
                  ).toLocaleString("en-US", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })}</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-money-check-alt"></i> Final Loan Amount:</span>
                  <span class="info-value amount">${
                    loan.final_loan_amount
                      ? "₱" +
                        parseFloat(loan.final_loan_amount).toLocaleString(
                          "en-US",
                          { minimumFractionDigits: 2, maximumFractionDigits: 2 }
                        )
                      : "Not set"
                  }</span>
                </div>
                <div class="info-item">
                  <span class="info-label"><i class="fas fa-calendar-alt"></i> Submission Date:</span>
                  <span class="info-value">${new Date(
                    loan.created_at
                  ).toLocaleDateString("en-US", {
                    month: "long",
                    day: "numeric",
                    year: "numeric",
                  })}</span>
                </div>
                <div class="info-item full-width">
                  <span class="info-label">Loan Status:</span>
                  <span class="status-badge status-${loan.status.toLowerCase()}"> ${
          loan.status
        }</span>
                </div>
                <div class="info-item full-width">
                  <span class="info-label">Pre-Approval Status:</span>
                  <span class="status-badge status-${loan.pre_approval_status.toLowerCase()}"> ${
          loan.pre_approval_status
        }</span>
                </div>
                <div class="info-item full-width">
                  <span class="info-label">Credit Investigation:</span>
                  <span class="status-badge status-${loan.credit_investigation_status.toLowerCase()}"> ${
          loan.credit_investigation_status
        }</span>
                </div>
              </div>
            </div>

            ${financialInfo}
            ${documentsTable}
            ${updateForm}
            ${remarksSection}
            ${logsSection}
          </div>
        `;

        // Attach event listeners for Admin 1 form
        attachAdmin1ModalEventListeners(
          loan.application_id,
          isCreditStatusEditable,
          loan.amount_applied
        );

        console.log("Modal content rendered successfully");
      } else {
        content.innerHTML = `<p class="error">Error: ${data.message}</p>`;
        console.error("Modal data error:", data.message);
      }
    })
    .catch((error) => {
      content.innerHTML = `<p class="error">Error loading loan details: ${error.message}</p>`;
      console.error("Fetch error in modal:", error);
    });
}

// Ensure functions are globally accessible
window.openLoanDetailsModal = openLoanDetailsModal;
window.closeLoanDetailsModal = closeLoanDetailsModal;

function closeLoanDetailsModal() {
  const modal = document.getElementById("loanDetailsModal");
  if (modal) {
    modal.classList.remove("show");
    setTimeout(() => {
      modal.style.display = "none";

      // Resume polling when modal closes (PollingManager handles this)
      if (typeof PollingManager !== "undefined") {
        PollingManager.resumePolling();
      } else {
        // Fallback for old polling intervals
        restartRefreshInterval();
        if (window.restartDueAccountsRefreshInterval) {
          restartDueAccountsRefreshInterval();
        }
        restartActivityLogsRefreshInterval();
      }
    }, 300);
  }
}

// Function to attach event listeners to modal forms (Admin 1 specific)
function attachAdmin1ModalEventListeners(
  applicationId,
  isCreditStatusEditable,
  amountApplied
) {
  // Handle credit investigation status change to show/hide loan amount section
  const creditStatusSelect = document.getElementById(
    "credit_investigation_status"
  );
  if (creditStatusSelect) {
    function toggleLoanAmountSection() {
      const loanAmountSection = document.getElementById("loan-amount-section");
      const creditStatus = creditStatusSelect.value;
      if (creditStatus && isCreditStatusEditable) {
        loanAmountSection.style.display = "block";
      } else {
        loanAmountSection.style.display = "none";
      }
    }

    toggleLoanAmountSection();
    creditStatusSelect.addEventListener("change", toggleLoanAmountSection);
  }

  // Status update form submission handler
  const statusForm = document.getElementById("statusUpdateForm");
  if (statusForm) {
    statusForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const creditStatus = document.getElementById(
        "credit_investigation_status"
      ).value;
      const finalLoanAmountInput = document.getElementById("final_loan_amount");
      const finalLoanAmount = finalLoanAmountInput
        ? finalLoanAmountInput.value
        : null;
      const remarks = document.getElementById("remarks").value.trim();

      // Debug logging
      console.log("Form submission data:", {
        creditStatus,
        finalLoanAmount,
        remarks,
        applicationId,
      });

      // Status and Amount are REQUIRED together
      if (!creditStatus && !finalLoanAmount) {
        showNotification(
          "Both Credit Investigation Status and Final Loan Amount are required.",
          "warning"
        );
        return;
      }

      if (creditStatus && !finalLoanAmount) {
        showNotification(
          "Please enter the Final Loan Amount when changing status.",
          "warning"
        );
        return;
      }

      if (finalLoanAmount && !creditStatus) {
        showNotification(
          "Please select a Credit Investigation Status when entering the loan amount.",
          "warning"
        );
        return;
      }

      // Validate final loan amount
      if (finalLoanAmount) {
        const amount = parseFloat(finalLoanAmount);
        if (isNaN(amount) || amount <= 0) {
          showNotification(
            "Final Loan Amount must be a valid positive number.",
            "error"
          );
          return;
        }
        if (amount < 10000) {
          showNotification(
            "Final Loan Amount must be at least ₱10,000.",
            "error"
          );
          return;
        }
        // Note: removed client-side cap vs applied amount; investigators may approve higher amounts.
      }

      // Prepare form data
      const formData = new FormData(this);

      // Debug: Log form data contents
      console.log("FormData contents:");
      for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
      }

      // Show loading overlay
      showLoadingOverlay("Saving credit investigation decision...");

      fetch("admin1_dashboard.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => {
          console.log("Fetch response status:", response.status);
          return response.json().then((data) => ({
            status: response.status,
            data: data,
          }));
        })
        .then((result) => {
          console.log("Server response:", result);
          hideLoadingOverlay();
          if (result.data.success) {
            showNotification(result.data.message, "success");
            setTimeout(() => {
              openLoanDetailsModal(applicationId); // Refresh modal
              refreshLoanApplicantsTable(); // Refresh table
            }, 500);
          } else {
            showNotification("Error: " + result.data.message, "error");
            console.error("Server error details:", result.data);
          }
        })
        .catch((error) => {
          hideLoadingOverlay();
          console.error("Fetch error:", error);
          showNotification("Error updating status: " + error.message, "error");
        });
    });
  }
}

// Function to send payment reminder
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

  fetch("admin1_dashboard.php", {
    method: "POST",
    body: formData,
    cache: "no-store",
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
          // Refresh activity logs to show new entry
          setTimeout(() => refreshActivityLogsTable(), 500);
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

// Initialize tables and intervals after DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  refreshLoanApplicantsTable();
  if (document.getElementById("dueAccountsContainer")) {
    refreshDueAccountsTable();
    dueAccountsRefreshInterval = setInterval(refreshDueAccountsTable, 5000);
  }
  refreshActivityLogsTable();
  activityLogsRefreshInterval = setInterval(refreshActivityLogsTable, 5000);
});

// Clean up intervals on page unload
window.addEventListener("unload", () => {
  clearInterval(refreshInterval);
  clearInterval(dueAccountsRefreshInterval);
  clearInterval(activityLogsRefreshInterval);
});

// ==================== INTEREST RATE MANAGEMENT (READ-ONLY FOR ADMIN1) ====================
function openManageInterestRateModal() {
  const modal = document.getElementById("manageInterestRateModal");
  const ratesGrid = document.getElementById("ratesGrid");
  const historyTable = document.getElementById("interestRateHistoryTable");
  const historyLoading = document.getElementById("historyLoading");

  // Check if required elements exist
  if (!modal || !ratesGrid || !historyTable) {
    console.error("Missing required elements for interest rate modal");
    return;
  }

  // Clear and show loading
  ratesGrid.innerHTML =
    '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
  historyTable.innerHTML =
    '<tr><td colspan="5"><div class="spinner"></div></td></tr>';
  if (historyLoading) historyLoading.style.display = "block";

  modal.style.display = "block";
  setTimeout(() => modal.classList.add("show"), 10);

  // Load current interest rates from API
  fetch("interest_rate_api.php?action=get_all_interest_rates", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const rates = data.rates;

        // Update stats
        updateInterestRateStats(rates);

        // Render rate cards (read-only - no edit button)
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
                <div class="rate-card">
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

  // Load interest rate history from API
  fetch("interest_rate_api.php?action=get_interest_rate_history", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (historyLoading) historyLoading.style.display = "none";
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
      if (historyLoading) historyLoading.style.display = "none";
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

function closeManageInterestRateModal() {
  const modal = document.getElementById("manageInterestRateModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

// Attach event listener for View Interest Rates button
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

  // ==================== VIEW INTEREST RATES ====================
  const viewInterestBtn = document.getElementById("viewInterestRatesBtn");
  if (viewInterestBtn) {
    viewInterestBtn.addEventListener("click", function (e) {
      e.preventDefault();
      openManageInterestRateModal();
    });
  }

  // Attach event listener for close button
  const closeModalBtn = document.getElementById("closeInterestRateModal");
  if (closeModalBtn) {
    closeModalBtn.addEventListener("click", function () {
      closeManageInterestRateModal();
    });
  }

  // Close modal when clicking outside
  const modal = document.getElementById("manageInterestRateModal");
  if (modal) {
    window.addEventListener("click", function (event) {
      if (event.target === modal) {
        closeManageInterestRateModal();
      }
    });
  }
});
