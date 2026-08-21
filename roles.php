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
        'title' => 'Roles Management',
        'add_title' => 'Add New Role',
        'title_label' => 'Role Title:',
        'title_placeholder' => 'Enter role title',
        'date_label' => 'Date:',
        'status_label' => 'Status:',
        'status_active' => 'Active',
        'status_inactive' => 'Inactive',
        'submit' => 'Submit',
        'reset' => 'Reset',
        'success' => 'Role added successfully!',
        'update_success' => 'Role updated successfully!',
        'error' => 'Error: ',
        'empty_error' => 'Title cannot be empty!',
        'list_title' => 'Roles List',
        'no_records' => 'No roles found.',
        'sr_no' => '#',
        'actions' => 'Actions',
        'edit' => 'Edit',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'delete_confirm' => 'Are you sure you want to delete this role?'
    ],
    'ur' => [
        'title' => 'رولز کا انتظام',
        'add_title' => 'نیا رول شامل کریں',
        'title_label' => 'رول کا عنوان:',
        'title_placeholder' => 'رول کا عنوان درج کریں',
        'date_label' => 'تاریخ:',
        'status_label' => 'حالت:',
        'status_active' => 'فعال',
        'status_inactive' => 'غیر فعال',
        'submit' => 'جمع کرائیں',
        'reset' => 'دوبارہ ترتیب دیں',
        'success' => 'رول کامیابی سے شامل ہو گیا!',
        'update_success' => 'رول کامیابی سے اپ ڈیٹ ہو گیا!',
        'error' => 'خرابی: ',
        'empty_error' => 'عنوان خالی نہیں ہو سکتا!',
        'list_title' => 'رولز کی فہرست',
        'no_records' => 'کوئی رول موجود نہیں۔',
        'sr_no' => 'نمبر',
        'actions' => 'اعمال',
        'edit' => 'ترمیم کریں',
        'save' => 'محفوظ کریں',
        'cancel' => 'منسوخ کریں',
        'delete' => 'حذف کریں',
        'delete_confirm' => 'کیا آپ واقعی اس رول کو حذف کرنا چاہتے ہیں؟'
    ]
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once('conn_inc.php');
    
    $title = trim($_POST['title']);
    $dated = $_POST['dated'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    if (!empty($title)) {
        if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
            // Update existing role
            $role_id = intval($_POST['role_id']);
            $stmt = $conn->prepare("UPDATE roles SET title = ?, dated = ?, status = ? WHERE id = ?");
            $stmt->bind_param("ssii", $title, $dated, $status, $role_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $translations[$lang]['update_success'];
                $_SESSION['message_type'] = "success";
            }
        } else {
            // Insert new role
            $stmt = $conn->prepare("INSERT INTO roles (title, dated, status) VALUES (?, ?, ?)");
            $stmt->bind_param("ssi", $title, $dated, $status);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $translations[$lang]['success'];
                $_SESSION['message_type'] = "success";
            }
        }
        
        if ($stmt->error) {
            $_SESSION['message'] = $translations[$lang]['error'].$stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        
        $stmt->close();
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['message'] = $translations[$lang]['empty_error'];
        $_SESSION['message_type'] = "danger";
    }
}

// Handle role deletion
if (isset($_GET['delete'])) {
    require_once('conn_inc.php');
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = ($lang == 'ur') ? 'رول کامیابی سے حذف ہو گیا!' : 'Role deleted successfully!';
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = $translations[$lang]['error'].$stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
  <title>Roles - <?php echo $translations[$lang]['title']; ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap CSS & JS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  
  <link rel="stylesheet" href="css/mystyle.css" />
  
  <!-- RTL support for Urdu -->
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
    <?php else: ?>
    .table th, .table td {
      text-align: left;
    }
    <?php endif; ?>
    
    .status-active {
      color: #3c763d;
      font-weight: bold;
    }
    .status-inactive {
      color: #a94442;
      font-weight: bold;
    }
    
    /* iPhone-style language switcher */
    .iphone-toggle {
      position: relative;
      display: inline-block;
      width: 120px;
      height: 34px;
      margin: 10px;
    }
    .iphone-toggle input { 
      opacity: 0;
      width: 0;
      height: 0;
    }
    .iphone-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #e9e9eb;
      transition: .4s;
      border-radius: 34px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 10px;
      font-weight: bold;
    }
    .iphone-slider:before {
      position: absolute;
      content: "";
      height: 26px;
      width: 50px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 34px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.3);
    }
    input:checked + .iphone-slider {
      background-color: #007aff;
    }
    input:checked + .iphone-slider:before {
      transform: translateX(60px);
    }
    .iphone-en, .iphone-ur {
      z-index: 1;
      font-size: 14px;
      color: #333;
    }
    input:checked + .iphone-slider .iphone-en {
      color: white;
    }
    input:not(:checked) + .iphone-slider .iphone-ur {
      color: white;
    }
  </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<!-- iPhone-style language switcher -->
