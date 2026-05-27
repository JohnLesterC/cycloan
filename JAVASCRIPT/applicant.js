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

  // Initialize search and filter functionality
  initializeTableFeatures();
});

// ========== TABLE FEATURES (SEARCH, FILTER, SORT) ==========
function initializeTableFeatures() {
  const searchInput = document.getElementById("searchInput");
  const statusFilter = document.getElementById("statusFilter");
  const loanTypeFilter = document.getElementById("loanTypeFilter");
  const table = document.getElementById("applicantsTable");

  if (!table) return;

  // Search functionality
  if (searchInput) {
    searchInput.addEventListener("input", function () {
      filterTable();
    });
  }

  // Filter functionality
  if (statusFilter) {
    statusFilter.addEventListener("change", function () {
      filterTable();
    });
  }

  if (loanTypeFilter) {
    loanTypeFilter.addEventListener("change", function () {
      filterTable();
    });
  }

  // Sorting functionality
  const sortableHeaders = table.querySelectorAll("th.sortable");
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
  const table = document.getElementById("applicantsTable");
  const noResults = document.getElementById("noResults");
  const showingCount = document.getElementById("showingCount");

  if (!table) return;

  const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
  const statusValue = statusFilter ? statusFilter.value.toLowerCase() : "";
  const loanTypeValue = loanTypeFilter
    ? loanTypeFilter.value.toLowerCase()
    : "";

  const rows = table.querySelectorAll("tbody tr");
  let visibleCount = 0;
  const totalCount = rows.length;

  rows.forEach((row) => {
    const name = row.dataset.name || "";
    const loanType = row.dataset.loanType || "";
    const status = row.dataset.status || "";
    const preApproval = row.dataset.preApproval || "";

    const matchesSearch =
      name.includes(searchTerm) || loanType.includes(searchTerm);
    const matchesStatus =
      !statusValue || status === statusValue || preApproval === statusValue;
    const matchesLoanType = !loanTypeValue || loanType === loanTypeValue;

    if (matchesSearch && matchesStatus && matchesLoanType) {
      row.style.display = "";
      visibleCount++;
    } else {
      row.style.display = "none";
    }
  });

  // Update showing count
  if (showingCount) {
    showingCount.innerHTML = `Showing <strong>1-${visibleCount}</strong> of <strong>${totalCount}</strong> applicants`;
  }

  // Show/hide no results message
  if (noResults) {
    if (visibleCount === 0) {
      noResults.style.display = "block";
      table.closest(".scrollable-table").style.display = "none";
    } else {
      noResults.style.display = "none";
      table.closest(".scrollable-table").style.display = "block";
    }
  }

  // Update stats
  updateStats(rows);
}

function sortTable(sortKey, headerElement) {
  const table = document.getElementById("applicantsTable");
  if (!table) return;

  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.querySelectorAll("tr"));

  // Remove sort classes from all headers
  table.querySelectorAll("th.sortable").forEach((th) => {
    th.classList.remove("sort-asc", "sort-desc");
  });

  // Determine sort direction
  const currentSort = headerElement.dataset.currentSort || "none";
  const newSort = currentSort === "asc" ? "desc" : "asc";
  headerElement.dataset.currentSort = newSort;
  headerElement.classList.add(`sort-${newSort}`);

  // Sort rows
  rows.sort((a, b) => {
    let aVal, bVal;

    switch (sortKey) {
      case "name":
        aVal = a.dataset.name;
        bVal = b.dataset.name;
        break;
      case "loan_type":
        aVal = a.dataset.loanType;
        bVal = b.dataset.loanType;
        break;
      case "amount":
        aVal = parseFloat(a.dataset.amount);
        bVal = parseFloat(b.dataset.amount);
        break;
      case "status":
        aVal = a.dataset.status;
        bVal = b.dataset.status;
        break;
      case "pre_approval":
        aVal = a.dataset.preApproval;
        bVal = b.dataset.preApproval;
        break;
      case "credit":
        aVal = a.dataset.credit;
        bVal = b.dataset.credit;
        break;
      case "date":
        aVal = parseInt(a.dataset.date);
        bVal = parseInt(b.dataset.date);
        break;
      default:
        return 0;
    }

    if (typeof aVal === "string") {
      aVal = aVal.toLowerCase();
      bVal = bVal.toLowerCase();
    }

    if (newSort === "asc") {
      return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
    } else {
      return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
    }
  });

  // Reappend sorted rows
  rows.forEach((row) => tbody.appendChild(row));
}

function updateStats(rows) {
  const visibleRows = Array.from(rows).filter(
    (row) => row.style.display !== "none"
  );

  const totalApplicants = document.getElementById("totalApplicants");
  const pendingCount = document.getElementById("pendingCount");
  const approvedCount = document.getElementById("approvedCount");

  if (totalApplicants) {
    totalApplicants.textContent = visibleRows.length;
  }

  if (pendingCount) {
    const pending = visibleRows.filter(
      (row) => row.dataset.preApproval === "pending"
    ).length;
    pendingCount.textContent = pending;
  }

  if (approvedCount) {
    const approved = visibleRows.filter(
      (row) => row.dataset.preApproval === "approved"
    ).length;
    approvedCount.textContent = approved;
  }
}

