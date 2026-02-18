<?php
/**
 * Reset All Invoices
 * Deletes all invoices and their allocations for a clean re-import
 */

use App\Database;

$pageTitle = 'Reset All Invoices';
$page = 'invoices';

$db = Database::getInstance();

// Handle reset action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    $confirmText = $_POST['confirm_text'] ?? '';
    
    if ($confirmText === 'DELETE ALL INVOICES') {
        try {
            $db->beginTransaction();
            
            // Get counts before deletion
            $invoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices");
            $allocationCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_camera_allocations");
            
            // Delete all allocations first (due to foreign key constraints)
            $db->query("DELETE FROM invoice_camera_allocations");
            
            // Delete all invoices
            $db->query("DELETE FROM invoices");
            
            $db->commit();
            
            $_SESSION['success'] = "Successfully deleted {$invoiceCount['count']} invoices and {$allocationCount['count']} allocations. You can now re-import from Xero.";
            header('Location: ?page=invoices');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = 'Failed to reset invoices: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = 'Confirmation text did not match. Please type "DELETE ALL INVOICES" exactly.';
    }
}

require __DIR__ . '/../layouts/header.php';

// Get current counts
$invoiceCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoices");
$allocationCount = $db->fetchOne("SELECT COUNT(*) as count FROM invoice_camera_allocations");
?>

<div class="container">
    <h1>⚠️ Reset All Invoices</h1>
    
    <div class="alert alert-danger" style="margin-bottom: 20px;">
        <h2>🚨 WARNING: This action cannot be undone!</h2>
        <p>This will permanently delete:</p>
        <ul>
            <li><strong><?= number_format($invoiceCount['count']) ?> invoices</strong></li>
            <li><strong><?= number_format($allocationCount['count']) ?> camera allocations</strong></li>
        </ul>
        <p><strong>Use this when:</strong></p>
        <ul>
            <li>You want to start fresh with a clean Xero import</li>
            <li>You have duplicate allocations that need to be cleaned up</li>
            <li>You're testing the system and want to reset</li>
        </ul>
    </div>
    
    <div class="card">
        <h2>What will happen:</h2>
        <ol>
            <li>All invoice records will be deleted from the database</li>
            <li>All camera allocation records will be deleted</li>
            <li>Your camera installations will remain intact ✅</li>
            <li>Your legal entities will remain intact ✅</li>
            <li>Your stores will remain intact ✅</li>
            <li>Your pricing tiers will remain intact ✅</li>
        </ol>
        
        <h2 style="margin-top: 30px;">After reset:</h2>
        <ol>
            <li>Go to Import → Xero Invoices</li>
            <li>Upload your Xero invoice export CSV</li>
            <li>Invoices will be imported WITHOUT automatic camera allocation</li>
            <li>Manually allocate cameras to each invoice as needed</li>
        </ol>
    </div>
    
    <div class="card" style="margin-top: 20px; background: #fff3cd; border: 2px solid #ffc107;">
        <h2>Confirm Reset</h2>
        <p>To proceed, type <strong>DELETE ALL INVOICES</strong> in the box below and click the button.</p>
        
        <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="confirm_reset" value="1">
            
            <div style="margin-bottom: 15px;">
                <label for="confirm_text" style="display: block; margin-bottom: 5px;">
                    <strong>Type "DELETE ALL INVOICES" to confirm:</strong>
                </label>
                <input 
                    type="text" 
                    id="confirm_text" 
                    name="confirm_text" 
                    style="width: 100%; padding: 10px; font-size: 16px; border: 2px solid #dc3545;"
                    placeholder="DELETE ALL INVOICES"
                    required
                    autocomplete="off">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button 
                    type="submit" 
                    class="btn btn-danger"
                    style="background: #dc3545; color: white; padding: 15px 30px; font-size: 16px; font-weight: bold;">
                    🗑️ DELETE ALL INVOICES AND ALLOCATIONS
                </button>
                <a href="?page=invoices" class="btn" style="padding: 15px 30px;">Cancel</a>
            </div>
        </form>
    </div>
    
    <div class="card" style="margin-top: 20px; background: #d1ecf1;">
        <h3>💡 Alternative: Selective Cleanup</h3>
        <p>If you only want to remove duplicate allocations without deleting invoices, you can:</p>
        <ol>
            <li>Go to each invoice individually</li>
            <li>Use the "Cleanup Duplicates" page to remove store-based allocations</li>
            <li>Keep the invoices and their correct individual camera allocations</li>
        </ol>
        <p><a href="?page=invoices" class="btn">Go to Invoices List</a></p>
    </div>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>

