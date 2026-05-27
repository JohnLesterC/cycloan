# 📧 CREDIT INVESTIGATION EMAIL - USER GUIDE

## How to Send Complete Investigation Emails

Follow these steps to send investigation status emails with all details to applicants:

---

## Step 1: Access Credit Investigation Update

1. Login to Admin Dashboard
2. Navigate to "Loan Applications" or "Pending Applications"
3. Find the application to investigate
4. Click "View Details" or edit the application

---

## Step 2: Update Investigation Status

In the credit investigation form, fill in:

### Field 1: Investigation Status (REQUIRED)
```
Select one of:
☐ Pending  - Investigation still in progress
☐ Completed - Investigation successful, approved
☐ Failed    - Investigation did not pass
```

### Field 2: Final Loan Amount (REQUIRED if Completed)
```
Example: 50000
This is the approved loan amount (if Completed)
```

### Field 3: Loan Term (if needed)
```
Select duration:
☐ 6 months
☐ 12 months
☐ 18 months
☐ 24 months
☐ 36 months
```

---

## Step 3: Add Investigation Notes/Remarks (IMPORTANT!)

**This is what the applicant will see in the email!**

In the "Remarks" or "Notes" field, add details like:

### Example 1: Approved Investigation
```
✅ All documents verified and approved
✅ Credit standing verified as good
✅ Employment status confirmed
✅ Income meets requirements
Ready for office visit and final approval
```

### Example 2: Failed Investigation
```
❌ Credit score below minimum threshold
❌ Missing employment verification
Please contact us to discuss alternative options
```

### Example 3: Pending Investigation
```
⏳ Undergoing income verification
⏳ Waiting for additional documentation
⏳ Employment status review in progress
We will notify you once complete
```

---

## Step 4: Submit & Send Email

1. Click "Update" or "Submit"
2. System automatically sends email to applicant

---

## What Applicant Receives

### Email Will Show:

```
📋 INVESTIGATION DETAILS

Application ID:           #APP-20251113-0001
Investigation Status:     Completed
Amount Requested:         ₱50,000.00
🎯 Approved Amount:       ₱50,000.00  (shown if Completed)
Loan Term:                12 months

📝 INVESTIGATION NOTES & REMARKS

Your notes here...
— Your Name on Nov 20, 2025 2:36 PM

Additional notes...
— Your Name on Nov 20, 2025 3:00 PM
```

---

## Email Fields Explained

| What Applicant Sees | Source | Who Controls It |
|---|---|---|
| Application ID | System | Auto-generated |
| Investigation Status | You select | Admin |
| Amount Requested | From application | Auto-filled |
| Approved Amount | You enter | Admin |
| Loan Term | You select | Admin |
| Investigation Notes | Remarks field | Admin |
| Admin Name | System | Auto-filled |

---

## Best Practices for Remarks

### ✅ DO:
- ✅ Be clear and professional
- ✅ Explain investigation findings
- ✅ Provide next steps
- ✅ Be encouraging for approved applications
- ✅ Suggest alternatives for failed applications
- ✅ Use bullet points for clarity
- ✅ Include timeline if applicable

### ❌ DON'T:
- ❌ Use all CAPS (except abbreviations)
- ❌ Use profanity or unprofessional language
- ❌ Share internal admin details
- ❌ Make promises you can't keep
- ❌ Leave blank (always add context)
- ❌ Use unclear abbreviations

---

## Example Remarks for Different Scenarios

### Scenario 1: APPROVED - Good Credit
```
Investigation completed successfully. Your credit standing is excellent and all documents have been verified. You are approved for a ₱50,000 loan at 12 months. Please visit our office within 7 days to finalize the loan agreement.
```

### Scenario 2: APPROVED - Standard
```
Congratulations! Your investigation has been completed and you are approved for a ₱40,000 loan with a 12-month term. Please bring a valid ID when visiting our office to sign the loan agreement.
```

### Scenario 3: APPROVED - With Conditions
```
Your investigation is complete and you are approved for ₱45,000 (adjusted from ₱50,000 requested). This adjustment was made based on verified income. 12-month term at 12% annual interest. Visit our office to proceed.
```

