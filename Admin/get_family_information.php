<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$name = trim($_GET['name'] ?? '');
$father_name = trim($_GET['father_name'] ?? '');
$response = [
    'exists' => false,
    'message' => ''
];

if (empty($name)) {
    echo json_encode($response);
    exit;
}

try {
    $name_clean = strtolower($name);
    $father_name_clean = strtolower($father_name);
    $father_name_for_query = $father_name_clean === '' ? null : $father_name_clean;
    $sql = "SELECT fm.id, f.family_code 
            FROM family_members fm 
            LEFT JOIN families f ON fm.family_id = f.id 
            WHERE LOWER(fm.name) = :name";     
    $params = [':name' => $name_clean];

    if ($father_name_for_query === null) {
        $sql .= " AND (fm.father_name IS NULL OR fm.father_name = '')";
    } else {
        $sql .= " AND LOWER(fm.father_name) = :father_name";
        $params[':father_name'] = $father_name_clean;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $response['exists'] = true;
        $response['message'] = 'This person already exists in family: ' . htmlspecialchars($result['family_code']);
    }

} catch (PDOException $e) {
    
    error_log("Error in get_family_information.php: " . $e->getMessage());
}

echo json_encode($response);
?>
