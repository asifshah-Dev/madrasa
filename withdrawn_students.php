<?php 
header('Content-Type: text/html; charset=utf-8');
require_once('security.php');

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}

$lang = $_SESSION['lang'];

// Language strings (reusing the same translations as in the original code)
$translations = [
    'en' => [
        'title' => 'Withdrawn Students',
        'list_title' => 'Withdrawn Students List',
        'no_records' => 'No withdrawn students found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'reactivate' => 'Reactivate',
        'reactivate_confirm' => 'Are you sure you want to reactivate this student?',
        'reactivate_success' => 'Student reactivated successfully!',
        'error' => 'Error: ',
        'name_label' => 'Student Name:',
        'father_name_label' => 'Father Name:',
        'mobile_label' => 'Mobile:',
        'reg_no_label' => 'Registration Number:',
        'guardian_name_label' => 'Guardian Name:',
        'guardian_mobile_label' => 'Guardian Mobile:',
        'guardian_cnic_label' => 'Guardian CNIC:',
        'branch_label' => 'Branch:',
        'class_or_course' => 'Class/Course:',
        'session_label' => 'Session:',
        'student_type_label' => 'Student Type:'
    ],
    'ur' => [
        'title' => 'اخراج',
        'list_title' => 'واپس لیے گئے طلباء کی فہرست',
        'no_records' => 'کوئی واپس لیے گئے طلباء موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'reactivate' => 'دوبارہ فعال کریں',
        'reactivate_confirm' => 'کیا آپ واقعی اس طالب علم کو دوبارہ فعال کرنا چاہتے ہیں؟',
        'reactivate_success' => 'طالب علم کامیابی سے دوبارہ فعال ہو گیا!',
        'error' => 'خرابی: ',
        'name_label' => 'طالب علم کا نام:',
        'father_name_label' => 'والد کا نام:',
        'mobile_label' => 'موبائل:',
        'reg_no_label' => 'رجسٹریشن نمبر:',
        'guardian_name_label' => 'سرپرست کا نام:',
        'guardian_mobile_label' => 'سرپرست کا موبائل:',
        'guardian_cnic_label' => 'سرپرست کا قومی شناختی کارڈ:',
        'branch_label' => 'برانچ:',
        'class_or_course' => 'کلاس/کورس:',
        'session_label' => 'سیشن:',
        'student_type_label' => 'طالب علم کی قسم:'
    ]
];

// Create database connection
require_once('conn_inc.php');

// Handle student reactivation
if (isset($_GET['reactivate'])) {
    $id = intval($_GET['reactivate']);
    $stmt = $conn->prepare("UPDATE student_class SET status = 0 WHERE student_registration_id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $translations[$lang]['reactivate_success'];
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
        $_SESSION['message_type'] = 'danger';
    }
    
    $stmt->close();
    header("Location: withdrawn_students.php");
    exit();
}

