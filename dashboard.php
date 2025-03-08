<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות
requireLogin();

// בדיקה אם המשתמש הוא מנהל
if (isAdmin()) {
    header('Location: admin_dashboard.php');
    exit;
} else {
    header('Location: user_dashboard.php');
    exit;
}
?>