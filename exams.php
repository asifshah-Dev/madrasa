<?php 
date_default_timezone_set('Asia/Karachi');
require_once('security.php');
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

// Process DELETE request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete']);
    
    try {
        $conn->begin_transaction();
        
        $check = $conn->prepare("SELECT id FROM exams WHERE id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Exam not found!");
        }
        $check->close();
        
        $check_marks = $conn->prepare("SELECT COUNT(*) as count FROM marks WHERE arrange_exam_id = ?");
        $check_marks->bind_param("i", $delete_id);
        $check_marks->execute();
        $marks_result = $check_marks->get_result();
        $marks = $marks_result->fetch_assoc();
        $check_marks->close();
        
        if ($marks['count'] > 0) {
            $stmt = $conn->prepare("UPDATE exams SET status = 0 WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['message'] = "<div style='color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✓ معلومات!</strong><br>امتحان میں نمبرات موجود ہیں، اس لیے اسے غیر فعال کر دیا گیا ہے۔</div>";
                $_SESSION['message_type'] = "info";
            }
        } else {
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px; text-align: right;'><strong>✓ کامیابی!</strong><br>امتحان کامیابی سے حذف کر دیا گیا۔</div>";
                $_SESSION['message_type'] = "success";
            }
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam'])) {
    require_once('conn_inc.php');
    
    $exam_type_id = intval($_POST['exam_type_id']);
    $class_ids = isset($_POST['class_id']) ? $_POST['class_id'] : [];
    $session_id = intval($_POST['session_id']);
    $start_date = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
    $status = 1;
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $is_edit = ($exam_id > 0);
    
    if (empty($exam_type_id) || empty($class_ids) || empty($session_id)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ خرابی!</strong><br>امتحان کی قسم، کلاس اور سیشن ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Get session year from session_id
    $session_stmt = $conn->prepare("SELECT title FROM sessions WHERE id = ?");
    $session_stmt->bind_param("i", $session_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    $session_row = $session_result->fetch_assoc();
    $session_year = $session_row['title'];
    $session_stmt->close();
    
    $conn->begin_transaction();
    
    try {
        if ($is_edit) {
            // First delete all existing entries for this exam group
            $delete_stmt = $conn->prepare("DELETE FROM exams WHERE exam_type_id = ? AND session_id = ? AND start_date = ?");
            $delete_stmt->bind_param("iis", $exam_type_id, $session_id, $start_date);
            $delete_stmt->execute();
            $delete_stmt->close();
            
            // Then insert all selected classes
            $stmt = $conn->prepare("INSERT INTO exams (exam_type_id, class_id, session_id, session_year, start_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($class_ids as $class_id) {
                $class_id = intval($class_id);
                if ($class_id > 0) {
                    $stmt->bind_param("iiissi", $exam_type_id, $class_id, $session_id, $session_year, $start_date, $status);
                    $stmt->execute();
                }
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO exams (exam_type_id, class_id, session_id, session_year, start_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($class_ids as $class_id) {
                $class_id = intval($class_id);
                if ($class_id > 0) {
                    $stmt->bind_param("iiissi", $exam_type_id, $class_id, $session_id, $session_year, $start_date, $status);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }
        
        $conn->commit();
        
        $count = count($class_ids);
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px; text-align: right;'><strong>✓ کامیابی!</strong><br>" . ($is_edit ? 'امتحان کامیابی سے اپ ڈیٹ کر دیا گیا۔' : "$count کلاسوں کے لیے امتحان کامیابی سے محفوظ کر دیا گیا۔") . "</div>";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ خرابی!</strong><br>" . $e->getMessage() . "</div>";
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
        body {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        
        .container {
            direction: rtl;
            text-align: right;
        }
        
        .form-control { 
            font-size: 18px !important; 
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            color: #333 !important;
            background-color: #fff !important;
            padding: 8px 12px !important;
            height: 46px !important;
            line-height: 1.5 !important;
        }
        
        .form-control option {
            color: #333 !important;
            background-color: #fff !important;
        }
        
        .form-control::placeholder {
            color: #999 !important;
            opacity: 1 !important;
        }
        
        .table-responsive { overflow-x: auto; }
        
        .table th, .table td {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 17px;
        }
        
        .panel-heading {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 20px;
            text-align: right;
        }
        
        .btn {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        
        .custom-alert { 
            margin-bottom: 20px;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 17px;
            text-align: right;
        }
        
        .form-group label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 17px;
            text-align: right;
            display: block;
        }
        
        /* Navigation - scrollable on mobile */
        .nav-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            margin-bottom: 20px;
            text-align: left;
            padding-bottom: 5px;
            direction: ltr;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .nav-scroll::-webkit-scrollbar {
            display: none;
        }
        
        .nav-scroll a {
            font-size: 16px;
            font-weight: bold;
            color: white;
            border: none;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
            white-space: nowrap;
        }
        
        .nav-scroll a:hover {
            opacity: 0.85;
            text-decoration: none;
            color: white;
        }
        
        .btn-exams { background-color: #5bc0de; }
        .btn-exam-types { background-color: #337ab7; }
        .btn-class-questions { background-color: #5cb85c; }
        
        .rtl-content { direction: rtl; text-align: right; }
        
        .urdu-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 22px;
            font-weight: normal;
            direction: rtl;
            text-align: right;
        }
        .urdu-field-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 17px;
            direction: rtl;
            color: #333;
            display: block;
            text-align: right;
        }
        .dual-button {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .dual-button .btn {
            min-width: 150px;
        }
        
        .btn-lg-custom { 
            font-size: 18px !important; 
            padding: 12px 25px !important; 
            font-weight: bold; 
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        
        .class-list { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 8px; 
        }
        .class-item { 
            flex: 0 0 auto; 
        }
        .class-item input[type="checkbox"] { 
            display: none; 
        }
        .class-item label {
            display: block;
            padding: 12px 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            background: #fff;
            font-size: 18px;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            cursor: pointer;
            transition: all 0.2s;
            user-select: none;
            text-align: center;
            min-width: 80px;
            color: #333;
        }
        .class-item label:hover { 
            border-color: #337ab7; 
            background: #f0f8ff; 
        }
        .class-item input:checked + label {
            background: #337ab7;
            color: white;
            border-color: #285e8e;
            font-weight: bold;
        }
        
        .btn-all {
            padding: 10px 20px;
            font-size: 16px;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .btn-select-all { 
            background: #5cb85c; 
            color: white; 
        }
        .btn-clear-all { 
            background: #d9534f; 
            color: white; 
            margin-left: 5px; 
        }
        
        /* Exam card styling */
        .exam-card {
            border: 2px solid #337ab7;
            border-radius: 6px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .exam-card-header {
            background: #337ab7;
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .exam-card-header .exam-title {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 22px;
        }
        .exam-card-header .exam-date {
            font-size: 16px;
            color: #d9edf7;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        .exam-card-body { 
            padding: 15px; 
            background: #fff; 
        }
        
        .rtl-table th { 
            text-align: right; 
            font-size: 16px; 
            font-weight: bold; 
        }
        .rtl-table td { 
            text-align: right; 
            font-size: 17px !important; 
            vertical-align: middle; 
        }
        
        .panel-heading.rtl { 
            text-align: right; 
        }
        select.form-control.rtl-select { 
            text-align: right; 
            direction: rtl; 
            padding: 8px 12px !important;
            height: 55px !important;
        }
        
        /* Mobile Card View for Exam List */
        .exam-mobile-card {
            display: none;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .exam-mobile-card .mobile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .exam-mobile-card .mobile-title {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 18px;
            font-weight: bold;
            color: #337ab7;
        }
        
        .exam-mobile-card .mobile-date {
            font-size: 14px;
            color: #666;
        }
        
        .exam-mobile-card .class-item-mobile {
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .exam-mobile-card .class-item-mobile:last-child {
            border-bottom: none;
        }
        
        .exam-mobile-card .class-name {
            font-size: 16px;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        
        .exam-mobile-card .mobile-actions {
            display: flex;
            gap: 8px;
        }
        
        .exam-mobile-card .mobile-actions .btn {
            font-size: 13px;
            padding: 5px 12px;
        }
        
        @media (max-width: 768px) {
            .nav-scroll a {
                font-size: 14px;
                padding: 8px 15px;
            }
            
            .dual-button { 
                flex-direction: column; 
                align-items: center; 
            }
            .dual-button .btn { 
                width: 100%; 
                min-width: unset;
            }
            .container { 
                padding-left: 10px; 
                padding-right: 10px; 
            }
            
            .panel-heading {
                font-size: 18px;
            }
            .urdu-label {
                font-size: 18px;
            }
            .urdu-field-label {
                font-size: 15px;
            }
            
            .form-control {
                font-size: 18px !important;
                color: #333 !important;
                background-color: #fff !important;
                padding: 8px 12px !important;
                height: 46px !important;
                line-height: 1.5 !important;
            }
            
            .form-control::placeholder {
                color: #999 !important;
                font-size: 16px !important;
            }
            
            select.form-control.rtl-select {
                font-size: 18px !important;
                padding: 8px 12px !important;
                height: 55px !important;
            }
            
            .class-item label {
                padding: 10px 14px;
                font-size: 16px;
                min-width: 60px;
                color: #333;
            }
            .class-list {
                gap: 6px;
            }
            .exam-card-header {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            .exam-card-header .exam-title {
                font-size: 18px;
            }
            
            /* Hide table on mobile */
            .exam-card .table-responsive {
                display: none;
            }
            
            /* Show mobile cards */
            .exam-mobile-card {
                display: block;
            }
            
            .exam-card .exam-card-body {
                padding: 0;
            }
            
            .btn-lg-custom {
                font-size: 18px !important;
                padding: 10px 20px !important;
            }
            
            .btn-all {
                font-size: 14px;
                padding: 8px 15px;
            }
        }
        
        @media (max-width: 480px) {
            .nav-scroll a {
                font-size: 12px;
                padding: 6px 12px;
                margin-right: 5px;
            }
            
            .panel-heading {
                font-size: 16px;
            }
            .urdu-label {
                font-size: 16px;
            }
            .urdu-field-label {
                font-size: 14px;
            }
            
            .form-control {
                font-size: 17px !important;
                color: #333 !important;
                background-color: #fff !important;
                padding: 8px 10px !important;
                height: 50px !important;
                line-height: 1.5 !important;
            }
            
            .form-control::placeholder {
                color: #999 !important;
                font-size: 15px !important;
            }
            
            select.form-control.rtl-select {
                font-size: 17px !important;
                padding: 8px 10px !important;
                height: 55px !important;
            }
            
            .custom-alert {
                font-size: 15px;
            }
            .dual-button .btn {
                font-size: 16px !important;
                padding: 10px 15px;
            }
            
            .class-item label {
                padding: 8px 12px;
                font-size: 15px;
                min-width: 50px;
                color: #333;
            }
            
            .exam-card-header .exam-title {
                font-size: 16px;
            }
            .exam-card-header .exam-date {
                font-size: 13px;
            }
            
            .exam-mobile-card .mobile-title {
                font-size: 16px;
            }
            .exam-mobile-card .class-name {
                font-size: 14px;
            }
            .exam-mobile-card .mobile-actions .btn {
                font-size: 12px;
                padding: 4px 10px;
            }
            
            .btn-lg-custom {
                font-size: 16px !important;
                padding: 10px 16px !important;
            }
            
            .btn-all {
                font-size: 13px;
                padding: 6px 12px;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <!-- Navigation - ORDER: Exams FIRST, Exam Types SECOND, Class Question Types THIRD -->
            <div class="nav-scroll">
                <a href="exams.php" class="btn-exams">امتحانات / Exams</a>
                <a href="exam_types.php" class="btn-exam-types">امتحان کی اقسام / Exam Types</a>
                <a href="class_question_types.php" class="btn-class-questions">کلاس کے سوالات / Class Question Types</a>
            </div>

            <div class="rtl-content">

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="custom-alert">
                        <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message'], $_SESSION['message_type']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="panel panel-primary">
                    <div class="panel-heading rtl">
                        <span class="urdu-label">
                            <?php echo isset($_GET['edit']) ? 'امتحان میں ترمیم' : 'نیا امتحان شامل کریں'; ?>
                        </span>
                    </div>
                    <div class="panel-body">
                        <form method="post" action="" id="examForm">
                            <?php
                            $edit_mode = isset($_GET['edit']);
                            $exam_data = [
                                'id' => '',
                                'exam_type_id' => '',
                                'class_id' => '',
                                'session_id' => '',
                                'session_year' => '',
                                'start_date' => ''
                            ];
                            $selected_classes = [];
                            
                            if ($edit_mode) {
                                require_once('conn_inc.php');
                                $id = intval($_GET['edit']);
                                
                                // Get the exam data
                                $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
                                $stmt->bind_param("i", $id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    $exam_data = $result->fetch_assoc();
                                }
                                $stmt->close();
                                
                                // Get all class IDs for this exam group
                                if ($exam_data['start_date']) {
                                    $class_stmt = $conn->prepare("SELECT class_id FROM exams WHERE exam_type_id = ? AND session_id = ? AND start_date = ? AND status = 1");
                                    $class_stmt->bind_param("iis", $exam_data['exam_type_id'], $exam_data['session_id'], $exam_data['start_date']);
                                } else {
                                    $class_stmt = $conn->prepare("SELECT class_id FROM exams WHERE exam_type_id = ? AND session_id = ? AND start_date IS NULL AND status = 1");
                                    $class_stmt->bind_param("ii", $exam_data['exam_type_id'], $exam_data['session_id']);
                                }
                                $class_stmt->execute();
                                $class_result = $class_stmt->get_result();
                                while ($row = $class_result->fetch_assoc()) {
                                    $selected_classes[] = $row['class_id'];
                                }
                                $class_stmt->close();
                            }
                            ?>
                            
                            <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_data['id']); ?>">
                            
                            <div class="row">
                                <div class="col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label for="exam_type_id" class="urdu-field-label">امتحان کی قسم *</label>
                                        <select class="form-control rtl-select" id="exam_type_id" name="exam_type_id" required>
                                            <option value="">-- امتحان کی قسم --</option>
                                            <?php
                                            require_once('conn_inc.php');
                                            $types = $conn->query("SELECT id, name FROM exam_types ORDER BY name");
                                            if ($types) {
                                                while ($type = $types->fetch_assoc()) {
                                                    $selected = ($type['id'] == $exam_data['exam_type_id']) ? 'selected' : '';
                                                    echo "<option value='{$type['id']}' $selected>" . htmlspecialchars($type['name']) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label for="session_id" class="urdu-field-label">سیشن *</label>
                                        <select class="form-control rtl-select" id="session_id" name="session_id" required>
                                            <option value="">-- سیشن منتخب کریں --</option>
                                            <?php
                                            require_once('conn_inc.php');
                                            $sessions = $conn->query("SELECT id, title FROM sessions ORDER BY id DESC");
                                            if ($sessions) {
                                                while ($session = $sessions->fetch_assoc()) {
                                                    $selected = ($session['id'] == $exam_data['session_id']) ? 'selected' : '';
                                                    echo "<option value='{$session['id']}' $selected>" . htmlspecialchars($session['title']) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 col-sm-12">
                                    <div class="form-group">
                                        <label for="start_date" class="urdu-field-label">تاریخ آغاز</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo htmlspecialchars($exam_data['start_date'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="urdu-field-label">کلاس منتخب کریں *</label>
                                        <div style="margin-bottom: 8px;">
                                            <button type="button" class="btn-all btn-clear-all" id="btnClearAll">صاف کریں</button>
                                            <button type="button" class="btn-all btn-select-all" id="btnSelectAll">تمام کلاسز</button>
                                        </div>
                                        <div class="class-list">
                                            <?php
                                            require_once('conn_inc.php');
                                            $classes = $conn->query("
                                                SELECT c.id, c.title as class_name, co.title as course_name 
                                                FROM classes c
                                                LEFT JOIN courses co ON c.course_id = co.id
                                                ORDER BY co.title, c.title
                                            ");
                                            if ($classes) {
                                                while ($class = $classes->fetch_assoc()) {
                                                    $display_name = $class['course_name'] ? $class['class_name'] . ' (' . $class['course_name'] . ')' : $class['class_name'];
                                                    $checked = ($edit_mode && in_array($class['id'], $selected_classes)) ? 'checked' : '';
                                                    echo '<div class="class-item">';
                                                    echo '<input type="checkbox" id="class_' . $class['id'] . '" name="class_id[]" value="' . $class['id'] . '" ' . $checked . '>';
                                                    echo '<label for="class_' . $class['id'] . '">' . htmlspecialchars($display_name) . '</label>';
                                                    echo '</div>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="dual-button">
                                        <button type="submit" name="submit_exam" class="btn btn-success btn-lg-custom">
                                            <span class="glyphicon glyphicon-floppy-disk"></span> 
                                            <?php echo $edit_mode ? 'اپ ڈیٹ کریں' : 'محفوظ کریں'; ?>
                                        </button>
                                        <?php if ($edit_mode): ?>
                                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default btn-lg-custom">
                                                منسوخ کریں
                                            </a>
                                        <?php else: ?>
                                            <button type="reset" class="btn btn-default btn-lg-custom">
                                                دوبارہ ترتیب دیں
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Exam List - Grouped by Exam Type + Date -->
                <div class="panel panel-info">
                    <div class="panel-heading rtl">
                        <span class="urdu-label">امتحانات کی فہرست</span>
                    </div>
                    <div class="panel-body">
                        <?php
                        require_once('conn_inc.php');
                        
                        // Get distinct exam groups
                        $group_query = "
                            SELECT DISTINCT e.exam_type_id, et.name as exam_type_name, e.start_date, e.session_id, s.title as session_title
                            FROM exams e
                            LEFT JOIN exam_types et ON e.exam_type_id = et.id
                            LEFT JOIN sessions s ON e.session_id = s.id
                            WHERE e.status = 1
                            ORDER BY e.start_date DESC, e.session_id DESC, et.name
                        ";
                        $group_result = $conn->query($group_query);
                        
                        if ($group_result && $group_result->num_rows > 0):
                            $exam_counter = 1;
                            while ($group_row = $group_result->fetch_assoc()):
                                $start_date_display = $group_row['start_date'] ? date('d-m-Y', strtotime($group_row['start_date'])) : '-';
                                
                                // Get classes for this exam group
                                if ($group_row['start_date']) {
                                    $class_stmt = $conn->prepare("
                                        SELECT e.id, c.title as class_name, co.title as course_name
                                        FROM exams e
                                        LEFT JOIN classes c ON e.class_id = c.id
                                        LEFT JOIN courses co ON c.course_id = co.id
                                        WHERE e.exam_type_id = ? 
                                        AND e.session_id = ? 
                                        AND e.start_date = ?
                                        AND e.status = 1
                                        ORDER BY co.title, c.title
                                    ");
                                    $class_stmt->bind_param("iis", $group_row['exam_type_id'], $group_row['session_id'], $group_row['start_date']);
                                } else {
                                    $class_stmt = $conn->prepare("
                                        SELECT e.id, c.title as class_name, co.title as course_name
                                        FROM exams e
                                        LEFT JOIN classes c ON e.class_id = c.id
                                        LEFT JOIN courses co ON c.course_id = co.id
                                        WHERE e.exam_type_id = ? 
                                        AND e.session_id = ? 
                                        AND e.start_date IS NULL
                                        AND e.status = 1
                                        ORDER BY co.title, c.title
                                    ");
                                    $class_stmt->bind_param("ii", $group_row['exam_type_id'], $group_row['session_id']);
                                }
                                $class_stmt->execute();
                                $class_result = $class_stmt->get_result();
                                
                                // Get first exam ID for edit button
                                $first_exam = $class_result->fetch_assoc();
                                $first_exam_id = $first_exam ? $first_exam['id'] : 0;
                                $class_result->data_seek(0); // Reset pointer
                        ?>
                        
                        <div class="exam-card">
                            <div class="exam-card-header">
                                <div>
                                    <span class="exam-title">
                                        <?php echo $exam_counter++; ?>. 
                                        <?php echo htmlspecialchars($group_row['exam_type_name'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="exam-date" style="margin-right: 20px;">
                                        📅 <?php echo $start_date_display; ?> | 📚 <?php echo htmlspecialchars($group_row['session_title'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                <?php if ($first_exam_id > 0): ?>
                                <a href="?edit=<?php echo $first_exam_id; ?>" class="btn btn-warning btn-sm" style="font-size: 16px; padding: 8px 20px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                    <span class="glyphicon glyphicon-edit"></span> ترمیم کریں
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="exam-card-body">
                                <?php if ($class_result && $class_result->num_rows > 0): ?>
                                    <!-- Desktop Table View -->
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped rtl-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>کلاس</th>
                                                    <th>کارروائی</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $class_counter = 1; while ($class_row = $class_result->fetch_assoc()): 
                                                    $class_display = $class_row['class_name'];
                                                    if ($class_row['course_name']) {
                                                        $class_display .= ' (' . $class_row['course_name'] . ')';
                                                    }
                                                ?>
                                                    <tr>
                                                        <td><?php echo $class_counter++; ?></td>
                                                        <td style="font-size: 17px;"><?php echo htmlspecialchars($class_display); ?></td>
                                                        <td>
                                                            <a href="?delete=<?php echo $class_row['id']; ?>" 
                                                               class="btn btn-xs btn-danger" 
                                                               onclick="return confirm('کیا آپ واقعی اس امتحان کو حذف کرنا چاہتے ہیں؟');"
                                                               style="font-size: 14px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                                                حذف کریں
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Mobile Card View -->
                                    <div class="exam-mobile-card">
                                        <div class="mobile-header">
                                            <span class="mobile-title">
                                                <?php echo htmlspecialchars($group_row['exam_type_name'] ?? 'N/A'); ?>
                                            </span>
                                            <span class="mobile-date">
                                                📅 <?php echo $start_date_display; ?> | 📚 <?php echo htmlspecialchars($group_row['session_title'] ?? 'N/A'); ?>
                                            </span>
                                        </div>
                                        <?php 
                                        $class_result->data_seek(0);
                                        $class_counter = 1; 
                                        while ($class_row = $class_result->fetch_assoc()): 
                                            $class_display = $class_row['class_name'];
                                            if ($class_row['course_name']) {
                                                $class_display .= ' (' . $class_row['course_name'] . ')';
                                            }
                                        ?>
                                            <div class="class-item-mobile">
                                                <span class="class-name">
                                                    <?php echo $class_counter++; ?>. <?php echo htmlspecialchars($class_display); ?>
                                                </span>
                                                <div class="mobile-actions">
                                                    <a href="?delete=<?php echo $class_row['id']; ?>" 
                                                       class="btn btn-xs btn-danger" 
                                                       onclick="return confirm('کیا آپ واقعی اس امتحان کو حذف کرنا چاہتے ہیں؟');"
                                                       style="font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                                        حذف کریں
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    
                                <?php else: ?>
                                    <div style="text-align: center; padding: 15px; color: #999; font-size: 16px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                        کوئی کلاس نہیں
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php 
                                $class_stmt->close();
                            endwhile;
                        else:
                        ?>
                            <div class="alert alert-info" style="padding: 20px; text-align: center; font-size: 18px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                کوئی امتحان نہیں ملا۔
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    
    $('#btnSelectAll').on('click', function() {
        $('.class-item input[type="checkbox"]').prop('checked', true);
    });
    
    $('#btnClearAll').on('click', function() {
        $('.class-item input[type="checkbox"]').prop('checked', false);
    });
    
    $('#examForm').on('submit', function(e) {
        var examType = $('#exam_type_id').val();
        var sessionId = $('#session_id').val();
        var checkedClasses = $('.class-item input[type="checkbox"]:checked').length;
        
        if (!examType) {
            e.preventDefault();
            alert('براہ کرم امتحان کی قسم منتخب کریں');
            return false;
        }
        
        if (!sessionId) {
            e.preventDefault();
            alert('براہ کرم سیشن منتخب کریں');
            return false;
        }
        
        if (checkedClasses === 0) {
            e.preventDefault();
            alert('براہ کرم کم از کم ایک کلاس منتخب کریں');
            return false;
        }
        
        return true;
    });
    
    setTimeout(function() { $('.custom-alert').fadeOut('slow'); }, 10000);
    
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>