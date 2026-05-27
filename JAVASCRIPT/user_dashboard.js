// Global state management
let loansCache = [];
let documentsCache = [];
let activeTab = "overview";

// Initialize dashboard when page loads
document.addEventListener("DOMContentLoaded", function () {
  initializeDashboard();
});

function initializeDashboard() {
  // Load initial data
  loadLoans();
  // Disabled: loadDocuments(); - will be handled by auto-polling
  // Documents will be fetched and rendered by the auto-polling system

  // Setup event listeners
  setupEventListeners();
}

function setupEventListeners() {
  // Tab switching
  const tabButtons = document.querySelectorAll(".tab-btn");
  tabButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const tabName = this.getAttribute("data-tab");
      switchTab(tabName);
    });
  });
}

function switchTab(tabName) {
  activeTab = tabName;

  // Hide all tab contents
  const tabContents = document.querySelectorAll(".tab-content");
  tabContents.forEach((content) => {
    content.style.display = "none";
  });

  // Remove active class from all tabs
  const tabButtons = document.querySelectorAll(".tab-btn");
  tabButtons.forEach((btn) => {
    btn.classList.remove("active");
  });

  // Show selected tab content
  const selectedTab = document.getElementById(tabName);
  if (selectedTab) {
    selectedTab.style.display = "block";
  }

  // Add active class to clicked tab
  event.target.classList.add("active");

  // Refresh data if needed
  if (tabName === "loans") {
    loadLoans();
  } else if (tabName === "documents") {
    loadDocuments();
  }
}

function loadLoans() {
  // Fetch loans via AJAX
  fetch("get_loan_details.php", {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        loansCache = data.loans || [];
        renderLoans(loansCache);
      } else {
        showNotification("Error loading loans", "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("Failed to load loans", "error");
    });
}

function loadDocuments() {
  // Fetch documents via AJAX
  fetch("get_documents.php", {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        documentsCache = data.documents || [];
        renderDocuments(documentsCache);
      } else {
        showNotification("Error loading documents", "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("Failed to load documents", "error");
    });
}

function renderLoans(loans) {
  const loansContainer = document.getElementById("loans-container");
  if (!loansContainer) return;

  if (!loans || loans.length === 0) {
    loansContainer.innerHTML = '<div class="no-data">No loans found</div>';
    return;
  }

  loansContainer.innerHTML = loans
    .map(
      (loan) => `
    <div class="loan-card">
      <div class="loan-header">
        <h3>Loan #${loan.loan_id}</h3>
        <span class="status-badge ${loan.status.toLowerCase()}">
          ${loan.status}
        </span>
      </div>
      <div class="loan-details">
        <div class="detail-row">
          <span class="label">Amount:</span>
          <span class="value">$${parseFloat(loan.loan_amount).toFixed(2)}</span>
        </div>
        <div class="detail-row">
          <span class="label">Interest Rate:</span>
          <span class="value">${loan.interest_rate}%</span>
        </div>
        <div class="detail-row">
          <span class="label">Term:</span>
          <span class="value">${loan.loan_term} months</span>
        </div>
        <div class="detail-row">
          <span class="label">Applied Date:</span>
          <span class="value">${new Date(
            loan.application_date
          ).toLocaleDateString()}</span>
        </div>
      </div>
      <button class="btn-primary" onclick="viewLoanDetails(${loan.loan_id})">
        View Details
      </button>
    </div>
  `
    )
    .join("");
}

function renderDocuments(documents) {
  // Use the modern card-based grid layout from documentStatusGrid
  const statusGrid = document.getElementById("documentStatusGrid");
  if (!statusGrid) {
    console.warn("documentStatusGrid not found");
    return;
  }

  if (!documents || documents.length === 0) {
    statusGrid.innerHTML = '<div class="no-data">No documents found</div>';
    return;
  }

  statusGrid.innerHTML = "";

  documents.forEach((doc) => {
    const card = document.createElement("div");
    card.className = "document-card";
    card.setAttribute("data-doc-id", doc.document_id);

    const statusClass = doc.status.toLowerCase();
    console.log(
      `📄 Document: ${doc.document_name}, Status: ${doc.status}, StatusClass: ${statusClass}, ID: ${doc.document_id}`
    );
    const statusIcon =
      statusClass === "approved"
        ? "✅"
        : statusClass === "rejected"
        ? "❌"
        : "⏳";

    card.innerHTML = `
      <div class="document-card-header">
        <div class="document-name">${doc.document_name}</div>
        <span class="status-badge status-${statusClass}">${doc.status}</span>
      </div>
      <div class="document-card-body">
        <div class="document-info">
          <span class="info-label">Status:</span>
          <span class="info-value ${statusClass}">${statusIcon} ${
      doc.status
    }</span>
        </div>
        <div class="document-info">
          <span class="info-label">Last Updated:</span>
          <span class="info-value">${
            doc.status_updated_at
              ? new Date(doc.status_updated_at).toLocaleDateString("en-US", {
                  month: "short",
                  day: "numeric",
                  year: "numeric",
                })
              : "N/A"
          }</span>
        </div>
        ${
          doc.rejection_notes
            ? `<div class="document-info"><span class="info-label">Reason:</span><span class="info-value rejection">${doc.rejection_notes}</span></div>`
            : ""
        }
      </div>
      <div class="document-card-actions">
        <button class="btn-resubmit" data-doc-id="${
          doc.document_id
        }" data-doc-name="${
      doc.document_name
    }"><i class="fas fa-upload"></i> Resubmit</button>
      </div>
    `;

    statusGrid.appendChild(card);

    // Set actions visibility based on status
    const actionsDiv = card.querySelector(".document-card-actions");
    if (statusClass !== "rejected") {
      actionsDiv.style.display = "none";
    }
  });

  // Attach event listeners to resubmit buttons
  const resubmitButtons = statusGrid.querySelectorAll(".btn-resubmit");
  resubmitButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const docId = this.getAttribute("data-doc-id");
      const docName = this.getAttribute("data-doc-name");
      showResubmitModal(docId, docName);
    });
  });
}

