function toggleDropdown(event) {
  event.stopPropagation();
  const dropdown = document.getElementById("dropdown");
  dropdown.classList.toggle("show");
}

window.onclick = function (event) {
  if (!event.target.closest(".profile-container")) {
    const dropdowns = document.getElementsByClassName("dropdown-menu");
    for (let i = 0; i < dropdowns.length; i++) {
      if (dropdowns[i].classList.contains("show")) {
        dropdowns[i].classList.remove("show");
      }
    }
  }
  const loanDetailsModal = document.getElementById("loanCalculatorModal");
  const confirmModal = document.getElementById("confirmModal");
  if (event.target === loanDetailsModal) {
    closeCalculatorModal();
  }
  if (event.target === confirmModal) {
    closeConfirmModal();
  }
};

function calculateAge() {
  const birthday = document.getElementById("birthday").value;
  const errorDiv = document.getElementById("birthday-error");
  if (!birthday) {
    errorDiv.textContent = "Please select a birthday.";
    errorDiv.style.display = "block";
    return;
  }

  const birthDate = new Date(birthday);
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();

  if (
    monthDiff < 0 ||
    (monthDiff === 0 && today.getDate() < birthDate.getDate())
  ) {
    age--;
  }

  if (age < 18) {
    errorDiv.textContent = "You must be at least 18 years old.";
    errorDiv.style.display = "block";
    document.getElementById("age").value = "";
  } else {
    errorDiv.style.display = "none";
    document.getElementById("age").value = age;
  }
}

function calculateSpouseAge() {
  const birthday = document.getElementById("spouse_birthday").value;
  const errorDiv = document.getElementById("spouse_birthday-error");
  if (!birthday) {
    errorDiv.textContent = "Please select a birthday.";
    errorDiv.style.display = "block";
    return;
  }

  const birthDate = new Date(birthday);
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();

  if (
    monthDiff < 0 ||
    (monthDiff === 0 && today.getDate() < birthDate.getDate())
  ) {
    age--;
  }

  if (age < 18) {
    errorDiv.textContent = "Spouse must be at least 18 years old.";
    errorDiv.style.display = "block";
    document.getElementById("spouse_age").value = "";
  } else {
    errorDiv.style.display = "none";
    document.getElementById("spouse_age").value = age;
  }
}

