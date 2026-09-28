<?php

header('Content-Type: application/json; charset=utf-8');

include("Pripojeni/pripojeniDatabaze.php");

$agentKey = 'tAjnYK0dPr0TiSK0veH0aGEnTa';

$receivedKey = '';

if (isset($_SERVER['HTTP_X_PRINT_AGENT_KEY'])) {
    $receivedKey = $_SERVER['HTTP_X_PRINT_AGENT_KEY'];
}

if ($receivedKey === '' || !hash_equals($agentKey, $receivedKey)) {
    http_response_code(403);

    echo json_encode(array(
        'success' => false,
        'message' => 'Neplatný přístupový klíč.'
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode(array(
        'success' => false,
        'message' => 'Povolena je pouze metoda POST.'
    ));
    exit;
}


// ---------------------------------------------------------
// Data
// ---------------------------------------------------------

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'Neplatná JSON data.'
    ));
    exit;
}

$jobId = isset($data['job_id']) ? (int)$data['job_id'] : 0;
$status = isset($data['status']) ? $data['status'] : '';
$errorMessage = isset($data['error_message'])
    ? trim($data['error_message'])
    : '';

if ($jobId <= 0) {
    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'Neplatné job_id.'
    ));
    exit;
}

if ($status !== 'done' && $status !== 'error') {
    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'Neplatný stav.'
    ));
    exit;
}


// ---------------------------------------------------------
// Aktualizace úlohy
// ---------------------------------------------------------

if ($status === 'done') {

    $sql = "
        UPDATE print_queue
        SET
            status = 'done',
            printed_at = NOW(),
            error_message = NULL
        WHERE id = ?
          AND status = 'printing'
    ";

    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $jobId);

} else {

    $sql = "
        UPDATE print_queue
        SET
            status = 'error',
            error_message = ?
        WHERE id = ?
          AND status = 'printing'
    ";

    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $jobId);
}

if (!$stmt || !mysqli_stmt_execute($stmt)) {
    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Nepodařilo se aktualizovat tiskovou úlohu.'
    ));
    exit;
}

$affected = mysqli_stmt_affected_rows($stmt);

mysqli_stmt_close($stmt);

echo json_encode(array(
    'success' => true,
    'job_id' => $jobId,
    'status' => $status,
    'updated' => ($affected > 0)
));