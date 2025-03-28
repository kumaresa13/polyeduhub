<?php
function handleError($errno, $errstr, $errfile, $errline) {
    // Log error details
    error_log("Error [$errno]: $errstr in $errfile on line $errline");

    // Depending on error type, you might want to show different messages
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Error:</strong> $errstr<br>";
        echo "File: $errfile<br>";
        echo "Line: $errline";
        echo "</div>";
    } else {
        // For production, show a generic error message
        echo "<div class='alert alert-danger'>An unexpected error occurred. Please try again later.</div>";
    }

    // Don't execute PHP's internal error handler
    return true;
}

// Set error handler
set_error_handler('handleError');

// Custom exception handler
function handleException($exception) {
    error_log($exception->getMessage());
    
    if (ini_get('display_errors')) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>Exception:</strong> " . $exception->getMessage() . "<br>";
        echo "File: " . $exception->getFile() . "<br>";
        echo "Line: " . $exception->getLine();
        echo "</div>";
    } else {
        echo "<div class='alert alert-danger'>An unexpected error occurred. Please try again later.</div>";
    }
}

// Set exception handler
set_exception_handler('handleException');