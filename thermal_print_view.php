<?php
// thermal_print_view.php
// Thermal printer fee slip (80mm width) - Urdu RTL Design with English Amounts

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['print_data'])) {
    header("Location: feeremainders.php");
    exit();
}

$print_data = $_SESSION['print_data'];

// Language strings with Urdu translations
$translations = [
    'ur' => [
        'student_name' => 'طالب علم کا نام',
        'father_name' => 'والد کا نام',
        'class' => 'جماعت',
        'session' => 'سیشن',
        'issue_date' => 'تاریخ اجراء',
        'student_id' => 'شناختی نمبر',
        'fee_type' => 'فیس کی قسم',
        'month' => 'مہینہ',
        'amount' => 'رقم',
        'total_due' => 'کل واجب الادا',
        'old_dues' => 'پرانے واجبات',
        'total_amount' => 'کل رقم',
        'paid' => 'ادا شدہ',
        'balance' => 'بقایا',
        'total' => 'کل',
        'total_fees' => 'کل فیس',
        'current_session' => 'موجودہ سیشن',
        'old_session' => 'پچھلے سیشن کے واجبات',
        'monthly_summary' => 'ماہانہ خلاصہ',
        'paid_until' => 'ادا شدہ تا',
        'no_fee_data' => 'فیس کی معلومات دستیاب نہیں',
        'fee_card' => 'فیس رسید',
        'student_details' => 'طالب علم کی تفصیلات',
        'rupees' => 'روپے',
        'only' => 'صرف'
    ]
];

$lang = 'ur';
$print_type = $print_data['print_type'] ?? '';
$session_title = $print_data['session_title'] ?? '';
$class_title = $print_data['class_title'] ?? '';
$students = $print_data['students'] ?? [];
$total_remaining = $print_data['total_remaining'] ?? 0;
$total_old_dues = $print_data['total_old_dues'] ?? 0;

