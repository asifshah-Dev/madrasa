<?php 
header('Content-Type: text/html; charset=utf-8');
require_once('security.php');

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}

$lang = $_SESSION['lang'];

// Language strings
$translations = [
    'en' => [
        'dob_label' => 'Date of Birth:',
        'dob_day_label' => 'Day:',
        'dob_month_label' => 'Month:',
        'dob_year_label' => 'Year:',
        'title' => 'Student Registration',
        'add_title' => 'Register New Student',
        'edit_title' => 'Edit Student Registration',
        'branch_label' => 'Branch:',
        'village_council_label' => 'Village Council:',
        'reg_no_label' => 'Registration Number:',
        'transport_fee_label' => 'Transport Fee:',
        'registration_date_label' => 'Registration Date:',
        'transport_label' => 'Use Transport:',
        'session_label' => 'Session:',
        'class_label' => 'Class:',
        'course_label' => 'Course:',
        'name_label' => 'Student Name:',
        'father_name_label' => 'Father Name:',
        'mobile_label' => 'Mobile:',
        'cnic_label' => 'CNIC:',
        'current_address_label' => 'Current Address:',
        'permanent_address_label' => 'Permanent Address:',
        'guardian_name_label' => 'Guardian Name:',
        'guardian_mobile_label' => 'Guardian Mobile:',
        'guardian_address_label' => 'Guardian Address:',
        'guardian_cnic_label' => 'Guardian CNIC:',
        'previous_schools_label' => 'Previous Schools:',
        'other_info_label' => 'Other Information:',
        'image_label' => 'Student Image:',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Student registered successfully!',
        'update_success' => 'Student information updated successfully!',
        'error' => 'Error: ',
        'required_error' => 'Required fields cannot be empty!',
        'list_title' => 'Registered Students',
        'no_records' => 'No students found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete_confirm' => 'Are you sure you want to delete this student record?',
        'select_option' => '-- Select --',
        'class_or_course' => 'Class/Course:',
        'old_dues_label' => 'Old Dues:',
        'old_dues_amount_label' => 'Old Dues Amount:',
        'print_registration_form' => 'Print Registration Form',
        'new_admission_label' => 'New Admission',
        'old_admission_label' => 'Old Admission',
        'invalid_dob' => 'Invalid Date of Birth or Year before 1950.',
        'withdraw' => 'Withdraw',
        'withdraw_confirm' => 'Are you sure you want to withdraw this student?',
        'withdraw_success' => 'Student withdrawn successfully!',
        'toggle_student_type' => 'Toggle Regular/Hostilized',
        'toggle_success' => 'Student type updated successfully!',
        'student_type_label' => 'Student Type:',
        'student_list' => 'Student List',
        // Custom fee translations
        'custom_fee_title'       => 'Custom Fee Assignment',
        'fee_type_label'         => 'Fee Type:',
        'fee_amount_label'       => 'Amount:',
        'fee_assigned_date_label'=> 'Assigned Date:',
        'add_fee_btn'            => 'Add Fee',
        'assigned_fees_title'    => 'Assigned Fees',
        'no_fees'                => 'No custom fees assigned yet.',
        'fee_save_success'       => 'Custom fee assigned successfully!',
        'fee_delete_success'     => 'Custom fee removed successfully!',
        'fee_error'              => 'Error saving fee. Please try again.',
        'save_student_first'     => 'Please save the student record first before assigning fees.',
        'remove'                 => 'Remove',
    ],
    'ur' => [
        'dob_label' => 'تاریخ پیدائش:',
        'dob_day_label' => 'دن:',
        'dob_month_label' => 'مہینہ:',
        'dob_year_label' => 'سال:',
        'title' => 'طالب علم کی رجسٹریشن',
        'add_title' => 'نیا طالب علم رجسٹر کریں',
        'edit_title' => 'طالب علم کی معلومات میں ترمیم کریں',
        'branch_label' => 'برانچ:',
        'village_council_label' => 'ویلیج کونسل:',
        'reg_no_label' => 'رجسٹریشن نمبر:',
        'transport_fee_label' => 'ٹرانسپورٹ فیس:',
        'registration_date_label' => 'رجسٹریشن کی تاریخ:',
        'transport_label' => 'ٹرانسپورٹ استعمال کریں:',
        'session_label' => 'سیشن:',
        'class_label' => 'کلاس:',
        'course_label' => 'کورس:',
        'name_label' => 'طالب علم کا نام:',
        'father_name_label' => 'والد کا نام:',
        'mobile_label' => 'موبائل:',
        'cnic_label' => 'قومی شناختی کارڈ:',
        'current_address_label' => 'موجودہ پتہ:',
        'permanent_address_label' => 'مستقل پتہ:',
        'guardian_name_label' => 'سرپرست کا نام:',
        'guardian_mobile_label' => 'سرپرست کا موبائل:',
        'guardian_address_label' => 'سرپرست کا پتہ:',
        'guardian_cnic_label' => 'سرپرست کا قومی شناختی کارڈ:',
        'previous_schools_label' => 'سابقہ اسکولز:',
        'other_info_label' => 'دیگر معلومات:',
        'image_label' => 'طالب علم کی تصویر:',
        'submit' => 'جمع کرائیں',
        'reset' => 'دوبارہ ترتیب دیں',
        'success' => 'طالب علم کامیابی سے رجسٹر ہو گیا!',
        'update_success' => 'طالب علم کی معلومات کامیابی سے اپ ڈیٹ ہو گئیں!',
        'error' => 'خرابی: ',
        'required_error' => 'ضروری فیلڈز خالی نہیں ہو سکتیں!',
        'list_title' => 'رجسٹرڈ طلباء',
        'no_records' => 'کوئی طالب علم موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'edit' => 'ترمیم کریں',
        'save' => 'محفوظ کریں',
        'cancel' => 'منسوخ کریں',
        'delete_confirm' => 'کیا آپ واقعی اس طالب علم کا ریکارڈ حذف کرنا چاہتے ہیں؟',
        'select_option' => '-- منتخب کریں --',
        'class_or_course' => 'کلاس/کورس:',
        'old_dues_label' => 'پرانا بقایا:',
        'old_dues_amount_label' => 'پرانا بقایا رقم:',
        'print_registration_form' => 'رجسٹریشن فارم پرنٹ کریں',
        'new_admission_label' => 'نیا داخلہ',
        'old_admission_label' => 'پرانا داخلہ',
        'invalid_dob' => 'غلط تاریخ پیدائش یا سال 1950 سے پہلے۔',
        'withdraw' => 'واپس لیں',
        'withdraw_confirm' => 'کیا آپ واقعی اس طالب علم کو واپس لینا چاہتے ہیں؟',
        'withdraw_success' => 'طالب علم کامیابی سے واپس لے لیا گیا!',
        'toggle_student_type' => 'ریگولر/ہوسٹلائزڈ تبدیل کریں',
        'toggle_success' => 'طالب علم کی قسم کامیابی سے اپ ڈیٹ ہو گئی!',
        'student_type_label' => 'طالب علم کی قسم:',
        'student_list' => 'طلباء کی فہرست',
        // Custom fee translations
        'custom_fee_title'       => 'کسٹم فیس کی تفویض',
        'fee_type_label'         => 'فیس کی قسم:',
        'fee_amount_label'       => 'رقم:',
        'fee_assigned_date_label'=> 'تفویض کی تاریخ:',
        'add_fee_btn'            => 'فیس شامل کریں',
        'assigned_fees_title'    => 'تفویض کردہ فیسیں',
        'no_fees'                => 'ابھی تک کوئی کسٹم فیس تفویض نہیں کی گئی۔',
        'fee_save_success'       => 'کسٹم فیس کامیابی سے تفویض ہو گئی!',
        'fee_delete_success'     => 'کسٹم فیس کامیابی سے ہٹا دی گئی!',
        'fee_error'              => 'فیس محفوظ کرنے میں خرابی۔ دوبارہ کوشش کریں۔',
        'save_student_first'     => 'فیس تفویض کرنے سے پہلے طالب علم کا ریکارڈ محفوظ کریں۔',
        'remove'                 => 'ہٹائیں',
    ]
];

