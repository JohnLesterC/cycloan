/**
 * Step 3: Document Requirements - Enhanced Validation & File Handling
 * ===================================================================
 */

// File upload handler with validation
function handleFileUpload(input) {
  const card = input.closest(".requirement-card");
  const placeholder = card.querySelector(".upload-placeholder");
  const fileInfo = card.querySelector(".file-info");
  const fileName = card.querySelector(".file-name");
  const errorMsg = card.querySelector(".error-message");

  // Clear previous errors
  errorMsg.textContent = "";
  card.classList.remove("has-error");

  if (input.files && input.files[0]) {
    const file = input.files[0];

    // Validate file type
    const validTypes = ["image/jpeg", "image/png", "application/pdf"];
    if (!validTypes.includes(file.type)) {
      errorMsg.textContent =
        "❌ Invalid file type. Please upload JPEG, PNG, or PDF only.";
      card.classList.add("has-error");
      input.value = "";
      return false;
    }

    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
      errorMsg.textContent = "❌ File too large. Maximum size is 5MB.";
      card.classList.add("has-error");
      input.value = "";
      return false;
    }

    // Show file info
    fileName.textContent = file.name;
    placeholder.style.display = "none";
    fileInfo.style.display = "flex";

    // Add success state
    card.classList.add("has-file");
    card.classList.remove("has-error");

    // Optional: Show file preview for images
    if (file.type.startsWith("image/")) {
      createImagePreview(file, card);
    }

    return true;
  }

  return false;
}

// Remove file function
function removeFile(inputId) {
  const input = document.getElementById(inputId);
  const card = input.closest(".requirement-card");
  const placeholder = card.querySelector(".upload-placeholder");
  const fileInfo = card.querySelector(".file-info");
  const errorMsg = card.querySelector(".error-message");

  // Clear file input
  input.value = "";

  // Reset UI
  placeholder.style.display = "flex";
  fileInfo.style.display = "none";
  card.classList.remove("has-file", "has-error");
  errorMsg.textContent = "";

  // Remove preview if exists
  const preview = card.querySelector(".file-preview");
  if (preview) {
    preview.remove();
  }
}

// Create image preview (optional enhancement)
function createImagePreview(file, card) {
  // Remove existing preview
  const existingPreview = card.querySelector(".file-preview");
  if (existingPreview) {
    existingPreview.remove();
  }

  const reader = new FileReader();
  reader.onload = function (e) {
    const preview = document.createElement("div");
    preview.className = "file-preview";
    preview.innerHTML = `
      <img src="${e.target.result}" alt="Preview" style="max-width: 100%; border-radius: 8px; margin-top: 12px;">
    `;
    card.querySelector(".file-info").after(preview);
  };
  reader.readAsDataURL(file);
}

// Enhanced Step 3 Validation
function validateStep3Enhanced() {
  const loanType = document.getElementById("loanType").value;
  const projectType = document.querySelector(
    'input[name="projectType"]:checked'
  )?.value;
  let isValid = true;
  let missingDocs = [];

  // Get visible requirement cards
  const visibleCards = document.querySelectorAll(
    '.requirement-card:not(.hidden)[data-required="true"]'
  );

  visibleCards.forEach((card) => {
    const input = card.querySelector('input[type="file"]');
    const errorMsg = card.querySelector(".error-message");
    const docTitle = card.querySelector(".doc-title").textContent;

    if (input && input.files.length === 0) {
      errorMsg.textContent = `❌ ${docTitle} is required. Please upload this document.`;
      card.classList.add("has-error");
      card.scrollIntoView({ behavior: "smooth", block: "center" });
      missingDocs.push(docTitle);
      isValid = false;
    } else {
      errorMsg.textContent = "";
      card.classList.remove("has-error");
    }
  });

  if (!isValid) {
    showValidationModal(missingDocs);
  }

  return isValid;
}

// Show validation modal
function showValidationModal(missingDocs) {
  const modal = document.getElementById("messageModal");
  const modalMessage = document.getElementById("modalMessage");

  if (modal && modalMessage) {
    modalMessage.innerHTML = `
      <div style="text-align: left;">
        <h3 style="color: var(--error); margin-bottom: 12px;">
          ⚠️ Missing Required Documents
        </h3>
        <p style="margin-bottom: 12px;">Please upload the following required documents:</p>
        <ul style="margin: 0; padding-left: 20px;">
          ${missingDocs
            .map((doc) => `<li style="margin: 6px 0;">${doc}</li>`)
            .join("")}
        </ul>
      </div>
    `;
    modal.style.display = "flex";
  }
}