function toggleEditForm(documentId) {
  const formRow = document.getElementById(`edit-form-row-${documentId}`);
  const form = document.getElementById(`edit-form-${documentId}`);

  if (formRow) {
    // Table layout - show/hide the form row
    if (formRow.style.display === "none") {
      formRow.style.display = "table-row";
      form.classList.add("active");
    } else {
      formRow.style.display = "none";
      form.classList.remove("active");
    }
  } else if (form) {
    // Card layout fallback
    form.classList.toggle("active");
  }
}

function submitDocumentForm(event, documentId) {
  event.preventDefault();

  const fileInput = document.getElementById(`doc-file-${documentId}`);
  const file = fileInput.files[0];

  if (!file) {
    showNotification("Please select a file", "error");
    return;
  }

  const formData = new FormData();
  formData.append("document_id", documentId);
  formData.append("document_file", file);

  showLoadingOverlay("Uploading document...");

  fetch("process_document_upload.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      hideLoadingOverlay();

      if (data.success) {
        showNotification("Document uploaded successfully", "success");
        toggleEditForm(documentId);
        loadDocuments();
      } else {
        showNotification(data.message || "Upload failed", "error");
      }
    })
    .catch((error) => {
      hideLoadingOverlay();
      console.error("Error:", error);
      showNotification("Upload failed", "error");
    });
}

function viewLoanDetails(applicationId) {
  // Fetch detailed loan information from the dashboard endpoint
  const url = new URL(window.location.href);
  url.searchParams.set("action", "get_loan_details");
  url.searchParams.set("application_id", applicationId);

  fetch(url, {
    method: "GET",
    headers: {
      "Content-Type": "application/json",
    },
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showLoanModal(data.loan, data.documents, data.remarks, data.schedule);
      } else {
        showNotification(data.message || "Error loading loan details", "error");
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showNotification("Failed to load loan details", "error");
    });
}

// Alias for compatibility with HTML onclick handlers
function openLoanDetailsModal(applicationId) {
  viewLoanDetails(applicationId);
}

// Make it global
window.openLoanDetailsModal = openLoanDetailsModal;

