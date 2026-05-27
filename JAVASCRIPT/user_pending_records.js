// user_pending_records.js - COMPLETE LOAN CALCULATOR (EXACTLY like user_dashboard.js)

let isModalOpening = false;

// Call immediately to ensure modals are available
if (document.readyState === "loading") {
  // Document still loading
  document.addEventListener("DOMContentLoaded", ensureModalExists);
} else {
  // Document already loaded
  ensureModalExists();
}

function showToast(message, type = "success") {
  Toastify({
    text: message,
    duration: 3000,
    close: true,
    gravity: "top",
    position: "right",
    backgroundColor: type === "success" ? "#28a745" : "#dc2626",
    stopOnFocus: true,
  }).showToast();
}

// ========== ENSURE MODAL EXISTS ==========
function ensureModalExists() {
  // Check if modals already exist
  if (document.getElementById("loanDetailsModal")) {
    return; // Already exists
  }

  // Create the modals if they don't exist
  const modalsHTML = `
    <div id="loanDetailsModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h2 id="loanDetailsModalLabel">Loan Application Details</h2>
          <span class="close" onclick="closeLoanDetailsModal()" role="button" aria-label="Close modal">×</span>
        </div>
        <div class="modal-body">
          <div id="loanDetailsContent" class="application-details">
            <p>Loading...</p>
          </div>
        </div>
      </div>
    </div>
    <div id="loanCalculatorModal" class="calculatorModal" style="display:none;">
      <div class="calculatorModal-content">
        <div class="calculatorModal-header">
          <h2><i class="fa-solid fa-calculator"></i> Loan Calculator</h2>
          <span class="close" onclick="closeCalculatorModal()">×</span>
        </div>
        <div class="calculatorModal-body">Calculator will load here</div>
      </div>
    </div>
  `;

  // Append to body
  const tempDiv = document.createElement("div");
  tempDiv.innerHTML = modalsHTML;
  document.body.appendChild(tempDiv.firstElementChild);
  document.body.appendChild(tempDiv.firstElementChild);
}

// ========== SIDEBAR TOGGLE (ENHANCED) ==========
document.addEventListener("DOMContentLoaded", function () {
  // Ensure modals exist
  ensureModalExists();

  const burger = document.querySelector(".burger");
  const nav = document.querySelector("nav");
  const navContainer = document.querySelector(".nav-container");
  const body = document.body;

  // Toggle burger menu
  if (burger && nav) {
    burger.addEventListener("click", function (e) {
      e.stopPropagation();
      this.classList.toggle("active");
      nav.classList.toggle("active");
      nav.classList.toggle("open");

      if (navContainer) {
        navContainer.classList.toggle("expanded");
      }

      // Prevent body scroll when menu is open
      if (nav.classList.contains("active")) {
        body.style.overflow = "hidden";
      } else {
        body.style.overflow = "";
      }
    });
  }

  // Close menu when clicking outside
  document.addEventListener("click", function (e) {
    if (
      nav &&
      burger &&
      !nav.contains(e.target) &&
      !burger.contains(e.target)
    ) {
      nav.classList.remove("active", "open");
      burger.classList.remove("active");
      if (navContainer) {
        navContainer.classList.remove("expanded");
      }
      body.style.overflow = "";
    }
  });

  // Close menu when clicking nav links (mobile)
  if (nav) {
    const navLinks = nav.querySelectorAll("a");
    navLinks.forEach((link) => {
      link.addEventListener("click", function () {
        if (window.innerWidth <= 768) {
          nav.classList.remove("active", "open");
          if (burger) burger.classList.remove("active");
          if (navContainer) navContainer.classList.remove("expanded");
          body.style.overflow = "";
        }
      });
    });
  }
});

// ========== PROFILE DROPDOWN ==========
function toggleDropdown(event) {
  event.stopPropagation();
  document.getElementById("dropdown").classList.toggle("show");
}

window.onclick = function (event) {
  if (!event.target.closest(".profile-container")) {
    document.getElementById("dropdown").classList.remove("show");
  }
  if (
    event.target.classList.contains("modal") &&
    !event.target.closest(".modal-content")
  ) {
    if (event.target.id === "loanDetailsModal") closeLoanDetailsModal();
    else if (event.target.id === "loanCalculatorModal") closeCalculatorModal();
  }
};

