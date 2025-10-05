<?php
class TransactionProcessor {
    private $con;
    
    // Transaction type constants matching your database
    const TYPE_TRANSFER = 1;
    const TYPE_PAYMENT_RECIEVED = 2;
    const TYPE_WITHDRAWAL = 3;
    const TYPE_DEPOSIT = 4;
    const TYPE_ONLINE_PAYMENT = 5;
    const TYPE_INTEREST = 6;
    const TYPE_FEE = 7;
    
    public function __construct($dbConnection) {
        $this->con = $dbConnection;
    }
    
    // ==================== CORE METHODS ====================
    
    public function processTransfer($senderAccount, $recipientAccount, $amount, $description = '') {
        $this->validateAccounts($senderAccount, $recipientAccount);
        $this->validateAmount($senderAccount, $amount);
        
        $reference = $this->generateReference('TRF');
        $currentDateTime = date('Y-m-d H:i:s');
        
        mysqli_begin_transaction($this->con);
        try {
            // SENDER: Record as Transfer (outgoing)
            $this->recordTransaction(
                account: $senderAccount,
                sender: $senderAccount,
                receiver: $recipientAccount,
                type: self::TYPE_TRANSFER,
                amount: -$amount,
                reference: $reference,
                status: 'completed',
                description: $description,
                datetime: $currentDateTime
            );
            
            // RECIPIENT: Record as PAYMENT_RECEIVED (incoming)
            $this->recordTransaction(
                account: $recipientAccount,
                sender: $senderAccount,
                receiver: $recipientAccount,
                type: self::TYPE_PAYMENT_RECEIVED,
                amount: $amount,
                reference: $reference,
                status: 'completed',
                description: $description,
                datetime: $currentDateTime
            );
            
            // Update balances
            $this->updateBalance($senderAccount, -$amount);
            $this->updateBalance($recipientAccount, $amount);
            
            mysqli_commit($this->con);
            return $this->successResponse($reference);
            
        } catch (Exception $e) {
            mysqli_rollback($this->con);
            return $this->errorResponse($e->getMessage());
        }
    }
    
 // Enhanced processDeposit method
public function processDeposit($account, $amount, $method = 'Cash', $description = '') {
    // Get account holder name
    $accountName = $this->getAccountHolderName($account);
    
    $reference = $this->generateReference('DEP');
    $currentDateTime = date('Y-m-d H:i:s');
    
    mysqli_begin_transaction($this->con);
    try {
        $this->recordTransaction(
            account: $account,
            sender: 'SYSTEM',
            senderName: 'System',
            receiver: $account,
            receiverName: $accountName,
            type: self::TYPE_DEPOSIT,
            amount: $amount,
            reference: $reference,
            status: 'completed',
            description: "$method Deposit: $description",
            datetime: $currentDateTime
        );
        
        $this->updateBalance($account, $amount);
        mysqli_commit($this->con);
        
        return $this->successResponse($reference);
    } catch (Exception $e) {
        mysqli_rollback($this->con);
        return $this->errorResponse($e->getMessage());
    }
}

// Enhanced processWithdrawal method
public function processWithdrawal($account, $amount, $method = 'Cash', $destination = '', $description = '') {
    // Get account holder name
    $accountName = $this->getAccountHolderName($account);
    
    // Determine receiver name based on method
    $receiverName = $method === 'Cash' ? 'Cash' : $destination;
    
    $reference = $this->generateReference('WDL');
    $currentDateTime = date('Y-m-d H:i:s');
    
    mysqli_begin_transaction($this->con);
    try {
        $this->validateAmount($account, $amount);
        
        $this->recordTransaction(
            account: $account,
            sender: $account,
            senderName: $accountName,
            receiver: 'SYSTEM',
            receiverName: $receiverName,
            type: self::TYPE_WITHDRAWAL,
            amount: -$amount,
            reference: $reference,
            status: 'completed',
            description: "$method Withdrawal: $description",
            datetime: $currentDateTime
        );
        
        $this->updateBalance($account, -$amount);
        mysqli_commit($this->con);
        
        return $this->successResponse($reference);
    } catch (Exception $e) {
        mysqli_rollback($this->con);
        return $this->errorResponse($e->getMessage());
    }
}

// Helper method to get account holder name
private function getAccountHolderName($account) {
    $sql = "SELECT name FROM accountsholder WHERE account = ?";
    $stmt = mysqli_prepare($this->con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $account);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['name'];
    }
    
    return 'Unknown'; // Fallback if name not found
}
    

private function recordTransaction($account, $sender, $senderName, $receiver, $receiverName, $type, $amount, 
                                $reference, $status, $description, $datetime) {
    list($dt, $tm) = explode(' ', $datetime);
    
    $sql = "INSERT INTO account_history 
            (account, sender, s_name, receiver, r_name, type, amount, reference_number, 
             status, description, dt, tm) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = mysqli_prepare($this->con, $sql);
    mysqli_stmt_bind_param($stmt, "sssssiisssss", 
        $account, 
        $sender, 
        $senderName,
        $receiver,
        $receiverName,
        $type, 
        abs($amount), 
        $reference, 
        $status, 
        $description, 
        $dt, 
        $tm);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to record transaction: " . mysqli_error($this->con));
    }
}


    private function updateBalance($account, $amount) {
        $sql = "UPDATE accounts_info SET balance = balance + ? WHERE account = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $amount, $account);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Balance update failed: " . mysqli_error($this->con));
        }
    }
    
    private function validateAmount($account, $amount) {
        $sql = "SELECT balance FROM accounts_info WHERE account = ? FOR UPDATE";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $balance = mysqli_fetch_assoc($result)['balance'];
        
        if ($balance < $amount) {
            throw new Exception("Insufficient funds");
        }
    }
    
    private function validateAccounts($sender, $recipient) {
        if ($sender === $recipient) {
            throw new Exception("Cannot transfer to same account");
        }
        
        // Check recipient exists and is active
        $sql = "SELECT 1 FROM accounts_info a 
                JOIN accountsholder h ON a.account = h.account 
                WHERE a.account = ? AND a.status = 'Active'";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $recipient);
        mysqli_stmt_execute($stmt);
        
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
            throw new Exception("Recipient account not found or inactive");
        }
    }
    
    private function generateReference($prefix) {
        return $prefix . '-' . strtoupper(uniqid());
    }
    
    private function successResponse($reference) {
        return ['status' => 'success', 'reference' => $reference];
    }
    
    private function errorResponse($message) {
        return ['status' => 'error', 'message' => $message];
    }
}
?>