<?php
/**
 * Diagnostic Tool: Check Xero Company Name Matches
 * Shows which names from your CSV match the database
 */

require_once __DIR__ . '/../app/Database.php';

use App\Database;

$db = Database::getInstance();

// Get all legal entities with Xero Company Names
$entities = $db->fetchAll("
    SELECT 
        id,
        legal_entity_name,
        xero_company_name
    FROM legal_entities
    WHERE xero_company_name IS NOT NULL AND xero_company_name != ''
    ORDER BY xero_company_name
");

// Names from your CSV
$csvNames = [
    'Eat 17',
    'Brookside Supermarket',
    'James Hall and Company Ltd',
    'Stanshawe Service Station (Yate) Ltd',
    'Brookfield Retail Ltd',
    'Shelley News Ltd',
    'Robertshaws Farm Shop Ltd',
    'Lightfoots (Est 1897) Ltd',
    'Thompson News \'N\' Food Ltd',
    'Fresh & Local Forecourts Limited',
    'William Strike Limited',
    'Haskins Garden Centres Ltd',
    'St Peters Garden Centre',
    'Mole Avon Country Stores',
    'Otter Garden Centres',
    'ADES Limited',
    'Skechers USA Ltd',
    'F & A Convenience',
    'Davids Kitchen',
    'Fron Goch Garden Centre',
    'Millets Farm Centre Limited',
    'Stevenson of Oxbridge',
    'Jempsons Supermarkets Ltd',
    'TYS Retail Ltd',
    'Forfar Road Service Station',
    'Brand Academy Store',
    'Thurrock Garden Centre Ltd',
    'SPAR Greaves Road',
    'TAP Retail Limited',
    'Aes Glasgow Limited T/A One Stop Dumbarton Road',
    'Hobbycraft Trading Limited',
    'SRJ Convenience Ltd',
    'BestOne Convenience',
    'Gosnays Retail',
    'Bassett Holdings Limited',
    'Qubros Ltd',
    'James Convenience Retail Ltd',
    'Elara Foods Ltd',
    'Yorkshire Garden Centres',
    'Brocksbushes Farm Shop',
    'Millbrook Garden Centres',
    'M&L Richardson & Sons Ltd',
    'Coolings Nurseries Ltd',
    'Keshco Ltd',
    'Gill Marsh',
    'SRJ Energy Ltd',
    'Messrs Mcilwrath & Lowe t/a Bargain Booze',
    'The Fertility Foundation',
    'Myuran Limited',
    'Webbs Garden Centre',
    'Hylands Group Limited',
    'Kavanaghs Group',
    'HKS Retail Ltd',
    'Gilletts Callington Ltd',
    'Whitehall Garden Centres',
    'RJ Raven Ltd',
    'Brobot Petroleum Ltd',
    'Clapham Wholefoods Limited',
    'Budgens Burnham',
    'Jaykishan Lostock Hall Limited',
    'Blacks Wine Ltd',
    'Pricewatch Group',
    'AF Blakemore',
    'Electra Shop Ltd t/a Londis Westham Road',
    'Village Market Trading Ltd',
    'Philanthropy London CIC',
    'Henderson Retail Ltd'
];

$csvNames = array_unique($csvNames);
sort($csvNames);

// Create lookup map
$dbMap = [];
foreach ($entities as $entity) {
    $dbMap[strtolower($entity['xero_company_name'])] = $entity;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Xero Company Name Match Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .match { background: #d4edda; padding: 5px; margin: 2px 0; }
        .no-match { background: #f8d7da; padding: 5px; margin: 2px 0; }
        .section { margin: 20px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
    </style>
</head>
<body>
    <h1>🔍 Xero Company Name Match Diagnostic</h1>

    <div class="section">
        <h2>Database: Legal Entities with Xero Company Names (<?= count($entities) ?>)</h2>
        <table>
            <tr>
                <th>Legal Entity Name</th>
                <th>Xero Company Name</th>
            </tr>
            <?php foreach ($entities as $entity): ?>
                <tr>
                    <td><?= htmlspecialchars($entity['legal_entity_name']) ?></td>
                    <td><strong><?= htmlspecialchars($entity['xero_company_name']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="section">
        <h2>CSV Names vs Database Matches (<?= count($csvNames) ?> unique names)</h2>
        <?php
        $matched = 0;
        $unmatched = 0;
        
        foreach ($csvNames as $csvName):
            $key = strtolower($csvName);
            $isMatch = isset($dbMap[$key]);
            
            if ($isMatch) {
                $matched++;
            } else {
                $unmatched++;
            }
        ?>
            <div class="<?= $isMatch ? 'match' : 'no-match' ?>">
                <?= $isMatch ? '✅' : '❌' ?>
                <strong><?= htmlspecialchars($csvName) ?></strong>
                <?php if ($isMatch): ?>
                    → Matches: <?= htmlspecialchars($dbMap[$key]['legal_entity_name']) ?>
                <?php else: ?>
                    → <span style="color: #721c24;">NO MATCH FOUND</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>Summary</h2>
        <p><strong>✅ Matched:</strong> <?= $matched ?> / <?= count($csvNames) ?></p>
        <p><strong>❌ Unmatched:</strong> <?= $unmatched ?> / <?= count($csvNames) ?></p>
        <p><strong>Match Rate:</strong> <?= round(($matched / count($csvNames)) * 100, 1) ?>%</p>
    </div>
</body>
</html>

