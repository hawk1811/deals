// JavaScript לטיפול בעורך נתוני המיסוי
document.addEventListener('DOMContentLoaded', function() {
    // טיפול בלשוניות
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // הסרת המצב פעיל מכל הלשוניות
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // הגדרת הלשונית הנוכחית כפעילה
            const tabId = this.getAttribute('data-tab') + '-tab';
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // הוספת מדרגת מס הכנסה
    document.getElementById('add-income-tax-bracket').addEventListener('click', function() {
        const container = document.getElementById('income-tax-brackets');
        const newRow = createBracketRow('income_tax');
        container.appendChild(newRow);
    });
    
    // הוספת מדרגת ביטוח לאומי
    document.getElementById('add-social-security-bracket').addEventListener('click', function() {
        const container = document.getElementById('social-security-brackets');
        const newRow = createBracketRow('social_security');
        container.appendChild(newRow);
    });
    
    // הוספת מדרגת מס בריאות
    document.getElementById('add-health-insurance-bracket').addEventListener('click', function() {
        const container = document.getElementById('health-insurance-brackets');
        const newRow = createBracketRow('health_insurance');
        container.appendChild(newRow);
    });
    
    // הוספת מאזין לכפתורי הסרה
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('remove-bracket')) {
            const row = event.target.closest('.tax-bracket-row');
            if (row) {
                row.remove();
            }
        }
    });
    
    // כפתור ביטול
    document.getElementById('cancel-tax-edit').addEventListener('click', function() {
        document.getElementById('tax-data-modal').style.display = 'none';
    });
    
    // פונקציה ליצירת שורת מדרגת מס חדשה
    function createBracketRow(type) {
        const row = document.createElement('div');
        row.className = 'tax-bracket-row';
        
        row.innerHTML = `
            <div class="form-group">
                <label>החל מ-</label>
                <input type="number" name="${type}_min[]" value="0" min="0" step="1">
            </div>
            <div class="form-group">
                <label>עד</label>
                <input type="number" name="${type}_max[]" value="" min="0" step="1">
                <small>השאר ריק לערך ללא הגבלה</small>
            </div>
            <div class="form-group">
                <label>שיעור %</label>
                <input type="number" name="${type}_rate[]" value="0" min="0" max="100" step="0.01">
            </div>
            <button type="button" class="btn btn-small btn-danger remove-bracket">הסר</button>
        `;
        
        return row;
    }
});