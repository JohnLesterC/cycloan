const adminRole = "<?php echo $adminRole; ?>";

// Notification System
function showNotification(message, type = "success") {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(
    ".custom-notification"
  );
  existingNotifications.forEach((notif) => notif.remove());

  // Create notification element
  const notification = document.createElement("div");
  notification.className = `custom-notification ${type}`;

  // Set icon based on type
  let icon = "";
  if (type === "success") {
    icon = '<i class="fas fa-check-circle"></i>';
  } else if (type === "error") {
    icon = '<i class="fas fa-exclamation-circle"></i>';
  } else if (type === "info") {
    icon = '<i class="fas fa-info-circle"></i>';
  } else if (type === "warning") {
    icon = '<i class="fas fa-exclamation-triangle"></i>';
  }

  notification.innerHTML = `
    ${icon}
    <span class="notification-message">${message}</span>
    <button class="notification-close" onclick="this.parentElement.remove()">
      <i class="fas fa-times"></i>
    </button>
  `;

  document.body.appendChild(notification);

  // Trigger animation
  setTimeout(() => notification.classList.add("show"), 10);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    notification.classList.remove("show");
    setTimeout(() => notification.remove(), 300);
  }, 5000);
}

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

// Function to update document status
function updateDocumentStatus(applicationId, documentId, status) {
  const formData = new FormData();
  formData.append("action", "update_document_status");
  formData.append("application_id", applicationId);
  formData.append("document_id", documentId);
  formData.append("status", status);

  showLoadingOverlay("Updating document status...");

  fetch("admin2_dashboard.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoadingOverlay();
      if (data.success) {
        showNotification(data.message, "success");
        setTimeout(() => {
          openLoanDetailsModal(applicationId); // Refresh modal
        }, 500);
      } else {
        showNotification("Error: " + data.message, "error");
      }
    })
    .catch((error) => {
      hideLoadingOverlay();
      showNotification(
        "Error updating document status: " + error.message,
        "error"
      );
    });
}

