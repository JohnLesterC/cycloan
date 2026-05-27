// Language Change Handler
function changeLanguage(lang) {
  // Redirect to same page with language parameter
  const url = new URL(window.location);
  url.searchParams.set("lang", lang);
  window.location.href = url.toString();
}

document.addEventListener("DOMContentLoaded", function () {
  console.log("DOMContentLoaded event fired");

  // Initialize auto-save functionality
  initializeAutoSave();

  // Initialize loading enhancements
  initializeLoadingEnhancements();

  initializeForm();

  // Defer barangay loading to ensure fields are rendered (conditional step logic)
  setTimeout(() => {
    loadBarangays();
  }, 500);

  // Backup: Ensure voter registration listener is attached
  // This runs even if initializeForm returns early
  attachVoterRegistrationListener();

  // Load saved form data if available
  loadSavedFormData();

  // Attach language selector change event handler
  const langSelector = document.getElementById("languageSelector");
  if (langSelector) {
    langSelector.addEventListener("change", function () {
      changeLanguage(this.value);
    });
  }
});

// Auto-save functionality
let autoSaveTimer;
const AUTO_SAVE_INTERVAL = 10000; // Save every 10 seconds

function initializeAutoSave() {
  console.log("Initializing auto-save functionality");

  // Get current step from URL
  const currentStep =
    new URLSearchParams(window.location.search).get("step") || "1";

  // Set up auto-save for all form inputs
  document.addEventListener("input", function (e) {
    if (e.target.closest("#registrationForm")) {
      clearTimeout(autoSaveTimer);
      autoSaveTimer = setTimeout(() => {
        saveFormData(currentStep);
      }, 2000); // Save 2 seconds after user stops typing
    }
  });

  // Also save on form changes (dropdowns, checkboxes)
  document.addEventListener("change", function (e) {
    if (e.target.closest("#registrationForm")) {
      saveFormData(currentStep);
    }
  });

  // Periodic auto-save
  setInterval(() => {
    saveFormData(currentStep);
  }, AUTO_SAVE_INTERVAL);
}

function saveFormData(step) {
  const form = document.getElementById("registrationForm");
  if (!form) return;

  const formData = new FormData(form);
  const data = {};

  // Convert FormData to regular object
  for (let [key, value] of formData.entries()) {
    if (key === "csrf_token") continue; // Skip CSRF token

    if (data[key]) {
      // Handle multiple values (like checkboxes)
      if (Array.isArray(data[key])) {
        data[key].push(value);
      } else {
        data[key] = [data[key], value];
      }
    } else {
      data[key] = value;
    }
  }

  // Save to localStorage with timestamp
  const saveData = {
    step: step,
    data: data,
    timestamp: new Date().toISOString(),
    url: window.location.href,
  };

  try {
    localStorage.setItem(
      "cycloan_registration_autosave",
      JSON.stringify(saveData)
    );
    showAutoSaveIndicator();
    console.log("Form data auto-saved for step", step);
  } catch (error) {
    console.warn("Failed to auto-save form data:", error);
  }
}

function loadSavedFormData() {
  try {
    const savedData = localStorage.getItem("cycloan_registration_autosave");
    if (!savedData) return;

    const parsed = JSON.parse(savedData);
    const currentStep =
      new URLSearchParams(window.location.search).get("step") || "1";

    // Only load if it's the same step and recent (within 24 hours)
    const saveTime = new Date(parsed.timestamp);
    const now = new Date();
    const hoursDiff = (now - saveTime) / (1000 * 60 * 60);

    if (parsed.step === currentStep && hoursDiff < 24) {
      showRecoveryPrompt(parsed);
    }
  } catch (error) {
    console.warn("Failed to load saved form data:", error);
  }
}

function showAutoSaveIndicator() {
  // Remove existing indicator
  const existing = document.querySelector(".auto-save-indicator");
  if (existing) existing.remove();

  // Create and show new indicator
  const indicator = document.createElement("div");
  indicator.className = "auto-save-indicator";
  indicator.innerHTML = '<i class="fas fa-check"></i> Auto-saved';
  indicator.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--secondary);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 1000;
    opacity: 0;
    transition: opacity 0.3s ease;
  `;

  document.body.appendChild(indicator);

  // Animate in
  setTimeout(() => (indicator.style.opacity = "1"), 10);

  // Remove after 2 seconds
  setTimeout(() => {
    indicator.style.opacity = "0";
    setTimeout(() => indicator.remove(), 300);
  }, 2000);
}

function showRecoveryPrompt(savedData) {
  const prompt = document.createElement("div");
  prompt.className = "recovery-prompt";
  prompt.innerHTML = `
    <div class="recovery-content">
      <h5><i class="fas fa-history"></i> Recover Previous Data?</h5>
      <p>We found unsaved changes from ${new Date(
        savedData.timestamp
      ).toLocaleString()}. Would you like to restore them?</p>
      <div class="recovery-buttons">
        <button class="btn btn-secondary btn-sm" onclick="dismissRecovery()">Dismiss</button>
        <button class="btn btn-primary btn-sm" onclick="restoreFormData()">Restore</button>
      </div>
    </div>
  `;
  prompt.style.cssText = `
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1001;
    max-width: 400px;
    width: 90%;
  `;

  document.body.appendChild(prompt);

  // Store data for restoration
  window.recoveryData = savedData;
}

function restoreFormData() {
  const data = window.recoveryData;
  if (!data) return;

  const form = document.getElementById("registrationForm");
  if (!form) return;

  // Restore form values
  Object.entries(data.data).forEach(([key, value]) => {
    const element = form.querySelector(`[name="${key}"]`);
    if (!element) return;

    if (element.type === "checkbox" || element.type === "radio") {
      if (Array.isArray(value)) {
        value.forEach((v) => {
          const checkbox = form.querySelector(`[name="${key}"][value="${v}"]`);
          if (checkbox) checkbox.checked = true;
        });
      } else {
        element.checked = element.value === value;
      }
    } else {
      element.value = Array.isArray(value) ? value[0] : value;
    }
  });

  dismissRecovery();

  // Show success message
  showMessage("Form data restored successfully!", "success");
}

function dismissRecovery() {
  const prompt = document.querySelector(".recovery-prompt");
  if (prompt) prompt.remove();

  // Clear saved data
  localStorage.removeItem("cycloan_registration_autosave");
  delete window.recoveryData;
}

function showMessage(text, type = "info") {
  const message = document.createElement("div");
  message.className = `alert alert-${type} auto-message`;
  message.textContent = text;
  message.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    z-index: 1002;
    min-width: 250px;
    opacity: 0;
    transition: opacity 0.3s ease;
  `;

  document.body.appendChild(message);
  setTimeout(() => (message.style.opacity = "1"), 10);

  setTimeout(() => {
    message.style.opacity = "0";
    setTimeout(() => message.remove(), 300);
  }, 3000);
}

