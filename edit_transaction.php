<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות
requireLogin();

// קבלת פרטי העסקה
if (!isset($_GET['year']) || !isset($_GET['month']) || !isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$year = (int)$_GET['year'];
$month = (int)$_GET['month'];
$transactionId = (int)$_GET['id'];

// טעינת העסקאות
$transactions = loadTransactions($_SESSION['username'], $year, $month, $_SESSION['password']);

// בדיקה אם העסקה קיימת
if (!isset($transactions[$transactionId])) {
    $_SESSION['error'] = "העסקה לא נמצאה";
    header('Location: dashboard.php?year=' . $year);
    exit;
}

$transaction = $transactions[$transactionId];

// בדיקה אם יש שדה תקופה בשנים, אחרת המרה מחודשים לשנים
if (!isset($transaction['duration_years'])) {
    $transaction['duration_years'] = max(1, round($transaction['duration_months'] / 12));
}

// טיפול בעדכון העסקה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "בקשה לא חוקית";
        header('Location: dashboard.php?year=' . $year);
        exit;
    }
    
    // המרת תקופה בשנים לחודשים
    $duration_years = (int)$_POST['duration_years'];
    $duration_months = $duration_years * 12;
    
    // האם התשלום מראש
    $is_prepaid = false;
    if ($duration_years > 1) {
        $is_prepaid = isset($_POST['is_prepaid']) && $_POST['is_prepaid'] === 'כן';
    } else {
        $is_prepaid = true; // בעסקה של שנה אחת, התשלום תמיד נחשב מראש
    }
    
    // עדכון נתוני העסקה
    $updatedTransaction = [
        'client_name' => $_POST['client_name'],
        'description' => $_POST['description'],
        'duration_years' => $duration_years,
        'duration_months' => $duration_months, // לתאימות לאחור
        'amount' => (float)$_POST['amount'],
        'is_prepaid' => $is_prepaid,
        'date_added' => $transaction['date_added']
    ];
    
    // שמירת הנתון של סך העסקאות בזמן הוספה, אם קיים
    if (isset($transaction['total_at_add_time'])) {
        $updatedTransaction['total_at_add_time'] = $transaction['total_at_add_time'];
    } else {
        // אם לא קיים, חישוב הסך נוכחי (לא כולל העסקה הנוכחית)
        $totalWithoutCurrent = calculateYearlyTransactionsTotal($_SESSION['username'], $year, $_SESSION['password']) - $transaction['amount'];
        $updatedTransaction['total_at_add_time'] = $totalWithoutCurrent;
    }
    
    // חישוב העמלה
    $yearSettings = loadYearSettings($_SESSION['username'], $year, $_SESSION['password']);
    $updatedTransaction['commission'] = calculateCommission(
        $updatedTransaction['amount'],
        $yearSettings['yearly_target'],
        $yearSettings['yearly_commission'],
        $updatedTransaction['total_at_add_time'],
        $duration_years,
        $is_prepaid,
        $updatedTransaction['total_at_add_time']
    );
    
    // עדכון העסקה
    $transactions[$transactionId] = $updatedTransaction;
    
    // שמירת הנתונים
    saveTransactions($_SESSION['username'], $year, $month, $transactions, $_SESSION['password']);
    
    // רישום בלוג
    logAction($_SESSION['username'], "עדכון עסקה", "לקוח: " . $updatedTransaction['client_name'] . ", סכום: " . $updatedTransaction['amount'] . "$");
    
    // חישוב מחדש של כל העמלות לשנה זו
    recalculateAllCommissions($_SESSION['username'], $year, $_SESSION['password']);
    
    // הודעת הצלחה והפניה בחזרה ללוח הבקרה
    $_SESSION['success'] = "העסקה עודכנה בהצלחה";
    header('Location: dashboard.php?year=' . $year . '&success=1');
    exit;
}

// שמות החודשים בעברית
$hebrewMonths = [
    1 => 'ינואר',
    2 => 'פברואר',
    3 => 'מרץ',
    4 => 'אפריל',
    5 => 'מאי',
    6 => 'יוני',
    7 => 'יולי',
    8 => 'אוגוסט',
    9 => 'ספטמבר',
    10 => 'אוקטובר',
    11 => 'נובמבר',
    12 => 'דצמבר'
];
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עריכת עסקה - ניהול עסקאות ועמלות</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <div class="header-title">
                <h1>עריכת עסקה</h1>
            </div>
            <div class="user-info">
                <span>שלום, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="dashboard.php?year=<?php echo $year; ?>" class="nav-link">חזרה ללוח הבקרה</a>
                <a href="index.php?logout=1" class="logout-button">התנתק</a>
            </div>
        </header>
        
        <div class="edit-transaction-container">
            <h2>עריכת עסקה - <?php echo $hebrewMonths[$month]; ?> <?php echo $year; ?></h2>
            
            <form method="post" class="transaction-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="client_name">שם לקוח</label>
                    <input type="text" id="client_name" name="client_name" value="<?php echo htmlspecialchars($transaction['client_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">תיאור העסקה</label>
                    <input type="text" id="description" name="description" value="<?php echo htmlspecialchars($transaction['description']); ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duration_years">תקופה</label>
                        <select id="duration_years" name="duration_years" required onchange="togglePrepaidOption()">
                            <option value="1" <?php echo ($transaction['duration_years'] == 1) ? 'selected' : ''; ?>>שנה אחת</option>
                            <option value="2" <?php echo ($transaction['duration_years'] == 2) ? 'selected' : ''; ?>>שנתיים</option>
                            <option value="3" <?php echo ($transaction['duration_years'] == 3) ? 'selected' : ''; ?>>שלוש שנים</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">סכום העסקה ($)</label>
                        <input type="number" id="amount" name="amount" min="0" step="0.01" value="<?php echo $transaction['amount']; ?>" required>
                    </div>
                </div>
                
                <div class="form-group checkbox-group" id="prepaid_container" style="display: <?php echo ($transaction['duration_years'] > 1) ? 'block' : 'none'; ?>;">
                    <label for="is_prepaid">משולמת מראש?</label>
                    <select id="is_prepaid" name="is_prepaid">
                        <option value="לא" <?php echo (!isset($transaction['is_prepaid']) || !$transaction['is_prepaid']) ? 'selected' : ''; ?>>לא</option>
                        <option value="כן" <?php echo (isset($transaction['is_prepaid']) && $transaction['is_prepaid']) ? 'selected' : ''; ?>>כן</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_transaction" class="btn btn-primary">עדכן עסקה</button>
                    <a href="dashboard.php?year=<?php echo $year; ?>" class="btn btn-secondary">בטל</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // פונקציה להצגה/הסתרה של אפשרות התשלום מראש
        function togglePrepaidOption() {
            const durationSelect = document.getElementById('duration_years');
            const prepaidContainer = document.getElementById('prepaid_container');
            
            if (durationSelect.value === '1') {
                prepaidContainer.style.display = 'none';
            } else {
                prepaidContainer.style.display = 'block';
            }
        }
    </script>
</body>
</html>