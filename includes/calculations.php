<?php
/**
 * Calculation Engines for Prepayments, Cash Flow, and Reconciliation
 */

/**
 * Calculate prepayment for an invoice
 *
 * Formula:
 * - Annual: Value / 365 × (365 - days since invoice)
 * - Quarterly: Value / 91.25 × (91.25 - days since invoice)
 * - Monthly: 0 (goes straight to P&L)
 */
function calculate_prepayment($invoice_amount, $invoice_date, $payment_frequency, $calculation_date = null) {
    if ($calculation_date === null) {
        $calculation_date = date('Y-m-d');
    }

    $days_since_invoice = days_between($invoice_date, $calculation_date);

    // Determine days in period
    switch ($payment_frequency) {
        case 'annual':
            $days_in_period = DAYS_PER_YEAR;
            break;
        case 'quarterly':
            $days_in_period = DAYS_PER_QUARTER;
            break;
        case 'monthly':
            $days_in_period = DAYS_PER_MONTH;
            break;
        default:
            $days_in_period = DAYS_PER_YEAR;
    }

    // Monthly goes straight to P&L (no prepayment)
    if ($payment_frequency === 'monthly') {
        return [
            'days_since_invoice' => $days_since_invoice,
            'days_in_period' => $days_in_period,
            'days_remaining' => 0,
            'prepayment_balance' => 0,
            'pl_credit_amount' => $invoice_amount
        ];
    }

    // Calculate days remaining
    $days_remaining = max(0, $days_in_period - $days_since_invoice);

    // Calculate prepayment balance
    $prepayment_balance = ($invoice_amount / $days_in_period) * $days_remaining;

    // P&L credit is the amount consumed
    $pl_credit_amount = $invoice_amount - $prepayment_balance;

    return [
        'days_since_invoice' => $days_since_invoice,
        'days_in_period' => $days_in_period,
        'days_remaining' => $days_remaining,
        'prepayment_balance' => round($prepayment_balance, 2),
        'pl_credit_amount' => round($pl_credit_amount, 2)
    ];
}

/**
 * Save prepayment calculation to database
 */
function save_prepayment($invoice_id, $subscriber_id, $calculation_date = null) {
    if ($calculation_date === null) {
        $calculation_date = date('Y-m-d');
    }

    // Get invoice details
    $invoice = db_query_one(
        "SELECT invoice_date, invoice_amount, payment_frequency FROM invoices WHERE id = ?",
        [$invoice_id]
    );

    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }

    // Calculate prepayment
    $calc = calculate_prepayment(
        $invoice['invoice_amount'],
        $invoice['invoice_date'],
        $invoice['payment_frequency'],
        $calculation_date
    );

    // Check if record already exists
    $existing = db_query_one(
        "SELECT id FROM prepayments WHERE invoice_id = ? AND calculation_date = ?",
        [$invoice_id, $calculation_date]
    );

    if ($existing) {
        // Update existing record
        $sql = "UPDATE prepayments SET
                days_since_invoice = ?,
                days_in_period = ?,
                prepayment_balance = ?,
                pl_credit_amount = ?
                WHERE id = ?";

        db_execute($sql, [
            $calc['days_since_invoice'],
            $calc['days_in_period'],
            $calc['prepayment_balance'],
            $calc['pl_credit_amount'],
            $existing['id']
        ]);

        return ['success' => true, 'id' => $existing['id'], 'action' => 'updated'];
    } else {
        // Insert new record
        $sql = "INSERT INTO prepayments
                (invoice_id, subscriber_id, calculation_date, invoice_date, invoice_amount,
                 payment_frequency, days_since_invoice, days_in_period, prepayment_balance, pl_credit_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        db_execute($sql, [
            $invoice_id,
            $subscriber_id,
            $calculation_date,
            $invoice['invoice_date'],
            $invoice['invoice_amount'],
            $invoice['payment_frequency'],
            $calc['days_since_invoice'],
            $calc['days_in_period'],
            $calc['prepayment_balance'],
            $calc['pl_credit_amount']
        ]);

        return ['success' => true, 'id' => db_last_insert_id(), 'action' => 'created'];
    }
}

/**
 * Calculate expected invoice amount based on camera count and pricing
 */