// Advanced Validation Enhancement
class FormValidator {
  constructor() {
    this.rules = {
      name: {
        pattern: /^[a-zA-Z\s\-\']{2,50}$/,
        message: "Name must be 2-50 characters, letters only",
      },
      email: {
        pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        message: "Please enter a valid email address",
      },
      phone: {
        pattern: /^(09|\+639)\d{9}$/,
        message: "Please enter a valid Philippine mobile number",
      },
      strongPassword: {
        pattern:
          /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/,
        message:
          "Password must be strong (8+ chars, uppercase, lowercase, number, special char)",
      },
    };

    this.suggestions = {
      email: {
        "gmail.co": "gmail.com",
        "gmail.cm": "gmail.com",
        "gmai.com": "gmail.com",
        "yahoo.co": "yahoo.com",
        "yahoo.cm": "yahoo.com",
        "hotmail.co": "hotmail.com",
        "outlook.co": "outlook.com",
      },
      occupation: [
        "Teacher",
        "Engineer",
        "Doctor",
        "Nurse",
        "Driver",
        "Manager",
        "Supervisor",
        "Clerk",
        "Sales Representative",
        "Mechanic",
        "Electrician",
        "Carpenter",
        "Cook",
        "Security Guard",
        "Student",
      ],
      birthPlace: [
        "Manila, NCR",
        "Calamba, Laguna",
        "Quezon City, NCR",
        "Cebu City, Cebu",
        "Davao City, Davao del Sur",
        "Antipolo, Rizal",
        "Pasig, NCR",
        "Taguig, NCR",
      ],
    };
  }

  validateField(field, value, ruleName) {
    const rule = this.rules[ruleName];
    if (!rule) return { valid: true };

    const isValid = rule.pattern.test(value);
    return {
      valid: isValid,
      message: isValid ? "" : rule.message,
    };
  }

  suggestCorrection(field, value) {
    if (field === "email") {
      const emailParts = value.split("@");
      if (emailParts.length === 2) {
        const domain = emailParts[1].toLowerCase();
        const suggestion = this.suggestions.email[domain];
        if (suggestion) {
          return `${emailParts[0]}@${suggestion}`;
        }
      }
    }
    return null;
  }

  getFieldSuggestions(field, value) {
    const suggestions = this.suggestions[field];
    if (!suggestions || !value) return [];

    const query = value.toLowerCase();
    return suggestions
      .filter((item) => item.toLowerCase().includes(query))
      .slice(0, 5);
  }
}

const validator = new FormValidator();

// Enhanced field validation with suggestions
function setupAdvancedValidation() {
  // Name fields
  ["first_name", "last_name", "spouse_first_name", "spouse_last_name"].forEach(
    (fieldId) => {
      const field = document.getElementById(fieldId);
      if (field) {
        setupFieldValidation(field, "name", fieldId);
      }
    }
  );

  // Email fields with suggestions
  ["email", "confirm_email", "spouse_email"].forEach((fieldId) => {
    const field = document.getElementById(fieldId);
    if (field) {
      setupEmailValidation(field, fieldId);
    }
  });

  // Phone validation
  const contactField = document.querySelector('[name="contact"]');
  if (contactField) {
    setupPhoneValidation(contactField);
  }

  // Occupation with autocomplete
  const occupationField = document.getElementById("occupation");
  if (occupationField) {
    setupAutocomplete(occupationField, "occupation");
  }

  // Birth place with suggestions
  const birthPlaceField = document.getElementById("birth_place");
  if (birthPlaceField) {
    setupAutocomplete(birthPlaceField, "birthPlace");
  }
}

function setupFieldValidation(field, ruleName, fieldId) {
  const errorContainer =
    document.getElementById(`${fieldId.replace("_", "")}ValidationMessage`) ||
    document.getElementById(`${fieldId}ValidationMessage`);

  field.addEventListener("blur", function () {
    const result = validator.validateField(field, this.value, ruleName);
    showValidationResult(this, errorContainer, result);
  });

  field.addEventListener("input", function () {
    if (this.classList.contains("error")) {
      const result = validator.validateField(field, this.value, ruleName);
      if (result.valid) {
        showValidationResult(this, errorContainer, result);
      }
    }
  });
}

function setupEmailValidation(field, fieldId) {
  const errorContainer = document.getElementById(`${fieldId}ValidationMessage`);

  field.addEventListener("blur", function () {
    const result = validator.validateField(field, this.value, "email");
    showValidationResult(this, errorContainer, result);

    // Check for email suggestions
    if (!result.valid && this.value) {
      const suggestion = validator.suggestCorrection("email", this.value);
      if (suggestion && suggestion !== this.value) {
        showEmailSuggestion(this, suggestion, errorContainer);
      }
    }
  });
}

function setupPhoneValidation(field) {
  field.addEventListener("input", function () {
    // Format phone number as user types
    let value = this.value.replace(/\D/g, "");
    if (value.length > 11) value = value.slice(0, 11);

    if (value.length > 0 && !value.startsWith("09")) {
      if (value.startsWith("9")) {
        value = "0" + value;
      }
    }

    this.value = value;
  });

  field.addEventListener("blur", function () {
    const result = validator.validateField(field, this.value, "phone");
    const errorContainer = document.getElementById("contactValidationMessage");
    showValidationResult(this, errorContainer, result);
  });
}

function setupAutocomplete(field, suggestionType) {
  let suggestionBox;

  field.addEventListener("input", function () {
    const suggestions = validator.getFieldSuggestions(
      suggestionType,
      this.value
    );

    if (suggestions.length > 0 && this.value.length > 1) {
      showSuggestions(this, suggestions);
    } else {
      hideSuggestions();
    }
  });

  field.addEventListener("blur", function () {
    // Delay hiding to allow click on suggestions
    setTimeout(() => hideSuggestions(), 150);
  });

  function showSuggestions(input, suggestions) {
    hideSuggestions();

    suggestionBox = document.createElement("div");
    suggestionBox.className = "autocomplete-suggestions";
    suggestionBox.style.cssText = `
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-top: none;
      border-radius: 0 0 4px 4px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;

    suggestions.forEach((suggestion) => {
      const item = document.createElement("div");
      item.textContent = suggestion;
      item.style.cssText = `
        padding: 10px;
        cursor: pointer;
        border-bottom: 1px solid #f0f0f0;
        transition: background-color 0.2s;
      `;

      item.addEventListener("mouseenter", () => {
        item.style.backgroundColor = "#f8f9fa";
      });

      item.addEventListener("mouseleave", () => {
        item.style.backgroundColor = "white";
      });

      item.addEventListener("click", () => {
        input.value = suggestion;
        hideSuggestions();
        input.dispatchEvent(new Event("input", { bubbles: true }));
      });

      suggestionBox.appendChild(item);
    });

    // Position relative to input
    const inputRect = input.getBoundingClientRect();
    const container = input.parentElement;
    container.style.position = "relative";
    container.appendChild(suggestionBox);
  }

  function hideSuggestions() {
    if (suggestionBox) {
      suggestionBox.remove();
      suggestionBox = null;
    }
  }
}

function showValidationResult(field, errorContainer, result) {
  if (result.valid) {
    field.classList.remove("error", "is-invalid");
    field.classList.add("is-valid");
    if (errorContainer) {
      errorContainer.textContent = "";
      errorContainer.classList.remove("error-message");
    }
  } else {
    field.classList.remove("is-valid");
    field.classList.add("error", "is-invalid");
    if (errorContainer) {
      errorContainer.textContent = result.message;
      errorContainer.classList.add("error-message");
    }
  }
}

function showEmailSuggestion(field, suggestion, errorContainer) {
  const suggestionEl = document.createElement("div");
  suggestionEl.className = "email-suggestion";
  suggestionEl.innerHTML = `
    <span>Did you mean: </span>
    <button type="button" class="suggestion-btn" onclick="applySuggestion('${field.id}', '${suggestion}')">
      ${suggestion}
    </button>
  `;
  suggestionEl.style.cssText = `
    margin-top: 5px;
    padding: 8px;
    background: #e3f2fd;
    border: 1px solid #2196f3;
    border-radius: 4px;
    font-size: 12px;
  `;

  if (errorContainer) {
    errorContainer.appendChild(suggestionEl);
  }
}

function applySuggestion(fieldId, suggestion) {
  const field = document.getElementById(fieldId);
  if (field) {
    field.value = suggestion;
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.dispatchEvent(new Event("blur", { bubbles: true }));
  }
}

// Enhanced Loading States & Animations
class LoadingManager {
  constructor() {
    this.activeLoaders = new Set();
  }

  showFieldLoading(fieldId) {
    const field =
      document.getElementById(fieldId) ||
      document.querySelector(`[name="${fieldId}"]`);
    if (!field) return;

    field.parentElement.classList.add("form-field-loading");
    this.activeLoaders.add(fieldId);
  }

  hideFieldLoading(fieldId) {
    const field =
      document.getElementById(fieldId) ||
      document.querySelector(`[name="${fieldId}"]`);
    if (!field) return;

    field.parentElement.classList.remove("form-field-loading");
    this.activeLoaders.delete(fieldId);
  }

  showPageLoading(message = "Processing your request...") {
    // Disabled - loading screen removed
    return;
  }

  hidePageLoading() {
    // Disabled - loading screen removed
    return;
  }

  showFormTransition() {
    const formSteps = document.querySelectorAll("h3, .form-grid");
    formSteps.forEach((step, index) => {
      step.style.opacity = "0";
      step.style.transform = "translateY(20px)";

      setTimeout(() => {
        step.classList.add("form-step");
        step.style.opacity = "1";
        step.style.transform = "translateY(0)";
      }, index * 100);
    });
  }

  animateProgressStep(stepNumber) {
    const progressSteps = document.querySelectorAll(".progress-step");
    if (progressSteps[stepNumber - 1]) {
      progressSteps[stepNumber - 1].classList.add("active");

      setTimeout(() => {
        progressSteps[stepNumber - 1].classList.remove("active");
      }, 600);
    }
  }

  showValidationSuccess(fieldId) {
    const field =
      document.getElementById(fieldId) ||
      document.querySelector(`[name="${fieldId}"]`);
    if (!field) return;

    // Add success animation class
    field.classList.add("validation-success");

    // Remove after animation
    setTimeout(() => {
      field.classList.remove("validation-success");
    }, 400);
  }
}

const loadingManager = new LoadingManager();

// Enhanced form submission with loading states
function enhancedFormSubmission() {
  const form = document.getElementById("registrationForm");
  if (!form) return;

  const originalSubmit = form.onsubmit;

  form.addEventListener("submit", function (e) {
    const submitBtn = form.querySelector('[type="submit"]');
    const currentStep =
      new URLSearchParams(window.location.search).get("step") || "1";

    // Show loading on submit button
    if (submitBtn) {
      const originalText = submitBtn.textContent;
      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<i class="fas fa-spinner fa-spin"></i> Processing...';

      // Restore button after delay (in case of validation errors)
      setTimeout(() => {
        if (submitBtn.disabled) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
      }, 5000);
    }

    // Show page loading for final submission
    if (currentStep === "5" || currentStep === "6") {
      setTimeout(() => {
        loadingManager.showPageLoading(
          "Finalizing your registration...",
          "Please wait while we process your information"
        );
      }, 100);
    }
  });
}

// Initialize loading enhancements
function initializeLoadingEnhancements() {
  // Show form transition on page load
  setTimeout(() => {
    loadingManager.showFormTransition();
  }, 100);

  // Animate current progress step
  const currentStep = parseInt(
    new URLSearchParams(window.location.search).get("step") || "1"
  );
  setTimeout(() => {
    loadingManager.animateProgressStep(currentStep);
  }, 500);

  // Setup enhanced form submission
  enhancedFormSubmission();

  // Add loading to email validation
  const emailField = document.getElementById("email");
  if (emailField) {
    emailField.addEventListener("blur", function () {
      if (this.value && this.value.includes("@")) {
        loadingManager.showFieldLoading("email");

        // Simulate API call delay
        setTimeout(() => {
          loadingManager.hideFieldLoading("email");
          loadingManager.showValidationSuccess("email");
        }, 1500);
      }
    });
  }

  // Add smooth hover effects to buttons
  const buttons = document.querySelectorAll(".btn");
  buttons.forEach((btn) => {
    btn.addEventListener("mouseenter", function () {
      this.style.transform = "translateY(-2px)";
    });

    btn.addEventListener("mouseleave", function () {
      this.style.transform = "translateY(0)";
    });
  });
}

// Load barangays from PSGC API
function loadBarangays() {
  const resBarangaySelect = document.getElementById("res_barangay_select");
  const busBarangaySelect = document.getElementById("bus_barangay_select");

  if (!resBarangaySelect && !busBarangaySelect) {
    console.warn("Barangay select fields not found (likely not on this step)");
    return;
  }

  // Fetch from API
  fetch("get_barangays.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.data) {
        const barangays = data.data;

        // Get the previously selected values from the form data
        const selectedResBarangay = resBarangaySelect
          ? resBarangaySelect.getAttribute("data-selected")
          : null;
        const selectedBusBarangay = busBarangaySelect
          ? busBarangaySelect.getAttribute("data-selected")
          : null;

        // Populate resident barangay dropdown
        if (resBarangaySelect) {
          resBarangaySelect.innerHTML =
            '<option value="">Select Barangay</option>';
          barangays.forEach((barangay) => {
            const option = document.createElement("option");
            option.value = barangay.name;
            option.textContent = barangay.name;
            if (selectedResBarangay === barangay.name) {
              option.selected = true;
            }
            resBarangaySelect.appendChild(option);
          });
        }

        // Populate business barangay dropdown
        if (busBarangaySelect) {
          busBarangaySelect.innerHTML =
            '<option value="">Select Barangay</option>';
          barangays.forEach((barangay) => {
            const option = document.createElement("option");
            option.value = barangay.name;
            option.textContent = barangay.name;
            if (selectedBusBarangay === barangay.name) {
              option.selected = true;
            }
            busBarangaySelect.appendChild(option);
          });
        }

        console.log("Barangays loaded successfully", barangays.length);
        // Attach listeners after barangays are loaded
        attachBarangayListeners();
        // Trigger address update in case barangay was pre-selected
        updateResidentialAddress();
        updateBusinessAddress();
      } else {
        console.error("Failed to load barangays:", data.error);
        // Fallback text
        if (resBarangaySelect) {
          resBarangaySelect.innerHTML =
            '<option value="">Failed to load barangays</option>';
        }
        if (busBarangaySelect) {
          busBarangaySelect.innerHTML =
            '<option value="">Failed to load barangays</option>';
        }
      }
    })
    .catch((error) => {
      console.error("Error fetching barangays:", error);
      if (resBarangaySelect) {
        resBarangaySelect.innerHTML =
          '<option value="">Error loading barangays</option>';
      }
      if (busBarangaySelect) {
        busBarangaySelect.innerHTML =
          '<option value="">Error loading barangays</option>';
      }
    });
}

// Separate function to ensure voter registration listener is always attached
function attachVoterRegistrationListener() {
  console.log("attachVoterRegistrationListener called");

  const voterRegistrationSelect = document.querySelector('[name="reg_voter"]');
  if (!voterRegistrationSelect) {
    console.warn(
      "Voter registration select field not found in attachVoterRegistrationListener"
    );
    return;
  }

  // Remove any existing listeners first to prevent duplicates
  voterRegistrationSelect.removeEventListener(
    "change",
    handleVoterRegistrationChange
  );

  // Attach the listener
  voterRegistrationSelect.addEventListener(
    "change",
    handleVoterRegistrationChange
  );
  console.log("Voter registration change listener attached (backup)");
}

// Separate function for the change handler (easier to remove/reattach)
function handleVoterRegistrationChange() {
  console.log("Voter registration field changed to:", this.value);

  // Validate voter registration status in real-time
  validateRegisteredVoterStatus();

  // Add visual feedback - show error for non-Calamba voters and non-voters
  if (this.value === "yes_not_calamba" || this.value === "no") {
    console.log("Adding error class to field");
    this.classList.add("error");
  } else {
    console.log("Removing error class from field");
    this.classList.remove("error");
  }
}

// SECURITY: Input sanitization function to prevent XSS attacks
function sanitizeInput(input) {
  if (typeof input !== "string") {
    return input;
  }

  // Create a temporary element to safely escape HTML
  const element = document.createElement("div");
  element.textContent = input;
  let sanitized = element.innerHTML;

  // Remove script tags and event handlers
  sanitized = sanitized.replace(
    /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi,
    ""
  );
  sanitized = sanitized.replace(/on\w+\s*=\s*["'][^"']*["']/gi, "");
  sanitized = sanitized.replace(/javascript:/gi, "");

  // Remove dangerous event attributes
  sanitized = sanitized.replace(/on\w+\s*=/gi, "");

  return sanitized;
}

// SECURITY: Validate and sanitize form inputs before submission
function sanitizeFormInputs(formElement) {
  const inputs = formElement.querySelectorAll(
    'input[type="text"], input[type="email"], textarea, select'
  );
  inputs.forEach((input) => {
    if (input.value && typeof input.value === "string") {
      input.value = sanitizeInput(input.value);
    }
  });
}

// Accept consent function with better error handling
function acceptConsent() {
  console.log("Accept button clicked");

  // SECURITY: Include CSRF token in fetch request
  const csrfToken = document.querySelector('input[name="csrf_token"]');
  const token = csrfToken ? csrfToken.value : "";

  fetch("process_consent.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body:
      "action=accept" +
      (token ? "&csrf_token=" + encodeURIComponent(token) : ""),
  })
    .then((response) => {
      console.log("Response status:", response.status);
      console.log("Response ok:", response.ok);

      if (!response.ok) {
        throw new Error("Network response was not ok: " + response.status);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Response data:", data);

      if (data.success) {
        // Hide modal
        const modalElement = document.getElementById("dataPrivacyModal");
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
          modal.hide();
        }

        // Show registration form
        const registrationContainer = document.getElementById(
          "registrationContainer"
        );
        if (registrationContainer) {
          registrationContainer.style.display = "block";
        }

        // Re-initialize form to ensure event listeners are active
        initializeForm();

        // Ensure voter registration listener is definitely attached
        attachVoterRegistrationListener();

        console.log("Consent accepted successfully");
      } else {
        alert("Error: " + (data.message || "Could not process consent"));
      }
    })
    .catch((error) => {
      console.error("Fetch Error:", error);
      console.error("Error details:", error.message);

      // More specific error message
      alert(
        "An error occurred while processing your consent. Please check:\n\n" +
          "1. Is process_consent.php file uploaded?\n" +
          "2. Is it in the same folder as registration.php?\n" +
          "3. Check browser console for details.\n\n" +
          "Error: " +
          error.message
      );
    });
}

// Decline consent function with better error handling
function declineConsent() {
  console.log("Decline button clicked");

  fetch("process_consent.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "action=decline",
  })
    .then((response) => {
      console.log("Decline response status:", response.status);

      if (!response.ok) {
        throw new Error("Network response was not ok: " + response.status);
      }
      return response.json();
    })
    .then((data) => {
      console.log("Decline response data:", data);

      if (data.success) {
        // Redirect to home page
        window.location.href = "index.php";
      } else {
        alert("Error: " + (data.message || "Could not process decline"));
      }
    })
    .catch((error) => {
      console.error("Decline Fetch Error:", error);
      alert("An error occurred. Error: " + error.message);
    });
}

function initializeForm() {
  // Only initialize if form is visible (i.e., consent given)
  const registrationContainer = document.getElementById(
    "registrationContainer"
  );
  if (!registrationContainer || registrationContainer.style.display === "none")
    return;

  // Setup advanced validation
  setupAdvancedValidation();

  // Initialize email validation for real-time checking
  initializeEmailValidation();

  // Initialize occupation dropdown functionality
  initializeOccupationDropdowns();

  // Initialize residency dropdown functionality
  initializeResidencyDropdown();

  initializeSpouseSection();
  initializeFinancialSections();
  initializeAddressFunctions();
  calculateTotals();

  // Call updates on load in case of pre-filled data from session
  updateResidentialAddress();
  updateBusinessAddress();

  // Add event listener to auto-calculate age
  const birthdayInput = document.getElementById("birthday");
  if (birthdayInput) {
    birthdayInput.addEventListener("change", calculateAge);
    // Trigger calculation if value is already set
    if (birthdayInput.value) {
      calculateAge();
    }
  }

  // Add event listener to auto-calculate spouse age
  const spouseBirthdayInput = document.getElementById("spouse_birthday");
  if (spouseBirthdayInput) {
    spouseBirthdayInput.addEventListener("change", calculateSpouseAge);
    // Trigger calculation if value is already set
    if (spouseBirthdayInput.value) {
      calculateSpouseAge();
    }
  }

  // Set up password validation
  const passwordInput = document.getElementById("password");
  if (passwordInput) {
    passwordInput.addEventListener("input", function () {
      validatePassword(this.value);
    });
  }

  const confirmPasswordInput = document.getElementById("confirm_password");
  if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener("input", validatePasswordMatch);
  }

  const civilStatusSelect = document.getElementById("civil_status");
  if (civilStatusSelect) {
    civilStatusSelect.addEventListener("change", function () {
      toggleSpouseSection();
    });
  }

  // Add real-time validation for Facebook links
  const fbAccountInput = document.getElementById("fb_account");
  if (fbAccountInput) {
    fbAccountInput.addEventListener("input", function () {
      validateFacebookLink(this, "fbAccountValidationMessage");
    });
  }
  const spouseFbAccountInput = document.getElementById("spouse_fb_account");
  if (spouseFbAccountInput) {
    spouseFbAccountInput.addEventListener("input", function () {
      validateFacebookLink(this, "spouseFbAccountValidationMessage");
    });
  }

  // Add real-time validation for contacts
  const contactInput = document.querySelector('[name="contact"]');
  if (contactInput) {
    contactInput.addEventListener("input", function () {
      validatePhoneNumber(this.value, this, "contactValidationMessage");
    });
  }
  const spouseContactInput = document.querySelector('[name="spouse_contact"]');
  if (spouseContactInput) {
    spouseContactInput.addEventListener("input", function () {
      validatePhoneNumber(this.value, this, "spouseContactValidationMessage");
    });
  }

  // Add real-time validation for emails
  const emailInput = document.getElementById("email");
  if (emailInput) {
    emailInput.addEventListener("input", function () {
      validateEmail(this.value, this, "emailValidationMessage");
      validateEmailMatch(); // Check if emails match when typing
    });
  }

  // Add confirm email validation
  const confirmEmailInput = document.getElementById("confirm_email");
  if (confirmEmailInput) {
    // Prevent copy-paste to ensure user types it manually
    confirmEmailInput.addEventListener("paste", function (e) {
      e.preventDefault();
      const errorContainer = document.getElementById(
        "confirmEmailValidationMessage"
      );
      errorContainer.innerHTML =
        '<i class="fas fa-exclamation-triangle"></i> Please type your email manually to confirm (copy-paste disabled)';
      errorContainer.style.color = "#f57c00";
      setTimeout(() => {
        errorContainer.textContent = "";
        errorContainer.style.color = "";
      }, 3000);
    });

    confirmEmailInput.addEventListener("input", function () {
      validateEmailMatch();
    });

    confirmEmailInput.addEventListener("blur", function () {
      validateEmailMatch();
    });
  }

  const spouseEmailInput = document.querySelector('[name="spouse_email"]');
  if (spouseEmailInput) {
    spouseEmailInput.addEventListener("input", function () {
      validateEmail(this.value, this, "spouseEmailValidationMessage");
    });
  }

  // Add real-time validation for year resident dropdown
  const yearResidentSelect = document.querySelector('[name="year_resident"]');
  if (yearResidentSelect) {
    yearResidentSelect.addEventListener("change", function () {
      const errorContainer = document.getElementById(
        "yearResidentValidationMessage"
      );
      const customField = document.getElementById("customResidencyField");

      if (this.value === "custom") {
        // Show custom input field
        customField.style.display = "block";
        const customInput = document.getElementById("year_resident_custom");
        if (customInput) {
          // Clear any error states from the custom input
          customInput.classList.remove("error", "is-invalid");
          customInput.focus();
          customInput.addEventListener("input", validateCustomResidency);
        }
        errorContainer.textContent = "";
        this.classList.remove("error");
      } else if (this.value === "") {
        // Empty selection
        customField.style.display = "none";
        errorContainer.textContent = "";
        this.classList.remove("error");
      } else {
        // Selected predefined option
        customField.style.display = "none";
        errorContainer.textContent =
          "Selected: " + this.options[this.selectedIndex].text;
        errorContainer.style.color = "#28a745";
        this.classList.remove("error");
      }
    });

    // Trigger change event on load to show/hide custom field
    yearResidentSelect.dispatchEvent(new Event("change"));
  }

  // Function to validate custom residency input
  function validateCustomResidency() {
    const value = this.value.trim();
    const errorContainer = document.getElementById("customResidencyMessage");
    const yearResidentSelect = document.querySelector('[name="year_resident"]');

    if (!value) {
      errorContainer.textContent = "";
      errorContainer.style.color = "";
      this.classList.remove("error", "is-invalid");
      return;
    }

    // Regex patterns for validation
    const numberRegex = /^\d+$/;
    const unitRegex = /^(\d+(?:\.\d+)?)\s*[ym]$/i;
    const combinedRegex = /^(\d+(?:\.\d+)?)\s*[ym]\s*(\d+(?:\.\d+)?)\s*[ym]$/i;
    const dateRegex = /^(0[1-9]|1[0-2])\/\d{4}$/;

    let isValid = false;
    let message = "";

    if (numberRegex.test(value)) {
      // Plain number interpreted as months
      const months = parseInt(value);
      if (months > 1200) {
        message = "✗ Maximum 1200 months (100 years) allowed";
        errorContainer.style.color = "#dc3545";
      } else {
        message = `✓ ${months} month${months !== 1 ? "s" : ""}`;
        errorContainer.style.color = "#28a745";
        isValid = true;
      }
    } else if (
      unitRegex.test(value) ||
      combinedRegex.test(value) ||
      dateRegex.test(value)
    ) {
      message = "✓ Format valid";
      errorContainer.style.color = "#28a745";
      isValid = true;
    } else {
      message = "✗ Use: 6m, 2y, 1y6m, or MM/YYYY";
      errorContainer.style.color = "#dc3545";
    }

    errorContainer.textContent = message;

    if (isValid) {
      this.classList.remove("error");
    } else {
      this.classList.add("error");
    }
  }

  // Add real-time validation for spouse dependents
  const spouseDependentsInput = document.querySelector(
    '[name="spouse_dependents"]'
  );
  if (spouseDependentsInput) {
    spouseDependentsInput.addEventListener("input", function () {
      const value = parseInt(this.value) || 0;
      const errorContainer = document.getElementById(
        "spouseDependentsValidationMessage"
      );
      if (value < 0) {
        errorContainer.textContent = "Number of dependents cannot be negative.";
        this.classList.add("error");
      } else {
        errorContainer.textContent = "";
        this.classList.remove("error");
      }
    });
  }

  // Add real-time validation for voter registration requirement
  const voterRegistrationSelect = document.querySelector('[name="reg_voter"]');
  if (voterRegistrationSelect) {
    console.log(
      "Voter registration select field found, attaching change listener in initializeForm"
    );
    // Remove any previous listener first
    voterRegistrationSelect.removeEventListener(
      "change",
      handleVoterRegistrationChange
    );
    // Attach the handler
    voterRegistrationSelect.addEventListener(
      "change",
      handleVoterRegistrationChange
    );
  } else {
    console.warn(
      "Voter registration select field not found during initialization"
    );
  }

  // Add form submission validation
  const registrationForm = document.getElementById("registrationForm");
  if (registrationForm) {
    registrationForm.addEventListener("submit", function (e) {
      // Only validate email on initial step
      const currentStep =
        new URLSearchParams(window.location.search).get("step") || "1";

      // Email validation is only on step 1 (personal information)
      if (currentStep === "1" && !validateEmailBeforeSubmit()) {
        e.preventDefault();
        // Focus on email field for better UX
        const emailInput = document.getElementById("email");
        if (emailInput) {
          emailInput.focus();
          emailInput.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        return false;
      }

      // Validate registered voter status on step 1
      if (currentStep === "1" && !validateRegisteredVoterStatus()) {
        e.preventDefault();
        return false;
      }

      // Validate occupation on step 1
      if (currentStep === "1" && !validateOccupationBeforeSubmit()) {
        e.preventDefault();
        return false;
      }

      // Validate residency on step 1
      if (currentStep === "1" && !validateResidencyBeforeSubmit()) {
        e.preventDefault();
        return false;
      }

      // Validate spouse occupation on spouse step (step 4 for married/separated/live-in)
      const civilStatus = document.querySelector(
        '[name="civil_status"]'
      )?.value;
      const isSpouseStep =
        civilStatus &&
        civilStatus !== "Single" &&
        civilStatus !== "Widowed" &&
        currentStep === "4";
      if (isSpouseStep && !validateSpouseOccupationBeforeSubmit()) {
        e.preventDefault();
        return false;
      }
    });
  }
}

/**
 * Initialize occupation dropdown functionality
 */
function initializeOccupationDropdowns() {
  // Handle main occupation dropdown
  const occupationSelect = document.getElementById("occupation");
  const customOccupationField = document.getElementById(
    "customOccupationField"
  );
  const customOccupationInput = document.getElementById("occupation_custom");

  if (occupationSelect && customOccupationField && customOccupationInput) {
    // Show custom field if "custom" is already selected (from saved data)
    if (occupationSelect.value === "custom") {
      customOccupationField.style.display = "block";
    }

    occupationSelect.addEventListener("change", function () {
      if (this.value === "custom") {
        customOccupationField.style.display = "block";
        customOccupationInput.focus();
        // Clear any previous error states
        customOccupationInput.classList.remove("error", "is-invalid");
      } else {
        customOccupationField.style.display = "none";
        customOccupationInput.value = "";
        // Clear error states when hiding
        customOccupationInput.classList.remove("error", "is-invalid");
      }
    });

    // Validate custom occupation input
    customOccupationInput.addEventListener("blur", function () {
      if (occupationSelect.value === "custom" && this.value.trim() === "") {
        this.classList.add("error", "is-invalid");
        showOccupationValidationMessage(
          "Please specify your occupation",
          false
        );
      } else if (this.value.trim() !== "") {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showOccupationValidationMessage("", true);
      }
    });

    customOccupationInput.addEventListener("input", function () {
      if (this.value.trim() !== "" && this.classList.contains("error")) {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showOccupationValidationMessage("", true);
      }
    });
  }

  // Handle spouse occupation dropdown
  const spouseOccupationSelect = document.getElementById("spouse_occupation");
  const customSpouseOccupationField = document.getElementById(
    "customSpouseOccupationField"
  );
  const customSpouseOccupationInput = document.getElementById(
    "spouse_occupation_custom"
  );

  if (
    spouseOccupationSelect &&
    customSpouseOccupationField &&
    customSpouseOccupationInput
  ) {
    // Show custom field if "custom" is already selected (from saved data)
    if (spouseOccupationSelect.value === "custom") {
      customSpouseOccupationField.style.display = "block";
    }

    spouseOccupationSelect.addEventListener("change", function () {
      if (this.value === "custom") {
        customSpouseOccupationField.style.display = "block";
        customSpouseOccupationInput.focus();
        // Clear any previous error states
        customSpouseOccupationInput.classList.remove("error", "is-invalid");
      } else {
        customSpouseOccupationField.style.display = "none";
        customSpouseOccupationInput.value = "";
        // Clear error states when hiding
        customSpouseOccupationInput.classList.remove("error", "is-invalid");
      }
    });

    // Validate custom spouse occupation input
    customSpouseOccupationInput.addEventListener("blur", function () {
      if (
        spouseOccupationSelect.value === "custom" &&
        this.value.trim() === ""
      ) {
        this.classList.add("error", "is-invalid");
        showSpouseOccupationValidationMessage(
          "Please specify spouse's occupation",
          false
        );
      } else if (this.value.trim() !== "") {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showSpouseOccupationValidationMessage("", true);
      }
    });

    customSpouseOccupationInput.addEventListener("input", function () {
      if (this.value.trim() !== "" && this.classList.contains("error")) {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showSpouseOccupationValidationMessage("", true);
      }
    });
  }
}

function showOccupationValidationMessage(message, isValid) {
  const messageContainer = document.getElementById(
    "occupationValidationMessage"
  );
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = isValid
      ? "validation-success"
      : "error-message";
    messageContainer.style.display = message ? "block" : "none";
  }
}

function showSpouseOccupationValidationMessage(message, isValid) {
  const messageContainer = document.getElementById(
    "spouseOccupationValidationMessage"
  );
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = isValid
      ? "validation-success"
      : "error-message";
    messageContainer.style.display = message ? "block" : "none";
  }
}

// Enhanced Residency Dropdown Handler
function initializeResidencyDropdown() {
  const residencySelect = document.getElementById("year_resident");
  const customResidencyField = document.getElementById("customResidencyField");
  const customResidencyInput = document.getElementById("year_resident_custom");

  if (residencySelect && customResidencyField && customResidencyInput) {
    // Show custom field if "custom" is already selected (from saved data)
    if (residencySelect.value === "custom") {
      customResidencyField.style.display = "block";
    }

    residencySelect.addEventListener("change", function () {
      if (this.value === "custom") {
        customResidencyField.style.display = "block";
        customResidencyInput.focus();
        // Clear any previous error states
        customResidencyInput.classList.remove("error", "is-invalid");
      } else if (this.value === "") {
        customResidencyField.style.display = "none";
        customResidencyInput.value = "";
        // Clear error states when hiding
        customResidencyInput.classList.remove("error", "is-invalid");
      } else {
        // Predefined option selected
        customResidencyField.style.display = "none";
        customResidencyInput.value = "";
        // Clear error states when hiding
        customResidencyInput.classList.remove("error", "is-invalid");
      }
    });

    // Validate custom residency input
    customResidencyInput.addEventListener("blur", function () {
      if (residencySelect.value === "custom" && this.value.trim() === "") {
        this.classList.add("error", "is-invalid");
        showResidencyValidationMessage(
          "Please specify your residency duration",
          false
        );
      } else if (this.value.trim() !== "") {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showResidencyValidationMessage("", true);
      }
    });

    customResidencyInput.addEventListener("input", function () {
      if (this.value.trim() !== "" && this.classList.contains("error")) {
        this.classList.remove("error", "is-invalid");
        this.classList.add("is-valid");
        showResidencyValidationMessage("", true);
      }
    });
  }
}

function showResidencyValidationMessage(message, isValid) {
  const messageContainer = document.getElementById("customResidencyMessage");
  if (messageContainer) {
    if (message) {
      messageContainer.textContent = message;
      messageContainer.className = isValid
        ? "validation-success"
        : "error-message";
      messageContainer.style.display = "block";
    } else if (isValid) {
      // Show format help when valid input
      messageContainer.innerHTML =
        "<strong>Valid format</strong> - Format: <strong>6m</strong> (months), <strong>2y</strong> (years), or <strong>1y6m</strong> (combined)";
      messageContainer.className = "validation-success";
      messageContainer.style.display = "block";
    } else {
      messageContainer.innerHTML =
        "Format: <strong>6m</strong> (months), <strong>2y</strong> (years), or <strong>1y6m</strong> (combined)";
      messageContainer.className = "field-help-text";
      messageContainer.style.display = "block";
    }
  }
}

/**
 * Validate occupation before form submission
 */
function validateOccupationBeforeSubmit() {
  const occupationSelect = document.getElementById("occupation");
  const customOccupationInput = document.getElementById("occupation_custom");

  if (!occupationSelect) return true; // No occupation field found, allow submission

  // Check if occupation is selected
  if (!occupationSelect.value || occupationSelect.value.trim() === "") {
    showOccupationValidationMessage("Please select your occupation", false);
    occupationSelect.focus();
    occupationSelect.scrollIntoView({ behavior: "smooth", block: "center" });
    return false;
  }

  // If custom occupation is selected, validate custom input
  if (occupationSelect.value === "custom") {
    if (
      !customOccupationInput ||
      !customOccupationInput.value ||
      customOccupationInput.value.trim() === ""
    ) {
      showOccupationValidationMessage("Please specify your occupation", false);
      if (customOccupationInput) {
        customOccupationInput.focus();
        customOccupationInput.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }
      return false;
    }

    // Update the select field value with custom occupation for form submission
    occupationSelect.value = customOccupationInput.value.trim();
  }

  return true;
}

// Validate residency before form submission
function validateResidencyBeforeSubmit() {
  const residencySelect = document.getElementById("year_resident");
  const customResidencyInput = document.getElementById("year_resident_custom");

  if (!residencySelect) return true; // No residency field found, allow submission

  // Check if residency is selected
  if (!residencySelect.value || residencySelect.value.trim() === "") {
    showResidencyValidationMessage(
      "Please select your length of residency",
      false
    );
    residencySelect.focus();
    residencySelect.scrollIntoView({ behavior: "smooth", block: "center" });
    return false;
  }

  // If custom residency is selected, validate custom input
  if (residencySelect.value === "custom") {
    if (
      !customResidencyInput ||
      !customResidencyInput.value ||
      customResidencyInput.value.trim() === ""
    ) {
      showResidencyValidationMessage(
        "Please specify your residency duration",
        false
      );
      if (customResidencyInput) {
        customResidencyInput.focus();
        customResidencyInput.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }
      return false;
    }

    // Update the select field value with custom residency for form submission
    residencySelect.value = customResidencyInput.value.trim();
  }

  return true;
}

/**
 * Validate spouse occupation before form submission
 */
function validateSpouseOccupationBeforeSubmit() {
  const spouseOccupationSelect = document.getElementById("spouse_occupation");
  const customSpouseOccupationInput = document.getElementById(
    "spouse_occupation_custom"
  );

  if (!spouseOccupationSelect) return true; // No spouse occupation field found, allow submission

  // Spouse occupation might not be required for all civil statuses
  const civilStatus = document.querySelector('[name="civil_status"]')?.value;
  if (!civilStatus || civilStatus === "Single" || civilStatus === "Widowed") {
    return true; // Not applicable for single/widowed
  }

  // Check if spouse occupation is selected (make it optional for now)
  if (
    spouseOccupationSelect.value &&
    spouseOccupationSelect.value === "custom"
  ) {
    if (
      !customSpouseOccupationInput ||
      !customSpouseOccupationInput.value ||
      customSpouseOccupationInput.value.trim() === ""
    ) {
      showSpouseOccupationValidationMessage(
        "Please specify spouse's occupation",
        false
      );
      if (customSpouseOccupationInput) {
        customSpouseOccupationInput.focus();
        customSpouseOccupationInput.scrollIntoView({
          behavior: "smooth",
          block: "center",
        });
      }
      return false;
    }

    // Update the select field value with custom occupation for form submission
    spouseOccupationSelect.value = customSpouseOccupationInput.value.trim();
  }

  return true;
}

/**
 * Validate that user is a registered voter in Calamba
 * Only allows form progression if voter is from Calamba
 * Shows modal for non-Calamba voters and non-voters
 */
function validateRegisteredVoterStatus() {
  const regVoterSelect = document.querySelector('[name="reg_voter"]');

  if (!regVoterSelect) {
    console.warn("Voter registration select field not found");
    return true; // Field not found, allow submission
  }

  const regVoterValue = regVoterSelect.value;
  console.log("Voter registration value:", regVoterValue);

  // Show modal if: voter but not in Calamba OR not a voter
  if (regVoterValue === "yes_not_calamba" || regVoterValue === "no") {
    console.log("Showing voter requirement modal for value:", regVoterValue);

    // Show modal instead of alert
    showVoterRequirementModal();

    // Highlight the field
    regVoterSelect.classList.add("error");
    regVoterSelect.setAttribute("aria-invalid", "true");
    regVoterSelect.style.borderColor = "#dc3545";
    regVoterSelect.style.backgroundColor = "rgba(220, 53, 69, 0.05)";

    // Scroll to the field
    regVoterSelect.scrollIntoView({ behavior: "smooth", block: "center" });

    return false;
  }

  // If answer is "Yes, Voter of Calamba", allow submission
  console.log("Voter registration valid for value:", regVoterValue);
  regVoterSelect.classList.remove("error");
  regVoterSelect.removeAttribute("aria-invalid");
  regVoterSelect.style.borderColor = "";
  regVoterSelect.style.backgroundColor = "";

  return true;
}

// Function to show voter requirement modal
function showVoterRequirementModal() {
  try {
    const modalElement = document.getElementById("voterRequirementModal");
    if (!modalElement) {
      console.error("Voter requirement modal element not found!");
      return false;
    }

    // Check if Bootstrap is available
    if (typeof bootstrap === "undefined") {
      console.error("Bootstrap is not loaded!");
      alert(
        "Error: Bootstrap modal library not loaded. Please refresh the page."
      );
      return false;
    }

    // Create and show the modal
    const voterModal = new bootstrap.Modal(modalElement);
    voterModal.show();
    console.log("Voter requirement modal shown successfully");
    return true;
  } catch (error) {
    console.error("Error showing voter requirement modal:", error);
    alert(
      "Registration requirement: You must be a registered voter of Calamba City to proceed."
    );
    return false;
  }
}

// Format number with commas
function formatNumberWithCommas(value) {
  let cleanValue = value.replace(/[^0-9.]/g, "");
  const parts = cleanValue.split(".");
  let integerPart = parts[0];
  const decimalPart = parts[1] ? "." + parts[1].slice(0, 2) : "";
  integerPart = integerPart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  return integerPart + decimalPart;
}

// Parse formatted number to float for calculations
function parseFormattedNumber(value) {
  // Handle null, undefined, or non-string values
  if (!value || typeof value !== "string") {
    return 0;
  }
  return parseFloat(value.replace(/,/g, "")) || 0;
}

// Validate Facebook link
function validateFacebookLink(input, errorContainerId) {
  const fbLink = input.value.trim();
  const errorContainer = document.getElementById(errorContainerId);
  const fbRegex =
    /^(https?:\/\/(www\.)?facebook\.com\/[a-zA-Z0-9][a-zA-Z0-9\.\-]{3,}[a-zA-Z0-9]\/?(\?.*)?)?$/;

  if (fbLink && !fbRegex.test(fbLink)) {
    errorContainer.textContent =
      "Please enter a valid Facebook profile URL in the format https://www.facebook.com/username to help us connect with you.";
    input.classList.add("error");
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", errorContainerId);
    return false;
  } else {
    errorContainer.textContent = "";
    input.classList.remove("error");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    return true;
  }
}

// Validate email format
function validateEmail(email, input, errorContainerId) {
  const emailRegex = /^\S+@\S+\.\S+$/;
  const errorContainer = document.getElementById(errorContainerId);

  if (email && !emailRegex.test(email)) {
    errorContainer.textContent =
      "Please enter a valid email address (e.g., example@domain.com).";
    errorContainer.style.color = "#d32f2f";
    input.classList.add("error");
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", errorContainerId);
    return false;
  } else if (email) {
    // Check for common typos in email domains
    const typoSuggestion = checkEmailTypos(email);
    if (typoSuggestion) {
      errorContainer.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Did you mean <strong>${typoSuggestion}</strong>? <a href="#" onclick="fixEmailTypo('${input.id}', '${typoSuggestion}'); return false;" style="color: #1b5e20; text-decoration: underline;">Use this</a>`;
      errorContainer.style.color = "#f57c00";
      input.classList.add("warning");
      input.setAttribute("aria-invalid", "true");
      input.setAttribute("aria-describedby", errorContainerId);
      return false;
    } else {
      errorContainer.textContent = "";
      errorContainer.style.color = "";
      input.classList.remove("error");
      input.classList.remove("warning");
      input.removeAttribute("aria-invalid");
      input.removeAttribute("aria-describedby");
      return true;
    }
  } else {
    errorContainer.textContent = "";
    errorContainer.style.color = "";
    input.classList.remove("error");
    input.classList.remove("warning");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    return true;
  }
}

