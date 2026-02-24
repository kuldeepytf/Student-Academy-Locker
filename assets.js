// ============================================
// DARK MODE TOGGLE - Enhanced
// ============================================
const darkModeToggle = document.getElementById('darkModeToggle');
if (darkModeToggle) {
    // Check saved preference
    const darkMode = localStorage.getItem('darkMode');
    const isDarkMode = darkMode === 'enabled';
    
    // Apply initial state
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        updateDarkModeButton(true);
    } else {
        updateDarkModeButton(false);
    }

    // Toggle dark mode
    darkModeToggle.addEventListener('click', () => {
        const isNowDark = !document.body.classList.contains('dark-mode');
        document.body.classList.toggle('dark-mode');
        
        if (isNowDark) {
            localStorage.setItem('darkMode', 'enabled');
            updateDarkModeButton(true);
            showNotification('🌙 Dark mode activated', 'success');
        } else {
            localStorage.setItem('darkMode', 'disabled');
            updateDarkModeButton(false);
            showNotification('☀️ Light mode activated', 'success');
        }
    });
}

function updateDarkModeButton(isDark) {
    if (!darkModeToggle) return;
    
    if (isDark) {
        darkModeToggle.innerHTML = '<span class="icon">☀️</span> Light Mode';
    } else {
        darkModeToggle.innerHTML = '<span class="icon">🌙</span> Dark Mode';
    }
}

// ============================================
// SIDEBAR TOGGLE FOR MOBILE
// ============================================
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        const isMobile = window.innerWidth <= 768;
        const classToToggle = isMobile ? 'active' : 'hidden';
        sidebar.classList.toggle(classToToggle);
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            } else {
                sidebar.classList.add('hidden');
            }
        }
    });
    
    // Reset sidebar when window is resized
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            sidebar.classList.remove('hidden');
        }
    });
}

// ============================================
// TEST TIMER
// ============================================
let timerInterval;
let timeRemaining;

function startTimer(minutes) {
    timeRemaining = minutes * 60; // Convert to seconds
    const timerDisplay = document.getElementById('timer');
    
    if (!timerDisplay) return;
    
    // Clear any existing timer
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    
    timerInterval = setInterval(() => {
        const mins = Math.floor(timeRemaining / 60);
        const secs = timeRemaining % 60;
        
        timerDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        
        // Warning at 1 minute
        if (timeRemaining === 60) {
            timerDisplay.style.background = 'linear-gradient(135deg, #f59e0b, #d97706)';
            showNotification('⚠️ Only 1 minute remaining!', 'warning');
        }
        
        // Critical warning at 10 seconds
        if (timeRemaining === 10) {
            timerDisplay.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
            timerDisplay.style.animation = 'pulse 0.5s infinite';
        }
        
        // Time's up
        if (timeRemaining <= 0) {
            clearInterval(timerInterval);
            showNotification('⏰ Time is up! Submitting automatically...', 'error');
            setTimeout(() => {
                const form = document.getElementById('testSubmitForm');
                if (form) form.submit();
            }, 1000);
        }
        
        timeRemaining--;
    }, 1000);
}

// Close test modal
function closeTestModal() {
    const modal = document.getElementById('testModal');
    if (modal) {
        modal.style.display = 'none';
        if (timerInterval) {
            clearInterval(timerInterval);
        }
    }
}

// Confirm before leaving test
window.addEventListener('beforeunload', (e) => {
    const modal = document.getElementById('testModal');
    if (modal && modal.style.display === 'flex') {
        e.preventDefault();
        e.returnValue = 'You have an active test. Are you sure you want to leave?';
        return e.returnValue;
    }
});

// ============================================
// AUTO-HIDE ALERTS
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s, transform 0.5s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});

// ============================================
// LIVE SEARCH FOR TABLES
// ============================================
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (input && table) {
        input.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        });
    }
}

