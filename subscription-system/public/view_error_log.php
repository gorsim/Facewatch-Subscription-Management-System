<?php
/**
 * View PHP Error Log
 * Shows the last 100 lines of the PHP error log
 */

$logFile = '/Applications/MAMP/logs/php_error.log';

?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Error Log</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; }
        .log-entry { margin: 5px 0; padding: 5px; border-left: 3px solid #007acc; }
        .log-entry.error { border-left-color: #f48771; }
        .log-entry.warning { border-left-color: #dcdcaa; }
        .timestamp { color: #608b4e; }
        .message { color: #ce9178; }
        .refresh { 
            position: fixed; 
            top: 20px; 
            right: 20px; 
            padding: 10px 20px; 
            background: #007acc; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px;
        }
        .clear { 
            position: fixed; 
            top: 20px; 
            right: 120px; 
            padding: 10px 20px; 
            background: #f48771; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px;
        }
        pre { background: #252526; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <a href="?refresh=1" class="refresh">🔄 Refresh</a>
    <h1>📋 PHP Error Log (Last 100 lines)</h1>
    
    <?php if (file_exists($logFile)): ?>
        <p>Log file: <code><?= $logFile ?></code></p>
        <p>Last modified: <?= date('Y-m-d H:i:s', filemtime($logFile)) ?></p>
        
        <pre><?php
        // Read last 100 lines
        $lines = file($logFile);
        $lastLines = array_slice($lines, -100);
        
        foreach ($lastLines as $line) {
            $class = '';
            if (stripos($line, 'error') !== false) {
                $class = 'error';
            } elseif (stripos($line, 'warning') !== false) {
                $class = 'warning';
            }
            
            echo '<div class="log-entry ' . $class . '">' . htmlspecialchars($line) . '</div>';
        }
        ?></pre>
    <?php else: ?>
        <p style="color: #f48771;">❌ Error log file not found at: <?= $logFile ?></p>
        <p>Check your MAMP configuration to find the correct path.</p>
    <?php endif; ?>
</body>
</html>

