<?php 
// Set timezone to Pakistan (Asia/Karachi)
date_default_timezone_set('Asia/Karachi');

require_once('security.php');

// Language handling
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

// Process DELETE request for exam types
if (isset($_GET['delete_exam_type']) && is_numeric($_GET['delete_exam_type'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete_exam_type']);
    
    try {
        $check = $conn->prepare("SELECT id, name FROM exam_types WHERE id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Exam type not found!");
        }
        
        $exam_type = $result->fetch_assoc();
        $check->close();
        
        // Check if exam type is used in any exam
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM exams WHERE exam_type_id = ?");
        $check_usage->bind_param("i", $delete_id);
        $check_usage->execute();
        $usage_result = $check_usage->get_result();
        $usage = $usage_result->fetch_assoc();
        $check_usage->close();
        
        if ($usage['count'] > 0) {
            throw new Exception("Cannot delete! This exam type is used in {$usage['count']} exam(s). Please delete those exams first.");
        }
        
        $stmt = $conn->prepare("DELETE FROM exam_types WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Exam type deleted successfully.<br>امتحان کی قسم کامیابی سے حذف کر دی گئی۔</div>";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to delete exam type: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process DELETE request for exams
if (isset($_GET['delete_exam']) && is_numeric($_GET['delete_exam'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete_exam']);
    
    try {
        $conn->begin_transaction();
        
        $check = $conn->prepare("SELECT id, title FROM exams WHERE id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Exam not found!");
        }
        
        $exam = $result->fetch_assoc();
        $check->close();
        
        // Check if exam has marks
        $check_marks = $conn->prepare("SELECT COUNT(*) as count FROM marks WHERE arrange_exam_id = ?");
        $check_marks->bind_param("i", $delete_id);
        $check_marks->execute();
        $marks_result = $check_marks->get_result();
        $marks = $marks_result->fetch_assoc();
        $check_marks->close();
        
        if ($marks['count'] > 0) {
            // Soft delete - set status to 0
            $stmt = $conn->prepare("UPDATE exams SET status = 0 WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['message'] = "<div style='color: #31708f; background-color: #d9edf7; border: 1px solid #bce8f1; padding: 15px; border-radius: 4px;'><strong>✓ معلومات!</strong><br>Exam has marks, so it has been deactivated (soft delete).<br>امتحان میں نمبرات موجود ہیں، اس لیے اسے غیر فعال کر دیا گیا ہے۔</div>";
                $_SESSION['message_type'] = "info";
            } else {
                throw new Exception("Failed to deactivate exam: " . $stmt->error);
            }
        } else {
            // Hard delete
            $stmt = $conn->prepare("DELETE FROM exams WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Exam deleted successfully.<br>امتحان کامیابی سے حذف کر دیا گیا۔</div>";
                $_SESSION['message_type'] = "success";
            } else {
                throw new Exception("Failed to delete exam: " . $stmt->error);
            }
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process DELETE request for question types
if (isset($_GET['delete_question_type']) && is_numeric($_GET['delete_question_type'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete_question_type']);
    
    try {
        $check = $conn->prepare("SELECT id, name FROM question_types WHERE id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Question type not found!");
        }
        
        $question_type = $result->fetch_assoc();
        $check->close();
        
        // Check if question type is used in class_question_types
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM class_question_types WHERE question_type_id = ?");
        $check_usage->bind_param("i", $delete_id);
        $check_usage->execute();
        $usage_result = $check_usage->get_result();
        $usage = $usage_result->fetch_assoc();
        $check_usage->close();
        
        if ($usage['count'] > 0) {
            throw new Exception("Cannot delete! This question type is used in {$usage['count']} class assignment(s). Please remove those assignments first.");
        }
        
        $stmt = $conn->prepare("DELETE FROM question_types WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Question type deleted successfully.<br>سوال کی قسم کامیابی سے حذف کر دی گئی۔</div>";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to delete question type: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process DELETE request for class question types
if (isset($_GET['delete_class_qt']) && is_numeric($_GET['delete_class_qt'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete_class_qt']);
    
    try {
        $stmt = $conn->prepare("
            SELECT cqt.id, c.name as class_name, qt.name as question_name 
            FROM class_question_types cqt
            JOIN classes c ON cqt.class_id = c.id
            JOIN question_types qt ON cqt.question_type_id = qt.id
            WHERE cqt.id = ?
        ");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Class question type not found!");
        }
        
        $class_qt = $result->fetch_assoc();
        $stmt->close();
        
        // Check if used in marks
        $check_marks = $conn->prepare("SELECT COUNT(*) as count FROM marks WHERE class_question_type_id = ?");
        $check_marks->bind_param("i", $delete_id);
        $check_marks->execute();
        $marks_result = $check_marks->get_result();
        $marks = $marks_result->fetch_assoc();
        $check_marks->close();
        
        if ($marks['count'] > 0) {
            throw new Exception("Cannot delete! This class question type is used in {$marks['count']} mark entry(s).");
        }
        
        $del_stmt = $conn->prepare("DELETE FROM class_question_types WHERE id = ?");
        $del_stmt->bind_param("i", $delete_id);
        
        if ($del_stmt->execute()) {
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Class question type deleted successfully.<br>کلاس کے سوال کی قسم کامیابی سے حذف کر دی گئی۔</div>";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to delete class question type: " . $del_stmt->error);
        }
        $del_stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#class-question-types");
    exit();
}

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once('conn_inc.php');
    $conn->query("SET time_zone = '+05:00'");
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action == 'get_classes') {
        $result = $conn->query("SELECT id, name FROM classes WHERE status = 1 ORDER BY name");
        $classes = [];
        while ($row = $result->fetch_assoc()) {
            $classes[] = $row;
        }
        $response['success'] = true;
        $response['classes'] = $classes;
    }
    elseif ($action == 'get_class_question_types') {
        $class_id = intval($_POST['class_id']);
        $stmt = $conn->prepare("
            SELECT cqt.*, qt.name as question_type_name
            FROM class_question_types cqt
            JOIN question_types qt ON cqt.question_type_id = qt.id
            WHERE cqt.class_id = ?
            ORDER BY cqt.sort_order
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['items'] = $items;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Process form submission for Exam Type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam_type'])) {
    require_once('conn_inc.php');
    
    $name = trim($_POST['name']);
    $exam_type_id = isset($_POST['exam_type_id']) ? intval($_POST['exam_type_id']) : 0;
    $is_edit = ($exam_type_id > 0);
    
    if (empty($name)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Exam type name is required.<br>امتحان کی قسم کا نام ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE exam_types SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $exam_type_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO exam_types (name) VALUES (?)");
        $stmt->bind_param("s", $name);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Exam type " . ($is_edit ? 'updated' : 'saved') . " successfully.<br>امتحان کی قسم کامیابی سے " . ($is_edit ? 'اپ ڈیٹ' : 'محفوظ') . " کر دی گئی۔</div>";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Failed: " . $stmt->error . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process form submission for Exam
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam'])) {
    require_once('conn_inc.php');
    
    $exam_type_id = intval($_POST['exam_type_id']);
    $session_year = trim($_POST['session_year']);
    $title = trim($_POST['title']);
    $status = isset($_POST['status']) ? intval($_POST['status']) : 1;
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $is_edit = ($exam_id > 0);
    
    if (empty($exam_type_id) || empty($session_year)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Exam type and session year are required.<br>امتحان کی قسم اور سیشن کا سال ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE exams SET exam_type_id = ?, session_year = ?, title = ?, status = ? WHERE id = ?");
        $stmt->bind_param("issii", $exam_type_id, $session_year, $title, $status, $exam_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO exams (exam_type_id, session_year, title, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $exam_type_id, $session_year, $title, $status);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Exam " . ($is_edit ? 'updated' : 'saved') . " successfully.<br>امتحان کامیابی سے " . ($is_edit ? 'اپ ڈیٹ' : 'محفوظ') . " کر دیا گیا۔</div>";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Failed: " . $stmt->error . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process form submission for Question Type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_question_type'])) {
    require_once('conn_inc.php');
    
    $name = trim($_POST['name']);
    $question_type_id = isset($_POST['question_type_id']) ? intval($_POST['question_type_id']) : 0;
    $is_edit = ($question_type_id > 0);
    
    if (empty($name)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Question type name is required.<br>سوال کی قسم کا نام ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE question_types SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $question_type_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO question_types (name) VALUES (?)");
        $stmt->bind_param("s", $name);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Question type " . ($is_edit ? 'updated' : 'saved') . " successfully.<br>سوال کی قسم کامیابی سے " . ($is_edit ? 'اپ ڈیٹ' : 'محفوظ') . " کر دی گئی۔</div>";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Failed: " . $stmt->error . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process form submission for Class Question Type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_class_qt'])) {
    require_once('conn_inc.php');
    
    $class_id = intval($_POST['class_id']);
    $question_type_id = intval($_POST['question_type_id']);
    $max_marks = floatval($_POST['max_marks']);
    $sort_order = intval($_POST['sort_order']);
    $class_qt_id = isset($_POST['class_qt_id']) ? intval($_POST['class_qt_id']) : 0;
    $is_edit = ($class_qt_id > 0);
    
    if (empty($class_id) || empty($question_type_id) || $max_marks <= 0) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>All fields are required and max marks must be greater than 0.<br>تمام فیلڈز ضروری ہیں اور زیادہ سے زیادہ نمبر 0 سے زیادہ ہونے چاہئیں۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF'] . "#class-question-types");
        exit();
    }
    
    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE class_question_types SET class_id = ?, question_type_id = ?, max_marks = ?, sort_order = ? WHERE id = ?");
        $stmt->bind_param("iidii", $class_id, $question_type_id, $max_marks, $sort_order, $class_qt_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO class_question_types (class_id, question_type_id, max_marks, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iidi", $class_id, $question_type_id, $max_marks, $sort_order);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>Class question type " . ($is_edit ? 'updated' : 'saved') . " successfully.<br>کلاس کے سوال کی قسم کامیابی سے " . ($is_edit ? 'اپ ڈیٹ' : 'محفوظ') . " کر دی گئی۔</div>";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Failed: " . $stmt->error . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF'] . "#class-question-types");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once('meta_inc.php'); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <style>
        .form-control {
            font-size: 16px !important;
        }
        
        .table-responsive { overflow-x: auto; }
        
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
        
        .custom-alert {
            margin-bottom: 20px;
        }
        
        /* Tab styling */
        .nav-tabs {
            margin-bottom: 20px;
            border-bottom: 2px solid #337ab7;
        }
        .nav-tabs > li > a {
            font-size: 16px;
            font-weight: bold;
            color: #555;
            cursor: pointer;
        }
        .nav-tabs > li.active > a,
        .nav-tabs > li.active > a:hover,
        .nav-tabs > li.active > a:focus {
            background-color: #337ab7;
            color: white;
            border-color: #337ab7;
        }
        
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
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
            
            .dual-language-heading {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .nav-tabs > li {
                float: none;
                display: block;
            }
            .nav-tabs > li > a {
                margin-right: 0;
                border-radius: 4px;
            }
        }
        
        .btn-space {
            margin: 2px;
        }
        
        .panel {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <?php if (isset($_SESSION['message'])): ?>
                <div class="custom-alert">
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message'], $_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs" role="tablist" id="myTabs">
                <li role="presentation" class="active">
                    <a href="#exam-types" aria-controls="exam-types" role="tab" data-toggle="tab" onclick="switchTab('exam-types')">
                        امتحان کی اقسام / Exam Types
                    </a>
                </li>
                <li role="presentation">
                    <a href="#exams" aria-controls="exams" role="tab" data-toggle="tab" onclick="switchTab('exams')">
                        امتحانات / Exams
                    </a>
                </li>
                <li role="presentation">
                    <a href="#question-types" aria-controls="question-types" role="tab" data-toggle="tab" onclick="switchTab('question-types')">
                        سوالات کی اقسام / Question Types
                    </a>
                </li>
                <li role="presentation">
                    <a href="#class-question-types" aria-controls="class-question-types" role="tab" data-toggle="tab" onclick="switchTab('class-question-types')">
                        کلاس کے سوالات / Class Question Types
                    </a>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content">
                
                <!-- ═══════════════════════════════════════════ -->
                <!-- TAB 1: EXAM TYPES                         -->
                <!-- ═══════════════════════════════════════════ -->
                <div role="tabpanel" class="tab-pane active" id="tab-exam-types">
                    
                    <!-- Form Panel -->
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">
                                    <?php 
                                    echo isset($_GET['edit_exam_type']) ? 
                                        'Edit Exam Type / امتحان کی قسم میں ترمیم کریں' : 
                                        'Add New Exam Type / نئی امتحان کی قسم شامل کریں'; 
                                    ?>
                                </span>
                                <span class="urdu-label">
                                    <?php 
                                    echo isset($_GET['edit_exam_type']) ? 
                                        'امتحان کی قسم میں ترمیم' : 
                                        'نئی امتحان کی قسم'; 
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="">
                                <?php
                                $edit_exam_type = isset($_GET['edit_exam_type']);
                                $exam_type_data = ['id' => '', 'name' => ''];
                                
                                if ($edit_exam_type) {
                                    require_once('conn_inc.php');
                                    $id = intval($_GET['edit_exam_type']);
                                    $stmt = $conn->prepare("SELECT * FROM exam_types WHERE id = ?");
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $exam_type_data = $result->fetch_assoc();
                                    }
                                    $stmt->close();
                                }
                                ?>
                                
                                <input type="hidden" name="exam_type_id" value="<?php echo htmlspecialchars($exam_type_data['id']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-8 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="exam_type_name" class="en-field-label">Exam Type Name / امتحان کی قسم کا نام *</label>
                                                <label class="urdu-field-label">امتحان کی قسم کا نام</label>
                                            </div>
                                            <input type="text" class="form-control" id="exam_type_name" name="name" 
                                                   value="<?php echo htmlspecialchars($exam_type_data['name']); ?>" 
                                                   placeholder="e.g. Monthly Test, Mid-Term, Final Exam / مثال: ماہانہ ٹیسٹ، ششماہی، سالانہ امتحان"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-12">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="dual-button">
                                                <button type="submit" name="submit_exam_type" class="btn btn-success btn-lg btn-block">
                                                    <?php echo $edit_exam_type ? 'Update / اپ ڈیٹ کریں' : 'Save / محفوظ کریں'; ?>
                                                </button>
                                                <?php if ($edit_exam_type): ?>
                                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default btn-block">
                                                        Cancel / منسوخ کریں
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- List Panel -->
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">Exam Types List / امتحان کی اقسام کی فہرست</span>
                                <span class="urdu-label">امتحان کی اقسام کی فہرست</span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php
                            require_once('conn_inc.php');
                            $result = $conn->query("SELECT * FROM exam_types ORDER BY id DESC");
                            ?>

                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th># / نمبر</th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Name / نام</span>
                                                        <span class="urdu-field-label">نام</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Actions / کارروائیاں</span>
                                                        <span class="urdu-field-label">کارروائیاں</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td style="font-size: 16px !important;"><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td>
                                                        <a href="?edit_exam_type=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning btn-space" style="font-size: 14px;">
                                                            Edit / ترمیم کریں
                                                        </a>
                                                        <a href="?delete_exam_type=<?php echo $row['id']; ?>" 
                                                           class="btn btn-xs btn-danger btn-space" 
                                                           onclick="return confirm('Are you sure you want to delete this exam type?\n\nکیا آپ واقعی اس امتحان کی قسم کو حذف کرنا چاہتے ہیں؟\n\nName: <?php echo htmlspecialchars($row['name']); ?>');"
                                                           style="font-size: 14px;">
                                                            Delete / حذف کریں
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No exam types found. / کوئی امتحان کی قسم نہیں ملی۔
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════ -->
                <!-- TAB 2: EXAMS                              -->
                <!-- ═══════════════════════════════════════════ -->
                <div role="tabpanel" class="tab-pane" id="tab-exams">
                    
                    <!-- Form Panel -->
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">
                                    <?php 
                                    echo isset($_GET['edit_exam']) ? 
                                        'Edit Exam / امتحان میں ترمیم کریں' : 
                                        'Add New Exam / نیا امتحان شامل کریں'; 
                                    ?>
                                </span>
                                <span class="urdu-label">
                                    <?php 
                                    echo isset($_GET['edit_exam']) ? 
                                        'امتحان میں ترمیم' : 
                                        'نیا امتحان'; 
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="">
                                <?php
                                $edit_exam = isset($_GET['edit_exam']);
                                $exam_data = [
                                    'id' => '',
                                    'exam_type_id' => '',
                                    'session_year' => date('Y') . '-' . (date('Y') + 1),
                                    'title' => '',
                                    'status' => 1
                                ];
                                
                                if ($edit_exam) {
                                    require_once('conn_inc.php');
                                    $id = intval($_GET['edit_exam']);
                                    $stmt = $conn->prepare("SELECT * FROM exams WHERE id = ?");
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $exam_data = $result->fetch_assoc();
                                    }
                                    $stmt->close();
                                }
                                ?>
                                
                                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam_data['id']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-4 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="exam_type_id" class="en-field-label">Exam Type / امتحان کی قسم *</label>
                                                <label class="urdu-field-label">امتحان کی قسم</label>
                                            </div>
                                            <select class="form-control" id="exam_type_id" name="exam_type_id" required>
                                                <option value="">-- Select Exam Type / امتحان کی قسم منتخب کریں --</option>
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
                                            <div class="dual-field">
                                                <label for="session_year" class="en-field-label">Session Year / سیشن سال *</label>
                                                <label class="urdu-field-label">سیشن سال</label>
                                            </div>
                                            <input type="text" class="form-control" id="session_year" name="session_year" 
                                                   value="<?php echo htmlspecialchars($exam_data['session_year']); ?>" 
                                                   placeholder="e.g. 2026-27 / مثال: 2026-27"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="exam_title" class="en-field-label">Title / عنوان</label>
                                                <label class="urdu-field-label">عنوان</label>
                                            </div>
                                            <input type="text" class="form-control" id="exam_title" name="title" 
                                                   value="<?php echo htmlspecialchars($exam_data['title']); ?>" 
                                                   placeholder="e.g. First Monthly Test / مثال: پہلا ماہانہ ٹیسٹ">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="exam_status" class="en-field-label">Status / حالت</label>
                                                <label class="urdu-field-label">حالت</label>
                                            </div>
                                            <select class="form-control" id="exam_status" name="status">
                                                <option value="1" <?php echo ($exam_data['status'] == 1) ? 'selected' : ''; ?>>Active / فعال</option>
                                                <option value="0" <?php echo ($exam_data['status'] == 0) ? 'selected' : ''; ?>>Inactive / غیر فعال</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-8 col-sm-12">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="dual-button">
                                                <button type="submit" name="submit_exam" class="btn btn-success btn-lg">
                                                    <?php echo $edit_exam ? 'Update / اپ ڈیٹ کریں' : 'Save / محفوظ کریں'; ?>
                                                </button>
                                                <?php if ($edit_exam): ?>
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

                    <!-- List Panel -->
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">Exams List / امتحانات کی فہرست</span>
                                <span class="urdu-label">امتحانات کی فہرست</span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php
                            require_once('conn_inc.php');
                            $query = "SELECT e.*, et.name as exam_type_name 
                                      FROM exams e
                                      LEFT JOIN exam_types et ON e.exam_type_id = et.id
                                      WHERE e.status = 1
                                      ORDER BY e.session_year DESC, e.id DESC";
                            $result = $conn->query($query);
                            ?>

                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th># / نمبر</th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Exam Type / امتحان کی قسم</span>
                                                        <span class="urdu-field-label">امتحان کی قسم</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Session Year / سیشن سال</span>
                                                        <span class="urdu-field-label">سیشن سال</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Title / عنوان</span>
                                                        <span class="urdu-field-label">عنوان</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Actions / کارروائیاں</span>
                                                        <span class="urdu-field-label">کارروائیاں</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td style="font-size: 16px !important;">
                                                        <?php echo htmlspecialchars($row['exam_type_name'] ?? 'N/A'); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['session_year']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['title'] ?? '-'); ?></td>
                                                    <td>
                                                        <a href="?edit_exam=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning btn-space" style="font-size: 14px;">
                                                            Edit / ترمیم کریں
                                                        </a>
                                                        <a href="?delete_exam=<?php echo $row['id']; ?>" 
                                                           class="btn btn-xs btn-danger btn-space" 
                                                           onclick="return confirm('Are you sure you want to delete this exam?\n\nکیا آپ واقعی اس امتحان کو حذف کرنا چاہتے ہیں؟\n\nTitle: <?php echo htmlspecialchars($row['title'] ?? 'N/A'); ?>');"
                                                           style="font-size: 14px;">
                                                            Delete / حذف کریں
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No exams found. / کوئی امتحان نہیں ملا۔
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════ -->
                <!-- TAB 3: QUESTION TYPES                     -->
                <!-- ═══════════════════════════════════════════ -->
                <div role="tabpanel" class="tab-pane" id="tab-question-types">
                    
                    <!-- Form Panel -->
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">
                                    <?php 
                                    echo isset($_GET['edit_question_type']) ? 
                                        'Edit Question Type / سوال کی قسم میں ترمیم کریں' : 
                                        'Add New Question Type / نئی سوال کی قسم شامل کریں'; 
                                    ?>
                                </span>
                                <span class="urdu-label">
                                    <?php 
                                    echo isset($_GET['edit_question_type']) ? 
                                        'سوال کی قسم میں ترمیم' : 
                                        'نئی سوال کی قسم'; 
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="">
                                <?php
                                $edit_qt = isset($_GET['edit_question_type']);
                                $qt_data = ['id' => '', 'name' => ''];
                                
                                if ($edit_qt) {
                                    require_once('conn_inc.php');
                                    $id = intval($_GET['edit_question_type']);
                                    $stmt = $conn->prepare("SELECT * FROM question_types WHERE id = ?");
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $qt_data = $result->fetch_assoc();
                                    }
                                    $stmt->close();
                                }
                                ?>
                                
                                <input type="hidden" name="question_type_id" value="<?php echo htmlspecialchars($qt_data['id']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-8 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="question_type_name" class="en-field-label">Question Type Name / سوال کی قسم کا نام *</label>
                                                <label class="urdu-field-label">سوال کی قسم کا نام</label>
                                            </div>
                                            <input type="text" class="form-control" id="question_type_name" name="name" 
                                                   value="<?php echo htmlspecialchars($qt_data['name']); ?>" 
                                                   placeholder="e.g. السوال الاول, لہجہ, تقریر / مثال: السوال الاول، لہجہ، تقریر"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-sm-12">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="dual-button">
                                                <button type="submit" name="submit_question_type" class="btn btn-success btn-lg btn-block">
                                                    <?php echo $edit_qt ? 'Update / اپ ڈیٹ کریں' : 'Save / محفوظ کریں'; ?>
                                                </button>
                                                <?php if ($edit_qt): ?>
                                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default btn-block">
                                                        Cancel / منسوخ کریں
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- List Panel -->
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">Question Types List / سوالات کی اقسام کی فہرست</span>
                                <span class="urdu-label">سوالات کی اقسام کی فہرست</span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php
                            require_once('conn_inc.php');
                            $result = $conn->query("SELECT * FROM question_types ORDER BY id DESC");
                            ?>

                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th># / نمبر</th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Name / نام</span>
                                                        <span class="urdu-field-label">نام</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Actions / کارروائیاں</span>
                                                        <span class="urdu-field-label">کارروائیاں</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td style="font-size: 16px !important;"><?php echo htmlspecialchars($row['name']); ?></td>
                                                    <td>
                                                        <a href="?edit_question_type=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning btn-space" style="font-size: 14px;">
                                                            Edit / ترمیم کریں
                                                        </a>
                                                        <a href="?delete_question_type=<?php echo $row['id']; ?>" 
                                                           class="btn btn-xs btn-danger btn-space" 
                                                           onclick="return confirm('Are you sure you want to delete this question type?\n\nکیا آپ واقعی اس سوال کی قسم کو حذف کرنا چاہتے ہیں؟\n\nName: <?php echo htmlspecialchars($row['name']); ?>');"
                                                           style="font-size: 14px;">
                                                            Delete / حذف کریں
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No question types found. / کوئی سوال کی قسم نہیں ملی۔
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══════════════════════════════════════════ -->
                <!-- TAB 4: CLASS QUESTION TYPES               -->
                <!-- ═══════════════════════════════════════════ -->
                <div role="tabpanel" class="tab-pane" id="tab-class-question-types">
                    
                    <!-- Form Panel -->
                    <div class="panel panel-primary">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">
                                    <?php 
                                    echo isset($_GET['edit_class_qt']) ? 
                                        'Edit Class Question Type / کلاس کے سوال کی قسم میں ترمیم کریں' : 
                                        'Add Class Question Type / کلاس کے سوال کی قسم شامل کریں'; 
                                    ?>
                                </span>
                                <span class="urdu-label">
                                    <?php 
                                    echo isset($_GET['edit_class_qt']) ? 
                                        'کلاس کے سوال کی قسم میں ترمیم' : 
                                        'کلاس کے سوال کی قسم شامل کریں'; 
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <form method="post" action="#class-question-types">
                                <?php
                                $edit_class_qt = isset($_GET['edit_class_qt']);
                                $class_qt_data = [
                                    'id' => '',
                                    'class_id' => '',
                                    'question_type_id' => '',
                                    'max_marks' => '',
                                    'sort_order' => 0
                                ];
                                
                                if ($edit_class_qt) {
                                    require_once('conn_inc.php');
                                    $id = intval($_GET['edit_class_qt']);
                                    $stmt = $conn->prepare("SELECT * FROM class_question_types WHERE id = ?");
                                    $stmt->bind_param("i", $id);
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        $class_qt_data = $result->fetch_assoc();
                                    }
                                    $stmt->close();
                                }
                                ?>
                                
                                <input type="hidden" name="class_qt_id" value="<?php echo htmlspecialchars($class_qt_data['id']); ?>">
                                
                                <div class="row">
                                    <div class="col-md-3 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="class_id" class="en-field-label">Class / کلاس *</label>
                                                <label class="urdu-field-label">کلاس</label>
                                            </div>
                                            <select class="form-control" id="class_id" name="class_id" required>
                                                <option value="">-- Select Class / کلاس منتخب کریں --</option>
                                                <?php
                                                require_once('conn_inc.php');
                                                $classes = $conn->query("SELECT id, name FROM classes WHERE status = 1 ORDER BY name");
                                                if ($classes) {
                                                    while ($class = $classes->fetch_assoc()) {
                                                        $selected = ($class['id'] == $class_qt_data['class_id']) ? 'selected' : '';
                                                        echo "<option value='{$class['id']}' $selected>" . htmlspecialchars($class['name']) . "</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3 col-sm-12">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="qt_id" class="en-field-label">Question Type / سوال کی قسم *</label>
                                                <label class="urdu-field-label">سوال کی قسم</label>
                                            </div>
                                            <select class="form-control" id="qt_id" name="question_type_id" required>
                                                <option value="">-- Select Question Type / سوال کی قسم منتخب کریں --</option>
                                                <?php
                                                require_once('conn_inc.php');
                                                $qtypes = $conn->query("SELECT id, name FROM question_types ORDER BY name");
                                                if ($qtypes) {
                                                    while ($qt = $qtypes->fetch_assoc()) {
                                                        $selected = ($qt['id'] == $class_qt_data['question_type_id']) ? 'selected' : '';
                                                        echo "<option value='{$qt['id']}' $selected>" . htmlspecialchars($qt['name']) . "</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-6">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="max_marks" class="en-field-label">Max Marks / زیادہ سے زیادہ نمبر *</label>
                                                <label class="urdu-field-label">زیادہ سے زیادہ نمبر</label>
                                            </div>
                                            <input type="number" class="form-control" id="max_marks" name="max_marks" 
                                                   value="<?php echo htmlspecialchars($class_qt_data['max_marks']); ?>" 
                                                   step="0.01" min="0.01" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-6">
                                        <div class="form-group">
                                            <div class="dual-field">
                                                <label for="sort_order" class="en-field-label">Sort Order / ترتیب</label>
                                                <label class="urdu-field-label">ترتیب</label>
                                            </div>
                                            <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                                   value="<?php echo htmlspecialchars($class_qt_data['sort_order']); ?>" 
                                                   min="0">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-2 col-sm-12">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="dual-button">
                                                <button type="submit" name="submit_class_qt" class="btn btn-success btn-block">
                                                    <?php echo $edit_class_qt ? 'Update / اپ ڈیٹ کریں' : 'Save / محفوظ کریں'; ?>
                                                </button>
                                                <?php if ($edit_class_qt): ?>
                                                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>#class-question-types" class="btn btn-default btn-block">
                                                        Cancel / منسوخ کریں
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- List Panel -->
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <div class="dual-language-heading">
                                <span class="english-label">Class Question Types List / کلاس کے سوالات کی اقسام کی فہرست</span>
                                <span class="urdu-label">کلاس کے سوالات کی اقسام کی فہرست</span>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php
                            require_once('conn_inc.php');
                            $query = "SELECT cqt.*, c.name as class_name, qt.name as question_type_name
                                      FROM class_question_types cqt
                                      JOIN classes c ON cqt.class_id = c.id
                                      JOIN question_types qt ON cqt.question_type_id = qt.id
                                      ORDER BY c.name, cqt.sort_order";
                            $result = $conn->query($query);
                            ?>

                            <?php if ($result && $result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead>
                                            <tr>
                                                <th># / نمبر</th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Class / کلاس</span>
                                                        <span class="urdu-field-label">کلاس</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Question Type / سوال کی قسم</span>
                                                        <span class="urdu-field-label">سوال کی قسم</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Max Marks / زیادہ سے زیادہ نمبر</span>
                                                        <span class="urdu-field-label">زیادہ سے زیادہ نمبر</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Sort Order / ترتیب</span>
                                                        <span class="urdu-field-label">ترتیب</span>
                                                    </div>
                                                </th>
                                                <th>
                                                    <div class="dual-field">
                                                        <span class="en-field-label">Actions / کارروائیاں</span>
                                                        <span class="urdu-field-label">کارروائیاں</span>
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $counter++; ?></td>
                                                    <td><?php echo htmlspecialchars($row['class_name']); ?></td>
                                                    <td style="font-size: 16px !important;"><?php echo htmlspecialchars($row['question_type_name']); ?></td>
                                                    <td class="text-right"><?php echo number_format($row['max_marks'], 2); ?></td>
                                                    <td><?php echo $row['sort_order']; ?></td>
                                                    <td>
                                                        <a href="?edit_class_qt=<?php echo $row['id']; ?>#class-question-types" class="btn btn-xs btn-warning btn-space" style="font-size: 14px;">
                                                            Edit / ترمیم کریں
                                                        </a>
                                                        <a href="?delete_class_qt=<?php echo $row['id']; ?>" 
                                                           class="btn btn-xs btn-danger btn-space" 
                                                           onclick="return confirm('Are you sure you want to delete this class question type?\n\nکیا آپ واقعی اس کلاس کے سوال کی قسم کو حذف کرنا چاہتے ہیں؟');"
                                                           style="font-size: 14px;">
                                                            Delete / حذف کریں
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    No class question types found. / کوئی کلاس کے سوال کی قسم نہیں ملی۔
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div><!-- /tab-content -->

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
// Simple tab switching function
function switchTab(tabName) {
    // Hide all tab panes
    var panes = document.querySelectorAll('.tab-pane');
    panes.forEach(function(pane) {
        pane.classList.remove('active');
    });
    
    // Remove active class from all tabs
    var tabs = document.querySelectorAll('#myTabs li');
    tabs.forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Show the selected tab pane
    var selectedPane = document.getElementById('tab-' + tabName);
    if (selectedPane) {
        selectedPane.classList.add('active');
    }
    
    // Add active class to the clicked tab
    var clickedTab = document.querySelector('a[href="#' + tabName + '"]').parentElement;
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
    
    // Update URL hash
    if (history.pushState) {
        history.pushState(null, null, '#' + tabName);
    } else {
        window.location.hash = '#' + tabName;
    }
}

// On page load, check for hash
document.addEventListener('DOMContentLoaded', function() {
    var hash = window.location.hash;
    if (hash) {
        var tabName = hash.substring(1);
        if (tabName) {
            switchTab(tabName);
        }
    }
    
    // Auto-hide session alerts
    setTimeout(function() { 
        var alerts = document.querySelectorAll('.custom-alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.style.display = 'none'; }, 500);
        });
    }, 10000);
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>