function showLoanModal(loan, documents = [], remarks = [], schedule = []) {
  // Create and display modal with loan details
  const modal = document.createElement("div");
  modal.className = "modal show";
  modal.id = "loan-modal";

  modal.innerHTML = `
    <div class="modal-content">
      <div class="modal-header">
        <h2>Loan Application Details</h2>
        <button class="close-btn" onclick="closeLoanModal()">×</button>
      </div>
      <div class="modal-body">
        <!-- Application Information Section -->
        <div class="modal-section">
          <h3>Application Information</h3>
          <div class="info-grid">
            <div class="info-item">
              <label>Application ID:</label>
              <span>${loan.application_id}</span>
            </div>
            <div class="info-item">
              <label>Loan Type:</label>
              <span>${loan.type_name || "N/A"}</span>
            </div>
            <div class="info-item">
              <label>Status:</label>
              <span class="status-badge ${loan.status.toLowerCase()}">
                ${loan.status}
              </span>
            </div>
            <div class="info-item">
              <label>Application Date:</label>
              <span>${new Date(loan.created_at).toLocaleDateString()}</span>
            </div>
          </div>
        </div>

        <!-- Financial Information Section -->
        <div class="modal-section">
          <h3>Financial Information</h3>
          <div class="info-grid">
            <div class="info-item">
              <label>Amount Applied:</label>
              <span>₱${parseFloat(
                loan.amount_applied || loan.amount || 0
              ).toFixed(2)}</span>
            </div>
            ${
              loan.final_loan_amount
                ? `
            <div class="info-item">
              <label>Final Approved Amount:</label>
              <span>₱${parseFloat(loan.final_loan_amount).toFixed(2)}</span>
            </div>
            `
                : ""
            }
            ${
              loan.term_length
                ? `
            <div class="info-item">
              <label>Term Length:</label>
              <span>${loan.term_length} months</span>
            </div>
            `
                : ""
            }
            ${
              loan.repayment_frequency
                ? `
            <div class="info-item">
              <label>Repayment Frequency:</label>
              <span>${loan.repayment_frequency}</span>
            </div>
            `
                : ""
            }
          </div>
        </div>

        <!-- Submitted Documents Section -->
        ${
          documents && documents.length > 0
            ? `
        <div class="documents-section">
          <h3>Submitted Documents</h3>
          <table class="documents-table">
            <thead>
              <tr>
                <th>Document</th>
                <th>Status</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              ${documents
                .map(
                  (doc) => `
              <tr>
                <td>
                  <a href="${doc.file_path}" target="_blank" class="doc-link">
                    <i class="fas fa-file-pdf doc-icon"></i>
                    ${doc.document_name}
                  </a>
                </td>
                <td>
                  <span class="status-badge ${doc.status.toLowerCase()}">
                    ${doc.status}
                  </span>
                </td>
                <td>${
                  doc.status_updated_at
                    ? new Date(doc.status_updated_at).toLocaleDateString()
                    : "N/A"
                }</td>
              </tr>
            `
                )
                .join("")}
            </tbody>
          </table>
        </div>
        `
            : ""
        }

        <!-- Remarks & Updates Section -->
        ${
          remarks && remarks.length > 0
            ? `
        <div class="remarks-section">
          <h3>Remarks & Updates</h3>
          <div class="remarks-timeline">
            ${remarks
              .map(
                (remark) => `
              <div class="remark-item">
                <div class="remark-time">${new Date(
                  remark.created_at
                ).toLocaleString()}</div>
                <div class="remark-text">${remark.remarks}</div>
              </div>
            `
              )
              .join("")}
          </div>
        </div>
        `
            : ""
        }

        <!-- Payment Schedule Section -->
        ${
          schedule &&
          schedule.length > 0 &&
          (loan.status === "Active" || loan.status === "Closed")
            ? `
        <div class="schedule-section">
          <h3>Payment Schedule</h3>
          <table class="schedule-table">
            <thead>
              <tr>
                <th>Due Date</th>
                <th>Amount</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              ${schedule
                .map(
                  (payment) => `
                <tr>
                  <td>${new Date(payment.due_date).toLocaleDateString()}</td>
                  <td>₱${parseFloat(payment.amount).toFixed(2)}</td>
                  <td>₱${parseFloat(payment.principal_amount || 0).toFixed(
                    2
                  )}</td>
                  <td>₱${parseFloat(payment.interest_amount || 0).toFixed(
                    2
                  )}</td>
                  <td><span class="status-badge ${payment.status.toLowerCase()}">${
                    payment.status
                  }</span></td>
                </tr>
              `
                )
                .join("")}
            </tbody>
          </table>
        </div>
        `
            : ""
        }
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Close modal when clicking outside
  modal.addEventListener("click", function (event) {
    if (event.target === modal) {
      closeLoanModal();
    }
  });
}

function closeLoanModal() {
  const modal = document.getElementById("loan-modal");
  if (modal) {
    modal.remove();
  }
}

// Make it global
window.closeLoanModal = closeLoanModal;

function showNotification(message, type = "success") {
  // Remove existing notifications
  const existingNotifications = document.querySelectorAll(".notification");
  existingNotifications.forEach((notif) => notif.remove());

  // Create notification element
  const notification = document.createElement("div");
  notification.className = `notification ${type}`;
  notification.innerHTML = `
    <span>${message}</span>
    <button onclick="this.parentElement.remove()" class="close-btn">×</button>
  `;

  document.body.appendChild(notification);

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

function showLoadingOverlay(message = "Loading...") {
  const overlay = document.createElement("div");
  overlay.className = "loading-overlay";
  overlay.id = "loading-overlay";
  overlay.innerHTML = `
    <div class="loading-content">
      <i class="fas fa-spinner fa-spin"></i>
      <p>${message}</p>
    </div>
  `;

  document.body.appendChild(overlay);
}

function hideLoadingOverlay() {
  const overlay = document.getElementById("loading-overlay");
  if (overlay) {
    overlay.remove();
  }
}

// ========== DOCUMENT STATUS FUNCTIONS ==========
let activeLoanApplicationId = null;

// Initialize document status loading
document.addEventListener("DOMContentLoaded", function () {
  const applicationIdElement = document.querySelector(".banner-info p");
  if (applicationIdElement) {
    const text = applicationIdElement.textContent;
    const match = text.match(/(?:Application ID|Loan ID):\s*(.+)/);
    if (match) {
      activeLoanApplicationId = match[1].trim();
      loadDocumentStatus();

      // Add refresh button listener
      const refreshBtn = document.getElementById("refreshDocStatusBtn");
      if (refreshBtn) {
        refreshBtn.addEventListener("click", function () {
          this.style.animation = "none";
          setTimeout(() => {
            this.style.animation = "";
            loadDocumentStatus();
          }, 10);
        });
      }
    }
  }

  // Add modal close on outside click
  const documentModal = document.getElementById("documentStatusModal");
  if (documentModal) {
    documentModal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeDocumentStatusModal();
      }
    });
  }
});

function loadDocumentStatus() {
  if (!activeLoanApplicationId) {
    return;
  }

  fetch(
    `user_dashboard.php?action=get_document_status&application_id=${encodeURIComponent(
      activeLoanApplicationId
    )}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        displayDocumentStatus(data.documents);
      } else {
        console.error("Error loading document status:", data);
      }
    })
    .catch((error) => console.error("Error loading document status:", error));
}