// Function to update loan status
function updateLoanStatus(applicationId) {
  const preApprovalStatus = document.getElementById(
    "pre_approval_status"
  ).value;
  const status = document.getElementById("status").value;
  const remarks = document.getElementById("remarks").value;

  const formData = new FormData();
  formData.append("action", "update_status");
  formData.append("application_id", applicationId);
  formData.append("pre_approval_status", preApprovalStatus);
  formData.append("status", status);
  formData.append("remarks", remarks);

  // Show loading overlay
  showLoadingOverlay("Updating loan status...");

  fetch("admin2_dashboard.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoadingOverlay();
      if (data.success) {
        alert(data.message);
        refreshLoanApplicantsTable();
        openLoanDetailsModal(applicationId); // Refresh modal
      } else {
        alert("Error: " + data.message);
      }
    })
    .catch((error) => {
      hideLoadingOverlay();
      alert("Error updating status: " + error.message);
    });
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

  // Show loading overlay with status
  showLoadingOverlay(
    "<div style='text-align: center;'><i class='fas fa-envelope fa-2x' style='margin-bottom: 10px; display: block;'></i><span>Sending payment reminder...</span></div>"
  );

  fetch("admin2_dashboard.php", {
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

// Function to refresh the loan applicants table
// Throttle for loan applicants refresh
let loanApplicantsLastFetch = 0;
let loanApplicantsThrottleDelay = 3000; // Only refresh every 3 seconds minimum

function refreshLoanApplicantsTable() {
  try {
    const now = Date.now();
    if (now - loanApplicantsLastFetch < loanApplicantsThrottleDelay) {
      return; // Skip if throttled
    }
    loanApplicantsLastFetch = now;

    const table = document.querySelector(".loan-table");
    if (!table) return; // Exit if table doesn't exist

    const tableBody = document.querySelector("#loanApplicantsTableBody");
    if (tableBody) {
      tableBody.closest("table").classList.add("table-loading");
    }
    fetch("admin2_dashboard.php?action=get_loan_applicants", {
      cache: "no-store",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
      })
      .then((data) => {
        if (tableBody && tableBody.closest("table")) {
          tableBody.closest("table").classList.remove("table-loading");
        }
        if (
          data.success &&
          data.loan_applicants &&
          Array.isArray(data.loan_applicants)
        ) {
          const tableBody = document.querySelector("#loanApplicantsTableBody");
          if (!tableBody) {
            console.error("Table body element not found");
            return;
          }
          tableBody.innerHTML = "";
          if (data.loan_applicants.length === 0) {
            tableBody.innerHTML =
              '<tr><td colspan="9">No loan applicants found.</td></tr>';
          } else {
            data.loan_applicants.forEach((applicant) => {
              const row = document.createElement("tr");
              row.setAttribute("data-app-id", applicant.application_id);

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
                <span class="status-badge badge-${
                  applicant.status ? applicant.status.toLowerCase() : "unknown"
                }">${applicant.status || "Unknown"}</span>
              </td>
              <td data-label="Pre-Approval Status">
                <span class="status-badge badge-${
                  applicant.pre_approval_status
                    ? applicant.pre_approval_status.toLowerCase()
                    : "unknown"
                }">${applicant.pre_approval_status || "Unknown"}</span>
              </td>
              <td data-label="Credit Investigation Status">
                <span class="status-badge badge-${
                  applicant.credit_investigation_status
                    ? applicant.credit_investigation_status.toLowerCase()
                    : "unknown"
                }">${applicant.credit_investigation_status || "Unknown"}</span>
              </td>
              <td data-label="Submission Date">
                  ${new Date(applicant.created_at).toLocaleString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric",
                  })}
              </td>

              <td data-label="Action">
                <a href="#" class="view-btn" onclick="openLoanDetailsModal('${
                  applicant.application_id
                }'); return false;"><i class="fas fa-eye"></i> View</a>
              </td>
            `;
              tableBody.appendChild(row);
            });
          }
          filterLoanApplicantsTable(); // Apply filters after refreshing
        } else {
          // Handle case where data.loan_applicants is undefined or not an array
          const tableBody = document.querySelector("#loanApplicantsTableBody");
          if (tableBody) {
            tableBody.innerHTML =
              '<tr><td colspan="9">No loan applicants data available.</td></tr>';
          }
          console.error(
            "Failed to refresh table: Invalid or missing loan_applicants data",
            data
          );
        }
      })
      .catch((error) => {
        const tableBody = document.querySelector("#loanApplicantsTableBody");
        if (tableBody && tableBody.closest("table")) {
          tableBody.closest("table").classList.remove("table-loading");
        }
        console.error("Fetch error:", error);
        // Show user-friendly message in table
        if (tableBody) {
          tableBody.innerHTML =
            '<tr><td colspan="9">Error loading loan applicants. Please refresh the page.</td></tr>';
        }
      });
  } catch (globalError) {
    console.error(
      "Unexpected error in refreshLoanApplicantsTable:",
      globalError
    );
    const tableBody = document.querySelector("#loanApplicantsTableBody");
    if (tableBody && tableBody.closest("table")) {
      tableBody.closest("table").classList.remove("table-loading");
    }
    if (tableBody) {
      tableBody.innerHTML =
        '<tr><td colspan="9">Error loading loan applicants. Please refresh the page.</td></tr>';
    }
  }
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
    const name = row.cells[0].textContent.toLowerCase().trim();
    const loanType = row.cells[1].textContent.trim();
    const loanStatus = row.cells[4].textContent.trim();
    const preApprovalStatus = row.cells[5].textContent.trim();
    const creditStatus = row.cells[6].textContent.trim();

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
  const searchInput = document.getElementById("filter-name");
  const clearIcon = document.querySelector(".clear-search");

  if (searchInput && clearIcon) {
    searchInput.addEventListener("input", function () {
      clearIcon.style.display = this.value.trim() !== "" ? "block" : "none";
    });
  }
});

// Function to refresh the due accounts table
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
  if (!container) {
    return;
  }

  const refreshBtn = document.querySelector(".refresh-btn");
  if (refreshBtn) {
    refreshBtn.classList.add("spinning");
  }

  container.classList.add("table-loading");

  fetch("admin2_dashboard.php?action=get_due_accounts", {
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
      if (refreshBtn) {
        refreshBtn.classList.remove("spinning");
      }

      if (data.success) {
        if (data.due_accounts.length === 0) {
          container.innerHTML = `
            <div class="empty-state">
              <i class="fa-solid fa-circle-check"></i>
              <p>No due accounts found. All payments are on track!</p>
            </div>
          `;
        } else {
          const tableHTML = generateDueAccountsTable(data.due_accounts);
          container.innerHTML = tableHTML;
        }
      } else {
        container.innerHTML = `
          <div class="error-state">
            <i class="fas fa-exclamation-triangle"></i>
            <p>Error loading due accounts. Please try again.</p>
          </div>
        `;
      }
    })
    .catch((error) => {
      container.classList.remove("table-loading");
      if (refreshBtn) {
        refreshBtn.classList.remove("spinning");
      }

      console.error("Fetch error:", error);
      container.innerHTML = `
        <div class="error-state">
          <i class="fas fa-exclamation-triangle"></i>
          <p>Failed to load due accounts.</p>
        </div>
      `;
    });
}

// Generate due accounts table HTML
function generateDueAccountsTable(dueAccounts) {
  let html = `
    <div class="scrollable-table">
      <table class="due-accounts-table" role="grid" aria-describedby="due-accounts-info">
        <thead>
          <tr>
            <th scope="col" data-sort="due_date" onclick="sortDueAccountsTable('due_date')">
              Due Date <span class="sort-icon"></span>
            </th>
            <th scope="col" data-sort="account_holder" onclick="sortDueAccountsTable('account_holder')">
              Account Holder <span class="sort-icon"></span>
            </th>
            <th scope="col" data-sort="amount" onclick="sortDueAccountsTable('amount')">
              Amount <span class="sort-icon"></span>
            </th>
            <th scope="col">Actions</th>
          </tr>
        </thead>
        <tbody id="dueAccountsTableBody">
  `;

  dueAccounts.forEach((due) => {
    const dueDate = new Date(due.due_date);
    const today = new Date();
    const daysUntilDue = Math.floor((dueDate - today) / (1000 * 60 * 60 * 24));
    const urgencyClass =
      daysUntilDue <= 7 ? "urgent" : daysUntilDue <= 15 ? "warning" : "normal";
    const urgencyBadge =
      daysUntilDue <= 7
        ? `<span class="urgency-badge urgent"><i class="fas fa-exclamation-circle"></i> ${
            daysUntilDue <= 0 ? "Overdue" : "Urgent"
          }</span>`
        : daysUntilDue <= 15
        ? `<span class="urgency-badge warning"><i class="fas fa-clock"></i> Due Soon</span>`
        : "";

    html += `
      <tr class="due-row ${urgencyClass}">
        <td data-label="Due Date">
          <span class="date-badge">${dueDate.toLocaleDateString("en-US", {
            month: "short",
            day: "2-digit",
            year: "numeric",
          })}</span>
          ${urgencyBadge}
        </td>
        <td data-label="Account Holder">
          <div class="account-holder-info">
            <strong>${escapeHtml(due.first_name + " " + due.last_name)}</strong>
            <small class="account-id">ID: ${escapeHtml(
              due.application_id
            )}</small>
          </div>
        </td>
        <td data-label="Amount">
          <span class="amount-badge">₱${parseFloat(due.amount).toFixed(
            2
          )}</span>
        </td>
        <td data-label="Actions">
          <div class="action-buttons">
            <a href="#" class="view-btn" onclick="openLoanDetailsModal('${escapeHtml(
              due.application_id
            )}'); return false;" 
              title="View Loan Details" aria-label="View loan details for ${escapeHtml(
                due.first_name
              )}">
              <i class="fas fa-eye"></i> View
            </a>
            </button>
            <button class="action-btn reminder-btn" onclick="sendPaymentReminder(${
              due.payment_id
            })" 
              title="Send Payment Reminder" aria-label="Send payment reminder to ${escapeHtml(
                due.first_name
              )}">
              <i class="fas fa-envelope"></i> Remind
            </button>
          </div>
        </td>
      </tr>
    `;
  });

  html += `
        </tbody>
      </table>
    </div>
  `;

  return html;
}

// Helper function to escape HTML special characters
function escapeHtml(text) {
  const map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;",
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

// Show notification message
function showNotification(message, type = "info") {
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
    <span>${message}</span>
  `;

  const container = document.getElementById("dueAccountsContainer");
  if (container && container.parentElement) {
    container.parentElement.insertBefore(notification, container);

    // Auto-remove notification after 4 seconds
    setTimeout(() => {
      notification.classList.add("fade-out");
      setTimeout(() => notification.remove(), 300);
    }, 4000);
  }
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

