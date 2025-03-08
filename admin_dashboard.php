<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות
requireLogin();

// בדיקה אם המשתמש אינו מנהל (אם כן, להפנות לדף משתמש רגיל)
if (!isAdmin()) {
    header('Location: user_dashboard.php');
    exit;
}

// טעינת פרטי המשתמש (כולל כינוי)
$usersFile = 'data/users.json';
$users = loadJsonData($usersFile) ?: [];
$userSettings = isset($users[$_SESSION['username']]) ? $users[$_SESSION['username']] : [];

// קבלת שנה מה-URL, או שימוש בשנה הנוכחית
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// טעינת כל שנות המס
$taxYears = getAllTaxYears();
sort($taxYears); // מיון השנים בסדר עולה

// טיפול בייצוא הלוגים לCSV
if (isset($_GET['export_logs']) && $_GET['export_logs'] == 1) {
    $csvData = exportLogsToCSV();
    
    if ($csvData) {
        // הגדרת הדפדפן לפורמט CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
        
        // שליחת הנתונים
        echo $csvData;
        exit;
    } else {
        $_SESSION['error'] = "אין נתוני לוג לייצוא";
    }
}

// טעינת יומן הפעולות (לוג מערכת)
$systemLogs = loadSystemLogs();

// טעינת יומן הפעולות (לוג מערכת)
$systemLogs = loadSystemLogs();

// טעינת יומן הפעולות (לוג מערכת)
$systemLogs = loadSystemLogs();

// מספר הפעולות שיוצגו בכל עמוד
$logsPerPage = 25;

// חישוב מספר העמודים
$totalLogs = count($systemLogs);
$totalPages = ceil($totalLogs / $logsPerPage);

// קבלת מספר העמוד הנוכחי
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// חישוב אינדקס התחלתי וסופי של הפעולות להצגה
$startIndex = ($currentPage - 1) * $logsPerPage;
$endIndex = min($startIndex + $logsPerPage - 1, $totalLogs - 1);

// מערך הפעולות להצגה בעמוד הנוכחי
$logsToDisplay = [];
if ($totalLogs > 0) {
    $logsToDisplay = array_slice($systemLogs, $startIndex, $logsPerPage);
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
    <title>לוח בקרה למנהל - ניהול עסקאות ועמלות</title>
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
                <h1>מערכת ניהול עסקאות ועמלות - ממשק מנהל</h1>
            </div>
            <div class="user-info">
                <span>שלום, <?php echo htmlspecialchars($userSettings['alias'] ?? $_SESSION['username']); ?></span>
                <a href="user_management.php" class="nav-link">ניהול משתמשים</a>
                <a href="tax_management.php" class="nav-link">ניהול נתוני מיסוי</a>
                <a href="index.php?logout=1" class="logout-button">התנתק</a>
            </div>
        </header>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message"><?php echo $_SESSION['success']; ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="admin-welcome">
            <div class="info-message">
                <h2>ברוך הבא לממשק ניהול המערכת</h2>
                <p>כמנהל מערכת, באפשרותך לנהל משתמשים ונתוני מס. השתמש בקישורים בתפריט העליון כדי לנווט בין האפשרויות השונות.</p>
                <div class="admin-quick-links">
                    <a href="user_management.php" class="btn">ניהול משתמשים</a>
                    <a href="tax_management.php" class="btn">ניהול נתוני מיסוי</a>
                </div>
            </div>
        </div>
        
        <div class="year-summary">
            <h2>מידע כללי</h2>
            <div class="summary-container">
                <div class="summary-box">
                    <div class="summary-label">מספר המשתמשים במערכת</div>
                    <div class="summary-value"><?php echo count($users) - 1; ?></div>
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">שנות מס מוגדרות</div>
                    <div class="summary-value"><?php echo count($taxYears); ?></div>
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">שנת מס נוכחית</div>
                    <div class="summary-value"><?php echo date('Y'); ?></div>
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">מספר פעולות בלוג</div>
                    <div class="summary-value"><?php echo $totalLogs; ?></div>
                </div>
            </div>
        </div>
        
        <div class="admin-section">
            <div class="admin-card">
                <h2>שנות מס מוגדרות במערכת</h2>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>שנת מס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($taxYears as $taxYear): ?>
                            <tr>
                                <td><?php echo $taxYear; ?></td>
                                <td>
                                    <a href="tax_management.php?year=<?php echo $taxYear; ?>" class="btn btn-small">
                                        <i class="fas fa-edit"></i> ערוך נתוני מס
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions">
                    <a href="tax_management.php" class="btn btn-primary">הוסף שנת מס חדשה</a>
                </div>
            </div>
            
            <div class="admin-card">
                <h2>משתמשים פעילים</h2>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>שם משתמש</th>
                            <th>סטטוס</th>
                            <th>פעולות</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $username => $userDetails): ?>
                            <?php if ($username !== 'admin'): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($username); ?></td>
                                    <td>
                                        <?php if (isset($userDetails['is_first_login']) && $userDetails['is_first_login']): ?>
                                            <span class="status-badge warning">לא הוגדרה סיסמה</span>
                                        <?php else: ?>
                                            <span class="status-badge success">פעיל</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="user_management.php" class="btn btn-small">
                                            <i class="fas fa-user-cog"></i> ניהול משתמש
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="form-actions">
                    <a href="user_management.php" class="btn btn-primary">הוסף משתמש חדש</a>
                </div>
            </div>
        </div>
        
        <div class="admin-card logs-container">
            <h2>יומן פעולות מערכת</h2>
            <?php if (empty($systemLogs)): ?>
                <div class="no-data">אין פעולות בלוג מערכת</div>
            <?php else: ?>
                <div class="export-actions">
                    <a href="?export_logs=1" class="btn btn-primary">
                        <i class="fas fa-file-export"></i> ייצוא לוגים לקובץ CSV
                    </a>
                </div>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>תאריך</th>
                            <th>שעה</th>
                            <th>משתמש</th>
                            <th>פעולה</th>
                            <th>פרטים</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logsToDisplay as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['date']); ?></td>
                                <td><?php echo htmlspecialchars($log['time']); ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['details']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>" class="btn btn-small">הקודם</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn btn-small <?php echo ($i == $currentPage) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>" class="btn btn-small">הבא</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>