// ========== EXPORT TO CSV ==========
function exportToCSV() {
  const table = document.getElementById("applicantsTable");
  if (!table) return;

  const rows = table.querySelectorAll("tr");
  const csv = [];

  rows.forEach((row) => {
    const cols = row.querySelectorAll("td, th");
    const csvRow = [];

    cols.forEach((col, index) => {
      // Skip the last column (Actions)
      if (index < cols.length - 1) {
        let text = col.textContent.trim();
        // Handle special characters and newlines
        text = text.replace(/"/g, '""');
        csvRow.push(`"${text}"`);
      }
    });

    if (csvRow.length > 0) {
      csv.push(csvRow.join(","));
    }
  });

  // Create and download file
  const csvContent = csv.join("\n");
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
  const link = document.createElement("a");
  const url = URL.createObjectURL(blob);

  const date = new Date().toISOString().split("T")[0];
  link.setAttribute("href", url);
  link.setAttribute("download", `loan_applicants_${date}.csv`);
  link.style.visibility = "hidden";

  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// ========== PROFILE DROPDOWN ==========
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

// ========== MODAL FUNCTIONALITY ==========

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
    `applicant.php?action=get_loan_details&application_id=${applicationId}`,
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

        // User Information Section
        const userInfoHTML = `
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
                <span class="info-value">${
                  loan.birthday ? new Date(loan.birthday).toLocaleDateString() : "Not provided"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Contact Number:</span>
                <span class="info-value">${loan.contact || "Not provided"}</span>
              </div>
            </div>
            
          </div>
        `;

        // Loan Information Section
        const loanInfoHTML = `
          <div class="modal-section">
            <h3><i class="fas fa-file-contract"></i> Loan Information</h3>
            
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Loan Type:</span>
                <span class="info-value">${loan.type_name}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Amount Applied:</span>
                <span class="info-value amount-highlight">₱${parseFloat(loan.amount_applied).toLocaleString("en-US", {minimumFractionDigits: 2, maximumFractionDigits: 2, })}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Loan Status:</span>
                <span class="status-badge badge-${loan.status.toLowerCase()}"> ${loan.status}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Pre-Approval Status:</span>
                <span class="status-badge badge-${loan.pre_approval_status.toLowerCase().replace(/\s+/g, "_")}">${loan.pre_approval_status}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Credit Investigation:</span>
                <span class="status-badge badge-${loan.credit_investigation_status.toLowerCase().replace(/\s+/g, "_")}"> ${loan.credit_investigation_status}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Term Length:</span>
                <span class="info-value"> ${loan.term_length || "N/A"} months</span>
              </div>
              <div class="info-item">
                <span class="info-label">Repayment Frequency:</span>
                <span class="info-value"> ${loan.repayment_frequency || "N/A"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Purpose:</span>
                <span class="info-value"> ${loan.purpose || "N/A"} ${loan.purpose === "Others" && loan.others_text ? " - " + loan.others_text : ""}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Project Type:</span>
                <span class="info-value"> ${loan.project_type || "N/A"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Project Description:</span>
                <span class="info-value"> ${loan.project_description || "N/A"}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Submission Date:</span>
                <span class="info-value"> ${new Date(loan.created_at).toLocaleDateString()}</span>
              </div>
            </div>
            
          </div>
        `;

        // Financial Information Section
        const financialInfoHTML = loan.net_income
          ? `
          <div class="modal-section">
            <h3><i class="fa-solid fa-coins"></i> Financial Information</h3>
            <div class="financial-grid">
              <div class="financial-card">
                <h4><i class="fas fa-wallet"></i> Income Sources</h4>
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
                    <strong>₱${parseFloat(loan.water_bill || 0).toLocaleString(
                      "en-US",
                      { minimumFractionDigits: 2, maximumFractionDigits: 2 }
                    )}</strong>
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
                    }">₱${parseFloat(loan.remaining_income || 0).toLocaleString(
              "en-US",
              { minimumFractionDigits: 2, maximumFractionDigits: 2 }
            )}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        `
          : `
          <div class="modal-section">
            <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
            <p class="no-data"><i class="fas fa-info-circle"></i> No financial information available.</p>
          </div>
        `;

        // Documents Section
        const documentsHTML = `
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
                      ${doc.document_name}
                    </a>
                  </div>
                `
                  )
                  .join("")}
              </div>
            `
                : `
              <p class="no-data"><i class="fas fa-info-circle"></i> No documents uploaded.</p>
            `
            }
          </div>
        `;

        // Remarks Section
        const remarksHTML = `
          <div class="modal-section">
            <h3><i class="fas fa-comments"></i> Remarks History</h3>
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
                        <span class="timeline-date"><i class="fas fa-clock"></i> ${new Date(
                          remark.created_at
                        ).toLocaleString()}</span>
                        ${
                          remark.admin_name
                            ? `<span class="timeline-admin"><i class="fas fa-user-shield"></i> ${remark.admin_name}</span>`
                            : ""
                        }
                      </div>
                      <div class="timeline-text">${remark.remarks}</div>
                    </div>
                  </div>
                `
                  )
                  .join("")}
              </div>
            `
                : `
              <p class="no-data"><i class="fas fa-info-circle"></i> No remarks available.</p>
            `
            }
          </div>
        `;

        // Combine all sections
        content.innerHTML =
          userInfoHTML +
          loanInfoHTML +
          financialInfoHTML +
          documentsHTML +
          remarksHTML;
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