<div class="container text-right">
  <label class="iphone-toggle">
    <input type="checkbox" id="language-toggle" <?php echo ($lang == 'ur') ? 'checked' : ''; ?>>
    <span class="iphone-slider">
      <span class="iphone-en">EN</span>
      <span class="iphone-ur">اردو</span>
    </span>
  </label>
</div>

<script>
  document.getElementById('language-toggle').addEventListener('change', function() {
    window.location.href = '?lang=' + (this.checked ? 'ur' : 'en');
  });
</script>

<!-- Dashboard -->
<div class="container">
  <div class="row">
    <div class="col-md-10 col-md-offset-1">

      <!-- Panel for Add/Edit Role -->
      <div class="panel panel-primary">
        <div class="panel-heading">
          <h3 class="panel-title">
            <?php 
            echo isset($_GET['edit']) ? 
                ($lang == 'ur' ? 'رول کی ترمیم کریں' : 'Edit Role') : 
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
            $role_data = ['id' => '', 'title' => '', 'dated' => date('Y-m-d'), 'status' => 1];
            
            if (isset($_GET['edit'])) {
                require_once('conn_inc.php');
                $id = intval($_GET['edit']);
                $result = $conn->query("SELECT * FROM roles WHERE id = $id");
                
                if ($result->num_rows > 0) {
                    $role_data = $result->fetch_assoc();
                    $edit_mode = true;
                }
            }
            ?>
            
            <input type="hidden" name="role_id" value="<?php echo $role_data['id']; ?>">
            
            <div class="form-group">
              <label for="title"><?php echo $translations[$lang]['title_label']; ?></label>
              <input type="text" class="form-control" id="title" name="title" required 
                     placeholder="<?php echo $translations[$lang]['title_placeholder']; ?>"
                     value="<?php echo htmlspecialchars($role_data['title']); ?>">
            </div>
            
            <div class="form-group">
              <label for="dated"><?php echo $translations[$lang]['date_label']; ?></label>
              <input type="date" class="form-control" id="dated" name="dated" required
                     value="<?php echo $role_data['dated']; ?>">
            </div>
            
            <div class="form-group">
              <label>
                <input type="checkbox" name="status" value="1" <?php echo $role_data['status'] ? 'checked' : ''; ?>>
                <?php echo $translations[$lang]['status_label']; ?>
                <span class="<?php echo $role_data['status'] ? 'status-active' : 'status-inactive'; ?>">
                  (<?php echo $role_data['status'] ? $translations[$lang]['status_active'] : $translations[$lang]['status_inactive']; ?>)
                </span>
              </label>
            </div>

            <div class="text-right">
              <button type="submit" class="btn btn-success">
                <span class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'plus'; ?>"></span> 
                <?php echo $translations[$lang]['submit']; ?>
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

      <!-- Panel for Roles List -->
      <div class="panel panel-info">
        <div class="panel-heading">
          <h3 class="panel-title"><?php echo $translations[$lang]['list_title']; ?></h3>
        </div>
        <div class="panel-body">

          <?php
          require_once('conn_inc.php');
          $result = $conn->query("SELECT * FROM roles ORDER BY id DESC");
          ?>

          <?php if ($result->num_rows > 0): ?>
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
                  <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                      <td><?php echo $counter++; ?></td>
                      <td><?php echo htmlspecialchars($row['title']); ?></td>
                      <td><?php echo $row['dated']; ?></td>
                      <td class="<?php echo $row['status'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $row['status'] ? $translations[$lang]['status_active'] : $translations[$lang]['status_inactive']; ?>
                      </td>
                      <td>
                        <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">
                          <span class="glyphicon glyphicon-edit"></span> 
                          <?php echo $translations[$lang]['edit']; ?>
                        </a>
                        <a href="?delete=<?php echo $row['id']; ?>" class="btn btn-xs btn-danger" 
                           onclick="return confirm('<?php echo $translations[$lang]['delete_confirm']; ?>')">
                          <span class="glyphicon glyphicon-trash"></span> 
                          <?php echo $translations[$lang]['delete']; ?>
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
<?php $conn->close(); ?>
</html>