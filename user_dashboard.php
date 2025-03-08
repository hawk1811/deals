<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות
requireLogin();

// בדיקה אם המשתמש הוא מנהל (אם כן, להפנות לדף המנהל)
if (isAdmin()) {
    header('Location: admin_dashboard.php');
    exit;
}

// קבלת שנה מה-URL, או שימוש בשנה הנוכחית
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// בדיקה אם להציג רק עסקאות רב-שנתיות שלא משולמות מראש
$showOnlyMultiYearNonPrepaid = isset($_GET['filter']) && $_GET['filter'] === 'multi_year_non_prepaid';

// טעינת פרטי המשתמש (כולל כינוי)
$usersFile = 'data/users.json';
$users = loadJsonData($usersFile) ?: [];
$userSettings = isset($users[$_SESSION['username']]) ? $users[$_SESSION['username']] : [];

// טיפול בעדכון הגדרות
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "בקשה לא חוקית";
        header('Location: user_dashboard.php');
        exit;
    }
    
    $yearSettings = [
        'base_salary' => isset($_POST['base_salary']) ? (float)$_POST['base_salary'] : 0,
        'car_allowance' => isset($_POST['car_allowance']) ? (float)$_POST['car_allowance'] : 0,
        'yearly_target' => isset($_POST['yearly_target']) ? (float)$_POST['yearly_target'] : 0,
        'yearly_commission' => isset($_POST['yearly_commission']) ? (float)$_POST['yearly_commission'] : 0,
        'tax_credit_points' => isset($_POST['tax_credit_points']) ? (float)$_POST['tax_credit_points'] : 2.5,
        'pension_rate' => isset($_POST['pension_rate']) ? (float)$_POST['pension_rate'] : 6,
        'hishtalmut_rate' => isset($_POST['hishtalmut_rate']) ? (float)$_POST['hishtalmut_rate'] : 2.5
    ];
    
    saveYearSettings($_SESSION['username'], $year, $yearSettings, $_SESSION['password']);
    
    // רישום פעולת שינוי הגדרות בלוג
    logAction($_SESSION['username'], "עדכון הגדרות", "שנת $year");
    
    // הפניה חזרה לדף
    header('Location: user_dashboard.php?year=' . $year);
    exit;
}

// טיפול בהוספת עסקה חדשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "בקשה לא חוקית";
        header('Location: user_dashboard.php');
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
    
    // חישוב סך העסקאות הנוכחי (לפני הוספת העסקה החדשה)
    $yearlyTransactionsTotal = calculateYearlyTransactionsTotal($_SESSION['username'], $year, $_SESSION['password']);
    
    $newTransaction = [
        'client_name' => $_POST['client_name'],
        'description' => $_POST['description'],
        'duration_years' => $duration_years,
        'duration_months' => $duration_months, // לתאימות לאחור
        'amount' => (float)$_POST['amount'],
        'is_prepaid' => $is_prepaid,
        'date_added' => date('Y-m-d H:i:s'),
        'total_at_add_time' => $yearlyTransactionsTotal
    ];
    
    // חישוב העמלה
    $yearSettings = loadYearSettings($_SESSION['username'], $year, $_SESSION['password']);
    $newTransaction['commission'] = calculateCommission(
        $newTransaction['amount'],
        $yearSettings['yearly_target'],
        $yearSettings['yearly_commission'],
        $yearlyTransactionsTotal,
        $duration_years,
        $is_prepaid,
        $yearlyTransactionsTotal
    );
    
    // טעינת העסקאות הקיימות
    $month = (int)$_POST['transaction_month'];
    $transactions = loadTransactions($_SESSION['username'], $year, $month, $_SESSION['password']);
    
    // הוספת העסקה החדשה
    $transactions[] = $newTransaction;
    
    // שמירת הנתונים
    saveTransactions($_SESSION['username'], $year, $month, $transactions, $_SESSION['password']);
    
    // רישום בלוג
    logAction($_SESSION['username'], "הוספת עסקה חדשה", "לקוח: " . $newTransaction['client_name'] . ", סכום: " . $newTransaction['amount'] . "$");
    
    // הפניה חזרה לדף
    header('Location: user_dashboard.php?year=' . $year . '&success=1');
    exit;
}

// טעינת הגדרות השנה
$yearSettings = loadYearSettings($_SESSION['username'], $year, $_SESSION['password']);