// ============================================
// INACTIVITY AUTO LOGOUT
// ============================================
let inactivityTimer;
const inactivityTime = 30 * 60 * 1000; // 30 minutes

function resetInactivityTimer() {
    clearTimeout(inactivityTimer);
    inactivityTimer = setTimeout(() => {
        showNotification('⏰ You have been logged out due to inactivity', 'error');
        setTimeout(() => {
            window.location.href = 'actions.php?logout=1';
        }, 2000);
    }, inactivityTime);
}

// Track user activity
const isDashboard = document.querySelector('.dashboard');
if (isDashboard) {
    ['mousemove', 'keypress', 'click', 'scroll', 'touchstart'].forEach(event => {
        document.addEventListener(event, resetInactivityTimer);
    });
    
    // Initialize timer
    resetInactivityTimer();
}

// ============================================
// FORM VALIDATION
// ============================================
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', function(e) {
            const inputs = this.querySelectorAll('[required]');
            let valid = true;
            let firstInvalid = null;
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    valid = false;
                    input.style.borderColor = '#ef4444';
                    input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                    
                    if (!firstInvalid) firstInvalid = input;
                } else {
                    input.style.borderColor = '';
                    input.style.boxShadow = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                showNotification('⚠️ Please fill all required fields', 'error');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Clear error styling on input
        const inputs = form.querySelectorAll('[required]');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = '';
                    this.style.boxShadow = '';
                }
            });
        });
    }
}

// ============================================
// FILE UPLOAD PREVIEW
// ============================================
function previewFile(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file && preview) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                preview.innerHTML = `
                    <div style="margin-top: 12px;">
                        <img src="${e.target.result}" 
                             style="max-width: 200px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <p style="margin-top: 8px; font-size: 13px; color: var(--text-secondary);">
                            ${file.name} (${formatBytes(file.size)})
                        </p>
                    </div>
                `;
            } else {
                preview.innerHTML = `
                    <div style="margin-top: 12px; padding: 12px; background: var(--bg-tertiary); border-radius: 8px;">
                        <p style="font-size: 14px; color: var(--text-primary);">
                            📄 ${file.name}
                        </p>
                        <p style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                            ${formatBytes(file.size)}
                        </p>
                    </div>
                `;
            }
        };
        
        reader.readAsDataURL(file);
    }
}