// Update refresh interval with debouncing
const debouncedRefreshDueAccountsTable = debounce(refreshDueAccountsTable, 500);
let dueAccountsRefreshInterval = null;
let loanApplicantsRefreshInterval = null;
let activityLogsRefreshInterval = null;

// Initialize intervals only once
if (
  document.getElementById("dueAccountsContainer") &&
  !dueAccountsRefreshInterval
) {
  dueAccountsRefreshInterval = setInterval(
    debouncedRefreshDueAccountsTable,
    5000
  );
}
if (document.querySelector(".loan-table") && !loanApplicantsRefreshInterval) {
  loanApplicantsRefreshInterval = setInterval(refreshLoanApplicantsTable, 5000);
}
if (
  document.getElementById("activityLogsTableBody") &&
  !activityLogsRefreshInterval
) {
  activityLogsRefreshInterval = setInterval(refreshActivityLogs, 5000);
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

// Throttle for activity logs refresh
let activityLogsLastFetch = 0;
let activityLogsThrottleDelay = 3000; // Only refresh every 3 seconds minimum

function refreshActivityLogs() {
  const now = Date.now();
  if (now - activityLogsLastFetch < activityLogsThrottleDelay) {
    return; // Skip if throttled
  }
  activityLogsLastFetch = now;

  const tableBody = document.getElementById("activityLogsTableBody");

  // Check if element exists before proceeding
  if (!tableBody) {
    return;
  }

  fetch("admin2_dashboard.php?action=get_activity_logs", { cache: "no-store" })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        tableBody.innerHTML = "";
        if (data.activity_logs.length === 0) {
          tableBody.innerHTML =
            '<tr><td colspan="7" class="no-activity-logs">No activity logs found.</td></tr>';
          return;
        }
        data.activity_logs.forEach((log) => {
          const date = new Date(log.created_at);
          const options = {
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
            hour12: true,
          };
          const formatted = date
            .toLocaleString("en-US", options)
            .replace(",", "");

          const row = document.createElement("tr");
          row.innerHTML = `
            <td data-label="DATE & TIME" style="font-weight: 600;">${formatted}</td>
            <td data-label="USER" style="font-weight: 600;">${
              log.first_name && log.last_name
                ? `${log.first_name} ${log.last_name}`
                : "Unknown"
            }</td>
            <td data-label="ROLE" style="font-weight: 600;" class="status-${log.user_role.toLowerCase()}">
              <span class="status-badge badge-${log.user_role.toLowerCase()}">${
            log.user_role
          }</span>
            </td>
            <td data-label="ACTION" style="font-weight: 600;" class="status-${log.action_type.toLowerCase()}">
              <span class="status-badge badge-${log.action_type.toLowerCase()}">${getActionTypeDisplay(
            log.action_type,
            log.action_type_display
          )}</span>
            </td>
            <td data-label="MODULE" style="font-weight: 600;">${
              log.module_display || log.module
            }</td>
            <td data-label="DESCRIPTION" style="font-weight: 600;">${escapeHtml(
              log.description || ""
            )}</td>
          `;
          tableBody.appendChild(row);
        });
      } else {
        console.error("Error refreshing activity logs:", data.message);
      }
    })
    .catch((error) => console.error("Error fetching activity logs:", error));
}

