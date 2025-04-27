<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Check if the session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Only check if user is logged in
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

// Set page title and specify a nested path variable
$page_title = "My Favorites";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

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
            <div class="text-center py-5">
                <i class="far fa-heart fa-4x text-muted mb-3"></i>
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
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= $sort !== 'latest' ? '&sort=' . $sort : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
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

<script>
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
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>