

<?php
// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../admin-login.php");
    exit();
}

// Get admin user information
$admin_id = $_SESSION['id'];

// Dashboard stats
try {
    $pdo = getDbConnection();
    
    // Get total users count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stmt->execute();
    $total_users = $stmt->fetch()['count'];
    
    // Get total resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM resources");
    $stmt->execute();
    $total_resources = $stmt->fetch()['count'];
    
    // Get pending resources count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM resources WHERE status = 'pending'");
    $stmt->execute();
    $pending_resources = $stmt->fetch()['count'];
    
    // Get total downloads count
    $stmt = $pdo->prepare("SELECT SUM(download_count) as count FROM resources");
    $stmt->execute();
    $total_downloads = $stmt->fetch()['count'] ?? 0;
    
    // Get recent activities
    $stmt = $pdo->prepare("
        SELECT al.action, al.details, al.created_at, u.first_name, u.last_name
        FROM activity_log al
        JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
    // Get recent resources
    $stmt = $pdo->prepare("
        SELECT r.id, r.title, r.status, r.created_at, u.first_name, u.last_name
        FROM resources r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_resources = $stmt->fetchAll();
    
    // Get resource distribution by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM resources 
        GROUP BY status
    ");
    $stmt->execute();
    $status_distribution = $stmt->fetchAll();
    
    // Format data for pie chart
    $status_labels = [];
    $status_data = [];
    $status_colors = [
        'approved' => '#4e73df',
        'pending' => '#f6c23e',
        'rejected' => '#e74a3b'
    ];
    $status_hover_colors = [
        'approved' => '#2e59d9',
        'pending' => '#dda20a',
        'rejected' => '#be2617'
    ];
    
    foreach ($status_distribution as $status) {
        $status_labels[] = ucfirst($status['status']);
        $status_data[] = $status['count'];
    }
    
    // Get resource uploads by month for the last 6 months
    $monthly_uploads = [];
    $categories = [];
    
    // First, get all categories
    $stmt = $pdo->prepare("SELECT id, name FROM resource_categories");
    $stmt->execute();
    $category_list = $stmt->fetchAll();
    
    // Initialize empty datasets for each category
    foreach ($category_list as $category) {
        $categories[$category['id']] = [
            'name' => $category['name'],
            'data' => array_fill(0, 6, 0) // Initialize with zeros for 6 months
        ];
    }
    
    // Prepare labels for last 6 months
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $current_month = date('n') - 1; // 0-indexed month
    $current_year = date('Y');
    
    $month_labels = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_index = ($current_month - $i + 12) % 12;
        $month_labels[] = $months[$month_index];
        
        // Get year for this month (may be previous year)
        $year = $current_year;
        if ($current_month - $i < 0) {
            $year--;
        }
        
        // First day of the month
        $start_date = sprintf('%d-%02d-01', $year, $month_index + 1);
        
        // Last day of the month
        $last_day = date('t', strtotime($start_date));
        $end_date = sprintf('%d-%02d-%02d', $year, $month_index + 1, $last_day);
        
        // Get uploads for this month by category
        $stmt = $pdo->prepare("
            SELECT category_id, COUNT(*) as count
            FROM resources
            WHERE created_at BETWEEN ? AND ?
            GROUP BY category_id
        ");
        $stmt->execute([$start_date, $end_date]);
        $monthly_results = $stmt->fetchAll();
        
        // Map results to datasets
        foreach ($monthly_results as $result) {
            if (isset($categories[$result['category_id']])) {
                $categories[$result['category_id']]['data'][5-$i] = $result['count'];
            }
        }
    }
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $total_users = 0;
    $total_resources = 0;
    $pending_resources = 0;
    $total_downloads = 0;
    $recent_activities = [];
    $recent_resources = [];
    $status_labels = [];
    $status_data = [];
    $month_labels = [];
    $categories = [];
}

// Set page title
$page_title = "Admin Dashboard";

// Include header
include_once 'includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Admin Dashboard</h1>
        
    </div>

    <!-- Stats Cards Row -->
    <div class="row">
        <!-- Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Students</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_users) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resources Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
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

        <!-- Pending Resources Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Approvals</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($pending_resources) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Downloads Card -->
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

    <!-- Content Row -->
    <div class="row">
        <!-- Pending Resources Card -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Resources Needing Approval</h6>
                    <a href="resources/approve.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_resources)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <p>No resources pending approval.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Uploaded By</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_resources as $resource): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($resource['title']) ?></td>
                                            <td><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></td>
                                            <td>
                                                <?php if ($resource['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($resource['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($resource['created_at'])) ?></td>
                                            <td>
                                                <a href="resources/review.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-search"></i>
                                                </a>
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

        <!-- Recent Activities -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                    <a href="reports/activity.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p>No recent activities to display.</p>
                        </div>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="d-flex mb-4">
                                    <div class="flex-shrink-0">
                                        <div class="bg-primary text-white p-2 rounded">
                                            <i class="fas fa-history"></i>
                                        </div>
                                    </div>
                                    <div class="ms-3">
                                        <h6 class="mb-0"><?= htmlspecialchars($activity['action']) ?></h6>
                                        <div class="text-muted"><?= htmlspecialchars($activity['details']) ?></div>
                                        <div class="small text-muted">
                                            By <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?> • 
                                            <?= time_elapsed_string($activity['created_at']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Charts Row -->
    <div class="row">
        <!-- Resource Type Distribution Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Upload Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="resourceUploadChart"></canvas>
                    </div>
                    <div class="mt-4 small text-center">
                        <?php 
                        $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#5a5c69'];
                        $i = 0;
                        foreach ($categories as $category): 
                            $color = $colors[$i % count($colors)];
                            $i++;
                        ?>
                        <span class="me-2">
                            <i class="fas fa-circle" style="color: <?= $color ?>"></i> <?= htmlspecialchars($category['name']) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Status Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Resource Distribution</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="resourceDistribution"></canvas>
                    </div>
                    <div class="mt-4 small text-center">
                        <?php foreach ($status_labels as $index => $label): ?>
                        <span class="me-2">
                            <i class="fas fa-circle" style="color: <?= $status_colors[strtolower($label)] ?? '#858796' ?>"></i> <?= $label ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links Row -->
    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Links</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="resources/approve.php" class="btn btn-primary btn-block p-3 d-flex align-items-center justify-content-between">
                                <span>Approve Resources</span>
                                <i class="fas fa-check-circle"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="users/index.php" class="btn btn-success btn-block p-3 d-flex align-items-center justify-content-between">
                                <span>Manage Users</span>
                                <i class="fas fa-users"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="reports/resources.php" class="btn btn-info btn-block p-3 d-flex align-items-center justify-content-between">
                                <span>Resource Reports</span>
                                <i class="fas fa-chart-bar"></i>
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <a href="settings/general.php" class="btn btn-secondary btn-block p-3 d-flex align-items-center justify-content-between">
                                <span>System Settings</span>
                                <i class="fas fa-cog"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Resource Upload Chart with real data
    const uploadChartCtx = document.getElementById('resourceUploadChart').getContext('2d');
    const uploadChart = new Chart(uploadChartCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($month_labels) ?>,
            datasets: [
                <?php 
                $i = 0;
                foreach ($categories as $id => $category): 
                    $color = $colors[$i % count($colors)];
                    $i++;
                ?>
                {
                    label: <?= json_encode($category['name']) ?>,
                    data: <?= json_encode($category['data']) ?>,
                    backgroundColor: '<?= $color ?>15',
                    borderColor: '<?= $color ?>',
                    borderWidth: 2,
                    pointBackgroundColor: '<?= $color ?>',
                    tension: 0.3
                },
                <?php endforeach; ?>
            ]
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Resource Distribution Pie Chart with real data
    const distributionChartCtx = document.getElementById('resourceDistribution').getContext('2d');
    const distributionChart = new Chart(distributionChartCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($status_labels) ?>,
            datasets: [{
                data: <?= json_encode($status_data) ?>,
                backgroundColor: [
                    <?php 
                    foreach ($status_labels as $label): 
                        echo "'" . ($status_colors[strtolower($label)] ?? '#858796') . "', ";
                    endforeach; 
                    ?>
                ],
                hoverBackgroundColor: [
                    <?php 
                    foreach ($status_labels as $label): 
                        echo "'" . ($status_hover_colors[strtolower($label)] ?? '#6b6d7d') . "', ";
                    endforeach; 
                    ?>
                ],
                hoverBorderColor: 'rgba(234, 236, 244, 1)'
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '75%'
        }
    });
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>