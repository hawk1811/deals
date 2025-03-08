<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// בדיקת הרשאות והתחברות
requireLogin();
requireAdmin();

// בדיקת CSRF
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = "בקשה לא חוקית";
    header('Location: tax_management.php');
    exit;
}

// בדיקה שהבקשה הגיעה בשיטת POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tax_management.php');
    exit;
}

// קבלת שנת המס
$taxYear = isset($_POST['tax_year']) ? (int)$_POST['tax_year'] : date('Y');

// הכנת מבנה הנתונים
$taxData = [
    'income_tax_brackets_monthly' => [],
    'tax_credit_point' => [
        'monthly_value' => (float)$_POST['tax_credit_monthly'],
        'annual_value' => (float)$_POST['tax_credit_annual']
    ],
    'national_insurance' => [
        'brackets' => [],
        'max_income_ceiling' => (float)$_POST['social_security_ceiling']
    ],
    'health_insurance' => [
        'brackets' => [],
        'max_income_ceiling' => (float)$_POST['health_insurance_ceiling']
    ],
    'education_fund' => [
        'tax_exempt_ceiling' => (float)$_POST['education_fund_ceiling']
    ]
];

// עיבוד מדרגות מס הכנסה
if (isset($_POST['income_tax_min']) && is_array($_POST['income_tax_min'])) {
    $count = count($_POST['income_tax_min']);
    
    for ($i = 0; $i < $count; $i++) {
        $minIncome = (float)$_POST['income_tax_min'][$i];
        $maxIncome = !empty($_POST['income_tax_max'][$i]) ? (float)$_POST['income_tax_max'][$i] : null;
        $rate = (float)$_POST['income_tax_rate'][$i] / 100; // המרה מאחוזים לערך עשרוני
        
        $taxData['income_tax_brackets_monthly'][] = [
            'bracket' => $i + 1,
            'min_income' => $minIncome,
            'max_income' => $maxIncome,
            'tax_rate' => $rate
        ];
    }
}

// עיבוד מדרגות ביטוח לאומי
if (isset($_POST['social_security_min']) && is_array($_POST['social_security_min'])) {
    $count = count($_POST['social_security_min']);
    
    for ($i = 0; $i < $count; $i++) {
        $minIncome = (float)$_POST['social_security_min'][$i];
        $maxIncome = !empty($_POST['social_security_max'][$i]) ? (float)$_POST['social_security_max'][$i] : null;
        $rate = (float)$_POST['social_security_rate'][$i] / 100;
        
        $taxData['national_insurance']['brackets'][] = [
            'min_income' => $minIncome,
            'max_income' => $maxIncome,
            'rate' => $rate
        ];
    }
}

// עיבוד מדרגות מס בריאות
if (isset($_POST['health_insurance_min']) && is_array($_POST['health_insurance_min'])) {
    $count = count($_POST['health_insurance_min']);
    
    for ($i = 0; $i < $count; $i++) {
        $minIncome = (float)$_POST['health_insurance_min'][$i];
        $maxIncome = !empty($_POST['health_insurance_max'][$i]) ? (float)$_POST['health_insurance_max'][$i] : null;
        $rate = (float)$_POST['health_insurance_rate'][$i] / 100;
        
        $taxData['health_insurance']['brackets'][] = [
            'min_income' => $minIncome,
            'max_income' => $maxIncome,
            'rate' => $rate
        ];
    }
}

// שמירת הנתונים לשנה הספציפית
if (saveTaxDataForYear($taxYear, $taxData)) {
    $_SESSION['success'] = "נתוני המיסוי לשנת $taxYear עודכנו בהצלחה";
    header("Location: tax_management.php?year=$taxYear");
} else {
    $_SESSION['error'] = "שגיאה בשמירת נתוני המיסוי";
    header("Location: tax_management.php?year=$taxYear");
}
exit;