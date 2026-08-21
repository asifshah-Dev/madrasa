<?php 
require_once('security.php');
require_once('conn_inc.php');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    
    if (!empty($title)) {
        if (isset($_POST['expense_head_id']) && !empty($_POST['expense_head_id'])) {
            // Update existing expense head
            $expense_head_id = intval($_POST['expense_head_id']);
            $stmt = $conn->prepare("UPDATE expense_categories SET title = ? WHERE id = ?");
            $stmt->bind_param("si", $title, $expense_head_id);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Expense head updated successfully!";
                $_SESSION['message_type'] = "success";
            }
        } else {
            // Insert new expense head
            $stmt = $conn->prepare("INSERT INTO expense_categories (title) VALUES (?)");
            $stmt->bind_param("s", $title);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = "Expense head added successfully!";
                $_SESSION['message_type'] = "success";
            }
        }
        
        if ($stmt->error) {
            $_SESSION['message'] = "Error: " . $stmt->error;
            $_SESSION['message_type'] = "danger";
        }
        
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['message'] = "Title cannot be empty!";
        $_SESSION['message_type'] = "danger";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    $stmt = $conn->prepare("DELETE FROM expense_categories WHERE id = ?");
    $stmt->bind_param("i", $CNT);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "Expense head deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error: " . $stmt->error;
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
    <style>
        /* Base font size increase */
        body {
            font-size: 16px;
        }
        
        /* Responsive container width */
        .container {
            width: 100%;
            max-width: 1400px;
            padding-right: 20px;
            padding-left: 20px;
        }
        
        /* Increase column width */
        .col-md-10 {
            width: 100%;
        }
        
        .col-md-offset-1 {
            margin-left: 0;
        }
        
        /* Panel styling improvements */
        .panel {
            margin-bottom: 30px;
            border-radius: 8px;
        }
        
        .panel-heading {
            padding: 18px 20px;
            border-radius: 8px 8px 0 0;
        }
        
        .panel-title {
            font-size: 20px;
            font-weight: 600;
        }
        
        .panel-body {
            padding: 25px;
        }
        
        /* Make input field much larger */
        .form-control {
            font-size: 18px;
            padding: 15px 20px;
            height: auto;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Make input field even larger on focus */
        .form-control:focus {
            font-size: 18px;
          
        }
        
        /* Bilingual label styling */
        .bilingual-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }
        
        .english-label {
            text-align: left;
            font-weight: 600;
            font-size: 17px;
        }
        
        .urdu-label {
            text-align: right;
            font-weight: 600;
            font-size: 17px;
            direction: rtl;
        }
        
        .panel-title-bilingual {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
        }
        
        .panel-title-english {
            text-align: left;
            font-size: 20px;
            font-weight: 600;
            color: white !important;
        }
        
        .panel-title-urdu {
            text-align: right;
            font-size: 20px;
            font-weight: 600;
            direction: rtl;
            color: white !important;
        }
        
        /* Ensure all text in panel heading is white */
        .panel-primary .panel-heading,
        .panel-info .panel-heading {
            background-color: #337ab7;
            border-color: #337ab7;
        }
        
        .panel-heading .panel-title,
        .panel-heading .panel-title * {
            color: white !important;
        }
        
        .action-buttons {
            white-space: nowrap;
        }
        
        /* Form input styling */
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-group label {
            margin-bottom: 12px;
            display: block;
        }
        
        /* Button styling */
        .btn {
            font-size: 16px !important;
            padding: 10px 20px !important;
            margin-left: 10px !important;
            border-radius: 6px !important;
        }
        
        .btn-xs {
            font-size: 14px !important;
            padding: 6px 12px !important;
        }
        
        /* Table styling */
        .table-responsive {
            border-radius: 6px;
            overflow-x: auto;
        }
        
        .table {
            font-size: 16px;
            width: 100%;
            margin-bottom: 0;
        }
        
        .table th {
            font-size: 17px;
            font-weight: 600;
            padding: 15px;
            background-color: #f9f9f9;
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
        }
        
        /* Alert styling */
        .alert {
            font-size: 16px;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
        }
        
        /* Responsive adjustments for mobile */
        @media (max-width: 768px) {
            body {
                font-size: 15px;
            }
            
            .container {
                padding-right: 15px;
                padding-left: 15px;
            }
            
            .panel-heading {
                padding: 15px;
            }
            
            .panel-title {
                font-size: 18px;
            }
            
            .panel-title-english,
            .panel-title-urdu {
                font-size: 16px;
            }
            
            .panel-body {
                padding: 20px 15px;
            }
            
            .bilingual-label {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .english-label,
            .urdu-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .urdu-label {
                text-align: left;
                direction: ltr;
            }
            
            .panel-title-bilingual {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .panel-title-english,
            .panel-title-urdu {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .panel-title-urdu {
                text-align: left;
                direction: ltr;
            }
            
            /* Larger input on mobile too */
            .form-control {
                font-size: 16px;
                padding: 14px 18px;
            }
            
            .form-control:focus {
                font-size: 18px;
                
            }
            
            .btn {
                font-size: 15px !important;
                padding: 8px 16px !important;
                margin-left: 5px !important;
                margin-bottom: 5px !important;
            }
            
            .btn-xs {
                font-size: 13px !important;
                padding: 5px 10px !important;
            }
            
            .table th,
            .table td {
                padding: 10px 12px;
                font-size: 14px;
            }
            
            .table th {
                font-size: 15px;
            }
            
            .alert {
                font-size: 15px;
                padding: 12px 15px;
            }
            
            .action-buttons {
                white-space: normal;
            }
            
            /* Make table more readable on mobile */
            .table-responsive {
                border: none;
            }
        }
        
        /* For very small devices */
        @media (max-width: 480px) {
            .table th,
            .table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .btn {
                font-size: 14px;
                padding: 6px 12px;
            }
            
            .panel-body {
                padding: 15px;
            }
            
            .form-control {
                font-size: 15px;
                padding: 12px 15px;
            }
        }
        
        /* For large laptops and desktops */
        @media (min-width: 1200px) {
            .container {
                max-width: 1400px;
            }
            
            body {
                font-size: 17px;
            }
            
            .panel-title {
                font-size: 22px;
            }
            
            /* Even larger input on desktop */
            .form-control {
                font-size: 20px;
                padding: 18px 25px;
            }
            
            .form-control:focus {
                font-size: 18px;
               
            }
            
            .table {
                font-size: 17px;
            }
            
            .table th {
                font-size: 18px;
            }
        }
        
        /* Improve spacing for better readability */
        .text-right {
            margin-top: 10px;
        }
        
        /* Glyphicon spacing */
        .glyphicon {
            margin-right: 5px;
        }
        
        /* Make input field standout */
        input[type="text"] {
            border: 2px solid #ddd;
            transition: all 0.3s ease;
           font-size: 18px;
            
        }
        
        input[type="text"]:focus {
            border-color: #337ab7;
            box-shadow: 0 0 8px rgba(51, 122, 183, 0.3);
            outline: none;
        }
    </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<!-- Dashboard -->
<div class="container">
    <!-- Back to Expenses Button -->
    <div style="margin-bottom: 15px; text-align: left;">
        <a href="expense.php" style="font-size: 18px; font-weight: bold; background-color: #337ab7; color: white; border: none; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block;">
             خرچہ
        </a>
    </div>
    
    <div class="row">
        <div class="col-md-12">

            <!-- Panel for Add/Edit Expense Head -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <div class="panel-title-bilingual">
                            <span class="panel-title-english">
                                <?php 
                                echo isset($_GET['edit']) ? "Edit Expense Head" : "Add New Expense Head"; 
                                ?>
                            </span>
                            <span class="panel-title-urdu">
                                <?php 
                                echo isset($_GET['edit']) ? "اخراجات کے سربراہ کی ترمیم کریں" : "نیا خرچہ سربراہ شامل کریں"; 
                                ?>
                            </span>
                        </div>
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
                        $expense_head_data = [
                            'id' => '', 
                            'title' => ''
                        ];
                        
                        if (isset($_GET['edit'])) {
                            $id = intval($_GET['edit']);
                            $result = $conn->query("SELECT id, title FROM expense_categories WHERE id = $id");
                            if ($result->num_rows > 0) {
                                $expense_head_data = $result->fetch_assoc();
                                $edit_mode = true;
                            }
                        }
                        ?>
                        
                        <input type="hidden" name="expense_head_id" value="<?php echo $expense_head_data['id']; ?>">
                        
                        <div class="form-group">
                            <label>
                                <div class="bilingual-label">
                                    <span class="english-label">Expense Head Title:</span>
                                    <span class="urdu-label">اخراجات کا سربراہ عنوان:</span>
                                </div>
                            </label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="Enter expense head title / اخراجات کا سربراہ عنوان درج کریں"
                                   value="<?php echo htmlspecialchars($expense_head_data['title']); ?>">
                        </div>

                        <div class="text-right">
                            <button type="submit" class="btn btn-success">
                                <span class="glyphicon glyphicon-<?php echo $edit_mode ? 'refresh' : 'plus'; ?>"></span> 
                                Submit / جمع کرائیں
                            </button>
                            
                            <?php if ($edit_mode): ?>
                                <a href="expense_heads.php" class="btn btn-default">
                                    <span class="glyphicon glyphicon-remove"></span> 
                                    Cancel / منسوخ کریں
                                </a>
                            <?php else: ?>
                                <button type="reset" class="btn btn-default">
                                    <span class="glyphicon glyphicon-refresh"></span> 
                                    Reset / دوبارہ ترتیب دیں
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panel for Expense Heads List -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <div class="panel-title-bilingual">
                            <span class="panel-title-english">Expense Heads List</span>
                            <span class="panel-title-urdu">اخراجات کے سربراہ کی فہرست</span>
                        </div>
                    </h3>
                </div>
                <div class="panel-body">

                    <?php
                    $result = $conn->query("SELECT id, title FROM expense_categories ORDER BY id DESC");
                    ?>

                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th># / نمبر</th>
                                        <th>
                                            <div class="bilingual-label">
                                                <span class="english-label">Expense Head Title</span>
                                                <span class="urdu-label">اخراجات کا سربراہ عنوان</span>
                                            </div>
                                        </th>
                                        <th>
                                            <div class="bilingual-label">
                                                <span class="english-label">Actions</span>
                                                <span class="urdu-label">اعمال</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $counter = 1; 
                                    while ($row = $result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="action-buttons">
                                                <a href="?edit=<?php echo $row['id']; ?>" class="btn btn-xs btn-warning">
                                                    <span class="glyphicon glyphicon-edit"></span> 
                                                    Edit / ترمیم کریں
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info text-center">
                            No expense heads found. / کوئی اخراجات کے سربراہ موجود نہیں۔
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