function toggleSpouseSection() {
  const civilStatus = document.getElementById("civil_status").value;
  const spouseSection = document.getElementById("spouse_section");
  const spouseInputs = spouseSection.querySelectorAll(".spouse-required");

  spouseSection.style.display = civilStatus === "Married" ? "block" : "none";
  spouseSection.style.opacity = civilStatus === "Married" ? "1" : "0";
  spouseSection.style.transition = "opacity 0.3s ease";

  spouseInputs.forEach((input) => {
    input.required = civilStatus === "Married";
  });
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function updateResidentialAddress() {
  const houseNo = document.getElementById("res_house_no").value;
  const street = document.getElementById("res_street").value;
  const subdivision = document.getElementById("res_subdivision").value;
  const barangay = document.getElementById("res_barangay").value;

  const addressParts = [houseNo, street, subdivision, barangay].filter(
    (part) => part
  );
  document.getElementById("res_address").value = addressParts.join(", ");
}

function updateBusinessAddress() {
  const bldgNo = document.getElementById("bus_bldg_no").value;
  const street = document.getElementById("bus_street").value;
  const subdivision = document.getElementById("bus_subdivision").value;
  const barangay = document.getElementById("bus_barangay").value;

  const addressParts = [bldgNo, street, subdivision, barangay].filter(
    (part) => part
  );
  document.getElementById("bus_address").value = addressParts.join(", ");
}

function calculateFinancials() {
  const incomeFields = document.querySelectorAll(".income-amount");
  const expenditureFields = document.querySelectorAll(".expenditure-amount");
  let totalIncome = 0;
  let totalExpenditure = 0;

  incomeFields.forEach((field) => {
    const value = parseFloat(field.value) || 0;
    totalIncome += value;
  });

  expenditureFields.forEach((field) => {
    const value = parseFloat(field.value) || 0;
    totalExpenditure += value;
  });

  document.querySelector("input[name='net_income']").value =
    totalIncome.toFixed(2);
  document.querySelector("input[name='expenditures']").value =
    totalExpenditure.toFixed(2);
  document.querySelector("input[name='remaining_income']").value = (
    totalIncome - totalExpenditure
  ).toFixed(2);
}

function validateForm() {
  let isValid = true;
  const requiredFields = document.querySelectorAll(
    "input[required], select[required]"
  );
  requiredFields.forEach((field) => {
    const errorDiv = document.getElementById(`${field.id}-error`);
    if (!field.value) {
      errorDiv.textContent = `${field.name.replace(/_/g, " ")} is required.`;
      errorDiv.style.display = "block";
      isValid = false;
    } else {
      errorDiv.style.display = "none";
    }
  });

  const contact = document.getElementById("contact");
  if (contact.value && !/^\d{11}$/.test(contact.value)) {
    document.getElementById("contact-error").textContent =
      "Please enter a valid 11-digit phone number.";
    document.getElementById("contact-error").style.display = "block";
    isValid = false;
  }

  const email = document.getElementById("email");
  if (email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    document.getElementById("email-error").textContent =
      "Please enter a valid email address.";
    document.getElementById("email-error").style.display = "block";
    isValid = false;
  }

  if (document.getElementById("civil_status").value === "Married") {
    const spouseRequiredFields = document.querySelectorAll(".spouse-required");
    spouseRequiredFields.forEach((field) => {
      const errorDiv = document.getElementById(`${field.id}-error`);
      if (!field.value) {
        errorDiv.textContent = `${field.name.replace(
          /_/g,
          " "
        )} is required for spouse.`;
        errorDiv.style.display = "block";
        isValid = false;
      } else {
        errorDiv.style.display = "none";
      }
    });

    const spouseContact = document.getElementById("spouse_contact");
    if (spouseContact.value && !/^\d{11}$/.test(spouseContact.value)) {
      document.getElementById("spouse_contact-error").textContent =
        "Please enter a valid 11-digit phone number.";
      document.getElementById("spouse_contact-error").style.display = "block";
      isValid = false;
    }

    const spouseEmail = document.getElementById("spouse_email");
    if (
      spouseEmail.value &&
      !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(spouseEmail.value)
    ) {
      document.getElementById("spouse_email-error").textContent =
        "Please enter a valid email address.";
      document.getElementById("spouse_email-error").style.display = "block";
      isValid = false;
    }
  }

  return isValid;
}

function previewImage(event) {
  const file = event.target.files[0];
  const preview = document.getElementById("profileImagePreview");
  const errorDiv =
    document.getElementById("profile_image-error") ||
    document.createElement("div");
  errorDiv.id = "profile_image-error";
  errorDiv.className = "error-message";
  event.target.parentNode.appendChild(errorDiv);

  if (file) {
    if (!file.type.startsWith("image/")) {
      errorDiv.textContent = "Please upload a valid image file.";
      errorDiv.style.display = "block";
      preview.src = "IMAGE/2x2.png";
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      errorDiv.textContent = "Image size must be less than 2MB.";
      errorDiv.style.display = "block";
      preview.src = "IMAGE/2x2.png";
      return;
    }
    errorDiv.style.display = "none";
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.src = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function closeConfirmModal() {
  const modal = document.getElementById("confirmModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
  }, 300);
}

function submitForm() {
  document.getElementById("profileForm").submit();
}

function openCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  modal.style.display = "flex";
  setTimeout(() => modal.classList.add("show"), 10);
}

function closeCalculatorModal() {
  const modal = document.getElementById("loanCalculatorModal");
  modal.classList.remove("show");
  setTimeout(() => {
    modal.style.display = "none";
  }, 300);
}

function updateLoanAmountRange() {
  const loanType = document.getElementById("loanType").value;
  const loanAmountInput = document.getElementById("loanAmount");
  const loanAmountLabel = document.getElementById("loanAmountLabel");
  const interestRateInput = document.getElementById("interestRate");
  const interestRateDisplay = document.getElementById("interestRateDisplay");

  if (loanType === "Individual") {
    loanAmountInput.min = 10000;
    loanAmountInput.max = 100000;
    loanAmountInput.placeholder = "Enter amount (₱10,000 - ₱100,000)";
    loanAmountLabel.textContent = "Loan Amount (₱10,000 - ₱100,000)";
    interestRateInput.value = 6.0;
    interestRateDisplay.textContent = "5.00% per annum";
  } else if (loanType === "Cooperative") {
    loanAmountInput.min = 50000;
    loanAmountInput.max = 500000;
    loanAmountInput.placeholder = "Enter amount (₱50,000 - ₱500,000)";
    loanAmountLabel.textContent = "Loan Amount (₱50,000 - ₱500,000)";
    interestRateInput.value = 4.0;
    interestRateDisplay.textContent = "4.00% per annum";
  } else {
    loanAmountInput.min = "";
    loanAmountInput.max = "";
    loanAmountInput.placeholder = "Select loan type first";
    loanAmountLabel.textContent = "Loan Amount";
    interestRateInput.value = "";
    interestRateDisplay.textContent = "Select a loan type";
  }
}

function calculateLoan() {
  const loanAmount = parseFloat(document.getElementById("loanAmount").value);
  const annualInterestRate =
    parseFloat(document.getElementById("interestRate").value) / 100;
  const monthlyInterestRate = annualInterestRate / 12;
  const loanTerm = parseFloat(document.getElementById("termLength").value);
  const errorDiv = document.getElementById("loanAmount-error");

  if (
    !loanAmount ||
    loanAmount < parseFloat(document.getElementById("loanAmount").min) ||
    loanAmount > parseFloat(document.getElementById("loanAmount").max)
  ) {
    errorDiv.textContent = `Please enter a valid amount between ₱${
      document.getElementById("loanAmount").min
    } and ₱${document.getElementById("loanAmount").max}.`;
    errorDiv.style.display = "block";
    return;
  }
  errorDiv.style.display = "none";

  const monthlyPayment =
    (loanAmount * monthlyInterestRate) /
    (1 - Math.pow(1 + monthlyInterestRate, -loanTerm));
  const resultDiv = document.getElementById("result");

  if (
    !isNaN(monthlyPayment) &&
    monthlyPayment !== Infinity &&
    monthlyPayment > 0
  ) {
    resultDiv.innerHTML = `Monthly Payment: ₱${monthlyPayment.toFixed(2)}`;
  } else {
    resultDiv.innerHTML =
      '<span class="error">Please enter valid values.</span>';
  }
}

// Initialize event listeners
document.addEventListener("DOMContentLoaded", () => {
  const debouncedResidentialAddress = debounce(updateResidentialAddress, 300);
  const debouncedBusinessAddress = debounce(updateBusinessAddress, 300);

  document
    .getElementById("res_house_no")
    .addEventListener("input", debouncedResidentialAddress);
  document
    .getElementById("res_street")
    .addEventListener("input", debouncedResidentialAddress);
  document
    .getElementById("res_subdivision")
    .addEventListener("input", debouncedResidentialAddress);
  document
    .getElementById("res_barangay")
    .addEventListener("change", debouncedResidentialAddress);

  document
    .getElementById("bus_bldg_no")
    .addEventListener("input", debouncedBusinessAddress);
  document
    .getElementById("bus_street")
    .addEventListener("input", debouncedBusinessAddress);
  document
    .getElementById("bus_subdivision")
    .addEventListener("input", debouncedBusinessAddress);
  document
    .getElementById("bus_barangay")
    .addEventListener("change", debouncedBusinessAddress);

  document
    .querySelectorAll(".income-amount, .expenditure-amount")
    .forEach((field) => {
      field.addEventListener("input", debounce(calculateFinancials, 300));
    });

  // Initial calculations
  calculateAge();
  calculateSpouseAge();
  toggleSpouseSection();
  updateResidentialAddress();
  updateBusinessAddress();
  calculateFinancials();
});

// Modal handling functions
function confirmSubmission() {
  // Validate form before showing modal
  if (document.getElementById("profileForm").checkValidity()) {
    document.getElementById("confirmModal").style.display = "flex";
  } else {
    document.getElementById("profileForm").reportValidity();
  }
}

function closeConfirmModal() {
  document.getElementById("confirmModal").style.display = "none";
  document.getElementById("confirmUpdateBtn").disabled = false;
  document.getElementById("loadingSpinner").style.display = "none";
}

function submitForm() {
  const confirmBtn = document.getElementById("confirmUpdateBtn");
  const loadingSpinner = document.getElementById("loadingSpinner");

  // Show loading state
  confirmBtn.disabled = true;
  confirmBtn.textContent = "Updating...";
  loadingSpinner.style.display = "block";

  // Simulate form submission (actual submission handled by form action)
  setTimeout(() => {
    document.getElementById("profileForm").submit();
  }, 1000); // Simulate network delay
}

// Ensure modal closes when clicking outside
window.onclick = function (event) {
  const modal = document.getElementById("confirmModal");
  if (event.target === modal) {
    closeConfirmModal();
  }
};