// Get data for listing withdrawn students
$result = $conn->query("
    SELECT 
        sr.*, 
        vc.title AS village_council,
        b.title AS branch_title,
        c.title AS class_title,
        co.title AS course_title,
        s.title AS session_title
    FROM student_registration sr
    LEFT JOIN village_councils vc ON sr.village_council_id = vc.id
    LEFT JOIN branches b ON sr.branch_id = b.id
    LEFT JOIN (
        SELECT sc1.*
        FROM student_class sc1
        INNER JOIN (
            SELECT student_registration_id, MAX(id) AS max_id
            FROM student_class
            GROUP BY student_registration_id
        ) sc2 ON sc1.id = sc2.max_id
    ) sc ON sr.id = sc.student_registration_id
    LEFT JOIN classes c ON sc.class_id = c.id
    LEFT JOIN courses co ON c.course_id = co.id
    LEFT JOIN sessions s ON sc.session_id = s.id
    WHERE sr.status = 1 or sc.status = 1
    ORDER BY CAST(sr.reg_no AS UNSIGNED) ASC
");
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <title><?php echo $translations[$lang]['title']; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="css/mystyle.css" />
    <style>
    <?php if ($lang == 'ur'): ?>
    body, .form-control, .btn, .alert, .navbar-nav {
        text-align: right;
        direction: rtl;
    }
    .dropdown-menu {
        text-align: right;
        left: 50px;
        right: auto;
    }
    <?php else: ?>
    .table th, .table td {
        text-align: left;
    }
    <?php endif; ?>
    
    /* Table styling */
    .table, .table-striped {
        font-size: 14px;
        color: #000 !important;
        border-collapse: collapse;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .table th {
        font-size: 16px;
        font-weight: 700;
        color: #fff !important;
        background-color: #34495e !important;
        padding: 12px !important;
        border: 1px solid #e2e8f0 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .table td, .table-striped td {
        color: #000 !important;
        padding: 10px !important;
        border: 1px solid #e2e8f0 !important;
        vertical-align: middle;
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f9fbfc !important;
    }
    
    .table-striped tbody tr:nth-of-type(even) {
        background-color: #ffffff !important;
    }
    
    .table-striped tbody tr:hover {
        background-color: #edf2f7 !important;
        transition: background-color 0.3s ease;
    }
    
    .priority-1 {
        font-size: 16px;
        font-weight: 600;
        color: #1a202c !important;
        display: block;
        margin-bottom: 4px;
    }
    
    .priority-2 {
        font-size: 15px;
        font-weight: 500;
        color: #2d3748 !important;
        display: block;
        margin-bottom: 4px;
    }
    
    .priority-3 {
        font-size: 14px;
        font-weight: 400;
        color: #4a5568 !important;
        display: block;
        margin-bottom: 4px;
    }
    
    .priority-4 {
        font-size: 14px;
        font-weight: 400;
        color: #718096 !important;
        display: block;
    }
    
    .table td .btn-xs {
        margin-right: 5px;
        padding: 5px 10px;
        font-size: 13px;
    }
    
    .panel-heading h3 {
        font-size: 20px;
    }
    </style>
</head>
<body>
<?php require_once('navbar.php'); ?>

<!-- Language switcher -->
<div class="container text-right">
    <a href="?lang=en" class="btn btn-xs btn-default <?php echo ($lang == 'en') ? 'active' : ''; ?>">English</a>
    <a href="?lang=ur" class="btn btn-xs btn-default <?php echo ($lang == 'ur') ? 'active' : ''; ?>">اردو</a>
</div>

<!-- Dashboard -->
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <!-- Panel for Withdrawn Students List -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $translations[$lang]['list_title']; ?></h3>
                </div>
                <div class="panel-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                            <?php 
                            echo $_SESSION['message']; 
                            unset($_SESSION['message'], $_SESSION['message_type']);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th width="5%"><?php echo $translations[$lang]['sr_no']; ?></th>
                                        <th width="20%"><?php echo ($lang == 'ur') ? 'طالب علم کی معلومات' : 'Student Information'; ?></th>
                                        <th width="20%"><?php echo ($lang == 'ur') ? 'سرپرست کی معلومات' : 'Guardian Information'; ?></th>
                                        <th width="20%"><?php echo ($lang == 'ur') ? 'کلاس/کورس کی معلومات' : 'Class/Course Information'; ?></th>
                                        <th width="10%"><?php echo $translations[$lang]['student_type_label']; ?></th>
                                        <th width="25%"><?php echo $translations[$lang]['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        $count = 1;
                                        while ($row = $result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $count++; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                                                <?php echo $translations[$lang]['father_name_label']; ?>: <?php echo htmlspecialchars($row['father_name']); ?><br>
                                                <?php echo $translations[$lang]['mobile_label']; ?>: <?php echo htmlspecialchars($row['mobile']); ?><br>
                                                <?php echo $translations[$lang]['reg_no_label']; ?>: <?php echo htmlspecialchars($row['reg_no']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($row['guardian_name']); ?><br>
                                                <?php echo $translations[$lang]['guardian_mobile_label']; ?>: <?php echo htmlspecialchars($row['guardian_mobile']); ?><br>
                                                <?php echo $translations[$lang]['guardian_cnic_label']; ?>: <?php echo htmlspecialchars($row['guardian_cnic']); ?>
                                            </td>
                                            <td>
                                                <?php echo $translations[$lang]['branch_label']; ?>: <?php echo htmlspecialchars($row['branch_title']); ?><br>
                                                <?php echo $translations[$lang]['class_or_course']; ?>: 
                                                <?php 
                                                    if (!empty($row['course_title'])) {
                                                        echo htmlspecialchars($row['course_title']);
                                                        if (!empty($row['class_title'])) {
                                                            echo ' - ' . htmlspecialchars($row['class_title']);
                                                        }
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?><br>
                                                <?php echo $translations[$lang]['session_label']; ?>: <?php echo htmlspecialchars($row['session_title'] ?? '-'); ?>
                                            </td>
                                            <td>
                                                <?php echo ucfirst(htmlspecialchars($row['student_type'])); ?>
                                            </td>
                                            <td>
                                                <a href="?reactivate=<?php echo $row['id']; ?>" class="btn btn-success btn-xs" onclick="return confirm('<?php echo $translations[$lang]['reactivate_confirm']; ?>')"><?php echo $translations[$lang]['reactivate']; ?></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info"><?php echo $translations[$lang]['no_records']; ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connection
$conn->close();
?>
</body>
</html>