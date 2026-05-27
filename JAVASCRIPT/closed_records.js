let currentApplicationId = null;

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
  currentApplicationId = applicationId;
  const modal = document.getElementById("loanDetailsModal");
  const content = document.getElementById("loanDetailsContent");
  const remarksTextarea = document.getElementById("remarks");

  if (!modal || !content) {
    console.error("Modal elements not found");
    return;
  }

  content.innerHTML = "<p>Loading loan details...</p>";
  if (remarksTextarea) remarksTextarea.value = "";
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  fetch(
    `closed_records.php?action=get_loan_details&application_id=${applicationId}`,
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
          <!-- User Information Section -->
          <div class="modal-section">
            <h3><i class="fas fa-user"></i> Applicant Information</h3>
            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">Full Name:</span>
                <span class="info-value">${loan.first_name} ${
          loan.last_name
        }</span>
              </div>
              <div class="info-item">
                <span class="info-label">Email Address:</span>
                <span class="info-value">${loan.email}</span>
              </div>
              <div class="info-item">
                <span class="info-label">Birthday:</span>
                <span class="info-value">${
                  loan.birthday
                    ? new Date(loan.birthday).toLocaleDateString()
                    : "Not provided"
                }</span>
              </div>
              <div class="info-item">
                <span class="info-label">Contact Number:</span>
                <span class="info-value">${
                  loan.contact || "Not provided"
                }</span>
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
            <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
            <div class="financial-grid">
              <!-- Income Card -->
              <div class="financial-card">
                <h4><i class="fas fa-wallet"></i> Income Sources</h4>
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

              <!-- Expenses Card -->
              <div class="financial-card">
                <h4><i class="fas fa-receipt"></i> Monthly Expenses</h4>
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

              <!-- Summary Card -->
              <div class="financial-card">
                <h4><i class="fas fa-calculator"></i> Financial Summary</h4>
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
                  }">₱${
          loan.remaining_income
            ? parseFloat(loan.remaining_income).toFixed(2)
            : "0.00"
        }</span>
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
                    <a href="${doc.file_path}" target="_blank">
                      <i class="fas fa-file-pdf"></i>
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
  currentApplicationId = null;
  const modal = document.getElementById("loanDetailsModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
  }, 300);
}

function submitRemarks() {
  if (!currentApplicationId) {
    alert("No application selected.");
    return;
  }

  const remarks = document.getElementById("remarks").value.trim();
  if (!remarks) {
    alert("Please enter remarks.");
    return;
  }

  const formData = new FormData();
  formData.append("action", "submit_remarks");
  formData.append("application_id", currentApplicationId);
  formData.append("remarks", remarks);

  fetch("closed_records.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert(data.message);
        document.getElementById("remarks").value = "";
        openLoanDetailsModal(currentApplicationId); // Refresh modal to show new remark
      } else {
        alert(data.message);
      }
    })
    .catch((error) => {
      alert("Error submitting remarks: " + error.message);
    });
}

window.addEventListener("click", (event) => {
  const modal = document.getElementById("loanDetailsModal");
  if (event.target === modal) {
    closeLoanDetailsModal();
  }
});
