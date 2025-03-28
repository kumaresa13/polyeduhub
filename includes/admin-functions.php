<?php
/**
 * Admin Helper Functions
 * Place this file in: polyeduhub/admin/includes/admin-functions.php
 */

/**
 * Get color for charts based on index
 * @param int $index Index in the chart data series
 * @param bool $hover Whether to return the hover version of the color
 * @return string Color in hexadecimal or rgba format
 */
function getChartColor($index, $hover = false) {
    // Chart color palette
    $colors = [
        ['#4e73df', '#2e59d9'], // primary
        ['#1cc88a', '#17a673'], // success
        ['#36b9cc', '#2c9faf'], // info
        ['#f6c23e', '#dda20a'], // warning
        ['#e74a3b', '#be2617'], // danger
        ['#858796', '#6b6d7d'], // secondary
        ['#5a5c69', '#373840'], // dark
        ['#4e73df', '#2e59d9'], // repeat primary with slight variation
        ['#1cc88a', '#17a673'], // repeat success with slight variation
        ['#36b9cc', '#2c9faf']  // repeat info with slight variation
    ];
    
    // Get color safely (handles more items than colors in the palette)
    $colorIndex = $index % count($colors);
    return $colors[$colorIndex][$hover ? 1 : 0];
}

/**
 * Format large numbers with suffixes (K, M, B)
 * @param int $number The number to format
 * @return string Formatted number (e.g. 1.5K, 2.3M)
 */
function formatNumber($number) {
    if ($number < 1000) {
        return $number;
    } elseif ($number < 1000000) {
        return round($number / 1000, 1) . 'K';
    } elseif ($number < 1000000000) {
        return round($number / 1000000, 1) . 'M';
    } else {
        return round($number / 1000000000, 1) . 'B';
    }
}

/**
 * Generate a secure token for CSRF protection
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token from form submission
 * @param string $token Token from form
 * @return bool Whether the token is valid
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format file size in human-readable format
 * @param int $bytes File size in bytes
 * @param int $precision Decimal precision for result
 * @return string Formatted file size (e.g. 2.5 MB)
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Get status badge HTML for resources
 * @param string $status Status value (approved, pending, rejected)
 * @return string HTML for status badge
 */
function getStatusBadge($status) {
    switch (strtolower($status)) {
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

/**
 * Create pagination links
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $urlPattern URL pattern with %d as page placeholder
 * @return string HTML for pagination
 */
function createPagination($currentPage, $totalPages, $urlPattern) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $currentPage - 1) . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }
    
    // Determine start and end page numbers to display
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    // Adjust if we're at the beginning or end
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, 1) . '">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    // Page numbers
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Add ellipsis and last page if needed
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $totalPages) . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($urlPattern, $currentPage + 1) . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Log admin action
 * @param int $admin_id Admin user ID
 * @param string $action Action performed
 * @param string $details Additional details about the action
 * @return bool Whether the action was logged successfully
 */
function logAdminAction($admin_id, $action, $details = '') {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $admin_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR']
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}

/**
 * Get category options for select dropdowns
 * @param int $selected_id Currently selected category ID
 * @return string HTML options for select dropdown
 */
function getCategoryOptions($selected_id = null) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, name FROM resource_categories ORDER BY name");
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        $html = '';
        foreach ($categories as $category) {
            $selected = ($selected_id == $category['id']) ? 'selected' : '';
            $html .= '<option value="' . $category['id'] . '" ' . $selected . '>' . htmlspecialchars($category['name']) . '</option>';
        }
        return $html;
    } catch (Exception $e) {
        error_log("Failed to get category options: " . $e->getMessage());
        return '';
    }
}

/**
 * Check if admin has necessary permission for an action
 * @param string $permission Permission to check
 * @return bool Whether admin has permission
 */
function adminHasPermission($permission) {
    // For simplicity, all admins have all permissions in this implementation
    // You can expand this to check specific permissions in a permissions table
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}