// Create database connection
require_once('conn_inc.php');

// ══════════════════════════════════════════════════════════════
// AJAX: Custom fee assignment handlers (for edit mode only)
// ══════════════════════════════════════════════════════════════
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {
    header('Content-Type: application/json');
    $action   = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];

    if ($action === 'add_fee') {
        $student_id    = intval($_POST['student_id']);
        $fee_type_id   = intval($_POST['fee_type_id']);
        $amount        = floatval($_POST['amount']);
        $assigned_date = trim($_POST['assigned_date']);
        $status        = 1;

        if (!$student_id || !$fee_type_id || $amount <= 0 || empty($assigned_date)) {
            $response['message'] = 'All fields are required and amount must be greater than 0.';
            echo json_encode($response);
            exit();
        }

        $stmt = $conn->prepare(
            "INSERT INTO student_fee_assignments (student_id, fee_type_id, amount, assigned_date, status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iidsi", $student_id, $fee_type_id, $amount, $assigned_date, $status);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $ft = $conn->query("SELECT title FROM fee_types WHERE id = $fee_type_id");
            $ft_row = $ft->fetch_assoc();
            $response['success']       = true;
            $response['message']       = $translations[$lang]['fee_save_success'];
            $response['id']            = $new_id;
            $response['fee_type_title']= htmlspecialchars($ft_row['title'] ?? '');
            $response['amount']        = number_format($amount, 2);
            $response['assigned_date'] = htmlspecialchars($assigned_date);
        } else {
            $response['message'] = 'DB Error: ' . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'delete_fee') {
        $fee_id = intval($_POST['fee_id']);
        $stmt = $conn->prepare("DELETE FROM student_fee_assignments WHERE id = ?");
        $stmt->bind_param("i", $fee_id);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = $translations[$lang]['fee_delete_success'];
        } else {
            $response['message'] = 'DB Error: ' . $stmt->error;
        }
        $stmt->close();

    } elseif ($action === 'get_fees') {
        $student_id = intval($_POST['student_id']);
        $result = $conn->query("
            SELECT sfa.id, ft.title AS fee_type_title, sfa.amount, sfa.assigned_date, sfa.status
            FROM student_fee_assignments sfa
            JOIN fee_types ft ON sfa.fee_type_id = ft.id
            WHERE sfa.student_id = $student_id
            ORDER BY sfa.id DESC
        ");

        $fees = [];
        while ($row = $result->fetch_assoc()) {
            $fees[] = [
                'id'             => $row['id'],
                'fee_type_title' => htmlspecialchars($row['fee_type_title']),
                'amount'         => number_format(floatval($row['amount']), 2),
                'assigned_date'  => htmlspecialchars($row['assigned_date']),
                'status'         => $row['status'],
            ];
        }
        $response['success'] = true;
        $response['fees']    = $fees;
    }

    echo json_encode($response);
    exit();
}

// Get dropdown options
$branches = [];
$village_councils = [];
$sessions = [];
$classes = [];
$courses = [];
$fee_types = [];

$branch_result = $conn->query("SELECT id, title FROM branches ORDER BY title");
while ($row = $branch_result->fetch_assoc()) {
    $branches[$row['id']] = $row['title'];
}

$vc_result = $conn->query("SELECT id, title, transport_fee FROM village_councils ORDER BY title");
while ($row = $vc_result->fetch_assoc()) {
    $village_councils[$row['id']] = ['title' => $row['title'], 'transport_fee' => $row['transport_fee']];
}

$session_result = $conn->query("SELECT id, title FROM sessions ORDER BY id DESC");
while ($row = $session_result->fetch_assoc()) {
    $sessions[$row['id']] = $row['title'];
}

