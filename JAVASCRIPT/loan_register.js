const MIN_AMOUNT_INDIVIDUAL = 10000;
const MAX_AMOUNT_INDIVIDUAL = 100000;
const MIN_AMOUNT_COOPERATIVE = 300000;
const MAX_AMOUNT_COOPERATIVE = 1000000;

// Function to add commas to a number for display
function addCommas(value) {
  if (!value) return "";
  const cleanValue = value.toString().replace(/[^0-9]/g, "");
  if (!cleanValue) return "";
  return parseInt(cleanValue).toLocaleString("en-US", {
    minimumFractionDigits: 0,
  });
}

// Function to remove commas and convert to number
function removeCommas(value) {
  return parseInt(value.replace(/,/g, ""), 10) || 0;
}

function validateLoanType() {
  const loanType = document.getElementById("loanType");
  const validTypes = ["Individual", "Cooperative"];
  const value = loanType.value.trim();
  if (!validTypes.includes(value)) {
    validateField(loanType, "Please select either Individual or Cooperative.");
    return false;
  }
  updateAmountConstraints();
  updateTermLengthOptions();
  return validateField(loanType, "", (v) => true);
}

function updateAmountConstraints() {
  const loanType = document.getElementById("loanType").value;
  const amountApplied = document.getElementById("amountApplied");
  if (loanType === "Individual") {
    amountApplied.min = MIN_AMOUNT_INDIVIDUAL;
    amountApplied.max = MAX_AMOUNT_INDIVIDUAL;
  } else if (loanType === "Cooperative") {
    amountApplied.min = MIN_AMOUNT_COOPERATIVE;
    amountApplied.max = MAX_AMOUNT_COOPERATIVE;
  } else {
    amountApplied.removeAttribute("min");
    amountApplied.removeAttribute("max");
  }
  validateAmountApplied();
}

document.getElementById("loanType").addEventListener("input", (e) => {
  const validTypes = ["Individual", "Cooperative"];
  if (!validTypes.includes(e.target.value)) {
    e.target.value = "";
  }
  validateLoanType();
});

function validateAmountApplied() {
  const amountApplied = document.getElementById("amountApplied");
  const loanType = document.getElementById("loanType").value;
  const value = removeCommas(amountApplied.value);
  let minAmount, maxAmount, errorMessage;

  if (loanType === "Individual") {
    minAmount = MIN_AMOUNT_INDIVIDUAL;
    maxAmount = MAX_AMOUNT_INDIVIDUAL;
    errorMessage = `Amount must be between ${minAmount.toLocaleString()} and ${maxAmount.toLocaleString()} PHP for Individual loans.`;
  } else if (loanType === "Cooperative") {
    minAmount = MIN_AMOUNT_COOPERATIVE;
    maxAmount = MAX_AMOUNT_COOPERATIVE;
    errorMessage = `Amount must be between ${minAmount.toLocaleString()} and ${maxAmount.toLocaleString()} PHP for Cooperative loans.`;
  } else {
    errorMessage = "Please select a loan type first.";
    return validateField(amountApplied, errorMessage, (v) => false);
  }

  if (isNaN(value) || value < minAmount || value > maxAmount) {
    return validateField(amountApplied, errorMessage, (v) => false);
  }
  if (!Number.isInteger(value)) {
    return validateField(
      amountApplied,
      "Amount must be a whole number.",
      (v) => false
    );
  }
  return validateField(amountApplied, "", (v) => true);
}

function validateProjectDescription() {
  const projectDescription = document.getElementById("projectDescription");
  const value = projectDescription.value.trim();
  const minLength = 20;
  const maxLength = 500;
  if (value.length < minLength) {
    return validateField(
      projectDescription,
      `Description must be at least ${minLength} characters.`,
      (v) => false
    );
  }
  if (value.length > maxLength) {
    return validateField(
      projectDescription,
      `Description cannot exceed ${maxLength} characters.`,
      (v) => false
    );
  }
  const sanitizedValue = value.replace(/[<>]/g, "");
  projectDescription.value = sanitizedValue;
  return validateField(projectDescription, "", (v) => true);
}

