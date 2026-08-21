<?php
// print_slip_handler.php
// Handles thermal slip print requests with secure data fetching

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('security.php');
require_once('conn_inc.php');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: feecard_create_monthly.php");
    exit();
}

// CSRF Protection
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['message'] = "Invalid request!";
    $_SESSION['message_type'] = 'danger';
    header("Location: feecard_create_monthly.php");
    exit();
}

// Regenerate CSRF token for next request
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Get and validate POST parameters
$student_id = isset($_POST['student_registration_id']) ? intval($_POST['student_registration_id']) : 0;
$session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
$class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;
$month = isset($_POST['month']) ? preg_replace('/[^0-9]/', '', $_POST['month']) : '';
$year = date('Y');

// Validate required parameters
if (!$student_id || !$session_id || !$class_id || !preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
    $_SESSION['message'] = "Invalid selection parameters.";
    $_SESSION['message_type'] = 'danger';
    header("Location: feecard_create_monthly.php");
    exit();
}

$due_date = date('Y-m-t', strtotime("$year-$month-01"));
$lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'en';

// Language strings for thermal slip
$translations = [
    'en' => [
        'school_name' => 'المدرسہ الفاروقیہ للتجوید والقراءت',
        'school_address' => 'نیو کالونی مٹہ سوات پاکستان',
        'fee_slip' => 'Fee Slip',
        'receipt_no' => 'Receipt No',
        'date' => 'Date',
        'student_id' => 'Student ID',
        'student_name' => 'Student Name',
        'father_name' => 'Father Name',
        'mobile' => 'Mobile',
        'class' => 'Class',
        'session' => 'Session',
        'month' => 'Month',
        'issue_date' => 'Issue Date',
        'fee_type' => 'Fee Type',
        'amount' => 'Amount',
        'old_dues' => 'Previous Dues',
        'total_payable' => 'Total Payable',
        'paid' => 'Paid',
        'thank_you' => 'Thank You',
        'office_signature' => 'Office Signature',
        'balance' => 'Balance',
        'paid_status' => 'Paid',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial'
    ],
    'ur' => [
        'school_name' => 'المدرسہ الفاروقیہ للتجوید والقراءت',
        'school_address' => 'نیو کالونی مٹہ سوات پاکستان',
        'fee_slip' => 'فیس رسید',
        'receipt_no' => 'رسید نمبر',
        'date' => 'تاریخ',
        'student_id' => 'طالب علم شناخت نمبر',
        'student_name' => 'طالب علم',
        'father_name' => 'والد کا نام',
        'mobile' => 'موبائل نمبر',
        'class' => 'کلاس',
        'session' => 'سیشن',
        'month' => 'مہینہ',
        'issue_date' => 'تاریخ اجراء',
        'fee_type' => 'فیس کی قسم',
        'amount' => 'رقم',
        'old_dues' => 'پچھلے بقایا جات',
        'total_payable' => 'کل قابل ادائیگی',
        'paid' => 'ادا شدہ',
        'thank_you' => 'شکریہ',
        'office_signature' => 'آفس دستخط',
        'balance' => 'بقایا',
        'paid_status' => 'ادا شدہ',
        'unpaid' => 'ادا نہیں',
        'partial' => 'جزوی'
    ]
];

// Fetch student details
$stmt = $conn->prepare("
    SELECT sr.id, sr.name, sr.father_name, sr.mobile, sc.id as student_class_id
    FROM student_registration sr
    INNER JOIN student_class sc ON sr.id = sc.student_registration_id
    WHERE sr.id = ? AND sc.session_id = ? AND sc.class_id = ? AND sc.status = 0
");
$stmt->bind_param("iii", $student_id, $session_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['message'] = "Student not found or not active in selected class/session.";
    $_SESSION['message_type'] = 'danger';
    header("Location: feecard_create_monthly.php");
    exit();
}

// Fetch current month fee records - ORDER BY title only (NO sort_order)
$fee_stmt = $conn->prepare("
    SELECT sfc.id, sfc.total_amount, sfc.remarks, ft.title as fee_type_title, ft.id as fee_type_id
    FROM student_fee_card sfc
    INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
    WHERE sfc.student_class_id = ? AND sfc.due_date = ? AND sfc.session_id = ?
    ORDER BY ft.title ASC
");
$fee_stmt->bind_param("isi", $student['student_class_id'], $due_date, $session_id);
$fee_stmt->execute();
$pending_fees = $fee_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fee_stmt->close();

// Calculate old dues (all previous months in same session)
$old_dues_stmt = $conn->prepare("
    SELECT SUM(sfc.total_amount) as total_old_dues
    FROM student_fee_card sfc
    WHERE sfc.student_class_id = ? AND sfc.due_date < ? AND sfc.session_id = ?
");
$old_dues_stmt->bind_param("isi", $student['student_class_id'], $due_date, $session_id);
$old_dues_stmt->execute();
$old_dues_row = $old_dues_stmt->get_result()->fetch_assoc();
$old_dues_stmt->close();
$old_dues = floatval($old_dues_row['total_old_dues'] ?? 0);

// Calculate current month total and grand total
$current_total = 0;
foreach ($pending_fees as $fee) {
    $current_total += floatval($fee['total_amount']);
}
$total_due = $current_total + $old_dues;

// Get class and session titles for display
$class_title = '';
$session_title = '';

$class_result = $conn->query("SELECT title FROM classes WHERE id = $class_id LIMIT 1");
if ($class_result && $row = $class_result->fetch_assoc()) {
    $class_title = $row['title'];
}
if ($class_result) $class_result->free();

$session_result = $conn->query("SELECT title FROM sessions WHERE id = $session_id LIMIT 1");
if ($session_result && $row = $session_result->fetch_assoc()) {
    $session_title = $row['title'];
}
if ($session_result) $session_result->free();

// Format month name for display
$month_names = [
    'en' => ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June',
            '07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'],
    'ur' => ['01'=>'جنوری','02'=>'فروری','03'=>'مارچ','04'=>'اپریل','05'=>'مئی','06'=>'جون',
            '07'=>'جولائی','08'=>'اگست','09'=>'ستمبر','10'=>'اکتوبر','11'=>'نومبر','12'=>'دسمبر']
];
$month_display = isset($month_names[$lang][$month]) ? $month_names[$lang][$month] : $month;

// Prepare print data structure for thermal_print_view.php
$_SESSION['print_data'] = [
    'print_type' => 'feecards',
    'lang' => $lang,
    'session_title' => $session_title,
    'class_title' => $class_title,
    'month' => $month,
    'month_display' => $month_display,
    'year' => $year,
    'due_date' => $due_date,
    'students' => [[
        'id' => $student['id'],
        'name' => $student['name'],
        'father_name' => $student['father_name'],
        'mobile' => $student['mobile'],
        'student_class_id' => $student['student_class_id'],
        'pending_fees' => $pending_fees,
        'old_dues_amount' => $old_dues,
        'current_total' => $current_total,
        'total_due' => $total_due,
        'paid_until_display' => '',
        'payment_status' => ($total_due <= 0) ? 'paid' : 'unpaid'
    ]],
    'total_remaining' => $total_due,
    'total_old_dues' => $old_dues,
    'receipt_no' => 'RCP-' . date('Ymd') . '-' . str_pad($student['id'], 5, '0', STR_PAD_LEFT),
    'issue_date' => date('d-m-Y')
];

// Redirect to thermal print view
header("Location: thermal_print_view.php");
exit();
?>