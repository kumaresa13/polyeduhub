<?php
// File path: admin/reports/user-statistics.php

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
    
    // Get active users by uploads
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department, 
               COUNT(r.id) as upload_count,
               SUM(r.download_count) as total_downloads
        FROM users u
        JOIN resources r ON u.id = r.user_id AND r.status = 'approved'
        WHERE r.created_at BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY upload_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_uploaders = $stmt->fetchAll();
    
    // Get active users by downloads
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department, 
               COUNT(rd.id) as download_count
        FROM users u
        JOIN resource_downloads rd ON u.id = rd.user_id
        WHERE rd.downloaded_at BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY download_count DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_downloaders = $stmt->fetchAll();
    
    // Get active users by points
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department, 
               SUM(ph.points) as earned_points
        FROM users u
        JOIN points_history ph ON u.id = ph.user_id
        WHERE ph.created_at BETWEEN ? AND ?
        GROUP BY u.id
        ORDER BY earned_points DESC
        LIMIT 10
    ");
    $stmt->execute([$start_date, $end_date]);
    $top_earners = $stmt->fetchAll();
    
    // Get department statistics
    $stmt = $pdo->prepare("
        SELECT u.department, 
               COUNT(DISTINCT u.id) as user_count,
               COUNT(DISTINCT r.id) as resource_count,
               SUM(r.download_count) as total_downloads,
               SUM(CASE WHEN ph.created_at BETWEEN ? AND ? THEN ph.points ELSE 0 END) as earned_points
        FROM users u
        LEFT JOIN resources r ON u.id = r.user_id AND r.status = 'approved' AND r.created_at BETWEEN ? AND ?
        LEFT JOIN points_history ph ON u.id = ph.user_id
        WHERE u.role = 'student' AND u.department IS NOT NULL AND u.department != ''
        GROUP BY u.department
        ORDER BY resource_count DESC
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
    $department_stats = $stmt->fetchAll();
    
    // Get user registrations by day
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users
        WHERE role = 'student' AND created_at BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute([$start_date, $end_date]);
    $daily_registrations = $stmt->fetchAll();
    
    // Prepare data for charts
    $registration_dates = [];
    $registration_counts = [];
    foreach ($daily_registrations as $day) {
        $registration_dates[] = date('M j', strtotime($day['date']));
        $registration_counts[] = $day['count'];
    }
    
    // Department chart data
    $department_names = [];
    $department_resources = [];
    $department_downloads = [];
    foreach ($department_stats as $dept) {
        $department_names[] = $dept['department'];
        $department_resources[] = $dept['resource_count'];
        $department_downloads[] = $dept['total_downloads'];
    }
    
    // Get total user count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE role = 'student' AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $new_user_count = $stmt->fetchColumn();
    
    // Get total active user count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) 
        FROM (
            SELECT user_id FROM resources WHERE created_at BETWEEN ? AND ?
            UNION
            SELECT user_id FROM resource_downloads WHERE downloaded_at BETWEEN ? AND ?
            UNION
            SELECT user_id FROM resource_comments WHERE created_at BETWEEN ? AND ?
        ) AS active_users
    ");
    $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
    $active_user_count = $stmt->fetchColumn();
    
    // Get total points awarded in period
    $stmt = $pdo->prepare("
        SELECT SUM(points) FROM points_history 
        WHERE created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $total_points_awarded = $stmt->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    error_log("Error generating user statistics: " . $e->getMessage());
    $top_uploaders = [];
    $top_downloaders = [];
    $top_earners = [];
    $department_stats = [];
    $daily_registrations = [];
    $registration_dates = [];
    $registration_counts = [];
    $department_names = [];
    $department_resources = [];
    $department_downloads = [];
    $new_user_count = 0;
    $active_user_count = 0;
    $total_points_awarded = 0;
}

// Check for export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="user_statistics_' . date('Y-m-d') . '.csv"');
    
    // Create CSV file
    $output = fopen('php://output', 'w');
    
    // Add report title
    fputcsv($output, ["User Statistics Report - " . date('F j, Y', strtotime($start_date)) . " to " . date('F j, Y', strtotime($end_date))]);
    fputcsv($output, [""]);
    
    // Add overall statistics section
    fputcsv($output, ["Overall Statistics"]);
    fputcsv($output, ["New Users", $new_user_count]);
    fputcsv($output, ["Active Users", $active_user_count]);
    fputcsv($output, ["Total Points Awarded", $total_points_awarded]);
    fputcsv($output, [""]);
    
    // Add top uploaders
    fputcsv($output, ["Top Uploaders"]);
    fputcsv($output, ["Name", "Department", "Uploads", "Downloads"]);
    foreach ($top_uploaders as $user) {
        fputcsv($output, [
            $user['first_name'] . ' ' . $user['last_name'],
            $user['department'],
            $user['upload_count'],
            $user['total_downloads']
        ]);
    }
    fputcsv($output, [""]);
    
    // Add top downloaders
    fputcsv($output, ["Top Downloaders"]);
    fputcsv($output, ["Name", "Department", "Downloads"]);
    foreach ($top_downloaders as $user) {
        fputcsv($output, [
            $user['first_name'] . ' ' . $user['last_name'],
            $user['department'],
            $user['download_count']
        ]);
    }
    fputcsv($output, [""]);
    
    // Add top point earners
    fputcsv($output, ["Top Point Earners"]);
    fputcsv($output, ["Name", "Department", "Points Earned"]);
    foreach ($top_earners as $user) {
        fputcsv($output, [
            $user['first_name'] . ' ' . $user['last_name'],
            $user['department'],
            $user['earned_points']
        ]);
    }
    fputcsv($output, [""]);
    
    // Add department statistics
    fputcsv($output, ["Department Statistics"]);
    fputcsv($output, ["Department", "Users", "Resources", "Downloads", "Points Earned"]);
    foreach ($department_stats as $dept) {
        fputcsv($output, [
            $dept['department'],
            $dept['user_count'],
            $dept['resource_count'],
            $dept['total_downloads'],
            $dept['earned_points']
        ]);
    }
    fputcsv($output, [""]);
    
    // Add daily registrations
    fputcsv($output, ["Daily User Registrations"]);
    fputcsv($output, ["Date", "New Users"]);
    foreach ($daily_registrations as $day) {
        fputcsv($output, [$day['date'], $day['count']]);
    }
    
    fclose($output);
    exit;
}

