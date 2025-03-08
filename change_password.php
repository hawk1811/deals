<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקה אם יש התחברות זמנית לשינוי סיסמה
if (!isset($_SESSION['change_password']) || !isset($_SESSION['temp_username'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['temp_username'];
$error = null;
$success = false;

// טיפול בטופס שינוי סיסמה
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_SESSION['password'] ?? 'password';
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // בדיקת תקינות הסיסמה
    if (strlen($newPassword) < 8) {
        $error = "הסיסמה חייבת להכיל לפחות 8 תווים";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "הסיסמאות אינן תואמות";
    } else {
        // עדכון הסיסמה
        if (changePassword($username, $newPassword)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $newPassword; // Update the password in session
            
            // רישום בלוג
            logAction($username, "שינוי סיסמה", "הסיסמה עודכנה בהצלחה");
            
            // מחיקת נתוני ההתחברות הזמניים
            unset($_SESSION['change_password']);
            unset($_SESSION['temp_username']);
            
            $success = true;
        } else {
            $error = "שגיאה בעדכון הסיסמה";
            
            // רישום בלוג
            logAction($username, "שגיאה בשינוי סיסמה", $error);
        }
    }
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="preload" href="assets/images/deals.png" as="image">
    <title>שינוי סיסמה - מערכת ניהול עסקאות ועמלות</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <img src="assets/images/deals.png" alt="מערכת ניהול עסקאות ועמלות" class="logo-image-large">
            </div>
            <h1>שינוי סיסמה</h1>
            <p class="info-message">זוהי הכניסה הראשונה שלך למערכת. עליך לשנות את סיסמת ברירת המחדל.</p>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">הסיסמה שונתה בהצלחה!</div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                </script>
            <?php else: ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="new_password">סיסמה חדשה</label>
                        <input type="password" id="new_password" name="new_password" required minlength="8">
                        <small>הסיסמה חייבת להכיל לפחות 8 תווים</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">אימות סיסמה</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">שנה סיסמה</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>