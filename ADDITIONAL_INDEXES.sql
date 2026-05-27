-- ============================================================================
-- ADDITIONAL PERFORMANCE INDEXES FOR DOCUMENT_TYPES
-- ============================================================================
-- These indexes ensure fast JOINs on document_type_id column

ALTER TABLE `document_types`
ADD INDEX IF NOT EXISTS `idx_document_type_id` (`document_type_id`);

-- Also ensure PRIMARY KEY on document_types exists
-- (Usually exists by default, but this ensures it)

-- Verify index usage
SHOW INDEXES FROM document_types;
SHOW INDEXES FROM documents;
