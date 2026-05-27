let isModalOpening = false;
let isSubmitting = false;

function showToast(message, type = "success") {
  Toastify({
    text: message,
    duration: 3000,
    close: true,
    gravity: "top",
    position: "right",
    style: {
      background: type === "success" ? "#28a745" : "#dc2626",
    },
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

function openCreateLoanModal(applicationId, event) {
  event.preventDefault();
  event.stopPropagation();
  if (isModalOpening) return;
  isModalOpening = true;
  const modal = document.getElementById("createLoanModal");
  resetForm();

  // Set the application_id AFTER resetting the form
  const loanIdField = document.getElementById("loanId");
  if (!loanIdField) {
    alert("ERROR: loanId field not found!");
    return;
  }

  loanIdField.value = applicationId;
  console.log("=== MODAL OPENED ===");
  console.log("Setting application_id to:", applicationId);
  console.log("loanId field value after setting:", loanIdField.value);
  console.log("===================");

  modal.classList.add("show");
  modal.style.display = "block";
  document.getElementById("loanAmount").focus();
  setTimeout(() => {
    isModalOpening = false;
  }, 300);
}

function closeCreateLoanModal() {
  const modal = document.getElementById("createLoanModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
  }, 300);
  isModalOpening = false;
}

function updateDurationConstraints() {
  const loanAmount =
    parseFloat(document.getElementById("loanAmount").value) || 0;
  const durationInput = document.getElementById("duration");
  let minDuration, maxDuration, errorMessage;

  if (loanAmount === 10000) {
    minDuration = 12;
    maxDuration = 18;
    errorMessage = "For 10,000 PHP, duration must be 12–18 months.";
  } else if (loanAmount > 10000 && loanAmount <= 50000) {
    minDuration = 12;
    maxDuration = 36;
    errorMessage = "For 10,001–50,000 PHP, duration must be 12–36 months.";
  } else if (loanAmount > 50000) {
    minDuration = 24;
    maxDuration = 60;
    errorMessage = "For >50,000 PHP, duration must be 24–60 months.";
  } else {
    minDuration = 12;
    maxDuration = 18;
    errorMessage = "Please enter a valid amount (minimum 10,000 PHP).";
  }

  durationInput.setAttribute("min", minDuration);
  durationInput.setAttribute("max", maxDuration);
  document.getElementById("durationError").textContent = errorMessage;
  updateFrequencyOptions();
}

function updateFrequencyOptions() {
  const duration = parseInt(document.getElementById("duration").value) || 0;
  const frequencySelect = document.getElementById("frequency");
  const quarterlyOption = frequencySelect.querySelector(
    'option[value="quarterly"]'
  );
  const semiAnnuallyOption = frequencySelect.querySelector(
    'option[value="semi-annually"]'
  );
  const annuallyOption = frequencySelect.querySelector(
    'option[value="annually"]'
  );

  quarterlyOption.disabled = duration < 6;
  semiAnnuallyOption.disabled = duration < 6;
  annuallyOption.disabled = duration < 12;

  if (
    (frequencySelect.value === "quarterly" && duration < 6) ||
    (frequencySelect.value === "semi-annually" && duration < 6) ||
    (frequencySelect.value === "annually" && duration < 12)
  ) {
    frequencySelect.value = "";
  }
}

function validateInputs() {
  const loanAmount =
    parseFloat(document.getElementById("loanAmount").value) || 0;
  const duration = parseInt(document.getElementById("duration").value) || 0;
  const frequency = document.getElementById("frequency").value;

  document.getElementById("durationError").style.display = "none";
  document.getElementById("frequencyError").style.display = "none";

  let valid = true;

  // Loan amount is now read-only, so we don't validate it
  // It's automatically set from the application's final_amount

  let minDuration, maxDuration, durationErrorMessage;
  if (loanAmount === 10000) {
    minDuration = 12;
    maxDuration = 18;
    durationErrorMessage = "For 10,000 PHP, duration must be 12–18 months.";
  } else if (loanAmount > 10000 && loanAmount <= 50000) {
    minDuration = 12;
    maxDuration = 36;
    durationErrorMessage =
      "For 10,001–50,000 PHP, duration must be 12–36 months.";
  } else if (loanAmount > 50000) {
    minDuration = 24;
    maxDuration = 60;
    durationErrorMessage = "For >50,000 PHP, duration must be 24–60 months.";
  } else {
    minDuration = 12;
    maxDuration = 18;
    durationErrorMessage = "Invalid loan amount.";
  }

  if (isNaN(duration) || duration < minDuration || duration > maxDuration) {
    document.getElementById("durationError").textContent = durationErrorMessage;
    document.getElementById("durationError").classList.add("show");
    document.getElementById("durationError").style.display = "block";
    valid = false;
  }

  if (!frequency) {
    document.getElementById("frequencyError").textContent =
      "Please select a valid payment frequency.";
    document.getElementById("frequencyError").classList.add("show");
    document.getElementById("frequencyError").style.display = "block";
    valid = false;
  } else if (frequency === "quarterly" && duration < 6) {
    document.getElementById("frequencyError").textContent =
      "Quarterly payments require at least 6 months duration.";
    document.getElementById("frequencyError").classList.add("show");
    document.getElementById("frequencyError").style.display = "block";
    valid = false;
  } else if (frequency === "semi-annually" && duration < 6) {
    document.getElementById("frequencyError").textContent =
      "Semi-annual payments require at least 6 months duration.";
    document.getElementById("frequencyError").classList.add("show");
    document.getElementById("frequencyError").style.display = "block";
    valid = false;
  } else if (frequency === "annually" && duration < 12) {
    document.getElementById("frequencyError").textContent =
      "Annual payments require at least 12 months duration.";
    document.getElementById("frequencyError").classList.add("show");
    document.getElementById("frequencyError").style.display = "block";
    valid = false;
  }

  return valid;
}

// ========== REAL-TIME VALIDATION ENHANCEMENTS ==========
// Real-time loan amount validation (now just displays the final amount - read-only)
function validateLoanAmountRealTime() {
  // Loan amount is now read-only, so no validation needed
  const loanAmount =
    parseFloat(document.getElementById("loanAmount").value) || 0;
  const loanAmountDisplay = document.getElementById("loanAmountDisplay");

  if (loanAmountDisplay && loanAmount > 0) {
    loanAmountDisplay.textContent =
      "₱" +
      loanAmount.toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
  }
}

// Real-time duration validation
function validateDurationRealTime() {
  const duration = parseInt(document.getElementById("duration").value) || 0;
  const loanAmount =
    parseFloat(document.getElementById("loanAmount").value) || 0;
  const errorElement = document.getElementById("durationError");
  const durationInput = document.getElementById("duration");

  if (duration === 0 || duration === "") {
    durationInput.classList.remove("valid", "invalid");
    errorElement.textContent = "";
    return;
  }

  let minDuration, maxDuration;
  if (loanAmount === 10000) {
    minDuration = 12;
    maxDuration = 18;
  } else if (loanAmount > 10000 && loanAmount <= 50000) {
    minDuration = 12;
    maxDuration = 36;
  } else if (loanAmount > 50000) {
    minDuration = 24;
    maxDuration = 60;
  } else {
    minDuration = 12;
    maxDuration = 18;
  }

  if (duration < minDuration || duration > maxDuration) {
    durationInput.classList.add("invalid");
    durationInput.classList.remove("valid");
    errorElement.textContent = `⚠️ Duration must be between ${minDuration} and ${maxDuration} months`;
    errorElement.classList.add("show");
  } else {
    durationInput.classList.add("valid");
    durationInput.classList.remove("invalid");
    errorElement.textContent = "✓ Valid duration";
    errorElement.classList.remove("show");
    updateFrequencyOptions();
  }
}

// Real-time frequency validation
function validateFrequencyRealTime() {
  const frequency = document.getElementById("frequency").value;
  const duration = parseInt(document.getElementById("duration").value) || 0;
  const errorElement = document.getElementById("frequencyError");
  const frequencySelect = document.getElementById("frequency");

  if (frequency === "") {
    frequencySelect.classList.remove("valid", "invalid");
    errorElement.textContent = "";
    return;
  }

  let isValid = true;
  let message = "";

  if (frequency === "quarterly" && duration < 6) {
    isValid = false;
    message = "⚠️ Quarterly payments need at least 6 months";
  } else if (frequency === "semi-annually" && duration < 6) {
    isValid = false;
    message = "⚠️ Semi-annual payments need at least 6 months";
  } else if (frequency === "annually" && duration < 12) {
    isValid = false;
    message = "⚠️ Annual payments need at least 12 months";
  }

  if (isValid) {
    frequencySelect.classList.add("valid");
    frequencySelect.classList.remove("invalid");
    errorElement.textContent = "✓ Valid frequency";
    errorElement.classList.remove("show");
  } else {
    frequencySelect.classList.add("invalid");
    frequencySelect.classList.remove("valid");
    errorElement.textContent = message;
    errorElement.classList.add("show");
  }
}

// Initialize real-time event listeners
function initializeRealTimeValidation() {
  // Loan amount is now read-only, so we don't need to listen for changes
  const durationInput = document.getElementById("duration");
  const frequencySelect = document.getElementById("frequency");

  if (durationInput) {
    durationInput.addEventListener("input", validateDurationRealTime);
    durationInput.addEventListener("blur", validateDurationRealTime);
  }

  if (frequencySelect) {
    frequencySelect.addEventListener("change", validateFrequencyRealTime);
    frequencySelect.addEventListener("blur", validateFrequencyRealTime);
  }
}

// Call initialization when modal opens
function openCreateLoanModal(applicationId, finalAmount, event) {
  event.preventDefault();
  event.stopPropagation();
  if (isModalOpening) return;
  isModalOpening = true;
  const modal = document.getElementById("createLoanModal");
  resetForm();

  // Set the application_id AFTER resetting the form
  const loanIdField = document.getElementById("loanId");
  if (!loanIdField) {
    alert("ERROR: loanId field not found!");
    return;
  }

  loanIdField.value = applicationId;

  // Set the final loan amount (read-only)
  const loanAmountField = document.getElementById("loanAmount");
  const loanAmountDisplay = document.getElementById("loanAmountDisplay");

  if (loanAmountField && loanAmountDisplay) {
    loanAmountField.value = finalAmount;
    loanAmountDisplay.textContent =
      "₱" +
      parseFloat(finalAmount).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
  }

  console.log("=== MODAL OPENED ===");
  console.log("Setting application_id to:", applicationId);
  console.log("Setting final amount to:", finalAmount);
  console.log("loanId field value after setting:", loanIdField.value);
  console.log("===================");

  modal.classList.add("show");
  modal.style.display = "block";
  document.getElementById("duration").focus();

  // Initialize real-time validation when modal opens
  setTimeout(() => {
    initializeRealTimeValidation();
    isModalOpening = false;
  }, 300);
}
function calculatePayment() {
  try {
    if (!validateInputs()) {
      document.getElementById("paymentResult").innerHTML = "";
      return;
    }

    const loanAmount = parseFloat(document.getElementById("loanAmount").value);
    const duration = parseInt(document.getElementById("duration").value);
    const frequency = document.getElementById("frequency").value;
    const annualInterestRate = parseFloat(
      document.getElementById("interestRate").value
    );
    const monthlyInterestRate = annualInterestRate / 100 / 12;

    let paymentAmount,
      numberOfPayments,
      paymentLabel,
      monthsPerPayment,
      interestRatePerPeriod;
    let totalInterest = 0;
    let totalPrincipal = loanAmount;
    let remainingBalance = loanAmount;
    let schedule = [];

    if (frequency === "monthly") {
      monthsPerPayment = 1;
      numberOfPayments = duration;
      interestRatePerPeriod = monthlyInterestRate;
      paymentAmount =
        (loanAmount * monthlyInterestRate) /
        (1 - Math.pow(1 + monthlyInterestRate, -duration));
      paymentLabel = "Monthly Payment";
    } else if (frequency === "quarterly") {
      monthsPerPayment = 3;
      numberOfPayments = Math.ceil(duration / 3);
      interestRatePerPeriod = monthlyInterestRate * 3;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
      paymentLabel = "Quarterly Payment";
    } else if (frequency === "semi-annually") {
      monthsPerPayment = 6;
      numberOfPayments = Math.ceil(duration / 6);
      interestRatePerPeriod = monthlyInterestRate * 6;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
      paymentLabel = "Semi-Annual Payment";
    } else if (frequency === "annually") {
      monthsPerPayment = 12;
      numberOfPayments = Math.ceil(duration / 12);
      interestRatePerPeriod = monthlyInterestRate * 12;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
      paymentLabel = "Annual Payment";
    }

    const today = new Date();
    for (let i = 0; i < numberOfPayments; i++) {
      const interest = remainingBalance * interestRatePerPeriod;
      const principal = paymentAmount - interest;
      remainingBalance = Math.max(0, remainingBalance - principal);
      totalInterest += interest;
      const dueDate = new Date(today);
      dueDate.setMonth(today.getMonth() + i * monthsPerPayment);
      // Handle day overflow (e.g., Jan 31 + 1 month → Feb 28/29)
      const day = dueDate.getDate();
      dueDate.setDate(1); // Reset to 1st to avoid overflow
      dueDate.setMonth(dueDate.getMonth() + 1); // Add one more month
      dueDate.setDate(
        Math.min(
          day,
          new Date(dueDate.getFullYear(), dueDate.getMonth(), 0).getDate()
        )
      ); // Set to last day of month if overflow
      schedule.push({
        due_date: dueDate.toISOString().split("T")[0],
        amount: paymentAmount.toFixed(2),
        interest_amount: interest.toFixed(2),
        principal_amount: principal.toFixed(2),
        balance: remainingBalance.toFixed(2),
      });
    }

    const finalAmount = (totalPrincipal + totalInterest).toFixed(2);

    let scheduleHtml = `
      <div class="payment-summary">
        <p><strong>${paymentLabel}:</strong> ₱${parseFloat(
      paymentAmount
    ).toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}</p>
        <p><strong>Total Principal:</strong> ₱${parseFloat(
          totalPrincipal
        ).toLocaleString("en-PH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}</p>
        <p><strong>Total Interest:</strong> ₱${parseFloat(
          totalInterest
        ).toLocaleString("en-PH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}</p>
        <p><strong>Final Amount:</strong> ₱${parseFloat(
          finalAmount
        ).toLocaleString("en-PH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })}</p>
      </div>
      <h3>Payment Schedule Preview</h3>
      <div class="scrollable-table payment-schedule-table">
        <table class="loan-table" role="grid" aria-label="Payment Schedule Preview">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Due Date</th>
              <th scope="col">Amount</th>
              <th scope="col">Interest</th>
              <th scope="col">Principal</th>
              <th scope="col">Balance</th>
            </tr>
          </thead>
          <tbody>`;
    schedule.forEach((item, index) => {
      scheduleHtml += `
        <tr>
          <td data-label="#">${index + 1}</td>
          <td data-label="Due Date">${item.due_date}</td>
          <td data-label="Amount">₱${parseFloat(item.amount).toLocaleString(
            "en-PH",
            {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }
          )}</td>
          <td data-label="Interest">₱${parseFloat(
            item.interest_amount
          ).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</td>
          <td data-label="Principal">₱${parseFloat(
            item.principal_amount
          ).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</td>
          <td data-label="Balance">₱${parseFloat(item.balance).toLocaleString(
            "en-PH",
            {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }
          )}</td>
        </tr>`;
    });
    scheduleHtml += `
        <tr class="total-row">
          <td data-label="Total" colspan="2"><strong>Total</strong></td>
          <td data-label="Total Amount">₱${parseFloat(
            totalInterest + totalPrincipal
          ).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</td>
          <td data-label="Total Interest">₱${parseFloat(
            totalInterest
          ).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</td>
          <td data-label="Total Principal">₱${parseFloat(
            totalPrincipal
          ).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          })}</td>
          <td data-label="Balance"></td>
        </tr>
      </tbody>
    </table>
  </div>`;
    document.getElementById("paymentResult").innerHTML = scheduleHtml;
  } catch (error) {
    showToast("Error calculating payment: " + error.message, "error");
    document.getElementById("paymentResult").innerHTML = "";
  }
}

function resetForm() {
  document.getElementById("createLoanForm").reset();
  document.getElementById("paymentResult").innerHTML = "";
  document.getElementById("loanAmountError").style.display = "none";
  document.getElementById("durationError").style.display = "none";
  document.getElementById("frequencyError").style.display = "none";
  const durationInput = document.getElementById("duration");
  durationInput.removeAttribute("min");
  durationInput.removeAttribute("max");
  const frequencySelect = document.getElementById("frequency");
  frequencySelect
    .querySelectorAll("option")
    .forEach((opt) => (opt.disabled = false));
}

document
  .getElementById("createLoanForm")
  .addEventListener("submit", function (e) {
    e.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true;
    const submitBtn = document.getElementById("submitBtn");
    submitBtn.disabled = true;
    submitBtn.classList.add("loading");

    if (!validateInputs()) {
      submitBtn.disabled = false;
      submitBtn.classList.remove("loading");
      isSubmitting = false;
      return;
    }

    const formData = new FormData(this);
    console.log("FormData contents:");
    for (let pair of formData.entries()) {
      console.log(pair[0] + ": " + pair[1]);
    }

    const loanAmount = parseFloat(formData.get("amount"));
    const duration = parseInt(formData.get("duration"));
    const frequency = formData.get("frequency");
    const annualInterestRate = parseFloat(formData.get("interest_rate"));
    const monthlyInterestRate = annualInterestRate / 100 / 12;

    let paymentAmount,
      numberOfPayments,
      monthsPerPayment,
      interestRatePerPeriod;
    let totalInterest = 0;
    let totalPrincipal = loanAmount;
    let remainingBalance = loanAmount;
    let schedule = [];

    if (frequency === "monthly") {
      monthsPerPayment = 1;
      numberOfPayments = duration;
      interestRatePerPeriod = monthlyInterestRate;
      paymentAmount =
        (loanAmount * monthlyInterestRate) /
        (1 - Math.pow(1 + monthlyInterestRate, -duration));
    } else if (frequency === "quarterly") {
      monthsPerPayment = 3;
      numberOfPayments = Math.ceil(duration / 3);
      interestRatePerPeriod = monthlyInterestRate * 3;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
    } else if (frequency === "semi-annually") {
      monthsPerPayment = 6;
      numberOfPayments = Math.ceil(duration / 6);
      interestRatePerPeriod = monthlyInterestRate * 6;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
    } else if (frequency === "annually") {
      monthsPerPayment = 12;
      numberOfPayments = Math.ceil(duration / 12);
      interestRatePerPeriod = monthlyInterestRate * 12;
      paymentAmount =
        (loanAmount * interestRatePerPeriod) /
        (1 - Math.pow(1 + interestRatePerPeriod, -numberOfPayments));
    }

    const today = new Date();
    for (let i = 0; i < numberOfPayments; i++) {
      const interest = remainingBalance * interestRatePerPeriod;
      const principal = paymentAmount - interest;
      remainingBalance = Math.max(0, remainingBalance - principal);
      totalInterest += interest;
      const dueDate = new Date(today);
      dueDate.setMonth(today.getMonth() + i * monthsPerPayment);
      const day = dueDate.getDate();
      dueDate.setDate(1);
      dueDate.setMonth(dueDate.getMonth() + 1);
      dueDate.setDate(
        Math.min(
          day,
          new Date(dueDate.getFullYear(), dueDate.getMonth(), 0).getDate()
        )
      );
      schedule.push({
        due_date: dueDate.toISOString().split("T")[0],
        amount: paymentAmount.toFixed(2),
        interest_amount: interest.toFixed(2),
        principal_amount: principal.toFixed(2),
      });
    }

    formData.append("payment_schedule", JSON.stringify(schedule));
    formData.append("total_interest", totalInterest.toFixed(2));
    formData.append("total_principal", totalPrincipal.toFixed(2));
    formData.append("payment_amount", paymentAmount.toFixed(2));
    formData.append("remaining_balance", remainingBalance.toFixed(2));

    console.log("=== BEFORE SENDING ===");
    console.log(
      "Checking loanId field value:",
      document.getElementById("loanId").value
    );
    console.log("FormData application_id:", formData.get("application_id"));
    console.log("All FormData entries:");
    for (let pair of formData.entries()) {
      console.log("  " + pair[0] + ": " + pair[1]);
    }
    console.log("===================");

    fetch("create_loan_process.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => {
        console.log("Response status:", res.status);
        return res.text();
      })
      .then((text) => {
        console.log("Raw response:", text);
        try {
          const data = JSON.parse(text);
          if (data && data.status === "success") {
            showToast(data.message, "success");
            closeCreateLoanModal();
            location.reload();
          } else {
            showToast(
              data && data.message ? data.message : "Unknown error occurred",
              "error"
            );
          }
        } catch (error) {
          showToast(
            "Error parsing JSON: " + error.message + " - Response: " + text,
            "error"
          );
        }
      })
      .catch((error) => {
        showToast("Error creating loan: " + error.message, "error");
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("loading");
        isSubmitting = false;
      });
  });

function openViewLoanModal(loanId, event) {
  event.preventDefault();
  event.stopPropagation();
  const modal = document.getElementById("viewLoanModal");
  const loanDetailsContent = document.getElementById("loanDetailsContent");

  loanDetailsContent.innerHTML = `
    <div class="loading-overlay">
      <div class="loading-spinner"></div>
      <div class="loading-text">Loading loan details...</div>
    </div>
  `;
  modal.classList.add("show");
  modal.style.display = "block";

  fetch(`get_loan_details.php?id=${loanId}`)
    .then((response) => {
      if (!response.ok)
        throw new Error(`HTTP error! Status: ${response.status}`);
      return response.json();
    })
    .then((data) => {
      if (!data) {
        throw new Error("No data returned from server");
      }
      if (data.error) {
        loanDetailsContent.innerHTML = `<p class="error-message show">${data.error}</p>`;
      } else {
        const frequency = data.payment_frequency
          ? data.payment_frequency.charAt(0).toUpperCase() +
            data.payment_frequency.slice(1)
          : "Not specified";
        loanDetailsContent.innerHTML = `
          <div class="loan-info">
          <p><strong>Loan ID:</strong> ${data.application_loan_id || "N/A"}</p>
          <p><strong>Loan Amount:</strong> ₱${
            data.amount
              ? parseFloat(data.amount).toLocaleString("en-PH", {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                })
              : "0.00"
          }</p>
            <p><strong>Total Principal:</strong> ₱${
              data.total_principal
                ? parseFloat(data.total_principal).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Total Interest:</strong> ₱${
              data.total_interest
                ? parseFloat(data.total_interest).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Final Amount:</strong> ₱${
              data.final_amount
                ? parseFloat(data.final_amount).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Duration:</strong> ${data.duration || "N/A"} months</p>
            <p><strong>Interest Rate:</strong> ${
              data.interest_rate || "N/A"
            }%</p>
            <p><strong>Payment Amount:</strong> ₱${
              data.payment_amount
                ? parseFloat(data.payment_amount).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Payment Frequency:</strong> ${frequency}</p>
            <p><strong>Created At:</strong> ${data.created_at || "N/A"}</p>
            <p><strong>Total Paid:</strong> ₱${
              data.total_paid
                ? parseFloat(data.total_paid).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Remaining Balance:</strong> ₱${
              data.remaining_balance
                ? parseFloat(data.remaining_balance).toLocaleString("en-PH", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                  })
                : "0.00"
            }</p>
            <p><strong>Status:</strong> <span class="status-badge ${data.status.toLowerCase()}">${
          data.status
        }</span></p>
          </div>
          
          <h3><i class="fa-solid fa-calendar-days"></i> Payment Schedule</h3>
          
            <table class="loan-table" role="grid" aria-label="Payment Schedule">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Due Date</th>
                  <th scope="col">Amount Due</th>
                  <th scope="col">Interest Due</th>
                  <th scope="col">Principal Due</th>
                  <th scope="col">Amount Paid</th>
                  <th scope="col">Interest Paid</th>
                  <th scope="col">Principal Paid</th>
                  <th scope="col">Status</th>
                  <th scope="col">Actions</th>
                </tr>
              </thead>
              <tbody>
                ${
                  data.schedule && data.schedule.length > 0
                    ? data.schedule
                        .map(
                          (item, index) => `
                    <tr>
                      <td data-label="#">${index + 1}</td>
                      <td data-label="Due Date">${item.due_date || "N/A"}</td>
                      <td data-label="Amount Due">₱${parseFloat(
                        item.amount
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Interest Due">₱${parseFloat(
                        item.interest_amount
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Principal Due">₱${parseFloat(
                        item.principal_amount
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Amount Paid">₱${parseFloat(
                        item.amount_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Interest Paid">₱${parseFloat(
                        item.interest_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Principal Paid">₱${parseFloat(
                        item.principal_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Status"><span class="status-badge ${item.status.toLowerCase()}">${
                            item.status
                          }</span></td>
                      <td data-label="Actions">
                        ${
                          item.status !== "Paid"
                            ? `<button class="action-btn pay-btn" onclick="openPaymentModal(${
                                item.payment_id
                              }, ${item.amount}, ${item.amount_paid || 0}, ${
                                item.interest_amount
                              }, ${item.principal_amount}, ${
                                item.interest_paid || 0
                              }, ${
                                item.principal_paid || 0
                              })" aria-label="Make payment for schedule item ${
                                index + 1
                              }"><i class="fas fa-hand-holding-usd"></i> Pay</button>`
                            : `<span style="color: var(--primary); font-weight: 600;"><i class="fas fa-check-circle"></i> Paid</span>`
                        }
                      </td>
                    </tr>
                  `
                        )
                        .join("")
                    : `<tr><td colspan="10" style="text-align: center; padding: 30px; color: #6b7280;">No payment schedule available</td></tr>`
                }
              </tbody>
            </table>
          
          
          <h3><i class="fa-solid fa-clock-rotate-left"></i> Payment History</h3>
          
            <table class="loan-table" role="grid" aria-label="Payment History">
              <thead>
                <tr>
                  <th scope="col">#</th>
                  <th scope="col">Payment Date</th>
                  <th scope="col">Amount Paid</th>
                  <th scope="col">Interest Paid</th>
                  <th scope="col">Principal Paid</th>
                  <th scope="col">Payment Type</th>
                  <th scope="col">Invoice Number</th>
                  <th scope="col">Processed By</th>
                </tr>
              </thead>
              <tbody>
                ${
                  data.payment_history && data.payment_history.length > 0
                    ? data.payment_history
                        .map(
                          (item, index) => `
                    <tr>
                      <td data-label="#">${index + 1}</td>
                      <td data-label="Payment Date">${
                        item.payment_date || "N/A"
                      }</td>
                      <td data-label="Amount Paid">₱${parseFloat(
                        item.amount_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Interest Paid">₱${parseFloat(
                        item.interest_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Principal Paid">₱${parseFloat(
                        item.principal_paid || 0
                      ).toLocaleString("en-PH", {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                      })}</td>
                      <td data-label="Payment Type">${
                        item.payment_type
                          ? item.payment_type.charAt(0).toUpperCase() +
                            item.payment_type.slice(1)
                          : "N/A"
                      }</td>
                      <td data-label="Invoice Number">${
                        item.invoice_number || "N/A"
                      }</td>
                      <td data-label="Processed By">${
                        item.admin_name || "Unknown"
                      }</td>
                    </tr>
                  `
                        )
                        .join("")
                    : `<tr><td colspan="8" style="text-align: center; padding: 30px; color: #6b7280;">No payment history available</td></tr>`
                }
              </tbody>
            </table>
          `;
      }
    })
    .catch((error) => {
      showToast("Error loading loan details: " + error.message, "error");
      loanDetailsContent.innerHTML = `<p class="error-message show">Error loading loan details: ${error.message}</p>`;
    });
}

function openPaymentModal(
  paymentId,
  amountDue,
  amountPaid,
  interestDue,
  principalDue,
  interestPaid,
  principalPaid
) {
  const remainingDue = parseFloat((amountDue - amountPaid).toFixed(2));
  const remainingInterest = parseFloat((interestDue - interestPaid).toFixed(2));
  const remainingPrincipal = parseFloat(
    (principalDue - principalPaid).toFixed(2)
  );
  const today = new Date().toISOString().split("T")[0];

  const modal = document.createElement("div");
  modal.className = "modal payment-modal";
  modal.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h2>Make Payment</h2>
        <span class="close" onclick="closePaymentModal(this.closest('.modal'), ${paymentId})" role="button" aria-label="Close payment modal">×</span>
      </div>
      <div class="modal-body">
        <form id="paymentForm">
          <input type="hidden" name="payment_id" value="${paymentId}">
          <input type="hidden" name="payment_date" value="${today}">
          <div class="form-group">
            <label for="paymentAmount">Payment Amount (PHP, max ₱${remainingDue.toLocaleString(
              "en-PH",
              {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              }
            )})</label>
            <input type="number" id="paymentAmount" name="amount_paid" step="0.01" min="0.01" max="${remainingDue}" required placeholder="Enter payment amount" aria-describedby="paymentAmountError">
            <div id="paymentAmountError" class="error-message"></div>
          </div>
          <div class="form-group">
            <label for="paymentType">Payment Type</label>
            <select id="paymentType" name="payment_type" required aria-describedby="paymentTypeError">
              <option value="full">Full Payment (Interest + Principal)</option>
              <option value="interest">Interest Only (max ₱${remainingInterest.toLocaleString(
                "en-PH",
                {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                }
              )})</option>
              <option value="principal">Principal Only (max ₱${remainingPrincipal.toLocaleString(
                "en-PH",
                {
                  minimumFractionDigits: 2,
                  maximumFractionDigits: 2,
                }
              )})</option>
              <option value="custom">Custom Split</option>
            </select>
            <div id="paymentTypeError" class="error-message"></div>
          </div>
          <div class="form-group custom-payment" style="display: none;">
            <label for="interestPaid">Interest Paid (max ₱${remainingInterest.toLocaleString(
              "en-PH",
              {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              }
            )})</label>
            <input type="number" id="interestPaid" name="interest_paid" step="0.01" min="0" max="${remainingInterest}" placeholder="Enter interest amount" aria-describedby="interestPaidError">
            <div id="interestPaidError" class="error-message"></div>
          </div>
          <div class="form-group custom-payment" style="display: none;">
            <label for="principalPaid">Principal Paid (max ₱${remainingPrincipal.toLocaleString(
              "en-PH",
              {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              }
            )})</label>
            <input type="number" id="principalPaid" name="principal_paid" step="0.01" min="0" max="${remainingPrincipal}" placeholder="Enter principal amount" aria-describedby="principalPaidError">
            <div id="principalPaidError" class="error-message"></div>
          </div>
          <div class="form-group">
            <label for="invoiceNumber">Invoice Number (optional)</label>
            <input type="text" id="invoiceNumber" name="invoice_number" maxlength="50" placeholder="e.g., INV-2025-123" aria-describedby="invoiceNumberError">
            <div id="invoiceNumberError" class="error-message"></div>
          </div>
          <button type="submit" class="action-btn submit-btn" id="submitPaymentBtn"><span>Submit Payment</span><span class="spinner"></span></button>
        </form>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.classList.add("show");
  modal.style.display = "block";

  const paymentTypeSelect = modal.querySelector("#paymentType");
  const customFields = modal.querySelectorAll(".custom-payment");
  const paymentAmountInput = modal.querySelector("#paymentAmount");
  const interestPaidInput = modal.querySelector("#interestPaid");
  const principalPaidInput = modal.querySelector("#principalPaid");
  const invoiceNumberInput = modal.querySelector("#invoiceNumber");

  paymentTypeSelect.addEventListener("change", function () {
    const isCustom = this.value === "custom";
    customFields.forEach(
      (field) => (field.style.display = isCustom ? "block" : "none")
    );
    if (!isCustom) {
      interestPaidInput.value = "";
      principalPaidInput.value = "";
      modal.querySelector("#interestPaidError").style.display = "none";
      modal.querySelector("#principalPaidError").style.display = "none";
    }
    validatePaymentForm();
  });

  // Real-time validation for payment amount
  paymentAmountInput.addEventListener("input", function () {
    const amount = parseFloat(this.value) || 0;
    if (amount > 0 && amount <= remainingDue) {
      this.classList.add("valid");
      this.classList.remove("invalid");
    } else if (amount > 0) {
      this.classList.add("invalid");
      this.classList.remove("valid");
    } else {
      this.classList.remove("valid", "invalid");
    }
    validatePaymentForm();
  });

  paymentAmountInput.addEventListener("blur", function () {
    validatePaymentForm();
  });

  // Real-time validation for custom fields
  interestPaidInput.addEventListener("input", function () {
    const amount = parseFloat(this.value) || 0;
    if (amount >= 0 && amount <= remainingInterest) {
      this.classList.add("valid");
      this.classList.remove("invalid");
    } else if (amount > 0) {
      this.classList.add("invalid");
      this.classList.remove("valid");
    } else {
      this.classList.remove("valid", "invalid");
    }
    validatePaymentForm();
  });

  principalPaidInput.addEventListener("input", function () {
    const amount = parseFloat(this.value) || 0;
    if (amount >= 0 && amount <= remainingPrincipal) {
      this.classList.add("valid");
      this.classList.remove("invalid");
    } else if (amount > 0) {
      this.classList.add("invalid");
      this.classList.remove("valid");
    } else {
      this.classList.remove("valid", "invalid");
    }
    validatePaymentForm();
  });

  // Real-time validation for invoice number
  invoiceNumberInput.addEventListener("input", function () {
    const value = this.value.trim();
    if (value.length === 0 || /^[A-Z0-9\-]{3,50}$/.test(value)) {
      this.classList.remove("invalid");
      this.classList.add("valid");
      modal.querySelector("#invoiceNumberError").style.display = "none";
    } else {
      this.classList.add("invalid");
      this.classList.remove("valid");
    }
  });

  function validatePaymentForm() {
    const paymentAmount = parseFloat(paymentAmountInput.value) || 0;
    const paymentType = paymentTypeSelect.value;
    const interestPaid = parseFloat(interestPaidInput.value) || 0;
    const principalPaid = parseFloat(principalPaidInput.value) || 0;
    const invoiceNumber = invoiceNumberInput.value.trim();

    modal.querySelector("#paymentAmountError").style.display = "none";
    modal.querySelector("#paymentTypeError").style.display = "none";
    modal.querySelector("#interestPaidError").style.display = "none";
    modal.querySelector("#principalPaidError").style.display = "none";
    modal.querySelector("#invoiceNumberError").style.display = "none";

    let valid = true;

    if (
      isNaN(paymentAmount) ||
      paymentAmount <= 0 ||
      paymentAmount > remainingDue
    ) {
      modal.querySelector(
        "#paymentAmountError"
      ).textContent = `⚠️ Enter amount: ₱0.01 to ₱${remainingDue.toLocaleString(
        "en-PH",
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )}`;
      modal.querySelector("#paymentAmountError").classList.add("show");
      modal.querySelector("#paymentAmountError").style.display = "block";
      paymentAmountInput.classList.add("invalid");
      valid = false;
    } else {
      paymentAmountInput.classList.add("valid");
      paymentAmountInput.classList.remove("invalid");
    }

    if (!paymentType) {
      modal.querySelector("#paymentTypeError").textContent =
        "Please select a payment type";
      modal.querySelector("#paymentTypeError").classList.add("show");
      modal.querySelector("#paymentTypeError").style.display = "block";
      valid = false;
    } else if (
      paymentType === "interest" &&
      paymentAmount > remainingInterest
    ) {
      modal.querySelector(
        "#paymentAmountError"
      ).textContent = `Interest-only payment cannot exceed remaining interest (₱${remainingInterest.toLocaleString(
        "en-PH",
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )})`;
      modal.querySelector("#paymentAmountError").classList.add("show");
      modal.querySelector("#paymentAmountError").style.display = "block";
      valid = false;
    } else if (
      paymentType === "principal" &&
      paymentAmount > remainingPrincipal
    ) {
      modal.querySelector(
        "#paymentAmountError"
      ).textContent = `Principal-only payment cannot exceed remaining principal (₱${remainingPrincipal.toLocaleString(
        "en-PH",
        {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }
      )})`;
      modal.querySelector("#paymentAmountError").classList.add("show");
      modal.querySelector("#paymentAmountError").style.display = "block";
      valid = false;
    } else if (paymentType === "custom") {
      if (
        isNaN(interestPaid) ||
        interestPaid < 0 ||
        interestPaid > remainingInterest
      ) {
        modal.querySelector(
          "#interestPaidError"
        ).textContent = `Interest paid must be between 0 and ₱${remainingInterest.toLocaleString(
          "en-PH",
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`;
        modal.querySelector("#interestPaidError").classList.add("show");
        modal.querySelector("#interestPaidError").style.display = "block";
        valid = false;
      }
      if (
        isNaN(principalPaid) ||
        principalPaid < 0 ||
        principalPaid > remainingPrincipal
      ) {
        modal.querySelector(
          "#principalPaidError"
        ).textContent = `Principal paid must be between 0 and ₱${remainingPrincipal.toLocaleString(
          "en-PH",
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`;
        modal.querySelector("#principalPaidError").classList.add("show");
        modal.querySelector("#principalPaidError").style.display = "block";
        valid = false;
      }
      if (
        valid &&
        Math.abs(interestPaid + principalPaid - paymentAmount) > 0.01
      ) {
        modal.querySelector(
          "#interestPaidError"
        ).textContent = `Interest and principal must sum to ₱${paymentAmount.toLocaleString(
          "en-PH",
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }
        )}`;
        modal.querySelector("#interestPaidError").classList.add("show");
        modal.querySelector("#interestPaidError").style.display = "block";
        valid = false;
      }
    }

    if (invoiceNumber && !/^[a-zA-Z0-9-_]{0,50}$/.test(invoiceNumber)) {
      modal.querySelector(
        "#invoiceNumberError"
      ).textContent = `Invoice number must be up to 50 alphanumeric characters, hyphens, or underscores.`;
      modal.querySelector("#invoiceNumberError").classList.add("show");
      modal.querySelector("#invoiceNumberError").style.display = "block";
      valid = false;
    }

    return valid;
  }

  paymentAmountInput.addEventListener("input", validatePaymentForm);
  interestPaidInput.addEventListener("input", validatePaymentForm);
  principalPaidInput.addEventListener("input", validatePaymentForm);
  invoiceNumberInput.addEventListener("input", validatePaymentForm);

  modal.querySelector("#paymentForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const submitBtn = modal.querySelector("#submitPaymentBtn");
    if (submitBtn.classList.contains("loading")) return;

    if (!validatePaymentForm()) return;

    submitBtn.disabled = true;
    submitBtn.classList.add("loading");

    const formData = {
      payment_id: parseInt(modal.querySelector("[name=payment_id]").value),
      amount_paid: parseFloat(modal.querySelector("[name=amount_paid]").value),
      payment_date: modal.querySelector("[name=payment_date]").value,
      payment_type: modal.querySelector("[name=payment_type]").value,
      interest_paid:
        parseFloat(modal.querySelector("[name=interest_paid]").value) || 0,
      principal_paid:
        parseFloat(modal.querySelector("[name=principal_paid]").value) || 0,
      invoice_number:
        modal.querySelector("[name=invoice_number]").value.trim() || null,
    };

    fetch("pay_balance.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(formData),
    })
      .then((response) => {
        if (!response.ok)
          throw new Error(`HTTP error! Status: ${response.status}`);
        return response.json();
      })
      .then((data) => {
        if (data && data.success) {
          showToast(data.message, "success");
          modal.remove();
          closeViewLoanModal();
          location.reload();
        } else {
          showToast(
            data && data.message ? data.message : "Unknown error occurred",
            "error"
          );
        }
      })
      .catch((error) => {
        showToast("Error processing payment: " + error.message, "error");
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("loading");
      });
  });
}

