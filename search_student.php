<?php
// Set UTF-8 header FIRST before any output
header('Content-Type: application/json; charset=utf-8');

require_once 'conn_inc.php';

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// Set MySQL connection charset
$conn->set_charset("utf8mb4");

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['error' => 'Database connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get search query
$search_query = isset($_POST['search_query']) ? trim($_POST['search_query']) : '';

error_log("Received POST data: " . json_encode($_POST, JSON_UNESCAPED_UNICODE));

if (empty($search_query)) {
    error_log("Empty search query received");
    echo json_encode(['error' => 'Empty search query'], JSON_UNESCAPED_UNICODE);
    exit;
}

error_log("Processing search query: " . $search_query);

$search_query_escaped = $conn->real_escape_string($search_query);

// Check if search query is numeric (likely an ID search)
$is_numeric = is_numeric($search_query);

if ($is_numeric) {
    // For numeric search, try exact ID match first, then LIKE for other fields
    $sql = "
        SELECT 
            sr.id,
            sr.name,
            sr.father_name,
            sr.mobile,
            sr.cnic,
            sr.reg_no,
            COALESCE(b.title, 'N/A') AS branch,
            COALESCE(vc.title, 'N/A') AS village_council,
            COALESCE(c.title, 'Not enrolled') AS current_class
        FROM student_registration sr
        LEFT JOIN branches b ON sr.branch_id = b.id
        LEFT JOIN village_councils vc ON sr.village_council_id = vc.id
        LEFT JOIN student_class sc ON sr.id = sc.student_registration_id AND sc.status = 0
        LEFT JOIN classes cl ON sc.class_id = cl.id
        LEFT JOIN courses c ON cl.course_id = c.id
        WHERE sr.id = ? 
           OR sr.reg_no = ?
           OR sr.mobile LIKE ?
           OR sr.cnic LIKE ?
        GROUP BY sr.id
        ORDER BY sr.id ASC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL prepare error: " . $conn->error);
        echo json_encode(['error' => 'SQL prepare failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $id = intval($search_query);
    $exact_match = $search_query_escaped;
    $like_query = "%$search_query_escaped%";
    
    $stmt->bind_param("isss", $id, $exact_match, $like_query, $like_query);
} else {
    // For text search, use LIKE for name and father name
    $sql = "
        SELECT 
            sr.id,
            sr.name,
            sr.father_name,
            sr.mobile,
            sr.cnic,
            sr.reg_no,
            COALESCE(b.title, 'N/A') AS branch,
            COALESCE(vc.title, 'N/A') AS village_council,
            COALESCE(c.title, 'Not enrolled') AS current_class
        FROM student_registration sr
        LEFT JOIN branches b ON sr.branch_id = b.id
        LEFT JOIN village_councils vc ON sr.village_council_id = vc.id
        LEFT JOIN student_class sc ON sr.id = sc.student_registration_id AND sc.status = 0
        LEFT JOIN classes cl ON sc.class_id = cl.id
        LEFT JOIN courses c ON cl.course_id = c.id
        WHERE sr.name LIKE ? 
           OR sr.father_name LIKE ?
           OR sr.reg_no LIKE ?
           OR sr.mobile LIKE ?
           OR sr.cnic LIKE ?
        GROUP BY sr.id
        ORDER BY sr.name ASC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("SQL prepare error: " . $conn->error);
        echo json_encode(['error' => 'SQL prepare failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $like_query = "%$search_query_escaped%";
    $stmt->bind_param("sssss", $like_query, $like_query, $like_query, $like_query, $like_query);
}

if (!$stmt->execute()) {
    error_log("SQL execute error: " . $stmt->error);
    echo json_encode(['error' => 'SQL execution failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = $stmt->get_result();
$students = [];

while ($row = $result->fetch_assoc()) {
    $student_id = $row['id'];
    
    // Calculate total dues considering both student_fee_card and student_fee_payments tables
    // Formula: Total Fee Amount - Total Paid Amount - Total Discount (from both fee_card and payments)
    $dues_sql = "
        SELECT 
            COALESCE(fc.total_fee, 0) as total_fee_amount,
            COALESCE(fp.total_paid, 0) as total_paid_amount,
            COALESCE(fc.total_discount, 0) as fee_card_discount,
            COALESCE(fp.total_payment_discount, 0) as payment_discount_amount
        FROM (
            -- Get total fee amount and discount from student_fee_card
            SELECT 
                SUM(sfc.total_amount) as total_fee,
                SUM(COALESCE(sfc.discount_amount, 0)) as total_discount
            FROM student_fee_card sfc
            INNER JOIN student_class sc ON sfc.student_class_id = sc.id
            WHERE sc.student_registration_id = ?
        ) fc
        CROSS JOIN (
            -- Get total paid amount and discount from student_fee_payments
            SELECT 
                SUM(COALESCE(sfp.paid_amount, 0)) as total_paid,
                SUM(COALESCE(sfp.discount_amount, 0)) as total_payment_discount
            FROM student_fee_payments sfp
            INNER JOIN student_fee_card sfc ON sfp.fee_card_id = sfc.id
            INNER JOIN student_class sc ON sfc.student_class_id = sc.id
            WHERE sc.student_registration_id = ?
        ) fp
    ";
    
    $dues_stmt = $conn->prepare($dues_sql);
    if ($dues_stmt) {
        $dues_stmt->bind_param("ii", $student_id, $student_id);
        $dues_stmt->execute();
        $dues_result = $dues_stmt->get_result();
        $dues_row = $dues_result->fetch_assoc();
        
        $total_fee_amount = floatval($dues_row['total_fee_amount']);
        $total_paid_amount = floatval($dues_row['total_paid_amount']);
        $fee_card_discount = floatval($dues_row['fee_card_discount']);
        $payment_discount = floatval($dues_row['payment_discount_amount']);
        
        // Calculate total discount (from both fee card and payments)
        $total_discount = $fee_card_discount + $payment_discount;
        
        // Calculate pending dues: total_fee - total_paid - total_discount
        $total_dues = $total_fee_amount - $total_paid_amount - $total_discount;
        
        // If negative, set to 0 (overpaid)
        $total_dues = $total_dues > 0 ? $total_dues : 0;
        
        $row['total_dues'] = $total_dues;
        
        // Also send detailed breakdown if needed
        $row['fee_details'] = [
            'total_fee' => $total_fee_amount,
            'total_paid' => $total_paid_amount,
            'total_discount' => $total_discount,
            'pending_dues' => $total_dues
        ];
        
        $dues_stmt->close();
    } else {
        $row['total_dues'] = 0;
        $row['fee_details'] = [
            'total_fee' => 0,
            'total_paid' => 0,
            'total_discount' => 0,
            'pending_dues' => 0
        ];
    }
    
    // Ensure proper UTF-8 encoding for all string values
    foreach ($row as $key => $value) {
        if (is_string($value)) {
            $row[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
    }
    
    $students[] = $row;
}

error_log("Search results count: " . count($students) . " for query: " . $search_query);

// Output JSON with proper UTF-8 encoding
echo json_encode($students, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt->close();
$conn->close();
?>