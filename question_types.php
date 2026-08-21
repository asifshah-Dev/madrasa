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
        $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM class_question_types WHERE question_type_id = ? AND status = 0");
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_question_type'])) {
    require_once('conn_inc.php');
    
    $name = trim($_POST['name']);
    $input_type = trim($_POST['input_type']);
    $question_type_id = isset($_POST['question_type_id']) ? intval($_POST['question_type_id']) : 0;
    $is_edit = ($question_type_id > 0);
    
    if (empty($name)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Question type name is required.<br>سوال کی قسم کا نام ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if (empty($input_type)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>Input type is required.<br>ان پٹ کی قسم ضروری ہے۔</div>";
        $_SESSION['message_type'] = "danger";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    if ($is_edit) {
        $stmt = $conn->prepare("UPDATE question_types SET name = ?, input_type = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $input_type, $question_type_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO question_types (name, input_type) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $input_type);
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
        }
        
        .form-control option {
            color: #333 !important;
            background-color: #fff !important;
        }
        
        .form-control::placeholder {
            color: #999 !important;
            opacity: 1 !important;
        }
        
        .form-control:-ms-input-placeholder {
            color: #999 !important;
        }
        
        .form-control::-ms-input-placeholder {
            color: #999 !important;
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
        
        .dual-button {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .dual-button .btn {
            min-width: 150px;
        }
        
        .input-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: bold;
        }
        .badge-marks {
            background-color: #d9edf7;
            color: #31708f;
        }
        .badge-boolean {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .urdu-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 20px;
            font-weight: normal;
            direction: rtl;
            text-align: right;
        }
        
        .urdu-field-label {
            font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;
            font-size: 17px;
            direction: rtl;
            color: #333;
        }
        
        .dual-language-heading {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
        }
        
        .dual-field {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }
        
        /* Card View Styles for Mobile */
        .card-view {
            display: none;
        }
        
        .question-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .question-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 10px;
        }
        
        .question-card .card-number {
            font-weight: bold;
            color: #337ab7;
            font-size: 18px;
        }
        
        .question-card .card-body {
            padding: 5px 0;
        }
        
        .question-card .card-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .question-card .card-row:last-child {
            border-bottom: none;
        }
        
        .question-card .card-label {
            font-weight: bold;
            color: #555;
            font-size: 16px;
        }
        
        .question-card .card-value {
            font-size: 17px;
            text-align: left;
        }
        
        .question-card .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 2px solid #f0f0f0;
            justify-content: center;
        }
        
        .question-card .card-actions .btn {
            flex: 1;
            min-width: unset;
            padding: 8px 15px;
            font-size: 15px;
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
            
            /* Hide table on mobile */
            .table-responsive {
                display: none;
            }
            
            /* Show card view on mobile */
            .card-view {
                display: block;
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
                font-size: 16px !important;
                color: #333 !important;
                background-color: #fff !important;
                padding: 8px 12px !important;
                height: 44px !important;
            }
            .form-control::placeholder {
                color: #999 !important;
            }
            .dual-language-heading {
                flex-direction: column;
                text-align: center;
                gap: 8px;
            }
            
            select.form-control {
                font-size: 16px !important;
                padding: 8px 12px !important;
                height: 44px !important;
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
                font-size: 15px !important;
                color: #333 !important;
                background-color: #fff !important;
                padding: 6px 10px !important;
                height: 40px !important;
            }
            .form-control::placeholder {
                color: #999 !important;
                font-size: 14px !important;
            }
            .input-type-badge {
                font-size: 12px;
                padding: 3px 8px;
            }
            .custom-alert {
                font-size: 15px;
            }
            .dual-button .btn {
                font-size: 14px !important;
                padding: 8px 15px;
            }
            .dual-field {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .question-card {
                padding: 12px;
            }
            .question-card .card-label {
                font-size: 14px;
            }
            .question-card .card-value {
                font-size: 15px;
            }
            .question-card .card-actions .btn {
                font-size: 13px;
                padding: 6px 12px;
            }
            
            select.form-control {
                font-size: 15px !important;
                padding: 6px 10px !important;
                height: 40px !important;
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

            <?php if (isset($_SESSION['message'])): ?>
                <div class="custom-alert">
                    <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message'], $_SESSION['message_type']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Form Panel -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <div class="dual-language-heading">
                        <span class="urdu-label">
                            <?php 
                            echo isset($_GET['edit']) ? 
                                'سوال کی قسم میں ترمیم' : 
                                'نئی سوال کی قسم'; 
                            ?>
                        </span>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" action="">
                        <?php
                        $edit_mode = isset($_GET['edit']);
                        $qt_data = ['id' => '', 'name' => '', 'input_type' => 'marks'];
                        
                        if ($edit_mode) {
                            require_once('conn_inc.php');
                            $id = intval($_GET['edit']);
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
                            <div class="col-md-5 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="question_type_name" class="urdu-field-label">سوال کی قسم کا نام *</label>
                                    </div>
                                    <input type="text" class="form-control" id="question_type_name" name="name" 
                                           value="<?php echo htmlspecialchars($qt_data['name']); ?>" 
                                           placeholder="مثال: السوال الاول، لہجہ، پارہ، تقریر"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <div class="dual-field">
                                        <label for="input_type" class="urdu-field-label">ان پٹ کی قسم *</label>
                                    </div>
                                    <select class="form-control" id="input_type" name="input_type" required style="font-size: 18px !important; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                                        <option value="">-- منتخب کریں --</option>
                                        <option value="marks" <?php echo ($qt_data['input_type'] == 'marks') ? 'selected' : ''; ?>>نمبرات (Marks)</option>
                                        <option value="boolean" <?php echo ($qt_data['input_type'] == 'boolean') ? 'selected' : ''; ?>>خالی جگہ / (Empty)</option>
                                    </select>
                                    <small style="color: #666; display: block; margin-top: 5px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif; font-size: 14px; text-align: right;">
                                        <strong>نمبرات:</strong> اس میں زیادہ سے زیادہ نمبر درج کیے جائیں گے (مثلاً: السوال الاول)<br>
                                        <strong>خالی جگہ / :</strong> اس میں صرف ہاں/نہیں کا انتخاب ہوگا (مثلاً: پارہ، سبق)
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-12">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="dual-button">
                                        <button type="submit" name="submit_question_type" class="btn btn-success btn-lg">
                                            <?php echo $edit_mode ? 'اپ ڈیٹ کریں' : 'محفوظ کریں'; ?>
                                        </button>
                                        <?php if ($edit_mode): ?>
                                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default btn-lg">
                                                منسوخ کریں
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
                        <span class="urdu-label">سوالات کی اقسام کی فہرست</span>
                    </div>
                </div>
                <div class="panel-body">
                    <?php
                    require_once('conn_inc.php');
                    $result = $conn->query("SELECT * FROM question_types ORDER BY id DESC");
                    ?>

                    <?php if ($result && $result->num_rows > 0): ?>
                        <!-- Desktop Table View -->
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th style="text-align: center;">#</th>
                                        <th style="text-align: right;">نام</th>
                                        <th style="text-align: center;">ان پٹ کی قسم</th>
                                        <th style="text-align: center;">کارروائیاں</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; while ($row = $result->fetch_assoc()): 
                                        $input_type_label = ($row['input_type'] == 'boolean') ? 'خالی جگہ' : 'نمبرات';
                                        $badge_class = ($row['input_type'] == 'boolean') ? 'badge-boolean' : 'badge-marks';
                                    ?>
                                        <tr>
                                            <td style="text-align: center;"><?php echo $counter++; ?></td>
                                            <td style="font-size: 18px !important; text-align: right;"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td style="text-align: center;">
                                                <span class="input-type-badge <?php echo $badge_class; ?>">
                                                    <?php echo $input_type_label; ?>
                                                </span>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning" style="font-size: 14px; margin: 2px; min-width: 70px; display: inline-block;">
                                                    ترمیم کریں
                                                </a>
                                                <a href="?delete=<?php echo $row['id']; ?>" 
                                                   class="btn btn-xs btn-danger" 
                                                   onclick="return confirm('کیا آپ واقعی اس سوال کی قسم کو حذف کرنا چاہتے ہیں؟\n\nنام: <?php echo htmlspecialchars($row['name']); ?>');"
                                                   style="font-size: 14px; margin: 2px; min-width: 70px; display: inline-block;">
                                                    حذف کریں
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Mobile Card View -->
                        <div class="card-view">
                            <?php 
                            // Reset the result pointer for card view
                            $result->data_seek(0);
                            $counter = 1; 
                            while ($row = $result->fetch_assoc()): 
                                $input_type_label = ($row['input_type'] == 'boolean') ? 'خالی جگہ' : 'نمبرات';
                                $badge_class = ($row['input_type'] == 'boolean') ? 'badge-boolean' : 'badge-marks';
                            ?>
                                <div class="question-card">
                                    <div class="card-header">
                                        <span class="card-number">#<?php echo $counter++; ?></span>
                                    </div>
                                    <div class="card-body">
                                        <div class="card-row">
                                            <span class="card-label">نام:</span>
                                            <span class="card-value" style="font-size: 18px !important;"><?php echo htmlspecialchars($row['name']); ?></span>
                                        </div>
                                        <div class="card-row">
                                            <span class="card-label">ان پٹ کی قسم:</span>
                                            <span class="card-value">
                                                <span class="input-type-badge <?php echo $badge_class; ?>">
                                                    <?php echo $input_type_label; ?>
                                                </span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-actions">
                                        <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-warning">
                                            ترمیم کریں
                                        </a>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-danger" 
                                           onclick="return confirm('کیا آپ واقعی اس سوال کی قسم کو حذف کرنا چاہتے ہیں؟\n\nنام: <?php echo htmlspecialchars($row['name']); ?>');">
                                            حذف کریں
                                        </a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                    <?php else: ?>
                        <div class="alert alert-info text-center" style="font-size: 18px; padding: 20px; font-family: 'Noto Nastaliq Urdu', 'Alvi Nastaleeq', 'Jameel Noori Nastaleeq', 'Urdu Typesetting', serif;">
                            کوئی سوال کی قسم نہیں ملی۔
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
setTimeout(function() { $('.custom-alert').fadeOut('slow'); }, 10000);
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>