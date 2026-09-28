<?php

header('Content-Type: application/json; charset=utf-8');

include("Pripojeni/pripojeniDatabaze.php");

// ---------------------------------------------------------
// Nastavení tajného klíče pro Print Agent
// ---------------------------------------------------------

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


// ---------------------------------------------------------
// Pouze GET
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode(array(
        'success' => false,
        'message' => 'Povolena je pouze metoda GET.'
    ));
    exit;
}


// ---------------------------------------------------------
// Najdi a rezervuj nejstarší čekající úlohu
// ---------------------------------------------------------

mysqli_autocommit($connection, false);

$sql = "
    SELECT
        id,
        printer,
        qr_text
    FROM print_queue
    WHERE status = 'waiting'
      AND printer = 'pt-p300bt'
    ORDER BY created_at ASC, id ASC
    LIMIT 1
    FOR UPDATE
";

$result = mysqli_query($connection, $sql);

if (!$result) {

    mysqli_rollback($connection);

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Chyba při hledání tiskové úlohy.'
    ));
    exit;
}


if (mysqli_num_rows($result) === 0) {

    mysqli_commit($connection);

    echo json_encode(array(
        'success' => true,
        'job' => null
    ));
    exit;
}


$row = mysqli_fetch_assoc($result);

$jobId = (int)$row['id'];

$sqlUpdate = "
    UPDATE print_queue
    SET
        status = 'printing',
        claimed_at = NOW(),
        attempts = attempts + 1
    WHERE id = " . $jobId . "
      AND status = 'waiting'
";

if (!mysqli_query($connection, $sqlUpdate)) {

    mysqli_rollback($connection);

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Nepodařilo se rezervovat tiskovou úlohu.'
    ));
    exit;
}

mysqli_commit($connection);


// ---------------------------------------------------------
// Odpověď agentovi
// ---------------------------------------------------------

echo json_encode(array(
    'success' => true,
    'job' => array(
        'id' => $jobId,
        'printer' => $row['printer'],
        'qr_text' => $row['qr_text']
    )
));