<?php
// פונקציות אימות ואבטחה

// בדיקה אם המשתמש מחובר
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

// אימות משתמש
function authenticateUser($username, $password) {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile);
    
    if (!$users) {
        // אם אין קובץ משתמשים, נוסיף משתמש אדמין כברירת מחדל
        $users = [
            'admin' => [
                'password' => password_hash('password', PASSWORD_DEFAULT),
                'is_first_login' => true,
                'is_admin' => true
            ]
        ];
        saveJsonData($usersFile, $users);
    }
    
    if (isset($users[$username])) {
        // בדיקה אם זו הכניסה הראשונה עם סיסמת ברירת מחדל
        if ($users[$username]['is_first_login'] && $password === 'password') {
            // Store the password for encryption/decryption purposes
            $_SESSION['password'] = $password;
            return true;
        }
        
        // בדיקת סיסמה מוצפנת
        if (password_verify($password, $users[$username]['password'])) {
            // Store the password for encryption/decryption purposes
            $_SESSION['password'] = $password;
            return true;
        }
    }
    
    return false;
}

// שינוי סיסמה
function changePassword($username, $newPassword) {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile);
    
    if (isset($users[$username])) {
        // Get old password for re-encryption
        $oldPassword = isset($_SESSION['password']) ? $_SESSION['password'] : 'password';
        
        // Update the password in the session for future encryptions
        $_SESSION['password'] = $newPassword;
        
        // Update user record
        $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $users[$username]['is_first_login'] = false;
        saveJsonData($usersFile, $users);
        
        // Re-encrypt user data with new password
        reencryptUserData($username, $oldPassword, $newPassword);
        
        return true;
    }
    
    return false;
}

// Re-encrypt user data when password changes
function reencryptUserData($username, $oldPassword, $newPassword) {
    // Re-encrypt all year settings
    $dataDir = "data/users_data/$username";
    if (file_exists($dataDir)) {
        $years = [];
        // Get settings files
        $settingsFiles = glob("$dataDir/*_settings.json");
        foreach ($settingsFiles as $file) {
            $filename = basename($file);
            preg_match('/(\d+)_settings\.json$/', $filename, $matches);
            if (isset($matches[1])) {
                $year = (int)$matches[1];
                $years[] = $year;
            }
        }
        
        // Re-encrypt settings for each year
        foreach ($years as $year) {
            // Load with old password
            $settings = loadYearSettings($username, $year, $oldPassword);
            if ($settings) {
                // Save with new password
                saveYearSettings($username, $year, $settings, $newPassword);
            }
        }
        
        // Re-encrypt all transactions
        foreach ($years as $year) {
            $yearDir = "$dataDir/$year";
            if (file_exists($yearDir)) {
                for ($month = 1; $month <= 12; $month++) {
                    $transactions = loadTransactions($username, $year, $month, $oldPassword);
                    if (!empty($transactions)) {
                        saveTransactions($username, $year, $month, $transactions, $newPassword);
                    }
                }
            }
        }
    }
}

// הוספת משתמש חדש
function addUser($username, $password, $alias = '', $isAdmin = false) {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile) ?: [];
    
    // בדיקה אם המשתמש כבר קיים
    if (isset($users[$username])) {
        return false;
    }
    
    $users[$username] = [
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'is_first_login' => true,
        'is_admin' => $isAdmin,
        'alias' => $alias ? $alias : $username // אם אין כינוי, משתמשים בשם המשתמש עצמו
    ];
    
    // Create user data directory
    $userDir = "data/users_data/$username";
    if (!file_exists($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    saveJsonData($usersFile, $users);
    return true;
}

// מחיקת משתמש
function deleteUser($username) {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile) ?: [];
    
    // בדיקה שהמשתמש קיים
    if (!isset($users[$username])) {
        return false;
    }
    
    // מניעת מחיקת משתמש אדמין ראשי
    if ($username === 'admin') {
        return false;
    }
    
    // מחיקת המשתמש
    unset($users[$username]);
    
    saveJsonData($usersFile, $users);
    return true;
}

// איפוס סיסמה למשתמש
function resetUserPassword($username) {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile) ?: [];
    
    // בדיקה שהמשתמש קיים
    if (!isset($users[$username])) {
        return false;
    }
    
    // איפוס הסיסמה ל-'password' וסימון כניסה ראשונה
    $users[$username]['password'] = password_hash('password', PASSWORD_DEFAULT);
    $users[$username]['is_first_login'] = true;
    
    saveJsonData($usersFile, $users);
    return true;
}

// קבלת רשימת כל המשתמשים
function getAllUsers() {
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile) ?: [];
    
    // הסרת סיסמאות מהמידע שמוחזר
    $userList = [];
    foreach ($users as $username => $userDetails) {
        $userList[$username] = [
            'is_admin' => isset($userDetails['is_admin']) ? $userDetails['is_admin'] : false,
            'is_first_login' => isset($userDetails['is_first_login']) ? $userDetails['is_first_login'] : false,
            'alias' => isset($userDetails['alias']) ? $userDetails['alias'] : $username
        ];
    }
    
    return $userList;
}

// בדיקת הרשאות אדמין
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $usersFile = 'data/users.json';
    $users = loadJsonData($usersFile);
    
    if (isset($users[$_SESSION['username']])) {
        return isset($users[$_SESSION['username']]['is_admin']) && 
               $users[$_SESSION['username']]['is_admin'] === true;
    }
    
    return false;
}

// אבטחת הדף - מפנה לדף התחברות אם המשתמש לא מחובר
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

// אבטחת הדף - מפנה לדף הבית אם המשתמש לא מנהל
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

// יצירת טוקן CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// אימות טוקן CSRF
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}