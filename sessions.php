<?php 
require_once('security.php');
require_once('conn_inc.php');

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
        'title' => 'Sessions Management',
        'add_title' => 'Add New Session',
        'title_label' => 'Session Title:',
        'title_placeholder' => 'Enter session title',
        'from_date_label' => 'From Date:',
        'to_date_label' => 'To Date:',
        'duration_label' => 'Duration',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Session added successfully!',
        'update_success' => 'Session updated successfully!',
        'error' => 'Error: ',
        'empty_error' => 'Title, From Date, and To Date cannot be empty!',
        'date_error' => 'To Date must be after From Date!',
        'list_title' => 'Sessions List',
        'no_records' => 'No sessions found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel'
    ],
    'ur' => [
        'title' => 'سیشنز کا انتظام',
        'add_title' => 'نیا سیشن شامل کریں',
        'title_label' => 'سیشن کا عنوان:',
        'title_placeholder' => 'سیشن کا عنوان درج کریں',
        'from_date_label' => 'از تاریخ:',
        'to_date_label' => 'تا تاریخ:',
        'duration_label' => 'دورانیہ',
        'submit' => 'جمع کرائیں',
        'reset' => 'دوبارہ ترتیب دیں',
        'success' => 'سیشن کامیابی سے شامل ہو گیا!',
        'update_success' => 'سیشن کامیابی سے اپ ڈیٹ ہو گیا!',
        'error' => 'خرابی: ',
        'empty_error' => 'عنوان، از تاریخ، اور تا تاریخ خالی نہیں ہو سکتے!',
        'date_error' => 'تا تاریخ از تاریخ کے بعد ہونی چاہیے!',
        'list_title' => 'سیشنز کی فہرست',
        'no_records' => 'کوئی سیشن موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'edit' => 'ترمیم کریں',
        'save' => 'محفوظ کریں',
        'cancel' => 'منسوخ کریں'
    ]
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $from_dated = $_POST['from_dated'];
    $to_dated = $_POST['to_dated'];
    
    $date_error = '';
    if (strtotime($to_dated) <= strtotime($from_dated)) {
        $date_error = $translations[$lang]['date_error'];
    }
    
    if (!empty($title) && !empty($from_dated) && !empty($to_dated) && empty($date_error)) {
        if (isset($_POST['session_id']) && !empty($_POST['session_id'])) {
            // Update existing session
            $session_id = intval($_POST['session_id']);
            $stmt = $conn->prepare("UPDATE sessions SET title = ?, from_dated = ?, to_dated = ? WHERE id = ?");
            $stmt->bind_param("sssi", $title, $from_dated, $to_dated, $session_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $translations[$lang]['update_success'];
                $_SESSION['message_type'] = "success";
            }
        } else {
            // Insert new session
            $stmt = $conn->prepare("INSERT INTO sessions (title, from_dated, to_dated) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $from_dated, $to_dated);
            
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
        $_SESSION['message'] = empty($title) || empty($from_dated) || empty($to_dated) ? 
            $translations[$lang]['empty_error'] : $date_error;
        $_SESSION['message_type'] = "danger";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM sessions WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = ($lang == 'ur') ? 'سیشن کامیابی سے حذف ہو گیا!' : 'Session deleted successfully!';
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = $translations[$lang]['error'] . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    
    $stmt->close();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
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

            <!-- Panel for Add/Edit Session -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?php 
                        echo isset($_GET['edit']) ? 
                            ($lang == 'ur' ? 'سیشن کی ترمیم کریں' : 'Edit Session') : 
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
                        $session_data = [
                            'id' => '', 
                            'title' => '', 
                            'from_dated' => date('Y-m-d'), 
                            'to_dated' => date('Y-m-d', strtotime('+1 month'))
                        ];
                        
                        if (isset($_GET['edit'])) {
                            $id = intval($_GET['edit']);
                            $result = $conn->query("SELECT id, title, from_dated, to_dated FROM sessions WHERE id = $id");
                            if ($result->num_rows > 0) {
                                $session_data = $result->fetch_assoc();
                                $edit_mode = true;
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="session_id" value="<?php echo $session_data['id']; ?>">
                        
                        <div class="form-group">
                            <label for="title"><?php echo $translations[$lang]['title_label']; ?></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>"
                                   value="<?php echo htmlspecialchars($session_data['title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="from_dated"><?php echo $translations[$lang]['from_date_label']; ?></label>
                            <input type="text" class="form-control datepicker" id="from_dated" name="from_dated" required
                                   value="<?php echo $session_data['from_dated']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="to_dated"><?php echo $translations[$lang]['to_date_label']; ?></label>
                            <input type="text" class="form-control datepicker" id="to_dated" name="to_dated" required
                                   value="<?php echo $session_data['to_dated']; ?>">
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-success">
                                <span class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'plus'; ?>"></span> 
                                <?php echo $translations[$lang]['submit']; ?>
                            </button>
                            
                            <?php if ($edit_mode): ?>
                                <a href="sessions.php" class="btn btn-default">
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

            <!-- Panel for Sessions List -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title"><?php echo $translations[$lang]['list_title']; ?></h3>
                </div>
                <div class="panel-body">

                    <?php
                    $result = $conn->query("SELECT id, title, from_dated, to_dated FROM sessions ORDER BY from_dated DESC");
                    ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations[$lang]['sr_no']; ?></th>
                                        <th><?php echo $translations[$lang]['title_label']; ?></th>
                                        <th><?php echo $translations[$lang]['from_date_label']; ?></th>
                                        <th><?php echo $translations[$lang]['to_date_label']; ?></th>
                                        <th><?php echo $translations[$lang]['duration_label']; ?></th>
                                        <th><?php echo $translations[$lang]['actions']; ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1; 
                                    while ($row = $result->fetch_assoc()): 
                                        $start_date = new DateTime($row['from_dated']);
                                        $end_date = new DateTime($row['to_dated']);
                                        $interval = $start_date->diff($end_date);
                                        
                                        // Calculate duration in years, months, days
                                        $duration_parts = [];
                                        if ($interval->y > 0) {
                                            $duration_parts[] = $interval->y . ' ' . ($lang == 'ur' ? ($interval->y == 1 ? 'سال' : 'سال') : ($interval->y == 1 ? 'year' : 'years'));
                                        }
                                        if ($interval->m > 0) {
                                            $duration_parts[] = $interval->m . ' ' . ($lang == 'ur' ? ($interval->m == 1 ? 'مہینہ' : 'مہینے') : ($interval->m == 1 ? 'month' : 'months'));
                                        }
                                        if ($interval->d > 0) {
                                            $duration_parts[] = $interval->d . ' ' . ($lang == 'ur' ? ($interval->d == 1 ? 'دن' : 'دن') : ($interval->d == 1 ? 'day' : 'days'));
                                        }
                                        // Handle zero duration (same day)
                                        if (empty($duration_parts)) {
                                            $duration_parts[] = '0 ' . ($lang == 'ur' ? 'دن' : 'days');
                                        }
                                        
                                        $duration = implode(' ', $duration_parts);
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($row['from_dated'])); ?></td>
                                            <td><?php echo date('d M Y', strtotime($row['to_dated'])); ?></td>
                                            <td><?php echo $duration; ?></td>
                                            <td>
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">
                                                    <span class="glyphicon glyphicon-edit"></span> 
                                                    <?php echo $translations[$lang]['edit']; ?>
                                                </a>
                                                <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-xs btn-danger" 
                                                   onclick="return confirm('<?php echo ($lang == 'ur') ? 'کیا آپ واقعی اس سیشن کو حذف کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this session?'; ?>')">
                                                    <span class="glyphicon glyphicon-trash"></span> 
                                                    <?php echo ($lang == 'ur') ? 'حذف کریں' : 'Delete'; ?>
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

<script>
$(document).ready(function(){
    // Initialize datepicker
    $('.datepicker').datepicker({
        format: 'yyyy-mm-dd',
        autoclose: true,
        todayHighlight: true
    });
    
    // Set default dates if not in edit mode
    <?php if (!$edit_mode): ?>
        $('#from_dated').datepicker('setDate', 'today');
        $('#to_dated').datepicker('setDate', '+1m');
    <?php endif; ?>
});
</script>

</body>
<?php if (isset($conn)) $conn->close(); ?>
</html>