<?php

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');


// ---------------------------------------------------------
// Musí být přihlášený uživatel
// ---------------------------------------------------------

if (!isset($_SESSION['uzivatel'])) {

    http_response_code(401);

    echo json_encode(array(
        'success' => false,
        'message' => 'Přihlášení vypršelo.'
    ));

    exit;
}


// ---------------------------------------------------------
// Povolit pouze POST
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(array(
        'success' => false,
        'message' => 'Povolena je pouze metoda POST.'
    ));

    exit;
}


// ---------------------------------------------------------
// Načtení JSON dat
// ---------------------------------------------------------

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'Neplatná data požadavku.'
    ));

    exit;
}


// ---------------------------------------------------------
// Kontrola CSRF tokenu
// ---------------------------------------------------------

$csrf = isset($data['csrf'])
    ? $data['csrf']
    : '';

if (
    !isset($_SESSION['auta_mame_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['auta_mame_csrf'], $csrf)
) {

    http_response_code(403);

    echo json_encode(array(
        'success' => false,
        'message' => 'Platnost stránky vypršela. Obnovte ji a zkuste tisk znovu.'
    ));

    exit;
}


// ---------------------------------------------------------
// Hodnota QR
// ---------------------------------------------------------

$qrText = isset($data['qr_text'])
    ? trim($data['qr_text'])
    : '';

if ($qrText === '') {

    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'Chybí hodnota qr_text.'
    ));

    exit;
}


// Povolená jsou písmena, číslice a pomlčka.
// Maximálně 64 znaků.

if (!preg_match('/^[A-Za-z0-9-]{1,64}$/', $qrText)) {

    http_response_code(400);

    echo json_encode(array(
        'success' => false,
        'message' => 'QR kód obsahuje nepovolené znaky.'
    ));

    exit;
}


// ---------------------------------------------------------
// Připojení k databázi
// ---------------------------------------------------------

require_once __DIR__ . '/Pripojeni/pripojeniDatabaze.php';


// ---------------------------------------------------------
// Vložení tiskové úlohy
// ---------------------------------------------------------

$sql = "
    INSERT INTO print_queue
        (
            printer,
            qr_text,
            status,
            attempts,
            created_at
        )
    VALUES
        (
            'pt-p300bt',
            ?,
            'waiting',
            0,
            NOW()
        )
";

$stmt = mysqli_prepare($connection, $sql);

if (!$stmt) {

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Nepodařilo se připravit SQL dotaz.'
    ));

    exit;
}


mysqli_stmt_bind_param(
    $stmt,
    's',
    $qrText
);


if (!mysqli_stmt_execute($stmt)) {

    http_response_code(500);

    echo json_encode(array(
        'success' => false,
        'message' => 'Nepodařilo se vytvořit tiskovou úlohu.'
    ));

    mysqli_stmt_close($stmt);

    exit;
}


$jobId = mysqli_insert_id($connection);

mysqli_stmt_close($stmt);


// ---------------------------------------------------------
// Odpověď
// ---------------------------------------------------------

echo json_encode(array(
    'success' => true,
    'job_id' => $jobId,
    'qr_text' => $qrText,
    'message' => 'Tisková úloha byla vytvořena.'
));