function displayDocumentStatus(documents) {
  const grid = document.getElementById("documentStatusGrid");
  if (!grid) return;

  if (!documents || documents.length === 0) {
    grid.innerHTML = `
      <div class="no-documents">
        <i class="fas fa-inbox"></i>
        <p>No documents found</p>
      </div>
    `;
    return;
  }

  grid.innerHTML = "";

  documents.forEach((doc) => {
    const card = document.createElement("div");
    card.className = "document-card";
    card.setAttribute("data-doc-id", doc.document_id);

    const statusClass = doc.status ? doc.status.toLowerCase() : "pending";
    const statusIcon =
      statusClass === "approved"
        ? "✅"
        : statusClass === "rejected"
        ? "❌"
        : "⏳";

    card.innerHTML = `
      <div class="document-card-header">
        <div class="document-name">${doc.document_name || "Document"}</div>
        <span class="status-badge status-${statusClass}">${
      doc.status || "Pending"
    }</span>
      </div>
      <div class="document-card-body">
        <div class="document-info">
          <span class="info-label">Status:</span>
          <span class="info-value ${statusClass}">${statusIcon} ${
      doc.status || "Pending"
    }</span>
        </div>
        <div class="document-info">
          <span class="info-label">Last Updated:</span>
          <span class="info-value">${
            doc.status_updated_at
              ? new Date(doc.status_updated_at).toLocaleDateString("en-US", {
                  month: "short",
                  day: "numeric",
                  year: "numeric",
                })
              : "N/A"
          }</span>
        </div>
        ${
          doc.rejection_notes
            ? `<div class="document-info"><span class="info-label">Reason:</span><span class="info-value rejection">${doc.rejection_notes}</span></div>`
            : ""
        }
      </div>
    `;

    grid.appendChild(card);
  });

  // Attach event listeners to resubmit buttons
  const resubmitButtons = grid.querySelectorAll(".btn-resubmit");
  resubmitButtons.forEach((btn) => {
    btn.addEventListener("click", function () {
      const docId = this.getAttribute("data-doc-id");
      const docName = this.getAttribute("data-doc-name");
      showResubmitModal(docId, docName);
    });
  });
}

