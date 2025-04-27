<?php
// Common functions for ShaSha CJRS

/**
 * Redirect to a URL with optional message
 *
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Type of message (success or error)
 * @return void
 */
function redirect($url, $message = '', $type = 'success') {
    if(!empty($message)) {
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
    
    header("Location: $url");
    exit;
}

/**
 * Display flash message and clear it from session
 *
 * @return void
 */
function display_message() {
    if(isset($_SESSION['message']) && isset($_SESSION['message_type'])) {
        $message = $_SESSION['message'];
        $type = $_SESSION['message_type'];
        
        // Clear the message from session
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
        
        // Display the message
        echo '<div class="' . $type . '-message">' . $message . '</div>';
    }
}

/**
 * Check if user is logged in
 *
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if user has specific role
 *
 * @param string $role Role to check
 * @return bool
 */
function has_role($role) {
    return is_logged_in() && $_SESSION['role'] === $role;
}

/**
 * Get current user ID
 *
 * @return int|null
 */
function get_user_id() {
    return is_logged_in() ? $_SESSION['user_id'] : null;
}

/**
 * Format date to a readable format
 *
 * @param string $date Date string
 * @param string $format Format to use
 * @return string
 */
function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Sanitize input data
 *
 * @param string $data Data to sanitize
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}