# 🔄 CYCLOAN System Flowcharts

**Created:** November 5, 2025  
**Purpose:** Complete system workflows and decision trees  
**Format:** Mermaid Flowchart diagrams ready for mermaid.js.org

---

## 📋 Table of Contents

1. [User Registration Flow](#1-user-registration-flow)
2. [User Login Flow](#2-user-login-flow)
3. [Loan Application Process](#3-loan-application-process)
4. [Document Verification Flow](#4-document-verification-flow)
5. [Credit Investigation Flow](#5-credit-investigation-flow)
6. [Loan Approval Flow](#6-loan-approval-flow)
7. [Payment Processing Flow](#7-payment-processing-flow)
8. [Admin Dashboard Navigation](#8-admin-dashboard-navigation)
9. [System Admin Controls](#9-system-admin-controls)
10. [OTP Generation & Verification](#10-otp-generation--verification)

---

## 1. User Registration Flow

```mermaid
flowchart TD
    Start([User Visits Registration Page]) --> Step1[Step 1: Personal Information]
    Step1 --> Validate1{Valid Input?}
    Validate1 -->|No| Error1[Show Error Message]
    Error1 --> Step1
    Validate1 -->|Yes| Store1[Store in Session]

    Store1 --> Step2[Step 2: Contact & Address]
    Step2 --> Validate2{Valid Phone & Address?}
    Validate2 -->|No| Error2[Show Error Message]
    Error2 --> Step2
    Validate2 -->|Yes| Store2[Store in Session]

    Store2 --> Step3[Step 3: Financial Information]
    Step3 --> Validate3{Valid Income Data?}
    Validate3 -->|No| Error3[Show Error Message]
    Error3 --> Step3
    Validate3 -->|Yes| Store3[Store in Session]

    Store3 --> Privacy[Privacy Policy & Consent]
    Privacy --> Consent{Accept Terms?}
    Consent -->|No| Decline[Registration Cancelled]
    Consent -->|Yes| SendOTP[Generate & Send OTP Email]

    SendOTP --> OTPSent[OTP Sent Successfully]
    OTPSent --> DBInsert[Insert User into Database]
    DBInsert --> Verify[Redirect to OTP Verification]

    Verify --> OTPInput[User Enters OTP]
    OTPInput --> CheckOTP{OTP Valid?}
    CheckOTP -->|No| OTPError[Show Error, Request Resend]
    OTPError --> OTPInput
    CheckOTP -->|Yes| Success[✅ Registration Complete]
    Success --> Redirect[Redirect to Login]
    Redirect --> End([User Logged In])

    Decline --> End

    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style Success fill:#a5d6a7
    style Error1 fill:#ffcdd2
    style Error2 fill:#ffcdd2
    style Error3 fill:#ffcdd2
    style OTPError fill:#ffcdd2
```

---

## 2. User Login Flow

```mermaid
flowchart TD
    Start([User Visits Login Page]) --> Input[Enter Email & Password]
    Input --> Validate{Valid Format?}
    Validate -->|No| Error1[Show Format Error]
    Error1 --> Input

    Validate -->|Yes| CheckDB[Query Database]
    CheckDB --> UserExists{User Exists?}
    UserExists -->|No| Error2[Invalid Credentials]
    Error2 --> Input

    UserExists -->|Yes| HashMatch{Password Match?}
    HashMatch -->|No| Error3[Invalid Credentials]
    Error3 --> Input

    HashMatch -->|Yes| CheckStatus{Account Active?}
    CheckStatus -->|No| Error4[Account Suspended]
    Error4 --> Input

    CheckStatus -->|Yes| SessionCreate[Create Session]
    SessionCreate --> Regenerate[Regenerate Session ID]
    Regenerate --> SetCookie[Set Secure Cookie]
    SetCookie --> LogActivity[Log Login Activity]

    LogActivity --> CheckRole{User Role?}
    CheckRole -->|Regular User| UserDash[Redirect to User Dashboard]
    CheckRole -->|Admin1| Admin1Dash[Redirect to Admin1 Dashboard]
    CheckRole -->|Admin2| Admin2Dash[Redirect to Admin2 Dashboard]
    CheckRole -->|Superadmin| SuperDash[Redirect to Superadmin Dashboard]

    UserDash --> End([✅ Logged In])
    Admin1Dash --> End
    Admin2Dash --> End
    SuperDash --> End

    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style Error1 fill:#ffcdd2
    style Error2 fill:#ffcdd2
    style Error3 fill:#ffcdd2
    style Error4 fill:#ffcdd2
```

---

## 3. Loan Application Process

```mermaid
flowchart TD
    Start([User Clicks Apply for Loan]) --> CheckLogin{User Logged In?}
    CheckLogin -->|No| Redirect[Redirect to Login]
    Redirect --> End([Exit])

    CheckLogin -->|Yes| ApplicationForm[Display Loan Application Form]
    ApplicationForm --> FillForm[User Fills Form]

    FillForm --> ValidateAmount{Loan Amount Valid?}
    ValidateAmount -->|No| Error1[Show Error]
    Error1 --> FillForm

    ValidateAmount -->|Yes| ValidateTerm{Term Valid?}
    ValidateTerm -->|No| Error2[Show Error]
    Error2 --> FillForm

    ValidateTerm -->|Yes| ValidatePurpose{Purpose Specified?}
    ValidatePurpose -->|No| Error3[Show Error]
    Error3 --> FillForm

    ValidatePurpose -->|Yes| Review[Review Application Summary]
    Review --> Confirm{Confirm Submission?}
    Confirm -->|No| FillForm

    Confirm -->|Yes| SaveDB[Save Application to Database]
    SaveDB --> GenerateAppNum[Generate Application Number]
    GenerateAppNum --> SendConfirm[Send Confirmation Email]

    SendConfirm --> CreateStatus[Set Status: Pending]
    CreateStatus --> NotifyAdmin[Notify Admin2 for Document Review]
    NotifyAdmin --> Success[✅ Application Submitted]

    Success --> DashRedirect[Redirect to User Dashboard]
    DashRedirect --> ViewApp[User Can View Application Status]

    ViewApp --> Application([Application in System])

    style Start fill:#e1f5ff
    style Application fill:#c8e6c9
    style Error1 fill:#ffcdd2
    style Error2 fill:#ffcdd2
    style Error3 fill:#ffcdd2
    style End fill:#ffcdd2
```

---

## 4. Document Verification Flow

```mermaid
flowchart TD
    Start([Admin2 Receives Application]) --> ViewApp[View Application Details]
    ViewApp --> CheckDocs[Check Required Documents]

    CheckDocs --> ListDocs[Display Document Checklist]
    ListDocs --> DocumentReady{All Required Documents Uploaded?}

    DocumentReady -->|No| NotifyUser[Notify User via Email]
    NotifyUser --> RequestDocs[Request Missing Documents]
    RequestDocs --> WaitUpload[Wait for Upload]
    WaitUpload --> CheckDocs

    DocumentReady -->|Yes| VerifyProcess[Begin Verification]
    VerifyProcess --> ReviewDoc1[Review Document 1]
    ReviewDoc1 --> CheckDoc1{Valid?}
    CheckDoc1 -->|No| RejectDoc1[Reject Document]
    RejectDoc1 --> RequestNew1[Request New Upload]
    RequestNew1 --> ReviewDoc1

    CheckDoc1 -->|Yes| ApproveDoc1[Approve Document]
    ApproveDoc1 --> ReviewDoc2[Review Document 2]
    ReviewDoc2 --> CheckDoc2{Valid?}
    CheckDoc2 -->|No| RejectDoc2[Reject Document]
    RejectDoc2 --> RequestNew2[Request New Upload]
    RequestNew2 --> ReviewDoc2

    CheckDoc2 -->|Yes| ApproveDoc2[Approve Document]
    ApproveDoc2 --> AllApproved{All Documents Approved?}

    AllApproved -->|No| PartialApprove[Mark as Partially Approved]
    PartialApprove --> HoldApplication[Hold Application]
    HoldApplication --> WaitMoreDocs[Wait for More Documents]
    WaitMoreDocs --> CheckDocs

    AllApproved -->|Yes| FullApprove[✅ All Documents Approved]
    FullApprove --> UpdateStatus[Update Status: Documents Verified]
    UpdateStatus --> NotifyAdmin1[Notify Admin1 for Credit Investigation]
    NotifyAdmin1 --> LogEntry[Log Activity]

    LogEntry --> Complete([Process Complete])

    style Start fill:#e1f5ff
    style Complete fill:#c8e6c9
    style FullApprove fill:#a5d6a7
    style RejectDoc1 fill:#ffcdd2
    style RejectDoc2 fill:#ffcdd2
```

---

## 5. Credit Investigation Flow

```mermaid
flowchart TD
    Start([Admin1 Receives Application]) --> ViewApp[View Application & Documents]
    ViewApp --> CollectInfo[Gather Applicant Information]

    CollectInfo --> CheckIncome{Verify Income Sources}
    CheckIncome -->|No Employment Records| Flag1[Flag for Manual Review]
    CheckIncome -->|Valid Records| ContinueCheck[Continue Investigation]

    ContinueCheck --> CheckDebts{Check Outstanding Debts?}
    CheckDebts -->|Yes - High Debt| Flag2[Flag for Credit Risk]
    CheckDebts -->|No/Low Debt| ContinueCheck2[Continue]

    ContinueCheck2 --> AnalyzeExpenses{Analyze Expense Ratio}
    AnalyzeExpenses -->|> 70% of Income| Flag3[Flag: High Expense Ratio]
    AnalyzeExpenses -->|< 70% of Income| ContinueCheck3[Continue]

    ContinueCheck3 --> CheckCivilStatus{Civil Status Correct?}
    CheckCivilStatus -->|No| Flag4[Flag: Discrepancy Found]
    CheckCivilStatus -->|Yes| CalculateScore[Calculate Credit Score]

    CalculateScore --> ScoreCheck{Credit Score Acceptable?}
    ScoreCheck -->|< 400| Reject[❌ Reject Application]
    ScoreCheck -->|400-600| Conditional[⚠️ Conditional Approval]
    ScoreCheck -->|> 600| Approve[✅ Approve for Loan]

    Flag1 --> ManualReview[Manual Review by Superadmin]
    Flag2 --> ManualReview
    Flag3 --> ManualReview
    Flag4 --> ManualReview
    ManualReview --> ManualDecision{Approve?}
    ManualDecision -->|No| Reject
    ManualDecision -->|Yes| Approve

    Reject --> UpdateStatus1[Update Status: Credit Failed]
    UpdateStatus1 --> NotifyUser1[Notify User: Application Rejected]
    NotifyUser1 --> EndReject([Process Complete - Rejected])

    Conditional --> UpdateStatus2[Update Status: Credit OK - Conditional]
    UpdateStatus2 --> SetConditions[Set Loan Conditions]
    SetConditions --> NotifyUser2[Notify User & Superadmin]
    NotifyUser2 --> EndConditional([Process Complete - Conditional])

    Approve --> UpdateStatus3[Update Status: Credit OK]
    UpdateStatus3 --> LogInvestigation[Log Investigation Results]
    LogInvestigation --> NotifySuper[Notify Superadmin for Final Review]
    NotifySuper --> EndApprove([Process Complete - Approved])

    style Start fill:#e1f5ff
    style Reject fill:#ffcdd2
    style Approve fill:#c8e6c9
    style Conditional fill:#fff9c4
    style Flag1 fill:#ffe0b2
    style Flag2 fill:#ffe0b2
    style Flag3 fill:#ffe0b2
    style Flag4 fill:#ffe0b2
```

---

## 6. Loan Approval Flow

```mermaid
flowchart TD
    Start([Superadmin Reviews Application]) --> ViewFull[View Complete Application]
    ViewFull --> ReviewAll[Review All Steps]

    ReviewAll --> CheckStep1{Documents OK?}
    CheckStep1 -->|No| Reject1[❌ Reject - Documents Issue]

    CheckStep1 -->|Yes| CheckStep2{Credit OK?}
    CheckStep2 -->|No| Reject2[❌ Reject - Credit Issue]

    CheckStep2 -->|Yes| CalculateLoan[Calculate Loan Details]
    CalculateLoan --> CalcRate{Calculate Interest Rate}
    CalcRate -->|Based on Score| IntRate[Get Interest Rate]
    IntRate --> CalcPayment[Calculate Monthly Payment]
    CalcPayment --> GenerateSchedule[Generate Payment Schedule]

    GenerateSchedule --> FinalReview{Approve Loan?}
    FinalReview -->|No| Reject3[❌ Reject Application]

    Reject1 --> NotifyReject[Notify User: Rejected]
    Reject2 --> NotifyReject
    Reject3 --> NotifyReject
    NotifyReject --> UpdateStatus1[Update Status: Rejected]
    UpdateStatus1 --> LogReject[Log Rejection]
    LogReject --> EndReject([Application Denied])

    FinalReview -->|Yes| CreateLoan[✅ Create Loan Record]
    CreateLoan --> SetLoanStatus[Set Status: Active]
    SetLoanStatus --> GenerateNumber[Generate Loan Number]
    GenerateNumber --> SaveSchedule[Save Payment Schedule]
    SaveSchedule --> NotifyApprove[Send Approval Email to User]
    NotifyApprove --> UpdateAppStatus[Update Application Status: Approved]
    UpdateAppStatus --> LogApproval[Log Approval in Activity]
    LogApproval --> CreateDisbursement[Create Disbursement Record]
    CreateDisbursement --> DisburseFunds[Disburse Loan Amount]
    DisburseFunds --> NotifyUser[Notify User: Funds Disbursed]
    NotifyUser --> Success[✅ Loan Activated]
    Success --> EndSuccess([Loan Active in System])

    style Start fill:#e1f5ff
    style CreateLoan fill:#c8e6c9
    style Success fill:#a5d6a7
    style Reject1 fill:#ffcdd2
    style Reject2 fill:#ffcdd2
    style Reject3 fill:#ffcdd2
    style EndSuccess fill:#c8e6c9
    style EndReject fill:#ffcdd2
```

---

## 7. Payment Processing Flow

```mermaid
flowchart TD
    Start([User Initiates Payment]) --> Login{User Logged In?}
    Login -->|No| LoginRedirect[Redirect to Login]
    LoginRedirect --> Start

    Login -->|Yes| ViewLoans[View Active Loans]
    ViewLoans --> SelectLoan[Select Loan to Pay]
    SelectLoan --> ViewSchedule[View Payment Schedule]
    ViewSchedule --> SelectPayment[Select Payment to Make]

    SelectPayment --> ViewAmount[View Payment Amount]
    ViewAmount --> EnterPayment[Enter Payment Details]

    EnterPayment --> ValidateAmount{Amount Valid?}
    ValidateAmount -->|No| Error1[Show Error]
    Error1 --> EnterPayment

    ValidateAmount -->|Yes| SelectMethod{Select Payment Method}
    SelectMethod -->|Cash| CashPayment[Generate Payment Slip]
    SelectMethod -->|Online| OnlinePayment[Redirect to Payment Gateway]

    CashPayment --> SubmitPayment[Submit Payment]
    OnlinePayment --> ProcessOnline[Process Online Transaction]
    ProcessOnline --> VerifyOnline{Transaction Successful?}
    VerifyOnline -->|No| OnlineError[Show Error Message]
    OnlineError --> OnlinePayment
    VerifyOnline -->|Yes| SubmitPayment

    SubmitPayment --> VerifyPayment{Verify Payment Details}
    VerifyPayment -->|Invalid| PayError[Show Error]
    PayError --> EnterPayment

    VerifyPayment -->|Valid| ProcessPayment[Process Payment]
    ProcessPayment --> UpdateBalance[Update Loan Balance]
    UpdateBalance --> CalculateNext[Calculate Next Payment]
    CalculateNext --> GenerateReceipt[Generate Receipt]
    GenerateReceipt --> SendReceipt[Send Receipt Email]
    SendReceipt --> LogPayment[Log Payment in Activity]

    LogPayment --> CheckBalance{Balance Remaining?}
    CheckBalance -->|Yes| Active[Loan Still Active]
    CheckBalance -->|No| MarkPaid[✅ Mark Loan as Paid]

    Active --> Success[✅ Payment Successful]
    MarkPaid --> Completion[✅ Loan Complete - No Balance]

    Success --> End([Return to Dashboard])
    Completion --> End

    style Start fill:#e1f5ff
    style End fill:#c8e6c9
    style Success fill:#a5d6a7
    style MarkPaid fill:#a5d6a7
    style Error1 fill:#ffcdd2
    style OnlineError fill:#ffcdd2
    style PayError fill:#ffcdd2
```

---

## 8. Admin Dashboard Navigation

```mermaid
flowchart TD
    Start([Admin Logs In]) --> CheckRole{User Role?}

    CheckRole -->|Admin1| Admin1Main[Admin1 Dashboard]
    CheckRole -->|Admin2| Admin2Main[Admin2 Dashboard]
    CheckRole -->|Superadmin| SuperMain[Superadmin Dashboard]

    Admin1Main --> Admin1Menu["📋 Admin1 Functions"]
    Admin1Menu --> A1Opt1[1. View Applications]
    Admin1Menu --> A1Opt2[2. Credit Investigation]
    Admin1Menu --> A1Opt3[3. View Payments]
    Admin1Menu --> A1Opt4[4. Activity Logs]
    Admin1Menu --> A1Opt5[5. Reports]

    A1Opt1 --> A1Dashboard[Dashboard: Pending Investigations]
    A1Opt2 --> A1Credit[Investigation Details & Decision]
    A1Opt3 --> A1Payments[Payment Records & History]
    A1Opt4 --> A1Logs[System Activity Log]
    A1Opt5 --> A1Reports[Generate Reports]

    Admin2Main --> Admin2Menu["📋 Admin2 Functions"]
    Admin2Menu --> A2Opt1[1. View Applications]
    Admin2Menu --> A2Opt2[2. Document Verification]
    Admin2Menu --> A2Opt3[3. Remarks & Notes]
    Admin2Menu --> A2Opt4[4. Activity Logs]
    Admin2Menu --> A2Opt5[5. Reports]

    A2Opt1 --> A2Dashboard[Dashboard: Pending Documents]
    A2Opt2 --> A2Documents[Verify & Approve Documents]
    A2Opt3 --> A2Remarks[Add Remarks to Applications]
    A2Opt4 --> A2Logs[System Activity Log]
    A2Opt5 --> A2Reports[Generate Reports]

    SuperMain --> SuperMenu["📋 Superadmin Functions"]
    SuperMenu --> SOpt1[1. Final Approval]
    SuperMenu --> SOpt2[2. User Management]
    SuperMenu --> SOpt3[3. Admin Management]
    SuperMenu --> SOpt4[4. System Reports]
    SuperMenu --> SOpt5[5. Settings]
    SuperMenu --> SOpt6[6. Activity Logs]

    SOpt1 --> SApproval[Review & Approve Loans]
    SOpt2 --> SUserMgmt[Manage User Accounts]
    SOpt3 --> SAdminMgmt[Manage Admin Accounts]
    SOpt4 --> SReports[Generate System Reports]
    SOpt5 --> SSettings[Configure System Settings]
    SOpt6 --> SLogs[View All Activities]

    A1Dashboard --> Action1{Select Action}
    A1Credit --> Action1
    A1Payments --> Action1
    A1Logs --> Action1
    A1Reports --> Action1

    A2Dashboard --> Action2{Select Action}
    A2Documents --> Action2
    A2Remarks --> Action2
    A2Logs --> Action2
    A2Reports --> Action2

    SApproval --> ActionS{Select Action}
    SUserMgmt --> ActionS
    SAdminMgmt --> ActionS
    SReports --> ActionS
    SSettings --> ActionS
    SLogs --> ActionS

    Action1 --> Process1[Execute Function]
    Action2 --> Process2[Execute Function]
    ActionS --> ProcessS[Execute Function]

    Process1 --> End([Return to Dashboard])
    Process2 --> End
    ProcessS --> End

    style Start fill:#e1f5ff
    style Admin1Main fill:#bbdefb
    style Admin2Main fill:#c8e6c9
    style SuperMain fill:#fff9c4
    style End fill:#ffccbc
```

---

## 9. System Admin Controls

```mermaid
flowchart TD
    Start([Superadmin Accesses Admin Panel]) --> MainMenu["🔧 System Administration"]

    MainMenu --> UserMgmt["👥 User Management"]
    MainMenu --> AdminMgmt["👨‍💼 Admin Management"]
    MainMenu --> LoanMgmt["💰 Loan Management"]
    MainMenu --> SystemConfig["⚙️ System Configuration"]
    MainMenu --> Reports["📊 Reports & Analytics"]

    UserMgmt --> ViewUsers[View All Users]
    ViewUsers --> UserAction{Action?}
    UserAction -->|Suspend| SuspendUser[Suspend User Account]
    UserAction -->|Activate| ActivateUser[Activate User Account]
    UserAction -->|Delete| DeleteUser[Delete User Account]
    UserAction -->|View Details| UserDetails[View User Details]

    AdminMgmt --> ViewAdmins[View All Admins]
    ViewAdmins --> AdminAction{Action?}
    AdminAction -->|Add| AddAdmin[Add New Admin]
    AdminAction -->|Edit| EditAdmin[Edit Admin Details]
    AdminAction -->|Remove| RemoveAdmin[Remove Admin]
    AdminAction -->|Reset Password| ResetAdmin[Reset Admin Password]

    AddAdmin --> SelectRole{Select Role}
    SelectRole -->|Admin1| CreateA1[Create Admin1 Account]
    SelectRole -->|Admin2| CreateA2[Create Admin2 Account]
    CreateA1 --> SendCredentials[Send Login Credentials]
    CreateA2 --> SendCredentials
    SendCredentials --> LogCreation[Log Admin Creation]

    LoanMgmt --> ViewLoans[View All Loans]
    ViewLoans --> LoanAction{Action?}
    LoanAction -->|Approve| ApproveLoan[Approve Loan]
    LoanAction -->|Reject| RejectLoan[Reject Loan]
    LoanAction -->|Modify| ModifyLoan[Modify Loan Terms]
    LoanAction -->|Close| CloseLoan[Close/Complete Loan]

    SystemConfig --> DbConfig[Database Configuration]
    SystemConfig --> EmailConfig[Email Settings]
    SystemConfig --> SecurityConfig[Security Settings]
    SystemConfig --> TimeZoneConfig[Timezone Configuration]

    DbConfig --> BackupDB[Backup Database]
    BackupDB --> ScheduleBackup[Schedule Regular Backups]

    EmailConfig --> SMTPSettings[SMTP Settings]
    SMTPSettings --> TestEmail[Test Email Connection]

    SecurityConfig --> ChangeCSRF[Update CSRF Tokens]
    SecurityConfig --> SessionConfig[Session Configuration]

    TimeZoneConfig --> SetTZ[Set System Timezone: Asia/Manila]
    SetTZ --> VerifyTZ[Verify All Timestamps]

    Reports --> LoanReports[Loan Reports]
    Reports --> PaymentReports[Payment Reports]
    Reports --> UserReports[User Statistics]
    Reports --> ActivityReports[Activity Logs Report]

    LoanReports --> ExportLoan[Export as PDF/CSV]
    PaymentReports --> ExportPayment[Export as PDF/CSV]
    UserReports --> ExportUser[Export as PDF/CSV]
    ActivityReports --> ExportActivity[Export as PDF/CSV]

    SuspendUser --> LogAction1[Log Action]
    ActivateUser --> LogAction1
    DeleteUser --> LogAction1
    RemoveAdmin --> LogAction1
    CloseLoan --> LogAction1

    LogAction1 --> End([Return to Admin Panel])

    style Start fill:#e1f5ff
    style MainMenu fill:#fff9c4
    style UserMgmt fill:#f8bbd0
    style AdminMgmt fill:#e1bee7
    style LoanMgmt fill:#c8e6c9
    style SystemConfig fill:#b2dfdb
    style Reports fill:#ffccbc
    style End fill:#a5d6a7
```

---

## 10. OTP Generation & Verification

```mermaid
flowchart TD
    Start([User Requests OTP]) --> CheckAction{Action Type?}

    CheckAction -->|Registration| RegOTP[Generate OTP for Registration]
    CheckAction -->|Password Reset| ForgotOTP[Generate OTP for Reset]
    CheckAction -->|Resend| ResendOTP[Generate New OTP]

    RegOTP --> GenCode1[Generate Random 6-digit Code]
    ForgotOTP --> GenCode2[Generate Random 6-digit Code]
    ResendOTP --> GenCode3[Generate Random 6-digit Code]

    GenCode1 --> HashOTP1[Hash OTP using BCrypt]
    GenCode2 --> HashOTP2[Hash OTP using BCrypt]
    GenCode3 --> HashOTP3[Hash OTP using BCrypt]

    HashOTP1 --> SetExpiry[Set Expiry: 15 minutes]
    HashOTP2 --> SetExpiry
    HashOTP3 --> SetExpiry

    SetExpiry --> StoreDB[Store in Database]
    StoreDB --> StoreOTP{Store OTP Sent}
    StoreOTP -->|In Session| SessionStore[Store in $_SESSION]
    StoreOTP -->|For Email| EmailOTP[Prepare Email Body]

    SessionStore --> SendEmail[Send OTP Email]
    EmailOTP --> SendEmail

    SendEmail --> EmailCheck{Email Sent?}
    EmailCheck -->|Failed| RetryEmail[Retry Email]
    RetryEmail --> SendEmail

    EmailCheck -->|Success| Confirmation[✅ OTP Sent Successfully]
    Confirmation --> DisplayForm[Display OTP Input Form]

    DisplayForm --> UserInput[User Enters OTP]
    UserInput --> ValidateFormat{Valid Format?}
    ValidateFormat -->|No| FormatError[Show Format Error]
    FormatError --> UserInput

    ValidateFormat -->|Yes| QueryDB[Query Database]
    QueryDB --> FindOTP{OTP Record Found?}

    FindOTP -->|No| OTPError1[❌ Invalid OTP]
    OTPError1 --> UserInput

    FindOTP -->|Yes| CheckExpiry{OTP Expired?}
    CheckExpiry -->|Yes| OTPError2[❌ OTP Expired]
    OTPError2 --> OfferResend[Offer to Resend]
    OfferResend --> ResendOTP

    CheckExpiry -->|No| CompareOTP{OTP Matches?}
    CompareOTP -->|No| AttemptCount{Attempts < 5?}
    AttemptCount -->|Yes| Increment[Increment Attempt Counter]
    Increment --> OTPError3[❌ Invalid OTP]
    OTPError3 --> UserInput

    AttemptCount -->|No| BlockUser[❌ Too Many Attempts]
    BlockUser --> LockAccount[Lock Account Temporarily]
    LockAccount --> NotifyUser[Notify User]

    CompareOTP -->|Yes| Success[✅ OTP Verified]
    Success --> DeleteOTP[Delete OTP from Database]
    DeleteOTP --> MarkVerified[Mark as Verified]

    MarkVerified --> NextAction{What Next?}
    NextAction -->|Registration| CompleteReg[Complete Registration]
    NextAction -->|Password Reset| ResetPass[Allow Password Reset]

    CompleteReg --> SuccessReg[✅ Account Created]
    ResetPass --> SuccessPass[✅ Ready for New Password]

    SuccessReg --> End([Registration Complete])
    SuccessPass --> End
    BlockUser --> EndBlocked([Account Locked])

    style Start fill:#e1f5ff
    style Success fill:#c8e6c9
    style SuccessReg fill:#a5d6a7
    style SuccessPass fill:#a5d6a7
    style OTPError1 fill:#ffcdd2
    style OTPError2 fill:#ffcdd2
    style OTPError3 fill:#ffcdd2
    style BlockUser fill:#d32f2f
    style EndBlocked fill:#d32f2f
```

---

## 🎯 Quick Navigation Guide

| Flowchart                | Process                         | Users         |
| ------------------------ | ------------------------------- | ------------- |
| 1️⃣ Registration          | New user account creation       | Regular Users |
| 2️⃣ Login                 | User authentication             | All Users     |
| 3️⃣ Loan Application      | Loan request submission         | Regular Users |
| 4️⃣ Document Verification | Document approval               | Admin2        |
| 5️⃣ Credit Investigation  | Credit check process            | Admin1        |
| 6️⃣ Loan Approval         | Final approval decision         | Superadmin    |
| 7️⃣ Payment Processing    | Payment submission & processing | Regular Users |
| 8️⃣ Admin Navigation      | Dashboard menu system           | All Admins    |
| 9️⃣ System Admin          | System management controls      | Superadmin    |
| 🔟 OTP Management        | OTP generation & verification   | All Users     |

---

## 💡 How to Use These Flowcharts

### **View Online (Recommended)**

1. Go to **[mermaid.js.org](https://mermaid.js.org)**
2. Click "**Start Editing**"
3. Copy a flowchart code from this file
4. Paste into the editor
5. See visualization instantly ✅

### **In Markdown Files**

````markdown
```mermaid
[copy flowchart code here]
```
````

```

### **Export & Share**
- Screenshot the flowchart
- Export as SVG/PNG
- Add to presentations/documentation
- Share with team members

---

## 🔗 Related Documentation

- 📊 **CYCLOAN_DATABASE_MERMAID_DIAGRAM.md** - Database schema & ER diagrams
- 📖 **PHILIPPINES_IMPLEMENTATION_GUIDE.md** - Implementation steps
- 🔐 **PHILIPPINES_COMPLETE_PACKAGE_SUMMARY.md** - System overview

---

## 📊 Process Statistics

| Process | Steps | Decision Points | Notifications | Time Estimate |
|---------|-------|-----------------|---------------|---------------|
| Registration | 5 steps | 5 | 2 (OTP + Confirmation) | 5-10 min |
| Login | 3 steps | 4 | 0 | 1-2 min |
| Loan Application | 3 steps | 3 | 2 (Submit + Admin) | 10-15 min |
| Document Verification | Multiple | 4+ | 2 (Approve + Reject) | Variable |
| Credit Investigation | 6 steps | 6 | 1 (Notification) | 2-5 business days |
| Loan Approval | 4 steps | 3 | 3 (Decision emails) | 1-2 hours |
| Payment Processing | 5 steps | 4 | 2 (Confirmation + Email) | 2-5 min |
| OTP Verification | 4 steps | 5 | 1 (OTP Email) | 1-2 min |

---

## ✅ Quality Checklist

- ✅ All user workflows documented
- ✅ All decision points included
- ✅ Error handling paths shown
- ✅ Notifications marked
- ✅ Admin functions detailed
- ✅ Security steps highlighted
- ✅ Database interactions shown
- ✅ Email/notification flows included
- ✅ Color-coded for clarity
- ✅ Ready for mermaid.js.org

---

**Created:** November 5, 2025
**Status:** ✅ Complete & Production-Ready
**Format:** Mermaid Flowchart Syntax
**For:** CYCLOAN Loan Management System

---

### 🚀 Next Steps

1. **View Diagrams:** Copy code to [mermaid.js.org](https://mermaid.js.org)
2. **Share with Team:** Export flowcharts as images
3. **Document Process:** Add to project documentation
4. **Train Staff:** Use flowcharts for staff training
5. **Reference:** Keep as standard process documentation

```