function handleViewDocument(button) {
  const docId = button.getAttribute("data-doc-id");
  const docName = button.getAttribute("data-doc-name");
  const status = button.getAttribute("data-status");
  const updatedAt = button.getAttribute("data-updated-at");
  const rejectionNotes = button.getAttribute("data-rejection-notes") || null;
  const filePath = button.getAttribute("data-file-path") || null;

  viewDocumentDetails(
    docId,
    docName,
    status,
    updatedAt,
    rejectionNotes,
    filePath
  );
}

function handleEditDocument(button) {
  const docId = button.getAttribute("data-doc-id");
  const docName = button.getAttribute("data-doc-name");

  editRejectedDocument(docId, docName);
}

function viewDocumentDetails(
  docId,
  docName,
  status,
  updatedAt,
  rejectionNotes,
  filePath
) {
  const modal = document.getElementById("documentStatusModal");
  const content = document.getElementById("documentStatusContent");

  if (!modal || !content) return;

  let detailsHtml = `
    <div class="document-detail-item">
      <span class="document-detail-label">Document Name</span>
      <p class="document-detail-value">${docName}</p>
    </div>
    <div class="document-detail-item">
      <span class="document-detail-label">Current Status</span>
      <p class="document-detail-value">
        <span class="status-badge ${status}">${status.toUpperCase()}</span>
      </p>
    </div>
    <div class="document-detail-item">
      <span class="document-detail-label">Last Updated</span>
      <p class="document-detail-value">
        ${
          updatedAt
            ? new Date(updatedAt).toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric",
              })
            : "N/A"
        }
      </p>
    </div>
  `;

  // Display file preview/download if file path exists
  if (filePath) {
    const isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(filePath);
    const isPDF = /\.pdf$/i.test(filePath);

    if (isImage) {
      detailsHtml += `
        <div class="document-detail-item">
          <span class="document-detail-label">Document Preview</span>
          <div class="document-preview-container">
            <img src="${filePath}" alt="${docName}" class="document-preview-image">
          </div>
        </div>
      `;
    } else if (isPDF) {
      detailsHtml += `
        <div class="document-detail-item">
          <span class="document-detail-label">Document</span>
          <div class="document-preview-container">
            <a href="${filePath}" target="_blank" class="pdf-download-btn">
              <i class="fas fa-file-pdf"></i> Open PDF
            </a>
            <p style="font-size: 12px; color: #666; margin-top: 8px;">Click to open or download the PDF file</p>
          </div>
        </div>
      `;
    } else {
      detailsHtml += `
        <div class="document-detail-item">
          <span class="document-detail-label">Document</span>
          <div class="document-preview-container">
            <a href="${filePath}" download class="file-download-btn">
              <i class="fas fa-download"></i> Download File
            </a>
          </div>
        </div>
      `;
    }
  }

  if (rejectionNotes) {
    detailsHtml += `
      <div class="rejection-history-section" id="rejectionHistorySection">
        <div class="rejection-history-loading">
          <i class="fas fa-spinner fa-spin"></i> Loading rejection history...
        </div>
      </div>
    `;
  }

  content.innerHTML = detailsHtml;
  modal.classList.add("show");

  // Fetch rejection history if document is rejected
  if (rejectionNotes) {
    fetchRejectionHistory(docId);
  }
}

