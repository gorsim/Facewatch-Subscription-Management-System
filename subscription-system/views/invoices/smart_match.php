<?php
/**
 * Smart Invoice Matching - Import & Match Xero Invoices
 */

use App\Database;
use App\Services\InvoiceMatchingService;

$pageTitle = 'Smart Invoice Matching';
$page = 'invoices';

$db = Database::getInstance();
$matchingService = new InvoiceMatchingService();

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    try {
        error_log("Smart Match: Upload started");
        $file = $_FILES['csv_file'];
        error_log("Smart Match: File error code = " . $file['error']);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (exceeds server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (exceeds form limit)',
                UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            throw new Exception($errorMsg . ' (Error code: ' . $file['error'] . ')');
        }

        error_log("Smart Match: File uploaded successfully: " . $file['name']);
        
        // Read CSV file
        error_log("Smart Match: Opening file: " . $file['tmp_name']);
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not open uploaded file');
        }
        error_log("Smart Match: File opened successfully");
        
        // Create import session
        $sessionName = $_POST['session_name'] ?? 'Import ' . date('Y-m-d H:i');
        $db->insert('xero_import_sessions', [
            'session_name' => $sessionName,
            'imported_by' => $_SESSION['user_email'] ?? 'system',
            'status' => 'pending'
        ]);
        $sessionId = $db->getConnection()->lastInsertId();
        
        // Helper function to parse various date formats
        $parseDate = function($dateStr) {
            if (empty($dateStr)) return date('Y-m-d');

            // Try different date formats
            $formats = [
                'd/m/y',      // 16/2/26
                'd/m/Y',      // 16/2/2026
                'd/n/y',      // 16/2/26 (single digit month)
                'd/n/Y',      // 16/2/2026
                'Y-m-d',      // 2026-02-16
                'd-m-Y',      // 16-02-2026
                'm/d/Y',      // 2/16/2026 (US format)
                'Y/m/d',      // 2026/02/16
            ];

            foreach ($formats as $format) {
                $date = DateTime::createFromFormat($format, $dateStr);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            }

            // Fallback to strtotime
            $timestamp = strtotime($dateStr);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }

            return date('Y-m-d'); // Default to today if all else fails
        };

        // Parse CSV
        $header = fgetcsv($handle);
        error_log("Smart Match: Header row: " . implode(', ', $header));
        $imported = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 5) continue; // Skip invalid rows

            // Map CSV columns (adjust based on Xero export format)
            $data = array_combine($header, $row);

            $db->insert('xero_imported_invoices', [
                'session_id' => $sessionId,
                'xero_invoice_id' => $data['Invoice ID'] ?? $data['InvoiceID'] ?? '',
                'xero_invoice_number' => $data['Invoice Number'] ?? $data['InvoiceNumber'] ?? '',
                'contact_name' => $data['Contact Name'] ?? $data['ContactName'] ?? '',
                'invoice_date' => $parseDate($data['Date'] ?? $data['InvoiceDate'] ?? ''),
                'due_date' => isset($data['Due Date']) ? $parseDate($data['Due Date']) : null,
                'amount' => floatval($data['Amount Due'] ?? $data['Total'] ?? 0),
                'status' => $data['Status'] ?? 'AUTHORISED'
            ]);

            $imported++;
        }

        fclose($handle);

        error_log("Smart Match: Imported $imported invoices");

        // Update session
        $db->update('xero_import_sessions', [
            'total_invoices' => $imported,
            'status' => 'reviewing'
        ], 'id = :id', ['id' => $sessionId]);

        error_log("Smart Match: Session updated, redirecting to session $sessionId");
        $_SESSION['success'] = "Imported $imported invoices successfully!";
        header("Location: ?page=invoices&action=smart_match&session=$sessionId");
        exit;

    } catch (Exception $e) {
        error_log("Smart Match ERROR: " . $e->getMessage());
        error_log("Smart Match ERROR trace: " . $e->getTraceAsString());
        $_SESSION['error'] = 'Import failed: ' . $e->getMessage();
    }
}

// Get session if specified
$sessionId = $_GET['session'] ?? null;
$session = null;
$matches = null;

