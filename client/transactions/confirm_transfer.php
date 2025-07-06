<?php
require_once('../includes/auth.php');
// require_once('../includes/header.php');

// Check if transfer data exists in session
if (!isset($_SESSION['transfer_data'])) {
    $_SESSION['error'] = "Transfer session expired. Please start over.";
    header('Location: transfer.php');
    exit;
}

$transferData = $_SESSION['transfer_data'];
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Confirm Transfer</h1>
    </div>
    
    <div class="card">
        <div class="card-body">
            <h5 class="card-title">Transfer Details</h5>
            <div class="mb-4">
                <p><strong>From:</strong> <?= htmlspecialchars($transferData['senderName']) ?> (<?= htmlspecialchars($transferData['senderAccount']) ?>)</p>
                <p><strong>To:</strong> <?= htmlspecialchars($transferData['recipientName']) ?> (<?= htmlspecialchars($transferData['recipientAccount']) ?>)</p>
                
                <?php if ($transferData['transferType'] === 'domestic'): ?>
                    <p><strong>Bank:</strong> <?= htmlspecialchars($transferData['bank']) ?></p>
                <?php elseif ($transferData['transferType'] === 'mobile_money'): ?>
                    <p><strong>Network:</strong> <?= htmlspecialchars($transferData['network']) ?></p>
                <?php elseif ($transferData['transferType'] === 'international'): ?>
                    <p><strong>Bank:</strong> <?= htmlspecialchars($transferData['bank']) ?></p>
                    <p><strong>SWIFT/BIC:</strong> <?= htmlspecialchars($transferData['swift']) ?></p>
                    <p><strong>Country:</strong> <?= htmlspecialchars($transferData['country']) ?></p>
                <?php endif; ?>
                
                <p><strong>Amount:</strong> GHC <?= number_format($transferData['amount'], 2) ?></p>
                
                <?php if (!empty($transferData['description'])): ?>
                    <p><strong>Description:</strong> <?= htmlspecialchars($transferData['description']) ?></p>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="process_transfer.php">
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-primary">Confirm & Send</button>
                <a href="transfer.php" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
</main>

