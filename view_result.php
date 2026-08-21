<?php 
date_default_timezone_set('Asia/Karachi');
require_once('security.php');
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once('conn_inc.php');
    $conn->query("SET time_zone = '+05:00'");
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action == 'get_exams_by_class') {
        $class_id = intval($_POST['class_id']);
        
        $stmt = $conn->prepare("
            SELECT e.*, et.name as exam_type_name
            FROM exams e
            LEFT JOIN exam_types et ON e.exam_type_id = et.id
            WHERE e.class_id = ? AND e.status = 1
            ORDER BY e.start_date DESC, e.id DESC
        ");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exams = [];
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
        $stmt->close();
        
        $response['success'] = true;
        $response['exams'] = $exams;
    }
    elseif ($action == 'get_result') {
        $exam_id = intval($_POST['exam_id']);
        $student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
        
        $stmt = $conn->prepare("
            SELECT e.*, et.name as exam_type_name, c.title as class_name, co.title as course_name
            FROM exams e
            LEFT JOIN exam_types et ON e.exam_type_id = et.id
            LEFT JOIN classes c ON e.class_id = c.id
            LEFT JOIN courses co ON c.course_id = co.id
            WHERE e.id = ?
        ");
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $qt_stmt = $conn->prepare("
            SELECT cqt.*, qt.name as question_type_name, qt.input_type
            FROM class_question_types cqt
            JOIN question_types qt ON cqt.question_type_id = qt.id
            WHERE cqt.class_id = ? AND cqt.status = 0
            ORDER BY cqt.sort_order
        ");
        $qt_stmt->bind_param("i", $exam['class_id']);
        $qt_stmt->execute();
        $qt_result = $qt_stmt->get_result();
        $question_types = [];
        $total_max_marks = 0;
        while ($row = $qt_result->fetch_assoc()) {
            $question_types[] = $row;
            if ($row['input_type'] == 'marks') {
                $total_max_marks += floatval($row['max_marks']);
            }
        }
        $qt_stmt->close();
        
        if ($student_id > 0) {
            $student_query = "SELECT sr.id, sr.name, sr.father_name, sr.reg_no FROM student_registration sr WHERE sr.id = ?";
            $student_stmt = $conn->prepare($student_query);
            $student_stmt->bind_param("i", $student_id);
        } else {
            $student_query = "
                SELECT sr.id, sr.name, sr.father_name, sr.reg_no
                FROM student_registration sr
                JOIN student_class sc ON sr.id = sc.student_registration_id
                WHERE sc.class_id = ?
                ORDER BY sr.name
            ";
            $student_stmt = $conn->prepare($student_query);
            $student_stmt->bind_param("i", $exam['class_id']);
        }
        
        $student_stmt->execute();
        $student_result = $student_stmt->get_result();
        $students = [];
        while ($row = $student_result->fetch_assoc()) {
            $students[] = $row;
        }
        $student_stmt->close();
        
        $marks_data = [];
        if (!empty($students)) {
            $student_ids = array_column($students, 'id');
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            
            $marks_query = "SELECT * FROM marks WHERE arrange_exam_id = ? AND student_id IN ($placeholders) AND status = 0";
            $marks_stmt = $conn->prepare($marks_query);
            $types = str_repeat('i', count($student_ids) + 1);
            $params = array_merge([$exam_id], $student_ids);
            
            $bind_params = [];
            $bind_params[] = $types;
            foreach ($params as $key => $value) {
                $bind_params[] = &$params[$key];
            }
            call_user_func_array([$marks_stmt, 'bind_param'], $bind_params);
            
            $marks_stmt->execute();
            $marks_result = $marks_stmt->get_result();
            while ($row = $marks_result->fetch_assoc()) {
                $marks_data[$row['student_id']][$row['class_question_type_id']] = $row;
            }
            $marks_stmt->close();
        }
        
        $response['success'] = true;
        $response['exam'] = $exam;
        $response['question_types'] = $question_types;
        $response['students'] = $students;
        $response['marks_data'] = $marks_data;
        $response['total_max_marks'] = $total_max_marks;
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$madrasa_name = "المدرسہ الفاروقیہ للتجوید والقراءت";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once('meta_inc.php'); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta charset="UTF-8">
    <title>نتیجہ - المدرسہ الفاروقیہ</title>
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
        }
        
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
        
        .custom-alert { margin-bottom: 20px; text-align: right; font-size: 16px; }
        
        .btn-lg-custom { font-size: 18px !important; padding: 12px 25px !important; font-weight: bold; }
        
        .rtl-table { width: 100%; border-collapse: collapse; margin: 0 auto; }
        .rtl-table th { 
            text-align: center; 
            font-size: 15px; 
            font-weight: bold; 
            vertical-align: middle; 
            background: #f5f5f5; 
            padding: 10px 8px !important;
            border: 1px solid #bbb;
        }
        .rtl-table td { 
            text-align: center; 
            vertical-align: middle; 
            padding: 9px 8px !important; 
            font-size: 15px;
            border: 1px solid #ccc;
        }
        .rtl-table td.text-right { text-align: right !important; font-size: 16px; }
        
        .panel-heading.rtl { text-align: right; }
        select.form-control.rtl-select { text-align: right; direction: rtl; }
        .alert-info.rtl { text-align: center; font-size: 18px; }
        
        /* Navigation button styling - LEFT ALIGNED */
        .nav-buttons {
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }
        
        .nav-btn-entry { 
            background-color: #5cb85c; 
            color: white;
            font-size: 13px;
            font-weight: bold;
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
        }
        
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            text-align: center;
            padding-top: 20%;
            font-size: 24px;
            color: #337ab7;
        }
        
        .student-info {
            background: #f9f9f9;
            padding: 12px 18px;
            margin-bottom: 12px;
            border-radius: 4px;
            font-size: 17px;
            border: 1px solid #ddd;
        }
        
        .result-card {
            border: 2px solid #337ab7;
            border-radius: 8px;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .result-card-header {
            background: #337ab7;
            color: white;
            padding: 14px 20px;
            text-align: center;
        }
        .result-card-header h3 { font-size: 20px; margin: 5px 0; }
        .result-card-body { padding: 18px; }
        
        .print-btn {
            background: #5bc0de;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 18px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .print-btn:hover { background: #46b8da; }
        
        .obtained { font-weight: bold; color: #337ab7; }
        .total-row { background: #e8f4ff; font-weight: bold; }
        
        .boolean-badge {
            display: inline-block;
            background: #dff0d8;
            color: #3c763d;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        .position-mumtaz { color: #1a7a1a; font-weight: bold; }
        .position-jayyidjiddan { color: #2d8a2d; font-weight: bold; }
        .position-jayyid { color: #337ab7; font-weight: bold; }
        .position-maqbool { color: #f0ad4e; font-weight: bold; }
        .position-fail { color: #d9534f; font-weight: bold; }
        
        .print-header {
            display: none;
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px double #000;
        }
        .print-header .madrasa-name {
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 10px;
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            line-height: 1.8;
        }
        .print-header .exam-info {
            font-size: 16px;
            color: #000;
            line-height: 2;
        }
        
        /* Mobile card view */
        .mobile-cards { display: none; }
        .desktop-table { display: block; }
        
        @media (max-width: 768px) {
            .urdu-label { font-size: 20px; }
            .btn-lg-custom { font-size: 16px !important; padding: 10px 20px !important; }
            
            .nav-btn-entry {
                font-size: 12px;
                padding: 5px 10px;
            }
            
            /* Hide desktop table, show mobile cards */
            .desktop-table { display: none; }
            .mobile-cards { display: block; }
            
            .student-result-card {
                background: #fff;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                margin-bottom: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                overflow: hidden;
            }
            
            .student-result-card-header {
                background: #337ab7;
                color: white;
                padding: 12px 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .student-result-card-header .student-rank {
                background: white;
                color: #337ab7;
                border-radius: 50%;
                width: 30px;
                height: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                font-size: 14px;
            }
            
            .student-result-card-header .student-name-mobile {
                font-size: 16px;
                font-weight: bold;
            }
            
            .student-result-card-body {
                padding: 12px 15px;
            }
            
            .student-result-card-body .father-name-mobile {
                font-size: 13px;
                color: #666;
                margin-bottom: 10px;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            
            .marks-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-bottom: 12px;
            }
            
            .marks-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 0;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .marks-item:last-child {
                border-bottom: none;
            }
            
            .marks-item .subject-name {
                font-size: 14px;
                color: #555;
            }
            
            .marks-item .marks-obtained {
                font-size: 15px;
                font-weight: bold;
                color: #337ab7;
            }
            
            .marks-summary {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 8px;
                display: flex;
                justify-content: space-around;
                align-items: center;
            }
            
            .marks-summary-item {
                text-align: center;
            }
            
            .marks-summary-item .summary-label {
                font-size: 12px;
                color: #666;
                display: block;
            }
            
            .marks-summary-item .summary-value {
                font-size: 16px;
                font-weight: bold;
                color: #333;
            }
            
            .marks-summary-item .summary-value.total {
                color: #337ab7;
                font-size: 18px;
            }
            
            /* Single student card */
            .single-student-card {
                background: #fff;
                border: 2px solid #337ab7;
                border-radius: 10px;
                overflow: hidden;
                margin-bottom: 20px;
            }
            
            .single-student-card-header {
                background: #337ab7;
                color: white;
                padding: 15px;
                text-align: center;
                font-size: 18px;
                font-weight: bold;
            }
            
            .single-student-card-body {
                padding: 15px;
            }
            
            .single-student-info {
                background: #f9f9f9;
                padding: 10px 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 15px;
                line-height: 1.8;
            }
            
            .print-btn {
                width: 100%;
                margin-top: 10px;
            }
            
            /* Print button container */
            .button-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .button-container .btn {
                width: 100%;
            }
        }
        
        @media print {
            @page {
                size: A4 landscape;
                margin: 8mm 6mm 8mm 6mm;
            }
            
            html, body {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                font-size: 14px;
                direction: rtl;
                font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            }
            
            .container {
                width: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .row {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .col-md-12 {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                float: none !important;
            }
            
            .rtl-content {
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .no-print, 
            .navbar, 
            .nav-buttons, 
            .loading-overlay, 
            .panel-primary, 
            .custom-alert, 
            .btn, 
            button,
            .panel,
            .mobile-cards,
            .desktop-table,
            .result-card {
                display: none !important;
            }
            
            #printResultTable {
                display: block !important;
                width: 100%;
            }
            
            #printArea {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 8px;
                padding-bottom: 8px;
                border-bottom: 3px double #000;
                width: 100%;
            }
            .print-header .madrasa-name {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 4px;
                line-height: 1.4;
            }
            .print-header .exam-info {
                font-size: 12px;
                color: #000;
                line-height: 1.6;
            }
            
            .result-card {
                border: none !important;
                border-radius: 0 !important;
                margin-bottom: 6px !important;
                page-break-inside: avoid;
                width: 100%;
            }
            
            .result-card-header {
                background: none !important;
                color: #000 !important;
                padding: 4px 0 !important;
                border-bottom: 1px solid #000;
                margin-bottom: 6px;
            }
            .result-card-header h3 {
                font-size: 14px !important;
                margin: 3px 0 !important;
            }
            
            .result-card-body {
                padding: 4px 0 !important;
            }
            
            .table-responsive {
                overflow: visible !important;
                width: 100% !important;
            }
            
            .rtl-table {
                font-size: 10px !important;
                width: 100% !important;
                table-layout: auto;
            }
            .rtl-table th {
                font-size: 10px !important;
                padding: 5px 6px !important;
                background: #eee !important;
                border: 1px solid #000 !important;
                color: #000 !important;
            }
            .rtl-table td {
                font-size: 10px !important;
                padding: 4px 6px !important;
                border: 1px solid #000 !important;
            }
            .rtl-table td.text-right { 
                font-size: 10px !important; 
                min-width: 60px;
            }
            
            #printResultTable table {
                display: table !important;
                border-collapse: collapse !important;
                border: 1px solid #333 !important;
                table-layout: fixed !important;
                margin: 0 auto !important;
            }
            #printResultTable table th.print-rtl-col,
            #printResultTable table td.print-rtl-col {
                text-align: right !important;
            }
            #printResultTable table tbody tr:nth-child(even) td {
                background-color: #fafafa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .student-info {
                font-size: 15px !important;
                padding: 10px 15px !important;
                border: 1px solid #000 !important;
                background: #fff !important;
                width: 100%;
            }
            
            .total-row td { font-size: 10px !important; }
            .total-row { background: #eee !important; }
            
            .obtained { color: #000 !important; }
            .boolean-badge { 
                border: 1px solid #999; 
                font-size: 11px; 
                padding: 1px 6px; 
            }
            
            .position-mumtaz, 
            .position-jayyidjiddan, 
            .position-jayyid, 
            .position-maqbool, 
            .position-fail {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="loading-overlay" id="loadingOverlay">
    <span class="glyphicon glyphicon-refresh glyphicon-spin"></span> براہ کرم انتظار کریں...
</div>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <div class="nav-buttons no-print">
                <a href="result_entry.php" class="nav-btn-entry">
                    نمبرات درج کریں / Result Entry
                </a>
            </div>

            <div class="rtl-content">

                <div class="panel panel-primary no-print">
                    <div class="panel-heading rtl">
                        <span class="urdu-label">نتیجہ دیکھیں</span>
                    </div>
                    <div class="panel-body">
                        
                        <div class="row">
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="select_class" class="urdu-field-label">کلاس منتخب کریں *</label>
                                    <select class="form-control rtl-select" id="select_class" style="height: 48px;">
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
                            
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="select_exam" class="urdu-field-label">امتحان منتخب کریں *</label>
                                    <select class="form-control rtl-select" id="select_exam" style="height: 48px;" disabled>
                                        <option value="">-- پہلے کلاس منتخب کریں --</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label for="select_student" class="urdu-field-label">طالب علم (اختیاری)</label>
                                    <select class="form-control rtl-select" id="select_student" style="height: 48px;" disabled>
                                        <option value="">-- تمام کلاس --</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-12 button-container" style="margin-top: 15px;">
                                <button type="button" class="btn btn-success btn-lg-custom" id="btnViewResult" disabled>
                                    <span class="glyphicon glyphicon-eye-open"></span> نتیجہ دیکھیں
                                </button>
                                <button type="button" class="btn btn-lg-custom print-btn" id="btnPrintAll" disabled>
                                    <span class="glyphicon glyphicon-print"></span> پرنٹ کریں
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <div id="printArea">
                    <div class="print-header">
                        <div class="madrasa-name"><?php echo $madrasa_name; ?></div>
                        <div class="exam-info" id="printExamInfo"></div>
                    </div>
                    <div id="resultContainer"></div>
                    <div id="printResultTable" style="display:none;"></div>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
    
    $('#select_class').on('change', function() {
        var classId = $(this).val();
        $('#select_exam').html('<option value="">-- لوڈ ہو رہا ہے... --</option>').prop('disabled', true);
        $('#select_student').html('<option value="">-- تمام کلاس --</option>').prop('disabled', true);
        $('#btnViewResult, #btnPrintAll').prop('disabled', true);
        $('#resultContainer').empty();
        $('#printExamInfo').empty();
        
        if (!classId) {
            $('#select_exam').html('<option value="">-- پہلے کلاس منتخب کریں --</option>');
            return;
        }
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_exams_by_class', class_id: classId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.exams && response.exams.length > 0) {
                    var options = '<option value="">-- امتحان منتخب کریں --</option>';
                    $.each(response.exams, function(i, exam) {
                        var dateStr = exam.start_date ? ' (' + exam.start_date + ')' : '';
                        options += '<option value="' + exam.id + '">' + exam.exam_type_name + ' - ' + exam.session_year + dateStr + '</option>';
                    });
                    $('#select_exam').html(options).prop('disabled', false);
                } else {
                    $('#select_exam').html('<option value="">-- کوئی امتحان نہیں --</option>');
                }
            }
        });
    });
    
    $('#select_exam').on('change', function() {
        if ($(this).val()) {
            $('#btnViewResult, #btnPrintAll').prop('disabled', false);
        } else {
            $('#btnViewResult, #btnPrintAll').prop('disabled', true);
        }
    });
    
    $('#btnViewResult').on('click', function() { loadResult(false); });
    $('#btnPrintAll').on('click', function() { loadResult(true); });
    
    function loadResult(printAfter) {
        var examId = $('#select_exam').val();
        var studentId = $('#select_student').val() || 0;
        
        if (!examId) { alert('براہ کرم امتحان منتخب کریں'); return; }
        
        showLoading();
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_result', exam_id: examId, student_id: studentId },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
                    var exam = response.exam;
                    var classDisplay = exam.class_name;
                    if (exam.course_name) classDisplay += ' (' + exam.course_name + ')';
                    var dateDisplay = exam.start_date ? exam.start_date : '-';
                    
                    // All on one line: Exam Name | Class | Session | Date
                    $('#printExamInfo').html('<strong>' + exam.exam_type_name + '</strong> | کلاس: ' + classDisplay + ' | سیشن: ' + exam.session_year + ' | تاریخ: ' + dateDisplay);
                    
                    displayResult(response, studentId > 0);
                    if (printAfter) { setTimeout(function() { window.print(); }, 500); }
                } else {
                    alert('نتیجہ لوڈ نہیں ہو سکا');
                }
            },
            error: function() { hideLoading(); alert('سرور سے رابطہ نہیں ہو سکا'); }
        });
    }
    
    function getPosition(percentage) {
        if (percentage >= 80) return { text: 'ممتاز', cls: 'position-mumtaz' };
        if (percentage >= 60) return { text: 'جید جدا', cls: 'position-jayyidjiddan' };
        if (percentage >= 50) return { text: 'جید', cls: 'position-jayyid' };
        if (percentage >= 40) return { text: 'مقبول', cls: 'position-maqbool' };
        return { text: 'فیل', cls: 'position-fail' };
    }
    
    function displayResult(data, isSingleStudent) {
        var questionTypes = data.question_types;
        var students = data.students;
        var marksData = data.marks_data;
        var totalMax = Math.round(data.total_max_marks);
        
        var html = '';
        
        if (isSingleStudent && students.length > 0) {
            var student = students[0];
            // Desktop view
            html += '<div class="desktop-table">';
            html += buildStudentResultCard(questionTypes, student, marksData, totalMax);
            html += '</div>';
            
            // Mobile view
            html += '<div class="mobile-cards">';
            html += buildSingleStudentMobileCard(questionTypes, student, marksData, totalMax);
            html += '</div>';
        } else {
            var studentResults = [];
            $.each(students, function(index, student) {
                var studentMarks = marksData[student.id] || {};
                var totalObtained = 0;
                
                $.each(questionTypes, function(i, qt) {
                    if (qt.input_type === 'marks') {
                        var mark = studentMarks[qt.id];
                        if (mark) {
                            totalObtained += Math.round(parseFloat(mark.obtained_marks));
                        }
                    }
                });
                
                var percentage = totalMax > 0 ? Math.round((totalObtained / totalMax) * 100) : 0;
                var position = getPosition(percentage);
                
                studentResults.push({
                    student: student,
                    totalObtained: totalObtained,
                    percentage: percentage,
                    position: position,
                    marks: studentMarks
                });
            });
            
            studentResults.sort(function(a, b) { return b.percentage - a.percentage; });
            
            // Desktop table
            html += '<div class="table-responsive desktop-table">';
            html += '<table class="table table-bordered rtl-table">';
            html += '<thead><tr><th>#</th><th>نام</th><th>والد</th>';
            
            $.each(questionTypes, function(i, qt) {
                if (qt.input_type === 'boolean') {
                    html += '<th>' + qt.question_type_name + '</th>';
                } else {
                    html += '<th>' + qt.question_type_name + '<br>/' + Math.round(qt.max_marks) + '</th>';
                }
            });
            
            html += '<th>کل<br>/' + totalMax + '</th><th>فیصد</th><th>درجہ</th>';
            html += '</tr></thead><tbody>';
            
            $.each(studentResults, function(index, result) {
                html += '<tr>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td class="text-right">' + result.student.name + '</td>';
                html += '<td class="text-right">' + (result.student.father_name || '-') + '</td>';
                
                $.each(questionTypes, function(i, qt) {
                    var mark = result.marks[qt.id];
                    var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : '-';
                    html += '<td>' + obtained + '</td>';
                });
                
                html += '<td class="obtained">' + result.totalObtained + '</td>';
                html += '<td>' + result.percentage + '%</td>';
                html += '<td class="' + result.position.cls + '">' + result.position.text + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            
            // Mobile cards for class view
            html += '<div class="mobile-cards">';
            $.each(studentResults, function(index, result) {
                html += buildClassMobileCard(index + 1, result, questionTypes, totalMax);
            });
            html += '</div>';
        }
        
        $('#resultContainer').html(html);
        buildPrintTable(data, isSingleStudent);
    }
    
    // =============================================
    // PRINT TABLE - mm-based fixed layout matching result_entry.php's award list style
    // =============================================
    function buildPrintTable(data, isSingleStudent) {
        var questionTypes = data.question_types;
        var students = data.students;
        var marksData = data.marks_data;
        var totalMax = Math.round(data.total_max_marks);
        var html = '';
        
        // Column widths in mm — edit these directly to control print sizing
        var serialColMM = 12;
        var nameColMM = 42;
        var fatherColMM = 42;
        var marksColMM = 18;
        var totalColMM = 22;
        var percentColMM = 18;
        var gradeColMM = 22;
        
        if (isSingleStudent && students.length > 0) {
            var student = students[0];
            var studentMarks = marksData[student.id] || {};
            var totalObtained = 0;
            
            var subjectColMM = 70;
            var maxColMM = 30;
            var obtainedColMM = 30;
            
            html += '<div style="text-align:right;font-size:11pt;margin-bottom:8px;">';
            html += '<strong>نام:</strong> ' + student.name + ' &nbsp;|&nbsp; ';
            html += '<strong>والد:</strong> ' + (student.father_name || '-') + ' &nbsp;|&nbsp; ';
            html += '<strong>رول نمبر:</strong> ' + (student.reg_no || '-');
            html += '</div>';
            
            html += '<table style="border-collapse:collapse;border:1px solid #333;font-size:11pt;table-layout:fixed;">';
            html += '<thead><tr>';
            html += '<th style="width:' + serialColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">#</th>';
            html += '<th class="print-rtl-col" style="width:' + subjectColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">مضمون</th>';
            html += '<th style="width:' + maxColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">زیادہ سے زیادہ</th>';
            html += '<th style="width:' + obtainedColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">حاصل کردہ</th>';
            html += '</tr></thead><tbody>';
            
            $.each(questionTypes, function(i, qt) {
                var mark = studentMarks[qt.id];
                var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : 0;
                if (qt.input_type === 'marks') totalObtained += obtained;
                
                html += '<tr>';
                html += '<td style="width:' + serialColMM + 'mm;padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;">' + (i + 1) + '</td>';
                html += '<td class="print-rtl-col" style="width:' + subjectColMM + 'mm;padding:4px 4px;border:1px solid #333;text-align:right;font-size:11pt;">' + qt.question_type_name + '</td>';
                html += '<td style="width:' + maxColMM + 'mm;padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;">' + Math.round(qt.max_marks) + '</td>';
                html += '<td style="width:' + obtainedColMM + 'mm;padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;font-weight:bold;">' + obtained + '</td>';
                html += '</tr>';
            });
            
            var percentage = totalMax > 0 ? Math.round((totalObtained / totalMax) * 100) : 0;
            var position = getPosition(percentage);
            
            html += '<tr style="background:#eee;">';
            html += '<td colspan="2" class="print-rtl-col" style="padding:4px 4px;border:1px solid #333;text-align:right;font-size:11pt;font-weight:bold;">کل</td>';
            html += '<td style="padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;font-weight:bold;">' + totalMax + '</td>';
            html += '<td style="padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;font-weight:bold;">' + totalObtained + '</td>';
            html += '</tr>';
            html += '<tr>';
            html += '<td colspan="3" class="print-rtl-col" style="padding:4px 4px;border:1px solid #333;text-align:right;font-size:11pt;">فیصد / درجہ</td>';
            html += '<td style="padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;font-weight:bold;">' + percentage + '% (' + position.text + ')</td>';
            html += '</tr>';
            
            html += '</tbody></table>';
        } else {
            var studentResults = [];
            $.each(students, function(index, student) {
                var studentMarks = marksData[student.id] || {};
                var totalObtained = 0;
                $.each(questionTypes, function(i, qt) {
                    if (qt.input_type === 'marks') {
                        var mark = studentMarks[qt.id];
                        if (mark) totalObtained += Math.round(parseFloat(mark.obtained_marks));
                    }
                });
                var percentage = totalMax > 0 ? Math.round((totalObtained / totalMax) * 100) : 0;
                var position = getPosition(percentage);
                studentResults.push({ student: student, totalObtained: totalObtained, percentage: percentage, position: position, marks: studentMarks });
            });
            studentResults.sort(function(a, b) { return b.percentage - a.percentage; });
            
            html += '<table style="border-collapse:collapse;border:1px solid #333;font-size:10pt;table-layout:fixed;">';
            html += '<thead><tr>';
            html += '<th style="width:' + serialColMM + 'mm;padding:5px 2px;font-size:8pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">#</th>';
            html += '<th class="print-rtl-col" style="width:' + nameColMM + 'mm;padding:5px 2px;font-size:8pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">نام</th>';
            html += '<th class="print-rtl-col" style="width:' + fatherColMM + 'mm;padding:5px 2px;font-size:8pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">والد</th>';
            
            $.each(questionTypes, function(i, qt) {
                var h = qt.question_type_name + '<br>/' + Math.round(qt.max_marks || 30);
                html += '<th style="width:' + marksColMM + 'mm;padding:5px 2px;font-size:7pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;white-space:normal;line-height:1.1;">' + h + '</th>';
            });
            
            html += '<th style="width:' + totalColMM + 'mm;padding:5px 2px;font-size:7pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">کل<br>/' + totalMax + '</th>';
            html += '<th style="width:' + percentColMM + 'mm;padding:5px 2px;font-size:7pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">فیصد</th>';
            html += '<th style="width:' + gradeColMM + 'mm;padding:5px 2px;font-size:7pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">درجہ</th>';
            html += '</tr></thead><tbody>';
            
            $.each(studentResults, function(index, result) {
                html += '<tr style="height:22px;">';
                html += '<td style="width:' + serialColMM + 'mm;padding:3px 2px;border:1px solid #333;text-align:center;font-size:10pt;">' + (index + 1) + '</td>';
                html += '<td class="print-rtl-col" style="width:' + nameColMM + 'mm;padding:3px 4px;border:1px solid #333;text-align:right;font-size:10pt;overflow:hidden;white-space:nowrap;">' + result.student.name + '</td>';
                html += '<td class="print-rtl-col" style="width:' + fatherColMM + 'mm;padding:3px 4px;border:1px solid #333;text-align:right;font-size:10pt;overflow:hidden;white-space:nowrap;">' + (result.student.father_name || '-') + '</td>';
                
                $.each(questionTypes, function(i, qt) {
                    var mark = result.marks[qt.id];
                    var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : '-';
                    html += '<td style="width:' + marksColMM + 'mm;padding:3px 2px;border:1px solid #333;text-align:center;font-size:10pt;">' + obtained + '</td>';
                });
                
                html += '<td style="width:' + totalColMM + 'mm;padding:3px 2px;border:1px solid #333;text-align:center;font-size:10pt;font-weight:bold;">' + result.totalObtained + '</td>';
                html += '<td style="width:' + percentColMM + 'mm;padding:3px 2px;border:1px solid #333;text-align:center;font-size:10pt;">' + result.percentage + '%</td>';
                html += '<td style="width:' + gradeColMM + 'mm;padding:3px 2px;border:1px solid #333;text-align:center;font-size:10pt;">' + result.position.text + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
        }
        
        $('#printResultTable').html(html);
    }
    
    function buildClassMobileCard(rank, result, questionTypes, totalMax) {
        var html = '<div class="student-result-card">';
        html += '<div class="student-result-card-header">';
        html += '<span class="student-name-mobile">' + result.student.name + '</span>';
        html += '<span class="student-rank">' + rank + '</span>';
        html += '</div>';
        html += '<div class="student-result-card-body">';
        html += '<div class="father-name-mobile">ولد: ' + (result.student.father_name || '-') + '</div>';
        
        html += '<div class="marks-list">';
        $.each(questionTypes, function(i, qt) {
            var mark = result.marks[qt.id];
            var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : '-';
            html += '<div class="marks-item">';
            html += '<span class="subject-name">' + qt.question_type_name + '</span>';
            html += '<span class="marks-obtained">' + obtained + ' / ' + Math.round(qt.max_marks) + '</span>';
            html += '</div>';
        });
        html += '</div>';
        
        html += '<div class="marks-summary">';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">کل نمبر</span>';
        html += '<span class="summary-value total">' + result.totalObtained + ' / ' + totalMax + '</span>';
        html += '</div>';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">فیصد</span>';
        html += '<span class="summary-value">' + result.percentage + '%</span>';
        html += '</div>';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">درجہ</span>';
        html += '<span class="summary-value ' + result.position.cls + '">' + result.position.text + '</span>';
        html += '</div>';
        html += '</div>';
        
        html += '</div></div>';
        return html;
    }
    
    function buildSingleStudentMobileCard(questionTypes, student, marksData, totalMax) {
        var studentMarks = marksData[student.id] || {};
        var totalObtained = 0;
        
        var html = '<div class="single-student-card">';
        html += '<div class="single-student-card-header">رپورٹ کارڈ</div>';
        html += '<div class="single-student-card-body">';
        
        html += '<div class="single-student-info">';
        html += '<strong>نام:</strong> ' + student.name + '<br>';
        html += '<strong>والد:</strong> ' + (student.father_name || '-') + '<br>';
        html += '<strong>رول نمبر:</strong> ' + (student.reg_no || '-');
        html += '</div>';
        
        html += '<div class="marks-list">';
        $.each(questionTypes, function(i, qt) {
            var mark = studentMarks[qt.id];
            var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : 0;
            
            if (qt.input_type === 'marks') totalObtained += obtained;
            
            html += '<div class="marks-item">';
            html += '<span class="subject-name">' + qt.question_type_name + '</span>';
            html += '<span class="marks-obtained">' + obtained + ' / ' + Math.round(qt.max_marks) + '</span>';
            html += '</div>';
        });
        html += '</div>';
        
        var percentage = totalMax > 0 ? Math.round((totalObtained / totalMax) * 100) : 0;
        var position = getPosition(percentage);
        
        html += '<div class="marks-summary" style="margin-top: 10px;">';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">کل نمبر</span>';
        html += '<span class="summary-value total">' + totalObtained + ' / ' + totalMax + '</span>';
        html += '</div>';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">فیصد</span>';
        html += '<span class="summary-value">' + percentage + '%</span>';
        html += '</div>';
        html += '<div class="marks-summary-item">';
        html += '<span class="summary-label">درجہ</span>';
        html += '<span class="summary-value ' + position.cls + '">' + position.text + '</span>';
        html += '</div>';
        html += '</div>';
        
        html += '</div></div>';
        return html;
    }
    
    function buildStudentResultCard(questionTypes, student, marksData, totalMax) {
        var studentMarks = marksData[student.id] || {};
        var totalObtained = 0;
        
        var html = '<div class="result-card">';
        html += '<div class="result-card-header"><h3>رپورٹ کارڈ</h3></div>';
        html += '<div class="result-card-body">';
        
        html += '<div class="student-info">';
        html += '<strong>نام:</strong> ' + student.name + ' &nbsp;|&nbsp; ';
        html += '<strong>والد:</strong> ' + (student.father_name || '-') + ' &nbsp;|&nbsp; ';
        html += '<strong>رول نمبر:</strong> ' + (student.reg_no || '-');
        html += '</div>';
        
        html += '<div class="table-responsive">';
        html += '<table class="table table-bordered rtl-table">';
        html += '<thead><tr><th>#</th><th>مضمون</th><th>زیادہ سے زیادہ</th><th>حاصل کردہ</th></tr></thead><tbody>';
        
        $.each(questionTypes, function(i, qt) {
            var mark = studentMarks[qt.id];
            var obtained = mark ? Math.round(parseFloat(mark.obtained_marks)) : 0;
            
            if (qt.input_type === 'marks') totalObtained += obtained;
            
            html += '<tr>';
            html += '<td>' + (i + 1) + '</td>';
            html += '<td class="text-right">' + qt.question_type_name + '</td>';
            html += '<td>' + Math.round(qt.max_marks) + '</td>';
            html += '<td class="obtained">' + obtained + '</td>';
            html += '</tr>';
        });
        
        var percentage = totalMax > 0 ? Math.round((totalObtained / totalMax) * 100) : 0;
        var position = getPosition(percentage);
        
        html += '<tr class="total-row"><td colspan="2" class="text-right"><strong>کل</strong></td><td><strong>' + totalMax + '</strong></td><td class="obtained"><strong>' + totalObtained + '</strong></td></tr>';
        html += '<tr><td colspan="3" class="text-right">فیصد / درجہ</td><td>' + percentage + '% <span class="' + position.cls + '">(' + position.text + ')</span></td></tr>';
        
        html += '</tbody></table></div></div></div>';
        
        return html;
    }
    
    function showLoading() { $('#loadingOverlay').show(); }
    function hideLoading() { $('#loadingOverlay').hide(); }
    
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>