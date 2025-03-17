<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Student Dashboard' ?> - <?= APP_NAME ?></title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom Student CSS -->
    <link rel="stylesheet" href="../assets/css/student.css">
    
    <!-- Additional Page-Specific Styles -->
    <?= $additional_styles ?? '' ?>
</head>
<body class="student-layout"><?php
    // Check student authentication
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
        header("Location: ../login.php");
        exit();
    }
?>