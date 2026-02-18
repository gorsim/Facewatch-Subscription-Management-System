<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Facewatch Subscription Management' ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px 0; margin-bottom: 30px; }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; }
        .nav a:hover, .nav a.active { background: #34495e; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h2 { margin-bottom: 15px; color: #2c3e50; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 14px; color: #7f8c8d; margin-bottom: 10px; }
        .stat-card .value { font-size: 32px; font-weight: bold; color: #2c3e50; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        .badge-forecast { background: #e3f2fd; color: #1976d2; border: 1px solid #90caf9; }
        .btn-sm { padding: 6px 12px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>🎯 Facewatch Subscription Management</h1>
            <nav class="nav">
                <a href="?page=dashboard" class="<?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="?page=subscribers" class="<?= ($page ?? '') === 'subscribers' ? 'active' : '' ?>">Subscribers</a>
                <a href="?page=invoices" class="<?= ($page ?? '') === 'invoices' ? 'active' : '' ?>">Invoices</a>
                <a href="?page=invoices&action=generator" class="<?= ($page ?? '') === 'invoices' && ($_GET['action'] ?? '') === 'generator' ? 'active' : '' ?>">📋 Invoice Generator</a>
                <a href="?page=import" class="<?= ($page ?? '') === 'import' ? 'active' : '' ?>">Import Data</a>
                <a href="?page=reports" class="<?= ($page ?? '') === 'reports' ? 'active' : '' ?>">Reports</a>
                <a href="?page=admin&action=pricing" class="<?= ($page ?? '') === 'admin' ? 'active' : '' ?>">💰 Pricing</a>
                <a href="?page=logout">Logout</a>
            </nav>
        </div>
    </div>
    <div class="container">

