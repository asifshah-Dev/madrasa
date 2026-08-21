<?php 
date_default_timezone_set('Asia/Karachi');
require_once('security.php');
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

// Process DELETE request (soft delete - set status to 1)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    require_once('conn_inc.php');
    
    $delete_id = intval($_GET['delete']);
    
    try {
        $stmt = $conn->prepare("SELECT cqt.id FROM class_question_types cqt WHERE cqt.id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Class question type not found!");
        }
        $stmt->close();
        
        $update_stmt = $conn->prepare("UPDATE class_question_types SET status = 1 WHERE id = ?");
        $update_stmt->bind_param("i", $delete_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px; text-align: right;'><strong>✓ کامیابی!</strong><br>سوال کی قسم کامیابی سے حذف کر دی گئی۔</div>";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("Failed to delete: " . $update_stmt->error);
        }
        $update_stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ Delete Failed!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once('conn_inc.php');
    $conn->query("SET time_zone = '+05:00'");
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action == 'get_question_types') {
        $result = $conn->query("SELECT id, name, input_type FROM question_types ORDER BY name");
        $types = [];
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        $response['success'] = true;
        $response['types'] = $types;
    }
    elseif ($action == 'get_class_question_types') {
        $class_id = intval($_POST['class_id']);
        $stmt = $conn->prepare("
            SELECT cqt.*, qt.name as question_type_name, qt.input_type
            FROM class_question_types cqt
            JOIN question_types qt ON cqt.question_type_id = qt.id
            WHERE cqt.class_id = ? AND cqt.status = 0
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_class_qt'])) {
    require_once('conn_inc.php');
    
    $class_id = intval($_POST['class_id']);
    $question_type_ids = isset($_POST['question_type_id']) ? $_POST['question_type_id'] : [];
    $max_marks = isset($_POST['max_marks']) ? $_POST['max_marks'] : [];
    $sort_orders = isset($_POST['sort_order']) ? $_POST['sort_order'] : [];
    $existing_ids = isset($_POST['existing_id']) ? $_POST['existing_id'] : [];
    
    if (empty($class_id) || empty($question_type_ids)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ خرابی!</strong><br>کلاس اور کم از کم ایک سوال کی قسم ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $clean_qt_ids = [];
    $clean_marks = [];
    $clean_sorts = [];
    $clean_existing_ids = [];
    
    foreach ($question_type_ids as $index => $qt_id) {
        $qt_id = intval($qt_id);
        if ($qt_id > 0) {
            $clean_qt_ids[] = $qt_id;
            $marks = isset($max_marks[$index]) ? floatval($max_marks[$index]) : 0;
            $clean_marks[] = $marks;
            $sort = isset($sort_orders[$index]) ? intval($sort_orders[$index]) : count($clean_qt_ids) - 1;
            $clean_sorts[] = $sort;
            $existing_id = isset($existing_ids[$index]) ? intval($existing_ids[$index]) : 0;
            $clean_existing_ids[] = $existing_id;
        }
    }
    
    if (empty($clean_qt_ids)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px; text-align: right;'><strong>✗ خرابی!</strong><br>کم از کم ایک سوال کی قسم منتخب کریں۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $conn->begin_transaction();
    
    try {
        $qt_input_types = [];
        $type_stmt = $conn->prepare("SELECT id, input_type FROM question_types");
        $type_stmt->execute();
        $type_result = $type_stmt->get_result();
        while ($type_row = $type_result->fetch_assoc()) {
            $qt_input_types[$type_row['id']] = $type_row['input_type'];
        }
        $type_stmt->close();
        
        $current_records = [];
        $current_stmt = $conn->prepare("SELECT id, question_type_id, max_marks, sort_order FROM class_question_types WHERE class_id = ? AND status = 0");
        $current_stmt->bind_param("i", $class_id);
        $current_stmt->execute();
        $current_result = $current_stmt->get_result();
        while ($row = $current_result->fetch_assoc()) {
            $current_records[$row['id']] = $row;
        }
        $current_stmt->close();
        
        $submitted_existing_ids = [];
        
        $update_stmt = $conn->prepare("UPDATE class_question_types SET question_type_id = ?, max_marks = ?, sort_order = ?, status = 0 WHERE id = ?");
        $soft_delete_stmt = $conn->prepare("UPDATE class_question_types SET status = 1 WHERE id = ? AND status = 0");
        $insert_stmt = $conn->prepare("INSERT INTO class_question_types (class_id, question_type_id, max_marks, sort_order, status) VALUES (?, ?, ?, ?, 0)");
        
        foreach ($clean_qt_ids as $index => $qt_id) {
            $input_type = isset($qt_input_types[$qt_id]) ? $qt_input_types[$qt_id] : 'marks';
            
            if ($input_type == 'boolean') {
                $marks = 0;
            } else {
                $marks = $clean_marks[$index];
            }
            
            $sort = $clean_sorts[$index];
            $existing_id = $clean_existing_ids[$index];
            
            if ($existing_id > 0 && isset($current_records[$existing_id])) {
                $current = $current_records[$existing_id];
                $submitted_existing_ids[] = $existing_id;
                
                if ($current['question_type_id'] != $qt_id || $current['max_marks'] != $marks || $current['sort_order'] != $sort) {
                    $soft_delete_stmt->bind_param("i", $existing_id);
                    $soft_delete_stmt->execute();
                    
                    $insert_stmt->bind_param("iidi", $class_id, $qt_id, $marks, $sort);
                    $insert_stmt->execute();
                } else {
                    $update_stmt->bind_param("idii", $qt_id, $marks, $sort, $existing_id);
                    $update_stmt->execute();
                }
            } else {
                $insert_stmt->bind_param("iidi", $class_id, $qt_id, $marks, $sort);
                $insert_stmt->execute();
            }
        }
        
        foreach ($current_records as $existing_id => $record) {
            if (!in_array($existing_id, $submitted_existing_ids)) {
                $soft_delete_stmt->bind_param("i", $existing_id);
                $soft_delete_stmt->execute();
            }
        }
        
        $update_stmt->close();
        $soft_delete_stmt->close();
        $insert_stmt->close();
        
        $conn->commit();
        
        $count = count($clean_qt_ids);
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px; text-align: right;'><strong>✓ کامیابی!</strong><br>$count سوالات کی اقسام کامیابی سے محفوظ کر دی گئیں۔</div>";
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
        .form-control { font-size: 18px !important; }
        .table-responsive { overflow-x: auto; }
        
        .rtl-content { direction: rtl; text-align: right; }
        
        .urdu-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 24px;
            font-weight: normal;
            direction: rtl;
            text-align: right;
        }
        .urdu-field-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 18px;
            direction: rtl;
            color: #333;
            display: block;
            text-align: right;
        }
        .dual-button {
            display: flex;
            justify-content: flex-start;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .custom-alert { margin-bottom: 20px; text-align: right; font-size: 16px; }
        
        .question-type-row {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn-lg-custom { font-size: 18px !important; padding: 12px 25px !important; font-weight: bold; }
        .btn-add-row { font-size: 18px !important; padding: 12px 25px !important; }
        .btn-remove-row { font-size: 16px !important; padding: 8px 16px !important; }
        
        .boolean-display {
            background-color: #dff0d8 !important;
            color: #3c763d;
            font-weight: bold;
            text-align: center;
            font-size: 18px !important;
        }
        
        .input-type-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: bold;
        }
        .badge-marks { background-color: #d9edf7; color: #31708f; }
        .badge-boolean { background-color: #dff0d8; color: #3c763d; }
        
        .class-card {
            border: 2px solid #337ab7;
            border-radius: 6px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .class-card-header {
            background: #337ab7;
            color: white;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .class-card-header .class-title {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 22px;
        }
        .class-card-body { padding: 15px; background: #fff; }
        .no-questions { text-align: center; padding: 20px; color: #999; font-size: 18px; }
        
        .rtl-table th { text-align: right; font-size: 18px; font-weight: bold; }
        .rtl-table td { text-align: right; font-size: 17px !important; vertical-align: middle; }
        .rtl-table td.number-cell { text-align: left !important; }
        
        .panel-heading.rtl { text-align: right; }
        select.form-control.rtl-select { text-align: right; direction: rtl; }
        select.form-control.rtl-select option { direction: rtl; text-align: right; }
        .alert-info.rtl { text-align: center; font-size: 18px; }
        
        /* Navigation buttons styling */
        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: nowrap;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 5px;
            margin-bottom: 20px;
            /* Hide scrollbar */
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
        }
        
        .nav-buttons::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        .nav-buttons a {
            white-space: nowrap;
            flex-shrink: 0;
            font-size: 16px;
            font-weight: bold;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        
        .nav-btn-exams { background-color: #5bc0de; color: white; }
        .nav-btn-exam-types { background-color: #337ab7; color: white; }
        .nav-btn-question-types { background-color: #f0ad4e; color: white; }
        
        /* Mobile card view for table */
        .mobile-cards { display: none; }
        
        @media (max-width: 768px) {
            .dual-button { flex-direction: column; gap: 10px; }
            .dual-button button { width: 100%; margin: 5px 0; }
            .urdu-label { font-size: 20px; }
            .class-card-header { flex-direction: column; gap: 10px; text-align: center; }
            .class-card-header .class-title { font-size: 18px; }
            
            /* Hide table, show cards on mobile */
            .desktop-table { display: none; }
            .mobile-cards { display: block; }
            
            .question-card {
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin-bottom: 12px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                cursor: pointer;
                transition: all 0.3s ease;
                overflow: hidden;
            }
            
            .question-card-header {
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #f8f9fa;
                border-radius: 8px;
            }
            
            .question-card-header .card-number {
                font-weight: bold;
                color: #337ab7;
                font-size: 18px;
            }
            
            .question-card-header .card-title {
                font-weight: bold;
                color: #333;
                font-size: 17px;
            }
            
            .question-card-header .card-toggle {
                color: #337ab7;
                font-size: 20px;
                transition: transform 0.3s ease;
            }
            
            .question-card-header .card-toggle.open {
                transform: rotate(180deg);
            }
            
            .question-card-details {
                display: none;
                padding: 15px;
                border-top: 1px solid #eee;
            }
            
            .question-card-details.show {
                display: block;
            }
            
            .question-card-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .question-card-row:last-child {
                border-bottom: none;
            }
            
            .question-card-label {
                font-weight: bold;
                color: #555;
                font-size: 16px;
            }
            
            .question-card-value {
                color: #333;
                font-size: 16px;
                text-align: left;
            }
            
            .question-card-actions {
                margin-top: 12px;
                padding-top: 12px;
                border-top: 2px solid #eee;
                text-align: center;
            }
            
            .question-card-actions .btn {
                font-size: 15px;
                padding: 8px 20px;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <!-- Navigation buttons with correct order: Exams, Exam Types, Question Types -->
            <div class="nav-buttons">
                <a href="exams.php" class="nav-btn-exams">
                    امتحانات / Exams
                </a>
                <a href="exam_types.php" class="nav-btn-exam-types">
                    امتحان کی اقسام / Exam Types
                </a>
                <a href="question_types.php" class="nav-btn-question-types">
                    سوالات کی اقسام / Question Types
                </a>
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
                        <span class="urdu-label">کلاس کو سوالات کی اقسام تفویض کریں</span>
                    </div>
                    <div class="panel-body">
                        <form method="post" action="" id="classQtForm">
                            
                            <div class="row">
                                <div class="col-md-6 col-sm-12">
                                    <div class="form-group">
                                        <label for="class_id" class="urdu-field-label">کلاس منتخب کریں *</label>
                                        <select class="form-control rtl-select" id="class_id" name="class_id" required style="height: 48px;">
                                            <option value="">-- کلاس منتخب کریں --</option>
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
                                                    echo "<option value='{$class['id']}'>" . htmlspecialchars($display_name) . "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="questionTypesContainer">
                                <div class="alert alert-info rtl" id="noClassSelected" style="padding: 20px;">
                                    براہ کرم پہلے کلاس منتخب کریں
                                </div>
                            </div>
                            
                            <div class="row" id="btnAddRow" style="display: none;">
                                <div class="col-md-12" style="margin-bottom: 20px;">
                                    <button type="button" class="btn btn-primary btn-add-row" id="btnAddQuestionType">
                                        <span class="glyphicon glyphicon-plus"></span> سوال کی قسم شامل کریں
                                    </button>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row" id="btnSubmitRow" style="display: none;">
                                <div class="col-md-12">
                                    <div class="dual-button">
                                        <button type="submit" name="submit_class_qt" class="btn btn-success btn-lg-custom" id="btnSubmit">
                                            <span class="glyphicon glyphicon-floppy-disk"></span> سب محفوظ کریں
                                        </button>
                                        <button type="button" class="btn btn-default btn-lg-custom" onclick="window.location.reload();">
                                            <span class="glyphicon glyphicon-refresh"></span> دوبارہ ترتیب دیں
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="panel panel-info">
                    <div class="panel-heading rtl">
                        <span class="urdu-label">کلاس کے سوالات کی اقسام کی فہرست</span>
                    </div>
                    <div class="panel-body">
                        <?php
                        require_once('conn_inc.php');
                        
                        $class_query = "
                            SELECT DISTINCT c.id, c.title as class_name, co.title as course_name
                            FROM class_question_types cqt
                            JOIN classes c ON cqt.class_id = c.id
                            LEFT JOIN courses co ON c.course_id = co.id
                            WHERE cqt.status = 0
                            ORDER BY co.title, c.title
                        ";
                        $class_result = $conn->query($class_query);
                        
                        if ($class_result && $class_result->num_rows > 0):
                            while ($class_row = $class_result->fetch_assoc()):
                                $class_display = $class_row['class_name'];
                                if ($class_row['course_name']) {
                                    $class_display .= ' (' . $class_row['course_name'] . ')';
                                }
                                
                                $qt_stmt = $conn->prepare("
                                    SELECT cqt.*, qt.name as question_type_name, qt.input_type
                                    FROM class_question_types cqt
                                    JOIN question_types qt ON cqt.question_type_id = qt.id
                                    WHERE cqt.class_id = ? AND cqt.status = 0
                                    ORDER BY cqt.sort_order
                                ");
                                $qt_stmt->bind_param("i", $class_row['id']);
                                $qt_stmt->execute();
                                $qt_result = $qt_stmt->get_result();
                        ?>
                        
                        <div class="class-card">
                            <div class="class-card-header">
                                <span class="class-title"><?php echo htmlspecialchars($class_display); ?></span>
                                <button type="button" class="btn btn-warning btn-sm btn-edit-class" 
                                        data-class-id="<?php echo $class_row['id']; ?>"
                                        style="font-size: 17px; padding: 10px 24px;">
                                    <span class="glyphicon glyphicon-edit"></span> ترمیم کریں
                                </button>
                            </div>
                            <div class="class-card-body">
                                <?php if ($qt_result && $qt_result->num_rows > 0): ?>
                                    <!-- Desktop table view -->
                                    <div class="table-responsive desktop-table">
                                        <table class="table table-bordered table-striped rtl-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>سوال کی قسم</th>
                                                    <th>ان پٹ کی قسم</th>
                                                    <th>زیادہ سے زیادہ نمبر</th>
                                                    <th>ترتیب</th>
                                                    <th>کارروائی</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $counter = 1; while ($qt_row = $qt_result->fetch_assoc()): 
                                                    $is_boolean = ($qt_row['input_type'] == 'boolean');
                                                    $input_type_label = $is_boolean ? 'خالی جگہ' : 'نمبرات';
                                                    $badge_class = $is_boolean ? 'badge-boolean' : 'badge-marks';
                                                ?>
                                                    <tr>
                                                        <td><?php echo $counter++; ?></td>
                                                        <td><?php echo htmlspecialchars($qt_row['question_type_name']); ?></td>
                                                        <td>
                                                            <span class="input-type-badge <?php echo $badge_class; ?>">
                                                                <?php echo $input_type_label; ?>
                                                            </span>
                                                        </td>
                                                        <td class="number-cell">
                                                            <?php echo $is_boolean ? '<span class="text-muted">-</span>' : number_format($qt_row['max_marks'], 2); ?>
                                                        </td>
                                                        <td><?php echo $qt_row['sort_order']; ?></td>
                                                        <td>
                                                            <a href="?delete=<?php echo $qt_row['id']; ?>" 
                                                               class="btn btn-xs btn-danger" 
                                                               onclick="return confirm('کیا آپ واقعی اس سوال کی قسم کو حذف کرنا چاہتے ہیں؟');"
                                                               style="font-size: 15px; padding: 6px 14px;">
                                                                حذف کریں
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Mobile card view - Clickable/Expandable -->
                                    <div class="mobile-cards">
                                        <?php $counter = 1; 
                                        // Reset the result pointer
                                        $qt_stmt->execute();
                                        $qt_result_mobile = $qt_stmt->get_result();
                                        while ($qt_row = $qt_result_mobile->fetch_assoc()): 
                                            $is_boolean = ($qt_row['input_type'] == 'boolean');
                                            $input_type_label = $is_boolean ? 'خالی جگہ' : 'نمبرات';
                                            $badge_class = $is_boolean ? 'badge-boolean' : 'badge-marks';
                                        ?>
                                            <div class="question-card">
                                                <div class="question-card-header" onclick="toggleCard(this)">
                                                    <span class="card-number">#<?php echo $counter; ?></span>
                                                    <span class="card-title"><?php echo htmlspecialchars($qt_row['question_type_name']); ?></span>
                                                    <span class="card-toggle">▼</span>
                                                </div>
                                                <div class="question-card-details">
                                                    <div class="question-card-row">
                                                        <span class="question-card-label">ان پٹ کی قسم</span>
                                                        <span class="question-card-value">
                                                            <span class="input-type-badge <?php echo $badge_class; ?>">
                                                                <?php echo $input_type_label; ?>
                                                            </span>
                                                        </span>
                                                    </div>
                                                    <div class="question-card-row">
                                                        <span class="question-card-label">زیادہ سے زیادہ نمبر</span>
                                                        <span class="question-card-value">
                                                            <?php echo $is_boolean ? '<span class="text-muted">-</span>' : number_format($qt_row['max_marks'], 2); ?>
                                                        </span>
                                                    </div>
                                                    <div class="question-card-row">
                                                        <span class="question-card-label">ترتیب</span>
                                                        <span class="question-card-value"><?php echo $qt_row['sort_order']; ?></span>
                                                    </div>
                                                    <div class="question-card-actions">
                                                        <a href="?delete=<?php echo $qt_row['id']; ?>" 
                                                           class="btn btn-danger" 
                                                           onclick="event.stopPropagation(); return confirm('کیا آپ واقعی اس سوال کی قسم کو حذف کرنا چاہتے ہیں؟');">
                                                            حذف کریں
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php $counter++; endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-questions">کوئی سوال کی قسم تفویض نہیں کی گئی</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php 
                                $qt_stmt->close();
                            endwhile;
                        else:
                        ?>
                            <div class="alert alert-info rtl" style="padding: 20px;">
                                کوئی کلاس کے سوال کی قسم نہیں ملی۔
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
// Toggle card function for mobile view
function toggleCard(headerElement) {
    var card = headerElement.parentElement;
    var details = card.querySelector('.question-card-details');
    var toggle = headerElement.querySelector('.card-toggle');
    
    details.classList.toggle('show');
    toggle.classList.toggle('open');
}

$(document).ready(function() {
    
    var questionTypesOptions = [];
    
    function loadQuestionTypes() {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_question_types' },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.types) {
                    questionTypesOptions = response.types;
                }
            }
        });
    }
    
    loadQuestionTypes();
    
    $('#classQtForm').on('submit', function(e) {
        var classId = $('#class_id').val();
        if (!classId) {
            e.preventDefault();
            alert('براہ کرم کلاس منتخب کریں');
            return false;
        }
        
        var hasQuestionType = false;
        $('select[name="question_type_id[]"]').each(function() {
            if ($(this).val()) {
                hasQuestionType = true;
                return false;
            }
        });
        
        if (!hasQuestionType) {
            e.preventDefault();
            alert('براہ کرم کم از کم ایک سوال کی قسم منتخب کریں');
            return false;
        }
        
        return true;
    });
    
    $('#class_id').on('change', function() {
        var classId = $(this).val();
        
        if (classId) {
            loadClassQuestionTypes(classId);
        } else {
            $('#btnAddRow, #btnSubmitRow').hide();
            $('#questionTypesContainer').html('<div class="alert alert-info rtl" id="noClassSelected" style="padding: 20px;">براہ کرم پہلے کلاس منتخب کریں</div>');
        }
    });
    
    $(document).on('click', '.btn-edit-class', function() {
        var classId = $(this).data('class-id');
        $('#class_id').val(classId).trigger('change');
        
        $('html, body').animate({
            scrollTop: $('#classQtForm').offset().top - 100
        }, 500);
    });
    
    function loadClassQuestionTypes(classId) {
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_class_question_types', class_id: classId },
            dataType: 'json',
            success: function(response) {
                $('#questionTypesContainer').empty();
                
                if (response.success && response.items && response.items.length > 0) {
                    $.each(response.items, function(index, item) {
                        addQuestionTypeRow(item.question_type_id, item.max_marks, item.sort_order, item.input_type, item.id);
                    });
                } else {
                    addQuestionTypeRow('', '', 0, '', '');
                }
                
                $('#btnAddRow, #btnSubmitRow').show();
            },
            error: function() {
                $('#questionTypesContainer').empty();
                addQuestionTypeRow('', '', 0, '', '');
                $('#btnAddRow, #btnSubmitRow').show();
            }
        });
    }
    
    $('#btnAddQuestionType').on('click', function() {
        var rowCount = $('#questionTypesContainer .question-type-row').length;
        addQuestionTypeRow('', '', rowCount, '', '');
    });
    
    $(document).on('click', '.btn-remove-row', function() {
        $(this).closest('.question-type-row').remove();
        
        if ($('#questionTypesContainer .question-type-row').length === 0) {
            addQuestionTypeRow('', '', 0, '', '');
        }
    });
    
    $(document).on('change', '.qt-select', function() {
        var $row = $(this).closest('.question-type-row');
        var selectedOption = $(this).find('option:selected');
        var inputType = selectedOption.data('input-type');
        updateFieldVisibility($row, inputType);
    });
    
    function addQuestionTypeRow(selectedQtId, maxMarks, sortOrder, inputType, existingId) {
        var optionsHtml = '<option value="">-- منتخب کریں --</option>';
        $.each(questionTypesOptions, function(index, qt) {
            var selected = (qt.id == selectedQtId) ? ' selected' : '';
            optionsHtml += '<option value="' + qt.id + '" data-input-type="' + qt.input_type + '"' + selected + '>' + qt.name + '</option>';
        });
        
        var isBoolean = (inputType === 'boolean');
        var marksValue = (!isBoolean && maxMarks !== '' && maxMarks !== undefined && maxMarks !== null) ? parseFloat(maxMarks) : '';
        var sortValue = (sortOrder !== undefined && sortOrder !== '' && sortOrder !== null) ? sortOrder : 0;
        var existingIdValue = (existingId !== undefined && existingId !== '' && existingId !== null) ? existingId : '';
        
        var rowHtml = '<div class="question-type-row">';
        rowHtml += '<input type="hidden" name="existing_id[]" value="' + existingIdValue + '">';
        rowHtml += '<div class="row">';
        
        rowHtml += '<div class="col-md-4 col-sm-12">';
        rowHtml += '<div class="form-group">';
        rowHtml += '<label class="urdu-field-label">سوال کی قسم *</label>';
        rowHtml += '<select class="form-control qt-select rtl-select" name="question_type_id[]" required style="font-size: 18px !important;">';
        rowHtml += optionsHtml;
        rowHtml += '</select>';
        rowHtml += '</div>';
        rowHtml += '</div>';
        
        rowHtml += '<div class="col-md-3 col-sm-6">';
        rowHtml += '<div class="form-group">';
        rowHtml += '<label class="urdu-field-label field-label-marks" style="' + (isBoolean ? 'display: none;' : '') + '">زیادہ سے زیادہ نمبر</label>';
        rowHtml += '<label class="urdu-field-label field-label-boolean" style="' + (isBoolean ? '' : 'display: none;') + '">قسم</label>';
        rowHtml += '<input type="number" class="form-control input-marks" name="max_marks[]" value="' + marksValue + '" step="0.01" min="0" style="font-size: 18px !important; ' + (isBoolean ? 'display: none;' : '') + '">';
        rowHtml += '<input type="text" class="form-control boolean-display input-boolean-display" value="خالی جگہ" readonly style="font-size: 18px !important; ' + (isBoolean ? '' : 'display: none;') + '">';
        rowHtml += '</div>';
        rowHtml += '</div>';
        
        rowHtml += '<div class="col-md-3 col-sm-6">';
        rowHtml += '<div class="form-group">';
        rowHtml += '<label class="urdu-field-label">ترتیب</label>';
        rowHtml += '<input type="number" class="form-control" name="sort_order[]" value="' + sortValue + '" min="0" style="font-size: 18px !important;">';
        rowHtml += '</div>';
        rowHtml += '</div>';
        
        rowHtml += '<div class="col-md-2 col-sm-12">';
        rowHtml += '<button type="button" class="btn btn-danger btn-remove-row btn-lg-custom" style="margin-top: 25px; width: 100%;">';
        rowHtml += '<span class="glyphicon glyphicon-remove"></span> حذف کریں';
        rowHtml += '</button>';
        rowHtml += '</div>';
        
        rowHtml += '</div>';
        rowHtml += '</div>';
        
        $('#questionTypesContainer').append(rowHtml);
    }
    
    function updateFieldVisibility($row, inputType) {
        if (inputType === 'boolean') {
            $row.find('.field-label-marks').hide();
            $row.find('.field-label-boolean').show();
            $row.find('.input-marks').hide().val('0');
            $row.find('.input-boolean-display').show();
        } else {
            $row.find('.field-label-marks').show();
            $row.find('.field-label-boolean').hide();
            $row.find('.input-marks').show();
            $row.find('.input-boolean-display').hide();
        }
    }
    
    setTimeout(function() { $('.custom-alert').fadeOut('slow'); }, 10000);
    
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>