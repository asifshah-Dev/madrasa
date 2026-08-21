<?php 
require_once('security.php');
require_once('conn_inc.php');

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Initialize variables
$student_info = null;
$current_class = null;
$student_class_id = null;
$fee_cards = [];
$class_fee_types = [];
$error_message = '';
$success_message = '';

if ($student_id > 0) {
    // Get student registration details
    $student_query = $conn->query("
        SELECT sr.*, vc.title as village_name 
        FROM student_registration sr 
        LEFT JOIN village_councils vc ON sr.village_council_id = vc.id 
        WHERE sr.id = $student_id
    ");
    
    if ($student_query) {
        $student_info = $student_query->fetch_assoc();
    }
    
    // Get current class (status = 0 means active)
    $class_query = $conn->query("
        SELECT sc.*, c.title as class_name, s.title as session_title, sc.session_id
        FROM student_class sc 
        INNER JOIN classes c ON sc.class_id = c.id 
        INNER JOIN sessions s ON sc.session_id = s.id 
        WHERE sc.student_registration_id = $student_id AND sc.status = 0 
        ORDER BY sc.id DESC LIMIT 1
    ");
    
    if ($class_query && $class_query->num_rows > 0) {
        $current_class = $class_query->fetch_assoc();
        $student_class_id = $current_class['id'];
        
        // Get fee cards for this student class (only pending ones with due amount > 0)
        $fee_card_query = $conn->query("
            SELECT sfc.*, ft.title as fee_type_title 
            FROM student_fee_card sfc 
            INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id 
            WHERE sfc.student_class_id = $student_class_id 
            AND sfc.status = 'pending'
            ORDER BY sfc.due_date ASC
        ");
        
        if ($fee_card_query) {
            while ($row = $fee_card_query->fetch_assoc()) {
                // Calculate paid amount and discount
                $payment_query = $conn->query("
                    SELECT COALESCE(SUM(paid_amount), 0) as total_paid, 
                           COALESCE(SUM(discount_amount), 0) as total_discount 
                    FROM student_fee_payments 
                    WHERE fee_card_id = " . $row['id'] . " AND status = 'completed'
                ");
                
                if ($payment_query) {
                    $payment_data = $payment_query->fetch_assoc();
                    $row['paid_amount'] = floatval($payment_data['total_paid']);
                    $row['total_discount'] = floatval($payment_data['total_discount']);
                } else {
                    $row['paid_amount'] = 0;
                    $row['total_discount'] = 0;
                }
                
                $row['due_amount'] = $row['total_amount'] - $row['paid_amount'] - $row['total_discount'];
                
                // Only include rows where due amount is greater than 0
                if ($row['due_amount'] > 0) {
                    $fee_cards[] = $row;
                }
            }
        }
        
        // Get fee types available for this class and session
        $fee_type_query = $conn->query("
            SELECT cft.*, ft.title as fee_type_title, ft.type
            FROM class_fee_types cft
            INNER JOIN fee_types ft ON cft.fee_type_id = ft.id
            WHERE cft.class_id = " . $current_class['class_id'] . " 
            AND cft.session_id = " . $current_class['session_id'] . "
            ORDER BY ft.title ASC
        ");
        
        if ($fee_type_query) {
            while ($row = $fee_type_query->fetch_assoc()) {
                $class_fee_types[] = $row;
            }
        }
    }
}

// Handle add new fee card
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_fee_card']) && $student_class_id) {
    $fee_type_id = intval($_POST['fee_type_id']);
    $amount = floatval($_POST['amount']);
    $month = mysqli_real_escape_string($conn, $_POST['month']); // Format: YYYY-MM
    $remarks = isset($_POST['remarks']) ? mysqli_real_escape_string($conn, $_POST['remarks']) : '';
    
    // Calculate due date as last day of selected month
    $due_date = date('Y-m-t', strtotime($month . '-01'));
    
    // Validate inputs
    $errors = [];
    if ($fee_type_id <= 0) $errors[] = "براہ کرم فیس کی قسم منتخب کریں";
    if ($amount <= 0) $errors[] = "رقم 0 سے زیادہ ہونی چاہیے";
    if (empty($month)) $errors[] = "براہ کرم مہینہ منتخب کریں";
    
    if (empty($errors)) {
        // Check if a fee card already exists for this fee type and month
        $check_query = $conn->query("
            SELECT id, status FROM student_fee_card 
            WHERE student_class_id = $student_class_id 
            AND fee_type_id = $fee_type_id 
            AND month = '$month'
        ");
        
        if ($check_query && $check_query->num_rows > 0) {
            $existing_row = $check_query->fetch_assoc();
            $existing_status = $existing_row['status'];
            $status_text = ($existing_status == 'pending') ? 'زیر التوا' : (($existing_status == 'paid') ? 'ادا شدہ' : 'حذف شدہ');
            $error_message = "یہ فیس اس مہینے کے لیے پہلے سے موجود ہے (حیثیت: $status_text)! ڈپلیکیٹ شامل نہیں کر سکتے۔";
        } else {
            // No duplicate found, proceed with insert
            $insert_sql = "
                INSERT INTO student_fee_card 
                (student_class_id, fee_type_id, total_amount, discount_amount, discount_type, 
                 discount_note, due_date, month, session_id, status, remarks, dated, paid_amount) 
                VALUES 
                ($student_class_id, $fee_type_id, $amount, 0, NULL, NULL, 
                 '$due_date', '$month', " . $current_class['session_id'] . ", 'pending', '$remarks', NOW(), 0)
            ";
            
            $insert_query = $conn->query($insert_sql);
            
            if ($insert_query) {
                header("Location: fee_collection.php?id=$student_id&add_success=1");
                exit();
            } else {
                $error_message = "فیس کارڈ شامل کرنے میں خرابی: " . $conn->error;
            }
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Handle delete fee card request
if (isset($_GET['delete_fee_card'])) {
    $card_id = intval($_GET['delete_fee_card']);
    
    // Check if there are any payments for this fee card
    $check_payments = $conn->query("SELECT COUNT(*) as count FROM student_fee_payments WHERE fee_card_id = $card_id");
    $payments_count = 0;
    if ($check_payments) {
        $payments_count = $check_payments->fetch_assoc()['count'];
    }
    
    if ($payments_count == 0) {
        // No payments, safe to delete
        $conn->query("DELETE FROM student_fee_card WHERE id = $card_id");
    } else {
        // Has payments, just update status to deleted
        $conn->query("UPDATE student_fee_card SET status = 'deleted' WHERE id = $card_id");
    }
    
    header("Location: fee_collection.php?id=$student_id&delete_success=1");
    exit();
}

// Handle restore transaction (delete transaction and restore fee card) with accounting reversal
if (isset($_GET['delete_transaction'])) {
    $transaction_id = intval($_GET['delete_transaction']);
    
    // Disable autocommit for transaction support
    $conn->autocommit(false);
    $transaction_success = true;
    
    try {
        // Fetch Payment Transaction Details
        $trans_sql = "
            SELECT sfp.*, sfc.student_class_id, sfc.fee_type_id, sfc.total_amount as card_total
            FROM student_fee_payments sfp
            INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
            WHERE sfp.id = $transaction_id AND sfp.status = 'completed'
        ";
        $trans_result = $conn->query($trans_sql);
        
        if (!$trans_result || $trans_result->num_rows === 0) {
            throw new Exception("ٹرانزیکشن نہیں ملی یا پہلے ہی بحال ہو چکی ہے۔");
        }
        
        $trans_data = $trans_result->fetch_assoc();
        $fee_card_id = intval($trans_data['fee_card_id']);
        $paid_amount = floatval($trans_data['paid_amount']);
        $discount_amount = floatval($trans_data['discount_amount']);
        $card_total = floatval($trans_data['card_total']);
        
        // Handle cash account reversal if payment was made
        if ($paid_amount > 0) {
            $cash_account_title = 'Main Account';
            $master_sql = "SELECT id, title, balance FROM master_account WHERE title = '$cash_account_title' FOR UPDATE";
            $master_result = $conn->query($master_sql);
            
            if (!$master_result || $master_result->num_rows === 0) {
                throw new Exception("کیش اکاؤنٹ نہیں ملا۔");
            }
            
            $master_account = $master_result->fetch_assoc();
            $master_account_id = $master_account['id'];
            $current_balance = floatval($master_account['balance']);
            
            // Insert reversal entry
            $reversal_amount = -$paid_amount;
            $reversal_balance = $current_balance + $reversal_amount;
            
            $reversal_sql = "
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, created_at) 
                VALUES 
                ($master_account_id, 'cash out', $reversal_amount, $reversal_balance, 
                 $fee_card_id, 'fee_restore_reversal', NOW())
            ";
            
            if (!$conn->query($reversal_sql)) {
                throw new Exception("ریورسل انٹری شامل کرنے میں ناکامی: " . $conn->error);
            }
            
            // Update master account balance
            $update_sql = "UPDATE master_account SET balance = $reversal_balance WHERE id = $master_account_id";
            if (!$conn->query($update_sql)) {
                throw new Exception("ماسٹر اکاؤنٹ بیلنس اپ ڈیٹ کرنے میں ناکامی: " . $conn->error);
            }
        }
        
        // Mark payment as reversed
        $restore_note = "Restored on " . date('Y-m-d H:i:s');
        $update_payment_sql = "
            UPDATE student_fee_payments 
            SET status = 'reversed', 
                remarks = CONCAT(IFNULL(remarks, ''), ' | Restored on: ', '$restore_note')
            WHERE id = $transaction_id
        ";
        
        if (!$conn->query($update_payment_sql)) {
            throw new Exception("ادائیگی کو ریورسڈ کے طور پر نشان زد کرنے میں ناکامی: " . $conn->error);
        }
        
        // Check remaining payments on this card
        $remaining_sql = "
            SELECT COALESCE(SUM(paid_amount), 0) as paid, 
                   COALESCE(SUM(discount_amount), 0) as disc
            FROM student_fee_payments
            WHERE fee_card_id = $fee_card_id AND status = 'completed'
        ";
        $remaining_result = $conn->query($remaining_sql);
        
        if ($remaining_result) {
            $remaining = $remaining_result->fetch_assoc();
            $total_paid = floatval($remaining['paid']);
            $total_disc = floatval($remaining['disc']);
            
            if ($total_paid + $total_disc >= $card_total) {
                $conn->query("UPDATE student_fee_card SET status = 'paid' WHERE id = $fee_card_id");
            } else {
                $conn->query("UPDATE student_fee_card SET status = 'pending' WHERE id = $fee_card_id");
            }
        }
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        header("Location: fee_collection.php?id=$student_id&restore_success=1");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->autocommit(true);
        
        error_log("Fee restore error (Transaction ID: $transaction_id): " . $e->getMessage());
        $error_message = "بحالی ناکام: " . $e->getMessage();
    }
}

// Handle fee payment submission with transaction support
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment']) && $student_class_id) {
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $transaction_ref = mysqli_real_escape_string($conn, $_POST['transaction_ref']);
    $payment_remarks = mysqli_real_escape_string($conn, $_POST['payment_remarks']);
    $payment_date = date('Y-m-d H:i:s');
    
    // Disable autocommit
    $conn->autocommit(false);
    $transaction_success = true;
    
    try {
        $total_payment = 0;
        $total_discount = 0;
        $processed_ids = [];
        $processed_cards = [];
        
        // Get cash account for accounting entries
        $cash_account_title = 'Main Account';
        $master_sql = "SELECT id, title, balance FROM master_account WHERE title = '$cash_account_title' FOR UPDATE";
        $master_result = $conn->query($master_sql);
        
        if (!$master_result || $master_result->num_rows === 0) {
            throw new Exception("کیش اکاؤنٹ '$cash_account_title' نہیں ملا۔");
        }
        
        $master_account = $master_result->fetch_assoc();
        $master_account_id = $master_account['id'];
        $current_balance = floatval($master_account['balance']);
        
        // Process each fee card payment
        foreach ($_POST['payment'] as $card_id => $amount) {
            $card_id = intval($card_id);
            $paid_amount = floatval($amount);
            $discount = floatval($_POST['discount'][$card_id] ?? 0);
            $discount_note = mysqli_real_escape_string($conn, $_POST['discount_note'][$card_id] ?? '');
            
            if ($paid_amount <= 0 && $discount <= 0) {
                continue;
            }
            
            // Validate fee card
            $card_sql = "
                SELECT sfc.*, ft.title as fee_type_title 
                FROM student_fee_card sfc 
                INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id 
                WHERE sfc.id = $card_id AND sfc.student_class_id = $student_class_id AND sfc.status = 'pending'
            ";
            $card_result = $conn->query($card_sql);
            
            if (!$card_result || $card_result->num_rows === 0) {
                throw new Exception("غلط یا پہلے سے پراسیس شدہ فیس کارڈ ID: $card_id");
            }
            
            $fee_card = $card_result->fetch_assoc();
            
            // Calculate remaining due
            $already_paid_sql = "
                SELECT COALESCE(SUM(paid_amount), 0) as paid, 
                       COALESCE(SUM(discount_amount), 0) as disc 
                FROM student_fee_payments 
                WHERE fee_card_id = $card_id AND status = 'completed'
            ";
            $already_paid_result = $conn->query($already_paid_sql);
            
            if ($already_paid_result) {
                $already_paid = $already_paid_result->fetch_assoc();
                $already_paid_amount = floatval($already_paid['paid']);
                $already_discount_amount = floatval($already_paid['disc']);
            } else {
                $already_paid_amount = 0;
                $already_discount_amount = 0;
            }
            
            $remaining_due = $fee_card['total_amount'] - $already_paid_amount - $already_discount_amount;
            
            if (($paid_amount + $discount) > $remaining_due) {
                throw new Exception("ادائیگی + رعایت بقایا رقم سے زیادہ ہے برائے فیس کارڈ: {$fee_card['fee_type_title']}");
            }
            
            // Insert payment record
            $discount_note_escaped = $conn->real_escape_string($discount_note);
            $payment_ref_escaped = $conn->real_escape_string($transaction_ref);
            $remarks_escaped = $conn->real_escape_string($payment_remarks);
            
            $insert_sql = "
                INSERT INTO student_fee_payments 
                (fee_card_id, paid_amount, discount_amount, discount_type, discount_note, 
                 payment_date, payment_method, transaction_ref, remarks, status, is_advance) 
                VALUES 
                ($card_id, $paid_amount, $discount, 'fixed', '$discount_note_escaped', 
                 '$payment_date', '$payment_method', '$payment_ref_escaped', 
                 '$remarks_escaped', 'completed', 0)
            ";
            
            if (!$conn->query($insert_sql)) {
                throw new Exception("ادائیگی کا ریکارڈ شامل کرنے میں ناکامی: " . $conn->error);
            }
            
            $payment_id = $conn->insert_id;
            $processed_ids[] = $payment_id;
            $total_payment += $paid_amount;
            $total_discount += $discount;
            
            $processed_cards[] = [
                'card_id' => $card_id,
                'fee_type' => $fee_card['fee_type_title'],
                'paid' => $paid_amount,
                'discount' => $discount,
                'total_amount' => $fee_card['total_amount'],
                'month' => $fee_card['month']
            ];
            
            // Update fee card status if fully paid
            $new_total_paid = $already_paid_amount + $paid_amount;
            $new_total_discount = $already_discount_amount + $discount;
            
            if ($new_total_paid + $new_total_discount >= $fee_card['total_amount']) {
                $conn->query("UPDATE student_fee_card SET status = 'paid' WHERE id = $card_id");
            }
        }
        
        // Update master account and insert detail account entries
        if ($total_payment > 0) {
            $new_balance = $current_balance + $total_payment;
            
            foreach ($processed_cards as $card) {
                if ($card['paid'] > 0) {
                    $detail_sql = "
                        INSERT INTO detail_account 
                        (master_account_id, type, amount, balance, ref_id, ref_type, created_at) 
                        VALUES 
                        ($master_account_id, 'cash in', {$card['paid']}, $new_balance, 
                         {$card['card_id']}, 'fee', NOW())
                    ";
                    
                    if (!$conn->query($detail_sql)) {
                        throw new Exception("ڈیٹیل اکاؤنٹ انٹری شامل کرنے میں ناکامی: " . $conn->error);
                    }
                }
            }
            
            $update_master_sql = "UPDATE master_account SET balance = $new_balance WHERE id = $master_account_id";
            if (!$conn->query($update_master_sql)) {
                throw new Exception("ماسٹر اکاؤنٹ بیلنس اپ ڈیٹ کرنے میں ناکامی: " . $conn->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        // Redirect on success
        $receipt_id = "REC-" . date('Ymd') . "-" . rand(1000, 9999);
        $ids_param = implode(',', $processed_ids);
        
        header("Location: fee_collection.php?id=$student_id&success=1&receipt=$receipt_id&amount=$total_payment&discount=$total_discount&ids=$ids_param");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->autocommit(true);
        
        error_log("Fee payment error (Student ID: $student_id): " . $e->getMessage());
        $error_message = "ادائیگی ناکام: " . $e->getMessage();
    }
}

// Handle Edit Fee Transaction (Reversal Method)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_fee_transaction'])) {
    $transaction_id = intval($_POST['transaction_id']);
    $new_paid_amount = floatval($_POST['new_paid_amount']);
    $new_discount = floatval($_POST['new_discount']);
    $new_payment_method = mysqli_real_escape_string($conn, $_POST['new_payment_method']);
    $new_transaction_ref = mysqli_real_escape_string($conn, $_POST['new_transaction_ref']);
    $new_remarks = mysqli_real_escape_string($conn, $_POST['new_remarks']);
    $payment_date = date('Y-m-d H:i:s');
    
    // Disable autocommit
    $conn->autocommit(false);
    
    try {
        // Validate original transaction
        $orig_sql = "
            SELECT sfp.*, sfc.student_class_id, sfc.fee_type_id, sfc.total_amount as card_total
            FROM student_fee_payments sfp
            INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
            WHERE sfp.id = $transaction_id AND sfp.status = 'completed'
        ";
        $orig_result = $conn->query($orig_sql);
        
        if (!$orig_result || $orig_result->num_rows === 0) {
            throw new Exception("اصل ٹرانزیکشن نہیں ملی یا پہلے ہی ریورس ہو چکی ہے۔");
        }
        
        $original = $orig_result->fetch_assoc();
        $old_paid = floatval($original['paid_amount']);
        $old_discount = floatval($original['discount_amount']);
        $fee_card_id = intval($original['fee_card_id']);
        $card_total = floatval($original['card_total']);
        
        // Calculate cash difference
        $cash_difference = $new_paid_amount - $old_paid;
        $needs_accounting = ($cash_difference != 0);
        
        // Handle cash account if needed
        if ($needs_accounting) {
            $cash_account_title = 'Main Account';
            $master_sql = "SELECT id, title, balance FROM master_account WHERE title = '$cash_account_title' FOR UPDATE";
            $master_result = $conn->query($master_sql);
            
            if (!$master_result || $master_result->num_rows === 0) {
                throw new Exception("کیش اکاؤنٹ نہیں ملا۔");
            }
            
            $master_account = $master_result->fetch_assoc();
            $master_account_id = $master_account['id'];
            $current_balance = floatval($master_account['balance']);
            
            // Reverse old transaction
            $reversal_amount = -$old_paid;
            $reversal_balance = $current_balance + $reversal_amount;
            
            $reversal_sql = "
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, created_at) 
                VALUES 
                ($master_account_id, 'cash out', $reversal_amount, $reversal_balance, 
                 $fee_card_id, 'fee_edit_reversal', NOW())
            ";
            
            if (!$conn->query($reversal_sql)) {
                throw new Exception("ریورسل انٹری شامل کرنے میں ناکامی: " . $conn->error);
            }
            
            // Update master account after reversal
            $conn->query("UPDATE master_account SET balance = $reversal_balance WHERE id = $master_account_id");
        }
        
        // Mark original payment as reversed
        $edit_note = "Edited on " . date('Y-m-d H:i:s');
        $conn->query("
            UPDATE student_fee_payments 
            SET status = 'reversed', 
                remarks = CONCAT(IFNULL(remarks, ''), ' | Reversed on edit: ', '$edit_note')
            WHERE id = $transaction_id
        ");
        
        // Insert new corrected transaction
        $new_trans_ref_escaped = $conn->real_escape_string($new_transaction_ref);
        $new_remarks_escaped = $conn->real_escape_string($new_remarks);
        
        $insert_new_sql = "
            INSERT INTO student_fee_payments 
            (fee_card_id, paid_amount, discount_amount, discount_type, discount_note, 
             payment_date, payment_method, transaction_ref, remarks, status, is_advance) 
            VALUES 
            ($fee_card_id, $new_paid_amount, $new_discount, 'fixed', '', 
             '$payment_date', '$new_payment_method', '$new_trans_ref_escaped', 
             '$new_remarks_escaped', 'completed', 0)
        ";
        
        if (!$conn->query($insert_new_sql)) {
            throw new Exception("نئی ادائیگی کا ریکارڈ شامل کرنے میں ناکامی: " . $conn->error);
        }
        
        $new_payment_id = $conn->insert_id;
        
        // Insert new detail account entry if cash changed
        if ($needs_accounting) {
            $new_balance_after = $reversal_balance + $new_paid_amount;
            
            $new_detail_sql = "
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, created_at) 
                VALUES 
                ($master_account_id, 'cash in', $new_paid_amount, $new_balance_after, 
                 $fee_card_id, 'fee', NOW())
            ";
            
            if (!$conn->query($new_detail_sql)) {
                throw new Exception("نئی ڈیٹیل اکاؤنٹ انٹری شامل کرنے میں ناکامی: " . $conn->error);
            }
            
            // Update master account final balance
            $conn->query("UPDATE master_account SET balance = $new_balance_after WHERE id = $master_account_id");
        }
        
        // Update fee card status
        $total_paid_sql = "
            SELECT COALESCE(SUM(paid_amount), 0) as paid, 
                   COALESCE(SUM(discount_amount), 0) as disc
            FROM student_fee_payments
            WHERE fee_card_id = $fee_card_id AND status = 'completed'
        ";
        $total_paid_result = $conn->query($total_paid_sql);
        
        if ($total_paid_result) {
            $total_paid_data = $total_paid_result->fetch_assoc();
            $final_paid = floatval($total_paid_data['paid']);
            $final_disc = floatval($total_paid_data['disc']);
            
            if ($final_paid + $final_disc >= $card_total) {
                $conn->query("UPDATE student_fee_card SET status = 'paid' WHERE id = $fee_card_id");
            } else {
                $conn->query("UPDATE student_fee_card SET status = 'pending' WHERE id = $fee_card_id");
            }
        }
        
        // Commit transaction
        $conn->commit();
        $conn->autocommit(true);
        
        header("Location: fee_collection.php?id=$student_id&edit_success=1&trans_id=$new_payment_id");
        exit();
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $conn->autocommit(true);
        
        error_log("Fee edit error (Transaction ID: $transaction_id): " . $e->getMessage());
        $error_message = "ترمیم ناکام: " . $e->getMessage();
    }
}

// Get recent transactions
$recent_transactions = [];
if ($student_class_id) {
    $trans_query = $conn->query("
        SELECT sfp.*, sfc.due_date, ft.title as fee_type_title, sfc.id as fee_card_id, sfc.month
        FROM student_fee_payments sfp 
        INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id 
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id 
        WHERE sfc.student_class_id = $student_class_id 
        AND sfp.status != 'reversed'
        ORDER BY sfp.id DESC LIMIT 20
    ");
    
    if ($trans_query) {
        while ($row = $trans_query->fetch_assoc()) {
            $recent_transactions[] = $row;
        }
    }
}

// Function to get month-year from due date (format: Jan-26)
function getMonthYearFromDueDate($due_date) {
    $timestamp = strtotime($due_date);
    return date('M-y', $timestamp);
}

// Function to format due date (format: 15 Jan 2025)
function formatDueDate($due_date) {
    return date('d M Y', strtotime($due_date));
}

// Updated function to generate month options with both month and year
function getMonthOptions($selected = '') {
    $months = [];
    $current_year = date('Y');
    $current_month = date('m');
    
    // Urdu month names
    $urdu_months = [
        '01' => 'جنوری', '02' => 'فروری', '03' => 'مارچ', '04' => 'اپریل',
        '05' => 'مئی', '06' => 'جون', '07' => 'جولائی', '08' => 'اگست',
        '09' => 'ستمبر', '10' => 'اکتوبر', '11' => 'نومبر', '12' => 'دسمبر'
    ];
    
    // Generate next 24 months (for fee due dates)
    for ($i = 0; $i < 24; $i++) {
        $timestamp = mktime(0, 0, 0, $current_month + $i, 1, $current_year);
        $month_num = date('m', $timestamp);
        $year = date('Y', $timestamp);
        $value = date('Y-m', $timestamp); // Store as YYYY-MM for value
        $label = $urdu_months[$month_num] . ' ' . $year; // Display as "جنوری 2026"
        $selected_attr = ($selected == $value) ? 'selected' : '';
        echo "<option value=\"$value\" $selected_attr>$label</option>";
    }
}

// Calculate total due for the payment summary
$total_due = 0;
foreach ($fee_cards as $card) {
    $total_due += $card['due_amount'];
}
?>

<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
  <title>فیس جمع - مدرسہ مینجمنٹ سسٹم</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Bootstrap CSS & JS - Using standard Bootstrap with RTL support -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Select2 for better dropdowns -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  
  <link rel="stylesheet" href="css/mystyle.css" />
  
  <style>
    /* RTL Base Override */
    body {
      direction: rtl;
      text-align: right;
    }
    
    .student-profile-header {
      background: white;
      color: black;
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      text-align: right;
    }
    
    .student-profile-header h2 {
      font-size: 1.3rem;
      margin-bottom: 0.2rem;
    }
    
    .student-profile-header h5 {
      font-size: 0.9rem;
      margin-bottom: 0.2rem;
      opacity: 0.9;
    }
    
    .profile-info-badge {
      padding: 3px 8px;
      border-radius: 15px;
      background: rgba(255,255,255,0.15);
      margin-left: 8px;
      margin-right: 0;
      display: inline-block;
      font-size: 0.7rem;
      margin-bottom: 2px;
    }
    
    .profile-info-badge i {
      margin-left: 3px;
      margin-right: 0;
      font-size: 0.65rem;
    }
    
    .fee-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .fee-table thead th {
      background: #3b3a85;
      color: white;
      border-bottom: none;
      padding: 12px 8px;
      font-size: 13px;
      font-weight: 500;
      white-space: nowrap;
      text-align: right;
    }
    
    .fee-table tbody tr {
      transition: all 0.3s ease;
    }
    
    .fee-table tbody tr:hover {
      background: #f8f9fa;
    }
    
    .fee-table tbody td {
      padding: 10px 8px;
      font-size: 13px;
      vertical-align: middle;
      text-align: right;
    }
    
    .fee-table tfoot tr {
      background: #e8f5e9;
      font-weight: 600;
      border-top: 2px solid #dee2e6;
    }
    
    .fee-table tfoot td {
      padding: 10px 8px;
      font-size: 13px;
      font-weight: 600;
    }
    
    .amount-input {
      font-size: 12px;
      padding: 6px 8px;
      border: 2px solid #e0e0e0;
      border-radius: 4px;
      transition: all 0.3s ease;
      width: 85px;
      height: 32px;
    }
    
    .amount-input:focus {
      border-color: #4285F4;
      box-shadow: 0 0 0 3px rgba(66,133,244,0.1);
    }
    
    .discount-input:disabled {
      background-color: #e9ecef;
      opacity: 0.6;
      cursor: not-allowed;
    }
    
    .summary-card {
      background: #3b3a85;
      color: white;
      padding: 0;
      border-radius: 10px;
      margin-bottom: 25px;
      border: none;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .summary-card .card-header {
      background: rgba(0,0,0,0.1);
      border-bottom: 1px solid rgba(255,255,255,0.2);
      padding: 12px 20px;
      border-top-left-radius: 10px;
      border-top-right-radius: 10px;
    }
    
    .summary-card .card-header h5 {
      font-size: 16px;
      font-weight: 600;
      margin: 0;
    }
    
    .summary-card .card-body {
      padding: 20px;
    }
    
    .transaction-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .transaction-table thead th {
      background: #f8f9fa;
      color: #495057;
      border-bottom: 2px solid #dee2e6;
      padding: 12px 10px;
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
      text-align: right;
    }
    
    .transaction-table tbody td {
      padding: 10px;
      font-size: 13px;
      vertical-align: middle;
      text-align: right;
    }
    
    .receipt-btn {
      background: none;
      border: 1px solid #4285F4;
      color: #4285F4;
      padding: 4px 8px;
      border-radius: 4px;
      transition: all 0.3s ease;
      font-size: 12px;
    }
    
    .receipt-btn:hover {
      background: #4285F4;
      color: white;
    }
    
    .badge-pending {
      background: #ffc107;
      color: #000;
      font-size: 11px;
      padding: 4px 8px;
    }
    
    .badge-paid {
      background: #28a745;
      color: #fff;
      font-size: 11px;
      padding: 4px 8px;
    }
    
    .transport-badge {
      background: #ffc107;
      color: #000;
      padding: 3px 8px;
      border-radius: 15px;
      font-weight: 600;
      font-size: 0.7rem;
    }
    
    .cleared-badge {
      background: #28a745;
      color: white;
      padding: 4px 8px;
      border-radius: 20px;
      font-weight: 500;
      display: inline-block;
      font-size: 11px;
    }
    
    .cleared-badge i {
      margin-left: 4px;
      margin-right: 0;
      font-size: 10px;
    }
    
    .balance-amount {
      font-weight: 600;
      font-size: 13px;
    }
    
    .amount-due {
      color: #dc3545;
      font-weight: 600;
      font-size: 13px;
    }
    
    .amount-paid {
      color: #28a745;
      font-weight: 600;
      font-size: 13px;
    }
    
    .payment-summary-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid rgba(255,255,255,0.15);
    }
    
    .payment-summary-item:last-child {
      border-bottom: none;
    }
    
    .payment-summary-label {
      font-size: 13px;
      font-weight: 500;
    }
    
    .payment-summary-value {
      font-size: 18px;
      font-weight: 700;
    }
    
    .month-badge {
      background: #e9ecef;
      color: #495057;
      padding: 4px 8px;
      border-radius: 15px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
      margin-left: 5px;
      margin-right: 0;
    }
    
    .container-custom {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .due-date-badge {
      color: #6c757d;
      padding: 2px 6px;
      font-size: 10px;
      font-weight: 500;
    }
    
    .due-date-badge.overdue {
      color: #dc3545;
      font-weight: 600;
    }
    
    .discount-section {
      background: #fff3cd;
      border-right: 4px solid #ffc107;
      border-left: none;
      padding: 8px 12px;
      margin-bottom: 15px;
      font-size: 12px;
      border-radius: 4px;
    }
    
    .discount-note-input {
      font-size: 11px;
      padding: 4px 6px;
      margin-top: 5px;
      width: 100px;
    }
    
    .select-all-row {
      background: #e9ecef;
      padding: 10px;
      border-bottom: 1px solid #dee2e6;
    }
    
    .checkbox-col {
      width: 40px;
      text-align: center;
    }
    
    .print-selected-btn {
      margin-right: 10px;
      margin-left: 0;
      font-size: 12px;
      padding: 4px 10px;
    }
    
    .transaction-checkbox {
      cursor: pointer;
      width: 16px;
      height: 16px;
    }
    
    .delete-btn {
      color: #dc3545;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
    }
    
    .delete-btn:hover {
      color: #c82333;
      transform: scale(1.1);
    }
    
    .delete-transaction-btn {
      color: #dc3545;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      background: none;
      border: none;
    }
    
    .delete-transaction-btn:hover {
      color: #c82333;
      transform: scale(1.1);
    }
    
    .action-col {
      width: 35px;
      text-align: center;
    }
    
    .month-date-cell {
      min-width: 140px;
    }
    
    /* Modified column widths */
    .col-md-8 {
      flex: 0 0 auto;
      width: 80%;
    }
    
    .col-md-4 {
      flex: 0 0 auto;
      width: 20%;
    }
    
    /* Payment form styling */
    .payment-form-label {
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 4px;
      color: #495057;
    }
    
    .payment-form-control {
      font-size: 13px;
      padding: 6px 10px;
      border-radius: 4px;
    }
    
    .btn-process {
      font-size: 14px;
      padding: 10px;
      font-weight: 600;
    }
    
    /* Recent transactions header */
    .transactions-header {
      background: #6c757d;
      color: white;
      padding: 12px 20px;
      border-top-left-radius: 10px;
      border-top-right-radius: 10px;
    }
    
    .transactions-header h5 {
      font-size: 16px;
      font-weight: 600;
      margin: 0;
    }
    
    .transactions-header .btn-light {
      font-size: 12px;
      padding: 4px 10px;
    }
    
    /* Text colors */
    .text-amount {
      font-weight: 600;
      font-size: 13px;
    }
    
    .fw-bold {
      font-weight: 600 !important;
    }
    
    /* Card header styling */
    .card-header.bg-dark {
      background: #ffffff !important;
      padding: 12px 20px;
    }
    
    .card-header.bg-dark h5 {
      font-size: 16px;
      color: black;
      font-weight: 600;
      margin: 0;
    }
    
    .card-header.bg-secondary {
      background: #3b3a85 !important;
      padding: 12px 20px;
    }
    
    .card-header.bg-secondary h5 {
      font-size: 16px;
      font-weight: 600;
      margin: 0;
    }
    
    /* Responsive styles */
    @media (max-width: 991px) {
      .col-md-8, .col-md-4 {
        width: 100%;
        flex: 0 0 100%;
      }
    }
  </style>
</head>
<body>
<div class="container-custom">
   
  <?php if (isset($_GET['edit_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> فیس ٹرانزیکشن کامیابی سے ترمیم ہو گئی!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <?php if (!empty($_GET['trans_id'])): ?>
    <button onclick="window.open('print_receipt.php?id=<?php echo $_GET['trans_id']; ?>&auto_print=1', '_blank')" class="btn btn-sm btn-success float-end">
      <i class="fas fa-print"></i> نئی رسید پرنٹ کریں
    </button>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle"></i> ادائیگی کامیابی سے پراسیس ہو گئی! 
      رقم: <?php echo number_format($_GET['amount'], 2); ?> | 
      رعایت: <?php echo number_format($_GET['discount'] ?? 0, 2); ?> | 
      رسید نمبر: <?php echo $_GET['receipt']; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <?php if (!empty($_GET['ids'])): ?>
      <button onclick="window.open('print_receipt.php?ids=<?php echo $_GET['ids']; ?>&auto_print=1', '_blank')" class="btn btn-sm btn-success float-end">
        <i class="fas fa-print"></i> رسید پرنٹ کریں
      </button>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['delete_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle"></i> فیس کارڈ کامیابی سے حذف کر دیا گیا!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['add_success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="fas fa-check-circle"></i> فیس کارڈ کامیابی سے شامل کر دیا گیا!
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['restore_success'])): ?>
  <div class="alert alert-success alert-dismissible fade show restore-message">
    <i class="fas fa-undo-alt"></i> ٹرانزیکشن کامیابی سے بحال! کیش اکاؤنٹ ریورس اور فیس کارڈ بحال کر دیا گیا۔
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>
  
  <?php if (!empty($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  
  <?php if ($student_info): ?>
  
  <!-- Compact Student Profile Header -->
  <div class="student-profile-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <h2><i class="fas fa-user-graduate me-1"></i><?php echo htmlspecialchars($student_info['name']); ?>
              <small style="font-size:11px !important;">ولد: <?php echo htmlspecialchars($student_info['father_name']); ?></small>
        </h2>
      </div>
      <div class="mt-2 mt-sm-0">
        <a href="advance_fee_payment.php?id=<?php echo $student_id; ?>" class="btn btn-warning btn-sm py-1 px-2 me-1" style="font-size:0.75rem;color:white;">
          <i class="fas fa-forward"></i> پیشگی
        </a>
         <a href="index.php" class="btn btn-success btn-sm py-1 px-2" style="font-size:0.75rem;">
          <i class="fas fa-home"></i> ڈیش بورڈ
        </a>
      </div>
    </div>
    
    <div class="mt-2 d-flex flex-wrap align-items-center">
      <span class="profile-info-badge">
        <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student_info['reg_no']); ?>
      </span>
      
      <?php if ($current_class): ?>
      <span class="profile-info-badge">
        <i class="fas fa-book"></i> <?php echo htmlspecialchars($current_class['class_name']); ?>
      </span>
      <span class="profile-info-badge">
        <i class="fas fa-calendar"></i> <?php echo htmlspecialchars($current_class['session_title']); ?>
      </span>
      <?php endif; ?>
      
      <span class="profile-info-badge">
        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($student_info['mobile'] ?? 'دستیاب نہیں'); ?>
      </span>
      
      <span class="profile-info-badge">
        <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($student_info['guardian_name'] ?? 'دستیاب نہیں'); ?>
      </span>
    </div>
  </div>
  
  <?php if ($current_class): ?>
  
  <!-- Add Fee Card Modal -->
  <div class="modal fade" id="addFeeModal" tabindex="-1" aria-labelledby="addFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addFeeModalLabel">
            <i class="fas fa-plus-circle me-2"></i>نیا فیس کارڈ شامل کریں
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="" id="addFeeForm">
          <div class="modal-body">
            <p class="text-muted small"><?php echo htmlspecialchars($current_class['class_name']); ?> - <?php echo htmlspecialchars($current_class['session_title']); ?> کے لیے نیا فیس کارڈ بنائیں</p>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="modal-form-label">فیس کی قسم *</label>
                <select name="fee_type_id" id="fee_type_id" class="form-select modal-form-select" required>
                  <option value="">فیس کی قسم منتخب کریں</option>
                  <?php foreach ($class_fee_types as $fee_type): ?>
                  <option value="<?php echo $fee_type['fee_type_id']; ?>" data-amount="<?php echo $fee_type['amount']; ?>">
                    <?php echo htmlspecialchars($fee_type['fee_type_title']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">رقم *</label>
                <input type="number" name="amount" id="amount" class="form-control modal-form-control" step="0.01" min="0" required>
              </div>
              <div class="col-md-12">
                <label class="modal-form-label">مہینہ *</label>
                <select name="month" id="month" class="form-select modal-form-select" required>
                  <option value="">مہینہ منتخب کریں</option>
                  <?php getMonthOptions(); ?>
                </select>
                <div id="dueDateDisplay" class="due-date-message"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times"></i> منسوخ
            </button>
            <button type="submit" name="add_fee_card" class="btn btn-primary">
              <i class="fas fa-save"></i> فیس کارڈ محفوظ کریں
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Fee Transaction Modal -->
  <div class="modal fade" id="editFeeModal" tabindex="-1" aria-labelledby="editFeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header" style="background: #ffc107;">
          <h5 class="modal-title" id="editFeeModalLabel">
            <i class="fas fa-edit me-2"></i>فیس ٹرانزیکشن میں ترمیم (ریورسل طریقہ)
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" action="" id="editFeeForm">
          <div class="modal-body">
            <input type="hidden" name="transaction_id" id="edit_transaction_id" value="">
            
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle"></i> 
              <strong>ریورسل طریقہ:</strong> یہ اصل ٹرانزیکشن کو ریورس کرے گا اور نئی ٹرانزیکشن بنائے گا۔ 
              اصل ٹرانزیکشن "ریورسڈ" کے طور پر نشان زد ہو جائے گی۔
            </div>
            
            <div class="row g-3">
              <div class="col-md-6">
                <label class="modal-form-label">اصل رقم *</label>
                <input type="text" id="edit_original_amount" class="form-control modal-form-control" readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">اصل رعایت *</label>
                <input type="text" id="edit_original_discount" class="form-control modal-form-control" readonly disabled>
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">نئی ادائیگی کی رقم *</label>
                <input type="number" name="new_paid_amount" id="edit_new_amount" class="form-control modal-form-control" step="0.01" min="0" required>
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">نئی رعایت *</label>
                <input type="number" name="new_discount" id="edit_new_discount" class="form-control modal-form-control" step="0.01" min="0" value="0">
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">ادائیگی کا طریقہ *</label>
                <select name="new_payment_method" id="edit_payment_method" class="form-select modal-form-select" required>
                  <option value="">طریقہ منتخب کریں</option>
                  <option value="cash" selected>نقد</option>
                  <option value="bank_transfer">بینک ٹرانسفر</option>
                  <option value="cheque">چیک</option>
                  <option value="online">آن لائن ادائیگی</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="modal-form-label">ٹرانزیکشن حوالہ</label>
                <input type="text" name="new_transaction_ref" id="edit_transaction_ref" class="form-control modal-form-control" placeholder="حوالہ نمبر (اختیاری)">
              </div>
              <div class="col-md-12">
                <label class="modal-form-label">ریمارکس</label>
                <textarea name="new_remarks" id="edit_remarks" class="form-control modal-form-control" rows="2" placeholder="ترمیم کے ریمارکس (اختیاری)"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times"></i> منسوخ
            </button>
            <button type="submit" name="edit_fee_transaction" class="btn btn-warning">
              <i class="fas fa-sync-alt"></i> ریورس اور ترمیم کریں
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <!-- Fee Payment Form -->
  <form method="POST" action="" id="feePaymentForm">
    <div class="row">
      <div class="col-md-8">
        <div class="card mb-4">
          <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-money-check-alt"></i> زیر التوا فیس کارڈز</h5>
            <button type="button" class="btn-add-fee" data-bs-toggle="modal" data-bs-target="#addFeeModal">
              <i class="fas fa-plus"></i> نئی فیس شامل کریں
            </button>
          </div>
          <div class="card-body p-0">
            <?php if (empty($fee_cards)): ?>
              <div class="alert alert-info m-3">
                <i class="fas fa-info-circle"></i> اس طالب علم کے لیے کوئی زیر التوا فیس کارڈ نہیں ملا۔ 
                فیس کارڈ بنانے کے لیے اوپر "نئی فیس شامل کریں" کے بٹن پر کلک کریں۔
              </div>
            <?php else: ?>
            <div class="table-responsive">
              <table class="table fee-table mb-0">
                <thead>
                  <tr>
                    <th class="action-col"></th>
                    <th>قسم</th>
                    <th>مہینہ (آخری تاریخ)</th>
                    <th>کل</th>
                    <th>ادا</th>
                    <th>رعایت</th>
                    <th>بقایا</th>
                    <th>ادائیگی</th>
                    <th>رعایت شامل</th>
                    <th>بیلنس</th>
                  </tr>
                </thead>
                <tbody>
                  <?php 
                  $total_fee = 0;
                  $total_paid = 0;
                  $total_discount_given = 0;
                  $total_due = 0;
                  ?>
                  
                  <?php foreach ($fee_cards as $index => $card): 
                    $total_fee += $card['total_amount'];
                    $total_paid += $card['paid_amount'];
                    $total_discount_given += $card['total_discount'];
                    $due = $card['due_amount'];
                    $total_due += $due;
                    
                    // Get month-year from due date
                    $month_year = getMonthYearFromDueDate($card['due_date']);
                    $formatted_date = formatDueDate($card['due_date']);
                    
                    // Check if overdue
                    $is_overdue = (strtotime($card['due_date']) < time()) && ($due > 0);
                  ?>
                  <tr id="fee-row-<?php echo $card['id']; ?>">
                    <td class="action-col" data-label="">
                      <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $card['id']; ?>)" class="delete-btn" title="فیس کارڈ حذف کریں">
                        <i class="fas fa-trash-alt"></i>
                      </a>
                    </td>
                    <td data-label="قسم">
                      <strong><?php echo htmlspecialchars($card['fee_type_title']); ?></strong>
                    </td>
                    <td class="month-date-cell" data-label="مہینہ">
                      <span class="month-badge"><?php echo $month_year; ?></span>
                      <span class="due-date-badge <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> <?php echo $formatted_date; ?>
                        <?php if ($is_overdue): ?>
                          <span class="badge bg-danger ms-1">زائد المعیاد</span>
                        <?php endif; ?>
                      </span>
                    </td>
                    <td class="text-amount" data-label="کل"><?php echo number_format($card['total_amount'], 2); ?></td>
                    <td class="text-success" data-label="ادا"><?php echo number_format($card['paid_amount'], 2); ?></td>
                    <td class="text-warning" data-label="رعایت"><?php echo number_format($card['total_discount'], 2); ?></td>
                    <td class="amount-due" data-label="بقایا"><?php echo number_format($due, 2); ?></td>
                    <td data-label="ادائیگی">
                      <input type="number" 
                             name="payment[<?php echo $card['id']; ?>]" 
                             class="form-control amount-input payment-amount" 
                             data-due="<?php echo $due; ?>" 
                             data-card-id="<?php echo $card['id']; ?>"
                             step="0.01" 
                             min="0" 
                             max="<?php echo $due; ?>"
                             placeholder="رقم">
                    </td>
                    <td data-label="رعایت شامل">
                      <input type="number" 
                             name="discount[<?php echo $card['id']; ?>]" 
                             class="form-control amount-input discount-input" 
                             data-card-id="<?php echo $card['id']; ?>"
                             step="0.01" 
                             min="0" 
                             max="<?php echo $due; ?>"
                             placeholder="رعایت">
                    </td>
                    <td data-label="بیلنس">
                      <span id="balance-<?php echo $card['id']; ?>" class="balance-amount fw-bold">
                        <?php if ($due <= 0): ?>
                          <span class="cleared-badge">
                            <i class="fas fa-check-circle"></i> کلیئر
                          </span>
                        <?php else: ?>
                          <span id="balance-value-<?php echo $card['id']; ?>"><?php echo number_format($due, 2); ?></span>
                        <?php endif; ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="text-end fw-bold" data-label="">کل:</td>
                    <td class="fw-bold" data-label="مہینہ"></td>
                    <td class="fw-bold" data-label="کل"><?php echo number_format($total_fee, 2); ?></td>
                    <td class="fw-bold text-success" data-label="ادا"><?php echo number_format($total_paid, 2); ?></td>
                    <td class="fw-bold text-warning" data-label="رعایت"><?php echo number_format($total_discount_given, 2); ?></td>
                    <td class="fw-bold text-danger" data-label="بقایا"><?php echo number_format($total_due, 2); ?></td>
                    <td data-label="ادائیگی"></td>
                    <td data-label="رعایت شامل"></td>
                    <td class="fw-bold" data-label="بیلنس" id="totalBalanceFooter"><?php echo number_format($total_due, 2); ?></td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            <!-- Payment Details -->
            <div class="row p-3">
              <div class="col-md-4 mb-2">
                <div class="form-group">
                  <label class="payment-form-label">ادائیگی کا طریقہ *</label>
                  <select name="payment_method" class="form-select payment-form-control" required>
                    <option value="">طریقہ منتخب کریں</option>
                    <option value="cash" selected>نقد</option>
                    <option value="bank_transfer">بینک ٹرانسفر</option>
                    <option value="cheque">چیک</option>
                    <option value="online">آن لائن ادائیگی</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4 mb-2">
                <div class="form-group">
                  <label class="payment-form-label">ٹرانزیکشن حوالہ</label>
                  <input type="text" name="transaction_ref" class="form-control payment-form-control" placeholder="حوالہ نمبر (اختیاری)">
                </div>
              </div>
              <div class="col-md-4 mb-2">
                <div class="form-group">
                  <label class="payment-form-label">ریمارکس</label>
                  <input type="text" name="payment_remarks" class="form-control payment-form-control" placeholder="ریمارکس (اختیاری)">
                </div>
              </div>
            </div>
            
            <div class="p-3">
              <button type="submit" name="process_payment" class="btn btn-success btn-process w-100">
                <i class="fas fa-check-circle"></i> ادائیگی پراسیس کریں
              </button>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-md-4">
        <!-- Payment Summary Card -->
        <div class="card summary-card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> ادائیگی کا خلاصہ</h5>
          </div>
          <div class="card-body">
            <div class="payment-summary-item">
              <span class="payment-summary-label">کل ادائیگی:</span>
              <strong class="payment-summary-value" id="selectedPayment">0.00</strong>
            </div>
            
            <div class="payment-summary-item">
              <span class="payment-summary-label">کل رعایت:</span>
              <strong class="payment-summary-value text-warning" id="selectedDiscount">0.00</strong>
            </div>
            
            <hr class="border-white opacity-25 my-2">
            
            <div class="payment-summary-item">
              <span class="payment-summary-label fs-6">بقایا:</span><br>
              <strong class="payment-summary-value fs-5" id="selectedBalance"><?php echo number_format($total_due, 2); ?></strong>
            </div>
            
            <div class="mt-3 text-center">
              <small class="text-white-50">
                <i class="fas fa-info-circle"></i> ادائیگی + رعایت فیس کلیئر کر دے گی
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </form>
  
  <!-- Recent Transactions -->
  <div class="card mt-4">
    <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center flex-wrap">
      <h5 class="mb-2 mb-sm-0"><i class="fas fa-history"></i> حالیہ ٹرانزیکشنز (آخری 20)</h5>
      <div class="d-flex">
        <button class="btn btn-sm btn-light me-1" id="selectAllBtn" onclick="toggleSelectAll()">سب منتخب کریں</button>
        <button class="btn btn-sm btn-warning print-selected-btn" onclick="printSelectedTransactions()">
          <i class="fas fa-print"></i> منتخب شدہ پرنٹ کریں
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table transaction-table mb-0">
          <thead>
            <tr>
              <th class="checkbox-col"><i class="fas fa-check-square"></i></th>
              <th>رسید نمبر</th>
              <th>تاریخ اور وقت</th>
              <th>فیس کی قسم</th>
              <th>مہینہ</th>
              <th>ادا کردہ رقم</th>
              <th>رعایت</th>
              <th>طریقہ</th>
              <th>حیثیت</th>
              <th>پرنٹ</th>
              <th>ترمیم</th>
              <th>بحال</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_transactions)): ?>
            <tr>
              <td colspan="12" class="text-center py-4">کوئی ٹرانزیکشن نہیں ملی</td>
            </tr>
            <?php else: ?>
              <?php foreach ($recent_transactions as $trans): 
                $receipt_id = "REC-" . date('Ymd', strtotime($trans['payment_date'])) . "-" . $trans['id'];
                $month_year = getMonthYearFromDueDate($trans['due_date']);
                $can_edit = ($trans['status'] == 'completed');
              ?>
              <tr id="trans-row-<?php echo $trans['id']; ?>">
                <td class="checkbox-col" data-label="">
                  <input type="checkbox" class="transaction-checkbox" value="<?php echo $trans['id']; ?>" id="trans_<?php echo $trans['id']; ?>">
                </td>
                <td data-label="رسید نمبر"><strong><?php echo $receipt_id; ?></strong></td>
                <td data-label="تاریخ اور وقت"><?php echo date('d M Y, h:i A', strtotime($trans['payment_date'])); ?></td>
                <td data-label="فیس کی قسم"><?php echo htmlspecialchars($trans['fee_type_title']); ?></td>
                <td data-label="مہینہ"><span class="month-badge"><?php echo $month_year; ?></span></td>
                <td class="text-success fw-bold" data-label="ادا کردہ رقم"><?php echo number_format($trans['paid_amount'], 2); ?></td>
                <td class="text-warning fw-bold" data-label="رعایت"><?php echo number_format($trans['discount_amount'], 2); ?></td>
                <td data-label="طریقہ"><?php echo ucfirst(str_replace('_', ' ', $trans['payment_method'])); ?></td>
                <td data-label="حیثیت">
                  <?php if ($trans['status'] == 'reversed'): ?>
                    <span class="badge bg-secondary">ریورسڈ</span>
                  <?php else: ?>
                    <span class="badge bg-success">ادا شدہ</span>
                  <?php endif; ?>
                </td>
                <td data-label="پرنٹ">
                  <button class="btn btn-sm btn-outline-primary receipt-btn" onclick="printSingleTransaction(<?php echo $trans['id']; ?>)">
                    <i class="fas fa-print"></i>
                  </button>
                </td>
                <td data-label="ترمیم">
                  <?php if ($can_edit): ?>
                  <button class="btn btn-sm btn-outline-warning" onclick="openEditModal(<?php echo $trans['id']; ?>, <?php echo $trans['paid_amount']; ?>, <?php echo $trans['discount_amount']; ?>, '<?php echo $trans['payment_method']; ?>', '<?php echo htmlspecialchars($trans['transaction_ref'] ?? ''); ?>', '<?php echo htmlspecialchars($trans['remarks'] ?? ''); ?>')" title="ٹرانزیکشن میں ترمیم کریں">
                    <i class="fas fa-edit"></i>
                  </button>
                  <?php else: ?>
                  <span class="text-muted small">مقفل</span>
                  <?php endif; ?>
                </td>
                <td data-label="بحال">
                  <button class="btn btn-sm btn-outline-danger" onclick="confirmRestoreTransaction(<?php echo $trans['id']; ?>)" title="فیس کارڈ بحال کریں">
                    <i class="fas fa-undo-alt"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  
  <?php else: ?>
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle"></i> طالب علم کسی بھی فعال کلاس میں داخل نہیں ہے۔ براہ کرم پہلے طالب علم کا داخلہ کریں۔
    </div>
  <?php endif; ?>
  
  <?php else: ?>
    <div class="alert alert-warning text-center py-5">
      <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
      <h4>کوئی طالب علم منتخب نہیں</h4>
      <p class="mb-3">براہ کرم طلبہ کی فہرست میں جائیں اور فیس جمع کرنے کے لیے طالب علم منتخب کریں۔</p>
      <a href="student_list.php" class="btn btn-primary">
        <i class="fas fa-list"></i> طلبہ کی فہرست دیکھیں
      </a>
    </div>
  <?php endif; ?>
  
</div>

<script>
$(document).ready(function() {
  // Initialize Select2 for better dropdown in modal
  if (typeof $.fn.select2 !== 'undefined') {
    $('#fee_type_id').select2({
      placeholder: 'فیس کی قسم منتخب کریں',
      allowClear: true,
      width: '100%',
      dropdownParent: $('#addFeeModal')
    });
  }
  
  // Auto-populate amount when fee type is selected
  $('#fee_type_id').on('change', function() {
    var selectedOption = $(this).find('option:selected');
    var amount = selectedOption.data('amount');
    if (amount) {
      $('#amount').val(amount);
    } else {
      $('#amount').val('');
    }
  });
  
  // Handle month selection to show due date
  $('#month').on('change', function() {
    var selectedMonth = $(this).val();
    if (selectedMonth) {
      var lastDay = getLastDayOfMonth(selectedMonth);
      var date = new Date(lastDay);
      var formattedDate = date.toLocaleDateString('ur-PK', { 
        day: 'numeric', 
        month: 'long', 
        year: 'numeric' 
      });
      $('#dueDateDisplay').html('<i class="fas fa-calendar-check"></i> آخری تاریخ: ' + formattedDate + ' (مہینے کا آخری دن)');
    } else {
      $('#dueDateDisplay').html('');
    }
  });
  
  function getLastDayOfMonth(yearMonth) {
    var date = new Date(yearMonth + '-01');
    date.setMonth(date.getMonth() + 1);
    date.setDate(date.getDate() - 1);
    return date.toISOString().split('T')[0];
  }
  
  function calculateBalance(cardId, changedField) {
    var paymentInput  = $('input[name="payment[' + cardId + ']"]');
    var discountInput = $('input[name="discount[' + cardId + ']"]');
    var due           = parseFloat(paymentInput.data('due')) || 0;
    var payment       = parseFloat(paymentInput.val()) || 0;
    var discount      = parseFloat(discountInput.val()) || 0;

    if (payment > due) { payment = due; paymentInput.val(payment.toFixed(2)); }
    if (discount > due) { discount = due; discountInput.val(discount.toFixed(2)); }

    if (payment + discount > due) {
      if (changedField === 'discount') {
        payment = due - discount;
        if (payment < 0) payment = 0;
        paymentInput.val(payment.toFixed(2));
      } else {
        discount = due - payment;
        if (discount < 0) discount = 0;
        discountInput.val(discount.toFixed(2));
      }
    }

    var balance = due - payment - discount;
    if (balance < 0) balance = 0;

    var balanceSpan = $('#balance-' + cardId);
    if (balance <= 0) {
      balanceSpan.html('<span class="cleared-badge"><i class="fas fa-check-circle"></i> کلیئر</span>');
      $('#fee-row-' + cardId).addClass('table-cleared');
    } else {
      balanceSpan.html('<span id="balance-value-' + cardId + '">' + balance.toFixed(2) + '</span>');
      $('#fee-row-' + cardId).removeClass('table-cleared');
    }

    return { payment: payment, discount: discount, balance: balance };
  }

  $(document).on('input', '.payment-amount', function() {
    var cardId = $(this).data('card-id');
    calculateBalance(cardId, 'payment');
    updateTotals();
  });

  $(document).on('input', '.discount-input', function() {
    var cardId = $(this).data('card-id');
    calculateBalance(cardId, 'discount');
    updateTotals();
  });
  
  function updateTotals() {
    var totalPayment = 0;
    var totalDiscount = 0;
    var totalBalance = 0;
    
    $('.payment-amount').each(function() {
      var cardId  = $(this).data('card-id');
      var due     = parseFloat($(this).data('due')) || 0;
      var payment = parseFloat($(this).val()) || 0;
      var discount= parseFloat($('input[name="discount[' + cardId + ']"]').val()) || 0;
      
      totalPayment  += payment;
      totalDiscount += discount;
      
      var balance = due - payment - discount;
      if (balance < 0) balance = 0;
      totalBalance += balance;
    });
    
    $('#selectedPayment').text(totalPayment.toFixed(2));
    $('#selectedDiscount').text(totalDiscount.toFixed(2));
    $('#selectedBalance').text(totalBalance.toFixed(2));
    $('#totalBalanceFooter').text(totalBalance.toFixed(2));
  }
  
  $('#feePaymentForm').on('submit', function(e) {
    var totalPayment = 0;
    var totalDiscount = 0;
    
    $('.payment-amount').each(function() {
      totalPayment += parseFloat($(this).val()) || 0;
    });
    
    $('.discount-input').each(function() {
      totalDiscount += parseFloat($(this).val()) || 0;
    });
    
    if (totalPayment <= 0 && totalDiscount <= 0) {
      e.preventDefault();
      alert('براہ کرم کم از کم ایک ادائیگی کی رقم یا رعایت درج کریں۔');
      return false;
    }
    
    if (!$('select[name="payment_method"]').val()) {
      e.preventDefault();
      alert('براہ کرم ادائیگی کا طریقہ منتخب کریں۔');
      return false;
    }
  });
  
  $('#addFeeForm').on('submit', function(e) {
    var feeType = $('#fee_type_id').val();
    var amount  = $('#amount').val();
    var month   = $('#month').val();
    
    if (!feeType) {
      e.preventDefault();
      alert('براہ کرم فیس کی قسم منتخب کریں۔');
      return false;
    }
    
    if (!amount || parseFloat(amount) <= 0) {
      e.preventDefault();
      alert('براہ کرم 0 سے زیادہ درست رقم درج کریں۔');
      return false;
    }
    
    if (!month) {
      e.preventDefault();
      alert('براہ کرم مہینہ منتخب کریں۔');
      return false;
    }
    
    return true;
  });
  
  $('#addFeeModal').on('hidden.bs.modal', function () {
    $('#addFeeForm')[0].reset();
    $('#fee_type_id').val(null).trigger('change');
    $('#dueDateDisplay').html('');
  });
});

function confirmDelete(cardId) {
  if (confirm('کیا آپ واقعی یہ فیس کارڈ حذف کرنا چاہتے ہیں؟ یہ کارروائی واپس نہیں لی جا سکتی۔')) {
    window.location.href = 'fee_collection.php?id=<?php echo $student_id; ?>&delete_fee_card=' + cardId;
  }
}

function confirmRestoreTransaction(transactionId) {
    if (confirm('⚠️ ٹرانزیکشن بحال کریں\n\nیہ کرے گا:\n• اکاؤنٹنگ میں کیش انٹری ریورس کرے گا\n• ادائیگی کو "ریورسڈ" کے طور پر نشان زد کرے گا\n• فیس کارڈ کو زیر التوا بحال کرے گا (اگر مکمل ادا نہیں)\n\nجاری رکھیں؟')) {
        window.location.href = 'fee_collection.php?id=<?php echo $student_id; ?>&delete_transaction=' + transactionId;
    }
}

function toggleSelectAll() {
    var checkboxes   = document.querySelectorAll('.transaction-checkbox');
    var selectAllBtn = document.getElementById('selectAllBtn');
    var allSelected = true;
    
    checkboxes.forEach(function(checkbox) {
        if (!checkbox.checked) {
            allSelected = false;
        }
    });
    
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = !allSelected;
    });
    
    selectAllBtn.textContent = allSelected ? 'سب منتخب کریں' : 'سب غیر منتخب کریں';
}

function printSingleTransaction(id) {
    window.open('print_receipt.php?id=' + id + '&auto_print=1', '_blank');
}

function printSelectedTransactions() {
    var selectedIds = [];
    document.querySelectorAll('.transaction-checkbox:checked').forEach(function(checkbox) {
        selectedIds.push(checkbox.value);
    });
    
    if (selectedIds.length === 0) {
        alert('براہ کرم پرنٹ کرنے کے لیے کم از کم ایک ٹرانزیکشن منتخب کریں۔');
        return;
    }
    
    var idsParam = selectedIds.join(',');
    window.open('print_receipt.php?ids=' + idsParam + '&auto_print=1', '_blank');
}

function openEditModal(transId, paidAmount, discountAmount, paymentMethod, transRef, remarks) {
  $('#edit_transaction_id').val(transId);
  $('#edit_original_amount').val(paidAmount.toFixed(2));
  $('#edit_original_discount').val(discountAmount.toFixed(2));
  $('#edit_new_amount').val(paidAmount.toFixed(2));
  $('#edit_new_discount').val(discountAmount.toFixed(2));
  $('#edit_payment_method').val(paymentMethod);
  $('#edit_transaction_ref').val(transRef);
  $('#edit_remarks').val(remarks);
  updateEditDisplay();
  $('#editFeeModal').modal('show');
}

$('#edit_new_amount, #edit_new_discount').on('input', function() {
  updateEditDisplay();
});

function updateEditDisplay() {
  var origAmount = parseFloat($('#edit_original_amount').val()) || 0;
  var newAmount = parseFloat($('#edit_new_amount').val()) || 0;
  
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

$('#editFeeForm').on('submit', function(e) {
  var newAmount = parseFloat($('#edit_new_amount').val()) || 0;
  var newDiscount = parseFloat($('#edit_new_discount').val()) || 0;
  
  if (newAmount <= 0 && newDiscount <= 0) {
    e.preventDefault();
    alert('براہ کرم درست ادائیگی کی رقم یا رعایت درج کریں۔');
    return false;
  }
  
  if (!$('#edit_payment_method').val()) {
    e.preventDefault();
    alert('براہ کرم ادائیگی کا طریقہ منتخب کریں۔');
    return false;
  }
  
  return true;
});

$('#editFeeModal').on('hidden.bs.modal', function () {
  $('#editFeeForm')[0].reset();
});
</script>

</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>