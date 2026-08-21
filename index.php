<?php 

require_once('security.php'); 
require_once('conn_inc.php');
header('Content-Type: text/html; charset=utf-8');

// Initialize session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Language handling
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
}

$lang = $_SESSION['lang'];

// Handle student type toggle
if (isset($_GET['toggle_type'])) {
    $id = intval($_GET['toggle_type']);
    
    $stmt = $conn->prepare("UPDATE student_registration SET student_type = 
                            CASE 
                                WHEN student_type = 'Regular' THEN 'Hostilized'
                                WHEN student_type = 'Hostilized' THEN 'Regular'
                                ELSE 'Regular'
                            END 
                            WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Student type toggled successfully!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error toggling student type.';
        $_SESSION['message_type'] = 'danger';
    }
    
    $stmt->close();
    header("Location: index.php?lang=" . $lang);
    exit();
}

// Handle withdrawal (status update)
if (isset($_POST['withdrawal'])) {
    $student_id = intval($_POST['student_id']);
    $new_status = 1;
    
    $stmt = $conn->prepare("UPDATE student_class SET status = ? WHERE student_registration_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("ii", $new_status, $student_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $_SESSION['message'] = 'Student withdrawal successful! Status set to Leave.';
        $_SESSION['message_type'] = 'success';
    } else {
        $stmt2 = $conn->prepare("INSERT INTO student_class (student_registration_id, status) VALUES (?, ?)");
        $stmt2->bind_param("ii", $student_id, $new_status);
        if ($stmt2->execute()) {
            $_SESSION['message'] = 'Student withdrawal successful! Status set to Leave.';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error processing withdrawal.';
            $_SESSION['message_type'] = 'danger';
        }
        $stmt2->close();
    }
    
    $stmt->close();
    header("Location: index.php?lang=" . $lang);
    exit();
}