if ($print_type !== 'feecards') {
    header("Location: feeremainders.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ur" dir="rtl">
<head>
    <title>Fee Slip - فیس رسید</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts: Noto Nastaliq for Urdu, Roboto for English -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu:wght@400;700;800&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    
    <!-- Include JsBarcode library -->
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Noto Nastaliq Urdu', 'Roboto', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.8;
            background: #fff;
            color: #000;
            width: 80mm;
            max-width: 80mm;
            margin: 0 auto;
            padding: 0;
            font-weight: 500;
            direction: rtl;
            text-align: right;
            overflow-x: hidden;
        }
        
        .thermal-container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        .thermal-slip {
            margin: 0;
            padding: 2mm 2mm 3mm 2mm;
            page-break-after: always;
            break-after: page;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        .thermal-slip:last-child {
            page-break-after: auto;
            break-after: auto;
        }
        
        /* HEADER */
        .thermal-header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 2.5mm;
            margin-bottom: 3mm;
            direction: rtl;
        }
        
        .thermal-title {
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            font-size: 16px;
            font-weight: 900;
            line-height: 1.8;
            margin-bottom: 1mm;
            word-wrap: break-word;
            letter-spacing: 0.5px;
        }
        
        .thermal-subtitle {
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 1mm;
            word-wrap: break-word;
        }
        
        .thermal-contact {
            font-family: 'Roboto', Arial, sans-serif;
            font-size: 9px;
            font-weight: 700;
            margin-bottom: 1mm;
            direction: ltr;
        }
        
        .slip-title {
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            font-size: 14px;
            font-weight: 900;
            letter-spacing: 2px;
            margin-top: 1mm;
            background: #000;
            color: #fff;
            display: inline-block;
            padding: 2px 10px;
        }
        
        /* STUDENT INFO */
        .thermal-student-info {
            margin-bottom: 3mm;
            padding: 2.5mm;
            background: #fafafa;
            border: 2px solid #111;
            direction: rtl;
        }
        
        .info-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5mm;
            font-size: 10px;
        }
        
        .thermal-info-line {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            width: 100%;
            padding-bottom: 0.8mm;
            border-bottom: 0.5px dotted #aaa;
            direction: rtl;
        }
        
        .thermal-info-line:last-child {
            border-bottom: none;
        }
        
        .thermal-info-label {
            font-weight: 900;
            color: #111;
            max-width: 42%;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            flex-shrink: 0;
            font-size: 9px;
            letter-spacing: 0.5px;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            text-align: right;
        }
        
        .thermal-info-value {
            color: #000;
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 1mm;
            font-weight: 800;
            font-size: 10px;
            font-family: 'Roboto', Arial, sans-serif;
            direction: ltr;
        }
        
        /* Old dues highlight */
        .old-dues-highlight {
            color: #c00;
            font-weight: 900;
            border-top: 2px solid #c00;
            margin-top: 1.5mm;
            padding-top: 1.5mm;
            background: #fff0f0;
        }
        
        /* FEE TABLE */
        .thermal-fee-table {
            width: 96%;
            max-width: 96%;
            margin: 0 auto 3mm auto;
            table-layout: fixed;
            border-collapse: collapse;
            font-size: 9.5px;
            direction: rtl;
        }
        
        .thermal-fee-table th {
            background: #111;
            color: #fff;
            padding: 1.5mm 1mm;
            text-align: right;
            font-weight: 900;
            border: 1px solid #000;
            font-size: 9px;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            overflow: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .thermal-fee-table td {
            padding: 1.2mm 1mm;
            border: 1px solid #333;
            font-weight: 700;
            text-align: right;
            overflow: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .fee-type-col {
            width: 40%;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            font-weight: 900 !important;
            font-size: 12px;
            text-align: right;
        }
        
        .month-col {
            width: 20%;
            text-align: center;
            font-weight: 900 !important;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
            font-size: 12px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .amount-col {
            width: 40%;
            text-align: left;
            font-weight: 900 !important;
            font-family: 'Roboto', monospace;
            direction: ltr;
            font-size: 13px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        /* Used only in the 4-column (Total/Paid/Balance) monthly summary table,
           where fee-type-col (40%) + 3 of these (20% each) = 100% width */
        .amount-col-sm {
            width: 20%;
            text-align: left;
            font-weight: 900 !important;
            font-family: 'Roboto', monospace;
            direction: ltr;
            font-size: 10px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .thermal-total-row {
            font-weight: 900 !important;
            border-top: 2px solid #000;
            background-color: #dcdcdc;
        }
        
        .thermal-total-row td {
            font-size: 14px;
            padding: 1.5mm;
            font-weight: 900 !important;
        }
        
        .old-dues-row {
            background: #fff0f0;
        }
        
        .old-dues-row td {
            font-weight: 900;
            color: #c00;
        }
        
        /* Month/Session Headers */
        .thermal-month-header {
            font-weight: 900;
            background: #111;
            color: #fff;
            padding: 1.5mm;
            margin: 3mm 0 2mm 0;
            font-size: 11px;
            text-align: center;
            border: 1px solid #000;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
        }
        
        .thermal-session-header {
            font-weight: 900;
            background: #111;
            color: #fff;
            padding: 1.5mm;
            margin: 3mm 0 2mm 0;
            font-size: 11px;
            text-align: center;
            border: 1px solid #000;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
        }
        
        .thermal-month-total {
            font-weight: 900;
            background: #f0f0f0;
            padding: 1.5mm;
            margin: 2mm 0;
            font-size: 10px;
            text-align: center;
            border: 1px solid #333;
            font-family: 'Noto Nastaliq Urdu', Arial, sans-serif;
        }
        
        /* Divider */
        .thermal-divider {
            border-bottom: 2px dashed #333;
            margin: 2mm 0;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 1.5mm 6mm;
            font-size: 11px;
            font-weight: 900;
            margin: 2mm 0;
            letter-spacing: 1.5px;
            font-family: 'Roboto', Arial, sans-serif;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #1e7e1e;
            color: #fff;
        }
        
        .status-unpaid {
            background: #c62828;
            color: #fff;
        }
        
        .status-partial {
            background: #e67e22;
            color: #fff;
        }
        
        /* Paid placeholder */
        .paid-placeholder {
            text-align: center;
            padding: 2.5mm;
            background: #e8f5e9;
            font-weight: 900;
            font-size: 12px;
            border: 2px solid #2e7d32;
            color: #1b5e1b;
            margin-bottom: 2.5mm;
        }
        
        /* BARCODE */
        .barcode-wrapper {
            text-align: center;
            margin: 2.5mm 0;
            padding: 2mm 0;
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            background: #fff;
            direction: ltr;
        }
        
        .barcode-container {
            text-align: center;
            margin: 0 auto;
        }
        
        .barcode-svg {
            width: 65mm !important;
            height: 15mm !important;
            display: block;
            margin: 0 auto;
        }
        
        .barcode-id {
            font-family: 'Roboto', monospace;
            font-size: 9px;
            font-weight: 900;
            margin-top: 1.5mm;
            letter-spacing: 2px;
            direction: ltr;
            background: #f0f0f0;
            display: inline-block;
            padding: 1mm 3mm;
            text-align: center;
        }
        
        /* Footer */
        .thermal-footer {
            text-align: center;
            font-size: 9px;
            margin-top: 2.5mm;
            padding-top: 2mm;
            border-top: 2px dashed #333;
            font-family: 'Roboto', Arial, sans-serif;
        }
        
        .thankyou {
            font-weight: 900;
            font-size: 10.5px;
            margin-bottom: 1mm;
            letter-spacing: 1px;
        }
        
        .signature {
            text-align: center;
            margin-top: 2.5mm;
            font-size: 9px;
            font-family: 'Roboto', Arial, sans-serif;
            font-weight: 800;
        }
        
        .signature-line {
            display: inline-block;
            width: 38mm;
            border-bottom: 2px solid #000;
            margin-bottom: 0.8mm;
        }
        
        /* Spacer */
        .slip-spacer {
            height: 2mm;
            border-bottom: 1px dotted #ccc;
            margin: 1mm 0;
        }
        
        /* PRINT CONTROLS */
        .no-print {
            text-align: center;
            margin: 3mm;
            padding: 2mm;
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 3px;
            font-size: 9px;
            direction: ltr;
        }
        
        .no-print button {
            margin: 0.5mm;
            padding: 1.5mm 4mm;
            font-size: 9px;
            cursor: pointer;
            background: #007bff;
            color: #fff;
            border: none;
            border-radius: 2px;
            font-family: 'Roboto', Arial, sans-serif;
            font-weight: 700;
        }
        
        .no-print button.cancel {
            background: #6c757d;
        }
        
        /* PRINT MEDIA - SIMPLE FIX */
        @media print {
            @page {
                size: 80mm auto;
                margin: 0mm;
            }
            
            html, body {
                width: 80mm !important;
                max-width: 80mm !important;
                min-width: 80mm !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .no-print {
                display: none !important;
            }
            
            .barcode-svg {
                width: 55mm !important;
            }
        }
    </style>
    
    <script>
        function generateBarcodes() {
            const barcodeContainers = document.querySelectorAll('.barcode-container');
            
            barcodeContainers.forEach((container, index) => {
                const studentId = container.getAttribute('data-student-id');
                const svgId = 'barcode-' + index;
                
                const svgElement = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                svgElement.id = svgId;
                svgElement.className = 'barcode-svg';
                
                container.innerHTML = '';
                container.appendChild(svgElement);
                
                try {
                    JsBarcode('#' + svgId, studentId, {
                        format: "CODE128",
                        width: 1.2,
                        height: 15,
                        displayValue: false,
                        background: "transparent",
                        lineColor: "#000",
                        margin: 0
                    });
                } catch(e) {
                    container.innerHTML = '<div style="font-family:monospace;font-size:9px;font-weight:bold;">' + studentId + '</div>';
                }
            });
        }
        
        window.addEventListener('load', function() {
            generateBarcodes();
            setTimeout(function() {
                window.print();
                setTimeout(function() {
                    window.location.href = 'feeremainders.php';
                }, 2000);
            }, 300);
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'feeremainders.php';
            }
        });
    </script>
</head>
<body>
    <div class="no-print">
        <strong>THERMAL PRINT PREVIEW</strong><br>
        <small>80mm | Urdu Layout | English Amounts | Auto-print enabled</small><br>
        <button onclick="window.print()">PRINT</button>
        <button class="cancel" onclick="window.location.href='feeremainders.php'">CANCEL</button>
    </div>
    
    <div class="thermal-container">
        <?php foreach ($students as $student_index => $student): ?>
        
        <div class="thermal-slip">
            <!-- Header -->
            <div class="thermal-header">
                <div class="thermal-title">المدرسہ الفاروقیہ للتجوید والقراءت</div>
                <div class="thermal-subtitle">نیو کالونی مٹہ سوات پاکستان</div>
                <div class="thermal-contact">Ph: 0946-791555</div>
                <div class="slip-title"><?php echo $translations[$lang]['fee_card']; ?></div>
            </div>
            
            <!-- Student Information -->
            <div class="thermal-student-info">
                <div class="info-grid">
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['student_id']; ?>:</span>
                        <span class="thermal-info-value"><?php echo $student['id']; ?></span>
                    </div>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['student_name']; ?>:</span>
                        <span class="thermal-info-value"><?php echo htmlspecialchars($student['name']); ?></span>
                    </div>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['father_name']; ?>:</span>
                        <span class="thermal-info-value"><?php echo htmlspecialchars($student['father_name']); ?></span>
                    </div>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['class']; ?>:</span>
                        <span class="thermal-info-value"><?php echo htmlspecialchars($class_title); ?></span>
                    </div>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['session']; ?>:</span>
                        <span class="thermal-info-value"><?php echo htmlspecialchars($session_title); ?></span>
                    </div>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['issue_date']; ?>:</span>
                        <span class="thermal-info-value"><?php echo date('d/m/y'); ?></span>
                    </div>
                    
                    <?php if (isset($student['paid_until_display']) && $student['paid_until_display']): ?>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['paid_until']; ?>:</span>
                        <span class="thermal-info-value"><?php echo $student['paid_until_display']; ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($student['mobile']) && !empty($student['mobile'])): ?>
                    <div class="thermal-info-line">
                        <span class="thermal-info-label">موبائل:</span>
                        <span class="thermal-info-value"><?php echo htmlspecialchars($student['mobile']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (isset($student['old_dues_amount']) && $student['old_dues_amount'] > 0): ?>
                    <div class="thermal-info-line old-dues-highlight">
                        <span class="thermal-info-label"><?php echo $translations[$lang]['old_dues']; ?>:</span>
                        <span class="thermal-info-value"><?php echo number_format($student['old_dues_amount']); ?>/-</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="thermal-divider"></div>
            
            <?php 
            $has_any_fees = false;
            
            // Check for pending fees
            if (isset($student['pending_fees']) && !empty($student['pending_fees'])) {
                $has_any_fees = true;
                ?>
                
                <!-- Pending Fees Table -->
                <table class="thermal-fee-table">
                    <thead>
                        <tr>
                            <th class="fee-type-col"><?php echo $translations[$lang]['fee_type']; ?></th>
                            <th class="month-col"><?php echo $translations[$lang]['month']; ?></th>
                            <th class="amount-col"><?php echo $translations[$lang]['amount']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student['pending_fees'] as $fee): ?>
                        <tr>
                            <td class="fee-type-col"><?php echo htmlspecialchars($fee['fee_type']); ?></td>
                            <td class="month-col"><?php echo htmlspecialchars($fee['month']); ?></td>
                            <td class="amount-col"><?php echo number_format($fee['amount']); ?>/-</td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (isset($student['old_dues_amount']) && $student['old_dues_amount'] > 0): ?>
                        <tr class="old-dues-row">
                            <td class="fee-type-col"><?php echo $translations[$lang]['old_dues']; ?></td>
                            <td class="month-col">-</td>
                            <td class="amount-col"><?php echo number_format($student['old_dues_amount']); ?>/-</td>
                        </tr>
                        <?php endif; ?>
                        
                        <!-- Grand Total Row -->
                        <tr class="thermal-total-row">
                            <td colspan="2" class="fee-type-col"><?php echo $translations[$lang]['total_due']; ?>:</td>
                            <td class="amount-col"><?php echo number_format($student['total_due']); ?>/-</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php
            } 
            // Check for all_fees (old structure)
            else if (isset($student['all_fees']) && !empty($student['all_fees'])) {
                
                $all_months = [];
                foreach ($student['all_fees'] as $session_fees) {
                    if (isset($session_fees['months']) && is_array($session_fees['months'])) {
                        foreach ($session_fees['months'] as $month_key => $month_name) {
                            if (!isset($all_months[$month_key])) {
                                $all_months[$month_key] = $month_name;
                            }
                        }
                    }
                }
                ksort($all_months);
                
                foreach ($student['all_fees'] as $session_fees): 
                    $session_total = 0;
                    $session_has_fees = false;
                    
                    if (isset($session_fees['fee_types']) && is_array($session_fees['fee_types'])) {
                        foreach ($session_fees['fee_types'] as $fee_type) {
                            if (isset($fee_type['total']) && $fee_type['total'] > 0) {
                                $session_has_fees = true;
                                $has_any_fees = true;
                                break;
                            }
                        }
                    }
                    
                    if (!$session_has_fees) continue;
                    
                    $is_current_session = (isset($session_fees['session_id']) && $session_fees['session_id'] == ($print_data['session_id'] ?? 0));
                    $session_label = $is_current_session ? 
                        $translations[$lang]['current_session'] . ' (' . (isset($session_fees['session_title']) ? $session_fees['session_title'] : '') . ')' : 
                        $translations[$lang]['old_session'] . ' (' . (isset($session_fees['session_title']) ? $session_fees['session_title'] : '') . ')';
                ?>
                
                <div class="thermal-session-header">
                    <?php echo $session_label; ?>
                </div>
                
                <?php 
                foreach ($all_months as $month_key => $month_name): 
                    $month_has_fees = false;
                    $month_total = 0;
                    $month_paid = 0;
                    $month_balance = 0;
                    
                    if (isset($session_fees['fee_types']) && is_array($session_fees['fee_types'])) {
                        foreach ($session_fees['fee_types'] as $fee_type) {
                            if (isset($fee_type['months'][$month_key]) && $fee_type['months'][$month_key] !== null) {
                                $card = $fee_type['months'][$month_key];
                                if (isset($card['amount']) || isset($card['total_amount'])) {
                                    $month_has_fees = true;
                                    $month_total += isset($card['total_amount']) ? $card['total_amount'] : 0;
                                    $month_paid += isset($card['paid_amount']) ? $card['paid_amount'] : 0;
                                    $month_balance += isset($card['amount']) ? $card['amount'] : 0;
                                }
                            }
                        }
                    }
                    
                    if (!$month_has_fees) continue;
                    
                    $session_total += $month_balance;
                ?>
                
                <div class="thermal-month-header">
                    <?php echo $month_name; ?> - <?php echo $translations[$lang]['monthly_summary']; ?>
                </div>
                
                <table class="thermal-fee-table">
                    <thead>
                        <tr>
                            <th class="fee-type-col"><?php echo $translations[$lang]['fee_type']; ?></th>
                            <th class="amount-col-sm"><?php echo $translations[$lang]['total_amount']; ?></th>
                            <th class="amount-col-sm"><?php echo $translations[$lang]['paid']; ?></th>
                            <th class="amount-col-sm"><?php echo $translations[$lang]['balance']; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (isset($session_fees['fee_types']) && is_array($session_fees['fee_types'])) {
                            foreach ($session_fees['fee_types'] as $fee_type): 
                                if (isset($fee_type['months'][$month_key]) && $fee_type['months'][$month_key] !== null):
                                    $card = $fee_type['months'][$month_key];
                                    if (isset($card['total_amount']) && $card['total_amount'] > 0):
                        ?>
                        <tr>
                            <td class="fee-type-col"><?php echo substr(htmlspecialchars($fee_type['title']), 0, 15); ?></td>
                            <td class="amount-col-sm"><?php echo number_format($card['total_amount']); ?>/-</td>
                            <td class="amount-col-sm"><?php echo number_format($card['paid_amount']); ?>/-</td>
                            <td class="amount-col-sm"><?php echo number_format($card['amount']); ?>/-</td>
                        </tr>
                        <?php 
                                    endif;
                                endif;
                            endforeach;
                        }
                        ?>
                        
                        <tr class="thermal-total-row">
                            <td class="fee-type-col"><?php echo $translations[$lang]['total']; ?></td>
                            <td class="amount-col-sm"><?php echo number_format($month_total); ?>/-</td>
                            <td class="amount-col-sm"><?php echo number_format($month_paid); ?>/-</td>
                            <td class="amount-col-sm"><?php echo number_format($month_balance); ?>/-</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php endforeach; ?>
                
                <div class="thermal-month-total">
                    <?php echo $session_label; ?> <?php echo $translations[$lang]['total_fees']; ?>: 
                    <strong><?php echo number_format($session_total); ?>/-</strong>
                </div>
                
                <?php endforeach; ?>
                
                <?php 
            }
            
            // If no fees at all
            if (!$has_any_fees): 
            ?>
            <div class="paid-placeholder">
                <?php echo $translations[$lang]['no_fee_data']; ?>
            </div>
            <?php endif; ?>
            
            <!-- Status Badge -->
            <?php 
            $status = ($student['total_due'] ?? 0) <= 0 ? 'paid' : 'unpaid';
            $status_class = 'status-' . $status;
            $status_text = $status == 'paid' ? 'ALL PAID' : 'UNPAID';
            ?>
            <div style="text-align:center;">
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </div>
            
            <!-- Barcode -->
            <div class="barcode-wrapper">
                <div class="barcode-container" data-student-id="<?php echo $student['id']; ?>">
                </div>
                <div class="barcode-id">
                    ID: <?php echo $student['id']; ?>
                </div>
            </div>
            
            
        </div>
        
        <?php if ($student_index < count($students) - 1): ?>
        <div class="slip-spacer"></div>
        <?php endif; ?>
        
        <?php endforeach; ?>
    </div>
</body>
</html>
<?php
unset($_SESSION['print_data']);
if (isset($conn) && $conn) $conn->close();
?>