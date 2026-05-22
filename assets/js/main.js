// ============================================
// OIS GRAFIKA - MAIN JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== MOBILE DRAWER MENU =====
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    const navOverlay = document.getElementById('navOverlay');
    
    function openDrawer() {
        navMenu.classList.add('active');
        if (navOverlay) navOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeDrawer() {
        navMenu.classList.remove('active');
        if (navOverlay) navOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (navMenu.classList.contains('active')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
        
        if (navOverlay) {
            navOverlay.addEventListener('click', closeDrawer);
        }
        
        // Close drawer on link click (mobile)
        navMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                closeDrawer();
            });
        });
        
        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && navMenu.classList.contains('active')) {
                closeDrawer();
            }
        });
    }
    
    // ===== AUTO-HIDE FLASH MESSAGES =====
    document.querySelectorAll('.flash').forEach(function(flash) {
        setTimeout(function() {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.4s';
            setTimeout(function() { flash.remove(); }, 400);
        }, 4000);
    });
    
    // ===== QUANTITY CONTROLS =====
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
    
    // ===== VARIANT SELECTION =====
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
                
                const priceEl = document.querySelector('.detail-price-num');
                if (priceEl && btn.dataset.totalPrice) {
                    priceEl.textContent = btn.dataset.totalPrice;
                }
            });
        });
    });
    
    // ===== COPY REFERRAL CODE =====
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
    
    // ===== CONFIRM DELETE =====
    document.querySelectorAll('[data-confirm]').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
    
    // ===== TAB SWITCHER =====
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

function formatRupiah(num) {
    return 'Rp ' + parseInt(num).toLocaleString('id-ID');
}

// ============================================
// HERO SLIDESHOW
// ============================================
(function() {
    const slideshow = document.getElementById('heroSlideshow');
    if (!slideshow) return;
    
    const slides = slideshow.querySelectorAll('.hero-slide');
    const dots = slideshow.querySelectorAll('.hero-dot');
    if (slides.length <= 1) return;
    
    let currentIdx = 0;
    let autoplayTimer;
    
    window.heroGoTo = function(idx) {
        slides[currentIdx].classList.remove('active');
        if (dots[currentIdx]) dots[currentIdx].classList.remove('active');
        currentIdx = (idx + slides.length) % slides.length;
        slides[currentIdx].classList.add('active');
        if (dots[currentIdx]) dots[currentIdx].classList.add('active');
        resetAutoplay();
    };
    
    window.heroSlide = function(delta) {
        window.heroGoTo(currentIdx + delta);
    };
    
    function resetAutoplay() {
        clearInterval(autoplayTimer);
        autoplayTimer = setInterval(() => window.heroSlide(1), 5000);
    }
    
    // Pause on hover
    slideshow.addEventListener('mouseenter', () => clearInterval(autoplayTimer));
    slideshow.addEventListener('mouseleave', resetAutoplay);
    
    resetAutoplay();
})();

// ============================================
// IMAGE LIGHTBOX
// ============================================
function openLightbox(url) {
    let overlay = document.getElementById('lightboxOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'lightboxOverlay';
        overlay.className = 'lightbox-overlay';
        overlay.innerHTML = `<button class="lightbox-close" type="button">&times;</button><img class="lightbox-img" id="lightboxImg" src="" alt="">`;
        overlay.addEventListener('click', closeLightbox);
        document.body.appendChild(overlay);
    }
    document.getElementById('lightboxImg').src = url;
    overlay.classList.add('show');
}
function closeLightbox() {
    const overlay = document.getElementById('lightboxOverlay');
    if (overlay) overlay.classList.remove('show');
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeLightbox();
});
