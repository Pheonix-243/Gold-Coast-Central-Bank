// Gold Coast Central Bank - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initSmoothScrolling();
    initAnimations();
    initFormValidation();
    initPasswordToggle();
    initNavbarScroll();
    initContactForm();
});

// Smooth scrolling for anchor links
function initSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                const offsetTop = target.offsetTop - 80; // Account for fixed navbar
                window.scrollTo({
                    top: offsetTop,
                    behavior: 'smooth'
                });
            }
        });
    });
}

// Scroll animations
function initAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    // Observe all elements with animation classes
    document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(el => {
        observer.observe(el);
    });
}

// Navbar scroll effect
function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 100) {
            navbar.classList.add('navbar-scrolled');
        } else {
            navbar.classList.remove('navbar-scrolled');
        }

        lastScrollY = currentScrollY;
    });
}

// Form validation
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });

    // Real-time validation for specific fields
    validateEmailFields();
    validatePhoneFields();
    validatePasswordFields();
}

// Email validation
function validateEmailFields() {
    const emailInputs = document.querySelectorAll('input[type="email"]');
    
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.setCustomValidity('Please enter a valid email address');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (email) this.classList.add('is-valid');
            }
        });
    });
}

// Phone validation (Ghana format)
function validatePhoneFields() {
    const phoneInputs = document.querySelectorAll('input[name="phone"]');
    
    phoneInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const phone = this.value.replace(/\D/g, '');
            const ghanaPhoneRegex = /^(0|233)?[2-9]\d{8}$/;
            
            if (phone && !ghanaPhoneRegex.test(phone)) {
                this.setCustomValidity('Please enter a valid Ghana phone number (e.g., 0244123456 or 233244123456)');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (phone) this.classList.add('is-valid');
            }
        });

        // Format phone number as user types
        input.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.startsWith('233')) {
                value = value.replace(/^233/, '+233 ');
            } else if (value.startsWith('0')) {
                value = value.replace(/^0/, '0');
                if (value.length > 3) {
                    value = value.slice(0, 4) + ' ' + value.slice(4);
                }
                if (value.length > 8) {
                    value = value.slice(0, 8) + ' ' + value.slice(8);
                }
            }
            
            this.value = value;
        });
    });
}

// Password validation
function validatePasswordFields() {
    const passwordInputs = document.querySelectorAll('input[name="password"]');
    const confirmPasswordInputs = document.querySelectorAll('input[name="confirm_password"]');
    
    passwordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const password = this.value;
            const minLength = 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumbers = /\d/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            let isValid = password.length >= minLength && hasUpperCase && hasLowerCase && hasNumbers && hasSpecialChar;
            
            if (password && !isValid) {
                this.setCustomValidity('Password must be at least 8 characters long and contain uppercase, lowercase, number, and special character');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (password) this.classList.add('is-valid');
            }

            // Update password strength indicator
            updatePasswordStrength(this, password);
        });
    });

    confirmPasswordInputs.forEach(input => {
        input.addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (confirmPassword) this.classList.add('is-valid');
            }
        });
    });
}

// Password strength indicator
function updatePasswordStrength(input, password) {
    let strengthIndicator = input.parentElement.querySelector('.password-strength');
    
    if (!strengthIndicator) {
        strengthIndicator = document.createElement('div');
        strengthIndicator.className = 'password-strength mt-2';
        input.parentElement.appendChild(strengthIndicator);
    }
    
    if (!password) {
        strengthIndicator.innerHTML = '';
        return;
    }
    
    let score = 0;
    let feedback = [];
    
    if (password.length >= 8) score++;
    else feedback.push('At least 8 characters');
    
    if (/[A-Z]/.test(password)) score++;
    else feedback.push('Uppercase letter');
    
    if (/[a-z]/.test(password)) score++;
    else feedback.push('Lowercase letter');
    
    if (/\d/.test(password)) score++;
    else feedback.push('Number');
    
    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score++;
    else feedback.push('Special character');
    
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['#dc3545', '#fd7e14', '#ffc107', '#20c997', '#28a745'];
    
    strengthIndicator.innerHTML = `
        <div class="d-flex justify-content-between align-items-center">
            <small class="text-muted">Strength: <span style="color: ${strengthColors[score - 1] || '#dc3545'}">${strengthLevels[score - 1] || 'Very Weak'}</span></small>
            <div class="progress" style="width: 100px; height: 4px;">
                <div class="progress-bar" style="width: ${score * 20}%; background-color: ${strengthColors[score - 1] || '#dc3545'}"></div>
            </div>
        </div>
        ${feedback.length > 0 ? `<small class="text-muted d-block">Missing: ${feedback.join(', ')}</small>` : ''}
    `;
}

