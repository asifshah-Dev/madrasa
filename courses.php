<?php 
require_once('security.php');

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
        'title' => 'Courses Management',
        'add_title' => 'Add New Course',
        'title_label' => 'Course Title:',
        'title_placeholder' => 'Enter course title',
        'duration_label' => 'Duration:',
        'duration_placeholder' => 'Enter duration (e.g., 3 months)',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Course added successfully!',
        'update_success' => 'Course updated successfully!',
        'error' => 'Error: ',
        'empty_error' => 'Title and duration cannot be empty!',
        'list_title' => 'Courses List',
        'no_records' => 'No courses found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel'
    ],
    'ur' => [
        'title' => 'کورسز کا انتظام',
        'add_title' => 'نیا کورس شامل کریں',
        'title_label' => 'کورس کا عنوان:',
        'title_placeholder' => 'کورس کا عنوان درج کریں',
        'duration_label' => 'دورانیہ:',
        'duration_placeholder' => 'دورانیہ درج کریں (مثال کے طور پر، 3 ماہ)',
        'submit' => 'جمع کرائیں',
        'reset' => 'دوبارہ ترتیب دیں',
        'success' => 'کورس کامیابی سے شامل ہو گیا!',
        'update_success' => 'کورس کامیابی سے اپ ڈیٹ ہو گیا!',
        'error' => 'خرابی: ',
        'empty_error' => 'عنوان اور دورانیہ خالی نہیں ہو سکتے!',
        'list_title' => 'کورسز کی فہرست',
        'no_records' => 'کوئی کورس موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'edit' => 'ترمیم کریں',
        'save' => 'محفوظ کریں',
        'cancel' => 'منسوخ کریں'
    ]
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once('conn_inc.php');
    
    $title = trim($_POST['title']);
    $duration = trim($_POST['duration']);
    
    if (!empty($title) && !empty($duration)) {
        if (isset($_POST['course_id']) && !empty($_POST['course_id'])) {
            // Update existing course
            $course_id = intval($_POST['course_id']);
            $stmt = $conn->prepare("UPDATE courses SET title = ?, duration = ? WHERE id = ?");
            $stmt->bind_param("ssi", $title, $duration, $course_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $translations[$lang]['update_success'];
                $_SESSION['message_type'] = "success";
            }
        } else {
            // Insert new course
            $stmt = $conn->prepare("INSERT INTO courses (title, duration) VALUES (?, ?)");
            $stmt->bind_param("ss", $title, $duration);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $translations[$lang]['success'];
                $_SESSION['message_type'] = "success";
            }
        }
        
        if ($stmt->error) {
            $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['message'] = $translations[$lang]['empty_error'];
        $_SESSION['message_type'] = "danger";
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <?php require_once('meta_inc.php'); ?>
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
        <div class="col-md-10 col-md-offset-1">

            <!-- Panel for Add/Edit Course -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?php 
                        echo isset($_GET['edit']) ? 
                            ($lang == 'ur' ? 'کورس کی ترمیم کریں' : 'Edit Course') : 
                            $translations[$lang]['add_title']; 
                        ?>
                    </h3>
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

                    <form method="post" action="">
                        <?php
                        $edit_mode = false;
                        $course_data = ['id' => '', 'title' => '', 'duration' => ''];
                        
                        if (isset($_GET['edit'])) {
                            require_once('conn_inc.php');
                            $id = intval($_GET['edit']);
                            $result = $conn->query("SELECT id, title, duration FROM courses WHERE id = $id");
                            
                            if ($result->num_rows > 0) {
                                $course_data = $result->fetch_assoc();
                                $edit_mode = true;
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="course_id" value="<?php echo $course_data['id']; ?>">
                        
                        <div class="form-group">
                            <label for="title"><?php echo $translations[$lang]['title_label']; ?></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>"
                                   value="<?php echo htmlspecialchars($course_data['title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="duration"><?php echo $translations[$lang]['duration_label']; ?></label>
                            <input type="text" class="form-control" id="duration" name="duration" required 
                                   placeholder="<?php echo $translations[$lang]['duration_placeholder']; ?>"
                                   value="<?php echo htmlspecialchars($course_data['duration']); ?>">
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-success">
                                <span class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'plus'; ?>"></span> 
                                <?php echo $translations[$lang]['submit']; ?>
                            </button>
                            
                            <?php if ($edit_mode): ?>
                                <a href="courses.php" class="btn btn-default">
                                    <span class="glyphicon glyphicon-remove"></span> 
                                    <?php echo $translations[$lang]['cancel']; ?>
                                </a>
                            <?php else: ?>
                                <button type="reset" class="btn btn-default">
                                    <span class="glyphicon glyphicon-refresh"></span> 
                                    <?php echo $translations[$lang]['reset']; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panel for Courses List -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $translations[$lang]['list_title']; ?></h3>
                </div>
                <div class="panel-body">

                    <?php
                    require_once('conn_inc.php');
                    $result = $conn->query("SELECT id, title, duration FROM courses ORDER BY id DESC");
                    ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['sr_no']; ?></th>
                                        <th><?php echo $translations[$lang]['title_label']; ?></th>
                                        <th><?php echo $translations[$lang]['duration_label']; ?></th>
                                        <th><?php echo $translations[$lang]['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo htmlspecialchars($row['duration']); ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">
                                                    <span class="glyphicon glyphicon-edit"></span> 
                                                    <?php echo $translations[$lang]['edit']; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            <?php echo $translations[$lang]['no_records']; ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script src="js/mobile_menu.js"></script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>