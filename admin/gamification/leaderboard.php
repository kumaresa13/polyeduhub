<input type="number" class="form-control" id="points_answer" name="points_answer" value="<?= POINTS_ANSWER ?>" min="0">
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" name="update_points" class="btn btn-primary">Update Point System</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Level Thresholds Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Level Thresholds</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th>Points Required</th>
                                    <th>Badge Color</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Level 1</td>
                                    <td>0 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #cd7f32;">Bronze</span></td>
                                </tr>
                                <tr>
                                    <td>Level 2</td>
                                    <td>100 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #cd7f32;">Bronze</span></td>
                                </tr>
                                <tr>
                                    <td>Level 3</td>
                                    <td>500 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #c0c0c0;">Silver</span></td>
                                </tr>
                                <tr>
                                    <td>Level 4</td>
                                    <td>1000 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #c0c0c0;">Silver</span></td>
                                </tr>
                                <tr>
                                    <td>Level 5</td>
                                    <td>5000 points</td>
                                    <td><span class="badge rounded-pill" style="background-color: #ffd700;">Gold</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Manual Point Adjustment Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Manual Point Adjustment</h6>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select Student</label>
                            <select class="form-select" id="user_id" name="user_id" required>
                                <option value="">Select a student</option>
                                <?php foreach ($all_students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> 
                                    (<?= htmlspecialchars($student['email']) ?>) - 
                                    Current Points: <?= number_format($student['points'] ?? 0) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="points" class="form-label">Points to Add/Subtract</label>
                            <input type="number" class="form-control" id="points" name="points" required>
                            <div class="form-text">Use positive number to add points, negative to subtract.</div>
                        </div>
                        <div class="mb-3">
                            <label for="action_description" class="form-label">Action Description</label>
                            <input type="text" class="form-control" id="action_description" name="action_description" required 
                                   placeholder="E.g., Manual Adjustment, Contest Winner">
                        </div>
                        <div class="mb-3">
                            <label for="action_details" class="form-label">Details (Optional)</label>
                            <textarea class="form-control" id="action_details" name="action_details" rows="2"></textarea>
                        </div>
                        <div class="text-center">
                            <button type="submit" name="adjust_points" class="btn btn-primary">Adjust Points</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Top Users Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Top 10 Users by Points</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Student</th>
                                    <th>Level</th>
                                    <th>Points</th>
                                    <th>Badges</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $key => $user): ?>
                                <tr>
                                    <td><?= $key + 1 ?></td>
                                    <td>
                                        <a href="../users/view.php?id=<?= $user['id'] ?>">
                                            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                        </a>
                                        <div class="small text-muted"><?= htmlspecialchars($user['department'] ?: 'N/A') ?></div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-primary">Level <?= $user['level'] ?? 1 ?></span>
                                    </td>
                                    <td><?= number_format($user['points'] ?? 0) ?></td>
                                    <td>
                                        <?= $user['badge_count'] ?> 
                                        <a href="../users/view.php?id=<?= $user['id'] ?>#badges" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-award"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($top_users)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No users found with points</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Point History Card -->
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Point Activity</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Points</th>
                                    <th>Action</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($point_history as $history): ?>
                                <tr>
                                    <td>
                                        <a href="../users/view.php?id=<?= $history['user_id'] ?>">
                                            <?= htmlspecialchars($history['first_name'] . ' ' . $history['last_name']) ?>
                                        </a>
                                    </td>
                                    <td class="<?= $history['points'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $history['points'] > 0 ? '+' : '' ?><?= $history['points'] ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($history['action']) ?>
                                        <?php if ($history['description']): ?>
                                        <span class="d-inline-block" data-bs-toggle="tooltip" title="<?= htmlspecialchars($history['description']) ?>">
                                            <i class="fas fa-info-circle"></i>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y g:i A', strtotime($history['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($point_history)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No point history available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>