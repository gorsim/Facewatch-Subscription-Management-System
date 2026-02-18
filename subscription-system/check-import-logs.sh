#!/bin/bash
echo "=== Checking PHP Error Log for Import Activity ==="
echo ""
tail -100 /Applications/MAMP/logs/php_error.log | grep -A 2 -B 2 "Import:"
echo ""
echo "=== Checking for CameraImporter logs ==="
tail -100 /Applications/MAMP/logs/php_error.log | grep -A 2 -B 2 "CameraImporter:"
echo ""
echo "=== Last 20 lines of error log ==="
tail -20 /Applications/MAMP/logs/php_error.log