function calculate_expected_invoice($subscriber_id, $main_cameras, $additional_cameras, $invoice_date) {
    // Get subscriber contract
    $contract = db_query_one(
        "SELECT * FROM subscriber_contracts
         WHERE subscriber_id = ? AND effective_date <= ?
         ORDER BY effective_date DESC LIMIT 1",
        [$subscriber_id, $invoice_date]
    );


    if (!$contract) {
        return ['success' => false, 'message' => 'No contract found for subscriber'];
    }

    $total_cameras = $main_cameras + $additional_cameras;

    // Check if using hardcoded rate
    if ($contract['is_hardcoded_rate']) {
        $main_rate = $contract['main_camera_rate'];
        $additional_rate = $contract['additional_camera_rate'];
    } else {
        // Get pricing tier
        $tier = get_pricing_tier($total_cameras, $invoice_date);

        if (!$tier) {
            return ['success' => false, 'message' => 'No pricing tier found'];
        }

        $main_rate = $contract['payment_frequency'] === 'monthly'
            ? $tier['monthly_price_per_camera']
            : $tier['annual_price_per_camera'];

        // Additional camera rate (if specified as percentage)
        if ($contract['additional_camera_percentage']) {
            $additional_rate = $main_rate * ($contract['additional_camera_percentage'] / 100);
        } else {
            $additional_rate = $contract['additional_camera_rate'] ?? $main_rate;
        }
    }

    $expected_amount = ($main_cameras * $main_rate) + ($additional_cameras * $additional_rate);

    return [
        'success' => true,
        'main_cameras' => $main_cameras,
        'additional_cameras' => $additional_cameras,
        'total_cameras' => $total_cameras,
        'main_rate' => $main_rate,
        'additional_rate' => $additional_rate,
        'expected_amount' => round($expected_amount, 2)
    ];
}

/**
 * Reconcile invoice against expected amount and installed cameras
 */
function reconcile_invoice($invoice_id) {
    // Get invoice details
    $invoice = db_query_one(
        "SELECT i.*, s.subscriber_name
         FROM invoices i
         JOIN subscribers s ON s.id = i.subscriber_id
         WHERE i.id = ?",
        [$invoice_id]
    );

    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }

    // Calculate expected amount
    $expected = calculate_expected_invoice(
        $invoice['subscriber_id'],
        $invoice['main_cameras'],
        $invoice['additional_cameras'],
        $invoice['invoice_date']
    );

    if (!$expected['success']) {
        return $expected;
    }

    // Get installed cameras (sum from camera_counts up to invoice date)
    $installed = db_query_one(
        "SELECT
            COALESCE(SUM(main_cameras_installed), 0) as installed_main,
            COALESCE(SUM(additional_cameras_installed), 0) as installed_additional
         FROM camera_counts
         WHERE subscriber_id = ? AND month_date <= ?",
        [$invoice['subscriber_id'], $invoice['invoice_date']]
    );

    $installed_main = $installed['installed_main'] ?? 0;
    $installed_additional = $installed['installed_additional'] ?? 0;

    // Calculate variances
    $amount_variance = $invoice['invoice_amount'] - $expected['expected_amount'];
    $amount_variance_pct = $expected['expected_amount'] > 0
        ? ($amount_variance / $expected['expected_amount']) * 100
        : 0;

    $installation_variance_main = $invoice['main_cameras'] - $installed_main;
    $installation_variance_additional = $invoice['additional_cameras'] - $installed_additional;

    // Determine status
    $status = 'ok';
    if (abs($amount_variance) > 0.01 || $installation_variance_main != 0 || $installation_variance_additional != 0) {
        $status = abs($amount_variance) > ($expected['expected_amount'] * 0.05) ? 'error' : 'warning';
    }

    // Save to reconciliation_log
    $check_date = date('Y-m-d');

    $sql = "INSERT INTO reconciliation_log
            (invoice_id, subscriber_id, check_date, invoiced_main_cameras, invoiced_additional_cameras,
             installed_main_cameras, installed_additional_cameras, expected_amount, actual_amount,
             variance_percentage, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            installed_main_cameras = VALUES(installed_main_cameras),
            installed_additional_cameras = VALUES(installed_additional_cameras),
            expected_amount = VALUES(expected_amount),
            actual_amount = VALUES(actual_amount),
            variance_percentage = VALUES(variance_percentage),
            status = VALUES(status)";

    db_execute($sql, [
        $invoice_id,
        $invoice['subscriber_id'],
        $check_date,
        $invoice['main_cameras'],
        $invoice['additional_cameras'],
        $installed_main,
        $installed_additional,
        $expected['expected_amount'],
        $invoice['invoice_amount'],
        round($amount_variance_pct, 2),
        $status
    ]);

    return [
        'success' => true,
        'invoice_number' => $invoice['invoice_number'],
        'subscriber_name' => $invoice['subscriber_name'],
        'invoiced_main' => $invoice['main_cameras'],
        'invoiced_additional' => $invoice['additional_cameras'],
        'installed_main' => $installed_main,
        'installed_additional' => $installed_additional,
        'installation_variance_main' => $installation_variance_main,
        'installation_variance_additional' => $installation_variance_additional,
        'expected_amount' => $expected['expected_amount'],
        'actual_amount' => $invoice['invoice_amount'],
        'amount_variance' => round($amount_variance, 2),
        'amount_variance_pct' => round($amount_variance_pct, 2),
        'status' => $status
    ];
}

