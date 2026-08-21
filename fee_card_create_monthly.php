<?php
// Error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
require_once('security.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$lang = $_SESSION['lang'];

// Language strings
$translations = array(
    'en' => array(
        'title' => 'Student Monthly Fee Card Management',
        'session_label' => 'Session',
        'month_label' => 'Month',
        'class_label' => 'Class',
        'select_option' => '-- Select --',
        'select_session' => '-- Select Session --',
        'select_month' => '-- Select Month --',
        'select_class' => '-- Select Class --',
        'fee_types_label' => 'Select Fee Types and Adjust Amounts',
        'generate_btn' => 'Generate Fee Cards',
        'update_btn' => 'Update Fee Cards',
        'view_btn' => 'View Existing',
        'print_btn' => 'Print All',
        'print_class_btn' => 'Print Class',
        'print_slip_btn' => 'Print Slip',
        'fee_records_title' => 'Fee Card Records',
        'no_records' => 'No monthly fee records found for the selected session, class, and month.',
        'no_fee_types' => 'No fee types defined for the selected class and session.',
        'define_fee_types' => 'Please define fee types for this class in the Class Fee Types page first.',
        'update_mode_note' => 'Update Mode: Checked fee types will be updated. Unchecked fee types will be removed for this month.',
        'duplicate_note' => 'A student cannot have the same fee type twice for the same month. Duplicate entries will be skipped automatically.',
        'zero_amount_note' => 'Note: You can enter 0 (zero) amount for free or waived fees.',
        'override_note' => 'Note: Student-specific fee assignments will override default class fee amounts.',
        'amount_note' => 'You can enter 0 for free/waived fees',
        'edit_fee_title' => 'Edit Fee Amount',
        'delete_confirm_title' => 'Confirm Delete',
        'amount_label' => 'Amount (PKR)',
        'remarks_label' => 'Remarks',
        'cancel' => 'Cancel',
        'update' => 'Update Fee',
        'delete' => 'Delete Fee',
        'delete_confirmation' => 'Are you sure you want to delete this fee record?',
        'delete_warning' => 'This action cannot be undone.',
        'student' => 'Student',
        'fee_type' => 'Fee Type',
        'student_id' => 'Student ID',
        'student_name' => 'Student',
        'father_name' => 'Father Name',
        'mobile' => 'Mobile',
        'fee_type_title' => 'Fee Type',
        'amount' => 'Amount',
        'due_date' => 'Due Date',
        'remarks' => 'Remarks',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'sr_no' => '#',
        'success' => 'Operation completed successfully!',
        'error' => 'Error: ',
        'invalid_csrf' => 'Invalid request!',
        'select_session_first' => 'Please select a session first.',
        'select_fee_types' => 'Please select at least one fee type.',
        'valid_amount' => 'Please enter a valid amount (0 or greater) for all selected fee types.',
        'confirm_generate' => 'Are you sure you want to generate monthly fee records for selected fee types? Student-specific assignments will override default amounts.',
        'confirm_update' => 'Are you sure you want to update fee amounts? Unchecked fee types will be removed for this month. Student-specific assignments will override default amounts.',
        'no_students' => 'No active students found for the selected session and class.',
        'invalid_selection' => 'Error: Invalid session, class, or month.',
        'fee_amount_updated' => 'Fee amount updated successfully!',
        'error_updating_fee' => 'Error updating fee amount.',
        'fee_record_deleted' => 'Fee record deleted successfully!',
        'error_deleting_fee' => 'Error deleting fee record.',
        'update_success' => 'Fee records updated successfully!',
        'create_success' => 'Monthly fee records created successfully!',
        'records_created' => 'Created:',
        'records_updated' => 'Updated:',
        'records_removed' => 'Removed:',
        'duplicate_skipped' => 'Skipped: duplicate entries',
        'records' => 'records',
        'january' => 'January', 'february' => 'February', 'march' => 'March',
        'april' => 'April', 'may' => 'May', 'june' => 'June',
        'july' => 'July', 'august' => 'August', 'september' => 'September',
        'october' => 'October', 'november' => 'November', 'december' => 'December',
        'original_amount' => 'Original',
        'existing_amount' => 'Existing',
        'loading' => 'Loading...',
        'old_dues' => 'Previous Dues',
        'total_payable' => 'Total Payable',
        'paid_amount' => 'Paid Amount',
        'balance' => 'Balance',
        'payment_status' => 'Payment Status',
        'unpaid' => 'Unpaid',
        'partial' => 'Partial',
        'paid' => 'Paid',
        'barcode' => 'Barcode',
        'school_name' => 'Madrasa Al-Farooqia',
        'school_address' => 'New Colony Matta Swat Pakistan',
        'fee_slip' => 'Fee Slip',
        'receipt_no' => 'Receipt No',
        'date' => 'Date',
        'student_details' => 'Student Details',
        'fee_details' => 'Fee Details',
        'summary' => 'Summary',
        'notes' => 'Notes',
        'thank_you' => 'Thank you',
        'issue_date' => 'Issue Date',
        'class' => 'Class',
        'session' => 'Session',
        'total_due' => 'Total Due',
        'pending_fees' => 'Pending Fees',
        'month' => 'Month',
        'class_fee' => 'Class Fee',
        'student_assigned' => 'Student Assigned',
        'fee_source' => 'Fee Source',
    ),
    'ur' => array(
        'title' => 'طلبا ماہانہ فیس کارڈ مینجمنٹ',
        'session_label' => 'سیشن',
        'month_label' => 'مہینہ',
        'class_label' => 'کلاس',
        'select_option' => '-- منتخب کریں --',
        'select_session' => '-- سیشن منتخب کریں --',
        'select_month' => '-- مہینہ منتخب کریں --',
        'select_class' => '-- کلاس منتخب کریں --',
        'fee_types_label' => 'فیس کی اقسام منتخب کریں اور رقم ایڈجسٹ کریں',
        'generate_btn' => 'فیس کارڈز بنائیں',
        'update_btn' => 'فیس کارڈز اپ ڈیٹ کریں',
        'view_btn' => 'موجودہ دیکھیں',
        'print_btn' => 'سب پرنٹ کریں',
        'print_class_btn' => 'کلاس پرنٹ کریں',
        'print_slip_btn' => 'پرنٹ سلپ',
        'fee_records_title' => 'فیس کارڈ ریکارڈز',
        'no_records' => 'منتخب سیشن، کلاس اور مہینہ کے لیے کوئی ماہانہ فیس ریکارڈ نہیں ملا۔',
        'no_fee_types' => 'منتخب کلاس اور سیشن کے لیے کوئی فیس ٹائپ متعین نہیں ہے۔',
        'define_fee_types' => 'براہ کرم پہلے کلاس فیس ٹائپس پیج پر اس کلاس کے لیے فیس ٹائپ متعین کریں۔',
        'update_mode_note' => 'اپ ڈیٹ موڈ: چیک شدہ فیس ٹائپس اپ ڈیٹ ہوں گی۔ غیر چیک شدہ فیس ٹائپس اس مہینے کے لیے ہٹا دی جائیں گی۔',
        'duplicate_note' => 'ایک طالب علم کے لیے ایک ہی مہینے میں ایک ہی فیس ٹائپ دو بار نہیں ہو سکتی۔ ڈپلیکیٹ اندراجات خود بخود چھوڑ دی جائیں گی۔',
        'zero_amount_note' => 'نوٹ: آپ مفت یا معاف شدہ فیس کے لیے 0 (صفر) رقم درج کر سکتے ہیں۔',
        'override_note' => 'نوٹ: طالب علم سے متعلقہ فیس مقرر کردہ رقم کلاس کی ڈیفالٹ رقم کو اوور رائڈ کر دے گی۔',
        'amount_note' => 'آپ مفت/معاف شدہ فیس کے لیے 0 درج کر سکتے ہیں',
        'edit_fee_title' => 'فیس رقم میں ترمیم کریں',
        'delete_confirm_title' => 'حذف کرنے کی تصدیق',
        'amount_label' => 'رقم (PKR)',
        'remarks_label' => 'تبصرہ',
        'cancel' => 'منسوخ کریں',
        'update' => 'فیس اپ ڈیٹ کریں',
        'delete' => 'فیس حذف کریں',
        'delete_confirmation' => 'کیا آپ واقعی یہ فیس ریکارڈ حذف کرنا چاہتے ہیں؟',
        'delete_warning' => 'یہ عمل واپس نہیں کیا جا سکتا۔',
        'student' => 'طالب علم',
        'fee_type' => 'فیس کی قسم',
        'student_id' => 'طالب علم شناخت نمبر',
        'student_name' => 'طالب علم',
        'father_name' => 'والد کا نام',
        'mobile' => 'موبائل نمبر',
        'fee_type_title' => 'فیس کی قسم',
        'amount' => 'رقم',
        'due_date' => 'آخری تاریخ',
        'remarks' => 'تبصرہ',
        'actions' => 'اعمال',
        'edit' => 'ترمیم',
        'delete' => 'حذف',
        'sr_no' => 'نمبر',
        'success' => 'آپریشن کامیابی سے مکمل ہوا!',
        'error' => 'خرابی: ',
        'invalid_csrf' => 'غلط درخواست!',
        'select_session_first' => 'براہ کرم پہلے سیشن منتخب کریں۔',
        'select_fee_types' => 'براہ کرم کم از کم ایک فیس ٹائپ منتخب کریں۔',
        'valid_amount' => 'براہ کرم تمام منتخب فیس ٹائپس کے لیے درست رقم (0 یا اس سے زیادہ) درج کریں۔',
        'confirm_generate' => 'کیا آپ منتخب فیس ٹائپس کے لیے ماہانہ فیس ریکارڈز بنانا چاہتے ہیں؟ طالب علم سے متعلقہ فیس ڈیفالٹ رقم کو اوور رائڈ کرے گی۔',
        'confirm_update' => 'کیا آپ فیس رقوم اپ ڈیٹ کرنا چاہتے ہیں؟ غیر چیک شدہ فیس ٹائپس اس مہینے کے لیے ہٹا دی جائیں گی۔ طالب علم سے متعلقہ فیس ڈیفالٹ رقم کو اوور رائڈ کرے گی۔',
        'no_students' => 'منتخب سیشن اور کلاس کے لیے کوئی فعال طالب علم نہیں ملا۔',
        'invalid_selection' => 'خرابی: غلط سیشن، کلاس، یا مہینہ۔',
        'fee_amount_updated' => 'فیس رقم کامیابی سے اپ ڈیٹ ہو گئی!',
        'error_updating_fee' => 'فیس رقم اپ ڈیٹ کرنے میں خرابی۔',
        'fee_record_deleted' => 'فیس ریکارڈ کامیابی سے حذف ہو گیا!',
        'error_deleting_fee' => 'فیس ریکارڈ حذف کرنے میں خرابی۔',
        'update_success' => 'فیس ریکارڈز کامیابی سے اپ ڈیٹ ہو گئے!',
        'create_success' => 'ماہانہ فیس ریکارڈز کامیابی سے بن گئے!',
        'records_created' => 'بنائے گئے:',
        'records_updated' => 'اپ ڈیٹ کیے گئے:',
        'records_removed' => 'ہٹائے گئے:',
        'duplicate_skipped' => 'چھوڑے گئے: ڈپلیکیٹ اندراجات',
        'records' => 'ریکارڈز',
        'january' => 'جنوری', 'february' => 'فروری', 'march' => 'مارچ',
        'april' => 'اپریل', 'may' => 'مئی', 'june' => 'جون',
        'july' => 'جولائی', 'august' => 'اگست', 'september' => 'ستمبر',
        'october' => 'اکتوبر', 'november' => 'نومبر', 'december' => 'دسمبر',
        'original_amount' => 'اصل',
        'existing_amount' => 'موجودہ',
        'loading' => 'لوڈ ہو رہا ہے...',
        'old_dues' => 'پچھلے بقایا جات',
        'total_payable' => 'کل قابل ادائیگی',
        'paid_amount' => 'ادا کردہ رقم',
        'balance' => 'بقایا',
        'payment_status' => 'ادائیگی کی حیثیت',
        'unpaid' => 'ادا نہیں',
        'partial' => 'جزوی',
        'paid' => 'ادا شدہ',
        'barcode' => 'بارکوڈ',
        'school_name' => 'المدرسہ الفاروقیہ للتجوید والقراءت',
        'school_address' => 'نیو کالونی مٹہ سوات پاکستان',
        'fee_slip' => 'فیس رسید',
        'receipt_no' => 'رسید نمبر',
        'date' => 'تاریخ',
        'student_details' => 'طالب علم کی تفصیلات',
        'fee_details' => 'فیس کی تفصیلات',
        'summary' => 'خلاصہ',
        'notes' => 'نوٹس',
        'thank_you' => 'شکریہ',
        'issue_date' => 'تاریخ اجراء',
        'class' => 'کلاس',
        'session' => 'سیشن',
        'total_due' => 'کل بقایا',
        'pending_fees' => 'بقایا فیس',
        'month' => 'مہینہ',
        'class_fee' => 'کلاس فیس',
        'student_assigned' => 'طالب علم کی مقرر کردہ فیس',
        'fee_source' => 'فیس کا ماخذ',
    )
);

// Create database connection
require_once('conn_inc.php');

// Fetch sessions
$sessions = array();
$result = $conn->query("SELECT id, title FROM sessions WHERE status = 0 ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    $sessions[$row['id']] = $row['title'];
}
$result->free();

// Fetch classes
$classes = array();
$result = $conn->query("SELECT id, title FROM classes WHERE status = 1 ORDER BY title");
while ($row = $result->fetch_assoc()) {
    $classes[$row['id']] = $row['title'];
}
$result->free();

// Handle edit/delete actions (keep existing logic)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = $translations[$lang]['invalid_csrf'];
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF'] . "?lang=" . $lang);
        exit();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $action_type = $_POST['action_type'];

    if ($action_type === 'edit_fee') {
        $fee_card_id = intval($_POST['fee_card_id']);
        $new_amount = floatval($_POST['amount']);
        $remarks = $_POST['remarks'];

        $stmt = $conn->prepare("UPDATE student_fee_card SET total_amount = ?, remarks = ? WHERE id = ?");
        $stmt->bind_param("dsi", $new_amount, $remarks, $fee_card_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $translations[$lang]['fee_amount_updated'];
            $_SESSION['message_type'] = 'success';
        } else {
            error_log("Database error: " . $stmt->error, 3, 'errors.log');
            $_SESSION['message'] = $translations[$lang]['error_updating_fee'];
            $_SESSION['message_type'] = 'danger';
        }
        $stmt->close();

        $redirect_url = $_SERVER['PHP_SELF'] . "?session_id=" . intval($_POST['session_id']) . 
                       "&class_id=" . intval($_POST['class_id']) . "&month=" . $_POST['month'] . 
                       "&lang=" . $lang . "#class-" . intval($_POST['class_id']);
        header("Location: " . $redirect_url);
        exit();

    } elseif ($action_type === 'delete_fee') {
        $fee_card_id = intval($_POST['fee_card_id']);

        $stmt = $conn->prepare("DELETE FROM student_fee_card WHERE id = ?");
        $stmt->bind_param("i", $fee_card_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = $translations[$lang]['fee_record_deleted'];
            $_SESSION['message_type'] = 'success';
        } else {
            error_log("Database error: " . $stmt->error, 3, 'errors.log');
            $_SESSION['message'] = $translations[$lang]['error_deleting_fee'];
            $_SESSION['message_type'] = 'danger';
        }
        $stmt->close();

        $redirect_url = $_SERVER['PHP_SELF'] . "?session_id=" . intval($_POST['session_id']) . 
                       "&class_id=" . intval($_POST['class_id']) . "&month=" . $_POST['month'] . 
                       "&lang=" . $lang . "#class-" . intval($_POST['class_id']);
        header("Location: " . $redirect_url);
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['action_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = $translations[$lang]['invalid_csrf'];
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF'] . "?lang=" . $lang);
        exit();
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $session_id = intval($_POST['session_id']);
    $class_id = intval($_POST['class_id']);
    $month = $_POST['month'];
    $year = date('Y');
    $action = isset($_POST['action']) ? $_POST['action'] : 'create';

    if ($action === 'view') {
        $query_string = "?session_id=$session_id&class_id=$class_id&month=$month&lang=" . $lang;
        header("Location: " . $_SERVER['PHP_SELF'] . $query_string);
        exit();
    }

    if (($action === 'create' || $action === 'update') && $session_id && $class_id && preg_match('/^(0[1-9]|1[0-2])$/', $month)) {
        if ($action === 'create' && (!isset($_POST['fee_types']) || empty($_POST['fee_types']))) {
            $_SESSION['message'] = $translations[$lang]['select_fee_types'];
            $_SESSION['message_type'] = 'warning';
            header("Location: " . $_SERVER['PHP_SELF'] . "?session_id=$session_id&class_id=$class_id&month=$month&lang=" . $lang . "#class-$class_id");
            exit();
        }
        
        $selected_fee_types = isset($_POST['fee_types']) ? array_map('intval', $_POST['fee_types']) : array();
        $due_date = date('Y-m-t', strtotime("$year-$month-01"));

        // Get active students
        $stmt = $conn->prepare("
            SELECT sc.id AS student_class_id, sr.id AS student_registration_id, sr.name, sr.father_name, sr.mobile
            FROM student_class sc
            INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
            WHERE sc.session_id = ? AND sc.class_id = ? AND sc.status = 0 AND sr.status = 0
            ORDER BY sr.name
        ");
        $stmt->bind_param("ii", $session_id, $class_id);
        $stmt->execute();
        $students_result = $stmt->get_result();
        $students = $students_result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $students_result->free();

        if (empty($students)) {
            $_SESSION['message'] = $translations[$lang]['no_students'];
            $_SESSION['message_type'] = 'warning';
            header("Location: " . $_SERVER['PHP_SELF'] . "?session_id=$session_id&class_id=$class_id&month=$month&lang=" . $lang . "#class-$class_id");
            exit();
        }

        // Get selected fee types with amounts from form
        $fee_structures = array();
        foreach ($selected_fee_types as $fee_type_id) {
            if (isset($_POST['fee_amounts'][$fee_type_id])) {
                $amount = floatval($_POST['fee_amounts'][$fee_type_id]);
                $title = isset($_POST['fee_titles'][$fee_type_id]) ? $_POST['fee_titles'][$fee_type_id] : '';
                
                if ($amount >= 0 && !empty($title)) {
                    $fee_structures[] = array(
                        'fee_type_id' => $fee_type_id,
                        'amount' => $amount,
                        'title' => $title
                    );
                }
            }
        }

        if ($action === 'create' && empty($fee_structures)) {
            $_SESSION['message'] = $translations[$lang]['no_fee_types'];
            $_SESSION['message_type'] = 'warning';
            header("Location: " . $_SERVER['PHP_SELF'] . "?session_id=$session_id&class_id=$class_id&month=$month&lang=" . $lang . "#class-$class_id");
            exit();
        }

        $record_count = 0;
        $delete_count = 0;
        $duplicate_count = 0;
        $month_name = date('F', strtotime("$year-$month-01"));

        if ($action === 'create') {
            $insert_stmt = $conn->prepare("
                INSERT INTO student_fee_card 
                (student_class_id, fee_type_id, total_amount, due_date, session_id, remarks)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $check_stmt = $conn->prepare("
                SELECT 1 FROM student_fee_card 
                WHERE student_class_id = ? AND fee_type_id = ? AND due_date = ? AND session_id = ?
            ");

            foreach ($students as $student) {
                $student_class_id = $student['student_class_id'];
                
                $student_fee_stmt = $conn->prepare("
                    SELECT fee_type_id, amount 
                    FROM student_fee_assignments 
                    WHERE student_id = ? AND status = 1
                ");
                $student_fee_stmt->bind_param("i", $student['student_registration_id']);
                $student_fee_stmt->execute();
                $student_fees_result = $student_fee_stmt->get_result();
                $student_fees = array();
                while ($fee_row = $student_fees_result->fetch_assoc()) {
                    $student_fees[$fee_row['fee_type_id']] = $fee_row['amount'];
                }
                $student_fee_stmt->close();
                $student_fees_result->free();
                
                foreach ($fee_structures as $fee) {
                    $fee_type_id = $fee['fee_type_id'];
                    $amount = isset($student_fees[$fee_type_id]) ? $student_fees[$fee_type_id] : $fee['amount'];
                    $remarks = $fee['title'] . " fee for $month_name $year";

                    $check_stmt->bind_param("iiss", $student_class_id, $fee_type_id, $due_date, $session_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    $exists = $check_result->num_rows > 0;
                    $check_result->free();

                    if (!$exists) {
                        $insert_stmt->bind_param("iidssi", $student_class_id, $fee_type_id, $amount, $due_date, $session_id, $remarks);
                        if ($insert_stmt->execute()) {
                            $record_count++;
                        }
                    } else {
                        $duplicate_count++;
                    }
                }
            }

            $insert_stmt->close();
            $check_stmt->close();

            $message = $translations[$lang]['create_success'];
            if ($record_count > 0) {
                $message .= " " . $translations[$lang]['records_created'] . " $record_count " . $translations[$lang]['records'] . ".";
            }
            if ($duplicate_count > 0) {
                $message .= " " . $translations[$lang]['duplicate_skipped'] . ": $duplicate_count.";
            }
            
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = 'success';
            
        } elseif ($action === 'update') {
            $existing_fee_types_stmt = $conn->prepare("
                SELECT DISTINCT fee_type_id 
                FROM student_fee_card sfc
                INNER JOIN student_class sc ON sfc.student_class_id = sc.id
                WHERE sfc.session_id = ? AND sc.class_id = ? AND sfc.due_date = ?
            ");
            $existing_fee_types_stmt->bind_param("iis", $session_id, $class_id, $due_date);
            $existing_fee_types_stmt->execute();
            $existing_result = $existing_fee_types_stmt->get_result();
            $all_existing_fee_types = array();
            while ($row = $existing_result->fetch_assoc()) {
                $all_existing_fee_types[] = $row['fee_type_id'];
            }
            $existing_fee_types_stmt->close();
            $existing_result->free();

            $update_stmt = $conn->prepare("
                UPDATE student_fee_card 
                SET total_amount = ?, remarks = ?
                WHERE student_class_id = ? AND fee_type_id = ? AND due_date = ? AND session_id = ?
            ");

            $insert_stmt = $conn->prepare("
                INSERT INTO student_fee_card 
                (student_class_id, fee_type_id, total_amount, due_date, session_id, remarks)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $check_stmt = $conn->prepare("
                SELECT id FROM student_fee_card 
                WHERE student_class_id = ? AND fee_type_id = ? AND due_date = ? AND session_id = ?
            ");

            $delete_stmt = $conn->prepare("
                DELETE FROM student_fee_card 
                WHERE student_class_id = ? AND fee_type_id = ? AND due_date = ? AND session_id = ?
            ");

            foreach ($students as $student) {
                $student_class_id = $student['student_class_id'];
                
                $student_fee_stmt = $conn->prepare("
                    SELECT fee_type_id, amount 
                    FROM student_fee_assignments 
                    WHERE student_id = ? AND status = 1
                ");
                $student_fee_stmt->bind_param("i", $student['student_registration_id']);
                $student_fee_stmt->execute();
                $student_fees_result = $student_fee_stmt->get_result();
                $student_fees = array();
                while ($fee_row = $student_fees_result->fetch_assoc()) {
                    $student_fees[$fee_row['fee_type_id']] = $fee_row['amount'];
                }
                $student_fee_stmt->close();
                $student_fees_result->free();
                
                foreach ($fee_structures as $fee) {
                    $fee_type_id = $fee['fee_type_id'];
                    $amount = isset($student_fees[$fee_type_id]) ? $student_fees[$fee_type_id] : $fee['amount'];
                    $remarks = $fee['title'] . " fee for $month_name $year (Updated)";

                    $check_stmt->bind_param("iiss", $student_class_id, $fee_type_id, $due_date, $session_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $update_stmt->bind_param("dsiiss", $amount, $remarks, $student_class_id, $fee_type_id, $due_date, $session_id);
                        if ($update_stmt->execute()) {
                            $record_count++;
                        }
                    } else {
                        $insert_stmt->bind_param("iidssi", $student_class_id, $fee_type_id, $amount, $due_date, $session_id, $remarks);
                        if ($insert_stmt->execute()) {
                            $record_count++;
                        }
                    }
                    $check_result->free();
                }
                
                foreach ($all_existing_fee_types as $existing_fee_type_id) {
                    if (!in_array($existing_fee_type_id, $selected_fee_types)) {
                        $delete_stmt->bind_param("iiss", $student_class_id, $existing_fee_type_id, $due_date, $session_id);
                        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
                            $delete_count++;
                        }
                    }
                }
            }

            $update_stmt->close();
            $insert_stmt->close();
            $check_stmt->close();
            $delete_stmt->close();

            $message = $translations[$lang]['update_success'];
            if ($record_count > 0) {
                $message .= " " . $translations[$lang]['records_updated'] . " $record_count " . $translations[$lang]['records'] . ".";
            }
            if ($delete_count > 0) {
                $message .= " " . $translations[$lang]['records_removed'] . " $delete_count " . $translations[$lang]['records'] . ".";
            }
            
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = 'success';
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?session_id=$session_id&class_id=$class_id&month=$month&lang=" . $lang . "#class-$class_id");
        exit();
    } else {
        $_SESSION['message'] = $translations[$lang]['invalid_selection'];
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF'] . "?lang=" . $lang);
        exit();
    }
}

// Get parameters from URL
$selected_session = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$selected_class = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_month = isset($_GET['month']) ? $_GET['month'] : '';
$selected_year = date('Y');
$selected_due_date = $selected_month ? date('Y-m-t', strtotime("$selected_year-$selected_month-01")) : '';

// Get session title and class title for display
$session_title = '';
if ($selected_session && isset($sessions[$selected_session])) {
    $session_title = $sessions[$selected_session];
}
$class_title = '';
if ($selected_class && isset($classes[$selected_class])) {
    $class_title = $classes[$selected_class];
}

// Validate session selection
$session_error = '';
if (isset($_GET['session_id']) && $_GET['session_id'] == '') {
    $session_error = $translations[$lang]['select_session_first'];
}

// Fetch available fee types
$available_fee_types = array();
if ($selected_session && $selected_class) {
    $stmt = $conn->prepare("
        SELECT ft.id AS fee_type_id, ft.title, cft.amount, 'class' as source
        FROM class_fee_types cft
        INNER JOIN fee_types ft ON cft.fee_type_id = ft.id
        WHERE cft.session_id = ? AND cft.class_id = ? AND cft.status = 0
        ORDER BY ft.title
    ");
    $stmt->bind_param("ii", $selected_session, $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_fee_types[$row['fee_type_id']] = $row;
    }
    $stmt->close();
    $result->free();
    
    $stmt = $conn->prepare("
        SELECT DISTINCT ft.id AS fee_type_id, ft.title, 0 as amount, 'student' as source
        FROM student_fee_assignments sfa
        INNER JOIN student_registration sr ON sfa.student_id = sr.id
        INNER JOIN student_class sc ON sr.id = sc.student_registration_id
        INNER JOIN fee_types ft ON sfa.fee_type_id = ft.id
        WHERE sc.session_id = ? AND sc.class_id = ? 
          AND sc.status = 0 AND sfa.status = 0 AND sr.status = 0
          AND ft.id NOT IN (
              SELECT fee_type_id FROM class_fee_types 
              WHERE session_id = ? AND class_id = ? AND status = 0
          )
        ORDER BY ft.title
    ");
    $stmt->bind_param("iiii", $selected_session, $selected_class, $selected_session, $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $available_fee_types[$row['fee_type_id']] = $row;
    }
    $stmt->close();
    $result->free();
    
    $available_fee_types = array_values($available_fee_types);
}

// Fetch existing fee records
$fee_records = array();
$existing_fee_amounts = array();
$has_existing_records = false;
$old_dues_data = array();
$existing_fee_types_in_use = array();

if ($selected_session && $selected_class && $selected_due_date) {
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM student_fee_card sfc
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sfc.session_id = ? AND sc.class_id = ? AND sfc.due_date = ? AND sr.status = 0
    ");
    $check_stmt->bind_param("iis", $selected_session, $selected_class, $selected_due_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count_row = $check_result->fetch_assoc();
    $has_existing_records = ($count_row['count'] > 0);
    $check_stmt->close();
    $check_result->free();
    
    $type_check_stmt = $conn->prepare("
        SELECT DISTINCT sfc.fee_type_id
        FROM student_fee_card sfc
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sfc.session_id = ? AND sc.class_id = ? AND sfc.due_date = ? AND sr.status = 0
    ");
    $type_check_stmt->bind_param("iis", $selected_session, $selected_class, $selected_due_date);
    $type_check_stmt->execute();
    $type_check_result = $type_check_stmt->get_result();
    while ($row = $type_check_result->fetch_assoc()) {
        $existing_fee_types_in_use[] = $row['fee_type_id'];
    }
    $type_check_stmt->close();
    $type_check_result->free();
    
    $amount_stmt = $conn->prepare("
        SELECT DISTINCT sfc.fee_type_id, sfc.total_amount, ft.title
        FROM student_fee_card sfc
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sfc.session_id = ? AND sc.class_id = ? AND sfc.due_date = ? AND sr.status = 0
    ");
    $amount_stmt->bind_param("iis", $selected_session, $selected_class, $selected_due_date);
    $amount_stmt->execute();
    $amount_result = $amount_stmt->get_result();
    while ($row = $amount_result->fetch_assoc()) {
        $existing_fee_amounts[$row['fee_type_id']] = array(
            'amount' => $row['total_amount'],
            'title' => $row['title']
        );
    }
    $amount_stmt->close();
    $amount_result->free();

    $stmt = $conn->prepare("
        SELECT sfc.id as fee_card_id, sfc.total_amount, sfc.due_date, sfc.remarks, sfc.fee_type_id,
               sr.id AS student_registration_id, sr.name AS student_name, 
               sr.father_name, sr.mobile,
               sc.id AS student_class_id,
               c.title AS class_title, ft.title AS fee_type_title
        FROM student_fee_card sfc
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        INNER JOIN classes c ON sc.class_id = c.id
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
        WHERE sfc.session_id = ? AND sc.class_id = ? AND sfc.due_date = ? AND sr.status = 0
        ORDER BY c.title, sr.name, ft.title
    ");
    $stmt->bind_param("iis", $selected_session, $selected_class, $selected_due_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $class_key = $row['class_title'];
        $student_key = $row['student_name'] . '_' . $row['student_registration_id'];

        if (!isset($fee_records[$class_key])) {
            $fee_records[$class_key] = array('class_title' => $class_key, 'students' => array());
        }
        if (!isset($fee_records[$class_key]['students'][$student_key])) {
            $fee_records[$class_key]['students'][$student_key] = array(
                'student_registration_id' => $row['student_registration_id'],
                'student_name' => $row['student_name'],
                'father_name' => $row['father_name'],
                'mobile' => $row['mobile'],
                'student_class_id' => $row['student_class_id'],
                'fees' => array()
            );
        }
        $fee_records[$class_key]['students'][$student_key]['fees'][] = array(
            'fee_card_id' => $row['fee_card_id'],
            'fee_type_id' => $row['fee_type_id'],
            'fee_type_title' => $row['fee_type_title'],
            'total_amount' => $row['total_amount'],
            'due_date' => $row['due_date'],
            'remarks' => $row['remarks'],
            'month' => date('F Y', strtotime($row['due_date']))
        );
    }
    $stmt->close();
    $result->free();
    
    foreach ($fee_records as $class_key => $class_data) {
        foreach ($class_data['students'] as $student_key => $student_data) {
            $student_class_id = $student_data['student_class_id'];
            
            $old_dues_stmt = $conn->prepare("
                SELECT SUM(sfc.total_amount) as total_old_dues
                FROM student_fee_card sfc
                WHERE sfc.student_class_id = ? AND sfc.due_date < ? AND sfc.session_id = ?
            ");
            $old_dues_stmt->bind_param("isi", $student_class_id, $selected_due_date, $selected_session);
            $old_dues_stmt->execute();
            $old_dues_result = $old_dues_stmt->get_result();
            $old_dues_row = $old_dues_result->fetch_assoc();
            $old_dues_total = isset($old_dues_row['total_old_dues']) ? $old_dues_row['total_old_dues'] : 0;
            $old_dues_stmt->close();
            $old_dues_result->free();
            
            $old_dues_data[$student_data['student_registration_id']] = $old_dues_total;
        }
    }
}

$months = array(
    '01' => $translations[$lang]['january'], '02' => $translations[$lang]['february'],
    '03' => $translations[$lang]['march'], '04' => $translations[$lang]['april'],
    '05' => $translations[$lang]['may'], '06' => $translations[$lang]['june'],
    '07' => $translations[$lang]['july'], '08' => $translations[$lang]['august'],
    '09' => $translations[$lang]['september'], '10' => $translations[$lang]['october'],
    '11' => $translations[$lang]['november'], '12' => $translations[$lang]['december']
);

$selected_month_name = '';
if ($selected_month && isset($months[$selected_month])) {
    $selected_month_name = $months[$selected_month];
}
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <title><?php echo $translations[$lang]['title']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* RTL Support */
        <?php if ($lang == 'ur'): ?>
        body, .form-control, .btn, .alert, .panel-title, .table th, .table td {
            text-align: right;
            direction: rtl;
        }
        .dropdown-menu {
            text-align: right;
            left: auto;
            right: 0;
        }
        .checkbox label {
            padding-right: 20px;
        }
        .modal-header .close {
            float: left;
        }
        <?php else: ?>
        .table th, .table td {
            text-align: left;
        }
        .checkbox label {
            padding-left: 20px;
        }
        <?php endif; ?>
        
        /* Form Controls */
        .form-control {
            font-size: 16px !important;
            padding: 10px 12px !important;
            height: auto !important;
        }
        
        select.form-control {
            font-size: 16px !important;
            padding: 10px 12px !important;
        }
        
        /* Note Styles */
        .update-note, .duplicate-info, .amount-zero-note, .session-error, .text-muted {
            font-size: 14px !important;
        }
        
        .update-note {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 12px;
            margin-bottom: 15px;
        }
        
        <?php if ($lang == 'ur'): ?>
        .update-note {
            border-right: 4px solid #007bff;
            border-left: none;
        }
        <?php endif; ?>
        
        .duplicate-info {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .amount-zero-note {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 5px;
        }
        
        .session-error {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        /* Button row styling */
        .action-buttons-row {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 25px 0;
            flex-wrap: wrap;
        }
        
        .action-buttons-row .btn {
            padding: 10px 20px !important;
            font-size: 15px !important;
            font-weight: bold !important;
            min-width: 160px;
        }
        
        @media (max-width: 576px) {
            .action-buttons-row {
                flex-direction: column;
                align-items: stretch;
            }
            .action-buttons-row .btn {
                width: 100%;
                min-width: auto;
            }
        }
        
        /* Panel Styles */
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
        
        @media (max-width: 576px) {
            .panel-info .panel-heading h3 {
                flex-direction: column;
                text-align: center;
            }
        }
        
        .panel-info .panel-heading .month-badge {
            background-color: #337ab7;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: normal;
        }
        
        /* Class Header */
        .class-header {
            margin-top: 20px;
            margin-bottom: 15px;
            padding: 12px 15px;
            background-color: #337ab7;
            color: white;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            scroll-margin-top: 20px;
        }
        
        .class-header span:first-child {
            font-size: 16px;
            font-weight: bold;
        }
        
        .class-header .badge {
            background-color: #fff;
            color: #337ab7;
            padding: 5px 10px;
            font-size: 13px;
        }
        
        /* Desktop Table View - Hidden on mobile */
        .desktop-table-view {
            display: block;
        }
        
        /* Mobile Card View - Visible on mobile */
        .mobile-card-view {
            display: none;
        }
        
        /* Table styling for desktop */
        .table {
            background-color: #ffffff !important;
            margin-bottom: 20px;
        }
        
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
            padding: 10px 8px;
            border-bottom: 2px solid #ddd;
        }
        
        .table tbody td {
            padding: 10px 8px;
            vertical-align: middle;
            font-size: 13px;
        }
        
        .table tbody tr.student-row td {
            background-color: #f9f9f9 !important;
            font-weight: 500;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .table tbody tr.fee-detail-row td {
            border-top: none !important;
            padding-left: 30px;
            background-color: #ffffff !important;
        }
        
        .table-hover tbody tr:hover td {
            background-color: #f5f5f5 !important;
        }
        
        /* Mobile Card View Styles */
        .fee-cards-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .fee-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
        }
        
        .card-student-header {
            background: #ffffff;
            color: black;
            padding: 15px;
        }
        
        .student-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .student-id {
            font-size: 11px;
            color: #fff;
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 8px;
        }
        
        .student-details {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .student-details span {
            display: inline-block;
            margin-right: 12px;
        }
        
        .student-details i {
            margin-right: 3px;
        }
        
        .card-fee-body {
            padding: 12px;
        }
        
        .fee-row {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        
        .fee-row:last-child {
            margin-bottom: 0;
        }
        
        .fee-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .fee-type-name {
            font-weight: bold;
            font-size: 14px;
            color: #333;
        }
        
        .fee-amount {
            font-weight: bold;
            font-size: 14px;
            color: #28a745;
        }
        
        .fee-details {
            font-size: 11px;
            color: #666;
            margin-bottom: 10px;
            padding-top: 5px;
            border-top: 1px dashed #dee2e6;
        }
        
        .fee-details div {
            margin-bottom: 3px;
        }
        
        .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        
        .card-actions .btn {
            padding: 5px 10px !important;
            font-size: 11px !important;
            border-radius: 4px;
            flex: 1;
            text-align: center;
        }
        
        .old-dues-card {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 6px;
            padding: 10px;
            margin-top: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .old-dues-label {
            font-weight: bold;
            color: #856404;
            font-size: 12px;
        }
        
        .old-dues-amount {
            font-weight: bold;
            color: #d9534f;
            font-size: 14px;
        }
        
        .print-slip-btn {
            width: 100%;
            margin-top: 10px;
            padding: 10px !important;
            font-size: 13px !important;
        }
        
        /* Action buttons for desktop table */
        .action-btns {
            white-space: nowrap;
        }
        
        .action-btns .btn {
            margin: 0 2px;
            padding: 4px 8px !important;
            font-size: 11px !important;
            border-radius: 4px;
        }
        
        .fee-amount {
            font-weight: bold;
            color: #28a745;
        }
        
        /* Bootstrap Color Buttons */
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #fff;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            color: #fff;
        }
        
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: #fff;
        }
        
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
            color: #fff;
        }
        
        /* Responsive Breakpoint - Mobile first approach */
        @media (max-width: 767px) {
            .desktop-table-view {
                display: none !important;
            }
            .mobile-card-view {
                display: block !important;
            }
        }
        
        @media (min-width: 768px) {
            .desktop-table-view {
                display: block !important;
            }
            .mobile-card-view {
                display: none !important;
            }
        }
        
        /* Checkbox List */
        .checkbox-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .fee-type-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 4px;
            border: 1px solid #ddd;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .fee-type-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        .fee-type-checkbox {
            margin-right: 15px;
        }
        
        <?php if ($lang == 'ur'): ?>
        .fee-type-checkbox {
            margin-left: 15px;
            margin-right: 0;
        }
        @media (max-width: 768px) {
            .fee-type-checkbox {
                margin-bottom: 10px;
            }
        }
        <?php endif; ?>
        
        .fee-type-details {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .fee-type-details {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        .fee-type-title {
            font-weight: bold;
            min-width: 180px;
            font-size: 14px;
        }
        
        .fee-type-amount-input {
            width: 150px;
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-align: right;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .fee-type-amount-input {
                width: 100%;
            }
        }
        
        .original-amount, .existing-amount {
            font-size: 12px;
            margin-left: 10px;
        }
        
        .original-amount {
            color: #666;
        }
        
        .existing-amount {
            color: #28a745;
            font-weight: bold;
        }
        
        /* Source badge styles */
        .source-badge {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 8px;
            font-weight: normal;
        }
        
        .source-class {
            color: #0052cc;
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
        }
        
        .source-student {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
        }
        
        /* Custom Modal Styles */
        .custom-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        
        .custom-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 0;
            border: 1px solid #888;
            width: 500px;
            max-width: 90%;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 576px) {
            .custom-modal-content {
                margin: 25% auto;
                width: 95%;
            }
        }
        
        .custom-modal-header {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-bottom: 2px solid #337ab7;
            border-radius: 6px 6px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .custom-modal-header.delete-modal-header {
            background-color: #f2dede;
            border-bottom-color: #d43f3a;
        }
        
        .custom-modal-header.delete-modal-header .custom-modal-title {
            color: #d43f3a;
        }
        
        .custom-modal-title {
            margin: 0;
            font-weight: bold;
            font-size: 18px;
        }
        
        .custom-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .custom-close:hover {
            color: #000;
        }
        
        .custom-modal-body {
            padding: 20px;
        }
        
        .custom-modal-footer {
            padding: 15px 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e5e5e5;
            border-radius: 0 0 6px 6px;
            text-align: right;
        }
        
        .custom-modal-footer button {
            margin-left: 10px;
            padding: 6px 12px;
            border-radius: 4px;
        }
        
        <?php if ($lang == 'ur'): ?>
        .custom-modal-footer {
            text-align: left;
        }
        .custom-modal-footer button {
            margin-right: 10px;
            margin-left: 0;
        }
        <?php endif; ?>
        
        @media (max-width: 576px) {
            .custom-modal-footer {
                display: flex;
                flex-direction: column-reverse;
                gap: 10px;
            }
            .custom-modal-footer button {
                margin: 0;
                width: 100%;
            }
        }
        
        /* Language Switcher */
        .language-switcher {
            text-align: right;
            margin-bottom: 15px;
            
        }
        
        .language-switcher .btn {
            font-size: 14px !important;
            padding: 5px 12px !important;
            border-radius: 4px;
           
        }
        .active {
            background-color: #337ab7 !important;
            border-color: #337ab7 !important;
            color: white !important;
        }
        @media (max-width: 576px) {
            .language-switcher {
            
            }
        }
        
        /* Print Styles */
        @media print {
            .no-print, .navbar, .action-buttons-row, .language-switcher, .action-btns, .btn, .custom-modal, .mobile-card-view {
                display: none !important;
            }
            .desktop-table-view {
                display: block !important;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<!-- Language switcher -->
<div class="container language-switcher no-print">
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

<div class="container">
    <div class="row">
        <div class="col-md-12">
            
            <!-- Panel for Fee Card Management -->
            <div class="panel panel-primary no-print">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $translations[$lang]['title']; ?></h3>
                </div>
                <div class="panel-body">
                    
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
                            <a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>
                            <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message'], $_SESSION['message_type']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($session_error): ?>
                        <div class="session-error">
                            <span class="glyphicon glyphicon-exclamation-sign"></span> 
                            <?php echo $session_error; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="fee-card-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" id="form-action" value="create">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $translations[$lang]['session_label']; ?> <span class="text-danger">*</span></label>
                                    <select name="session_id" id="session_id" class="form-control" required>
                                        <option value=""><?php echo $translations[$lang]['select_session']; ?></option>
                                        <?php foreach ($sessions as $id => $title): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ($id == $selected_session) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $translations[$lang]['month_label']; ?> <span class="text-danger">*</span></label>
                                    <select name="month" id="month" class="form-control" required>
                                        <option value=""><?php echo $translations[$lang]['select_month']; ?></option>
                                        <?php foreach ($months as $value => $label): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($value == $selected_month) ? 'selected' : ''; ?>>
                                                <?php echo $label; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label><?php echo $translations[$lang]['class_label']; ?> <span class="text-danger">*</span></label>
                                    <select name="class_id" id="class_id" class="form-control" required>
                                        <option value=""><?php echo $translations[$lang]['select_class']; ?></option>
                                        <?php foreach ($classes as $id => $title): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ($id == $selected_class) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted"><?php echo ($lang == 'ur') ? 'فیس ٹائپس لوڈ کرنے کے لیے کلاس منتخب کریں' : 'Select class to load fee types'; ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($selected_session && $selected_class && $selected_month): ?>
                            <?php if (!empty($available_fee_types)): ?>
                                <?php if ($has_existing_records): ?>
                                    
                                <?php endif; ?>
                                
                               
                                
                                <div class="form-group">
                                    <label style="font-size: 16px;"><?php echo $translations[$lang]['fee_types_label']; ?></label>
                                    <div class="checkbox-list">
                                        <?php foreach ($available_fee_types as $ft): 
                                            $show_amount = $ft['amount'];
                                            $existing_note = '';
                                            
                                            if (isset($existing_fee_amounts[$ft['fee_type_id']])) {
                                                $show_amount = $existing_fee_amounts[$ft['fee_type_id']]['amount'];
                                                $existing_note = $translations[$lang]['existing_amount'] . ': PKR ' . number_format($show_amount, 2);
                                            }
                                            
                                            $is_checked = false;
                                            if ($has_existing_records) {
                                                $is_checked = in_array($ft['fee_type_id'], $existing_fee_types_in_use);
                                            } else {
                                                $is_checked = true;
                                            }
                                            
                                            $source_badge = ($ft['source'] == 'class') ? 
                                                '<span class="source-badge source-class">' . $translations[$lang]['class_fee'] . '</span>' : 
                                                '<span class="source-badge source-student">' . $translations[$lang]['student_assigned'] . '</span>';
                                        ?>
                                            <div class="fee-type-item">
                                                <div class="fee-type-checkbox">
                                                    <input type="checkbox" 
                                                           name="fee_types[]" 
                                                           value="<?php echo $ft['fee_type_id']; ?>" 
                                                           class="fee-type-checkbox-input"
                                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                                           data-fee-id="<?php echo $ft['fee_type_id']; ?>"
                                                           data-original-amount="<?php echo $ft['amount']; ?>"
                                                           data-existing-amount="<?php echo isset($existing_fee_amounts[$ft['fee_type_id']]) ? $existing_fee_amounts[$ft['fee_type_id']]['amount'] : ''; ?>">
                                                </div>
                                                <div class="fee-type-details">
                                                    <div class="fee-type-title">
                                                        <?php echo htmlspecialchars($ft['title']); ?>
                                                        <?php echo $source_badge; ?>
                                                        <input type="hidden" name="fee_titles[<?php echo $ft['fee_type_id']; ?>]" value="<?php echo htmlspecialchars($ft['title']); ?>">
                                                    </div>
                                                    <div>
                                                        <input type="number" 
                                                               name="fee_amounts[<?php echo $ft['fee_type_id']; ?>]" 
                                                               class="fee-type-amount-input"
                                                               value="<?php echo number_format($show_amount, 2, '.', ''); ?>"
                                                               
                                                               min="0"
                                                               required>
                                                        <?php if (isset($existing_fee_amounts[$ft['fee_type_id']])): ?>
                                                            <span class="existing-amount">
                                                                (<?php echo $existing_note; ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="original-amount">
                                                                (<?php echo $translations[$lang]['original_amount']; ?>: PKR <?php echo number_format($ft['amount'], 2); ?>)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="amount-zero-note">
                                        <span class="glyphicon glyphicon-info-sign"></span> 
                                        <?php echo $translations[$lang]['zero_amount_note']; ?>
                                    </div>
                                </div>

                                <div class="action-buttons-row">
                                    <?php if (!$has_existing_records): ?>
                                        <button type="button" id="create-btn" class="btn btn-success">
                                            <span class="glyphicon glyphicon-plus"></span> 
                                            <?php echo $translations[$lang]['generate_btn']; ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_existing_records): ?>
                                        <button type="button" id="update-btn" class="btn btn-warning">
                                            <span class="glyphicon glyphicon-refresh"></span> 
                                            <?php echo $translations[$lang]['update_btn']; ?>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" id="view-btn" class="btn btn-info">
                                        <span class="glyphicon glyphicon-eye-open"></span> 
                                        <?php echo $translations[$lang]['view_btn']; ?>
                                    </button>
                                    
                                    <?php if (!empty($fee_records)): ?>
                                        <button type="button" id="print-all-btn" class="btn btn-primary" onclick="printAllSlips()">
                                            <span class="glyphicon glyphicon-print"></span> 
                                            <?php echo $translations[$lang]['print_btn']; ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <?php echo $translations[$lang]['no_fee_types']; ?>
                                    <p><small><?php echo $translations[$lang]['define_fee_types']; ?></small></p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Panel for Fee Card Records -->
            <div class="panel panel-info no-print">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?php echo $translations[$lang]['fee_records_title']; ?>
                        <?php if ($selected_month_name): ?>
                            <span class="month-badge">
                                <span class="glyphicon glyphicon-calendar"></span> 
                                <?php echo $selected_month_name . ' ' . $selected_year; ?>
                            </span>
                        <?php endif; ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if (!empty($fee_records)): ?>
                        <?php foreach ($fee_records as $class_section_key => $class_section_data): ?>
                            <div class="class-header" id="class-<?php echo $selected_class; ?>">
                                <span><i class="glyphicon glyphicon-education"></i> <?php echo htmlspecialchars($class_section_data['class_title']); ?></span>
                                <span class="badge"><?php echo count($class_section_data['students']); ?> <?php echo $translations[$lang]['student']; ?></span>
                            </div>
                            
                            <!-- DESKTOP TABLE VIEW -->
                            <div class="desktop-table-view">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            </td>
                                                <th><?php echo $translations[$lang]['sr_no']; ?></th>
                                                <th><?php echo $translations[$lang]['student_name']; ?></th>
                                                <th><?php echo $translations[$lang]['father_name']; ?></th>
                                                <th><?php echo $translations[$lang]['fee_type_title']; ?></th>
                                                <th><?php echo $translations[$lang]['amount']; ?></th>
                                                <th><?php echo $translations[$lang]['due_date']; ?></th>
                                                <th><?php echo $translations[$lang]['remarks']; ?></th>
                                                <th class="no-print"><?php echo $translations[$lang]['actions']; ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; 
                                            foreach ($class_section_data['students'] as $student_key => $student_data): 
                                                $student_fees = $student_data['fees'];
                                                $rowspan = count($student_fees);
                                                $first_row = true;
                                                $old_dues = isset($old_dues_data[$student_data['student_registration_id']]) ? $old_dues_data[$student_data['student_registration_id']] : 0;
                                            ?>
                                                <?php foreach ($student_fees as $index => $fee): ?>
                                                    <tr class="<?php echo $first_row ? 'student-row' : 'fee-detail-row'; ?>">
                                                        <?php if ($first_row): ?>
                                                            <td rowspan="<?php echo $rowspan; ?>"><?php echo $counter++; ?> </td>
                                                            <td rowspan="<?php echo $rowspan; ?>">
                                                                <strong><?php echo htmlspecialchars($student_data['student_name']); ?></strong><br>
                                                                <small class="text-muted">ID: <?php echo htmlspecialchars($student_data['student_registration_id']); ?></small>
                                                            </td>
                                                            <td rowspan="<?php echo $rowspan; ?>">
                                                                <?php echo htmlspecialchars($student_data['father_name']); ?><br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($student_data['mobile']); ?></small>
                                                            </td>
                                                        <?php endif; ?>
                                                        <td><?php echo htmlspecialchars($fee['fee_type_title']); ?></td>
                                                        <td class="fee-amount">PKR <?php echo number_format($fee['total_amount'], 2); ?></td>
                                                        <td><?php echo htmlspecialchars($fee['due_date']); ?></td>
                                                        <td><?php echo htmlspecialchars($fee['remarks']); ?></td>
                                                        <td class="no-print action-btns">
                                                            <button type="button" class="btn btn-xs btn-warning" onclick="openEditModal(<?php echo $fee['fee_card_id']; ?>, <?php echo $fee['total_amount']; ?>, '<?php echo htmlspecialchars(addslashes($fee['remarks'])); ?>', <?php echo $selected_session; ?>, <?php echo $selected_class; ?>, '<?php echo $selected_month; ?>')">
                                                                <span class="glyphicon glyphicon-pencil"></span> <?php echo $translations[$lang]['edit']; ?>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-danger" onclick="openDeleteModal(<?php echo $fee['fee_card_id']; ?>, '<?php echo htmlspecialchars(addslashes($fee['fee_type_title'])); ?>', '<?php echo htmlspecialchars(addslashes($student_data['student_name'])); ?>', <?php echo $selected_session; ?>, <?php echo $selected_class; ?>, '<?php echo $selected_month; ?>')">
                                                                <span class="glyphicon glyphicon-trash"></span> <?php echo $translations[$lang]['delete']; ?>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-info" onclick='printThermalSlip(
                                                                <?php echo json_encode($student_data['student_registration_id']); ?>, 
                                                                <?php echo json_encode($student_data['student_name']); ?>, 
                                                                <?php echo json_encode($student_data['father_name']); ?>, 
                                                                <?php echo json_encode($student_data['mobile']); ?>, 
                                                                <?php echo json_encode($student_fees); ?>, 
                                                                <?php echo json_encode($selected_month_name); ?>, 
                                                                <?php echo json_encode($selected_year); ?>,
                                                                <?php echo json_encode($session_title); ?>,
                                                                <?php echo json_encode($class_title); ?>,
                                                                <?php echo json_encode($old_dues); ?>
                                                            )'>
                                                                <span class="glyphicon glyphicon-print"></span> <?php echo $translations[$lang]['print_slip_btn']; ?>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    <?php $first_row = false; ?>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- MOBILE CARD VIEW -->
                            <div class="mobile-card-view">
                                <div class="fee-cards-container">
                                    <?php $counter = 1;
                                    foreach ($class_section_data['students'] as $student_key => $student_data): 
                                        $student_fees = $student_data['fees'];
                                        $old_dues = isset($old_dues_data[$student_data['student_registration_id']]) ? $old_dues_data[$student_data['student_registration_id']] : 0;
                                    ?>
                                        <div class="fee-card">
                                            <div class="card-student-header">
                                                <div class="student-name">
                                                    <?php echo $counter++; ?>. <?php echo htmlspecialchars($student_data['student_name']); ?>
                                                    <span class="student-id">ID: <?php echo htmlspecialchars($student_data['student_registration_id']); ?></span>
                                                </div>
                                                <div class="student-details">
                                                    <span><i class="glyphicon glyphicon-user"></i> <?php echo htmlspecialchars($student_data['father_name']); ?></span>
                                                    <span><i class="glyphicon glyphicon-phone"></i> <?php echo htmlspecialchars($student_data['mobile']); ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="card-fee-body">
                                                <?php foreach ($student_fees as $fee): ?>
                                                    <div class="fee-row">
                                                        <div class="fee-header">
                                                            <span class="fee-type-name"><?php echo htmlspecialchars($fee['fee_type_title']); ?></span>
                                                            <span class="fee-amount">PKR <?php echo number_format($fee['total_amount'], 2); ?></span>
                                                        </div>
                                                        <div class="fee-details">
                                                            <div><i class="glyphicon glyphicon-calendar"></i> <?php echo $translations[$lang]['due_date']; ?>: <?php echo htmlspecialchars($fee['due_date']); ?></div>
                                                            <?php if (!empty($fee['remarks'])): ?>
                                                                <div><i class="glyphicon glyphicon-comment"></i> <?php echo htmlspecialchars($fee['remarks']); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-actions">
                                                            <button type="button" class="btn btn-xs btn-warning" onclick="openEditModal(<?php echo $fee['fee_card_id']; ?>, <?php echo $fee['total_amount']; ?>, '<?php echo htmlspecialchars(addslashes($fee['remarks'])); ?>', <?php echo $selected_session; ?>, <?php echo $selected_class; ?>, '<?php echo $selected_month; ?>')">
                                                                <span class="glyphicon glyphicon-pencil"></span> <?php echo $translations[$lang]['edit']; ?>
                                                            </button>
                                                            <button type="button" class="btn btn-xs btn-danger" onclick="openDeleteModal(<?php echo $fee['fee_card_id']; ?>, '<?php echo htmlspecialchars(addslashes($fee['fee_type_title'])); ?>', '<?php echo htmlspecialchars(addslashes($student_data['student_name'])); ?>', <?php echo $selected_session; ?>, <?php echo $selected_class; ?>, '<?php echo $selected_month; ?>')">
                                                                <span class="glyphicon glyphicon-trash"></span> <?php echo $translations[$lang]['delete']; ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                
                                                
                                                <button type="button" class="btn btn-info print-slip-btn" onclick='printThermalSlip(
                                                    <?php echo json_encode($student_data['student_registration_id']); ?>, 
                                                    <?php echo json_encode($student_data['student_name']); ?>, 
                                                    <?php echo json_encode($student_data['father_name']); ?>, 
                                                    <?php echo json_encode($student_data['mobile']); ?>, 
                                                    <?php echo json_encode($student_fees); ?>, 
                                                    <?php echo json_encode($selected_month_name); ?>, 
                                                    <?php echo json_encode($selected_year); ?>,
                                                    <?php echo json_encode($session_title); ?>,
                                                    <?php echo json_encode($class_title); ?>,
                                                    <?php echo json_encode($old_dues); ?>
                                                )'>
                                                    <span class="glyphicon glyphicon-print"></span> <?php echo $translations[$lang]['print_slip_btn']; ?>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php if ($selected_session && $selected_class && $selected_month): ?>
                            <div class="alert alert-info text-center"><?php echo $translations[$lang]['no_records']; ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Edit Modal -->
<div id="customEditModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header">
            <h4 class="custom-modal-title"><?php echo $translations[$lang]['edit_fee_title']; ?></h4>
            <span class="custom-close" onclick="closeEditModal()">&times;</span>
        </div>
        <form method="post" action="" id="editFeeForm">
            <div class="custom-modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action_type" value="edit_fee">
                <input type="hidden" name="fee_card_id" id="edit_fee_card_id" value="">
                <input type="hidden" name="session_id" id="edit_session_id" value="">
                <input type="hidden" name="class_id" id="edit_class_id" value="">
                <input type="hidden" name="month" id="edit_month" value="">
                <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                
                <div class="form-group">
                    <label><?php echo $translations[$lang]['amount_label']; ?></label>
                    <input type="number" name="amount" id="edit_amount" class="form-control"  min="0" required>
                    <small class="text-muted"><?php echo $translations[$lang]['amount_note']; ?></small>
                </div>
                
                <div class="form-group">
                    <label><?php echo $translations[$lang]['remarks_label']; ?></label>
                    <textarea name="remarks" id="edit_remarks" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-default" onclick="closeEditModal()"><?php echo $translations[$lang]['cancel']; ?></button>
                <button type="submit" class="btn btn-primary"><?php echo $translations[$lang]['update']; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Delete Modal -->
<div id="customDeleteModal" class="custom-modal">
    <div class="custom-modal-content">
        <div class="custom-modal-header delete-modal-header">
            <h4 class="custom-modal-title"><?php echo $translations[$lang]['delete_confirm_title']; ?></h4>
            <span class="custom-close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <form method="post" action="" id="deleteFeeForm">
            <div class="custom-modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action_type" value="delete_fee">
                <input type="hidden" name="fee_card_id" id="delete_fee_card_id" value="">
                <input type="hidden" name="session_id" id="delete_session_id" value="">
                <input type="hidden" name="class_id" id="delete_class_id" value="">
                <input type="hidden" name="month" id="delete_month" value="">
                <input type="hidden" name="lang" value="<?php echo $lang; ?>">
                
                <p><?php echo $translations[$lang]['delete_confirmation']; ?></p>
                <p><strong><?php echo $translations[$lang]['student']; ?>:</strong> <span id="delete_student_name"></span></p>
                <p><strong><?php echo $translations[$lang]['fee_type']; ?>:</strong> <span id="delete_fee_type"></span></p>
                <p class="text-danger"><small><?php echo $translations[$lang]['delete_warning']; ?></small></p>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-default" onclick="closeDeleteModal()"><?php echo $translations[$lang]['cancel']; ?></button>
                <button type="submit" class="btn btn-danger"><?php echo $translations[$lang]['delete']; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-dismiss alerts
setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);

// Custom Modal Functions
function openEditModal(feeCardId, amount, remarks, sessionId, classId, month) {
    document.getElementById('edit_fee_card_id').value = feeCardId;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_remarks').value = remarks;
    document.getElementById('edit_session_id').value = sessionId;
    document.getElementById('edit_class_id').value = classId;
    document.getElementById('edit_month').value = month;
    document.getElementById('customEditModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    document.getElementById('customEditModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function openDeleteModal(feeCardId, feeType, studentName, sessionId, classId, month) {
    document.getElementById('delete_fee_card_id').value = feeCardId;
    document.getElementById('delete_fee_type').innerText = feeType;
    document.getElementById('delete_student_name').innerText = studentName;
    document.getElementById('delete_session_id').value = sessionId;
    document.getElementById('delete_class_id').value = classId;
    document.getElementById('delete_month').value = month;
    document.getElementById('customDeleteModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('customDeleteModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

window.onclick = function(event) {
    var editModal = document.getElementById('customEditModal');
    var deleteModal = document.getElementById('customDeleteModal');
    if (event.target == editModal) closeEditModal();
    if (event.target == deleteModal) closeDeleteModal();
}

// Print Slip Function
function printThermalSlip(studentId, studentName, fatherName, mobile, feesArray, monthName, year, sessionTitle, classTitle, oldDues) {
    if (!studentId || !<?php echo $selected_session; ?> || !<?php echo $selected_class; ?> || !'<?php echo $selected_month; ?>') {
        alert('Error: Missing required parameters for printing.');
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'print_slip_handler.php';
    form.target = '_blank';
    form.style.display = 'none';
    
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
    form.appendChild(csrfInput);
    
    var params = {
        'student_registration_id': studentId,
        'session_id': '<?php echo $selected_session; ?>',
        'class_id': '<?php echo $selected_class; ?>',
        'month': '<?php echo $selected_month; ?>'
    };
    
    for (var key in params) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// Print All Students Function
function printAllSlips() {
    var sessionId = document.getElementById('session_id').value;
    var classId = document.getElementById('class_id').value;
    var month = document.getElementById('month').value;
    
    if (!sessionId) {
        alert('<?php echo $translations[$lang]['select_session_first']; ?>');
        return;
    }
    if (!classId) {
        alert('<?php echo $translations[$lang]['select_class']; ?>');
        return;
    }
    if (!month) {
        alert('<?php echo $translations[$lang]['select_month']; ?>');
        return;
    }
    
    if (!confirm('Are you sure you want to print fee slips for ALL students in this class?\n\nThis may take a while if there are many students.')) {
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = 'print_all_handler.php';
    form.target = '_blank';
    form.style.display = 'none';
    
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
    form.appendChild(csrfInput);
    
    var params = {
        'session_id': sessionId,
        'class_id': classId,
        'month': month
    };
    
    for (var key in params) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

document.addEventListener('DOMContentLoaded', function() {
    var sessionSelect = document.getElementById('session_id');
    var classSelect = document.getElementById('class_id');
    var monthSelect = document.getElementById('month');
    var createBtn = document.getElementById('create-btn');
    var updateBtn = document.getElementById('update-btn');
    var viewBtn = document.getElementById('view-btn');
    var form = document.getElementById('fee-card-form');
    var formAction = document.getElementById('form-action');
    
    function toggleAmountInput(checkbox) {
        var feeId = checkbox.getAttribute('data-fee-id');
        var amountInput = document.querySelector('input[name="fee_amounts[' + feeId + ']"]');
        if (amountInput) {
            amountInput.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                var existingAmount = checkbox.getAttribute('data-existing-amount');
                var originalAmount = checkbox.getAttribute('data-original-amount');
                amountInput.value = existingAmount || originalAmount;
            }
        }
    }
    
    var checkboxes = document.querySelectorAll('.fee-type-checkbox-input');
    for (var i = 0; i < checkboxes.length; i++) {
        toggleAmountInput(checkboxes[i]);
        checkboxes[i].addEventListener('change', function() { toggleAmountInput(this); });
    }
    
    if (classSelect) {
        classSelect.addEventListener('change', function() {
            var sessionId = sessionSelect.value;
            var classId = classSelect.value;
            var month = monthSelect.value;
            var lang = '<?php echo $lang; ?>';
            if (sessionId && classId && month) {
                window.location.href = window.location.pathname + '?session_id=' + sessionId + '&class_id=' + classId + '&month=' + month + '&lang=' + lang;
            } else if (sessionId && classId) {
                window.location.href = window.location.pathname + '?session_id=' + sessionId + '&class_id=' + classId + '&lang=' + lang;
            }
        });
    }
    
    if (createBtn) {
        createBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!sessionSelect.value) { alert('<?php echo $translations[$lang]['select_session_first']; ?>'); return; }
            var feeTypeCheckboxes = document.querySelectorAll('.fee-type-checkbox-input:checked');
            if (feeTypeCheckboxes.length === 0) { alert('<?php echo $translations[$lang]['select_fee_types']; ?>'); return; }
            var hasErrors = false;
            var checkedBoxes = document.querySelectorAll('.fee-type-checkbox-input:checked');
            for (var i = 0; i < checkedBoxes.length; i++) {
                var feeId = checkedBoxes[i].getAttribute('data-fee-id');
                var amountInput = document.querySelector('input[name="fee_amounts[' + feeId + ']"]');
                if (amountInput && (amountInput.value === '' || parseFloat(amountInput.value) < 0)) {
                    alert('<?php echo $translations[$lang]['valid_amount']; ?>');
                    amountInput.focus();
                    hasErrors = true;
                    break;
                }
            }
            if (hasErrors) return;
            if (confirm('<?php echo $translations[$lang]['confirm_generate']; ?>')) { formAction.value = 'create'; form.submit(); }
        });
    }
    
    if (updateBtn) {
        updateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!sessionSelect.value) { alert('<?php echo $translations[$lang]['select_session_first']; ?>'); return; }
            var hasErrors = false;
            var checkedBoxes = document.querySelectorAll('.fee-type-checkbox-input:checked');
            for (var i = 0; i < checkedBoxes.length; i++) {
                var feeId = checkedBoxes[i].getAttribute('data-fee-id');
                var amountInput = document.querySelector('input[name="fee_amounts[' + feeId + ']"]');
                if (amountInput && (amountInput.value === '' || parseFloat(amountInput.value) < 0)) {
                    alert('<?php echo $translations[$lang]['valid_amount']; ?>');
                    amountInput.focus();
                    hasErrors = true;
                    break;
                }
            }
            if (hasErrors) return;
            if (confirm('<?php echo $translations[$lang]['confirm_update']; ?>')) { formAction.value = 'update'; form.submit(); }
        });
    }
    
    if (viewBtn) {
        viewBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (!sessionSelect.value) { alert('<?php echo $translations[$lang]['select_session_first']; ?>'); return; }
            formAction.value = 'view';
            form.submit();
        });
    }
    
    if (window.location.hash) {
        var element = document.querySelector(window.location.hash);
        if (element) {
            setTimeout(function() { element.scrollIntoView({ behavior: 'smooth' }); }, 100);
        }
    }
});
</script>

</body>
</html>
<?php
if (isset($conn)) $conn->close();
ob_end_flush();
?>