function closePaymentModal(modal, paymentId) {
  modal.classList.remove("show");
  modal.style.display = "none";
  setTimeout(() => {
    modal.remove();
  }, 300);
}

function closeViewLoanModal() {
  const modal = document.getElementById("viewLoanModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
    document.getElementById("loanDetailsContent").innerHTML = `
      <div class="loading-overlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading loan details...</div>
      </div>
    `;
  }, 300);
}

document.getElementById("loanAmount").addEventListener("input", function () {
  updateDurationConstraints();
  validateInputs();
});

document.getElementById("duration").addEventListener("input", function () {
  updateFrequencyOptions();
  validateInputs();
});

document.getElementById("frequency").addEventListener("change", function () {
  validateInputs();
});

// ========== ENHANCED PAYMENT FORM FUNCTIONS ==========
function togglePaymentTypeSection() {
  const paymentType = document.getElementById("paymentTypeSelect").value;
  const customSection = document.getElementById("customPaymentSection");

  if (paymentType === "custom") {
    customSection.style.display = "block";
  } else {
    customSection.style.display = "none";
    // Reset custom fields
    document.getElementById("customInterest").value = "";
    document.getElementById("customPrincipal").value = "";
    document.getElementById("customTotalAmount").textContent = "₱0.00";
  }
}

