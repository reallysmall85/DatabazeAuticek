<?php
declare(strict_types=1);
session_start();

// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

// 2) Připojení k databázi (mysql­i)
include __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Nepodařilo se připojit k databázi: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

// 3) Kontrola oprávnění
$opravneni = isset($_SESSION['uzivatel']['opravneni']) 
    ? $_SESSION['uzivatel']['opravneni'] 
    : 4;
$jmeno     = isset($_SESSION['uzivatel']['jmeno']) 
    ? $_SESSION['uzivatel']['jmeno'] 
    : '???';
$prijmeni  = isset($_SESSION['uzivatel']['prijmeni']) 
    ? $_SESSION['uzivatel']['prijmeni'] 
    : '???';

if ($opravneni > 2) {
    header("Location: Prihlaseni.php");
    exit();
}



// ---------- Pomocne funkce ----------
function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function bindParamsDynamic(mysqli_stmt $stmt, string $types, array &$params): void
{
    $refs = [];
    $refs[] = $types;
    foreach ($params as $k => &$v) {
        $refs[] = &$v;
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function datetimeToInput(?string $value): string
{
    // MySQL DATETIME "2026-03-01 10:20:30" -> input type=datetime-local "2026-03-01T10:20"
    if ($value === null || $value === '') return '';
    $ts = strtotime($value);
    if ($ts === false) return '';
    return date('Y-m-d\TH:i', $ts);
}

function inputToDatetime(?string $value): ?string
{
    // input datetime-local "2026-03-01T10:20" -> MySQL DATETIME "2026-03-01 10:20:00"
    $value = trim((string)$value);
    if ($value === '') return null;
    $ts = strtotime($value);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

// ---------- Definice poli formularu (vsechny sloupce tabulky vstupenky) ----------
// Pozn.: "save" => zda se pole uklada do DB pri insert/update
$fields = [
    'id' => ['label' => 'id', 'type' => 'number', 'readonly' => true, 'nullable' => true, 'save' => false],
    'objednavka_id' => ['label' => 'objednavka_id', 'type' => 'number', 'nullable' => false, 'save' => true],
    'objednavka_polozka_id' => ['label' => 'objednavka_polozka_id', 'type' => 'number', 'nullable' => false, 'save' => true],

    'vstupenka_cislo' => ['label' => 'vstupenka_cislo', 'type' => 'text', 'nullable' => false, 'save' => true],
    'vstupenka_kod' => ['label' => 'vstupenka_kod', 'type' => 'text', 'nullable' => false, 'save' => true],
    'qr_kod_data' => ['label' => 'qr_kod_data', 'type' => 'textarea', 'nullable' => true, 'save' => true],

    'udalost_id' => ['label' => 'udalost_id', 'type' => 'number', 'nullable' => true, 'save' => true],
    'termin_id' => ['label' => 'termin_id', 'type' => 'number', 'nullable' => true, 'save' => true],
    'udalost_nazev' => ['label' => 'udalost_nazev', 'type' => 'text', 'nullable' => false, 'save' => true],
    'termin_zacatek' => ['label' => 'termin_zacatek', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'termin_konec' => ['label' => 'termin_konec', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'misto_konani' => ['label' => 'misto_konani', 'type' => 'text', 'nullable' => true, 'save' => true],
    'typ_vstupenky' => ['label' => 'typ_vstupenky', 'type' => 'text', 'nullable' => true, 'save' => true],
    'sektor' => ['label' => 'sektor', 'type' => 'text', 'nullable' => true, 'save' => true],
    'rada' => ['label' => 'rada', 'type' => 'text', 'nullable' => true, 'save' => true],
    'sedadlo' => ['label' => 'sedadlo', 'type' => 'text', 'nullable' => true, 'save' => true],
    'drzitel_jmeno' => ['label' => 'drzitel_jmeno', 'type' => 'text', 'nullable' => true, 'save' => true],

    'mena_kod' => ['label' => 'mena_kod', 'type' => 'text', 'nullable' => false, 'save' => true],
    'cena_bez_dph' => ['label' => 'cena_bez_dph', 'type' => 'number-step', 'step' => '0.01', 'nullable' => false, 'save' => true],
    'sazba_dph_procent' => ['label' => 'sazba_dph_procent', 'type' => 'number-step', 'step' => '0.01', 'nullable' => false, 'save' => true],
    'dph_celkem' => ['label' => 'dph_celkem', 'type' => 'number-step', 'step' => '0.01', 'nullable' => false, 'save' => true],
    'cena_celkem' => ['label' => 'cena_celkem', 'type' => 'number-step', 'step' => '0.01', 'nullable' => false, 'save' => true],

    'stav_vstupenky' => ['label' => 'stav_vstupenky', 'type' => 'text', 'nullable' => false, 'save' => true],

    'odeslano_email_dne' => ['label' => 'odeslano_email_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'doruceno_dne' => ['label' => 'doruceno_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'stazeno_dne' => ['label' => 'stazeno_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],

    'pouzita_dne' => ['label' => 'pouzita_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'scan_pocet' => ['label' => 'scan_pocet', 'type' => 'number', 'nullable' => false, 'save' => true],
    'posledni_scan_dne' => ['label' => 'posledni_scan_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'posledni_scan_vysledek' => ['label' => 'posledni_scan_vysledek', 'type' => 'text', 'nullable' => true, 'save' => true],
    'scan_misto' => ['label' => 'scan_misto', 'type' => 'text', 'nullable' => true, 'save' => true],
    'scan_zarizeni' => ['label' => 'scan_zarizeni', 'type' => 'text', 'nullable' => true, 'save' => true],
    'scan_obsluha' => ['label' => 'scan_obsluha', 'type' => 'text', 'nullable' => true, 'save' => true],

    'stornovana_dne' => ['label' => 'stornovana_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'refundovana_dne' => ['label' => 'refundovana_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'zneplatnena_dne' => ['label' => 'zneplatnena_dne', 'type' => 'datetime-local', 'nullable' => true, 'save' => true],
    'duvod_zneplatneni' => ['label' => 'duvod_zneplatneni', 'type' => 'text', 'nullable' => true, 'save' => true],

    'poznamka_interni' => ['label' => 'poznamka_interni', 'type' => 'textarea', 'nullable' => true, 'save' => true],

    // Casova razitka z DB - zobrazit, ale neupravovat
    'vytvoreno_dne' => ['label' => 'vytvoreno_dne', 'type' => 'datetime-local', 'readonly' => true, 'nullable' => true, 'save' => false],
    'upraveno_dne' => ['label' => 'upraveno_dne', 'type' => 'datetime-local', 'readonly' => true, 'nullable' => true, 'save' => false],
];

// Vychozi hodnoty (aby insert nepadal na NOT NULL tam, kde chceme mit rozumne defaulty)
$formData = [
    'id' => '',
    'objednavka_id' => '',
    'objednavka_polozka_id' => '',
    'vstupenka_cislo' => '',
    'vstupenka_kod' => '',
    'qr_kod_data' => '',
    'udalost_id' => '',
    'termin_id' => '',
    'udalost_nazev' => '',
    'termin_zacatek' => '',
    'termin_konec' => '',
    'misto_konani' => '',
    'typ_vstupenky' => '',
    'sektor' => '',
    'rada' => '',
    'sedadlo' => '',
    'drzitel_jmeno' => '',
    'mena_kod' => 'CZK',
    'cena_bez_dph' => '0.00',
    'sazba_dph_procent' => '0.00',
    'dph_celkem' => '0.00',
    'cena_celkem' => '0.00',
    'stav_vstupenky' => 'vydana',
    'odeslano_email_dne' => '',
    'doruceno_dne' => '',
    'stazeno_dne' => '',
    'pouzita_dne' => '',
    'scan_pocet' => '0',
    'posledni_scan_dne' => '',
    'posledni_scan_vysledek' => '',
    'scan_misto' => '',
    'scan_zarizeni' => '',
    'scan_obsluha' => '',
    'stornovana_dne' => '',
    'refundovana_dne' => '',
    'zneplatnena_dne' => '',
    'duvod_zneplatneni' => '',
    'poznamka_interni' => '',
    'vytvoreno_dne' => '',
    'upraveno_dne' => '',
];

$zprava = '';
$chyba = '';

// ---------- Nacteni POST do formularovych dat ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $name => $meta) {
        if (!array_key_exists($name, $formData)) {
            $formData[$name] = '';
        }

        $raw = $_POST[$name] ?? '';

        if (($meta['type'] ?? '') === 'datetime-local') {
            // Ulozime zpet do formulare ve formatu datetime-local
            $formData[$name] = trim((string)$raw);
        } else {
            $formData[$name] = is_string($raw) ? trim($raw) : '';
        }
    }
}

$akce = $_POST['akce'] ?? '';

// ---------- Funkce pro nacteni vstupenky podle cisla ----------
function nactiVstupenkuPodleCisla(mysqli $connection, string $vstupenkaCislo): ?array
{
    $sql = "SELECT * FROM vstupenky WHERE vstupenka_cislo = ? LIMIT 1";
    $stmt = $connection->prepare($sql);
    $stmt->bind_param('s', $vstupenkaCislo);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();

    return $row ?: null;
}

// ---------- Akce: NACTI UDAJE ----------
if ($akce === 'load') {
    try {
        $vstupenkaCislo = trim((string)($formData['vstupenka_cislo'] ?? ''));

        if ($vstupenkaCislo === '') {
            $chyba = "Zadej nejdrive vstupenka_cislo.";
        } else {
            $row = nactiVstupenkuPodleCisla($connection, $vstupenkaCislo);

            if (!$row) {
                $chyba = "Vstupenka s cislem '" . h($vstupenkaCislo) . "' nebyla nalezena.";
            } else {
                foreach ($fields as $name => $meta) {
                    $val = $row[$name] ?? '';

                    if (($meta['type'] ?? '') === 'datetime-local') {
                        $formData[$name] = datetimeToInput($val);
                    } else {
                        $formData[$name] = ($val === null ? '' : (string)$val);
                    }
                }

                $zprava = "Udaje byly nacteny.";
            }
        }
    } catch (Throwable $e) {
        $chyba = "Chyba pri nacitani: " . $e->getMessage();
    }
}

// ---------- Akce: ULOZ ZMENY (insert / update) ----------
if ($akce === 'save') {
    try {
        // Zakladni validace
        $requiredFields = [
            'objednavka_id',
            'objednavka_polozka_id',
            'vstupenka_cislo',
            'vstupenka_kod',
            'udalost_nazev',
            'mena_kod',
            'cena_bez_dph',
            'sazba_dph_procent',
            'dph_celkem',
            'cena_celkem',
            'stav_vstupenky',
            'scan_pocet',
        ];

        $missing = [];
        foreach ($requiredFields as $rf) {
            $val = trim((string)($formData[$rf] ?? ''));
            if ($val === '') {
                $missing[] = $rf;
            }
        }

        if (!empty($missing)) {
            $chyba = "Chybi povinna pole: " . implode(', ', $missing);
        } else {
            // Zjisti, zda existuje zaznam:
            // 1) pokud je zadane id, zkusit podle id
            // 2) jinak podle vstupenka_cislo
            $existujiciId = null;
            $idInput = trim((string)($formData['id'] ?? ''));

            if ($idInput !== '') {
                $sql = "SELECT id FROM vstupenky WHERE id = ? LIMIT 1";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param('i', $idInput);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($res) {
                    $existujiciId = (int)$res['id'];
                }
            }

            if ($existujiciId === null) {
                $vstupenkaCislo = trim((string)$formData['vstupenka_cislo']);
                $sql = "SELECT id FROM vstupenky WHERE vstupenka_cislo = ? LIMIT 1";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param('s', $vstupenkaCislo);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($res) {
                    $existujiciId = (int)$res['id'];
                }
            }

            // Priprav data pro DB (jen save=true)
            $saveColumns = [];
            $saveValues = [];

            foreach ($fields as $name => $meta) {
                if (($meta['save'] ?? false) !== true) {
                    continue;
                }

                $value = $formData[$name] ?? '';

                // Prevod datetime-local -> DATETIME
                if (($meta['type'] ?? '') === 'datetime-local') {
                    $dbValue = inputToDatetime((string)$value);
                } else {
                    $dbValue = trim((string)$value);
                    if ($dbValue === '' && ($meta['nullable'] ?? true)) {
                        $dbValue = null;
                    }
                }

                $saveColumns[] = $name;
                $saveValues[] = $dbValue;
            }

            if ($existujiciId !== null) {
                // UPDATE
                $setParts = [];
                foreach ($saveColumns as $col) {
                    $setParts[] = "{$col} = ?";
                }

                $sql = "UPDATE vstupenky SET " . implode(', ', $setParts) . " WHERE id = ?";

                $stmt = $connection->prepare($sql);

                $params = $saveValues;
                $params[] = $existujiciId;

                $types = str_repeat('s', count($saveValues)) . 'i';
                bindParamsDynamic($stmt, $types, $params);

                $stmt->execute();
                $stmt->close();

                $formData['id'] = (string)$existujiciId;
                $zprava = "Zmeny byly ulozeny (UPDATE).";
            } else {
                // INSERT
                $placeholders = implode(', ', array_fill(0, count($saveColumns), '?'));
                $sql = "INSERT INTO vstupenky (" . implode(', ', $saveColumns) . ") VALUES ({$placeholders})";

                $stmt = $connection->prepare($sql);

                $params = $saveValues;
                $types = str_repeat('s', count($saveValues));
                bindParamsDynamic($stmt, $types, $params);

                $stmt->execute();
                $novyId = $connection->insert_id;
                $stmt->close();

                $formData['id'] = (string)$novyId;
                $zprava = "Zaznam neexistoval - byl vytvoren novy radek (INSERT).";
            }

            // Po ulozeni znovu nacti aktualni data z DB (kvuli vytvoreno_dne/upraveno_dne a formatum)
            $row = null;

            if (!empty($formData['id'])) {
                $sql = "SELECT * FROM vstupenky WHERE id = ? LIMIT 1";
                $stmt = $connection->prepare($sql);
                $idForReload = (int)$formData['id'];
                $stmt->bind_param('i', $idForReload);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            if ($row) {
                foreach ($fields as $name => $meta) {
                    $val = $row[$name] ?? '';

                    if (($meta['type'] ?? '') === 'datetime-local') {
                        $formData[$name] = datetimeToInput($val);
                    } else {
                        $formData[$name] = ($val === null ? '' : (string)$val);
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $chyba = "Chyba pri ukladani: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Sprava vstupenky</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f7f7f7;
            color: #222;
        }
        .wrap {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
        }
        h1 {
            margin-top: 0;
            font-size: 24px;
        }
        .msg {
            padding: 10px 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .msg.ok {
            background: #e8f7e8;
            border: 1px solid #b8e0b8;
        }
        .msg.err {
            background: #fdeaea;
            border: 1px solid #efb8b8;
        }

        .grid {
            display: grid;
            grid-template-columns: 280px 1fr auto;
            gap: 8px 12px;
            align-items: start;
        }

        .label {
            font-weight: bold;
            padding-top: 8px;
            word-break: break-word;
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 7px 8px;
            border: 1px solid #bbb;
            border-radius: 5px;
            font: inherit;
            background: #fff;
        }

        textarea {
            min-height: 80px;
            resize: vertical;
        }

        input[readonly],
        textarea[readonly] {
            background: #f0f0f0;
            color: #555;
        }

        .row-spacer {
            grid-column: 1 / -1;
            height: 8px;
            border-bottom: 1px dashed #ddd;
            margin: 6px 0;
        }

        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        button {
            padding: 9px 14px;
            border: 1px solid #888;
            border-radius: 6px;
            cursor: pointer;
            background: #f3f3f3;
            font-weight: bold;
        }
        button:hover {
            background: #e9e9e9;
        }

        .mini {
            padding: 7px 10px;
            font-size: 13px;
            white-space: nowrap;
            margin-left: 8px;
        }

        .note {
            margin-top: 15px;
            font-size: 13px;
            color: #555;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Editace vstupenky</h1>

    <?php if ($zprava !== ''): ?>
        <div class="msg ok"><?php echo h($zprava); ?></div>
    <?php endif; ?>

    <?php if ($chyba !== ''): ?>
        <div class="msg err"><?php echo h($chyba); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="grid">
            <?php foreach ($fields as $name => $meta): ?>
                <div class="label"><?php echo h($meta['label']); ?></div>

                <div>
                    <?php
                    $type = $meta['type'] ?? 'text';
                    $readonly = !empty($meta['readonly']) ? 'readonly' : '';
                    $value = $formData[$name] ?? '';
                    ?>

                    <?php if ($type === 'textarea'): ?>
                        <textarea name="<?php echo h($name); ?>" <?php echo $readonly; ?>><?php echo h((string)$value); ?></textarea>

                    <?php elseif ($type === 'datetime-local'): ?>
                        <input
                            type="datetime-local"
                            name="<?php echo h($name); ?>"
                            value="<?php echo h((string)$value); ?>"
                            <?php echo $readonly; ?>
                        >

                    <?php elseif ($type === 'number-step'): ?>
                        <input
                            type="number"
                            step="<?php echo h((string)($meta['step'] ?? '0.01')); ?>"
                            name="<?php echo h($name); ?>"
                            value="<?php echo h((string)$value); ?>"
                            <?php echo $readonly; ?>
                        >

                    <?php else: ?>
                        <input
                            type="<?php echo ($type === 'number') ? 'number' : 'text'; ?>"
                            name="<?php echo h($name); ?>"
                            value="<?php echo h((string)$value); ?>"
                            <?php echo $readonly; ?>
                        >
                    <?php endif; ?>
                </div>

                <div>
                    <?php if ($name === 'vstupenka_cislo'): ?>
                        <button type="submit" class="mini" name="akce" value="load">Načti údaje</button>
                    <?php endif; ?>
                </div>

                <?php if (in_array($name, ['objednavka_polozka_id', 'qr_kod_data', 'cena_celkem', 'upraveno_dne'], true)): ?>
                    <div class="row-spacer"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button type="submit" name="akce" value="save">Ulož změny</button>
        </div>

        <div class="note">
            Pozn.: Pri vytvareni nove vstupenky musis vyplnit povinna pole a zadat existujici
            <strong>objednavka_id</strong> a <strong>objednavka_polozka_id</strong> (kvuli cizim klicum).
        </div>
    </form>
</div>
</body>
</html>