// Check for common email typos and suggest corrections
function checkEmailTypos(email) {
  const parts = email.toLowerCase().split("@");
  if (parts.length !== 2) return null;

  const domain = parts[1];
  const username = parts[0];

  // Common email provider typos
  const commonDomains = {
    // Gmail typos
    "gamil.com": "gmail.com",
    "gmial.com": "gmail.com",
    "gmai.com": "gmail.com",
    "gmil.com": "gmail.com",
    "gmaill.com": "gmail.com",
    "gmeil.com": "gmail.com",
    "gnail.com": "gmail.com",
    "gmal.com": "gmail.com",
    "gmali.com": "gmail.com",
    "gmsil.com": "gmail.com",
    "gimail.com": "gmail.com",
    "gemail.com": "gmail.com",

    // Yahoo typos
    "yaho.com": "yahoo.com",
    "yahooo.com": "yahoo.com",
    "yhoo.com": "yahoo.com",
    "yahho.com": "yahoo.com",
    "yhaoo.com": "yahoo.com",
    "yaoo.com": "yahoo.com",

    // Outlook/Hotmail typos
    "hotmial.com": "hotmail.com",
    "hotmil.com": "hotmail.com",
    "hotmai.com": "hotmail.com",
    "hotnail.com": "hotmail.com",
    "outlok.com": "outlook.com",
    "outloo.com": "outlook.com",
    "outlok.com": "outlook.com",

    // Other common providers
    "ymail.com": "yahoo.com",
    "rocketmail.com": "yahoo.com",
  };

  // Check if domain has a typo
  if (commonDomains[domain]) {
    return username + "@" + commonDomains[domain];
  }

  // Check for missing TLD (.com, .net, etc.)
  if (!domain.includes(".")) {
    if (domain === "gmail" || domain === "gmial" || domain === "gamil") {
      return username + "@gmail.com";
    } else if (domain === "yahoo" || domain === "yaho") {
      return username + "@yahoo.com";
    } else if (domain === "hotmail") {
      return username + "@hotmail.com";
    } else if (domain === "outlook") {
      return username + "@outlook.com";
    }
  }

  return null;
}

