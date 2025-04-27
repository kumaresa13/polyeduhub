<?php
// File path: admin/reports/resource-statistics.php

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

// Set time period for report
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$start_date = '';
$end_date = '';

// Determine date ranges
if ($period === 'week') {
    $start_date = date('Y-m-d', strtotime('-1 week'));
    $end_date = date('Y-m-d');
} elseif ($period === 'month') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
} elseif ($period === 'year') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-12-31');
} elseif ($period === 'all') {
    $start_date = '2000-01-01'; // Effectively all-time
    $end_date = date('Y-m-d');
} elseif ($period === 'custom' && isset($_GET['start_date'], $_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
} else {
    // Default to current month
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-t');
    $period = 'month';
}

// Generate reports
try {
    $pdo = getDbConnection();
    
    // Get resource count by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM resources
        WHERE created_at BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$start_date, $end_date]);
    $status_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get resource count by category
    $stmt = $pdo->prepare("
        SELECT rc.name, COUNT(r.id) as count 
        FROM resources r
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.created_at BETWEEN ? AND ?
        GROUP BY rc.id, rc.name
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $category_counts = $stmt->fetchAll();
    
    // Get resource count by file type
    $stmt = $pdo->prepare("
        SELECT file_type, COUNT(*) as count 
        FROM resources
        WHERE created_at BETWEEN ? AND ?
        GROUP BY file_type
        ORDER BY count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $filetype_counts = $stmt->fetchAll();
    
    // Get total download count
    $stmt = $pdo->prepare("
        SELECT SUM(download_count) as total_downloads 
        FROM resources
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $total_downloads = $stmt->fetchColumn() ?: 0;
    
    // Get top downloaded resources
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.download_count, r.file_type, r.created_at,
               u.first_name, u.last_name, rc.name as category_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        JOIN resource_categories rc ON r.category_id = rc.id
        WHERE r.status = 'approved' AND r.created_at BETWEEN ? AND ?
        ORDER BY r.download_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_downloads = $stmt->fetchAll();
    
    // Get uploads by day
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM resources
        WHERE created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $daily_uploads = $stmt->fetchAll();
    
    // Prepare data for charts
    $upload_dates = [];
    $upload_counts = [];
    foreach ($daily_uploads as $day) {
        $upload_dates[] = date('M j', strtotime($day['date']));
        $upload_counts[] = $day['count'];
    }
    
    // Category chart data
    $category_names = [];
    $category_data = [];
    foreach ($category_counts as $cat) {
        $category_names[] = $cat['name'];
        $category_data[] = $cat['count'];
    }
    
    // File type chart data
    $filetype_names = [];
    $filetype_data = [];
    foreach ($filetype_counts as $type) {
        $filetype_names[] = strtoupper($type['file_type']);
        $filetype_data[] = $type['count'];
    }
    
    // Status chart data
    $status_labels = ['Approved', 'Pending', 'Rejected'];
    $status_data = [
        $status_counts['approved'] ?? 0,
        $status_counts['pending'] ?? 0,
        $status_counts['rejected'] ?? 0
    ];
    
    // Get total resource count
    $total_resources = array_sum($status_data);
    
} catch (PDOException $e) {
    error_log("Error generating resource statistics: " . $e->getMessage());
    $status_counts = [
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0
    ];
    $category_counts = [];
    $filetype_counts = [];
    $total_downloads = 0;
    $top_downloads = [];
    $upload_dates = [];
    $upload_counts = [];
    $category_names = [];
    $category_data = [];
    $filetype_names = [];
    $filetype_data = [];
    $status_labels = ['Approved', 'Pending', 'Rejected'];
    $status_data = [0, 0, 0];
    $total_resources = 0;
}

// Check for export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="resource_statistics_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add report title
    fputcsv($output, ["Resource Statistics - " . date('F j, Y', strtotime($start_date)) . " to " . date('F j, Y', strtotime($end_date))]);
    fputcsv($output, [""]);
    
    // Add overall statistics section
    fputcsv($output, ["Overall Statistics"]);
    fputcsv($output, ["Total Resources", $total_resources]);
    fputcsv($output, ["Approved Resources", $status_counts['approved'] ?? 0]);
    fputcsv($output, ["Pending Resources", $status_counts['pending'] ?? 0]);
    fputcsv($output, ["Rejected Resources", $status_counts['rejected'] ?? 0]);
    fputcsv($output, ["Total Downloads", $total_downloads]);
    fputcsv($output, [""]);
    
    // Add category statistics
    fputcsv($output, ["Resources by Category"]);
    fputcsv($output, ["Category", "Count"]);
    foreach ($category_counts as $cat) {
        fputcsv($output, [$cat['name'], $cat['count']]);
    }
    fputcsv($output, [""]);
    
    // Add file type statistics
    fputcsv($output, ["Resources by File Type"]);
    fputcsv($output, ["File Type", "Count"]);
    foreach ($filetype_counts as $type) {
        fputcsv($output, [strtoupper($type['file_type']), $type['count']]);
    }
    fputcsv($output, [""]);
    
    // Add daily uploads
    fputcsv($output, ["Daily Resource Uploads"]);
    fputcsv($output, ["Date", "Uploads"]);
    foreach ($daily_uploads as $day) {
        fputcsv($output, [$day['date'], $day['count']]);
    }
    fputcsv($output, [""]);
    
    // Add top downloads
    fputcsv($output, ["Top Downloaded Resources"]);
    fputcsv($output, ["Title", "Category", "File Type", "Uploader", "Date", "Downloads"]);
    foreach ($top_downloads as $resource) {
        fputcsv($output, [
            $resource['title'],
            $resource['category_name'],
            strtoupper($resource['file_type']),
            $resource['first_name'] . ' ' . $resource['last_name'],
            date('Y-m-d', strtotime($resource['created_at'])),
            $resource['download_count']
        ]);
    }
    
    fclose($output);
    exit;
}

// Set page title and nested path variable
$page_title = "Resource Statistics";
$nested = true;

// Additional styles for charts
$additional_styles = '
<style>
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
        <h1 class="h3 mb-0 text-gray-800">Resource Statistics</h1>
        <div>
            <a href="?period=<?= $period ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv" class="btn btn-sm btn-success shadow-sm me-2">
                <i class="fas fa-file-csv fa-sm text-white-50"></i> Export to CSV
            </a>
            <a href="#" class="btn btn-sm btn-primary shadow-sm" onclick="window.print()">
                <i class="fas fa-print fa-sm text-white-50"></i> Print Report
            </a>
        </div>
    </div>
    
    <!-- Date Range Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Select Date Range</h6>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label for="period" class="form-label">Predefined Periods</label>
                    <select class="form-select" id="period" name="period" onchange="toggleDateFields()">
                        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>Current Month</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Current Year</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>
                
                <div class="col-md-2" id="startDateField" style="<?= $period !== 'custom' ? 'display:none;' : '' ?>">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
                </div>
                
                <div class="col-md-2" id="endDateField" style="<?= $period !== 'custom' ? 'display:none;' : '' ?>">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Report Header -->
    <div class="text-center mb-4">
        <h4>Resource Statistics Report</h4>
        <p><?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?></p>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_resources) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($status_counts['approved'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Resources</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($status_counts['pending'] ?? 0) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Downloads</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_downloads) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-download fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <!-- Status Distribution Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Status Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- File Type Distribution Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">File Type Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="fileTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Daily Uploads Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Daily Resource Uploads</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="uploadsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Category Distribution Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Category Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Downloads Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Top Downloaded Resources</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>File Type</th>
                            <th>Uploader</th>
                            <th>Upload Date</th>
                            <th>Downloads</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($top_downloads)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No download data available for this period</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($top_downloads as $index => $resource): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <a href="../resources/view.php?id=<?= $resource['id'] ?>">
                                        <?= htmlspecialchars($resource['title']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($resource['category_name']) ?></td>
                                <td><?= strtoupper($resource['file_type']) ?></td>
                                <td><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></td>
                                <td><?= date('M j, Y', strtotime($resource['created_at'])) ?></td>
                                <td><?= number_format($resource['download_count']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Function to toggle date fields based on selection
    function toggleDateFields() {
        const period = document.getElementById('period').value;
        
        document.getElementById('startDateField').style.display = period === 'custom' ? 'block' : 'none';
        document.getElementById('endDateField').style.display = period === 'custom' ? 'block' : 'none';
    }
    
    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($status_labels) ?>,
                datasets: [{
                    data: <?= json_encode($status_data) ?>,
                    backgroundColor: ['#1cc88a', '#f6c23e', '#e74a3b'],
                    hoverBackgroundColor: ['#17a673', '#dda20a', '#be2617'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // File Type Chart
        const fileTypeCtx = document.getElementById('fileTypeChart').getContext('2d');
        const fileTypeChart = new Chart(fileTypeCtx, {
            type: 'pie',
            data: {
                labels: <?= json_encode($filetype_names) ?>,
                datasets: [{
                    data: <?= json_encode($filetype_data) ?>,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#f8f9fc', '#858796'],
                    hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617', '#e6e8ef', '#60616f'],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Daily Uploads Chart
        const uploadsCtx = document.getElementById('uploadsChart').getContext('2d');
        const uploadsChart = new Chart(uploadsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($upload_dates) ?>,
                datasets: [{
                    label: 'Resource Uploads',
                    data: <?= json_encode($upload_counts) ?>,
                    fill: true,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    tension: 0.3
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
        
        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($category_names) ?>,
                datasets: [{
                    label: 'Resources',
                    data: <?= json_encode($category_data) ?>,
                    backgroundColor: '#4e73df',
                    hoverBackgroundColor: '#2e59d9'
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