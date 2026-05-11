<?php
/**
 * GL Mapping System
 * 
 * Maps CMMS entities to accounting GL accounts
 * Handles cost allocation, journal entry creation, and audit trails
 */

class GLMapping {
    
    private $c;
    
    public function __construct($mysqli_connection) {
        $this->c = $mysqli_connection;
    }

    /**
     * Get GL account mapping for a work order
     * Considers: equipment, maintenance type, cost center, priority
     */
    public function getWorkOrderGLAccount($wo_id, $equipment_id = null, $maintenance_type = null) {
        $result = mysqli_query($this->c,
            "SELECT gl_account FROM wo_gl_mappings 
             WHERE wo_id=" . (int)$wo_id . " 
             LIMIT 1"
        );

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['gl_account'];
        }

        // Try to find by equipment type
        if ($equipment_id) {
            $result = mysqli_query($this->c,
                "SELECT gl_account FROM equipment_gl_mappings 
                 WHERE equipment_id=" . (int)$equipment_id . " 
                 LIMIT 1"
            );

            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                return $row['gl_account'];
            }
        }

        // Default maintenance account
        return '61000'; // Maintenance & Repairs
    }

    /**
     * Create GL journal entry for work order completion
     */
    public function createWOJournalEntry($wo_id, $amount, $journal_type = 'WO_COMPLETION') {
        // Get WO details
        $wo_result = mysqli_query($this->c,
            "SELECT id, wo_id, title, mechanic_id FROM work_orders WHERE id=" . (int)$wo_id . " LIMIT 1"
        );

        if (!$wo_result || mysqli_num_rows($wo_result) === 0) {
            return ['success' => false, 'error' => 'Work order not found'];
        }

        $wo = mysqli_fetch_assoc($wo_result);

        // Get GL accounts
        $debit_account = $this->getWorkOrderGLAccount($wo_id);
        $credit_account = $this->getConfig('default_payable_account', '21000'); // Accounts Payable

        // Create journal entry
        $je_insert = "INSERT INTO gl_journal_entries 
                      (entity_type, entity_id, journal_type, debit_account, debit_amount, 
                       credit_account, credit_amount, description, created_by, created_at, status)
                      VALUES 
                      ('WorkOrder', " . (int)$wo_id . ",
                       '" . mysqli_real_escape_string($this->c, $journal_type) . "',
                       '" . mysqli_real_escape_string($this->c, $debit_account) . "', $amount,
                       '" . mysqli_real_escape_string($this->c, $credit_account) . "', $amount,
                       'WO Maintenance: " . mysqli_real_escape_string($this->c, $wo['wo_id']) . " - " . mysqli_real_escape_string($this->c, $wo['title']) . "',
                       " . (int)$wo['mechanic_id'] . ", NOW(), 'Draft')";

        if (!mysqli_query($this->c, $je_insert)) {
            return ['success' => false, 'error' => 'Failed to create journal entry: ' . mysqli_error($this->c)];
        }

        $je_id = mysqli_insert_id($this->c);

        return [
            'success' => true,
            'journal_entry_id' => $je_id,
            'debit_account' => $debit_account,
            'credit_account' => $credit_account,
            'amount' => $amount
        ];
    }

    /**
     * Post journal entry to GL (final accounting)
     */
    public function postJournalEntry($je_id) {
        // Get journal entry
        $je_result = mysqli_query($this->c,
            "SELECT * FROM gl_journal_entries WHERE id=" . (int)$je_id . " LIMIT 1"
        );

        if (!$je_result || mysqli_num_rows($je_result) === 0) {
            return ['success' => false, 'error' => 'Journal entry not found'];
        }

        $je = mysqli_fetch_assoc($je_result);

        // Verify accounts exist in chart of accounts
        $debit_check = mysqli_query($this->c,
            "SELECT id FROM chart_of_accounts WHERE account_code='" . 
            mysqli_real_escape_string($this->c, $je['debit_account']) . "' LIMIT 1"
        );

        if (!$debit_check || mysqli_num_rows($debit_check) === 0) {
            return ['success' => false, 'error' => 'Invalid debit account: ' . $je['debit_account']];
        }

        $credit_check = mysqli_query($this->c,
            "SELECT id FROM chart_of_accounts WHERE account_code='" . 
            mysqli_real_escape_string($this->c, $je['credit_account']) . "' LIMIT 1"
        );

        if (!$credit_check || mysqli_num_rows($credit_check) === 0) {
            return ['success' => false, 'error' => 'Invalid credit account: ' . $je['credit_account']];
        }

        // Post entry
        $update = "UPDATE gl_journal_entries SET status='Posted', posted_date=NOW() WHERE id=" . (int)$je_id;

        if (!mysqli_query($this->c, $update)) {
            return ['success' => false, 'error' => 'Failed to post: ' . mysqli_error($this->c)];
        }

        // Create GL transactions
        $this->createGLTransaction($je['debit_account'], $je['debit_amount'], 'Debit', $je_id);
        $this->createGLTransaction($je['credit_account'], $je['credit_amount'], 'Credit', $je_id);

        return ['success' => true, 'journal_entry_id' => $je_id];
    }

    /**
     * Create GL transaction (account detail record)
     */
    private function createGLTransaction($account_code, $amount, $type, $je_id) {
        $insert = "INSERT INTO gl_transactions 
                   (account_code, debit_amount, credit_amount, transaction_type, 
                    journal_entry_id, transaction_date, created_at)
                   VALUES 
                   ('" . mysqli_real_escape_string($this->c, $account_code) . "',
                    " . ($type === 'Debit' ? $amount : 0) . ",
                    " . ($type === 'Credit' ? $amount : 0) . ",
                    '" . mysqli_real_escape_string($this->c, $type) . "',
                    " . (int)$je_id . ", NOW(), NOW())";

        mysqli_query($this->c, $insert);
    }

    /**
     * Setup GL mapping for equipment type
     */
    public function mapEquipmentToAccount($equipment_id, $gl_account, $cost_center = null) {
        $insert = "INSERT INTO equipment_gl_mappings 
                   (equipment_id, gl_account, cost_center, created_at)
                   VALUES 
                   (" . (int)$equipment_id . ",
                    '" . mysqli_real_escape_string($this->c, $gl_account) . "',
                    " . (!empty($cost_center) ? "'" . mysqli_real_escape_string($this->c, $cost_center) . "'" : "NULL") . ",
                    NOW())
                   ON DUPLICATE KEY UPDATE 
                   gl_account='" . mysqli_real_escape_string($this->c, $gl_account) . "',
                   cost_center=" . (!empty($cost_center) ? "'" . mysqli_real_escape_string($this->c, $cost_center) . "'" : "NULL");

        if (mysqli_query($this->c, $insert)) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => mysqli_error($this->c)];
    }

    /**
     * Allocate work order cost to multiple GL accounts (cost splitting)
     */
    public function allocateWOCost($wo_id, $allocations) {
        // $allocations = [
        //    ['account' => '61000', 'percentage' => 60, 'description' => 'Labor'],
        //    ['account' => '62000', 'percentage' => 40, 'description' => 'Materials']
        // ]

        // Get WO total amount
        $wo_result = mysqli_query($this->c,
            "SELECT COALESCE(SUM(amount), 0) as total FROM work_order_costs WHERE wo_id=" . (int)$wo_id
        );

        if (!$wo_result) {
            return ['success' => false, 'error' => 'WO not found'];
        }

        $wo_data = mysqli_fetch_assoc($wo_result);
        $total_amount = $wo_data['total'];

        // Create allocation entries
        foreach ($allocations as $alloc) {
            $amount = ($total_amount * $alloc['percentage']) / 100;
            
            $insert = "INSERT INTO wo_cost_allocations 
                       (wo_id, gl_account, amount, percentage, description, created_at)
                       VALUES 
                       (" . (int)$wo_id . ",
                        '" . mysqli_real_escape_string($this->c, $alloc['account']) . "',
                        $amount,
                        " . (float)$alloc['percentage'] . ",
                        '" . mysqli_real_escape_string($this->c, $alloc['description']) . "',
                        NOW())";

            if (!mysqli_query($this->c, $insert)) {
                return ['success' => false, 'error' => 'Failed to create allocation: ' . mysqli_error($this->c)];
            }
        }

        return ['success' => true, 'total_allocated' => $total_amount];
    }

    /**
     * Get GL account trial balance for reporting
     */
    public function getAccountBalance($account_code, $from_date = null, $to_date = null) {
        $where = "WHERE account_code='" . mysqli_real_escape_string($this->c, $account_code) . "'";
        
        if ($from_date) {
            $where .= " AND transaction_date >= '" . mysqli_real_escape_string($this->c, $from_date) . "'";
        }

        if ($to_date) {
            $where .= " AND transaction_date <= '" . mysqli_real_escape_string($this->c, $to_date) . "'";
        }

        $result = mysqli_query($this->c,
            "SELECT 
                SUM(debit_amount) as total_debits,
                SUM(credit_amount) as total_credits,
                (SUM(debit_amount) - SUM(credit_amount)) as balance
             FROM gl_transactions $where"
        );

        if ($result) {
            $row = mysqli_fetch_assoc($result);
            return [
                'account_code' => $account_code,
                'debits' => (float)($row['total_debits'] ?? 0),
                'credits' => (float)($row['total_credits'] ?? 0),
                'balance' => (float)($row['balance'] ?? 0)
            ];
        }

        return ['account_code' => $account_code, 'debits' => 0, 'credits' => 0, 'balance' => 0];
    }

    /**
     * Get configuration value
     */
    private function getConfig($key, $default = null) {
        $result = mysqli_query($this->c,
            "SELECT value FROM system_config WHERE key='" . mysqli_real_escape_string($this->c, $key) . "' LIMIT 1"
        );

        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['value'];
        }

        return $default;
    }
}

?>
