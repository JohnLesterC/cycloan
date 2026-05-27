// ========== SIDEBAR TOGGLE (ENHANCED) ==========
document.addEventListener("DOMContentLoaded", function () {
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

  // Initialize table features
  initializeTableFeatures();
});

// ========== TABLE FEATURES ==========
function initializeTableFeatures() {
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const loanTypeFilter = document.getElementById("loanTypeFilter");
  const sortableHeaders = document.querySelectorAll(".sortable");

  // Search functionality with debouncing
  let searchTimeout;
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        filterTable();
      }, 300);
    });
  }

  // Filter change handlers
  if (statusFilter) {
    statusFilter.addEventListener("change", filterTable);
  }

  if (loanTypeFilter) {
    loanTypeFilter.addEventListener("change", filterTable);
  }

  // Sortable headers
  sortableHeaders.forEach((header) => {
    header.addEventListener("click", function () {
      const sortKey = this.dataset.sort;
      sortTable(sortKey, this);
    });
  });
}

function filterTable() {
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const loanTypeFilter = document.getElementById("loanTypeFilter");
  const table = document.getElementById("pendingTable");
  const noResults = document.getElementById("noResults");

  if (!table) return;

  const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
  const statusValue = statusFilter ? statusFilter.value.toLowerCase() : "";
  const loanTypeValue = loanTypeFilter
    ? loanTypeFilter.value.toLowerCase()
    : "";

  const rows = table.querySelectorAll("tbody tr");
  let visibleCount = 0;

  rows.forEach((row) => {
    const name = row.dataset.name ? row.dataset.name.toLowerCase() : "";
    const loanType = row.dataset.loanType
      ? row.dataset.loanType.toLowerCase()
      : "";
    const status = row.dataset.status ? row.dataset.status.toLowerCase() : "";

    const matchesSearch = name.includes(searchTerm);
    const matchesStatus = !statusValue || status === statusValue;
    const matchesLoanType = !loanTypeValue || loanType === loanTypeValue;

    if (matchesSearch && matchesStatus && matchesLoanType) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  // Show/hide no results message
  if (noResults) {
    if (visibleCount === 0 && rows.length > 0) {
      noResults.style.display = "block";
      table.parentElement.style.display = "none";
    } else {
      noResults.style.display = "none";
      table.parentElement.style.display = "block";
    }
  }

  // Update stats
  updateStats(Array.from(rows).filter((r) => r.style.display !== "none"));
}

function sortTable(sortKey, headerElement) {
  const table = document.getElementById("pendingTable");
  if (!table) return;

  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.querySelectorAll("tr"));

  // Determine sort direction
  const isAscending = !headerElement.classList.contains("asc");

  // Remove sort classes from all headers
  document.querySelectorAll(".sortable").forEach((h) => {
    h.classList.remove("asc", "desc");
  });

  // Add appropriate class to clicked header
  headerElement.classList.add(isAscending ? "asc" : "desc");

  // Sort rows
  rows.sort((a, b) => {
    let aValue, bValue;

    switch (sortKey) {
      case "name":
        aValue = a.dataset.name || "";
        bValue = b.dataset.name || "";
        break;
      case "loan-type":
        aValue = a.dataset.loanType || "";
        bValue = b.dataset.loanType || "";
        break;
      case "amount":
        aValue = parseFloat(a.dataset.amount) || 0;
        bValue = parseFloat(b.dataset.amount) || 0;
        break;
      case "status":
        aValue = a.dataset.status || "";
        bValue = b.dataset.status || "";
        break;
      case "pre-approval":
        aValue = a.dataset.preApproval || "";
        bValue = b.dataset.preApproval || "";
        break;
      case "credit":
        aValue = a.dataset.credit || "";
        bValue = b.dataset.credit || "";
        break;
      case "date":
        aValue = parseInt(a.dataset.date) || 0;
        bValue = parseInt(b.dataset.date) || 0;
        break;
      default:
        return 0;
    }

    if (typeof aValue === "string") {
      return isAscending
        ? aValue.localeCompare(bValue)
        : bValue.localeCompare(aValue);
    } else {
      return isAscending ? aValue - bValue : bValue - aValue;
    }
  });

  // Re-append sorted rows
  rows.forEach((row) => tbody.appendChild(row));
}

function updateStats(visibleRows) {
  const totalPending = document.getElementById("total-pending");
  const preApprovedCount = document.getElementById("pre-approved-count");
  const investigationCount = document.getElementById("investigation-count");
  const totalAmount = document.getElementById("total-amount");

  if (!visibleRows.length) return;

  const total = visibleRows.length;
  const preApproved = visibleRows.filter(
    (row) => row.dataset.preApproval === "approved"
  ).length;
  const investigation = visibleRows.filter((row) => {
    const credit = row.dataset.credit;
    return credit === "pending" || credit === "in_progress";
  }).length;
  const amount = visibleRows.reduce(
    (sum, row) => sum + parseFloat(row.dataset.amount || 0),
    0
  );

  if (totalPending) totalPending.textContent = total;
  if (preApprovedCount) preApprovedCount.textContent = preApproved;
  if (investigationCount) investigationCount.textContent = investigation;
  if (totalAmount) totalAmount.textContent = "₱" + amount.toFixed(2);
}

function exportToCSV() {
  const table = document.getElementById("pendingTable");
  if (!table) return;

  const rows = Array.from(table.querySelectorAll("tbody tr")).filter(
    (row) => row.style.display !== "none"
  );

  if (rows.length === 0) {
    alert("No data to export!");
    return;
  }

  let csv =
    "Applicant Name,Loan Type,Amount Applied,Loan Status,Pre-Approval Status,Credit Status,Submission Date\n";

  rows.forEach((row) => {
    const cells = [
      row.dataset.name || "",
      row.dataset.loanType || "",
      row.dataset.amount || "",
      row.dataset.status || "",
      row.dataset.preApproval || "",
      row.dataset.credit || "",
      new Date(parseInt(row.dataset.date) * 1000).toLocaleDateString() || "",
    ];
    csv += cells.map((cell) => `"${cell}"`).join(",") + "\n";
  });

  const blob = new Blob([csv], { type: "text/csv" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `pending_applications_${
    new Date().toISOString().split("T")[0]
  }.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
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
  const modal = document.getElementById("loanDetailsModal");
  const content = document.getElementById("loanDetailsContent");

  if (!modal || !content) {
    console.error("Modal elements not found");
    return;
  }

  content.innerHTML = "<p>Loading loan details...</p>";
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  fetch(
    `pending_records.php?action=get_loan_details&application_id=${applicationId}`,
    {
      cache: "no-store",
    }
  )
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        const loan = data.loan;
        const documents = data.documents;
        const remarks = data.remarks;

        // Build the complete HTML with sections
        const html = `
          <div class="modal-section">
            <h3><i class="fas fa-user"></i> Applicant Information</h3>
            
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value">${loan.first_name} ${loan.last_name}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email Address:</span>
                <span class="info-value">${loan.email}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Birthday:</span>
                <span class="info-value">${loan.birthday ? new Date(loan.birthday).toLocaleDateString() : "Not provided"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Contact Number:</span>
                <span class="info-value">${loan.contact || "Not provided"}</span>
              </div>
            </div>
          </div>

          <!-- Loan Information Section -->
          <div class="modal-section">
            <h3><i class="fas fa-file-contract"></i> Loan Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Loan Type:</span>
                <span class="info-value">${loan.type_name}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Amount Applied:</span>
                <span class="info-value">₱${parseFloat(
                  loan.amount_applied
                ).toFixed(2)}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Loan Status:</span>
                <span class="info-value"><span class="status-badge badge-${loan.status
                  .toLowerCase()
                  .replace(/\s+/g, "-")}">${loan.status}</span></span>
              </div>
              <div class="info-item">
                <span class="info-label">Pre-Approval Status:</span>
                <span class="info-value"><span class="status-badge badge-${loan.pre_approval_status
                  .toLowerCase()
                  .replace(/\s+/g, "-")}">${
          loan.pre_approval_status
        }</span></span>
              </div>
              <div class="info-item">
                <span class="info-label">Credit Investigation:</span>
                <span class="info-value"><span class="status-badge badge-${loan.credit_investigation_status
                  .toLowerCase()
                  .replace(/\s+/g, "-")}">${
          loan.credit_investigation_status
        }</span></span>
              </div>
              <div class="info-item">
                <span class="info-label">Term Length:</span>
                <span class="info-value">${
                  loan.term_length || "N/A"
                } months</span>
              </div>
              <div class="info-item">
                <span class="info-label">Repayment Frequency:</span>
                <span class="info-value">${
                  loan.repayment_frequency || "N/A"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label">Purpose:</span>
                <span class="info-value">${loan.purpose || "N/A"}${
          loan.purpose === "Others" && loan.others_text
            ? " - " + loan.others_text
            : ""
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label">Project Type:</span>
                <span class="info-value">${loan.project_type || "N/A"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Project Description:</span>
                <span class="info-value">${
                  loan.project_description || "N/A"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label">Submission Date:</span>
                <span class="info-value">${new Date(
                  loan.created_at
                ).toLocaleDateString()}</span>
              </div>
            </div>
          </div>

        <!-- Financial Information Section -->
        <div class="modal-section">
            <h3><i class="fa-solid fa-coins"></i> Financial Information</h3>
            
            <div class="financial-grid">
            
              <!-- Income Card -->
                <div class="financial-card">
                    <h4><i class="fas fa-wallet"></i> Income Sources</h4>
                
                    <div class="financial-items">
                        <div class="financial-row">
                          <span>Business Income:</span>
                          <span>₱${ 
                            loan.business_income
                              ? parseFloat(loan.business_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Salary Income:</span>
                          <span>₱${
                            loan.salary_income
                              ? parseFloat(loan.salary_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Remittance Income:</span>
                          <span>₱${
                            loan.remittance_income
                              ? parseFloat(loan.remittance_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Other Income:</span>
                          <span>₱${
                            loan.other_income
                              ? parseFloat(loan.other_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Business 2 Income:</span>
                          <span>₱${
                            loan.business2_income
                              ? parseFloat(loan.business2_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Salary 2 Income:</span>
                          <span>₱${
                            loan.salary2_income
                              ? parseFloat(loan.salary2_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row total-row">
                          <span>Net Income:</span>
                          <span>₱${
                            loan.net_income
                              ? parseFloat(loan.net_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                    </div>
                </div>

                <!-- Expenses Card -->
                <div class="financial-card">
                    <h4><i class="fas fa-receipt"></i> Monthly Expenses</h4>
                    
                    <div class="financial-items">
                        <div class="financial-row">
                          <span>Food Allowance:</span>
                          <span>₱${
                            loan.food_allowance
                              ? parseFloat(loan.food_allowance).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Electricity Bill:</span>
                          <span>₱${
                            loan.electricity_bill
                              ? parseFloat(loan.electricity_bill).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Water Bill:</span>
                          <span>₱${
                            loan.water_bill
                              ? parseFloat(loan.water_bill).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Internet Bill:</span>
                          <span>₱${
                            loan.internet_bill
                              ? parseFloat(loan.internet_bill).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Gas Bill:</span>
                          <span>₱${
                            loan.gas_bill
                              ? parseFloat(loan.gas_bill).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Educational Allowance:</span>
                          <span>₱${
                            loan.educational_allowance
                              ? parseFloat(loan.educational_allowance).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Car Amortization:</span>
                          <span>₱${
                            loan.car_amortization
                              ? parseFloat(loan.car_amortization).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Insurance:</span>
                          <span>₱${
                            loan.insurance
                              ? parseFloat(loan.insurance).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Other Expenses:</span>
                          <span>₱${
                            loan.other_expense
                              ? parseFloat(loan.other_expense).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row total-row">
                          <span>Total Expenditures:</span>
                          <span>₱${
                            loan.total_expenditures
                              ? parseFloat(loan.total_expenditures).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                    </div>
                </div>
        
                <!-- Summary Card -->
                <div class="financial-summary">
                    <h4><i class="fas fa-calculator"></i> Financial Summary</h4>
                    
                    <div class="financial-items">
                        <div class="financial-row">
                          <span>Net Income:</span>
                          <span>₱${
                            loan.net_income
                              ? parseFloat(loan.net_income).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row">
                          <span>Total Expenditures:</span>
                          <span>₱${
                            loan.total_expenditures
                              ? parseFloat(loan.total_expenditures).toFixed(2)
                              : "0.00"
                          }</span>
                        </div>
                        
                        <div class="financial-row">
                          <span>Expected Monthly Amortization:</span>
                          <span>₱${
                            loan.expected_monthly_amortization
                              ? parseFloat(loan.expected_monthly_amortization).toFixed(
                                  2
                                )
                              : "0.00"
                          }</span>
                        </div>
                        <div class="financial-row total-row">
                          <span>Remaining Income:</span>
                          <span class="${
                            parseFloat(loan.remaining_income || 0) < 0
                              ? "negative"
                              : "positive"
                          }">₱${loan.remaining_income ? parseFloat(loan.remaining_income).toFixed(2) : "0.00"}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            
        <!-- Documents Section -->
          <div class="modal-section">
            <h3><i class="fas fa-folder-open"></i> Uploaded Documents</h3>
            ${
              documents.length > 0
                ? `
              <div class="documents-grid">
                ${documents
                  .map(
                    (doc) => `
                  <div class="document-item">
                    <i class="fas fa-file-alt"></i>
                    <a href="${doc.file_path}" target="_blank" class="document-link">
                      <span>${doc.document_name}</span>
                    </a>
                  </div>
                `
                  )
                  .join("")}
              </div>
            `
                : '<p class="no-data">No documents uploaded.</p>'
            }
          </div>

          <!-- Remarks Section -->
          <div class="modal-section">
            <h3><i class="fas fa-comments"></i> Previous Remarks</h3>
            ${
              remarks.length > 0
                ? `
              <div class="remarks-timeline">
                ${remarks
                  .map(
                    (remark) => `
                  <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                      <div class="timeline-header">
                        <span class="timeline-badge">${
                          remark.status_type || "General"
                        }</span>
                        <span class="timeline-date">${new Date(
                          remark.created_at
                        ).toLocaleString()}</span>
                      </div>
                      <p class="timeline-text">${remark.remarks}</p>
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            `
                : '<p class="no-data">No remarks available.</p>'
            }
          </div>
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
  setTimeout(() => {
    modal.style.display = "none";
  }, 300);
}

window.addEventListener("click", (event) => {
  const modal = document.getElementById("loanDetailsModal");
  if (event.target === modal) {
    closeLoanDetailsModal();
  }
});
