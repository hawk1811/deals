<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות אדמין
requireLogin();
requireAdmin();

// קבלת שנה מה-URL, או שימוש בשנה הנוכחית
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// טיפול בהוספת שנת מס חדשה
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tax_year'])) {
    if (!validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "בקשה לא חוקית";
    } else {
        $newYear = (int)$_POST['new_tax_year'];
        
        // בדיקת תקינות שנה
        if ($newYear < 2020 || $newYear > 2100) {
            $_SESSION['error'] = "נא להזין שנה תקינה בטווח 2020-2100";
        } else {
            // בדיקה אם כבר קיימת שנה כזו
            if (taxYearExists($newYear)) {
                $_SESSION['error'] = "נתוני מס לשנת $newYear כבר קיימים במערכת";
            } else {
                // יצירת נתוני מס לשנה החדשה (העתקה משנה קודמת או יצירה מאפס)
                $baseYear = isset($_POST['base_year']) ? (int)$_POST['base_year'] : date('Y');
                
                if (createTaxDataForYear($newYear, $baseYear)) {
                    $_SESSION['success'] = "נתוני המס לשנת $newYear נוצרו בהצלחה";
                    header("Location: tax_management.php?year=$newYear");
                    exit;
                } else {
                    $_SESSION['error'] = "שגיאה ביצירת נתוני מס לשנת $newYear";
                }
            }
        }
    }
}

// טעינת נתוני מיסוי לשנה הנוכחית
$taxData = loadTaxDataForYear($year);

// קבלת רשימת כל שנות המס הקיימות
$taxYears = getAllTaxYears();
sort($taxYears); // מיון השנים בסדר עולה

