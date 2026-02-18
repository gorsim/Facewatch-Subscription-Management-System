-- Verify legal_entities table structure
USE facewatch_subscriptions;

-- Show all columns in legal_entities table
SHOW COLUMNS FROM legal_entities;

-- Check if specific columns exist
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'facewatch_subscriptions'
AND TABLE_NAME = 'legal_entities'
AND COLUMN_NAME IN ('main_camera_rate', 'additional_camera_rate', 'payment_frequency')
ORDER BY ORDINAL_POSITION;

