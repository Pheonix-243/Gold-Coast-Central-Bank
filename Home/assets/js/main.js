// Gold Coast Central Bank - Main JavaScript

document.addEventListener("DOMContentLoaded", function () {
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
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute("href"));
      if (target) {
        const offsetTop = target.offsetTop - 80; // Account for fixed navbar
        window.scrollTo({
          top: offsetTop,
          behavior: "smooth",
        });
      }
    });
  });
}

// Scroll animations (KEPT INTACT)
function initAnimations() {
  const observerOptions = {
    threshold: 0.1,
    rootMargin: "0px 0px -50px 0px",
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("visible");
      }
    });
  }, observerOptions);

  // Observe all elements with animation classes
  document
    .querySelectorAll(".fade-in, .slide-in-left, .slide-in-right")
    .forEach((el) => {
      observer.observe(el);
    });
}

// Navbar scroll effect (KEPT INTACT)
function initNavbarScroll() {
  const navbar = document.querySelector(".navbar");
  let lastScrollY = window.scrollY;

  window.addEventListener("scroll", () => {
    const currentScrollY = window.scrollY;

    if (currentScrollY > 100) {
      navbar.classList.add("navbar-scrolled");
    } else {
      navbar.classList.remove("navbar-scrolled");
    }

    lastScrollY = currentScrollY;
  });
}

// Modified form validation (REMOVED PASSWORD STRENGTH)
function initFormValidation() {
  const forms = document.querySelectorAll(".needs-validation");

  forms.forEach((form) => {
    form.addEventListener("submit", function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }

      form.classList.add("was-validated");
    });

    // Only apply email validation
    validateEmailFields();
  });
}

// Email validation (KEPT)
function validateEmailFields() {
  const emailInputs = document.querySelectorAll('input[type="email"]');

  emailInputs.forEach((input) => {
    input.addEventListener("blur", function () {
      const email = this.value.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (email && !emailRegex.test(email)) {
        this.setCustomValidity("Please enter a valid email address");
        this.classList.add("is-invalid");
      } else {
        this.setCustomValidity("");
        this.classList.remove("is-invalid");
        if (email) this.classList.add("is-valid");
      }
    });
  });
}

// Password toggle visibility (KEPT)
function initPasswordToggle() {
  const passwordToggles = document.querySelectorAll(".password-toggle");

  passwordToggles.forEach((toggle) => {
    toggle.addEventListener("click", function () {
      const input = this.previousElementSibling;
      const icon = this.querySelector("i");

      if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
      } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
      }
    });
  });
}

// ALL OTHER FUNCTIONS REMAIN EXACTLY THE SAME (CONTACT FORM, NOTIFICATIONS, ETC.)
// [Rest of your original code remains unchanged...]

// Contact form handling
function initContactForm() {
  const contactForm = document.getElementById("contactForm");

  if (contactForm) {
    contactForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;

      submitBtn.classList.add("btn-loading");
      submitBtn.disabled = true;

      setTimeout(() => {
        submitBtn.classList.remove("btn-loading");
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;

        showNotification(
          "Message sent successfully! We will get back to you soon.",
          "success"
        );
        this.reset();
        this.classList.remove("was-validated");
      }, 2000);
    });
  }
}

// Notification system
function showNotification(message, type = "info") {
  const notification = document.createElement("div");
  notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
  notification.style.cssText =
    "top: 100px; right: 20px; z-index: 9999; min-width: 300px;";
  notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

  document.body.appendChild(notification);

  setTimeout(() => {
    if (notification.parentElement) {
      notification.remove();
    }
  }, 5000);
}

// [Keep all other existing functions exactly as they were...]
// Loading overlay, utility functions, AOS initialization, etc.

// Initialize login loading when DOM is ready
document.addEventListener("DOMContentLoaded", function () {
  initSmoothScrolling();
  initAnimations();
  initFormValidation();
  initPasswordToggle();
  initNavbarScroll();
  initContactForm();
  initLoginLoadingScreen();
});

// Export functions for global use
window.GCCBank = {
  showNotification,
  showLoadingOverlay,
  hideLoadingOverlay,
  submitFormWithLoading,
  formatCurrency,
  formatPhoneNumber,
  showPremiumLoadingScreen,
};