$class_result = $conn->query("
    SELECT c.id, c.title AS class_title, cr.title AS course_title 
    FROM classes c
    INNER JOIN courses cr ON cr.id = c.course_id
    ORDER BY c.id DESC
");
while ($row = $class_result->fetch_assoc()) {
    $classes[$row['id']] = $row['course_title'] . ' - ' . $row['class_title'];
}

$course_result = $conn->query("SELECT id, title FROM courses ORDER BY title");
while ($row = $course_result->fetch_assoc()) {
    $courses[$row['id']] = $row['title'];
}

$ft_result = $conn->query("SELECT id, title, type FROM fee_types ORDER BY title");
while ($row = $ft_result->fetch_assoc()) {
    $fee_types[$row['id']] = $row;
}

$month_map = [
    'jan' => '01', 'january' => '01', 'feb' => '02', 'february' => '02',
    'mar' => '03', 'march' => '03', 'apr' => '04', 'april' => '04',
    'may' => '05', 'jun' => '06', 'june' => '06', 'jul' => '07', 'july' => '07',
    'aug' => '08', 'august' => '08', 'sep' => '09', 'sept' => '09', 'september' => '09',
    'oct' => '10', 'october' => '10', 'nov' => '11', 'november' => '11',
    'dec' => '12', 'december' => '12'
];

// ══════════════════════════════════════════════════════════════
// Handle form submission - STUDENT + CUSTOM FEES in ONE submission
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
    $branch_id = mysqli_real_escape_string($conn, $_POST['branch_id']);
    $village_council_id = mysqli_real_escape_string($conn, $_POST['village_council_id']);
    $reg_no = mysqli_real_escape_string($conn, $_POST['reg_no']);
    $session_id = mysqli_real_escape_string($conn, $_POST['session_id']);
    $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $student_type = mysqli_real_escape_string($conn, $_POST['student_type']);
    
    // Handle DOB
    $dob_day = trim(mysqli_real_escape_string($conn, $_POST['dob_day']));
    $dob_month = trim(mysqli_real_escape_string($conn, $_POST['dob_month']));
    $dob_year = trim(mysqli_real_escape_string($conn, $_POST['dob_year']));
    
    $dob = '';
    if (!empty($dob_day) && !empty($dob_month) && !empty($dob_year)) {
        $dob_month_lower = strtolower($dob_month);
        if (isset($month_map[$dob_month_lower])) {
            $month_num = $month_map[$dob_month_lower];
        } elseif (is_numeric($dob_month) && $dob_month >= 1 && $dob_month <= 12) {
            $month_num = str_pad(intval($dob_month), 2, '0', STR_PAD_LEFT);
        } else {
            $month_num = '';
        }
        $day_num = str_pad(intval($dob_day), 2, '0', STR_PAD_LEFT);
        $year_num = intval($dob_year);
        if ($month_num && $year_num >= 1950 && checkdate($month_num, $day_num, $year_num)) {
            $dob = sprintf('%04d-%02d-%02d', $year_num, $month_num, $day_num);
        } else {
            $_SESSION['message'] = $translations[$lang]['invalid_dob'];
            $_SESSION['message_type'] = 'danger';
        }
    }
    
    $father_name = mysqli_real_escape_string($conn, $_POST['father_name']);
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    $cnic = mysqli_real_escape_string($conn, $_POST['cnic']);
    $current_address = mysqli_real_escape_string($conn, $_POST['current_address']);
    $permanent_address = mysqli_real_escape_string($conn, $_POST['permanent_address']);
    $guardian_name = mysqli_real_escape_string($conn, $_POST['guardian_name']);
    $guardian_mobile = mysqli_real_escape_string($conn, $_POST['guardian_mobile']);
    $guardian_address = mysqli_real_escape_string($conn, $_POST['guardian_address']);
    $guardian_cnic = mysqli_real_escape_string($conn, $_POST['guardian_cnic']);
    $previous_schools_description = mysqli_real_escape_string($conn, $_POST['previous_schools_description']);
    $student_other_description = mysqli_real_escape_string($conn, $_POST['student_other_description']);
    $transport_fee = mysqli_real_escape_string($conn, $_POST['transport_fee']);
    $is_transport = isset($_POST['is_transport']) ? 0 : 1;
    $registration_date = mysqli_real_escape_string($conn, $_POST['registration_date']);
    $is_old_dues = isset($_POST['is_old_dues']) ? 1 : 0;
    $old_dues_amount = isset($_POST['old_dues_amount']) ? mysqli_real_escape_string($conn, $_POST['old_dues_amount']) : 0;
    $is_admission = 1;
    $promotion_date = date('Y-m-d');

    $image_path = '';
    if (!empty($_FILES['image']['name'])) {
        $image_name = time() . '_' . basename($_FILES['image']['name']);
        $target_dir = "Uploads/students/";
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = $target_file;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // NEW: Collect custom fees from form submission (for new student)
    // ═══════════════════════════════════════════════════════════
    $custom_fees_to_insert = [];
    if ($student_id == 0 && isset($_POST['custom_fees']) && is_array($_POST['custom_fees'])) {
        foreach ($_POST['custom_fees'] as $fee) {
            if (!empty($fee['fee_type_id']) && !empty($fee['amount']) && !empty($fee['assigned_date'])) {
                $custom_fees_to_insert[] = [
                    'fee_type_id' => intval($fee['fee_type_id']),
                    'amount' => floatval($fee['amount']),
                    'assigned_date' => mysqli_real_escape_string($conn, $fee['assigned_date'])
                ];
            }
        }
    }

    if ($student_id > 0) {
        // Update existing student
        $query = "UPDATE student_registration SET 
            branch_id = '$branch_id', village_council_id = '$village_council_id', reg_no = '$reg_no',
            name = '$name', dob = '$dob', father_name = '$father_name', mobile = '$mobile', cnic = '$cnic',
            current_address = '$current_address', permanent_address = '$permanent_address',
            guardian_name = '$guardian_name', guardian_mobile = '$guardian_mobile',
            guardian_address = '$guardian_address', guardian_cnic = '$guardian_cnic',
            previous_schools_description = '$previous_schools_description',
            student_other_description = '$student_other_description', transport_fee = '$transport_fee',
            is_transport = '$is_transport', registration_date = '$registration_date',
            is_old_dues = '$is_old_dues', old_dues_amount = '$old_dues_amount',
            is_admission = '$is_admission', student_type = '$student_type'";
        if (!empty($image_path)) { $query .= ", image_path = '$image_path'"; }
        $query .= " WHERE id = '$student_id'";

        if (mysqli_query($conn, $query)) {
            $check_class_query = "SELECT id FROM student_class WHERE student_registration_id = '$student_id'";
            $result = mysqli_query($conn, $check_class_query);
            if (mysqli_num_rows($result) > 0) {
                $update_class = "UPDATE student_class SET session_id = '$session_id', class_id = '$class_id', promotion_date = '$promotion_date' WHERE student_registration_id = '$student_id'";
                mysqli_query($conn, $update_class);
            } else {
                $insert_class = "INSERT INTO student_class (student_registration_id, session_id, class_id, promotion_date) VALUES ('$student_id', '$session_id', '$class_id', '$promotion_date')";
                mysqli_query($conn, $insert_class);
            }
            $_SESSION['message'] = $translations[$lang]['update_success'];
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = $translations[$lang]['error'] . mysqli_error($conn);
            $_SESSION['message_type'] = 'danger';
        }
    } else {
        // Insert new student
        $query = "INSERT INTO student_registration (
            branch_id, village_council_id, reg_no, name, dob, father_name, mobile, cnic,
            current_address, permanent_address, guardian_name, guardian_mobile,
            guardian_address, guardian_cnic, previous_schools_description, student_other_description,
            image_path, registration_date, is_transport, transport_fee, is_old_dues, old_dues_amount, is_admission, student_type
        ) VALUES (
            '$branch_id', '$village_council_id', '$reg_no', '$name', '$dob', '$father_name', '$mobile', '$cnic',
            '$current_address', '$permanent_address', '$guardian_name', '$guardian_mobile',
            '$guardian_address', '$guardian_cnic', '$previous_schools_description', '$student_other_description',
            '$image_path', '$registration_date', '$is_transport', '$transport_fee', '$is_old_dues', '$old_dues_amount', '$is_admission', '$student_type'
        )";

        if (mysqli_query($conn, $query)) {
            $new_student_id = mysqli_insert_id($conn);
            
            // Insert student_class
            $insert_class = "INSERT INTO student_class (student_registration_id, session_id, class_id, promotion_date) VALUES ('$new_student_id', '$session_id', '$class_id', '$promotion_date')";
            mysqli_query($conn, $insert_class);
            
            // Insert admission fee if new admission
            if ($is_old_dues == 0) {
                $fee_query = "SELECT amount FROM class_fee_types WHERE class_id = '$class_id' AND fee_type_id = 1 AND session_id = '$session_id' LIMIT 1";
                $fee_result = mysqli_query($conn, $fee_query);
                if ($fee_result && mysqli_num_rows($fee_result) > 0) {
                    $fee_row = mysqli_fetch_assoc($fee_result);
                    $admission_fee = $fee_row['amount'];
                    $class_id_query = "SELECT id FROM student_class WHERE student_registration_id = '$new_student_id' ORDER BY id DESC LIMIT 1";
                    $class_id_result = mysqli_query($conn, $class_id_query);
                    if ($class_id_result && mysqli_num_rows($class_id_result) > 0) {
                        $class_id_row = mysqli_fetch_assoc($class_id_result);
                        $student_class_id = $class_id_row['id'];
                        $insert_fee = "INSERT INTO student_fee_card (student_class_id, fee_type_id, total_amount, due_date) VALUES ('$student_class_id', 1, '$admission_fee', '$registration_date')";
                        mysqli_query($conn, $insert_fee);
                    }
                }
            }
            
            // ═══════════════════════════════════════════════════════════
            // NEW: Insert custom fees collected from form submission
            // ═══════════════════════════════════════════════════════════
            if (!empty($custom_fees_to_insert)) {
                $stmt = $conn->prepare("INSERT INTO student_fee_assignments (student_id, fee_type_id, amount, assigned_date, status) VALUES (?, ?, ?, ?, 1)");
                foreach ($custom_fees_to_insert as $cf) {
                    $stmt->bind_param("iids", $new_student_id, $cf['fee_type_id'], $cf['amount'], $cf['assigned_date']);
                    $stmt->execute();
                }
                $stmt->close();
            }

            $_SESSION['message'] = $translations[$lang]['success'];
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = $translations[$lang]['error'] . mysqli_error($conn);
            $_SESSION['message_type'] = 'danger';
        }
    }

    header("Location: student_registration.php");
    exit();
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM student_registration WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = ($lang == 'ur') ? 'طالب علم کا ریکارڈ کامیابی سے حذف ہو گیا!' : 'Student record deleted successfully!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
        $_SESSION['message_type'] = 'danger';
    }
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle student withdrawal
if (isset($_GET['withdraw'])) {
    $id = intval($_GET['withdraw']);
    $stmt = $conn->prepare("UPDATE student_registration SET status = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = $translations[$lang]['withdraw_success'];
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
        $_SESSION['message_type'] = 'danger';
    }
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Handle student type toggle
if (isset($_GET['toggle_type'])) {
    $id = intval($_GET['toggle_type']);
    $stmt = $conn->prepare("UPDATE student_registration SET student_type = IF(student_type = 'regular', 'hostilized', 'regular') WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['message'] = $translations[$lang]['toggle_success'];
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
        $_SESSION['message_type'] = 'danger';
    }
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Get data for edit form
$edit_mode = false;
$student_data = [
    'id' => '', 'branch_id' => '', 'village_council_id' => '', 'reg_no' => '', 'session_id' => '',
    'class_id' => '', 'course_id' => '', 'name' => '', 'dob_day' => '', 'dob_month' => '', 'dob_year' => '',
    'father_name' => '', 'mobile' => '', 'cnic' => '', 'current_address' => '', 'permanent_address' => '',
    'guardian_name' => '', 'guardian_mobile' => '', 'guardian_address' => '', 'guardian_cnic' => '',
    'previous_schools_description' => '', 'student_other_description' => '', 'image_path' => '',
    'transport_fee' => '0', 'is_transport' => '1', 'registration_date' => date('Y-m-d'),
    'is_old_dues' => '0', 'old_dues_amount' => '0', 'is_admission' => '1', 'student_type' => 'regular'
];

if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("
        SELECT sr.*, sc.class_id, sc.session_id, sc.promotion_date
        FROM student_registration sr
        LEFT JOIN (SELECT * FROM student_class WHERE student_registration_id = $id ORDER BY id DESC LIMIT 1) sc ON sr.id = sc.student_registration_id
        WHERE sr.id = $id
    ");
    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
        if (!empty($student_data['dob'])) {
            $date = date_parse($student_data['dob']);
            $student_data['dob_day'] = str_pad($date['day'], 2, '0', STR_PAD_LEFT);
            $student_data['dob_month'] = str_pad($date['month'], 2, '0', STR_PAD_LEFT);
            $student_data['dob_year'] = $date['year'];
        }
        $edit_mode = true;
    }
    $result->close();
}

// Fetch existing custom fee assignments (for edit mode)
$existing_fees = [];
if ($edit_mode && !empty($student_data['id'])) {
    $fee_res = $conn->query("
        SELECT sfa.id, ft.title AS fee_type_title, sfa.amount, sfa.assigned_date, sfa.status
        FROM student_fee_assignments sfa
        JOIN fee_types ft ON sfa.fee_type_id = ft.id
        WHERE sfa.student_id = " . intval($student_data['id']) . "
        ORDER BY sfa.id DESC
    ");
    while ($row = $fee_res->fetch_assoc()) {
        $existing_fees[] = $row;
    }
}

// Get data for listing
$result = $conn->query("
    SELECT sr.*, vc.title AS village_council, b.title AS branch_title, c.title AS class_title,
        co.title AS course_title, s.title AS session_title
    FROM student_registration sr
    LEFT JOIN village_councils vc ON sr.village_council_id = vc.id
    LEFT JOIN branches b ON sr.branch_id = b.id
    LEFT JOIN (
        SELECT sc1.* FROM student_class sc1
        INNER JOIN (SELECT student_registration_id, MAX(id) AS max_id FROM student_class GROUP BY student_registration_id) sc2 ON sc1.id = sc2.max_id
    ) sc ON sr.id = sc.student_registration_id
    LEFT JOIN classes c ON sc.class_id = c.id
    LEFT JOIN courses co ON c.course_id = co.id
    LEFT JOIN sessions s ON sc.session_id = s.id
    WHERE sr.status = 0
    ORDER BY CAST(sr.reg_no AS UNSIGNED) ASC
");

$current_query = $_GET;
$current_query['lang'] = 'en';
$en_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
$current_query['lang'] = 'ur';
$ur_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <title><?php echo $translations[$lang]['title']; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="css/mystyle.css" />
    <style>
    /* ===== BASE RESPONSIVE STYLES ===== */
    * {
        box-sizing: border-box;
    }
    
    body {
        font-size: 16px;
        line-height: 1.5;
        overflow-x: hidden;
    }
    
    .container {
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
        margin-right: auto;
        margin-left: auto;
    }
    
    /* Responsive container breakpoints */
    @media (min-width: 576px) {
        .container { max-width: 540px; }
    }
    @media (min-width: 768px) {
        .container { max-width: 720px; }
    }
    @media (min-width: 992px) {
        .container { max-width: 960px; }
    }
    @media (min-width: 1200px) {
        .container { max-width: 1140px; }
    }
    
    /* RTL Support */
    <?php if ($lang == 'ur'): ?>
    body, .form-control, .btn, .alert, .navbar-nav, 
    .panel-title, .form-group label, .table, .modal-content,
    .dropdown-menu, .panel-heading, .panel-body {
        text-align: right;
        direction: rtl;
    }
    .dropdown-menu { text-align: right; left: auto; right: 0; }
    .table th, .table td { text-align: right; }
    .form-group-inline .form-control { margin-left: 0; margin-right: 10px; }
    .btn .glyphicon { margin-left: 5px; margin-right: 0; }
    <?php else: ?>
    .table th, .table td { text-align: left; }
    <?php endif; ?>
    
    /* Header Section - NORMAL SIZED BUTTONS in one line */
    .registration-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: nowrap;
        gap: 10px;
    }
    
    /* Normal Student List Button */
    .student-list-btn {
        padding: 6px 12px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
        background-color: #5cb85c !important;
        border-color: #5cb85c !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        border-radius: 4px !important;
    }
    
    .student-list-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        background-color: #4cae4c !important;
    }
    
    .student-list-btn .glyphicon {
        font-size: 14px !important;
    }
    
    /* Normal Language Switcher Buttons */
    .language-switcher {
        display: flex !important;
        gap: 8px;
        flex-wrap: nowrap;
    }
    
    .language-switcher .btn {
        padding: 6px 12px !important;
        font-size: 18px !important;
        font-weight: 600 !important;
        border-radius: 4px !important;
        transition: all 0.3s ease !important;
        background-color: #337ab7 !important;
        border: 1px solid #ddd !important;
        color: white !important;
        white-space: nowrap;
    }
    
    .language-switcher .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        background-color: #286090 !important;
    }
    
    .language-switcher .btn.active {
        background-color: #2e6da4 !important;
        border-color: #2e6da4 !important;
        font-weight: 600;
    }
    
    .language-switcher .btn:not(.active) {
        background-color: #f5f5f5 !important;
        color: #333 !important;
        border-color: #ccc !important;
    }
    
    /* Mobile view - slightly larger for touch, still in one line */
    @media (max-width: 767px) {
        .registration-header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            gap: 6px;
        }
        
        .student-list-btn {
            padding: 8px 14px !important;
            font-size: 13px !important;
            gap: 5px;
            flex-shrink: 0;
        }
        
        .student-list-btn .glyphicon {
            font-size: 13px !important;
        }
        
        .language-switcher {
            gap: 9px;
            flex-shrink: 1;
        }
        
        .language-switcher .btn {
            padding: 8px 14px !important;
            font-size: 13px !important;
        }
    }
    
    /* Very small screens (<= 480px) - compact but still touch-friendly */
    @media (max-width: 480px) {
        .registration-header {
            gap: 5px;
        }
        
        .student-list-btn {
            padding: 6px 10px !important;
            font-size: 11px !important;
            width:  160px !important;
        }
        
        .student-list-btn .glyphicon {
            font-size: 11px !important;
           
              
        }
        
        .language-switcher .btn {
            padding: 6px 10px !important;
            font-size: 14px !important;
           
        }
    }
    
    /* Panel Styles */
    .panel {
        margin-bottom: 20px;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .panel-heading {
        padding: 12px 15px;
    }
    
    .panel-heading h3 {
        font-size: 18px;
        margin: 0;
    }
    
    @media (min-width: 768px) {
        .panel-heading h3 { font-size: 20px; }
    }
    
    .panel-body {
        padding: 15px;
    }
    
    @media (min-width: 768px) {
        .panel-body { padding: 20px; }
    }
    
    /* Form Sections */
    .form-section {
        margin-bottom: 20px;
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        background-color: #fefefe;
        transition: all 0.3s ease;
    }
    
    @media (min-width: 768px) {
        .form-section {
            margin-bottom: 25px;
            padding: 20px;
        }
    }
    
    .form-section:hover {
        border-color: #337ab7;
        box-shadow: 0 2px 8px rgba(51, 122, 183, 0.1);
    }
    
    .form-section h4 {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 15px;
        margin-top: 0;
        color: #337ab7;
        border-bottom: 2px solid #337ab7;
        padding-bottom: 6px;
        display: inline-block;
    }
    
    @media (min-width: 768px) {
        .form-section h4 {
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 8px;
        }
    }
    
    /* Form Groups */
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }
    
    @media (min-width: 768px) {
        .form-group label {
            font-size: 16px;
            margin-bottom: 8px;
        }
    }
    
    .form-control {
        font-size: 14px !important;
        padding: 8px 10px !important;
        height: auto !important;
        border-radius: 4px;
        width: 100%;
    }
    
    @media (min-width: 768px) {
        .form-control {
            font-size: 16px !important;
            padding: 10px 12px !important;
        }
    }
    
    select.form-control {
        font-size: 14px !important;
        padding: 8px 10px !important;
    }
    
    @media (min-width: 768px) {
        select.form-control {
            font-size: 16px !important;
            padding: 10px 12px !important;
        }
    }
    
    textarea.form-control {
        font-size: 14px !important;
        padding: 8px 10px !important;
    }
    
    @media (min-width: 768px) {
        textarea.form-control {
            font-size: 16px !important;
            padding: 10px 12px !important;
        }
    }
    
    /* Inline Form Group for DOB */
    .form-group-inline {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    
    .form-group-inline > label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .form-group-inline .form-control {
        flex: 1;
        min-width: 70px;
    }
    
    @media (min-width: 480px) {
        .form-group-inline > label {
            width: auto;
            margin-bottom: 0;
            margin-right: 10px;
        }
    }
    
    @media (min-width: 768px) {
        .form-group-inline .form-control {
            width: auto;
        }
    }
    
    /* Checkbox Styling */
    .form-group input[type="checkbox"] {
        transform: scale(1.1);
        margin-right: 8px;
        vertical-align: middle;
    }
    
    /* Buttons */
    .btn {
        font-size: 14px !important;
        padding: 8px 16px !important;
        border-radius: 4px;
        transition: all 0.3s ease;
    }
    
    @media (min-width: 768px) {
        .btn {
            font-size: 16px !important;
            padding: 10px 20px !important;
        }
    }
    
    .btn-lg {
        font-size: 16px !important;
        padding: 10px 20px !important;
    }
    
    @media (min-width: 768px) {
        .btn-lg {
            font-size: 18px !important;
            padding: 12px 30px !important;
        }
    }
    
    .btn-xs {
        font-size: 11px !important;
        padding: 4px 8px !important;
    }
    
    @media (min-width: 768px) {
        .btn-xs {
            font-size: 13px !important;
            padding: 4px 10px !important;
        }
    }
    
    /* Tables - Responsive */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .table {
        width: 100%;
        margin-bottom: 0;
        font-size: 12px;
    }
    
    @media (min-width: 768px) {
        .table { font-size: 14px; }
    }
    
    .table th {
        font-size: 13px;
        font-weight: 700;
        color: #fff !important;
        background-color: #34495e !important;
        padding: 10px 8px !important;
        border: 1px solid #e2e8f0 !important;
    }
    
    @media (min-width: 768px) {
        .table th {
            font-size: 16px;
            padding: 12px !important;
        }
    }
    
    .table td {
        padding: 8px 6px !important;
        border: 1px solid #e2e8f0 !important;
        vertical-align: middle;
        word-break: break-word;
    }
    
    @media (min-width: 768px) {
        .table td {
            padding: 10px !important;
        }
    }
    
    /* Student Image */
    .student-image {
        max-width: 80px;
        max-height: 80px;
    }
    
    @media (min-width: 768px) {
        .student-image {
            max-width: 100px;
            max-height: 100px;
        }
    }
    
    /* Old Dues Container */
    #old_dues_amount_container {
        display: none;
        margin-top: 10px;
    }
    
    /* Fee Section */
    .fee-section {
        margin-bottom: 20px;
        padding: 15px;
        border: 2px solid #d4edda;
        border-radius: 8px;
        background-color: #f8fff9;
        transition: all 0.3s ease;
    }
    
    @media (min-width: 768px) {
        .fee-section {
            margin-bottom: 25px;
            padding: 20px;
        }
    }
    
    .fee-section:hover {
        border-color: #28a745;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.12);
    }
    
    .fee-section h4 {
        font-size: 16px;
        font-weight: bold;
        margin-bottom: 15px;
        margin-top: 0;
        color: #28a745;
        border-bottom: 2px solid #28a745;
        padding-bottom: 6px;
        display: inline-block;
    }
    
    @media (min-width: 768px) {
        .fee-section h4 {
            font-size: 18px;
            margin-bottom: 20px;
            padding-bottom: 8px;
        }
    }
    
    .fee-disabled-notice {
        color: #856404;
        background-color: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        padding: 8px 12px;
        font-size: 12px;
        margin-bottom: 0;
    }
    
    @media (min-width: 768px) {
        .fee-disabled-notice {
            padding: 10px 14px;
            font-size: 14px;
        }
    }
    
    /* Dynamic Fee Rows for NEW Student */
    .custom-fee-row {
        background: #fff;
        padding: 12px;
        margin-bottom: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    @media (min-width: 768px) {
        .custom-fee-row {
            flex-direction: row;
            align-items: flex-end;
            gap: 15px;
            padding: 15px;
        }
    }
    
    .custom-fee-row .form-group {
        margin-bottom: 0 !important;
        flex: 1;
    }
    
    .custom-fee-row .form-group label {
        font-size: 12px;
        font-weight: 500;
        margin-bottom: 4px;
        display: block;
    }
    
    @media (min-width: 768px) {
        .custom-fee-row .form-group label {
            font-size: 13px;
        }
    }
    
    .custom-fee-row .btn-remove-fee {
        padding: 6px 12px;
        margin-top: 5px;
        align-self: flex-start;
    }
    
    @media (min-width: 768px) {
        .custom-fee-row .btn-remove-fee {
            margin-top: 0;
            margin-bottom: 0;
        }
    }
    
    /* Alerts */
    .alert {
        font-size: 14px !important;
        padding: 10px 15px !important;
    }
    
    @media (min-width: 768px) {
        .alert {
            font-size: 16px !important;
            padding: 15px 20px !important;
        }
    }
    
    /* Button Group in Form */
    .text-center .btn {
        margin: 5px;
    }
    
    /* Mobile First Adjustments */
    @media (max-width: 767px) {
        .form-group-inline {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .form-group-inline .form-control {
            width: 100%;
        }
        
        .custom-fee-row {
            flex-direction: column;
        }
        
        .custom-fee-row .btn-remove-fee {
            width: 100%;
        }
        
        .table td .btn-xs {
            display: inline-block;
            margin: 2px;
        }
        
        .text-center .btn {
            display: block;
            width: 100%;
            margin: 10px 0;
        }
        
        .text-center .btn + .btn {
            margin-top: 10px;
        }
        
        .form-section {
            margin-bottom: 15px;
            padding: 12px;
        }
        
        .panel-body {
            padding: 12px;
        }
    }
    
    /* Tablet Adjustments */
    @media (min-width: 768px) and (max-width: 991px) {
        .form-group-inline .form-control {
            min-width: 100px;
        }
        
        .custom-fee-row {
            gap: 10px;
        }
    }
    
    /* Print Styles */
    @media print {
        .registration-header, .language-switcher, .btn, .panel-heading .btn,
        .form-section .btn, .fee-section .btn, .text-center .btn,
        .student-list-btn, #btnAddCustomFeeRow, .btn-remove-fee {
            display: none !important;
        }
        
        .container {
            width: 100%;
            max-width: none;
            padding: 0;
            margin: 0;
        }
        
        .panel, .form-section, .fee-section {
            border: 1px solid #ddd;
            break-inside: avoid;
        }
        
        .form-control {
            border: none;
            padding: 0;
        }
    }
    </style>
</head>
<body>
<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="registration-header">
        <a href="student_list.php" class="btn btn-success student-list-btn">
            <span class="glyphicon glyphicon-list"></span> <?php echo $translations[$lang]['student_list']; ?>
        </a>
        <div class="language-switcher">
            <a href="<?php echo htmlspecialchars($en_url); ?>" class="btn btn-default <?php echo ($lang == 'en') ? 'active' : ''; ?>">
                <span class="glyphicon glyphicon-globe"></span> English
            </a>
            <a href="<?php echo htmlspecialchars($ur_url); ?>" class="btn btn-default <?php echo ($lang == 'ur') ? 'active' : ''; ?>">
                <span class="glyphicon glyphicon-globe"></span> اردو
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?php echo $edit_mode ? $translations[$lang]['edit_title'] : $translations[$lang]['add_title']; ?>
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                            <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
                        </div>
                    <?php endif; ?>

                    <form id="studentForm" method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="student_id" value="<?php echo $student_data['id']; ?>">
                        
                        <div class="row">
                            <!-- Left Column -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'برانچ کی معلومات' : 'Branch Information'; ?></h4>
                                    <div class="form-group">
                                        <label for="branch_id"><?php echo $translations[$lang]['branch_label']; ?></label>
                                        <select class="form-control" id="branch_id" name="branch_id" required>
                                            <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                            <?php foreach ($branches as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo ($id == $student_data['branch_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'بنیادی معلومات' : 'Basic Information'; ?></h4>
                                    <div class="form-group">
                                        <label for="reg_no"><?php echo $translations[$lang]['reg_no_label']; ?></label>
                                        <input type="text" class="form-control" id="reg_no" name="reg_no" value="<?php echo htmlspecialchars($student_data['reg_no']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="name"><?php echo $translations[$lang]['name_label']; ?></label>
                                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($student_data['name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="father_name"><?php echo $translations[$lang]['father_name_label']; ?></label>
                                        <input type="text" class="form-control" id="father_name" name="father_name" required value="<?php echo htmlspecialchars($student_data['father_name']); ?>">
                                    </div>
                                    <div class="form-group form-group-inline">
                                        <label><?php echo $translations[$lang]['dob_label']; ?></label>
                                        <input type="text" class="form-control" id="dob_day" name="dob_day" value="<?php echo htmlspecialchars($student_data['dob_day']); ?>" placeholder="<?php echo $translations[$lang]['dob_day_label']; ?>" maxlength="2" pattern="[0-3][0-9]" style="width: 100%;">
                                        <select class="form-control" id="dob_month" name="dob_month" style="width: 100%;">
                                            <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                            <?php $months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
                                            foreach($months as $v=>$l): ?>
                                            <option value="<?php echo $v; ?>" <?php echo ($student_data['dob_month'] == $v) ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="form-control" id="dob_year" name="dob_year" value="<?php echo htmlspecialchars($student_data['dob_year']); ?>" placeholder="<?php echo $translations[$lang]['dob_year_label']; ?>" maxlength="4" pattern="[0-9]{4}" style="width: 100%;">
                                    </div>
                                    <div class="form-group">
                                        <label for="mobile"><?php echo $translations[$lang]['mobile_label']; ?></label>
                                        <input type="text" class="form-control" id="mobile" name="mobile" required value="<?php echo htmlspecialchars($student_data['mobile']); ?>" pattern="[0-9]{11}" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length > 11) this.value = this.value.slice(0,11);">
                                    </div>
                                    <div class="form-group">
                                        <label for="cnic"><?php echo $translations[$lang]['cnic_label']; ?></label>
                                        <input type="text" class="form-control" id="cnic" name="cnic" value="<?php echo htmlspecialchars($student_data['cnic']); ?>" pattern="[0-9]{13}" maxlength="13" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length > 13) this.value = this.value.slice(0,13);">
                                    </div>
                                    <div class="form-group">
                                        <label for="student_type"><?php echo $translations[$lang]['student_type_label']; ?></label>
                                        <select class="form-control" id="student_type" name="student_type" required>
                                            <option value="regular" <?php echo ($student_data['student_type'] == 'regular') ? 'selected' : ''; ?>>Regular</option>
                                            <option value="hostilized" <?php echo ($student_data['student_type'] == 'hostilized') ? 'selected' : ''; ?>>Hostilized</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'پتے کی معلومات' : 'Address Information'; ?></h4>
                                    <div class="form-group">
                                        <label for="current_address"><?php echo $translations[$lang]['current_address_label']; ?></label>
                                        <textarea class="form-control" id="current_address" name="current_address" rows="3" required><?php echo htmlspecialchars($student_data['current_address']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="permanent_address"><?php echo $translations[$lang]['permanent_address_label']; ?></label>
                                        <textarea class="form-control" id="permanent_address" name="permanent_address" rows="3" required><?php echo htmlspecialchars($student_data['permanent_address']); ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column -->
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'سرپرست کی معلومات' : 'Guardian Information'; ?></h4>
                                    <div class="form-group">
                                        <label for="guardian_name"><?php echo $translations[$lang]['guardian_name_label']; ?></label>
                                        <input type="text" class="form-control" id="guardian_name" name="guardian_name" value="<?php echo htmlspecialchars($student_data['guardian_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="guardian_mobile"><?php echo $translations[$lang]['guardian_mobile_label']; ?></label>
                                        <input type="text" class="form-control" id="guardian_mobile" name="guardian_mobile" value="<?php echo htmlspecialchars($student_data['guardian_mobile']); ?>" pattern="[0-9]{11}" maxlength="11" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length > 11) this.value = this.value.slice(0,11);">
                                    </div>
                                    <div class="form-group">
                                        <label for="guardian_address"><?php echo $translations[$lang]['guardian_address_label']; ?></label>
                                        <textarea class="form-control" id="guardian_address" name="guardian_address" rows="2"><?php echo htmlspecialchars($student_data['guardian_address']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="guardian_cnic"><?php echo $translations[$lang]['guardian_cnic_label']; ?></label>
                                        <input type="text" class="form-control" id="guardian_cnic" name="guardian_cnic" value="<?php echo htmlspecialchars($student_data['guardian_cnic']); ?>" pattern="[0-9]{13}" maxlength="13" oninput="this.value=this.value.replace(/[^0-9]/g,''); if(this.value.length > 13) this.value = this.value.slice(0,13);">
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'سیشن اور کلاس' : 'Session and Class'; ?></h4>
                                    <div class="form-group">
                                        <label for="session_id"><?php echo $translations[$lang]['session_label']; ?></label>
                                        <select class="form-control" id="session_id" name="session_id" required>
                                            <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                            <?php foreach ($sessions as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo ($id == $student_data['session_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="class_id"><?php echo $translations[$lang]['class_label']; ?></label>
                                        <select class="form-control" id="class_id" name="class_id" required>
                                            <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                            <?php foreach ($classes as $id => $name): ?>
                                                <option value="<?php echo $id; ?>" <?php echo ($id == $student_data['class_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'داخلہ اور بقایا' : 'Admission and Dues'; ?></h4>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="is_old_dues" name="is_old_dues" value="1" <?php echo ($student_data['is_old_dues'] == 1) ? 'checked' : ''; ?>>
                                            <?php echo $translations[$lang]['old_admission_label']; ?>
                                        </label>
                                        <small class="text-muted"><?php echo ($lang == 'ur') ? '(چیک کریں اگر پرانا داخلہ ہے)' : '(Check if old admission)'; ?></small>
                                        <div id="old_dues_amount_container" style="<?php echo ($student_data['is_old_dues'] == 1) ? 'display: block;' : 'display: none;'; ?>">
                                            <label for="old_dues_amount"><?php echo $translations[$lang]['old_dues_amount_label']; ?></label>
                                            <input type="number" class="form-control" id="old_dues_amount" name="old_dues_amount" value="<?php echo htmlspecialchars($student_data['old_dues_amount']); ?>" min="0" step="0.01">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'اضافی معلومات' : 'Additional Information'; ?></h4>
                                    <div class="form-group">
                                        <label for="previous_schools_description"><?php echo $translations[$lang]['previous_schools_label']; ?></label>
                                        <textarea class="form-control" id="previous_schools_description" name="previous_schools_description" rows="3"><?php echo htmlspecialchars($student_data['previous_schools_description']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="student_other_description"><?php echo $translations[$lang]['other_info_label']; ?></label>
                                        <textarea class="form-control" id="student_other_description" name="student_other_description" rows="3"><?php echo htmlspecialchars($student_data['student_other_description']); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Full Width Sections -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'تصویر' : 'Image'; ?></h4>
                                    <div class="form-group">
                                        <label for="image"><?php echo $translations[$lang]['image_label']; ?></label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <?php if (!empty($student_data['image_path'])): ?>
                                            <div class="current-image" style="margin-top: 10px;">
                                                <img src="<?php echo htmlspecialchars($student_data['image_path']); ?>" class="student-image img-thumbnail">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-section">
                                    <h4><?php echo ($lang == 'ur') ? 'ٹرانسپورٹ اور ویلیج کونسل' : 'Transport and Village Council'; ?></h4>
                                    <div class="row">
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label for="village_council_id"><?php echo $translations[$lang]['village_council_label']; ?></label>
                                                <select class="form-control" id="village_council_id" name="village_council_id" required>
                                                    <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                                    <?php foreach ($village_councils as $id => $data): ?>
                                                        <option value="<?php echo $id; ?>" <?php echo ($id == $student_data['village_council_id']) ? 'selected' : ''; ?> data-transport-fee="<?php echo $data['transport_fee']; ?>">
                                                            <?php echo htmlspecialchars($data['title']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label for="transport_fee"><?php echo $translations[$lang]['transport_fee_label']; ?></label>
                                                <input type="number" class="form-control" id="transport_fee" name="transport_fee" value="<?php echo htmlspecialchars($student_data['transport_fee']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label for="is_transport">
                                                    <input type="checkbox" id="is_transport" name="is_transport" value="0" <?php echo ($student_data['is_transport'] == 0) ? 'checked' : ''; ?>>
                                                    <?php echo $translations[$lang]['transport_label']; ?>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6 col-sm-12">
                                            <div class="form-group">
                                                <label for="registration_date"><?php echo $translations[$lang]['registration_date_label']; ?></label>
                                                <input type="date" class="form-control" id="registration_date" name="registration_date" value="<?php echo htmlspecialchars($student_data['registration_date']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ══════════════════════════════════════════
                                 CUSTOM FEE ASSIGNMENT SECTION - NOW WORKS FOR BOTH NEW & EDIT
                            ══════════════════════════════════════════ -->
                            <div class="col-md-12">
                                <div class="fee-section">
                                    <h4>
                                        <span class="glyphicon glyphicon-usd"></span>
                                        <?php echo $translations[$lang]['custom_fee_title']; ?>
                                    </h4>

                                    <!-- Dynamic Fee Rows Container (for NEW student form submission) -->
                                    <div id="customFeesContainer">
                                        <!-- Fee rows will be added here via JS for new students -->
                                    </div>
                                    
                                    <!-- Add Fee Button (only for new student mode) -->
                                    <?php if (!$edit_mode): ?>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAddCustomFeeRow" style="margin-bottom: 15px;">
                                            <span class="glyphicon glyphicon-plus"></span> <?php echo $translations[$lang]['add_fee_btn']; ?>
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($edit_mode): ?>
                                        <!-- Edit mode: AJAX-based fee management -->
                                        <div class="row" style="margin-bottom: 10px;">
                                            <div class="col-sm-12 col-md-4">
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label><?php echo $translations[$lang]['fee_type_label']; ?></label>
                                                    <select class="form-control" id="fee_type_id">
                                                        <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                                        <?php foreach ($fee_types as $ft_id => $ft): ?>
                                                            <option value="<?php echo $ft_id; ?>">
                                                                <?php echo htmlspecialchars($ft['title']); ?>
                                                                <?php if (!empty($ft['type'])): ?>
                                                                    (<?php echo htmlspecialchars($ft['type']); ?>)
                                                                <?php endif; ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-sm-12 col-md-3">
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label><?php echo $translations[$lang]['fee_amount_label']; ?></label>
                                                    <input type="number" class="form-control" id="fee_amount" step="0.01" min="0.01" placeholder="0.00">
                                                </div>
                                            </div>
                                            <div class="col-sm-12 col-md-3">
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label><?php echo $translations[$lang]['fee_assigned_date_label']; ?></label>
                                                    <input type="date" class="form-control" id="fee_assigned_date" value="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-sm-12 col-md-2">
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label>&nbsp;</label>
                                                    <button type="button" class="btn btn-success btn-block" id="btnAddFee">
                                                        <span class="glyphicon glyphicon-plus"></span>
                                                        <?php echo $translations[$lang]['add_fee_btn']; ?>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="feeAlert" class="alert" role="alert" style="display: none;"></div>
                                        <div style="margin-top: 15px;">
                                            <strong style="font-size: 15px; color: #333;"><?php echo $translations[$lang]['assigned_fees_title']; ?></strong>
                                            <div class="table-responsive" style="margin-top: 8px;">
                                                <table class="table table-bordered table-striped" style="margin-bottom: 0;">
                                                    <thead>
                                                        <tr>
                                                            <th>#</th>
                                                            <th><?php echo $translations[$lang]['fee_type_label']; ?></th>
                                                            <th><?php echo $translations[$lang]['fee_amount_label']; ?></th>
                                                            <th><?php echo $translations[$lang]['fee_assigned_date_label']; ?></th>
                                                            <th><?php echo $translations[$lang]['actions']; ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="feeTableBody">
                                                        <?php if (empty($existing_fees)): ?>
                                                            <tr id="noFeesRow"><td colspan="5" class="text-center text-muted"><?php echo $translations[$lang]['no_fees']; ?></td></tr>
                                                        <?php else: ?>
                                                            <?php foreach ($existing_fees as $idx => $fee): ?>
                                                                <tr data-fee-id="<?php echo $fee['id']; ?>">
                                                                    <td><?php echo $idx + 1; ?></td>
                                                                    <td><?php echo htmlspecialchars($fee['fee_type_title']); ?></td>
                                                                    <td class="text-right"><?php echo number_format(floatval($fee['amount']), 2); ?></td>
                                                                    <td><?php echo htmlspecialchars($fee['assigned_date']); ?></td>
                                                                    <td class="text-center">
                                                                        <button type="button" class="btn btn-xs btn-danger delete-fee-btn" data-fee-id="<?php echo $fee['id']; ?>">
                                                                            <span class="glyphicon glyphicon-trash"></span> <?php echo ($lang == 'ur' ? 'حذف' : 'Delete'); ?>
                                                                        </button>
                                                                    </td
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group text-center" style="margin-top: 10px;">
                                    <button type="submit" class="btn btn-primary btn-lg" style="min-width: 150px;">
                                        <?php echo $edit_mode ? $translations[$lang]['save'] : $translations[$lang]['submit']; ?>
                                    </button>
                                    <button type="reset" class="btn btn-default btn-lg" style="min-width: 150px;"><?php echo $translations[$lang]['reset']; ?></button>
                                    <?php if ($edit_mode): ?>
                                        <a href="student_registration.php" class="btn btn-danger btn-lg" style="min-width: 150px;"><?php echo $translations[$lang]['cancel']; ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('#is_old_dues').change(function () {
        if ($(this).is(':checked')) { $('#old_dues_amount_container').slideDown(); }
        else { $('#old_dues_amount_container').slideUp(); }
    });

    $('#village_council_id').change(function () {
        var selectedOption = $(this).find('option:selected');
        var transportFee = selectedOption.data('transport-fee') || 0;
        $('#transport_fee').val(transportFee);
    });
    <?php if ($edit_mode): ?>
        $('#village_council_id').trigger('change');
    <?php endif; ?>

    // ═══════════════════════════════════════════════════════════
    // NEW: Dynamic Custom Fee Rows for NEW Student Registration
    // ═══════════════════════════════════════════════════════════
    var feeRowIndex = 0;
    
    function addCustomFeeRow() {
        var rowHtml = '<div class="custom-fee-row" data-row-index="' + feeRowIndex + '">' +
            '<div class="form-group">' +
                '<label><?php echo addslashes($translations[$lang]['fee_type_label']); ?></label>' +
                '<select class="form-control" name="custom_fees[' + feeRowIndex + '][fee_type_id]" >' +
                    '<option value=""><?php echo addslashes($translations[$lang]['select_option']); ?></option>' +
                    <?php foreach ($fee_types as $ft_id => $ft): ?>
                        '<option value="<?php echo $ft_id; ?>"><?php echo addslashes(htmlspecialchars($ft['title'])); ?><?php if (!empty($ft['type'])): ?> (<?php echo addslashes(htmlspecialchars($ft['type'])); ?>)<?php endif; ?></option>' +
                    <?php endforeach; ?>
                '</select>' +
            '</div>' +
            '<div class="form-group">' +
                '<label><?php echo addslashes($translations[$lang]['fee_amount_label']); ?></label>' +
                '<input type="number" class="form-control" name="custom_fees[' + feeRowIndex + '][amount]" step="0.01" min="0.01" placeholder="0.00" >' +
            '</div>' +
            '<div class="form-group">' +
                '<label><?php echo addslashes($translations[$lang]['fee_assigned_date_label']); ?></label>' +
                '<input type="date" class="form-control" name="custom_fees[' + feeRowIndex + '][assigned_date]" value="<?php echo date("Y-m-d"); ?>" >' +
            '</div>' +
            '<button type="button" class="btn btn-danger btn-sm btn-remove-fee">' +
                '<span class="glyphicon glyphicon-trash"></span> <?php echo addslashes($translations[$lang]['remove']); ?>' +
            '</button>' +
        '</div>';
        $('#customFeesContainer').append(rowHtml);
        feeRowIndex++;
    }
    
    $('#btnAddCustomFeeRow').on('click', function() {
        addCustomFeeRow();
    });
    
    $(document).on('click', '.btn-remove-fee', function() {
        $(this).closest('.custom-fee-row').fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // Add one fee row by default for new student
    <?php if (!$edit_mode): ?>
        addCustomFeeRow();
    <?php endif; ?>

    // ═══════════════════════════════════════════════════════════
    // Existing AJAX-based fee management for EDIT mode (unchanged)
    // ═══════════════════════════════════════════════════════════
    <?php if ($edit_mode): ?>
        var studentId = <?php echo intval($student_data['id']); ?>;

        function showFeeAlert(msg, type) {
            var $a = $('#feeAlert');
            $a.removeClass('alert-success alert-danger alert-warning').addClass('alert-' + type).html(msg).fadeIn();
            setTimeout(function () { $a.fadeOut(); }, 4000);
        }

        function reindexFeeRows() {
            $('#feeTableBody tr[data-fee-id]').each(function (i) { $(this).find('td:first').text(i + 1); });
        }

        $('#btnAddFee').on('click', function () {
            var feeTypeId = $('#fee_type_id').val();
            var feeAmount = parseFloat($('#fee_amount').val());
            var assignedDate = $('#fee_assigned_date').val();

            if (!feeTypeId) { showFeeAlert('<?php echo addslashes($translations[$lang]['fee_type_label']); ?> <?php echo ($lang == "ur") ? "منتخب کریں" : "Please select a fee type."; ?>', 'warning'); return; }
            if (!feeAmount || feeAmount <= 0) { showFeeAlert('<?php echo ($lang == "ur") ? "درست رقم درج کریں۔" : "Please enter a valid amount."; ?>', 'warning'); return; }
            if (!assignedDate) { showFeeAlert('<?php echo ($lang == "ur") ? "تاریخ درج کریں۔" : "Please select an assigned date."; ?>', 'warning'); return; }

            var $btn = $(this).prop('disabled', true).text('<?php echo ($lang == "ur") ? "محفوظ ہو رہا ہے..." : "Saving..."; ?>');

            $.ajax({
                url: window.location.href, type: 'POST',
                data: { action: 'add_fee', student_id: studentId, fee_type_id: feeTypeId, amount: feeAmount, assigned_date: assignedDate },
                dataType: 'json',
                success: function (r) {
                    if (r.success) {
                        $('#noFeesRow').remove();
                        var rowCount = $('#feeTableBody tr[data-fee-id]').length + 1;
                        var newRow = '<tr data-fee-id="' + r.id + '"><td>' + rowCount + '</td><td>' + r.fee_type_title + '</td><td class="text-right">' + r.amount + '</td><td>' + r.assigned_date + '</td><td class="text-center"><button type="button" class="btn btn-xs btn-danger delete-fee-btn" data-fee-id="' + r.id + '"><span class="glyphicon glyphicon-trash"></span> <?php echo ($lang == "ur") ? "حذف" : "Delete"; ?></button></td></tr>';
                        $('#feeTableBody').append(newRow);
                        $('#fee_type_id').val(''); $('#fee_amount').val(''); $('#fee_assigned_date').val('<?php echo date("Y-m-d"); ?>');
                        showFeeAlert('<strong>✓</strong> ' + r.message, 'success');
                    } else { showFeeAlert('<strong>✗</strong> ' + r.message, 'danger'); }
                },
                error: function () { showFeeAlert('<?php echo addslashes($translations[$lang]["fee_error"]); ?>', 'danger'); },
                complete: function () { $btn.prop('disabled', false).html('<span class="glyphicon glyphicon-plus"></span> <?php echo addslashes($translations[$lang]["add_fee_btn"]); ?>'); }
            });
        });

        $(document).on('click', '.delete-fee-btn', function () {
            var confirmed = confirm('<?php echo ($lang == "ur") ? "کیا آپ واقعی یہ فیس حذف کرنا چاہتے ہیں؟" : "Are you sure you want to delete this fee?"; ?>');
            if (!confirmed) return;
            var feeId = $(this).data('fee-id');
            var $row  = $(this).closest('tr');
            $.ajax({
                url: window.location.href, type: 'POST',
                data: { action: 'delete_fee', fee_id: feeId },
                dataType: 'json',
                success: function (r) {
                    if (r.success) {
                        $row.fadeOut(300, function () {
                            $(this).remove();
                            reindexFeeRows();
                            if ($('#feeTableBody tr[data-fee-id]').length === 0) {
                                $('#feeTableBody').html('<tr id="noFeesRow"><td colspan="5" class="text-center text-muted"><?php echo addslashes($translations[$lang]["no_fees"]); ?></td></tr>');
                            }
                        });
                        showFeeAlert('<strong>✓</strong> ' + r.message, 'success');
                    } else { showFeeAlert('<strong>✗</strong> ' + r.message, 'danger'); }
                },
                error: function () { showFeeAlert('<?php echo addslashes($translations[$lang]["fee_error"]); ?>', 'danger'); }
            });
        });
    <?php endif; ?>
});
</script>

</body>
</html>
<?php $conn->close(); ?>