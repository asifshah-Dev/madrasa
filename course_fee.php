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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Invalid request!</span><span class="divider">|</span><span class="ur-text">غلط درخواست!</span></div>';
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $class_id = intval($_POST['class_id']);
    $session_id = intval($_POST['session_id']);
    
    // Get fee types and amounts (allow zero amounts)
    $fee_type_data = [];
    if (isset($_POST['fee_type_ids']) && is_array($_POST['fee_type_ids'])) {
        foreach ($_POST['fee_type_ids'] as $fee_type_id) {
            $fee_type_id = intval($fee_type_id);
            $amount = isset($_POST['amounts'][$fee_type_id]) ? floatval($_POST['amounts'][$fee_type_id]) : 0;
            // Allow zero amount, but still validate it's a number
            if ($amount >= 0) {
                $fee_type_data[$fee_type_id] = $amount;
            }
        }
    }
    
    $errors = [];
    if (empty($class_id)) $errors[] = 'class_id';
    if (empty($session_id)) $errors[] = 'session_id';
    if (empty($fee_type_data)) {
        $errors[] = 'fee_type_ids';
        $_SESSION['message'] = '<div class="dual-message"><span class="en-text">At least one fee type must be selected!</span><span class="divider">|</span><span class="ur-text">کم از کم ایک فیس قسم منتخب کرنی ہوگی!</span></div>';
        $_SESSION['message_type'] = 'danger';
    }

    if (empty($errors)) {
        if (isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1') {
            // Proper edit mode - delete old records for the original class/session combo
            $old_class_id = intval($_POST['old_class_id']);
            $old_session_id = intval($_POST['old_session_id']);
            
            // Delete existing fee types for the original class and session
            $stmt = $conn->prepare("DELETE FROM class_fee_types WHERE class_id = ? AND session_id = ?");
            $stmt->bind_param("ii", $old_class_id, $old_session_id);
            $stmt->execute();
            $stmt->close();

            // Insert new records for each selected fee type
            $stmt = $conn->prepare("INSERT INTO class_fee_types (class_id, fee_type_id, session_id, amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $class_id, $fee_type_id, $session_id, $amount);
            $success = true;

            foreach ($fee_type_data as $fee_type_id => $amount) {
                if (!$stmt->execute()) {
                    $success = false;
                    error_log("Database error: " . $stmt->error, 3, 'errors.log');
                    break;
                }
            }

            if ($success) {
                $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Fee type updated successfully!</span><span class="divider">|</span><span class="ur-text">فیس قسم کامیابی سے اپ ڈیٹ ہو گئی!</span></div>';
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Error: An unexpected error occurred.</span><span class="divider">|</span><span class="ur-text">خرابی: ایک غیر متوقع خرابی پیش آگئی۔</span></div>';
                $_SESSION['message_type'] = "danger";
            }
            $stmt->close();
        } else {
            // Insert mode: Add new records for each selected fee type
            $stmt = $conn->prepare("INSERT INTO class_fee_types (class_id, fee_type_id, session_id, amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiid", $class_id, $fee_type_id, $session_id, $amount);
            $success = true;

            foreach ($fee_type_data as $fee_type_id => $amount) {
                if (!$stmt->execute()) {
                    $success = false;
                    error_log("Database error: " . $stmt->error, 3, 'errors.log');
                    break;
                }
            }

            if ($success) {
                $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Fee type assigned successfully!</span><span class="divider">|</span><span class="ur-text">فیس قسم کامیابی سے تفویض ہو گئی!</span></div>';
                $_SESSION['message_type'] = "success";
            } else {
                $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Error: An unexpected error occurred.</span><span class="divider">|</span><span class="ur-text">خرابی: ایک غیر متوقع خرابی پیش آگئی۔</span></div>';
                $_SESSION['message_type'] = "danger";
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Required fields cannot be empty!</span><span class="divider">|</span><span class="ur-text">ضروری فیلڈز خالی نہیں ہو سکتیں!</span></div>';
            $_SESSION['message_type'] = "danger";
        }
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM class_fee_types WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Fee type deleted successfully!</span><span class="divider">|</span><span class="ur-text">فیس قسم کامیابی سے حذف ہو گئی!</span></div>';
        $_SESSION['message_type'] = "success";
    } else {
        error_log("Database error: " . $stmt->error, 3, 'errors.log');
        $_SESSION['message'] = '<div class="dual-message"><span class="en-text">Error: An unexpected error occurred.</span><span class="divider">|</span><span class="ur-text">خرابی: ایک غیر متوقع خرابی پیش آگئی۔</span></div>';
        $_SESSION['message_type'] = "danger";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get data for edit form
$edit_mode = false;
$fee_data = [
    'class_id' => '', 
    'session_id' => '', 
    'old_class_id' => '',
    'old_session_id' => '',
    'fee_types' => []
];

if (isset($_GET['edit_class_id']) && isset($_GET['edit_session_id'])) {
    $edit_class_id = intval($_GET['edit_class_id']);
    $edit_session_id = intval($_GET['edit_session_id']);
    $result = $conn->query("SELECT class_id, fee_type_id, session_id, amount FROM class_fee_types WHERE class_id = $edit_class_id AND session_id = $edit_session_id");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $fee_data['fee_types'][$row['fee_type_id']] = $row['amount'];
            if (empty($fee_data['class_id'])) {
                $fee_data['class_id'] = $row['class_id'];
                $fee_data['session_id'] = $row['session_id'];
                // Store original values for update
                $fee_data['old_class_id'] = $row['class_id'];
                $fee_data['old_session_id'] = $row['session_id'];
            }
        }
        $edit_mode = true;
    }
    $result->close();
}

// Get dropdown options
$classes = [];
$sessions = [];
$fee_types = [];

// Fetch sessions
$session_result = $conn->query("SELECT id, title FROM sessions ORDER BY title");
while ($row = $session_result->fetch_assoc()) {
    $sessions[$row['id']] = $row['title'];
}
$session_result->close();

// Fetch classes with course titles
$class_result = $conn->query("
    SELECT c.id AS class_id, c.title AS class_title, co.title AS course_title 
    FROM classes c
    LEFT JOIN courses co ON co.id = c.course_id
    ORDER BY co.title, c.title
");
while ($row = $class_result->fetch_assoc()) {
    $classes[$row['class_id']] = $row['course_title'] . ' - ' . $row['class_title'];
}
$class_result->close();

// Fetch fee types
$fee_type_result = $conn->query("SELECT id, title FROM fee_types ORDER BY title");
while ($row = $fee_type_result->fetch_assoc()) {
    $fee_types[$row['id']] = $row['title'];
}
$fee_type_result->close();

// Get data for listing, grouped by course
$fee_list = [];
$result = $conn->query("
    SELECT cft.id, cft.class_id, cft.fee_type_id, cft.session_id, cft.amount,
           co.id AS course_id, co.title AS course_title,
           c.title AS class_title, ft.title AS fee_type_title, s.title AS session_title
    FROM class_fee_types cft
    JOIN classes c ON cft.class_id = c.id
    JOIN courses co ON c.course_id = co.id
    JOIN fee_types ft ON cft.fee_type_id = ft.id
    LEFT JOIN sessions s ON cft.session_id = s.id
    ORDER BY co.title, c.title, ft.title, s.title
");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $course_id = $row['course_id'];
        $fee_list[$course_id]['course_title'] = $row['course_title'];
        $fee_list[$course_id]['fees'][] = $row;
    }
}
$result->close();

// Define pastel colors for courses
$course_colors = [
    '#e6f3ff', // Light Blue
    '#e6ffe6', // Light Green
    '#fff5e6', // Light Orange
    '#ffe6f3', // Light Pink
    '#f0e6ff', // Light Purple
    '#e6fffa'  // Light Cyan
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Class Fee Types Management | کلاس فیس اقسام کا انتظام</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <style>
    /* Base Font Sizes - Larger for better readability */
    * {
        box-sizing: border-box;
    }

    body {
        font-size: 16px;
        line-height: 1.6;
        background-color: #f5f5f5;
    }

    /* Dual Label Styling - Always left-right */
    .dual-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        gap: 10px;
    }

    .dual-label .en-text {
        text-align: left;
        flex: 1;
        font-size: inherit;
    }

    .dual-label .ur-text {
        text-align: right;
        flex: 1;
        font-family: 'Noto Nastaliq Urdu', 'Urdu Typesetting', 'Jameel Noori Nastaleeq', serif;
        font-size: inherit;
        direction: rtl;
    }

    .dual-label .divider {
        color: #999;
        font-weight: bold;
        padding: 0 5px;
        flex-shrink: 0;
    }

    .dual-label-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        font-weight: 600;
        gap: 10px;
    }

    .dual-label-heading .en-text {
        text-align: left;
        flex: 1;
        font-size: inherit;
    }

    .dual-label-heading .ur-text {
        text-align: right;
        flex: 1;
        font-family: 'Noto Nastaliq Urdu', 'Urdu Typesetting', 'Jameel Noori Nastaleeq', serif;
        font-size: inherit;
        direction: rtl;
    }

    .dual-label-heading .divider {
        color: #999;
        padding: 0 5px;
        flex-shrink: 0;
    }

    /* Message dual language - Always left-right */
    .dual-message {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
        width: 100%;
    }

    .dual-message .en-text {
        flex: 1;
        text-align: left;
        font-size: inherit;
    }

    .dual-message .ur-text {
        flex: 1;
        text-align: right;
        font-family: 'Noto Nastaliq Urdu', 'Urdu Typesetting', 'Jameel Noori Nastaleeq', serif;
        font-size: inherit;
        direction: rtl;
    }

    .dual-message .divider {
        color: #999;
        padding: 0 5px;
        flex-shrink: 0;
    }

    .table th,
    .table td {
        text-align: left;
    }

    .checkbox label {
        padding-left: 20px;
    }

    /* Headers */
    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: 600;
    }

    .panel-title {
        font-size: 18px;
        font-weight: 600;
    }

    /* Form Labels */
    label {
        font-size: 15px;
        font-weight: 600;
        margin-bottom: 8px;
        display: block;
    }

    /* Form Controls */
    .form-control {
        font-size: 15px !important;
        padding: 10px 12px !important;
        height: auto !important;
        border-radius: 6px !important;
    }

    select.form-control {
        padding: 10px 12px;
    }

    /* Buttons */
    .btn {
        font-size: 15px;
        padding: 8px 16px;
        border-radius: 6px;
        font-weight: 500;
        white-space: normal;
        word-wrap: break-word;
    }

    .btn-xs {
        font-size: 13px;
        padding: 6px 12px;
    }

    /* Tables - Desktop */
    .table {
        font-size: 15px;
        margin-bottom: 0;
    }

    .table th {
        font-weight: 600;
        background-color: #f9f9f9;
        padding: 12px 8px;
        vertical-align: middle;
        white-space: nowrap;
    }

    .table td {
        padding: 12px 8px;
        vertical-align: middle;
    }

    /* Fee Types Container */
    .fee-types-container {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 8px;
        background-color: #fafafa;
    }

    .fee-type-item {
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
    }

    .fee-type-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .fee-type-item .checkbox {
        flex: 1;
        margin: 0;
        min-width: 180px;
    }

    .fee-type-item .checkbox label {
        font-size: 15px;
        font-weight: normal;
        margin-bottom: 0;
    }

    .amount-input {
        width: 140px;
        min-width: 120px;
    }

    .amount-input input {
        font-size: 15px;
        padding: 8px 10px;
    }

    /* Course Header */
    .course-header {
        margin: 20px 0 15px 0;
        padding: 12px 15px;
        background-color: #e8e8e8;
        border-left: 5px solid #337ab7;
        font-size: 18px;
        font-weight: bold;
        border-radius: 6px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    /* Alerts */
    .alert {
        font-size: 15px;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    /* Button group for dual labels */
    .btn-dual {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-dual .btn-divider {
        color: rgba(255, 255, 255, 0.7);
        margin: 0 2px;
    }

    /* Table responsive wrapper */
    .table-responsive {
        border: none;
        margin-bottom: 20px;
        border-radius: 6px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    /* Mobile Card View for Tables */
    @media (max-width: 767px) {

        /* Hide regular table on mobile */
        .table.desktop-table {
            display: none;
        }

        /* Show card view on mobile */
        .mobile-card-view {
            display: block;
        }

        .fee-card {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .fee-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .fee-card-header .sr-no {
            font-size: 14px;
            font-weight: bold;
            color: #666;
        }

        .fee-card-header .amount {
            font-size: 18px;
            font-weight: bold;
            color: #337ab7;
        }

        .fee-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .fee-card-row:last-child {
            border-bottom: none;
        }

        .fee-card-label {
            font-weight: 600;
            color: #555;
            font-size: 13px;
            min-width: 70px;
        }

        .fee-card-label .en-label {
            display: block;
            font-size: 11px;
            color: #888;
            margin-top: 2px;
        }

        .fee-card-value {
            text-align: right;
            font-size: 14px;
            color: #333;
            flex: 1;
            padding-left: 10px;
        }

        .fee-card-actions {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            text-align: center;
        }

        .fee-card-actions .btn {
            width: 100%;
            font-size: 14px;
            padding: 10px;
        }
    }

    /* Desktop - hide mobile cards */
    @media (min-width: 768px) {
        .mobile-card-view {
            display: none;
        }

        .table.desktop-table {
            display: table;
        }
    }

    /* Responsive Styles */
    @media (max-width: 768px) {
        body {
            font-size: 15px;
        }

        .panel-title {
            font-size: 15px;
        }

        /* Keep dual labels left-right on mobile */
        .dual-label,
        .dual-label-heading,
        .dual-message {
            flex-direction: row;
            gap: 6px;
            align-items: center;
        }

        .dual-label .en-text,
        .dual-label-heading .en-text,
        .dual-message .en-text {
            text-align: left;
            font-size: 13px;
            padding: 0;
            border-bottom: none;
        }

        .dual-label .ur-text,
        .dual-label-heading .ur-text,
        .dual-message .ur-text {
            text-align: right;
            font-size: 13px;
            padding: 0;
            direction: rtl;
        }

        .dual-label .divider,
        .dual-label-heading .divider,
        .dual-message .divider {
            display: inline;
            font-size: 12px;
            padding: 0 3px;
        }

        label {
            font-size: 14px;
        }

        .form-control {
            font-size: 16px;
            padding: 12px;
        }

        .btn {
                    font-size: 18px !important;
        padding: 14px 18px !important;
            width: 100%;
            margin: 5px 0;
        }

        .btn-xs {
            font-size: 13px;
            padding: 8px 12px;
            width: auto;
        }

        .fee-type-item .checkbox label {
            font-size: 14px;
        }

        .amount-input {
            width: 100%;
            margin-top: 8px;
        }

        .amount-input input {
            font-size: 16px;
            padding: 10px;
        }

        .course-header {
            font-size: 14px;
            padding: 10px 12px;
            flex-direction: column;
            align-items: flex-start;
        }

        .course-header .dual-label {
            flex-direction: row;
            width: 100%;
            font-size: 14px;
        }

        .alert {
            font-size: 14px;
            padding: 12px;
        }

        .fee-types-container {
            padding: 12px;
        }

        .panel-body {
            padding: 15px;
        }

        /* Button dual labels on mobile - keep inline */
        .btn-dual {
            flex-direction: row;
            gap: 5px;
            align-items: center;
            font-size: 13px;
        }

        .btn-dual .btn-divider {
            display: inline;
        }

        /* Table header dual labels */
        .table th .dual-label {
            flex-direction: row;
            gap: 4px;
            font-size: 11px;
        }

        .table th .dual-label span {
            font-size: 11px;
            line-height: 1.3;
        }
    }

    @media (max-width: 480px) {
        body {
            font-size: 14px;
        }

        .panel-heading {
            padding: 10px 12px;
        }

        .panel-title {
            font-size: 14px;
        }

        /* Even smaller screens - keep left-right */
        .dual-label .en-text,
        .dual-label-heading .en-text,
        .dual-message .en-text {
            font-size: 12px;
        }

        .dual-label .ur-text,
        .dual-label-heading .ur-text,
        .dual-message .ur-text {
            font-size: 12px;
        }

        .dual-label .divider,
        .dual-label-heading .divider,
        .dual-message .divider {
            font-size: 11px;
            padding: 0 2px;
        }

        label {
            font-size: 14px;
            margin-bottom: 8px;
        }

        .form-control {
            font-size: 16px;
            padding: 10px;
        }

        .btn {
            font-size: 20px;
            padding: 14px 18px;
            width: 100%;
            margin: 5px 0;
        }

        .btn-xs {
            font-size: 12px;
            padding: 6px 10px;
            width: auto;
        }

        .fee-type-item {
            flex-direction: column;
            align-items: stretch;
        }

        .fee-type-item .checkbox {
            margin-bottom: 10px;
        }

        .fee-type-item .checkbox label {
            font-size: 14px;
        }

        .alert {
            font-size: 13px;
            padding: 10px;
        }

        .text-right {
            text-align: center !important;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .action-buttons .btn {
            width: 100%;
            margin: 2px 0;
        }

        .fee-card {
            padding: 12px;
        }

        .fee-card-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }

        .fee-card-value {
            text-align: left;
            padding-left: 0;
        }

        .btn-dual {
            font-size: 12px;
            gap: 4px;
        }
    }

    /* Tablet devices */
    @media (min-width: 769px) and (max-width: 1024px) {
        body {
            font-size: 16px;
        }

        .table {
            font-size: 14px;
        }

        .btn {
            font-size: 15px;
        }
    }

    /* Desktop Large Screens */
    @media (min-width: 1200px) {
        body {
            font-size: 16px;
        }

        .panel-title {
            font-size: 18px;
        }

        .table {
            font-size: 15px;
        }

        .btn {
            font-size: 15px !important;
            padding: 8px 18px !important;
        }
    }

    /* Container padding */
    .container {
        padding-left: 15px;
        padding-right: 15px;
    }

    /* Panel spacing */
    .panel {
        margin-bottom: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .panel-body {
        padding: 20px;
    }

    /* Form group spacing */
    .form-group {
        margin-bottom: 20px;
    }

    /* Required field indicator */
    .text-danger {
        font-size: 14px;
    }

    /* Help text */
    .text-muted {
        font-size: 14px;
        margin-top: 5px;
        display: block;
    }

    /* Badge styling */
    .badge {
        font-size: 13px;
        padding: 5px 8px;
        margin-left: 8px;
    }

    /* Hover effects */
    .table tbody tr:hover {
        background-color: #f5f5f5 !important;
        cursor: pointer;
    }

    /* Touch-friendly elements */
    .btn,
    .form-control,
    select,
    .checkbox label {
        touch-action: manipulation;
        cursor: pointer;
    }

    /* Loading state */
    .glyphicon-spin {
        animation: spin 1s infinite linear;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(359deg);
        }
    }

    /* Responsive images */
    img {
        max-width: 100%;
        height: auto;
    }

    /* Print styles */
    @media print {
        .dual-label .ur-text {
            display: none;
        }

        .mobile-card-view {
            display: none;
        }

        .table.desktop-table {
            display: table;
        }
    }
    </style>
</head>

<body>

    <?php require_once('navbar.php'); ?>

    <!-- Dashboard -->
    <div class="container">
        <div class="row">
            <div class="col-xs-12 col-sm-12 col-md-12">

                <!-- Panel for Add/Edit Fee Type -->
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <div class="dual-label-heading">
                                <span class="en-text">
                                    <i class="glyphicon glyphicon-<?php echo $edit_mode ? 'edit' : 'plus-sign'; ?>"></i>
                                    <?php echo $edit_mode ? 'Edit Class Fee Type' : 'Add Class Fee Type'; ?>
                                </span>
                                <span class="divider">|</span>
                                <span class="ur-text">
                                    <?php echo $edit_mode ? 'کلاس فیس قسم میں ترمیم کریں' : 'کلاس فیس کی قسم شامل کریں'; ?>
                                    
                                </span>
                            </div>
                        </h3>
                    </div>
                    <div class="panel-body">

                        <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade in"
                            role="alert">
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                            <i
                                class="glyphicon glyphicon-<?php echo $_SESSION['message_type'] == 'success' ? 'ok-sign' : 'exclamation-sign'; ?>"></i>
                            <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message'], $_SESSION['message_type']);
                            ?>
                        </div>
                        <?php endif; ?>

                        <form method="post" action="" id="feeForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <?php if ($edit_mode): ?>
                            <input type="hidden" name="edit_mode" value="1">
                            <input type="hidden" name="old_class_id" value="<?php echo $fee_data['old_class_id']; ?>">
                            <input type="hidden" name="old_session_id"
                                value="<?php echo $fee_data['old_session_id']; ?>">
                            <?php endif; ?>

                            <div class="form-group">
                                <label>
                                    <div class="dual-label">
                                        <span class="en-text">

                                            Session

                                        </span>
                                        <span class="divider">|</span>
                                        <span class="ur-text">
                                            سیشن


                                        </span>
                                    </div>
                                </label>
                                <select name="session_id" id="session_id" class="form-control" required>
                                    <option value="">-- Select / منتخب کریں --</option>
                                    <?php foreach ($sessions as $id => $title): ?>
                                    <option value="<?php echo $id; ?>"
                                        <?php echo ($id == $fee_data['session_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <div class="dual-label">
                                        <span class="en-text">
                                            
                                            Class
                                            
                                        </span>
                                        <span class="divider">|</span>
                                        <span class="ur-text">
                                            کلاس
                                           
                                            
                                        </span>
                                    </div>
                                </label>
                                <select name="class_id" id="class_id" class="form-control" required>
                                    <option value="">-- Select / منتخب کریں --</option>
                                    <?php foreach ($classes as $id => $title): ?>
                                    <option value="<?php echo $id; ?>"
                                        <?php echo ($id == $fee_data['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>
                                    <div class="dual-label">
                                        <span class="en-text">Fee Type</span>
                                        <span class="divider">|</span>
                                        <span class="ur-text">
                                            فیس کی قسم
                                           
                                        </span>
                                    </div>
                                </label>
                                <div class="fee-types-container">
                                    <?php foreach ($fee_types as $id => $title): ?>
                                    <div class="fee-type-item">
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="fee_type_ids[]" value="<?php echo $id; ?>"
                                                    class="fee_type_checkbox"
                                                    <?php echo isset($fee_data['fee_types'][$id]) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($title); ?>
                                            </label>
                                        </div>
                                        <div class="amount-input">
                                            <input type="number" class="form-control" name="amounts[<?php echo $id; ?>]"
                                                step="0.01" min="0" placeholder="0.00"
                                                value="<?php echo isset($fee_data['fee_types'][$id]) ? htmlspecialchars($fee_data['fee_types'][$id]) : ''; ?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="dual-label">
                                    <small class="text-muted en-text">Amount must be a positive number!</small>
                                    <span class="divider" style="font-size: 12px;">|</span>
                                    <small class="text-muted ur-text" style="direction: rtl;">رقم ایک مثبت عدد ہونی
                                        چاہیے!</small>
                                </div>
                            </div>

                            <div class="text-right">
                                <button type="submit" class="btn btn-primary" id="submitBtn">
                                    <span class="btn-dual">
                                        <span>
                                            <i
                                                class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'ok'; ?>"></i>
                                            <?php echo $edit_mode ? 'Save' : 'Submit'; ?>
                                        </span>
                                        <span class="btn-divider">|</span>
                                        <span>
                                            <?php echo $edit_mode ? 'محفوظ کریں' : 'جمع کریں'; ?>
                                            <i
                                                class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'ok'; ?>"></i>
                                        </span>
                                    </span>
                                </button>

                                <?php if ($edit_mode): ?>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-default">
                                    <span class="btn-dual">
                                        <span>
                                            <i class="glyphicon glyphicon-remove"></i>
                                            Cancel
                                        </span>
                                        <span class="btn-divider">|</span>
                                        <span>
                                            منسوخ کریں
                                            <i class="glyphicon glyphicon-remove"></i>
                                        </span>
                                    </span>
                                </a>
                                <?php else: ?>
                                <button type="reset" class="btn btn-default" id="resetBtn">
                                    <span class="btn-dual">
                                        <span>
                                            <i class="glyphicon glyphicon-refresh"></i>
                                            Reset
                                        </span>
                                        <span class="btn-divider">|</span>
                                        <span>
                                            دوبارہ ترتیب دیں
                                            <i class="glyphicon glyphicon-refresh"></i>
                                        </span>
                                    </span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Panel for Fee Types List -->
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <h3 class="panel-title">
                            <div class="dual-label-heading">
                                <span class="en-text">
                                    <i class="glyphicon glyphicon-list"></i>
                                    Class Fee Types Management
                                </span>
                                <span class="divider">|</span>
                                <span class="ur-text">
                                    کلاس فیس اقسام کا انتظام
                                   
                                </span>
                            </div>
                        </h3>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($fee_list)): ?>
                        <?php foreach ($fee_list as $course_id => $course_data): ?>
                        <div class="course-header">
                            <div class="dual-label" style="flex: 1;">
                                <span class="en-text">
                                    <i class="glyphicon glyphicon-book"></i>
                                    Course: <?php echo htmlspecialchars($course_data['course_title']); ?>
                                </span>
                                <span class="divider">|</span>
                                <span class="ur-text">
                                    کورس: <?php echo htmlspecialchars($course_data['course_title']); ?>
                                    
                                </span>
                            </div>
                           
                        </div>

                        <!-- Desktop Table View -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover desktop-table">
                                <thead>
                                    <tr class="active">
                                        <th width="5%"># / نمبر</th>
                                        <th width="20%">
                                            <div class="dual-label">
                                                <span>Class</span>
                                                <span>کلاس</span>
                                            </div>
                                        </th>
                                        <th width="25%">
                                            <div class="dual-label">
                                                <span>Fee Type</span>
                                                <span>فیس کی قسم</span>
                                            </div>
                                        </th>
                                        <th width="15%">
                                            <div class="dual-label">
                                                <span>Session</span>
                                                <span>سیشن</span>
                                            </div>
                                        </th>
                                        <th width="15%">
                                            <div class="dual-label">
                                                <span>Amount</span>
                                                <span>رقم</span>
                                            </div>
                                        </th>
                                        <th width="20%">
                                            <div class="dual-label">
                                                <span>Actions</span>
                                                <span>اعمال</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; foreach ($course_data['fees'] as $row): ?>
                                    <tr
                                        style="background-color: <?php echo $course_colors[$course_id % count($course_colors)]; ?>;">
                                        <td><strong><?php echo $counter++; ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['class_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['fee_type_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['session_title'] ?? 'N/A'); ?></td>
                                        <td><strong><?php echo number_format($row['amount'], 2); ?></strong></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit_class_id=<?php echo $row['class_id']; ?>&edit_session_id=<?php echo $row['session_id']; ?>"
                                                    class="btn btn-xs btn-warning">
                                                    <i class="glyphicon glyphicon-edit"></i>
                                                    Edit | ترمیم
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Mobile Card View -->
                        <div class="mobile-card-view">
                            <?php $counter = 1; foreach ($course_data['fees'] as $row): ?>
                            <div class="fee-card"
                                style="background-color: <?php echo $course_colors[$course_id % count($course_colors)]; ?>;">
                               
                                <div class="fee-card-row">
                                    <div class="fee-card-label">
                                        Class
                                        <span class="en-label">کلاس</span>
                                    </div>
                                    <div class="fee-card-value"><?php echo htmlspecialchars($row['class_title']); ?>
                                    </div>
                                </div>
                                 
                                <div class="fee-card-row">
                                    <div class="fee-card-label">
                                        Fee Type
                                        <span class="en-label">فیس کی قسم</span>
                                    </div>
                                    <div class="fee-card-value"><?php echo htmlspecialchars($row['fee_type_title']); ?>
                                    </div>
                                </div>
                                <div class="fee-card-header">
                                    <span class="sr-no">Amount</span>
                                    <span class="amount"><?php echo number_format($row['amount'], 2); ?></span>
                                </div>
                                <div class="fee-card-row">
                                    <div class="fee-card-label">
                                        Session
                                        <span class="en-label">سیشن</span>
                                    </div>
                                    <div class="fee-card-value">
                                        <?php echo htmlspecialchars($row['session_title'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="fee-card-actions">
                                    <a href="?edit_class_id=<?php echo $row['class_id']; ?>&edit_session_id=<?php echo $row['session_id']; ?>"
                                        class="btn btn-warning">
                                        <i class="glyphicon glyphicon-edit"></i>
                                        Edit | ترمیم
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <div class="alert alert-info text-center">
                            <i class="glyphicon glyphicon-info-sign"></i>
                            <div class="dual-label" style="display: inline-flex;">
                                <span>No fee types found.</span>
                                <span class="divider">|</span>
                                <span>کوئی فیس قسم موجود نہیں۔</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);

        // Enable/disable class dropdown based on session selection
        $('#session_id').change(function() {
            if ($(this).val() !== '') {
                $('#class_id').prop('disabled', false);
            } else {
                $('#class_id').prop('disabled', true).val('');
                $('.fee_type_checkbox').prop('disabled', true).prop('checked', false);
                $('.fee_type_checkbox').closest('.fee-type-item').find('input[type="number"]').prop(
                    'disabled', true);
            }
        });

        // Enable/disable fee type checkboxes and amount inputs based on class selection
        $('#class_id').change(function() {
            if ($(this).val() !== '') {
                $('.fee_type_checkbox').prop('disabled', false);
                $('.fee_type_checkbox').closest('.fee-type-item').find('input[type="number"]').prop(
                    'disabled', false);
            } else {
                $('.fee_type_checkbox').prop('disabled', true).prop('checked', false);
                $('.fee_type_checkbox').closest('.fee-type-item').find('input[type="number"]').prop(
                    'disabled', true);
            }
        });

        // Enable/disable amount input based on checkbox state
        $(document).on('change', '.fee_type_checkbox', function() {
            var amountInput = $(this).closest('.fee-type-item').find('input[type="number"]');
            if ($(this).is(':checked')) {
                amountInput.prop('disabled', false);
                amountInput.focus();
            } else {
                amountInput.prop('disabled', true).val('');
            }
        });

        // Form validation before submit
        $('#feeForm').on('submit', function(e) {
            var hasChecked = false;
            var hasValidAmount = true;

            $('.fee_type_checkbox:checked').each(function() {
                hasChecked = true;
                var amount = $(this).closest('.fee-type-item').find('input[type="number"]')
                .val();
                if (amount === '' || parseFloat(amount) < 0) {
                    hasValidAmount = false;
                    return false;
                }
            });

            if (!hasChecked) {
                e.preventDefault();
                alert('At least one fee type must be selected!\nکم از کم ایک فیس قسم منتخب کرنی ہوگی!');
                return false;
            }

            if (!hasValidAmount) {
                e.preventDefault();
                alert('Amount must be a positive number!\nرقم ایک مثبت عدد ہونی چاہیے!');
                return false;
            }

            // Show loading indicator
            $('#submitBtn').html(
                '<span class="btn-dual"><span><i class="glyphicon glyphicon-refresh glyphicon-spin"></i> Processing...</span><span class="btn-divider">|</span><span>کارروائی جاری ہے...</span></span>'
                ).prop('disabled', true);
        });

        // Initialize form state on page load
        function initializeForm() {
            if ($('#session_id').val() !== '') {
                $('#class_id').prop('disabled', false);
                if ($('#class_id').val() !== '') {
                    $('.fee_type_checkbox').prop('disabled', false);
                    $('.fee_type_checkbox').each(function() {
                        var amountInput = $(this).closest('.fee-type-item').find(
                        'input[type="number"]');
                        if ($(this).is(':checked')) {
                            amountInput.prop('disabled', false);
                        } else {
                            amountInput.prop('disabled', true);
                        }
                    });
                }
            }
        }

        initializeForm();

        // Reset button handler
        $('#resetBtn').on('click', function(e) {
            e.preventDefault();
            $('#feeForm')[0].reset();
            $('.fee_type_checkbox').prop('disabled', true);
            $('.fee_type_checkbox').closest('.fee-type-item').find('input[type="number"]').prop(
                'disabled', true);
            $('#class_id').prop('disabled', true);
            initializeForm();
        });
    });
    </script>

</body>

</html>
<?php
// Close database connection
$conn->close();
?>