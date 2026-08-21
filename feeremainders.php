<?php 
// Add error reporting at the very top to catch all errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

require_once('security.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    require_once('conn_inc.php');
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Database connection failed: " . ($conn->connect_error ?? 'Unknown error'));
    }
} catch (Exception $e) {
    error_log("Connection error: " . $e->getMessage());
    $_SESSION['message'] = 'Database connection error. Please check error_log.txt';
    $_SESSION['message_type'] = 'danger';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$display_data = null;
$selected_class_id = 0;
$selected_students = [];

if (isset($_SESSION['form_data_class_only'])) {
    $selected_class_id = $_SESSION['form_data_class_only']['class_id'] ?? 0;
    $selected_students = $_SESSION['form_data_class_only']['selected_students'] ?? [];
    unset($_SESSION['form_data_class_only']);
}

if (isset($_GET['clear']) && $_GET['clear'] == 1) {
    unset($_SESSION['print_data']);
}

$latest_session_id = 0;
$latest_session_title = '';
try {
    $session_query = "SELECT id, title, from_dated, to_dated FROM sessions WHERE status = 0 ORDER BY from_dated DESC LIMIT 1";
    $session_result = $conn->query($session_query);
    
    if ($session_result && $session_row = $session_result->fetch_assoc()) {
        $latest_session_id = $session_row['id'];
        $latest_session_title = $session_row['title'] . ' (' . date('M Y', strtotime($session_row['from_dated'])) . ' - ' . date('M Y', strtotime($session_row['to_dated'])) . ')';
    }
} catch (Exception $e) {
    error_log("Session fetch error: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }
        
        $selected_class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
        $action = isset($_POST['action']) ? $_POST['action'] : 'view';
        
        // Get selected students from POST
        $selected_students = [];
        if (isset($_POST['selected_students']) && !empty($_POST['selected_students'])) {
            if (is_array($_POST['selected_students'])) {
                $selected_students = array_map('intval', $_POST['selected_students']);
            } elseif (is_string($_POST['selected_students'])) {
                $selected_students = array_map('intval', explode(',', $_POST['selected_students']));
            }
        }
        
        // Debug log
        error_log("Action: $action, Selected students: " . print_r($selected_students, true));
        
        if ($selected_class_id > 0 && $latest_session_id > 0) {
            $class_query = "SELECT title FROM classes WHERE id = ?";
            $class_stmt = $conn->prepare($class_query);
            $class_stmt->bind_param("i", $selected_class_id);
            $class_stmt->execute();
            $class_result = $class_stmt->get_result();
            $class_row = $class_result->fetch_assoc();
            $class_title = $class_row['title'] ?? '';
            
            $query = "SELECT sr.id, sr.name, sr.father_name, sr.mobile, 
                             sr.is_old_dues, sr.old_dues_amount,
                             sc.id AS student_class_id
                      FROM student_registration sr 
                      JOIN student_class sc ON sr.id = sc.student_registration_id 
                      WHERE sc.session_id = ? AND sc.class_id = ? AND sr.status = 0 AND sc.status = 0
                      ORDER BY sr.name";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $latest_session_id, $selected_class_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $students_data = [];
            $total_remaining = 0;
            $total_old_dues = 0;
            $student_count = 0;
            
            while ($student_row = $result->fetch_assoc()) {
                $student_count++;
                $student_id = $student_row['id'];
                
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
                $fee_cards_stmt->bind_param("ii", $student_id, $latest_session_id);
                $fee_cards_stmt->execute();
                $fee_cards_result = $fee_cards_stmt->get_result();
                
                $all_months = [];
                $paid_until = null;
                $paid_until_display = null;
                $pending_fees = [];
                $total_pending = 0;
                
                while ($fee_card = $fee_cards_result->fetch_assoc()) {
                    $month_key = $fee_card['month_key'];
                    
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
                    
                    if ($due_amount > 0) {
                        $all_months[$month_key]['is_fully_paid'] = false;
                    }
                }
                
                ksort($all_months);
                
                foreach ($all_months as $month_key => $month_data) {
                    if ($month_data['is_fully_paid'] && $month_data['total_cleared'] >= $month_data['total_amount']) {
                        $paid_until = $month_key;
                        $paid_until_display = $month_data['display'];
                    } else {
                        break;
                    }
                }
                
                $start_collecting = false;
                foreach ($all_months as $month_key => $month_data) {
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
                
                $old_dues = 0;
                if ($student_row['is_old_dues'] == 1 && $student_row['old_dues_amount'] > 0) {
                    $old_dues = $student_row['old_dues_amount'];
                }
                
                $total_due = $total_pending + $old_dues;
                
                if ($total_due > 0) {
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
                    $total_remaining += $total_due;
                    $total_old_dues += $old_dues;
                }
            }
            
            error_log("Processed $student_count students, found " . count($students_data) . " with dues");
            
            // CHECK IF THIS IS A PRINT ACTION
            if ($action == 'print' || $action == 'thermal_print') {
                error_log("Print action detected. Selected students count: " . count($selected_students));
                
                if (empty($selected_students)) {
                    $_SESSION['message'] = 'Please select at least one student to print.';
                    $_SESSION['message_type'] = 'warning';
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                // Filter only selected students
                $filtered_students = [];
                foreach ($students_data as $student) {
                    if (in_array($student['id'], $selected_students)) {
                        $filtered_students[] = $student;
                    }
                }
                
                if (empty($filtered_students)) {
                    $_SESSION['message'] = 'No matching students found for print.';
                    $_SESSION['message_type'] = 'warning';
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                }
                
                // Store print data and redirect
                $_SESSION['print_data'] = [
                    'session_title' => $latest_session_title,
                    'class_title' => $class_title,
                    'students' => $filtered_students,
                    'total_remaining' => $total_remaining,
                    'total_old_dues' => $total_old_dues,
                    'print_type' => 'feecards'
                ];
                
                error_log("Redirecting to thermal_print_view.php with " . count($filtered_students) . " students");
                header("Location: thermal_print_view.php");
                exit();
            }
            
            // Normal view action - store data for display
            if (empty($students_data)) {
                $_SESSION['message'] = 'No students with dues found for this class.';
                $_SESSION['message_type'] = 'success';
            } else {
                $display_data = [
                    'session_title' => $latest_session_title,
                    'class_title' => $class_title,
                    'students' => $students_data,
                    'total_remaining' => $total_remaining,
                    'total_old_dues' => $total_old_dues,
                    'all_students' => $students_data,
                    'selected_students' => $selected_students,
                    'class_id' => $selected_class_id
                ];
                
                $_SESSION['form_data_class_only'] = [
                    'class_id' => $selected_class_id,
                    'selected_students' => $selected_students
                ];
            }
            
        } else {
            if ($selected_class_id == 0) {
                $_SESSION['message'] = 'Please select a class.';
            } elseif ($latest_session_id == 0) {
                $_SESSION['message'] = 'No active session found.';
            }
            $_SESSION['message_type'] = 'warning';
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        
    } catch (Exception $e) {
        error_log("FATAL ERROR: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred. Please try again.";
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get classes for dropdown
$classes = [];
try {
    $result = $conn->query("SELECT id, title FROM classes ORDER BY title");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Class Fee Dues - کلاس فیس واجبات</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        .navbar { min-height: 50px; margin-bottom: 20px; }
        .navbar-brand { padding: 15px 15px; height: 50px; font-size: 18px; line-height: 20px; }
        .navbar-nav > li > a { padding-top: 15px; padding-bottom: 15px; line-height: 20px; }
        .container { max-width: 1200px; margin-top: 20px; }
        .panel { margin-top: 20px; }
        .btn-group { margin-top: 20px; text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .btn-group .btn { min-width: 200px; }
        .form-control { height: 45px; font-size: 16px; }
        .btn-lg { padding: 12px 30px; font-size: 16px; font-weight: bold; }
        .btn-success { background-color: #28a745; border-color: #28a745; }
        .panel-heading { background-color: #007bff !important; color: white !important; }
        .results-section { margin-top: 30px; display: <?php echo $display_data ? 'block' : 'none'; ?>; }
        
        .reminder-slip {
            font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif;
            max-width: 800px; margin: 0 auto 30px auto; padding: 20px;
            border: 2px solid #007bff; border-radius: 10px; background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1); direction: rtl; text-align: right;
        }
        .reminder-header { text-align: center; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 15px; }
        .reminder-header h3 { color: #007bff; font-weight: bold; margin: 5px 0; font-size: 26px; }
        .reminder-header .urdu-title { color: #28a745; font-weight: 800; font-size: 22px; margin: 5px 0; }
        .student-info { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 15px; border-right: 4px solid #007bff; direction: rtl; text-align: right; }
        .student-info .info-row { display: flex; justify-content: flex-start; align-items: center; padding: 6px 0; border-bottom: 1px dotted #ddd; direction: rtl; text-align: right; flex-wrap: wrap; }
        .student-info .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: 700; color: #007bff; font-size: 18px; text-align: right; min-width: 160px; }
        .info-value { font-size: 16px; text-align: right; margin-left: 10px; font-weight: normal; word-break: break-word; }
        .session-info-banner { background-color: #e3f2fd; color: #1565c0; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-weight: bold; font-size: 18px; border-right: 4px solid #1565c0; text-align: center; direction: rtl; }
        .pending-fees { margin-top: 15px; direction: rtl; text-align: right; }
        .pending-fees .pending-title { font-size: 20px; font-weight: 800; color: #dc3545; margin-bottom: 15px; text-align: right; }
        .pending-fees table { width: 100%; border-collapse: collapse; margin-bottom: 10px; direction: rtl; }
        .pending-fees th { background-color: #007bff; color: white; padding: 10px; text-align: right; font-size: 16px; font-weight: bold; }
        .pending-fees td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 15px; text-align: right; }
        .pending-fees .amount-cell { text-align: right; font-weight: bold; color: #dc3545; font-size: 16px; }
        .old-dues { background-color: #fff3cd; padding: 12px; border-radius: 5px; margin: 10px 0; border-right: 4px solid #ffc107; font-size: 16px; font-weight: bold; direction: rtl; text-align: right; }
        .grand-total { background-color: #007bff; color: white; padding: 15px; border-radius: 5px; margin-top: 10px; font-weight: bold; font-size: 20px; text-align: right; direction: rtl; }
        .footer-note { margin-top: 15px; text-align: center; font-size: 14px; color: #6c757d; border-top: 1px solid #ddd; padding-top: 10px; direction: rtl; }
        .footer-note p { margin: 8px 0; font-weight: bold; font-size: 16px; }
        
        .student-selection-container { background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; border: 1px solid #dee2e6; position: sticky; top: 70px; z-index: 1000; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .student-selection-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .student-selection-title { font-size: 18px; font-weight: bold; color: #007bff; display: flex; align-items: center; gap: 10px; }
        .selection-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .print-selected-buttons { display: flex; gap: 10px; align-items: center; margin-left: auto; flex-wrap: wrap; }
        .print-selected-buttons .btn { min-width: 160px; font-size: 14px; padding: 8px 16px; margin: 0; }
        .student-checkbox { transform: scale(1.3); margin-right: 10px; }
        .selected-count { background-color: #28a745; color: white; padding: 5px 15px; border-radius: 20px; font-weight: bold; font-size: 14px; white-space: nowrap; }
        .reminder-checkbox { margin-bottom: 10px; padding: 10px; background-color: #f8f9fa; border-radius: 5px; direction: rtl; text-align: right; }
        .bulk-print-buttons { text-align: center; margin: 20px 0; padding-top: 20px; border-top: 2px solid #dee2e6; display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }
        .bulk-print-buttons .btn { min-width: 200px; }
        .thermal-print-btn { background-color: #17a2b8; border-color: #17a2b8; color: white; }
        .thermal-print-btn:hover { background-color: #138496; border-color: #117a8b; }
        .urdu-bold-large { font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif; font-size: 24px; font-weight: 800; direction: rtl; text-align: right; color: #000; }
        .urdu-bold { font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif; font-size: 20px; font-weight: 700; direction: rtl; text-align: right; color: #000; }
        .urdu-bold-small { font-family: 'Arial', 'Noto Nastaliq Urdu', sans-serif; font-size: 16px; font-weight: 600; direction: rtl; text-align: right; color: #000; }
        .table-responsive-wrapper { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php require_once('navbar.php'); ?>
    
    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
        <?php endif; ?>
        
        <div class="panel panel-primary" style="width:80% !important; margin:0 auto;margin-top:10px;">
            <div class="panel-heading">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 18px; font-weight: bold;">Select Class for Fee Dues</span>
                    <span class="urdu-bold-large" style="color: white;">فیس واجبات کے لیے کلاس منتخب کریں</span>
                </div>
            </div>
            <div class="panel-body">
                <?php if ($latest_session_id > 0): ?>
                <div class="session-info-banner">
                    <span style="color: #1565c0;">Active Session:</span> 
                    <span class="urdu-bold"><?php echo htmlspecialchars($latest_session_title); ?></span>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Warning:</strong> No active session found.
                    <span class="urdu-bold-small" style="display: block; text-align: right;">کوئی فعال سیشن نہیں ملا۔</span>
                </div>
                <?php endif; ?>
                
                <form method="post" class="form-horizontal" id="mainForm" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="view">
                    
                    <div class="form-group">
                        <label class="control-label col-sm-3">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 16px; font-weight: bold;">Select Class</span>
                                <span class="urdu-bold">کلاس منتخب کریں</span>
                            </div>
                        </label>
                        <div class="col-sm-9">
                            <select name="class_id" class="form-control" required>
                                <option value="">Select an option / ایک آپشن منتخب کریں</option>
                                <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($selected_class_id == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['title']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="col-sm-12 text-center">
                            <div class="btn-group">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <span class="glyphicon glyphicon-list"></span> 
                                    <span style="font-weight: bold;">Show Students with Dues</span>
                                    <span class="urdu-bold" style="color: white;">واجبات والے طلباء دکھائیں</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <?php if ($display_data): ?>
        <div class="results-section">
            <div class="student-selection-container">
                <div class="student-selection-header" style="width:80% !important; margin:0 auto;">
                    <div class="student-selection-title">
                        <span class="badge"><?php echo count($display_data['all_students']); ?> students with dues</span>
                    </div>
                    
                    <div class="selection-controls">
                        <button type="button" class="btn btn-lg btn-success" id="selectAllBtn">
                            Select All / سب منتخب کریں
                        </button>
                        <button type="button" class="btn btn-lg btn-default" id="deselectAllBtn">
                            Deselect All / سب کا انتخاب ختم کریں
                        </button>
                        <div class="selected-count" id="selectedCount">0 selected</div>
                    </div>
                    
                    <div class="print-selected-buttons">
                        <button type="button" class="btn btn-warning thermal-print-btn" id="thermalPrintSelectedBtn" disabled>
                            Thermal Print Selected / منتخب تھرمل پرنٹ
                        </button>
                        <button type="button" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" class="btn btn-default">
                            New Search / نئی تلاش
                        </button>
                    </div>
                </div>
            </div>
            
            <form method="post" id="printForm" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="class_id" id="printClassId" value="<?php echo $selected_class_id; ?>">
                <input type="hidden" name="action" id="printAction" value="thermal_print">
                <input type="hidden" name="selected_students" id="selectedStudentsInput" value="">
            </form>
            
            <div class="display-container">
                <?php foreach ($display_data['all_students'] as $student): ?>
                <div class="reminder-checkbox">
                    <input type="checkbox" value="<?php echo $student['id']; ?>" class="student-checkbox" data-student-id="<?php echo $student['id']; ?>"
                           style="transform: scale(1.3); margin-left: 10px;"
                           <?php echo in_array($student['id'], $display_data['selected_students']) ? 'checked' : ''; ?>>
                    <strong style="font-size: 18px;">منتخب کریں:</strong> 
                    <span style="font-size: 16px; font-weight: bold;"><?php echo htmlspecialchars($student['name']); ?></span>
                </div>
                
                <div class="reminder-slip">
                    <div class="reminder-header">
                        <h3>المدرسہ الفاروقیہ للتجوید والقراءت</h3>
                        <div class="urdu-title">فیس ریمائنڈر سلپ</div>
                    </div>
                    
                    <div class="student-info">
                        <div class="info-row"><span class="info-label">طالب علم کا نام :</span><span class="info-value"><?php echo htmlspecialchars($student['name']); ?></span></div>
                        <div class="info-row"><span class="info-label">والد کا نام :</span><span class="info-value"><?php echo htmlspecialchars($student['father_name']); ?></span></div>
                        <div class="info-row"><span class="info-label">طالب علم ID :</span><span class="info-value"><?php echo $student['id']; ?></span></div>
                        <div class="info-row"><span class="info-label">کلاس :</span><span class="info-value"><?php echo htmlspecialchars($display_data['class_title']); ?></span></div>
                        <div class="info-row"><span class="info-label">سیشن :</span><span class="info-value"><?php echo htmlspecialchars($display_data['session_title']); ?></span></div>
                        <div class="info-row"><span class="info-label">موبائل :</span><span class="info-value"><?php echo $student['mobile'] ?: '---'; ?></span></div>
                        <div class="info-row"><span class="info-label">تاریخ :</span><span class="info-value"><?php echo date('d-m-Y'); ?></span></div>
                    </div>
                    
                    <?php if ($student['paid_until_display']): ?>
                    <div class="student-info" style="background-color: #e8f5e9; border-right: 4px solid #28a745;">
                        <div class="info-row" style="border-bottom: none; justify-content: center;">
                            <span style="color: #28a745; font-size: 18px;">✓ فیس ادا کر دی گئی ہے : <?php echo $student['paid_until_display']; ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($student['pending_fees']) || $student['old_dues_amount'] > 0): ?>
                    <div class="pending-fees">
                        <div class="pending-title">زیر التواء فیس</div>
                        <?php if (!empty($student['pending_fees'])): ?>
                        <div class="table-responsive-wrapper">
                            <table>
                                <thead><tr><th>فیس کی قسم</th><th>مہینہ</th><th>رقم (PKR)</th></tr></thead>
                                <tbody>
                                    <?php foreach ($student['pending_fees'] as $fee): ?>
                                    <tr><td><?php echo htmlspecialchars($fee['fee_type']); ?></td><td><?php echo htmlspecialchars($fee['month']); ?></td><td class="amount-cell"><?php echo number_format($fee['amount']); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        <?php if ($student['old_dues_amount'] > 0): ?>
                        <div class="old-dues"><span style="color: #856404;">سابقہ سیشن کے واجبات :</span> <strong><?php echo number_format($student['old_dues_amount']); ?> PKR</strong></div>
                        <?php endif; ?>
                        <div class="grand-total"><span style="color: white;">کل واجبات : </span><span style="font-size: 22px;"><?php echo number_format($student['total_due']); ?> PKR</span></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="footer-note">
                        <p style="color: #856404; font-size: 16px;">براہ کرم جلد از جلد واجبات ادا کریں۔</p>
                        <p style="margin-top: 20px;">_________________________</p>
                        <p>اکاؤنٹس آفیسر کے دستخط</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="bulk-print-buttons">
                <button type="button" class="btn btn-warning btn-lg thermal-print-btn" id="thermalPrintAllBtn">
                    Thermal Print All / تمام تھرمل پرنٹ کریں
                </button>
                <button type="button" onclick="window.location.href='<?php echo $_SERVER['PHP_SELF']; ?>'" class="btn btn-default btn-lg">
                    New Search / نئی تلاش
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <script>
    $(document).ready(function() {
        <?php if ($display_data): ?>
        $('html, body').animate({ scrollTop: $(".results-section").offset().top }, 1000);
        setTimeout(function() { initializeCheckboxFunctionality(); }, 500);
        <?php endif; ?>
    });
    
    function initializeCheckboxFunctionality() {
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const selectedCount = document.getElementById('selectedCount');
        const thermalPrintSelectedBtn = document.getElementById('thermalPrintSelectedBtn');
        const thermalPrintAllBtn = document.getElementById('thermalPrintAllBtn');
        const printForm = document.getElementById('printForm');
        const selectedStudentsInput = document.getElementById('selectedStudentsInput');
        const printAction = document.getElementById('printAction');
        
        if (studentCheckboxes.length === 0) return;
        
        function getSelectedStudentIds() {
            const ids = [];
            document.querySelectorAll('.student-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
            return ids;
        }
        
        function updateSelection() {
            const count = document.querySelectorAll('.student-checkbox:checked').length;
            if (selectedCount) selectedCount.textContent = count + ' selected';
            if (thermalPrintSelectedBtn) thermalPrintSelectedBtn.disabled = (count === 0);
        }
        
        function submitPrint(selectedIds) {
            if (selectedIds.length === 0) {
                alert('Please select at least one student.');
                return;
            }
            printAction.value = 'thermal_print';
            selectedStudentsInput.value = selectedIds.join(',');
            console.log('Submitting - Action:', printAction.value, 'Students:', selectedStudentsInput.value);
            printForm.submit();
        }
        
        studentCheckboxes.forEach(function(cb) { cb.addEventListener('change', updateSelection); });
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                studentCheckboxes.forEach(function(cb) { cb.checked = true; });
                updateSelection();
            });
        }
        
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                studentCheckboxes.forEach(function(cb) { cb.checked = false; });
                updateSelection();
            });
        }
        
        if (thermalPrintSelectedBtn) {
            thermalPrintSelectedBtn.addEventListener('click', function() {
                submitPrint(getSelectedStudentIds());
            });
        }
        
        if (thermalPrintAllBtn) {
            thermalPrintAllBtn.addEventListener('click', function() {
                const allIds = [];
                studentCheckboxes.forEach(function(cb) { allIds.push(cb.value); });
                submitPrint(allIds);
            });
        }
        
        updateSelection();
    }
    </script>
</body>
</html>
<?php $conn->close(); ?>