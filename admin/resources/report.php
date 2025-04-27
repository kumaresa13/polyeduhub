<?php
// File path: admin/resources/reports.php

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/admin-functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

// Get admin user information
$admin_id = $_SESSION['id'];

// Get time period for report
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$category = isset($_GET['category']) ? intval($_GET['category']) : 0;

// Determine date ranges based on period
$start_date = '';
$end_date = '';
$period_label = '';

if ($period === 'month') {
    $start_date = sprintf("%04d-%02d-01", $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    $period_label = date('F Y', strtotime($start_date));
} elseif ($period === 'year') {
    $start_date = "$year-01-01";
    $end_date = "$year-12-31";
    $period_label = $year;
} elseif ($period === 'quarter') {
    $quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil($month / 3);
    $first_month = ($quarter - 1) * 3 + 1;
    $start_date = sprintf("%04d-%02d-01", $year, $first_month);
    $end_month = $first_month + 2;
    $end_date = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $year, $end_month)));
    $period_label = "Q$quarter $year";
} elseif ($period === 'custom') {
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $period_label = date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
} else {
    // Default to current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $period_label = date('F Y');
}

// Generate reports
try {
    $pdo = getDbConnection();
    
    // Get all categories for filter
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
    
    // 1. Overall Statistics
    $category_filter = $category > 0 ? "AND r.category_id = ?" : "";
    $category_params = $category > 0 ? [$category] : [];
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_resources,
            SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved_resources,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending_resources,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected_resources,
            SUM(r.download_count) as total_downloads,
            SUM(r.view_count) as total_views
        FROM resources r
        WHERE r.created_at BETWEEN ? AND ? $category_filter
    ");
    
    $stmt_params = array_merge([$start_date, $end_date], $category_params);
    $stmt->execute($stmt_params);
    $overall_stats = $stmt->fetch();
    
    // 2. Resource Uploads by Day
    $stmt = $pdo->prepare("
        SELECT 
            DATE(r.created_at) as upload_date,
            COUNT(*) as upload_count
        FROM resources r
        WHERE r.created_at BETWEEN ? AND ? $category_filter
        GROUP BY DATE(r.created_at)
        ORDER BY upload_date
    ");
    
    $stmt->execute($stmt_params);
    $daily_uploads = $stmt->fetchAll();
    
    // Prepare data for daily uploads chart
    $upload_dates = [];
    $upload_counts = [];
    foreach ($daily_uploads as $day) {
        $upload_dates[] = date('M j', strtotime($day['upload_date']));
        $upload_counts[] = $day['upload_count'];
    }
    
    // 3. Resource Uploads by Category
    $stmt = $pdo->prepare("
        SELECT 
            rc.name as category_name,
            COUNT(*) as resource_count
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.created_at BETWEEN ? AND ?
        GROUP BY r.category_id, rc.name
        ORDER BY resource_count DESC
    ");
    
    $stmt->execute([$start_date, $end_date]);
    $category_stats = $stmt->fetchAll();
    
    // Prepare data for category chart
    $category_names = [];
    $category_counts = [];
    foreach ($category_stats as $cat) {
        $category_names[] = $cat['category_name'];
        $category_counts[] = $cat['resource_count'];
    }
    
    // 4. Top Downloaded Resources
    $stmt = $pdo->prepare("
        SELECT 
            r.id,
            r.title,
            r.download_count,
            r.view_count,
            rc.name as category_name,
            CONCAT(u.first_name, ' ', u.last_name) as uploader_name
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'approved' AND r.created_at BETWEEN ? AND ? $category_filter
        ORDER BY r.download_count DESC
        LIMIT 10
    ");
    
    $stmt->execute($stmt_params);
    $top_downloads = $stmt->fetchAll();
    
    // 5. Top Active Uploaders
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            COUNT(*) as upload_count,
            SUM(r.download_count) as total_downloads
        FROM resources r
        JOIN users u ON r.user_id = u.id
        WHERE r.created_at BETWEEN ? AND ? $category_filter
        GROUP BY u.id, user_name
        ORDER BY upload_count DESC
        LIMIT 10
    ");
    
    $stmt->execute($stmt_params);
    $top_uploaders = $stmt->fetchAll();
    
    // 6. File Type Distribution
    $stmt = $pdo->prepare("
        SELECT 
            r.file_type,
            COUNT(*) as file_count
        FROM resources r
        WHERE r.created_at BETWEEN ? AND ? $category_filter
        GROUP BY r.file_type
        ORDER BY file_count DESC
    ");
    
    $stmt->execute($stmt_params);
    $file_types = $stmt->fetchAll();
    
    // Prepare data for file type chart
    $file_type_labels = [];
    $file_type_counts = [];
    foreach ($file_types as $type) {
        $file_type_labels[] = strtoupper($type['file_type']);
        $file_type_counts[] = $type['file_count'];
    }
    
} catch (PDOException $e) {
    error_log("Error generating reports: " . $e->getMessage());
    $overall_stats = [
        'total_resources' => 0,
        'approved_resources' => 0,
        'pending_resources' => 0,
        'rejected_resources' => 0,
        'total_downloads' => 0,
        'total_views' => 0
    ];
    $daily_uploads = [];
    $category_stats = [];
    $top_downloads = [];
    $top_uploaders = [];
    $file_types = [];
    
    // Empty chart data
    $upload_dates = [];
    $upload_counts = [];
    $category_names = [];
    $category_counts = [];
    $file_type_labels = [];
    $file_type_counts = [];
}

// Check for export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="resource_report_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add report title
    fputcsv($output, ["Resource Report - $period_label"]);
    fputcsv($output, [""]);
    
    // Add overall statistics section
    fputcsv($output, ["Overall Statistics"]);
    fputcsv($output, ["Total Resources", $overall_stats['total_resources']]);
    fputcsv($output, ["Approved Resources", $overall_stats['approved_resources']]);
    fputcsv($output, ["Pending Resources", $overall_stats['pending_resources']]);
    fputcsv($output, ["Rejected Resources", $overall_stats['rejected_resources']]);
    fputcsv($output, ["Total Downloads", $overall_stats['total_downloads']]);
    fputcsv($output, ["Total Views", $overall_stats['total_views']]);
    fputcsv($output, [""]);
    
    // Add daily uploads
    fputcsv($output, ["Daily Resource Uploads"]);
    fputcsv($output, ["Date", "Number of Uploads"]);
    foreach ($daily_uploads as $day) {
        fputcsv($output, [date('Y-m-d', strtotime($day['upload_date'])), $day['upload_count']]);
    }
    fputcsv($output, [""]);
    
    // Add category statistics
    fputcsv($output, ["Resources by Category"]);
    fputcsv($output, ["Category", "Number of Resources"]);
    foreach ($category_stats as $cat) {
        fputcsv($output, [$cat['category_name'], $cat['resource_count']]);
    }
    fputcsv($output, [""]);
    
    // Add top downloads
    fputcsv($output, ["Top 10 Downloaded Resources"]);
    fputcsv($output, ["Title", "Category", "Uploader", "Downloads", "Views"]);
    foreach ($top_downloads as $resource) {
        fputcsv($output, [
            $resource['title'],
            $resource['category_name'],
            $resource['uploader_name'],
            $resource['download_count'],
            $resource['view_count']
        ]);
    }
    fputcsv($output, [""]);
    
    // Add top uploaders
    fputcsv($output, ["Top 10 Active Uploaders"]);
    fputcsv($output, ["Name", "Number of Uploads", "Total Downloads"]);
    foreach ($top_uploaders as $uploader) {
        fputcsv($output, [
            $uploader['user_name'],
            $uploader['upload_count'],
            $uploader['total_downloads']
        ]);
    }
    
    fclose($output);
    exit;
}

// Set page title and nested path variable
$page_title = "Resource Reports";
$nested = true;

// Additional styles for charts
$additional_styles = '
<style>
    .stat-card {
        border-left: 4px solid #4e73df;
    }
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }
</style>
';

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Resource Reports</h1>
        <div>
            <a href="?period=<?= $period ?>&year=<?= $year ?>&month=<?= $month ?>&category=<?= $category ?>&export=csv" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm me-2">
                <i class="fas fa-file-csv fa-sm text-white-50"></i> Export to CSV
            </a>
            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" onclick="window.print()">
                <i class="fas fa-print fa-sm text-white-50"></i> Print Report
            </a>
        </div>
    </div>
    
    <!-- Report Period Selection -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="period" class="form-label">Time Period</label>
                    <select class="form-select" id="period" name="period" onchange="togglePeriodFields()">
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Monthly</option>
                        <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>Quarterly</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Yearly</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-2" id="monthField" style="<?= $period !== 'month' ? 'display:none;' : '' ?>">
                    <label for="month" class="form-label">Month</label>
                    <select class="form-select" id="month" name="month">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $month === $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2" id="quarterField" style="<?= $period !== 'quarter' ? 'display:none;' : '' ?>">
                    <label for="quarter" class="form-label">Quarter</label>
                    <select class="form-select" id="quarter" name="quarter">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <option value="<?= $i ?>" <?= (isset($_GET['quarter']) && $_GET['quarter'] == $i) ? 'selected' : '' ?>>
                                Q<?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="year" class="form-label">Year</label>
                    <select class="form-select" id="year" name="year">
                        <?php for ($i = date('Y'); $i >= date('Y') - 5; $i--): ?>
                            <option value="<?= $i ?>" <?= $year === $i ? 'selected' : '' ?>><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2" id="startDateField" style="<?= $period !== 'custom' ? 'display:none;' : '' ?>">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?= isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01') ?>">
                </div>
                
                <div class="col-md-2" id="endDateField" style="<?= $period !== 'custom' ? 'display:none;' : '' ?>">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?= isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d') ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="category" class="form-label">Category (Optional)</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category === $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Report Header -->
    <div class="text-center mb-4">
        <h4>Resource Report: <?= htmlspecialchars($period_label) ?></h4>
        <?php if ($category > 0): ?>
            <?php 
            $category_name = "All Categories";
            foreach ($categories as $cat) {
                if ($cat['id'] == $category) {
                    $category_name = $cat['name'];
                    break;
                }
            }
            ?>
            <p>Category: <?= htmlspecialchars($category_name) ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Overall Statistics Row -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['total_resources']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card" style="border-left-color: #1cc88a;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['approved_resources']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card" style="border-left-color: #f6c23e;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['pending_resources']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card" style="border-left-color: #e74a3b;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Rejected</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['rejected_resources']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card" style="border-left-color: #36b9cc;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Downloads</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['total_downloads']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-2 col-md-4 mb-4">
            <div class="card shadow h-100 py-2 stat-card" style="border-left-color: #858796;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">Total Views</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($overall_stats['total_views']) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-eye fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <!-- Daily Uploads Chart -->
        <div class="col-xl-8 col-lg-7 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Uploads Over Time</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($upload_dates)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-gray-300 mb-3"></i>
                            <p>No data available for this time period.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="uploadsChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- File Type Distribution Chart -->
        <div class="col-xl-4 col-lg-5 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">File Type Distribution</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($file_type_labels)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-pie fa-3x text-gray-300 mb-3"></i>
                            <p>No data available for this time period.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="fileTypeChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Category Distribution Chart -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resources by Category</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($category_names)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-bar fa-3x text-gray-300 mb-3"></i>
                            <p>No data available for this time period.</p>
                        </div>
                    <?php else: ?>
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tables Row -->
    <div class="row">
        <!-- Top Downloads Table -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Downloaded Resources</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($top_downloads)): ?>
                        <div class="text-center py-4">
                            <p>No download data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Uploader</th>
                                        <th>Downloads</th>
                                        <th>Views</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_downloads as $resource): ?>
                                        <tr>
                                            <td>
                                                <a href="view.php?id=<?= $resource['id'] ?>">
                                                    <?= htmlspecialchars($resource['title']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                            <td><?= htmlspecialchars($resource['uploader_name']) ?></td>
                                            <td><?= number_format($resource['download_count']) ?></td>
                                            <td><?= number_format($resource['view_count']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Uploaders Table -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Active Uploaders</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($top_uploaders)): ?>
                        <div class="text-center py-4">
                            <p>No uploader data available for this period.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Uploads</th>
                                        <th>Downloads</th>
                                        <th>Engagement</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_uploaders as $uploader): ?>
                                        <tr>
                                            <td>
                                                <a href="../users/view.php?id=<?= $uploader['id'] ?>">
                                                    <?= htmlspecialchars($uploader['user_name']) ?>
                                                </a>
                                            </td>
                                            <td><?= number_format($uploader['upload_count']) ?></td>
                                            <td><?= number_format($uploader['total_downloads']) ?></td>
                                            <td>
                                                <?php
                                                // Calculate an engagement score
                                                $engagementScore = $uploader['upload_count'] * 10 + $uploader['total_downloads'];
                                                
                                                // Display stars based on engagement
                                                $stars = min(5, ceil($engagementScore / 100));
                                                
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $stars) {
                                                        echo '<i class="fas fa-star text-warning"></i>';
                                                    } else {
                                                        echo '<i class="far fa-star text-warning"></i>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Function to toggle period fields based on selection
    function togglePeriodFields() {
        const period = document.getElementById('period').value;
        
        document.getElementById('monthField').style.display = 'none';
        document.getElementById('quarterField').style.display = 'none';
        document.getElementById('startDateField').style.display = 'none';
        document.getElementById('endDateField').style.display = 'none';
        
        if (period === 'month') {
            document.getElementById('monthField').style.display = '';
        } else if (period === 'quarter') {
            document.getElementById('quarterField').style.display = '';
        } else if (period === 'custom') {
            document.getElementById('startDateField').style.display = '';
            document.getElementById('endDateField').style.display = '';
        }
    }
    
    // Initialize charts if there's data
    document.addEventListener('DOMContentLoaded', function() {
        // Daily Uploads Chart
        <?php if (!empty($upload_dates)): ?>
        const uploadsCtx = document.getElementById('uploadsChart').getContext('2d');
        const uploadsChart = new Chart(uploadsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($upload_dates) ?>,
                datasets: [{
                    label: 'Uploads',
                    data: <?= json_encode($upload_counts) ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(tooltipItems) {
                                return tooltipItems[0].label;
                            },
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // File Type Chart
        <?php if (!empty($file_type_labels)): ?>
        const fileTypeCtx = document.getElementById('fileTypeChart').getContext('2d');
        const fileTypeChart = new Chart(fileTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($file_type_labels) ?>,
                datasets: [{
                    data: <?= json_encode($file_type_counts) ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                        '#6610f2', '#fd7e14', '#20c9a6', '#858796', '#5a5c69'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', 
                        '#5000b9', '#d16608', '#169b7f', '#60616f', '#3a3b47'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Category Chart
        <?php if (!empty($category_names)): ?>
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($category_names) ?>,
                datasets: [{
                    label: 'Number of Resources',
                    data: <?= json_encode($category_counts) ?>,
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9',
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>
    });
    
    // Print-specific styles
    window.onbeforeprint = function() {
        document.querySelectorAll('.card').forEach(card => {
            card.style.boxShadow = 'none';
            card.style.border = '1px solid #ccc';
        });
    };
    
    window.onafterprint = function() {
        document.querySelectorAll('.card').forEach(card => {
            card.style.boxShadow = '';
            card.style.border = '';
        });
    };
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>