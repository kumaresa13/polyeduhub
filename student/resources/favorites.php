<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session and check if user is logged in
session_start();
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'];

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query
$params = [$user_id];
$where_clauses = ["r.status = 'approved' AND rf.user_id = ?"];

if (!empty($search)) {
    $where_clauses[] = "(r.title LIKE ? OR r.description LIKE ? OR rt.name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_sql = implode(' AND ', $where_clauses);

// Sorting
switch ($sort) {
    case 'downloads':
        $order_sql = "r.download_count DESC";
        break;
    case 'rating':
        $order_sql = "avg_rating DESC";
        break;
    case 'oldest_added':
        $order_sql = "rf.created_at ASC";
        break;
    default:
        $order_sql = "rf.created_at DESC";
        break;
}

// Get total resource count
$count_sql = "
    SELECT COUNT(DISTINCT r.id) as total
    FROM resources r
    JOIN resource_favorites rf ON r.id = rf.resource_id
    LEFT JOIN resource_tag_relationship rtr ON r.id = rtr.resource_id
    LEFT JOIN resource_tags rt ON rtr.tag_id = rt.id
    WHERE {$where_sql}
";

$pdo = getDbConnection();
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_resources = $stmt->fetchColumn();
$total_pages = ceil($total_resources / $per_page);

// Get resources
$sql = "
    SELECT r.id, r.title, r.description, r.file_path, r.file_type, r.file_size, 
           r.thumbnail, r.category_id, r.download_count, r.view_count, r.created_at,
           u.id as user_id, u.first_name, u.last_name, u.profile_image, u.department,
           rc.name as category_name,
           rf.created_at as favorited_at,
           (SELECT COUNT(*) FROM resource_comments WHERE resource_id = r.id) as comment_count,
           (SELECT COALESCE(AVG(rating), 0) FROM resource_ratings WHERE resource_id = r.id) as avg_rating,
           (SELECT COUNT(*) FROM resource_ratings WHERE resource_id = r.id) as rating_count,
           (SELECT rating FROM resource_ratings WHERE resource_id = r.id AND user_id = ?) as user_rating
    FROM resources r
    JOIN resource_favorites rf ON r.id = rf.resource_id
    JOIN users u ON r.user_id = u.id
    JOIN resource_categories rc ON r.category_id = rc.id
    LEFT JOIN resource_tag_relationship rtr ON r.id = rtr.resource_id
    LEFT JOIN resource_tags rt ON rtr.tag_id = rt.id
    WHERE {$where_sql}
    GROUP BY r.id
    ORDER BY {$order_sql}
    LIMIT {$per_page} OFFSET {$offset}
";

// Add user_id params at beginning
array_unshift($params, $user_id);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Page title
$page_title = "My Favorites";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= $page_title ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/styles.css">
    
    <style>
        .resource-card {
            transition: transform 0.2s;
            height: 100%;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
        }
        
        .resource-thumbnail {
            height: 160px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fc;
        }
        
        .resource-thumbnail img {
            max-height: 100%;
            object-fit: cover;
        }
        
        .resource-thumbnail .resource-icon {
            font-size: 4rem;
            color: #4e73df;
        }
        
        .resource-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .resource-description {
            max-height: 60px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .star-rating {
            color: #ffc107;
        }
        
        .star-rating .far.fa-star {
            color: #e4e5e9;
        }
        
        .favorite-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.8);
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .favorite-btn:hover {
            background-color: rgba(255, 255, 255, 1);
        }
        
        .favorite-btn.active {
            color: #e74a3b;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 0;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #d1d3e2;
            margin-bottom: 20px;
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon rotate-n-15">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="sidebar-brand-text mx-3">PolyEduHub</div>
        </div>
        
        <hr class="sidebar-divider">
        
        <div class="sidebar-heading">
            Navigation
        </div>
        
        <ul class="nav">
            <li class="nav-item">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Resources
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-fw fa-folder"></i>
                    <span>Browse Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="upload.php">
                    <i class="fas fa-fw fa-file-upload"></i>
                    <span>Upload Resource</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="my-resources.php">
                    <i class="fas fa-fw fa-list"></i>
                    <span>My Resources</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link active" href="favorites.php">
                    <i class="fas fa-fw fa-star"></i>
                    <span>Favorites</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Community
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="../chat/index.php">
                    <i class="fas fa-fw fa-comments"></i>
                    <span>Chat Rooms</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../leaderboard/index.php">
                    <i class="fas fa-fw fa-trophy"></i>
                    <span>Leaderboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <div class="sidebar-heading">
                Account
            </div>
            
            <li class="nav-item">
                <a class="nav-link" href="../profile/index.php">
                    <i class="fas fa-fw fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../profile/badges.php">
                    <i class="fas fa-fw fa-award"></i>
                    <span>My Badges</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="../notifications/index.php">
                    <i class="fas fa-fw fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            
            <li class="nav-item">
                <a class="nav-link" href="../../logout.php">
                    <i class="fas fa-fw fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Content Wrapper -->
    <div class="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand-lg navbar-light mb-4">
            <div class="container-fluid">
                <button class="btn toggle-sidebar" id="toggleSidebar">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Search -->
                <form class="navbar-search" method="GET" action="">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search in favorites..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="navbar-nav ms-auto">
                    <!-- User Information -->
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle user-info" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="d-none d-lg-inline text-gray-600 small me-2"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
                            <img src="../../assets/img/ui/default-profile.png" alt="Profile Image">
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="../profile/index.php"><i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../profile/edit.php"><i class="fas fa-cogs fa-sm fa-fw me-2 text-gray-400"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../../logout.php"><i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid">
            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">My Favorites</h1>
                
                <!-- Sort Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-sort me-1"></i> 
                        <?php
                            switch ($sort) {
                                case 'downloads':
                                    echo 'Most Downloads';
                                    break;
                                case 'rating':
                                    echo 'Highest Rated';
                                    break;
                                case 'oldest_added':
                                    echo 'Oldest Favorites';
                                    break;
                                default:
                                    echo 'Recently Favorited';
                                    break;
                            }
                        ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="sortDropdown">
                        <li>
                            <a class="dropdown-item <?= $sort === 'latest' ? 'active' : '' ?>" href="favorites.php?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=latest">
                                Recently Favorited
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $sort === 'oldest_added' ? 'active' : '' ?>" href="favorites.php?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=oldest_added">
                                Oldest Favorites
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $sort === 'downloads' ? 'active' : '' ?>" href="favorites.php?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=downloads">
                                Most Downloads
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item <?= $sort === 'rating' ? 'active' : '' ?>" href="favorites.php?<?= !empty($search) ? 'search=' . urlencode($search) . '&' : '' ?>sort=rating">
                                Highest Rated
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Favorites Content -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?php if (!empty($search)): ?>
                        Search Results in Favorites for "<?= htmlspecialchars($search) ?>"
                        <?php else: ?>
                        Your Favorite Resources
                        <?php endif; ?>
                    </h6>
                    <div class="small text-muted">
                        <?php if ($total_resources > 0): ?>
                        Showing <?= min(($offset + 1), $total_resources) ?> - <?= min(($offset + $per_page), $total_resources) ?> of <?= $total_resources ?> resources
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($resources)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h5>No favorite resources found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search)): ?>
                            No favorites match your search query. Try a different search term or clear filters.
                            <?php else: ?>
                            You haven't added any resources to your favorites yet. Browse resources and click the heart icon to add them here.
                            <?php endif; ?>
                        </p>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-search me-1"></i> Browse Resources
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="row">
                        <?php foreach ($resources as $resource): ?>
                        <div class="col-md-6 col-xl-4 mb-4">
                            <div class="card resource-card shadow-sm h-100">
                                <!-- Favorite Button -->
                                <button class="favorite-btn active" data-resource-id="<?= $resource['id'] ?>" title="Remove from favorites">
                                    <i class="fas fa-heart"></i>
                                </button>
                                
                                <!-- Resource Thumbnail -->
                                <div class="resource-thumbnail">
                                    <?php if ($resource['thumbnail']): ?>
                                    <img src="<?= htmlspecialchars($resource['thumbnail']) ?>" alt="<?= htmlspecialchars($resource['title']) ?>">
                                    <?php else: ?>
                                        <?php
                                        // Select icon based on file type
                                        $icon_class = 'fa-file';
                                        switch ($resource['file_type']) {
                                            case 'pdf':
                                                $icon_class = 'fa-file-pdf';
                                                break;
                                            case 'doc':
                                            case 'docx':
                                                $icon_class = 'fa-file-word';
                                                break;
                                            case 'xls':
                                            case 'xlsx':
                                                $icon_class = 'fa-file-excel';
                                                break;
                                            case 'ppt':
                                            case 'pptx':
                                                $icon_class = 'fa-file-powerpoint';
                                                break;
                                            case 'zip':
                                            case 'rar':
                                                $icon_class = 'fa-file-archive';
                                                break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif':
                                                $icon_class = 'fa-file-image';
                                                break;
                                        }
                                        ?>
                                    <div class="resource-icon">
                                        <i class="fas <?= $icon_class ?>"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <h5 class="card-title h6 mb-1">
                                        <a href="view.php?id=<?= $resource['id'] ?>" class="text-decoration-none text-dark">
                                            <?= htmlspecialchars($resource['title']) ?>
                                        </a>
                                    </h5>
                                    <div class="resource-meta mb-2 d-flex justify-content-between">
                                        <span>
                                            <i class="fas fa-folder-open me-1"></i> <?= htmlspecialchars($resource['category_name']) ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-file me-1"></i> <?= strtoupper($resource['file_type']) ?>
                                        </span>
                                    </div>
                                    <p class="card-text resource-description">
                                        <?= htmlspecialchars($resource['description'] ?: 'No description provided.') ?>
                                    </p>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="star-rating" title="Rating: <?= number_format($resource['avg_rating'], 1) ?>/5">
                                            <?php
                                            $rating = round($resource['avg_rating'] * 2) / 2; // Round to nearest 0.5
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($rating >= $i) {
                                                    echo '<i class="fas fa-star"></i>';
                                                } elseif ($rating >= $i - 0.5) {
                                                    echo '<i class="fas fa-star-half-alt"></i>';
                                                } else {
                                                    echo '<i class="far fa-star"></i>';
                                                }
                                            }
                                            ?>
                                            <small class="ms-1 text-muted">(<?= $resource['rating_count'] ?>)</small>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-download me-1"></i> <?= $resource['download_count'] ?>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <a href="../profile/index.php?id=<?= $resource['user_id'] ?>">
                                                <img src="<?= $resource['profile_image'] ? htmlspecialchars($resource['profile_image']) : '../../assets/img/ui/default-profile.png' ?>" alt="<?= htmlspecialchars($resource['first_name']) ?>" class="rounded-circle" width="24" height="24">
                                            </a>
                                        </div>
                                        <div class="small text-muted">
                                            <a href="../profile/index.php?id=<?= $resource['user_id'] ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']) ?></a>
                                            <div>Added <?= time_elapsed_string($resource['favorited_at']) ?></div>
                                        </div>
                                        <div class="ms-auto">
                                            <a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                                
                                <?php
                                // Show limited page numbers with ellipsis
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">' . $i . '</a>
                                    </li>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . ($sort !== 'latest' ? '&sort=' . $sort : '') . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="sticky-footer bg-white mt-4">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>&copy; <?= date('Y') ?> PolyEduHub. All rights reserved.</span>
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="../../assets/js/scripts.js"></script>
    
    <script>
        // Toggle sidebar on mobile
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.content').classList.toggle('pushed');
        });
        
        // Handle favorite toggle
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const resourceId = this.getAttribute('data-resource-id');
                
                // Send favorite request
                fetch('toggle_favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `resource_id=${resourceId}&action=remove`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the resource card from the view
                        const card = this.closest('.col-md-6');
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            
                            // Check if there are no more resources
                            const resources = document.querySelectorAll('.resource-card');
                            if (resources.length === 0) {
                                // Reload the page to show empty state
                                window.location.reload();
                            }
                        }, 300);
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
        
        // Helper function for time formatting (to be replaced by server function)
        function time_elapsed_string(datetime) {
            var now = new Date();
            var timestamp = new Date(datetime);
            var diff = Math.floor((now - timestamp) / 1000);
            
            if (diff < 60) {
                return 'just now';
            } else if (diff < 3600) {
                return Math.floor(diff / 60) + ' minutes ago';
            } else if (diff < 86400) {
                return Math.floor(diff / 3600) + ' hours ago';
            } else if (diff < 604800) {
                return Math.floor(diff / 86400) + ' days ago';
            } else {
                return 'on ' + timestamp.toLocaleDateString();
            }
        }
    </script>
</body>
</html>