function fetchRejectionHistory(documentId) {
  fetch(
    `user_dashboard.php?action=get_rejection_history&document_id=${documentId}`
  )
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.history.length > 0) {
        displayRejectionHistory(data.history);
      } else {
        const section = document.getElementById("rejectionHistorySection");
        if (section) {
          section.innerHTML = `
            <div class="rejection-history-item">
              <p style="color: #666; font-size: 12px;">No additional rejection history available.</p>
            </div>
          `;
        }
      }
    })
    .catch((error) => {
      console.error("Error fetching rejection history:", error);
      const section = document.getElementById("rejectionHistorySection");
      if (section) {
        section.innerHTML = `
          <div class="rejection-history-item">
            <p style="color: #d32f2f; font-size: 12px;">Error loading rejection history.</p>
          </div>
        `;
      }
    });
}

function displayRejectionHistory(history) {
  const section = document.getElementById("rejectionHistorySection");
  if (!section) return;

  let historyHtml = `
    <h4><i class="fas fa-history"></i> Rejection History</h4>
    <div class="rejection-history-timeline">
  `;

  history.forEach((item, index) => {
    const date = new Date(item.date);
    const formattedDate = date.toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });

    historyHtml += `
      <div class="rejection-history-item">
        <div class="rejection-history-marker">
          <div class="rejection-history-dot"></div>
          ${
            index < history.length - 1
              ? '<div class="rejection-history-line"></div>'
              : ""
          }
        </div>
        <div class="rejection-history-content">
          <div class="rejection-history-date">${formattedDate}</div>
          <p class="rejection-history-reason">${item.reason}</p>
        </div>
      </div>
    `;
  });

  historyHtml += `
    </div>
  `;

  section.innerHTML = historyHtml;
}

// Alias for showResubmitModal - calls editRejectedDocument
function showResubmitModal(docId, docName) {
  editRejectedDocument(docId, docName);
}

function editRejectedDocument(docId, docName) {
  const modal = document.getElementById("documentStatusModal");
  const content = document.getElementById("documentStatusContent");

  if (!modal || !content) return;

  const formHtml = `
    <div class="edit-document-form">
      <h4><i class="fas fa-edit"></i> Resubmit Document</h4>
      <p style="font-size: 13px; color: #666; margin-bottom: 16px;">
        Please upload a new version of <strong>${docName}</strong>
      </p>
      
      <div class="file-upload-area" id="fileUploadArea">
        <i class="fas fa-cloud-upload-alt"></i>
        <p><strong>Click to upload or drag and drop</strong></p>
        <p style="font-size: 12px;">PNG, JPG or PDF (Max 5MB)</p>
      </div>
      
      <input type="file" id="uploadFileInput" class="upload-file-input" accept=".png,.jpg,.jpeg,.pdf">
      
      <div class="upload-actions">
        <button class="upload-btn" id="submitUploadBtn" disabled>
          <i class="fas fa-check"></i> Submit
        </button>
        <button class="cancel-btn" id="cancelUploadBtn">
          <i class="fas fa-times"></i> Cancel
        </button>
      </div>
    </div>
  `;

  // Store current doc info in window for access in handlers
  window.currentDocId = docId;
  window.currentDocName = docName;

  content.innerHTML = formHtml;
  modal.classList.add("show");

  // Attach event listeners
  setTimeout(() => {
    const fileUploadArea = document.getElementById("fileUploadArea");
    const uploadFileInput = document.getElementById("uploadFileInput");
    const submitBtn = document.getElementById("submitUploadBtn");
    const cancelBtn = document.getElementById("cancelUploadBtn");

    if (fileUploadArea) {
      fileUploadArea.addEventListener("click", function () {
        uploadFileInput.click();
      });
    }

    if (uploadFileInput) {
      uploadFileInput.addEventListener("change", function () {
        handleFileSelect(this);
      });
    }

    if (submitBtn) {
      submitBtn.addEventListener("click", function () {
        submitDocumentUpdate();
      });
    }

    if (cancelBtn) {
      cancelBtn.addEventListener("click", function () {
        closeDocumentStatusModal();
      });
    }
  }, 0);
}