// ========== LOAN DETAILS MODAL (PROFESSIONAL DESIGN) ==========
function openLoanDetailsModal(applicationId) {
  let modal = document.getElementById("loanDetailsModal");
  let content = document.getElementById("loanDetailsContent");

  // If elements don't exist, try to find them or create them
  if (!modal || !content) {
    console.error("Modal not found, attempting to create...");

    // Try waiting a moment and checking again
    setTimeout(() => {
      modal = document.getElementById("loanDetailsModal");
      content = document.getElementById("loanDetailsContent");

      if (!modal || !content) {
        console.error(
          "Modal still not found after delay. Creating new modal structure..."
        );
        ensureModalExists();
        modal = document.getElementById("loanDetailsModal");
        content = document.getElementById("loanDetailsContent");
      }

      if (modal && content) {
        proceedWithModalOpen(applicationId, modal, content);
      } else {
        alert("Unable to load modal. Please refresh the page.");
      }
    }, 100);
    return;
  }

  proceedWithModalOpen(applicationId, modal, content);
}

function proceedWithModalOpen(applicationId, modal, content) {
  content.innerHTML = "<p>Loading...</p>";
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  fetch(
    `user_pending_records.php?action=get_loan_details&application_id=${applicationId}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const loan = data.loan;
        const documents = data.documents;
        const remarks = data.remarks;

        let html = `
          <!-- Application Information Section -->
          <div class="modal-section">
            <h3>Application Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <label>Application ID:</label>
                <span>${loan.application_id}</span>
              </div>
              <div class="info-item">
                <label>Name:</label>
                <span>${loan.first_name} ${loan.last_name}</span>
              </div>
              <div class="info-item">
                <label>Loan Type:</label>
                <span>${loan.type_name || "N/A"}</span>
              </div>
              <div class="info-item">
                <label>Status:</label>
                <span class="status-badge ${loan.status.toLowerCase()}">
                  ${loan.status}
                </span>
              </div>
              <div class="info-item">
                <label>Amount Applied:</label>
                <span>₱${parseFloat(loan.amount_applied || 0).toFixed(2)}</span>
              </div>
              <div class="info-item">
                <label>Application Date:</label>
                <span>${new Date(loan.created_at).toLocaleDateString()}</span>
              </div>
            </div>
          </div>

          <!-- Financial Information Section -->
          <div class="modal-section">
            <h3>💰 Financial Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <label>Business Income:</label>
                <span>₱${parseFloat(loan.business_income || 0).toFixed(
                  2
                )}</span>
              </div>
              <div class="info-item">
                <label>Salary Income:</label>
                <span>₱${parseFloat(loan.salary_income || 0).toFixed(2)}</span>
              </div>
              <div class="info-item">
                <label>Remittance Income:</label>
                <span>₱${parseFloat(loan.remittance_income || 0).toFixed(
                  2
                )}</span>
              </div>
              <div class="info-item">
                <label>Other Income:</label>
                <span>₱${parseFloat(loan.other_income || 0).toFixed(2)}</span>
              </div>
              <div class="info-item">
                <label>Net Income:</label>
                <span>₱${parseFloat(loan.net_income || 0).toFixed(2)}</span>
              </div>
              <div class="info-item">
                <label>Total Expenditures:</label>
                <span>₱${parseFloat(loan.total_expenditures || 0).toFixed(
                  2
                )}</span>
              </div>
            </div>
          </div>

          <!-- Submitted Documents Section -->
          ${
            documents && documents.length > 0
              ? `
          <div class="documents-section">
            <h3>Submitted Documents</h3>
            <table class="documents-table">
              <thead>
                <tr>
                  <th>Document</th>
                  <th>Link</th>
                </tr>
              </thead>
              <tbody>
                ${documents
                  .map(
                    (doc) => `
                <tr>
                  <td>${doc.document_name}</td>
                  <td>
                    <a href="${doc.file_path}" target="_blank" class="doc-link">
                      <i class="fas fa-file-pdf"></i> View PDF
                    </a>
                  </td>
                </tr>
              `
                  )
                  .join("")}
              </tbody>
            </table>
          </div>
          `
              : ""
          }

          <!-- Remarks & Updates Section -->
          ${
            remarks && remarks.length > 0
              ? `
          <div class="remarks-section">
            <h3>Remarks & Updates</h3>
            <div class="remarks-timeline">
              ${remarks
                .map(
                  (remark) => `
                <div class="remark-item">
                  <div class="remark-time">${new Date(
                    remark.created_at
                  ).toLocaleString()}</div>
                  <div class="remark-text">${remark.remarks}</div>
                </div>
              `
                )
                .join("")}
            </div>
          </div>
          `
              : ""
          }
        `;
        content.innerHTML = html;
      } else {
        content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.message}</p>`;
      }
    })
    .catch((error) => {
      content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error loading details: ${error.message}</p>`;
    });
}

function closeLoanDetailsModal() {
  const modal = document.getElementById("loanDetailsModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

// Global alias for inline onclick handlers
window.closeLoanDetailsModal = closeLoanDetailsModal;

// ========== FULL LOAN CALCULATOR FUNCTIONS ==========
function openCalculatorModal() {
  if (isModalOpening) return;
  isModalOpening = true;

  const modal = document.getElementById("loanCalculatorModal");
  const interestRateDisplay = document.getElementById("interestRateDisplay");
  const interestRateInput = document.getElementById("interestRate");

  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  document.getElementById("calculatorForm").reset();
  document.getElementById("resultsDisplay").innerHTML = `
        <div class="no-result">
            <i class="fa-solid fa-calculator"></i>
            <p>Enter loan details and click Calculate to see results</p>
        </div>
    `;
  document.getElementById("amortizationContainer").style.display = "none";
  interestRateDisplay.textContent = "Loading...";
  updateLoanAmountRange();

  fetch("user_pending_records.php?action=get_current_interest_rate", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        interestRateDisplay.textContent = `${data.interest_rate}%`;
        interestRateInput.value = data.interest_rate;
      } else {
        interestRateDisplay.textContent = "Error loading rate";
      }
    })
    .catch((error) => {
      interestRateDisplay.textContent = "Error loading rate";
      console.error("Fetch error:", error);
    })
    .finally(() => (isModalOpening = false));
}

function closeCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
    isModalOpening = false;
  }, 300);
}

function updateLoanAmountRange() {
  const loanType = document.getElementById("loanType").value;
  const loanAmount = document.getElementById("loanAmount");
  const loanAmountLabel = document.getElementById("loanAmountLabel");

  if (loanType === "Individual") {
    loanAmount.min = 10000;
    loanAmount.max = 100000;
    loanAmountLabel.innerHTML =
      'Loan Amount (₱10,000 - ₱100,000) <span style="color: red;">*</span>';
  } else if (loanType === "Cooperative") {
    loanAmount.min = 100000;
    loanAmount.max = 1000000;
    loanAmountLabel.innerHTML =
      'Loan Amount (₱100,000 - ₱1,000,000) <span style="color: red;">*</span>';
  }
}

function updateRepaymentOptions() {
  const termLength = parseInt(document.getElementById("termLength").value) || 0;
  const freqSelect = document.getElementById("repaymentFrequency");

  if (!freqSelect) return;

  const options = freqSelect.options;

  for (let i = 1; i < options.length; i++) {
    options[i].disabled = false;
    options[i].style.display = "";
  }

  if (termLength === 6) {
    for (let i = 1; i < options.length; i++) {
      const val = options[i].value;
      if (val === "Quarterly" || val === "Annually") {
        options[i].disabled = true;
        options[i].style.display = "none";
      }
    }
    if (freqSelect.value === "Quarterly" || freqSelect.value === "Annually") {
      freqSelect.value = "";
    }
  }
}

function calculateLoan() {
  const loanAmount = parseFloat(document.getElementById("loanAmount").value);
  const interestRate =
    parseFloat(document.getElementById("interestRate").value) / 100;
  const termLength = parseInt(document.getElementById("termLength").value);
  const repaymentFrequency =
    document.getElementById("repaymentFrequency").value;
  const errorDiv = document.getElementById("errorMessage");

  errorDiv.classList.remove("show");

  // Validation
  if (!loanAmount || loanAmount < 10000 || loanAmount > 1000000) {
    showError("Please enter a valid loan amount between ₱10,000 - ₱1,000,000");
    return;
  }
  if (!termLength || ![6, 12, 18, 24, 36].includes(termLength)) {
    showError("Please select a valid term length");
    return;
  }
  if (!repaymentFrequency) {
    showError("Please select repayment frequency");
    return;
  }
  if (termLength === 6 && repaymentFrequency !== "Monthly") {
    showError("For 6-month term, only Monthly repayment is allowed");
    return;
  }

  let paymentsPerYear;
  switch (repaymentFrequency) {
    case "Monthly":
      paymentsPerYear = 12;
      break;
    case "Quarterly":
      paymentsPerYear = 4;
      break;
    case "Annually":
      paymentsPerYear = 1;
      break;
    default:
      paymentsPerYear = 12;
  }

  const totalPayments = (termLength * paymentsPerYear) / 12;
  const monthlyRate = interestRate / paymentsPerYear;
  const monthlyPayment =
    (loanAmount * (monthlyRate * Math.pow(1 + monthlyRate, totalPayments))) /
    (Math.pow(1 + monthlyRate, totalPayments) - 1);
  const totalInterest = monthlyPayment * totalPayments - loanAmount;

  document.getElementById("resultsDisplay").innerHTML = `
        <div class="result-card">
            <div class="result-label">${repaymentFrequency} Payment</div>
            <div class="result-value">₱${monthlyPayment.toFixed(2)}</div>
        </div>
        <div class="result-card">
            <div class="result-label">Total Interest</div>
            <div class="result-value">₱${totalInterest.toFixed(2)}</div>
        </div>
        <div class="result-card">
            <div class="result-label">Total Repayment</div>
            <div class="result-value">₱${(loanAmount + totalInterest).toFixed(
              2
            )}</div>
        </div>
        <div class="summary-grid">
            <div class="summary-item"><div class="summary-item-label">Principal</div><div class="summary-item-value">₱${loanAmount.toFixed(
              2
            )}</div></div>
            <div class="summary-item"><div class="summary-item-label">Total Payments</div><div class="summary-item-value">${totalPayments.toFixed(
              0
            )}</div></div>
            <div class="summary-item"><div class="summary-item-label">Interest Rate</div><div class="summary-item-value">${
              document.getElementById("interestRate").value
            }%</div></div>
            <div class="summary-item"><div class="summary-item-label">Loan Term</div><div class="summary-item-value">${termLength} Months</div></div>
        </div>
    `;

  generateAmortizationSchedule(
    loanAmount,
    monthlyRate,
    totalPayments,
    monthlyPayment
  );
  showToast("Loan calculation successful!", "success");
}

function generateAmortizationSchedule(
  principal,
  periodRate,
  totalPayments,
  paymentAmount
) {
  let balance = principal;
  let tbody = document.getElementById("amortizationBody");
  tbody.innerHTML = "";

  for (let i = 0; i < totalPayments; i++) {
    const interestPayment = balance * periodRate;
    const principalPayment = paymentAmount - interestPayment;
    balance -= principalPayment;
    if (balance < 0) balance = 0;

    const row = document.createElement("tr");
    row.innerHTML = `
            <td>${i + 1}</td>
            <td>₱${paymentAmount.toFixed(2)}</td>
            <td>₱${principalPayment.toFixed(2)}</td>
            <td>₱${interestPayment.toFixed(2)}</td>
            <td>₱${balance.toFixed(2)}</td>
        `;
    tbody.appendChild(row);
  }
  document.getElementById("amortizationContainer").style.display = "block";
}

function showError(message) {
  const errorDiv = document.getElementById("errorMessage");
  document.getElementById("errorText").textContent = message;
  errorDiv.classList.add("show");
  showToast(message, "error");
}

// ========== AUTO-POLLING FOR PENDING RECORDS ==========
let pendingRecordsRefreshInterval;

function refreshPendingBanner() {
  // Reload the active pending loan banner
  fetch("user_pending_records.php?action=refresh_banner", {
    cache: "no-store",
  })
    .then((response) => response.text())
    .then((html) => {
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(html, "text/html");
      const newBanner = newDoc.querySelector(".active-loan-banner");
      const currentBanner = document.querySelector(".active-loan-banner");

      if (newBanner && currentBanner) {
        currentBanner.innerHTML = newBanner.innerHTML;
      } else if (newBanner && !currentBanner) {
        // Banner appeared (new pending application)
        const mainContent = document.querySelector(".main-content");
        const h2 = mainContent.querySelector("h2");
        if (h2) {
          h2.insertAdjacentElement("afterend", newBanner);
        }
      }
    })
    .catch((error) => {
      console.error("Error refreshing pending banner:", error);
    });
}

function startPendingRecordsPolling() {
  // Start auto-refresh every 5 seconds
  pendingRecordsRefreshInterval = setInterval(() => {
    refreshPendingBanner();
    loadRequiredActions();
    loadActivityLog();
  }, 5000);
}

// ========== REQUIRED ACTIONS LOADER ==========
function loadRequiredActions() {
  // Get application ID from the page
  const applicationId = document
    .querySelector(".banner-info p")
    ?.textContent?.split(": ")[1];

  if (!applicationId) {
    return;
  }

  fetch(
    `user_pending_records.php?action=get_required_actions&application_id=${encodeURIComponent(
      applicationId
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayRequiredActions(data.actions);
      }
    })
    .catch((error) => console.error("Error loading required actions:", error));
}

