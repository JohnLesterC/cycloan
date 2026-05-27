<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM loan_applications
    WHERE user_id = ? AND status IN ('Active', 'Pending')
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] > 0) {
    $_SESSION['error'] = 'You cannot apply for a new loan while you have an active or pending loan application.';
    header('Location: user_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Borrower Registration</title>
    <link rel="stylesheet" href="CSS/loans_register.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body>
    <a href="user_dashboard.php" class="back-button">
        <i class="fas fa-arrow-left"></i>
    </a>
    <div class="container">
        <div class="progress-bar">
            <div class="progress" style="width: 0%"></div>
            <div class="step active" data-label="Basic Details">1</div>
            <div class="step" data-label="Project Details">2</div>
            <div class="step" data-label="Requirements">3</div>
            <div class="step" data-label="Overview">4</div>
        </div>
        <form id="uploadForm" action="loan_register_process.php" method="POST" enctype="multipart/form-data">
            <div class="form-step active" id="step1">
                <h2>Step 1: Basic Details</h2>
                <div class="form-group">
                    <input type="text" id="loanType" name="loanType" required placeholder=" " list="loanTypes">
                    <label for="loanType">Loan Type</label>
                    <datalist id="loanTypes">
                        <option value="Individual">Individual Loan</option>
                        <option value="Cooperative">Cooperative Loan</option>
                    </datalist>
                    <span class="error-message"></span>
                </div>
                <div class="radio-group">
                    <h3>Loan Status</h3>
                    <label><input type="radio" id="new" name="loanStatus" value="new" required disabled> New</label>
                    <label><input type="radio" id="renewal" name="loanStatus" value="renewal" disabled> Renewal</label>
                    <span class="error-message" id="loanStatusError"></span>
                </div>
                <div class="form-group">
                    <input type="text" inputmode="numeric" id="amountApplied" name="amountApplied" required
                        placeholder=" ">
                    <label for="amountApplied">Amount Applied For (PHP)</label>
                    <span class="error-message"></span>
                </div>
                <div class="button-group">
                    <button type="button" onclick="window.location.href='/user_dashboard.php'">Cancel</button>
                    <button type="button" class="next-step" onclick="nextStep(1)">Next</button>
                </div>
            </div>
            <div class="form-step" id="step2">
                <h2>Step 2: Project Details</h2>
                <div class="form-group" id="termLengthField">
                    <select id="termLength" name="termLength" required>
                        <option value="" disabled selected>Select Term Length</option>
                        <option value="6">6 Months</option>
                        <option value="12">12 Months</option>
                        <option value="18">18 Months</option>
                    </select>
                    <label for="termLength">Term Length (Months)</label>
                    <span class="error-message"></span>
                </div>
                <div class="form-group">
                    <select id="repaymentFrequency" name="repaymentFrequency" required>
                        <option value="" disabled selected>Select Repayment Frequency</option>
                        <option value="Monthly">Monthly</option>
                        <option value="Semi-Annually">Semi-Annually</option>
                        <option value="Quarterly">Quarterly</option>
                        <option value="Annually">Annually</option>
                    </select>
                    <label for="repaymentFrequency">Repayment Frequency</label>
                    <span class="error-message"></span>
                </div>
                <div class="radio-group">
                    <h3>Purpose</h3>
                    <label><input type="radio" id="startupCapital" name="purpose" value="Start-up Capital" required>
                        Start-up Capital</label>
                    <label><input type="radio" id="supplementWorkingCapital" name="purpose"
                            value="Supplement Working Capital"> Supplement Working Capital</label>
                    <label><input type="radio" id="others" name="purpose" value="Others"> Others</label>
                    <div class="form-group" id="othersField" style="display: none;">
                        <input type="text" id="othersText" name="othersText" placeholder=" ">
                        <label for="othersText">Specify Other Purpose</label>
                        <span class="error-message"></span>
                    </div>
                    <span class="error-message"></span>
                </div>
                <div class="radio-group">
                    <h3>Type of Project</h3>
                    <label><input type="radio" id="agricultural" name="projectType" value="Agricultural-based" required>
                        Agricultural-based</label>
                    <label><input type="radio" id="nonAgricultural" name="projectType" value="Non-Agricultural">
                        Non-Agricultural</label>
                    <span class="error-message"></span>
                </div>
                <div class="form-group">
                    <textarea id="projectDescription" name="projectDescription" required placeholder=" "></textarea>
                    <label for="projectDescription">Project Description</label>
                    <span class="error-message"></span>
                </div>
                <div class="button-group">
                    <button type="button" onclick="prevStep(2)">Previous</button>
                    <button type="button" class="next-step" onclick="nextStep(2)">Next</button>
                </div>
            </div>
            <div class="form-step" id="step3">
                <h2>Step 3: Document Requirements</h2>
                <p class="step-description">Please upload the required documents below. All files must be in JPEG, PNG,
                    or PDF format (max 5MB each).</p>

                <!-- Individual Requirements -->
                <div class="requirements-section" id="individualSection">
                    <div class="section-header">
                        <div class="header-icon">📄</div>
                        <h3>Individual Loan Documents</h3>
                        <span class="required-badge">Required</span>
                    </div>
                    <div class="requirements-grid individualRequirements">
                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">🖼️</span>
                                <label for="2x2pic" class="doc-title">2x2 Picture</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="2x2pic" name="2x2pic"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('2x2pic')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">🗳️</span>
                                <label for="votersCertificate" class="doc-title">Voter's Certificate</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="votersCertificate" name="votersCertificate"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('votersCertificate')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">🏠</span>
                                <label for="residenceCertificate" class="doc-title">Residence Certificate</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="residenceCertificate" name="residenceCertificate"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file"
                                        onclick="removeFile('residenceCertificate')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">📋</span>
                                <label for="barangayClearance" class="doc-title">Barangay Clearance</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="barangayClearance" name="barangayClearance"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('barangayClearance')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">💼</span>
                                <label for="businessPermit" class="doc-title">Business Permit</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="businessPermit" name="businessPermit"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('businessPermit')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">DTI, BBC, or CBP • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>
                    </div>
                </div>

                <!-- Agricultural Requirements -->
                <div class="requirements-section" id="agriculturalSection" style="display: none;">
                    <div class="section-header">
                        <div class="header-icon">🌾</div>
                        <h3>Agricultural Project Documents</h3>
                        <span class="required-badge">Required</span>
                    </div>
                    <div class="requirements-grid agriculturalRequirements">
                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">📊</span>
                                <label for="farmPlanBudget" class="doc-title">Farm Plan & Budget</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="farmPlanBudget" name="farmPlanBudget"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('farmPlanBudget')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>
                    </div>
                </div>

                <!-- Cooperative Requirements -->
                <div class="requirements-section" id="cooperativeSection" style="display: none;">
                    <div class="section-header">
                        <div class="header-icon">🤝</div>
                        <h3>Cooperative Loan Documents</h3>
                        <span class="required-badge">Required</span>
                    </div>
                    <div class="requirements-grid cooperativeRequirements">
                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">📝</span>
                                <label for="loanProjectProposal" class="doc-title">Loan Project Proposal</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="loanProjectProposal" name="loanProjectProposal"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file"
                                        onclick="removeFile('loanProjectProposal')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">💰</span>
                                <label for="auditedFinancial" class="doc-title">Audited Financial Statement</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="auditedFinancial" name="auditedFinancial"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('auditedFinancial')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">Latest year • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">🏦</span>
                                <label for="bankStatement" class="doc-title">Bank Statement</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="bankStatement" name="bankStatement"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('bankStatement')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">Last 6 months • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>

                        <div class="requirement-card hidden" data-required="true">
                            <div class="card-header">
                                <span class="doc-icon">📑</span>
                                <label for="birRegistration" class="doc-title">BIR Registration</label>
                                <span class="required-asterisk">*</span>
                            </div>
                            <div class="upload-area">
                                <input type="file" id="birRegistration" name="birRegistration"
                                    accept="image/jpeg,image/png,application/pdf" onchange="handleFileUpload(this)">
                                <div class="upload-placeholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Choose file or drag here</span>
                                </div>
                                <div class="file-info" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span class="file-name"></span>
                                    <button type="button" class="remove-file" onclick="removeFile('birRegistration')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-note">JPEG, PNG, PDF • Max 5MB</div>
                            <div class="error-message"></div>
                        </div>
                    </div>
                </div>

                <div class="upload-progress" style="display: none;">
                    <div class="progress-info">
                        <span class="progress-text">Uploading files...</span>
                        <span class="progress-percent">0%</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" onclick="prevStep(3)">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="next-step" onclick="nextStep(3)">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div class="form-step" id="step4">
                <h2>Step 4: Loan Application Overview</h2>
                <p class="step-description">Please review your loan application details before submitting.</p>

                <div class="overview-container">
                    <!-- Loan Details Card -->
                    <div class="overview-card">
                        <div class="card-header-overview">
                            <div class="header-icon-overview">💰</div>
                            <h3>Loan Details</h3>
                        </div>
                        <div class="overview-grid">
                            <div class="overview-item">
                                <span class="item-label">Application ID:</span>
                                <span class="item-value" id="overviewApplicationId">Will be generated upon
                                    submission</span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Loan ID:</span>
                                <span class="item-value" id="overviewLoanId">Will be generated upon submission</span>
                            </div>
                            <div class="overview-item highlight">
                                <span class="item-label">Loan Type:</span>
                                <span class="item-value" id="overviewLoanType"></span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Loan Status:</span>
                                <span class="item-value status-badge" id="overviewLoanStatus"></span>
                            </div>
                            <div class="overview-item highlight">
                                <span class="item-label">Principal Amount:</span>
                                <span class="item-value amount" id="overviewPrincipal"></span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Interest Rate:</span>
                                <span class="item-value" id="overviewInterestRate"></span>
                            </div>
                            <div class="overview-item highlight">
                                <span class="item-label">Total Payable Amount:</span>
                                <span class="item-value amount" id="overviewTotalPayable"></span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Term Length:</span>
                                <span class="item-value" id="overviewTermLength"></span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Repayment Frequency:</span>
                                <span class="item-value" id="overviewRepaymentFrequency"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Project Details Card -->
                    <div class="overview-card">
                        <div class="card-header-overview">
                            <div class="header-icon-overview">📋</div>
                            <h3>Project Details</h3>
                        </div>
                        <div class="overview-grid">
                            <div class="overview-item">
                                <span class="item-label">Purpose:</span>
                                <span class="item-value" id="overviewPurpose"></span>
                            </div>
                            <div class="overview-item">
                                <span class="item-label">Project Type:</span>
                                <span class="item-value" id="overviewProjectType"></span>
                            </div>
                            <div class="overview-item full-width">
                                <span class="item-label">Project Description:</span>
                                <span class="item-value description" id="overviewProjectDescription"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Uploaded Documents Card -->
                    <div class="overview-card">
                        <div class="card-header-overview">
                            <div class="header-icon-overview">📄</div>
                            <h3>Uploaded Documents</h3>
                        </div>
                        <ul id="overviewDocuments" class="documents-list"></ul>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" onclick="prevStep(4)">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </div>
        </form>
    </div>
    </div>

    </div>

    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <p id="modalMessage"></p>
        </div>
    </div>
    <script src="JAVASCRIPT/step3_validation.js"></script>
    <script src="JAVASCRIPT/loan_register.js"></script>
</body>

</html>