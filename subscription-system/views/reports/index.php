<?php
/**
 * Reports Page
 */

use App\Models\Invoice;
use App\Services\PrepaymentCalculator;
use App\Services\InvoiceReconciliationService;
use App\Database;

$pageTitle = 'Reports';
$page = 'reports';

$report = $_GET['report'] ?? 'prepayments';

// Auto-recalculate all invoices before displaying any report
// This ensures the data is always up-to-date
$reconciliationService = new InvoiceReconciliationService();
$reconciliationService->reconcileAll();
error_log("Auto-recalculated all invoices before displaying $report report");

// Handle CSV exports BEFORE any HTML output

// Cash Flow CSV Export
if ($report === 'cashflow' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $db = Database::getInstance();

    // Calculate date range: last 3 months + through to 31/3/31
    $startDate = new DateTime('first day of -3 months');
    $endDate = new DateTime('2031-03-31');

    // Get all invoices with legal entity info
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            le.legal_entity_name,
            le.payment_terms_days,
            le.termination_date as entity_termination_date
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.invoice_date IS NOT NULL
        AND i.invoice_status IN ('draft', 'issued', 'reconciled_to_xero', 'forecast')
        ORDER BY i.invoice_date
    ");

    // Build monthly cash flow data
    $monthlyData = [];
    $currentMonth = clone $startDate;
    $currentMonth->modify('first day of this month');

    while ($currentMonth <= $endDate) {
        $monthKey = $currentMonth->format('Y-m');
        $monthlyData[$monthKey] = [
            'month' => $currentMonth->format('M Y'),
            'expected_cash' => 0,
            'invoice_count' => 0
        ];
        $currentMonth->modify('+1 month');
    }

    // Calculate expected payment dates for each invoice
    foreach ($invoices as $invoice) {
        $invoiceDate = new DateTime($invoice['invoice_date']);
        $paymentTerms = $invoice['payment_terms_days'] ?? 30;

        // Calculate expected payment date
        $expectedPaymentDate = clone $invoiceDate;
        $expectedPaymentDate->modify("+{$paymentTerms} days");

        // Check if entity is terminated before expected payment
        if ($invoice['entity_termination_date']) {
            $terminationDate = new DateTime($invoice['entity_termination_date']);
            if ($expectedPaymentDate > $terminationDate) {
                continue; // Skip if payment expected after termination
            }
        }

        $monthKey = $expectedPaymentDate->format('Y-m');

        if (isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey]['expected_cash'] += $invoice['invoice_amount'];
            $monthlyData[$monthKey]['invoice_count']++;
        }
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cashflow_forecast_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Month', 'Expected Cash Inflow', 'Invoice Count']);

    // Data rows
    foreach ($monthlyData as $data) {
        fputcsv($output, [
            $data['month'],
            number_format($data['expected_cash'], 2),
            $data['invoice_count']
        ]);
    }

    fclose($output);
    exit;
}