/**
 * Generate cash flow forecast for an invoice
 */
function generate_cash_flow_forecast($invoice_id) {
    // Get invoice details
    $invoice = db_query_one(
        "SELECT i.*, s.payment_terms_days
         FROM invoices i
         JOIN subscribers s ON s.id = i.subscriber_id
         WHERE i.id = ?",
        [$invoice_id]
    );

    if (!$invoice) {
        return ['success' => false, 'message' => 'Invoice not found'];
    }

    // Skip if already paid
    if ($invoice['payment_status'] === 'paid') {
        return ['success' => true, 'message' => 'Invoice already paid', 'skipped' => true];
    }

    // Calculate expected payment date
    $payment_terms_days = $invoice['payment_terms_days'] ?? 30;
    $expected_payment_date = date('Y-m-d', strtotime($invoice['invoice_date'] . " + $payment_terms_days days"));

    // Determine confidence level based on payment history
    // For now, default to medium (can be enhanced with historical data)
    $confidence_level = 'medium';

    // Check if forecast already exists
    $existing = db_query_one(
        "SELECT id FROM cash_flow_forecast WHERE invoice_id = ?",
        [$invoice_id]
    );

    if ($existing) {
        // Update existing
        $sql = "UPDATE cash_flow_forecast SET
                expected_payment_date = ?,
                expected_amount = ?,
                confidence_level = ?
                WHERE id = ?";

        db_execute($sql, [
            $expected_payment_date,
            $invoice['invoice_amount'],
            $confidence_level,
            $existing['id']
        ]);

        return ['success' => true, 'id' => $existing['id'], 'action' => 'updated'];
    } else {
        // Insert new
        $sql = "INSERT INTO cash_flow_forecast
                (invoice_id, subscriber_id, expected_payment_date, expected_amount, confidence_level)
                VALUES (?, ?, ?, ?, ?)";

        db_execute($sql, [
            $invoice_id,
            $invoice['subscriber_id'],
            $expected_payment_date,
            $invoice['invoice_amount'],
            $confidence_level
        ]);

        return ['success' => true, 'id' => db_last_insert_id(), 'action' => 'created'];
    }
}

/**
 * Batch process all invoices for prepayments, reconciliation, and cash flow
 */
function process_all_invoices($calculation_date = null) {
    if ($calculation_date === null) {
        $calculation_date = date('Y-m-d');
    }

    $invoices = db_query("SELECT id, subscriber_id FROM invoices");

    $results = [
        'total' => count($invoices),
        'prepayments_created' => 0,
        'prepayments_updated' => 0,
        'reconciliations' => 0,
        'forecasts_created' => 0,
        'forecasts_updated' => 0,
        'errors' => []
    ];

    foreach ($invoices as $invoice) {
        try {
            // Save prepayment
            $prep_result = save_prepayment($invoice['id'], $invoice['subscriber_id'], $calculation_date);
            if ($prep_result['success']) {
                if ($prep_result['action'] === 'created') {
                    $results['prepayments_created']++;
                } else {
                    $results['prepayments_updated']++;
                }
            }

            // Reconcile
            $recon_result = reconcile_invoice($invoice['id']);
            if ($recon_result['success']) {
                $results['reconciliations']++;
            }

            // Generate cash flow forecast
            $forecast_result = generate_cash_flow_forecast($invoice['id']);
            if ($forecast_result['success'] && !isset($forecast_result['skipped'])) {
                if ($forecast_result['action'] === 'created') {
                    $results['forecasts_created']++;
                } else {
                    $results['forecasts_updated']++;
                }
            }

        } catch (Exception $e) {
            $results['errors'][] = "Invoice ID {$invoice['id']}: " . $e->getMessage();
        }
    }

    return $results;
}
