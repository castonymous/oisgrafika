// ============================================
// OIS GRAFIKA - MAIN JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // Mobile menu toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
        });
        
        // Close menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navMenu.contains(e.target) && !navToggle.contains(e.target)) {
                navMenu.classList.remove('active');
                navToggle.classList.remove('active');
            }
        });
    }
    
    // User dropdown toggle (mobile)
    const userToggle = document.querySelector('.nav-user-toggle');
    if (userToggle) {
        userToggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                userToggle.parentElement.classList.toggle('active');
            }
        });
    }
    
    // Auto-hide flash messages
    document.querySelectorAll('.flash').forEach(function(flash) {
        setTimeout(function() {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.4s';
            setTimeout(function() { flash.remove(); }, 400);
        }, 4000);
    });
    
    // Quantity controls
    document.querySelectorAll('.qty-control').forEach(function(control) {
        const input = control.querySelector('input');
        const btnMinus = control.querySelector('.qty-minus');
        const btnPlus = control.querySelector('.qty-plus');
        
        if (btnMinus) {
            btnMinus.addEventListener('click', function() {
                let val = parseInt(input.value) || 1;
                if (val > 1) input.value = val - 1;
                input.dispatchEvent(new Event('change'));
            });
        }
        
        if (btnPlus) {
            btnPlus.addEventListener('click', function() {
                let val = parseInt(input.value) || 1;
                input.value = val + 1;
                input.dispatchEvent(new Event('change'));
            });
        }
    });
    
    // Variant selection
    document.querySelectorAll('.variant-options').forEach(function(group) {
        const buttons = group.querySelectorAll('.variant-btn');
        const hiddenInput = group.parentElement.querySelector('input[type="hidden"][name="variant_id"]');
        
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                if (hiddenInput) {
                    hiddenInput.value = btn.dataset.variantId || '';
                }
                
                // Update price if needed
                const priceEl = document.querySelector('.detail-price-num');
                if (priceEl && btn.dataset.totalPrice) {
                    priceEl.textContent = btn.dataset.totalPrice;
                }
            });
        });
    });
    
    // Copy referral code
    document.querySelectorAll('.btn-copy').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const code = btn.dataset.copy || btn.parentElement.querySelector('.referral-code').textContent;
            navigator.clipboard.writeText(code).then(function() {
                const originalText = btn.textContent;
                btn.textContent = '✓ Tersalin';
                setTimeout(function() {
                    btn.textContent = originalText;
                }, 2000);
            });
        });
    });
    
    // Confirm delete
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
    
    // Tab switcher
    document.querySelectorAll('.tabs-nav').forEach(function(nav) {
        const tabs = nav.querySelectorAll('.tab-item');
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                if (tab.dataset.tab) {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    const target = tab.dataset.tab;
                    document.querySelectorAll('.tab-content').forEach(function(content) {
                        content.style.display = content.dataset.tab === target ? 'block' : 'none';
                    });
                }
            });
        });
    });
});

// Format Rupiah util
function formatRupiah(num) {
    return 'Rp ' + parseInt(num).toLocaleString('id-ID');
}