// Fix email typo by applying suggestion
function fixEmailTypo(inputId, suggestedEmail) {
  const input = document.getElementById(inputId);
  const errorContainerId =
    inputId === "email"
      ? "emailValidationMessage"
      : "spouseEmailValidationMessage";
  const errorContainer = document.getElementById(errorContainerId);

  if (input) {
    input.value = suggestedEmail;
    errorContainer.innerHTML =
      '<i class="fas fa-check-circle"></i> Email corrected!';
    errorContainer.style.color = "#2e7d32";
    input.classList.remove("error");
    input.classList.remove("warning");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");

    // Clear success message after 3 seconds
    setTimeout(() => {
      errorContainer.textContent = "";
      errorContainer.style.color = "";
    }, 3000);
  }
}

// Validate phone number format
function validatePhoneNumber(phone, input, errorContainerId) {
  const phoneRegex = /^\d{11}$/;
  const errorContainer = document.getElementById(errorContainerId);
  if (phone && !phoneRegex.test(phone)) {
    errorContainer.textContent = "Please enter a valid 11-digit phone number.";
    input.classList.add("error");
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", errorContainerId);
    return false;
  } else {
    errorContainer.textContent = "";
    input.classList.remove("error");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    return true;
  }
}

// Validate financial input
function validateFinancialInput(input) {
  const value = input.value.trim();
  const parsed = parseFormattedNumber(value);
  const errorContainer = document.getElementById(`${input.name}-error`);
  const isExpenditure = input.classList.contains("expenditure-amount");
  const minValue = isExpenditure ? 500 : 100;
  const typeName = isExpenditure ? "expenditure" : "income";

  if (value === "") {
    errorContainer.textContent = `Please enter a value for this ${typeName}.`;
    input.classList.add("error");
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", `${input.name}-error`);
    return false;
  } else if (isNaN(parsed) || parsed < minValue) {
    errorContainer.textContent = `Please note: This ${typeName} value must be at least ₱${minValue.toLocaleString()}. Adjust it for accurate calculations.`;
    input.classList.add("error");
    input.setAttribute("aria-invalid", "true");
    input.setAttribute("aria-describedby", `${input.name}-error`);
    return false;
  } else {
    errorContainer.textContent = "";
    input.classList.remove("error");
    input.removeAttribute("aria-invalid");
    input.removeAttribute("aria-describedby");
    return true;
  }
}

// Calculate age based on birthday with current date (2025-10-19)
function calculateAge() {
  const birthdayInput = document.getElementById("birthday");
  const ageInput = document.getElementById("age");
  const messageContainer = document.getElementById("birthdayValidationMessage");

  if (birthdayInput && ageInput && messageContainer) {
    if (birthdayInput.value) {
      const birthDate = new Date(birthdayInput.value);
      if (isNaN(birthDate.getTime())) {
        ageInput.value = "";
        messageContainer.textContent =
          "Please enter a valid date in YYYY-MM-DD format.";
        birthdayInput.classList.add("error");
        birthdayInput.setAttribute("aria-invalid", "true");
        birthdayInput.setAttribute(
          "aria-describedby",
          "birthdayValidationMessage"
        );
        return;
      }

      const today = new Date(2025, 9, 19);
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();

      if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
      ) {
        age--;
      }

      ageInput.value = age;

      if (age < 21) {
        messageContainer.textContent =
          "Please note: You must be at least 21 years old to register. If this is incorrect, please verify your birthday.";
        birthdayInput.classList.add("error");
        birthdayInput.setAttribute("aria-invalid", "true");
        birthdayInput.setAttribute(
          "aria-describedby",
          "birthdayValidationMessage"
        );
      } else {
        messageContainer.textContent = "";
        birthdayInput.classList.remove("error");
        birthdayInput.removeAttribute("aria-invalid");
        birthdayInput.removeAttribute("aria-describedby");
      }
    } else {
      ageInput.value = "";
      messageContainer.textContent = "";
      birthdayInput.classList.remove("error");
      birthdayInput.removeAttribute("aria-invalid");
      birthdayInput.removeAttribute("aria-describedby");
    }
  }
}

// Calculate spouse age with current date
function calculateSpouseAge() {
  const birthdayInput = document.getElementById("spouse_birthday");
  const ageInput = document.getElementById("spouse_age");
  const messageContainer = document.getElementById(
    "spouseBirthdayValidationMessage"
  );

  if (birthdayInput && ageInput) {
    if (birthdayInput.value) {
      const birthDate = new Date(birthdayInput.value);
      if (isNaN(birthDate.getTime())) {
        ageInput.value = "";
        if (messageContainer) {
          messageContainer.textContent =
            "Please enter a valid date in DD/MM/YYYY format for your spouse.";
        }
        birthdayInput.classList.add("error");
        birthdayInput.setAttribute("aria-invalid", "true");
        birthdayInput.setAttribute(
          "aria-describedby",
          "spouseBirthdayValidationMessage"
        );
        return;
      }

      const today = new Date(2025, 9, 19);
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();

      if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
      ) {
        age--;
      }

      ageInput.value = age;

      // No age validation - just calculate and display
      if (messageContainer) {
        messageContainer.textContent = "";
      }
      birthdayInput.classList.remove("error");
      birthdayInput.removeAttribute("aria-invalid");
      birthdayInput.removeAttribute("aria-describedby");
    } else {
      ageInput.value = "";
      if (messageContainer) {
        messageContainer.textContent = "";
      }
      birthdayInput.classList.remove("error");
      birthdayInput.removeAttribute("aria-invalid");
      birthdayInput.removeAttribute("aria-describedby");
    }
  }
}

// Toggle spouse section
function toggleSpouseSection() {
  const civilStatus = document.getElementById("civil_status")?.value || "";
  const spouseSection = document.getElementById("spouse_section");

  if (spouseSection) {
    const shouldShow = civilStatus !== "Single" && civilStatus !== "Widowed";
    spouseSection.classList.toggle("hidden", !shouldShow);
    const spouseFields = spouseSection.querySelectorAll("input, select");
    const requiredFields = [
      "spouse_first_name",
      "spouse_last_name",
      "spouse_birthday",
      "spouse_age",
      "spouse_occupation",
      "spouse_dependents",
      "spouse_birth_place",
    ];
    spouseFields.forEach((field) => {
      field.required = shouldShow && requiredFields.includes(field.name);
      if (!shouldShow) {
        field.value = "";
        field.classList.remove("error");
        field.removeAttribute("aria-invalid");
        field.removeAttribute("aria-describedby");
        const errorContainer =
          document.getElementById(`${field.name}-error`) ||
          document.getElementById("spouseBirthdayValidationMessage") ||
          document.getElementById("spouseFbAccountValidationMessage") ||
          document.getElementById("spouseContactValidationMessage") ||
          document.getElementById("spouseEmailValidationMessage") ||
          document.getElementById("spouseDependentsValidationMessage");
        if (errorContainer) {
          errorContainer.textContent = "";
        }
      }
    });
  }
}

// Initialize spouse section
function initializeSpouseSection() {
  toggleSpouseSection();
}

// Initialize financial sections
function initializeFinancialSections() {
  const incomeSourcesCheckboxes = {
    income_business: "income_business_field",
    income_salary: "income_salary_field",
    income_remittance: "income_remittance_field",
    income_other: "income_other_field",
    income_business2: "income_business2_field",
    income_salary2: "income_salary2_field",
  };

  Object.entries(incomeSourcesCheckboxes).forEach(([checkboxId, fieldId]) => {
    const checkbox = document.getElementById(checkboxId);
    const field = document.getElementById(fieldId);

    if (checkbox && field) {
      checkbox.addEventListener("change", function () {
        field.classList.toggle("hidden", !this.checked);
        if (!this.checked) {
          const inputField = field.querySelector("input");
          inputField.value = "";
          const errorContainer = document.getElementById(
            `${inputField.name}-error`
          );
          errorContainer.textContent = "";
          inputField.classList.remove("error");
          inputField.removeAttribute("aria-invalid");
          inputField.removeAttribute("aria-describedby");
        }
        calculateTotals();
      });
    }
  });

  const expenditureCheckboxes = {
    exp_food: "exp_food_field",
    exp_electricity: "exp_electricity_field",
    exp_water: "exp_water_field",
    exp_internet: "exp_internet_field",
    exp_gas: "exp_gas_field",
    exp_education: "exp_education_field",
    exp_car: "exp_car_field",
    exp_insurance: "exp_insurance_field",
    exp_other: "exp_other_field",
  };

  Object.entries(expenditureCheckboxes).forEach(([checkboxId, fieldId]) => {
    const checkbox = document.getElementById(checkboxId);
    const field = document.getElementById(fieldId);

    if (checkbox && field) {
      checkbox.addEventListener("change", function () {
        field.classList.toggle("hidden", !this.checked);
        if (!this.checked) {
          const inputField = field.querySelector("input");
          inputField.value = "";
          const errorContainer = document.getElementById(
            `${inputField.name}-error`
          );
          errorContainer.textContent = "";
          inputField.classList.remove("error");
          inputField.removeAttribute("aria-invalid");
          inputField.removeAttribute("aria-describedby");
        }
        calculateTotals();
      });
    }
  });

  document
    .querySelectorAll(".income-amount, .expenditure-amount")
    .forEach((input) => {
      input.addEventListener("input", function () {
        const cursorPosition = input.selectionStart;
        const oldLength = input.value.length;
        input.value = formatNumberWithCommas(input.value);
        const newLength = input.value.length;
        input.setSelectionRange(
          cursorPosition + (newLength - oldLength),
          cursorPosition + (newLength - oldLength)
        );
        validateFinancialInput(this);
        calculateTotals();
      });
    });

  const expectedAmortInput = document.querySelector(
    '[name="expected_monthly_amortization"]'
  );
  if (expectedAmortInput) {
    expectedAmortInput.addEventListener("input", function () {
      const cursorPosition = this.selectionStart;
      const oldLength = this.value.length;
      this.value = formatNumberWithCommas(this.value);
      const newLength = this.value.length;
      this.setSelectionRange(
        cursorPosition + (newLength - oldLength),
        cursorPosition + (newLength - oldLength)
      );
      calculateTotals();
    });
  }
}

