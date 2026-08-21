<?php
require_once('security.php');
require_once('conn_inc.php');
header('Content-Type: text/html; charset=utf-8');

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$lang = $_SESSION['lang'];

// Set MySQL connection charset
$conn->set_charset("utf8mb4");

// Get selected class
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;

// Auto-select current active session
$selected_session = 0;
$currentSessionResult = $conn->query("SELECT id FROM sessions WHERE status = 0 ORDER BY id DESC LIMIT 1");
if ($currentSessionResult && $currentSessionResult->num_rows > 0) {
    $selected_session = $currentSessionResult->fetch_assoc()['id'] ?? 0;
}

// Get all active classes
$classes_query = "SELECT cl.id, cl.title as class_name, c.title as course_name 
                  FROM classes cl 
                  LEFT JOIN courses c ON cl.course_id = c.id 
                  ORDER BY c.title ASC, cl.title ASC";
$classes_result = $conn->query($classes_query);

// Get all active sessions
$sessions_query = "SELECT id, title, from_dated, to_dated FROM sessions WHERE status = 0 ORDER BY from_dated DESC";
$sessions_result = $conn->query($sessions_query);

// Initialize student data array
$students_data = [];
$months = [];

if ($selected_class > 0 && $selected_session > 0) {
    // Get session date range
    $session_query = $conn->prepare("SELECT from_dated, to_dated FROM sessions WHERE id = ?");
    $session_query->bind_param("i", $selected_session);
    $session_query->execute();
    $session_result = $session_query->get_result();
    $session_data = $session_result->fetch_assoc();
    
    if ($session_data) {
        $start_date = new DateTime($session_data['from_dated']);
        $end_date = new DateTime($session_data['to_dated']);
        
        // Generate all months in session
        $current = clone $start_date;
        while ($current <= $end_date) {
            $months[] = $current->format('Y-m');
            $current->modify('+1 month');
        }
        
        // Get students in selected class
        $students_query = $conn->prepare("
            SELECT 
                sr.id,
                sr.name,
                sr.father_name,
                sr.mobile,
                sr.reg_no,
                sc.id as student_class_id
            FROM student_registration sr
            INNER JOIN student_class sc ON sr.id = sc.student_registration_id
            WHERE sc.class_id = ? 
            AND sc.session_id = ?
            AND sc.status = 0
            ORDER BY sr.name ASC
        ");
        $students_query->bind_param("ii", $selected_class, $selected_session);
        $students_query->execute();
        $students_result = $students_query->get_result();
        
        while ($student = $students_result->fetch_assoc()) {
            $student_id = $student['id'];
            $student_class_id = $student['student_class_id'];
            
            // Get fee cards for this student
            $fee_query = $conn->prepare("
                SELECT 
                    sfc.id as fee_card_id,
                    DATE_FORMAT(sfc.due_date, '%Y-%m') as fee_month,
                    sfc.total_amount,
                    COALESCE(sfc.discount_amount, 0) as card_discount,
                    COALESCE(SUM(sfp.paid_amount), 0) as total_paid,
                    COALESCE(SUM(sfp.discount_amount), 0) as payment_discount
                FROM student_fee_card sfc
                LEFT JOIN student_fee_payments sfp ON sfc.id = sfp.fee_card_id
                WHERE sfc.student_class_id = ?
                AND sfc.session_id = ?
                GROUP BY sfc.id, fee_month, sfc.total_amount, card_discount
                ORDER BY fee_month ASC
            ");
            $fee_query->bind_param("ii", $student_class_id, $selected_session);
            $fee_query->execute();
            $fee_result = $fee_query->get_result();
            
            $monthly_fees = [];
            $total_dues = 0;
            $total_paid_amount = 0;
            $total_fee_amount = 0;
            
            while ($fee = $fee_result->fetch_assoc()) {
                $month_key = $fee['fee_month'];
                $total_amount = floatval($fee['total_amount']);
                $card_discount = floatval($fee['card_discount']);
                $paid = floatval($fee['total_paid']);
                $payment_discount = floatval($fee['payment_discount']);
                
                $total_discount = $card_discount + $payment_discount;
                $dues = $total_amount - $paid - $total_discount;
                $dues = $dues > 0 ? $dues : 0;
                
                if (!isset($monthly_fees[$month_key])) {
                    $monthly_fees[$month_key] = [
                        'total_amount' => 0,
                        'paid_amount' => 0,
                        'discount' => 0,
                        'dues' => 0
                    ];
                }
                
                $monthly_fees[$month_key]['total_amount'] += $total_amount;
                $monthly_fees[$month_key]['paid_amount'] += $paid;
                $monthly_fees[$month_key]['discount'] += $total_discount;
                $monthly_fees[$month_key]['dues'] += $dues;
                
                $total_dues += $dues;
                $total_paid_amount += $paid;
                $total_fee_amount += $total_amount;
            }
            
            $student['monthly_fees'] = $monthly_fees;
            $student['total_dues'] = $total_dues;
            $student['total_paid'] = $total_paid_amount;
            $student['total_fee'] = $total_fee_amount;
            
            $students_data[] = $student;
        }
    }
}

// Get month names for display
$month_names = [];
foreach ($months as $month) {
    $date = DateTime::createFromFormat('Y-m', $month);
    $month_names[$month] = $date->format('M y');
}

// Get session title and class title
$session_title = '';
if ($selected_session) {
    $sessions_result->data_seek(0);
    while ($row = $sessions_result->fetch_assoc()) {
        if ($row['id'] == $selected_session) {
            $session_title = $row['title'];
            break;
        }
    }
}

$class_title = '';
if ($selected_class && $classes_result) {
    $classes_result->data_seek(0);
    while ($row = $classes_result->fetch_assoc()) {
        if ($row['id'] == $selected_class) {
            $class_title = $row['course_name'] . ' - ' . $row['class_name'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <title>Class Wise Fee Report - Madrasa Management System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">

    <!-- Bootstrap CSS & JS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        <?php if ($lang == 'ur'): ?>
        body, .form-control, .btn, .alert, .panel-title, .table th, .table td {
            text-align: right;
            direction: rtl;
        }
        <?php endif; ?>
        
        body {
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }
        
        /* Main content starts after navbar */
        .main-content {
            padding-top: 15px;
        }
        
        .container-fluid {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .panel {
            margin-bottom: 20px;
        }
        
        .panel-primary .panel-heading {
            background-color: #337ab7;
            border-color: #337ab7;
        }
        
        .panel-info {
            background-color: #ffffff !important;
        }
        
        .panel-info .panel-heading {
            background-color: #f5f5f5 !important;
            border-color: #ddd !important;
            color: #333 !important;
        }
        
        .panel-info .panel-heading h3 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .badge-info {
            background-color: #337ab7;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: normal;
        }
        
        /* Class Header */
        .class-header {
            margin-bottom: 15px;
            padding: 10px 15px;
            background-color: #337ab7;
            color: white;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .class-header .badge {
            background-color: #fff;
            color: #337ab7;
            padding: 5px 10px;
            font-size: 13px;
        }
        
        /* Student Info */
        .student-info-block {
            line-height: 1.4;
        }
        
        .student-id-large {
            font-size: 16px;
            font-weight: 700;
            color: #1a56db;
            margin-bottom: 2px;
        }
        
        .student-name {
            font-weight: 600;
            font-size: 14px;
            color: #2d3748;
        }
        
        .student-father {
            font-size: 12px;
            color: #4a5568;
            margin-top: 1px;
        }
        
        .student-mobile {
            font-size: 11px;
            color: #0d6efd;
            margin-top: 1px;
        }
        
        /* Table */
        .table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table {
            background-color: #ffffff !important;
            margin-bottom: 20px;
            width: 100%;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            padding: 8px 6px;
            border-bottom: 2px solid #ddd;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 8px 6px;
            vertical-align: middle;
            font-size: 12px;
        }
        
        .table tbody tr:hover td {
            background-color: #f0f4f8 !important;
        }
        
        /* Amounts */
        .fee-amount {
            font-weight: bold;
            color: #28a745;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .fee-due {
            font-weight: bold;
            color: #dc3545;
            white-space: nowrap;
            font-size: 12px;
        }
        
        .total-fee {
            font-weight: bold;
            color: #0d6efd;
            white-space: nowrap;
            font-size: 12px;
        }
        
        /* Month badges */
        .monthly-fee-container {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
        }
        
        .month-badge {
            display: inline-block;
            border-radius: 3px;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: 600;
            white-space: nowrap;
            cursor: help;
            line-height: 1.4;
        }
        
        .month-badge.paid {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .month-badge.due {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .month-badge.partial {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .btn-collect {
            background-color: #28a745;
            border-color: #28a745;
            color: white;
            padding: 3px 10px;
            border-radius: 3px;
            font-weight: 600;
            font-size: 10px;
            text-decoration: none;
            display: inline-block;
            white-space: nowrap;
        }
        
        .btn-collect:hover {
            background-color: #218838;
            color: white;
            text-decoration: none;
        }
        
        .summary-row {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
        }
        
        .summary-row td {
            border-bottom: none;
            padding: 10px 6px;
            font-size: 13px;
        }
        
        .grand-total-label {
            font-weight: 600;
            color: #2d3748;
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .language-switcher {
            text-align: right;
            margin-bottom: 10px;
        }
        
        .language-switcher .btn {
            font-size: 13px !important;
            padding: 4px 10px !important;
            border-radius: 4px;
        }
        
        .language-switcher .btn.active {
            background-color: #337ab7 !important;
            border-color: #337ab7 !important;
            color: white !important;
        }
        
        .no-fee-text {
            color: #999;
            font-style: italic;
            font-size: 11px;
        }
        
        .text-center {
            text-align: center !important;
        }
        
        /* Column widths */
        .col-sr { width: 3%; }
        .col-info { width: 16%; }
        .col-monthly { width: 42%; }
        .col-total { width: 10%; }
        .col-paid { width: 10%; }
        .col-due { width: 10%; }
        .col-action { width: 9%; }
        
        /* Mobile Cards */
        .mobile-card-view {
            display: none;
        }
        
        .desktop-table-view {
            display: block;
        }
        
        .fee-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .card-student-header {
            background: #337ab7;
            color: white;
            padding: 12px 15px;
        }
        
        .card-student-id {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .card-student-name {
            font-size: 15px;
            font-weight: 600;
        }
        
        .card-student-details {
            font-size: 12px;
            opacity: 0.95;
            line-height: 1.5;
            margin-top: 5px;
        }
        
        .card-fee-body {
            padding: 12px;
        }
        
        .card-monthly-fees {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-bottom: 12px;
        }
        
        .card-summary {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        
        .card-summary-item {
            text-align: center;
            flex: 1;
        }
        
        .card-summary-label {
            font-size: 9px;
            color: #718096;
            text-transform: uppercase;
        }
        
        .card-summary-value {
            font-size: 14px;
            font-weight: bold;
        }
        
        .card-summary-value.total { color: #0d6efd; }
        .card-summary-value.paid { color: #28a745; }
        .card-summary-value.due { color: #dc3545; }
        
        .card-action {
            text-align: center;
            margin-top: 8px;
        }
        
        .card-action .btn-collect {
            width: 100%;
            padding: 10px;
            font-size: 14px;
        }
        
        @media (max-width: 992px) {
            .table thead th { font-size: 11px; padding: 6px 4px; }
            .table tbody td { font-size: 11px; padding: 6px 4px; }
            .month-badge { font-size: 9px; padding: 2px 5px; }
            .student-id-large { font-size: 14px; }
            .student-name { font-size: 12px; }
            .col-info { width: 20%; }
            .col-monthly { width: 36%; }
        }
        
        @media (max-width: 767px) {
            .desktop-table-view { display: none !important; }
            .mobile-card-view { display: block !important; }
            .class-header { flex-direction: column; text-align: center; gap: 8px; }
            .panel-info .panel-heading h3 { flex-direction: column; text-align: center; }
            .grand-total-label { margin-bottom: 10px; }
            .container-fluid { padding: 0 10px; }
        }
        
        @media print {
            .navbar, .no-print, .btn-collect, .language-switcher, .mobile-card-view {
                display: none !important;
            }
            .desktop-table-view { display: block !important; }
            body { background: white; }
            .panel { border: none !important; box-shadow: none !important; }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="main-content">
    <div class="container-fluid">
        
        <!-- Language switcher -->
        <div class="language-switcher no-print">
            <?php
            $current_query = $_GET;
            $current_query['lang'] = 'en';
            $en_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
            $current_query['lang'] = 'ur';
            $ur_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
            ?>
            <a href="<?php echo htmlspecialchars($en_url); ?>" class="btn btn-sm btn-default <?php echo ($lang == 'en') ? 'active' : ''; ?>">English</a>
            <a href="<?php echo htmlspecialchars($ur_url); ?>" class="btn btn-sm btn-default <?php echo ($lang == 'ur') ? 'active' : ''; ?>">اردو</a>
        </div>

        <!-- Class Selection -->
        <div class="panel panel-primary no-print">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fas fa-file-invoice"></i> 
                    <?php echo $lang == 'ur' ? 'کلاس وار فیس رپورٹ' : 'Class Wise Fee Report'; ?>
                </h3>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-chalkboard"></i> 
                                <?php echo $lang == 'ur' ? 'کلاس منتخب کریں' : 'Select Class'; ?>
                            </label>
                            <select name="class_id" id="class_select" class="form-control" onchange="loadClass(this.value)">
                                <option value=""><?php echo $lang == 'ur' ? '-- کلاس منتخب کریں --' : '-- Select Class --'; ?></option>
                                <?php 
                                if ($classes_result && $classes_result->num_rows > 0) {
                                    $classes_result->data_seek(0);
                                    while ($class = $classes_result->fetch_assoc()) {
                                        $selected = ($class['id'] == $selected_class) ? 'selected' : '';
                                        $display_name = $class['course_name'] . ' - ' . $class['class_name'];
                                        echo '<option value="' . $class['id'] . '" ' . $selected . '>' . htmlspecialchars($display_name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="form-group">
                            <label>
                                <i class="fas fa-calendar-alt"></i> 
                                <?php echo $lang == 'ur' ? 'موجودہ سیشن' : 'Current Session'; ?>
                            </label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($session_title); ?>" readonly style="background: #e9ecef;">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fee Records -->
        <?php if ($selected_class > 0 && !empty($students_data)): ?>
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <?php echo $lang == 'ur' ? 'فیس رپورٹ' : 'Fee Report'; ?>
                    <span class="badge-info">
                        <span class="glyphicon glyphicon-education"></span> 
                        <?php echo htmlspecialchars($class_title); ?>
                    </span>
                    <span class="badge-info">
                        <span class="glyphicon glyphicon-calendar"></span> 
                        <?php echo htmlspecialchars($session_title); ?>
                    </span>
                </h3>
            </div>
            <div class="panel-body">
                <div class="class-header">
                    <span><i class="glyphicon glyphicon-education"></i> <?php echo htmlspecialchars($class_title); ?></span>
                    <span class="badge"><?php echo count($students_data); ?> <?php echo $lang == 'ur' ? 'طلبہ' : 'Students'; ?></span>
                </div>
                
                <!-- DESKTOP TABLE -->
                <div class="desktop-table-view">
                    <div class="table-wrap">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th class="col-sr text-center">#</th>
                                    <th class="col-info"><?php echo $lang == 'ur' ? 'طالب علم' : 'Student Info'; ?></th>
                                    <th class="col-monthly"><?php echo $lang == 'ur' ? 'ماہانہ فیس' : 'Monthly Fee Status'; ?></th>
                                    <th class="col-total text-center"><?php echo $lang == 'ur' ? 'کل فیس' : 'Total Fee'; ?></th>
                                    <th class="col-paid text-center"><?php echo $lang == 'ur' ? 'جمع' : 'Paid'; ?></th>
                                    <th class="col-due text-center"><?php echo $lang == 'ur' ? 'واجبات' : 'Dues'; ?></th>
                                    <th class="col-action text-center no-print"><?php echo $lang == 'ur' ? 'عمل' : 'Action'; ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $counter = 1;
                                $grand_total_dues = 0;
                                $grand_total_paid = 0;
                                $grand_total_fee = 0;
                                
                                foreach ($students_data as $student): 
                                    $grand_total_dues += $student['total_dues'];
                                    $grand_total_paid += $student['total_paid'];
                                    $grand_total_fee += $student['total_fee'];
                                ?>
                                    <tr>
                                        <td class="text-center"><?php echo $counter++; ?></td>
                                        <td>
                                            <div class="student-info-block">
                                                <div class="student-id-large"><?php echo htmlspecialchars($student['reg_no']); ?></div>
                                                <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <div class="student-father">S/O <?php echo htmlspecialchars($student['father_name']); ?></div>
                                                <div class="student-mobile">📱 <?php echo htmlspecialchars($student['mobile']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $has_any_fee = false;
                                            if (!empty($student['monthly_fees'])):
                                            ?>
                                                <div class="monthly-fee-container">
                                                <?php 
                                                foreach ($months as $month): 
                                                    if (isset($student['monthly_fees'][$month])):
                                                        $has_any_fee = true;
                                                        $month_fee = $student['monthly_fees'][$month];
                                                        $dues = $month_fee['dues'];
                                                        $paid = $month_fee['paid_amount'];
                                                        $total = $month_fee['total_amount'];
                                                        
                                                        if ($dues > 0 && $paid > 0) {
                                                            $badge_class = 'partial';
                                                        } elseif ($dues > 0) {
                                                            $badge_class = 'due';
                                                        } else {
                                                            $badge_class = 'paid';
                                                        }
                                                ?>
                                                    <span class="month-badge <?php echo $badge_class; ?>" 
                                                          title="Total: ₹<?php echo number_format($total); ?> | Paid: ₹<?php echo number_format($paid); ?> | Due: ₹<?php echo number_format($dues); ?>">
                                                        <?php echo $month_names[$month]; ?> ₹<?php echo number_format($total); ?>
                                                    </span>
                                                <?php 
                                                    endif;
                                                endforeach;
                                                ?>
                                                </div>
                                            <?php 
                                            endif;
                                            
                                            if (!$has_any_fee):
                                            ?>
                                                <span class="no-fee-text">No fee records</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="total-fee">₹<?php echo number_format($student['total_fee']); ?></span></td>
                                        <td class="text-center"><span class="fee-amount">₹<?php echo number_format($student['total_paid']); ?></span></td>
                                        <td class="text-center">
                                            <span class="<?php echo $student['total_dues'] > 0 ? 'fee-due' : 'fee-amount'; ?>">
                                                ₹<?php echo number_format($student['total_dues']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center no-print">
                                            <a href="fee_collection.php?id=<?php echo $student['id']; ?>&lang=<?php echo $lang; ?>" class="btn-collect">
                                                <i class="fas fa-money-bill-wave"></i> Collect
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <tr class="summary-row">
                                    <td colspan="2" class="text-center">
                                        <strong><?php echo $lang == 'ur' ? 'کل' : 'Total'; ?></strong>
                                        <br><small>(<?php echo count($students_data); ?> Students)</small>
                                    </td>
                                    <td class="text-center">-</td>
                                    <td class="text-center">₹<?php echo number_format($grand_total_fee); ?></td>
                                    <td class="text-center">₹<?php echo number_format($grand_total_paid); ?></td>
                                    <td class="text-center">₹<?php echo number_format($grand_total_dues); ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- MOBILE CARDS -->
                <div class="mobile-card-view">
                    <?php foreach ($students_data as $student): ?>
                        <div class="fee-card">
                            <div class="card-student-header">
                                <div class="card-student-id">#<?php echo htmlspecialchars($student['reg_no']); ?></div>
                                <div class="card-student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                                <div class="card-student-details">
                                    <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($student['father_name']); ?></div>
                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['mobile']); ?></div>
                                </div>
                            </div>
                            <div class="card-fee-body">
                                <div class="card-monthly-fees">
                                    <?php 
                                    $has_any_fee = false;
                                    if (!empty($student['monthly_fees'])):
                                        foreach ($months as $month): 
                                            if (isset($student['monthly_fees'][$month])):
                                                $has_any_fee = true;
                                                $month_fee = $student['monthly_fees'][$month];
                                                $dues = $month_fee['dues'];
                                                $paid = $month_fee['paid_amount'];
                                                $total = $month_fee['total_amount'];
                                                
                                                if ($dues > 0 && $paid > 0) {
                                                    $badge_class = 'partial';
                                                } elseif ($dues > 0) {
                                                    $badge_class = 'due';
                                                } else {
                                                    $badge_class = 'paid';
                                                }
                                    ?>
                                                <span class="month-badge <?php echo $badge_class; ?>">
                                                    <?php echo $month_names[$month]; ?> ₹<?php echo number_format($total); ?>
                                                </span>
                                    <?php 
                                            endif;
                                        endforeach;
                                    endif;
                                    
                                    if (!$has_any_fee):
                                    ?>
                                        <span class="no-fee-text">No fee records found</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-summary">
                                    <div class="card-summary-item">
                                        <div class="card-summary-label">Total Fee</div>
                                        <div class="card-summary-value total">₹<?php echo number_format($student['total_fee']); ?></div>
                                    </div>
                                    <div class="card-summary-item">
                                        <div class="card-summary-label">Paid</div>
                                        <div class="card-summary-value paid">₹<?php echo number_format($student['total_paid']); ?></div>
                                    </div>
                                    <div class="card-summary-item">
                                        <div class="card-summary-label">Dues</div>
                                        <div class="card-summary-value due">₹<?php echo number_format($student['total_dues']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="card-action no-print">
                                    <a href="fee_collection.php?id=<?php echo $student['id']; ?>&lang=<?php echo $lang; ?>" class="btn-collect">
                                        <i class="fas fa-money-bill-wave"></i> Collect Fee
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Summary Cards -->
                <div class="row desktop-table-view" style="margin-top: 20px;">
                    <div class="col-md-4 col-sm-4">
                        <div class="grand-total-label text-center">
                            <div style="font-size: 12px; color: #718096;"><i class="fas fa-calculator"></i> Total Fee</div>
                            <div style="font-size: 22px; font-weight: 700; color: #0d6efd;">₹<?php echo number_format($grand_total_fee); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-4">
                        <div class="grand-total-label text-center">
                            <div style="font-size: 12px; color: #718096;"><i class="fas fa-check-circle"></i> Total Collected</div>
                            <div style="font-size: 22px; font-weight: 700; color: #198754;">₹<?php echo number_format($grand_total_paid); ?></div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-4">
                        <div class="grand-total-label text-center">
                            <div style="font-size: 12px; color: #718096;"><i class="fas fa-exclamation-circle"></i> Total Dues</div>
                            <div style="font-size: 22px; font-weight: 700; color: <?php echo $grand_total_dues > 0 ? '#dc3545' : '#198754'; ?>;">₹<?php echo number_format($grand_total_dues); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($selected_class > 0 && empty($students_data)): ?>
        <div class="alert alert-info text-center" style="padding: 50px;">
            <i class="glyphicon glyphicon-info-sign" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
            <h4>No Students Found</h4>
            <p>No students are enrolled in this class for the current session.</p>
        </div>
        <?php else: ?>
        <div class="alert alert-info text-center" style="padding: 50px;">
            <i class="glyphicon glyphicon-hand-up" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
            <h4>Please Select a Class</h4>
            <p>Select a class above to view the fee report for the current session.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-load class when selected
function loadClass(classId) {
    if (classId) {
        var lang = '<?php echo $lang; ?>';
        window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?class_id=' + classId + '&lang=' + lang;
    }
}

// Initialize tooltips
$(document).ready(function() {
    $('[title]').tooltip({
        placement: 'top',
        container: 'body'
    });
});
</script>

</body>
</html>