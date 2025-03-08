<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות אדמין
requireLogin();
requireAdmin();

// טיפול בפעולות ניהול משתמשים
$success = null;
$error = null;

// הוספת משתמש חדש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = "בקשה לא חוקית";
    } else {
        $username = trim($_POST['username']);
        $alias = trim($_POST['alias']);
        $isAdmin = isset($_POST['is_admin']) ? true : false;
        
        // בדיקת תקינות שם המשתמש
        if (empty($username)) {
            $error = "שם המשתמש לא יכול להיות ריק";
        } elseif (strlen($username) < 3) {
            $error = "שם המשתמש חייב להכיל לפחות 3 תווים";
        } elseif (addUser($username, 'password', $alias, $isAdmin)) {
            $success = "המשתמש $username נוסף בהצלחה עם סיסמת ברירת מחדל";
        } else {
            $error = "שם המשתמש $username כבר קיים במערכת";
        }
    }
}

// מחיקת משתמש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = "בקשה לא חוקית";
    } else {
        $username = $_POST['username'];
        
        if (deleteUser($username)) {
            $success = "המשתמש $username נמחק בהצלחה";
        } else {
            $error = "לא ניתן למחוק את המשתמש $username";
        }
    }
}

// איפוס סיסמה למשתמש
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $error = "בקשה לא חוקית";
    } else {
        $username = $_POST['username'];
        
        if (resetUserPassword($username)) {
            $success = "הסיסמה של המשתמש $username אופסה בהצלחה לסיסמת ברירת המחדל";
        } else {
            $error = "לא ניתן לאפס את הסיסמה של המשתמש $username";
        }
    }
}

// טעינת רשימת המשתמשים
$users = getAllUsers();
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
    <title>ניהול משתמשים - מערכת ניהול עסקאות ועמלות</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <div class="app-logo">
                <img src="assets/images/deals.png" alt="מערכת ניהול עסקאות ועמלות" class="logo-image">
            </div>
            <div class="header-title">
                <h1>ניהול משתמשים</h1>
            </div>
            <div class="user-info">
                <span>מנהל: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="dashboard.php" class="nav-link">חזרה ללוח הבקרה</a>
                <a href="index.php?logout=1" class="logout-button">התנתק</a>
            </div>
        </header>
        
        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="admin-section">
            <div class="admin-card">
                <h2>הוספת משתמש חדש</h2>
                <form method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-group">
                        <label for="username">שם משתמש</label>
                        <input type="text" id="username" name="username" required minlength="3">
                    </div>
                    
                    <div class="form-group">
                        <label for="alias">כינוי להצגה</label>
                        <input type="text" id="alias" name="alias" placeholder="אופציונלי - יוצג במקום שם המשתמש">
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_admin" name="is_admin">
                        <label for="is_admin">הרשאות מנהל</label>
                    </div>
                    
                    <button type="submit" name="add_user" class="btn btn-primary">הוסף משתמש</button>
                </form>
            </div>
            
            <div class="admin-card">
                <h2>ניהול משתמשים קיימים</h2>
                
                <?php if (empty($users) || count($users) <= 1): ?>
                    <div class="no-data">אין משתמשים נוספים מלבד מנהל המערכת</div>
                <?php else: ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>שם משתמש</th>
                                <th>הרשאות</th>
                                <th>סטטוס</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $username => $userDetails): ?>
                                <?php if ($username !== 'admin'): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($username); ?></td>
                                        <td><?php echo $userDetails['is_admin'] ? 'מנהל' : 'משתמש רגיל'; ?></td>
                                        <td>
                                            <?php if ($userDetails['is_first_login']): ?>
                                                <span class="status-badge warning">לא הוגדרה סיסמה</span>
                                            <?php else: ?>
                                                <span class="status-badge success">פעיל</span>
                                            <?php endif; ?>
                                            <div class="user-alias">
                                                כינוי: <?php echo htmlspecialchars($userDetails['alias'] ?? $username); ?>
                                            </div>
                                        </td>
                                        <td class="actions-cell">
                                            <form method="post" action="" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                                <button type="submit" name="reset_password" class="btn btn-small btn-warning" onclick="return confirm('האם לאפס את הסיסמה של <?php echo htmlspecialchars($username); ?>?');">
                                                    <i class="fas fa-key"></i> איפוס סיסמה
                                                </button>
                                            </form>
                                            
                                            <form method="post" action="" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                                <button type="submit" name="delete_user" class="btn btn-small btn-danger" onclick="return confirm('האם למחוק את המשתמש <?php echo htmlspecialchars($username); ?>?');">
                                                    <i class="fas fa-trash-alt"></i> מחיקה
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>