<?php
require_once('security.php');
require_once('conn_inc.php');

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

// Translations
$translations = [
    'en' => [
        'title' => 'Student Registration Form',
        'print_btn' => 'Print Form',
        'back_btn' => 'Back',
        'basic_info' => 'Basic Information',
        'address_info' => 'Address Information',
        'guardian_info' => 'Guardian Information',
        'academic_info' => 'Academic Information',
        'transport_info' => 'Transport Information',
        'other_info' => 'Other Information',
        'branch' => 'Branch',
        'village_council' => 'Village Council',
        'session' => 'Session',
        'class' => 'Class',
        'name' => 'Student Name',
        'father_name' => 'Father Name',
        'mobile' => 'Mobile',
        'cnic' => 'CNIC',
        'current_address' => 'Current Address',
        'permanent_address' => 'Permanent Address',
        'guardian_name' => 'Guardian Name',
        'guardian_mobile' => 'Guardian Mobile',
        'guardian_address' => 'Guardian Address',
        'guardian_cnic' => 'Guardian CNIC',
        'previous_schools' => 'Previous Schools',
        'other_details' => 'Other Details',
        'student_image' => 'Student Image',
        'transport_fee' => 'Transport Fee',
        'using_transport' => 'Using Transport',
        'registration_date' => 'Registration Date',
        'yes' => 'Yes',
        'no' => 'No',
        'signature' => 'Signature',
        'date' => 'Date',
        'place' => 'Place',
        'form_title' => 'STUDENT REGISTRATION FORM',
        'school_name' => 'Al-Madrasa Al-Farooqiyah Li Tajweed Wal Qira\'at',
        'school_address' => 'New Colony Matta Swat, Pakistan',
        'office_use' => 'For Office Use Only',
        'registration_no' => 'Registration No.',
        'form_footer' => 'I hereby declare that the information provided is correct to the best of my knowledge.'
    ],
    'ur' => [
        'title' => 'طالب علم رجسٹریشن فارم',
        'print_btn' => 'فارم پرنٹ کریں',
        'back_btn' => 'واپس جائیں',
        'basic_info' => 'بنیادی معلومات',
        'address_info' => 'پتے کی معلومات',
        'guardian_info' => 'سرپرست کی معلومات',
        'academic_info' => 'تعلیمی معلومات',
        'transport_info' => 'ٹرانسپورٹ معلومات',
        'other_info' => 'دیگر معلومات',
        'branch' => 'برانچ',
        'village_council' => 'ویلیج کونسل',
        'session' => 'سیشن',
        'class' => 'کلاس',
        'name' => 'طالب علم کا نام',
        'father_name' => 'والد کا نام',
        'mobile' => 'موبائل',
        'cnic' => 'قومی شناختی کارڈ',
        'current_address' => 'موجودہ پتہ',
        'permanent_address' => 'مستقل پتہ',
        'guardian_name' => 'سرپرست کا نام',
        'guardian_mobile' => 'سرپرست کا موبائل',
        'guardian_address' => 'سرپرست کا پتہ',
        'guardian_cnic' => 'سرپرست کا قومی شناختی کارڈ',
        'previous_schools' => 'سابقہ اسکولز',
        'other_details' => 'دیگر تفصیلات',
        'student_image' => 'طالب علم کی تصویر',
        'transport_fee' => 'ٹرانسپورٹ فیس',
        'using_transport' => 'ٹرانسپورٹ استعمال',
        'registration_date' => 'رجسٹریشن کی تاریخ',
        'yes' => 'ہاں',
        'no' => 'نہیں',
        'signature' => 'دستخط',
        'date' => 'تاریخ',
        'place' => 'مقام',
        'form_title' => 'طالب علم رجسٹریشن فارم',
        'school_name' => 'المدرسہ الفاروقیہ للتجوید والقراءت',
        'school_address' => 'نیو کالونی مٹہ سوات، پاکستان',
        'office_use' => 'دفتری استعمال کے لیے',
        'registration_no' => 'رجسٹریشن نمبر',
        'form_footer' => 'میں اقرار کرتا/کرتی ہوں کہ دی گئی معلومات میرے علم کے مطابق درست ہیں۔'
    ]
];

