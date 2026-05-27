-- =====================================================
-- Credit Investigation Schema Adjustments
-- =====================================================
-- This migration ensures the database properly stores:
-- 1. Term Length from credit investigation form
-- 2. Remarks from credit investigation form
-- =====================================================

-- Verify loan_applications table has required fields
-- No changes needed - table already has term_length and final_loan_amount

-- Ensure remarks table has correct structure
-- Current structure works - just verify the columns match
-- ALTER TABLE remarks MODIFY remarks TEXT NOT NULL; -- Already correct

-- Add index for faster queries (if they don't already exist)
ALTER TABLE remarks ADD INDEX IF NOT EXISTS idx_application_id (application_id);
ALTER TABLE remarks ADD INDEX IF NOT EXISTS idx_created_at (created_at);
ALTER TABLE loan_applications ADD INDEX IF NOT EXISTS idx_credit_investigation (credit_investigation_status);
ALTER TABLE loan_applications ADD INDEX IF NOT EXISTS idx_final_amount (final_loan_amount);

-- Create view for credit investigation details (optional for reporting)
CREATE OR REPLACE VIEW credit_investigation_summary AS
SELECT 
    la.application_id,
    la.final_loan_amount,
    la.term_length,
    la.credit_investigation_status,
    COUNT(r.remark_id) as remark_count,
    MAX(r.created_at) as last_remark_date,
    u.first_name,
    u.last_name,
    u.email
FROM loan_applications la
LEFT JOIN remarks r ON la.application_id = r.application_id
LEFT JOIN users1 u ON la.user_id = u.id
WHERE la.credit_investigation_status IN ('Completed', 'Failed', 'Pending')
GROUP BY la.application_id, la.final_loan_amount, la.term_length, la.credit_investigation_status, u.first_name, u.last_name, u.email;

-- =====================================================
-- Verification Queries (run separately for checking)
-- These are informational only - not part of migration
-- =====================================================
-- SELECT 'loan_applications - term_length' as field_check, COUNT(*) as count 
-- FROM information_schema.COLUMNS 
-- WHERE TABLE_NAME = 'loan_applications' 
-- AND COLUMN_NAME = 'term_length' 
-- AND TABLE_SCHEMA = DATABASE();

-- SELECT 'loan_applications - final_loan_amount' as field_check, COUNT(*) as count 
-- FROM information_schema.COLUMNS 
-- WHERE TABLE_NAME = 'loan_applications' 
-- AND COLUMN_NAME = 'final_loan_amount' 
-- AND TABLE_SCHEMA = DATABASE();

-- SELECT 'remarks - remarks column' as field_check, COUNT(*) as count 
-- FROM information_schema.COLUMNS 
-- WHERE TABLE_NAME = 'remarks' 
-- AND COLUMN_NAME = 'remarks' 
-- AND TABLE_SCHEMA = DATABASE();
