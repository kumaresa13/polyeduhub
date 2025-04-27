<?php
// File path: admin/gamification/badges.php

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

// Handle badge operations
$message = '';
$message_type = '';

// Add new badge
if (isset($_POST['add_badge'])) {
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $description = htmlspecialchars(trim($_POST['description'] ?? ''));
    $points_required = intval($_POST['points_required']);
    $icon = filter_var($_POST['icon']);

    if (empty($name)) {
        $message = "Badge name is required";
        $message_type = "danger";
    } else {
        try {
            $pdo = getDbConnection();

            // Check if badge already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM badges WHERE name = ?");
            $stmt->execute([$name]);
            $badgeExists = $stmt->fetchColumn() > 0;

            if ($badgeExists) {
                $message = "A badge with this name already exists";
                $message_type = "danger";
            } else {
                // Insert new badge
                $stmt = $pdo->prepare("
                    INSERT INTO badges (name, description, points_required, icon, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$name, $description, $points_required, $icon]);

                // Log action
                logAdminAction($admin_id, "Added badge", "Added new badge: " . $name);

                $message = "Badge added successfully";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            error_log("Error adding badge: " . $e->getMessage());
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Edit badge
if (isset($_POST['edit_badge'])) {
    $badge_id = intval($_POST['badge_id']);
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
    $points_required = intval($_POST['points_required']);
    $icon = filter_var($_POST['icon'], FILTER_SANITIZE_STRING);

    if (empty($name)) {
        $message = "Badge name is required";
        $message_type = "danger";
    } else {
        try {
            $pdo = getDbConnection();

            // Check if badge already exists with this name (excluding current)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM badges WHERE name = ? AND id != ?");
            $stmt->execute([$name, $badge_id]);
            $badgeExists = $stmt->fetchColumn() > 0;

            if ($badgeExists) {
                $message = "A badge with this name already exists";
                $message_type = "danger";
            } else {
                // Update badge
                $stmt = $pdo->prepare("
                    UPDATE badges 
                    SET name = ?, description = ?, points_required = ?, icon = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $points_required, $icon, $badge_id]);

                // Log action
                logAdminAction($admin_id, "Updated badge", "Updated badge ID: " . $badge_id);

                $message = "Badge updated successfully";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            error_log("Error updating badge: " . $e->getMessage());
            $message = "Database error: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Delete badge
if (isset($_POST['delete_badge'])) {
    $badge_id = intval($_POST['badge_id']);

    try {
        $pdo = getDbConnection();

        // Get badge name for logging
        $stmt = $pdo->prepare("SELECT name FROM badges WHERE id = ?");
        $stmt->execute([$badge_id]);
        $badgeName = $stmt->fetchColumn();

        // Delete badge
        $stmt = $pdo->prepare("DELETE FROM badges WHERE id = ?");
        $stmt->execute([$badge_id]);

        // Also delete from user_badges table
        $stmt = $pdo->prepare("DELETE FROM user_badges WHERE badge_id = ?");
        $stmt->execute([$badge_id]);

        // Log action
        logAdminAction($admin_id, "Deleted badge", "Deleted badge: " . $badgeName);

        $message = "Badge deleted successfully";
        $message_type = "success";
    } catch (PDOException $e) {
        error_log("Error deleting badge: " . $e->getMessage());
        $message = "Database error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Get all badges
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT b.*, COUNT(ub.user_id) as assigned_count
        FROM badges b
        LEFT JOIN user_badges ub ON b.id = ub.badge_id
        GROUP BY b.id
        ORDER BY b.points_required ASC
    ");
    $stmt->execute();
    $badges = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting badges: " . $e->getMessage());
    $badges = [];
}

// Badge icons list
$badge_icons = [
    'badge-newcomer.png' => 'Newcomer',
    'badge-bronze.png' => 'Bronze',
    'badge-silver.png' => 'Silver',
    'badge-gold.png' => 'Gold',
    'badge-contributor.png' => 'Contributor',
    'badge-participant.png' => 'Participant',
    'badge-sharer.png' => 'Sharer',
    'badge-helper.png' => 'Helper',
    'badge-champion.png' => 'Champion',
    'badge-expert.png' => 'Expert',
    'badge-star.png' => 'Star'
];

// Set page title and nested path variable
$page_title = "Manage Badges";
$nested = true;

// Include header
include_once '../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Badges Management</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBadgeModal">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Badge
        </button>
    </div>

    <!-- Display Messages -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Badges Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Available Badges</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="badgesTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Icon</th>
                            <th>Badge Name</th>
                            <th>Description</th>
                            <th>Points Required</th>
                            <th>Awarded To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($badges as $badge): ?>
                            <?php
                            // Determine badge level based on points required
                            $badge_level = 'bronze';
                            if ($badge['points_required'] >= 500) {
                                $badge_level = 'gold';
                            } elseif ($badge['points_required'] >= 200) {
                                $badge_level = 'silver';
                            }
                            $level_color = [
                                'bronze' => '#cd7f32',
                                'silver' => '#c0c0c0',
                                'gold' => '#ffd700'
                            ][$badge_level];
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="position-relative d-inline-block">
                                        <img src="../../assets/img/badges/<?= htmlspecialchars($badge['icon']) ?>"
                                            alt="<?= htmlspecialchars($badge['name']) ?>" width="40" height="40">
                                        <span class="position-absolute top-0 start-100 translate-middle p-1 rounded-circle"
                                            style="background-color: <?= $level_color ?>; width: 12px; height: 12px;">
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($badge['name']) ?></td>
                                <td><?= htmlspecialchars($badge['description']) ?></td>
                                <td><?= number_format($badge['points_required']) ?></td>
                                <td><?= number_format($badge['assigned_count']) ?> users</td>
                                <td>
                                    <button class="btn btn-sm btn-primary edit-badge" data-bs-toggle="modal"
                                        data-bs-target="#editBadgeModal" data-id="<?= $badge['id'] ?>"
                                        data-name="<?= htmlspecialchars($badge['name']) ?>"
                                        data-description="<?= htmlspecialchars($badge['description']) ?>"
                                        data-points="<?= $badge['points_required'] ?>"
                                        data-icon="<?= htmlspecialchars($badge['icon']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-badge" data-bs-toggle="modal"
                                        data-bs-target="#deleteBadgeModal" data-id="<?= $badge['id'] ?>"
                                        data-name="<?= htmlspecialchars($badge['name']) ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($badges)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No badges found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Badge Levels & Instructions Card -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Badge Levels</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded-circle mb-2"
                                style="background-color: #cd7f32; width: 50px; height: 50px; margin: 0 auto;"></div>
                            <h5 class="font-weight-bold">Bronze Level</h5>
                            <p>0-199 Points</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded-circle mb-2"
                                style="background-color: #c0c0c0; width: 50px; height: 50px; margin: 0 auto;"></div>
                            <h5 class="font-weight-bold">Silver Level</h5>
                            <p>200-499 Points</p>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 rounded-circle mb-2"
                                style="background-color: #ffd700; width: 50px; height: 50px; margin: 0 auto;"></div>
                            <h5 class="font-weight-bold">Gold Level</h5>
                            <p>500+ Points</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Badge Instructions</h6>
                </div>
                <div class="card-body">
                    <ul>
                        <li><strong>Points Required:</strong> The minimum number of points a user needs to earn this
                            badge.</li>
                        <li><strong>Badge Icons:</strong> Upload badge icons to the <code>/assets/img/badges/</code>
                            directory.</li>
                        <li><strong>Automatic Assignment:</strong> Badges are automatically assigned to users when they
                            reach the required points.</li>
                        <li><strong>Badge Display:</strong> Badges appear on the user's profile and in the leaderboard.
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Badge Modal -->
<div class="modal fade" id="addBadgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Badge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="badge_name" class="form-label">Badge Name</label>
                        <input type="text" class="form-control" id="badge_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="badge_description" class="form-label">Description</label>
                        <textarea class="form-control" id="badge_description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="points_required" class="form-label">Points Required</label>
                        <input type="number" class="form-control" id="points_required" name="points_required" min="0"
                            value="0">
                    </div>
                    <div class="mb-3">
                        <label for="badge_icon" class="form-label">Badge Icon</label>
                        <select class="form-select" id="badge_icon" name="icon" required>
                            <option value="">Select an Icon</option>
                            <?php foreach ($badge_icons as $icon => $icon_name): ?>
                                <option value="<?= $icon ?>"><?= $icon_name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_badge" class="btn btn-primary">Add Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Badge Modal -->
<div class="modal fade" id="editBadgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Badge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="edit_badge_id" name="badge_id">
                    <div class="mb-3">
                        <label for="edit_badge_name" class="form-label">Badge Name</label>
                        <input type="text" class="form-control" id="edit_badge_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_badge_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_badge_description" name="description"
                            rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_points_required" class="form-label">Points Required</label>
                        <input type="number" class="form-control" id="edit_points_required" name="points_required"
                            min="0">
                    </div>
                    <div class="mb-3">
                        <label for="edit_badge_icon" class="form-label">Badge Icon</label>
                        <select class="form-select" id="edit_badge_icon" name="icon" required>
                            <?php foreach ($badge_icons as $icon => $icon_name): ?>
                                <option value="<?= $icon ?>"><?= $icon_name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_badge" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Badge Modal -->
<div class="modal fade" id="deleteBadgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Badge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" id="delete_badge_id" name="badge_id">
                    <p>Are you sure you want to delete the badge: <strong id="delete_badge_name"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will also remove this badge from all users who have earned it.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_badge" class="btn btn-danger">Delete Badge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Edit Badge Modal
        const editButtons = document.querySelectorAll('.edit-badge');
        editButtons.forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const description = this.getAttribute('data-description');
                const points = this.getAttribute('data-points');
                const icon = this.getAttribute('data-icon');

                document.getElementById('edit_badge_id').value = id;
                document.getElementById('edit_badge_name').value = name;
                document.getElementById('edit_badge_description').value = description;
                document.getElementById('edit_points_required').value = points;

                const iconSelect = document.getElementById('edit_badge_icon');
                for (let i = 0; i < iconSelect.options.length; i++) {
                    if (iconSelect.options[i].value === icon) {
                        iconSelect.options[i].selected = true;
                        break;
                    }
                }
            });
        });

        // Delete Badge Modal
        const deleteButtons = document.querySelectorAll('.delete-badge');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function () {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                document.getElementById('delete_badge_id').value = id;
                document.getElementById('delete_badge_name').textContent = name;
            });
        });
    });
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>