// Set page title and nested path variable
$page_title = "User Statistics";
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
        <h1 class="h3 mb-0 text-gray-800">User Statistics</h1>
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
        <h4>User Statistics Report</h4>
        <p><?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?></p>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                New Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($new_user_count) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($active_user_count) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Points Awarded</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_points_awarded) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-star fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <!-- Daily Registrations Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Daily User Registrations</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($registration_dates)): ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No registration data available for this period</p>
                    </div>
                    <?php else: ?>
                    <div class="chart-container">
                        <canvas id="registrationsChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Department Activity Chart -->
        <div class="col-xl-6 col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Department Activity</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($department_names)): ?>
                    <div class="text-center py-5">
                        <p class="text-muted">No department data available for this period</p>
                    </div>
                    <?php else: ?>
                    <div class="chart-container">
                        <canvas id="departmentChart"></canvas>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Users Tables -->
    <div class="row">
        <!-- Top Uploaders -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Uploaders</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Uploads</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_uploaders)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($top_uploaders as $index => $user): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <a href="../users/view.php?id=<?= $user['id'] ?>">
                                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                            </a>
                                            <div class="small text-muted"><?= htmlspecialchars($user['department'] ?: 'N/A') ?></div>
                                        </td>
                                        <td><?= number_format($user['upload_count']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Downloaders -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Downloaders</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Downloads</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_downloaders)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($top_downloaders as $index => $user): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <a href="../users/view.php?id=<?= $user['id'] ?>">
                                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                            </a>
                                            <div class="small text-muted"><?= htmlspecialchars($user['department'] ?: 'N/A') ?></div>
                                        </td>
                                        <td><?= number_format($user['download_count']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Point Earners -->
        <div class="col-lg-4 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top Point Earners</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th>Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($top_earners)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No data available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($top_earners as $index => $user): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <a href="../users/view.php?id=<?= $user['id'] ?>">
                                                <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                            </a>
                                            <div class="small text-muted"><?= htmlspecialchars($user['department'] ?: 'N/A') ?></div>
                                        </td>
                                        <td><?= number_format($user['earned_points']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Department Statistics -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Department Statistics</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Users</th>
                            <th>Resources</th>
                            <th>Total Downloads</th>
                            <th>Points Earned</th>
                            <th>Avg Points per User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($department_stats)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No department data available</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($department_stats as $dept): ?>
                            <tr>
                                <td><?= htmlspecialchars($dept['department']) ?></td>
                                <td><?= number_format($dept['user_count']) ?></td>
                                <td><?= number_format($dept['resource_count']) ?></td>
                                <td><?= number_format($dept['total_downloads']) ?></td>
                                <td><?= number_format($dept['earned_points']) ?></td>
                                <td><?= number_format($dept['user_count'] > 0 ? $dept['earned_points'] / $dept['user_count'] : 0) ?></td>
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
        <?php if (!empty($registration_dates)): ?>
        // Registration Chart
        const registrationsCtx = document.getElementById('registrationsChart').getContext('2d');
        const registrationsChart = new Chart(registrationsCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($registration_dates) ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?= json_encode($registration_counts) ?>,
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
                }
            }
        });
        <?php endif; ?>
        
        <?php if (!empty($department_names)): ?>
        // Department Chart
        const departmentCtx = document.getElementById('departmentChart').getContext('2d');
        const departmentChart = new Chart(departmentCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($department_names) ?>,
                datasets: [{
                    label: 'Resources',
                    data: <?= json_encode($department_resources) ?>,
                    backgroundColor: '#4e73df',
                    order: 2
                }, {
                    label: 'Downloads',
                    data: <?= json_encode($department_downloads) ?>,
                    backgroundColor: '#1cc88a',
                    type: 'line',
                    order: 1
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