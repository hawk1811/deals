<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות
requireLogin();

// בדיקת CSRF
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = "בקשה לא חוקית";
    header('Location: dashboard.php');
    exit;
}

// קבלת פרטי העסקה
if (!isset($_POST['year']) || !isset($_POST['month']) || !isset($_POST['id'])) {
    header('Location: dashboard.php');
    exit;
}

$year = (int)$_POST['year'];
$month = (int)$_POST['month'];
$transactionId = (int)$_POST['id'];

// טעינת העסקאות
$transactions = loadTransactions($_SESSION['username'], $year, $month, $_SESSION['password']);

// בדיקה אם העסקה קיימת
if (!isset($transactions[$transactionId])) {
    $_SESSION['error'] = "העסקה לא נמצאה";
    header('Location: dashboard.php?year=' . $year);
    exit;
}

// שמירת פרטי העסקה לפני המחיקה לצורך הלוג
$transaction = $transactions[$transactionId];

// רישום בלוג
logAction($_SESSION['username'], "מחיקת עסקה", "לקוח: " . $transaction['client_name'] . ", סכום: " . $transaction['amount'] . "$");

// מחיקת העסקה
array_splice($transactions, $transactionId, 1);

// שמירת הנתונים המעודכנים
saveTransactions($_SESSION['username'], $year, $month, $transactions, $_SESSION['password']);

// חישוב מחדש של כל העמלות לשנה זו
recalculateAllCommissions($_SESSION['username'], $year, $_SESSION['password']);

// הודעת הצלחה והפניה בחזרה ללוח הבקרה
$_SESSION['success'] = "העסקה נמחקה בהצלחה";
header('Location: dashboard.php?year=' . $year);
exit;