function handleFileSelect(input) {
  const file = input.files[0];
  const submitBtn = document.getElementById("submitUploadBtn");
  const fileUploadArea = document.getElementById("fileUploadArea");

  if (file) {
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ["image/jpeg", "image/png", "application/pdf"];

    if (!allowedTypes.includes(file.type)) {
      alert("Invalid file type. Please upload PNG, JPG, or PDF.");
      input.value = "";
      submitBtn.disabled = true;
      return;
    }

    if (file.size > maxSize) {
      alert("File size exceeds 5MB limit.");
      input.value = "";
      submitBtn.disabled = true;
      return;
    }

    // Generate preview
    const reader = new FileReader();
    reader.onload = function (e) {
      let previewHtml = `
        <div class="file-preview" style="margin-top: 16px; padding: 16px; background: #f5f5f5; border-radius: 8px; border: 1px solid #e0e0e0;">
          <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <i class="fas fa-check-circle" style="color: #1b5e20; font-size: 18px;"></i>
            <div>
              <p style="margin: 0; font-weight: 500; color: #1b5e20;">File Selected</p>
              <p style="margin: 4px 0 0 0; font-size: 12px; color: #666;">${
                file.name
              }</p>
            </div>
          </div>
          <div style="font-size: 12px; color: #666; display: flex; justify-content: space-between;">
            <span>Size: ${(file.size / 1024).toFixed(2)} KB</span>
            <span>Type: ${
              file.type === "application/pdf" ? "PDF" : "Image"
            }</span>
          </div>
      `;

      // Add preview for images
      if (file.type.startsWith("image/")) {
        previewHtml += `
          <div style="margin-top: 12px; border-top: 1px solid #ddd; padding-top: 12px;">
            <p style="margin: 0 0 8px 0; font-weight: 500; font-size: 12px; color: #333;">Preview:</p>
            <img src="${e.target.result}" style="max-width: 100%; max-height: 300px; border-radius: 6px; border: 1px solid #ddd;">
          </div>
        `;
      } else if (file.type === "application/pdf") {
        previewHtml += `
          <div style="margin-top: 12px; border-top: 1px solid #ddd; padding-top: 12px;">
            <p style="margin: 0 0 8px 0; font-weight: 500; font-size: 12px; color: #333;">PDF Document</p>
            <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #ddd; text-align: center;">
              <i class="fas fa-file-pdf" style="font-size: 32px; color: #d32f2f;"></i>
              <p style="margin: 8px 0 0 0; font-size: 12px; color: #666;">Ready for upload</p>
            </div>
          </div>
        `;
      }

      previewHtml += `</div>`;

      // Check if preview already exists and remove it
      const existingPreview = fileUploadArea.querySelector(".file-preview");
      if (existingPreview) {
        existingPreview.remove();
      }

      // Insert preview after the upload area
      fileUploadArea.insertAdjacentHTML("afterend", previewHtml);
    };
    reader.readAsDataURL(file);

    submitBtn.disabled = false;
  } else {
    submitBtn.disabled = true;
    // Remove preview if exists
    const preview = document.querySelector(".file-preview");
    if (preview) {
      preview.remove();
    }
  }
}

function submitDocumentUpdate() {
  const fileInput = document.getElementById("uploadFileInput");
  const file = fileInput.files[0];

  if (!file) {
    alert("Please select a file");
    return;
  }

  const formData = new FormData();
  formData.append("action", "update_document");
  formData.append("document_id", window.currentDocId);
  formData.append("application_id", activeLoanApplicationId);
  formData.append("new_file", file);

  const submitBtn = document.getElementById("submitUploadBtn");
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';

  fetch("user_dashboard.php", {
    method: "POST",
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        showToast("Document updated successfully!", "success");
        closeDocumentStatusModal();
        loadDocumentStatus();
      } else {
        showToast(data.message || "Failed to update document", "error");
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit';
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      showToast("Error uploading document", "error");
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit';
    });
}

function closeDocumentStatusModal() {
  const modal = document.getElementById("documentStatusModal");
  if (modal) {
    modal.classList.remove("show");
  }
}

function showToast(message, type = "success") {
  if (typeof Toastify !== "undefined") {
    Toastify({
      text: message,
      duration: 3000,
      close: true,
      gravity: "top",
      position: "right",
      backgroundColor: type === "success" ? "#28a745" : "#dc2626",
      stopOnFocus: true,
    }).showToast();
  } else {
    alert(message);
  }
}