// Calculate financial totals
function calculateTotals() {
  let totalIncome = 0;
  let allIncomeValid = true;
  document.querySelectorAll(".income-amount").forEach((input) => {
    if (input.closest(".financial-input")?.classList.contains("hidden")) return;
    if (!validateFinancialInput(input)) {
      allIncomeValid = false;
    } else {
      const value = parseFormattedNumber(input.value);
      totalIncome += value;
    }
  });
  const netIncomeInput = document.querySelector('[name="net_income"]');
  if (netIncomeInput) {
    netIncomeInput.value = allIncomeValid
      ? formatNumberWithCommas(totalIncome.toFixed(2))
      : "";
  }

  let totalExpenditures = 0;
  let allExpendituresValid = true;
  document.querySelectorAll(".expenditure-amount").forEach((input) => {
    if (input.closest(".financial-input")?.classList.contains("hidden")) return;
    if (!validateFinancialInput(input)) {
      allExpendituresValid = false;
    } else {
      const value = parseFormattedNumber(input.value);
      totalExpenditures += value;
    }
  });
  const expendituresInput = document.querySelector('[name="expenditures"]');
  if (expendituresInput) {
    expendituresInput.value = allExpendituresValid
      ? formatNumberWithCommas(totalExpenditures.toFixed(2))
      : "";
  }

  const expectedAmortizationInput = document.querySelector(
    '[name="expected_monthly_amortization"]'
  );
  const expectedAmortization = expectedAmortizationInput
    ? parseFormattedNumber(expectedAmortizationInput.value)
    : 0;

  const remainingIncome =
    totalIncome - totalExpenditures - expectedAmortization;
  const remainingIncomeInput = document.querySelector(
    '[name="remaining_income"]'
  );
  if (remainingIncomeInput) {
    remainingIncomeInput.value =
      allIncomeValid && allExpendituresValid
        ? formatNumberWithCommas(remainingIncome.toFixed(2))
        : "";
  }
}

// Initialize address combining functionality
function initializeAddressFunctions() {
  const resInputs = [
    "res_house_no",
    "res_street",
    "res_subdivision",
    "res_barangay",
  ];
  resInputs.forEach((id) => {
    const input = document.querySelector(`[name="${id}"]`);
    if (input) {
      input.addEventListener("input", updateResidentialAddress);
      input.addEventListener("change", updateResidentialAddress);
    }
  });

  const busInputs = [
    "bus_bldg_no",
    "bus_street",
    "bus_subdivision",
    "bus_barangay",
  ];
  busInputs.forEach((id) => {
    const input = document.querySelector(`[name="${id}"]`);
    if (input) {
      input.addEventListener("input", updateBusinessAddress);
      input.addEventListener("change", updateBusinessAddress);
    }
  });
}

// Attach address update listeners to barangay dropdowns after they're populated
function attachBarangayListeners() {
  const resBarangaySelect = document.getElementById("res_barangay_select");
  const busBarangaySelect = document.getElementById("bus_barangay_select");

  if (resBarangaySelect) {
    resBarangaySelect.addEventListener("change", updateResidentialAddress);
  }
  if (busBarangaySelect) {
    busBarangaySelect.addEventListener("change", updateBusinessAddress);
  }
}

// Update residential address
function updateResidentialAddress() {
  const houseNo =
    document.querySelector('[name="res_house_no"]')?.value.trim() || "";
  const street =
    document.querySelector('[name="res_street"]')?.value.trim() || "";
  const subdivision =
    document.querySelector('[name="res_subdivision"]')?.value.trim() || "";
  const barangay =
    document.querySelector('[name="res_barangay"]')?.value.trim() || "";

  let completeAddress = "";
  if (houseNo) completeAddress += houseNo;
  if (street) completeAddress += completeAddress ? " " + street : street;
  if (subdivision)
    completeAddress += completeAddress ? ", " + subdivision : subdivision;
  if (barangay)
    completeAddress += completeAddress
      ? ", Brgy. " + barangay
      : "Brgy. " + barangay;
  if (completeAddress) completeAddress += ", Calamba City, Laguna";

  const resAddressInput = document.querySelector('[name="res_address"]');
  if (resAddressInput) {
    resAddressInput.value = completeAddress;
  }
}

// Update business address
function updateBusinessAddress() {
  const buildingNo =
    document.querySelector('[name="bus_bldg_no"]')?.value.trim() || "";
  const street =
    document.querySelector('[name="bus_street"]')?.value.trim() || "";
  const subdivision =
    document.querySelector('[name="bus_subdivision"]')?.value.trim() || "";
  const barangay =
    document.querySelector('[name="bus_barangay"]')?.value.trim() || "";

  let completeAddress = "";
  if (buildingNo) completeAddress += buildingNo;
  if (street) completeAddress += completeAddress ? " " + street : street;
  if (subdivision)
    completeAddress += completeAddress ? ", " + subdivision : subdivision;
  if (barangay)
    completeAddress += completeAddress
      ? ", Brgy. " + barangay
      : "Brgy. " + barangay;
  if (completeAddress) completeAddress += ", Calamba City, Laguna";

  const busAddressInput = document.querySelector('[name="bus_address"]');
  if (busAddressInput) {
    busAddressInput.value = completeAddress;
  }
}

// Password validation
function validatePassword(password) {
  // Check all requirements
  const requirements = {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    number: /\d/.test(password),
    special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password),
  };

  // Update requirement indicators if they exist (registration page)
  if (document.getElementById("req-length")) {
    document
      .getElementById("req-length")
      .classList.toggle("met", requirements.length);
    document
      .getElementById("req-uppercase")
      .classList.toggle("met", requirements.uppercase);
    document
      .getElementById("req-lowercase")
      .classList.toggle("met", requirements.lowercase);
    document
      .getElementById("req-number")
      .classList.toggle("met", requirements.number);
    document
      .getElementById("req-special")
      .classList.toggle("met", requirements.special);
  }

  // Check if passwords match (registration page)
  if (document.getElementById("req-match")) {
    const passwordsMatch =
      password === (document.getElementById("confirm_password")?.value || "") &&
      password !== "";
    document
      .getElementById("req-match")
      .classList.toggle("met", passwordsMatch);
  }

  // Update strength bar if it exists (registration page)
  if (document.getElementById("strengthBar")) {
    const bar = document.getElementById("strengthBar");
    const text = document.getElementById("strengthText");
    let strength = Object.values(requirements).filter((req) => req).length;
    let strengthLevel = "Weak";
    let strengthClass = "strength-weak";

    if (strength >= 4) {
      strengthLevel = "Medium";
      strengthClass = "strength-medium";
      bar.style.width = "66%";
    } else if (strength >= 5) {
      strengthLevel = "Strong";
      strengthClass = "strength-strong";
      bar.style.width = "100%";
    } else {
      bar.style.width = "33%";
    }

    bar.className = "strength-bar-fill " + strengthClass;
    text.textContent = "Password strength: " + strengthLevel;
  }

  return true;
}

// Toggle password visibility
function togglePassword(fieldId) {
  const field = document.getElementById(fieldId);
  const iconId = fieldId === "password" ? "toggleIcon1" : "toggleIcon2";
  const icon = document.getElementById(iconId);

  if (field.type === "password") {
    field.type = "text";
    if (icon) {
      icon.classList.remove("fa-eye");
      icon.classList.add("fa-eye-slash");
    }
  } else {
    field.type = "password";
    if (icon) {
      icon.classList.remove("fa-eye-slash");
      icon.classList.add("fa-eye");
    }
  }
}

// Validate password match
function validatePasswordMatch() {
  const password = document.getElementById("password")?.value;
  const confirmPassword = document.getElementById("confirm_password")?.value;
  const messageContainer = document.getElementById("passwordMatchMessage");

  // Update strength indicators if they exist
  if (document.getElementById("req-match")) {
    const passwordsMatch = password === confirmPassword && password !== "";
    document
      .getElementById("req-match")
      .classList.toggle("met", passwordsMatch);
  }

  if (messageContainer && password !== confirmPassword && confirmPassword) {
    messageContainer.textContent =
      "Passwords do not match. Please re-enter them to ensure they are identical.";
    return false;
  } else if (messageContainer) {
    messageContainer.textContent = "";
  }
  return true;
}

// Validate email match
function validateEmailMatch() {
  const email = document.getElementById("email")?.value.trim().toLowerCase();
  const confirmEmail = document
    .getElementById("confirm_email")
    ?.value.trim()
    .toLowerCase();
  const messageContainer = document.getElementById(
    "confirmEmailValidationMessage"
  );

  if (!messageContainer) return true;

  // If confirm email is empty, don't show error yet
  if (!confirmEmail) {
    messageContainer.textContent = "";
    messageContainer.className = "";
    messageContainer.style.color = "";
    const confirmInput = document.getElementById("confirm_email");
    if (confirmInput) {
      confirmInput.classList.remove(
        "error",
        "success",
        "is-invalid",
        "is-valid"
      );
    }
    return true;
  }

  if (email !== confirmEmail) {
    messageContainer.textContent =
      "Emails do not match. Please make sure both emails are identical.";
    messageContainer.className = "error-message";
    messageContainer.style.color = "";
    const confirmInput = document.getElementById("confirm_email");
    if (confirmInput) {
      confirmInput.classList.add("error", "is-invalid");
      confirmInput.classList.remove("success", "is-valid");
    }
    return false;
  } else {
    messageContainer.textContent = "Emails match!";
    messageContainer.className = "validation-success";
    messageContainer.style.color = "";
    const confirmInput = document.getElementById("confirm_email");
    if (confirmInput) {
      confirmInput.classList.remove("error", "is-invalid");
      confirmInput.classList.add("success", "is-valid");
    }
    return true;
  }
}

// Step-specific validation
function validateStep(step) {
  let isValid = true;
  let requiredFields = [];
  const civilStatus = document.getElementById("civil_status")?.value || "";
  const financialStep =
    civilStatus === "Single" || civilStatus === "Widowed" ? 4 : 5;
  const finalStep =
    civilStatus === "Single" || civilStatus === "Widowed" ? 5 : 6;

  switch (step) {
    case 1:
      requiredFields = document.querySelectorAll(
        "#registrationForm input[required], #registrationForm select[required]"
      );
      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          field.classList.add("error");
          isValid = false;
        } else {
          field.classList.remove("error");
        }
      });

      const ageInput = document.getElementById("age");
      if (ageInput && ageInput.value < 21) {
        document.getElementById("birthdayValidationMessage").textContent =
          "Please note: You must be at least 21 years old to register. If this is incorrect, please verify your birthday.";
        document.getElementById("birthday").classList.add("error");
        isValid = false;
      } else if (ageInput) {
        document.getElementById("birthdayValidationMessage").textContent = "";
        document.getElementById("birthday").classList.remove("error");
      }

      // Validate contact (required)
      const contactInput = document.querySelector('[name="contact"]');
      if (
        contactInput &&
        !validatePhoneNumber(
          contactInput.value,
          contactInput,
          "contactValidationMessage"
        )
      ) {
        isValid = false;
      }

      // Validate email (required)
      const emailInput = document.getElementById("email");
      if (
        emailInput &&
        !validateEmail(emailInput.value, emailInput, "emailValidationMessage")
      ) {
        isValid = false;
      }

      // Validate email match (required)
      if (!validateEmailMatch()) {
        isValid = false;
      }

      // Validate residency dropdown and custom input if needed
      const residencySelect = document.querySelector('[name="year_resident"]');
      const customResidencyInput = document.getElementById(
        "year_resident_custom"
      );

      if (residencySelect) {
        const value = residencySelect.value.trim();

        if (!value) {
          document.getElementById("yearResidentValidationMessage").textContent =
            "Please select your length of residency.";
          residencySelect.classList.add("error");
          isValid = false;
        } else if (value === "custom") {
          // Validate custom input
          const customValue = customResidencyInput?.value.trim();

          if (!customValue) {
            document.getElementById(
              "yearResidentValidationMessage"
            ).textContent = "Please enter your residency duration.";
            residencySelect.classList.add("error");
            // Don't add error to custom input if it's just empty - only on invalid format
            customResidencyInput?.classList.remove("error", "is-invalid");
            isValid = false;
          } else {
            // Validate format
            const numberRegex = /^\d+$/;
            const unitRegex = /^(\d+(?:\.\d+)?)\s*[ym]$/i;
            const combinedRegex =
              /^(\d+(?:\.\d+)?)\s*[ym]\s*(\d+(?:\.\d+)?)\s*[ym]$/i;
            const dateRegex = /^(0[1-9]|1[0-2])\/\d{4}$/;

            if (numberRegex.test(customValue)) {
              const months = parseInt(customValue);
              if (months > 1200) {
                document.getElementById(
                  "yearResidentValidationMessage"
                ).textContent = "Maximum 1200 months (100 years) allowed.";
                customResidencyInput.classList.add("error");
                isValid = false;
              } else {
                document.getElementById(
                  "yearResidentValidationMessage"
                ).textContent = "";
                residencySelect.classList.remove("error");
                customResidencyInput.classList.remove("error");
              }
            } else if (
              unitRegex.test(customValue) ||
              combinedRegex.test(customValue) ||
              dateRegex.test(customValue)
            ) {
              document.getElementById(
                "yearResidentValidationMessage"
              ).textContent = "";
              residencySelect.classList.remove("error");
              customResidencyInput.classList.remove("error");
            } else {
              document.getElementById(
                "yearResidentValidationMessage"
              ).textContent = "Invalid format. Use: 6m, 2y, 1y6m, or MM/YYYY.";
              customResidencyInput.classList.add("error");
              isValid = false;
            }
          }
        } else {
          // Valid predefined option selected
          document.getElementById("yearResidentValidationMessage").textContent =
            "";
          residencySelect.classList.remove("error");
        }
      }

      // Validate Facebook if filled
      const fbAccountInput = document.getElementById("fb_account");
      if (
        fbAccountInput &&
        fbAccountInput.value &&
        !validateFacebookLink(fbAccountInput, "fbAccountValidationMessage")
      ) {
        isValid = false;
      }
      break;

    case 2:
      requiredFields = document.querySelectorAll(
        "#registrationForm input[required], #registrationForm select[required]"
      );
      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          field.classList.add("error");
          isValid = false;
        } else {
          field.classList.remove("error");
        }
      });
      break;

    case 3:
      requiredFields = document.querySelectorAll(
        "#registrationForm select[required]"
      );
      requiredFields.forEach((field) => {
        if (!field.value.trim()) {
          field.classList.add("error");
          isValid = false;
        } else {
          field.classList.remove("error");
        }
      });
      break;

    case 4:
      if (civilStatus !== "Single" && civilStatus !== "Widowed") {
        const spouseRequiredFields =
          document.querySelectorAll(".spouse-required");
        spouseRequiredFields.forEach((field) => {
          if (!field.value.trim()) {
            field.classList.add("error");
            isValid = false;
          } else {
            field.classList.remove("error");
          }
        });

        // Validate spouse dependents (>=0)
        const spouseDependentsInput = document.querySelector(
          '[name="spouse_dependents"]'
        );
        if (spouseDependentsInput) {
          const spouseDependents = parseInt(spouseDependentsInput.value) || 0;
          if (spouseDependents < 0) {
            document.getElementById(
              "spouseDependentsValidationMessage"
            ).textContent =
              "Please enter a valid number of dependents (0 or greater).";
            spouseDependentsInput.classList.add("error");
            isValid = false;
          } else {
            document.getElementById(
              "spouseDependentsValidationMessage"
            ).textContent = "";
            spouseDependentsInput.classList.remove("error");
          }
        }

        // Spouse age validation removed - no minimum age requirement
        const spouseAgeInput = document.getElementById("spouse_age");
        if (spouseAgeInput) {
          document.getElementById(
            "spouseBirthdayValidationMessage"
          ).textContent = "";
          document.getElementById("spouse_birthday").classList.remove("error");
        }

        // Validate spouse contact if filled
        const spouseContactInput = document.querySelector(
          '[name="spouse_contact"]'
        );
        if (
          spouseContactInput &&
          spouseContactInput.value &&
          !validatePhoneNumber(
            spouseContactInput.value,
            spouseContactInput,
            "spouseContactValidationMessage"
          )
        ) {
          isValid = false;
        }

        // Validate spouse email if filled
        const spouseEmailInput = document.querySelector(
          '[name="spouse_email"]'
        );
        if (
          spouseEmailInput &&
          spouseEmailInput.value &&
          !validateEmail(
            spouseEmailInput.value,
            spouseEmailInput,
            "spouseEmailValidationMessage"
          )
        ) {
          isValid = false;
        }

        // Validate spouse Facebook if filled
        const spouseFbInput = document.getElementById("spouse_fb_account");
        if (
          spouseFbInput &&
          spouseFbInput.value &&
          !validateFacebookLink(
            spouseFbInput,
            "spouseFbAccountValidationMessage"
          )
        ) {
          isValid = false;
        }
      }
      break;

    case financialStep:
      let allFinancialValid = true;
      document
        .querySelectorAll(".income-amount, .expenditure-amount")
        .forEach((input) => {
          if (input.closest(".financial-input").classList.contains("hidden"))
            return;
          if (!validateFinancialInput(input)) {
            allFinancialValid = false;
          }
        });
      if (!allFinancialValid) isValid = false;

      const expectedAmortInput = document.querySelector(
        '[name="expected_monthly_amortization"]'
      );
      if (expectedAmortInput) {
        const expectedAmortization = parseFormattedNumber(
          expectedAmortInput.value
        );
        if (expectedAmortization < 0) {
          expectedAmortInput.classList.add("error");
          isValid = false;
        } else {
          expectedAmortInput.classList.remove("error");
        }
      }
      break;

    case finalStep:
      // Password validation is now handled by real-time oninput handlers
      // Just check that both fields are filled for final submission
      const password = document.getElementById("password")?.value;
      const confirmPassword =
        document.getElementById("confirm_password")?.value;

      if (!password || !confirmPassword) {
        if (!password) {
          document.getElementById("password").classList.add("error");
          isValid = false;
        }
        if (!confirmPassword) {
          document.getElementById("confirm_password").classList.add("error");
          isValid = false;
        }
      } else {
        document.getElementById("password").classList.remove("error");
        document.getElementById("confirm_password").classList.remove("error");
      }

      // Validate data privacy consent checkbox
      const dataPrivacyCheckbox = document.getElementById(
        "data_privacy_consent"
      );
      const dataPrivacyMessage = document.getElementById(
        "dataPrivacyValidationMessage"
      );
      if (dataPrivacyCheckbox && !dataPrivacyCheckbox.checked) {
        dataPrivacyMessage.textContent =
          "You must agree to the Data Privacy Policy to proceed.";
        dataPrivacyCheckbox.classList.add("error");
        isValid = false;
      } else if (dataPrivacyMessage) {
        dataPrivacyMessage.textContent = "";
        if (dataPrivacyCheckbox) {
          dataPrivacyCheckbox.classList.remove("error");
        }
      }
      break;
  }

  if (!isValid) {
    alert(
      "Please review the form: Some required fields are missing or invalid. Check the highlighted areas and try again."
    );
  }

  // SECURITY: Sanitize form inputs before submission
  if (isValid) {
    const registrationForm = document.getElementById("registrationForm");
    if (registrationForm) {
      sanitizeFormInputs(registrationForm);
    }
  }

  // Show loading indicator if validation passed
  if (isValid) {
    const loadingIndicator = document.getElementById("loadingIndicator");
    if (loadingIndicator) {
      loadingIndicator.style.display = "block";
    }
  }

  return isValid;
}