function displayRequiredActions(actions) {
  const actionsList = document.getElementById("requiredActionsList");
  const actionCount = document.getElementById("actionCount");

  if (!actionsList) return;

  if (actions.length === 0) {
    actionCount.textContent = "0";
    actionsList.innerHTML = `
      <div class="no-actions">
        <i class="fas fa-check-circle"></i>
        <p>All actions completed! Your application is progressing.</p>
      </div>
    `;
    return;
  }

  actionCount.textContent = actions.length;

  let html = "";
  actions.forEach((action) => {
    const priorityClass = `${action.priority}-priority`;
    html += `
      <div class="action-item ${priorityClass}">
        <div class="action-icon">
          <i class="fas ${action.icon}"></i>
        </div>
        <div class="action-content">
          <p class="action-title">${action.title}</p>
          <p class="action-description">${action.description}</p>
        </div>
      </div>
    `;
  });

  actionsList.innerHTML = html;
}

// ========== ACTIVITY LOG LOADER ==========
function loadActivityLog() {
  // Get application ID from the page
  const applicationId = document
    .querySelector(".banner-info p")
    ?.textContent?.split(": ")[1];

  if (!applicationId) {
    return;
  }

  fetch(
    `user_pending_records.php?action=get_activity_log&application_id=${encodeURIComponent(
      applicationId
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayActivityLog(data.activities);
      }
    })
    .catch((error) => console.error("Error loading activity log:", error));
}

function displayActivityLog(activities) {
  const timeline = document.getElementById("activityTimeline");

  if (!timeline) return;

  if (activities.length === 0) {
    timeline.innerHTML = `
      <div class="no-activities">
        <i class="fas fa-inbox"></i>
        <p>No activity recorded yet</p>
      </div>
    `;
    return;
  }

  let html = "";
  activities.forEach((activity) => {
    const timestamp = new Date(activity.timestamp);
    const timeString = formatTime(timestamp);
    const typeClass = activity.type || "default";

    html += `
      <div class="activity-item ${typeClass}">
        <div class="activity-dot">
          <i class="fas ${activity.icon}"></i>
        </div>
        <div class="activity-content">
          <p class="activity-title">${activity.title}</p>
          <p class="activity-description">${activity.description}</p>
          <p class="activity-time">${timeString}</p>
        </div>
      </div>
    `;
  });

  timeline.innerHTML = html;
}

function formatTime(date) {
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return "Just now";
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? "s" : ""} ago`;
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? "s" : ""} ago`;
  if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`;

  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function stopPendingRecordsPolling() {
  if (pendingRecordsRefreshInterval) {
    clearInterval(pendingRecordsRefreshInterval);
    pendingRecordsRefreshInterval = null;
  }
}

// ========== DOCUMENT STATUS FUNCTIONS (FROM DASHBOARD) ==========
let activeLoanApplicationId = null;

// Initialize document status loading
document.addEventListener("DOMContentLoaded", function () {
  const applicationIdElement = document.querySelector(".banner-info p");
  if (applicationIdElement) {
    const text = applicationIdElement.textContent;
    const match = text.match(/(?:Application ID|Loan ID):\s*(.+)/);
    if (match) {
      activeLoanApplicationId = match[1].trim();
      loadDocumentStatus();

      // Add refresh button listener
      const refreshBtn = document.getElementById("refreshDocStatusBtn");
      if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
          this.style.animation = "none";
          setTimeout(() => {
            this.style.animation = "";
            loadDocumentStatus();
          }, 10);
        });
      }
    }
  }

  // Add modal close on outside click
  const documentModal = document.getElementById("documentStatusModal");
  if (documentModal) {
    documentModal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeDocumentStatusModal();
      }
    });
  }
});

