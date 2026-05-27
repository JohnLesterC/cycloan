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
  const dropdown = document.getElementById("dropdown");
  dropdown.classList.toggle("show");
}

window.onclick = function (event) {
  if (!event.target.closest(".profile-container")) {
    const dropdown = document.getElementById("dropdown");
    if (dropdown.classList.contains("show")) {
      dropdown.classList.remove("show");
    }
  }
  const calculatorModal = document.getElementById("loanCalculatorModal");
  if (event.target === calculatorModal) {
    closeCalculatorModal();
  }
};

function openCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  const interestRateDisplay = document.getElementById("interestRateDisplay");
  const interestRateInput = document.getElementById("interestRate");

  if (!modal || !interestRateDisplay || !interestRateInput) {
    console.error("Calculator modal or elements not found in DOM");
    return;
  }

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

  fetch("user_history_activity.php?action=get_current_interest_rate", {
    cache: "no-store",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        interestRateDisplay.textContent = `Current Rate: ${parseFloat(
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
  if (!modal) {
    console.error("Calculator modal not found in DOM");
    return;
  }
  modal.classList.remove("show");
  setTimeout(() => (modal.style.display = "none"), 300);
}

function updateLoanAmountRange() {
  const loanType = document.getElementById("loanType").value;
  const loanAmountInput = document.getElementById("loanAmount");
  const loanAmountLabel = document.getElementById("loanAmountLabel");

  if (!loanAmountInput || !loanAmountLabel) {
    console.error("Loan amount input or label not found in DOM");
    return;
  }

  if (loanType === "Individual") {
    loanAmountInput.min = 10000;
    loanAmountInput.max = 100000;
    loanAmountInput.placeholder = "Enter amount (₱10,000 - ₱100,000)";
    loanAmountLabel.innerHTML = `Loan Amount (₱10,000 - ₱100,000) <span style="color: red;">*</span>`;
  } else if (loanType === "Cooperative") {
    loanAmountInput.min = 100000;
    loanAmountInput.max = 1000000;
    loanAmountInput.placeholder = "Enter amount (₱100,000 - ₱1,000,000)";
    loanAmountLabel.innerHTML = `Loan Amount (₱100,000 - ₱1,000,000) <span style="color: red;">*</span>`;
  }
  loanAmountInput.value = "";
}

function updateRepaymentOptions() {
  const termLength = parseInt(document.getElementById("termLength").value) || 0;
  const freqSelect = document.getElementById("repaymentFrequency");

  if (!freqSelect) return;

  const options = freqSelect.options;

  // Reset all options first
  for (let i = 1; i < options.length; i++) {
    options[i].disabled = false;
    options[i].style.display = "";
  }

  // If 6 months is selected, disable Quarterly and Annually
  if (termLength === 6) {
    for (let i = 1; i < options.length; i++) {
      const val = options[i].value;
      if (val === "Quarterly" || val === "Annually") {
        options[i].disabled = true;
        options[i].style.display = "none";
      }
    }

    // If current selection is invalid, reset to empty or Monthly
    if (freqSelect.value === "Quarterly" || freqSelect.value === "Annually") {
      freqSelect.value = "";
    }
  }
}

function calculateLoan() {
  const loanType = document.getElementById("loanType").value;
  const loanAmount = parseFloat(document.getElementById("loanAmount").value);
  const interestRate =
    parseFloat(document.getElementById("interestRate").value) / 100;
  const termLength = parseInt(document.getElementById("termLength").value);
  const repaymentFrequency =
    document.getElementById("repaymentFrequency").value;
  const errorDiv = document.getElementById("errorMessage");
  const resultsDisplay = document.getElementById("resultsDisplay");

  // Clear previous error
  errorDiv.classList.remove("show");

  // Validation
  let error = "";
  if (!loanType) {
    error = "Please select a loan type.";
  } else if (isNaN(loanAmount) || loanAmount <= 0) {
    error = "Please enter a valid loan amount.";
  } else if (
    loanType === "Individual" &&
    (loanAmount < 10000 || loanAmount > 100000)
  ) {
    error = "Individual loan amount must be between ₱10,000 and ₱100,000.";
  } else if (
    loanType === "Cooperative" &&
    (loanAmount < 100000 || loanAmount > 1000000)
  ) {
    error = "Cooperative loan amount must be between ₱100,000 and ₱1,000,000.";
  } else if (isNaN(interestRate)) {
    error = "Interest rate not loaded. Please try again.";
  } else if (!termLength || ![6, 12, 18, 24, 36].includes(termLength)) {
    error = "Please select a valid term length.";
  } else if (!repaymentFrequency) {
    error = "Please select a repayment frequency.";
  } else if (termLength === 6 && repaymentFrequency !== "Monthly") {
    error = "For 6-month term, only Monthly repayment is allowed.";
  }

  if (error) {
    showError(error);
    return;
  }

  // Calculate loan
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
  const periodRate = interestRate / paymentsPerYear;
  const paymentAmount =
    (loanAmount * (periodRate * Math.pow(1 + periodRate, totalPayments))) /
    (Math.pow(1 + periodRate, totalPayments) - 1);
  const totalInterest = paymentAmount * totalPayments - loanAmount;
  const totalRepayment = loanAmount + totalInterest;

  if (
    isNaN(paymentAmount) ||
    paymentAmount === Infinity ||
    paymentAmount <= 0
  ) {
    showError("Please enter valid values.");
    return;
  }

  displayResults(
    paymentAmount,
    totalInterest,
    totalRepayment,
    loanAmount,
    totalPayments,
    repaymentFrequency,
    termLength
  );
  generateAmortizationSchedule(
    loanAmount,
    periodRate,
    totalPayments,
    paymentAmount
  );
}

function displayResults(
  paymentAmount,
  totalInterest,
  totalRepayment,
  loanAmount,
  totalPayments,
  frequency,
  termLength
) {
  const resultsHtml = `
        <div class="result-card">
            <div class="result-label">Payment per ${frequency}</div>
            <div class="result-value">₱${paymentAmount.toFixed(2)}</div>
        </div>
        <div class="result-card">
            <div class="result-label">Total Interest</div>
            <div class="result-value">₱${totalInterest.toFixed(2)}</div>
        </div>
        <div class="result-card">
            <div class="result-label">Total Amount Payable</div>
            <div class="result-value">₱${totalRepayment.toFixed(2)}</div>
        </div>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-item-label">Principal</div>
                <div class="summary-item-value">₱${loanAmount.toFixed(2)}</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Total Payments</div>
                <div class="summary-item-value">${totalPayments.toFixed(
                  0
                )}</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Interest Rate</div>
                <div class="summary-item-value">${parseFloat(
                  document.getElementById("interestRate").value
                ).toFixed(2)}%</div>
            </div>
            <div class="summary-item">
                <div class="summary-item-label">Loan Term</div>
                <div class="summary-item-value">${termLength} Months</div>
            </div>
        </div>
    `;
  document.getElementById("resultsDisplay").innerHTML = resultsHtml;
}

function generateAmortizationSchedule(
  principal,
  periodRate,
  totalPayments,
  paymentAmount
) {
  let balance = principal;
  let paymentNumber = 1;
  let tbody = document.getElementById("amortizationBody");
  tbody.innerHTML = "";

  for (let i = 0; i < totalPayments; i++) {
    const interestPayment = balance * periodRate;
    const principalPayment = paymentAmount - interestPayment;
    balance -= principalPayment;

    if (balance < 0) balance = 0;

    const row = document.createElement("tr");
    row.innerHTML = `
            <td>${paymentNumber}</td>
            <td>₱${paymentAmount.toFixed(2)}</td>
            <td>₱${principalPayment.toFixed(2)}</td>
            <td>₱${interestPayment.toFixed(2)}</td>
            <td>₱${balance.toFixed(2)}</td>
        `;
    tbody.appendChild(row);
    paymentNumber++;
  }

  document.getElementById("amortizationContainer").style.display = "block";
}

function showError(message) {
  const errorDiv = document.getElementById("errorMessage");
  document.getElementById("errorText").textContent = message;
  errorDiv.classList.add("show");
}

// Tab switching function
function switchTab(tabName) {
  // Hide all tab contents
  const tabContents = document.querySelectorAll(".tab-content");
  tabContents.forEach((content) => {
    content.classList.remove("active");
  });

  // Remove active class from all tab buttons
  const tabButtons = document.querySelectorAll(".tab-btn");
  tabButtons.forEach((btn) => {
    btn.classList.remove("active");
  });

  // Show selected tab content
  const selectedTab = document.getElementById(tabName + "Tab");
  if (selectedTab) {
    selectedTab.classList.add("active");
  }

  // Add active class to clicked button
  const activeButton = document.querySelector(
    `button[onclick="switchTab('${tabName}')"]`
  );
  if (activeButton) {
    activeButton.classList.add("active");
  }
}

// Add DOMContentLoaded event listener
document.addEventListener("DOMContentLoaded", function () {
  const termLengthSelect = document.getElementById("termLength");
  if (termLengthSelect) {
    termLengthSelect.addEventListener("change", updateRepaymentOptions);
  }

  // Initialize first tab as active on page load
  switchTab("login");
});