// ============================================================================
// REAL-TIME EMAIL VALIDATION - Check if email is already registered
// ============================================================================

let emailValidationTimeout;
let emailIsValid = false;

/**
 * Initialize email validation listener
 */
function initializeEmailValidation() {
  const emailInput = document.getElementById("email");

  if (emailInput) {
    // Add event listener for real-time validation on blur and change
    emailInput.addEventListener("blur", function () {
      validateEmailRealTime(this.value);
    });

    emailInput.addEventListener("change", function () {
      validateEmailRealTime(this.value);
    });

    // Validate on input with debouncing to avoid excessive requests
    emailInput.addEventListener("input", function () {
      clearTimeout(emailValidationTimeout);

      // Only validate if email is not empty and has @ symbol
      if (this.value.includes("@")) {
        emailValidationTimeout = setTimeout(() => {
          validateEmailRealTime(this.value);
        }, 500); // Wait 500ms after user stops typing
      } else {
        // Clear validation feedback if email is empty
        clearEmailValidationFeedback();
        emailIsValid = false;
      }
    });
  }
}

/**
 * Validate email in real-time against database
 */
function validateEmailRealTime(email) {
  if (!email || email.trim() === "") {
    clearEmailValidationFeedback();
    emailIsValid = false;
    return;
  }

  // Show loading state
  showEmailValidationLoading();

  // Create FormData for POST request
  const formData = new FormData();
  formData.append("email", email);

  // Fetch from validation endpoint
  fetch("validate_email.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error(
          "Validation request failed with status " + response.status
        );
      }
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        if (data.exists) {
          // Email is already registered
          showEmailValidationError(data.message);
          emailIsValid = false;
        } else {
          // Email is available
          showEmailValidationSuccess(data.message);
          emailIsValid = true;
        }
      } else {
        // Validation failed
        showEmailValidationError(data.message || "Unable to validate email");
        emailIsValid = false;
      }
    })
    .catch((error) => {
      console.error("Email validation error:", error);
      // Don't show error to user on network failures - just clear feedback
      clearEmailValidationFeedback();
      emailIsValid = false;
    });
}

/**
 * Show loading state for email validation
 */
function showEmailValidationLoading() {
  const emailInput = document.getElementById("email");

  if (emailInput) {
    emailInput.classList.remove("is-valid", "is-invalid");
    emailInput.classList.add("is-validating");
  }

  // Show simple loading message
  const messageContainer = document.getElementById("emailValidationMessage");
  if (messageContainer) {
    messageContainer.textContent = "Checking availability...";
    messageContainer.className = "field-help-text";
    messageContainer.style.display = "block";
    messageContainer.style.color = "#6c757d";
  } else {
    // If no existing container, create a simple one
    const simpleMessage = document.createElement("div");
    simpleMessage.id = "emailValidationMessage";
    simpleMessage.className = "field-help-text";
    simpleMessage.textContent = "Checking availability...";
    simpleMessage.style.marginTop = "5px";
    simpleMessage.style.color = "#6c757d";

    if (emailInput && emailInput.parentNode) {
      emailInput.parentNode.appendChild(simpleMessage);
    }
  }
}

/**
 * Show success state for email validation
 */
function showEmailValidationSuccess(message) {
  const emailInput = document.getElementById("email");

  if (emailInput) {
    emailInput.classList.remove("is-invalid", "is-validating");
    emailInput.classList.add("is-valid");
  }

  // Clear any existing feedback div
  clearEmailValidationFeedback();

  // Show simple success message
  const messageContainer = document.getElementById("emailValidationMessage");
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = "validation-success";
    messageContainer.style.display = "block";
  } else {
    // If no existing container, create a simple one
    const simpleMessage = document.createElement("div");
    simpleMessage.id = "emailValidationMessage";
    simpleMessage.className = "validation-success";
    simpleMessage.textContent = message;
    simpleMessage.style.marginTop = "5px";

    if (emailInput && emailInput.parentNode) {
      emailInput.parentNode.appendChild(simpleMessage);
    }
  }
}

/**
 * Show error state for email validation
 */
function showEmailValidationError(message) {
  const emailInput = document.getElementById("email");

  if (emailInput) {
    emailInput.classList.remove("is-valid", "is-validating");
    emailInput.classList.add("is-invalid");
  }

  // Clear any existing feedback div
  clearEmailValidationFeedback();

  // Show simple message in the existing validation message container
  const messageContainer = document.getElementById("emailValidationMessage");
  if (messageContainer) {
    messageContainer.textContent = message;
    messageContainer.className = "error-message";
    messageContainer.style.display = "block";
  } else {
    // If no existing container, create a simple one
    const simpleMessage = document.createElement("div");
    simpleMessage.id = "emailValidationMessage";
    simpleMessage.className = "error-message";
    simpleMessage.textContent = message;
    simpleMessage.style.marginTop = "5px";

    if (emailInput && emailInput.parentNode) {
      emailInput.parentNode.appendChild(simpleMessage);
    }
  }
}

/**
 * Clear email validation feedback
 */
function clearEmailValidationFeedback() {
  const emailInput = document.getElementById("email");

  if (emailInput) {
    emailInput.classList.remove("is-valid", "is-invalid", "is-validating");
  }

  // Clear simple message
  const messageContainer = document.getElementById("emailValidationMessage");
  if (messageContainer) {
    messageContainer.textContent = "";
    messageContainer.style.display = "none";
  }

  // Also clear the old feedback element if it exists
  const feedbackElement = document.getElementById("emailValidationFeedback");
  if (feedbackElement) {
    feedbackElement.style.display = "none";
    feedbackElement.innerHTML = "";
  }
}

/**
 * Get or create email feedback element
 */
function getOrCreateEmailFeedback() {
  let feedbackElement = document.getElementById("emailValidationFeedback");

  if (!feedbackElement) {
    const emailInput = document.getElementById("email");
    if (emailInput && emailInput.parentNode) {
      feedbackElement = document.createElement("div");
      feedbackElement.id = "emailValidationFeedback";
      feedbackElement.className = "email-feedback";
      feedbackElement.style.display = "none";
      feedbackElement.style.marginTop = "10px";
      feedbackElement.style.padding = "12px 16px";
      feedbackElement.style.borderRadius = "6px";
      // Insert right after the email input's parent div
      emailInput.parentNode.insertAdjacentElement("afterend", feedbackElement);
    }
  }

  return feedbackElement;
}

/**
 * Validate email before form submission
 * Returns true if email is valid and available
 */
function validateEmailBeforeSubmit() {
  const emailInput = document.getElementById("email");

  if (!emailInput) return true; // Allow submission if email field doesn't exist

  const email = emailInput.value.trim();

  if (!email) {
    showEmailValidationError("Email is required");
    return false;
  }

  if (!emailIsValid) {
    // Check if validation was done
    if (emailInput.classList.contains("is-valid")) {
      return true; // Email is valid
    } else if (emailInput.classList.contains("is-invalid")) {
      return false; // Email is invalid
    } else {
      // Validation hasn't been done yet, show error
      showEmailValidationError("Please verify your email address");
      return false;
    }
  }

  return true; // Email is valid
}

/**
 * Validate first name and last name in real-time
 * Accepts letters, spaces, hyphens, and apostrophes
 */