function validateOthersText() {
  const othersText = document.getElementById("othersText");
  const value = othersText.value.trim();
  const minLength = 5;
  const maxLength = 100;
  if (document.getElementById("others").checked && value.length < minLength) {
    return validateField(
      othersText,
      `Please provide at least ${minLength} characters.`,
      (v) => false
    );
  }
  if (value.length > maxLength) {
    return validateField(
      othersText,
      `Input cannot exceed ${maxLength} characters.`,
      (v) => false
    );
  }
  othersText.value = value.replace(/[<>]/g, "");
  return validateField(othersText, "", (v) => true);
}

function validateTermLength() {
  const termLength = document.getElementById("termLength");
  const loanType = document.getElementById("loanType").value;
  const validTermsIndividual = ["6", "12", "18"];
  const validTermsCooperative = ["6", "12", "18", "24", "36"];
  const value = termLength.value;
  let validTerms =
    loanType === "Cooperative" ? validTermsCooperative : validTermsIndividual;
  let errorMessage =
    loanType === "Cooperative"
      ? "Please select a valid term length (6, 12, 18, 24, or 36 months)."
      : "Please select a valid term length (6, 12, or 18 months).";

  if (!validTerms.includes(value)) {
    return validateField(termLength, errorMessage, (v) => false);
  }
  return validateField(termLength, "", (v) => true);
}

function validateRepaymentFrequency() {
  const repaymentFrequency = document.getElementById("repaymentFrequency");
  const validFrequencies = [
    "Monthly",
    "Quarterly",
    "Semi-Annually",
    "Annually",
  ];
  const value = repaymentFrequency.value;
  if (!validFrequencies.includes(value)) {
    return validateField(
      repaymentFrequency,
      "Please select a valid repayment frequency.",
      (v) => false
    );
  }
  return validateField(repaymentFrequency, "", (v) => true);
}

function validateFileInputs() {
  const fileInputs = document.querySelectorAll(
    '.requirements-content.expanded input[type="file"]:not([disabled])'
  );
  const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];
  const maxSize = 5 * 1024 * 1024;
  let isValid = true;

  fileInputs.forEach((input) => {
    if (input.files.length > 0) {
      const file = input.files[0];
      const errorElement = input
        .closest(".form-group")
        .querySelector(".error-message");
      if (!allowedTypes.includes(file.type)) {
        errorElement.textContent = "Only JPEG, PNG, or PDF files are allowed.";
        input.classList.add("error");
        isValid = false;
      } else if (file.size > maxSize) {
        errorElement.textContent = "File size must not exceed 5MB.";
        input.classList.add("error");
        isValid = false;
      } else {
        errorElement.textContent = "";
        input.classList.remove("error");
      }
    }
  });

  return isValid;
}

function showErrorModal(errors) {
  const modal = document.getElementById("messageModal");
  const modalMessage = document.getElementById("modalMessage");
  modalMessage.innerHTML = `Please address the following:<br><ul class="no-bullets">${errors
    .map((e) => `<li>${e}</li>`)
    .join("")}</ul>`;
  modal.classList.add("error");
  modal.style.display = "block";
}

let currentStep = 1;
const totalSteps = 4;

function updateProgress() {
  const progress = document.querySelector(".progress");
  const steps = document.querySelectorAll(".step");
  progress.style.width = `${((currentStep - 1) / (totalSteps - 1)) * 100}%`;
  steps.forEach((step, index) => {
    step.classList.remove("active", "completed");
    if (index + 1 < currentStep) {
      step.classList.add("completed");
    } else if (index + 1 === currentStep) {
      step.classList.add("active");
    }
  });
}

function nextStep(step) {
  if (step === 1 && validateStep1()) {
    transitionStep(1, 2);
  } else if (step === 2 && validateStep2()) {
    transitionStep(2, 3);
    toggleRequirements();
  } else if (step === 3 && validateStep3()) {
    fetchInterestRateAndShowOverview();
  }
}

