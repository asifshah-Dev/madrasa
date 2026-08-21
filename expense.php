<?php 
// Set timezone to Pakistan (Asia/Karachi)
date_default_timezone_set('Asia/Karachi');

require_once('security.php');

// Language handling
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

// Language strings
$translations = [
    'en' => [
        'title' => 'Expense Invoicing',
        'add_title' => 'Add New Expense',
        'edit_title' => 'Edit Expense',
        'payment_type' => 'Payment Type',
        'invoice_date' => 'Invoice Date',
        'description' => 'Description',
        'expense_head' => 'Expense Category',
        'amount' => 'Amount',
        'total_amount' => 'Total Amount',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Expense saved successfully!',
        'update_success' => 'Expense updated successfully!',
        'delete_success' => 'Expense deleted successfully!.',
        'error' => 'Error: ',
        'empty_error' => 'Required fields cannot be empty!',
        'invalid_amount' => 'Amount cannot be zero!',
        'transaction_error' => 'Transaction failed: ',
        'expense_not_found' => 'Expense not found!',
        'delete_confirm' => 'Are you sure you want to delete this expense? This will restore the balance.',
        'list_title' => 'Expenses List',
        'no_records' => 'No expenses found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'cancel' => 'Cancel',
        'status' => 'Status',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'status_deleted' => 'Deleted',
        'add_details' => 'Add Details',
        'product_details' => 'Product Details',
        'product_name' => 'Product Name',
        'category' => 'Category',
        'unit' => 'Unit',
        'quantity' => 'Quantity',
        'unit_price' => 'Unit Price',
        'total_price' => 'Total Price',
        'save_details' => 'Save Details',
        'close' => 'Close',
        'select_product' => 'Select Product',
        'add_new_product' => 'Add New Product',
        'product_list' => 'Product List',
        'inventory_details' => 'Inventory Details'
    ]
];