function validateName(fieldId, messageId, fieldType = "First Name") {
  const nameInput = document.getElementById(fieldId);
  const messageContainer = document.getElementById(messageId);

  if (!nameInput || !messageContainer) return;

  nameInput.addEventListener("input", function () {
    const name = this.value.trim();

    if (!name) {
      messageContainer.textContent = "";
      nameInput.classList.remove("error");
      nameInput.removeAttribute("aria-invalid");
      return;
    }

    // Pattern: Letters, spaces, hyphens, apostrophes (3-50 characters)
    const namePattern = /^[a-zA-Z\s'-]{3,50}$/;

    if (!namePattern.test(name)) {
      let errorMsg = "";
      if (name.length < 3) {
        errorMsg = `${fieldType} must be at least 3 characters long`;
      } else if (name.length > 50) {
        errorMsg = `${fieldType} cannot exceed 50 characters`;
      } else {
        errorMsg = `${fieldType} can only contain letters, spaces, hyphens, and apostrophes`;
      }

      messageContainer.textContent = errorMsg;
      nameInput.classList.add("error");
      nameInput.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "";
      nameInput.classList.remove("error");
      nameInput.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate contact number in real-time
 * Philippine format: 11 digits (0 followed by 10 digits)
 */
function validateContactNumber() {
  const contactInput = document.getElementById("contact");
  const messageContainer = document.getElementById("contactValidationMessage");

  if (!contactInput || !messageContainer) return;

  contactInput.addEventListener("input", function () {
    const contact = this.value.trim();

    if (!contact) {
      messageContainer.textContent = "";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
      return;
    }

    // Remove any non-digit characters for validation
    const digitsOnly = contact.replace(/\D/g, "");

    if (digitsOnly.length === 0) {
      messageContainer.textContent = "Please enter a valid phone number";
      contactInput.classList.add("error");
      contactInput.setAttribute("aria-invalid", "true");
      return;
    }

    // Philippine format: starts with 0 or 63, followed by 10 digits
    if (digitsOnly.length === 11 && digitsOnly.startsWith("0")) {
      messageContainer.textContent = "✓ Valid phone number";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
    } else if (digitsOnly.length === 12 && digitsOnly.startsWith("63")) {
      messageContainer.textContent = "✓ Valid international format";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
    } else {
      messageContainer.textContent = `Phone number must be 11 digits (0XXXXXXXXXX) or international format (63XXXXXXXXXX)`;
      contactInput.classList.add("error");
      contactInput.setAttribute("aria-invalid", "true");
    }
  });
}

/**
 * Enhanced birthday validation with age calculation
 * Prevents future dates and validates minimum age
 */
function enhancedBirthdayValidation(
  birthdayFieldId,
  ageFieldId,
  messageId,
  minimumAge = 21
) {
  const birthdayInput = document.getElementById(birthdayFieldId);
  const ageInput = document.getElementById(ageFieldId);
  const messageContainer = document.getElementById(messageId);

  if (!birthdayInput || !ageInput || !messageContainer) return;

  birthdayInput.addEventListener("change", function () {
    const birthdayValue = this.value;

    if (!birthdayValue) {
      ageInput.value = "";
      messageContainer.textContent = "";
      birthdayInput.classList.remove("error");
      birthdayInput.removeAttribute("aria-invalid");
      return;
    }

    const birthDate = new Date(birthdayValue);
    const today = new Date();

    // Validate date format
    if (isNaN(birthDate.getTime())) {
      messageContainer.textContent = "Please enter a valid date";
      birthdayInput.classList.add("error");
      birthdayInput.setAttribute("aria-invalid", "true");
      ageInput.value = "";
      return;
    }

    // Check if date is in future
    if (birthDate > today) {
      messageContainer.textContent = "Birthday cannot be in the future";
      birthdayInput.classList.add("error");
      birthdayInput.setAttribute("aria-invalid", "true");
      ageInput.value = "";
      return;
    }

    // Calculate age
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
      age--;
    }

    ageInput.value = age;

    // Validate minimum age
    if (age < minimumAge) {
      messageContainer.textContent = `You must be at least ${minimumAge} years old to register`;
      birthdayInput.classList.add("error");
      birthdayInput.setAttribute("aria-invalid", "true");
      messageContainer.classList.remove("validation-success");
      messageContainer.classList.add("error-message");
    } else if (age > 150) {
      messageContainer.textContent =
        "Please check your birthday - age seems unrealistic";
      birthdayInput.classList.add("error");
      birthdayInput.setAttribute("aria-invalid", "true");
      messageContainer.classList.remove("validation-success");
      messageContainer.classList.add("error-message");
    } else {
      messageContainer.textContent = "Valid age";
      birthdayInput.classList.remove("error");
      birthdayInput.removeAttribute("aria-invalid");
      messageContainer.classList.remove("error-message");
      messageContainer.classList.add("validation-success");
    }
  });
}

/**
 * Validate text field with minimum and maximum length in real-time
 * Used for birth_place, occupation, etc.
 */
function validateTextField(
  fieldId,
  messageId,
  minLength = 2,
  maxLength = 50,
  fieldType = "Field"
) {
  const textInput = document.getElementById(fieldId);
  const messageContainer = document.getElementById(messageId);

  if (!textInput || !messageContainer) return;

  textInput.addEventListener("input", function () {
    const text = this.value.trim();

    if (!text) {
      messageContainer.textContent = "";
      textInput.classList.remove("error");
      textInput.removeAttribute("aria-invalid");
      return;
    }

    // Check minimum length
    if (text.length < minLength) {
      messageContainer.textContent = `${fieldType} must be at least ${minLength} characters`;
      textInput.classList.add("error");
      textInput.setAttribute("aria-invalid", "true");
    } else if (text.length > maxLength) {
      messageContainer.textContent = `${fieldType} cannot exceed ${maxLength} characters`;
      textInput.classList.add("error");
      textInput.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "";
      textInput.classList.remove("error");
      textInput.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate email format in real-time with visual feedback
 */
function validateEmailFormat() {
  const emailInput = document.getElementById("confirm_email");
  const messageContainer = document.getElementById(
    "confirmEmailValidationMessage"
  );

  if (!emailInput || !messageContainer) return;

  emailInput.addEventListener("input", function () {
    const email = this.value.trim();

    if (!email) {
      messageContainer.textContent = "";
      emailInput.classList.remove("error");
      emailInput.removeAttribute("aria-invalid");
      return;
    }

    // Basic email validation regex
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(email)) {
      messageContainer.textContent = "Please enter a valid email address";
      emailInput.classList.add("error");
      emailInput.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "";
      emailInput.classList.remove("error");
      emailInput.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate years of residency in real-time
 * Accepts: number of years, or month/year format (MM/YYYY)
 */
function validateYearsOfResidency() {
  const residencyInput = document.getElementById("year_resident");
  const messageContainer = document.getElementById(
    "yearResidentValidationMessage"
  );

  if (!residencyInput || !messageContainer) return;

  residencyInput.addEventListener("input", function () {
    const value = this.value.trim().toLowerCase();
    const helpText = this.parentNode.querySelector(".field-help-text");

    if (!value) {
      messageContainer.textContent = "";
      residencyInput.classList.remove("error");
      residencyInput.removeAttribute("aria-invalid");
      if (helpText) helpText.style.display = "block";
      return;
    }

    let totalMonths = 0;
    let displayValue = null;
    let errorMsg = "";

    // Pattern 1: Just a number (MONTHS, not years!)
    if (/^\d+$/.test(value)) {
      const months = parseInt(value, 10);
      if (months < 0) {
        errorMsg = "Months of residency cannot be negative";
      } else if (months > 1200) {
        errorMsg = "Maximum 1200 months (100 years) allowed";
      } else {
        totalMonths = months;
        displayValue = `${months} month${months !== 1 ? "s" : ""}`;
      }
    }
    // Pattern 2: Flexible format with years/months (e.g., "2y", "3m", "2y3m", "2y 3m")
    else if (/^[\d.]+[ym](\s*[\d.]+[ym])?$/i.test(value)) {
      // Parse years and months
      const yearMatch = value.match(/([\d.]+)\s*y/i);
      const monthMatch = value.match(/([\d.]+)\s*m/i);

      const years = yearMatch ? parseFloat(yearMatch[1]) : 0;
      const months = monthMatch ? parseFloat(monthMatch[1]) : 0;

      // Convert to total months for validation
      totalMonths = Math.round(years * 12 + months);

      if (totalMonths < 0) {
        errorMsg = "Residency duration cannot be negative";
      } else if (totalMonths > 1200) {
        errorMsg = "Maximum 1200 months (100 years) allowed";
      } else if (totalMonths === 0) {
        errorMsg = "Please enter a valid duration (e.g., 2y, 3m, or 2y3m)";
      } else {
        // Display in user-friendly format showing years and months
        if (years > 0 && months > 0) {
          displayValue = `${Math.floor(years)}y ${Math.floor(months)}m`;
        } else if (years > 0) {
          displayValue = `${
            years === Math.floor(years) ? Math.floor(years) : years
          }y`;
        } else {
          displayValue = `${Math.floor(months)}m`;
        }
      }
    }
    // Pattern 3: Month/Year format (MM/YYYY or M/YYYY)
    else if (/^(0?[1-9]|1[0-2])\/\d{4}$/.test(value)) {
      const parts = value.split("/");
      const month = parseInt(parts[0], 10);
      const year = parseInt(parts[1], 10);

      // Validate month
      if (month < 1 || month > 12) {
        errorMsg = "Please enter a valid month (1-12)";
      } else {
        // Validate year (cannot be future)
        const currentDate = new Date();
        const currentYear = currentDate.getFullYear();

        if (year > currentYear) {
          errorMsg = "Year cannot be in the future";
        } else if (year < currentYear - 100) {
          errorMsg = "Year cannot be more than 100 years ago";
        } else {
          // Calculate months of residency
          let calculatedMonths = (currentYear - year) * 12;

          // Adjust based on months
          if (month > currentDate.getMonth() + 1) {
            calculatedMonths -= 12;
          } else {
            calculatedMonths += currentDate.getMonth() + 1 - month;
          }

          if (calculatedMonths < 0) {
            errorMsg = "Residency start date cannot be in the future";
          } else {
            totalMonths = calculatedMonths;
            displayValue = `${calculatedMonths} month${
              calculatedMonths !== 1 ? "s" : ""
            }`;
          }
        }
      }
    } else {
      errorMsg =
        "Use format: 5 (months), 2y (years), 1y6m (years & months), or MM/YYYY (e.g., 03/2020)";
    }

    if (errorMsg) {
      messageContainer.textContent = errorMsg;
      residencyInput.classList.add("error");
      residencyInput.setAttribute("aria-invalid", "true");
      messageContainer.classList.remove("validation-success");
      messageContainer.classList.add("error-message");
      if (helpText) helpText.style.display = "none";
    } else {
      messageContainer.innerHTML = `✓ ${displayValue}`;
      residencyInput.classList.remove("error");
      residencyInput.removeAttribute("aria-invalid");
      messageContainer.classList.remove("error-message");
      messageContainer.classList.add("validation-success");
      if (helpText) helpText.style.display = "none";
    }
  });
}

/**
 * Validate Facebook URL or username in real-time
 * Accepts: Facebook URL, username, or just the handle
 */
function validateFacebookUrl() {
  const fbInput = document.getElementById("fb_account");
  const messageContainer = document.getElementById(
    "fbAccountValidationMessage"
  );

  if (!fbInput || !messageContainer) return;

  fbInput.addEventListener("input", function () {
    const fbInput = this.value.trim();

    if (!fbInput) {
      messageContainer.textContent = "";
      this.classList.remove("error");
      this.removeAttribute("aria-invalid");
      return;
    }

    // Pattern 1: Full Facebook URL (https://www.facebook.com/username or https://facebook.com/username)
    const fbUrlPattern =
      /^https?:\/\/(www\.)?facebook\.com\/[a-zA-Z0-9\.\-_]{3,}$/i;

    // Pattern 2: Facebook username handle (just the username, can contain letters, numbers, dots, hyphens, underscores)
    const fbUsernamePattern = /^[a-zA-Z0-9\.\-_]{3,}$/;

    const isValidUrl = fbUrlPattern.test(fbInput);
    const isValidUsername = fbUsernamePattern.test(fbInput);

    if (!isValidUrl && !isValidUsername) {
      messageContainer.textContent =
        "Please enter a valid Facebook profile (URL or username, e.g., https://www.facebook.com/john.doe or john.doe)";
      this.classList.add("error");
      this.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "";
      this.classList.remove("error");
      this.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate spouse contact number in real-time
 * Philippine format: 11 digits
 */
function validateSpouseContactNumber() {
  const contactInput = document.getElementById("spouse_contact");
  const messageContainer = document.getElementById(
    "spouseContactValidationMessage"
  );

  if (!contactInput || !messageContainer) return;

  contactInput.addEventListener("input", function () {
    const contact = this.value.trim();

    if (!contact) {
      messageContainer.textContent = "";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
      return;
    }

    // Remove any non-digit characters for validation
    const digitsOnly = contact.replace(/\D/g, "");

    if (digitsOnly.length === 0) {
      messageContainer.textContent = "Please enter a valid phone number";
      contactInput.classList.add("error");
      contactInput.setAttribute("aria-invalid", "true");
      return;
    }

    // Philippine format: starts with 0 or 63, followed by 10 digits
    if (digitsOnly.length === 11 && digitsOnly.startsWith("0")) {
      messageContainer.textContent = "✓ Valid phone number";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
    } else if (digitsOnly.length === 12 && digitsOnly.startsWith("63")) {
      messageContainer.textContent = "✓ Valid international format";
      contactInput.classList.remove("error");
      contactInput.removeAttribute("aria-invalid");
    } else {
      messageContainer.textContent = `Phone number must be 11 digits (0XXXXXXXXXX) or international format (63XXXXXXXXXX)`;
      contactInput.classList.add("error");
      contactInput.setAttribute("aria-invalid", "true");
    }
  });
}

/**
 * Validate spouse email format in real-time with visual feedback
 */
function validateSpouseEmailFormat() {
  const emailInput = document.getElementById("spouse_email");
  const messageContainer = document.getElementById(
    "spouseEmailValidationMessage"
  );

  if (!emailInput || !messageContainer) return;

  emailInput.addEventListener("input", function () {
    const email = this.value.trim();

    if (!email) {
      messageContainer.textContent = "";
      emailInput.classList.remove("error");
      emailInput.removeAttribute("aria-invalid");
      return;
    }

    // Basic email validation regex
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailPattern.test(email)) {
      messageContainer.textContent = "Please enter a valid email address";
      emailInput.classList.add("error");
      emailInput.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "✓ Valid email";
      emailInput.classList.remove("error");
      emailInput.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate spouse Facebook URL or username in real-time
 */
function validateSpouseFacebookUrl() {
  const fbInput = document.getElementById("spouse_fb_account");
  const messageContainer = document.getElementById(
    "spouseFbAccountValidationMessage"
  );

  if (!fbInput || !messageContainer) return;

  fbInput.addEventListener("input", function () {
    const fbValue = this.value.trim();

    if (!fbValue) {
      messageContainer.textContent = "";
      this.classList.remove("error");
      this.removeAttribute("aria-invalid");
      return;
    }

    // Pattern 1: Full Facebook URL
    const fbUrlPattern =
      /^https?:\/\/(www\.)?facebook\.com\/[a-zA-Z0-9\.\-_]{3,}$/i;

    // Pattern 2: Facebook username handle
    const fbUsernamePattern = /^[a-zA-Z0-9\.\-_]{3,}$/;

    const isValidUrl = fbUrlPattern.test(fbValue);
    const isValidUsername = fbUsernamePattern.test(fbValue);

    if (!isValidUrl && !isValidUsername) {
      messageContainer.textContent =
        "Please enter a valid Facebook profile (URL or username)";
      this.classList.add("error");
      this.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = "✓ Valid Facebook profile";
      this.classList.remove("error");
      this.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Validate spouse number of dependents in real-time
 */
function validateSpouseDependents() {
  const dependentsInput = document.getElementById("spouse_dependents");
  const messageContainer = document.getElementById(
    "spouseDependentsValidationMessage"
  );

  if (!dependentsInput || !messageContainer) return;

  dependentsInput.addEventListener("input", function () {
    const value = this.value.trim();

    if (!value) {
      messageContainer.textContent = "";
      dependentsInput.classList.remove("error");
      dependentsInput.removeAttribute("aria-invalid");
      return;
    }

    const dependents = parseInt(value, 10);

    if (isNaN(dependents)) {
      messageContainer.textContent = "Please enter a valid number";
      dependentsInput.classList.add("error");
      dependentsInput.setAttribute("aria-invalid", "true");
    } else if (dependents < 0) {
      messageContainer.textContent = "Number cannot be negative";
      dependentsInput.classList.add("error");
      dependentsInput.setAttribute("aria-invalid", "true");
    } else if (dependents > 20) {
      messageContainer.textContent = "Number seems too high (max 20)";
      dependentsInput.classList.add("error");
      dependentsInput.setAttribute("aria-invalid", "true");
    } else {
      messageContainer.textContent = `✓ ${dependents} dependent${
        dependents !== 1 ? "s" : ""
      }`;
      dependentsInput.classList.remove("error");
      dependentsInput.removeAttribute("aria-invalid");
    }
  });
}

/**
 * Add real-time character counter for fields with max length
 */
function addCharacterCounter(fieldId, maxLength = 50) {
  const input = document.getElementById(fieldId);

  if (!input) return;

  // Create counter element if it doesn't exist
  if (
    !input.nextElementSibling ||
    !input.nextElementSibling.classList.contains("char-counter")
  ) {
    const counter = document.createElement("div");
    counter.className = "char-counter";
    counter.style.fontSize = "11px";
    counter.style.color = "#666";
    counter.style.marginTop = "2px";
    input.parentNode.insertBefore(counter, input.nextSibling);
  }

  const counter = input.nextElementSibling;

  input.addEventListener("input", function () {
    const currentLength = this.value.length;
    counter.textContent = `${currentLength}/${maxLength}`;

    // Change color based on usage
    if (currentLength >= maxLength * 0.9) {
      counter.style.color = "#ff6b6b"; // Red if near limit
    } else if (currentLength >= maxLength * 0.7) {
      counter.style.color = "#ffa500"; // Orange if approaching limit
    } else {
      counter.style.color = "#666";
    }
  });

  // Initialize counter
  const initialLength = input.value.length;
  counter.textContent = `${initialLength}/${maxLength}`;
}

/**
 * Show real-time validation progress indicator
 */
function showValidationProgress() {
  const form = document.querySelector("form");
  if (!form) return;

  // Get all required fields
  const requiredFields = form.querySelectorAll("[required]");
  let filledFields = 0;

  requiredFields.forEach((field) => {
    if (field.value && field.value.trim() !== "") {
      filledFields++;
    }

    // Listen to changes
    field.addEventListener("input", function () {
      updateProgressIndicator();
    });
    field.addEventListener("change", function () {
      updateProgressIndicator();
    });
  });

  function updateProgressIndicator() {
    const currentFilled = Array.from(requiredFields).filter(
      (f) => f.value && f.value.trim() !== ""
    ).length;
    const percentage = Math.round(
      (currentFilled / requiredFields.length) * 100
    );

    // You can use this to update a progress bar if needed
    console.log(`Form completion: ${percentage}%`);
  }

  updateProgressIndicator();
}

/**
 * Highlight invalid fields on blur for better UX
 */
function addFieldHighlighting() {
  const inputs = document.querySelectorAll(
    "input[required], textarea[required], select[required]"
  );

  inputs.forEach((input) => {
    input.addEventListener("blur", function () {
      if (this.classList.contains("error")) {
        this.style.borderColor = "#dc3545";
        this.style.backgroundColor = "rgba(220, 53, 69, 0.05)";
      }
    });

    input.addEventListener("focus", function () {
      if (!this.classList.contains("error")) {
        this.style.borderColor = "";
        this.style.backgroundColor = "";
      }
    });
  });
}

/**
 * Initialize all form validations on page load
 */
function initializeFormValidations() {
  // Initialize name validations
  validateName("first_name", "firstNameValidationMessage", "First Name");
  validateName("last_name", "lastNameValidationMessage", "Last Name");

  // Initialize contact number validation
  validateContactNumber();

  // Initialize birthday validation
  enhancedBirthdayValidation(
    "birthday",
    "age",
    "birthdayValidationMessage",
    21
  );

  // Initialize birth place validation
  if (document.getElementById("birth_place")) {
    validateTextField(
      "birth_place",
      "birthPlaceValidationMessage",
      2,
      50,
      "Birth place"
    );
    addCharacterCounter("birth_place", 50);
  }

  // Initialize occupation validation
  if (document.getElementById("occupation")) {
    validateTextField(
      "occupation",
      "occupationValidationMessage",
      2,
      50,
      "Occupation"
    );
    addCharacterCounter("occupation", 50);
  }

  // Initialize confirm email validation
  if (document.getElementById("confirm_email")) {
    validateEmailFormat();
  }

  // Initialize years resident validation
  if (document.getElementById("year_resident")) {
    // Create message container if it doesn't exist
    if (!document.getElementById("yearResidentValidationMessage")) {
      const container = document.createElement("div");
      container.id = "yearResidentValidationMessage";
      container.className = "error-message";
      document
        .getElementById("year_resident")
        .parentNode.appendChild(container);
    }
    validateYearsOfResidency();
  }

  // Initialize Facebook URL validation
  if (document.getElementById("fb_account")) {
    validateFacebookUrl();
  }

  // If spouse section exists, validate spouse fields
  if (document.getElementById("spouse_first_name")) {
    validateName(
      "spouse_first_name",
      "spouseFirstNameValidationMessage",
      "Spouse First Name"
    );
    validateName(
      "spouse_last_name",
      "spouseLastNameValidationMessage",
      "Spouse Last Name"
    );

    // Initialize spouse occupation validation
    if (document.getElementById("spouse_occupation")) {
      validateTextField(
        "spouse_occupation",
        "spouseOccupationValidationMessage",
        2,
        50,
        "Spouse Occupation"
      );
    }

    // Initialize spouse birth place validation
    if (document.getElementById("spouse_birth_place")) {
      validateTextField(
        "spouse_birth_place",
        "spouseBirthPlaceValidationMessage",
        2,
        50,
        "Spouse Birth Place"
      );
    }

    // Initialize spouse contact validation
    if (document.getElementById("spouse_contact")) {
      validateSpouseContactNumber();
    }

    // Initialize spouse email validation
    if (document.getElementById("spouse_email")) {
      validateSpouseEmailFormat();
    }

    // Initialize spouse Facebook validation
    if (document.getElementById("spouse_fb_account")) {
      validateSpouseFacebookUrl();
    }

    // Initialize spouse dependents validation
    if (document.getElementById("spouse_dependents")) {
      validateSpouseDependents();
    }
  }

  // Add field highlighting for better UX
  addFieldHighlighting();

  // Show validation progress
  showValidationProgress();

  // Initialize enhanced real-time validation
  enhanceRealTimeValidation();
}

/**
 * Enhanced Real-Time Validation System - Improved Version
 * Prevents duplicate messages and provides better UX
 */
function enhanceRealTimeValidation() {
  const form = document.querySelector("form");
  if (!form) return;

  const inputs = form.querySelectorAll(
    'input[type="text"], input[type="email"], input[type="number"], input[type="date"], select, textarea'
  );

  inputs.forEach((input) => {
    // Prevent duplicate listeners
    if (input.dataset.validationInitialized === "true") return;
    input.dataset.validationInitialized = "true";

    input.addEventListener("blur", function () {
      validateFieldImproved(this);
    });

    input.addEventListener("input", function () {
      clearTimeout(this.validationTimeout);
      this.validationTimeout = setTimeout(() => {
        validateFieldImproved(this);
      }, 400);
    });

    if (input.tagName === "SELECT") {
      input.addEventListener("change", function () {
        validateFieldImproved(this);
      });
    }
  });
}

/**
 * Improved field validation with specific messages
 */
function validateFieldImproved(field) {
  const validation = getFieldValidation(field);

  // Skip validation for empty optional fields
  if (!field.hasAttribute("required") && !field.value.trim()) {
    clearFieldValidation(field);
    return true;
  }

  if (validation.isValid) {
    setFieldSuccess(field);
  } else {
    setFieldError(field, validation.message);
  }

  return validation.isValid;
}

/**
 * Get validation for field with specific messages
 */
function getFieldValidation(field) {
  const value = field.value.trim();
  const fieldType = getFieldType(field);
  const fieldId = field.id.toLowerCase();
  const fieldName = field.name.toLowerCase();

  // Required field check
  if (!value && field.hasAttribute("required")) {
    return { isValid: false, message: "This field is required" };
  }

  if (!value) {
    return { isValid: true, message: "" };
  }

  // Email validation
  if (fieldType === "email" || fieldId.includes("email")) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
      return {
        isValid: false,
        message: "Enter a valid email (e.g., john@example.com)",
      };
    }
    return { isValid: true, message: "" };
  }

  // Phone validation
  if (fieldType === "phone" || fieldName.includes("contact")) {
    const phone = value.replace(/\D/g, "");
    if (phone.length < 10 || phone.length > 13) {
      return { isValid: false, message: "Enter a 10-13 digit phone number" };
    }
    return { isValid: true, message: "" };
  }

  // Name validation
  if (fieldType === "name" || fieldId.includes("name")) {
    if (value.length < 3) {
      return { isValid: false, message: "Name must be at least 3 characters" };
    }
    if (value.length > 50) {
      return { isValid: false, message: "Name cannot exceed 50 characters" };
    }
    const nameRegex = /^[a-zA-Z\s'-]{3,50}$/;
    if (!nameRegex.test(value)) {
      return {
        isValid: false,
        message: "Only letters, spaces, hyphens, and apostrophes allowed",
      };
    }
    return { isValid: true, message: "" };
  }

  // Date validation (birthday)
  if (fieldType === "date" || fieldId.includes("birthday")) {
    const selectedDate = new Date(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selectedDate > today) {
      return { isValid: false, message: "Birthday cannot be in the future" };
    }

    const age = today.getFullYear() - selectedDate.getFullYear();
    const monthDiff = today.getMonth() - selectedDate.getMonth();
    const adjustedAge =
      monthDiff < 0 ||
      (monthDiff === 0 && today.getDate() < selectedDate.getDate())
        ? age - 1
        : age;

    // Age must be between 21 and 75 for all applicants
    if (adjustedAge < 21) {
      return {
        isValid: false,
        message: "Must be at least 21 years old",
      };
    }

    if (adjustedAge > 75) {
      return {
        isValid: false,
        message: "Age cannot exceed 75 years",
      };
    }

    return { isValid: true, message: "" };
  }

  // Years of residency
  if (fieldId.includes("resident") || fieldId.includes("year")) {
    const lowerValue = value.toLowerCase();

    // Check plain number format (e.g., "5" = 5 months, not years!)
    if (/^\d+$/.test(value)) {
      const months = parseInt(value);
      if (months >= 0 && months <= 1200) {
        return { isValid: true, message: "" };
      }
      return {
        isValid: false,
        message: "Enter months between 0 and 1200 (0-100 years)",
      };
    }

    // Check flexible format with units (e.g., "2y", "6m", "1y6m", "2y3m", "2y 3m")
    if (/^[\d.]+[ym](\s*[\d.]+[ym])?$/i.test(value)) {
      const yearMatch = lowerValue.match(/([\d.]+)\s*y/);
      const monthMatch = lowerValue.match(/([\d.]+)\s*m/);

      const years = yearMatch ? parseFloat(yearMatch[1]) : 0;
      const months = monthMatch ? parseFloat(monthMatch[1]) : 0;
      const totalMonths = Math.round(years * 12 + months);

      if (totalMonths > 0 && totalMonths <= 1200) {
        return { isValid: true, message: "" };
      }
      if (totalMonths === 0) {
        return {
          isValid: false,
          message: "Enter a valid duration (e.g., 2y, 3m, or 2y3m)",
        };
      }
      return {
        isValid: false,
        message: "Duration cannot exceed 1200 months (100 years)",
      };
    }

    // Check MM/YYYY format (e.g., "03/2020")
    if (/^(0[1-9]|1[0-2])\/\d{4}$/.test(value)) {
      const [month, year] = value.split("/");
      const monthNum = parseInt(month);
      const yearNum = parseInt(year);
      const currentYear = new Date().getFullYear();

      if (monthNum < 1 || monthNum > 12) {
        return { isValid: false, message: "Month must be 01-12" };
      }
      if (yearNum > currentYear) {
        return { isValid: false, message: "Year cannot be in the future" };
      }
      return { isValid: true, message: "" };
    }

    return {
      isValid: false,
      message:
        "Use format: 5 (months), 2y (years), 1y6m (years & months), or MM/YYYY (e.g., 03/2020)",
    };
  }

  // Facebook validation
  if (fieldId.includes("fb") || fieldName.includes("facebook")) {
    const isFbUrl =
      value.toLowerCase().includes("facebook.com/") ||
      value.toLowerCase().includes("fb.com/");
    const isUsername = /^[a-zA-Z0-9._]{3,}$/.test(value);

    if (isFbUrl || isUsername) {
      return { isValid: true, message: "" };
    }
    return { isValid: false, message: "Enter Facebook URL or username" };
  }

  // Text field validation (birth place, occupation)
  if (fieldId.includes("birth_place") || fieldId.includes("occupation")) {
    if (value.length < 2) {
      return { isValid: false, message: "At least 2 characters required" };
    }
    if (value.length > 50) {
      return { isValid: false, message: "Cannot exceed 50 characters" };
    }
    return { isValid: true, message: "" };
  }

  // Number validation
  if (fieldType === "number") {
    const num = parseFloat(value);
    if (isNaN(num)) {
      return { isValid: false, message: "Enter a valid number" };
    }

    if (field.min && num < parseFloat(field.min)) {
      return { isValid: false, message: `Minimum value is ${field.min}` };
    }
    if (field.max && num > parseFloat(field.max)) {
      return { isValid: false, message: `Maximum value is ${field.max}` };
    }

    return { isValid: true, message: "" };
  }

  // Select validation
  if (fieldType === "select") {
    if (value === "" || value === null) {
      return { isValid: false, message: "Please select an option" };
    }
    return { isValid: true, message: "" };
  }

  return { isValid: true, message: "" };
}

/**
 * Set field to success state
 */
function setFieldSuccess(field) {
  field.classList.remove("is-invalid");
  field.classList.add("is-valid");

  const errorContainer = field.parentNode.querySelector(".field-feedback");
  if (errorContainer) {
    errorContainer.textContent = "";
    errorContainer.className = "field-feedback";
  }

  const formGroup = field.closest(".form-group") || field.parentNode;
  formGroup.classList.remove("has-error");
  formGroup.classList.add("has-success");
}

/**
 * Set field to error state
 */
function setFieldError(field, message) {
  field.classList.remove("is-valid");
  field.classList.add("is-invalid");

  let errorContainer = field.parentNode.querySelector(".field-feedback");

  if (!errorContainer) {
    errorContainer = document.createElement("div");
    errorContainer.className = "field-feedback error-message";
    field.parentNode.appendChild(errorContainer);
  }

  errorContainer.textContent = message;
  errorContainer.className = "field-feedback error-message";

  const formGroup = field.closest(".form-group") || field.parentNode;
  formGroup.classList.remove("has-success");
  formGroup.classList.add("has-error");
}

/**
 * Clear field validation
 */
function clearFieldValidation(field) {
  field.classList.remove("is-invalid", "is-valid");

  const errorContainer = field.parentNode.querySelector(".field-feedback");
  if (errorContainer) {
    errorContainer.textContent = "";
    errorContainer.className = "field-feedback";
  }

  const formGroup = field.closest(".form-group") || field.parentNode;
  formGroup.classList.remove("has-error", "has-success");
}

// Initialize validations when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  initializeFormValidations();
  initializeEnhancedValidation();
});

/**
 * ===== ENHANCED VALIDATION SYSTEM FOR CONSISTENT UI/UX =====
 */

/**
 * Enhanced form field styling for better UX
 */
function enhanceFormFieldStyling() {
  const form = document.querySelector("form");
  if (!form) return;

  const inputs = form.querySelectorAll("input, select, textarea");

  inputs.forEach((input) => {
    // Add focus enhancement
    input.addEventListener("focus", function () {
      this.parentElement.classList.add("field-focused");
    });

    input.addEventListener("blur", function () {
      this.parentElement.classList.remove("field-focused");
    });

    // Add typing feedback
    input.addEventListener("input", function () {
      if (this.value.trim().length > 0) {
        this.classList.add("has-content");
        this.parentElement.classList.add("field-has-content");
      } else {
        this.classList.remove("has-content");
        this.parentElement.classList.remove("field-has-content");
      }
    });

    // Initial check for pre-filled fields
    if (input.value && input.value.trim().length > 0) {
      input.classList.add("has-content");
      input.parentElement.classList.add("field-has-content");
    }
  });
}

/**
 * Initialize enhanced validation system
 */
function initializeEnhancedValidation() {
  enhanceFormFieldStyling();

  // Add smooth transitions and enhanced styling
  const style = document.createElement("style");
  style.textContent = `
    /* Enhanced field focus states */
    .field-focused {
      transform: translateY(-1px);
      transition: transform 0.2s ease;
    }
    
    input.has-content:not(:focus).is-valid,
    select.has-content:not(:focus).is-valid {
      background: rgba(40, 167, 69, 0.05);
      border-color: #28a745;
    }
    
    /* Smooth error message animations */
    .error-message {
      animation: slideInError 0.3s ease;
      transform: translateX(0);
    }
    
    .validation-success {
      animation: slideInSuccess 0.3s ease;
    }
    
    @keyframes slideInError {
      from {
        opacity: 0;
        transform: translateX(-10px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }
    
    @keyframes slideInSuccess {
      from {
        opacity: 0;
        transform: translateY(-5px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Enhanced button hover effects */
    .btn:hover {
      transform: translateY(-2px);
      transition: all 0.3s ease;
    }
    
    .btn:active {
      transform: translateY(0);
    }
    
    /* Progress indicator enhancement */
    .progress-line.completed {
      background: linear-gradient(90deg, var(--primary), var(--secondary));
      height: 4px;
      border-radius: 2px;
    }
  `;
  document.head.appendChild(style);

  console.log("Enhanced validation system initialized");
}

// Enhanced Security Measures
class SecurityManager {
  constructor() {
    this.suspiciousActivity = [];
    this.attemptCounts = new Map();
    this.setupSecurityListeners();
  }

  setupSecurityListeners() {
    // Monitor for rapid form submissions (potential bot)
    let lastSubmitTime = 0;
    document.addEventListener("submit", (e) => {
      const currentTime = Date.now();
      if (currentTime - lastSubmitTime < 2000) {
        // Less than 2 seconds
        this.logSuspiciousActivity("rapid_submission", {
          timeDiff: currentTime - lastSubmitTime,
          form: e.target.id,
        });
      }
      lastSubmitTime = currentTime;
    });

    // Monitor for SQL injection attempts
    document.addEventListener("input", (e) => {
      if (e.target.type === "text" || e.target.type === "email") {
        this.checkForInjectionAttempts(e.target);
      }
    });

    // Detect copy-paste of sensitive data
    const sensitiveFields = ["password", "confirm_password", "email"];
    sensitiveFields.forEach((fieldName) => {
      const field = document.querySelector(`[name="${fieldName}"]`);
      if (field) {
        field.addEventListener("paste", (e) => {
          this.logSuspiciousActivity("sensitive_data_paste", {
            field: fieldName,
            timestamp: new Date().toISOString(),
          });
        });
      }
    });
  }

  checkForInjectionAttempts(field) {
    const value = field.value.toLowerCase();
    const suspiciousPatterns = [
      /('|(\\-\\-)|(;)|(\\|)|(\\*)|(%))/,
      /(union|select|insert|update|delete|drop|create|alter)/,
      /(script|javascript|vbscript|onload|onerror)/,
    ];

    suspiciousPatterns.forEach((pattern, index) => {
      if (pattern.test(value)) {
        this.logSuspiciousActivity("injection_attempt", {
          field: field.name,
          pattern: index,
          value: value.substring(0, 50), // First 50 chars only
        });

        // Show warning
        this.showSecurityWarning(
          "Invalid characters detected. Please use standard characters only."
        );
      }
    });
  }

  logSuspiciousActivity(type, details) {
    const activity = {
      type,
      details,
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent,
      url: window.location.href,
    };

    this.suspiciousActivity.push(activity);
    console.warn("Security alert:", activity);
  }

  showSecurityWarning(message) {
    showMessage(message, "warning");
  }
}

// Enhanced password strength validator
function checkPasswordSecurity(password) {
  const checks = {
    length: password.length >= 8,
    uppercase: /[A-Z]/.test(password),
    lowercase: /[a-z]/.test(password),
    numbers: /\\d/.test(password),
    special: /[@$!%*?&]/.test(password),
    noCommon: !isCommonPassword(password),
  };

  const score = Object.values(checks).filter(Boolean).length;

  return {
    score,
    maxScore: Object.keys(checks).length,
    checks,
    strength: score < 3 ? "weak" : score < 5 ? "medium" : "strong",
  };
}

function isCommonPassword(password) {
  const common = [
    "password",
    "123456",
    "password123",
    "admin",
    "qwerty",
    "abc123",
    "welcome",
    "login",
    "123456789",
    "password1",
  ];
  return common.some((p) => password.toLowerCase().includes(p));
}

// Initialize security manager
const securityManager = new SecurityManager();