if ($sessionId) {
    $session = $db->fetchOne("SELECT * FROM xero_import_sessions WHERE id = :id", ['id' => $sessionId]);

    if ($session) {
        // Check if there are any existing matches in the database
        $existingMatches = $db->fetchOne("
            SELECT COUNT(*) as count
            FROM xero_imported_invoices
            WHERE session_id = :session_id
            AND matched_invoice_id IS NOT NULL
        ", ['session_id' => $sessionId]);

        $hasExistingMatches = ($existingMatches['count'] > 0);

        // Check if this is a new session (status = 'reviewing') or viewing historical results
        // A session is "new" only if it's in reviewing status AND has no existing matches
        $isNewSession = ($session['status'] === 'reviewing' && !$hasExistingMatches);

        // DEBUG: Remove this after testing
        error_log("Session {$sessionId}: status={$session['status']}, matched_count={$session['matched_count']}, hasExistingMatches=" . ($hasExistingMatches ? 'YES' : 'NO') . ", isNew=" . ($isNewSession ? 'YES' : 'NO'));

        if ($isNewSession) {
            // New session - run matching algorithm to find new matches
            error_log("Using findMatches() for session {$sessionId}");
            $matches = $matchingService->findMatches($sessionId);
        } else {
            // Historical session or re-opened session - show saved matches
            error_log("Using getSessionMatches() for session {$sessionId}");
            $matches = $matchingService->getSessionMatches($sessionId);
        }

        // DEBUG: Log what we got
        error_log("Matches returned: perfect=" . count($matches['perfect']) . ", auto=" . count($matches['auto']) . ", suggested=" . count($matches['suggested']) . ", possible=" . count($matches['possible']) . ", unmatched=" . count($matches['unmatched']));
    }
}

// Get recent sessions
$recentSessions = $db->fetchAll("
    SELECT * FROM xero_import_sessions
    ORDER BY import_date DESC
    LIMIT 10
");

require __DIR__ . '/../layouts/header.php';
?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🤖 Smart Invoice Matching</h1>
        <a href="?page=invoices" class="btn">← Back to Invoices</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error" style="background: #f8d7da; border: 2px solid #f5c6cb; padding: 20px; margin-bottom: 20px;">
            <h3>⚠️ Upload Error</h3>
            <p style="margin: 10px 0; font-size: 16px;"><?= htmlspecialchars($_SESSION['error']) ?></p>
            <p style="margin: 10px 0; color: #666;">
                <strong>Tip:</strong> Check the error log for more details. The error has been logged for debugging.
            </p>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success" style="background: #d4edda; border: 2px solid #c3e6cb; padding: 20px; margin-bottom: 20px;">
            <h3>✅ Success</h3>
            <p style="margin: 10px 0; font-size: 16px;"><?= htmlspecialchars($_SESSION['success']) ?></p>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (!$sessionId): ?>
        <!-- Import Page -->
        <div class="card" style="margin-bottom: 20px; background: #e7f3ff; border-left: 4px solid #2196F3;">
            <h3 style="margin-top: 0;">💡 How It Works</h3>
            <ol style="margin-bottom: 0;">
                <li><strong>Export from Xero</strong>: Go to Xero → Reports → Invoice Report → Export to CSV</li>
                <li><strong>Upload CSV</strong>: Use the form below to upload your Xero invoice export</li>
                <li><strong>Auto-Match</strong>: Our smart algorithm will match invoices automatically</li>
                <li><strong>Review</strong>: Check suggested matches and approve/reject</li>
                <li><strong>Bulk Reconcile</strong>: Reconcile all matched invoices in one click!</li>
            </ol>
        </div>

        <div class="card">
            <h2>📥 Import Xero Invoices</h2>
            <form method="POST" enctype="multipart/form-data" style="max-width: 600px;">
                <div style="margin-bottom: 20px;">
                    <label for="session_name" style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Import Session Name
                    </label>
                    <input type="text" 
                           id="session_name" 
                           name="session_name" 
                           value="Import <?= date('F Y') ?>"
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Give this import a name (e.g., "January 2026 Invoices")
                    </small>
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="csv_file" style="display: block; font-weight: bold; margin-bottom: 5px;">
                        Xero CSV File <span style="color: #d9534f;">*</span>
                    </label>
                    <input type="file" 
                           id="csv_file" 
                           name="csv_file" 
                           accept=".csv"
                           required
                           style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Upload the CSV file exported from Xero
                    </small>
                </div>

                <button type="submit" class="btn btn-success" style="font-size: 1.1em; padding: 12px 24px;">
                    📤 Upload & Start Matching
                </button>
            </form>
        </div>

        <?php if (!empty($recentSessions)): ?>
        <div class="card" style="margin-top: 20px;">
            <h2>📋 Recent Import Sessions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Session Name</th>
                        <th>Date</th>
                        <th>Invoices</th>
                        <th>Matched</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSessions as $s): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($s['session_name']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($s['import_date'])) ?></td>
                        <td><?= $s['total_invoices'] ?></td>
                        <td><?= $s['matched_count'] ?></td>
                        <td>
                            <?php
                            $badges = [
                                'pending' => '<span class="badge badge-secondary">Pending</span>',
                                'reviewing' => '<span class="badge badge-warning">Reviewing</span>',
                                'completed' => '<span class="badge badge-success">Completed</span>',
                                'cancelled' => '<span class="badge badge-danger">Cancelled</span>'
                            ];
                            echo $badges[$s['status']] ?? $s['status'];
                            ?>
                        </td>
                        <td>
                            <a href="?page=invoices&action=smart_match&session=<?= $s['id'] ?>" class="btn btn-sm btn-primary">
                                View Matches
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Matching Results Page -->
        <?php require __DIR__ . '/smart_match_results.php'; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