// Password toggle visibility
function initPasswordToggle() {
    const passwordToggles = document.querySelectorAll('.password-toggle');
    
    passwordToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

// Contact form handling
function initContactForm() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.classList.add('btn-loading');
            submitBtn.disabled = true;
            
            // Simulate form submission (replace with actual AJAX call)
            setTimeout(() => {
                submitBtn.classList.remove('btn-loading');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                
                // Show success message
                showNotification('Message sent successfully! We will get back to you soon.', 'success');
                
                // Reset form
                this.reset();
                this.classList.remove('was-validated');
            }, 2000);
        });
    }
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Loading overlay
function showLoadingOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center';
    overlay.style.cssText = 'background-color: rgba(0,0,0,0.7); z-index: 9999;';
    overlay.innerHTML = `
        <div class="text-center text-white">
            <div class="spinner-border text-warning mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5>Processing...</h5>
        </div>
    `;
    
    document.body.appendChild(overlay);
    return overlay;
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

// Form submission with loading
function submitFormWithLoading(form, successCallback) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.classList.add('btn-loading');
    submitBtn.disabled = true;
    
    // Your form submission logic here
    
    return new Promise((resolve) => {
        setTimeout(() => {
            submitBtn.classList.remove('btn-loading');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
            
            if (successCallback) {
                successCallback();
            }
            
            resolve();
        }, 2000);
    });
}

// Utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS'
    }).format(amount);
}

function formatPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.startsWith('233')) {
        return `+233 ${cleaned.slice(3, 6)} ${cleaned.slice(6, 9)} ${cleaned.slice(9)}`;
    } else if (cleaned.startsWith('0')) {
        return `${cleaned.slice(0, 4)} ${cleaned.slice(4, 7)} ${cleaned.slice(7)}`;
    }
    return phone;
}

// Initialize AOS (Animate On Scroll) if needed
function initAOS() {
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
    }
}

// Premium Login Loading Screen
function initLoginLoadingScreen() {
    const loginForms = document.querySelectorAll('form[action=""], form[method="POST"]');
    
    loginForms.forEach(form => {
        // Check if this is a login form (has email and password fields)
        const emailField = form.querySelector('input[type="email"], input[name="email"]');
        const passwordField = form.querySelector('input[type="password"], input[name="password"]');
        
        if (emailField && passwordField && form.closest('[class*="auth"]')) {
            form.addEventListener('submit', function(e) {
                // Only show loading if form is valid
                if (form.checkValidity()) {
                    showPremiumLoadingScreen();
                }
            });
        }
    });
}

function showPremiumLoadingScreen() {
    // Create loading overlay
    const overlay = document.createElement('div');
    overlay.className = 'login-loading-overlay';
    overlay.innerHTML = `
        <div class="loading-content">
            <div class="loading-logo"></div>
            <h3 class="loading-title">Accessing Your Account</h3>
            <p class="loading-subtitle">Connecting to secure servers worldwide</p>
            
            <div class="loading-progress">
                <div class="loading-progress-bar"></div>
            </div>
            
            <div class="loading-steps">
                <div class="loading-step active" data-step="1">Verifying credentials...</div>
                <div class="loading-step" data-step="2">Establishing secure connection...</div>
                <div class="loading-step" data-step="3">Loading your dashboard...</div>
            </div>
            
            <div class="trust-indicators mt-4">
                <div class="trust-badge">
                    <i class="fas fa-shield-alt"></i>
                    256-bit SSL
                </div>
                <div class="trust-badge">
                    <i class="fas fa-globe"></i>
                    Global Access
                </div>
                <div class="trust-badge">
                    <i class="fas fa-lock"></i>
                    Bank-Grade Security
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Animate steps
    setTimeout(() => {
        overlay.classList.add('active');
        animateLoadingSteps();
    }, 100);
    
    return overlay;
}

function animateLoadingSteps() {
    const steps = document.querySelectorAll('.loading-step');
    let currentStep = 0;
    
    const stepInterval = setInterval(() => {
        // Remove active from current step
        if (steps[currentStep]) {
            steps[currentStep].classList.remove('active');
        }
        
        // Move to next step
        currentStep++;
        
        // Add active to new step
        if (steps[currentStep]) {
            steps[currentStep].classList.add('active');
        } else {
            clearInterval(stepInterval);
        }
    }, 800);
}

// Initialize login loading when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all functionality
    initSmoothScrolling();
    initAnimations();
    initFormValidation();
    initPasswordToggle();
    initNavbarScroll();
    initContactForm();
    initLoginLoadingScreen(); // Add this new function
});

// Export functions for global use
window.GCCBank = {
    showNotification,
    showLoadingOverlay,
    hideLoadingOverlay,
    submitFormWithLoading,
    formatCurrency,
    formatPhoneNumber,
    showPremiumLoadingScreen
};