// טעינת נתוני מיסוי לשנה הנוכחית
$taxData = loadTaxDataForYear($year);

// חישוב סך עסקאות TCV (Total Contract Value) לשנה הנוכחית
$yearlyTransactionsTCVTotal = calculateYearlyTransactionsTotal($_SESSION['username'], $year, $_SESSION['password']);

// חישוב סך עסקאות ACV (Annual Contract Value) - שנה ראשונה בלבד
$yearlyTransactionsACVTotal = 0;

// חישוב סך העמלות בפועל לשנה הנוכחית (מסכם את כל העמלות מכל העסקאות)
$yearlyCommissionsTotal = calculateYearlyCommissionsTotal($_SESSION['username'], $year, $_SESSION['password']);

// נאסוף את כל העסקאות לחישוב ACV
$allYearTransactions = [];
for ($month = 1; $month <= 12; $month++) {
    $transactions = loadTransactions($_SESSION['username'], $year, $month, $_SESSION['password']);
    foreach ($transactions as $transaction) {
        $allYearTransactions[] = $transaction;
    }
}

// חישוב ACV
foreach ($allYearTransactions as $transaction) {
    if (isset($transaction['duration_years'])) {
        $duration_years = $transaction['duration_years'];
        $is_prepaid = isset($transaction['is_prepaid']) ? $transaction['is_prepaid'] : true;
        
        if ($duration_years == 1 || $is_prepaid) {
            // עסקאות לשנה אחת או משולמות מראש - מוסיפים את הסכום המלא
            $yearlyTransactionsACVTotal += $transaction['amount'];
        } else {
            // עסקאות רב-שנתיות לא משולמות מראש - רק החלק היחסי לשנה הראשונה
            $yearlyTransactionsACVTotal += ($transaction['amount'] / $duration_years);
        }
    } else {
        // לתמיכה לאחור בעסקאות ישנות
        $yearlyTransactionsACVTotal += $transaction['amount'];
    }
}

// בדיקה אם יש עודף מעל היעד
$aboveTarget = 0;
$regularCommission = 0;
$bonusCommission = 0;

if ($yearlyTransactionsTCVTotal > $yearSettings['yearly_target'] && $yearSettings['yearly_target'] > 0) {
    $aboveTarget = $yearlyTransactionsTCVTotal - $yearSettings['yearly_target'];
}

