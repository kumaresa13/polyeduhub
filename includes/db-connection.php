<?php
// Place this file in: polyeduhub/includes/db-connection.php

// Database configuration using constants to avoid variable conflicts
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'polyeduhub');

// Function to get a database connection
function getDbConnection()
{
    // Use constants instead of global variables
    $servername = DB_SERVER;
    $username = DB_USERNAME;
    $password = DB_PASSWORD;
    $dbname = DB_NAME;

    static $pdo = null;

    // If we already have a connection, return it
    if ($pdo !== null) {
        return $pdo;
    }

    try {
        // First try to connect to the database server
        $dsn = "mysql:host=$servername;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Check if the database exists
        $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
        $dbExists = $stmt->fetchColumn();

        if (!$dbExists) {
            // Create the database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Connect to the specific database
        $pdo->exec("USE `$dbname`");

        // Check if essential tables exist, if not create them
        createTablesIfNotExist($pdo);

        return $pdo;

    } catch (PDOException $e) {
        // Log the error but don't stop execution
        error_log("Database Connection Error: " . $e->getMessage());

        // Return null to indicate connection failure
        return null;
    }
}

// Create database tables if they don't exist
function createTablesIfNotExist($pdo)
{

    // Add resource_favorites table if it doesn't exist
    $result = $pdo->query("SHOW TABLES LIKE 'resource_favorites'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `resource_favorites` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `resource_id` int(11) NOT NULL,
        `user_id` int(11) NOT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `resource_user` (`resource_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    // Check if users table exists
    $result = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($result->rowCount() == 0) {
        // Create users table
        $pdo->exec("CREATE TABLE `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `first_name` varchar(50) NOT NULL,
            `last_name` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL UNIQUE,
            `password` varchar(255) NOT NULL,
            `role` enum('student', 'admin') NOT NULL DEFAULT 'student',
            `department` varchar(100) NULL,
            `student_id` varchar(20) NULL,
            `year_of_study` int(1) NULL,
            `profile_image` varchar(255) NULL,
            `bio` text NULL,
            `status` enum('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
            `last_login` datetime NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Create admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (first_name, last_name, email, password, role) 
                    VALUES ('Admin', 'User', 'admin@polyeduhub.com', '$hashedPassword', 'admin')");
    }

    // Check if resource_categories table exists
    $result = $pdo->query("SHOW TABLES LIKE 'resource_categories'");
    if ($result->rowCount() == 0) {
        // Create resource_categories table
        $pdo->exec("CREATE TABLE `resource_categories` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `description` text NULL,
            `parent_id` int(11) NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Insert default categories
        $pdo->exec("INSERT INTO resource_categories (name, description) VALUES 
            ('Notes', 'Study notes and lecture materials'),
            ('Assignments', 'Assignment materials and examples'),
            ('Activities', 'Learning activities and exercises'),
            ('Projects', 'Project documentation and reports'),
            ('Exams', 'Past year exam papers and solutions'),
            ('Tutorials', 'Tutorial materials and guides')");
    }

    // Check if resources table exists
    $result = $pdo->query("SHOW TABLES LIKE 'resources'");
    if ($result->rowCount() == 0) {
        // Create resources table
        $pdo->exec("CREATE TABLE `resources` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `description` text NULL,
            `file_path` varchar(255) NOT NULL,
            `file_type` varchar(50) NOT NULL,
            `file_size` int(11) NOT NULL,
            `thumbnail` varchar(255) NULL,
            `category_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            `download_count` int(11) NOT NULL DEFAULT 0,
            `view_count` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Check if user_points table exists
    $result = $pdo->query("SHOW TABLES LIKE 'user_points'");
    if ($result->rowCount() == 0) {
        // Create user_points table
        $pdo->exec("CREATE TABLE `user_points` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `points` int(11) NOT NULL DEFAULT 0,
            `level` int(11) NOT NULL DEFAULT 1,
            `last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Check if resource_downloads table exists
    $result = $pdo->query("SHOW TABLES LIKE 'resource_downloads'");
    if ($result->rowCount() == 0) {
        // Create resource_downloads table
        $pdo->exec("CREATE TABLE `resource_downloads` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `resource_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `downloaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Add chat rooms table if it doesn't exist
    $result = $pdo->query("SHOW TABLES LIKE 'chat_rooms'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `chat_rooms` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `description` text NULL,
            `created_by` int(11) NOT NULL,
            `is_private` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` datetime NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Add chat messages table if it doesn't exist
    $result = $pdo->query("SHOW TABLES LIKE 'chat_messages'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `chat_messages` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `room_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `message` text NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Add chat room users (for tracking who is in which room)
    $result = $pdo->query("SHOW TABLES LIKE 'chat_room_users'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `chat_room_users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `room_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_read` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `room_user` (`room_id`, `user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Add badges table
    $result = $pdo->query("SHOW TABLES LIKE 'badges'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `badges` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `description` text NOT NULL,
            `icon` varchar(255) NOT NULL,
            `points_required` int(11) NOT NULL DEFAULT 0,
            `uploads_required` int(11) NOT NULL DEFAULT 0,
            `downloads_required` int(11) NOT NULL DEFAULT 0,
            `comments_required` int(11) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add default badges
        $pdo->exec("INSERT INTO badges (name, description, icon, points_required) VALUES
            ('Newbie', 'Just joined the platform', 'badge-newbie.png', 0),
            ('Resource Contributor', 'Shared 5 resources', 'badge-contributor.png', 50),
            ('Knowledge Seeker', 'Downloaded 10 resources', 'badge-seeker.png', 100),
            ('Super Sharer', 'Shared 20 resources', 'badge-super.png', 200),
            ('Polytech Star', 'Earned 500 points', 'badge-star.png', 500)");
    }

    // Add user_badges table (to track which badges users have earned)
    $result = $pdo->query("SHOW TABLES LIKE 'user_badges'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `user_badges` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `badge_id` int(11) NOT NULL,
            `earned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `user_badge` (`user_id`, `badge_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Add activity_log table for tracking user actions
    $result = $pdo->query("SHOW TABLES LIKE 'activity_log'");
    if ($result->rowCount() == 0) {
        $pdo->exec("CREATE TABLE `activity_log` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `action` varchar(100) NOT NULL,
            `details` text NULL,
            `ip_address` varchar(45) NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

// For backward compatibility with the mysqli connection
$conn = null;
try {
    // Create a mysqli connection for old code that might use it
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Check connection
    if ($conn->connect_error) {
        error_log("MySQLi Connection Error: " . $conn->connect_error);
        $conn = null; // Set to null on error
    }
} catch (Exception $e) {
    error_log("MySQLi Connection Exception: " . $e->getMessage());
    $conn = null; // Set to null on error
}

// Helper function to sanitize input data
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to run SQL queries with PDO
function dbSelect($sql, $params = [])
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return []; // Return empty array if connection fails
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("SQL Query Error: " . $e->getMessage());
        return [];
    }
}

// Execute an insert and return the last inserted ID
function dbInsert($table, $data)
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return 0; // Return 0 if connection fails
    }

    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Insert Error: " . $e->getMessage());
        return 0;
    }
}

// Execute an update query
function dbUpdate($table, $data, $where, $whereParams = [])
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return 0; // Return 0 if connection fails
    }

    try {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "$column = ?";
        }

        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";

        $params = array_merge(array_values($data), $whereParams);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Update Error: " . $e->getMessage());
        return 0;
    }
}

// Execute a delete query
function dbDelete($table, $where, $params = [])
{
    $pdo = getDbConnection();
    if ($pdo === null) {
        return 0; // Return 0 if connection fails
    }

    try {
        $sql = "DELETE FROM $table WHERE $where";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Delete Error: " . $e->getMessage());
        return 0;
    }
}