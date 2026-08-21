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
        'title' => 'Classes Management',
        'add_title' => 'Add New Classes',
        'edit_title' => 'Edit Class',
        'course_label' => 'Select Course:',
        'classes_label' => 'Select Classes:',
        'title_label' => 'Class Title:',
        'title_placeholder' => 'Enter class title',
        'date_label' => 'Date:',
        'status_label' => 'Status:',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Classes added successfully!',
        'update_success' => 'Class updated successfully!',
        'delete_success' => 'Class deleted successfully!',
        'error' => 'Error: ',
        'empty_error' => 'Required fields cannot be empty!',
        'no_classes_selected' => 'Please select at least one class!',
        'list_title' => 'Classes List',
        'no_records' => 'No classes found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'select_option' => '-- Select --',
        'invalid_csrf' => 'Invalid request!'
    ],
    'ur' => [
        'title' => 'کلاسز کا انتظام',
        'add_title' => 'نئی کلاسیں شامل کریں',
        'edit_title' => 'کلاس میں ترمیم کریں',
        'course_label' => 'کورس منتخب کریں:',
        'classes_label' => 'کلاسیں منتخب کریں:',
        'title_label' => 'کلاس کا عنوان:',
        'title_placeholder' => 'کلاس کا عنوان درج کریں',
        'date_label' => 'تاریخ:',
        'status_label' => 'حالت:',
        'status_active' => 'فعال',
        'status_inactive' => 'غیر فعال',
        'submit' => 'جمع کرائیں',
        'reset' => 'دوبارہ ترتیب دیں',
        'success' => 'کلاسیں کامیابی سے شامل ہو گئیں!',
        'update_success' => 'کلاس کامیابی سے اپ ڈیٹ ہو گئی!',
        'delete_success' => 'کلاس کامیابی سے حذف ہو گئی!',
        'error' => 'خرابی: ',
        'empty_error' => 'ضروری فیلڈز خالی نہیں ہو سکتیں!',
        'no_classes_selected' => 'براہ کرم کم از کم ایک کلاس منتخب کریں!',
        'list_title' => 'کلاسز کی فہرست',
        'no_records' => 'کوئی کلاس موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'edit' => 'ترمیم کریں',
        'delete' => 'حذف کریں',
        'save' => 'محفوظ کریں',
        'cancel' => 'منسوخ کریں',
        'select_option' => '-- منتخب کریں --',
        'invalid_csrf' => 'غلط درخواست!'
    ]
];

// Create database connection
require_once('conn_inc.php');

// Get data for edit form
$edit_mode = false;
$class_data = [
    'id' => '',
    'course_id' => '',
    'title' => '',
    'dated' => date('Y-m-d'),
    'status' => 1
];