function calculateCustomTotal() {
  const interest =
    parseFloat(document.getElementById("customInterest").value) || 0;
  const principal =
    parseFloat(document.getElementById("customPrincipal").value) || 0;
  const total = interest + principal;

  document.getElementById("customTotalAmount").textContent =
    "₱" +
    total.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
}

function validatePaymentForm() {
  const amount =
    parseFloat(document.getElementById("paymentAmount").value) || 0;
  const paymentType = document.getElementById("paymentTypeSelect").value;
  const errorContainer = document.getElementById("paymentFormErrors");
  const errors = [];

  if (!errorContainer) return true; // Safety check
  errorContainer.innerHTML = "";

  // Get loan balance from the page context
  const loanBalanceText = document.querySelector(
    ".payment-summary-item:nth-child(1) .summary-amount"
  );
  const loanBalance = loanBalanceText
    ? parseFloat(loanBalanceText.textContent.replace(/[₱,]/g, ""))
    : 0;

  // Validation rules
  if (amount <= 0) {
    errors.push("Payment amount must be greater than 0");
  }

  if (amount > loanBalance) {
    errors.push(
      `Payment amount cannot exceed loan balance (₱${loanBalance.toLocaleString(
        "en-PH",
        { minimumFractionDigits: 2, maximumFractionDigits: 2 }
      )})`
    );
  }

  if (!paymentType) {
    errors.push("Please select a payment type");
  }

  if (paymentType === "custom") {
    const customInterest =
      parseFloat(document.getElementById("customInterest").value) || 0;
    const customPrincipal =
      parseFloat(document.getElementById("customPrincipal").value) || 0;
    const customTotal = customInterest + customPrincipal;

    if (Math.abs(customTotal - amount) > 0.01) {
      errors.push(
        `Custom allocation (₱${customTotal.toFixed(
          2
        )}) must equal payment amount (₱${amount.toFixed(2)})`
      );
    }

    if (customInterest < 0 || customPrincipal < 0) {
      errors.push("Interest and principal amounts cannot be negative");
    }
  }

  if (errors.length > 0) {
    errorContainer.innerHTML =
      "<ul>" + errors.map((err) => `<li>${err}</li>`).join("") + "</ul>";
    errorContainer.style.display = "block";
    return false;
  }

  errorContainer.style.display = "none";
  return true;
}

