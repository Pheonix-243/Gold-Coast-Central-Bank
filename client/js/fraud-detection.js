/**
 * Client-side fraud detection helpers
 */

class FraudDetectionClient {
    /**
     * Get device fingerprint for client-side
     */
    static getDeviceFingerprint() {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        let fingerprint = '';
        
        // Canvas fingerprinting
        ctx.textBaseline = "top";
        ctx.font = "14px 'Arial'";
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#f60";
        ctx.fillRect(125, 1, 62, 20);
        ctx.fillStyle = "#069";
        ctx.fillText("FRAUD_DETECTION", 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText("FRAUD_DETECTION", 4, 17);
        
        fingerprint += canvas.toDataURL();
        
        // Screen properties
        fingerprint += window.screen.width + 'x' + window.screen.height;
        fingerprint += window.screen.colorDepth;
        fingerprint += (window.screen.orientation || {}).type || window.screen.mozOrientation || window.screen.msOrientation;
        
        // Browser properties
        fingerprint += navigator.userAgent;
        fingerprint += navigator.language;
        fingerprint += navigator.hardwareConcurrency || 'unknown';
        fingerprint += navigator.platform;
        fingerprint += navigator.maxTouchPoints || 'unknown';
        
        // Timezone
        fingerprint += new Date().getTimezoneOffset();
        
        // Hash the fingerprint
        return this.hashString(fingerprint);
    }
    
    static hashString(str) {
        // Simple hash function for demo purposes
        // In production, use a more robust hashing algorithm
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash.toString(16);
    }
    
    /**
     * Collect device info and add to form before submission
     */
    static addDeviceInfoToForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        const fingerprint = this.getDeviceFingerprint();
        const screenResolution = window.screen.width + 'x' + window.screen.height;
        
        // Add hidden fields
        this.addHiddenField(form, 'device_fingerprint', fingerprint);
        this.addHiddenField(form, 'screen_resolution', screenResolution);
    }
    
    static addHiddenField(form, name, value) {
        let input = form.querySelector(`input[name="${name}"]`);
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.appendChild(input);
        }
        input.value = value;
    }
}

// Add device info to all transaction forms
document.addEventListener('DOMContentLoaded', function() {
    // Add to transfer form
    const transferForm = document.getElementById('transferForm');
    if (transferForm) {
        FraudDetectionClient.addDeviceInfoToForm('transferForm');
    }
    
    // Add to deposit form
    const depositForm = document.getElementById('depositForm');
    if (depositForm) {
        FraudDetectionClient.addDeviceInfoToForm('depositForm');
    }
    
    // Add to withdrawal form
    const withdrawalForm = document.getElementById('withdrawalForm');
    if (withdrawalForm) {
        FraudDetectionClient.addDeviceInfoToForm('withdrawalForm');
    }
});