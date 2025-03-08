<?php
// בדיקה שהקובץ לא ניגש באופן ישיר
if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

// טעינת נתוני מיסוי
$taxData = loadTaxDataFromFile();
?>

<!-- חלונית לעריכת נתוני מיסוי -->
<div id="tax-data-modal" class="modal" style="display: none;">
    <div class="modal-content modal-lg">
        <span class="close">&times;</span>
        <h2>עריכת נתוני מיסוי</h2>
        
        <form id="tax-data-form" method="post" action="update_tax_data.php">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
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