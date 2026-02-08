<?php
/**
 * Authentication Helper Functions
 * Handles login verification and session management
 */

/**
 * Check if user is logged in, redirect to login if not
 */
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Check if user is logged in (returns boolean)
 */
function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get current admin user ID
 */
function getAdminId() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Get current admin username
 */
function getAdminUsername() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['admin_username'] ?? null;
}
