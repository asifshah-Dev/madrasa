<?php 
date_default_timezone_set('Asia/Karachi');
require_once('security.php');
$_SESSION['lang'] = 'en';
$lang = $_SESSION['lang'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    require_once('conn_inc.php');
    $conn->query("SET time_zone = '+05:00'");
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $response = ['success' => false, 'message' => ''];
    
    if ($action == 'get_students') {
        $class_id = intval($_POST['class_id']);
        $stmt = $conn->prepare("SELECT sr.id, sr.name, sr.father_name, sr.reg_no FROM student_registration sr JOIN student_class sc ON sr.id = sc.student_registration_id WHERE sc.class_id = ? ORDER BY sr.name");
        $stmt->bind_param("i", $class_id); $stmt->execute();
        $result = $stmt->get_result(); $students = [];
        while ($row = $result->fetch_assoc()) { $students[] = $row; }
        $stmt->close();
        $response['success'] = true; $response['students'] = $students;
    }
    elseif ($action == 'get_exam_details') {
        $exam_id = intval($_POST['exam_id']);
        $stmt = $conn->prepare("SELECT e.*, et.name as exam_type_name, c.title as class_name, co.title as course_name FROM exams e LEFT JOIN exam_types et ON e.exam_type_id = et.id LEFT JOIN classes c ON e.class_id = c.id LEFT JOIN courses co ON c.course_id = co.id WHERE e.id = ?");
        $stmt->bind_param("i", $exam_id); $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$exam) { $response['message'] = 'Exam not found'; echo json_encode($response); exit(); }
        
        $qt_stmt = $conn->prepare("SELECT cqt.*, qt.name as question_type_name, qt.input_type FROM class_question_types cqt JOIN question_types qt ON cqt.question_type_id = qt.id WHERE cqt.class_id = ? AND cqt.status = 0 ORDER BY cqt.sort_order");
        $qt_stmt->bind_param("i", $exam['class_id']); $qt_stmt->execute();
        $qt_result = $qt_stmt->get_result(); $question_types = [];
        while ($row = $qt_result->fetch_assoc()) { $question_types[] = $row; }
        $qt_stmt->close();
        
        $marks_stmt = $conn->prepare("SELECT * FROM marks WHERE arrange_exam_id = ? AND status = 0");
        $marks_stmt->bind_param("i", $exam_id); $marks_stmt->execute();
        $marks_result = $marks_stmt->get_result(); $existing_marks = [];
        while ($row = $marks_result->fetch_assoc()) { $existing_marks[$row['student_id']][$row['class_question_type_id']] = $row; }
        $marks_stmt->close();
        
        $response['success'] = true; $response['exam'] = $exam; $response['question_types'] = $question_types; $response['existing_marks'] = $existing_marks;
    }
    elseif ($action == 'save_marks') {
        $exam_id = intval($_POST['exam_id']); $student_id = intval($_POST['student_id']);
        $marks_data = isset($_POST['marks']) ? $_POST['marks'] : [];
        $conn->begin_transaction();
        try {
            $existing = []; $check_stmt = $conn->prepare("SELECT id, class_question_type_id FROM marks WHERE arrange_exam_id = ? AND student_id = ? AND status = 0");
            $check_stmt->bind_param("ii", $exam_id, $student_id); $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            while ($row = $check_result->fetch_assoc()) { $existing[$row['class_question_type_id']] = $row['id']; }
            $check_stmt->close();
            $update_stmt = $conn->prepare("UPDATE marks SET obtained_marks = ?, max_marks = ?, question_type_name = ?, status = 0 WHERE id = ?");
            $insert_stmt = $conn->prepare("INSERT INTO marks (student_id, arrange_exam_id, class_question_type_id, question_type_name, obtained_marks, max_marks, status) VALUES (?, ?, ?, ?, ?, ?, 0)");
            foreach ($marks_data as $cqt_id => $mark) {
                $obtained = round(floatval($mark['obtained'])); $max = round(floatval($mark['max'])); $qt_name = trim($mark['question_type_name']);
                if (isset($existing[$cqt_id])) { $update_stmt->bind_param("iisi", $obtained, $max, $qt_name, $existing[$cqt_id]); $update_stmt->execute(); }
                else { $insert_stmt->bind_param("iiisii", $student_id, $exam_id, $cqt_id, $qt_name, $obtained, $max); $insert_stmt->execute(); }
            }
            $update_stmt->close(); $insert_stmt->close(); $conn->commit();
            $response['success'] = true; $response['message'] = 'نمبرات کامیابی سے محفوظ کر دیے گئے۔';
        } catch (Exception $e) { $conn->rollback(); $response['message'] = $e->getMessage(); }
    }
    header('Content-Type: application/json'); echo json_encode($response); exit();
}
$madrasa_name = "المدرسہ الفاروقیہ للتجوید والقراءت";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once('meta_inc.php'); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta charset="UTF-8">
    <title>نمبرات</title>
    <style>
        .form-control { font-size: 16px !important; }
        .table-responsive { overflow-x: auto; }
        .rtl-content { direction: rtl; text-align: right; }
        .urdu-label { font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif; font-size: 24px; direction: rtl; text-align: right; }
        .urdu-field-label { font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif; font-size: 18px; direction: rtl; color: #333; display: block; text-align: right; }
        .custom-alert { margin-bottom: 20px; text-align: right; font-size: 16px; }
        .marks-input { width: 65px; text-align: center; font-size: 18px !important; font-weight: bold; border: 2px solid #ccc; border-radius: 4px; padding: 8px 4px; display: block; margin: 0 auto; }
        .marks-input:focus { border-color: #337ab7; outline: none; }
        .boolean-input { border-color: #5cb85c !important; }
        .question-title-cell { display: block; font-size: 14px; font-weight: bold; color: #333; text-align: center; margin-bottom: 5px; white-space: nowrap; }
        .max-marks-label { display: block; font-size: 12px; color: #999; text-align: center; margin-top: 3px; }
        .student-row { background: #fff; transition: all 0.2s; }
        .student-row:hover { background: #f9f9f9; }
        .student-row.saved { background: #dff0d8 !important; }
        .student-name { font-size: 18px; font-weight: bold; }
        .father-name { font-size: 14px; color: #666; }
        .save-indicator { color: #3c763d; font-size: 14px; display: none; }
        .saved .save-indicator { display: inline; }
        .rtl-table th { text-align: center; font-size: 16px; font-weight: bold; vertical-align: middle; background: #f5f5f5; padding: 10px 6px !important; }
        .rtl-table td { text-align: center; vertical-align: middle; padding: 8px 6px !important; }
        .rtl-table td.text-right { text-align: right !important; }
        .panel-heading.rtl { text-align: right; }
        select.form-control.rtl-select { text-align: right; direction: rtl; }
        .alert-info.rtl { text-align: center; font-size: 18px; }
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); z-index: 9999; text-align: center; padding-top: 20%; font-size: 24px; color: #337ab7; }
        .input-wrapper { text-align: center; }
        .save-btn-cell { white-space: nowrap; }
        .print-btn { background: #5bc0de; color: white; border: none; padding: 12px 24px; font-size: 18px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .mobile-cards { display: none; }

        .print-header { display: none; text-align: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 3px double #000; }
        .print-header .madrasa-name { font-size: 26px; font-weight: bold; margin-bottom: 10px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif; line-height: 1.8; }
        .print-header .exam-info { font-size: 16px; color: #000; line-height: 2; }

        @media (max-width: 768px) {
            .urdu-label { font-size: 20px; }
            .marks-input { width: 60px; font-size: 16px !important; padding: 8px 2px; }
            .student-name { font-size: 16px; }
            .father-name { font-size: 13px; }
            .question-title-cell { font-size: 13px; }
            .desktop-table { display: none; }
            .mobile-cards { display: block; }
            .student-card { background: #fff; border: 2px solid #e0e0e0; border-radius: 10px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.08); }
            .student-card.saved { border-color: #5cb85c; background: #dff0d8; }
            .student-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }
            .student-info { flex: 1; }
            .student-info .student-name { font-size: 18px; font-weight: bold; color: #333; display: block; margin-bottom: 4px; }
            .student-info .father-name { font-size: 14px; color: #666; }
            .student-number { background: #337ab7; color: white; border-radius: 50%; width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 16px; }
            .marks-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
            .marks-item { background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #e0e0e0; }
            .marks-item .qt-name { font-size: 13px; font-weight: bold; color: #555; display: block; margin-bottom: 8px; text-align: center; }
            .marks-item .input-group { display: flex; align-items: center; justify-content: center; gap: 5px; }
            .marks-item .marks-input { width: 60px; font-size: 16px !important; padding: 8px 4px; }
            .marks-item .max-mark { font-size: 13px; color: #999; white-space: nowrap; }
            .save-btn-container { text-align: center; padding-top: 10px; border-top: 2px solid #f0f0f0; }
            .save-btn-container .btn { width: 100%; font-size: 16px; padding: 10px; }
            .save-indicator-mobile { color: #3c763d; font-size: 16px; display: none; margin-top: 8px; }
            .student-card.saved .save-indicator-mobile { display: block; }
        }

        @media print {
            @page { 
                size: A4 landscape; 
                margin: 8mm 6mm 8mm 6mm;
            }
            
            html, body { 
                width: 100% !important; 
                height: 100% !important;
                margin: 0 !important; 
                padding: 0 !important; 
                background: #fff !important; 
                direction: rtl;
                font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .navbar, .nav-buttons, .loading-overlay, .panel, .panel-primary, 
            .panel-heading, .panel-body, .custom-alert, .btn, button, #examInfo, 
            #select_exam, .form-group, .form-control, .save-btn-cell, .save-btn-container, 
            .save-indicator, .save-indicator-mobile, .desktop-table, .mobile-cards, 
            .student-card, .marks-grid, .marks-item, .student-card-header, .student-number, 
            .student-info, .qt-name, .input-group, .max-mark, .marks-input, 
            .question-title-cell, .max-marks-label, .input-wrapper, .student-row, 
            .student-name, .father-name, #printBtnArea, #marksEntryArea, .urdu-field-label, 
            label, #questionHeaders, #studentsBody, #mobileCardsContainer, #marksTable, 
            .rtl-table, .table, .table-responsive,
            .container > .row > .col-md-12 > div:not(.rtl-content),
            .rtl-content > *:not(#printArea),
            #btnPrintAwardList,
            a[href="view_result.php"] { 
                display: none !important; 
            }
            
            .container, .container > .row, .container > .row > .col-md-12 {
                width: 100% !important; max-width: 100% !important;
                padding: 0 !important; margin: 0 !important; float: none !important;
            }
            .rtl-content { width: 100% !important; padding: 0 !important; margin: 0 !important; }
            
            #printArea { 
                display: block !important; 
                width: 100% !important; 
                padding: 0 !important; 
                margin: 0 !important; 
                text-align: center !important;
            }
            #printArea * { box-sizing: border-box !important; }
            
            .print-header {
                display: block !important; 
                text-align: center; 
                margin-bottom: 6px; 
                padding-bottom: 6px; 
                border-bottom: 3px double #333; 
                width: 100%; 
            }
            .print-header .madrasa-name { 
                font-size: 16pt; font-weight: bold; 
                margin-bottom: 4px; line-height: 1.5; color: #000;
            }
            .print-header .exam-info { 
                font-size: 10pt; color: #000; line-height: 1.4; 
            }
            
            #printArea .print-table,
            #printContent table {
                display: table !important; 
                border-collapse: collapse !important; 
                border: 1px solid #333 !important;
                table-layout: fixed !important;
                margin: 0 auto !important;
            }
            
            #printArea .print-table thead,
            #printContent table thead { 
                display: table-header-group !important; 
            }
            
            #printArea .print-table th,
            #printContent table th { 
                display: table-cell !important; 
                font-size: 8pt !important; 
                font-weight: bold !important; 
                padding: 6px 2px !important;
                background-color: #e8e8e8 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                border: 1px solid #333 !important; 
                text-align: center !important; 
                vertical-align: middle !important; 
                line-height: 1.2 !important;
                color: #000 !important;
                white-space: normal !important;
                word-break: keep-all !important;
            }
            
            #printArea .print-table tbody,
            #printContent table tbody { 
                display: table-row-group !important; 
            }
            
            #printArea .print-table tr,
            #printContent table tr { 
                display: table-row !important; 
                page-break-inside: avoid !important;
                height: 26px !important;
            }
            
            #printArea .print-table td,
            #printContent table td { 
                display: table-cell !important; 
                font-size: 11pt !important; 
                padding: 4px 3px !important; 
                border: 1px solid #333 !important; 
                text-align: center !important; 
                vertical-align: middle !important; 
                line-height: 1.3 !important;
                color: #000 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
            }
            
            #printArea .print-table tbody tr:nth-child(even) td,
            #printContent table tbody tr:nth-child(even) td { 
                background-color: #fafafa !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            #printArea .print-table th.print-rtl-col,
            #printContent table th.print-rtl-col,
            #printArea .print-table td.print-rtl-col,
            #printContent table td.print-rtl-col {
                text-align: right !important;
            }
            
            * {
                -webkit-text-stroke: 0.15px !important;
                text-rendering: geometricPrecision !important;
            }
        }
    </style>
</head>
<body>
<?php require_once('navbar.php'); ?>
<div class="loading-overlay" id="loadingOverlay"><span class="glyphicon glyphicon-refresh glyphicon-spin"></span> براہ کرم انتظار کریں...</div>

<div class="container">
    <div class="row"><div class="col-md-12">

        <div style="margin-bottom:20px;text-align:left;">
            <a href="view_result.php" style="font-size:13px;font-weight:bold;background-color:#5cb85c;color:white;padding:6px 12px;text-decoration:none;border-radius:4px;display:inline-block;">نتائج دیکھیں / View Results</a>
        </div>

        <div class="rtl-content">
            <div class="panel panel-primary no-print">
                <div class="panel-heading rtl"><span class="urdu-label">نمبرات درج کریں</span></div>
                <div class="panel-body">
                    <div class="row"><div class="col-md-6 col-sm-12"><div class="form-group">
                        <label for="select_exam" class="urdu-field-label">امتحان منتخب کریں *</label>
                        <select class="form-control rtl-select" id="select_exam" style="height:48px;">
                            <option value="">-- امتحان منتخب کریں --</option>
                            <?php 
                            require_once('conn_inc.php');
                            $exams = $conn->query("SELECT e.*, et.name as exam_type_name, c.title as class_name, co.title as course_name FROM exams e LEFT JOIN exam_types et ON e.exam_type_id = et.id LEFT JOIN classes c ON e.class_id = c.id LEFT JOIN courses co ON c.course_id = co.id WHERE e.status = 1 ORDER BY e.start_date DESC, e.session_year DESC");
                            if ($exams) { 
                                while ($exam = $exams->fetch_assoc()) { 
                                    $d = $exam['class_name']; 
                                    if($exam['course_name']) $d .= ' ('.$exam['course_name'].')'; 
                                    $dd = $exam['start_date'] ? date('d-m-Y', strtotime($exam['start_date'])) : ''; 
                                    echo "<option value='{$exam['id']}'>".htmlspecialchars($exam['exam_type_name'])." - ".htmlspecialchars($d)." - ".htmlspecialchars($exam['session_year']).($dd?" (".$dd.")":"")."</option>"; 
                                } 
                            } 
                            ?>
                        </select>
                    </div></div></div>
                    
                    <div id="examInfo" style="display:none;margin-bottom:15px;">
                        <div style="background:#d9edf7;padding:12px 18px;border-radius:6px;font-size:18px;">
                            <strong>کلاس:</strong> <span id="infoClass"></span> | 
                            <strong>سیشن:</strong> <span id="infoSession"></span> | 
                            <strong>تاریخ:</strong> <span id="infoDate"></span>
                        </div>
                    </div>
                    
                    <div id="printBtnArea" style="display:none;margin-bottom:15px;">
                        <button type="button" class="btn print-btn no-print" id="btnPrintAwardList">
                            <span class="glyphicon glyphicon-print"></span> پرنٹ ایوارڈ لسٹ
                        </button>
                    </div>
                    
                    <div id="marksEntryArea" style="display:none;">
                        <div class="table-responsive desktop-table">
                            <table class="table table-bordered rtl-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th style="text-align:right;">نام</th>
                                        <th style="text-align:right;">والد</th>
                                        <th id="questionHeaders"></th>
                                        <th>محفوظ</th>
                                    </tr>
                                </thead>
                                <tbody id="studentsBody"></tbody>
                            </table>
                        </div>
                        <div id="mobileCardsContainer" class="mobile-cards"></div>
                    </div>
                </div>
            </div>
            
            <!-- PRINT AREA -->
            <div id="printArea">
                <div class="print-header">
                    <div class="madrasa-name"><?php echo $madrasa_name; ?></div>
                    <div class="exam-info" id="printExamInfo"></div>
                </div>
                <div id="printContent"></div>
            </div>
            
        </div>
        
    </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    var questionTypes = [];
    var existingMarks = {};
    var currentExamId = null;
    var currentClassId = null;
    var currentExamData = null;
    var allStudents = [];
    
    $('#select_exam').on('change', function() {
        var id = $(this).val(); 
        if (!id) {
            $('#examInfo, #marksEntryArea, #printBtnArea').hide(); 
            return;
        }
        currentExamId = id; 
        showLoading();
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_exam_details', exam_id: id },
            dataType: 'json',
            success: function(r) {
                hideLoading();
                if (r.success) {
                    currentExamData = r.exam;
                    questionTypes = r.question_types;
                    existingMarks = r.existing_marks;
                    currentClassId = r.exam.class_id;
                    
                    var cd = r.exam.class_name; 
                    if (r.exam.course_name) cd += ' (' + r.exam.course_name + ')';
                    $('#infoClass').text(cd);
                    $('#infoSession').text(r.exam.session_year);
                    $('#infoDate').text(r.exam.start_date || '-');
                    $('#examInfo, #printBtnArea').show();
                    
                    var h = '';
                    for (var i = 0; i < questionTypes.length; i++) {
                        h += '<th></th>';
                    }
                    $('#questionHeaders').html(h);
                    loadStudents();
                } else {
                    alert('امتحان کی تفصیلات لوڈ نہیں ہو سکیں');
                }
            },
            error: function() {
                hideLoading();
                alert('سرور سے رابطہ نہیں ہو سکا');
            }
        });
    });
    
    function loadStudents() {
        showLoading();
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_students', class_id: currentClassId },
            dataType: 'json',
            success: function(r) {
                hideLoading();
                if (r.success && r.students && r.students.length > 0) {
                    allStudents = r.students;
                    var rh = '', ch = '';
                    
                    $.each(r.students, function(i, s) {
                        var sm = existingMarks[s.id] || {};
                        
                        rh += '<tr class="student-row" id="studentRow_' + s.id + '">';
                        rh += '<td>' + (i + 1) + '</td>';
                        rh += '<td class="text-right"><span class="student-name">' + s.name + '</span></td>';
                        rh += '<td class="text-right"><span class="father-name">' + (s.father_name || '-') + '</span></td>';
                        
                        $.each(questionTypes, function(qi, qt) {
                            var em = sm[qt.id];
                            var ov = (em && em.obtained_marks !== undefined && em.obtained_marks !== null) ? Math.round(parseFloat(em.obtained_marks)) : '';
                            rh += '<td><div class="input-wrapper"><span class="question-title-cell">' + qt.question_type_name + '</span>';
                            if (qt.input_type === 'boolean') {
                                rh += '<input type="number" class="form-control marks-input boolean-input" value="' + ov + '" step="1" min="0" max="30" data-cqt="' + qt.id + '" data-max="30" data-qtname="' + qt.question_type_name + '" placeholder="0"><span class="max-marks-label">/30</span>';
                            } else {
                                rh += '<input type="number" class="form-control marks-input" value="' + ov + '" step="1" min="0" max="' + Math.round(qt.max_marks) + '" data-cqt="' + qt.id + '" data-max="' + Math.round(qt.max_marks) + '" data-qtname="' + qt.question_type_name + '" placeholder="0"><span class="max-marks-label">/' + Math.round(qt.max_marks) + '</span>';
                            }
                            rh += '</div></td>';
                        });
                        
                        rh += '<td class="save-btn-cell"><button type="button" class="btn btn-success btn-sm btn-save-marks" data-student="' + s.id + '" style="font-size:14px;padding:6px 14px;"><span class="glyphicon glyphicon-floppy-disk"></span> محفوظ</button><span class="save-indicator"> ✓</span></td>';
                        rh += '</tr>';
                        
                        ch += '<div class="student-card" id="studentCard_' + s.id + '">';
                        ch += '<div class="student-card-header"><div class="student-info"><span class="student-name">' + s.name + '</span><span class="father-name">ولد: ' + (s.father_name || '-') + '</span></div><div class="student-number">' + (i + 1) + '</div></div>';
                        ch += '<div class="marks-grid">';
                        
                        $.each(questionTypes, function(qi, qt) {
                            var em = sm[qt.id];
                            var ov = (em && em.obtained_marks !== undefined) ? Math.round(parseFloat(em.obtained_marks)) : '';
                            var mv = qt.input_type === 'boolean' ? 30 : Math.round(qt.max_marks);
                            ch += '<div class="marks-item"><span class="qt-name">' + qt.question_type_name + '</span><div class="input-group"><input type="number" class="form-control marks-input ' + (qt.input_type === 'boolean' ? 'boolean-input' : '') + '" value="' + ov + '" step="1" min="0" max="' + mv + '" data-cqt="' + qt.id + '" data-max="' + mv + '" data-qtname="' + qt.question_type_name + '" placeholder="0"><span class="max-mark">/' + mv + '</span></div></div>';
                        });
                        
                        ch += '</div><div class="save-btn-container"><button type="button" class="btn btn-success btn-save-marks-mobile" data-student="' + s.id + '"><span class="glyphicon glyphicon-floppy-disk"></span> محفوظ کریں</button><div class="save-indicator-mobile">✓ کامیابی سے محفوظ ہو گیا</div></div></div>';
                    });
                    
                    $('#studentsBody').html(rh);
                    $('#mobileCardsContainer').html(ch);
                    $('#marksEntryArea').show();
                } else {
                    allStudents = [];
                    $('#studentsBody').html('<tr><td colspan="10" style="text-align:center;padding:20px;font-size:18px;color:#999;">کوئی طالب علم نہیں ملا</td></tr>');
                    $('#mobileCardsContainer').html('<div style="text-align:center;padding:20px;font-size:18px;color:#999;">کوئی طالب علم نہیں ملا</div>');
                    $('#marksEntryArea').show();
                }
            },
            error: function() {
                hideLoading();
                alert('طالب علم لوڈ نہیں ہو سکے');
            }
        });
    }
    
    $(document).on('click', '.btn-save-marks', function() {
        saveMarks($(this).data('student'), 'studentRow_' + $(this).data('student'), $(this));
    });
    
    $(document).on('click', '.btn-save-marks-mobile', function() {
        saveMarks($(this).data('student'), 'studentCard_' + $(this).data('student'), $(this));
    });
    
    function saveMarks(sid, cid, $btn) {
        var $c = $('#' + cid);
        var md = {};
        var he = false;
        
        $c.find('.marks-input').each(function() {
            var $i = $(this);
            var ob = $(this).val();
            $i.css('border-color', $i.hasClass('boolean-input') ? '#5cb85c' : '#ccc');
            if (ob !== '') {
                if (parseFloat(ob) > parseFloat($(this).data('max'))) {
                    $i.css('border-color', 'red');
                    he = true;
                }
                md[$(this).data('cqt')] = {
                    obtained: ob,
                    max: $(this).data('max'),
                    question_type_name: $(this).data('qtname')
                };
            }
        });
        
        if (he) {
            alert('کچھ نمبر زیادہ سے زیادہ حد سے تجاوز کر گئے ہیں۔');
            return;
        }
        
        $btn.prop('disabled', true).html('⏳...');
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'save_marks',
                exam_id: currentExamId,
                student_id: sid,
                marks: md
            },
            dataType: 'json',
            success: function(r) {
                $btn.prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> محفوظ');
                if ($btn.hasClass('btn-save-marks-mobile')) {
                    $btn.html('<span class="glyphicon glyphicon-floppy-disk"></span> محفوظ کریں');
                }
                if (r.success) {
                    $c.addClass('saved');
                    setTimeout(function() { $c.removeClass('saved'); }, 2500);
                } else {
                    alert('نمبرات محفوظ نہیں ہو سکے: ' + r.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<span class="glyphicon glyphicon-floppy-disk"></span> محفوظ');
                alert('سرور سے رابطہ نہیں ہو سکا');
            }
        });
    }
    
    $(document).on('input', '.marks-input', function() {
        var v = parseFloat($(this).val()) || 0;
        var m = parseFloat($(this).attr('max')) || 0;
        $(this).css('border-color', v > m ? 'red' : $(this).hasClass('boolean-input') ? '#5cb85c' : '#ccc');
    });
    
    // =============================================
    // PRINT FUNCTION - FIXED LAYOUT, NARROW/SHORT MARKS CELLS
    // =============================================
    $('#btnPrintAwardList').on('click', function() {
        if (!currentExamData || allStudents.length === 0) {
            alert('براہ کرم پہلے امتحان منتخب کریں');
            return;
        }
        
        $('#printContent').empty();
        
        var cd = currentExamData.class_name;
        if (currentExamData.course_name) cd += ' (' + currentExamData.course_name + ')';
        var dd = currentExamData.start_date ? currentExamData.start_date : '-';
        
        $('#printExamInfo').html(
            '<strong>' + currentExamData.exam_type_name + '</strong> | ' +
            'کلاس: ' + cd + ' | ' +
            'سیشن: ' + currentExamData.session_year + ' | ' +
            'تاریخ: ' + dd
        );
        
        var qtCount = questionTypes.length;
        
        // Column widths in mm — edit these directly to control print sizing
        var serialColMM = 14;
        var nameColMM = 45;
        var fatherColMM = 45;
        var marksColMM = 20; // <-- change this value to make marks columns narrower/wider
        
        var tableHTML = '<table style="border-collapse:collapse;border:1px solid #333;font-size:11pt;table-layout:fixed;">';
        
        tableHTML += '<thead><tr>';
        tableHTML += '<th style="width:' + serialColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">#</th>';
        tableHTML += '<th class="print-rtl-col" style="width:' + nameColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">نام</th>';
        tableHTML += '<th class="print-rtl-col" style="width:' + fatherColMM + 'mm;padding:6px 2px;font-size:9pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;">والد</th>';
        
        $.each(questionTypes, function(i, qt) {
            var h = qt.question_type_name + '<br>/' + (qt.input_type === 'boolean' ? 30 : Math.round(qt.max_marks));
            tableHTML += '<th style="width:' + marksColMM + 'mm;padding:6px 2px;font-size:7pt;font-weight:bold;background:#e8e8e8;border:1px solid #333;text-align:center;white-space:normal;word-break:break-all;line-height:1.1;overflow:hidden;">' + h + '</th>';
        });
        tableHTML += '</tr></thead>';
        
        tableHTML += '<tbody>';
        $.each(allStudents, function(i, s) {
            tableHTML += '<tr style="height:26px;">';
            tableHTML += '<td style="width:' + serialColMM + 'mm;padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;">' + (i + 1) + '</td>';
            tableHTML += '<td class="print-rtl-col" style="width:' + nameColMM + 'mm;padding:4px 4px;border:1px solid #333;text-align:right;font-size:11pt;overflow:hidden;white-space:nowrap;">' + s.name + '</td>';
            tableHTML += '<td class="print-rtl-col" style="width:' + fatherColMM + 'mm;padding:4px 4px;border:1px solid #333;text-align:right;font-size:11pt;overflow:hidden;white-space:nowrap;">' + (s.father_name || '-') + '</td>';
            
            $.each(questionTypes, function() {
                tableHTML += '<td style="width:' + marksColMM + 'mm;height:26px;padding:4px 2px;border:1px solid #333;text-align:center;font-size:11pt;">&nbsp;</td>';
            });
            
            tableHTML += '</tr>';
        });
        tableHTML += '</tbody>';
        tableHTML += '</table>';
        
        $('#printContent').html(tableHTML);
        
        setTimeout(function() {
            window.print();
        }, 500);
    });
    
    function showLoading() { $('#loadingOverlay').show(); }
    function hideLoading() { $('#loadingOverlay').hide(); }
});
</script>
</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>