function resetPaymentForm() {
  document.getElementById("createPaymentForm").reset();
  document.getElementById("paymentFormErrors").innerHTML = "";
  document.getElementById("paymentFormErrors").style.display = "none";
  document.getElementById("customPaymentSection").style.display = "none";
  document.getElementById("customTotalAmount").textContent = "₱0.00";
  document.getElementById("charCount").textContent = "0/500";
}

function closePaymentForm() {
  const modal = document.getElementById("paymentModal");
  if (modal) {
    modal.classList.remove("show");
    setTimeout(() => {
      modal.style.display = "none";
    }, 300);
  }
}

// Initialize payment form event listeners when page loads
document.addEventListener("DOMContentLoaded", function () {
  const paymentForm = document.getElementById("createPaymentForm");
  if (!paymentForm) return; // Safety check if form doesn't exist

  // Payment type change listener
  const paymentTypeSelect = document.getElementById("paymentTypeSelect");
  if (paymentTypeSelect) {
    paymentTypeSelect.addEventListener("change", togglePaymentTypeSection);
  }

  // Custom total calculation listeners
  const customInterest = document.getElementById("customInterest");
  const customPrincipal = document.getElementById("customPrincipal");
  if (customInterest) {
    customInterest.addEventListener("input", calculateCustomTotal);
  }
  if (customPrincipal) {
    customPrincipal.addEventListener("input", calculateCustomTotal);
  }

  // Notes character counter
  const notesField = document.getElementById("paymentNotes");
  const charCount = document.getElementById("charCount");
  if (notesField && charCount) {
    notesField.addEventListener("input", function () {
      charCount.textContent = this.value.length + "/500";
    });
  }

  // Real-time validation
  const paymentAmount = document.getElementById("paymentAmount");
  if (paymentAmount) {
    paymentAmount.addEventListener("input", validatePaymentForm);
  }
  if (paymentTypeSelect) {
    paymentTypeSelect.addEventListener("change", validatePaymentForm);
  }

  // Form submission
  paymentForm.addEventListener("submit", function (e) {
    e.preventDefault();

    if (!validatePaymentForm()) {
      showToast("Please fix the errors above", "error");
      return;
    }

    const submitBtn = document.querySelector(".payment-submit-btn");
    if (submitBtn.classList.contains("loading")) return;

    submitBtn.disabled = true;
    submitBtn.classList.add("loading");

    const formData = new FormData(paymentForm);

    // Show spinner
    const spinner = submitBtn.querySelector(".spinner");
    if (spinner) {
      spinner.style.display = "inline-block";
    }

    fetch("create_loan_payment.php", {
      method: "POST",
      body: formData,
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        return res.json();
      })
      .then((data) => {
        if (data && data.success) {
          showToast(
            data.message || "Payment submitted successfully",
            "success"
          );
          closePaymentForm();
          // Refresh the page to show updated data
          setTimeout(() => location.reload(), 1500);
        } else {
          showToast(
            data && data.message ? data.message : "An error occurred",
            "error"
          );
        }
      })
      .catch((error) => {
        showToast("Error submitting payment: " + error.message, "error");
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.classList.remove("loading");
        if (spinner) {
          spinner.style.display = "none";
        }
      });
  });

  // Close button
  const closeBtn = document.querySelector(".payment-close-btn");
  if (closeBtn) {
    closeBtn.addEventListener("click", closePaymentForm);
  }

  // Reset button
  const resetBtn = document.querySelector(".payment-reset-btn");
  if (resetBtn) {
    resetBtn.addEventListener("click", function (e) {
      e.preventDefault();
      resetPaymentForm();
    });
  }

  // Cancel button
  const cancelBtn = document.querySelector(".payment-cancel-btn");
  if (cancelBtn) {
    cancelBtn.addEventListener("click", function (e) {
      e.preventDefault();
      closePaymentForm();
    });
  }
});