### Scenario 4: FAILED - Request More Documents
```
We could not complete your investigation at this time due to missing documentation. Please provide updated employment verification and income documents within 7 days. Reply with your documents and we'll resume your investigation.
```

### Scenario 5: FAILED - Income Issue
```
Unfortunately, your investigation reveals that your verified monthly income does not meet our current loan requirements for the requested amount. We recommend applying for a smaller loan amount or retrying after 3 months when your income might increase. Contact us to discuss options.
```

### Scenario 6: PENDING - Under Review
```
Your credit investigation is currently under review. We are verifying your employment details with your employer. You should expect to hear from us within 3-5 business days. No action is needed at this time.
```

---

## Email Testing

### To Test Email Content:

1. Update a test application with investigation status
2. Add detailed remarks
3. Submit
4. Check that applicant (or test email) receives email with:
   - ✅ All investigation details
   - ✅ Your remarks clearly displayed
   - ✅ Professional formatting
   - ✅ Correct application ID
   - ✅ Correct amounts and term

---

## Troubleshooting

### Email Not Sent?
1. Check applicant email is in system
2. Verify you completed ALL required fields:
   - Investigation Status
   - Final Loan Amount (if Completed)
3. Check debug_log.txt for errors
4. Try sending test email from email_debug_test.php

### Remarks Not Showing in Email?
1. Verify you added remarks before submitting
2. Check remarks are not blank
3. Wait 1-2 minutes for email to send
4. Ask recipient to check spam folder

### Wrong Amount Showing?
1. Verify Final Loan Amount was entered correctly
2. Check Amount Requested matches application
3. Different amounts = Correctly displayed in email

---

## Quick Reference

### To Send Investigation Email:

1. **Set Status** → Completed / Failed / Pending
2. **Set Amount** → Approved loan amount
3. **Add Notes** → Investigation findings
4. **Select Term** → 6/12/18/24/36 months
5. **Submit** → Email sent automatically!

### Applicant Receives:
- ✅ Investigation Status
- ✅ Loan Amounts (Requested & Approved)
- ✅ Loan Term
- ✅ Your Investigation Notes

---

## Sample Email Preview

```
═══════════════════════════════════════════
           CYCLOAN LOAN PROGRAM
═══════════════════════════════════════════

Hello John Camit!

✅ Congratulations! Your credit investigation has 
   been completed successfully.

📋 INVESTIGATION DETAILS

Application ID:           #APP-20251113-0001
Investigation Status:     ✓ Completed
Amount Requested:         ₱50,000.00
🎯 Approved Amount:       ₱50,000.00
Loan Term:                12 months

📝 INVESTIGATION NOTES & REMARKS

Good credit standing, all documents verified
— Ken Sollosa on Nov 13, 2025 2:36 PM

Ready for office visit and final approval
— Admin on Nov 13, 2025 2:40 PM

📞 NEXT STEPS

Please visit our office to complete your loan 
processing:
• Bring valid ID and required documents
• Sign loan agreement and terms
• Complete final verification process

🏢 OFFICE INFORMATION

Address: Lower Ground Floor (LG)24 New City Hall Bldg
Phone: 0981-303-8698 | Landline: 545-6789 loc 8018-19
Hours: Mon-Fri 8:00 AM - 5:00 PM

═══════════════════════════════════════════
```

---

## Frequently Asked Questions

**Q: What if the applicant email is wrong?**
A: Email won't be sent. Update email in user profile first, then resubmit.

**Q: Can I edit remarks after submitting?**
A: Yes, add a new remark. Only the most recent 5 remarks appear in the email.

**Q: Does the applicant see all my remarks?**
A: Only the most recent 5 remarks appear in the email.

**Q: What if Final Loan Amount is blank?**
A: It won't show in the email. Always enter if Investigation Status is "Completed".

**Q: Can I resend the email?**
A: Update the application and resubmit to trigger a new email.

**Q: When is the email sent?**
A: Immediately after you submit the update (if all required fields are completed).

---

## Support

For issues or questions:
- Check debug_log.txt for error messages
- Review CREDIT_INVESTIGATION_EMAIL_ENHANCEMENT.md for technical details
- Test using email_debug_test.php

---

**Feature Active:** November 20, 2025  
**Status:** ✅ Ready to Use  
**Version:** 1.0

Happy investigating! 🎯
