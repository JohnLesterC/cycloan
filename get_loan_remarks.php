<?php
/**
 * API endpoint to fetch suggested remarks (possible selections) for decision reasoning
 * Returns JSON array of suggested remarks based on pre-approval status and loan type
 * These are template suggestions that admins can select and save as new remarks
 */

// Set JSON header
header('Content-Type: application/json');

// Get the pre-approval status parameter (optional)
$status = isset($_GET['status']) ? $_GET['status'] : 'Pending';
$loanType = isset($_GET['loan_type']) ? $_GET['loan_type'] : 'Individual'; // Default to Individual

// Suggested remarks organized by pre-approval status and loan type
$suggestedRemarks = [
    'Individual' => [
        'Approved' => [
            'All required documents verified and complete. Pre-approval granted.',
            'Applicant meets all pre-approval criteria and qualifications.',
            'Credit assessment approved. Financial profile satisfactory.',
            'Income verification confirmed. Loan amount supported.',
            'Complete documentation review completed successfully.',
            'Employment verification confirmed. Profile approved for funding.',
            'Debt-to-income ratio acceptable. Application approved.',
            'No adverse credit findings. Pre-approval granted.',
            'Asset verification completed. Applicant qualified for requested amount.',
            'All conditions satisfied. Ready to proceed with loan processing.',
            'Personal financial stability confirmed. Approval recommended.',
            'Others'
        ],
        'Rejected' => [
            'Application does not meet minimum pre-approval requirements.',
            'Credit investigation revealed adverse findings.',
            'Income insufficient to support requested loan amount.',
            'Debt-to-income ratio exceeds acceptable threshold.',
            'Missing critical documentation. Cannot proceed with review.',
            'Employment verification unsuccessful or incomplete.',
            'Insufficient credit history or poor credit rating.',
            'Outstanding liabilities prevent loan approval.',
            'Applicant requested to resubmit with additional documentation.',
            'Loan amount requested exceeds maximum qualification based on income.',
            'Unable to verify income sources and employment history.',
            'Personal credit profile does not meet lending standards.',
            'Others'
        ],
        'Pending' => [
            'Application under review. Awaiting additional documents from applicant.',
            'Credit investigation in progress. Verification pending.',
            'Financial documents being reviewed and verified.',
            'Employment and income verification in process.',
            'Awaiting applicant response to information requests.',
            'Document review ongoing. More time required for assessment.',
            'Third-party verifications being collected.',
            'Applicant contacted for clarification on submitted documents.',
            'Preliminary review completed. Final assessment in progress.',
            'Bank statements and employment records under verification.',
            'Personal credit check and background verification in progress.',
            'Others'
        ]
    ],
    'Cooperative' => [
        'Approved' => [
            'Cooperative financial statements verified and complete. Pre-approval granted.',
            'Cooperative meets all pre-approval criteria and membership requirements.',
            'Cooperative credit assessment approved. Financial profile satisfactory.',
            'Cooperative audit and cooperative officer income verified.',
            'Complete cooperative documentation review completed successfully.',
            'Cooperative leadership verification confirmed. Organization approved for funding.',
            'Cooperative debt-to-income ratio acceptable. Application approved.',
            'No adverse findings in cooperative financial history. Pre-approval granted.',
            'Cooperative asset verification completed. Organization qualified for requested amount.',
            'All cooperative conditions satisfied. Ready to proceed with loan processing.',
            'Cooperative member consensus and organizational stability confirmed. Approval recommended.',
            'Others'
        ],
        'Rejected' => [
            'Cooperative application does not meet minimum pre-approval requirements.',
            'Cooperative credit investigation revealed adverse findings.',
            'Cooperative income insufficient to support requested loan amount.',
            'Cooperative debt-to-income ratio exceeds acceptable threshold.',
            'Missing critical cooperative documentation. Cannot proceed with review.',
            'Cooperative officer verification unsuccessful or incomplete.',
            'Insufficient cooperative credit history or poor financial rating.',
            'Outstanding cooperative liabilities prevent loan approval.',
            'Cooperative requested to resubmit with additional financial documentation.',
            'Loan amount requested exceeds cooperative qualification based on assets.',
            'Unable to verify cooperative income sources and asset information.',
            'Cooperative organizational structure or governance issues identified.',
            'Others'
        ],
        'Pending' => [
            'Cooperative application under review. Awaiting additional documents from organization.',
            'Cooperative credit investigation in progress. Verification pending.',
            'Cooperative financial statements and audits being reviewed and verified.',
            'Cooperative officer income verification and organizational assessment in process.',
            'Awaiting cooperative response to information and documentation requests.',
            'Cooperative documentation review ongoing. More time required for full assessment.',
            'Third-party cooperative verification and member list validation in progress.',
            'Cooperative leadership contacted for clarification on submitted documents.',
            'Preliminary cooperative review completed. Final organizational assessment in progress.',
            'Cooperative financial records and member verification under review.',
            'Cooperative bylaws and organizational structure verification in progress.',
            'Cooperative audit and compliance check pending.',
            'Others'
        ]
    ]
];

// Validate status parameter
if (!isset($suggestedRemarks['Individual'][$status])) {
    $status = 'Pending'; // Default to Pending if invalid
}

// Validate loan type parameter
if (!isset($suggestedRemarks[$loanType])) {
    $loanType = 'Individual'; // Default to Individual if invalid
}

try {
    // Return suggested remarks for the selected status and loan type
    echo json_encode([
        'success' => true,
        'remarks' => array_values($suggestedRemarks[$loanType][$status]),
        'status' => $status,
        'loan_type' => $loanType,
        'count' => count($suggestedRemarks[$loanType][$status])
    ]);

} catch (Exception $e) {
    error_log("Error in get_loan_remarks.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>