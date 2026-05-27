let isModalOpening = false;

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
});

function toggleDropdown(event) {
  event.stopPropagation();
  document.getElementById("dropdown").classList.toggle("show");
}

window.onclick = function (event) {
  if (!event.target.closest(".profile-container")) {
    document.querySelectorAll(".dropdown-menu.show").forEach((dropdown) => {
      dropdown.classList.remove("show");
    });
  }
  if (
    event.target.classList.contains("modal") &&
    !event.target.closest(".modal-content")
  ) {
    if (event.target.id === "loanDetailsModal") closeLoanDetailsModal();
    if (event.target.id === "loanCalculatorModal") closeCalculatorModal();
  }
};

function openLoanDetailsModal(applicationId) {
  const modal = document.getElementById("loanDetailsModal");
  const content = document.getElementById("loanDetailsContent");

  content.innerHTML =
    '<div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  fetch(
    `user_closed_records.php?action=get_loan_details&application_id=${applicationId}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const loan = data.loan;
        const documents = data.documents;
        const remarks = data.remarks;

        content.innerHTML = `
          <div class="details-grid">
            <div><strong>Name:</strong> ${loan.first_name} ${
          loan.last_name
        }</div>
            <div><strong>Email:</strong> ${loan.email || "N/A"}</div>
            <div><strong>Birthday:</strong> ${
              loan.birthday
                ? new Date(loan.birthday).toLocaleDateString()
                : "N/A"
            }</div>
            <div><strong>Contact:</strong> ${loan.contact || "N/A"}</div>
            <div><strong>Loan Type:</strong> ${loan.type_name}</div>
            <div><strong>Amount:</strong> ₱${parseFloat(
              loan.amount_applied
            ).toLocaleString()}</div>
            <div><strong>Status:</strong> <span class="status-badge completed">${
              loan.status
            }</span></div>
            <div><strong>Term:</strong> ${
              loan.term_length || "N/A"
            } months</div>
            <div><strong>Frequency:</strong> ${
              loan.repayment_frequency || "N/A"
            }</div>
            <div><strong>Date:</strong> ${new Date(
              loan.created_at
            ).toLocaleDateString()}</div>
          </div>

          <div class="financial-info">
            <h3><i class="fas fa-chart-line"></i> Financial Information</h3>
            ${
              hasFinancialData(loan)
                ? financialHTML(loan)
                : "<p>No financial data available.</p>"
            }
          </div>

          <div class="loan-info">
            <h3><i class="fas fa-info-circle"></i> Loan Details</h3>
            <p><strong>Purpose:</strong> ${loan.purpose || "N/A"}${
          loan.purpose === "Others" && loan.others_text
            ? ` - ${loan.others_text}`
            : ""
        }</p>
            <p><strong>Project Type:</strong> ${loan.project_type || "N/A"}</p>
            <p><strong>Description:</strong> ${
              loan.project_description || "N/A"
            }</p>
          </div>

          ${
            documents.length > 0
              ? `
            <div class="uploaded-files">
              <h3><i class="fas fa-file"></i> Documents</h3>
              <ul>${documents
                .map(
                  (doc) =>
                    `<li><a href="${doc.file_path}" target="_blank"><i class="fas fa-file-pdf"></i> ${doc.document_name}</a></li>`
                )
                .join("")}</ul>
            </div>
          `
              : ""
          }

          ${
            remarks.length > 0
              ? `
            <div class="remarks-list">
              <h3><i class="fas fa-comments"></i> Remarks</h3>
              <ul>${remarks
                .map(
                  (remark) =>
                    `<li><span>${new Date(
                      remark.created_at
                    ).toLocaleString()}</span> ${remark.remarks}</li>`
                )
                .join("")}</ul>
            </div>
          `
              : ""
          }
        `;
      } else {
        content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> ${data.message}</p>`;
      }
    })
    .catch((error) => {
      content.innerHTML = `<p class="error"><i class="fas fa-exclamation-circle"></i> Error: ${error.message}</p>`;
      showToast("Error loading details", "error");
    });
}

function hasFinancialData(loan) {
  return Object.values(loan).some(
    (val) =>
      [
        "business_income",
        "salary_income",
        "net_income",
        "total_expenditures",
        "remaining_income",
      ].includes(val) &&
      val !== null &&
      val !== ""
  );
}

function financialHTML(loan) {
  return `
    <div class="financial-subsection">
      <h4>Income</h4>
      <p><strong>Net Income:</strong> ₱${parseFloat(
        loan.net_income || 0
      ).toFixed(2)}</p>
    </div>
    <div class="financial-subsection">
      <h4>Expenses</h4>
      <p><strong>Total Expenses:</strong> ₱${parseFloat(
        loan.total_expenditures || 0
      ).toFixed(2)}</p>
    </div>
    <div class="financial-subsection">
      <h4>Summary</h4>
      <p><strong>Remaining Income:</strong> ₱${parseFloat(
        loan.remaining_income || 0
      ).toFixed(2)}</p>
    </div>
  `;
}

function closeLoanDetailsModal() {
  const modal = document.getElementById("loanDetailsModal");
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

// LOAN CALCULATOR (COMPLETE)
function openCalculatorModal() {
  if (isModalOpening) return;
  isModalOpening = true;

  const modal = document.getElementById("loanCalculatorModal");
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);

  document.getElementById("calculatorForm").reset();
  document.getElementById("resultsDisplay").innerHTML = `
    <div class="no-result">
      <i class="fa-solid fa-calculator"></i>
      <p>Enter loan details and click Calculate</p>
    </div>
  `;
  document.getElementById("amortizationContainer").style.display = "none";

  fetch("user_closed_records.php?action=get_current_interest_rate", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("interestRate").value = data.interest_rate;
        document.getElementById(
          "interestRateDisplay"
        ).textContent = `${data.interest_rate.toFixed(2)}%`;
      } else {
        showToast("Error loading interest rate", "error");
      }
    })
    .catch(() => showToast("Error loading interest rate", "error"))
    .finally(() => (isModalOpening = false));
}

