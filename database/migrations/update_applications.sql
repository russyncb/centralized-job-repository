-- Add export tracking columns to applications table
ALTER TABLE applications 
ADD COLUMN exported BOOLEAN DEFAULT FALSE,
ADD COLUMN export_date TIMESTAMP NULL;

-- Add index to improve query performance on export status
CREATE INDEX idx_application_export ON applications(exported, export_date); 