// רשימת השנים ללא השנה הנוכחית (לשימוש ב-dropdown של שנת בסיס)
$otherYears = array_filter($taxYears, function($y) use ($year) {
    return $y != $year;
});
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
    <title>ניהול נתוני מיסוי - מערכת ניהול עסקאות ועמלות</title>
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
                <h1>ניהול נתוני מיסוי</h1>
            </div>
            <div class="user-info">
                <span>מנהל: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="dashboard.php" class="nav-link">חזרה ללוח הבקרה</a>
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
        
        <div class="year-navigation">
            <?php foreach ($taxYears as $taxYear): ?>
                <a href="?year=<?php echo $taxYear; ?>" class="nav-button <?php echo ($taxYear == $year) ? 'active' : ''; ?>">
                    <?php echo $taxYear; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <div class="admin-section">
            <div class="admin-card">
                <h2>הוספת שנת מס חדשה</h2>
                <form method="post" action="" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_tax_year">שנת מס חדשה</label>
                            <input type="number" id="new_tax_year" name="new_tax_year" min="2020" max="2100" value="<?php echo date('Y') + 1; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="base_year">העתק נתונים משנה</label>
                            <select id="base_year" name="base_year">
                                <?php foreach ($taxYears as $taxYear): ?>
                                    <option value="<?php echo $taxYear; ?>" <?php echo ($taxYear == $year) ? 'selected' : ''; ?>>
                                        <?php echo $taxYear; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-submit">
                            <button type="submit" name="add_tax_year" class="btn btn-primary">הוסף שנת מס</button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="admin-card">
                <h2>עריכת נתוני מיסוי לשנת <?php echo $year; ?></h2>
                
                <form id="tax-data-form" method="post" action="update_tax_data.php">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="tax_year" value="<?php echo $year; ?>">
                    
                    <div class="tabs">
                        <button type="button" class="tab-button active" data-tab="income-tax">מס הכנסה</button>
                        <button type="button" class="tab-button" data-tab="social-security">ביטוח לאומי</button>
                        <button type="button" class="tab-button" data-tab="health-insurance">מס בריאות</button>
                        <button type="button" class="tab-button" data-tab="other">הגדרות נוספות</button>
                    </div>
                    
                    <div class="tab-content active" id="income-tax-tab">
                        <h3>מדרגות מס הכנסה חודשי</h3>
                        <div class="tax-brackets-container" id="income-tax-brackets">
                            <?php foreach ($taxData['income_tax_brackets_monthly'] as $index => $bracket): ?>
                            <div class="tax-bracket-row">
                                <div class="form-group">
                                    <label>החל מ-</label>
                                    <input type="number" name="income_tax_min[]" value="<?php echo $bracket['min_income']; ?>" min="0" step="1">
                                </div>
                                <div class="form-group">
                                    <label>עד</label>
                                    <input type="number" name="income_tax_max[]" value="<?php echo $bracket['max_income'] ?? ''; ?>" min="0" step="1">
                                    <small>השאר ריק לערך ללא הגבלה</small>
                                </div>
                                <div class="form-group">
                                    <label>שיעור מס %</label>
                                    <input type="number" name="income_tax_rate[]" value="<?php echo $bracket['tax_rate'] * 100; ?>" min="0" max="100" step="0.01">
                                </div>
                                <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-small btn-danger remove-bracket">הסר</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-small" id="add-income-tax-bracket">הוסף מדרגה</button>
                        
                        <div class="form-group">
                            <label>ערך נקודת זיכוי חודשית (₪)</label>
                            <input type="number" name="tax_credit_monthly" value="<?php echo $taxData['tax_credit_point']['monthly_value']; ?>" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>ערך נקודת זיכוי שנתית (₪)</label>
                            <input type="number" name="tax_credit_annual" value="<?php echo $taxData['tax_credit_point']['annual_value']; ?>" min="0" step="0.01">
                        </div>
                    </div>
                    
                    <div class="tab-content" id="social-security-tab">
                        <h3>מדרגות ביטוח לאומי</h3>
                        <div class="tax-brackets-container" id="social-security-brackets">
                            <?php foreach ($taxData['national_insurance']['brackets'] as $index => $bracket): ?>
                            <div class="tax-bracket-row">
                                <div class="form-group">
                                    <label>החל מ-</label>
                                    <input type="number" name="social_security_min[]" value="<?php echo $bracket['min_income']; ?>" min="0" step="1">
                                </div>
                                <div class="form-group">
                                    <label>עד</label>
                                    <input type="number" name="social_security_max[]" value="<?php echo $bracket['max_income'] ?? ''; ?>" min="0" step="1">
                                    <small>השאר ריק לערך ללא הגבלה</small>
                                </div>
                                <div class="form-group">
                                    <label>שיעור %</label>
                                    <input type="number" name="social_security_rate[]" value="<?php echo $bracket['rate'] * 100; ?>" min="0" max="100" step="0.01">
                                </div>
                                <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-small btn-danger remove-bracket">הסר</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-small" id="add-social-security-bracket">הוסף מדרגה</button>
                        
                        <div class="form-group">
                            <label>תקרת הכנסה לביטוח לאומי (₪)</label>
                            <input type="number" name="social_security_ceiling" value="<?php echo $taxData['national_insurance']['max_income_ceiling']; ?>" min="0" step="1">
                        </div>
                    </div>
                    
                    <div class="tab-content" id="health-insurance-tab">
                        <h3>מדרגות מס בריאות</h3>
                        <div class="tax-brackets-container" id="health-insurance-brackets">
                            <?php foreach ($taxData['health_insurance']['brackets'] as $index => $bracket): ?>
                            <div class="tax-bracket-row">
                                <div class="form-group">
                                    <label>החל מ-</label>
                                    <input type="number" name="health_insurance_min[]" value="<?php echo $bracket['min_income']; ?>" min="0" step="1">
                                </div>
                                <div class="form-group">
                                    <label>עד</label>
                                    <input type="number" name="health_insurance_max[]" value="<?php echo $bracket['max_income'] ?? ''; ?>" min="0" step="1">
                                    <small>השאר ריק לערך ללא הגבלה</small>
                                </div>
                                <div class="form-group">
                                    <label>שיעור %</label>
                                    <input type="number" name="health_insurance_rate[]" value="<?php echo $bracket['rate'] * 100; ?>" min="0" max="100" step="0.01">
                                </div>
                                <?php if ($index > 0): ?>
                                <button type="button" class="btn btn-small btn-danger remove-bracket">הסר</button>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-small" id="add-health-insurance-bracket">הוסף מדרגה</button>
                        
                        <div class="form-group">
                            <label>תקרת הכנסה למס בריאות (₪)</label>
                            <input type="number" name="health_insurance_ceiling" value="<?php echo $taxData['health_insurance']['max_income_ceiling']; ?>" min="0" step="1">
                        </div>
                    </div>
                    
                    <div class="tab-content" id="other-tab">
                        <h3>הגדרות נוספות</h3>
                        <div class="form-group">
                            <label>תקרת הפרשה פטורה ממס לקרן השתלמות (₪)</label>
                            <input type="number" name="education_fund_ceiling" value="<?php echo $taxData['education_fund']['tax_exempt_ceiling']; ?>" min="0" step="1">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">שמור שינויים</button>
                        <button type="button" class="btn btn-secondary" id="cancel-tax-edit">בטל</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="assets/js/tax_editor.js"></script>
</body>
</html>