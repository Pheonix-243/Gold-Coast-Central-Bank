<?php
require_once('../includes/auth.php');
require_once('../includes/header.php');

// Check if there's a pending deposit
if (!isset($_SESSION['pending_deposit'])) {
    $_SESSION['error'] = "No deposit request found";
    header('Location: deposit.php');
    exit;
}

$amount = $_SESSION['pending_deposit']['amount'];
$email = $_SESSION['client_email'] ?? 'customer@example.com'; // Fallback email if not set
$reference = 'DEP-' . uniqid();

// Store the reference in session for verification later
$_SESSION['paystack_reference'] = $reference;
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Complete Your Deposit</h1>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-body text-center">
                    <h5 class="card-title">Payment Amount: GHC<?= number_format($amount, 2) ?></h5>
                    <p class="text-muted">You'll be redirected to Paystack to complete your payment</p>
                    
                    <div class="spinner-border text-primary my-4" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Include Paystack Inline JS -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const amountInKobo = <?= $amount * 100 ?>; // Paystack uses kobo/cent units
    const email = '<?= $email ?>';
    const reference = '<?= $reference ?>';
    
    const handler = PaystackPop.setup({
        key: 'pk_test_7090654890c9b9e49ccd73414cf46791275afd28', // Replace with your Paystack public key
        email: email,
        amount: amountInKobo,
        currency: 'GHS', // Or your preferred currency
        ref: reference,
        callback: function(response) {
            // On successful payment, redirect to verification
            window.location.href = 'verify_paystack.php?reference=' + response.reference;
        },
        onClose: function() {
            // When user closes the payment modal
            window.location.href = 'deposit.php?status=cancelled';
        }
    });
    
    // Automatically open Paystack modal when page loads
    handler.openIframe();
});
</script>

<?php require_once('../includes/footer.php'); ?>