// Process DELETE request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Validate: Expense exists and is active
        $check_expense = $conn->prepare("SELECT * FROM expenses WHERE id = ? AND status = 1");
        $check_expense->bind_param("i", $delete_id);
        $check_expense->execute();
        $expense_result = $check_expense->get_result();
        
        if ($expense_result->num_rows === 0) {
            throw new Exception("Expense not found or already deleted!");
        }
        
        $expense = $expense_result->fetch_assoc();
        $expense_amount = floatval($expense['total_amount']);
        $check_expense->close();
        
        // 2. Lock master account and get current balance
        $check_master = $conn->query("SELECT id, balance FROM master_account WHERE id = 1 FOR UPDATE");
        if ($check_master->num_rows === 0) {
            throw new Exception("Master account not found!");
        }
        
        $master_row = $check_master->fetch_assoc();
        $current_balance = floatval($master_row['balance']);
        $master_account_id = $master_row['id'];
        
        // 3. Calculate restored balance
        $restored_balance = $current_balance + $expense_amount;
        $transaction_date = date('Y-m-d H:i:s');
        
        // 4. Insert reversal entry (credit) in detail_account
        $reversal_stmt = $conn->prepare("
            INSERT INTO detail_account 
            (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
            VALUES (?, 'cash in', ?, ?, ?, 'expense_deletion', ?, NOW())
        ");
        
        $credit_amount = $expense_amount; // Positive for credit
        $reversal_stmt->bind_param("iddis", $master_account_id, $credit_amount, $restored_balance, $delete_id, $transaction_date);
        
        if (!$reversal_stmt->execute()) {
            throw new Exception("Failed to create reversal entry: " . $reversal_stmt->error);
        }
        $reversal_stmt->close();
        
        // 5. Update master_account with restored balance
        $update_master = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
        $update_master->bind_param("di", $restored_balance, $master_account_id);
        
        if (!$update_master->execute()) {
            throw new Exception("Failed to restore balance: " . $update_master->error);
        }
        $update_master->close();
        
        // 6. Soft delete - update expense status to 0 (inactive/deleted)
        $soft_delete = $conn->prepare("UPDATE expenses SET status = 0 WHERE id = ?");
        $soft_delete->bind_param("i", $delete_id);
        
        if (!$soft_delete->execute()) {
            throw new Exception("Failed to delete expense: " . $soft_delete->error);
        }
        $soft_delete->close();
        
        // Also delete inventory details for this expense
        $delete_inv = $conn->prepare("DELETE FROM expanse_inventory_details WHERE expense_id = ?");
        $delete_inv->bind_param("i", $delete_id);
        $delete_inv->execute();
        $delete_inv->close();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ " . $translations[$lang]['delete_success'] . "</strong><br>Restored Amount: " . number_format($expense_amount, 2) . "<br>New Balance: " . number_format($restored_balance, 2) . "</div>";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process AJAX requests for product details CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once('conn_inc.php');
    $conn->query("SET time_zone = '+05:00'");
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];
    
    // Product CRUD operations
    if ($action == 'get_products') {
        $result = $conn->query("SELECT id, product_name, category, unit FROM products WHERE status = 1 ORDER BY product_name");
        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
        $response['success'] = true;
        $response['products'] = $products;
    } 
    elseif ($action == 'add_product') {
        $product_name = trim($_POST['product_name']);
        $category = trim($_POST['category']);
        $unit = trim($_POST['unit']);
        
        // Validate that category is valid
        $valid_categories = ['Furniture', 'Stationery', 'Electronics', 'Clothing'];
        if (!in_array($category, $valid_categories)) {
            $response['message'] = 'Invalid category. Allowed values: ' . implode(', ', $valid_categories);
            echo json_encode($response);
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO products (product_name, category, unit, status, created_at) VALUES (?, ?, ?, 1, NOW())");
        $stmt->bind_param("sss", $product_name, $category, $unit);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Product added successfully';
            $response['product_id'] = $conn->insert_id;
            $response['product_name'] = $product_name;
            $response['category'] = $category;
            $response['unit'] = $unit;
        } else {
            $response['message'] = 'Failed to add product: ' . $stmt->error;
        }
        $stmt->close();
    }
    elseif ($action == 'get_inventory_details') {
        $expense_id = intval($_POST['expense_id']);
        $stmt = $conn->prepare("
            SELECT eid.*, p.product_name, p.category, p.unit 
            FROM expanse_inventory_details eid
            JOIN products p ON eid.product_id = p.id
            WHERE eid.expense_id = ?
        ");
        $stmt->bind_param("i", $expense_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $details = [];
        while ($row = $result->fetch_assoc()) {
            $details[] = $row;
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['details'] = $details;
    }
    elseif ($action == 'add_inventory_detail') {
        $expense_id = intval($_POST['expense_id']);
        $product_id = intval($_POST['product_id']);
        $quantity = floatval($_POST['quantity']);
        $unit_price = floatval($_POST['unit_price']);
        $total_price = $quantity * $unit_price;
        $description = trim($_POST['description']);
        
        $stmt = $conn->prepare("
            INSERT INTO expanse_inventory_details (expense_id, product_id, quantity, unit_price, total_price, description) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiddds", $expense_id, $product_id, $quantity, $unit_price, $total_price, $description);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Detail added successfully';
            $response['detail_id'] = $conn->insert_id;
            $response['total_price'] = $total_price;
        } else {
            $response['message'] = 'Failed to add detail: ' . $stmt->error;
        }
        $stmt->close();
    }
    elseif ($action == 'delete_inventory_detail') {
        $detail_id = intval($_POST['detail_id']);
        
        $stmt = $conn->prepare("DELETE FROM expanse_inventory_details WHERE id = ?");
        $stmt->bind_param("i", $detail_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Detail deleted successfully';
        } else {
            $response['message'] = 'Failed to delete detail: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Process form submission (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_expense'])) {
    require_once('conn_inc.php');
    
    // Set MySQL timezone to Pakistan (UTC+5)
    $conn->query("SET time_zone = '+05:00'");
    
    // Get form data
    $expense_categories_id = isset($_POST['expense_categories_id']) ? intval($_POST['expense_categories_id']) : 0;
    $payment_type = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : '';
    $invoice_date = !empty($_POST['invoice_date']) ? trim($_POST['invoice_date']) : date('Y-m-d');
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Get raw amount and convert to positive for expense
    $raw_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
    $total_amount = abs($raw_amount);
    $debit_amount = -$total_amount; // Negative for detail_account
    
    $status = 1;
    
    // 1. VALIDATE: Check required fields
    if (empty($expense_categories_id) || $expense_categories_id == 0) {
        $_SESSION['message'] = "Error: Expense category is required!";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (empty($payment_type)) {
        $_SESSION['message'] = "Error: Payment type is required!";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($total_amount == 0) {
        $_SESSION['message'] = "Error: Amount cannot be zero!";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Validate that expense category exists
    $check_cat = $conn->prepare("SELECT id FROM expense_categories WHERE id = ? AND status = 1");
    $check_cat->bind_param("i", $expense_categories_id);
    $check_cat->execute();
    $cat_result = $check_cat->get_result();
    
    if ($cat_result->num_rows === 0) {
        $_SESSION['message'] = "Error: Invalid or inactive expense category (ID: " . $expense_categories_id . ")!";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $check_cat->close();
    
    // Get expense_id for edit mode
    $expense_id = isset($_POST['expense_id']) ? intval($_POST['expense_id']) : 0;
    $is_edit = ($expense_id > 0);
    
    // 2. START TRANSACTION
    $conn->begin_transaction();
    
    try {
        // Check/Create master_account and lock it
        $check_master = $conn->query("SELECT id, balance FROM master_account WHERE id = 1 FOR UPDATE");
        if ($check_master->num_rows === 0) {
            $conn->query("INSERT INTO master_account (id, title, balance, created_at) VALUES (1, 'Main Account', 0, NOW())");
            $current_balance = 0;
            $master_account_id = 1;
        } else {
            $master_row = $check_master->fetch_assoc();
            $current_balance = floatval($master_row['balance']);
            $master_account_id = $master_row['id'];
        }
        
        $transaction_date = date('Y-m-d H:i:s');
        
        if ($is_edit) {
            // EDIT MODE
            $check_expense = $conn->prepare("SELECT * FROM expenses WHERE id = ? AND status = 1");
            $check_expense->bind_param("i", $expense_id);
            $check_expense->execute();
            $old_expense_result = $check_expense->get_result();
            
            if ($old_expense_result->num_rows === 0) {
                throw new Exception($translations[$lang]['expense_not_found']);
            }
            
            $old_expense = $old_expense_result->fetch_assoc();
            $old_amount = floatval($old_expense['total_amount']);
            $check_expense->close();
            
            $balance_adjustment = $old_amount - $total_amount;
            $new_balance = $current_balance + $balance_adjustment;
            
            if ($balance_adjustment >= 0) {
                $transaction_type = 'cash in';
                $transaction_amount = $balance_adjustment;
            } else {
                $transaction_type = 'cash out';
                $transaction_amount = abs($balance_adjustment);
            }
            
            $adjustment_stmt = $conn->prepare("
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                VALUES (?, ?, ?, ?, ?, 'expense_edit', ?, NOW())
            ");
            
            $adjustment_stmt->bind_param("isddis", 
                $master_account_id, 
                $transaction_type, 
                $transaction_amount, 
                $new_balance, 
                $expense_id, 
                $transaction_date
            );
            
            if (!$adjustment_stmt->execute()) {
                throw new Exception("Failed to insert adjustment entry: " . $adjustment_stmt->error);
            }
            $adjustment_stmt->close();
            
            $update_master = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
            $update_master->bind_param("di", $new_balance, $master_account_id);
            
            if (!$update_master->execute()) {
                throw new Exception("Failed to update master balance: " . $update_master->error);
            }
            $update_master->close();
            
            $update_expense = $conn->prepare("
                UPDATE expenses SET 
                expense_categories_id = ?, payment_type = ?, total_amount = ?, 
                description = ?, invoice_date = ?, status = ? 
                WHERE id = ?
            ");
            
            $update_expense->bind_param("isdssii", 
                $expense_categories_id, $payment_type, $total_amount, 
                $description, $invoice_date, $status, $expense_id
            );
            
            if (!$update_expense->execute()) {
                throw new Exception("Failed to update expense: " . $update_expense->error);
            }
            $update_expense->close();
            
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ Expense Updated Successfully!</strong><br>New Balance: " . number_format($new_balance, 2) . "</div>";
            $_SESSION['message_type'] = "success";
            
        } else {
            // ADD MODE
            $new_balance = $current_balance - $total_amount;
            
            $expense_stmt = $conn->prepare("
                INSERT INTO expenses 
                (expense_categories_id, payment_type, total_amount, description, invoice_date, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if (!$expense_stmt) {
                throw new Exception("Prepare expenses failed: " . $conn->error);
            }
            
            $expense_stmt->bind_param("isdssi", 
                $expense_categories_id, $payment_type, $total_amount, 
                $description, $invoice_date, $status
            );
            
            if (!$expense_stmt->execute()) {
                throw new Exception("Insert expenses failed: " . $expense_stmt->error);
            }
            
            $new_expense_id = $conn->insert_id;
            $expense_stmt->close();
            
            $detail_stmt = $conn->prepare("
                INSERT INTO detail_account 
                (master_account_id, type, amount, balance, ref_id, ref_type, transaction_date, created_at) 
                VALUES (?, 'cash out', ?, ?, ?, 'expense', ?, NOW())
            ");
            
            if (!$detail_stmt) {
                throw new Exception("Prepare detail_account failed: " . $conn->error);
            }
            
            $detail_stmt->bind_param("iddis", $master_account_id, $debit_amount, $new_balance, $new_expense_id, $transaction_date);
            
            if (!$detail_stmt->execute()) {
                throw new Exception("Insert detail_account failed: " . $detail_stmt->error);
            }
            
            $detail_stmt->close();
            
            $update_master = $conn->prepare("UPDATE master_account SET balance = ? WHERE id = ?");
            $update_master->bind_param("di", $new_balance, $master_account_id);
            
            if (!$update_master->execute()) {
                throw new Exception("Update master_account failed: " . $update_master->error);
            }
            
            $update_master->close();
            
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ Expense Saved Successfully!</strong><br>New Balance: " . number_format($new_balance, 2) . "</div>";
            $_SESSION['message_type'] = "success";
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Transaction Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once('meta_inc.php'); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <style>
        .amount-input { text-align: left; }
        .table-responsive { overflow-x: auto; }
        .table .amount-column { 
            text-align: right; 
            font-family: monospace;
            font-size: 18px;
        }
        .table .description-column {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .table .date-column { white-space: nowrap; }
        input[type=number].amount-input { text-align: left; }
        input[type=number] { -moz-appearance: textfield; }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        .description-column:hover {
            overflow: visible;
            white-space: normal;
            word-wrap: break-word;
            background-color: #fff;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
        }
        .balance-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .balance-negative {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .custom-alert {
            margin-bottom: 20px;
        }
        .deleted-row {
            background-color: #f2dede !important;
            opacity: 0.7;
        }
        .btn-delete {
            margin-left: 5px;
        }
        .product-details-table td, .product-details-table th {
            vertical-align: middle;
        }
        .btn-add-details {
            margin-left: 10px;
        }

        /* Increased input text size */
        .form-control {
            font-size: 16px !important;
        }
        
        select.form-control {
            font-size: 16px !important;
        }
        
        textarea.form-control {
            font-size: 16px !important;
        }
        
        input.form-control {
            font-size: 16px !important;
        }
        
        .input-sm {
            font-size: 14px !important;
        }

        /* ── Slide-over overlay panel ── */
        #detailsOverlay {
            display: none;
            position: fixed;
            top: 0; right: 0; bottom: 0; left: 0;
            z-index: 1040;
            background: rgba(0,0,0,0.55);
        }
        #detailsPanel {
            position: fixed;
            top: 0; right: -780px; bottom: 0;
            width: 780px;
            max-width: 98vw;
            background: #fff;
            z-index: 1050;
            box-shadow: -4px 0 24px rgba(0,0,0,0.25);
            transition: right 0.32s cubic-bezier(.4,0,.2,1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        #detailsPanel.open { right: 0; }
        #detailsPanelHeader {
            background: #31708f;
            color: #fff;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
        }
        #detailsPanelHeader h4 { margin: 0; font-size: 16px; }
        #detailsPanelClose {
            background: none; border: none; color: #fff;
            font-size: 22px; line-height: 1; cursor: pointer; padding: 0 4px;
        }
        #detailsPanelBody {
            flex: 1;
            overflow-y: auto;
            padding: 18px 20px;
        }
        #detailsPanelFooter {
            border-top: 1px solid #ddd;
            padding: 12px 20px;
            flex-shrink: 0;
            text-align: right;
            background: #f9f9f9;
        }

        /* ── Inline Add-Product box (inside the panel) ── */
        #addProductBox {
            display: none;
            border: 1px solid #bbb;
            border-radius: 4px;
            padding: 14px 16px;
            background: #f5f5f5;
            margin-bottom: 14px;
        }
        #addProductBox h5 {
            margin-top: 0;
            margin-bottom: 12px;
            color: #333;
        }
        
        /* ── Panel heading with dual language labels ── */
        .dual-language-heading {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
        }
        .english-label {
            font-weight: bold;
            font-size: 16px;
        }
        .urdu-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 18px;
            font-weight: normal;
            direction: rtl;
            text-align: right;
        }
        
        /* ── Dual language form field wrapper ── */
        .dual-field {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }
        .en-field-label {
            font-size: 14px;
            font-weight: normal;
            color: #333;
        }
       
        .urdu-field-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 14px;
            direction: rtl;
            color: #666;
        }
        .dual-button {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* ============================================
           CARD LAYOUT FOR MOBILE VIEW (Tablet & Phone)
           ============================================ */
        @media (max-width: 768px) {
            /* Hide the table header on mobile */
            .expenses-table thead {
                display: none;
            }
            
            /* Convert table rows to card-like blocks */
            .expenses-table,
            .expenses-table tbody,
            .expenses-table tr,
            .expenses-table td {
                display: block;
                width: 100%;
            }
            
            .expenses-table tr {
                margin-bottom: 20px;
                border: 1px solid #ddd;
                border-radius: 8px;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                padding: 12px;
                position: relative;
            }
            
            .expenses-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 8px;
                border-bottom: 1px solid #eee;
                text-align: right;
            }
            
            .expenses-table td:last-child {
                border-bottom: none;
            }
            
            /* Add label before each cell value */
            .expenses-table td:before {
                content: attr(data-label);
                font-weight: bold;
                width: 40%;
                text-align: left;
                padding-right: 10px;
                font-size: 16px;
                color: #555;
            }
            
            /* Special styling for amount column */
            .expenses-table td.amount-column {
                justify-content: space-between;
                text-align: right;
            }
            
            .expenses-table td.amount-column:before {
                content: "Total Amount / کل رقم:";
            }
            
            /* Description column */
            .expenses-table td.description-column {
                flex-wrap: wrap;
            }
           
            .expenses-table td.description-column:before {
                content: "Description / تفصیل:";
                align-self: flex-start;
            }
            
            .expenses-table td.description-column span {
                width: 55%;
                text-align: right;
                word-break: break-word;
            }
            
            /* Date column */
            .expenses-table td.date-column:before {
                content: "Date / تاریخ:";
            }
            
            /* Expense Category */
            .expenses-table td:nth-of-type(3):before {
                content: "Category / زمرہ:";
            }
            
            /* Payment Type */
            .expenses-table td:nth-of-type(4):before {
                content: "Payment / ادائیگی:";
            }
            
            /* Inventory Details */
            .expenses-table td:nth-of-type(6):before {
                content: "Inventory / انوینٹری:";
            }
            
            /* Actions */
            .expenses-table td:last-of-type:before {
                content: "Actions / کارروائیاں:";
            }
            
            /* Serial number styling */
            .expenses-table td:first-of-type:before {
                content: "#:";
            }
            
            /* Deleted row styling */
            .expenses-table tr.deleted-row {
             
                background-color: #f2dede;
                opacity: 0.8;
            }
            
            /* Make buttons stack on mobile */
            .expenses-table td:last-of-type {
                flex-direction: column;
                gap: 8px;
            }
            
            .expenses-table td:last-of-type a,
            .expenses-table td:last-of-type button {
                width: 100%;
                margin: 2px 0;
            }
            
            /* Container padding adjustment */
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            /* Form adjustments for mobile */
            .form-group .dual-field {
                flex-direction: column;
                gap: 5px;
            }
            
            .dual-button {
                flex-direction: column;
                gap: 10px;
            }
            
            .dual-button div {
                width: 100%;
            }
            
            .dual-button button,
            .dual-button a {
                width: 100%;
                margin: 5px 0;
            }
            
            /* Balance info mobile */
            .balance-info .dual-field {
                flex-direction: column;
                text-align: center;
            }
            
            /* Panel heading mobile */
            .dual-language-heading {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            /* Slide panel width for mobile */
            #detailsPanel {
                width: 100%;
                max-width: 100%;
                right: -100%;
            }
            
            /* Increased mobile input font size */
            .form-control {
                font-size: 18px !important;
            }
        }
        
        /* Small phones */
        @media (max-width: 480px) {
            .expenses-table td {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .expenses-table td:before {
                width: 100%;
                margin-bottom: 5px;
                font-size: 14px;
            }
            
            .expenses-table td.description-column span {
                width: 100%;
                text-align: left;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <!-- Expense Heads Button -->
            <div style="margin-bottom: 15px; text-align: left;">
                <a href="expense_heads.php" style="font-size: 18px; font-weight: bold; background-color: #337ab7; color: white; border: none; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
                    اخراجات کے زمرے
                </a>
            </div>

            <!-- Panel for Add/Edit Expense -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="dual-language-heading">
                        <span class="english-label">
                            <?php 
                            echo isset($_GET['edit']) ? 
                                $translations[$lang]['edit_title'] . ' (ID: ' . intval($_GET['edit']) . ')' : 
                                $translations[$lang]['add_title']; 
                            ?>
                        </span>
                        <span class="urdu-label">
                            <?php 
                            echo isset($_GET['edit']) ? 
                                'اخراجات میں ترمیم کریں (ID: ' . intval($_GET['edit']) . ')' : 
                                'نئے اخراجات شامل کریں'; 
                            ?>
                        </span>
                    </div>
                </div>
                <div class="panel-body">

                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="custom-alert">
                            <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message'], $_SESSION['message_type']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" id="expenseForm">
                        <?php
                        $edit_mode = isset($_GET['edit']);
                        $expense_data = [
                            'id' => '',
                            'expense_categories_id' => '',
                            'payment_type' => 'Cash',
                            'total_amount' => '',
                            'description' => '',
                            'invoice_date' => date('Y-m-d'),
                            'status' => 1
                        ];
                        
                        if ($edit_mode) {
                            require_once('conn_inc.php');
                            $conn->query("SET time_zone = '+05:00'");
                            
                            $id = intval($_GET['edit']);
                            $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
                            $stmt->bind_param("i", $id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                $expense_data = $result->fetch_assoc();
                                $expense_data['invoice_date'] = date('Y-m-d', strtotime($expense_data['invoice_date']));
                            } else {
                                echo "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 10px; border-radius: 4px; margin-bottom: 15px;'><strong>Error:</strong> Expense record not found.</div>";
                            }
                            $stmt->close();
                        }
                        ?>
                        
                        <input type="hidden" name="expense_id" id="expense_id" value="<?php echo htmlspecialchars($expense_data['id']); ?>">
                        
                        <div class="row">
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="expense_categories_id" class="en-field-label"><?php echo $translations[$lang]['expense_head']; ?> </label>
                                        <label class="urdu-field-label">اخراجات کا زمرہ </label>
                                    </div>
                                    <select class="form-control" id="expense_categories_id" name="expense_categories_id" required>
                                        <option value="">-- Select Expense Category -- / -- اخراجات کا زمرہ منتخب کریں --</option>
                                        <?php
                                        require_once('conn_inc.php');
                                        $categories_query = "SELECT id, title FROM expense_categories WHERE status = 1 ORDER BY title";
                                        $categories_result = $conn->query($categories_query);
                                        
                                        if ($categories_result && $categories_result->num_rows > 0) {
                                            while ($category = $categories_result->fetch_assoc()) {
                                                $selected = ($category['id'] == $expense_data['expense_categories_id']) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($category['id']) . "' $selected>" . 
                                                     htmlspecialchars($category['title']) . "</option>";
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="payment_type" class="en-field-label"><?php echo $translations[$lang]['payment_type']; ?> </label>
                                        <label class="urdu-field-label">ادائیگی کی قسم </label>
                                    </div>
                                    <select class="form-control" id="payment_type" name="payment_type" required>
                                        <option value="Cash" <?php echo ($expense_data['payment_type'] == 'Cash') ? 'selected' : ''; ?>>Cash / نقد</option>
                                        <option value="Bank" <?php echo ($expense_data['payment_type'] == 'Bank') ? 'selected' : ''; ?>>Bank / بینک</option>
                                        <option value="Cheque" <?php echo ($expense_data['payment_type'] == 'Cheque') ? 'selected' : ''; ?>>Cheque / چیک</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="invoice_date" class="en-field-label"><?php echo $translations[$lang]['invoice_date']; ?></label>
                                        <label class="urdu-field-label">انوائس کی تاریخ</label>
                                    </div>
                                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" 
                                           value="<?php echo htmlspecialchars($expense_data['invoice_date']); ?>">
                                    <small class="text-muted">Leave empty to use current Pakistan date / خالی چھوڑیں تو پاکستان کی موجودہ تاریخ استعمال ہوگی</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="description" class="en-field-label"><?php echo $translations[$lang]['description']; ?></label>
                                        <label class="urdu-field-label">تفصیل</label>
                                    </div>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Enter description / تفصیل درج کریں"><?php 
                                        echo htmlspecialchars($expense_data['description']); 
                                    ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="total_amount" class="en-field-label">
                                            <?php echo $translations[$lang]['total_amount']; ?> * 
                                            <small class="text-muted">(Enter amount)</small>
                                        </label>
                                        <label class="urdu-field-label">کل رقم</label>
                                    </div>
                                    <input type="number" class="form-control amount-input" id="total_amount" 
                                           name="total_amount" step="0.01" required
                                           value="<?php echo $expense_data['total_amount'] !== '' ? htmlspecialchars($expense_data['total_amount']) : ''; ?>"
                                           placeholder="Enter amount / رقم درج کریں">
                                </div>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div>
                                        <button type="button" class="btn btn-info btn-lg btn-add-details" 
                                                id="btnOpenDetailsFromForm"
                                                <?php echo empty($expense_data['id']) ? 'disabled' : ''; ?>>
                                            <span class="glyphicon glyphicon-list-alt"></span> <?php echo $translations[$lang]['add_details']; ?> / تفصیلات شامل کریں
                                        </button>
                                        <small class="text-muted" style="display: block;">Save expense first to add product details / اخراجات محفوظ کریں پھر مصنوعات کی تفصیلات شامل کریں</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 text-right">
                                <div class="dual-button">
                                    <div>
                                        <button type="submit" name="submit_expense" class="btn btn-success btn-lg" style="font-size: 14px;">
                                            <?php echo $edit_mode ? 'Update' : 'Save'; ?> Expense / <?php echo $edit_mode ? 'اپ ڈیٹ کریں' : 'محفوظ کریں'; ?>
                                        </button>
                                    </div>
                                    <div>
                                        <?php if ($edit_mode): ?>
                                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default btn-lg">
                                                Cancel / منسوخ کریں
                                            </a>
                                        <?php else: ?>
                                            <button type="reset" class="btn btn-default btn-lg">
                                                Reset / دوبارہ ترتیب دیں
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panel for Expenses List -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <div class="dual-language-heading">
                        <span class="english-label"><?php echo $translations[$lang]['list_title']; ?></span>
                        <span class="urdu-label">اخراجات کی فہرست</span>
                    </div>
                </div>
                <div class="panel-body">

                    <?php
                    require_once('conn_inc.php');
                    $conn->query("SET time_zone = '+05:00'");
                    
                    // Modified query to only show active expenses (status = 1)
                    $query = "SELECT e.*, ec.title as expense_category_title 
                              FROM expenses e
                              LEFT JOIN expense_categories ec ON e.expense_categories_id = ec.id
                              WHERE e.status = 1
                              ORDER BY e.invoice_date DESC, e.id DESC";
                    
                    $result = $conn->query($query);
                    ?>

                    <?php if ($result && $result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover expenses-table">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['sr_no']; ?></th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['invoice_date']; ?></span>
                                                <span class="urdu-field-label">انوائس کی تاریخ</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['expense_head']; ?></span>
                                                <span class="urdu-field-label">اخراجات کا زمرہ</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['payment_type']; ?></span>
                                                <span class="urdu-field-label">ادائیگی کی قسم</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['description']; ?></span>
                                                <span class="urdu-field-label">تفصیل</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['total_amount']; ?></span>
                                                <span class="urdu-field-label">کل رقم</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['inventory_details']; ?></span>
                                                <span class="urdu-field-label">انوینٹری کی تفصیلات</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="dual-field">
                                                <span class="en-field-label"><?php echo $translations[$lang]['actions']; ?></span>
                                                <span class="urdu-field-label">کارروائیاں</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; while ($row = $result->fetch_assoc()): 
                                        $serial = $counter++;
                                    ?>
                                        <tr>
                                            <td data-label="# / نمبر"><?php echo $serial; ?></td>
                                            <td class="date-column" data-label="Date / تاریخ: <?php echo date('Y-m-d', strtotime($row['invoice_date'])); ?>">
                                                <?php echo htmlspecialchars(date('Y-m-d', strtotime($row['invoice_date']))); ?>
                                            </td>
                                            <td style="font-size: 16px !important;" data-label="Category / زمرہ: <?php echo htmlspecialchars($row['expense_category_title'] ? $row['expense_category_title'] : 'N/A'); ?>">
                                                <?php echo htmlspecialchars($row['expense_category_title'] ? $row['expense_category_title'] : 'N/A'); ?>
                                            </td>
                                            <td data-label="Payment / ادائیگی: <?php echo htmlspecialchars($row['payment_type']); ?>">
                                                <?php echo htmlspecialchars($row['payment_type']); ?>
                                            </td>
                                            <td class="description-column" data-label="Description / تفصیل: <?php echo htmlspecialchars($row['description'] ? $row['description'] : '-'); ?>" title="<?php echo htmlspecialchars($row['description']); ?>">
                                                <span><?php echo htmlspecialchars($row['description'] ? $row['description'] : '-'); ?></span>
                                            </td>
                                            <td class="amount-column" data-label="Total Amount / کل رقم: <?php echo number_format($row['total_amount'], 2); ?>">
                                                <?php echo number_format($row['total_amount'], 2); ?>
                                            </td>
                                            <td data-label="Inventory / انوینٹری:" class="text-center">
                                                <?php if ($row['id']): ?>
                                                    <button type="button" class="btn btn-xs btn-info view-details-btn" style="font-size: 12px;"
                                                            data-expense-id="<?php echo $row['id']; ?>"
                                                            data-expense-category="<?php echo htmlspecialchars($row['expense_category_title']); ?>">
                                                        <span class="glyphicon glyphicon-list-alt"></span> View Details / تفصیلات دیکھیں
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Actions / کارروائیاں:">
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning" style="display: inline-block; margin: 2px; font-size: 14px;">
                                                    <?php echo $translations[$lang]['edit']; ?> / ترمیم کریں
                                                </a>
                                                <a href="?delete=<?php echo $row['id']; ?>" 
                                                   class="btn btn-xs btn-danger btn-delete" 
                                                   onclick="return confirm('<?php echo $translations[$lang]['delete_confirm']; ?>\n\nAmount: <?php echo number_format($row['total_amount'], 2); ?>\nCategory: <?php echo htmlspecialchars($row['expense_category_title'] ?? 'N/A'); ?>');"
                                                   style="display: inline-block; margin: 2px; font-size: 14px;">
                                                    <?php echo $translations[$lang]['delete']; ?> / حذف کریں
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="active">
                                        <th colspan="5" class="text-right">
                                            <div class="dual-field">
                                                <span class="en-field-label">Total Active Expenses:</span>
                                                <span class="urdu-field-label">کل فعال اخراجات</span>
                                            </div>
                                        </th>
                                        <th class="amount-column">
                                            <?php
                                            $total_query = $conn->query("SELECT SUM(total_amount) as grand_total FROM expenses WHERE status = 1");
                                            if ($total_query) {
                                                echo number_format($total_query->fetch_assoc()['grand_total'] ?? 0, 2);
                                            }
                                            ?>
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <?php echo $translations[$lang]['no_records']; ?> / کوئی ریکارڈ نہیں ملا
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════
     SLIDE-OVER DETAILS PANEL  (replaces the modal)
     ══════════════════════════════════════════════════ -->
<div id="detailsOverlay"></div>

<div id="detailsPanel">
    <div id="detailsPanelHeader">
        <h4>
            <span class="glyphicon glyphicon-list-alt"></span>
            <?php echo $translations[$lang]['product_details']; ?> / مصنوعات کی تفصیلات
            <small id="expenseCategoryLabel" style="font-weight:normal; font-size:13px;"></small>
        </h4>
        <button id="detailsPanelClose" title="Close / بند کریں">&times;</button>
    </div>

    <div id="detailsPanelBody">
        <input type="hidden" id="panel_expense_id" value="">

        <!-- ── Inline: Add New Product box (hidden by default) ── -->
        <div id="addProductBox">
            <h5><span class="glyphicon glyphicon-plus-sign"></span> <?php echo $translations[$lang]['add_new_product']; ?> / نئی مصنوعات شامل کریں</h5>
            <div class="row">
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <div class="dual-field">
                            <label class="en-field-label"><?php echo $translations[$lang]['product_name']; ?> *</label>
                            <label class="urdu-field-label">مصنوعات کا نام *</label>
                        </div>
                        <input type="text" class="form-control input-sm" id="new_product_name" placeholder="Product name / مصنوعات کا نام">
                    </div>
                </div>
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <div class="dual-field">
                            <label class="en-field-label"><?php echo $translations[$lang]['category']; ?> *</label>
                            <label class="urdu-field-label">زمرہ *</label>
                        </div>
                        <select class="form-control input-sm" id="new_category">
                            <option value="">-- Select / منتخب کریں --</option>
                            <option value="Furniture">Furniture / فرنیچر</option>
                            <option value="Stationery">Stationery / اسٹیشنری</option>
                            <option value="Electronics">Electronics / الیکٹرانکس</option>
                            <option value="Clothing">Clothing / کپڑے</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <div class="dual-field">
                            <label class="en-field-label"><?php echo $translations[$lang]['unit']; ?> *</label>
                            <label class="urdu-field-label">یونٹ *</label>
                        </div>
                        <input type="text" class="form-control input-sm" id="new_unit" placeholder="e.g. Pcs, Kg / مثال: پی سیز، کلوگرام">
                    </div>
                </div>
            </div>
            <div class="text-right">
                <button type="button" class="btn btn-default btn-sm" id="btnCancelProduct"><?php echo $translations[$lang]['cancel']; ?> / منسوخ کریں</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveProduct"><?php echo $translations[$lang]['save_details']; ?> / محفوظ کریں</button>
            </div>
        </div>

        <!-- ── Add Product Detail Form ── -->
        <div class="panel panel-default">
            <div class="panel-heading" style="padding: 8px 12px;">
                <strong>Add Product to Expense / اخراجات میں مصنوعات شامل کریں</strong>
            </div>
            <div class="panel-body" style="padding: 12px;">
                <div class="row">
                    <div class="col-sm-5 col-xs-12">
                        <div class="form-group">
                            <div class="dual-field">
                                <label class="en-field-label"><?php echo $translations[$lang]['select_product']; ?> *</label>
                                <label class="urdu-field-label">مصنوعات منتخب کریں *</label>
                            </div>
                            <select class="form-control input-sm" id="product_id">
                                <option value="">-- Select Product / مصنوعات منتخب کریں --</option>
                            </select>
                            <button type="button" class="btn btn-xs btn-primary" style="margin-top:5px;" id="btnShowAddProduct">
                                <span class="glyphicon glyphicon-plus"></span> <?php echo $translations[$lang]['add_new_product']; ?> / نئی مصنوعات شامل کریں
                            </button>
                        </div>
                    </div>
                    <div class="col-sm-2 col-xs-6">
                        <div class="form-group">
                            <div class="dual-field">
                                <label class="en-field-label"><?php echo $translations[$lang]['quantity']; ?> *</label>
                                <label class="urdu-field-label">مقدار *</label>
                            </div>
                            <input type="number" class="form-control input-sm" id="quantity" step="0.01" value="1">
                        </div>
                    </div>
                    <div class="col-sm-2 col-xs-6">
                        <div class="form-group">
                            <div class="dual-field">
                                <label class="en-field-label"><?php echo $translations[$lang]['unit_price']; ?> *</label>
                                <label class="urdu-field-label">فی یونٹ قیمت *</label>
                            </div>
                            <input type="number" class="form-control input-sm" id="unit_price" step="0.01">
                        </div>
                    </div>
                    <div class="col-sm-3 col-xs-12">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-success btn-sm btn-block" id="btnAddDetail">
                                <span class="glyphicon glyphicon-plus"></span> <?php echo $translations[$lang]['save_details']; ?> / محفوظ کریں
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <div class="form-group">
                            <div class="dual-field">
                                <label class="en-field-label"><?php echo $translations[$lang]['description']; ?></label>
                                <label class="urdu-field-label">تفصیل</label>
                            </div>
                            <textarea class="form-control input-sm" id="detail_description" rows="2" placeholder="Enter description / تفصیل درج کریں"></textarea>
                        </div>
                    </div>
                </div>
                <div id="detailFormAlert"></div>
            </div>
        </div>

        <!-- ── Inventory Details Table ── -->
        <div class="panel panel-info">
            <div class="panel-heading" style="padding: 8px 12px;">
                <strong><?php echo $translations[$lang]['inventory_details']; ?> / انوینٹری کی تفصیلات</strong>
            </div>
            <div class="panel-body" style="padding: 10px;">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped product-details-table" id="inventoryDetailsTable">
                        <thead>
                            <tr>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['product_name']; ?></span>
                                        <span class="urdu-field-label">مصنوعات کا نام</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['category']; ?></span>
                                        <span class="urdu-field-label">زمرہ</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['quantity']; ?></span>
                                        <span class="urdu-field-label">مقدار</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['unit']; ?></span>
                                        <span class="urdu-field-label">یونٹ</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['unit_price']; ?></span>
                                        <span class="urdu-field-label">فی یونٹ قیمت</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['total_price']; ?></span>
                                        <span class="urdu-field-label">کل قیمت</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['description']; ?></span>
                                        <span class="urdu-field-label">تفصیل</span>
                                    </div>
                                </th>
                                <th>
                                    <div class="dual-field">
                                        <span class="en-field-label"><?php echo $translations[$lang]['actions']; ?></span>
                                        <span class="urdu-field-label">کارروائیاں</span>
                                    </div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="noDetailsRow">
                                <td colspan="8" class="text-center">No product details added yet / ابھی تک کوئی مصنوعات کی تفصیلات شامل نہیں کی گئیں</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="active">
                                <th colspan="5" class="text-right">
                                    <div class="dual-field">
                                        <span class="en-field-label">Total Inventory Value:</span>
                                        <span class="urdu-field-label">کل انوینٹری قیمت:</span>
                                    </div>
                                </th>
                                <th id="inventoryTotal" class="amount-column">0.00</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div><!-- /detailsPanelBody -->

    <div id="detailsPanelFooter">
        <button type="button" class="btn btn-default" id="btnClosePanel"><?php echo $translations[$lang]['close']; ?> / بند کریں</button>
    </div>
</div><!-- /detailsPanel -->


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
$(document).ready(function () {

    /* ═══════════════════════════════════════════════
       SLIDE-OVER PANEL helpers
    ═══════════════════════════════════════════════ */
    function openPanel(expenseId, expenseCategory) {
        $('#panel_expense_id').val(expenseId);
        $('#expenseCategoryLabel').text(expenseCategory ? ' — ' + expenseCategory : '');
        $('#detailsOverlay').fadeIn(200);
        setTimeout(function () { $('#detailsPanel').addClass('open'); }, 10);
        $('body').css('overflow', 'hidden');
        loadProducts();
        loadInventoryDetails(expenseId);
    }

    function closePanel() {
        $('#detailsPanel').removeClass('open');
        setTimeout(function () { $('#detailsOverlay').fadeOut(200); }, 320);
        $('body').css('overflow', '');
        // Hide the add-product box if open
        $('#addProductBox').slideUp(150);
    }

    $('#detailsPanelClose, #btnClosePanel').on('click', closePanel);
    $('#detailsOverlay').on('click', closePanel);

    /* ── "Add Details" button from main form ── */
    $('#btnOpenDetailsFromForm').on('click', function () {
        var expenseId = $('#expense_id').val();
        if (!expenseId) { alert('Please save the expense first. / براہ کرم پہلے اخراجات محفوظ کریں۔'); return; }
        var expenseCategory = $('#expense_categories_id option:selected').text();
        openPanel(expenseId, expenseCategory);
    });

    /* ── "View Details" button from table ── */
    $(document).on('click', '.view-details-btn', function () {
        var expenseId       = $(this).data('expense-id');
        var expenseCategory = $(this).data('expense-category');
        openPanel(expenseId, expenseCategory);
    });

    /* ═══════════════════════════════════════════════
       Add-Product inline box toggle
    ═══════════════════════════════════════════════ */
    $('#btnShowAddProduct').on('click', function () {
        $('#addProductBox').slideToggle(200);
        $('#new_product_name').focus();
    });

    $('#btnCancelProduct').on('click', function () {
        $('#addProductBox').slideUp(150);
        $('#new_product_name, #new_unit').val('');
        $('#new_category').val('');
    });

    /* ═══════════════════════════════════════════════
       AJAX helpers
    ═══════════════════════════════════════════════ */
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function showDetailAlert(msg, type) {
        var cls = type === 'success' ? 'alert-success' : 'alert-danger';
        var html = '<div class="alert ' + cls + ' alert-dismissible" style="margin-top:8px;">' +
            '<a href="#" class="close" data-dismiss="alert">&times;</a>' + msg + '</div>';
        $('#detailFormAlert').html(html);
        setTimeout(function () { $('#detailFormAlert .alert').fadeOut(); }, 3000);
    }

    function loadProducts() {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_products' },
            dataType: 'json',
            success: function (r) {
                if (r.success && r.products) {
                    var $sel = $('#product_id');
                    var prev = $sel.val();
                    $sel.empty().append('<option value="">-- Select Product / مصنوعات منتخب کریں --</option>');
                    $.each(r.products, function (i, p) {
                        $sel.append('<option value="' + p.id + '">' +
                            escapeHtml(p.product_name) + ' (' + escapeHtml(p.category) + ' - ' + escapeHtml(p.unit) + ')</option>');
                    });
                    if (prev) $sel.val(prev);
                }
            }
        });
    }

    function loadInventoryDetails(expenseId) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_inventory_details', expense_id: expenseId },
            dataType: 'json',
            success: function (r) {
                var $tbody = $('#inventoryDetailsTable tbody');
                $tbody.empty();
                if (!r.success || r.details.length === 0) {
                    $tbody.append('<tr id="noDetailsRow"><td colspan="8" class="text-center">No product details added yet / ابھی تک کوئی مصنوعات کی تفصیلات شامل نہیں کی گئیں</td></tr>');
                    $('#inventoryTotal').text('0.00');
                    return;
                }
                var total = 0;
                $.each(r.details, function (i, d) {
                    total += parseFloat(d.total_price);
                    $tbody.append(
                        '<tr data-detail-id="' + d.id + '">' +
                        '<td>' + escapeHtml(d.product_name) + '</td>' +
                        '<td>' + escapeHtml(d.category) + '</td>' +
                        '<td class="text-right">' + parseFloat(d.quantity).toFixed(2) + '</td>' +
                        '<td>' + escapeHtml(d.unit) + '</td>' +
                        '<td class="text-right">' + parseFloat(d.unit_price).toFixed(2) + '</td>' +
                        '<td class="text-right">' + parseFloat(d.total_price).toFixed(2) + '</td>' +
                        '<td>' + escapeHtml(d.description || '-') + '</td>' +
                        '<td class="text-center"><button type="button" class="btn btn-xs btn-danger delete-detail-btn" data-detail-id="' + d.id + '">' +
                        '<span class="glyphicon glyphicon-trash"></span> Delete / حذف کریں</button></td>' +
                        '</tr>'
                    );
                });
                $('#inventoryTotal').text(total.toFixed(2));
            }
        });
    }

    /* ── Add inventory detail ── */
    $('#btnAddDetail').on('click', function () {
        var expenseId = $('#panel_expense_id').val();
        var productId = $('#product_id').val();
        var quantity  = $('#quantity').val();
        var unitPrice = $('#unit_price').val();
        var desc      = $('#detail_description').val();

        if (!expenseId)                         { showDetailAlert('Expense ID not found. / اخراجات کی شناخت نہیں ملی', 'error'); return; }
        if (!productId)                         { showDetailAlert('Please select a product. / براہ کرم مصنوعات منتخب کریں۔', 'error'); return; }
        if (!quantity || parseFloat(quantity) <= 0) { showDetailAlert('Please enter a valid quantity. / براہ کرم درست مقدار درج کریں۔', 'error'); return; }
        if (!unitPrice || parseFloat(unitPrice) <= 0) { showDetailAlert('Please enter a valid unit price. / براہ کرم درست فی یونٹ قیمت درج کریں۔', 'error'); return; }

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'add_inventory_detail', expense_id: expenseId,
                    product_id: productId, quantity: quantity, unit_price: unitPrice, description: desc },
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    $('#product_id').val('');
                    $('#quantity').val('1');
                    $('#unit_price').val('');
                    $('#detail_description').val('');
                    loadInventoryDetails(expenseId);
                    showDetailAlert('<strong>Success!</strong> ' + r.message + ' / <strong>کامیابی!</strong>', 'success');
                } else {
                    showDetailAlert('Error: ' + r.message + ' / خرابی:', 'error');
                }
            },
            error: function () { showDetailAlert('Server error. Please try again. / سرور خرابی۔ براہ کرم دوبارہ کوشش کریں۔', 'error'); }
        });
    });

    /* ── Delete inventory detail ── */
    $(document).on('click', '.delete-detail-btn', function () {
        var detailId  = $(this).data('detail-id');
        var expenseId = $('#panel_expense_id').val();
        if (!confirm('Are you sure you want to delete this product detail? / کیا آپ واقعی اس مصنوعات کی تفصیل کو حذف کرنا چاہتے ہیں؟')) return;

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'delete_inventory_detail', detail_id: detailId },
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    loadInventoryDetails(expenseId);
                    showDetailAlert('<strong>Success!</strong> ' + r.message + ' / <strong>کامیابی!</strong>', 'success');
                } else {
                    showDetailAlert('Error: ' + r.message + ' / خرابی:', 'error');
                }
            },
            error: function () { showDetailAlert('Server error. Please try again. / سرور خرابی۔ براہ کرم دوبارہ کوشش کریں۔', 'error'); }
        });
    });

    /* ── Save new product ── */
    $('#btnSaveProduct').on('click', function () {
        var name     = $('#new_product_name').val().trim();
        var category = $('#new_category').val();
        var unit     = $('#new_unit').val().trim();

        if (!name)     { alert('Please enter product name. / براہ کرم مصنوعات کا نام درج کریں۔'); return; }
        if (!category) { alert('Please select a category. / براہ کرم زمرہ منتخب کریں۔'); return; }
        if (!unit)     { alert('Please enter unit. / براہ کرم یونٹ درج کریں۔'); return; }

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'add_product', product_name: name, category: category, unit: unit },
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    $('#new_product_name, #new_unit').val('');
                    $('#new_category').val('');
                    $('#addProductBox').slideUp(150);
                    loadProducts();   // refresh dropdown
                    showDetailAlert('<strong>Product added!</strong> ' + escapeHtml(r.product_name) + ' / <strong>مصنوعات شامل ہوگئی!</strong>', 'success');
                    // Auto-select the new product
                    setTimeout(function () { $('#product_id').val(r.product_id); }, 400);
                } else {
                    alert('Error: ' + r.message + ' / خرابی:');
                }
            },
            error: function () { alert('Server error. Please try again. / سرور خرابی۔ براہ کرم دوبارہ کوشش کریں۔'); }
        });
    });

    /* ═══════════════════════════════════════════════
       Main form validation (unchanged logic)
    ═══════════════════════════════════════════════ */
    $('#expenseForm').on('submit', function (e) {
        var totalAmount = parseFloat($('#total_amount').val()) || 0;
        var category    = $('#expense_categories_id').val();
        if (!category || category === '') {
            e.preventDefault(); alert('Please select an expense category. / براہ کرم اخراجات کا زمرہ منتخب کریں۔'); return false;
        }
        if (totalAmount === 0) {
            e.preventDefault(); alert('Amount cannot be zero. / رقم صفر نہیں ہو سکتی۔'); return false;
        }
        return true;
    });

    /* Auto-hide session alerts */
    setTimeout(function () { $('.custom-alert').fadeOut('slow'); }, 10000);
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>