// Revenue Forecast CSV Export
if ($report === 'revenue' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $db = Database::getInstance();
    $prepaymentCalc = new PrepaymentCalculator();

    // Calculate date range: through to 31/3/31
    $startDate = new DateTime('first day of this month');
    $endDate = new DateTime('2031-03-31');

    // Get all invoices (including forecast invoices for future projections)
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            le.legal_entity_name,
            le.termination_date as entity_termination_date
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.payment_frequency IN ('annual', 'quarterly')
        AND i.invoice_status IN ('draft', 'issued', 'reconciled_to_xero', 'forecast')
        ORDER BY i.invoice_date
    ");

    // Build monthly P&L data
    $monthlyData = [];
    $currentMonth = clone $startDate;

    while ($currentMonth <= $endDate) {
        $monthKey = $currentMonth->format('Y-m');

        // Calculate start and end of month
        $monthStart = clone $currentMonth;
        $monthStart->modify('first day of this month');
        $monthEnd = clone $currentMonth;
        $monthEnd->modify('last day of this month');

        // Prepaid (Start) should be the balance at the END of the previous month
        $prepaidStartDate = clone $monthStart;
        $prepaidStartDate->modify('-1 day'); // Last day of previous month

        // Calculate prepayments at start and end of month
        $prepaidStart = 0;
        $prepaidEnd = 0;
        $invoicedInMonth = 0;

        foreach ($invoices as $invoice) {
            $invoiceDate = new DateTime($invoice['invoice_date']);

            // Skip invoices dated after the end of this month
            if ($invoiceDate > $monthEnd) {
                continue;
            }

            // Check if entity is terminated
            if ($invoice['entity_termination_date']) {
                $terminationDate = new DateTime($invoice['entity_termination_date']);
                if ($monthStart > $terminationDate) {
                    continue; // Skip if month is after termination
                }
            }

            // Prepayment at start of month (= end of previous month)
            $calcStart = $prepaymentCalc->calculate($invoice, $prepaidStartDate->format('Y-m-d'));
            $prepaidStart += $calcStart['prepayment_balance'];

            // Prepayment at end of month
            $calcEnd = $prepaymentCalc->calculate($invoice, $monthEnd->format('Y-m-d'));
            $prepaidEnd += $calcEnd['prepayment_balance'];

            // Invoiced in this month
            if ($invoiceDate->format('Y-m') === $monthKey) {
                $invoicedInMonth += $invoice['invoice_amount'];
            }
        }

        // P&L Credit = Prepaid at start + Invoiced in month - Prepaid at end
        $plCredit = $prepaidStart + $invoicedInMonth - $prepaidEnd;

        $monthlyData[$monthKey] = [
            'month' => $currentMonth->format('M Y'),
            'prepaid_start' => $prepaidStart,
            'invoiced' => $invoicedInMonth,
            'prepaid_end' => $prepaidEnd,
            'pl_credit' => $plCredit
        ];

        $currentMonth->modify('+1 month');
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="revenue_forecast_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Month', 'Prepaid (Start)', 'Invoiced', 'Prepaid (End)', 'P&L Credit']);

    // Data rows
    foreach ($monthlyData as $data) {
        fputcsv($output, [
            $data['month'],
            number_format($data['prepaid_start'], 2),
            number_format($data['invoiced'], 2),
            number_format($data['prepaid_end'], 2),
            number_format($data['pl_credit'], 2)
        ]);
    }

    fclose($output);
    exit;
}

// Prepayments CSV Export
if ($report === 'prepayments' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $prepaymentCalc = new PrepaymentCalculator();
    $db = Database::getInstance();

    // Default to last month end if no date specified
    if (!isset($_GET['prepayment_date'])) {
        $lastMonthEnd = new DateTime('first day of last month');
        $lastMonthEnd->modify('last day of this month');
        $selectedDate = $lastMonthEnd->format('Y-m-d');
    } else {
        $selectedDate = $_GET['prepayment_date'];
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="prepayments_' . $selectedDate . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, [
        'Legal Entity',
        'Invoice Number',
        'Invoice Date',
        'Invoice Amount',
        'Payment Frequency',
        'Days Remaining',
        'Prepayment Balance',
        'P&L Recognized'
    ]);

    // Get data - only invoices dated on or before the calculation date
    $invoices = $db->fetchAll("
        SELECT i.*, le.legal_entity_name
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.payment_frequency IN ('annual', 'quarterly')
        AND i.invoice_date <= :selected_date
        ORDER BY le.legal_entity_name, i.invoice_date DESC
    ", ['selected_date' => $selectedDate]);

    foreach ($invoices as $invoice) {
        $calc = $prepaymentCalc->calculate($invoice, $selectedDate);
        if ($calc['prepayment_balance'] > 0) {
            fputcsv($output, [
                $invoice['legal_entity_name'],
                $invoice['invoice_number'],
                date('d/m/Y', strtotime($invoice['invoice_date'])),
                number_format($invoice['invoice_amount'], 2),
                ucfirst($invoice['payment_frequency']),
                $calc['days_remaining'],
                number_format($calc['prepayment_balance'], 2),
                number_format($calc['pl_credit_amount'], 2)
            ]);
        }
    }

    fclose($output);
    exit;
}

require __DIR__ . '/../layouts/header.php';
?>

<div class="card">
    <h2>Reports</h2>
    
    <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?page=reports&report=prepayments" class="btn <?= $report === 'prepayments' ? 'btn-success' : '' ?>">Prepayments</a>
        <a href="?page=reports&report=cameras" class="btn <?= $report === 'cameras' ? 'btn-success' : '' ?>">Cameras</a>
        <a href="?page=reports&report=cashflow" class="btn <?= $report === 'cashflow' ? 'btn-success' : '' ?>">Cash Flow</a>
        <a href="?page=reports&report=revenue" class="btn <?= $report === 'revenue' ? 'btn-success' : '' ?>">Revenue</a>
    </div>
</div>

<?php if ($report === 'prepayments'): ?>
    <?php
    $invoiceModel = new Invoice();
    $prepaymentCalc = new PrepaymentCalculator();
    $db = Database::getInstance();

    // Get selected date or default to last month end
    if (!isset($_GET['prepayment_date'])) {
        $lastMonthEnd = new DateTime('first day of last month');
        $lastMonthEnd->modify('last day of this month');
        $selectedDate = $lastMonthEnd->format('Y-m-d');
    } else {
        $selectedDate = $_GET['prepayment_date'];
    }

    // Only include invoices dated on or before the calculation date
    $invoices = $db->fetchAll("
        SELECT i.*, le.legal_entity_name
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.payment_frequency IN ('annual', 'quarterly')
        AND i.invoice_date <= :selected_date
        ORDER BY le.legal_entity_name, i.invoice_date DESC
    ", ['selected_date' => $selectedDate]);

    $prepayments = [];
    $totalPrepayment = 0;

    foreach ($invoices as $invoice) {
        $calc = $prepaymentCalc->calculate($invoice, $selectedDate);
        if ($calc['prepayment_balance'] > 0) {
            $prepayments[] = array_merge($invoice, $calc);
            $totalPrepayment += $calc['prepayment_balance'];
        }
    }
    ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h3>Prepayments Report</h3>
                <p style="color: #666; margin: 5px 0;">As of <?= date('d/m/Y', strtotime($selectedDate)) ?></p>
            </div>
            <a href="?page=reports&report=prepayments&prepayment_date=<?= $selectedDate ?>&export=csv"
               class="btn btn-success">📥 Export to CSV</a>
        </div>

        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="report" value="prepayments">
            <div class="form-group" style="max-width: 300px;">
                <label for="prepayment_date">Select Calculation Date</label>
                <input type="date" id="prepayment_date" name="prepayment_date"
                       value="<?= $selectedDate ?>" onchange="this.form.submit()">
                <small>Choose the date to calculate prepayments for</small>
            </div>
        </form>

        <p><strong>Total Prepayment Balance: £<?= number_format($totalPrepayment, 2) ?></strong></p>
        
        <table>
            <thead>
                <tr>
                    <th>Legal Entity</th>
                    <th>Invoice #</th>
                    <th>Invoice Date</th>
                    <th>Invoice Amount</th>
                    <th>Frequency</th>
                    <th>Days Remaining</th>
                    <th>Prepayment Balance</th>
                    <th>P&L Recognized</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($prepayments as $prep): ?>
                <tr>
                    <td><?= htmlspecialchars($prep['legal_entity_name']) ?></td>
                    <td><?= htmlspecialchars($prep['invoice_number']) ?></td>
                    <td><?= date('d/m/Y', strtotime($prep['invoice_date'])) ?></td>
                    <td>£<?= number_format($prep['invoice_amount'], 2) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst($prep['payment_frequency']) ?></span></td>
                    <td><?= $prep['days_remaining'] ?></td>
                    <td><strong>£<?= number_format($prep['prepayment_balance'], 2) ?></strong></td>
                    <td>£<?= number_format($prep['pl_credit_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($report === 'cameras'): ?>
    <?php
    $db = Database::getInstance();

    // Get selected date or default to today
    $selectedDate = $_GET['snapshot_date'] ?? date('Y-m-d');

    // Get filter values
    $filterLegalEntity = $_GET['filter_legal_entity'] ?? '';
    $filterCameraType = $_GET['filter_camera_type'] ?? '';
    $filterStatus = $_GET['filter_status'] ?? 'active';
    $searchTerm = $_GET['search'] ?? '';

    // Build WHERE conditions
    $whereConditions = ["ci.installation_date <= :snapshot_date"];
    $params = ['snapshot_date' => $selectedDate];

    // Status filter
    if ($filterStatus === 'active') {
        $whereConditions[] = "(ci.removal_date IS NULL OR ci.removal_date > :snapshot_date2)";
        $params['snapshot_date2'] = $selectedDate;
    } elseif ($filterStatus === 'removed') {
        $whereConditions[] = "ci.removal_date IS NOT NULL AND ci.removal_date <= :snapshot_date3";
        $params['snapshot_date3'] = $selectedDate;
    }
    // 'all' status = no additional filter

    // Legal entity filter
    if ($filterLegalEntity) {
        $whereConditions[] = "le.id = :legal_entity_id";
        $params['legal_entity_id'] = $filterLegalEntity;
    }

    // Camera type filter
    if ($filterCameraType) {
        $whereConditions[] = "ci.camera_type = :camera_type";
        $params['camera_type'] = $filterCameraType;
    }

    // Search filter
    if ($searchTerm) {
        $whereConditions[] = "(st.store_name LIKE :search OR ci.camera_name LIKE :search2 OR ci.safr_code LIKE :search3)";
        $params['search'] = '%' . $searchTerm . '%';
        $params['search2'] = '%' . $searchTerm . '%';
        $params['search3'] = '%' . $searchTerm . '%';
    }

    $whereClause = implode(' AND ', $whereConditions);

    // Get all individual cameras for the selected snapshot date
    $cameras = $db->fetchAll("
        SELECT
            ci.id as camera_id,
            ci.camera_name,
            ci.safr_code,
            ci.camera_type,
            ci.installation_date,
            ci.removal_date,
            st.id as store_internal_id,
            st.store_name,
            st.store_id,
            le.legal_entity_name,
            le.id as legal_entity_id
        FROM camera_installations ci
        JOIN stores st ON ci.store_id = st.id
        JOIN legal_entities le ON st.legal_entity_id = le.id
        WHERE $whereClause
        ORDER BY le.legal_entity_name, st.store_name, ci.installation_date
    ", $params);

    // Get all legal entities for filter dropdown
    $legalEntities = $db->fetchAll("
        SELECT DISTINCT le.id, le.legal_entity_name
        FROM legal_entities le
        JOIN stores st ON st.legal_entity_id = le.id
        JOIN camera_installations ci ON ci.store_id = st.id
        ORDER BY le.legal_entity_name
    ");

    $totalCameras = count($cameras);
    ?>

    <div class="card">
        <h3>📸 Camera Inventory Report</h3>

        <form method="GET" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="report" value="cameras">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                <div class="form-group">
                    <label for="snapshot_date">Snapshot Date</label>
                    <input type="date" id="snapshot_date" name="snapshot_date" value="<?= $selectedDate ?>">
                </div>

                <div class="form-group">
                    <label for="filter_legal_entity">Legal Entity</label>
                    <select id="filter_legal_entity" name="filter_legal_entity">
                        <option value="">All Legal Entities</option>
                        <?php foreach ($legalEntities as $le): ?>
                            <option value="<?= $le['id'] ?>" <?= $filterLegalEntity == $le['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($le['legal_entity_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_camera_type">Camera Type</label>
                    <select id="filter_camera_type" name="filter_camera_type">
                        <option value="">All Types</option>
                        <option value="main" <?= $filterCameraType === 'main' ? 'selected' : '' ?>>Main</option>
                        <option value="additional" <?= $filterCameraType === 'additional' ? 'selected' : '' ?>>Additional</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filter_status">Status</label>
                    <select id="filter_status" name="filter_status">
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="removed" <?= $filterStatus === 'removed' ? 'selected' : '' ?>>Removed Only</option>
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Cameras</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Store, camera, SAFR code...">
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="?page=reports&report=cameras" class="btn">Clear Filters</a>
            </div>
        </form>

        <p><strong>Snapshot Date: <?= date('d F Y', strtotime($selectedDate)) ?></strong></p>
        <p><strong>Total Cameras: <?= number_format($totalCameras) ?></strong></p>

        <?php if (empty($cameras)): ?>
            <div class="alert alert-warning">
                No cameras found matching the selected filters.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Legal Entity</th>
                            <th>Store Name</th>
                            <th>Store ID</th>
                            <th>Camera Name</th>
                            <th>SAFR Code</th>
                            <th>Type</th>
                            <th>Installation Date</th>
                            <th>Removal Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cameras as $camera): ?>
                        <?php
                            $isActive = empty($camera['removal_date']) || strtotime($camera['removal_date']) > strtotime($selectedDate);
                            $statusClass = $isActive ? '' : 'style="opacity: 0.6;"';
                        ?>
                        <tr <?= $statusClass ?>>
                            <td><?= htmlspecialchars($camera['legal_entity_name']) ?></td>
                            <td>
                                <a href="?page=stores&action=view&id=<?= $camera['store_internal_id'] ?>" style="color: #0066cc; text-decoration: underline;">
                                    <?= htmlspecialchars($camera['store_name']) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($camera['store_id']) ?></td>
                            <td><?= htmlspecialchars($camera['camera_name'] ?: '-') ?></td>
                            <td>
                                <?php if ($camera['safr_code']): ?>
                                    <code style="background: #f0f0f0; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($camera['safr_code']) ?></code>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="padding: 2px 8px; border-radius: 3px; font-size: 0.85em; <?= $camera['camera_type'] === 'main' ? 'background: #e3f2fd; color: #1976d2;' : 'background: #f3e5f5; color: #7b1fa2;' ?>">
                                    <?= ucfirst($camera['camera_type']) ?>
                                </span>
                            </td>
                            <td><?= date('d M Y', strtotime($camera['installation_date'])) ?></td>
                            <td><?= $camera['removal_date'] ? date('d M Y', strtotime($camera['removal_date'])) : '-' ?></td>
                            <td>
                                <span style="padding: 2px 8px; border-radius: 3px; font-size: 0.85em; <?= $isActive ? 'background: #e8f5e9; color: #2e7d32;' : 'background: #ffebee; color: #c62828;' ?>">
                                    <?= $isActive ? 'Active' : 'Removed' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="8">TOTAL CAMERAS</td>
                            <td><?= number_format($totalCameras) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($report === 'cashflow'): ?>
    <?php
    $db = Database::getInstance();

    // Calculate date range: last 3 months + through to 31/3/31
    $startDate = new DateTime('first day of -3 months');
    $endDate = new DateTime('2031-03-31');

    // Get all invoices with legal entity info (including forecast invoices)
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            le.legal_entity_name,
            le.payment_terms_days,
            le.termination_date as entity_termination_date
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.invoice_date IS NOT NULL
        AND i.invoice_status IN ('draft', 'issued', 'reconciled_to_xero', 'forecast')
        ORDER BY i.invoice_date
    ");

    // Build monthly cash flow data
    $monthlyData = [];
    $currentMonth = clone $startDate;
    $currentMonth->modify('first day of this month');

    while ($currentMonth <= $endDate) {
        $monthKey = $currentMonth->format('Y-m');
        $monthlyData[$monthKey] = [
            'month' => $currentMonth->format('M Y'),
            'expected_cash' => 0,
            'invoice_count' => 0
        ];
        $currentMonth->modify('+1 month');
    }

    // Calculate expected payment dates for each invoice
    foreach ($invoices as $invoice) {
        $invoiceDate = new DateTime($invoice['invoice_date']);
        $paymentTerms = $invoice['payment_terms_days'] ?? 30;

        // Calculate expected payment date
        $expectedPaymentDate = clone $invoiceDate;
        $expectedPaymentDate->modify("+{$paymentTerms} days");

        // Check if entity is terminated before expected payment
        if ($invoice['entity_termination_date']) {
            $terminationDate = new DateTime($invoice['entity_termination_date']);
            if ($expectedPaymentDate > $terminationDate) {
                continue; // Skip if payment expected after termination
            }
        }

        $monthKey = $expectedPaymentDate->format('Y-m');

        if (isset($monthlyData[$monthKey])) {
            $monthlyData[$monthKey]['expected_cash'] += $invoice['invoice_amount'];
            $monthlyData[$monthKey]['invoice_count']++;
        }
    }
    ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>💰 Cash Flow Forecast</h3>
            <a href="?page=reports&report=cashflow&export=csv" class="btn btn-success">📥 Export to CSV</a>
        </div>

        <p style="margin-bottom: 20px; color: #666;">
            Expected cash inflows by month based on invoice dates + payment terms.
            Showing last 3 months through to 31st March 2031.
        </p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Expected Cash Inflow</th>
                        <th>Invoice Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalExpected = 0;
                    $totalInvoices = 0;
                    foreach ($monthlyData as $data):
                        $totalExpected += $data['expected_cash'];
                        $totalInvoices += $data['invoice_count'];
                    ?>
                    <tr>
                        <td><strong><?= $data['month'] ?></strong></td>
                        <td>£<?= number_format($data['expected_cash'], 2) ?></td>
                        <td><?= $data['invoice_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td>Total</td>
                        <td>£<?= number_format($totalExpected, 2) ?></td>
                        <td><?= $totalInvoices ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
            <h4 style="margin-top: 0;">📊 Methodology</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>Expected Payment Date:</strong> Invoice Date + Payment Terms (days)</li>
                <li><strong>Payment Terms:</strong> Set at legal entity level (default: 30 days)</li>
                <li><strong>Terminations:</strong> Excludes payments expected after entity termination date</li>
                <li><strong>Time Range:</strong> Last 3 months through to 31st March 2031</li>
            </ul>
        </div>
    </div>

<?php else: ?>
    <?php
    // P&L Credit Forecast (Revenue Recognition)
    $db = Database::getInstance();
    $prepaymentCalc = new PrepaymentCalculator();

    // Calculate date range: through to 31/3/31
    $startDate = new DateTime('first day of this month');
    $endDate = new DateTime('2031-03-31');

    // Get all invoices (including forecast invoices for future projections)
    $invoices = $db->fetchAll("
        SELECT
            i.*,
            le.legal_entity_name,
            le.termination_date as entity_termination_date
        FROM invoices i
        JOIN legal_entities le ON i.legal_entity_id = le.id
        WHERE i.payment_frequency IN ('annual', 'quarterly')
        AND i.invoice_status IN ('draft', 'issued', 'reconciled_to_xero', 'forecast')
        ORDER BY i.invoice_date
    ");

    // Build monthly P&L data
    $monthlyData = [];
    $currentMonth = clone $startDate;

    while ($currentMonth <= $endDate) {
        $monthKey = $currentMonth->format('Y-m');

        // Calculate start and end of month
        $monthStart = clone $currentMonth;
        $monthStart->modify('first day of this month');
        $monthEnd = clone $currentMonth;
        $monthEnd->modify('last day of this month');

        // Prepaid (Start) should be the balance at the END of the previous month
        $prepaidStartDate = clone $monthStart;
        $prepaidStartDate->modify('-1 day'); // Last day of previous month

        // Calculate prepayments at start and end of month
        $prepaidStart = 0;
        $prepaidEnd = 0;
        $invoicedInMonth = 0;

        foreach ($invoices as $invoice) {
            $invoiceDate = new DateTime($invoice['invoice_date']);

            // Skip invoices dated after the end of this month
            if ($invoiceDate > $monthEnd) {
                continue;
            }

            // Check if entity is terminated
            if ($invoice['entity_termination_date']) {
                $terminationDate = new DateTime($invoice['entity_termination_date']);
                if ($monthStart > $terminationDate) {
                    continue; // Skip if month is after termination
                }
            }

            // Prepayment at start of month (= end of previous month)
            $calcStart = $prepaymentCalc->calculate($invoice, $prepaidStartDate->format('Y-m-d'));
            $prepaidStart += $calcStart['prepayment_balance'];

            // Prepayment at end of month
            $calcEnd = $prepaymentCalc->calculate($invoice, $monthEnd->format('Y-m-d'));
            $prepaidEnd += $calcEnd['prepayment_balance'];

            // Invoiced in this month
            if ($invoiceDate->format('Y-m') === $monthKey) {
                $invoicedInMonth += $invoice['invoice_amount'];
            }
        }

        // P&L Credit = Prepaid at start + Invoiced in month - Prepaid at end
        $plCredit = $prepaidStart + $invoicedInMonth - $prepaidEnd;

        $monthlyData[$monthKey] = [
            'month' => $currentMonth->format('M Y'),
            'prepaid_start' => $prepaidStart,
            'invoiced' => $invoicedInMonth,
            'prepaid_end' => $prepaidEnd,
            'pl_credit' => $plCredit
        ];

        $currentMonth->modify('+1 month');
    }
    ?>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>📈 P&L Credit Forecast (Revenue Recognition)</h3>
            <a href="?page=reports&report=revenue&export=csv" class="btn btn-success">📥 Export to CSV</a>
        </div>

        <p style="margin-bottom: 20px; color: #666;">
            Monthly revenue recognition based on prepayment amortization.
            Showing next 3 years (36 months).
        </p>

        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Prepaid (Start)</th>
                        <th>Invoiced</th>
                        <th>Prepaid (End)</th>
                        <th>P&L Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalPLCredit = 0;
                    $totalInvoiced = 0;
                    foreach ($monthlyData as $data):
                        $totalPLCredit += $data['pl_credit'];
                        $totalInvoiced += $data['invoiced'];
                    ?>
                    <tr>
                        <td><strong><?= $data['month'] ?></strong></td>
                        <td>£<?= number_format($data['prepaid_start'], 2) ?></td>
                        <td><?= $data['invoiced'] > 0 ? '£' . number_format($data['invoiced'], 2) : '-' ?></td>
                        <td>£<?= number_format($data['prepaid_end'], 2) ?></td>
                        <td><strong>£<?= number_format($data['pl_credit'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td>Total</td>
                        <td>-</td>
                        <td>£<?= number_format($totalInvoiced, 2) ?></td>
                        <td>-</td>
                        <td>£<?= number_format($totalPLCredit, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
            <h4 style="margin-top: 0;">📊 Methodology</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><strong>P&L Credit Formula:</strong> Prepaid at Start + Invoiced in Month - Prepaid at End</li>
                <li><strong>Prepayments:</strong> Calculated using PrepaymentCalculator service</li>
                <li><strong>Invoiced:</strong> New invoices issued during the month</li>
                <li><strong>Terminations:</strong> Excludes months after entity termination date</li>
                <li><strong>Time Range:</strong> Next 3 years (36 months)</li>
            </ul>
            <p style="margin: 10px 0 0 0; font-size: 0.9em; color: #666;">
                <strong>Note:</strong> This shows revenue recognition for accounting purposes,
                not cash flow. See Cash Flow tab for expected payment timing.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