// ========== LOAN CALCULATOR FUNCTIONS ==========
function openCalculatorModal() {
  document.getElementById("loanCalculatorModal").style.display = "flex";
  document.getElementById("loanCalculatorModal").classList.add("show");

  // Reset form and result
  document.getElementById("calculatorForm").reset();
  document.getElementById("result").innerHTML = "";

  // Reset repayment options
  updateRepaymentOptions();

  fetch("active_records.php?action=get_current_interest_rate")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("interestRate").value = data.interest_rate;
        document.getElementById(
          "interestRateDisplay"
        ).textContent = `${data.interest_rate}%`;
      } else {
        document.getElementById("interestRateDisplay").textContent =
          "Error loading rate";
      }
    });
}

function closeCalculatorModal() {
  document.getElementById("loanCalculatorModal").style.display = "none";
  document.getElementById("loanCalculatorModal").classList.remove("show");
}

function updateLoanAmountRange() {
  const loanType = document.getElementById("loanType").value;
  const loanAmount = document.getElementById("loanAmount");
  const loanAmountLabel = document.getElementById("loanAmountLabel");
  if (loanType === "Individual") {
    loanAmount.min = 10000;
    loanAmount.max = 100000;
    loanAmountLabel.textContent = "Loan Amount (₱10,000 - ₱100,000)";
  } else if (loanType === "Cooperative") {
    loanAmount.min = 50000;
    loanAmount.max = 500000;
    loanAmountLabel.textContent = "Loan Amount (₱50,000 - ₱500,000)";
  }
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
  const loanAmount = parseFloat(document.getElementById("loanAmount").value);
  const interestRate =
    parseFloat(document.getElementById("interestRate").value) / 100;
  const termLength = parseInt(document.getElementById("termLength").value);
  const repaymentFrequency =
    document.getElementById("repaymentFrequency").value;

  // Validation for 6-month term
  if (termLength === 6 && repaymentFrequency !== "Monthly") {
    document.getElementById("result").innerHTML = `
            <p class="error"><i class="fas fa-exclamation-circle"></i> For 6-month term, only Monthly repayment is allowed.</p>
        `;
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

  document.getElementById("result").innerHTML = `
        <p><strong>${repaymentFrequency} Payment:</strong> ₱${monthlyPayment.toFixed(
    2
  )}</p>
        <p><strong>Total Interest:</strong> ₱${totalInterest.toFixed(2)}</p>
        <p><strong>Total Repayment:</strong> ₱${(
          loanAmount + totalInterest
        ).toFixed(2)}</p>
    `;
}

function generateAmortizationSchedule(
  principal,
  rate,
  totalPayments,
  paymentAmount,
  frequency
) {
  let balance = principal;
  let tbody = document.getElementById("amortizationBody");
  tbody.innerHTML = "";

  for (let i = 1; i <= totalPayments; i++) {
    const interestPayment = balance * rate;
    const principalPayment = paymentAmount - interestPayment;
    balance -= principalPayment;

    if (balance < 0) balance = 0;

    const row = tbody.insertRow();
    row.innerHTML = `
      <td>${i}</td>
      <td>₱${paymentAmount.toFixed(2)}</td>
      <td>₱${principalPayment.toFixed(2)}</td>
      <td>₱${interestPayment.toFixed(2)}</td>
      <td>₱${Math.max(0, balance).toFixed(2)}</td>
    `;
  }

  // Show amortization container
  document.getElementById("amortizationContainer").style.display = "block";
}

// Real-time validation for calculator fields
function validateCalculatorFields() {
  const loanAmount =
    parseFloat(document.getElementById("loanAmount").value) || 0;
  const termLength = parseInt(document.getElementById("termLength").value) || 0;
  const repaymentFrequency =
    document.getElementById("repaymentFrequency").value;
  const errorMessage = document.getElementById("errorMessage");
  const errorText = document.getElementById("errorText");
  const loanType = document.getElementById("loanType").value;

  // Determine loan amount limits based on type
  let minAmount, maxAmount;
  if (loanType === "Individual") {
    minAmount = 10000;
    maxAmount = 100000;
  } else if (loanType === "Cooperative") {
    minAmount = 50000;
    maxAmount = 500000;
  } else {
    minAmount = 0;
    maxAmount = Infinity;
  }

  // Clear previous errors
  errorMessage.style.display = "none";
  const loanAmountInput = document.getElementById("loanAmount");
  const termLengthSelect = document.getElementById("termLength");
  const repaymentFrequencySelect =
    document.getElementById("repaymentFrequency");

  loanAmountInput.classList.remove("valid", "invalid");
  termLengthSelect.classList.remove("valid", "invalid");
  repaymentFrequencySelect.classList.remove("valid", "invalid");

  // Validate loan amount
  if (loanAmount && (loanAmount < minAmount || loanAmount > maxAmount)) {
    errorText.textContent = `Loan amount must be between ₱${minAmount.toLocaleString()} and ₱${maxAmount.toLocaleString()}`;
    errorMessage.style.display = "flex";
    loanAmountInput.classList.add("invalid");
    return false;
  } else if (loanAmount > 0) {
    loanAmountInput.classList.add("valid");
  }

  // Validate term length
  if (termLength > 0) {
    termLengthSelect.classList.add("valid");
  } else if (
    termLength === 0 &&
    document.getElementById("termLength").value !== ""
  ) {
    termLengthSelect.classList.add("invalid");
    errorText.textContent = "Please select a valid term length";
    errorMessage.style.display = "flex";
    return false;
  }

  // Validate repayment frequency
  if (repaymentFrequency) {
    // Check if frequency is valid for 6-month term
    if (termLength === 6 && repaymentFrequency !== "Monthly") {
      errorText.textContent =
        "For 6-month term, only Monthly repayment is allowed";
      errorMessage.style.display = "flex";
      repaymentFrequencySelect.classList.add("invalid");
      return false;
    }
    repaymentFrequencySelect.classList.add("valid");
  } else if (document.getElementById("repaymentFrequency").value !== "") {
    repaymentFrequencySelect.classList.add("invalid");
    errorText.textContent = "Please select a repayment frequency";
    errorMessage.style.display = "flex";
    return false;
  }

  return true;
}

// Initialize calculator form submission and close handler
document.addEventListener("DOMContentLoaded", function () {
  const calculatorForm = document.getElementById("calculatorForm");
  if (calculatorForm) {
    calculatorForm.addEventListener("submit", function (e) {
      e.preventDefault();
      calculateLoan();
    });

    // Add real-time validation listeners
    const loanTypeSelect = document.getElementById("loanType");
    const loanAmountInput = document.getElementById("loanAmount");
    const termLengthSelect = document.getElementById("termLength");
    const repaymentFrequencySelect =
      document.getElementById("repaymentFrequency");

    if (loanTypeSelect) {
      loanTypeSelect.addEventListener("change", function () {
        updateLoanAmountRange();
        validateCalculatorFields();
      });
    }

    if (loanAmountInput) {
      loanAmountInput.addEventListener("input", validateCalculatorFields);
      loanAmountInput.addEventListener("blur", validateCalculatorFields);
    }

    if (termLengthSelect) {
      termLengthSelect.addEventListener("change", function () {
        updateRepaymentOptions();
        validateCalculatorFields();
      });
    }

    if (repaymentFrequencySelect) {
      repaymentFrequencySelect.addEventListener(
        "change",
        validateCalculatorFields
      );
      repaymentFrequencySelect.addEventListener(
        "blur",
        validateCalculatorFields
      );
    }
  }

  // Close calculator modal when clicking close button
  const closeBtn = document.querySelector("#loanCalculatorModal .close");
  if (closeBtn) {
    closeBtn.addEventListener("click", closeCalculatorModal);
  }

  // Close calculator modal when clicking outside
  const modal = document.getElementById("loanCalculatorModal");
  if (modal) {
    window.addEventListener("click", function (e) {
      if (e.target === modal) {
        closeCalculatorModal();
      }
    });
  }
});