function prevStep(step) {
  if (step === 2) {
    transitionStep(2, 1);
  } else if (step === 3) {
    transitionStep(3, 2);
    hideAllRequirements();
  } else if (step === 4) {
    transitionStep(4, 3);
  }
}

function transitionStep(from, to) {
  document.getElementById(`step${from}`).classList.remove("active");
  document.getElementById(`step${to}`).classList.add("active");
  currentStep = to;
  updateProgress();
}

function validateStep1() {
  const errors = [];
  if (!validateLoanType()) errors.push("Invalid or missing loan type.");

  const loanStatus = document.querySelector('input[name="loanStatus"]:checked');
  if (!loanStatus) {
    errors.push("Please select a loan status.");
  } else {
    if (loanStatus.disabled) {
      errors.push("Selected loan status is not allowed.");
    }
  }

  if (!validateAmountApplied())
    errors.push("Invalid or missing amount applied.");

  if (errors.length > 0) {
    showErrorModal(errors);
    return false;
  }
  return true;
}

function validateStep2() {
  const errors = [];
  if (!validateTermLength()) errors.push("Invalid or missing term length.");
  if (!validateRepaymentFrequency())
    errors.push("Invalid or missing repayment frequency.");
  if (!document.querySelector('input[name="purpose"]:checked'))
    errors.push("Please select a purpose.");
  if (document.getElementById("others").checked && !validateOthersText())
    errors.push("Invalid or missing other purpose.");
  if (!document.querySelector('input[name="projectType"]:checked'))
    errors.push("Please select a project type.");
  if (!validateProjectDescription())
    errors.push("Invalid or missing project description.");

  if (errors.length > 0) {
    showErrorModal(errors);
    return false;
  }
  return true;
}

function validateStep3() {
  const loanType = document.getElementById("loanType").value;
  const projectType = document.querySelector(
    'input[name="projectType"]:checked'
  )?.value;
  const errors = [];
  let isValid = true;

  if (loanType === "Individual") {
    const individualFiles = [
      { id: "2x2pic", label: "2X2 Picture" },
      { id: "votersCertificate", label: "Voter's Certificate" },
      { id: "residenceCertificate", label: "Residence Certificate" },
      { id: "barangayClearance", label: "Barangay Clearance" },
      { id: "businessPermit", label: "Business Permit (DTI, BBC, CBP)" },
    ];
    individualFiles.forEach((file) => {
      const input = document.getElementById(file.id);
      if (input && input.files.length === 0 && !input.disabled) {
        errors.push(`Missing ${file.label}.`);
        const card = input.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = `Please upload ${file.label}.`;
          }
          input.classList.add("error");
        }
        isValid = false;
      } else if (input) {
        const card = input.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = "";
          }
          input.classList.remove("error");
        }
      }
    });

    if (projectType === "Agricultural-based") {
      const farmPlanInput = document.getElementById("farmPlanBudget");
      if (
        farmPlanInput &&
        farmPlanInput.files.length === 0 &&
        !farmPlanInput.disabled
      ) {
        errors.push("Missing Farm Plan and Budget.");
        const card = farmPlanInput.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = "Please upload Farm Plan and Budget.";
          }
          farmPlanInput.classList.add("error");
        }
        isValid = false;
      } else if (farmPlanInput) {
        const card = farmPlanInput.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = "";
          }
          farmPlanInput.classList.remove("error");
        }
      }
    }
  } else if (loanType === "Cooperative") {
    const cooperativeFiles = [
      {
        id: "loanProjectProposal",
        label: "Application thru Loan Project Proposal",
      },
      { id: "auditedFS", label: "Audited Financial Statement" },
      { id: "bankStatement", label: "Bank Statement" },
      { id: "BIR", label: "BIR" },
    ];
    cooperativeFiles.forEach((file) => {
      const input = document.getElementById(file.id);
      if (input && input.files.length === 0 && !input.disabled) {
        errors.push(`Missing ${file.label}.`);
        const card = input.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = `Please upload ${file.label}.`;
          }
          input.classList.add("error");
        }
        isValid = false;
      } else if (input) {
        const card = input.closest(".requirement-card");
        if (card) {
          const errorElement = card.querySelector(".error-message");
          if (errorElement) {
            errorElement.textContent = "";
          }
          input.classList.remove("error");
        }
      }
    });
  }

  isValid &= validateFileInputs();

  if (!isValid) {
    showErrorModal(errors);
  }

  return isValid;
}

