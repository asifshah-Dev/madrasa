<?php 
require_once('security.php'); 
require_once('conn_inc.php');

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch student information
$student_info = null;
$current_class = null;
$student_class_id = null;
$fee_types = [];

if ($student_id > 0) {
    // Get student registration details
    $student_query = $conn->query("
        SELECT sr.*, vc.title as village_name 
        FROM student_registration sr 
        LEFT JOIN village_councils vc ON sr.village_council_id = vc.id 
        WHERE sr.id = $student_id
    ");
    $student_info = $student_query->fetch_assoc();
    
    // Get current class (status = 0 means active)
    $class_query = $conn->query("
        SELECT sc.*, c.title as class_name, s.title as session_title 
        FROM student_class sc 
        INNER JOIN classes c ON sc.class_id = c.id 
        INNER JOIN sessions s ON sc.session_id = s.id 
        WHERE sc.student_registration_id = $student_id AND sc.status = 0 
        ORDER BY sc.id DESC LIMIT 1
    ");
    
    if ($class_query->num_rows > 0) {
        $current_class = $class_query->fetch_assoc();
        $student_class_id = $current_class['id'];
    }
    
    // Get active fee types
    $fee_types_query = $conn->query("SELECT id, title FROM fee_types WHERE status = 1 ORDER BY title");
    while ($row = $fee_types_query->fetch_assoc()) {
        $fee_types[] = $row;
    }
    
    // Get class fee amounts for the current class and session
    $class_fees = [];
    if ($student_class_id && $current_class) {
        $class_fees_query = $conn->query("
            SELECT fee_type_id, amount 
            FROM class_fee_types 
            WHERE class_id = {$current_class['class_id']} 
            AND session_id = {$current_class['session_id']}
        ");
        
        while ($row = $class_fees_query->fetch_assoc()) {
            $class_fees[$row['fee_type_id']] = $row['amount'];
        }
    }
    // Convert to JSON for JavaScript
    $class_fees_json = json_encode($class_fees);
}
// Handle advance fee submission with accounting integration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_advance'])) {
    
    $fee_type_id = intval($_POST['fee_type_id']);
    $month_number = intval($_POST['month_number']);
    $year = intval($_POST['year']);
    $due_date = mysqli_real_escape_string($conn, $_POST['due_date']);
    $total_amount = floatval($_POST['total_amount']);
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $paid_amount = floatval($_POST['paid_amount']);
    $discount_note = mysqli_real_escape_string($conn, $_POST['discount_note'] ?? '');
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $transaction_ref = mysqli_real_escape_string($conn, $_POST['transaction_ref'] ?? '');
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $session_id = $current_class['session_id'];
    
    // Validate
    $errors = [];
    if ($fee_type_id <= 0) $errors[] = "Please select fee type";
    if ($month_number < 1 || $month_number > 12) $errors[] = "Please select valid month";
    if ($year < 2000) $errors[] = "Please select valid year";
    if (empty($due_date)) $errors[] = "Please select due date";
    if ($total_amount <= 0) $errors[] = "Total amount must be greater than 0";
    if ($paid_amount < 0) $errors[] = "Paid amount cannot be negative";
    if (empty($payment_method) && $paid_amount > 0) $errors[] = "Please select payment method";
    if (empty($payment_date)) $errors[] = "Please select payment date";
    
    // Check if fee card already exists for this month
    if (empty($errors)) {
        $month_start = date('Y-m-01', strtotime("$year-$month_number-01"));
        $month_end = date('Y-m-t', strtotime("$year-$month_number-01"));
        
        $check_query = $conn->query("
            SELECT sfc.id, sfc.status, ft.title as fee_type_title
            FROM student_fee_card sfc
            INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
            WHERE sfc.student_class_id = $student_class_id
            AND sfc.fee_type_id = $fee_type_id
            AND sfc.session_id = $session_id
            AND sfc.due_date BETWEEN '$month_start' AND '$month_end'
            LIMIT 1
        ");
        
        if ($check_query && $check_query->num_rows > 0) {
            $existing = $check_query->fetch_assoc();
            $errors[] = "Fee card for " . htmlspecialchars($existing['fee_type_title']) . " for this month already exists. Status: " . 
                       ucfirst($existing['status']) . ". Please use normal fee collection to pay this fee.";
        }
    }
    
    if (empty($errors)) {
        $transaction_started = false;
        
        try {
            // === STEP 1: Start Database Transaction ===
            $conn->begin_transaction();
            $transaction_started = true;
            
            // Format month name for display
            $month_names = ['January', 'February', 'March', 'April', 'May', 'June', 
                           'July', 'August', 'September', 'October', 'November', 'December'];
            $month_display = $month_names[$month_number - 1] . ' ' . $year;
            
            // Set due date to last day of selected month if not provided
            if (empty($due_date)) {
                $due_date = date('Y-m-t', strtotime("$year-$month_number-01"));
            }
            
            // For zero payments, set default payment method
            if ($paid_amount == 0 && empty($payment_method)) {
                $payment_method = 'cash';
            }
            
            // === STEP 2: Lock & Read Cash Account (ONLY if cash changes) ===
            $master_account_id = null;
            $current_balance = null;
            $needs_accounting = ($paid_amount > 0);
            
            if ($needs_accounting) {
                $cash_account_title = 'Main Account';
                $lock_query = $conn->prepare("
                    SELECT id, title, balance 
                    FROM master_account 
                    WHERE title = ? 
                    FOR UPDATE
                ");
                $lock_query->bind_param("s", $cash_account_title);
                $lock_query->execute();
                $master_result = $lock_query->get_result();
                
                if ($master_result->num_rows === 0) {
                    throw new Exception("Cash account '$cash_account_title' not found.");
                }
                $master_account = $master_result->fetch_assoc();
                $master_account_id = $master_account['id'];
                $current_balance = floatval($master_account['balance']);
            }
            
            // === STEP 3: Insert into student_fee_card ===
            $insert_card = $conn->prepare("
                INSERT INTO student_fee_card 
                (student_class_id, fee_type_id, total_amount, due_date, session_id, status, remarks, dated) 
                VALUES 
                (?, ?, ?, ?, ?, 'paid', ?, NOW())
            ");
            $card_remarks = 'Advance payment for ' . $month_display . ' - ' . $remarks;
            $insert_card->bind_param(
                "iiddss",  // ✅ 6 characters for 6 variables
                $student_class_id,   // 1 - i
                $fee_type_id,        // 2 - i
                $total_amount,       // 3 - d
                $due_date,           // 4 - d (date as string)
                $session_id,         // 5 - s
                $card_remarks        // 6 - s
            );
            
            if (!$insert_card->execute()) {
                throw new Exception("Failed to insert fee card: " . $conn->error);
            }
            $fee_card_id = $conn->insert_id;
            
            // === STEP 4: Insert into student_fee_payments (FIXED) ===
            $insert_payment = $conn->prepare("
                INSERT INTO student_fee_payments 
                (fee_card_id, paid_amount, discount_amount, discount_type, discount_note, 
                 payment_date, payment_method, transaction_ref, remarks, status, is_advance) 
                VALUES 
                (?, ?, ?, 'fixed', ?, ?, ?, ?, ?, 'completed', 1)
            ");
            
            // 8 placeholders = 8 variables
            $payment_remarks = 'Advance payment for ' . $month_display . ' - ' . $remarks;
            $insert_payment->bind_param(
                "iddsssss",  // ✅ FIXED: 8 characters for 8 variables
                $fee_card_id,         // 1 - i
                $paid_amount,         // 2 - d
                $discount_amount,     // 3 - d
                $discount_note,       // 4 - s
                $payment_date,        // 5 - s
                $payment_method,      // 6 - s
                $transaction_ref,     // 7 - s
                $payment_remarks      // 8 - s
            );
            
            if (!$insert_payment->execute()) {
                throw new Exception("Failed to insert payment: " . $conn->error);
            }
            $payment_id = $conn->insert_id;
            
            // === STEP 5: Insert into detail_account (ONLY if cash received) ===
            if ($needs_accounting && $master_account_id) {
                $new_balance = $current_balance + $paid_amount;
                
                $detail_insert = $conn->prepare("
                    INSERT INTO detail_account 
                    (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                    VALUES 
                    (?, 'cash in', ?, ?, ?, 'advance_fee', ?, NOW())
                ");
                
                // 5 placeholders = 5 variables
                $detail_insert->bind_param(
                    "iddis",  // ✅ 5 characters for 5 variables
                    $master_account_id,   // 1 - i
                    $paid_amount,         // 2 - d
                    $new_balance,         // 3 - d
                    $fee_card_id,         // 4 - i
                    $payment_date         // 5 - s
                );
                
                if (!$detail_insert->execute()) {
                    throw new Exception("Failed to insert detail_account entry: " . $conn->error);
                }
                
                // === STEP 6: Update master_account.balance ===
                $update_master = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
                $update_master->bind_param("di", $new_balance, $master_account_id);
                if (!$update_master->execute()) {
                    throw new Exception("Failed to update master_account: " . $conn->error);
                }
            }
            
            // === STEP 7: Commit Transaction ===
            $conn->commit();
            $transaction_started = false;
            
            // === Success Response ===
            $success_message = "Advance fee payment recorded successfully!";
            $receipt_id = "ADV-" . date('Ymd') . "-" . rand(1000, 9999);
            
            header("Location: advance_fee_payment.php?id=$student_id&success=1&receipt=$receipt_id&amount=$paid_amount&payment_id=$payment_id");
            exit();
            
        } catch (Exception $e) {
            // === STEP 8: Rollback on Any Error ===
            if ($transaction_started) {
                $conn->rollback();
            }
            
            error_log("Advance fee error (Student ID: $student_id): " . $e->getMessage());
            $error_message = "Error: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle Edit Advance Fee Transaction (Reversal Method)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_advance_transaction'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $new_paid_amount = floatval($_POST['new_paid_amount']);
    $new_discount = floatval($_POST['new_discount']);
    $new_payment_method = mysqli_real_escape_string($conn, $_POST['new_payment_method']);
    $new_transaction_ref = mysqli_real_escape_string($conn, $_POST['new_transaction_ref']);
    $new_remarks = mysqli_real_escape_string($conn, $_POST['new_remarks']);
    $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
    
    $transaction_started = false;
    
    try {
        // === STEP 1: Start Database Transaction ===
        $conn->begin_transaction();
        $transaction_started = true;
        
        // === STEP 2: Validate Original Transaction Exists ===
        $orig_query = $conn->prepare("
            SELECT sfp.*, sfc.student_class_id, sfc.fee_type_id, sfc.total_amount as card_total, sfc.session_id
            FROM student_fee_payments sfp
            INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
            WHERE sfp.id = ? AND sfp.status = 'completed' AND sfp.is_advance = 1
            FOR UPDATE
        ");
        $orig_query->bind_param("i", $transaction_id);
        $orig_query->execute();
        $orig_result = $orig_query->get_result();
        
        if ($orig_result->num_rows === 0) {
            throw new Exception("Original advance transaction not found or already reversed.");
        }
        $original = $orig_result->fetch_assoc();
        
        $old_paid = floatval($original['paid_amount']);
        $old_discount = floatval($original['discount_amount']);
        $fee_card_id = intval($original['fee_card_id']);
        $student_class_id = intval($original['student_class_id']);
        $card_total = floatval($original['card_total']);
        
        // === STEP 3: Calculate Cash Difference (ONLY paid_amount affects cash) ===
        $cash_difference = $new_paid_amount - $old_paid;
        $needs_accounting = ($cash_difference != 0);
        
        // === STEP 4: Lock & Read Cash Account (ONLY if cash changes) ===
        $master_account_id = null;
        $current_balance = null;
        
        if ($needs_accounting) {
            $cash_account_title = 'Main Account';
            $lock_query = $conn->prepare("
                SELECT id, title, balance 
                FROM master_account 
                WHERE title = ? 
                FOR UPDATE
            ");
            $lock_query->bind_param("s", $cash_account_title);
            $lock_query->execute();
            $master_result = $lock_query->get_result();
            
            if ($master_result->num_rows === 0) {
                throw new Exception("Cash account not found.");
            }
            $master_account = $master_result->fetch_assoc();
            $master_account_id = $master_account['id'];
            $current_balance = floatval($master_account['balance']);
        }
        
        // === STEP 5: Reverse Old Transaction (ONLY if cash changes) ===
        $reversal_balance = $current_balance;
        
        if ($needs_accounting) {
            $reversal_amount = -$old_paid; // Negative for cash out
            $reversal_balance = $current_balance + $reversal_amount;
            
            $reversal_insert = $conn->prepare("
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                VALUES 
                (?, 'cash out', ?, ?, ?, 'advance_edit_reversal', ?, NOW())
            ");
            
            $reversal_insert->bind_param(
                "iddis",  // ✅ 5 characters for 5 variables
                $master_account_id,
                $reversal_amount,
                $reversal_balance,
                $fee_card_id,
                $payment_date
            );
            
            if (!$reversal_insert->execute()) {
                throw new Exception("Failed to insert reversal entry: " . $conn->error);
            }
            
            // Update master_account after reversal
            $update_master_rev = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
            $update_master_rev->bind_param("di", $reversal_balance, $master_account_id);
            if (!$update_master_rev->execute()) {
                throw new Exception("Failed to update master_account after reversal: " . $conn->error);
            }
        }
        
        // === STEP 6: Mark Original Payment as Reversed ===
        $update_orig_payment = $conn->prepare("
            UPDATE student_fee_payments 
            SET status = 'reversed', remarks = CONCAT(IFNULL(remarks, ''), ' | Reversed on edit: ', ?)
            WHERE id = ?
        ");
        $edit_note = "Edited on " . date('Y-m-d H:i:s');
        $update_orig_payment->bind_param("si", $edit_note, $transaction_id);
        if (!$update_orig_payment->execute()) {
            throw new Exception("Failed to mark original payment as reversed: " . $conn->error);
        }
        
        // === STEP 7: Validate New Amount ===
        $existing_query = $conn->prepare("
            SELECT COALESCE(SUM(paid_amount), 0) as paid
            FROM student_fee_payments
            WHERE fee_card_id = ? AND status = 'completed'
        ");
        $existing_query->bind_param("i", $fee_card_id);
        $existing_query->execute();
        $existing = $existing_query->get_result()->fetch_assoc();
        
        $existing_paid = floatval($existing['paid']);
        
        if (($existing_paid + $new_paid_amount) > $card_total) {
            throw new Exception("New payment amount exceeds remaining fee card total.");
        }
        
        // === STEP 8: Insert NEW Corrected Transaction ===
        $new_status = 'completed';
        $new_remarks_text = "Edited advance transaction (was ID: $transaction_id)";
        
        $insert_new = $conn->prepare("
            INSERT INTO student_fee_payments 
            (fee_card_id, paid_amount, discount_amount, discount_type, discount_note, 
             payment_date, payment_method, transaction_ref, remarks, status, is_advance) 
            VALUES 
            (?, ?, ?, 'fixed', '', ?, ?, ?, ?, ?, 1)
        ");
        
        // 8 placeholders = 8 variables
        $insert_new->bind_param(
            "iddsssss",  // ✅ 8 characters for 8 variables
            $fee_card_id,         // 1 - i
            $new_paid_amount,     // 2 - d
            $new_discount,        // 3 - d
            $payment_date,        // 4 - s
            $new_payment_method,  // 5 - s
            $new_transaction_ref, // 6 - s
            $new_remarks_text,    // 7 - s
            $new_status           // 8 - s
        );
        
        if (!$insert_new->execute()) {
            throw new Exception("Failed to insert new payment record: " . $conn->error);
        }
        
        $new_payment_id = $conn->insert_id;
        
        // === STEP 9: Insert NEW Detail Account Entry (ONLY if cash changes) ===
        $final_balance = $reversal_balance;
        
        if ($needs_accounting) {
            $new_balance_after = $reversal_balance + $new_paid_amount;
            $final_balance = $new_balance_after;
            
            $new_detail_insert = $conn->prepare("
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                VALUES 
                (?, 'cash in', ?, ?, ?, 'advance_fee', ?, NOW())
            ");
            
            $new_detail_insert->bind_param(
                "iddis",  // ✅ 5 characters for 5 variables
                $master_account_id,
                $new_paid_amount,
                $new_balance_after,
                $fee_card_id,
                $payment_date
            );
            
            if (!$new_detail_insert->execute()) {
                throw new Exception("Failed to insert new detail_account entry: " . $conn->error);
            }
            
            // Update master_account final balance
            $update_master_final = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
            $update_master_final->bind_param("di", $new_balance_after, $master_account_id);
            if (!$update_master_final->execute()) {
                throw new Exception("Failed to update master_account final balance: " . $conn->error);
            }
        }
        
        // === STEP 10: Update Fee Card Status ===
        $total_paid_query = $conn->prepare("
            SELECT COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(discount_amount), 0) as disc
            FROM student_fee_payments
            WHERE fee_card_id = ? AND status = 'completed'
        ");
        $total_paid_query->bind_param("i", $fee_card_id);
        $total_paid_query->execute();
        $total_paid = $total_paid_query->get_result()->fetch_assoc();
        
        $final_paid = floatval($total_paid['paid']);
        $final_disc = floatval($total_paid['disc']);
        
        if ($final_paid + $final_disc >= $card_total) {
            $update_card = $conn->prepare("UPDATE student_fee_card SET status = 'paid' WHERE id = ?");
            $update_card->bind_param("i", $fee_card_id);
            $update_card->execute();
        } else {
            $update_card = $conn->prepare("UPDATE student_fee_card SET status = 'pending' WHERE id = ?");
            $update_card->bind_param("i", $fee_card_id);
            $update_card->execute();
        }
        
        // === STEP 11: Commit Transaction ===
        $conn->commit();
        $transaction_started = false;
        
        // === Success Response ===
        $cash_change_msg = $needs_accounting 
            ? " | Cash changed: " . number_format($cash_difference, 2) 
            : " | No cash change (discount only)";
        
        $success_message = "Advance fee edited successfully! Paid: " . number_format($old_paid, 2) . 
                          " → " . number_format($new_paid_amount, 2) . $cash_change_msg;
        
        header("Location: advance_fee_payment.php?id=$student_id&edit_success=1&trans_id=$new_payment_id");
        exit();
        
    } catch (Exception $e) {
        // === STEP 12: Rollback on Any Error ===
        if ($transaction_started) {
            $conn->rollback();
        }
        
        error_log("Advance fee edit error (Transaction ID: $transaction_id): " . $e->getMessage());
        $error_message = "Edit failed: " . $e->getMessage();
    }
}

// Handle restore advance transaction (reverse accounting and restore fee card)
if (isset($_GET['restore_advance'])) {
    $transaction_id = intval($_GET['restore_advance']);
    $transaction_started = false;
    
    try {
        // === STEP 1: Start Database Transaction ===
        $conn->begin_transaction();
        $transaction_started = true;
        
        // === STEP 2: Fetch Payment Transaction Details ===
        $trans_query = $conn->prepare("
            SELECT sfp.*, sfc.student_class_id, sfc.fee_type_id, sfc.total_amount as card_total
            FROM student_fee_payments sfp
            INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
            WHERE sfp.id = ? AND sfp.status = 'completed' AND sfp.is_advance = 1
            FOR UPDATE
        ");
        $trans_query->bind_param("i", $transaction_id);
        $trans_query->execute();
        $trans_result = $trans_query->get_result();
        
        if ($trans_result->num_rows === 0) {
            throw new Exception("Advance transaction not found or already restored.");
        }
        $trans_data = $trans_result->fetch_assoc();
        
        $fee_card_id = intval($trans_data['fee_card_id']);
        $paid_amount = floatval($trans_data['paid_amount']);
        $discount_amount = floatval($trans_data['discount_amount']);
        $student_class_id = intval($trans_data['student_class_id']);
        $card_total = floatval($trans_data['card_total']);
        
        // === STEP 3: Lock & Read Cash Account ===
        $cash_account_title = 'Main Account';
        $lock_query = $conn->prepare("
            SELECT id, title, balance 
            FROM master_account 
            WHERE title = ? 
            FOR UPDATE
        ");
        $lock_query->bind_param("s", $cash_account_title);
        $lock_query->execute();
        $master_result = $lock_query->get_result();
        
        if ($master_result->num_rows === 0) {
            throw new Exception("Cash account not found.");
        }
        $master_account = $master_result->fetch_assoc();
        $master_account_id = $master_account['id'];
        $current_balance = floatval($master_account['balance']);
        
        // === STEP 4: Insert Reversal Entry in detail_account (Cash Out) ===
        // Only if there was actual cash received (paid_amount > 0)
        if ($paid_amount > 0) {
            $reversal_amount = -$paid_amount; // Negative for cash out
            $reversal_balance = $current_balance + $reversal_amount;
            
            $reversal_insert = $conn->prepare("
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                VALUES 
                (?, 'cash out', ?, ?, ?, 'advance_restore_reversal', ?, NOW())
            ");
            
            // 5 placeholders = 5 variables
            $reversal_insert->bind_param(
                "iddis",  // ✅ 5 characters for 5 variables
                $master_account_id,   // 1 - i (integer)
                $reversal_amount,     // 2 - d (double) - negative
                $reversal_balance,    // 3 - d (double)
                $fee_card_id,         // 4 - i (integer)
                date('Y-m-d H:i:s')   // 5 - s (string)
            );
            
            if (!$reversal_insert->execute()) {
                throw new Exception("Failed to insert reversal entry: " . $conn->error);
            }
            
            // === STEP 5: Update master_account Balance ===
            $update_master = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
            $update_master->bind_param("di", $reversal_balance, $master_account_id);
            if (!$update_master->execute()) {
                throw new Exception("Failed to update master_account balance: " . $conn->error);
            }
        }
        
        // === STEP 6: Mark Payment as Reversed (Don't Delete - Audit Trail) ===
        $update_payment = $conn->prepare("
            UPDATE student_fee_payments 
            SET status = 'reversed', remarks = CONCAT(IFNULL(remarks, ''), ' | Restored on: ', ?)
            WHERE id = ?
        ");
        $restore_note = "Restored on " . date('Y-m-d H:i:s');
        $update_payment->bind_param("si", $restore_note, $transaction_id);
        if (!$update_payment->execute()) {
            throw new Exception("Failed to mark payment as reversed: " . $conn->error);
        }
        
        // === STEP 7: Update Fee Card Status ===
        // Check remaining payments on this card
        $remaining_query = $conn->prepare("
            SELECT COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(discount_amount), 0) as disc
            FROM student_fee_payments
            WHERE fee_card_id = ? AND status = 'completed'
        ");
        $remaining_query->bind_param("i", $fee_card_id);
        $remaining_query->execute();
        $remaining = $remaining_query->get_result()->fetch_assoc();
        
        $total_paid = floatval($remaining['paid']);
        $total_disc = floatval($remaining['disc']);
        
        if ($total_paid + $total_disc >= $card_total) {
            // Still fully paid by other transactions
            $update_card = $conn->prepare("UPDATE student_fee_card SET status = 'paid' WHERE id = ?");
        } else {
            // Not fully paid anymore
            $update_card = $conn->prepare("UPDATE student_fee_card SET status = 'pending' WHERE id = ?");
        }
        $update_card->bind_param("i", $fee_card_id);
        if (!$update_card->execute()) {
            throw new Exception("Failed to update fee card status: " . $conn->error);
        }
        
        // === STEP 8: Commit Transaction ===
        $conn->commit();
        $transaction_started = false;
        
        $success_message = "Advance transaction restored successfully! Amount: " . number_format($paid_amount, 2) . 
                          " reversed from cash account.";
        
        header("Location: advance_fee_payment.php?id=$student_id&restore_success=1");
        exit();
        
    } catch (Exception $e) {
        // === STEP 9: Rollback on Any Error ===
        if ($transaction_started) {
            $conn->rollback();
        }
        
        error_log("Advance fee restore error (Transaction ID: $transaction_id): " . $e->getMessage());
        $error_message = "Restore failed: " . $e->getMessage();
    }
}

// Get advance payment history
$advance_payments = [];
if ($student_class_id) {
    $advance_query = $conn->query("
        SELECT sfp.*, sfc.due_date, sfc.total_amount, ft.title as fee_type_title,
               MONTH(sfc.due_date) as month_number,
               YEAR(sfc.due_date) as year
        FROM student_fee_payments sfp 
        INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id 
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id 
        WHERE sfc.student_class_id = $student_class_id 
        AND sfp.is_advance = 1 
        ORDER BY sfp.id DESC LIMIT 20
    ");
    while ($row = $advance_query->fetch_assoc()) {
        $advance_payments[] = $row;
    }
}

// Function to get month name from number
function getMonthName($month_number) {
    $months = ['January', 'February', 'March', 'April', 'May', 'June', 
               'July', 'August', 'September', 'October', 'November', 'December'];
    return $months[$month_number - 1] ?? '';
}

// Function to get month-year from due date
function getMonthYearFromDueDate($due_date) {
    $timestamp = strtotime($due_date);
    return date('M-y', $timestamp);
}

// Get current year and next few years for dropdown
$current_year = date('Y');
$years = range($current_year, $current_year + 3);

// Set default due date to last day of next month
$next_month = date('n') + 1;
$next_month_year = date('Y');
if ($next_month > 12) {
    $next_month = 1;
    $next_month_year++;
}
$default_due_date = date('Y-m-t', strtotime("$next_month_year-$next_month-01"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Advance Fee Payment - Madrasa Management System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS & JS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="css/mystyle.css" />
  
  <style>
    .student-profile-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 25px;
      border-radius: 10px;
      margin-bottom: 25px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .advance-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      padding: 25px;
      margin-bottom: 25px;
    }
    
    .advance-header {
      background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
      color: white;
      padding: 15px 20px;
      border-radius: 10px 10px 0 0;
      margin: -25px -25px 20px -25px;
    }
    
    .form-label {
      font-weight: 600;
      color: #495057;
    }
    
    .required:after {
      content: " *";
      color: red;
    }
    
    .info-badge {
      padding: 8px 15px;
      border-radius: 20px;
      background: rgba(255,255,255,0.2);
      margin-right: 10px;
      display: inline-block;
    }
    
    .back-link {
      margin-bottom: 20px;
    }
    
    .back-link a {
      color: #667eea;
      text-decoration: none;
      font-size: 14px;
    }
    
    .back-link a:hover {
      text-decoration: underline;
    }
    
    .amount-input {
      font-size: 16px;
      padding: 10px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      transition: all 0.3s ease;
    }
    
    .amount-input:focus {
      border-color: #ff9800;
      box-shadow: 0 0 0 3px rgba(255,152,0,0.1);
    }
    
    .summary-card {
      background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
      color: white;
      padding: 20px;
      border-radius: 10px;
    }
    
    .advance-badge {
      background: #ff9800;
      color: white;
      padding: 3px 8px;
      border-radius: 5px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
    }
    
    .free-student-badge {
      background: #28a745;
      color: white;
      padding: 3px 8px;
      border-radius: 5px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
    }
    
    .discount-full-badge {
      background: #dc3545;
      color: white;
      padding: 3px 8px;
      border-radius: 5px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
    }
    
    .transaction-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .receipt-btn {
      background: none;
      border: 1px solid #4285F4;
      color: #4285F4;
      padding: 5px 10px;
      border-radius: 5px;
      transition: all 0.3s ease;
    }
    
    .receipt-btn:hover {
      background: #4285F4;
      color: white;
    }
    
    .container-custom {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .month-select, .year-select {
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
    }
    
    .month-badge {
      background: #ff9800;
      color: white;
      padding: 5px 10px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .due-date-note {
      font-size: 12px;
      color: #6c757d;
      margin-top: 5px;
    }
    
    .zero-payment-indicator {
      background: #28a745;
      color: white;
      padding: 2px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }
    
    /* New styles for checkboxes */
    .checkbox-col {
      width: 40px;
      text-align: center;
    }
    
    .print-selected-btn {
      margin-left: 10px;
      background: #ff9800;
      color: white;
      border: none;
    }
    
    .print-selected-btn:hover {
      background: #f57c00;
      color: white;
    }
    
    .transaction-checkbox {
      cursor: pointer;
      width: 18px;
      height: 18px;
    }
    
    .card-header .btn-light {
      background: white;
      color: #ff9800;
      border: 1px solid #ff9800;
    }
    
    .card-header .btn-light:hover {
      background: #ff9800;
      color: white;
    }
    
    /* Style for duplicate warning */
    .duplicate-warning {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 10px;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>

<div class="container-custom">
  
  <!-- Simple back link -->
  <div class="back-link">
    <a href="fee_collection.php?id=<?php echo $student_id; ?>">
      <i class="fas fa-arrow-left"></i> Back to Fee Collection
    </a>
    <span class="float-end">
      <a href="index.php" class="text-secondary">
        <i class="fas fa-home"></i> Dashboard
      </a>
    </span>
  </div>
  <?php if (isset($_GET['restore_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-undo-alt"></i> Advance transaction restored successfully! Cash account reversed and fee card restored.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
  <?php if (isset($_GET['edit_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> Advance fee transaction edited successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <?php if (!empty($_GET['trans_id'])): ?>
    <a href="print_receipt.php?id=<?php echo $_GET['trans_id']; ?>&auto_print=1" target="_blank" class="btn btn-sm btn-success float-end ms-2">
      <i class="fas fa-print"></i> Print New Receipt
    </a>
    <?php endif; ?>
  </div>
<?php endif; ?>
  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle"></i> Advance payment processed successfully! 
      Receipt #: <?php echo $_GET['receipt']; ?>
      <?php if ($_GET['amount'] == 0): ?>
        <span class="zero-payment-indicator ms-2">Zero Payment - Fully Waived</span>
      <?php endif; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <?php if (isset($_GET['payment_id'])): ?>
      <a href="print_receipt.php?id=<?php echo $_GET['payment_id']; ?>&auto_print=1" target="_blank" class="btn btn-sm btn-success float-end ms-2">
        <i class="fas fa-print"></i> Print Receipt
      </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($student_info && $current_class): ?>
  
  <!-- Student Profile Header -->
  <div class="student-profile-header">
    <div class="row align-items-center">
      <div class="col-md-8">
        <h2><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars($student_info['name']); ?></h2>
        <h5>S/O: <?php echo htmlspecialchars($student_info['father_name']); ?></h5>
        <div class="mt-3">
          <span class="info-badge">
            <i class="fas fa-id-card"></i> Reg No: <?php echo htmlspecialchars($student_info['reg_no']); ?>
          </span>
          <span class="info-badge">
            <i class="fas fa-book"></i> Class: <?php echo htmlspecialchars($current_class['class_name']); ?>
          </span>
          <span class="info-badge">
            <i class="fas fa-calendar"></i> Session: <?php echo htmlspecialchars($current_class['session_title']); ?>
          </span>
          <span class="advance-badge">
            <i class="fas fa-forward"></i> ADVANCE PAYMENT
          </span>
        </div>
      </div>
      <div class="col-md-4 text-md-end">
        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($student_info['mobile'] ?? 'N/A'); ?></p>
        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($student_info['current_address'] ?? 'N/A'); ?></p>
      </div>
    </div>
  </div>
  
  <!-- Advance Payment Form -->
  <div class="advance-card">
    <div class="advance-header">
      <h4 class="mb-0"><i class="fas fa-forward"></i> Record Advance Fee Payment</h4>
      <p class="mb-0 mt-1 text-white-50">Create advance payment for future months (due_date determines the month)</p>
    </div>
    
    <form method="POST" action="" id="advancePaymentForm">
      <div class="row">
        <!-- Left Column - Fee Details -->
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label required">Fee Type</label>
            <select name="fee_type_id" class="form-select" required>
              <option value="">-- Select Fee Type --</option>
              <?php foreach ($fee_types as $ft): ?>
              <option value="<?php echo $ft['id']; ?>"><?php echo htmlspecialchars($ft['title']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Select Month & Year</label>
            <div class="row">
              <div class="col-md-7">
                <select name="month_number" id="monthNumber" class="form-select month-select" required>
                  <option value="">-- Month --</option>
                  <option value="1">January</option>
                  <option value="2">February</option>
                  <option value="3">March</option>
                  <option value="4">April</option>
                  <option value="5">May</option>
                  <option value="6">June</option>
                  <option value="7">July</option>
                  <option value="8">August</option>
                  <option value="9">September</option>
                  <option value="10">October</option>
                  <option value="11">November</option>
                  <option value="12">December</option>
                </select>
              </div>
              <div class="col-md-5">
                <select name="year" id="year" class="form-select year-select" required>
                  <option value="">-- Year --</option>
                  <?php foreach ($years as $y): ?>
                  <option value="<?php echo $y; ?>" <?php echo ($y == $current_year + 1) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="due-date-note">
              <i class="fas fa-info-circle"></i> Due date will be automatically set to the last day of selected month
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Due Date</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
              <input type="date" 
                     name="due_date" 
                     id="dueDate"
                     class="form-control" 
                     required
                     value="<?php echo $default_due_date; ?>">
            </div>
            <small class="text-muted">The month from due_date determines which month this fee is for</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Total Amount</label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" 
                     name="total_amount" 
                     class="form-control amount-input" 
                     step="0.01" 
                     min="1" 
                     required
                     id="totalAmount"
                     value="1000">
            </div>
          </div>
        </div>
        
        <!-- Right Column - Payment Details -->
        <div class="col-md-6">
          <div class="mb-3">
            <label class="form-label">Discount Amount</label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" 
                     name="discount_amount" 
                     class="form-control amount-input" 
                     step="0.01" 
                     min="0" 
                     id="discountAmount"
                     value="0">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Discount Note</label>
            <input type="text" 
                   name="discount_note" 
                   class="form-control" 
                   placeholder="Reason for discount (optional)">
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Paid Amount</label>
            <div class="input-group">
              <span class="input-group-text">Rs.</span>
              <input type="number" 
                     name="paid_amount" 
                     class="form-control amount-input" 
                     step="0.01" 
                     min="0" 
                     required
                     id="paidAmount"
                     value="1000">
            </div>
            <small class="text-muted">Automatically updates based on discount</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Payment Date</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-calendar"></i></span>
              <input type="date" 
                     name="payment_date" 
                     class="form-control" 
                     required
                     value="<?php echo date('Y-m-d'); ?>">
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label required">Payment Method</label>
            <select name="payment_method" class="form-select" id="paymentMethod" required>
              <option value="">-- Select Method --</option>
              <option value="cash">Cash</option>
              <option value="bank_transfer">Bank Transfer</option>
              <option value="cheque">Cheque</option>
              <option value="online">Online Payment</option>
            </select>
            <small class="text-muted payment-method-note">Optional for zero payments</small>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Transaction Reference</label>
            <input type="text" 
                   name="transaction_ref" 
                   class="form-control" 
                   placeholder="Reference number (optional)">
          </div>
          
          <div class="mb-3">
            <label class="form-label">Remarks</label>
            <textarea name="remarks" 
                      class="form-control" 
                      rows="2" 
                      placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
      </div>
      
      <!-- Payment Summary -->
      <div class="row mt-4">
        <div class="col-md-6 offset-md-3">
          <div class="summary-card">
            <h5 class="mb-3"><i class="fas fa-calculator"></i> Payment Summary</h5>
            <div class="row">
              <div class="col-6">
                <p class="mb-1">Total Fee:</p>
                <p class="mb-1">Discount:</p>
                <p class="mb-1 fw-bold">Net Payable:</p>
                <p class="mb-1 fw-bold">Paid Amount:</p>
                <p class="mb-0 text-warning">Balance:</p>
              </div>
              <div class="col-6 text-end">
                <p class="mb-1" id="summaryTotal">Rs. 0.00</p>
                <p class="mb-1 text-warning" id="summaryDiscount">- Rs. 0.00</p>
                <p class="mb-1 fw-bold" id="summaryNet">Rs. 0.00</p>
                <p class="mb-1 fw-bold" id="summaryPaid">Rs. 0.00</p>
                <p class="mb-0" id="summaryBalance">Rs. 0.00</p>
              </div>
            </div>
            <div class="mt-2 text-center">
              <small>Fee for: <span id="selectedMonthDisplay"><?php echo getMonthName($next_month) . ' ' . $next_month_year; ?></span></small>
            </div>
            <div class="mt-2 text-center" id="zeroPaymentMessage" style="display: none;">
              <span class="free-student-badge">
                <i class="fas fa-gift"></i> Zero Payment - Fully Waived
              </span>
            </div>
          </div>
        </div>
      </div>
      
      <div class="row mt-4">
        <div class="col-12 text-center">
          <button type="submit" name="submit_advance" class="btn btn-lg px-5" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
            <i class="fas fa-forward"></i> Process Advance Payment
          </button>
          <a href="fee_collection.php?id=<?php echo $student_id; ?>" class="btn btn-lg btn-secondary px-5 ms-2">
            <i class="fas fa-times"></i> Cancel
          </a>
        </div>
      </div>
    </form>
  </div>
  
  <!-- Recent Advance Payments History -->
  <div class="card mt-4">
    <div class="card-header" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
      <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-history"></i> Recent Advance Payments History</h5>
        <div>
          <button class="btn btn-sm btn-light" id="selectAllBtn" onclick="toggleSelectAll()">Select All</button>
          <button class="btn btn-sm print-selected-btn" onclick="printSelectedAdvanceTransactions()">
            <i class="fas fa-print"></i> Print Selected
          </button>
        </div>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table transaction-table mb-0">
          <!-- Update the table header -->
<thead class="bg-light">
  <tr>
    <th class="checkbox-col"><i class="fas fa-check-square"></i></th>
    <th>Receipt #</th>
    <th>Date</th>
    <th>Fee Type</th>
    <th>Month</th>
    <th>Due Date</th>
    <th>Total</th>
    <th>Discount</th>
    <th>Paid</th>
    <th>Method</th>
    <th>Action</th>
    <th>Edit</th>
    <th>Restore</th>
  </tr>
</thead>
          <tbody>
            <?php if (empty($advance_payments)): ?>
            <tr>
              <td colspan="11" class="text-center py-4">
                <i class="fas fa-info-circle"></i> No advance payments found
              </td>
            </tr>
            <?php else: ?>
              <?php foreach ($advance_payments as $payment): 
  $receipt_id = "ADV-" . date('Ymd', strtotime($payment['payment_date'])) . "-" . $payment['id'];
  $month_name = getMonthName($payment['month_number']);
  $month_year = getMonthYearFromDueDate($payment['due_date']);
  $is_zero_payment = ($payment['paid_amount'] == 0);
  $can_edit = ($payment['status'] == 'completed');
  $can_restore = ($payment['status'] == 'completed');
?>
<tr>
  <td class="checkbox-col">
    <input type="checkbox" class="transaction-checkbox" value="<?php echo $payment['id']; ?>" id="trans_<?php echo $payment['id']; ?>">
  </td>
  <td>
    <strong><?php echo $receipt_id; ?></strong>
    <?php if ($is_zero_payment): ?>
      <br><span class="free-student-badge mt-1">Zero Payment</span>
    <?php endif; ?>
    <?php if ($payment['status'] == 'reversed'): ?>
      <br><span class="badge bg-secondary mt-1">Reversed</span>
    <?php endif; ?>
  </td>
  <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
  <td><?php echo htmlspecialchars($payment['fee_type_title']); ?></td>
  <td><span class="month-badge"><?php echo $month_year; ?></span></td>
  <td><?php echo date('d M Y', strtotime($payment['due_date'])); ?></td>
  <td>Rs. <?php echo number_format($payment['total_amount'] ?? 0, 2); ?></td>
  <td class="text-danger">- Rs. <?php echo number_format($payment['discount_amount'], 2); ?></td>
  <td class="<?php echo $is_zero_payment ? 'text-info' : 'text-success'; ?> fw-bold">
    Rs. <?php echo number_format($payment['paid_amount'], 2); ?>
  </td>
  <td><?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?></td>
  <td>
    <button class="btn btn-sm btn-outline-primary receipt-btn" onclick="printSingleAdvanceTransaction(<?php echo $payment['id']; ?>)">
      <i class="fas fa-print"></i>
    </button>
  </td>
  <td>
    <?php if ($can_edit): ?>
    <button class="btn btn-sm btn-outline-warning" onclick="openEditAdvanceModal(
      <?php echo $payment['id']; ?>, 
      <?php echo $payment['paid_amount']; ?>, 
      <?php echo $payment['discount_amount']; ?>, 
      '<?php echo $payment['payment_method']; ?>', 
      '<?php echo htmlspecialchars($payment['transaction_ref'] ?? ''); ?>', 
      '<?php echo htmlspecialchars($payment['remarks'] ?? ''); ?>',
      '<?php echo $payment['payment_date']; ?>'
    )" title="Edit Transaction">
      <i class="fas fa-edit"></i>
    </button>
    <?php else: ?>
    <span class="text-muted small">Locked</span>
    <?php endif; ?>
  </td>
  <td>
    <?php if ($can_restore): ?>
    <button class="btn btn-sm btn-outline-danger" onclick="confirmRestoreAdvance(<?php echo $payment['id']; ?>)" title="Restore Advance Fee">
      <i class="fas fa-undo-alt"></i>
    </button>
    <?php else: ?>
    <span class="text-muted small">Restored</span>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  

  <!-- Edit Advance Fee Transaction Modal -->
<div class="modal fade" id="editAdvanceModal" tabindex="-1" aria-labelledby="editAdvanceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); color: white;">
        <h5 class="modal-title" id="editAdvanceModalLabel">
          <i class="fas fa-edit me-2"></i>Edit Advance Fee (Reversal Method)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: brightness(0) invert(1);"></button>
      </div>
      <form method="POST" action="" id="editAdvanceForm">
        <div class="modal-body">
          <input type="hidden" name="transaction_id" id="edit_transaction_id" value="">
          
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Reversal Method:</strong> This will reverse the original transaction and create a new one. 
            Original transaction will be marked as "reversed".
          </div>
          
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Original Amount *</label>
              <input type="text" id="edit_original_amount" class="form-control" readonly disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">Original Discount *</label>
              <input type="text" id="edit_original_discount" class="form-control" readonly disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label required">New Payment Amount *</label>
              <input type="number" name="new_paid_amount" id="edit_new_amount" class="form-control amount-input" step="0.01" min="0" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">New Discount *</label>
              <input type="number" name="new_discount" id="edit_new_discount" class="form-control amount-input" step="0.01" min="0" value="0">
            </div>
            <div class="col-md-6">
              <label class="form-label required">Payment Date *</label>
              <input type="date" name="payment_date" id="edit_payment_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label required">Payment Method *</label>
              <select name="new_payment_method" id="edit_payment_method" class="form-select" required>
                <option value="">Select Method</option>
                <option value="cash" selected>Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="online">Online Payment</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Transaction Ref</label>
              <input type="text" name="new_transaction_ref" id="edit_transaction_ref" class="form-control" placeholder="Ref # (optional)">
            </div>
            <div class="col-md-12">
              <label class="form-label">Remarks</label>
              <textarea name="new_remarks" id="edit_remarks" class="form-control" rows="2" placeholder="Edit remarks (optional)"></textarea>
            </div>
          </div>
          
          <div class="mt-3">
            <strong class="text-danger">Accounting Impact:</strong>
            <ul class="small text-muted">
              <li>Original: <span id="edit_orig_display">0.00</span> → Reversed (cash out)</li>
              <li>New: <span id="edit_new_display">0.00</span> → Added (cash in)</li>
              <li>Net change: <span id="edit_net_display">0.00</span></li>
            </ul>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times"></i> Cancel
          </button>
          <button type="submit" name="edit_advance_transaction" class="btn btn-warning">
            <i class="fas fa-sync-alt"></i> Reverse & Edit
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

  <?php else: ?>
    <div class="alert alert-warning text-center py-5">
      <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
      <h4>Student not found or no active class</h4>
      <p class="mb-3">Please select a valid student with an active class.</p>
      <a href="student_list.php" class="btn btn-primary">
        <i class="fas fa-list"></i> View Student List
      </a>
    </div>
  <?php endif; ?>
  
</div>

<script>
$(document).ready(function() {
  // Class fees data from PHP
  var classFees = <?php echo isset($class_fees_json) ? $class_fees_json : '{}'; ?>;
  
  // Calculate payment summary
  function calculateSummary() {
    var total = parseFloat($('#totalAmount').val()) || 0;
    var discount = parseFloat($('#discountAmount').val()) || 0;
    
    // Validate discount cannot be more than total
    if (discount > total) {
      $('#discountAmount').val(total);
      discount = total;
    }
    
    var net = total - discount;
    var paid = parseFloat($('#paidAmount').val()) || 0;
    
    // Update summary
    $('#summaryTotal').text('Rs. ' + total.toFixed(2));
    $('#summaryDiscount').text('- Rs. ' + discount.toFixed(2));
    $('#summaryNet').text('Rs. ' + net.toFixed(2));
    $('#summaryPaid').text('Rs. ' + paid.toFixed(2));
    
    // Calculate balance
    var balance = net - paid;
    $('#summaryBalance').text('Rs. ' + balance.toFixed(2));
    
    // Style balance based on value
    if (balance < 0) {
      $('#summaryBalance').removeClass('text-warning').addClass('text-info');
    } else if (balance > 0) {
      $('#summaryBalance').removeClass('text-info').addClass('text-warning');
    } else {
      $('#summaryBalance').removeClass('text-info text-warning').addClass('text-success');
    }
    
    // Show/hide zero payment message
    if (paid === 0) {
      $('#zeroPaymentMessage').show();
      
      // Make payment method optional
      $('#paymentMethod').removeAttr('required');
      $('.payment-method-note').show();
    } else {
      $('#zeroPaymentMessage').hide();
      
      // Make payment method required
      $('#paymentMethod').attr('required', 'required');
      $('.payment-method-note').hide();
    }
  }
  
  // MODIFIED: Update paid amount based on discount
  function updatePaidAmount() {
    var total = parseFloat($('#totalAmount').val()) || 0;
    var discount = parseFloat($('#discountAmount').val()) || 0;
    
    // Validate discount cannot be more than total
    if (discount > total) {
      $('#discountAmount').val(total);
      discount = total;
    }
    
    // Calculate net amount after discount
    var netAmount = total - discount;
    
    // Set paid amount to net amount (so discount automatically reduces paid amount)
    $('#paidAmount').val(netAmount.toFixed(2));
    
    // Recalculate summary
    calculateSummary();
  }
  
  // Update due date based on selected month and year
  function updateDueDate() {
    var month = $('#monthNumber').val();
    var year = $('#year').val();
    var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                     'July', 'August', 'September', 'October', 'November', 'December'];
    
    if (month && year) {
      // Calculate last day of selected month
      var lastDay = new Date(year, month, 0).getDate();
      var dueDate = year + '-' + month.toString().padStart(2, '0') + '-' + lastDay;
      $('#dueDate').val(dueDate);
      
      // Update month display
      var display = monthNames[month - 1] + ' ' + year;
      $('#selectedMonthDisplay').text(display);
    }
  }
  
  // Load fee amount when fee type is selected
  function loadFeeAmount() {
    var feeTypeId = $('select[name="fee_type_id"]').val();
    
    if (feeTypeId && classFees[feeTypeId]) {
      var amount = classFees[feeTypeId];
      $('#totalAmount').val(amount);
      $('#discountAmount').val(0); // Reset discount
      updatePaidAmount(); // This will set paid amount = total amount
    }
  }
  
  // Bind fee type change event
  $('select[name="fee_type_id"]').on('change', loadFeeAmount);
  
  // MODIFIED: Bind discount input to update paid amount
  $('#discountAmount').on('input', updatePaidAmount);
  
  // Bind other input events
  $('#totalAmount, #paidAmount').on('input', calculateSummary);
  $('#monthNumber, #year').on('change', updateDueDate);
  
  // Initialize summary and due date
  calculateSummary();
  
  // Set default month to next month
  var today = new Date();
  var nextMonth = today.getMonth() + 2;
  var currentYear = today.getFullYear();
  var nextMonthYear = currentYear;
  
  if (nextMonth > 12) {
    nextMonth = 1;
    nextMonthYear++;
  }
  
  $('#monthNumber').val(nextMonth);
  $('#year').val(nextMonthYear);
  
  updateDueDate();
  
  // Form validation
  $('#advancePaymentForm').on('submit', function(e) {
    var total = parseFloat($('#totalAmount').val()) || 0;
    var discount = parseFloat($('#discountAmount').val()) || 0;
    var paid = parseFloat($('#paidAmount').val()) || 0;
    var netAmount = total - discount;
    
    if (paid < 0) {
      e.preventDefault();
      alert('Paid amount cannot be negative.');
      return false;
    }
    
    // Validate payment method only if paid amount > 0
    if (paid > 0 && !$('select[name="payment_method"]').val()) {
      e.preventDefault();
      alert('Please select a payment method for payments greater than 0.');
      return false;
    }
    
    if (!$('#monthNumber').val()) {
      e.preventDefault();
      alert('Please select a month.');
      return false;
    }
    
    if (!$('#year').val()) {
      e.preventDefault();
      alert('Please select a year.');
      return false;
    }
    
    // Confirmation for zero payments
    if (paid === 0 && netAmount > 0) {
      return confirm('You are recording a payment of Rs. 0 while the net amount is Rs. ' + netAmount.toFixed(2) + '. This will mark the fee as fully discounted. Continue?');
    } else if (paid === 0 && netAmount === 0) {
      return confirm('You are recording a fully waived fee with Rs. 0 payment. Continue?');
    }
    
    return true;
  });
});

// Select/Deselect all checkboxes
function toggleSelectAll() {
    var checkboxes = document.querySelectorAll('.transaction-checkbox');
    var selectAllBtn = document.getElementById('selectAllBtn');
    
    // Check if all are already selected
    var allSelected = true;
    checkboxes.forEach(function(checkbox) {
        if (!checkbox.checked) {
            allSelected = false;
        }
    });
    
    // Toggle selection
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = !allSelected;
    });
    
    // Update button text
    selectAllBtn.textContent = allSelected ? 'Select All' : 'Deselect All';
}

// Print single advance transaction
function printSingleAdvanceTransaction(id) {
    window.open('print_receipt.php?id=' + id + '&auto_print=1', '_blank');
}

// Print selected advance transactions
function printSelectedAdvanceTransactions() {
    var selectedIds = [];
    document.querySelectorAll('.transaction-checkbox:checked').forEach(function(checkbox) {
        selectedIds.push(checkbox.value);
    });
    
    if (selectedIds.length === 0) {
        alert('Please select at least one transaction to print.');
        return;
    }
    
    var idsParam = selectedIds.join(',');
    window.open('print_receipt.php?ids=' + idsParam + '&auto_print=1', '_blank');
}

// Open Edit Advance Modal with transaction data
function openEditAdvanceModal(transId, paidAmount, discountAmount, paymentMethod, transRef, remarks, paymentDate) {
  $('#edit_transaction_id').val(transId);
  $('#edit_original_amount').val(paidAmount.toFixed(2));
  $('#edit_original_discount').val(discountAmount.toFixed(2));
  $('#edit_new_amount').val(paidAmount.toFixed(2)); // Pre-fill with original
  $('#edit_new_discount').val(discountAmount.toFixed(2));
  $('#edit_payment_method').val(paymentMethod);
  $('#edit_transaction_ref').val(transRef);
  $('#edit_remarks').val(remarks);
  $('#edit_payment_date').val(paymentDate);
  
  // Update display
  updateEditAdvanceDisplay();
  
  // Show modal
  $('#editAdvanceModal').modal('show');
}

// Update edit modal display when amounts change
$('#edit_new_amount, #edit_new_discount').on('input', function() {
  updateEditAdvanceDisplay();
});

function updateEditAdvanceDisplay() {
  var origAmount = parseFloat($('#edit_original_amount').val()) || 0;
  var origDiscount = parseFloat($('#edit_original_discount').val()) || 0;
  var newAmount = parseFloat($('#edit_new_amount').val()) || 0;
  var newDiscount = parseFloat($('#edit_new_discount').val()) || 0;
  
  $('#edit_orig_display').text(origAmount.toFixed(2));
  $('#edit_new_display').text(newAmount.toFixed(2));
  
  var netChange = newAmount - origAmount;
  var netSpan = $('#edit_net_display');
  netSpan.text(netChange.toFixed(2));
  
  if (netChange > 0) {
    netSpan.removeClass('text-danger').addClass('text-success');
  } else if (netChange < 0) {
    netSpan.removeClass('text-success').addClass('text-danger');
  } else {
    netSpan.removeClass('text-success text-danger');
  }
}

// Validate edit form
$('#editAdvanceForm').on('submit', function(e) {
  var newAmount = parseFloat($('#edit_new_amount').val()) || 0;
  var newDiscount = parseFloat($('#edit_new_discount').val()) || 0;
  
  if (newAmount < 0) {
    e.preventDefault();
    alert('Paid amount cannot be negative.');
    return false;
  }
  
  if (newAmount > 0 && !$('#edit_payment_method').val()) {
    e.preventDefault();
    alert('Please select a payment method for payments greater than 0.');
    return false;
  }
  
  // Confirm reversal
  var origAmount = parseFloat($('#edit_original_amount').val()) || 0;
  if (newAmount !== origAmount || newDiscount !== parseFloat($('#edit_original_discount').val())) {
    var confirmMsg = 'This will REVERSE the original transaction and create a new one.\n\n';
    confirmMsg += 'Original: ' + origAmount.toFixed(2) + '\n';
    confirmMsg += 'New: ' + newAmount.toFixed(2) + '\n\n';
    confirmMsg += 'Continue?';
    
    if (!confirm(confirmMsg)) {
      e.preventDefault();
      return false;
    }
  }
  
  return true;
});

// Reset edit form when modal closes
$('#editAdvanceModal').on('hidden.bs.modal', function () {
  $('#editAdvanceForm')[0].reset();
});

// Confirm restore advance transaction (reverse accounting and restore fee card)
function confirmRestoreAdvance(transactionId) {
    if (confirm('⚠️ RESTORE ADVANCE TRANSACTION\n\nThis will:\n• Reverse the cash entry in accounting\n• Mark payment as "reversed"\n• Restore fee card to pending (if not fully paid)\n\nContinue?')) {
        window.location.href = 'advance_fee_payment.php?id=<?php echo $student_id; ?>&restore_advance=' + transactionId;
    }
}

</script>

</body>
</html>
<?php $conn->close(); ?>