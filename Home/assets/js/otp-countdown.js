/**
 * OTP Countdown and Timer Management
 * Vanilla JS - No form validation, clean countdown timers only
 */

let expiryTimer = null;
let cooldownTimer = null;

function initializeOTPCountdown(expiryTimestamp, cooldownSeconds) {
  // Initialize expiry countdown
  if (expiryTimestamp > 0) {
    startExpiryCountdown(expiryTimestamp);
  }

  // Initialize cooldown countdown
  if (cooldownSeconds > 0) {
    startCooldownTimer(cooldownSeconds);
  }
}

function startExpiryCountdown(expiryTimestamp) {
  const timerElement = document.getElementById("timer");
  if (!timerElement) return;

  function updateTimer() {
    const now = Math.floor(Date.now() / 1000);
    const remaining = expiryTimestamp - now;

    if (remaining <= 0) {
      timerElement.textContent = "EXPIRED";
      timerElement.style.color = "#e53e3e";
      timerElement.style.fontWeight = "600";

      // Disable form elements
      const otpInput = document.getElementById("otp");
      const verifyButton = document.querySelector(".verify-button");

      if (otpInput) otpInput.disabled = true;
      if (verifyButton) verifyButton.disabled = true;

      showExpiryMessage();
      clearInterval(expiryTimer);
      return;
    }

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    const timeString = `${minutes}:${seconds.toString().padStart(2, "0")}`;

    timerElement.textContent = timeString;

    // Change color when time is running out
    if (remaining <= 60) {
      timerElement.style.color = "#e53e3e"; // red
    } else if (remaining <= 120) {
      timerElement.style.color = "#d69e2e"; // yellow
    } else {
      timerElement.style.color = "#48bb78"; // green
    }
    timerElement.style.fontWeight = "600";
  }

  // Update immediately and then every second
  updateTimer();
  expiryTimer = setInterval(updateTimer, 1000);
}

function startCooldownTimer(initialSeconds) {
  const resendBtn = document.querySelector(".resend-button");
  if (!resendBtn) return;

  let remaining = initialSeconds;

  // Immediately disable the button if there's a cooldown
  if (remaining > 0) {
    resendBtn.disabled = true;
    resendBtn.innerHTML = `Resend in <span class="cooldown-counter">${remaining}</span>s`;
  }

  function updateCooldown() {
    remaining--;

    if (remaining <= 0) {
      resendBtn.disabled = false;
      resendBtn.innerHTML = "🔄 Resend Code";
      clearInterval(cooldownTimer);
      return;
    }

    resendBtn.querySelector(".cooldown-counter").textContent = remaining;
  }

  // Only start timer if there's an active cooldown
  if (remaining > 0) {
    cooldownTimer = setInterval(updateCooldown, 1000);
  }
}

function showExpiryMessage() {
  const container = document.querySelector(".otp-container");
  if (!container) return;

  // Remove existing expiry messages
  const existingMessages = container.querySelectorAll(".expiry-message");
  existingMessages.forEach((msg) => msg.remove());

  // Create expiry message
  const expiryDiv = document.createElement("div");
  expiryDiv.className = "expiry-message";
  expiryDiv.style.cssText = `
        background: #fed7d7;
        color: #c53030;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        border: 1px solid #feb2b2;
    `;
  expiryDiv.innerHTML = `
        <div style="font-size: 24px; margin-bottom: 10px;">⏰</div>
        <h3>Your verification code has expired</h3>
        <p>Please request a new code to continue</p>
    `;

  const otpHeader = container.querySelector(".otp-header");
  otpHeader.insertAdjacentElement("afterend", expiryDiv);
}

function resetTimers() {
  if (expiryTimer) {
    clearInterval(expiryTimer);
    expiryTimer = null;
  }

  if (cooldownTimer) {
    clearInterval(cooldownTimer);
    cooldownTimer = null;
  }
}

// Cleanup on page unload
window.addEventListener("beforeunload", function () {
  resetTimers();
});

// Auto-refresh functionality for expired sessions
function checkSessionValidity() {
  fetch(window.location.href, {
    method: "HEAD",
  })
    .then((response) => {
      if (response.redirected) {
        window.location.href = response.url;
      }
    })
    .catch((error) => {
      console.error("Session check failed:", error);
    });
}

// Check session every 30 seconds
setInterval(checkSessionValidity, 30000);
