<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקה אם המשתמש מחובר
$isLoggedIn = isLoggedIn();

// טיפול בבקשות התחברות והתנתקות
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (authenticateUser($username, $password)) {
        // בדיקה אם זו כניסה ראשונה (סיסמה היא 'password')
        if ($password === 'password') {
            $_SESSION['change_password'] = true;
            $_SESSION['temp_username'] = $username;
            $_SESSION['password'] = $password; // Store password for encryption/decryption
            
            // רישום בלוג
            logAction($username, "התחברות ראשונית", "הפניה לשינוי סיסמה");
            
            header('Location: change_password.php');
            exit;
        }
        
        $_SESSION['username'] = $username;
        $_SESSION['logged_in'] = true;
        $_SESSION['password'] = $password; // Store password for encryption/decryption
        
        // רישום בלוג
        logAction($username, "התחברות למערכת", "");
        
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "שם משתמש או סיסמה שגויים";
        
        // רישום בלוג של ניסיון התחברות כושל
        logAction($username, "ניסיון התחברות כושל", "שם משתמש או סיסמה שגויים");
    }
}

if (isset($_GET['logout'])) {
    // רישום בלוג לפני הניתוק
    if (isset($_SESSION['username'])) {
        logAction($_SESSION['username'], "התנתקות מהמערכת", "");
    }
    
    // Clear session
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

// הגדרת השנה הנוכחית כברירת מחדל
$currentYear = date('Y');
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="preload" href="assets/images/deals.png" as="image">
    <title>מערכת ניהול עסקאות ועמלות</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn): ?>
            <div class="login-container">
                <div class="login-logo">
                    <img src="assets/images/deals.png" alt="מערכת ניהול עסקאות ועמלות" class="logo-image-large">
                </div>
                <h1>מערכת ניהול עסקאות ועמלות</h1>
                <form method="post" action="">
                    <?php if (isset($error)): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="username">שם משתמש</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">סיסמה</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary">התחבר</button>
                </form>
            </div>
        <?php else: ?>
            <script>
                window.location.href = 'dashboard.php';
            </script>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>