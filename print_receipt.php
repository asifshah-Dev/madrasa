<?php
require_once('security.php');
require_once('conn_inc.php');

// Get payment ID(s)
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$payment_ids = isset($_GET['ids']) ? $_GET['ids'] : '';

$payments = [];
$is_multiple = false;

if (!empty($payment_ids) && $payment_id == 0) {
    $ids_array = array_map('intval', explode(',', $payment_ids));
    $ids_string = implode(',', $ids_array);
    $is_multiple = true;
    
    $query = $conn->query("
        SELECT 
            sfp.*,
            sfc.id as fee_card_id,
            sfc.total_amount as fee_card_total,
            sfc.month as fee_month,
            sfc.due_date,
            ft.title as fee_type_title,
            sc.id as student_class_id,
            c.title as class_name,
            s.title as session_title,
            sr.id as student_id,
            sr.name as student_name,
            sr.father_name,
            sr.reg_no,
            sr.mobile,
            sr.guardian_name,
            sr.current_address
        FROM student_fee_payments sfp
        INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN classes c ON sc.class_id = c.id
        INNER JOIN sessions s ON sc.session_id = s.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sfp.id IN ($ids_string) AND sfp.status = 'completed'
        ORDER BY sfp.payment_date ASC
    ");
    
    while ($row = $query->fetch_assoc()) {
        $payments[] = $row;
    }
} else if ($payment_id > 0) {
    $query = $conn->query("
        SELECT 
            sfp.*,
            sfc.id as fee_card_id,
            sfc.total_amount as fee_card_total,
            sfc.month as fee_month,
            sfc.due_date,
            ft.title as fee_type_title,
            sc.id as student_class_id,
            c.title as class_name,
            s.title as session_title,
            sr.id as student_id,
            sr.name as student_name,
            sr.father_name,
            sr.reg_no,
            sr.mobile,
            sr.guardian_name,
            sr.current_address
        FROM student_fee_payments sfp
        INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
        INNER JOIN fee_types ft ON sfc.fee_type_id = ft.id
        INNER JOIN student_class sc ON sfc.student_class_id = sc.id
        INNER JOIN classes c ON sc.class_id = c.id
        INNER JOIN sessions s ON sc.session_id = s.id
        INNER JOIN student_registration sr ON sc.student_registration_id = sr.id
        WHERE sfp.id = $payment_id AND sfp.status = 'completed'
    ");
    
    if ($query->num_rows > 0) {
        $payments[] = $query->fetch_assoc();
    }
}

if (empty($payments)) {
    die('Payment(s) not found');
}

$trans = $payments[0];

// ============ FIX: Deduplicate Fee Cards for Summary ============
$unique_fee_cards = [];
foreach ($payments as $payment) {
    $card_id = $payment['fee_card_id'];
    if (!isset($unique_fee_cards[$card_id])) {
        $unique_fee_cards[$card_id] = [
            'fee_card_id' => $payment['fee_card_id'],
            'fee_card_total' => floatval($payment['fee_card_total']),
            'fee_type_title' => $payment['fee_type_title'],
            'fee_month' => $payment['fee_month'],
            'class_name' => $payment['class_name'],
            'session_title' => $payment['session_title']
        ];
    }
}

// For each unique fee card, get payment history
$fee_cards_summary = [];
foreach ($unique_fee_cards as $card_id => $card_info) {
    // Get ALL payments for this fee card (including the selected ones)
    $all_payments_query = $conn->query("
        SELECT 
            id,
            paid_amount,
            discount_amount,
            payment_date,
            payment_method,
            transaction_ref,
            remarks
        FROM student_fee_payments 
        WHERE fee_card_id = $card_id 
        AND status = 'completed'
        ORDER BY payment_date ASC
    ");
    
    $installments = [];
    $total_paid = 0;
    $total_discount = 0;
    
    if ($all_payments_query) {
        while ($inst = $all_payments_query->fetch_assoc()) {
            $installments[] = $inst;
            $total_paid += floatval($inst['paid_amount']);
            $total_discount += floatval($inst['discount_amount']);
        }
    }
    
    $remaining_balance = $card_info['fee_card_total'] - $total_paid - $total_discount;
    
    $fee_cards_summary[] = [
        'fee_card_id' => $card_id,
        'fee_card_total' => $card_info['fee_card_total'],
        'fee_type_title' => $card_info['fee_type_title'],
        'fee_month' => $card_info['fee_month'],
        'total_paid' => $total_paid,
        'total_discount' => $total_discount,
        'remaining_balance' => $remaining_balance,
        'installments' => $installments,
        'is_fully_paid' => ($remaining_balance <= 0)
    ];
}

// Calculate totals from unique fee cards (NOT from individual payments)
$total_fee_amount = 0;
$total_paid_amount = 0;
$total_discount_amount = 0;
$total_remaining_amount = 0;

foreach ($fee_cards_summary as $card) {
    $total_fee_amount += $card['fee_card_total'];
    $total_paid_amount += $card['total_paid'];
    $total_discount_amount += $card['total_discount'];
    $total_remaining_amount += $card['remaining_balance'];
}

// Calculate this receipt's payment amount (sum of selected payments)
$total_paid_this_receipt = 0;
$total_discount_this_receipt = 0;
foreach ($payments as $p) {
    $total_paid_this_receipt += floatval($p['paid_amount']);
    $total_discount_this_receipt += floatval($p['discount_amount']);
}

// Receipt number
if ($is_multiple) {
    $receipt_no = "REC-MULTI-" . date('Ymd') . "-" . $payments[0]['id'];
} else {
    $receipt_no = "REC-" . date('Ymd', strtotime($payments[0]['payment_date'])) . "-" . $payments[0]['id'];
}

// Format month display
function formatMonthDisplay($month_str) {
    if (empty($month_str)) return '';
    $urdu_months = [
        '01' => 'جنوری', '02' => 'فروری', '03' => 'مارچ', '04' => 'اپریل',
        '05' => 'مئی', '06' => 'جون', '07' => 'جولائی', '08' => 'اگست',
        '09' => 'ستمبر', '10' => 'اکتوبر', '11' => 'نومبر', '12' => 'دسمبر'
    ];
    $date_parts = explode('-', $month_str);
    if (count($date_parts) == 2) {
        return $urdu_months[$date_parts[1]] . ' ' . $date_parts[0];
    }
    return $month_str;
}

// Number to words function
function numberToWords($number) {
    if ($number == 0) return 'Zero';
    $words = array(
        1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen', 20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty',
        50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety'
    );
    
    if ($number < 20) return $words[$number];
    if ($number < 100) {
        $tens = floor($number / 10) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '');
    }
    if ($number < 1000) {
        $hundreds = floor($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' Hundred' . ($remainder ? ' and ' . numberToWords($remainder) : '');
    }
    if ($number < 100000) {
        $thousands = floor($number / 1000);
        $remainder = $number % 1000;
        return numberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . numberToWords($remainder) : '');
    }
    if ($number < 10000000) {
        $lakhs = floor($number / 100000);
        $remainder = $number % 100000;
        return numberToWords($lakhs) . ' Lakh' . ($remainder ? ' ' . numberToWords($remainder) : '');
    }
    $crores = floor($number / 10000000);
    $remainder = $number % 10000000;
    return numberToWords($crores) . ' Crore' . ($remainder ? ' ' . numberToWords($remainder) : '');
}

$amount_parts = explode('.', number_format($total_paid_this_receipt, 2, '.', ''));
$rupees = intval($amount_parts[0]);
$amount_in_words = numberToWords($rupees) . ' Rupees Only';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt Summary - <?php echo $receipt_no; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            width: 72mm;
            margin: 0 auto;
            padding: 3px;
        }
        .receipt { 
            border: 1px solid #000; 
            padding: 4px;
        }
        .header { 
            text-align: center; 
            border-bottom: 1px dashed #000; 
            margin-bottom: 5px;
            padding-bottom: 3px;
        }
        .title { 
            font-size: 14px; 
            font-weight: bold; 
        }
        .subtitle {
            font-size: 9px;
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin: 2px 0;
        }
        .label { 
            font-weight: bold; 
        }
        .divider { 
            border-top: 1px dashed #000; 
            margin: 4px 0;
        }
        .summary-box {
            background: #f8f9fa;
            padding: 5px;
            margin: 5px 0;
            border: 1px solid #ddd;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
            font-size: 11px;
        }
        .total-row {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #000;
            padding-top: 3px;
            margin-top: 3px;
        }
        .footer { 
            text-align: center; 
            border-top: 1px dashed #000; 
            margin-top: 5px; 
            padding-top: 3px;
            font-size: 9px;
        }
        .no-print { 
            text-align: center; 
            margin-bottom: 8px;
        }
        .no-print button { 
            margin: 0 3px; 
            padding: 3px 8px; 
            font-size: 11px;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .paid-amount {
            color: #28a745;
            font-weight: bold;
        }
        .due-amount {
            color: #dc3545;
            font-weight: bold;
        }
        .center {
            text-align: center;
        }
        .receipt-no {
            background: #f0f0f0;
            padding: 2px 4px;
            font-family: monospace;
            font-size: 10px;
        }
        .warning-note {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 3px;
            margin: 4px 0;
            font-size: 8px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print</button>
    <button onclick="window.close()" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Close</button>
</div>

<div class="receipt">
    <!-- Header -->
    <div class="header">
        <div class="title">المدرسہ الفاروقیہ للتجوید والقراءت</div>
        <div class="subtitle">نیو کالونی مٹہ سوات پاکستان</div>
        <div class="subtitle">Ph: 09*******</div>
        <div style="font-size: 12px; font-weight: bold; margin-top: 2px;">
            <i class="fas fa-receipt"></i> فیس رسید - خلاصہ
            <?php if ($is_multiple): ?>
            <span style="background: #8b5cf6; color: white; padding: 1px 4px; border-radius: 3px; font-size: 8px;">MULTIPLE</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receipt Info -->
    <div style="margin: 4px 0;">
        <div class="info-row">
            <span class="label">Receipt No:</span>
            <span class="receipt-no"><?php echo $receipt_no; ?></span>
        </div>
        <div class="info-row">
            <span class="label">Date:</span>
            <span><?php echo date('d-m-Y H:i', strtotime($payments[0]['payment_date'])); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Payment Mode:</span>
            <span><?php echo ucfirst(str_replace('_', ' ', $payments[0]['payment_method'])); ?></span>
        </div>
        <?php if (!empty($payments[0]['transaction_ref'])): ?>
        <div class="info-row">
            <span class="label">Ref No:</span>
            <span><?php echo htmlspecialchars($payments[0]['transaction_ref']); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Student Info -->
    <div style="margin: 4px 0;">
        <div class="info-row">
            <span class="label">Student:</span>
            <span><?php echo htmlspecialchars($trans['student_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Father:</span>
            <span><?php echo htmlspecialchars($trans['father_name']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Reg No:</span>
            <span><?php echo htmlspecialchars($trans['reg_no']); ?></span>
        </div>
        <div class="info-row">
            <span class="label">Class:</span>
            <span><?php echo htmlspecialchars($trans['class_name']); ?></span>
        </div>
        <?php if (!empty($trans['session_title'])): ?>
        <div class="info-row">
            <span class="label">Session:</span>
            <span><?php echo htmlspecialchars($trans['session_title']); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="divider"></div>

    <!-- Payment Summary -->
    <div class="summary-box">
        <div class="summary-row">
            <span class="label">📋 This Payment:</span>
            <span class="paid-amount">Rs. <?php echo number_format($total_paid_this_receipt, 2); ?>/-</span>
        </div>
        <?php if ($total_discount_this_receipt > 0): ?>
        <div class="summary-row">
            <span class="label">💰 Discount This Time:</span>
            <span class="paid-amount">Rs. <?php echo number_format($total_discount_this_receipt, 2); ?>/-</span>
        </div>
        <?php endif; ?>
        
        <div class="divider" style="margin: 3px 0;"></div>
        
        <div class="summary-row">
            <span class="label">📚 Total Fee:</span>
            <span>Rs. <?php echo number_format($total_fee_amount, 2); ?>/-</span>
        </div>
        <div class="summary-row">
            <span class="label">✅ Total Paid (All):</span>
            <span class="paid-amount">Rs. <?php echo number_format($total_paid_amount, 2); ?>/-</span>
        </div>
        <?php if ($total_discount_amount > 0): ?>
        <div class="summary-row">
            <span class="label">🎁 Total Discount (All):</span>
            <span class="paid-amount">Rs. <?php echo number_format($total_discount_amount, 2); ?>/-</span>
        </div>
        <?php endif; ?>
        
        <div class="total-row summary-row">
            <span class="label">⚠️ Remaining Balance:</span>
            <span class="due-amount">Rs. <?php echo number_format($total_remaining_amount, 2); ?>/-</span>
        </div>
    </div>

    <!-- Fee Cards Details (Deduplicated) -->
    <div style="margin: 5px 0;">
        <div class="info-row" style="background: #f0f0f0; padding: 2px;">
            <span class="label">Fee Type / Month</span>
            <span class="label">Paid</span>
            <span class="label">Remaining</span>
        </div>
        <?php foreach ($fee_cards_summary as $card): ?>
        <div class="info-row" style="font-size: 10px;">
            <span><?php echo htmlspecialchars($card['fee_type_title']); ?><br><small><?php echo formatMonthDisplay($card['fee_month']); ?></small></span>
            <span class="paid-amount">Rs. <?php echo number_format($card['total_paid']); ?>/-</span>
            <span class="due-amount">Rs. <?php echo number_format($card['remaining_balance']); ?>/-</span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Amount in Words -->
    <div style="font-size: 9px; border-top: 1px dotted #000; border-bottom: 1px dotted #000; padding: 3px 0; margin: 4px 0;">
        <strong>Amount in Words:</strong> <?php echo $amount_in_words; ?>
    </div>

    <!-- Remarks -->
    <?php if (!empty($payments[0]['remarks'])): ?>
    <div style="background: #fff3cd; padding: 3px; margin: 4px 0; border: 1px solid #ffc107; font-size: 9px;">
        <strong>Remarks:</strong> <?php echo htmlspecialchars($payments[0]['remarks']); ?>
    </div>
    <?php endif; ?>

    <!-- Warning if multiple payments selected from same fee card -->
    <?php if (count($payments) > count($fee_cards_summary)): ?>
    <div class="warning-note">
        <i class="fas fa-info-circle"></i> Note: Multiple installments combined for same fee card
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer">
        <div>🇵🇰 Thank You | جزاک اللہ</div>
        <div style="font-size: 8px;">Computer generated receipt</div>
    </div>
</div>

<script>
    setTimeout(function() {
        <?php if (isset($_GET['auto_print']) && $_GET['auto_print'] == 1): ?>
        window.print();
        <?php endif; ?>
    }, 500);
</script>
</body>
</html>
<?php $conn->close(); ?>