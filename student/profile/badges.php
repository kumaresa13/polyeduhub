<?php
// Include configuration and database connection
require_once '../../includes/config.php';
require_once '../../includes/db-connection.php';
require_once '../../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header("Location: ../../login.php");
    exit();
}

// Get user information
$user_id = $_SESSION['id'] ?? 0;
$earned_badges = [];
$all_badges = [];
$badges = [];
$user_points = 0;

try {
    $pdo = getDbConnection();

    // Get earned badges
    $stmt = $pdo->prepare("
        SELECT b.*, ub.earned_at
        FROM badges b
        JOIN user_badges ub ON b.id = ub.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.earned_at DESC
    ");
    $stmt->execute([$user_id]);
    $earned_badges = $stmt->fetchAll();

    // Get all available badges (including unearned ones)
    $stmt = $pdo->prepare("SELECT * FROM badges ORDER BY required_points ASC");
    $stmt->execute();
    $all_badges = $stmt->fetchAll();

    // Get user points for progress calculation
    $stmt = $pdo->prepare("SELECT points FROM user_points WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_points = $stmt->fetchColumn() ?: 0;

    // Get earned badge IDs before checking for new badges
    $earned_badge_ids = array_column($earned_badges, 'id');

    // Check and award new badges based on points
    checkAndAwardBadges($user_id, $user_points, $earned_badge_ids, $all_badges);

    // Fetch earned badges again in case new ones were just awarded
    $stmt = $pdo->prepare("
    SELECT b.*, ub.earned_at
    FROM badges b
    JOIN user_badges ub ON b.id = ub.badge_id
    WHERE ub.user_id = ?
    ORDER BY ub.earned_at DESC
");
    $stmt->execute([$user_id]);
    $earned_badges = $stmt->fetchAll();

    // Update earned badge IDs after potentially awarding new badges
    $earned_badge_ids = array_column($earned_badges, 'id');

    // Organize badges (mark which ones are earned)
    $badges = [];
    foreach ($all_badges as $badge) {
        $badge['is_earned'] = in_array($badge['id'], $earned_badge_ids);
        if ($badge['is_earned']) {
            // Find earned date
            foreach ($earned_badges as $earned) {
                if ($earned['id'] === $badge['id']) {
                    $badge['earned_at'] = $earned['earned_at'];
                    break;
                }
            }
        }
        $badges[] = $badge;
    }

} catch (PDOException $e) {
    error_log("Error in badges page: " . $e->getMessage());
    $badges = [];
    $earned_badges = [];
    $all_badges = [];
    $user_points = 0;
}

// Page title
$page_title = "My Badges";
$nested = true;

// Include header
include_once '../includes/header.php';


?>


<!-- Page Content -->
<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">My Badges</h1>
        <a href="index.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Profile
        </a>
    </div>

    <!-- Badges Overview Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Badges Overview</h6>
        </div>
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="text-center">
                        <h4 class="mb-3 font-weight-bold">Your Achievement Progress</h4>
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <div class="progress" style="height: 30px; width: 80%;">
                                <?php
                                // Calculate progress percentage - based on badges earned vs total
                                $progress = (isset($earned_badges) && is_array($earned_badges) && count($earned_badges) > 0 && isset($all_badges) && is_array($all_badges) && count($all_badges) > 0)
                                    ? (count($earned_badges) / count($all_badges) * 100)
                                    : 0; ?>
                                <div class="progress-bar bg-success" role="progressbar"
                                    style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0"
                                    aria-valuemax="100">
                                    <?= round($progress) ?>%
                                </div>
                            </div>
                        </div>
                        <p class="mb-0">You've earned <?= count($earned_badges) ?> out of <?= count($all_badges) ?>
                            available badges</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="text-center">
                        <h4 class="mb-3 font-weight-bold">Badge Progression</h4>
                        <div class="d-flex justify-content-around">
                            <div class="badge-stat text-center">
                                <div class="h2 text-primary"><?= count($earned_badges) ?></div>
                                <div class="text-gray-600">Earned</div>
                            </div>
                            <div class="badge-stat text-center">
                                <div class="h2 text-warning"><?= count($all_badges) - count($earned_badges) ?></div>
                                <div class="text-gray-600">Remaining</div>
                            </div>
                            <div class="badge-stat text-center">
                                <div class="h2 text-info"><?= number_format($user_points) ?></div>
                                <div class="text-gray-600">Points</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Earned Badges Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Earned Badges</h6>
        </div>
        <div class="card-body">
            <?php if (empty($earned_badges)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-award fa-4x text-gray-300 mb-3"></i>
                    <p class="text-gray-600 mb-0">You haven't earned any badges yet.</p>
                    <p class="text-gray-600">Keep contributing to the platform to earn badges!</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($earned_badges as $badge): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 border-left-success shadow-sm">
                                <div class="card-body text-center">
                                    <div class="badge-icon mb-3 d-flex justify-content-center">
                                        <img src="../../assets/img/badges/<?= htmlspecialchars($badge['icon']) ?>"
                                            alt="<?= htmlspecialchars($badge['name']) ?>" width="80" height="80">
                                    </div>
                                    <h5 class="card-title font-weight-bold"><?= htmlspecialchars($badge['name']) ?></h5>
                                    <p class="card-text small text-gray-600"><?= htmlspecialchars($badge['description']) ?></p>
                                    <div class="small text-success">
                                        <i class="fas fa-check-circle me-1"></i> Earned on
                                        <?= date('M d, Y', strtotime($badge['earned_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- All Available Badges Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Available Badges</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($badges as $badge): ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div
                            class="card h-100 <?= $badge['is_earned'] ? 'border-left-success' : 'border-left-danger' ?> shadow-sm">
                            <div class="card-body text-center">
                                <div class="badge-icon mb-3 d-flex justify-content-center">
                                    <img src="../../assets/img/badges/<?= htmlspecialchars($badge['icon']) ?>"
                                        style="opacity: <?= $badge['is_earned'] ? '1' : '0.5' ?>;"
                                        alt="<?= htmlspecialchars($badge['name']) ?>" width="80" height="80">
                                </div>
                                <h5 class="card-title font-weight-bold"><?= htmlspecialchars($badge['name']) ?></h5>
                                <p class="card-text small text-gray-600"><?= htmlspecialchars($badge['description']) ?></p>
                                <?php if ($badge['is_earned']): ?>
                                    <div class="small text-success">
                                        <i class="fas fa-check-circle me-1"></i> Earned on
                                        <?= date('M d, Y', strtotime($badge['earned_at'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="small text-muted">
                                        <?php if ($badge['required_points'] > 0): ?>
                                            <i class="fas fa-lock me-1"></i> Requires
                                            <?= number_format($badge['required_points']) ?> points
                                            <div class="progress mt-2" style="height: 5px;">
                                                <?php $point_progress = min(100, ($user_points / $badge['required_points']) * 100); ?>
                                                <div class="progress-bar" role="progressbar" style="width: <?= $point_progress ?>%"
                                                    aria-valuenow="<?= $point_progress ?>" aria-valuemin="0" aria-valuemax="100">
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <i class="fas fa-lock me-1"></i> Not yet earned
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- How to Earn Badges Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">How to Earn Badges</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="text-center mb-3">
                        <i class="fas fa-upload fa-3x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Upload Resources</h5>
                    <p class="text-center text-gray-600">Share your knowledge by uploading quality resources. The more
                        resources you share, the more badges you earn.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center mb-3">
                        <i class="fas fa-comment-dots fa-3x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Participate Actively</h5>
                    <p class="text-center text-gray-600">Engage with other students by commenting on resources,
                        participating in discussions, and rating content.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="text-center mb-3">
                        <i class="fas fa-star fa-3x text-primary"></i>
                    </div>
                    <h5 class="text-center font-weight-bold">Earn Points</h5>
                    <p class="text-center text-gray-600">Collect points through various activities. Different badges
                        require different point thresholds.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';

/**
 * Function to check and award badges based on user points
 * This needs to run immediately after getting user points
 */
function checkAndAwardBadges($user_id, $user_points, $earned_badge_ids, $all_badges)
{
    try {
        $pdo = getDbConnection();

        foreach ($all_badges as $badge) {
            // Skip if badge already earned
            if (in_array($badge['id'], $earned_badge_ids)) {
                continue;
            }

            // Check if user meets point requirement
            if ($user_points >= $badge['required_points']) {
                // Award badge
                $stmt = $pdo->prepare("
                    INSERT INTO user_badges (user_id, badge_id, earned_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$user_id, $badge['id']]);

                // Create notification
                createNotification(
                    $user_id,
                    "Congratulations! You've earned the " . $badge['name'] . " badge!",
                    "../profile/badges.php"
                );

                // Log the badge award
                logActivity(
                    $user_id,
                    'Badge Earned',
                    "Earned the " . $badge['name'] . " badge"
                );
            }
        }
    } catch (Exception $e) {
        error_log("Error in checkAndAwardBadges: " . $e->getMessage());
    }
}
?>