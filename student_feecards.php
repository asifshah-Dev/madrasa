<?php 
require_once('security.php');

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Create database connection
require_once('conn_inc.php');

// Initialize display variables
$display_data = null;
$selected_session_id = 0;
$selected_class_id = 0;
$print_type = '';
$all_students_data = []; // Store all students data for filtering

// Check if we have data in session from previous request
if (isset($_SESSION['form_data'])) {
    $selected_session_id = $_SESSION['form_data']['session_id'] ?? 0;
    $selected_class_id = $_SESSION['form_data']['class_id'] ?? 0;
    $print_type = $_SESSION['form_data']['print_type'] ?? '';
    
    // Clear the session data to prevent resubmission
    unset($_SESSION['form_data']);
}

// Clear print data if coming back from print view
if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    unset($_SESSION['print_data']);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = 'Invalid request!';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $selected_session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $selected_class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
    $print_type = isset($_POST['print_type']) ? $_POST['print_type'] : '';
    $action = isset($_POST['action']) ? $_POST['action'] : 'view';
    
    // Check for selected students - handle both array and comma-separated string
    $selected_students = [];
    if (isset($_POST['selected_students'])) {
        if (is_array($_POST['selected_students'])) {
            $selected_students = array_map('intval', $_POST['selected_students']);
        } elseif (is_string($_POST['selected_students']) && !empty($_POST['selected_students'])) {
            $selected_students = array_map('intval', explode(',', $_POST['selected_students']));
        }
    }
    
    if ($selected_session_id > 0 && $selected_class_id > 0 && $print_type != '') {
        try {
            // Fetch session and class details
            $session_query = "SELECT title, from_dated, to_dated FROM sessions WHERE id = ? AND status = 0";
            $session_stmt = $conn->prepare($session_query);
            $session_stmt->bind_param("i", $selected_session_id);
            $session_stmt->execute();
            $session_result = $session_stmt->get_result();
            
            if ($session_row = $session_result->fetch_assoc()) {
                $session_title = $session_row['title'] . ' (' . date('M Y', strtotime($session_row['from_dated'])) . ' - ' . date('M Y', strtotime($session_row['to_dated'])) . ')';
                
                // Fetch class details
                $class_query = "SELECT title FROM classes WHERE id = ?";
                $class_stmt = $conn->prepare($class_query);
                $class_stmt->bind_param("i", $selected_class_id);
                $class_stmt->execute();
                $class_result = $class_stmt->get_result();
                $class_row = $class_result->fetch_assoc();
                $class_title = $class_row['title'] ?? '';
                
                // Fetch students for the selected session and class
                $query = "SELECT sr.id, sr.name, sr.father_name, sr.mobile, 
                                 sr.is_old_dues, sr.old_dues_amount,
                                 sc.id AS student_class_id, sc.promotion_date
                          FROM student_registration sr 
                          JOIN student_class sc ON sr.id = sc.student_registration_id 
                          WHERE sc.session_id = ? AND sc.class_id = ? AND sr.status = 0 AND sc.status = 0
                          ORDER BY sr.name";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $selected_session_id, $selected_class_id);
                $stmt->execute();
                $result = $stmt->get_result();

                // Process students data
                $students_data = [];
                $total_remaining = 0;
                $total_old_dues = 0;
                
                while ($student_row = $result->fetch_assoc()) {
                    $student_id = $student_row['id'];
                    
                    // Get all fee cards for this student in the selected session
                    $fee_cards_query = "SELECT sfc.id, sfc.fee_type_id, sfc.total_amount, 
                                               sfc.due_date, ft.title as fee_type_title,
                                               DATE_FORMAT(sfc.due_date, '%M %Y') AS month_display,
                                               DATE_FORMAT(sfc.due_date, '%Y-%m') AS month_key
                                        FROM student_fee_card sfc
                                        JOIN student_class sc ON sfc.student_class_id = sc.id
                                        JOIN fee_types ft ON sfc.fee_type_id = ft.id
                                        WHERE sc.student_registration_id = ?
                                        AND sc.session_id = ?
                                        ORDER BY sfc.due_date";
                    $fee_cards_stmt = $conn->prepare($fee_cards_query);
                    $fee_cards_stmt->bind_param("ii", $student_id, $selected_session_id);
                    $fee_cards_stmt->execute();
                    $fee_cards_result = $fee_cards_stmt->get_result();
                    
                    $all_months = [];
                    $paid_until = null;
                    $paid_until_display = null;
                    $pending_fees = [];
                    $total_pending = 0;
                    
                    // First pass: collect all months and calculate payments
                    while ($fee_card = $fee_cards_result->fetch_assoc()) {
                        $month_key = $fee_card['month_key'];
                        
                        // Calculate actual paid amount including discounts (like in fee_collection.php)
                        $payment_query = "SELECT COALESCE(SUM(paid_amount), 0) as total_paid, 
                                                 COALESCE(SUM(discount_amount), 0) as total_discount 
                                          FROM student_fee_payments 
                                          WHERE fee_card_id = ? AND status = 'completed'";
                        $payment_stmt = $conn->prepare($payment_query);
                        $payment_stmt->bind_param("i", $fee_card['id']);
                        $payment_stmt->execute();
                        $payment_result = $payment_stmt->get_result();
                        $payment_data = $payment_result->fetch_assoc();
                        
                        $total_paid = $payment_data['total_paid'] ?? 0;
                        $total_discount = $payment_data['total_discount'] ?? 0;
                        $total_cleared = $total_paid + $total_discount;
                        $due_amount = $fee_card['total_amount'] - $total_cleared;
                        
                        // Store month data
                        if (!isset($all_months[$month_key])) {
                            $all_months[$month_key] = [
                                'display' => $fee_card['month_display'],
                                'date' => $fee_card['due_date'],
                                'fees' => [],
                                'total_amount' => 0,
                                'total_cleared' => 0,
                                'is_fully_paid' => true
                            ];
                        }
                        
                        $all_months[$month_key]['fees'][] = [
                            'fee_type' => $fee_card['fee_type_title'],
                            'total_amount' => $fee_card['total_amount'],
                            'paid_amount' => $total_paid,
                            'discount_amount' => $total_discount,
                            'due_amount' => max(0, $due_amount),
                            'is_paid' => ($due_amount <= 0)
                        ];
                        
                        $all_months[$month_key]['total_amount'] += $fee_card['total_amount'];
                        $all_months[$month_key]['total_cleared'] += $total_cleared;
                        
                        // If any fee in this month has due amount > 0, month is not fully paid
                        if ($due_amount > 0) {
                            $all_months[$month_key]['is_fully_paid'] = false;
                        }
                    }
                    
                    // Sort months chronologically
                    ksort($all_months);
                    
                    // Find consecutive paid months from the beginning
                    $consecutive_paid = true;
                    foreach ($all_months as $month_key => $month_data) {
                        if ($month_data['is_fully_paid'] && $month_data['total_cleared'] >= $month_data['total_amount']) {
                            // This month is fully paid
                            $paid_until = $month_key;
                            $paid_until_display = $month_data['display'];
                        } else {
                            // Found first unpaid month, stop checking
                            break;
                        }
                    }
                    
                    // Collect pending fees (months after the last fully paid month)
                    $start_collecting = false;
                    foreach ($all_months as $month_key => $month_data) {
                        // If we haven't set paid_until, collect all months
                        // If we have paid_until, collect only months after it
                        if ($paid_until === null) {
                            $start_collecting = true;
                        } elseif ($month_key > $paid_until) {
                            $start_collecting = true;
                        }
                        
                        if ($start_collecting) {
                            foreach ($month_data['fees'] as $fee) {
                                if ($fee['due_amount'] > 0) {
                                    $pending_fees[] = [
                                        'fee_type' => $fee['fee_type'],
                                        'month' => $month_data['display'],
                                        'amount' => $fee['due_amount'],
                                        'total_amount' => $fee['total_amount'],
                                        'paid_amount' => $fee['paid_amount'],
                                        'discount_amount' => $fee['discount_amount']
                                    ];
                                    $total_pending += $fee['due_amount'];
                                }
                            }
                        }
                    }
                    
                    // Check for old dues
                    $old_dues = 0;
                    if ($student_row['is_old_dues'] == 1 && $student_row['old_dues_amount'] > 0) {
                        $old_dues = $student_row['old_dues_amount'];
                    }
                    
                    $total_due = $total_pending + $old_dues;
                    
                    // For fee cards, include all students even with zero dues
                    // For defaulter list, only include those with dues
                    if ($print_type == 'feecards' || ($print_type == 'defaulter' && $total_due > 0)) {
                        $student_data = [
                            'id' => $student_row['id'],
                            'name' => $student_row['name'],
                            'father_name' => $student_row['father_name'],
                            'mobile' => $student_row['mobile'],
                            'student_class_id' => $student_row['student_class_id'],
                            'old_dues_amount' => $old_dues,
                            'total_pending' => $total_pending,
                            'total_due' => $total_due,
                            'paid_until_display' => $paid_until_display,
                            'pending_fees' => $pending_fees,
                            'all_months' => $all_months
                        ];
                        
                        $students_data[] = $student_data;
                        $all_students_data[] = $student_data;
                        
                        if ($total_due > 0) {
                            $total_remaining += $total_due;
                            $total_old_dues += $old_dues;
                        }
                    }
                }
                
                // Check if there are any students
                if (empty($students_data)) {
                    $_SESSION['message'] = 'No students found for this session and class.';
                    $_SESSION['message_type'] = 'warning';
                } else {
                    // For print actions, filter students based on selection
                    if (($action == 'print' || $action == 'thermal_print') && !empty($selected_students)) {
                        $filtered_students = [];
                        $filtered_total_remaining = 0;
                        $filtered_total_old_dues = 0;
                        
                        foreach ($students_data as $student) {
                            if (in_array($student['id'], $selected_students)) {
                                $filtered_students[] = $student;
                                $filtered_total_remaining += $student['total_due'];
                                $filtered_total_old_dues += $student['old_dues_amount'];
                            }
                        }
                        
                        if (empty($filtered_students)) {
                            $_SESSION['message'] = 'Please select at least one student to print.';
                            $_SESSION['message_type'] = 'warning';
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit();
                        }
                        
                        $students_data = $filtered_students;
                        $total_remaining = $filtered_total_remaining;
                        $total_old_dues = $filtered_total_old_dues;
                    }
                    
                    // Store data for display or print
                    $display_data = [
                        'print_type' => $print_type,
                        'session_title' => $session_title,
                        'class_title' => $class_title,
                        'students' => $students_data,
                        'total_remaining' => $total_remaining,
                        'total_old_dues' => $total_old_dues,
                        'all_students' => $all_students_data,
                        'selected_students' => $selected_students,
                        'session_id' => $selected_session_id,
                        'class_id' => $selected_class_id
                    ];
                    
                    // Store form data in session to maintain state
                    $_SESSION['form_data'] = [
                        'session_id' => $selected_session_id,
                        'class_id' => $selected_class_id,
                        'print_type' => $print_type,
                        'selected_students' => $selected_students
                    ];
                    
                    // Store print data in session if action is print
                    if ($action == 'print') {
                        $_SESSION['print_data'] = $display_data;
                        header("Location: print_view.php");
                        exit();
                    }
                    
                    // Store print data in session if action is thermal_print - Changed to thermal_print_view2.php
                    if ($action == 'thermal_print') {
                        $_SESSION['print_data'] = $display_data;
                        header("Location: thermal_print_view2.php");
                        exit();
                    }
                }
                
            } else {
                $_SESSION['message'] = "Invalid session selected.";
                $_SESSION['message_type'] = 'danger';
            }
            
        } catch (Exception $e) {
            $_SESSION['message'] = "Database error: " . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        if ($selected_session_id == 0) {
            $_SESSION['message'] = 'Please select a session.';
        } elseif ($selected_class_id == 0) {
            $_SESSION['message'] = 'Please select a class.';
        } elseif ($print_type == '') {
            $_SESSION['message'] = 'Please select a print type.';
        }
        $_SESSION['message_type'] = 'warning';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get sessions for dropdown
$sessions = [];
try {
    $query = "SELECT id, title, from_dated, to_dated 
              FROM sessions 
              WHERE status = 0 
              ORDER BY from_dated DESC";
    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $sessions[] = $row;
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get classes for dropdown - FIXED: Removed the status condition that was filtering out all classes
$classes = [];
try {
    // First, let's check what columns are available in the classes table
    $check_columns = $conn->query("SHOW COLUMNS FROM classes");
    $has_status_column = false;
    $has_active_column = false;
    while ($col = $check_columns->fetch_assoc()) {
        if ($col['Field'] == 'status') $has_status_column = true;
        if ($col['Field'] == 'active') $has_active_column = true;
    }
    
    // Build query based on available columns
    if ($has_status_column) {
        $query = "SELECT id, title FROM classes WHERE status = 0 OR status IS NULL ORDER BY title";
    } elseif ($has_active_column) {
        $query = "SELECT id, title FROM classes WHERE active = 1 ORDER BY title";
    } else {
        $query = "SELECT id, title FROM classes ORDER BY title";
    }
    
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
    
    // Debug: If still no classes, try a simpler query
    if (empty($classes)) {
        $query = "SELECT id, title FROM classes ORDER BY title";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $classes[] = $row;
            }
        }
    }
} catch (Exception $e) {
    // Don't show error, just leave classes empty
    error_log("Error fetching classes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Fee Reminder Slip - فیس ریمائنڈر سلپ</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Add Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        /* Fix navbar size - make it compact */
        .navbar {
            min-height: 50px;
            margin-bottom: 20px;
        }
        
        .navbar-brand {
            padding: 15px 15px;
            height: 50px;
            font-size: 18px;
            line-height: 20px;
        }
        
        .navbar-nav > li > a {
            padding-top: 15px;
            padding-bottom: 15px;
            line-height: 20px;
        }
        
        .navbar-toggle {
            margin-top: 8px;
            margin-bottom: 8px;
            padding: 9px 10px;
        }
        
        .navbar-collapse {
            max-height: 340px;
        }
        
        /* Main container styles */
        .container {
            max-width: 1200px;
            margin-top: 20px;
            padding-left: 15px;
            padding-right: 15px;
        }
        
        .panel {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .btn-group {
            margin-top: 20px;
            text-align: center;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-group .btn {
            margin: 0;
            min-width: 200px;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .logo-container img {
            max-height: 80px;
        }
        
        .alert {
            margin-top: 20px;
        }
        
        .form-control {
            height: 45px;
            font-size: 16px;
        }
        
        .btn-lg {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .panel-heading {
            background-color: #007bff !important;
            color: white !important;
        }
        
        .results-section {
            margin-top: 30px;
            display: <?php echo $display_data ? 'block' : 'none'; ?>;
        }
        
        /* Fee Reminder Slip Styles */
        .reminder-slip {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            max-width: 800px;
            margin: 0 auto 30px auto;
            padding: 20px;
            border: 2px solid #007bff;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            direction: rtl;
            text-align: right;
        }
        
        .reminder-header {
            text-align: center;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .reminder-header h3 {
            color: #007bff;
            font-weight: bold;
            margin: 5px 0;
            font-size: 26px;
        }
        
        .reminder-header .urdu-title {
            color: #28a745;
            font-weight: 800;
            font-size: 22px;
            margin: 5px 0;
        }
        
        .student-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-right: 4px solid #007bff;
            direction: rtl;
            text-align: right;
        }
        
        .student-info .info-row {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            padding: 6px 0;
            border-bottom: 1px dotted #ddd;
            direction: rtl;
            text-align: right;
            flex-wrap: wrap;
        }
        
        .student-info .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 700;
            color: #007bff;
            font-size: 18px;
            text-align: right;
            min-width: 160px;
        }
        
        .info-value {
            font-size: 16px;
            text-align: right;
            margin-left: 10px;
            font-weight: normal;
            word-break: break-word;
        }
        
        .paid-message {
            background-color: #e8f5e9;
            color: #28a745;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 18px;
            border-right: 4px solid #28a745;
            text-align: center;
            direction: rtl;
        }
        
        .pending-fees {
            margin-top: 15px;
            direction: rtl;
            text-align: right;
        }
        
        .pending-fees .pending-title {
            font-size: 20px;
            font-weight: 800;
            color: #dc3545;
            margin-bottom: 15px;
            text-align: right;
        }
        
        .pending-fees table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            direction: rtl;
        }
        
        .pending-fees th {
            background-color: #007bff;
            color: white;
            padding: 10px;
            text-align: right;
            font-size: 16px;
            font-weight: bold;
        }
        
        .pending-fees td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
            font-size: 15px;
            text-align: right;
        }
        
        .pending-fees tr:last-child td {
            border-bottom: none;
        }
        
        .pending-fees .amount-cell {
            text-align: right;
            font-weight: bold;
            color: #dc3545;
            font-size: 16px;
        }
        
        .old-dues {
            background-color: #fff3cd;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
            border-right: 4px solid #ffc107;
            font-size: 16px;
            font-weight: bold;
            direction: rtl;
            text-align: right;
        }
        
        .grand-total {
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-weight: bold;
            font-size: 20px;
            text-align: right;
            direction: rtl;
        }
        
        .footer-note {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            direction: rtl;
        }
        
        .footer-note p {
            margin: 8px 0;
            font-weight: bold;
            font-size: 16px;
        }
        
        /* Sticky Student Selection Container */
        .student-selection-container {
            background-color: #f8f9fa;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            border: 1px solid #dee2e6;
            position: sticky;
            top: 70px;
            z-index: 1000;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .student-selection-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .student-selection-title {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selection-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .print-selected-buttons {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-left: auto;
            flex-wrap: wrap;
        }
        
        .print-selected-buttons .btn {
            min-width: 160px;
            font-size: 14px;
            padding: 8px 16px;
            margin: 0;
        }
        
        .student-checkbox {
            transform: scale(1.3);
            margin-right: 10px;
        }
        
        .selected-count {
            background-color: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .reminder-checkbox {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            direction: rtl;
            text-align: right;
        }
        
        .bulk-print-buttons {
            text-align: center;
            margin: 20px 0;
            padding-top: 20px;
            border-top: 2px solid #dee2e6;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        .bulk-print-buttons .btn {
            margin: 0;
            min-width: 200px;
        }
        
        .thermal-print-btn {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        
        .thermal-print-btn:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 18px;
        }
        
        /* Defaulter list styles */
        .defaulter-table-display {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            margin-top: 10px;
            direction: rtl;
        }
        
        .defaulter-table-display th,
        .defaulter-table-display td {
            padding: 8px;
            border: 1px solid #000;
            text-align: center;
            vertical-align: middle;
        }
        
        .defaulter-table-display th {
            background-color: #f5f5f5;
            font-weight: 700;
            font-size: 16px;
        }
        
        .display-header-info {
            margin-bottom: 10px;
            font-size: 16px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-right: 4px solid #007bff;
            direction: rtl;
            text-align: right;
        }
        
        .display-header-info p {
            margin: 8px 0;
            font-weight: bold;
        }
        
        .rep_header-display {
            text-align: center;
            margin-bottom: 5px;
            padding: 2px;
            border-bottom: 2px solid #000;
            font-weight: bold;
            font-size: 14px;
            position: relative;
            font-family: 'Arial Narrow', Arial, sans-serif;
        }
        
        .rep_header-display img {
            height: 50px;
            position: absolute;
            right: 10px;
            top: 5px;
        }
        
        .defaulter-checkbox-cell {
            width: 30px;
            text-align: center;
        }
        
        .defaulter-checkbox {
            transform: scale(1.2);
        }
        
        .defaulter-old-dues {
            font-weight: bold;
            color: #ff6b00;
        }
        
        .defaulter-total-amount {
            font-weight: bold;
            color: #dc3545;
            font-size: 16px;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Urdu Bold Text Styles */
        .urdu-text {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            direction: rtl;
            text-align: right;
        }
        
        .urdu-bold-large {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            font-size: 24px;
            font-weight: 800;
            direction: rtl;
            text-align: right;
            color: #000;
        }
        
        .urdu-bold {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            font-size: 20px;
            font-weight: 700;
            direction: rtl;
            text-align: right;
            color: #000;
        }
        
        .urdu-bold-medium {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            font-size: 18px;
            font-weight: 700;
            direction: rtl;
            text-align: right;
            color: #000;
        }
        
        .urdu-bold-small {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            font-size: 16px;
            font-weight: 600;
            direction: rtl;
            text-align: right;
            color: #000;
        }
        
        /* Table wrapper for horizontal scroll on mobile */
        .table-responsive-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .panel[style*="width:80%"] {
                width: 100% !important;
            }
            
            .student-selection-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .student-selection-container {
                top: 50px;
                padding: 10px;
            }
            
            .print-selected-buttons {
                margin-left: 0;
                justify-content: center;
                flex-direction: column;
            }
            
            .print-selected-buttons .btn {
                width: 100%;
            }
            
            .selection-controls {
                justify-content: center;
                display: block;
            }
            
            .selection-controls .btn {
                flex: 1;
                min-width: 0;
            }
            
            .urdu-bold-large {
                font-size: 18px;
            }
            
            .urdu-bold {
                font-size: 16px;
            }
            
            .student-info .info-row {
                
                align-items: flex-start;
            }
            
            .info-label {
                min-width: auto;
                font-size: 16px;
                margin-bottom: 5px;
            }
            
            .info-value {
                margin-left: 0;
                margin-bottom: 5px;
                font-size: 14px;
            }
            
            .reminder-slip {
                padding: 10px;
                margin: 10px;
            }
            
            .reminder-header h3 {
                font-size: 20px;
            }
            
            .reminder-header .urdu-title {
                font-size: 18px;
            }
            
            .pending-fees th,
            .pending-fees td {
                padding: 6px;
                font-size: 13px;
            }
            
            .pending-fees .pending-title {
                font-size: 16px;
            }
            
            .grand-total {
                font-size: 16px;
                padding: 10px;
            }
            
            .grand-total span {
                font-size: 18px !important;
            }
            
            .btn-lg {
                padding: 10px 20px;
                font-size: 14px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                width: 100%;
                margin: 5px 0;
            }
            
            .bulk-print-buttons {
                flex-direction: column;
            }
            
            .bulk-print-buttons .btn {
                width: 100%;
            }
            
            .form-group .control-label {
                margin-bottom: 10px;
            }
            
            .rep_header-display img {
                height: 35px;
                position: static;
                display: block;
                margin: 0 auto 10px;
            }
            
            .rep_header-display {
                font-size: 12px;
            }
            
            .defaulter-table-display {
                font-size: 11px;
            }
            
            .defaulter-table-display th,
            .defaulter-table-display td {
                padding: 4px;
                font-size: 11px;
            }
            
            .defaulter-table-display th {
                font-size: 12px;
            }
            
            .display-header-info {
                font-size: 13px;
                padding: 10px;
            }
            
            .student-selection-title {
                font-size: 14px;
            }
            
            .selected-count {
                font-size: 12px;
                padding: 4px 10px;
            }
            
            .reminder-checkbox {
                font-size: 14px;
            }
            
            .footer-note p {
                font-size: 13px;
            }
            
            .paid-message {
                font-size: 14px;
                padding: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .urdu-bold-large {
                font-size: 16px;
            }
            
            .urdu-bold {
                font-size: 14px;
            }
            
            .reminder-header h3 {
                font-size: 18px;
            }
            
            .reminder-header .urdu-title {
                font-size: 16px;
            }
            
            .student-selection-header {
                gap: 10px;
            }
            
            .print-selected-buttons .btn {
                min-width: auto;
                font-size: 12px;
                padding: 6px 12px;
            }
            
            .selection-controls .btn {
                font-size: 12px;
                padding: 6px 12px;
            }
            
            .defaulter-table-display {
                font-size: 10px;
            }
            
            .defaulter-table-display th,
            .defaulter-table-display td {
                padding: 3px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <?php require_once('navbar.php'); ?>
    
    <div class="container">
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php 
            echo $_SESSION['message']; 
            unset($_SESSION['message'], $_SESSION['message_type']);
            ?>
        </div>
        <?php endif; ?>
        
        <div class="panel panel-primary" style="width:80% !important; margin:0 auto;margin-top:10px;">
            <div class="panel-heading">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 18px; font-weight: bold;">Select Session and Class</span>
                    <span class="urdu-bold-large" style="color: white;">سیشن اور کلاس منتخب کریں</span>
                </div>
            </div>
            <div class="panel-body">
                <form method="post" class="form-horizontal" id="mainForm" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="view">
                    
                    <div class="form-group">
                        <label class="control-label col-sm-3">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 16px; font-weight: bold;">Select Session</span>
                                <span class="urdu-bold">سیشن منتخب کریں</span>
                            </div>
                        </label>
                        <div class="col-sm-9">
                            <select name="session_id" class="form-control" required>
                                <option value="">Select an option / ایک آپشن منتخب کریں</option>
                                <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['id']; ?>" 
                                    <?php echo ($selected_session_id == $session['id']) ? 'selected' : ''; ?>>
                                    <?php echo $session['title'] . ' (' . date('M Y', strtotime($session['from_dated'])) . ' - ' . date('M Y', strtotime($session['to_dated'])) . ')'; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="control-label col-sm-3">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                                <span style="font-size: 16px; font-weight: bold;">Select Class</span>
                                <span class="urdu-bold">کلاس منتخب کریں</span>
                            </div>
                        </label>
                        <div class="col-sm-9">
                            <select name="class_id" class="form-control" required>
                                <option value="">Select an option / ایک آپشن منتخب کریں</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" 
                                    <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($classes)): ?>
                            <small style="display: block; margin-top: 5px; font-size: 14px; font-weight: bold; color: #a94442;">
                                <span class="urdu-bold-small" style="text-align: right; display: block;">کوئی کلاس نہیں ملی۔ براہ کرم ڈیٹا بیس میں کلاسز شامل کریں۔</span>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="col-sm-12 text-center">
                            <div class="btn-group">
                                <button type="submit" name="print_type" value="feecards" class="btn btn-success btn-lg" style="display: inline-flex; justify-content: center; align-items: center;  flex-wrap: wrap;">
                                    <span class="glyphicon glyphicon-list"></span> 
                                    <span style="font-weight: bold; font-size: 16px;">Generate Fee Reminder</span>
                                    <span class="urdu-bold" style="color: white;">فیس ریمائنڈر بنائیں</span>
                                </button>
                               
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($display_data): ?>
        <div class="results-section">
            <!-- Sticky Student Selection Container with Inline Print Buttons -->
            <div class="student-selection-container">
                <div class="student-selection-header" style="width:80% !important; margin:0 auto;">
                    <div class="student-selection-title">
                        <span class="badge"><?php echo count($display_data['all_students']); ?> students found / <span style="font-weight: bold;">طلباء ملے</span></span>
                    </div>
                    
                    <div class="selection-controls">
                        <button type="button" class="btn btn-lg btn-success" id="selectAllBtn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="glyphicon glyphicon-check"></span> 
                            <span style="font-weight: bold;">Select All</span>
                            <span class="urdu-bold" style="color: white;">سب منتخب کریں</span>
                        </button>
                        <button type="button" class="btn btn-lg btn-default" id="deselectAllBtn" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="glyphicon glyphicon-unchecked"></span> 
                            <span style="font-weight: bold;">Deselect All</span>
                            <span class="urdu-bold">سب کا انتخاب ختم کریں</span>
                        </button>
                        <div class="selected-count" id="selectedCount">0 selected / <span style="font-weight: bold;">منتخب</span></div>
                    </div>
                    
                    <!-- Print Selected Buttons (Inline) -->
                    <div class="print-selected-buttons">
                        <?php if ($display_data['print_type'] == 'feecards'): ?>
                       
                        
                        <button type="button" class="btn btn-warning thermal-print-btn" id="thermalPrintSelectedBtn" disabled style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="glyphicon glyphicon-print"></span> 
                            <span style="font-weight: bold;">Thermal Print</span>
                            <span class="urdu-bold">تھرمل پرنٹ</span>
                        </button>
                        
                        <?php elseif ($display_data['print_type'] == 'defaulter'): ?>
                        <button type="button" class="btn btn-success" id="printSelectedBtn" disabled style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="glyphicon glyphicon-print"></span> 
                            <span style="font-weight: bold;">Print Selected</span>
                            <span class="urdu-bold" style="color: white;">منتخب پرنٹ کریں</span>
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" class="btn btn-default" style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="glyphicon glyphicon-refresh"></span> 
                            <span style="font-weight: bold;">New Search</span>
                            <span class="urdu-bold">نئی تلاش</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Hidden form for print actions -->
            <form method="post" id="printForm" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="session_id" id="printSessionId" value="<?php echo $selected_session_id; ?>">
                <input type="hidden" name="class_id" id="printClassId" value="<?php echo $selected_class_id; ?>">
                <input type="hidden" name="print_type" id="printType" value="<?php echo $display_data['print_type']; ?>">
                <input type="hidden" name="action" id="printAction" value="">
                <input type="hidden" name="selected_students" id="selectedStudentsInput" value="">
            </form>
            
            <?php if ($display_data['print_type'] == 'defaulter'): ?>
            <!-- Defaulter List Display -->
            <div class="display-container">
                <div class="rep_header-display">
                    <img src="logo2.png" />
                    <div style="font-size: 20px; font-weight: bold; margin: 10px 0;">المدرسہ الفاروقیہ للتجوید والقراءت</div>
                    <div style="font-size: 14px;">
                        <span>Phone: 0346-9401982 | Email: info@das.edu.pk</span>
                    </div>
                    <div style="margin-top: 5px;">
                        <span class="urdu-bold-large" style="display: block; color: #dc3545; font-size: 22px;">ڈیفالٹر فہرست</span>
                    </div>
                </div>

                <div class="display-header-info">
                    <p><strong>سیشن:</strong> <?php echo $display_data['session_title']; ?></p>
                    <p><strong>کلاس:</strong> <?php echo $display_data['class_title']; ?></p>
                    <p><strong>تاریخ:</strong> <?php echo date('d-m-Y'); ?></p>
                    <p><strong>سابقہ واجبات:</strong> <?php echo number_format($display_data['total_old_dues']); ?> PKR</p>
                    <p><strong style="font-size: 18px;">کل مجموعہ:</strong> <span style="font-size: 18px; font-weight: bold; color: #dc3545;"><?php echo number_format($display_data['total_remaining']); ?> PKR</span></p>
                </div>

                <div class="table-responsive-wrapper">
                    <table class="defaulter-table-display">
                        <thead>
                            <tr>
                                <th style="width: 10%;">کل واجبات</th>
                                <th style="width: 10%;">زیر التواء فیس</th>
                                <th style="width: 10%;">سابقہ واجبات</th>
                                <th style="width: 10%;">موبائل</th>
                                <th style="width: 15%; text-align: right;">والد کا نام</th>
                                <th style="width: 20%; text-align: right;">طالب علم کا نام</th>
                                <th style="width: 8%;">طالب علم ID</th>
                                <th style="width: 5%;">نمبر</th>
                                <th style="width: 5%;" class="defaulter-checkbox-cell">
                                    <input type="checkbox" id="selectAllTable" class="defaulter-checkbox">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($display_data['all_students'] as $student): 
                                if ($student['total_due'] > 0): 
                            ?>
                            <tr>
                                <td class="defaulter-total-amount"><strong><?php echo number_format($student['total_due']); ?></strong></td>
                                <td class="defaulter-total-amount"><?php echo number_format($student['total_pending']); ?></td>
                                <td class="defaulter-old-dues"><?php echo $student['old_dues_amount'] > 0 ? number_format($student['old_dues_amount']) : '-'; ?></td>
                                <td><?php echo $student['mobile'] ?: '-'; ?></td>
                                <td style="text-align: right;"><?php echo $student['father_name']; ?></td>
                                <td style="text-align: right;"><?php echo $student['name']; ?></td>
                                <td><?php echo $student['id']; ?></td>
                                <td><?php echo $counter++; ?></td>
                                <td class="defaulter-checkbox-cell">
                                    <input type="checkbox" value="<?php echo $student['id']; ?>" 
                                           class="student-checkbox defaulter-checkbox" 
                                           data-student-id="<?php echo $student['id']; ?>"
                                           <?php echo in_array($student['id'], $display_data['selected_students']) ? 'checked' : ''; ?>>
                                </td>
                            </tr>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($display_data['print_type'] == 'feecards'): ?>
            <!-- Fee Reminder Slips -->
            <div class="display-container">
                <?php foreach ($display_data['all_students'] as $student): ?>
                <div class="reminder-checkbox">
                    <input type="checkbox" value="<?php echo $student['id']; ?>" 
                           class="student-checkbox" 
                           data-student-id="<?php echo $student['id']; ?>"
                           style="transform: scale(1.3); margin-left: 10px;"
                           <?php echo in_array($student['id'], $display_data['selected_students']) ? 'checked' : ''; ?>>
                    <strong style="font-size: 18px;">منتخب کریں:</strong> 
                    <span style="font-size: 16px; font-weight: bold;"><?php echo htmlspecialchars($student['name']); ?></span>
                </div>
                
                <div class="reminder-slip">
                    <div class="reminder-header">
                        <h3>المدرسہ الفاروقیہ للتجوید والقراءت</h3>
                        <div class="urdu-title"> فیس ریمائنڈر سلپ</div>
                    </div>
                    
                    <div class="student-info">
                        <div class="info-row">
                            <span class="info-label">طالب علم کا نام :</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">والد کا نام :</span>
                            <span class="info-value"><?php echo htmlspecialchars($student['father_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">طالب علم ID :</span>
                            <span class="info-value"><?php echo $student['id']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">کلاس :</span>
                            <span class="info-value"><?php echo htmlspecialchars($display_data['class_title']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">سیشن :</span>
                            <span class="info-value"><?php echo htmlspecialchars($display_data['session_title']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">موبائل :</span>
                            <span class="info-value"><?php echo $student['mobile'] ?: '---'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">تاریخ :</span>
                            <span class="info-value"><?php echo date('d-m-Y'); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($student['paid_until_display']): ?>
                    <div class="paid-message">
                        <span style="color: #28a745; font-size: 18px;">✓ فیس ادا کر دی گئی ہے : <?php echo $student['paid_until_display']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['pending_fees']) || $student['old_dues_amount'] > 0): ?>
                    <div class="pending-fees">
                        <div class="pending-title">زیر التواء فیس</div>
                        
                        <?php if (!empty($student['pending_fees'])): ?>
                        <div class="table-responsive-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>فیس کی قسم</th>
                                        <th>مہینہ</th>
                                        <th>رقم (PKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($student['pending_fees'] as $fee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                                        <td><?php echo htmlspecialchars($fee['month']); ?></td>
                                        <td class="amount-cell"><?php echo number_format($fee['amount']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($student['old_dues_amount'] > 0): ?>
                        <div class="old-dues">
                            <span style="color: #856404;"> سابقہ سیشن کے واجبات :</span> 
                            <strong><?php echo number_format($student['old_dues_amount']); ?> PKR</strong>
                        </div>
                        <?php endif; ?>
                        
                        <div class="grand-total">
                            <span style="color: white;">کل واجبات : </span> 
                            <span style="font-size: 22px;"><?php echo number_format($student['total_due']); ?> PKR</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="paid-message" style="background-color: #d4edda; color: #155724;">
                        <span style="color: #155724; font-size: 18px;">✓ کوئی زیر التواء فیس نہیں۔ تمام واجبات ادا کر دی گئی ہیں۔</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="footer-note">
                        <p style="color: #856404; font-size: 16px;">براہ کرم جلد از جلد واجبات ادا کریں تاکہ لیٹ فیس چارجز سے بچا جا سکے۔</p>
                        <p style="font-size: 16px;">کسی بھی سوال کے لیے کالج آفس سے رابطہ کریں۔</p>
                        <p style="margin-top: 20px; font-size: 14px;">_________________________</p>
                        <p style="font-size: 18px;">اکاؤنٹس آفیسر کے دستخط</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Bulk Print Buttons -->
            <div class="bulk-print-buttons">
                <?php if ($display_data['print_type'] == 'feecards'): ?>
               
                
                <button type="button" class="btn btn-warning btn-lg thermal-print-btn" id="thermalPrintAllBtn" style="display: inline-flex; align-items: center; gap: 10px;">
                    <span class="glyphicon glyphicon-print"></span> 
                    <span style="font-weight: bold; font-size: 18px;">Thermal Print All</span>
                    <span class="urdu-bold-large">تھرمل پرنٹ کریں</span>
                </button>
                
                <?php elseif ($display_data['print_type'] == 'defaulter'): ?>
                <button type="button" class="btn btn-success btn-lg" id="printAllBtn" style="display: inline-flex; align-items: center; gap: 10px;">
                    <span class="glyphicon glyphicon-print"></span> 
                    <span style="font-weight: bold; font-size: 18px;">Print All</span>
                    <span class="urdu-bold-large" style="color: white;">تمام پرنٹ کریں</span>
                </button>
                <?php endif; ?>
                
                <button type="button" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" class="btn btn-default btn-lg" style="display: inline-flex; align-items: center; gap: 10px;">
                    <span class="glyphicon glyphicon-refresh"></span> 
                    <span style="font-weight: bold; font-size: 18px;">New Search</span>
                    <span class="urdu-bold-large">نئی تلاش</span>
                </button>
            </div>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- Add jQuery and Bootstrap JS at the bottom -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        <?php if ($display_data): ?>
        // Scroll to results
        $('html, body').animate({
            scrollTop: $(".results-section").offset().top
        }, 1000);
        
        // Initialize checkbox functionality
        setTimeout(function() {
            initializeCheckboxFunctionality();
        }, 500);
        <?php endif; ?>
    });
    
    function initializeCheckboxFunctionality() {
        console.log('Initializing checkbox functionality');
        
        // Get all elements
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const selectAllTable = document.getElementById('selectAllTable');
        const selectedCount = document.getElementById('selectedCount');
        const printSelectedBtn = document.getElementById('printSelectedBtn');
        const thermalPrintSelectedBtn = document.getElementById('thermalPrintSelectedBtn');
        const printAllBtn = document.getElementById('printAllBtn');
        const thermalPrintAllBtn = document.getElementById('thermalPrintAllBtn');
        const printForm = document.getElementById('printForm');
        const selectedStudentsInput = document.getElementById('selectedStudentsInput');
        const printAction = document.getElementById('printAction');
        
        console.log('Found checkboxes:', studentCheckboxes.length);
        
        if (studentCheckboxes.length === 0) {
            console.log('No checkboxes found');
            return;
        }
        
        // Function to get selected student IDs
        function getSelectedStudentIds() {
            const selected = document.querySelectorAll('.student-checkbox:checked');
            const ids = [];
            selected.forEach(function(checkbox) {
                ids.push(checkbox.value);
            });
            return ids;
        }
        
        // Function to update selection count and button states
        function updateSelection() {
            const selected = document.querySelectorAll('.student-checkbox:checked');
            const selectedCountValue = selected.length;
            
            console.log('Selected count:', selectedCountValue);
            
            // Update count display
            if (selectedCount) {
                selectedCount.textContent = selectedCountValue + ' selected / منتخب';
            }
            
            // Enable/disable print buttons
            const hasSelection = selectedCountValue > 0;
            if (printSelectedBtn) {
                printSelectedBtn.disabled = !hasSelection;
            }
            if (thermalPrintSelectedBtn) {
                thermalPrintSelectedBtn.disabled = !hasSelection;
            }
            
            // Update "select all" checkbox in table if it exists
            if (selectAllTable) {
                selectAllTable.checked = selectedCountValue === studentCheckboxes.length && studentCheckboxes.length > 0;
            }
        }
        
        // Function to submit print form
        function submitPrintForm(actionType) {
            const selectedIds = getSelectedStudentIds();
            if (selectedIds.length === 0 && actionType !== 'print_all' && actionType !== 'thermal_print_all') {
                alert('Please select at least one student to print. / براہ کرم پرنٹ کرنے کے لیے کم از کم ایک طالب علم منتخب کریں۔');
                return false;
            }
            
            if (actionType === 'print_selected') {
                printAction.value = 'print';
                selectedStudentsInput.value = selectedIds.join(',');
            } else if (actionType === 'thermal_print_selected') {
                printAction.value = 'thermal_print';
                selectedStudentsInput.value = selectedIds.join(',');
            } else if (actionType === 'print_all') {
                // Get all student IDs
                const allIds = [];
                studentCheckboxes.forEach(function(checkbox) {
                    allIds.push(checkbox.value);
                });
                printAction.value = 'print';
                selectedStudentsInput.value = allIds.join(',');
            } else if (actionType === 'thermal_print_all') {
                // Get all student IDs
                const allIds = [];
                studentCheckboxes.forEach(function(checkbox) {
                    allIds.push(checkbox.value);
                });
                printAction.value = 'thermal_print';
                selectedStudentsInput.value = allIds.join(',');
            }
            
            console.log('Submitting form with action:', printAction.value);
            console.log('Selected students:', selectedStudentsInput.value);
            
            printForm.submit();
            return true;
        }
        
        // Add change event listeners to all checkboxes
        studentCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', updateSelection);
        });
        
        // Select All button click handler
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                studentCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = true;
                });
                updateSelection();
            });
        }
        
        // Deselect All button click handler
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                studentCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = false;
                });
                updateSelection();
            });
        }
        
        // Select All checkbox in table click handler
        if (selectAllTable) {
            selectAllTable.addEventListener('change', function() {
                studentCheckboxes.forEach(function(checkbox) {
                    checkbox.checked = this.checked;
                }, this);
                updateSelection();
            });
        }
        
        // Print Selected button click handler
        if (printSelectedBtn) {
            printSelectedBtn.addEventListener('click', function() {
                submitPrintForm('print_selected');
            });
        }
        
        // Thermal Print Selected button click handler - redirects to thermal_print_view2.php
        if (thermalPrintSelectedBtn) {
            thermalPrintSelectedBtn.addEventListener('click', function() {
                submitPrintForm('thermal_print_selected');
            });
        }
        
        // Print All button click handler
        if (printAllBtn) {
            printAllBtn.addEventListener('click', function() {
                submitPrintForm('print_all');
            });
        }
        
        // Thermal Print All button click handler - redirects to thermal_print_view2.php
        if (thermalPrintAllBtn) {
            thermalPrintAllBtn.addEventListener('click', function() {
                submitPrintForm('thermal_print_all');
            });
        }
        
        // Initial update
        updateSelection();
    }
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>