// Auto-show requirements based on loan type and project type
function updateRequirementsVisibility() {
  const loanType = document.getElementById("loanType").value;
  const projectType = document.querySelector(
    'input[name="projectType"]:checked'
  )?.value;

  // Hide all sections first
  document.querySelectorAll(".requirements-section").forEach((section) => {
    section.style.display = "none";
  });

  // Hide all requirement cards
  document.querySelectorAll(".requirement-card").forEach((card) => {
    card.classList.add("hidden");
  });

  // Show individual requirements for Individual loans
  if (loanType === "Individual") {
    document.getElementById("individualSection").style.display = "block";
    document
      .querySelectorAll(".individualRequirements .requirement-card")
      .forEach((card) => {
        card.classList.remove("hidden");
      });
  }

  // Show cooperative requirements for Cooperative loans
  if (loanType === "Cooperative") {
    document.getElementById("cooperativeSection").style.display = "block";
    document
      .querySelectorAll(".cooperativeRequirements .requirement-card")
      .forEach((card) => {
        card.classList.remove("hidden");
      });
  }

  // Show agricultural requirements if project type is agricultural
  if (projectType === "Agricultural-based") {
    document.getElementById("agriculturalSection").style.display = "block";
    document
      .querySelectorAll(".agriculturalRequirements .requirement-card")
      .forEach((card) => {
        card.classList.remove("hidden");
      });
  }
}

// Initialize drag and drop functionality
function initDragAndDrop() {
  document.querySelectorAll(".upload-area").forEach((area) => {
    const input = area.querySelector('input[type="file"]');
    const placeholder = area.querySelector(".upload-placeholder");

    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      area.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
      e.preventDefault();
      e.stopPropagation();
    }

    ["dragenter", "dragover"].forEach((eventName) => {
      area.addEventListener(eventName, () => {
        placeholder.style.borderColor = "var(--primary)";
        placeholder.style.background = "rgba(27, 94, 32, 0.1)";
      });
    });

    ["dragleave", "drop"].forEach((eventName) => {
      area.addEventListener(eventName, () => {
        placeholder.style.borderColor = "";
        placeholder.style.background = "";
      });
    });

    area.addEventListener("drop", (e) => {
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        input.files = files;
        handleFileUpload(input);
      }
    });
  });
}

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
  // Initialize drag and drop
  initDragAndDrop();

  // Listen for loan type and project type changes
  const loanTypeInput = document.getElementById("loanType");
  if (loanTypeInput) {
    loanTypeInput.addEventListener("change", updateRequirementsVisibility);
  }

  const projectTypeRadios = document.querySelectorAll(
    'input[name="projectType"]'
  );
  projectTypeRadios.forEach((radio) => {
    radio.addEventListener("change", updateRequirementsVisibility);
  });

  // Update visibility on page load
  updateRequirementsVisibility();
});

// File size formatter
function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}

// Get upload summary
function getUploadSummary() {
  const visibleCards = document.querySelectorAll(
    '.requirement-card:not(.hidden)[data-required="true"]'
  );
  let uploadedCount = 0;
  let totalCount = visibleCards.length;

  visibleCards.forEach((card) => {
    const input = card.querySelector('input[type="file"]');
    if (input && input.files.length > 0) {
      uploadedCount++;
    }
  });

  return {
    uploaded: uploadedCount,
    total: totalCount,
    percentage:
      totalCount > 0 ? Math.round((uploadedCount / totalCount) * 100) : 0,
  };
}

// Update progress indicator (if exists)
function updateProgressIndicator() {
  const summary = getUploadSummary();
  const progressEl = document.querySelector(".upload-progress");

  if (progressEl && summary.total > 0) {
    progressEl.style.display = "block";
    progressEl.querySelector(
      ".progress-text"
    ).textContent = `Uploaded ${summary.uploaded} of ${summary.total} documents`;
    progressEl.querySelector(
      ".progress-percent"
    ).textContent = `${summary.percentage}%`;
    progressEl.querySelector(
      ".progress-bar-fill"
    ).style.width = `${summary.percentage}%`;
  }
}