function fetchInterestRateAndShowOverview() {
  const termLength = document.getElementById("termLength").value;
  fetch(`get_interest_rate.php?term_length=${termLength}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error("Network response was not ok");
      }
      return response.json();
    })
    .then((data) => {
      if (data.error) {
        showErrorModal([data.error]);
        return;
      }
      populateOverview(data.interest_rate);
      transitionStep(3, 4);
    })
    .catch((error) => {
      console.error("Error fetching interest rate:", error);
      showErrorModal(["Unable to load interest rate. Please try again later."]);
    });
}

function populateOverview(interestRate) {
  const loanType = document.getElementById("loanType").value;
  const loanStatus = document.querySelector(
    'input[name="loanStatus"]:checked'
  ).value;
  const amountApplied = removeCommas(
    document.getElementById("amountApplied").value
  );
  const termLength = document.getElementById("termLength").value;
  const repaymentFrequency =
    document.getElementById("repaymentFrequency").value;
  const purpose = document.querySelector('input[name="purpose"]:checked').value;
  const othersText = document.getElementById("othersText").value;
  const projectType = document.querySelector(
    'input[name="projectType"]:checked'
  ).value;
  const projectDescription =
    document.getElementById("projectDescription").value;

  // Calculate total payable amount using compound interest (same as calculator)
  const annualRate = interestRate / 100;

  // Determine payments per year based on repayment frequency
  let paymentsPerYear;
  switch (repaymentFrequency) {
    case "Monthly":
      paymentsPerYear = 12;
      break;
    case "Quarterly":
      paymentsPerYear = 4;
      break;
    case "Semi-Annually":
      paymentsPerYear = 2;
      break;
    case "Annually":
      paymentsPerYear = 1;
      break;
    default:
      paymentsPerYear = 12;
  }

  const totalPayments = (parseInt(termLength) * paymentsPerYear) / 12;
  const periodRate = annualRate / paymentsPerYear;

  // Calculate payment amount using amortization formula
  const paymentAmount =
    (amountApplied * (periodRate * Math.pow(1 + periodRate, totalPayments))) /
    (Math.pow(1 + periodRate, totalPayments) - 1);

  // Calculate total interest and total payable
  const totalInterest = paymentAmount * totalPayments - amountApplied;
  const totalPayable = amountApplied + totalInterest;

  // Generate preview IDs (actual IDs will be generated on server)
  const today = new Date();
  const dateStr =
    today.getFullYear() +
    String(today.getMonth() + 1).padStart(2, "0") +
    String(today.getDate()).padStart(2, "0");
  const previewAppId = `APP-${dateStr}-XXXX`;
  const previewLoanId = `LOAN-${dateStr}-XXXX`;

  // Populate overview
  document.getElementById("overviewApplicationId").textContent =
    previewAppId + " (Preview)";
  document.getElementById("overviewLoanId").textContent =
    previewLoanId + " (Preview)";
  document.getElementById("overviewLoanType").textContent = loanType;
  document.getElementById("overviewLoanStatus").textContent =
    loanStatus.charAt(0).toUpperCase() + loanStatus.slice(1);
  document.getElementById("overviewPrincipal").textContent =
    amountApplied.toLocaleString("en-US");
  document.getElementById("overviewInterestRate").textContent =
    interestRate.toFixed(2) + "%";
  document.getElementById("overviewTotalPayable").textContent =
    totalPayable.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  document.getElementById("overviewTermLength").textContent =
    termLength + " Months";
  document.getElementById("overviewRepaymentFrequency").textContent =
    repaymentFrequency;
  document.getElementById("overviewPurpose").textContent =
    purpose === "Others" ? othersText : purpose;
  document.getElementById("overviewProjectType").textContent = projectType;
  document.getElementById("overviewProjectDescription").textContent =
    projectDescription;

  // Populate uploaded documents
  const documentsList = document.getElementById("overviewDocuments");
  documentsList.innerHTML = "";

  // Map of document types to Font Awesome icons
  const documentIcons = {
    "2x2pic": "fa-image",
    votersCertificate: "fa-id-card",
    residenceCertificate: "fa-home",
    barangayClearance: "fa-certificate",
    businessPermit: "fa-briefcase",
    farmPlanBudget: "fa-leaf",
    loanProjectProposal: "fa-file-contract",
    auditedFinancial: "fa-calculator",
    bankStatement: "fa-bank",
    birRegistration: "fa-receipt",
  };

  // Get all file inputs from visible requirement cards
  const visibleSections = document.querySelectorAll(
    '.requirements-section:not([style*="display: none"])'
  );
  visibleSections.forEach((section) => {
    const fileInputs = section.querySelectorAll(
      'input[type="file"]:not([disabled])'
    );
    fileInputs.forEach((input) => {
      if (input.files.length > 0) {
        const fileName = input.files[0].name;
        const card = input.closest(".requirement-card");
        const label = card
          ? card.querySelector(".doc-title").textContent
          : input.id;

        // Get the appropriate icon for this document type
        const icon = documentIcons[input.id] || "fa-file-alt";
        const iconClass = `fas ${icon}`;

        documentsList.innerHTML += `<li><i class="${iconClass}"></i><strong>${label}:</strong> ${fileName}</li>`;
      }
    });
  });

  // If no documents found, show a message
  if (documentsList.innerHTML === "") {
    documentsList.innerHTML =
      '<li style="border-left-color: #ff9800;"><i class="fas fa-info-circle"></i>No documents uploaded</li>';
  }
}

function validateField(element, errorMessage, condition = (v) => !!v) {
  const value = element.type === "radio" ? element : element.value;
  const errorElement = element
    .closest(".form-group, .radio-group")
    ?.querySelector(".error-message");
  if (!condition(value)) {
    if (errorElement) errorElement.textContent = errorMessage;
    element.classList.add("error");
    return false;
  } else {
    if (errorElement) errorElement.textContent = "";
    element.classList.remove("error");
    return true;
  }
}

function updateTermLengthOptions() {
  const loanType = document.getElementById("loanType").value;
  const termLengthSelect = document.getElementById("termLength");
  const previousValue = termLengthSelect.value;
  termLengthSelect.innerHTML =
    '<option value="" disabled>Select Term Length</option>';

  const termOptions =
    loanType === "Cooperative"
      ? ["6", "12", "18", "24", "36"]
      : ["6", "12", "18"];

  termOptions.forEach((option) => {
    termLengthSelect.innerHTML += `<option value="${option}">${option} Months</option>`;
  });

  if (previousValue && termOptions.includes(previousValue)) {
    termLengthSelect.value = previousValue;
  } else {
    termLengthSelect.value = "";
  }

  validateTermLength();
  updateRepaymentFrequencyOptions();
}

function updateRepaymentFrequencyOptions() {
  const termLength = document.getElementById("termLength").value;
  const repaymentFrequencySelect =
    document.getElementById("repaymentFrequency");
  const previousValue = repaymentFrequencySelect.value;
  repaymentFrequencySelect.innerHTML =
    '<option value="" disabled selected>Select Repayment Frequency</option>';

  if (termLength === "6") {
    repaymentFrequencySelect.innerHTML += `
      <option value="Monthly">Monthly</option>
    `;
  } else if (["12", "18", "24", "36"].includes(termLength)) {
    repaymentFrequencySelect.innerHTML += `
      <option value="Monthly">Monthly</option>
      <option value="Semi-Annually">Semi-Annually</option>
      <option value="Quarterly">Quarterly</option>
      <option value="Annually">Annually</option>
    `;
  }

  if (
    previousValue &&
    repaymentFrequencySelect.querySelector(`option[value="${previousValue}"]`)
  ) {
    repaymentFrequencySelect.value = previousValue;
  }
}

function hideAllRequirements() {
  // Hide all requirement cards
  const allCards = document.querySelectorAll(".requirement-card");
  allCards.forEach((card) => {
    card.classList.add("hidden");
    card.classList.remove("visible");
  });

  // Disable all file inputs
  document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.disabled = true;
  });
}

function toggleRequirements() {
  const loanType = document.getElementById("loanType").value;
  const projectType = document.querySelector(
    'input[name="projectType"]:checked'
  )?.value;

  // Hide all sections first
  const individualSection = document.getElementById("individualSection");
  const agriculturalSection = document.getElementById("agriculturalSection");
  const cooperativeSection = document.getElementById("cooperativeSection");

  if (individualSection) individualSection.style.display = "none";
  if (agriculturalSection) agriculturalSection.style.display = "none";
  if (cooperativeSection) cooperativeSection.style.display = "none";

  // Show appropriate sections based on loan type
  if (loanType === "Individual") {
    if (individualSection) {
      individualSection.style.display = "block";
      const individualCards =
        individualSection.querySelectorAll(".requirement-card");
      individualCards.forEach((card) => {
        card.classList.remove("hidden");
        setTimeout(() => card.classList.add("visible"), 10);
      });
      individualSection
        .querySelectorAll('input[type="file"]')
        .forEach((input) => {
          input.disabled = false;
        });
    }

    if (projectType === "Agricultural-based" && agriculturalSection) {
      agriculturalSection.style.display = "block";
      const agriculturalCards =
        agriculturalSection.querySelectorAll(".requirement-card");
      agriculturalCards.forEach((card) => {
        card.classList.remove("hidden");
        setTimeout(() => card.classList.add("visible"), 10);
      });
      agriculturalSection
        .querySelectorAll('input[type="file"]')
        .forEach((input) => {
          input.disabled = false;
        });
    }
  } else if (loanType === "Cooperative") {
    if (cooperativeSection) {
      cooperativeSection.style.display = "block";
      const cooperativeCards =
        cooperativeSection.querySelectorAll(".requirement-card");
      cooperativeCards.forEach((card) => {
        card.classList.remove("hidden");
        setTimeout(() => card.classList.add("visible"), 10);
      });
      cooperativeSection
        .querySelectorAll('input[type="file"]')
        .forEach((input) => {
          input.disabled = false;
        });
    }
  }
}

// Deprecated: toggleRequirementsSection - No longer used with new card-based structure
// function toggleRequirementsSection(sectionId, forceOpen = false) {
//   const content = document.getElementById(`${sectionId}Requirements`);
//   const toggleIcon =
//     content.previousElementSibling.querySelector(".toggle-icon");
//   if (forceOpen || !content.classList.contains("expanded")) {
//     content.classList.add("expanded");
//     content.style.maxHeight = content.scrollHeight + "px";
//     toggleIcon.textContent = "▲";
//   } else {
//     content.classList.remove("expanded");
//     content.style.maxHeight = "0";
//     toggleIcon.textContent = "▼";
//   }
// }

function toggleOthersField() {
  const othersRadio = document.getElementById("others");
  const othersField = document.getElementById("othersField");
  othersField.style.display = othersRadio.checked ? "block" : "none";
  if (!othersRadio.checked) {
    document.getElementById("othersText").value = "";
    document.getElementById("othersText").classList.remove("error");
    const errorElement = othersField.querySelector(".error-message");
    if (errorElement) errorElement.textContent = "";
  }
}

function showModal(message, isError = false) {
  const modal = document.getElementById("messageModal");
  const modalMessage = document.getElementById("modalMessage");
  modalMessage.textContent = message;
  modal.classList.toggle("error", isError);
  modal.style.display = "block";
}

function closeModal() {
  document.getElementById("messageModal").style.display = "none";
  document.getElementById("messageModal").classList.remove("error");
}

document.getElementById("loanType").addEventListener("input", () => {
  validateField(
    document.getElementById("loanType"),
    "Please select a valid loan type.",
    (v) => ["Individual", "Cooperative"].includes(v)
  );
  if (currentStep === 3) toggleRequirements();
});

document.querySelectorAll('input[name="loanStatus"]').forEach((radio) => {
  radio.addEventListener("change", () => {
    validateField(radio, "Please select a loan status.");
  });
});

document.getElementById("amountApplied").addEventListener("input", (e) => {
  const input = e.target;
  const cursorPosition = input.selectionStart;
  const rawValue = input.value.replace(/[^0-9]/g, "");
  const formattedValue = addCommas(rawValue);
  input.value = formattedValue;

  if (rawValue) {
    const commasBefore = (
      input.value.substring(0, cursorPosition).match(/,/g) || []
    ).length;
    const newLength = formattedValue.length;
    const oldLength = input.value.length;
    const digitsBefore = input.value
      .substring(0, cursorPosition)
      .replace(/[^0-9]/g, "").length;
    let newCursorPosition = digitsBefore;
    for (let i = 1; i <= digitsBefore; i++) {
      if (i % 3 === 0 && i < rawValue.length) newCursorPosition++;
    }
    input.setSelectionRange(newCursorPosition, newCursorPosition);
  }

  validateField(
    input,
    "Please enter a valid amount.",
    (v) => !isNaN(removeCommas(v)) && removeCommas(v) >= 0
  );
  validateAmountApplied();
  updateTermLengthOptions();
});

document.getElementById("termLength").addEventListener("change", () => {
  validateTermLength();
  updateRepaymentFrequencyOptions();
});

document.getElementById("repaymentFrequency").addEventListener("change", () => {
  validateRepaymentFrequency();
});

document.querySelectorAll('input[name="purpose"]').forEach((radio) => {
  radio.addEventListener("change", () => {
    validateField(radio, "Please select a purpose.");
    toggleOthersField();
  });
});

document.getElementById("othersText").addEventListener("input", () => {
  validateField(
    document.getElementById("othersText"),
    "Please specify the other purpose.",
    (v) => v.trim() !== ""
  );
});

document.querySelectorAll('input[name="projectType"]').forEach((radio) => {
  radio.addEventListener("change", () => {
    validateField(radio, "Please select a project type.");
    if (currentStep === 3) toggleRequirements();
  });
});

document.getElementById("projectDescription").addEventListener("input", () => {
  validateField(
    document.getElementById("projectDescription"),
    "Please provide a project description.",
    (v) => v.trim() !== ""
  );
});

document.getElementById("uploadForm").addEventListener("submit", (e) => {
  if (!validateStep1() || !validateStep2() || !validateStep3()) {
    e.preventDefault();
    showModal(
      "Please complete all required fields and upload necessary documents.",
      true
    );
  } else {
    const amountApplied = document.getElementById("amountApplied");
    amountApplied.value = removeCommas(amountApplied.value);
    console.log(
      "Submitting form with termLength:",
      document.getElementById("termLength").value
    );
    console.log("Submitting amountApplied:", amountApplied.value);
    console.log(
      "Submitting loan application, Loan ID will be generated on server in format LOAN-YYYYMMDD-XXXX."
    );
  }
});

hideAllRequirements();
updateTermLengthOptions();
updateProgress();

function fetchLoanStatus() {
  fetch("check_loan_status.php")
    .then((response) => {
      if (!response.ok) {
        throw new Error("Network response was not ok");
      }
      return response.json();
    })
    .then((data) => {
      const newRadio = document.getElementById("new");
      const renewalRadio = document.getElementById("renewal");
      const loanStatusError = document.getElementById("loanStatusError");

      if (data.error) {
        loanStatusError.textContent = data.error;
        return;
      }

      if (data.hasLoans) {
        renewalRadio.disabled = false;
        newRadio.disabled = true;
        renewalRadio.checked = true;
      } else {
        newRadio.disabled = false;
        renewalRadio.disabled = true;
        newRadio.checked = true;
      }
    })
    .catch((error) => {
      console.error("Error fetching loan status:", error);
      document.getElementById("loanStatusError").textContent =
        "Unable to load loan status. Please try again later.";
    });
}

document.addEventListener("DOMContentLoaded", fetchLoanStatus);
