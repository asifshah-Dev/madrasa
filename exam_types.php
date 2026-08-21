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
        $check = $conn->prepare("SELECT id, name FROM exam_types WHERE id = ?");
        $check->bind_param("i", $delete_id);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("امتحان کی قسم نہیں ملی!");
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
            throw new Exception("حذف نہیں کیا جا سکتا! یہ امتحان کی قسم {$usage['count']} امتحان(ات) میں استعمال ہو رہی ہے۔ براہ کرم پہلے ان امتحانات کو حذف کریں۔");
        }
        
        $stmt = $conn->prepare("DELETE FROM exam_types WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>امتحان کی قسم کامیابی سے حذف کر دی گئی۔</div>";
            $_SESSION['message_type'] = "success";
        } else {
            throw new Exception("امتحان کی قسم حذف کرنے میں ناکامی: " . $stmt->error);
        }
        $stmt->close();
        
    } catch (Exception $e) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ حذف کرنے میں ناکامی!</strong><br>" . $e->getMessage() . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_exam_type'])) {
    require_once('conn_inc.php');
    
    $name = trim($_POST['name']);
    $exam_type_id = isset($_POST['exam_type_id']) ? intval($_POST['exam_type_id']) : 0;
    $is_edit = ($exam_type_id > 0);
    
    if (empty($name)) {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>امتحان کی قسم کا نام ضروری ہے۔</div>";
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
        $_SESSION['message'] = "<div style='color: #3c763d; background-color: #dff0d8; border: 1px solid #d6e9c6; padding: 15px; border-radius: 4px;'><strong>✓ کامیابی!</strong><br>امتحان کی قسم کامیابی سے " . ($is_edit ? 'اپ ڈیٹ' : 'محفوظ') . " کر دی گئی۔</div>";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "<div style='color: #a94442; background-color: #f2dede; border: 1px solid #ebccd1; padding: 15px; border-radius: 4px;'><strong>✗ خرابی!</strong><br>ناکامی: " . $stmt->error . "</div>";
        $_SESSION['message_type'] = "danger";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="ur">
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
            height: 55px !important;
            padding: 8px 12px !important;
            line-height: 1.5 !important;
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
        .btn-question-types { background-color: #f0ad4e; }
        .btn-class-questions { background-color: #5cb85c; }
        
        .dual-button {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .dual-button .btn {
            min-width: 150px;
            height: 55px !important;
            font-size: 18px !important;
            padding: 0 25px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            line-height: 1 !important;
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
                height: 55px !important;
                font-size: 18px !important;
            }
            .container { 
                padding-left: 10px; 
                padding-right: 10px; 
            }
            .table th, .table td { 
                font-size: 15px; 
            }
            
            .form-control {
                height: 55px !important;
                font-size: 18px !important;
                padding: 8px 12px !important;
                line-height: 1.5 !important;
            }
        }
        
        @media (max-width: 480px) {
            .nav-scroll a {
                font-size: 12px;
                padding: 6px 12px;
                margin-right: 5px;
            }
            .table th, .table td { 
                font-size: 14px; 
            }
            
            .form-control {
                height: 55px !important;
                font-size: 17px !important;
                padding: 8px 10px !important;
                line-height: 1.5 !important;
            }
            
            .dual-button .btn {
                height: 50px !important;
                font-size: 16px !important;
            }
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container">
    <div class="row">
        <div class="col-md-12">

            <!-- Navigation - EXACT ORDER: Exams FIRST, Question Types SECOND, Class Question Types THIRD -->
            <div class="nav-scroll">
                <a href="exams.php" class="btn-exams">امتحانات / Exams</a>
                <a href="question_types.php" class="btn-question-types">سوالات کی اقسام / Question Types</a>
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
                    <?php 
                    echo isset($_GET['edit']) ? 
                        'امتحان کی قسم میں ترمیم کریں' : 
                        'نئی امتحان کی قسم شامل کریں'; 
                    ?>
                </div>
                <div class="panel-body">
                    <form method="post" action="">
                        <?php
                        $edit_mode = isset($_GET['edit']);
                        $exam_type_data = ['id' => '', 'name' => ''];
                        
                        if ($edit_mode) {
                            require_once('conn_inc.php');
                            $id = intval($_GET['edit']);
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
                                    <label for="exam_type_name">امتحان کی قسم کا نام *</label>
                                    <input type="text" class="form-control" id="exam_type_name" name="name" 
                                           value="<?php echo htmlspecialchars($exam_type_data['name']); ?>" 
                                           placeholder="مثال: ماہانہ ٹیسٹ، ششماہی، سالانہ امتحان"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-12">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <div class="dual-button">
                                        <button type="submit" name="submit_exam_type" class="btn btn-success">
                                            <?php echo $edit_mode ? 'اپ ڈیٹ کریں' : 'محفوظ کریں'; ?>
                                        </button>
                                        <?php if ($edit_mode): ?>
                                            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-default">
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
                    امتحان کی اقسام کی فہرست
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
                                        <th>نمبر</th>
                                        <th>نام</th>
                                        <th>کارروائیاں</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td style="font-size: 18px !important;"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning" style="font-size: 14px; margin: 2px; min-width: 70px; display: inline-block;">
                                                    ترمیم کریں
                                                </a>
                                                <a href="?delete=<?php echo $row['id']; ?>" 
                                                   class="btn btn-xs btn-danger" 
                                                   onclick="return confirm('کیا آپ واقعی اس امتحان کی قسم کو حذف کرنا چاہتے ہیں؟\n\nنام: <?php echo htmlspecialchars($row['name']); ?>');"
                                                   style="font-size: 14px; margin: 2px; min-width: 70px; display: inline-block;">
                                                    حذف کریں
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center" style="font-size: 18px;">
                            کوئی امتحان کی قسم نہیں ملی۔
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