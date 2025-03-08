<?php
// פונקציות עיקריות של המערכת

// פונקציה לבדיקה אם שנת מס קיימת
function taxYearExists($year) {
    $taxFile = "data/tax_years/$year.json";
    return file_exists($taxFile);
}

// קבלת רשימת כל שנות המס הקיימות
function getAllTaxYears() {
    $dataDir = 'data/tax_years';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
        
        // יצירת שנת מס ראשונית (2025)
        $initialYear = 2025;
        createTaxDataForYear($initialYear);
    }
    
    $years = [];
    $files = glob("$dataDir/*.json");
    
    foreach ($files as $file) {
        $filename = basename($file);
        $year = (int)str_replace('.json', '', $filename);
        $years[] = $year;
    }
    
    // אם אין שנים, ניצור את 2025 כברירת מחדל
    if (empty($years)) {
        $initialYear = 2025;
        createTaxDataForYear($initialYear);
        $years[] = $initialYear;
    }
    
    return $years;
}

// יצירת נתוני מס לשנה חדשה
function createTaxDataForYear($year, $baseYear = null) {
    $dataDir = 'data/tax_years';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $taxFile = "$dataDir/$year.json";
    
    // בדיקה אם כבר קיים קובץ לשנה זו
    if (file_exists($taxFile)) {
        return false;
    }
    
    // אם יש שנת בסיס, נעתיק את הנתונים ממנה
    if ($baseYear !== null && taxYearExists($baseYear)) {
        $baseData = loadTaxDataForYear($baseYear);
    } else {
        // אחרת ניצור נתוני ברירת מחדל
        $baseData = getSampleTaxData();
    }
    
    // שמירת הנתונים בקובץ חדש
    $jsonData = json_encode($baseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($taxFile, $jsonData) !== false;
}

// פונקציה לטעינת נתוני מיסוי משנה ספציפית
function loadTaxDataForYear($year) {
    $taxFile = "data/tax_years/$year.json";
    
    if (file_exists($taxFile)) {
        $jsonContent = file_get_contents($taxFile);
        $taxData = json_decode($jsonContent, true);
        
        // וידוא תקינות המבנה
        if (validateTaxData($taxData)) {
            return $taxData;
        }
    }
    
    // במקרה של שגיאה - החזרת נתוני ברירת מחדל
    return getSampleTaxData();
}

// שמירת נתוני מיסוי לשנה ספציפית
function saveTaxDataForYear($year, $taxData) {
    $dataDir = 'data/tax_years';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $taxFile = "$dataDir/$year.json";
    
    // שמירת הנתונים בפורמט JSON מסודר
    $jsonData = json_encode($taxData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($taxFile, $jsonData) !== false;
}

// פונקציה לטעינת נתוני מיסוי מקובץ JSON מקומי
function loadTaxDataFromFile() {
    // נתמך לאחורה - לפני המעבר למערכת שנות מס
    $taxFile = 'data/taxes.json';
    
    if (file_exists($taxFile)) {
        $jsonContent = file_get_contents($taxFile);
        $taxData = json_decode($jsonContent, true);
        
        // וידוא תקינות המבנה
        if (validateTaxData($taxData)) {
            return $taxData;
        }
    }
    
    // במקרה של שגיאה - החזרת נתוני ברירת מחדל
    // ננסה לטעון משנת המס הנוכחית
    $currentYear = date('Y');
    if (taxYearExists($currentYear)) {
        return loadTaxDataForYear($currentYear);
    }
    
    // אם גם זה לא עובד, נחזיר נתוני ברירת מחדל
    return getSampleTaxData();
}

// בדיקת תקינות נתוני המיסוי
function validateTaxData($taxData) {
    // בדיקה בסיסית של מבנה הנתונים
    return isset($taxData['income_tax_brackets_monthly']) && 
           isset($taxData['tax_credit_point']) &&
           isset($taxData['national_insurance']) &&
           isset($taxData['health_insurance']);
}

// שמירת נתוני מיסוי לקובץ
function saveTaxDataToFile($taxData) {
    $taxFile = 'data/taxes.json';
    
    // וידוא שהתיקייה קיימת
    $dir = dirname($taxFile);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // שמירת הנתונים בפורמט JSON מסודר
    $jsonData = json_encode($taxData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($taxFile, $jsonData) !== false;
}

// פונקציה לקבלת נתוני מיסוי לדוגמה (במקום קריאה אמיתית ל-API)
function getSampleTaxData() {
    // נתונים משוערים לשנת 2025
    return [
        'income_tax_brackets_monthly' => [
            [
                'bracket' => 1,
                'min_income' => 0,
                'max_income' => 7010,
                'tax_rate' => 0.1
            ],
            [
                'bracket' => 2,
                'min_income' => 7011,
                'max_income' => 10060,
                'tax_rate' => 0.14
            ],
            [
                'bracket' => 3,
                'min_income' => 10061,
                'max_income' => 16150,
                'tax_rate' => 0.2
            ],
            [
                'bracket' => 4,
                'min_income' => 16151,
                'max_income' => 22440,
                'tax_rate' => 0.31
            ],
            [
                'bracket' => 5,
                'min_income' => 22441,
                'max_income' => 46690,
                'tax_rate' => 0.35
            ],
            [
                'bracket' => 6,
                'min_income' => 46691,
                'max_income' => 60130,
                'tax_rate' => 0.47
            ],
            [
                'bracket' => 7,
                'min_income' => 60131,
                'max_income' => null,
                'tax_rate' => 0.5
            ]
        ],
        'tax_credit_point' => [
            'monthly_value' => 242,
            'annual_value' => 2904
        ],
        'national_insurance' => [
            'brackets' => [
                [
                    'min_income' => 0,
                    'max_income' => 7522,
                    'rate' => 0.0104
                ],
                [
                    'min_income' => 7523,
                    'max_income' => 50695,
                    'rate' => 0.07
                ]
            ],
            'max_income_ceiling' => 50695
        ],
        'health_insurance' => [
            'brackets' => [
                [
                    'min_income' => 0,
                    'max_income' => 7522,
                    'rate' => 0.0323
                ],
                [
                    'min_income' => 7523,
                    'max_income' => 50695,
                    'rate' => 0.0517
                ]
            ],
            'max_income_ceiling' => 50695
        ],
        'education_fund' => [
            'tax_exempt_ceiling' => 15712
        ]
    ];
}

// חישוב מס הכנסה לפי מדרגות
function calculateIncomeTaxBrackets($income, $brackets) {
    $tax = 0;
    
    // מיון המדרגות לפי min_income בסדר עולה
    usort($brackets, function($a, $b) {
        return $a['min_income'] <=> $b['min_income'];
    });
    
    $remainingIncome = $income;
    
    for ($i = 0; $i < count($brackets); $i++) {
        $currentBracket = $brackets[$i];
        $minIncome = $currentBracket['min_income'];
        $maxIncome = $currentBracket['max_income'] ?? PHP_FLOAT_MAX;
        $rate = $currentBracket['tax_rate'];
        
        // חישוב ההכנסה במדרגה הנוכחית
        $incomeInBracket = 0;
        
        if ($i == 0) {
            // מדרגה ראשונה
            $incomeInBracket = min($remainingIncome, $maxIncome);
        } else {
            // מדרגות הבאות
            $incomeInBracket = min($remainingIncome, $maxIncome - $minIncome);
        }
        
        if ($incomeInBracket <= 0) {
            break;
        }
        
        // חישוב המס למדרגה הנוכחית
        $tax += $incomeInBracket * $rate;
        $remainingIncome -= $incomeInBracket;
        
        if ($remainingIncome <= 0) {
            break;
        }
    }
    
    return $tax;
}

// חישוב ביטוח לאומי ומס בריאות לפי מדרגות
function calculateInsuranceTax($income, $brackets, $maxCeiling) {
    $tax = 0;
    $income = min($income, $maxCeiling); // מגביל את ההכנסה לתקרה
    
    // מיון המדרגות לפי min_income בסדר עולה
    usort($brackets, function($a, $b) {
        return $a['min_income'] <=> $b['min_income'];
    });
    
    $remainingIncome = $income;
    
    for ($i = 0; $i < count($brackets); $i++) {
        $currentBracket = $brackets[$i];
        $minIncome = $currentBracket['min_income'];
        $maxIncome = $currentBracket['max_income'] ?? $maxCeiling;
        $rate = $currentBracket['rate'];
        
        // חישוב ההכנסה במדרגה הנוכחית
        $incomeInBracket = 0;
        
        if ($i == 0) {
            // מדרגה ראשונה
            $incomeInBracket = min($remainingIncome, $maxIncome);
        } else {
            // מדרגות הבאות
            $incomeInBracket = min($remainingIncome, $maxIncome - $minIncome + 1);
        }
        
        if ($incomeInBracket <= 0) {
            break;
        }
        
        // חישוב המס למדרגה הנוכחית
        $tax += $incomeInBracket * $rate;
        $remainingIncome -= $incomeInBracket;
        
        if ($remainingIncome <= 0) {
            break;
        }
    }
    
    return $tax;
}

// חישוב שכר נטו המעודכן
function calculateNetSalaryNew($baseSalary, $carAllowance, $commissions, $yearSettings, $taxData) {
    // סך הכנסה ברוטו
    $grossSalary = $baseSalary + $carAllowance + $commissions;
    
    // חישוב מס הכנסה
    $incomeTax = calculateIncomeTaxBrackets(
        $grossSalary, 
        $taxData['income_tax_brackets_monthly']
    );
    
    // הפחתת נקודות זיכוי
    $taxCreditValue = $taxData['tax_credit_point']['monthly_value'];
    $taxCreditPoints = $yearSettings['tax_credit_points'];
    $incomeTax = max(0, $incomeTax - ($taxCreditValue * $taxCreditPoints));
    
    // חישוב ביטוח לאומי
    $socialSecurity = calculateInsuranceTax(
        $grossSalary, 
        $taxData['national_insurance']['brackets'],
        $taxData['national_insurance']['max_income_ceiling']
    );
    
    // חישוב מס בריאות
    $healthTax = calculateInsuranceTax(
        $grossSalary, 
        $taxData['health_insurance']['brackets'],
        $taxData['health_insurance']['max_income_ceiling']
    );
    
    // חישוב הפרשות לפנסיה (רק על השכר הבסיסי)
    $pensionContribution = $baseSalary * ($yearSettings['pension_rate'] / 100);
    
    // חישוב הפרשות לקרן השתלמות (רק על השכר הבסיסי)
    $histalmutMaxExempt = $taxData['education_fund']['tax_exempt_ceiling'];
    $histalmutContribution = min(
        $baseSalary * ($yearSettings['hishtalmut_rate'] / 100),
        $histalmutMaxExempt * ($yearSettings['hishtalmut_rate'] / 100)
    );
    
    // חישוב שכר נטו
    $netSalary = $grossSalary - $incomeTax - $socialSecurity - $healthTax - $pensionContribution - $histalmutContribution;
    
    // הכנת מבנה הנתונים
    $incomeTaxBrackets = [];
    foreach ($taxData['income_tax_brackets_monthly'] as $bracket) {
        $incomeTaxBrackets[] = [
            'bracket' => $bracket['min_income'],
            'rate' => $bracket['tax_rate'] * 100
        ];
    }
    
    return [
        'gross_salary' => $grossSalary,
        'net_salary' => $netSalary,
        'deductions' => [
            'income_tax' => $incomeTax,
            'health_tax' => $healthTax,
            'social_security' => $socialSecurity,
            'pension' => $pensionContribution,
            'hishtalmut' => $histalmutContribution
        ],
        'detailed' => [
            'base_salary' => $baseSalary,
            'car_allowance' => $carAllowance,
            'commissions' => $commissions,
            'tax_credit_points' => $yearSettings['tax_credit_points'],
            'tax_credit_value' => $taxData['tax_credit_point']['monthly_value'],
            'income_tax_brackets' => $incomeTaxBrackets,
            'pension_rate' => $yearSettings['pension_rate'],
            'hishtalmut_rate' => $yearSettings['hishtalmut_rate'],
            'hishtalmut_max_exempt' => $taxData['education_fund']['tax_exempt_ceiling']
        ]
    ];
}

// הצגת פרטי התשלום בפורמט מסודר - ללא אגורות
function formatCurrency($amount, $currency = '₪') {
    return number_format($amount, 0) . ' ' . $currency;
}

// טעינת נתונים בפורמט JSON
function loadJsonData($filename) {
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        return json_decode($content, true);
    }
    return null;
}

// שמירת נתונים בפורמט JSON
function saveJsonData($filename, $data) {
    $dir = dirname($filename);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
}

// קבלת מפתח הצפנה למשתמש
function getUserEncryptionKey($username, $password) {
    return hash('sha256', $username . $password);
}

// הצפנת נתונים
function encryptData($data, $key) {
    $jsonData = json_encode($data);
    $method = 'aes-256-cbc';
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    $encrypted = openssl_encrypt($jsonData, $method, $key, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

// פענוח נתונים
function decryptData($encryptedData, $key) {
    $data = base64_decode($encryptedData);
    
    if ($data === false) {
        // Invalid base64 data
        return false;
    }
    
    if (strpos($data, '::') === false) {
        // Missing separator
        return false;
    }
    
    list($encrypted, $iv) = explode('::', $data, 2);
    $iv = base64_decode($iv);
    
    if ($iv === false) {
        // Invalid IV
        return false;
    }
    
    $method = 'aes-256-cbc';
    $decrypted = openssl_decrypt($encrypted, $method, $key, 0, $iv);
    
    if ($decrypted === false) {
        // Decryption failed
        return false;
    }
    
    return json_decode($decrypted, true);
}

// פונקציה לרישום פעולות בלוג
function logAction($username, $action, $details = '') {
    $dataDir = 'data/logs';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $date = date('d-m-Y');
    $time = date('H:i:s');
    $logFile = "$dataDir/system_log.json";
    
    $logEntry = [
        'date' => $date,
        'time' => $time,
        'username' => $username,
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // קריאת הלוג הקיים
    $logData = [];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $logData = json_decode($content, true) ?: [];
    }
    
    // הוספת הרשומה החדשה
    array_unshift($logData, $logEntry); // הוספה בתחילת המערך
    
    // שמירת הנתונים המעודכנים
    file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// טעינת יומן המערכת
function loadSystemLogs() {
    $logFile = 'data/logs/system_log.json';
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        return json_decode($content, true) ?: [];
    }
    
    return [];
}

// יצוא לוגים לפורמט CSV
function exportLogsToCSV() {
    $logs = loadSystemLogs();
    
    if (empty($logs)) {
        return false;
    }
    
    // הכנת הנתונים בפורמט CSV
    $csvData = [];
    
    // כותרות העמודות
    $csvData[] = ['תאריך', 'שעה', 'משתמש', 'פעולה', 'פרטים', 'IP'];
    
    // נתוני הלוגים
    foreach ($logs as $log) {
        $csvData[] = [
            $log['date'] ?? '',
            $log['time'] ?? '',
            $log['username'] ?? '',
            $log['action'] ?? '',
            $log['details'] ?? '',
            $log['ip'] ?? ''
        ];
    }
    
    // המרה למחרוזת CSV
    $csvString = '';
    foreach ($csvData as $row) {
        // הוספת מירכאות לשדות ובריחה מתווים מיוחדים
        $csvRow = [];
        foreach ($row as $field) {
            $escapedField = str_replace('"', '""', $field);
            $csvRow[] = '"' . $escapedField . '"';
        }
        
        $csvString .= implode(',', $csvRow) . "\n";
    }
    
    // הוספת BOM לתמיכה בעברית ב-Excel
    $csvString = "\xEF\xBB\xBF" . $csvString;
    
    return $csvString;
}

// שמירת נתוני משתמש לשנה מסוימת
function saveYearSettings($username, $year, $settings, $password) {
    $dataDir = "data/users_data/$username";
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $filename = "$dataDir/{$year}_settings.json";
    
    // הצפנת הנתונים
    $key = getUserEncryptionKey($username, $password);
    $encryptedData = encryptData($settings, $key);
    
    // רישום בלוג
    logAction($username, "עדכון הגדרות לשנת $year", "");
    
    // חישוב מחדש של העמלות לכל העסקאות
    recalculateAllCommissions($username, $year, $password);
    
    return file_put_contents($filename, $encryptedData) !== false;
}

// טעינת נתוני משתמש לשנה מסוימת
function loadYearSettings($username, $year, $password) {
    $filename = "data/users_data/$username/{$year}_settings.json";
    
    if (file_exists($filename)) {
        $encryptedData = file_get_contents($filename);
        $key = getUserEncryptionKey($username, $password);
        
        try {
            $data = decryptData($encryptedData, $key);
            if ($data) {
                return $data;
            }
        } catch (Exception $e) {
            // Log error or handle decryption failure
            error_log("Error decrypting data for user $username, year $year: " . $e->getMessage());
        }
    }
    
    // ערכי ברירת מחדל אם אין נתונים או אם פענוח נכשל
    return [
        'base_salary' => 0,
        'car_allowance' => 0,
        'yearly_target' => 0,
        'yearly_commission' => 0,
        'tax_credit_points' => 2.5,
        'pension_rate' => 6,
        'hishtalmut_rate' => 2.5
    ];
}

// שמירת עסקאות לשנה וחודש מסוימים
function saveTransactions($username, $year, $month, $transactions, $password) {
    $dataDir = "data/users_data/$username/$year";
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0755, true);
    }
    
    $filename = "$dataDir/{$month}_transactions.json";
    
    // הצפנת הנתונים
    $key = getUserEncryptionKey($username, $password);
    $encryptedData = encryptData($transactions, $key);
    
    return file_put_contents($filename, $encryptedData) !== false;
}

// טעינת עסקאות לשנה וחודש מסוימים
function loadTransactions($username, $year, $month, $password) {
    $filename = "data/users_data/$username/$year/{$month}_transactions.json";
    
    if (file_exists($filename)) {
        $encryptedData = file_get_contents($filename);
        $key = getUserEncryptionKey($username, $password);
        
        try {
            $data = decryptData($encryptedData, $key);
            if ($data) {
                return $data;
            }
        } catch (Exception $e) {
            // Log error or handle decryption failure
            error_log("Error decrypting transactions for user $username, year $year, month $month: " . $e->getMessage());
        }
    }
    
    return [];
}

// חישוב סך העסקאות לשנה מסוימת
function calculateYearlyTransactionsTotal($username, $year, $password) {
    $total = 0;
    
    for ($month = 1; $month <= 12; $month++) {
        $transactions = loadTransactions($username, $year, $month, $password);
        foreach ($transactions as $transaction) {
            $total += $transaction['amount'];
        }
    }
    
    return $total;
}

// חישוב סך העמלות השנתי הכולל
function calculateYearlyCommissionsTotal($username, $year, $password) {
    $total = 0;
    
    for ($month = 1; $month <= 12; $month++) {
        $transactions = loadTransactions($username, $year, $month, $password);
        foreach ($transactions as $transaction) {
            $total += isset($transaction['commission']) ? $transaction['commission'] : 0;
        }
    }
    
    return $total;
}

// חישוב סך העמלות לחודש מסוים
function calculateMonthlyCommissionsTotal($transactions) {
    $total = 0;
    
    foreach ($transactions as $transaction) {
        $total += isset($transaction['commission']) ? $transaction['commission'] : 0;
    }
    
    return $total;
}

// חישוב עמלות מעסקאות
function calculateCommission($transactionAmount, $yearlyTarget, $yearlyCommission, $totalYearlyTransactions, $duration_years = 1, $is_prepaid = true, $total_at_add_time = 0) {
    // בדיקה לערכי אפס
    if ($yearlyTarget <= 0 || $yearlyCommission <= 0) {
        return 0;
    }
    
    // אם סופקו נתוני זמן הוספה, נשתמש בהם, אחרת נשתמש בסך הנוכחי
    $totalForCalculation = ($total_at_add_time > 0) ? $total_at_add_time : $totalYearlyTransactions;
    
    $commission = 0;
    
    // הגדרת הסכום לחישוב העמלה בהתאם לתקופה ואופן התשלום
    if ($duration_years == 1 || $is_prepaid) {
        // לעסקה לשנה אחת או לעסקה המשולמת מראש - חישוב על הסכום הכולל
        $calculationAmount = $transactionAmount;
        
        // בדיקה אם סך העסקאות השנתי חצה את היעד
        if ($totalForCalculation <= $yearlyTarget) {
            // עדיין לא הגענו ליעד
            $commission = ($calculationAmount / $yearlyTarget) * $yearlyCommission;
        } else {
            // חצינו את היעד - נבדוק אם העסקה הנוכחית גרמה לכך
            $aboveTarget = $totalForCalculation - $yearlyTarget;
            
            if ($totalForCalculation - $calculationAmount >= $yearlyTarget) {
                // כל העסקה הנוכחית היא מעל היעד
                $commission = ($calculationAmount / $yearlyTarget) * $yearlyCommission * 1.5;
            } else {
                // העסקה מתחלקת - חלק עד היעד וחלק מעל היעד
                $partToTarget = $yearlyTarget - ($totalForCalculation - $calculationAmount);
                $partAboveTarget = $calculationAmount - $partToTarget;
                
                $regularCommission = ($partToTarget / $yearlyTarget) * $yearlyCommission;
                $bonusCommission = ($partAboveTarget / $yearlyTarget) * $yearlyCommission * 1.5;
                
                $commission = $regularCommission + $bonusCommission;
            }
        }
    } else {
        // לעסקאות של יותר משנה שאינן משולמות מראש
        // נחלק את העסקה למספר חלקים לפי מספר השנים
        $amountPerYear = $transactionAmount / $duration_years;
        
        // החלק הראשון בעמלה מלאה
        $firstYearCommission = 0;
        
        // בדיקה אם סך העסקאות השנתי חצה את היעד
        if ($totalForCalculation <= $yearlyTarget) {
            // עדיין לא הגענו ליעד
            $firstYearCommission = ($amountPerYear / $yearlyTarget) * $yearlyCommission;
        } else {
            // חצינו את היעד - נבדוק אם העסקה הנוכחית גרמה לכך
            $aboveTarget = $totalForCalculation - $yearlyTarget;
            
            if ($totalForCalculation - $amountPerYear >= $yearlyTarget) {
                // כל העסקה הנוכחית היא מעל היעד
                $firstYearCommission = ($amountPerYear / $yearlyTarget) * $yearlyCommission * 1.5;
            } else {
                // העסקה מתחלקת - חלק עד היעד וחלק מעל היעד
                $partToTarget = $yearlyTarget - ($totalForCalculation - $amountPerYear);
                $partAboveTarget = $amountPerYear - $partToTarget;
                
                $regularCommission = ($partToTarget / $yearlyTarget) * $yearlyCommission;
                $bonusCommission = ($partAboveTarget / $yearlyTarget) * $yearlyCommission * 1.5;
                
                $firstYearCommission = $regularCommission + $bonusCommission;
            }
        }
        
        // שאר החלקים בחצי עמלה
        $remainingYearsCommission = 0;
        if ($duration_years > 1) {
            // החלקים הנוספים מקבלים חצי עמלה
            $remainingAmount = $amountPerYear * ($duration_years - 1);
            $remainingYearsCommission = ($remainingAmount / $yearlyTarget) * $yearlyCommission * 0.5;
        }
        
        $commission = $firstYearCommission + $remainingYearsCommission;
    }
    
    return $commission;
}

// חישוב מחדש של העמלות לכל העסקאות בשנה
function recalculateAllCommissions($username, $year, $password) {
    $yearSettings = loadYearSettings($username, $year, $password);
    $yearlyTarget = $yearSettings['yearly_target'];
    $yearlyCommission = $yearSettings['yearly_commission'];
    
    // נעבור על כל חודשי השנה
    for ($month = 1; $month <= 12; $month++) {
        $transactions = loadTransactions($username, $year, $month, $password);
        $modified = false;
        
        // נעבור על כל עסקה ונחשב מחדש את העמלה
        foreach ($transactions as &$transaction) {
            if (isset($transaction['total_at_add_time'])) {
                // נשתמש בנתוני הסך שנשמרו בזמן הוספת העסקה
                $totalAtAddTime = $transaction['total_at_add_time'];
                
                // המרת תקופה בשנים
                $durationYears = 1;
                if (isset($transaction['duration_years'])) {
                    $durationYears = $transaction['duration_years'];
                } else if (isset($transaction['duration_months'])) {
                    $durationYears = max(1, round($transaction['duration_months'] / 12));
                    // עדכון שדה חדש
                    $transaction['duration_years'] = $durationYears;
                }
                
                // חישוב העמלה מחדש
                $newCommission = calculateCommission(
                    $transaction['amount'],
                    $yearlyTarget,
                    $yearlyCommission,
                    $totalAtAddTime,
                    $durationYears,
                    $transaction['is_prepaid'] ?? true,
                    $totalAtAddTime
                );
                
                // עדכון העמלה אם השתנתה
                if (!isset($transaction['commission']) || $transaction['commission'] != $newCommission) {
                    $transaction['commission'] = $newCommission;
                    $modified = true;
                }
            }
        }
        
        // שמירת הנתונים המעודכנים אם היה שינוי
        if ($modified) {
            saveTransactions($username, $year, $month, $transactions, $password);
            logAction($username, "חישוב מחדש של עמלות", "חודש $month, שנת $year");
        }
    }
}