// Handle student deletion
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM student_registration WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = 'Student deleted successfully!';
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error deleting student.';
        $_SESSION['message_type'] = 'danger';
    }
    
    $stmt->close();
    header("Location: index.php?lang=" . $lang);
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo ($lang == 'ur') ? 'rtl' : 'ltr'; ?>">
<head>
  <title>Madrasa Management System</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">

  <!-- Bootstrap CSS & JS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  
  <!-- Chart.js for visualizations -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- Google Fonts for better typography -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&family=Noto+Sans+Arabic&display=swap" rel="stylesheet">
  
  <link rel="stylesheet" href="css/mystyle.css" />

  <style>
    * {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }
    
    <?php if ($lang == 'ur'): ?>
    body, .form-control, .btn, .alert, .dashboard-card, .modern-panel, 
    .search-input-enhanced, .stat-number, .stat-label, .student-info {
        font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', 'Segoe UI', sans-serif !important;
        direction: rtl;
        text-align: right;
    }
    .search-input-group { direction: ltr; }
    .search-input-enhanced { text-align: right; }
    .student-info { text-align: right; }
    .student-info small i { margin-left: 5px; margin-right: 0; }
    .btn-action i { margin-left: 5px; margin-right: 0; }
    <?php endif; ?>
    
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
    }
    
    /* Alert Messages */
    .alert-container {
      margin-bottom: 20px;
    }
    .alert {
      border-radius: 12px;
      font-size: 14px;
      padding: 12px 18px;
      margin-bottom: 15px;
    }
    
    /* Enhanced Dashboard Cards */
    .dashboard-card {
      border-radius: 20px;
      padding: 25px 20px;
      margin-bottom: 25px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }
    
    .dashboard-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    }
    
    .dashboard-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }
    
    .card-icon-wrapper {
      width: 60px;
      height: 60px;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    }
    
    .card-icon {
      font-size: 28px;
    }
    
    .stat-number {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 5px;
      line-height: 1.2;
    }
    
    .stat-label {
      font-size: 14px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .trend-indicator {
      position: absolute;
      top: 20px;
      right: 20px;
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 20px;
    }
    
    /* Dual language labels in cards */
    .dual-lang-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }
    
    .lang-left {
      text-align: left;
      flex: 1;
    }
    
    .lang-right {
      text-align: right;
      flex: 1;
    }
    
    .lang-separator {
      width: 1px;
      height: 60px;
      background: rgba(255, 255, 255, 0.3);
      margin: 0 10px;
    }
    
    .stat-label-en {
      font-size: 13px;
      font-weight: 600;
      opacity: 0.95;
      margin-bottom: 2px;
      font-family: 'Inter', sans-serif;
    }
    
    .stat-label-ur {
      font-size: 14px;
      font-weight: 600;
      opacity: 0.95;
      margin-bottom: 2px;
      font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;
    }
    
    .stat-subtitle-en {
      font-size: 11px;
      opacity: 0.75;
      font-family: 'Inter', sans-serif;
    }
    
    .stat-subtitle-ur {
      font-size: 12px;
      opacity: 0.75;
      font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;
    }
    
    /* Dual language panel headers */
    .dual-lang-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .header-en {
      font-weight: 600;
      color: #2d3748;
      font-size: 18px;
    }
    
    .header-ur {
      font-weight: 600;
      color: #2d3748;
      font-size: 16px;
      font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;
    }
    
    .header-separator {
      color: #cbd5e0;
      margin: 0 10px;
    }
    
    /* Modern Panels */
    .modern-panel {
      background: white;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
      border: none;
      margin-bottom: 25px;
      overflow: hidden;
    }
    
    .modern-panel .panel-heading {
      background: white;
      border-bottom: 2px solid #f7fafc;
      padding: 20px 25px;
    }
    
    .modern-panel .panel-heading h4 {
      margin: 0;
      font-weight: 600;
      color: #2d3748;
      font-size: 18px;
    }
    
    .modern-panel .panel-heading h4 i {
      margin-right: 10px;
      color: #667eea;
    }
    
    .modern-panel .panel-body {
      padding: 25px;
    }
    
    /* Quick Action Buttons - Matching dashboard card colors */
    .quick-action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      padding: 20px;
      background: white;
      border-radius: 20px;
      margin-bottom: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.08);
    }
    
    .btn-quick-action {
      flex: 1;
      min-width: 150px;
      padding: 16px 15px;
      border-radius: 16px;
      font-weight: 600;
      text-align: center;
      transition: all 0.3s ease;
      border: none;
      color: white;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      font-size: 14px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;
    }
    
    .btn-quick-action:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
      text-decoration: none;
      color: white;
    }
    
    .btn-quick-action i {
      font-size: 18px;
    }
    
    .btn-add-student {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-add-student:hover {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-student-list {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-student-list:hover {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-fee-cards {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    
    .btn-fee-cards:hover {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    
    .btn-fee-reminders {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-fee-reminders:hover {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-expense {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    .btn-expense:hover {
      background: #ffffff;
      color: rgba(15, 86, 138, 0.95)
    }
    /* Enhanced Search Section */
    .search-section-enhanced {
      border-radius: 30px;
      padding: 5px;
      margin-bottom: 10px;
      backdrop-filter: blur(10px);
      position: relative;
      overflow: hidden;
    }
    
    .search-wrapper-enhanced {
      position: relative;
      z-index: 1;
      max-width: 800px;
      margin: 0 auto;
    }
    
    .search-input-group {
      display: flex;
      align-items: center;
      background: white;
      border-radius: 60px;
      padding: 5px;
      border: 2px solid rgba(13, 110, 253, 0.1);
      transition: all 0.3s ease;
    }
    
    .search-input-group:focus-within {
      transform: translateY(-2px);
    }
    
    .search-icon-wrapper {
      width: 50px;
      height: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: rgba(15, 86, 138, 0.95);
      border-radius: 50%;
      margin: 3px;
    }
    
    .search-icon-wrapper i {
      color: white;
      font-size: 20px;
    }
    
    .search-input-enhanced {
      flex: 1;
      border: none;
      padding: 15px 20px;
      font-size: 16px;
      outline: none;
      background: transparent;
      color: #1a202c;
    }
    
    .search-input-enhanced::placeholder {
      color: #a0aec0;
      font-weight: 300;
    }
    
    .search-button-enhanced {
      background: rgba(15, 86, 138, 0.95);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 50px;
      font-weight: 600;
      font-size: 15px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-right: 5px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .search-button-enhanced:hover {
      transform: translateY(-2px);
    }
    
    /* Enhanced Quick Links */
    .quick-links-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }
    
    .quick-link-item {
      background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
      padding: 20px;
      border-radius: 16px;
      text-align: center;
      transition: all 0.3s ease;
      border: 1px solid #e2e8f0;
      text-decoration: none;
      color: #2d3748;
      display: block;
    }
    
    .quick-link-item:hover {
      transform: translateY(-3px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.1);
      border-color: #667eea;
      text-decoration: none;
      color: #2d3748;
    }
    
    .quick-link-item i {
      font-size: 28px;
      margin-bottom: 12px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .quick-link-item h5 {
      margin: 0 0 5px 0;
      font-weight: 600;
      font-size: 15px;
    }
    
    .quick-link-item p {
      margin: 0;
      font-size: 12px;
      color: #718096;
    }
    
    /* Activity Timeline */
    .activity-timeline {
      position: relative;
      padding-left: 30px;
    }
    
    <?php if ($lang == 'ur'): ?>
    .activity-timeline { padding-left: 0; padding-right: 30px; }
    .activity-timeline::before { left: auto; right: 8px; }
    .timeline-dot { left: auto; right: -30px; }
    <?php endif; ?>
    
    .activity-timeline::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
    }
    
    .timeline-item {
      position: relative;
      padding-bottom: 25px;
    }
    
    .timeline-item:last-child {
      padding-bottom: 0;
    }
    
    .timeline-dot {
      position: absolute;
      left: -30px;
      top: 0;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: white;
      border: 3px solid #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .timeline-content {
      background: #f7fafc;
      padding: 15px;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
    }
    
    .timeline-content strong {
      color: #2d3748;
      display: block;
      margin-bottom: 5px;
    }
    
    .timeline-time {
      font-size: 12px;
      color: #718096;
      margin-top: 8px;
    }
    
    .timeline-time i {
      margin-right: 5px;
      color: #667eea;
    }
    
    /* Search Results Enhancement */
    .search-results-container {
      margin-top: 25px;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
    }
    
    .search-results-table {
      width: 100%;
      background: white;
      border-radius: 20px;
      overflow: hidden;
    }
    
    .search-results-table thead {
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    }
    
    .search-results-table th {
      padding: 18px 16px;
      font-weight: 600;
      font-size: 13px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: white;
      border: none;
    }
    
    .search-results-table td {
      padding: 20px 16px;
      vertical-align: middle;
      border-bottom: 1px solid #e9ecef;
      background: white;
    }
    
    .search-results-table tbody tr {
      transition: all 0.3s ease;
    }
    
    .search-results-table tbody tr:hover {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      transform: scale(1.01);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .student-info {
      font-size: 14px;
      line-height: 1.8;
    }
    
    .student-info strong {
      font-size: 16px;
    }
    
    /* Action Buttons */
    .action-buttons {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    
    .btn-action {
      padding: 8px 12px;
      border-radius: 30px;
      font-size: 11px;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      color: white;
      text-decoration: none;
      white-space: nowrap;
    }
    
    .btn-action:hover {
      transform: translateY(-2px);
      color: white;
      text-decoration: none;
    }
    
    .btn-action i {
      font-size: 12px;
    }
    
    .btn-edit { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
    .btn-print { background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); }
    .btn-fee { background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); }
    .btn-withdrawal { background: linear-gradient(135deg, #dc3545 0%, #bd2130 100%); }
    .btn-toggle { background: linear-gradient(135deg, #6f42c1 0%, #5a32a3 100%); }
    .btn-profile { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); }
    .btn-delete { background: linear-gradient(135deg, #343a40 0%, #23272b 100%); }
    
    /* Progress Bar Enhancement */
    .progress {
      height: 8px;
      border-radius: 10px;
      background: #e2e8f0;
      margin: 10px 0;
    }
    
    .progress-bar {
      background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
      border-radius: 10px;
    }
    
    /* Mobile Optimizations */
    .search-toggle-btn {
      display: none;
      width: 100%;
      padding: 16px;
      background: rgba(15, 86, 138, 0.95);
      color: white;
      border: none;
      border-radius: 50px;
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 20px;
    }
    
    .no-results-message {
      text-align: center;
      padding: 50px 20px;
      background: white;
      border-radius: 20px;
    }
    
    .no-results-message i {
      font-size: 48px;
      color: #0d6efd;
      opacity: 0.5;
      margin-bottom: 20px;
    }
    
    .no-results-message h4 {
      color: #2d3748;
      margin-bottom: 10px;
    }
    
    .no-results-message p {
      color: #718096;
    }
    
    /* Chart Container Enhancement */
    .chart-container {
      position: relative;
      height: 350px;
      margin-bottom: 20px;
    }
    
    /* Session Selector Enhancement */
    .session-selector {
      background: #f7fafc;
      padding: 15px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      border: 1px solid #e2e8f0;
    }
    
    .session-selector label {
      font-weight: 600;
      color: #2d3748;
      margin-right: 15px;
    }
    
    .session-selector select {
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      padding: 10px 15px;
      font-size: 14px;
      background: white;
      min-width: 250px;
    }
    
    .session-selector select:focus {
      border-color: #667eea;
      outline: none;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Dues styling */
    .dues-amount {
      font-size: 18px;
      font-weight: 700;
    }
    
    .dues-status {
      font-size: 11px;
      margin-top: 3px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .dashboard-header {
        padding: 20px 20px 30px 20px;
        margin: -15px -10px 20px -10px;
      }
      
      .dashboard-header h1 {
        font-size: 24px;
      }
      
      .search-toggle-btn {
        display: block;
      }
      
      .expandable-search-container {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
      }
      
      .expandable-search-container.expanded {
        max-height: 800px;
      }
      
      .search-section-enhanced {
        padding: 20px;
      }
      
      .search-input-group {
        flex-wrap: wrap;
        background: transparent;
        box-shadow: none;
        border: none;
        padding: 0;
      }
      
      .search-icon-wrapper {
        display: none;
      }
      
      .search-input-enhanced {
        width: 100%;
        background: white;
        border-radius: 50px;
        padding: 15px 20px;
        margin-bottom: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        border: 2px solid rgba(13, 110, 253, 0.1);
      }
      
      .search-button-enhanced {
        width: 100%;
        justify-content: center;
        padding: 15px;
        margin-right: 0;
      }
      
      .stat-number {
        font-size: 28px;
      }
      
      .quick-links-grid {
        grid-template-columns: 1fr;
      }
      
      .chart-container {
        height: 250px;
      }
      
      /* Mobile: Keep dual-lang side by side (left/right) */
      .dual-lang-container {
        flex-direction: row !important;
        gap: 8px;
      }
      
      .lang-separator {
        width: 1px !important;
        height: 40px !important;
        margin: 0 8px !important;
      }
      
      .lang-left {
        text-align: left !important;
      }
      
      .lang-right {
        text-align: right !important;
      }
      
      .stat-label-en {
        font-size: 11px;
      }
      
      .stat-label-ur {
        font-size: 12px;
      }
      
      .stat-subtitle-en {
        font-size: 9px;
      }
      
      .stat-subtitle-ur {
        font-size: 10px;
      }
      
      .dual-lang-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
      }
      
      .header-separator {
        display: none;
      }
      
      .header-en {
        font-size: 16px;
      }
      
      .header-ur {
        font-size: 14px;
      }
      
      /* Mobile Session Selector Fix */
      .session-selector {
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      
      .session-selector label {
        margin-right: 0;
        margin-bottom: 5px;
      }
      
      .session-selector select {
        width: 100% !important;
        min-width: 100% !important;
        padding: 1px 15px;
        font-size: 16px;
      }
      
      /* Mobile Quick Action Buttons */
      .quick-action-buttons {
        flex-wrap: wrap;
        gap: 10px;
        padding: 15px;
      }
      
      .btn-quick-action {
        flex: 1 1 calc(33.333% - 10px);
        min-width: 100px;
        padding: 14px 10px;
        font-size: 12px;
      }
      
      .btn-quick-action i {
        font-size: 16px;
      }
      
      /* Mobile Search Results - Hide table, show cards */
      .search-results-container .table-responsive {
        overflow-x: visible;
      }
      
      .search-results-table {
        min-width: 100%;
        border-radius: 15px;
      }
      
      .search-results-table thead {
        display: none;
      }
      
      .search-results-table tbody {
        display: block;
      }
      
      .search-results-table tbody tr {
        display: block;
        margin-bottom: 15px;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
        background: white;
      }
      
      .search-results-table tbody tr:hover {
        transform: none;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
      }
      
      .search-results-table td {
        display: block;
        width: 100% !important;
        padding: 15px;
        border-bottom: 1px solid #e9ecef;
        text-align: left;
      }
      
      <?php if ($lang == 'ur'): ?>
      .search-results-table td { text-align: right; }
      <?php endif; ?>
      
      .search-results-table td:last-child {
        border-bottom: none;
      }
      
      .search-results-table td::before {
        content: attr(data-label);
        font-weight: 600;
        display: block;
        margin-bottom: 8px;
        color: #0d6efd;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .student-info {
        padding: 5px 0;
      }
      
      .student-info strong {
        font-size: 18px;
        display: block;
        margin-bottom: 10px;
      }
      
      .student-info small {
        display: block;
        margin-bottom: 5px;
        font-size: 14px;
      }
      
      .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
      }
      
      .btn-action {
        flex: 1 1 calc(50% - 8px);
        text-align: center;
        padding: 10px 12px;
        font-size: 12px;
        margin: 0;
        justify-content: center;
      }
      
      /* Modern Panel Mobile Fixes */
      .modern-panel .panel-heading {
        padding: 15px 20px;
      }
      
      .modern-panel .panel-body {
        padding: 20px 15px;
      }
      
      /* Container padding fix */
      .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
      }
      
      /* No results message mobile fix */
      .no-results-message {
        padding: 30px 15px;
      }
      
      .no-results-message i {
        font-size: 40px;
      }
    }
    
    @media (max-width: 480px) {
      .btn-quick-action {
        flex: 1 1 calc(30% - 8px);
        min-width: 80px;
        padding: 12px 8px;
        font-size: 11px;
      }
      
      .btn-quick-action i {
        font-size: 14px;
      }
    }
    
    /* Tablet optimization */
    @media (min-width: 769px) and (max-width: 991px) {
      .session-selector select {
        min-width: 200px;
      }
      
      .btn-action {
        padding: 6px 10px;
        font-size: 10px;
      }
      
      .btn-quick-action {
        min-width: 130px;
        padding: 14px 12px;
        font-size: 13px;
      }
    }
    
    @media (min-width: 769px) {
      .search-toggle-btn {
        display: none !important;
      }
      
      .expandable-search-container {
        display: block !important;
        max-height: none !important;
      }
    }
  </style>
</head>
<body>

<?php require_once('navbar.php'); ?>

<div class="container-fluid">
  
  <!-- Alert Messages -->
  <?php if (isset($_SESSION['message'])): ?>
  <div class="alert-container">
    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible">
      <button type="button" class="close" data-dismiss="alert">&times;</button>
      <?php echo $_SESSION['message']; unset($_SESSION['message'], $_SESSION['message_type']); ?>
    </div>
  </div>
  <?php endif; ?>
  
  <!-- Mobile Expandable Search Toggle -->
  <button class="search-toggle-btn" id="searchToggleBtn">
    <i class="fas fa-search"></i> <?php echo $lang == 'ur' ? 'طلبہ تلاش کریں' : 'طالب علم تلاش کریں اور فیس جمع کریں'; ?>
  </button>
  
  <!-- Expandable Search Container -->
  <div class="expandable-search-container" id="expandableSearch">
    <div class="search-section-enhanced">
      <div class="search-wrapper-enhanced">
        <div class="search-input-group">
          <div class="search-icon-wrapper">
            <i class="fas fa-search"></i>
          </div>
          <input type="text" 
                 class="search-input-enhanced" 
                 id="searchInput" 
                 placeholder="<?php echo $lang == 'ur' ? 'رجسٹریشن نمبر یا نام درج کریں...' : 'رجسٹریشن نمبر تلاش کریں اور فیس جمع کریں۔'; ?>"
                 autocomplete="off">
          <button class="search-button-enhanced" id="searchButton">
            <i class="fas fa-search"></i> <?php echo $lang == 'ur' ? 'تلاش' : 'تلاش'; ?>
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <div class="quick-action-buttons" style="background: none; box-shadow: none;">
    <a href="student_registration.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-add-student">
      <i class="fas fa-user-plus"></i> نیا طالب علم
    </a>
    <a href="student_list.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-student-list">
      <i class="fas fa-users"></i> طلبہ کی فہرست
    </a>
    <a href="fee_card_create_monthly.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-fee-cards">
      <i class="fas fa-credit-card"></i> فیس کارڈز بنائیں
    </a>
    <a href="feeremainders.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-fee-reminders">
      <i class="fas fa-bell"></i> فیس یاددہانی
    </a>
     <a href="class_wise_collection.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-fee-reminders">
      <i class="fas fa-chalkboard-teacher"></i> کلاس فیس
    </a>
    <a href="expense.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="btn-quick-action btn-expense">
      <i class="fas fa-receipt"></i> اخراجات
    </a>
  </div>
  
  <!-- Search Results -->
  <div class="search-results-container" id="searchResults" style="display: none;">
    <div class="table-responsive">
      <table class="table search-results-table">
        <thead>
          <tr>
            <th><?php echo $lang == 'ur' ? 'طالب علم کی معلومات' : 'Student Information'; ?></th>
            <th><?php echo $lang == 'ur' ? 'کل واجبات' : 'Total Dues'; ?></th>
            <th><?php echo $lang == 'ur' ? 'اعمال' : 'Quick Actions'; ?></th>
          </tr>
        </thead>
        <tbody id="searchResultsBody"></tbody>
      </table>
    </div>
  </div>
  
  <!-- Stats Cards -->
  <div class="row">
    <div class="col-lg-3 col-md-6 col-sm-6">
      <div class="dashboard-card bg-primary text-white" style="background: linear-gradient(135deg, rgba(13, 110, 253, 0.95) 0%, rgba(13, 110, 253, 0.85) 100%) !important; backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="trend-indicator" style="background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(5px);">
          <i class="fas fa-arrow-up"></i> <?php echo $lang == 'ur' ? 'فعال' : 'Active'; ?>
        </div>
        <div class="card-icon-wrapper" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);">
          <div class="card-icon"><i class="fas fa-users"></i></div>
        </div>
        <?php
          $student_count = $conn->query("SELECT COUNT(DISTINCT sr.id) as total 
FROM student_registration sr
INNER JOIN student_class sc ON sc.student_registration_id = sr.id
WHERE sc.status = 0")->fetch_assoc();
        ?>
        <div class="stat-number" style="background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo number_format($student_count['total']); ?></div>
        <div class="dual-lang-container">
          <div class="lang-left">
            <div class="stat-label-en">Total Students</div>
            <div class="stat-subtitle-en">Across all branches</div>
          </div>
          
          <div class="lang-right">
            <div class="stat-label-ur">کل طلباء</div>
            <div class="stat-subtitle-ur">تمام برانچز میں</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
      <div class="dashboard-card bg-success text-white" style="background: linear-gradient(135deg, rgba(25, 135, 84, 0.95) 0%, rgba(25, 135, 84, 0.85) 100%) !important; backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="trend-indicator" style="background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(5px);">
          <i class="fas fa-book-open"></i> <?php echo $lang == 'ur' ? 'فعال' : 'Active'; ?>
        </div>
        <div class="card-icon-wrapper" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);">
          <div class="card-icon"><i class="fas fa-book"></i></div>
        </div>
        <?php
          $course_count = $conn->query("SELECT COUNT(*) as total FROM courses WHERE status = 0")->fetch_assoc();
        ?>
        <div class="stat-number" style="background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo number_format($course_count['total']); ?></div>
        <div class="dual-lang-container">
          <div class="lang-left">
            <div class="stat-label-en">Active Courses</div>
            <div class="stat-subtitle-en">Currently offered</div>
          </div>
          
          <div class="lang-right">
            <div class="stat-label-ur">فعال کورسز</div>
            <div class="stat-subtitle-ur">فی الحال پیشکش</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
      <div class="dashboard-card bg-warning text-white" style="background: linear-gradient(135deg, rgba(255, 193, 7, 0.95) 0%, rgba(255, 193, 7, 0.85) 100%) !important; backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="trend-indicator" style="background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(5px);">
          <i class="fas fa-exclamation-circle"></i> <?php echo $lang == 'ur' ? 'زیر التوا' : 'Pending'; ?>
        </div>
        <div class="card-icon-wrapper" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);">
          <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <?php
          $defaulters = $conn->query("SELECT COUNT(DISTINCT student_class_id) as total FROM student_fee_card WHERE status = 'pending'")->fetch_assoc();
        ?>
        <div class="stat-number" style="background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"><?php echo number_format($defaulters['total']); ?></div>
        <div class="dual-lang-container">
          <div class="lang-left">
            <div class="stat-label-en">Fee Defaulters</div>
            <div class="stat-subtitle-en">Pending payments</div>
          </div>
          
          <div class="lang-right">
            <div class="stat-label-ur">فیس ڈیفالٹرز</div>
            <div class="stat-subtitle-ur">زیر التوا ادائیگیاں</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-3 col-md-6 col-sm-6">
      <div class="dashboard-card bg-danger text-white" style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.95) 0%, rgba(220, 53, 69, 0.85) 100%) !important; backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2);">
        <div class="trend-indicator" style="background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(5px);">
          <i class="fas fa-rupee-sign"></i> <?php echo $lang == 'ur' ? 'غیر ادا شدہ' : 'Unpaid'; ?>
        </div>
        <div class="card-icon-wrapper" style="background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(5px);">
          <div class="card-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        <?php
          $pending_fees = $conn->query("SELECT SUM(total_amount) as total FROM student_fee_card WHERE status = 'pending'")->fetch_assoc();
        ?>
        <div class="stat-number" style="background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">₹<?php echo number_format($pending_fees['total'] ?? 0); ?></div>
        <div class="dual-lang-container">
          <div class="lang-left">
            <div class="stat-label-en">Pending Fees</div>
            <div class="stat-subtitle-en">Unpaid amount</div>
          </div>
          
          <div class="lang-right">
            <div class="stat-label-ur">زیر التوا فیس</div>
            <div class="stat-subtitle-ur">غیر ادا شدہ رقم</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div class="row">
    <!-- Main Content Area -->
    <div class="col-lg-8">
      <!-- Fee Collection Chart -->
      <div class="modern-panel">
        <div class="panel-heading">
          <div class="dual-lang-header">
            <div class="header-en">
              <i class="fas fa-chart-bar"></i> Fee Collection Overview
            </div>
            <div class="header-separator">|</div>
            <div class="header-ur">
              <i class="fas fa-chart-bar"></i> فیس جمع کرنے کا جائزہ
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="session-selector">
            <label for="sessionSelect"><i class="fas fa-calendar-alt"></i> <?php echo $lang == 'ur' ? 'سیشن منتخب کریں:' : 'Select Session:'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">سیشن منتخب کریں:</span></label>
            <select id="sessionSelect" class="form-control" style="display: inline-block; width: auto;">
              <option value=""><?php echo $lang == 'ur' ? 'سیشن منتخب کریں' : 'Choose a session'; ?> | سیشن منتخب کریں</option>
              <?php
              $currentSessionResult = $conn->query("SELECT id FROM sessions WHERE status = 0 ORDER BY id DESC LIMIT 1");
              $currentSessionId = $currentSessionResult->fetch_assoc()['id'] ?? null;
              $sessionQuery = $conn->query("SELECT id, title, from_dated, to_dated FROM sessions WHERE status = 0 ORDER BY from_dated DESC");
              while ($session = $sessionQuery->fetch_assoc()) {
                $selected = ($session['id'] == $currentSessionId) ? "selected" : "";
                echo "<option value='{$session['id']}' data-from='{$session['from_dated']}' data-to='{$session['to_dated']}' $selected>{$session['title']}</option>";
              }
              ?>
            </select>
          </div>
          <div class="chart-container">
            <canvas id="feeChart"></canvas>
          </div>
          <div id="noDataAlert" class="alert alert-info" style="display: none; border-radius: 12px;">
            <i class="fas fa-info-circle"></i> 
            <?php echo $lang == 'ur' ? 'منتخب کردہ سیشن کے لیے کوئی فیس ادائیگی ریکارڈ نہیں کی گئی۔' : 'No fee payments have been recorded for the selected session.'; ?> 
            | 
            <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">منتخب کردہ سیشن کے لیے کوئی فیس ادائیگی ریکارڈ نہیں کی گئی۔</span>
          </div>
        </div>
      </div>

      <!-- Course Enrollment -->
      <div class="modern-panel">
        <div class="panel-heading">
          <div class="dual-lang-header">
            <div class="header-en">
              <i class="fas fa-graduation-cap"></i> Course Enrollment Overview
            </div>
            <div class="header-separator">|</div>
            <div class="header-ur">
              <i class="fas fa-graduation-cap"></i> کورس میں داخلے کا جائزہ
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="chart-container">
            <canvas id="courseChart"></canvas>
          </div>
          <?php
          $enrollment_data = $conn->query("
            SELECT 
              c.title AS course_name,
              COUNT(sc.id) AS student_count
            FROM courses c
            LEFT JOIN classes cl ON c.id = cl.course_id
            LEFT JOIN student_class sc ON cl.id = sc.class_id
            WHERE c.status = 0
            GROUP BY c.id
            ORDER BY student_count DESC
          ");
          
          $has_enrollments = $enrollment_data->num_rows > 0;
          $enrollment_rows = [];
          
          if ($has_enrollments) {
            echo '<div class="table-responsive" style="margin-top: 20px;">
                    <table class="table" style="background: #f7fafc; border-radius: 12px;">
                      <thead style="background: #e2e8f0;">
                        <tr>
                          <th>' . ($lang == 'ur' ? 'کورس' : 'Course') . ' | کورس</th>
                          <th>' . ($lang == 'ur' ? 'طلبہ' : 'Students') . ' | طلبہ</th>
                          <th>' . ($lang == 'ur' ? 'تقسیم' : 'Distribution') . ' | تقسیم</th>
                        </tr>
                      </thead>
                      <tbody>';
            
            $total_students = 0;
            
            while($row = $enrollment_data->fetch_assoc()) {
              $total_students += $row['student_count'];
              $enrollment_rows[] = $row;
            }
            
            foreach($enrollment_rows as $row) {
              $percentage = $total_students > 0 ? round(($row['student_count']/$total_students)*100, 1) : 0;
              echo '<tr>
                      <td><strong>'.$row['course_name'].'</strong></td>
                      <td>'.number_format($row['student_count']).' ' . ($lang == 'ur' ? 'طلبہ' : 'students') . '</td>
                      <td>
                        <div class="progress" style="margin: 0;">
                          <div class="progress-bar" role="progressbar" style="width: '.$percentage.'%">
                            '.$percentage.'%
                          </div>
                        </div>
                      </td>
                    </tr>';
            }
            
            echo '</tbody></table></div>';
          } else {
            echo '<div class="alert alert-info" style="border-radius: 12px;"><i class="fas fa-info-circle"></i> ' . ($lang == 'ur' ? 'کسی بھی کورس میں طلبہ کے داخلے نہیں ملے۔' : 'No student enrollments found in any courses.') . ' | <span style="font-family: \'Noto Nastaliq Urdu\', \'Noto Sans Arabic\', sans-serif;">کسی بھی کورس میں طلبہ کے داخلے نہیں ملے۔</span></div>';
          }
          ?>
        </div>
      </div>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
      <!-- Quick Actions -->
      <div class="modern-panel">
        <div class="panel-heading">
          <div class="dual-lang-header">
            <div class="header-en">
              <i class="fas fa-bolt"></i> Quick Actions
            </div>
            <div class="header-separator">|</div>
            <div class="header-ur">
              <i class="fas fa-bolt"></i> فوری اقدامات
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="quick-links-grid">
            <a href="student_registration.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-user-plus"></i>
              <h5><?php echo $lang == 'ur' ? 'نیا طالب علم' : 'New Student'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">نیا طالب علم</span></h5>
              <p><?php echo $lang == 'ur' ? 'داخلہ رجسٹر کریں' : 'Register admission'; ?> | داخلہ رجسٹر کریں</p>
            </a>
            <a href="fee_collection.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-money-bill"></i>
              <h5><?php echo $lang == 'ur' ? 'فیس جمع کریں' : 'Collect Fees'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">فیس جمع کریں</span></h5>
              <p><?php echo $lang == 'ur' ? 'ادائیگیاں پروسیس کریں' : 'Process payments'; ?> | ادائیگیاں پروسیس کریں</p>
            </a>
            <a href="student_list.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-list"></i>
              <h5><?php echo $lang == 'ur' ? 'طلبہ کی فہرست' : 'Student List'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">طلبہ کی فہرست</span></h5>
              <p><?php echo $lang == 'ur' ? 'تمام ریکارڈ دیکھیں' : 'View all records'; ?> | تمام ریکارڈ دیکھیں</p>
            </a>
            <a href="fee_reports.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-file-invoice"></i>
              <h5><?php echo $lang == 'ur' ? 'رپورٹس' : 'Reports'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">رپورٹس</span></h5>
              <p><?php echo $lang == 'ur' ? 'بیانات تیار کریں' : 'Generate statements'; ?> | بیانات تیار کریں</p>
            </a>
            <a href="add_expense.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-receipt"></i>
              <h5><?php echo $lang == 'ur' ? 'اخراجات شامل کریں' : 'Add Expense'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">اخراجات شامل کریں</span></h5>
              <p><?php echo $lang == 'ur' ? 'اخراجات ریکارڈ کریں' : 'Record costs'; ?> | اخراجات ریکارڈ کریں</p>
            </a>
            <a href="student_feecards.php<?php echo $lang != 'en' ? '?lang=' . $lang : ''; ?>" class="quick-link-item">
              <i class="fas fa-credit-card"></i>
              <h5><?php echo $lang == 'ur' ? 'فیس کارڈز' : 'Fee Cards'; ?> | <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">فیس کارڈز</span></h5>
              <p><?php echo $lang == 'ur' ? 'فیس کی صورتحال دیکھیں' : 'View fee status'; ?> | فیس کی صورتحال دیکھیں</p>
            </a>
          </div>
        </div>
      </div>
      
      <!-- Recent Activity -->
      <div class="modern-panel">
        <div class="panel-heading">
          <div class="dual-lang-header">
            <div class="header-en">
              <i class="fas fa-clock"></i> Recent Activity
            </div>
            <div class="header-separator">|</div>
            <div class="header-ur">
              <i class="fas fa-clock"></i> حالیہ سرگرمی
            </div>
          </div>
        </div>
        <div class="panel-body">
          <div class="activity-timeline">
            <?php
            $recent_activity = $conn->query("
              SELECT 'student' as type, name, registration_date as date FROM student_registration 
              UNION ALL
              SELECT 'fee' as type, CONCAT('Fee collected for ID: ', fee_card_id) as name, payment_date as date FROM student_fee_payments
              ORDER BY date DESC LIMIT 5
            ");
            
            $has_activity = false;
            while($activity = $recent_activity->fetch_assoc()) {
              $has_activity = true;
              $icon = $activity['type'] == 'student' ? 'fa-user-graduate' : 'fa-money-bill-wave';
              $type_text = $activity['type'] == 'student' ? ($lang == 'ur' ? 'طالب علم' : 'Student') : ($lang == 'ur' ? 'فیس' : 'Fee');
              ?>
              <div class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <strong><i class="fas <?php echo $icon; ?>" style="margin-right: 8px; color: #667eea;"></i><?php echo $type_text; ?> <?php echo $lang == 'ur' ? 'سرگرمی' : 'Activity'; ?></strong>
                  <?php echo $activity['name']; ?>
                  <div class="timeline-time">
                    <i class="far fa-calendar-alt"></i>
                    <?php echo date('M j, Y - g:i A', strtotime($activity['date'])); ?>
                  </div>
                </div>
              </div>
              <?php 
            }
            
            if (!$has_activity) {
              echo '<div class="text-center" style="padding: 30px; color: #a0aec0;">';
              echo '<i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>';
              echo '<p>' . ($lang == 'ur' ? 'کوئی حالیہ سرگرمی ظاہر کرنے کے لیے نہیں' : 'No recent activity to display') . ' | <span style="font-family: \'Noto Nastaliq Urdu\', \'Noto Sans Arabic\', sans-serif;">کوئی حالیہ سرگرمی ظاہر کرنے کے لیے نہیں</span></p>';
              echo '</div>';
            }
            ?>
          </div>
        </div>
      </div>
      
      <!-- Quick Stats Summary -->
      <div class="modern-panel">
        <div class="panel-heading">
          <div class="dual-lang-header">
            <div class="header-en">
              <i class="fas fa-chart-pie"></i> Quick Summary
            </div>
            <div class="header-separator">|</div>
            <div class="header-ur">
              <i class="fas fa-chart-pie"></i> فوری خلاصہ
            </div>
          </div>
        </div>
        <div class="panel-body">
          <?php
          $total_collected = $conn->query("SELECT SUM(paid_amount) as total FROM student_fee_payments")->fetch_assoc();
          $active_students = $conn->query("SELECT COUNT(*) as total FROM student_registration WHERE status = 'active'")->fetch_assoc();
          ?>
          <div style="padding: 10px 0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
              <span style="color: #718096;">
                <i class="fas fa-check-circle" style="color: #48bb78;"></i> 
                <?php echo $lang == 'ur' ? 'فعال طلبہ' : 'Active Students'; ?> | 
                <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">فعال طلبہ</span>
              </span>
              <strong><?php echo number_format($active_students['total']); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 15px;">
              <span style="color: #718096;">
                <i class="fas fa-rupee-sign" style="color: #48bb78;"></i> 
                <?php echo $lang == 'ur' ? 'کل جمع شدہ' : 'Total Collected'; ?> | 
                <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">کل جمع شدہ</span>
              </span>
              <strong>₹<?php echo number_format($total_collected['total'] ?? 0); ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
              <span style="color: #718096;">
                <i class="fas fa-clock" style="color: #ed8936;"></i> 
                <?php echo $lang == 'ur' ? 'زیر التوا رقم' : 'Pending Amount'; ?> | 
                <span style="font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', sans-serif;">زیر التوا رقم</span>
              </span>
              <strong>₹<?php echo number_format($pending_fees['total'] ?? 0); ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Initialize Chart
var feeCtx = document.getElementById('feeChart').getContext('2d');
var feeChart = new Chart(feeCtx, {
  type: 'bar',
  data: {
    labels: [],
    datasets: [
      {
        label: '<?php echo $lang == 'ur' ? 'فیس جمع شدہ' : 'Fee Collected'; ?> | فیس جمع شدہ',
        data: [],
        backgroundColor: 'rgba(13, 110, 253, 0.2)',
        borderColor: '#0d6efd',
        borderWidth: 2,
        borderRadius: 8
      },
      {
        label: '<?php echo $lang == 'ur' ? 'زیر التوا فیس' : 'Pending Fees'; ?> | زیر التوا فیس',
        data: [],
        backgroundColor: 'rgba(220, 53, 69, 0.2)',
        borderColor: '#dc3545',
        borderWidth: 2,
        borderRadius: 8
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { 
        position: 'top',
        labels: {
          usePointStyle: true,
          padding: 20
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: '#e2e8f0'
        }
      },
      x: {
        grid: {
          display: false
        }
      }
    }
  }
});

function getMonthsBetween(startDate, endDate) {
  var months = [];
  var current = new Date(startDate);
  endDate = new Date(endDate);
  while (current <= endDate) {
    months.push(current.toLocaleString('default', { month: 'long', year: 'numeric' }));
    current.setMonth(current.getMonth() + 1);
  }
  return months;
}

function updateChart(sessionId, fromDate, toDate) {
  if (!sessionId) {
    $('#noDataAlert').show();
    feeChart.data.labels = [];
    feeChart.data.datasets[0].data = [];
    feeChart.data.datasets[1].data = [];
    feeChart.update();
    return;
  }
  var months = getMonthsBetween(fromDate, toDate);
  $.ajax({
    url: 'fetch_fee_data.php',
    method: 'POST',
    data: { session_id: sessionId },
    dataType: 'json',
    success: function(response) {
      if (response.collected.length === 0 && response.pending.length === 0) {
        $('#noDataAlert').show();
      } else {
        $('#noDataAlert').hide();
      }
      feeChart.data.labels = months;
      feeChart.data.datasets[0].data = response.collected;
      feeChart.data.datasets[1].data = response.pending;
      feeChart.update();
    },
    error: function() {
      alert('Error fetching fee data.');
      $('#noDataAlert').show();
    }
  });
}

// Course Chart
<?php if ($has_enrollments && count($enrollment_rows) > 0) { ?>
var courseCtx = document.getElementById('courseChart').getContext('2d');
var courseChart = new Chart(courseCtx, {
    type: 'doughnut',
    data: {
        labels: [
          <?php 
          foreach($enrollment_rows as $row) {
            echo "'".addslashes($row['course_name'])."',";
          }
          ?>
        ],
        datasets: [{
            data: [
              <?php 
              foreach($enrollment_rows as $row) {
                echo $row['student_count'].",";
              }
              ?>
            ],
            backgroundColor: [
                '#0d6efd',
                '#198754',
                '#ffc107',
                '#dc3545',
                '#6f42c1',
                '#0dcaf0',
                '#fd7e14',
                '#d63384'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              usePointStyle: true
            }
          }
        },
        cutout: '60%'
    }
});
<?php } else { ?>
var courseCtx = document.getElementById('courseChart');
if (courseCtx) {
  courseCtx.style.display = 'none';
}
<?php } ?>

// Helper function to escape HTML
function escapeHtml(str) {
  if (!str) return '';
  return str.toString()
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

var currentLang = '<?php echo $lang; ?>';

$(document).ready(function () {
  // Initialize session selector
  var $selected = $('#sessionSelect option:selected');
  var sessionId = $selected.val();
  var fromDate = $selected.data('from');
  var toDate = $selected.data('to');
  if (sessionId) {
    updateChart(sessionId, fromDate, toDate);
  }
  
  // Session change handler
  $('#sessionSelect').on('change', function() {
    var sessionId = $(this).val();
    var fromDate = $(this).find('option:selected').data('from');
    var toDate = $(this).find('option:selected').data('to');
    updateChart(sessionId, fromDate, toDate);
  });
  
  // Mobile search toggle
  $('#searchToggleBtn').on('click', function() {
    $('#expandableSearch').toggleClass('expanded');
    if ($('#expandableSearch').hasClass('expanded')) {
      $(this).html('<i class="fas fa-times"></i> ' + (currentLang == 'ur' ? 'تلاش بند کریں' : 'تلاش بند کریں'));
      setTimeout(function() {
        $('#searchInput').focus();
      }, 300);
    } else {
      $(this).html('<i class="fas fa-search"></i> ' + (currentLang == 'ur' ? 'طلبہ تلاش کریں' : 'طالب علم تلاش کریں'));
    }
  });

  // Search button click handler
  $('#searchButton').on('click', function() {
    performSearch();
  });
  
  // Enter key handler
  $('#searchInput').on('keypress', function(e) {
    if (e.which === 13) {
      performSearch();
    }
  });
  
  // Withdrawal button handler
  $(document).on('click', '.withdrawal-btn', function(e) {
    e.preventDefault(); e.stopPropagation();
    var studentId = $(this).data('student-id');
    if (confirm(currentLang == 'ur' ? 'کیا آپ واقعی اس طالب علم کو خارج کرنا چاہتے ہیں؟ اس کا اسٹیٹس چھٹی پر ہو جائے گا۔' : 'Are you sure you want to withdraw this student? This will set the status to Leave.')) {
      var form = $('<form method="POST" action=""></form>');
      form.append('<input type="hidden" name="withdrawal" value="1">');
      form.append('<input type="hidden" name="student_id" value="' + studentId + '">');
      form.append('<input type="hidden" name="lang" value="' + currentLang + '">');
      $('body').append(form);
      form.submit();
    }
  });
  
  // Toggle type button handler
  $(document).on('click', '.toggle-type-btn', function(e) {
    e.preventDefault(); e.stopPropagation();
    var studentId = $(this).data('student-id');
    if (confirm(currentLang == 'ur' ? 'کیا آپ واقعی اس طالب علم کی قسم تبدیل کرنا چاہتے ہیں؟' : 'Are you sure you want to toggle this student\'s type?')) {
      window.location.href = window.location.pathname + '?toggle_type=' + studentId + '&lang=' + currentLang;
    }
  });
  
  // Delete button handler
  $(document).on('click', '.delete-btn', function(e) {
    e.preventDefault(); e.stopPropagation();
    var studentId = $(this).data('student-id');
    if (confirm(currentLang == 'ur' ? 'کیا آپ واقعی اس طالب علم کا ریکارڈ حذف کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this student record?')) {
      window.location.href = window.location.pathname + '?delete=' + studentId + '&lang=' + currentLang;
    }
  });
  
  // Search function
function performSearch() {
    var query = $('#searchInput').val().trim();
    
    // Show loading state
    $('#searchResultsBody').html('<tr><td colspan="3" class="text-center" style="padding: 50px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #0d6efd;"></i><p style="margin-top: 15px;">' + (currentLang == 'ur' ? 'تلاش جاری ہے...' : 'Searching...') + '</p></td></tr>');
    $('#searchResults').show();
    
    $.ajax({
      url: 'search_student.php',
      method: 'POST',
      data: { search_query: query },
      dataType: 'json',
      success: function(response) {
        console.log('Search response:', response);
        $('#searchResultsBody').empty();

        if (response.error) {
          $('#searchResultsBody').append('<tr><td colspan="3" class="text-center"><div class="no-results-message"><i class="fas fa-exclamation-circle"></i><h4>' + (currentLang == 'ur' ? 'کوئی نتیجہ نہیں ملا' : 'No Results Found') + '</h4><p>' + response.error + '</p></div></td></tr>');
        } else if (!Array.isArray(response) || response.length === 0) {
          $('#searchResultsBody').append('<tr><td colspan="3" class="text-center"><div class="no-results-message"><i class="fas fa-search"></i><h4>' + (currentLang == 'ur' ? 'کوئی طالب علم نہیں ملا' : 'No Students Found') + '</h4><p>' + (currentLang == 'ur' ? 'مختلف الفاظ کے ساتھ تلاش کرنے کی کوشش کریں' : 'Try searching with a different query') + '</p></div></td></tr>');
        } else {
          $.each(response, function(index, student) {
            var id = student.id !== undefined ? student.id : '';
            var name = student.name !== undefined ? student.name : 'N/A';
            var father_name = student.father_name !== undefined ? student.father_name : 'N/A';
            var mobile = student.mobile !== undefined ? student.mobile : 'N/A';
            var total_dues = student.total_dues !== undefined ? student.total_dues : 0;
            var reg_no = student.reg_no !== undefined ? student.reg_no : 'N/A';
            var cnic = student.cnic !== undefined ? student.cnic : 'N/A';
            var branch = student.branch !== undefined ? student.branch : 'N/A';
            var current_class = student.current_class !== undefined ? student.current_class : 'Not enrolled';
            
            // Get fee details if available
            var fee_details = student.fee_details;
            var total_fee = fee_details ? fee_details.total_fee : 0;
            var total_paid = fee_details ? fee_details.total_paid : 0;
            var total_discount = fee_details ? fee_details.total_discount : 0;
            
            // Student info with name, father name, and contact in one column
            var studentInfo = '<div class="student-info">' +
                              '<strong style="color: #0d6efd; font-size: 16px;">' + escapeHtml(name) + '</strong><br>' +
                              '<small><i class="fas fa-user" style="color: #0d6efd;"></i> ' + (currentLang == 'ur' ? 'والد:' : 'Father:') + ' ' + escapeHtml(father_name) + '</small><br>' +
                              '<small><i class="fas fa-phone" style="color: #0d6efd;"></i> ' + escapeHtml(mobile) + '</small>' +
                              '</div>';
            
            // Total dues with tooltip showing breakdown
            var duesColor = total_dues > 0 ? '#dc3545' : '#198754';
            var duesStatus = total_dues > 0 ? (currentLang == 'ur' ? 'واجب الادا' : 'Pending') : (currentLang == 'ur' ? 'ادا شدہ' : 'Paid');
            
            var duesInfo = '<div class="dues-amount" style="color: ' + duesColor + ';" ' +
                           'title="' + (currentLang == 'ur' ? 'کل فیس: ₹' : 'Total Fee: ₹') + total_fee.toLocaleString('en-IN', {minimumFractionDigits: 2}) + 
                           ' | ' + (currentLang == 'ur' ? 'ادا شدہ: ₹' : 'Paid: ₹') + total_paid.toLocaleString('en-IN', {minimumFractionDigits: 2}) + 
                           ' | ' + (currentLang == 'ur' ? 'رعایت: ₹' : 'Discount: ₹') + total_discount.toLocaleString('en-IN', {minimumFractionDigits: 2}) + '">' +
                           '₹' + parseFloat(total_dues).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + 
                           '</div>' +
                           '<div class="dues-status" style="color: ' + duesColor + '; font-size: 11px;">' +
                           duesStatus +
                           '</div>' +
                           '<small style="color: #718096; font-size: 10px;">' +
                           (currentLang == 'ur' ? 'کل: ₹' : 'Total: ₹') + total_fee.toLocaleString('en-IN', {minimumFractionDigits: 2}) + 
                           ' | ' + (currentLang == 'ur' ? 'ادا: ₹' : 'Paid: ₹') + total_paid.toLocaleString('en-IN', {minimumFractionDigits: 2}) +
                           '</small>';
            
            // ALL OLD action buttons
            var actions = '<div class="action-buttons">' +
                          '<a href="student_registration.php?edit=' + encodeURIComponent(id) + '&lang=' + currentLang + '" class="btn-action btn-edit" title="' + (currentLang == 'ur' ? 'ترمیم' : 'Edit') + '">' +
                          '<i class="fas fa-edit"></i> ' + (currentLang == 'ur' ? 'ترمیم' : 'Edit') + '</a> ' +
                          '<a href="student_registration_print.php?id=' + encodeURIComponent(id) + '&lang=' + currentLang + '" class="btn-action btn-print" target="_blank" title="' + (currentLang == 'ur' ? 'فارم پرنٹ کریں' : 'Print Form') + '">' +
                          '<i class="fas fa-print"></i> ' + (currentLang == 'ur' ? 'پرنٹ' : 'Print') + '</a> ' +
                          '<a href="fee_collection.php?id=' + encodeURIComponent(id) + '&lang=' + currentLang + '" class="btn-action btn-fee" title="' + (currentLang == 'ur' ? 'فیس جمع کریں' : 'Collect Fee') + '">' +
                          '<i class="fas fa-money-bill-wave"></i> ' + (currentLang == 'ur' ? 'فیس' : 'Fee') + '</a> ' +
                          '<button type="button" class="btn-action btn-withdrawal withdrawal-btn" data-student-id="' + id + '" title="' + (currentLang == 'ur' ? 'خارج کریں' : 'Withdrawal') + '">' +
                          '<i class="fas fa-sign-out-alt"></i> ' + (currentLang == 'ur' ? 'خارج' : 'Leave') + '</button> ' +
                          '<button type="button" class="btn-action btn-toggle toggle-type-btn" data-student-id="' + id + '" title="' + (currentLang == 'ur' ? 'قسم تبدیل کریں' : 'Toggle Type') + '">' +
                          '<i class="fas fa-exchange-alt"></i> ' + (currentLang == 'ur' ? 'قسم' : 'Type') + '</button> ' +
                          '<a href="student_profile.php?id=' + encodeURIComponent(id) + '&lang=' + currentLang + '" class="btn-action btn-profile" title="' + (currentLang == 'ur' ? 'پروفائل' : 'Profile') + '">' +
                          '<i class="fas fa-user-circle"></i> ' + (currentLang == 'ur' ? 'پروفائل' : 'Profile') + '</a> ' +
                          '<button type="button" class="btn-action btn-delete delete-btn" data-student-id="' + id + '" title="' + (currentLang == 'ur' ? 'حذف کریں' : 'Delete') + '">' +
                          '<i class="fas fa-trash"></i> ' + (currentLang == 'ur' ? 'حذف' : 'Delete') + '</button>' +
                          '</div>';
            
            var row = '<tr>' +
                      '<td data-label="' + (currentLang == 'ur' ? 'طالب علم کی معلومات' : 'Student Information') + '">' + studentInfo + '</td>' +
                      '<td data-label="' + (currentLang == 'ur' ? 'کل واجبات' : 'Total Dues') + '">' + duesInfo + '</td>' +
                      '<td data-label="' + (currentLang == 'ur' ? 'اعمال' : 'Quick Actions') + '">' + actions + '</td>' +
                      '</tr>';
            
            $('#searchResultsBody').append(row);
          });
        }
        
        if ($(window).width() <= 768) {
          $('html, body').animate({
            scrollTop: $('#searchResults').offset().top - 20
          }, 500);
        }
      },
      error: function(xhr, status, error) {
        console.error('Search error:', status, error);
        $('#searchResultsBody').empty().append('<tr><td colspan="3" class="text-center"><div class="no-results-message"><i class="fas fa-exclamation-triangle"></i><h4>' + (currentLang == 'ur' ? 'خرابی' : 'Error') + '</h4><p>' + (currentLang == 'ur' ? 'ڈیٹا حاصل کرنے میں خرابی۔ براہ کرم دوبارہ کوشش کریں۔' : 'Error fetching data. Please try again.') + '</p></div></td></tr>');
      }
    });
  }
  
  if ($(window).width() > 768) {
    $('#expandableSearch').addClass('expanded');
  }
  
  $(window).on('resize', function() {
    if ($(window).width() > 768) {
      $('#expandableSearch').css('display', 'block').addClass('expanded');
      $('#searchToggleBtn').hide();
    } else {
      $('#searchToggleBtn').show();
      if (!$('#expandableSearch').hasClass('expanded')) {
        $('#expandableSearch').removeClass('expanded');
      }
    }
  }).trigger('resize');
});
</script>

</body>
</html>