<?php
require_once('security.php');
require_once('conn_inc.php');

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$selected_class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$selected_session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
$students_data = [];
$classes = [];
$sessions = [];

// Get all sessions
$sessions_query = $conn->query("SELECT * FROM sessions ORDER BY id DESC");
if ($sessions_query) {
    while ($row = $sessions_query->fetch_assoc()) {
        $sessions[] = $row;
    }
}

// Get all classes
$classes_query = $conn->query("SELECT * FROM classes ORDER BY title ASC");
if ($classes_query) {
    while ($row = $classes_query->fetch_assoc()) {
        $classes[] = $row;
    }
}

// Auto-select latest session if none selected
if ($selected_session_id == 0 && !empty($sessions)) {
    $selected_session_id = $sessions[0]['id'];
}

// Handle AJAX fee collection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_collect'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];
    
    $student_class_id = intval($_POST['student_class_id']);
    $payment_method = 'cash';
    $payment_date = date('Y-m-d H:i:s');
    
    $conn->autocommit(false);
    
    try {
        $cash_account_title = 'Main Account';
        $master_sql = "SELECT id, title, balance FROM master_account WHERE title = '$cash_account_title' FOR UPDATE";
        $master_result = $conn->query($master_sql);
        
        if (!$master_result || $master_result->num_rows === 0) {
            throw new Exception("کیش اکاؤنٹ نہیں ملا");
        }
        
        $master_account = $master_result->fetch_assoc();
        $master_account_id = $master_account['id'];
        $current_balance = floatval($master_account['balance']);
        
        $total_collected = 0;
        $processed_cards = [];
        $student_name = '';
        
        $student_query = $conn->query("SELECT sr.name FROM student_class sc INNER JOIN student_registration sr ON sc.student_registration_id = sr.id WHERE sc.id = $student_class_id");
        if ($student_query && $student_query->num_rows > 0) {
            $student_name = $student_query->fetch_assoc()['name'];
        }
        
        if (isset($_POST['payments']) && is_array($_POST['payments'])) {
            foreach ($_POST['payments'] as $card_id => $payment_data) {
                $card_id = intval($card_id);
                $paid_amount = floatval($payment_data['amount'] ?? 0);
                $discount = floatval($payment_data['discount'] ?? 0);
                
                if ($paid_amount <= 0 && $discount <= 0) continue;
                
                $card_sql = "
                    SELECT sfc.*, ft.title as fee_type_title 
                    FROM student_fee_card sfc 
                    INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id 
                    WHERE sfc.id = $card_id AND sfc.student_class_id = $student_class_id AND sfc.status = 'pending'
                ";
                $card_result = $conn->query($card_sql);
                
                if (!$card_result || $card_result->num_rows === 0) {
                    throw new Exception("غلط فیس کارڈ");
                }
                
                $fee_card = $card_result->fetch_assoc();
                
                $already_paid_sql = "
                    SELECT COALESCE(SUM(paid_amount), 0) as paid, 
                           COALESCE(SUM(discount_amount), 0) as disc 
                    FROM student_fee_payments 
                    WHERE fee_card_id = $card_id AND status = 'completed'
                ";
                $already_paid_result = $conn->query($already_paid_sql);
                $already_paid = $already_paid_result->fetch_assoc();
                $already_paid_amount = floatval($already_paid['paid']);
                $already_discount_amount = floatval($already_paid['disc']);
                
                $remaining_due = $fee_card['total_amount'] - $already_paid_amount - $already_discount_amount;
                
                if (($paid_amount + $discount) > $remaining_due + 0.01) {
                    throw new Exception("ادائیگی بقایا سے زیادہ ہے");
                }
                
                $insert_sql = "
                    INSERT INTO student_fee_payments 
                    (fee_card_id, paid_amount, discount_amount, discount_type, discount_note, 
                     payment_date, payment_method, transaction_ref, remarks, status, is_advance) 
                    VALUES 
                    ($card_id, $paid_amount, $discount, 'fixed', '', 
                     '$payment_date', '$payment_method', '', '', 'completed', 0)
                ";
                
                if (!$conn->query($insert_sql)) {
                    throw new Exception("ادائیگی ریکارڈ ناکام");
                }
                
                $total_collected += $paid_amount;
                
                $processed_cards[] = [
                    'card_id' => $card_id,
                    'paid' => $paid_amount,
                    'total_amount' => $fee_card['total_amount']
                ];
                
                $new_total_paid = $already_paid_amount + $paid_amount;
                $new_total_discount = $already_discount_amount + $discount;
                
                if ($new_total_paid + $new_total_discount >= $fee_card['total_amount']) {
                    $conn->query("UPDATE student_fee_card SET status = 'paid' WHERE id = $card_id");
                }
            }
        }
        
        if ($total_collected > 0) {
            $new_balance = $current_balance + $total_collected;
            
            foreach ($processed_cards as $card) {
                if ($card['paid'] > 0) {
                    $conn->query("
                        INSERT INTO detail_account 
                        (master_account_id, type, amount, balance, ref_id, ref_type, created_at) 
                        VALUES 
                        ($master_account_id, 'cash in', {$card['paid']}, $new_balance, 
                         {$card['card_id']}, 'fee', NOW())
                    ");
                }
            }
            
            $conn->query("UPDATE master_account SET balance = $new_balance WHERE id = $master_account_id");
            
            $conn->commit();
            $conn->autocommit(true);
            
            // Calculate new dues for response
            $new_total_due = 0;
            $fee_cards_query = $conn->query("
                SELECT sfc.*, ft.title as fee_type_title,
                       COALESCE((SELECT SUM(paid_amount) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed'), 0) as paid_amount,
                       COALESCE((SELECT SUM(discount_amount) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed'), 0) as total_discount
                FROM student_fee_card sfc
                INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
                WHERE sfc.student_class_id = $student_class_id AND sfc.status = 'pending'
                ORDER BY sfc.due_date ASC
            ");
            
            $table_rows = '';
            $mobile_cards = '';
            $total_fee = 0;
            $total_paid = 0;
            $total_disc = 0;
            
            if ($fee_cards_query && $fee_cards_query->num_rows > 0) {
                while ($card = $fee_cards_query->fetch_assoc()) {
                    $due = $card['total_amount'] - $card['paid_amount'] - $card['total_discount'];
                    if ($due > 0) {
                        $new_total_due += $due;
                        $total_fee += $card['total_amount'];
                        $total_paid += $card['paid_amount'];
                        $total_disc += $card['total_discount'];
                        
                        $month_year = date('M-y', strtotime($card['due_date']));
                        $formatted_date = date('d M Y', strtotime($card['due_date']));
                        $is_overdue = (strtotime($card['due_date']) < time());
                        
                        $table_rows .= '
                        <tr>
                            <td data-label="قسم"><strong>' . htmlspecialchars($card['fee_type_title']) . '</strong></td>
                            <td data-label="مہینہ" class="month-date-cell">
                                <span class="month-badge">' . $month_year . '</span>
                                <span class="due-date-badge ' . ($is_overdue ? 'overdue' : '') . '">
                                    <i class="fas fa-calendar-alt"></i> ' . $formatted_date . '
                                    ' . ($is_overdue ? '<span class="badge bg-danger ms-1">زائد المعیاد</span>' : '') . '
                                </span>
                            </td>
                            <td data-label="کل" class="text-amount">' . number_format($card['total_amount']) . '</td>
                            <td data-label="ادا" class="text-success">' . number_format($card['paid_amount']) . '</td>
                            <td data-label="رعایت" class="text-warning">' . number_format($card['total_discount']) . '</td>
                            <td data-label="بقایا" class="amount-due">' . number_format($due) . '</td>
                            <td data-label="ادائیگی">
                                <input type="number" name="payments[' . $card['id'] . '][amount]" class="form-control amount-input payment-amount" data-due="' . $due . '" data-card-id="' . $card['id'] . '" data-student-id="' . $student_class_id . '" step="0.01" min="0" max="' . $due . '" placeholder="0" value="">
                            </td>
                            <td data-label="رعایت">
                                <input type="number" name="payments[' . $card['id'] . '][discount]" class="form-control amount-input discount-input" data-card-id="' . $card['id'] . '" data-student-id="' . $student_class_id . '" step="0.01" min="0" max="' . $due . '" placeholder="0" value="">
                            </td>
                            <td data-label="بیلنس">
                                <span id="balance-' . $student_class_id . '-' . $card['id'] . '" class="balance-amount fw-bold">
                                    <span id="balance-value-' . $student_class_id . '-' . $card['id'] . '">' . number_format($due) . '</span>
                                </span>
                            </td>
                        </tr>';
                        
                        $mobile_cards .= '
                        <div class="mobile-fee-item">
                            <div class="mobile-fee-header">
                                <span class="mobile-fee-type">' . htmlspecialchars($card['fee_type_title']) . '</span>
                                <span class="mobile-fee-month">' . $month_year . ($is_overdue ? ' <span class="badge bg-danger" style="font-size:0.5rem;">زائد</span>' : '') . '</span>
                            </div>
                            <div class="mobile-fee-details">
                                <div class="mobile-fee-detail">
                                    <div class="mobile-fee-detail-label">کل</div>
                                    <div class="mobile-fee-detail-value">' . number_format($card['total_amount']) . '</div>
                                </div>
                                <div class="mobile-fee-detail">
                                    <div class="mobile-fee-detail-label">ادا</div>
                                    <div class="mobile-fee-detail-value paid">' . number_format($card['paid_amount']) . '</div>
                                </div>
                                <div class="mobile-fee-detail">
                                    <div class="mobile-fee-detail-label">بقایا</div>
                                    <div class="mobile-fee-detail-value due">' . number_format($due) . '</div>
                                </div>
                            </div>
                            <div class="mobile-fee-inputs">
                                <div class="mobile-input-group">
                                    <input type="text" name="payments[' . $card['id'] . '][amount]" class="mobile-amount-input" data-due="' . $due . '" data-card-id="' . $card['id'] . '" data-student-id="' . $student_class_id . '" inputmode="numeric" pattern="[0-9]*" placeholder="ادائیگی" value="" autocomplete="off">
                                </div>
                                <div class="mobile-input-group">
                                    <input type="text" name="payments[' . $card['id'] . '][discount]" class="mobile-discount-input" data-card-id="' . $card['id'] . '" data-student-id="' . $student_class_id . '" inputmode="numeric" pattern="[0-9]*" placeholder="رعایت" value="" autocomplete="off">
                                </div>
                                <div class="mobile-balance">
                                    <span class="mobile-balance-value" id="mobile-balance-' . $student_class_id . '-' . $card['id'] . '">' . number_format($due) . '</span>
                                </div>
                            </div>
                        </div>';
                    }
                }
            }
            
            $tfoot = '';
            if ($table_rows) {
                $tfoot = '
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end fw-bold">کل:</td>
                        <td class="fw-bold">' . number_format($total_fee) . '</td>
                        <td class="fw-bold text-success">' . number_format($total_paid) . '</td>
                        <td class="fw-bold text-warning">' . number_format($total_disc) . '</td>
                        <td class="fw-bold text-danger">' . number_format($new_total_due) . '</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>';
            }
            
            // Calculate updated class stats
            $class_stats_sql = "
                SELECT 
                    COUNT(DISTINCT sc.id) as total_students,
                    COALESCE(SUM(sfc.total_amount), 0) as total_fee,
                    COALESCE(SUM(
                        (SELECT COALESCE(SUM(paid_amount), 0) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed')
                    ), 0) as total_paid,
                    COALESCE(SUM(
                        (SELECT COALESCE(SUM(discount_amount), 0) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed')
                    ), 0) as total_discount
                FROM student_class sc
                LEFT JOIN student_fee_card sfc ON sc.id = sfc.student_class_id AND sfc.status = 'pending'
                WHERE sc.class_id = $selected_class_id 
                AND sc.session_id = $selected_session_id 
                AND sc.status = 0
            ";
            $stats_result = $conn->query($class_stats_sql);
            $class_stats = $stats_result->fetch_assoc();
            
            $total_class_due = $class_stats['total_fee'] - $class_stats['total_paid'] - $class_stats['total_discount'];
            $students_with_dues_sql = "
                SELECT COUNT(DISTINCT sc.id) as count
                FROM student_class sc
                INNER JOIN student_fee_card sfc ON sc.id = sfc.student_class_id AND sfc.status = 'pending'
                WHERE sc.class_id = $selected_class_id 
                AND sc.session_id = $selected_session_id 
                AND sc.status = 0
                AND (sfc.total_amount - 
                    COALESCE((SELECT SUM(paid_amount) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed'), 0) -
                    COALESCE((SELECT SUM(discount_amount) FROM student_fee_payments WHERE fee_card_id = sfc.id AND status = 'completed'), 0)
                ) > 0
            ";
            $dues_result = $conn->query($students_with_dues_sql);
            $students_with_dues = $dues_result->fetch_assoc()['count'];
            
            $response = [
                'success' => true,
                'message' => $student_name . ' کی فیس کامیابی سے جمع! رقم: ' . number_format($total_collected),
                'student_class_id' => $student_class_id,
                'new_total_due' => number_format($new_total_due),
                'table_rows' => $table_rows,
                'tfoot' => $tfoot,
                'mobile_cards' => $mobile_cards,
                'has_fees' => ($new_total_due > 0),
                'class_stats' => [
                    'total_students' => $class_stats['total_students'],
                    'students_with_dues' => $students_with_dues,
                    'total_due' => number_format($total_class_due)
                ]
            ];
        } else {
            throw new Exception("کوئی ادائیگی نہیں کی گئی");
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        $response = ['success' => false, 'message' => 'ادائیگی ناکام: ' . $e->getMessage()];
    }
    
    echo json_encode($response);
    exit();
}

// Fetch students data when class is selected
if ($selected_class_id > 0) {
    if ($selected_session_id == 0 && !empty($sessions)) {
        $selected_session_id = $sessions[0]['id'];
    }
    
    $students_query = $conn->query("
        SELECT 
            sc.id as student_class_id,
            sc.student_registration_id,
            sr.name as student_name,
            sr.father_name,
            sr.reg_no,
            sr.mobile,
            sr.guardian_name
        FROM student_class sc
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sc.class_id = $selected_class_id 
        AND sc.session_id = $selected_session_id 
        AND sc.status = 0
        ORDER BY sr.name ASC
    ");
    
    if ($students_query && $students_query->num_rows > 0) {
        while ($student = $students_query->fetch_assoc()) {
            $student_class_id = $student['student_class_id'];
            
            $fee_cards_query = $conn->query("
                SELECT sfc.*, ft.title as fee_type_title, ft.id as fee_type_id
                FROM student_fee_card sfc
                INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
                WHERE sfc.student_class_id = $student_class_id 
                AND sfc.status = 'pending'
                ORDER BY sfc.due_date ASC
            ");
            
            $student['fee_cards'] = [];
            $student['total_due'] = 0;
            $student['total_fee'] = 0;
            $student['total_paid'] = 0;
            $student['total_discount'] = 0;
            
            if ($fee_cards_query) {
                while ($card = $fee_cards_query->fetch_assoc()) {
                    $payment_query = $conn->query("
                        SELECT COALESCE(SUM(paid_amount), 0) as total_paid, 
                               COALESCE(SUM(discount_amount), 0) as total_discount 
                        FROM student_fee_payments 
                        WHERE fee_card_id = " . $card['id'] . " AND status = 'completed'
                    ");
                    
                    if ($payment_query) {
                        $payment_data = $payment_query->fetch_assoc();
                        $card['paid_amount'] = floatval($payment_data['total_paid']);
                        $card['total_discount'] = floatval($payment_data['total_discount']);
                    } else {
                        $card['paid_amount'] = 0;
                        $card['total_discount'] = 0;
                    }
                    
                    $card['due_amount'] = $card['total_amount'] - $card['paid_amount'] - $card['total_discount'];
                    
                    if ($card['due_amount'] > 0) {
                        $student['fee_cards'][] = $card;
                        $student['total_due'] += $card['due_amount'];
                    }
                    
                    $student['total_fee'] += $card['total_amount'];
                    $student['total_paid'] += $card['paid_amount'];
                    $student['total_discount'] += $card['total_discount'];
                }
            }
            
            $students_data[] = $student;
        }
    }
}

$selected_class_name = '';

if ($selected_class_id > 0) {
    $class_name_query = $conn->query("SELECT title FROM classes WHERE id = $selected_class_id");
    if ($class_name_query && $class_name_query->num_rows > 0) {
        $selected_class_name = $class_name_query->fetch_assoc()['title'];
    }
}

function getMonthYearFromDueDate($due_date) {
    return date('M-y', strtotime($due_date));
}

function formatDueDate($due_date) {
    return date('d M Y', strtotime($due_date));
}
?>

<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
  <title>کلاس وار فیس جمع - مدرسہ مینجمنٹ سسٹم</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <link rel="stylesheet" href="css/mystyle.css" />
  
  <style>
    body {
      direction: rtl;
      text-align: right;
    }
    
    .student-profile-header {
      background: white;
      color: black;
      padding: 15px 20px;
      border-radius: 8px;
      margin-bottom: 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      text-align: right;
    }
    
    .student-profile-header h2 {
      font-size: 1.3rem;
      margin-bottom: 0.2rem;
    }
    
    .profile-info-badge {
      padding: 3px 8px;
      border-radius: 15px;
      background: rgba(255,255,255,0.15);
      margin-left: 8px;
      margin-right: 0;
      display: inline-block;
      font-size: 0.7rem;
      margin-bottom: 2px;
    }
    
    .profile-info-badge i {
      margin-left: 3px;
      margin-right: 0;
      font-size: 0.65rem;
    }
    
    .fee-table {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .fee-table thead th {
      background: #3b3a85;
      color: white;
      border-bottom: none;
      padding: 12px 8px;
      font-size: 13px;
      font-weight: 500;
      white-space: nowrap;
      text-align: right;
    }
    
    .fee-table tbody tr {
      transition: all 0.3s ease;
    }
    
    .fee-table tbody tr:hover {
      background: #f8f9fa;
    }
    
    .fee-table tbody td {
      padding: 10px 8px;
      font-size: 13px;
      vertical-align: middle;
      text-align: right;
    }
    
    .fee-table tfoot tr {
      background: #e8f5e9;
      font-weight: 600;
      border-top: 2px solid #dee2e6;
    }
    
    .fee-table tfoot td {
      padding: 10px 8px;
      font-size: 13px;
      font-weight: 600;
    }
    
    .amount-input {
      font-size: 12px;
      padding: 6px 8px;
      border: 2px solid #e0e0e0;
      border-radius: 4px;
      transition: all 0.3s ease;
      width: 85px;
      height: 32px;
    }
    
    .amount-input:focus {
      border-color: #4285F4;
      box-shadow: 0 0 0 3px rgba(66,133,244,0.1);
    }
    
    .cleared-badge {
      background: #28a745;
      color: white;
      padding: 4px 8px;
      border-radius: 20px;
      font-weight: 500;
      display: inline-block;
      font-size: 11px;
    }
    
    .cleared-badge i {
      margin-left: 4px;
      margin-right: 0;
      font-size: 10px;
    }
    
    .balance-amount {
      font-weight: 600;
      font-size: 13px;
    }
    
    .amount-due {
      color: #dc3545;
      font-weight: 600;
      font-size: 13px;
    }
    
    .month-badge {
      background: #e9ecef;
      color: #495057;
      padding: 4px 8px;
      border-radius: 15px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
      margin-left: 5px;
      margin-right: 0;
    }
    
    .container-custom {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .due-date-badge {
      color: #6c757d;
      padding: 2px 6px;
      font-size: 10px;
      font-weight: 500;
    }
    
    .due-date-badge.overdue {
      color: #dc3545;
      font-weight: 600;
    }
    
    .text-amount {
      font-weight: 600;
      font-size: 13px;
    }
    
    .fw-bold {
      font-weight: 600 !important;
    }
    
    .student-card {
      background: white;
      border-radius: 10px;
      margin-bottom: 20px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      border: 1px solid #e0e0e0;
    }
    
    .student-card-header {
      background: #f8f9fa;
      border-bottom: 2px solid #dee2e6;
      padding: 15px 20px;
    }
    
    .student-card-header h6 {
      font-size: 14px;
      font-weight: 600;
      margin: 0;
      color: #3b3a85;
    }
    
    .student-card-body {
      padding: 0;
    }
    
    .selection-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .selection-card label {
      font-weight: 600;
      font-size: 13px;
      color: #495057;
      margin-bottom: 5px;
    }
    
    .selection-card .form-select {
      font-size: 14px;
      padding: 10px 12px;
      border-radius: 6px;
      border: 2px solid #e0e0e0;
    }
    
    .payment-form-label {
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 4px;
      color: #495057;
    }
    
    .payment-form-control {
      font-size: 13px;
      padding: 6px 10px;
      border-radius: 4px;
    }
    
    .btn-process {
      font-size: 14px;
      padding: 8px 20px;
      font-weight: 600;
    }
    
    .student-due-summary {
      background: #fff3cd;
      border-right: 4px solid #ffc107;
      border-left: none;
      padding: 8px 12px;
      margin-bottom: 10px;
      font-size: 12px;
      border-radius: 4px;
    }
    
    .no-dues-badge {
      background: #d4edda;
      color: #155724;
      padding: 8px 15px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }
    
    .toast-container {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 9999;
      width: 90%;
      max-width: 500px;
    }
    
    .custom-toast {
      background: white;
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 10px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      display: flex;
      align-items: center;
      gap: 12px;
      animation: slideDown 0.3s ease;
      border-right: 4px solid #28a745;
    }
    
    .custom-toast.error {
      border-right-color: #dc3545;
    }
    
    .custom-toast .toast-icon {
      font-size: 24px;
      flex-shrink: 0;
    }
    
    .custom-toast .toast-icon.success { color: #28a745; }
    .custom-toast .toast-icon.error { color: #dc3545; }
    
    .custom-toast .toast-content { flex: 1; }
    
    .custom-toast .toast-title {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: 2px;
    }
    
    .custom-toast .toast-message {
      font-size: 12px;
      color: #6c757d;
    }
    
    .custom-toast .toast-close {
      cursor: pointer;
      color: #adb5bd;
      font-size: 18px;
      flex-shrink: 0;
    }
    
    @keyframes slideDown {
      from { transform: translateY(-100px); opacity: 0; }
      to { transform: translateY(0); opacity: 1; }
    }
    
    @keyframes slideUp {
      from { transform: translateY(0); opacity: 1; }
      to { transform: translateY(-100px); opacity: 0; }
    }
    
    .spinner-sm {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255,255,255,0.3);
      border-radius: 50%;
      border-top-color: white;
      animation: spin 0.6s linear infinite;
      vertical-align: middle;
      margin-left: 5px;
    }
    
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
    
    .mobile-only {
      display: none;
    }
    
    .desktop-only {
      display: inline;
    }
    
    /* Updated stat badge */
    .stat-updated {
      animation: pulse 0.5s ease;
    }
    
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }
    
    /* ============ MOBILE STYLES ============ */
    @media (max-width: 768px) {
      .container-custom {
        padding: 8px;
      }
      
      .student-profile-header {
        padding: 10px 12px;
        margin-bottom: 12px;
      }
      
      .student-profile-header h2 {
        font-size: 1rem;
      }
      
      .student-profile-header .profile-info-badge {
        font-size: 0.65rem;
        padding: 2px 6px;
      }
      
      .selection-card {
        padding: 10px;
        margin-bottom: 12px;
      }
      
      .selection-card .form-select {
        font-size: 0.8rem;
        padding: 8px;
      }
      
      .student-card {
        margin-bottom: 10px;
        border-radius: 8px;
      }
      
      .student-card-header {
        cursor: pointer;
        padding: 10px 12px;
        -webkit-tap-highlight-color: transparent;
        user-select: none;
      }
      
      .student-card-header:active {
        background: #e9ecef;
      }
      
      .desktop-only {
        display: none !important;
      }
      
      .mobile-only {
        display: inline-block;
      }
      
      .mobile-toggle-icon {
        font-size: 14px;
        color: #6c757d;
        transition: transform 0.3s;
        margin-right: 6px;
      }
      
      .mobile-toggle-icon.open {
        transform: rotate(180deg);
      }
      
      .desktop-table {
        display: none;
      }
      
      .mobile-fee-cards {
        display: block;
      }
      
      .mobile-student-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
      }
      
      .mobile-student-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: #2d2d2d;
      }
      
      .mobile-student-father {
        font-size: 0.7rem;
        color: #6c757d;
      }
      
      .mobile-due-badge {
        background: #fff3cd;
        color: #856404;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
      }
      
      .mobile-clear-badge {
        background: #d4edda;
        color: #155724;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
      }
      
      .mobile-fee-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 10px;
        margin-bottom: 8px;
        border-right: 3px solid #3b3a85;
      }
      
      .mobile-fee-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
      }
      
      .mobile-fee-type {
        font-size: 0.8rem;
        font-weight: 600;
        color: #2d2d2d;
      }
      
      .mobile-fee-month {
        font-size: 0.65rem;
        background: #e9ecef;
        padding: 2px 6px;
        border-radius: 8px;
        color: #495057;
      }
      
      .mobile-fee-details {
        display: flex;
        gap: 4px;
        margin-bottom: 8px;
      }
      
      .mobile-fee-detail {
        flex: 1;
        text-align: center;
        background: white;
        padding: 5px;
        border-radius: 5px;
      }
      
      .mobile-fee-detail-label {
        font-size: 0.55rem;
        color: #6c757d;
      }
      
      .mobile-fee-detail-value {
        font-size: 0.7rem;
        font-weight: 600;
      }
      
      .mobile-fee-detail-value.due { color: #dc3545; }
      .mobile-fee-detail-value.paid { color: #28a745; }
      
      .mobile-fee-inputs {
        display: flex;
        gap: 6px;
        align-items: center;
      }
      
      .mobile-input-group {
        flex: 1;
        min-width: 0;
      }
      
      .mobile-input-group input {
        width: 100%;
        padding: 12px 8px;
        border: 2px solid #d0d0d0;
        border-radius: 8px;
        font-size: 16px;
        text-align: center;
        background: white;
        color: #2d2d2d;
        -webkit-appearance: none;
        appearance: none;
        outline: none;
      }
      
      .mobile-input-group input:focus {
        border-color: #4285F4;
        box-shadow: 0 0 0 3px rgba(66,133,244,0.15);
      }
      
      .mobile-input-group input::placeholder {
        color: #adb5bd;
        font-size: 14px;
      }
      
      .mobile-balance {
        text-align: center;
        min-width: 55px;
        flex-shrink: 0;
      }
      
      .mobile-balance-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: #dc3545;
      }
      
      .mobile-balance-value.cleared {
        color: #28a745;
      }
      
      .payment-details-section {
        display: none;
      }
      
      .student-card-body {
        display: none;
        padding: 10px;
        background: #fafbfc;
      }
      
      .student-card-body.show {
        display: block;
      }
      
      .btn-collect-mobile {
        width: 100%;
        padding: 14px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        margin-top: 12px;
        -webkit-tap-highlight-color: transparent;
      }
      
      .btn-collect-mobile:active {
        background: #1e7e34;
      }
      
      .btn-collect-mobile:disabled {
        background: #adb5bd;
      }
      
      .btn-clear-mobile {
        width: 100%;
        padding: 10px;
        background: none;
        border: 1px solid #dc3545;
        color: #dc3545;
        border-radius: 8px;
        font-size: 0.85rem;
        margin-top: 8px;
        cursor: pointer;
        -webkit-tap-highlight-color: transparent;
      }
      
      .btn-clear-mobile:active {
        background: #dc3545;
        color: white;
      }
    }
    
    @media (min-width: 769px) {
      .student-card-body {
        display: block !important;
      }
      
      .mobile-only {
        display: none !important;
      }
      
      .desktop-table {
        display: block;
      }
      
      .mobile-fee-cards {
        display: none;
      }
      
      .student-card-header {
        cursor: default;
      }
      
      .btn-collect-mobile,
      .btn-clear-mobile {
        display: none;
      }
    }
  </style>
</head>
<body>

<div class="toast-container" id="toastContainer"></div>

<div class="container-custom">
  
  <!-- Class Selection -->
  <div class="selection-card">
    <form method="GET" action="" id="selectionForm">
      <div class="row g-3 align-items-end">
        <div class="col-md-8 col-8">
          <label class="payment-form-label"><i class="fas fa-chalkboard"></i> کلاس منتخب کریں</label>
          <select name="class_id" class="form-select payment-form-control" onchange="this.form.submit()">
            <option value="">کلاس منتخب کریں</option>
            <?php foreach ($classes as $class): ?>
            <option value="<?php echo $class['id']; ?>" <?php echo $selected_class_id == $class['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($class['title']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 col-4">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search"></i> دکھائیں
          </button>
        </div>
      </div>
      <input type="hidden" name="session_id" value="<?php echo $selected_session_id; ?>">
    </form>
  </div>
  
  <?php if ($selected_class_id > 0): ?>
  
  <!-- Class Header with dynamic stats -->
  <div class="student-profile-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div>
        <h2>
          <i class="fas fa-chalkboard-teacher me-1"></i>
          <?php echo htmlspecialchars($selected_class_name); ?>
        </h2>
      </div>
      <div class="mt-2 mt-sm-0">
        <a href="fee_collection.php" class="btn btn-warning btn-sm py-1 px-2 me-1" style="font-size:0.75rem;">
          <i class="fas fa-user"></i> انفرادی فیس
        </a>
        <a href="index.php" class="btn btn-success btn-sm py-1 px-2" style="font-size:0.75rem;">
          <i class="fas fa-home"></i> ڈیش بورڈ
        </a>
      </div>
    </div>
    
    <div class="mt-2 d-flex flex-wrap align-items-center">
      <span class="profile-info-badge" id="stat-total-students">
        <i class="fas fa-user-graduate"></i> کل طلبہ: <strong><?php echo count($students_data); ?></strong>
      </span>
      <?php 
        $students_with_dues = 0;
        $total_class_due = 0;
        foreach ($students_data as $s) {
          if ($s['total_due'] > 0) {
            $students_with_dues++;
            $total_class_due += $s['total_due'];
          }
        }
      ?>
      <span class="profile-info-badge" id="stat-students-with-dues">
        <i class="fas fa-exclamation-triangle"></i> واجب الادا: <strong><?php echo $students_with_dues; ?></strong>
      </span>
      <span class="profile-info-badge" id="stat-total-due">
        <i class="fas fa-money-bill-wave"></i> کل بقایا: <strong><?php echo number_format($total_class_due); ?></strong>
      </span>
    </div>
  </div>
  
  <?php if (empty($students_data)): ?>
  <div class="alert alert-info text-center py-5">
    <i class="fas fa-info-circle fa-3x mb-3"></i>
    <h4>کوئی طالب علم نہیں ملا</h4>
    <p class="mb-0">اس کلاس میں کوئی طالب علم داخل نہیں ہے۔</p>
  </div>
  <?php else: ?>
  
  <?php foreach ($students_data as $student): ?>
  <div class="student-card" id="student-card-<?php echo $student['student_class_id']; ?>">
    <div class="student-card-header" onclick="toggleMobileCard(<?php echo $student['student_class_id']; ?>)">
      <!-- DESKTOP HEADER -->
      <div class="desktop-only" style="width:100%;">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div>
            <h6>
              <i class="fas fa-user-graduate"></i>
              <?php echo htmlspecialchars($student['student_name']); ?>
              <small style="font-size:11px;">ولد: <?php echo htmlspecialchars($student['father_name']); ?></small>
            </h6>
            <div class="mt-1">
              <span class="profile-info-badge" style="background:#e9ecef;color:#495057;">
                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['reg_no']); ?>
              </span>
              <?php if (!empty($student['mobile'])): ?>
              <span class="profile-info-badge" style="background:#e9ecef;color:#495057;">
                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['mobile']); ?>
              </span>
              <?php endif; ?>
              <?php if (!empty($student['guardian_name'])): ?>
              <span class="profile-info-badge" style="background:#e9ecef;color:#495057;">
                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($student['guardian_name']); ?>
              </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-end">
            <span id="due-badge-<?php echo $student['student_class_id']; ?>" style="<?php echo $student['total_due'] <= 0 ? 'display:none;' : ''; ?>">
              <span class="student-due-summary" style="margin-bottom:0;">
                <i class="fas fa-exclamation-circle"></i> بقایا: <strong id="due-amount-<?php echo $student['student_class_id']; ?>"><?php echo number_format($student['total_due']); ?></strong>
              </span>
            </span>
            <span id="clear-badge-<?php echo $student['student_class_id']; ?>" style="<?php echo $student['total_due'] > 0 ? 'display:none;' : ''; ?>">
              <span class="no-dues-badge">
                <i class="fas fa-check-circle"></i> تمام فیس جمع
              </span>
            </span>
          </div>
        </div>
      </div>
      
      <!-- MOBILE HEADER -->
      <div class="mobile-only" style="width:100%;">
        <div class="mobile-student-info">
          <div>
            <div class="mobile-student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
            <div class="mobile-student-father">ولد: <?php echo htmlspecialchars($student['father_name']); ?></div>
          </div>
          <div style="display:flex;align-items:center;gap:6px;">
            <span id="mobile-due-badge-<?php echo $student['student_class_id']; ?>" class="mobile-due-badge" style="<?php echo $student['total_due'] <= 0 ? 'display:none;' : ''; ?>">
              بقایا: <strong id="mobile-due-amount-<?php echo $student['student_class_id']; ?>"><?php echo number_format($student['total_due']); ?></strong>
            </span>
            <span id="mobile-clear-badge-<?php echo $student['student_class_id']; ?>" class="mobile-clear-badge" style="<?php echo $student['total_due'] > 0 ? 'display:none;' : ''; ?>">
              <i class="fas fa-check-circle"></i> کلیئر
            </span>
            <i class="fas fa-chevron-down mobile-toggle-icon" id="toggle-icon-<?php echo $student['student_class_id']; ?>"></i>
          </div>
        </div>
      </div>
    </div>
    
    <div class="student-card-body" id="student-body-<?php echo $student['student_class_id']; ?>">
      <form class="student-fee-form" id="form-<?php echo $student['student_class_id']; ?>" onsubmit="return submitFeeForm(this, <?php echo $student['student_class_id']; ?>)">
        <input type="hidden" name="student_class_id" value="<?php echo $student['student_class_id']; ?>">
        <input type="hidden" name="ajax_collect" value="1">
        
        <!-- DESKTOP TABLE -->
        <div class="desktop-table">
          <div class="table-responsive">
            <table class="table fee-table mb-0">
              <thead>
                <tr>
                  <th>قسم</th>
                  <th>مہینہ (آخری تاریخ)</th>
                  <th>کل</th>
                  <th>ادا</th>
                  <th>رعایت</th>
                  <th>بقایا</th>
                  <th>ادائیگی</th>
                  <th>رعایت شامل</th>
                  <th>بیلنس</th>
                </tr>
              </thead>
              <tbody id="table-body-<?php echo $student['student_class_id']; ?>">
                <?php if (!empty($student['fee_cards'])): ?>
                  <?php foreach ($student['fee_cards'] as $card): 
                    $month_year = getMonthYearFromDueDate($card['due_date']);
                    $formatted_date = formatDueDate($card['due_date']);
                    $is_overdue = (strtotime($card['due_date']) < time()) && ($card['due_amount'] > 0);
                  ?>
                  <tr>
                    <td data-label="قسم"><strong><?php echo htmlspecialchars($card['fee_type_title']); ?></strong></td>
                    <td data-label="مہینہ" class="month-date-cell">
                      <span class="month-badge"><?php echo $month_year; ?></span>
                      <span class="due-date-badge <?php echo $is_overdue ? 'overdue' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> <?php echo $formatted_date; ?>
                        <?php if ($is_overdue): ?><span class="badge bg-danger ms-1">زائد المعیاد</span><?php endif; ?>
                      </span>
                    </td>
                    <td data-label="کل" class="text-amount"><?php echo number_format($card['total_amount']); ?></td>
                    <td data-label="ادا" class="text-success"><?php echo number_format($card['paid_amount']); ?></td>
                    <td data-label="رعایت" class="text-warning"><?php echo number_format($card['total_discount']); ?></td>
                    <td data-label="بقایا" class="amount-due"><?php echo number_format($card['due_amount']); ?></td>
                    <td data-label="ادائیگی">
                      <input type="number" name="payments[<?php echo $card['id']; ?>][amount]" class="form-control amount-input payment-amount" data-due="<?php echo $card['due_amount']; ?>" data-card-id="<?php echo $card['id']; ?>" data-student-id="<?php echo $student['student_class_id']; ?>" step="0.01" min="0" max="<?php echo $card['due_amount']; ?>" placeholder="0" value="">
                    </td>
                    <td data-label="رعایت">
                      <input type="number" name="payments[<?php echo $card['id']; ?>][discount]" class="form-control amount-input discount-input" data-card-id="<?php echo $card['id']; ?>" data-student-id="<?php echo $student['student_class_id']; ?>" step="0.01" min="0" max="<?php echo $card['due_amount']; ?>" placeholder="0" value="">
                    </td>
                    <td data-label="بیلنس">
                      <span id="balance-<?php echo $student['student_class_id']; ?>-<?php echo $card['id']; ?>" class="balance-amount fw-bold">
                        <span id="balance-value-<?php echo $student['student_class_id']; ?>-<?php echo $card['id']; ?>"><?php echo number_format($card['due_amount']); ?></span>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="9" class="text-center py-3 text-muted">کوئی زیر التوا فیس نہیں</td></tr>
                <?php endif; ?>
              </tbody>
              <?php if (!empty($student['fee_cards'])): ?>
              <tfoot id="table-foot-<?php echo $student['student_class_id']; ?>">
                <tr>
                  <td colspan="2" class="text-end fw-bold">کل:</td>
                  <td class="fw-bold"><?php echo number_format($student['total_fee']); ?></td>
                  <td class="fw-bold text-success"><?php echo number_format($student['total_paid']); ?></td>
                  <td class="fw-bold text-warning"><?php echo number_format($student['total_discount']); ?></td>
                  <td class="fw-bold text-danger"><?php echo number_format($student['total_due']); ?></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>
        
        <!-- MOBILE CARDS -->
        <div class="mobile-fee-cards" id="mobile-cards-<?php echo $student['student_class_id']; ?>">
          <?php if (!empty($student['fee_cards'])): ?>
            <?php foreach ($student['fee_cards'] as $card): 
              $month_year = getMonthYearFromDueDate($card['due_date']);
              $is_overdue = (strtotime($card['due_date']) < time());
            ?>
            <div class="mobile-fee-item">
              <div class="mobile-fee-header">
                <span class="mobile-fee-type"><?php echo htmlspecialchars($card['fee_type_title']); ?></span>
                <span class="mobile-fee-month"><?php echo $month_year; ?><?php if ($is_overdue): ?> <span class="badge bg-danger" style="font-size:0.5rem;">زائد</span><?php endif; ?></span>
              </div>
              <div class="mobile-fee-details">
                <div class="mobile-fee-detail">
                  <div class="mobile-fee-detail-label">کل</div>
                  <div class="mobile-fee-detail-value"><?php echo number_format($card['total_amount']); ?></div>
                </div>
                <div class="mobile-fee-detail">
                  <div class="mobile-fee-detail-label">ادا</div>
                  <div class="mobile-fee-detail-value paid"><?php echo number_format($card['paid_amount']); ?></div>
                </div>
                <div class="mobile-fee-detail">
                  <div class="mobile-fee-detail-label">بقایا</div>
                  <div class="mobile-fee-detail-value due"><?php echo number_format($card['due_amount']); ?></div>
                </div>
              </div>
              <div class="mobile-fee-inputs">
                <div class="mobile-input-group">
                  <input type="text" name="payments[<?php echo $card['id']; ?>][amount]" class="mobile-amount-input" data-due="<?php echo $card['due_amount']; ?>" data-card-id="<?php echo $card['id']; ?>" data-student-id="<?php echo $student['student_class_id']; ?>" inputmode="numeric" pattern="[0-9]*" placeholder="ادائیگی" value="" autocomplete="off">
                </div>
                <div class="mobile-input-group">
                  <input type="text" name="payments[<?php echo $card['id']; ?>][discount]" class="mobile-discount-input" data-card-id="<?php echo $card['id']; ?>" data-student-id="<?php echo $student['student_class_id']; ?>" inputmode="numeric" pattern="[0-9]*" placeholder="رعایت" value="" autocomplete="off">
                </div>
                <div class="mobile-balance">
                  <span class="mobile-balance-value" id="mobile-balance-<?php echo $student['student_class_id']; ?>-<?php echo $card['id']; ?>"><?php echo number_format($card['due_amount']); ?></span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-center py-3 text-muted" style="font-size:0.8rem;">کوئی زیر التوا فیس نہیں</div>
          <?php endif; ?>
        </div>
        
        <?php if (!empty($student['fee_cards'])): ?>
        <!-- DESKTOP Buttons -->
        <div class="p-3 bg-light desktop-only">
          <button type="submit" class="btn btn-success btn-process" id="submit-btn-<?php echo $student['student_class_id']; ?>">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($student['student_name']); ?> کی فیس جمع کریں
          </button>
          <button type="button" class="btn btn-outline-danger btn-process ms-2" onclick="clearStudentForm(<?php echo $student['student_class_id']; ?>)">
            <i class="fas fa-eraser"></i> صاف کریں
          </button>
        </div>
        
        <!-- MOBILE Buttons -->
        <div class="mobile-only" style="padding:10px 0;">
          <button type="submit" class="btn-collect-mobile" id="mobile-submit-btn-<?php echo $student['student_class_id']; ?>">
            <i class="fas fa-check-circle"></i> فیس جمع کریں
          </button>
          <button type="button" class="btn-clear-mobile" onclick="clearStudentForm(<?php echo $student['student_class_id']; ?>)">
            <i class="fas fa-eraser"></i> صاف کریں
          </button>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  
  <?php endif; ?>
  
  <?php else: ?>
  <div class="alert alert-info text-center py-5">
    <i class="fas fa-hand-point-up fa-3x mb-3"></i>
    <h4>براہ کرم کلاس منتخب کریں</h4>
    <p class="mb-3">فیس جمع کرنے کے لیے اوپر دیے گئے فارم سے کلاس منتخب کریں۔</p>
  </div>
  <?php endif; ?>
  
</div>

<script>
function showToast(message, type) {
  type = type || 'success';
  var container = document.getElementById('toastContainer');
  var toast = document.createElement('div');
  toast.className = 'custom-toast ' + (type === 'error' ? 'error' : '');
  var icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
  var title = type === 'success' ? 'کامیاب' : 'خرابی';
  
  toast.innerHTML = '<div class="toast-icon ' + type + '"><i class="fas ' + icon + '"></i></div>' +
    '<div class="toast-content"><div class="toast-title">' + title + '</div><div class="toast-message">' + message + '</div></div>' +
    '<div class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></div>';
  
  container.appendChild(toast);
  
  setTimeout(function() {
    if (toast.parentElement) {
      toast.style.animation = 'slideUp 0.3s ease forwards';
      setTimeout(function() { if (toast.parentElement) toast.remove(); }, 300);
    }
  }, 2000);
}

// Update class statistics in the header
function updateClassStats(stats) {
  // Update total students
  var totalStudentsEl = document.getElementById('stat-total-students');
  if (totalStudentsEl) {
    totalStudentsEl.innerHTML = '<i class="fas fa-user-graduate"></i> کل طلبہ: <strong>' + stats.total_students + '</strong>';
    totalStudentsEl.classList.add('stat-updated');
    setTimeout(function() { totalStudentsEl.classList.remove('stat-updated'); }, 500);
  }
  
  // Update students with dues
  var studentsWithDuesEl = document.getElementById('stat-students-with-dues');
  if (studentsWithDuesEl) {
    studentsWithDuesEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> واجب الادا: <strong>' + stats.students_with_dues + '</strong>';
    studentsWithDuesEl.classList.add('stat-updated');
    setTimeout(function() { studentsWithDuesEl.classList.remove('stat-updated'); }, 500);
  }
  
  // Update total due
  var totalDueEl = document.getElementById('stat-total-due');
  if (totalDueEl) {
    totalDueEl.innerHTML = '<i class="fas fa-money-bill-wave"></i> کل بقایا: <strong>' + stats.total_due + '</strong>';
    totalDueEl.classList.add('stat-updated');
    setTimeout(function() { totalDueEl.classList.remove('stat-updated'); }, 500);
  }
}

function toggleMobileCard(studentId) {
  if (window.innerWidth > 768) return;
  
  var body = document.getElementById('student-body-' + studentId);
  var icon = document.getElementById('toggle-icon-' + studentId);
  var isCurrentlyOpen = body.classList.contains('show');
  
  var allBodies = document.querySelectorAll('.student-card-body');
  var allIcons = document.querySelectorAll('.mobile-toggle-icon');
  
  allBodies.forEach(function(otherBody) {
    if (otherBody.id !== 'student-body-' + studentId) otherBody.classList.remove('show');
  });
  
  allIcons.forEach(function(otherIcon) {
    if (otherIcon.id !== 'toggle-icon-' + studentId) otherIcon.classList.remove('open');
  });
  
  if (isCurrentlyOpen) {
    body.classList.remove('show');
    icon.classList.remove('open');
  } else {
    body.classList.add('show');
    icon.classList.add('open');
    
    setTimeout(function() {
      var card = document.getElementById('student-card-' + studentId);
      if (card) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 150);
  }
}

function filterNumericInput(value) {
  return value.replace(/[^0-9]/g, '');
}

function calculateBalance(studentId, cardId, changedField) {
  var form = document.getElementById('form-' + studentId);
  if (!form) return;
  
  var allAmountInputs = form.querySelectorAll('input[name="payments[' + cardId + '][amount]"]');
  var allDiscountInputs = form.querySelectorAll('input[name="payments[' + cardId + '][discount]"]');
  
  if (allAmountInputs.length === 0 || allDiscountInputs.length === 0) return;
  
  var activeAmountInput = null;
  allAmountInputs.forEach(function(inp) {
    if (inp === document.activeElement) activeAmountInput = inp;
  });
  if (!activeAmountInput) activeAmountInput = allAmountInputs[0];
  
  var activeDiscountInput = null;
  allDiscountInputs.forEach(function(inp) {
    if (inp === document.activeElement) activeDiscountInput = inp;
  });
  if (!activeDiscountInput) activeDiscountInput = allDiscountInputs[0];
  
  if (activeAmountInput.classList.contains('mobile-amount-input')) {
    activeAmountInput.value = filterNumericInput(activeAmountInput.value);
  }
  if (activeDiscountInput.classList.contains('mobile-discount-input')) {
    activeDiscountInput.value = filterNumericInput(activeDiscountInput.value);
  }
  
  var due = parseFloat(activeAmountInput.getAttribute('data-due')) || 0;
  var payment = parseFloat(activeAmountInput.value) || 0;
  var discount = parseFloat(activeDiscountInput.value) || 0;
  
  if (payment > due) { payment = due; }
  if (discount > due) { discount = due; }
  
  if (payment + discount > due) {
    if (changedField === 'discount') {
      payment = due - discount;
      if (payment < 0) payment = 0;
    } else {
      discount = due - payment;
      if (discount < 0) discount = 0;
    }
  }
  
  var balance = due - payment - discount;
  if (balance < 0) balance = 0;
  
  allAmountInputs.forEach(function(inp) { 
    if (inp !== document.activeElement) inp.value = payment || ''; 
  });
  allDiscountInputs.forEach(function(inp) { 
    if (inp !== document.activeElement) inp.value = discount || ''; 
  });
  
  var balanceDisplay = document.getElementById('balance-value-' + studentId + '-' + cardId);
  if (balanceDisplay) balanceDisplay.textContent = balance.toLocaleString();
  
  var mobileBalance = document.getElementById('mobile-balance-' + studentId + '-' + cardId);
  if (mobileBalance) {
    mobileBalance.textContent = balance.toLocaleString();
    if (balance <= 0) mobileBalance.classList.add('cleared');
    else mobileBalance.classList.remove('cleared');
  }
}

document.addEventListener('input', function(e) {
  if (e.target.classList.contains('payment-amount') || e.target.classList.contains('discount-input') ||
      e.target.classList.contains('mobile-amount-input') || e.target.classList.contains('mobile-discount-input')) {
    var studentId = e.target.getAttribute('data-student-id');
    var cardId = e.target.getAttribute('data-card-id');
    var field = (e.target.classList.contains('discount-input') || e.target.classList.contains('mobile-discount-input')) ? 'discount' : 'payment';
    calculateBalance(studentId, cardId, field);
  }
});

document.addEventListener('click', function(e) {
  if (window.innerWidth <= 768) {
    var target = e.target;
    if (target.closest('.student-card-body') || 
        target.tagName === 'INPUT' || 
        target.tagName === 'TEXTAREA' || 
        target.tagName === 'SELECT' || 
        target.tagName === 'BUTTON' ||
        target.closest('button') ||
        target.closest('input')) {
      e.stopPropagation();
    }
  }
});

function submitFeeForm(form, studentId) {
  var totalPayment = 0;
  
  form.querySelectorAll('.payment-amount, .mobile-amount-input').forEach(function(input) {
    totalPayment += parseFloat(input.value) || 0;
  });
  
  form.querySelectorAll('.discount-input, .mobile-discount-input').forEach(function(input) {
    totalPayment += parseFloat(input.value) || 0;
  });
  
  if (totalPayment <= 0) {
    showToast('براہ کرم کم از کم ایک ادائیگی کی رقم یا رعایت درج کریں۔', 'error');
    return false;
  }
  
  if (!confirm('کیا آپ واقعی ' + totalPayment.toLocaleString() + ' روپے کی ادائیگی پراسیس کرنا چاہتے ہیں؟')) {
    return false;
  }
  
  var submitBtn = document.getElementById('submit-btn-' + studentId);
  var mobileSubmitBtn = document.getElementById('mobile-submit-btn-' + studentId);
  var originalText = submitBtn ? submitBtn.innerHTML : '';
  var mobileOriginalText = mobileSubmitBtn ? mobileSubmitBtn.innerHTML : '';
  
  if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<span class="spinner-sm"></span> پراسیس...'; }
  if (mobileSubmitBtn) { mobileSubmitBtn.disabled = true; mobileSubmitBtn.innerHTML = '<span class="spinner-sm"></span> پراسیس...'; }
  
  var formData = new FormData(form);
  
  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(function(response) { return response.json(); })
  .then(function(data) {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
    if (mobileSubmitBtn) { mobileSubmitBtn.disabled = false; mobileSubmitBtn.innerHTML = mobileOriginalText; }
    
    if (data.success) {
      showToast(data.message, 'success');
      
      // Update tables
      var tableBody = document.getElementById('table-body-' + studentId);
      if (tableBody) tableBody.innerHTML = data.table_rows || '<tr><td colspan="9" class="text-center py-3 text-muted">تمام فیس جمع ہو چکی ہے</td></tr>';
      
      var tableFoot = document.getElementById('table-foot-' + studentId);
      if (tableFoot) tableFoot.innerHTML = data.tfoot || '';
      
      var mobileCards = document.getElementById('mobile-cards-' + studentId);
      if (mobileCards) mobileCards.innerHTML = data.mobile_cards || '<div class="text-center py-3" style="font-size:0.8rem;color:#6c757d;"><i class="fas fa-check-circle" style="color:#28a745;font-size:1.3rem;"></i><p style="margin-top:5px;">تمام فیس جمع ہو چکی ہے</p></div>';
      
      // Update student badges
      var dueBadge = document.getElementById('due-badge-' + studentId);
      var clearBadge = document.getElementById('clear-badge-' + studentId);
      var dueAmount = document.getElementById('due-amount-' + studentId);
      var mobileDueBadge = document.getElementById('mobile-due-badge-' + studentId);
      var mobileClearBadge = document.getElementById('mobile-clear-badge-' + studentId);
      var mobileDueAmount = document.getElementById('mobile-due-amount-' + studentId);
      
      if (!data.has_fees) {
        if (dueBadge) dueBadge.style.display = 'none';
        if (clearBadge) clearBadge.style.display = '';
        if (mobileDueBadge) mobileDueBadge.style.display = 'none';
        if (mobileClearBadge) mobileClearBadge.style.display = '';
      } else {
        if (dueBadge) dueBadge.style.display = '';
        if (clearBadge) clearBadge.style.display = 'none';
        if (dueAmount) dueAmount.textContent = data.new_total_due;
        if (mobileDueBadge) mobileDueBadge.style.display = '';
        if (mobileClearBadge) mobileClearBadge.style.display = 'none';
        if (mobileDueAmount) mobileDueAmount.textContent = data.new_total_due;
      }
      
      // Update class statistics dynamically (NO page refresh!)
      if (data.class_stats) {
        updateClassStats(data.class_stats);
      }
      
      // Clear inputs
      form.querySelectorAll('input[type="text"], input[type="number"]').forEach(function(input) {
        input.value = '';
      });
      
      // NO location.reload() - page stays, everything updates dynamically!
      
    } else {
      showToast(data.message || 'ادائیگی ناکام', 'error');
    }
  })
  .catch(function(error) {
    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
    if (mobileSubmitBtn) { mobileSubmitBtn.disabled = false; mobileSubmitBtn.innerHTML = mobileOriginalText; }
    showToast('نیٹ ورک کی خرابی۔', 'error');
  });
  
  return false;
}

function clearStudentForm(studentId) {
  if (confirm('کیا آپ اس طالب علم کی تمام ادائیگیاں صاف کرنا چاہتے ہیں؟')) {
    var form = document.getElementById('form-' + studentId);
    if (form) {
      form.querySelectorAll('input[type="text"], input[type="number"]').forEach(function(input) {
        input.value = '';
        input.dispatchEvent(new Event('input'));
      });
    }
  }
}
</script>

</body>
</html>
<?php 
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close(); 
}
?>