function closeCalculatorModal() {
  document.getElementById("loanCalculatorModal").classList.remove("show");
  setTimeout(() => {
    document.getElementById("loanCalculatorModal").style.display = "none";
    isModalOpening = false;
  }, 300);
}

function updateLoanAmountRange() {
  const loanType = document.getElementById("loanType").value;
  const input = document.getElementById("loanAmount");
  const label = document.getElementById("loanAmountLabel");

  const ranges = {
    Individual: { min: 10000, max: 100000, text: "₱10,000 - ₱100,000" },
    Cooperative: { min: 100000, max: 1000000, text: "₱100,000 - ₱1,000,000" },
  };

  const range = ranges[loanType] || ranges.Cooperative;
  input.min = range.min;
  input.max = range.max;
  label.textContent = `Loan Amount ${range.text} *`;

  if (input.value && (input.value < range.min || input.value > range.max)) {
    input.value = "";
  }
}

function updateRepaymentOptions() {
  const term = parseInt(document.getElementById("termLength").value);
  const select = document.getElementById("repaymentFrequency");

  Array.from(select.options).forEach((option, i) => {
    if (i === 0) return;
    option.disabled = term === 6 && option.value !== "Monthly";
    option.style.display =
      term === 6 && option.value !== "Monthly" ? "none" : "";
  });

  if (term === 6 && select.value !== "Monthly") {
    select.value = "Monthly";
  }
}

function calculateLoan() {
  const form = document.getElementById("calculatorForm");
  const formData = new FormData(form);
  const data = Object.fromEntries(formData);

  // Validation
  const errors = [];
  if (!data.loanType) errors.push("Select loan type");
  if (!data.loanAmount || data.loanAmount < 10000)
    errors.push("Valid loan amount");
  if (!data.termLength) errors.push("Select term length");
  if (!data.repaymentFrequency) errors.push("Select frequency");

  if (errors.length) {
    showError(errors.join(", "));
    return;
  }

  const payment = calculatePayment(
    parseFloat(data.loanAmount),
    parseFloat(data.interestRate) / 100,
    parseInt(data.termLength),
    data.repaymentFrequency
  );

  if (payment) {
    displayResults(data, payment);
    generateAmortizationSchedule(data, payment);
    showToast("Calculation complete!", "success");
  }
}

function calculatePayment(principal, annualRate, months, frequency) {
  let periodsPerYear;
  switch (frequency) {
    case "Monthly":
      periodsPerYear = 12;
      break;
    case "Quarterly":
      periodsPerYear = 4;
      break;
    case "Annually":
      periodsPerYear = 1;
      break;
  }

  const rate = annualRate / periodsPerYear;
  const n = months / (12 / periodsPerYear);

  if (rate === 0) return principal / n;

  return (
    (principal * (rate * Math.pow(1 + rate, n))) / (Math.pow(1 + rate, n) - 1)
  );
}

function displayResults(data, payment) {
  const totalPayment =
    payment *
    (data.termLength / (12 / getPeriodsPerYear(data.repaymentFrequency)));
  const totalInterest = totalPayment - data.loanAmount;

  document.getElementById("resultsDisplay").innerHTML = `
    <div class="results-display">
      <p><strong>${
        data.repaymentFrequency
      } Payment:</strong> ₱${payment.toFixed(2)}</p>
      <p><strong>Total Payment:</strong> ₱${totalPayment.toFixed(2)}</p>
      <p><strong>Total Interest:</strong> ₱${totalInterest.toFixed(2)}</p>
      <p><strong>Loan Amount:</strong> ₱${parseFloat(
        data.loanAmount
      ).toLocaleString()}</p>
      <p><strong>Term:</strong> ${data.termLength} months</p>
    </div>
  `;
}

function getPeriodsPerYear(frequency) {
  return { Monthly: 12, Quarterly: 4, Annually: 1 }[frequency];
}

function generateAmortizationSchedule(data, payment) {
  const principal = parseFloat(data.loanAmount);
  const annualRate = parseFloat(data.interestRate) / 100;
  const periodsPerYear = getPeriodsPerYear(data.repaymentFrequency);
  const rate = annualRate / periodsPerYear;
  const totalPeriods = data.termLength / (12 / periodsPerYear);

  let balance = principal;
  let html = "";

  for (let i = 1; i <= totalPeriods; i++) {
    const interest = balance * rate;
    const principalPaid = payment - interest;
    balance -= principalPaid;

    html += `
      <tr>
        <td>${i}</td>
        <td>₱${payment.toFixed(2)}</td>
        <td>₱${principalPaid.toFixed(2)}</td>
        <td>₱${interest.toFixed(2)}</td>
        <td>₱${Math.max(0, balance).toFixed(2)}</td>
      </tr>
    `;
  }

  document.getElementById("amortizationBody").innerHTML = html;
  document.getElementById("amortizationContainer").style.display = "block";
}

function showError(message) {
  const errorDiv = document.getElementById("errorMessage");
  errorDiv.textContent = message;
  errorDiv.className = "error-message show";
  setTimeout(() => (errorDiv.className = "error-message"), 5000);
}