// הכנת נתונים לכל חודש
$monthsData = [];
for ($month = 12; $month >= 1; $month--) {
    $transactions = loadTransactions($_SESSION['username'], $year, $month, $_SESSION['password']);
    
    // פילטור עסקאות לפי הבקשה
    if ($showOnlyMultiYearNonPrepaid) {
        $filteredTransactions = [];
        foreach ($transactions as $index => $transaction) {
            $duration_years = isset($transaction['duration_years']) ? $transaction['duration_years'] : 1;
            $is_prepaid = isset($transaction['is_prepaid']) ? $transaction['is_prepaid'] : true;
            
            if ($duration_years > 1 && !$is_prepaid) {
                $filteredTransactions[$index] = $transaction;
            }
        }
        $transactions = $filteredTransactions;
    }
    
    // לא להציג חודשים ריקים
    if (empty($transactions)) {
        continue;
    }
    
    $monthlyCommissions = calculateMonthlyCommissionsTotal($transactions);
    
    // חישוב ברוטו ונטו עם הלוגיקה החדשה
    $grossSalary = $yearSettings['base_salary'] + $yearSettings['car_allowance'] + $monthlyCommissions;
    $salaryDetails = calculateNetSalaryNew(
        $yearSettings['base_salary'],
        $yearSettings['car_allowance'],
        $monthlyCommissions,
        $yearSettings,
        $taxData
    );
    
    $monthsData[$month] = [
        'transactions' => $transactions,
        'commissions_total' => $monthlyCommissions,
        'gross_salary' => $grossSalary,
        'net_salary' => $salaryDetails['net_salary'],
        'salary_details' => $salaryDetails
    ];
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
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    <link rel="preload" href="assets/images/deals.png" as="image">
    <title>לוח בקרה - ניהול עסקאות ועמלות</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* רספונסיביות - תיקונים למכשירים ניידים */
        @media (max-width: 768px) {
            /* תיקון למודאל (חלונית קופצת) */
            .modal-content {
                width: 95%;
                max-width: 95%;
                margin: 10px auto;
                padding: 15px;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            /* הגדלת כפתורי סגירה לנוחות שימוש במסך מגע */
            .close {
                font-size: 32px;
                padding: 10px;
            }
            
            /* שיפור מראה טפסים */
            .form-group input, 
            .form-group select,
            .form-group textarea {
                font-size: 16px; /* מניעת זום אוטומטי ב-iOS */
                padding: 12px;
            }
            
            .btn {
                padding: 12px 15px;
                font-size: 16px;
                margin: 5px 0;
            }
            
            /* שיפור מראה סיכום שנתי */
            .summary-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .summary-box {
                width: 100%;
                min-height: auto;
            }
            
            /* שיפור מראה טבלאות */
            .transactions-table {
                font-size: 14px;
            }
            
            .transactions-table th,
            .transactions-table td {
                padding: 8px 5px;
            }
            
            /* להסתיר עמודות פחות חשובות בטלפון */
            .transactions-table th:nth-child(3),  /* תקופה */
            .transactions-table td:nth-child(3),
            .transactions-table th:nth-child(4),  /* תשלום */
            .transactions-table td:nth-child(4) {
                display: none;
            }
            
            /* סידור מחדש של כפתורי פעולה */
            .actions-cell {
                display: flex;
                flex-direction: row;
                justify-content: center;
                gap: 5px;
            }
            
            .actions-cell .btn {
                padding: 8px;
            }
            
            /* שיפור מראה פירוט שכר */
            .salary-summary {
                flex-direction: column;
            }
            
            .salary-box {
                width: 100%;
            }
            
            /* תיקון לתפריט השנה */
            .year-navigation {
                flex-wrap: wrap;
                gap: 10px;
                padding: 10px;
            }
            
            /* שיפור מראה חלוניות פירוט שכר */
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                padding-bottom: 15px;
            }
            
            .detail-value {
                margin-top: 5px;
            }
            
            /* שיפור מראה עמודת פעולות */
            .inline-form {
                display: inline-block;
                margin: 0;
            }
            
            /* שיפור מראה כותרת דף */
            .app-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .header-title h1 {
                font-size: 22px;
            }
            
            /* שיפור מראה כפתורי פעולה בדף */
            .add-transaction-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
            
            .add-transaction-container .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <div class="app-logo">
                <img src="assets/images/deals.png" alt="מערכת ניהול עסקאות ועמלות" class="logo-image">
            </div>
            <div class="header-title">
                <h1>מערכת ניהול עסקאות ועמלות</h1>
            </div>
            <div class="user-info">
                <span>שלום, <?php echo htmlspecialchars($userSettings['alias'] ?? $_SESSION['username']); ?></span>
                <a href="index.php?logout=1" class="logout-button">התנתק</a>
            </div>
        </header>
        
        <div class="year-navigation">
            <a href="?year=<?php echo $year - 1; ?>" class="nav-button"><i class="fas fa-chevron-right"></i> שנה קודמת</a>
            <span class="current-year"><?php echo $year; ?></span>
            <a href="?year=<?php echo $year + 1; ?>" class="nav-button">שנה הבאה <i class="fas fa-chevron-left"></i></a>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">העסקה נוספה בהצלחה!</div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message"><?php echo $_SESSION['success']; ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message"><?php echo $_SESSION['error']; ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Toggle button for settings -->
        <div class="settings-toggle">
            <button id="toggle-settings" class="btn btn-small">
                <i class="fas fa-cog"></i> הגדרות משתמש
            </button>
        </div>
        
        <div id="settings-container" class="settings-container" style="display: none;">
            <h2>הגדרות לשנת <?php echo $year; ?></h2>
            <form method="post" class="settings-form">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="settings-row">
                    <div class="form-group">
                        <label for="base_salary">שכר חודשי בסיס (₪)</label>
                        <input type="number" id="base_salary" name="base_salary" value="<?php echo $yearSettings['base_salary']; ?>" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="car_allowance">החזר רכב (₪)</label>
                        <input type="number" id="car_allowance" name="car_allowance" value="<?php echo $yearSettings['car_allowance']; ?>" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="yearly_target">יעד מכירות שנתי ($)</label>
                        <input type="number" id="yearly_target" name="yearly_target" value="<?php echo $yearSettings['yearly_target']; ?>" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="yearly_commission">עמלה שנתית (₪)</label>
                        <input type="number" id="yearly_commission" name="yearly_commission" value="<?php echo $yearSettings['yearly_commission']; ?>" required min="0">
                    </div>
                </div>
                
                <div class="settings-row">
                    <div class="form-group">
                        <label for="tax_credit_points">נקודות זכות במס הכנסה</label>
                        <input type="number" id="tax_credit_points" name="tax_credit_points" value="<?php echo $yearSettings['tax_credit_points']; ?>" step="0.5" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="pension_rate">הפרשה לפנסיה (%)</label>
                        <input type="number" id="pension_rate" name="pension_rate" value="<?php echo $yearSettings['pension_rate']; ?>" step="0.1" min="0" max="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="hishtalmut_rate">הפרשה לקרן השתלמות (%)</label>
                        <input type="number" id="hishtalmut_rate" name="hishtalmut_rate" value="<?php echo $yearSettings['hishtalmut_rate']; ?>" step="0.1" min="0" max="100">
                    </div>
                </div>
                
                <button type="submit" name="update_settings" class="btn btn-primary">שמור הגדרות</button>
            </form>
        </div>
        
        <div class="year-summary">
            <h2>סיכום שנתי</h2>
            <div class="summary-container">
                <div class="summary-box">
                    <div class="summary-label">סה"כ עסקאות TCV</div>
                    <div class="summary-value"><?php echo formatCurrency($yearlyTransactionsTCVTotal, '$'); ?></div>
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">סה"כ עסקאות ACV</div>
                    <div class="summary-value"><?php echo formatCurrency($yearlyTransactionsACVTotal, '$'); ?></div>
                </div>
                
                <div class="summary-box">
                    <div class="summary-label">יעד שנתי</div>
                    <div class="summary-value"><?php echo formatCurrency($yearSettings['yearly_target'], '$'); ?></div>
                </div>
                
                <div class="summary-box <?php echo ($yearSettings['yearly_target'] > 0 && $yearlyTransactionsACVTotal / $yearSettings['yearly_target'] >= 1) ? 'highlight' : ''; ?>">
                    <div class="summary-label">אחוז מהיעד (ACV)</div>
                    <div class="summary-value"><?php echo ($yearSettings['yearly_target'] > 0 ? round(($yearlyTransactionsACVTotal / $yearSettings['yearly_target']) * 100) : 0); ?>%</div>
                </div>
                
                <?php if ($aboveTarget > 0): ?>
                <div class="summary-box highlight">
                    <div class="summary-label">מעל היעד (בונוס)</div>
                    <div class="summary-value"><?php echo formatCurrency($aboveTarget, '$'); ?></div>
                </div>
                <?php else: ?>
                <div class="summary-box">
                    <div class="summary-label">נותר ליעד</div>
                    <div class="summary-value"><?php echo formatCurrency(max(0, $yearSettings['yearly_target'] - $yearlyTransactionsTCVTotal), '$'); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="summary-box highlight">
                    <div class="summary-label">סה"כ עמלות</div>
                    <div class="summary-value"><?php echo formatCurrency($yearlyCommissionsTotal, '₪'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="add-transaction-container">
            <button id="show-add-transaction" class="btn btn-success">הוסף עסקה חדשה</button>
            
            <?php if ($showOnlyMultiYearNonPrepaid): ?>
                <a href="?year=<?php echo $year; ?>" class="btn btn-warning">הצג את כל העסקאות</a>
            <?php else: ?>
                <a href="?year=<?php echo $year; ?>&filter=multi_year_non_prepaid" class="btn btn-info">הצג רק עסקאות רב-שנתיות ללא תשלום מראש</a>
            <?php endif; ?>
            
            <div id="add-transaction-form" class="modal" style="display: none;">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>הוספת עסקה חדשה</h2>
                    
                    <form method="post" class="transaction-form">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="form-group">
                            <label for="client_name">שם לקוח</label>
                            <input type="text" id="client_name" name="client_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">תיאור העסקה</label>
                            <input type="text" id="description" name="description" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration_years">תקופה</label>
                                <select id="duration_years" name="duration_years" required onchange="togglePrepaidOption()">
                                    <option value="1">שנה אחת</option>
                                    <option value="2">שנתיים</option>
                                    <option value="3">שלוש שנים</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="transaction_month">חודש</label>
                                <select id="transaction_month" name="transaction_month" required>
                                    <?php foreach ($hebrewMonths as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="amount">סכום העסקה ($)</label>
                            <input type="number" id="amount" name="amount" min="0" step="0.01" required>
                        </div>
                        
                        <div class="form-group checkbox-group" id="prepaid_container" style="display: none;">
                            <label for="is_prepaid">משולמת מראש?</label>
                            <select id="is_prepaid" name="is_prepaid">
                                <option value="לא">לא</option>
                                <option value="כן">כן</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_transaction" class="btn btn-primary">הוסף עסקה</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="months-container">
            <?php foreach ($monthsData as $month => $data): ?>
                <div class="month-section">
                    <h2><?php echo $hebrewMonths[$month]; ?> <?php echo $year; ?></h2>
                    
                    <?php if (empty($data['transactions'])): ?>
                        <div class="no-data">אין עסקאות לחודש זה</div>
                    <?php else: ?>
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>לקוח</th>
                                    <th>תיאור</th>
                                    <th>תקופה</th>
                                    <th>תשלום</th>
                                    <th>סכום</th>
                                    <th>עמלה</th>
                                    <th>פעולות</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['transactions'] as $index => $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['client_name']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td>
                                            <?php 
                                            if (isset($transaction['duration_years'])) {
                                                $duration = $transaction['duration_years'];
                                                if ($duration == 1) {
                                                    echo "שנה אחת";
                                                } elseif ($duration == 2) {
                                                    echo "שנתיים";
                                                } elseif ($duration == 3) {
                                                    echo "שלוש שנים";
                                                } else {
                                                    echo $transaction['duration_months'] . " חודשים";
                                                }
                                            } else {
                                                // תאימות לאחור
                                                echo $transaction['duration_months'] . " חודשים";
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo (isset($transaction['is_prepaid']) && $transaction['is_prepaid']) ? 'מראש' : 'שנתי'; ?></td>
                                        <td><?php echo formatCurrency($transaction['amount'], '$'); ?></td>
                                        <td><?php echo formatCurrency($transaction['commission'], '₪'); ?></td>
                                        <td class="actions-cell">
                                            <a href="edit_transaction.php?year=<?php echo $year; ?>&month=<?php echo $month; ?>&id=<?php echo $index; ?>" class="btn btn-small" title="ערוך">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="post" action="delete_transaction.php" class="inline-form" onsubmit="return confirm('האם למחוק את העסקה?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="year" value="<?php echo $year; ?>">
                                                <input type="hidden" name="month" value="<?php echo $month; ?>">
                                                <input type="hidden" name="id" value="<?php echo $index; ?>">
                                                <button type="submit" class="btn btn-small btn-danger" title="מחק">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="summary-row">
                                    <td colspan="5">סה"כ עמלות לחודש:</td>
                                    <td><?php echo formatCurrency($data['commissions_total'], '₪'); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <div class="salary-summary">
                            <div class="salary-box">
                                <div class="salary-label">שכר בסיס</div>
                                <div class="salary-value"><?php echo formatCurrency($yearSettings['base_salary'], '₪'); ?></div>
                            </div>
                            
                            <?php if ($yearSettings['car_allowance'] > 0): ?>
                            <div class="salary-box">
                                <div class="salary-label">החזר רכב</div>
                                <div class="salary-value"><?php echo formatCurrency($yearSettings['car_allowance'], '₪'); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="salary-box">
                                <div class="salary-label">עמלות</div>
                                <div class="salary-value"><?php echo formatCurrency($data['commissions_total'], '₪'); ?></div>
                            </div>
                            
                            <div class="salary-box">
                                <div class="salary-label">ברוטו</div>
                                <div class="salary-value"><?php echo formatCurrency($data['gross_salary'], '₪'); ?></div>
                            </div>
                            
                            <div class="salary-box highlight">
                                <div class="salary-label">נטו</div>
                                <div class="salary-value"><?php echo formatCurrency($data['net_salary'], '₪'); ?></div>
                            </div>
                            
                            <div class="salary-details-button">
                                <button class="btn btn-small show-salary-details" data-month="<?php echo $month; ?>">פירוט ניכויים</button>
                            </div>
                        </div>
                        
                        <div id="salary-details-<?php echo $month; ?>" class="salary-details-modal modal" style="display: none;">
                            <div class="modal-content">
                                <span class="close">&times;</span>
                                <h3>פירוט חישוב שכר - <?php echo $hebrewMonths[$month]; ?> <?php echo $year; ?></h3>
                                
                                <div class="details-section">
                                    <h4>הכנסה</h4>
                                    <div class="detail-row">
                                        <div class="detail-label">שכר בסיס:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['detailed']['base_salary'], '₪'); ?></div>
                                    </div>
                                    <?php if ($yearSettings['car_allowance'] > 0): ?>
                                    <div class="detail-row">
                                        <div class="detail-label">החזר רכב:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['detailed']['car_allowance'], '₪'); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-row">
                                        <div class="detail-label">עמלות:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['detailed']['commissions'], '₪'); ?></div>
                                    </div>
                                    <div class="detail-row highlight">
                                        <div class="detail-label">סה"כ ברוטו:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['gross_salary'], '₪'); ?></div>
                                    </div>
                                </div>
                                
                                <div class="details-section">
                                    <h4>ניכויים</h4>
                                    <div class="detail-row">
                                        <div class="detail-label">מס הכנסה:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['deductions']['income_tax'], '₪'), ' -'; ?></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">נקודות זיכוי (מחושבות בתוך ניכוי המס):</div>
                                        <div class="detail-value"><?php $a = $data['salary_details']['detailed']['tax_credit_points']; $b = $data['salary_details']['detailed']['tax_credit_value']; $result = round($a * $b); echo formatCurrency($result, '₪'), ' +'; ?></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">מס בריאות:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['deductions']['health_tax'], '₪'), ' -'; ?></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">ביטוח לאומי:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['deductions']['social_security'], '₪'), ' -'; ?></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">הפרשת עובד לפנסיה (<?php echo $yearSettings['pension_rate']; ?>%):</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['deductions']['pension'], '₪'), ' -'; ?></div>
                                    </div>
                                    <div class="detail-row">
                                        <div class="detail-label">הפרשת עובד לקרן השתלמות (<?php echo $yearSettings['hishtalmut_rate']; ?>%):</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['deductions']['hishtalmut'], '₪'), ' -'; ?></div>
                                    </div>
                                    <div class="detail-row highlight">
                                        <div class="detail-label">סה"כ ניכויים:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['gross_salary'] - $data['salary_details']['net_salary'], '₪'), ' -'; ?></div>
                                    </div>
                                </div>
                                
                                <div class="net-salary-section highlight">
                                    <div class="detail-row">
                                        <div class="detail-label">תשלום נטו:</div>
                                        <div class="detail-value"><?php echo formatCurrency($data['salary_details']['net_salary'], '₪'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // JavaScript להצגת והסתרת חלוניות
        document.addEventListener('DOMContentLoaded', function() {
            // פונקציה להצגת וסגירת חלוניות
            function setupModal(openButton, modal, closeButton) {
                if (!openButton || !modal) return;
                
                openButton.addEventListener('click', function() {
                    modal.style.display = 'block';
                });
                
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        modal.style.display = 'none';
                    });
                }
                
                window.addEventListener('click', function(event) {
                    if (event.target == modal) {
                        modal.style.display = 'none';
                    }
                });
            }
            
            // הצגה והסתרה של הגדרות משתמש
            const toggleSettingsButton = document.getElementById('toggle-settings');
            const settingsContainer = document.getElementById('settings-container');
            
            if (toggleSettingsButton && settingsContainer) {
                toggleSettingsButton.addEventListener('click', function() {
                    if (settingsContainer.style.display === 'none') {
                        settingsContainer.style.display = 'block';
                        toggleSettingsButton.innerHTML = '<i class="fas fa-times"></i> הסתר הגדרות';
                    } else {
                        settingsContainer.style.display = 'none';
                        toggleSettingsButton.innerHTML = '<i class="fas fa-cog"></i> הגדרות משתמש';
                    }
                });
            }
            
            // טופס הוספת עסקה
            setupModal(
                document.getElementById('show-add-transaction'),
                document.getElementById('add-transaction-form'),
                document.querySelector('#add-transaction-form .close')
            );
            
            // פירוט שכר לכל חודש
            document.querySelectorAll('.show-salary-details').forEach(function(button) {
                var month = button.getAttribute('data-month');
                var modal = document.getElementById('salary-details-' + month);
                var closeButton = modal.querySelector('.close');
                
                setupModal(button, modal, closeButton);
            });
            
            // בדיקה ראשונית של מצב אפשרות התשלום מראש
            togglePrepaidOption();
        });
        
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
    
    <script src="assets/js/main.js"></script>
</body>
</html>