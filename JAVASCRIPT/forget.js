// Wait for the DOM to be fully loaded
document.addEventListener("DOMContentLoaded", function () {
  // Get form elements
  const form = document.querySelector("form");
  const emailInput = document.getElementById("email");
  const submitButton = form.querySelector('input[type="submit"]');

  // Regular expression for email validation
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

  // Function to show error message below input
  function showError(input, message) {
    // Remove existing error message
    const existingError = input.parentElement.querySelector(".error-message");
    if (existingError) {
      existingError.remove();
    }

    // Create and display new error message
    const errorDiv = document.createElement("div");
    errorDiv.className = "error-message";
    errorDiv.style.color = "var(--error)";
    errorDiv.style.fontSize = "0.85rem";
    errorDiv.style.marginTop = "0.25rem";
    errorDiv.textContent = message;
    input.parentElement.appendChild(errorDiv);

    // Style the input to indicate error
    input.style.borderColor = "var(--error)";
  }

  // Function to clear error message
  function clearError(input) {
    const existingError = input.parentElement.querySelector(".error-message");
    if (existingError) {
      existingError.remove();
    }
    input.style.borderColor = ""; // Reset to default
  }

  // Function to validate email
  function validateEmail() {
    const email = emailInput.value.trim();

    // Check if email is empty
    if (!email) {
      showError(emailInput, "Email is required.");
      return false;
    }

    // Check email format
    if (!emailRegex.test(email)) {
      showError(emailInput, "Please enter a valid email address.");
      return false;
    }

    // Check email length (schema: VARCHAR(100))
    if (email.length > 100) {
      showError(emailInput, "Email must be 100 characters or less.");
      return false;
    }

    // Clear error if valid
    clearError(emailInput);
    return true;
  }

  // Real-time validation on input
  emailInput.addEventListener("input", function () {
    validateEmail();
  });

  // Add loading spinner style
  const style = document.createElement("style");
  style.textContent = `
    .loading::after {
      content: '';
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid var(--light);
      border-top-color: var(--primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-left: 8px;
    }
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    .custom-modal .modal-content {
      border-radius: 5px;
      border: 1px solid var(--dark);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
    }
    .custom-modal .modal-header {
      background: var(--dark);
      color: var(--light);
      border-bottom: none;
    }
    .custom-modal .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
    }
    .custom-modal .modal-body {
      font-size: 1rem;
      color: var(--text);
    }
    .custom-modal .modal-body.error {
      color: var(--error);
    }
    .custom-modal .modal-footer {
      border-top: none;
      justify-content: center;
    }
    .custom-modal .btn-close {
      filter: invert(1);
    }
    .custom-modal .btn-primary {
      background: var(--dark);
      border: none;
      transition: all 0.3s ease;
    }
    .custom-modal .btn-primary:hover {
      background: var(--med);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(168, 228, 75, 0.3);
    }
  `;
  document.head.appendChild(style);

  // Function to show Bootstrap modal
  function showMessageModal(
    title,
    message,
    type,
    options = { closable: true }
  ) {
    // Remove any existing modal
    const existingModal = document.querySelector(".custom-modal");
    if (existingModal) {
      existingModal.remove();
    }

    // Create modal structure
    const modal = document.createElement("div");
    modal.className = "modal fade custom-modal";
    modal.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${title}</h5>
            ${
              options.closable
                ? '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
                : ""
            }
          </div>
          <div class="modal-body ${type}">${message}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    // Initialize and show modal
    const bootstrapModal = new bootstrap.Modal(modal, {
      backdrop: options.closable ? true : "static",
      keyboard: options.closable,
    });
    bootstrapModal.show();

    // Auto-redirect after 5 seconds for success/info messages
    if (type === "info" || type === "success") {
      setTimeout(() => {
        bootstrapModal.hide();
        window.location.href = "index.php";
      }, 5000);
    }

    // Clean up modal after hide
    modal.addEventListener("hidden.bs.modal", () => {
      modal.remove();
    });
  }

  // Form submission handler
  form.addEventListener("submit", function (event) {
    // Prevent form submission if validation fails
    if (!validateEmail()) {
      event.preventDefault();
      showMessageModal(
        "Error",
        "Please correct the errors in the form before submitting.",
        "error",
        { closable: true }
      );
    } else {
      // Show loading state
      submitButton.disabled = true;
      submitButton.value = "Submitting";
      submitButton.classList.add("loading");
    }
  });

  // Reset submit button state on form reset or page load
  form.addEventListener("reset", function () {
    submitButton.disabled = false;
    submitButton.value = "Submit";
    submitButton.classList.remove("loading");
    clearError(emailInput);
  });

  // Display message from URL query parameter (from PHP redirect)
  const urlParams = new URLSearchParams(window.location.search);
  const message = urlParams.get("message");
  if (message) {
    showMessageModal("Notification", message, "info", { closable: true });
  }
});
