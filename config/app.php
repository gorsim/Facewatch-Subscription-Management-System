<?php
/**
 * Application Configuration
 * Facewatch Subscription Management System
 */

return [
    'name' => 'Facewatch Subscription Management',
    'version' => '1.0.0',
    'timezone' => 'Europe/London',
    'session_lifetime' => 12 * 60 * 60, // 12 hours in seconds
    'upload_max_size' => 16 * 1024 * 1024, // 16MB
    'allowed_upload_types' => ['csv', 'xlsx', 'xls'],
    'date_format' => 'd/m/Y',
    'datetime_format' => 'd/m/Y H:i:s',
    'currency_symbol' => '£',
    'vat_rate' => 0.20, // 20% VAT
];

