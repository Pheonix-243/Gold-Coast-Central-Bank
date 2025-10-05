<?php
class CategorizationEngine {
    private $con;
    
    public function __construct($connection) {
        $this->con = $connection;
    }
    
    /**
     * Automatically categorize a transaction based on available data
     * THIS IS WHERE THE MAGIC HAPPENS
     */
    public function autoCategorize($description, $transferType, $recipientName = '', $amount = 0) {
        // Handle null values and clean up
        $description = strtolower(trim($description ?? ''));
        $recipientName = strtolower(trim($recipientName ?? ''));
        
        error_log("Auto-categorizing: desc='{$description}', type='{$transferType}', recipient='{$recipientName}'");
        
        // If it's empty, return null immediately
        if (empty($description) && empty($recipientName)) {
            error_log("No data for categorization");
            return null;
        }
        
        $candidates = [];
        
        // Get all active rules
        $sql = "SELECT cr.*, sc.name as category_name, sc.color, sc.icon 
                FROM category_rules cr 
                JOIN spending_categories sc ON cr.category_id = sc.id 
                WHERE cr.is_active = 1 AND sc.is_active = 1 
                ORDER BY cr.confidence_score DESC";
        
        $result = mysqli_query($this->con, $sql);
        
        if (!$result) {
            error_log("Failed to get categorization rules: " . mysqli_error($this->con));
            return null;
        }
        
        while ($rule = mysqli_fetch_assoc($result)) {
            $matches = false;
            $ruleValue = strtolower($rule['rule_value']);
            
            switch ($rule['rule_type']) {
                case 'keyword':
                    // Check both description AND recipient name for keywords
                    if ($this->matchesRule($description, $ruleValue, $rule['rule_operator']) || 
                        $this->matchesRule($recipientName, $ruleValue, $rule['rule_operator'])) {
                        $matches = true;
                        error_log("Keyword match: {$ruleValue} -> {$rule['category_name']}");
                    }
                    break;
                    
                case 'recipient':
                    if ($this->matchesRule($recipientName, $ruleValue, $rule['rule_operator'])) {
                        $matches = true;
                        error_log("Recipient match: {$ruleValue} -> {$rule['category_name']}");
                    }
                    break;
                    
                case 'transfer_type':
                    if ($transferType === $ruleValue) {
                        $matches = true;
                        error_log("Transfer type match: {$transferType} -> {$rule['category_name']}");
                    }
                    break;
            }
            
            if ($matches) {
                $candidates[] = [
                    'category_id' => $rule['category_id'],
                    'category_name' => $rule['category_name'],
                    'confidence' => (float)$rule['confidence_score'],
                    'rule_matched' => $rule['rule_type'] . ': ' . $rule['rule_value'],
                    'color' => $rule['color'],
                    'icon' => $rule['icon']
                ];
                
                // If we have a high-confidence match, return immediately
                if ($rule['confidence_score'] >= 0.90) {
                    mysqli_free_result($result);
                    error_log("High confidence match found: {$rule['category_name']}");
                    return $candidates[0];
                }
            }
        }
        
        mysqli_free_result($result);
        
        // Return the highest confidence candidate, or null if no matches
        if (!empty($candidates)) {
            usort($candidates, function($a, $b) {
                return $b['confidence'] <=> $a['confidence'];
            });
            
            $bestMatch = $candidates[0];
            error_log("Best match selected: {$bestMatch['category_name']} (confidence: {$bestMatch['confidence']})");
            return $bestMatch;
        }
        
        error_log("No categorization matches found");
        return null;
    }
    
    /**
     * Check if text matches a rule based on the operator
     */
    private function matchesRule($text, $ruleValue, $operator) {
        if (empty($text)) return false;
        
        switch ($operator) {
            case 'contains':
                return strpos($text, $ruleValue) !== false;
                
            case 'exact':
                return $text === $ruleValue;
                
            case 'starts_with':
                return strpos($text, $ruleValue) === 0;
                
            case 'ends_with':
                return substr($text, -strlen($ruleValue)) === $ruleValue;
                
            case 'regex':
                return preg_match($ruleValue, $text) === 1;
                
            default:
                return strpos($text, $ruleValue) !== false;
        }
    }
    
    /**
     * Get all available categories
     */
    public function getAllCategories() {
        $categories = [];
        $sql = "SELECT * FROM spending_categories WHERE is_active = 1 ORDER BY name";
        $result = mysqli_query($this->con, $sql);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $categories[] = $row;
            }
            mysqli_free_result($result);
        }
        
        return $categories;
    }
    
    /**
     * Update transaction category
     */
    public function updateTransactionCategory($transactionId, $categoryId, $source = 'manual') {
        error_log("Updating transaction {$transactionId} with category {$categoryId}, source: {$source}");
        
        // Handle null category ID (remove category)
        if (empty($categoryId)) {
            $sql = "UPDATE account_history SET category_id = NULL, category_source = NULL, category_confidence = NULL WHERE no = ?";
            $stmt = mysqli_prepare($this->con, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $transactionId);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $success;
            }
        } else {
            $sql = "UPDATE account_history SET category_id = ?, category_source = ?, category_confidence = 1.0 WHERE no = ?";
            $stmt = mysqli_prepare($this->con, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "isi", $categoryId, $source, $transactionId);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $success;
            }
        }
        
        return false;
    }
    
    /**
     * Remove category from transaction
     */
    public function removeTransactionCategory($transactionId) {
        return $this->updateTransactionCategory($transactionId, null);
    }
    
    /**
     * Get categorization statistics for a user
     */
    public function getUserCategorizationStats($account) {
        $stats = [
            'total_transactions' => 0,
            'categorized' => 0,
            'auto_categorized' => 0,
            'manual_categorized' => 0,
            'uncategorized' => 0
        ];
        
        $sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN category_id IS NOT NULL THEN 1 ELSE 0 END) as categorized,
                SUM(CASE WHEN category_source = 'auto' THEN 1 ELSE 0 END) as auto_categorized,
                SUM(CASE WHEN category_source = 'manual' THEN 1 ELSE 0 END) as manual_categorized,
                SUM(CASE WHEN category_id IS NULL THEN 1 ELSE 0 END) as uncategorized
                FROM account_history 
                WHERE account = ?";
        
        $stmt = mysqli_prepare($this->con, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $account);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $total, $categorized, $auto, $manual, $uncategorized);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);
            
            $stats = [
                'total_transactions' => $total,
                'categorized' => $categorized,
                'auto_categorized' => $auto,
                'manual_categorized' => $manual,
                'uncategorized' => $uncategorized
            ];
        }
        
        return $stats;
    }
}
?>