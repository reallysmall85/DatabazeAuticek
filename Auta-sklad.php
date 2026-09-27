<?php
session_start();
$ulozeniUmisteni = $_SERVER['REQUEST_METHOD'] === 'POST';
$dotazPolozky = isset($_GET['nactiPolozku']) || $ulozeniUmisteni;
if ($dotazPolozky) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
}

if (!isset($_SESSION['uzivatel'])) {
    if ($dotazPolozky) {
        http_response_code(403);
        echo json_encode(['chyba' => 'Pro načtení položky se přihlas oprávněným účtem.']);
        exit();
    }
    header('Location: Prihlaseni.php');
    exit();
}

$opravneni = $_SESSION['uzivatel']['opravneni'] ?? 4;
if ($opravneni > 2) {
    if ($dotazPolozky) {
        http_response_code(403);
        echo json_encode(['chyba' => 'Pro načtení položky se přihlas oprávněným účtem.']);
        exit();
    }
    header('Location: Prihlaseni.php');
    exit();
}
// QR výstavy a skladu se generuje pouze do odpovědi, bez zápisu do databáze či souboru.
if ((isset($_GET['qrVystava']) || isset($_GET['qrSklad'])) && !$dotazPolozky) {
    $skladQr = isset($_GET['qrSklad']);
    $poleQr = $skladQr ? ['sklad', 'skrin', 'krabice'] : ['sektor', 'vitrina', 'police'];
    $prefixyQr = $skladQr ? ['SKL', 'SKR', 'KRA'] : ['SEK', 'VIT', 'POL'];
    $hodnoty = [];
    foreach ($poleQr as $pole) {
        $hodnota = $_GET[$pole] ?? '';
        if (!is_string($hodnota) || !preg_match('/^[0-9]{1,20}$/D', $hodnota)) {
            http_response_code(400);
            exit('Vyplň všechna tři pole číslicemi (nejvýše 20 číslic v poli).');
        }
        $hodnoty[] = $hodnota;
    }
    session_write_close();
    require_once __DIR__ . '/phpqrcode/qrlib.php';
    header('Cache-Control: no-store');
    QRcode::png($prefixyQr[0] . $hodnoty[0] . '-' . $prefixyQr[1] . $hodnoty[1] . '-' . $prefixyQr[2] . $hodnoty[2], false, QR_ECLEVEL_M, 6, 4);
    exit();
}
if (!isset($_SESSION['sklad_csrf'])) {
    $_SESSION['sklad_csrf'] = bin2hex(random_bytes(32));
}
if ($ulozeniUmisteni) {
    $id = $_POST['id'] ?? '';
    $cil = $_POST['cil'] ?? 'model';
    if (!in_array($cil, ['model', 'krabicka'], true)) {
        http_response_code(400);
        echo json_encode(['chyba' => 'Neplatný typ umístění.']);
        exit();
    }
    $sloupecUmisteni = $cil === 'krabicka' ? 'umistenikrabicky' : 'umisteniauta';
    $umisteni = $_POST['umisteni'] ?? null;
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['sklad_csrf'], $token)) {
        http_response_code(403);
        echo json_encode(['chyba' => 'Platnost formuláře vypršela. Obnov stránku.']);
        exit();
    }
    if (!is_string($id) || !preg_match('/^[0-9]{1,20}$/D', $id)
        || !is_string($umisteni) || trim($umisteni) === '' || mb_strlen($umisteni, 'UTF-8') > 80) {
        http_response_code(400);
        echo json_encode(['chyba' => 'Neplatná položka nebo QR umístění (nejvýše 80 znaků).']);
        exit();
    }
    session_write_close();
    ob_start();
    $transakce = false;
    try {
        require_once __DIR__ . '/Pripojeni/pripojeniDatabaze.php';
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        mysqli_begin_transaction($connection);
        $transakce = true;
        $stmt = mysqli_prepare($connection, 'SELECT id FROM auta WHERE id = ? FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 's', $id);
        mysqli_stmt_execute($stmt);
        $existuje = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$existuje) {
            mysqli_rollback($connection);
            $transakce = false;
            ob_end_clean();
            http_response_code(404);
            echo json_encode(['chyba' => 'Položka nenalezena. Umístění nebylo uloženo.']);
            exit();
        }
        $stmt = mysqli_prepare($connection, 'UPDATE auta SET ' . $sloupecUmisteni . ' = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ss', $umisteni, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($connection);
        $transakce = false;
        ob_end_clean();
        echo json_encode([$sloupecUmisteni => $umisteni], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $chyba) {
        if ($transakce) mysqli_rollback($connection);
        ob_end_clean();
        error_log('Sklad - uložení umístění: ' . $chyba->getMessage());
        http_response_code(500);
        echo json_encode(['chyba' => 'Umístění se nepodařilo uložit. Načti položku znovu a ověř její údaje.']);
    }
    exit();
}
// Samostatná odpověď pro čtečku QR; oprávnění jsou ověřena výše.
if ($dotazPolozky) {
    session_write_close();
    $id = $_GET['id'] ?? '';
    if (!is_string($id) || !preg_match('/^[0-9]{1,20}$/D', trim($id))) {
        echo json_encode(['polozka' => null]);
        exit();
    }
    $id = trim($id);
    ob_start();
    try {
        require_once __DIR__ . '/Pripojeni/pripojeniDatabaze.php';
        $stmt = mysqli_prepare($connection, 'SELECT id, cislo, nazev, upresneni, umisteniauta, umistenikrabicky FROM auta WHERE id = ? LIMIT 1');
        if (!$stmt || !mysqli_stmt_bind_param($stmt, 's', $id) || !mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Načtení položky selhalo.');
        }
        $result = mysqli_stmt_get_result($stmt);
        if (!$result) {
            throw new RuntimeException('Výsledek dotazu není dostupný.');
        }
        $polozka = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        // ID posíláme jako text, aby JavaScript neztratil přesnost velkých čísel.
        if ($polozka) $polozka['id'] = (string)$polozka['id'];
        $odpoved = json_encode(['polozka' => $polozka], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        ob_end_clean();
        echo $odpoved;
    } catch (Throwable $chyba) {
        ob_end_clean();
        error_log('Sklad - načtení položky: ' . $chyba->getMessage());
        http_response_code(500);
        echo json_encode(['chyba' => 'Položku se nepodařilo načíst. Zkus to znovu.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="desktop-styly.css?v=<?php echo filemtime(__DIR__ . '/desktop-styly.css'); ?>">
    <title>Sklad aut</title>
    <meta name="sklad-csrf" content="<?php echo htmlspecialchars($_SESSION['sklad_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="horni-fixni-panel" id="horniFixniPanel">
        <div class="horni-segment horni-segment-navigace">
            <a href="Uvodni.php" title="Zpět na úvodní stránku">
                <img width="50" height="50" src="Ikony/Home.png" alt="Domů">
            </a>
            <a href="Prihlaseni.php" title="Odhlásit se">
                <img width="50" height="50" src="Ikony/Logout.png" alt="Odhlásit se">
            </a>
        </div>
    </div>
    <table class="tabulka-uzivatele">
        <tr>
            <th colspan="2">POLOŽKA</th>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center;">
                <button id="nacti-qr-polozky" type="button" class="zaoblene-tlacitko-oranzove">Načti QR položky</button>
            </td>
        </tr>
        <tr>
            <td>Databázové číslo:</td>
            <td id="databazove-cislo" aria-live="polite" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td>Číslo modelu:</td>
            <td id="cislo-modelu" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td>Název:</td>
            <td id="nazev-modelu" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td>Upřesnění:</td>
            <td id="upresneni-modelu" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td rowspan="2">Umístění modelu:</td>
            <td id="umisteni-modelu" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td style="border-left: 1px solid black;">
                <button id="nacti-qr-modelu" type="button" class="zaoblene-tlacitko-oranzove" disabled>Načti nové QR modelu</button>
            </td>
        </tr>
        <tr>
            <td rowspan="2">Umístění krabičky:</td>
            <td id="umisteni-krabicky" style="overflow-wrap: anywhere;"></td>
        </tr>
        <tr>
            <td style="border-left: 1px solid black;">
                <button id="nacti-qr-krabicky" type="button" class="zaoblene-tlacitko-oranzove" disabled>Načti nové QR krabičky</button>
            </td>
        </tr>
    </table>
    <p id="qr-zprava" class="sklad-zprava" role="status" aria-atomic="true"></p>
    <table class="tabulka-uzivatele" style="margin-top: 24px; margin-bottom: 24px;">
        <tr><th colspan="2">VÝSTAVA</th></tr>
        <tr>
            <td><label for="vystava-sektor">Sektor:</label></td>
            <td><textarea id="vystava-sektor" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><label for="vystava-vitrina">Vitrína:</label></td>
            <td><textarea id="vystava-vitrina" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><label for="vystava-police">Police:</label></td>
            <td><textarea id="vystava-police" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><button id="vystava-generuj" type="button" class="zaoblene-tlacitko-oranzove" disabled>Generuj QR</button></td>
            <td id="vystava-qr" aria-live="polite" style="text-align: center;"></td>
        </tr>
    </table>
    <table class="tabulka-uzivatele" style="margin-top: 24px; margin-bottom: 24px;">
        <tr><th colspan="2">SKLAD</th></tr>
        <tr>
            <td><label for="sklad-sklad">Sklad:</label></td>
            <td><textarea id="sklad-sklad" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><label for="sklad-skrin">Skříň:</label></td>
            <td><textarea id="sklad-skrin" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><label for="sklad-krabice">Krabice:</label></td>
            <td><textarea id="sklad-krabice" rows="1" cols="20" inputmode="numeric" maxlength="20"></textarea></td>
        </tr>
        <tr>
            <td><button id="sklad-generuj" type="button" class="zaoblene-tlacitko-oranzove" disabled>Generuj QR</button></td>
            <td id="sklad-qr" aria-live="polite" style="text-align: center;"></td>
        </tr>
    </table>
    <dialog id="qr-dialog" aria-labelledby="qr-nadpis" style="width: min(90vw, 480px); padding: 16px; border: none; border-radius: 6px;">
        <h2 id="qr-nadpis">Načti QR položky</h2>
        <p>Namiř fotoaparát na QR kód v rámečku.</p>
        <div id="qr-kamera"></div>
        <button id="qr-zavrit" type="button" class="zaoblene-tlacitko">Zavřít fotoaparát</button>
    </dialog>
    <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="auta-sklad-qr.js?v=<?php echo filemtime(__DIR__ . '/auta-sklad-qr.js'); ?>"></script>
    <script src="auta-sklad-vystava.js?v=<?php echo filemtime(__DIR__ . '/auta-sklad-vystava.js'); ?>"></script>
</body>
</html>