// Function to export table data to CSV
function exportToCSV() {
  const table = document.querySelector(".loan-table");
  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.querySelectorAll("tr"));

  // Check if there's data to export
  if (rows.length === 0 || rows[0].cells.length === 1) {
    alert("No data available to export.");
    return;
  }

  // CSV header with proper column names
  const headers = [
    "Application ID",
    "Applicant Name",
    "Loan Type",
    "Amount Applied (PHP)",
    "Final Loan Amount (PHP)",
    "Loan Status",
    "Pre-Approval Status",
    "Credit Investigation Status",
    "Submission Date",
  ];

  // Start building CSV with UTF-8 BOM for Excel compatibility
  let csvContent = "\uFEFF"; // UTF-8 BOM

  // Add headers
  csvContent += headers.map((h) => `"${h}"`).join(",") + "\r\n";

  // Extract and format data from visible rows only
  let recordCount = 0;
  rows.forEach((row) => {
    // Skip hidden rows (filtered out)
    if (row.style.display === "none") return;

    const cells = Array.from(row.querySelectorAll("td"));
    if (cells.length === 0) return;

    // Extract application ID from the View button onclick
    const viewBtn = row.querySelector(".view-btn");
    const applicationId = viewBtn
      ? viewBtn.getAttribute("onclick").match(/\d+/)[0]
      : "N/A";

    const rowData = [
      applicationId, // Application ID
      cells[0].textContent.trim(), // Applicant Name
      cells[1].textContent.trim(), // Loan Type
      cells[2].textContent.trim().replace(" PHP", ""), // Amount Applied
      cells[3].textContent.trim().replace(" PHP", ""), // Final Loan Amount
      cells[4].textContent.trim(), // Loan Status
      cells[5].textContent.trim(), // Pre-Approval Status
      cells[6].textContent.trim(), // Credit Investigation Status
      cells[7].textContent.trim(), // Submission Date
    ];

    // Properly escape and format each cell
    const formattedRow = rowData
      .map((cell) => {
        let text = String(cell).trim();
        // Remove any existing quotes and escape new ones
        text = text.replace(/"/g, '""');
        // Wrap in quotes
        return `"${text}"`;
      })
      .join(",");

    csvContent += formattedRow + "\r\n";
    recordCount++;
  });

  // Create blob for download
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);

  // Create download link
  const currentDate = new Date();
  const link = document.createElement("a");
  const fileName = `CLDD_Loan_Applicants_${
    currentDate.toISOString().split("T")[0]
  }_${currentDate.getHours()}${String(currentDate.getMinutes()).padStart(
    2,
    "0"
  )}${String(currentDate.getSeconds()).padStart(2, "0")}.csv`;

  link.setAttribute("href", url);
  link.setAttribute("download", fileName);
  link.style.display = "none";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  // Clean up the URL
  URL.revokeObjectURL(url);

  // Show success message
  console.log(`✅ Exported ${recordCount} records to ${fileName}`);

  // Show user-friendly notification
  const notification = document.createElement("div");
  notification.innerHTML = `
    <div style="position: fixed; top: 20px; right: 20px; background: #2e7d32; color: white; 
                padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000; font-family: 'Poppins', sans-serif; animation: slideIn 0.3s ease;">
      <i class="fas fa-check-circle"></i> 
      <strong>Export Successful!</strong><br>
      <small>${recordCount} records exported to ${fileName}</small>
    </div>
    <style>
      @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
    </style>
  `;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.transition = "opacity 0.3s ease";
    notification.style.opacity = "0";
    setTimeout(() => document.body.removeChild(notification), 300);
  }, 3000);
}

// Intervals already set up at page initialization above

// Close modal when clicking outside
window.addEventListener("click", (event) => {
  const modal = document.getElementById("loanDetailsModal");
  if (event.target === modal) {
    closeLoanDetailsModal();
  }
});

// ==================== INTEREST RATE MANAGEMENT (READ-ONLY FOR ADMIN2) ====================
function openManageInterestRateModal() {
  const modal = document.getElementById("manageInterestRateModal");
  const ratesGrid = document.getElementById("ratesGrid");
  const historyTable = document.getElementById("interestRateHistoryTable");
  const historyLoading = document.getElementById("historyLoading");

  // Clear and show loading
  ratesGrid.innerHTML =
    '<div class="rate-card-skeleton"><div class="spinner"></div><p>Loading rates...</p></div>';
  historyTable.innerHTML =
    '<tr><td colspan="5"><div class="spinner"></div></td></tr>';
  historyLoading.style.display = "block";

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
