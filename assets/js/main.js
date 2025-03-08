/**
 * קובץ JavaScript ראשי למערכת ניהול עסקאות ועמלות
 */

document.addEventListener('DOMContentLoaded', function() {
    // התאמת גובה החלונית הקופצת כאשר התוכן ארוך
    function adjustModalHeight() {
        const modals = document.querySelectorAll('.modal');
        
        modals.forEach(modal => {
            const modalContent = modal.querySelector('.modal-content');
            if (modalContent) {
                const windowHeight = window.innerHeight;
                const contentHeight = modalContent.offsetHeight;
                
                if (contentHeight > windowHeight * 0.8) {
                    modalContent.style.height = (windowHeight * 0.8) + 'px';
                    modalContent.style.overflowY = 'auto';
                } else {
                    modalContent.style.height = 'auto';
                    modalContent.style.overflowY = 'visible';
                }
            }
        });
    }
    
    // אימות שדות הטופס
    function validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });
        
        // בדיקות נוספות ניתן להוסיף כאן...
        
        return isValid;
    }
    
    // הגדרת אירועים לטפסים
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                alert('נא למלא את כל השדות החובה');
            }
        });
    });
    
    // חישוב דינמי של עמלה בזמן הזנת עסקה חדשה
    const amountInput = document.getElementById('amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            calculateEstimatedCommission();
        });
    }
    
    function calculateEstimatedCommission() {
        const amountInput = document.getElementById('amount');
        const yearlyTargetElement = document.getElementById('yearly_target');
        const yearlyCommissionElement = document.getElementById('yearly_commission');
        
        if (amountInput && yearlyTargetElement && yearlyCommissionElement) {
            const amount = parseFloat(amountInput.value) || 0;
            const yearlyTarget = parseFloat(yearlyTargetElement.value) || 1;
            const yearlyCommission = parseFloat(yearlyCommissionElement.value) || 0;
            
            // חישוב פשוט של עמלה משוערת (ללא התחשבות בעסקאות קיימות)
            const estimatedCommission = (amount / yearlyTarget) * yearlyCommission;
            
            // הצגת העמלה המשוערת (אם יש אלמנט מתאים)
            const estimatedCommissionElement = document.getElementById('estimated_commission');
            if (estimatedCommissionElement) {
                estimatedCommissionElement.textContent = estimatedCommission.toFixed(2) + ' ₪';
            }
        }
    }
    
    // הוספת אירוע לחישוב עמלה משוערת בזמן טעינת הדף
    if (amountInput) {
        calculateEstimatedCommission();
    }
    
    // הוספת פונקציונליות לחלוניות
    window.addEventListener('resize', adjustModalHeight);
    
    // קריאה ראשונית לכוונון גובה החלוניות
    adjustModalHeight();
    
    // הוספת אפקט למעבר עכבר על שורות בטבלה
    const tableRows = document.querySelectorAll('.transactions-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.classList.add('hover');
        });
        
        row.addEventListener('mouseleave', function() {
            this.classList.remove('hover');
        });
    });
    
    // סינון עסקאות לפי שם לקוח (אם יש שדה חיפוש)
    const searchInput = document.getElementById('search_client');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.transactions-table tbody tr');
            
            rows.forEach(row => {
                const clientName = row.querySelector('td:first-child').textContent.toLowerCase();
                if (clientName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});