// user_active_record.js - COMPLETE LOAN CALCULATOR (EXACTLY like user_dashboard.js)

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
    if (event.target.id === "viewLoanModal") closeViewLoanModal();
    else if (event.target.id === "loanCalculatorModal") closeCalculatorModal();
  }
};

// ========== VIEW LOAN MODAL ==========
function openViewLoanModal(loanId) {
  const modal = document.getElementById("viewLoanModal");
  modal.style.display = "flex";
  modal.classList.add("show");
  document.getElementById("loanDetailsContent").innerHTML = "<p>Loading...</p>";

  fetch("get_loan_details_user.php?id=" + loanId)
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        document.getElementById(
          "loanDetailsContent"
        ).innerHTML = `<p style="color: red;">${data.error}</p>`;
      } else {
        document.getElementById("loanDetailsContent").innerHTML = `
                    <p><strong>Loan ID:</strong> ${data.loan_id}</p>
                    <p><strong>Loan Amount:</strong> ₱${parseFloat(
                      data.amount
                    ).toFixed(2)}</p>
                    <p><strong>Duration:</strong> ${data.duration} months</p>
                    <p><strong>Interest Rate:</strong> ${
                      data.interest_rate
                    }%</p>
                    <p><strong>Monthly Payment:</strong> ₱${parseFloat(
                      data.monthly_payment
                    ).toFixed(2)}</p>
                    <p><strong>Created At:</strong> ${data.created_at}</p>
                    <p><strong>Status:</strong> ${data.status}</p>
                    <h3>Payment Schedule</h3>
                    <table>
                        <thead><tr><th>ID</th><th>Due Date</th><th>Amount</th><th>Status</th></tr></thead>
                        <tbody>${data.schedule
                          .map(
                            (item, index) =>
                              `<tr><td>${index + 1}</td><td>${
                                item.due_date
                              }</td><td>₱${parseFloat(item.amount).toFixed(
                                2
                              )}</td><td>${item.status}</td></tr>`
                          )
                          .join("")}</tbody>
                    </table>
                `;
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      document.getElementById(
        "loanDetailsContent"
      ).innerHTML = `<p style="color: red;">Error loading loan details.</p>`;
    });
}

function closeViewLoanModal() {
  document.getElementById("viewLoanModal").style.display = "none";
  document.getElementById("viewLoanModal").classList.remove("show");
}

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

  fetch("user_active_record.php?action=get_current_interest_rate", {
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
    loanAmount.min = 50000;
    loanAmount.max = 500000;
    loanAmountLabel.innerHTML =
      'Loan Amount (₱50,000 - ₱500,000) <span style="color: red;">*</span>';
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

  if (termLength === 6 && repaymentFrequency !== "Monthly") {
    showError("For 6-month term, only Monthly repayment is allowed.");
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
}

// ========== INITIALIZE ==========
document.addEventListener("DOMContentLoaded", function () {
  const termLengthSelect = document.getElementById("termLength");
  if (termLengthSelect) {
    termLengthSelect.addEventListener("change", updateRepaymentOptions);
  }
});