// Format bytes to human readable
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// ============================================
// SMOOTH SCROLL
// ============================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ============================================
// CONFIRM DELETE ACTIONS
// ============================================
document.querySelectorAll('a[href*="delete"]').forEach(link => {
    if (!link.hasAttribute('onclick')) {
        link.addEventListener('click', function(e) {
            if (!confirm('⚠️ Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    }
});

// ============================================
// ENHANCED NOTIFICATION SYSTEM
// ============================================
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };
    
    notification.innerHTML = `
        <span style="font-size: 18px; margin-right: 8px;">${icons[type] || icons.info}</span>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 24px;
        right: 24px;
        padding: 16px 24px;
        background: ${colors[type] || colors.info};
        color: white;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        z-index: 3000;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        min-width: 300px;
        max-width: 500px;
        animation: slideInRight 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// ============================================
// ANIMATION STYLES
// ============================================
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);

// ============================================
// DYNAMIC TABLE SORTING
// ============================================
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    let ascending = true;
    const header = table.querySelector(`th:nth-child(${columnIndex + 1})`);
    
    if (header.dataset.sorted === 'asc') {
        ascending = false;
        header.dataset.sorted = 'desc';
    } else {
        ascending = true;
        header.dataset.sorted = 'asc';
    }
    
    rows.sort((a, b) => {
        const aValue = a.querySelectorAll('td')[columnIndex].textContent.trim();
        const bValue = b.querySelectorAll('td')[columnIndex].textContent.trim();
        
        if (!isNaN(aValue) && !isNaN(bValue)) {
            return ascending ? 
                parseFloat(aValue) - parseFloat(bValue) : 
                parseFloat(bValue) - parseFloat(aValue);
        }
        
        return ascending ? 
            aValue.localeCompare(bValue) : 
            bValue.localeCompare(aValue);
    });
    
    rows.forEach(row => tbody.appendChild(row));
    showNotification('✓ Table sorted', 'info');
}

// ============================================
// PRINT FUNCTIONALITY
// ============================================
function printSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<link rel="stylesheet" href="assets.css">');
        printWindow.document.write('<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">');
        printWindow.document.write('</head><body>');
        printWindow.document.write(section.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
}

// ============================================
// COPY TO CLIPBOARD
// ============================================
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('✓ Copied to clipboard!', 'success');
        }).catch(() => {
            fallbackCopy(text);
        });
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    showNotification('✓ Copied to clipboard!', 'success');
}

// ============================================
// TOOLTIPS
// ============================================
document.querySelectorAll('[data-tooltip]').forEach(element => {
    element.addEventListener('mouseenter', function() {
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = this.getAttribute('data-tooltip');
        tooltip.style.cssText = `
            position: absolute;
            background: var(--text-primary);
            color: var(--bg-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
            z-index: 4000;
            pointer-events: none;
            box-shadow: var(--shadow-lg);
            animation: fadeIn 0.2s;
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = this.getBoundingClientRect();
        tooltip.style.top = `${rect.top - tooltip.offsetHeight - 8}px`;
        tooltip.style.left = `${rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)}px`;
        
        this.addEventListener('mouseleave', () => tooltip.remove(), { once: true });
    });
});

// ============================================
// ENHANCED FILE INPUT
// ============================================
document.querySelectorAll('input[type="file"]').forEach(input => {
    const wrapper = document.createElement('div');
    wrapper.className = 'file-input-wrapper';
    wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';
    
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);
    
    input.addEventListener('change', function() {
        const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
        const fileSize = this.files[0] ? formatBytes(this.files[0].size) : '';
        let label = wrapper.querySelector('.file-label');
        
        if (!label) {
            label = document.createElement('div');
            label.className = 'file-label';
            label.style.cssText = `
                margin-top: 8px;
                padding: 12px;
                background: var(--bg-tertiary);
                border-radius: 8px;
                color: var(--text-primary);
                font-size: 14px;
                font-weight: 500;
            `;
            wrapper.appendChild(label);
        }
        
        label.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">📎</span>
                <div>
                    <div>${fileName}</div>
                    ${fileSize ? `<div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">${fileSize}</div>` : ''}
                </div>
            </div>
        `;
    });
});

// ============================================
// PAGE LOAD ANIMATIONS
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Fade in elements
    const fadeElements = document.querySelectorAll('.stat-card, .section-box, .assignment-card, .test-card');
    fadeElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        setTimeout(() => {
            el.style.transition = 'opacity 0.5s, transform 0.5s';
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 50);
    });
});

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K for search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[type="search"], input[placeholder*="search" i]');
        if (searchInput) {
            searchInput.focus();
            showNotification('🔍 Search activated', 'info');
        }
    }
    
    // Escape to close modals
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal[style*="display: flex"]');
        if (modal) {
            closeTestModal();
        }
    }
});

// ============================================
// CONSOLE LOG
// ============================================
console.log('%c Student Academy Locker ', 'background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 10px 20px; border-radius: 5px; font-size: 16px; font-weight: bold;');
console.log('%c Enhanced UI loaded successfully! ', 'color: #10b981; font-weight: bold;');
console.log('Dark mode:', localStorage.getItem('darkMode'));

// ============================================
// PERFORMANCE MONITORING
// ============================================
window.addEventListener('load', () => {
    const perfData = performance.timing;
    const loadTime = perfData.loadEventEnd - perfData.navigationStart;
    console.log(`%c Page loaded in ${loadTime}ms`, 'color: #3b82f6; font-weight: bold;');
});