function loadDocumentStatus() {
  if (!activeLoanApplicationId) {
    return;
  }

  fetch(
    `user_pending_records.php?action=get_document_status&application_id=${encodeURIComponent(
      activeLoanApplicationId
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayDocumentStatus(data.documents);
      } else {
        console.error("Error loading document status:", data);
      }
    })
    .catch((error) => console.error("Error loading document status:", error));
}

function displayDocumentStatus(documents) {
  const grid = document.getElementById("documentStatusGrid");
  if (!grid) return;

  if (documents.length === 0) {
    grid.innerHTML = `
      <div class="no-documents">
        <i class="fas fa-inbox"></i>
        <p>No documents found</p>
      </div>
    `;
    return;
  }

  let html = `
    <table class="document-status-table">
      <thead>
        <tr>
          <th>Document Name</th>
          <th>Status</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
  `;

  documents.forEach((doc) => {
    const statusClass = doc.status ? doc.status.toLowerCase() : "pending";
    const isRejected = doc.status === "Rejected";
    const lastUpdated = doc.status_updated_at
      ? new Date(doc.status_updated_at).toLocaleDateString("en-US", {
          year: "numeric",
          month: "short",
          day: "numeric",
        })
      : "N/A";

    html += `
      <tr>
        <td data-label="Document Name">${doc.document_name}</td>
        <td data-label="Status">
          <span class="status-badge ${statusClass}">${doc.status}</span>
          ${
            isRejected && doc.rejection_notes
              ? `<div class="rejection-reason" style="margin-top: 5px; color: #d32f2f; font-size: 0.85rem;"><strong>Reason:</strong> ${doc.rejection_notes}</div>`
              : ""
          }
        </td>
        <td data-label="Last Updated">${lastUpdated}</td>
        <td data-label="Actions">
          <div class="doc-actions">
            ${
              doc.file_path
                ? `<a href="${doc.file_path}" target="_blank" class="doc-action-btn view" title="View Document"><i class="fas fa-eye"></i> View</a>`
                : `<span class="doc-action-btn view" style="opacity: 0.5; cursor: not-allowed;" title="No file attached"><i class="fas fa-eye-slash"></i> N/A</span>`
            }
          </div>
        </td>
      </tr>
    `;
  });

  html += `
      </tbody>
    </table>
  `;

  grid.innerHTML = html;
}

// ========== INITIALIZE ==========
document.addEventListener("DOMContentLoaded", function () {
  const termLengthSelect = document.getElementById("termLength");
  if (termLengthSelect) {
    termLengthSelect.addEventListener("change", updateRepaymentOptions);
  }

  const loanTypeSelect = document.getElementById("loanType");
  if (loanTypeSelect) {
    loanTypeSelect.addEventListener("change", updateLoanAmountRange);
  }

  // Load initial required actions and activity log
  loadRequiredActions();
  loadActivityLog();

  // Add refresh button listener for activity log
  const refreshActivityBtn = document.getElementById("refreshActivityBtn");
  if (refreshActivityBtn) {
    refreshActivityBtn.addEventListener("click", function () {
      this.style.animation = "none";
      setTimeout(() => {
        this.style.animation = "";
        loadActivityLog();
      }, 10);
    });
  }

  // Start auto-polling for pending records
  startPendingRecordsPolling();

  // Stop polling when page is hidden (tab not active)
  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      stopPendingRecordsPolling();
    } else {
      startPendingRecordsPolling();
    }
  });

  // Stop polling on page unload
  window.addEventListener("beforeunload", function () {
    stopPendingRecordsPolling();
  });
});