if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT id, course_id, title, dated, status FROM classes WHERE id = $id");
    
    if ($result->num_rows > 0) {
        $class_data = $result->fetch_assoc();
        $edit_mode = true;
    }
    $result->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['message'] = $translations[$lang]['invalid_csrf'];
        $_SESSION['message_type'] = 'danger';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Regenerate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $course_id = intval($_POST['course_id']);
    $class_titles = isset($_POST['class_titles']) ? $_POST['class_titles'] : [];
    $dated = $_POST['dated'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    $errors = [];
    if (empty($course_id)) $errors[] = 'course_id';
    if (empty($class_titles)) {
        $errors[] = 'class_titles';
        $_SESSION['message'] = $translations[$lang]['no_classes_selected'];
        $_SESSION['message_type'] = 'danger';
    }

    if (empty($errors)) {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // Update existing class
            $id = intval($_POST['id']);
            $title = trim($class_titles[0]); // Single title for edit mode
            if (!empty($title)) {
                $stmt = $conn->prepare("UPDATE classes SET course_id = ?, title = ?, dated = ?, status = ? WHERE id = ?");
                $stmt->bind_param("issii", $course_id, $title, $dated, $status, $id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = $translations[$lang]['update_success'];
                    $_SESSION['message_type'] = 'success';
                } else {
                    error_log("Database error: " . $stmt->error, 3, 'errors.log');
                    $_SESSION['message'] = $translations[$lang]['error'] . 'An unexpected error occurred.';
                    $_SESSION['message_type'] = 'danger';
                }
                $stmt->close();
            } else {
                $_SESSION['message'] = $translations[$lang]['empty_error'];
                $_SESSION['message_type'] = 'danger';
            }
        } else {
            // Insert new classes
            $stmt = $conn->prepare("INSERT INTO classes (course_id, title, dated, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $course_id, $title, $dated, $status);
            $success = true;

            foreach ($class_titles as $title) {
                $title = trim($title);
                if (!empty($title)) {
                    if (!$stmt->execute()) {
                        $success = false;
                        error_log("Database error: " . $stmt->error, 3, 'errors.log');
                        break;
                    }
                }
            }

            if ($success) {
                $_SESSION['message'] = $translations[$lang]['success'];
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = $translations[$lang]['error'] . 'An unexpected error occurred.';
                $_SESSION['message_type'] = 'danger';
            }
            $stmt->close();
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        if (!isset($_SESSION['message'])) {
            $_SESSION['message'] = $translations[$lang]['empty_error'];
            $_SESSION['message_type'] = 'danger';
        }
    }
}

// Handle class deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = $translations[$lang]['delete_success'];
        $_SESSION['message_type'] = 'success';
    } else {
        error_log("Database error: " . $stmt->error, 3, 'errors.log');
        $_SESSION['message'] = $translations[$lang]['error'] . 'An unexpected error occurred.';
        $_SESSION['message_type'] = 'danger';
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get dropdown options
$courses = [];
$course_result = $conn->query("SELECT id, title FROM courses ORDER BY title");
while ($row = $course_result->fetch_assoc()) {
    $courses[$row['id']] = $row['title'];
}
$course_result->close();

// Get data for listing, grouped by course
$result = $conn->query("
    SELECT c.id, c.course_id, c.title, c.dated, c.status, 
           cr.title AS course_title
    FROM classes c
    JOIN courses cr ON c.course_id = cr.id
    ORDER BY cr.title, c.title
");
$classes_by_course = [];
$course_ids = [];
while ($row = $result->fetch_assoc()) {
    $classes_by_course[$row['course_id']]['course_title'] = $row['course_title'];
    $classes_by_course[$row['course_id']]['classes'][] = $row;
    $course_ids[] = $row['course_id'];
}
$result->close();

// Define color palette for courses
$colors = [
    '#e6f3ff', // Light Blue
    '#e6ffe6', // Light Green
    '#fff3e6', // Light Orange
    '#ffe6e6', // Light Red
    '#f3e6ff', // Light Purple
    '#e6fff3', // Light Mint
    '#ffffe6', // Light Yellow
    '#f0f0f0'  // Light Gray
];
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
    <title><?php echo $translations[$lang]['title']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <style>
        <?php if ($lang == 'ur'): ?>
        body, .form-control, .btn, .alert, .navbar-nav {
            text-align: right;
            direction: rtl;
        }
        .dropdown-menu {
            text-align: right;
            left: auto;
            right: 0;
        }
        .table th, .table td {
            text-align: right;
        }
        .checkbox label {
            padding-right: 20px;
        }
        <?php else: ?>
        .table th, .table td {
            text-align: left;
        }
        .checkbox label {
            padding-left: 20px;
        }
        <?php endif; ?>
        .classes-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 10px;
        }
        .class-item {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .class-item:last-child {
            border-bottom: none;
        }
        .status-active {
            color: #3c763d;
            font-weight: bold;
        }
        .status-inactive {
            color: #a94442;
            font-weight: bold;
        }
        .course-header {
            font-weight: bold;
            font-size: 16px;
        }
        <?php for ($i = 0; $i < count($colors); $i++): ?>
        .course-<?php echo $i; ?> {
            background-color: <?php echo $colors[$i]; ?>;
        }
        <?php endfor; ?>
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<!-- Language switcher -->
<div class="container text-right">
    <?php
    $current_query = $_GET;
    $current_query['lang'] = 'en';
    $en_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
    $current_query['lang'] = 'ur';
    $ur_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
    ?>
    <a href="<?php echo htmlspecialchars($en_url); ?>" class="btn btn-xs btn-default <?php echo ($lang == 'en') ? 'active' : ''; ?>">English</a>
    <a href="<?php echo htmlspecialchars($ur_url); ?>" class="btn btn-xs btn-default <?php echo ($lang == 'ur') ? 'active' : ''; ?>">اردو</a>
</div>

<!-- Dashboard -->
<div class="container">
    <div class="row">
        <div class="col-md-10 col-md-offset-1">

            <!-- Panel for Add/Edit Classes -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $edit_mode ? $translations[$lang]['edit_title'] : $translations[$lang]['add_title']; ?></h3>
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
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <?php if ($edit_mode): ?>
                            <input type="hidden" name="id" value="<?php echo $class_data['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label><?php echo $translations[$lang]['course_label']; ?></label>
                            <select name="course_id" id="course_id" class="form-control" required>
                                <option value=""><?php echo $translations[$lang]['select_option']; ?></option>
                                <?php foreach ($courses as $id => $title): ?>
                                    <option value="<?php echo $id; ?>" <?php echo ($id == $class_data['course_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $translations[$lang]['classes_label']; ?></label>
                            <div class="classes-container">
                                <div id="classes_list">
                                    <?php if ($edit_mode): ?>
                                        <div class="class-item">
                                            <input type="text" class="form-control" name="class_titles[]" 
                                                   placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>" 
                                                   value="<?php echo htmlspecialchars($class_data['title']); ?>" required>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted"><?php echo ($lang == 'ur') ? 'براہ کرم پہلے کورس منتخب کریں' : 'Please select a course first'; ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$edit_mode): ?>
                                    <button type="button" id="add_class_btn" class="btn btn-xs btn-primary" style="margin-top: 10px;">
                                        <span class="glyphicon glyphicon-plus"></span> 
                                        <?php echo ($lang == 'ur') ? 'نیا کلاس شامل کریں' : 'Add New Class'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $translations[$lang]['date_label']; ?></label>
                            <input type="date" class="form-control" name="dated" required 
                                   value="<?php echo htmlspecialchars($class_data['dated']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label><?php echo $translations[$lang]['status_label']; ?></label>
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="status" value="1" 
                                           <?php echo $class_data['status'] ? 'checked' : ''; ?>>
                                    <?php echo $translations[$lang]['status_active']; ?>
                                </label>
                            </div>
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-success">
                                <span class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'plus'; ?>"></span> 
                                <?php echo $edit_mode ? $translations[$lang]['save'] : $translations[$lang]['submit']; ?>
                            </button>
                            <?php if ($edit_mode): ?>
                                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-default">
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

            <!-- Panel for Classes List -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $translations[$lang]['list_title']; ?></h3>
                </div>
                <div class="panel-body">

                    <?php if (!empty($classes_by_course)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['sr_no']; ?></th>
                                        <th><?php echo $translations[$lang]['title_label']; ?></th>
                                        <th><?php echo $translations[$lang]['date_label']; ?></th>
                                        <th><?php echo $translations[$lang]['status_label']; ?></th>
                                        <th><?php echo $translations[$lang]['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1; 
                                    $course_index = 0;
                                    foreach ($classes_by_course as $course_id => $data): 
                                        $color_class = 'course-' . ($course_index % count($colors));
                                        $course_index++;
                                    ?>
                                        <tr class="course-header <?php echo $color_class; ?>">
                                            <th colspan="5"><?php echo htmlspecialchars($data['course_title']); ?></th>
                                        </tr>
                                        <?php foreach ($data['classes'] as $row): ?>
                                            <tr class="<?php echo $color_class; ?>">
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                <td><?php echo date('d M Y', strtotime($row['dated'])); ?></td>
                                                <td>
                                                    <span class="<?php echo $row['status'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $row['status'] ? $translations[$lang]['status_active'] : $translations[$lang]['status_inactive']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">
                                                        <span class="glyphicon glyphicon-edit"></span> 
                                                        <?php echo $translations[$lang]['edit']; ?>
                                                    </a>
                                                    <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-xs btn-danger" 
                                                       onclick="return confirm('<?php echo ($lang == 'ur') ? 'کیا آپ واقعی اس کلاس کو حذف کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this class?'; ?>')">
                                                        <span class="glyphicon glyphicon-trash"></span> 
                                                        <?php echo $translations[$lang]['delete']; ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
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

<script>
$(document).ready(function() {
    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);

    // Add new class input
    var classCounter = 0;
    $('#add_class_btn').click(function() {
        if ($('#course_id').val() === '') {
            alert('<?php echo ($lang == 'ur') ? 'براہ کرم پہلے کورس منتخب کریں' : 'Please select a course first'; ?>');
            return;
        }
        
        var newClassHtml = '<div class="class-item" id="class_item_' + classCounter + '">' +
            '<div class="input-group">' +
            '<input type="text" class="form-control" name="class_titles[]" placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>" required>' +
            '<span class="input-group-btn">' +
            '<button type="button" class="btn btn-danger remove-class" data-id="' + classCounter + '">' +
            '<span class="glyphicon glyphicon-remove"></span>' +
            '</button>' +
            '</span>' +
            '</div>' +
            '</div>';
        
        $('#classes_list').append(newClassHtml);
        classCounter++;
    });

    // Remove class input
    $(document).on('click', '.remove-class', function() {
        var id = $(this).data('id');
        $('#class_item_' + id).remove();
    });

    // Course selection change
    $('#course_id').change(function() {
        if ($(this).val() !== '') {
            $('#add_class_btn').prop('disabled', false);
            <?php if (!$edit_mode): ?>
                $('#classes_list').html('');
            <?php endif; ?>
        } else {
            $('#add_class_btn').prop('disabled', true);
            <?php if (!$edit_mode): ?>
                $('#classes_list').html('<p class="text-muted"><?php echo ($lang == 'ur') ? 'براہ کرم پہلے کورس منتخب کریں' : 'Please select a course first'; ?></p>');
            <?php endif; ?>
        }
    });

    // Initialize add class button state
    <?php if ($edit_mode || empty($class_data['course_id'])): ?>
        $('#add_class_btn').prop('disabled', true);
    <?php else: ?>
        '#add_class_btn').prop('disabled', false);
    <?php endif; ?>
});
</script>

</body>
</html>
<?php
// Close database connection
$conn->close();
?>