// Get student data
$student_data = [];
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $result = $conn->query("
        SELECT sr.*, 
               vc.title AS village_title, 
               b.title AS branch_title,
               s.title AS session_title,
               c.title AS class_title,
               co.title AS course_title
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
        LEFT JOIN sessions s ON sc.session_id = s.id
        LEFT JOIN classes c ON sc.class_id = c.id
        LEFT JOIN courses co ON c.course_id = co.id
        WHERE sr.id = '$id'
    ");
    
    if ($result->num_rows > 0) {
        $student_data = $result->fetch_assoc();
    }
}

// Build language switcher URLs
$current_query = $_GET;
$current_query['lang'] = 'en';
$en_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
$current_query['lang'] = 'ur';
$ur_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($current_query);
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" dir="<?php echo $lang == 'ur' ? 'rtl' : 'ltr'; ?>">
<head>
    <title><?php echo $translations[$lang]['title']; ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&family=Noto+Sans+Arabic&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.4;
        }

        <?php if ($lang == 'ur'): ?>
        body, .text-right, .table th, .table td, .label, .value, .section-title {
            text-align: right;
            direction: rtl;
            font-family: 'Noto Nastaliq Urdu', 'Noto Sans Arabic', 'Jameel Noori Nastaleeq', 'Nafees', sans-serif;
        }
        
        .photo-container {
            float: left;
            margin-left: 0;
            margin-right: 15px;
        }
        
        .header-content {
            margin-left: 0;
            margin-right: 120px;
        }
        <?php endif; ?>

        .print-container {
            max-width: 850px;
            margin: 10px auto;
            padding: 15px;
            border: 1px solid #2c3e50;
            background: #fff;
        }

        .print-header {
            position: relative;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2c3e50;
            min-height: 100px;
        }

        .photo-container {
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 120px;
            border: 1px solid #ccc;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f9f9f9;
            overflow: hidden;
        }
        
        <?php if ($lang == 'ur'): ?>
        .photo-container {
            left: 0;
            right: auto;
        }
        <?php endif; ?>

        .student-photo {
            max-width: 90px;
            max-height: 110px;
            object-fit: cover;
        }
        
        .no-image-text {
            font-size: 10px;
            text-align: center;
            color: #999;
            padding: 5px;
        }

        .header-content {
            margin-right: 120px;
        }
        
        <?php if ($lang == 'ur'): ?>
        .header-content {
            margin-left: 120px;
            margin-right: 0;
        }
        <?php endif; ?>

        .school-logo {
            width: 50px;
            height: 50px;
            display: inline-block;
            vertical-align: middle;
        }

        .print-title {
            font-size: 9px;
            font-weight: 700;
            color: #2c3e50;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .school-info {
            font-size: 22px;
            color: #2c3e50;
            font-weight: 600;
            margin: 2px 0;
        }
        
        .school-address {
            font-size: 12px;
            color: #555;
            margin: 2px 0;
        }

        .form-section {
            margin-bottom: 10px;
            padding: 8px;
            background: #fafafa;
            border-radius: 3px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background: #2c3e50;
            padding: 5px 10px;
            margin-bottom: 8px;
            border-radius: 3px;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .form-field {
            flex: 1;
            min-width: 180px;
            padding: 0 5px;
        }

        .label {
            font-weight: 600;
            font-size: 13px;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .value {
            font-size: 13px;
            padding: 3px 0;
            border-bottom: 1px solid #ccc;
            min-height: 28px;
        }

        .table-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .table-info th, .table-info td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 13px;
        }

        .table-info th {
            background: #e7f1fa;
            font-weight: 600;
            color: #2c3e50;
            width: 35%;
        }
        
        .table-info td {
            width: 65%;
        }

        .signature-area {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .signature-box {
            width: 180px;
            text-align: center;
            padding-top: 3px;
        }

        .signature-line {
            border-top: 1px solid #2c3e50;
            margin-top: 20px;
            margin-bottom: 5px;
        }

        .footer-note {
            margin: 15px 0 5px;
            font-size: 10px;
            font-style: italic;
            text-align: center;
            color: #555;
        }

        .office-use {
            border: 1px solid #2c3e50;
            padding: 8px;
            margin-bottom: 10px;
            background: #fafafa;
            border-radius: 3px;
        }
        
        .office-use .form-row {
            margin-bottom: 0;
        }

        /* Language Switcher */
        .language-switcher {
            margin-bottom: 15px;
            text-align: right;
        }
        
        .language-switcher .btn {
            margin-left: 5px;
        }
        
        <?php if ($lang == 'ur'): ?>
        .language-switcher {
            text-align: left;
        }
        .language-switcher .btn {
            margin-right: 5px;
            margin-left: 0;
        }
        <?php endif; ?>

        @media print {
            body {
                margin: 0;
                background: white;
                font-size: 10pt;
            }
            .no-print {
                display: none;
            }
            .print-container {
                margin: 0;
                border: none;
                padding: 5px;
            }
            .form-section {
                background: none;
                padding: 5px;
            }
            .table-info th, .table-info td {
                border: 1px solid #000;
            }
            .page-break {
                page-break-after: always;
            }
            .photo-container {
                border: 1px solid #000;
            }
        }
        
        @media (max-width: 768px) {
            .print-container {
                padding: 10px;
            }
            .form-field {
                min-width: 100%;
                margin-bottom: 5px;
            }
            .photo-container {
                position: relative;
                float: right;
                margin-bottom: 10px;
            }
            .header-content {
                margin-right: 0;
            }
            <?php if ($lang == 'ur'): ?>
            .photo-container {
                float: left;
            }
            .header-content {
                margin-left: 0;
            }
            <?php endif; ?>
        }
    </style>
</head>
<body>
<div class="container no-print" style="margin: 10px auto;">
    <div class="row">
        <div class="col-md-12">
            <div class="language-switcher">
                <a href="<?php echo htmlspecialchars($en_url); ?>" class="btn btn-xs btn-default <?php echo $lang == 'en' ? 'active' : ''; ?>">
                    <span class="glyphicon glyphicon-globe"></span> English
                </a>
                <a href="<?php echo htmlspecialchars($ur_url); ?>" class="btn btn-xs btn-default <?php echo $lang == 'ur' ? 'active' : ''; ?>">
                    <span class="glyphicon glyphicon-globe"></span> اردو
                </a>
                <button onclick="window.print();" class="btn btn-primary btn-sm">
                    <span class="glyphicon glyphicon-print"></span> 
                    <?php echo $translations[$lang]['print_btn']; ?>
                </button>
                <a href="student_list.php?lang=<?php echo $lang; ?>" class="btn btn-default btn-sm">
                    <span class="glyphicon glyphicon-arrow-left"></span> 
                    <?php echo $translations[$lang]['back_btn']; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="print-container">
    <!-- Header with Image on Top Left/Right based on language -->
    <div class="print-header">
        <!-- Student Photo Container - Positioned at top right (or left for Urdu) -->
        
        
        <!-- Header Content -->
        <div class="header-content">
            <div class="school-info"><?php echo $translations[$lang]['school_name']; ?></div>
            <div class="school-address"><?php echo $translations[$lang]['school_address']; ?></div>
            <div class="print-title"><?php echo $translations[$lang]['form_title']; ?></div>

        </div>
    </div>

    <!-- Office Use Only -->
    <div class="office-use">
        <div class="section-title"><?php echo $translations[$lang]['office_use']; ?></div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['registration_no']; ?></div>
                <div class="value"><?php echo isset($student_data['id']) ? htmlspecialchars($student_data['reg_no']) : ''; ?></div>
            </div>
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['registration_date']; ?></div>
                <div class="value"><?php echo isset($student_data['registration_date']) ? htmlspecialchars($student_data['registration_date']) : date('Y-m-d'); ?></div>
            </div>
        </div>
    </div>

    <!-- Basic Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['basic_info']; ?></div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['name']; ?></div>
                <div class="value"><?php echo isset($student_data['name']) ? htmlspecialchars($student_data['name']) : ''; ?></div>
            </div>
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['father_name']; ?></div>
                <div class="value"><?php echo isset($student_data['father_name']) ? htmlspecialchars($student_data['father_name']) : ''; ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['mobile']; ?></div>
                <div class="value"><?php echo isset($student_data['mobile']) ? htmlspecialchars($student_data['mobile']) : ''; ?></div>
            </div>
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['cnic']; ?></div>
                <div class="value"><?php echo isset($student_data['cnic']) ? htmlspecialchars($student_data['cnic']) : ''; ?></div>
            </div>
        </div>
    </div>

    <!-- Address Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['address_info']; ?></div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['current_address']; ?></div>
                <div class="value" style="min-height: 40px;"><?php echo isset($student_data['current_address']) ? nl2br(htmlspecialchars($student_data['current_address'])) : ''; ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['permanent_address']; ?></div>
                <div class="value" style="min-height: 40px;"><?php echo isset($student_data['permanent_address']) ? nl2br(htmlspecialchars($student_data['permanent_address'])) : ''; ?></div>
            </div>
        </div>
    </div>

    <!-- Guardian Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['guardian_info']; ?></div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['guardian_name']; ?></div>
                <div class="value"><?php echo isset($student_data['guardian_name']) ? htmlspecialchars($student_data['guardian_name']) : ''; ?></div>
            </div>
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['guardian_mobile']; ?></div>
                <div class="value"><?php echo isset($student_data['guardian_mobile']) ? htmlspecialchars($student_data['guardian_mobile']) : ''; ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['guardian_cnic']; ?></div>
                <div class="value"><?php echo isset($student_data['guardian_cnic']) ? htmlspecialchars($student_data['guardian_cnic']) : ''; ?></div>
            </div>
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['guardian_address']; ?></div>
                <div class="value" style="min-height: 40px;"><?php echo isset($student_data['guardian_address']) ? nl2br(htmlspecialchars($student_data['guardian_address'])) : ''; ?></div>
            </div>
        </div>
    </div>

    <!-- Academic Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['academic_info']; ?></div>
        <table class="table-info">
            <tr>
                <th><?php echo $translations[$lang]['branch']; ?></th>
                <td><?php echo isset($student_data['branch_title']) ? htmlspecialchars($student_data['branch_title']) : ''; ?></td>
            </tr>
            <tr>
                <th><?php echo $translations[$lang]['village_council']; ?></th>
                <td><?php echo isset($student_data['village_title']) ? htmlspecialchars($student_data['village_title']) : ''; ?></td>
            </tr>
            <tr>
                <th><?php echo $translations[$lang]['session']; ?></th>
                <td><?php echo isset($student_data['session_title']) ? htmlspecialchars($student_data['session_title']) : ''; ?></td>
            </tr>
            <tr>
                <th><?php echo $translations[$lang]['class']; ?></th>
                <td><?php echo isset($student_data['class_title']) ? htmlspecialchars($student_data['class_title']) : ''; ?></td>
            </tr>
        </table>
    </div>

    <!-- Transport Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['transport_info']; ?></div>
        <table class="table-info">
            <tr>
                <th><?php echo $translations[$lang]['using_transport']; ?></th>
                <td><?php echo isset($student_data['is_transport']) ? ($student_data['is_transport'] == 1 ? $translations[$lang]['yes'] : $translations[$lang]['no']) : ''; ?></td>
            </tr>
            <?php if (isset($student_data['is_transport']) && $student_data['is_transport'] == 1): ?>
            <tr>
                <th><?php echo $translations[$lang]['transport_fee']; ?></th>
                <td><?php echo isset($student_data['transport_fee']) ? 'Rs. ' . number_format($student_data['transport_fee']) : ''; ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Other Information -->
    <div class="form-section">
        <div class="section-title"><?php echo $translations[$lang]['other_info']; ?></div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['previous_schools']; ?></div>
                <div class="value" style="min-height: 40px;"><?php echo isset($student_data['previous_schools_description']) ? nl2br(htmlspecialchars($student_data['previous_schools_description'])) : ''; ?></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-field">
                <div class="label"><?php echo $translations[$lang]['other_details']; ?></div>
                <div class="value" style="min-height: 40px;"><?php echo isset($student_data['student_other_description']) ? nl2br(htmlspecialchars($student_data['student_other_description'])) : ''; ?></div>
            </div>
        </div>
    </div>

    <!-- Signature Area -->
    <div class="signature-area">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><?php echo $translations[$lang]['signature']; ?></div>
            <div class="label">(<?php echo ($lang == 'ur') ? 'والد/سرپرست' : 'Father/Guardian'; ?>)</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><?php echo $translations[$lang]['date']; ?></div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div><?php echo $translations[$lang]['place']; ?></div>
        </div>
    </div>

    <!-- Footer Note -->
    <div class="footer-note">
        <?php echo $translations[$lang]['form_footer']; ?>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
</body>
</html>
<?php
$conn->close();
?>