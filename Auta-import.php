<?php
session_start();

// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

// 2) Připojení k databázi (mysqli)
include __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Nepodařilo se připojit k databázi: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

// 3) Kontrola oprávnění
$opravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
$jmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : '???';
$prijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : '???';

if ($opravneni > 2 ) {
    header("Location: Prihlaseni.php");
    exit();
}

// 4) PhpSpreadsheet
require_once __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// (volitelné) pro větší soubory
// ini_set('memory_limit', '512M');
// set_time_limit(120);

/** ================= Pomocné funkce ================= */

/** Normalizuje název sloupce: diakritika pryč, malá písmena, vše mimo [a-z0-9_] -> '_' */
function normalizeHeaderName(string $s): string {
    $s = trim($s);
    if ($s === '') return '';

    $s = mb_strtolower($s, 'UTF-8');

    // hrubé mapování běžné diakritiky (CZ/SK + něco navíc)
    $from = ['á','à','â','ä','ã','å','ă','ą','Á','Ä','Â','À','Ã','Å','Ă','Ą',
             'č','ć','Č','Ć',
             'ď','đ','Ď','Đ',
             'é','ě','ë','ê','è','ę','É','Ě','Ë','Ê','È','Ę',
             'í','ï','î','ì','Í','Ï','Î','Ì',
             'ľ','ĺ','ł','Ľ','Ĺ','Ł',
             'ń','ň','ñ','Ń','Ň','Ñ',
             'ó','ö','ô','ò','õ','ő','Ó','Ö','Ô','Ò','Õ','Ő',
             'ř','ŕ','Ř','Ŕ',
             'š','ś','Š','Ś',
             'ť','ţ','Ť','Ţ',
             'ú','ů','ü','û','ù','ű','Ú','Ů','Ü','Û','Ù','Ű',
             'ý','ÿ','Ý','Ÿ',
             'ž','ź','ż','Ž','Ź','Ż'];
    $to   = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
             'c','c','c','c',
             'd','d','d','d',
             'e','e','e','e','e','e','e','e','e','e','e','e',
             'i','i','i','i','i','i','i','i',
             'l','l','l','l','l','l',
             'n','n','n','n','n','n',
             'o','o','o','o','o','o','o','o','o','o','o','o',
             'r','r','r','r',
             's','s','s','s',
             't','t','t','t',
             'u','u','u','u','u','u','u','u','u','u','u','u',
             'y','y','y','y',
             'z','z','z','z','z','z'];
    $s = str_replace($from, $to, $s);

    // odstraň případné kombinační znaky
    $s = preg_replace('/\p{M}+/u', '', $s);

    // vše mimo [a-z0-9_] -> _
    $s = preg_replace('/[^a-z0-9_]+/u', '_', $s);

    // zredukuj více podtržítek a ořež okraje
    $s = preg_replace('/_+/', '_', $s);
    $s = trim($s, '_');

    return $s;
}

/** Rozřeže složené barvy podle kořenů a vrátí až 5 pojmenovaných barev v pořadí výskytu. */
function extrahujBarvyZTextu(?string $text): array {
    if ($text === null) return [];
    $s = trim(mb_strtolower($text, 'UTF-8'));
    if ($s === '') return [];

    // normalizuj oddělovače
    $s = str_replace(['/', '\\', ',', ';', '+', '|'], ' ', $s);

    $map = [
        'azur'   => 'azurová',
        'béž'    => 'béžová',
        'bíl'    => 'bílá',
        'bronz'  => 'bronzová',
        'carbon' => 'carbon', 'carb'=>'carbon','karb'=>'carbon',
        'čern'   => 'černá',
        'červ'   => 'červená',
        'fial'   => 'fialová',
        'hněd'   => 'hnědá',
        'chrom'  => 'chromová',
        'krém'   => 'krémová',
        'lesk'   => 'lesklá',
        'mat'    => 'matná',
        'mix'    => 'mix barev', 'více'=>'mix barev','duh'=>'mix barev',
        'mod'    => 'modrá',
        'oranž'  => 'oranžová',
        'růž'    => 'růžová',
        'stří'   => 'stříbrná',
        'svět'   => 'světlá',
        'šed'    => 'šedá',
        'tmav'   => 'tmavá',
        'tyrky'  => 'tyrkysová',
        'zel'    => 'zelená',
        'zlat'   => 'zlatá',
        'žlu'    => 'žlutá',
    ];

    foreach (['mix','více','duh'] as $mx) {
        if (mb_strpos($s, $mx) !== false) return ['mix barev'];
    }

    $hits = [];
    foreach ($map as $root => $full) {
        if (@preg_match_all('/' . preg_quote($root, '/') . '/u', $s, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $match) {
                $hits[] = ['pos' => $match[1], 'full' => $full];
            }
        }
    }
    usort($hits, fn($a,$b) => $a['pos'] <=> $b['pos']);

    $out = [];
    foreach ($hits as $h) {
        if (!in_array($h['full'], $out, true)) {
            $out[] = $h['full'];
            if (count($out) >= 5) break;
        }
    }
    return $out;
}

/** Rozdělí jména jezdců podle - , ; : / \ _ (mezery okolo se ignorují), vrátí max 3 položky. */
function extrahujJezdceZTextu(?string $text): array {
    if ($text === null) return [];
    $s = trim((string)$text);
    if ($s === '') return [];
    // oddělovače: - , ; : / \ _
    $parts = preg_split('/\s*(?:\-|,|;|:|\/|\\\\|_)\s*/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out[] = $p;
        if (count($out) >= 3) break; // max 3
    }
    return $out;
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mobile-styly.css" media="(max-width: 1199px)">
	<link rel="stylesheet" href="desktop-styly.css" media="(min-width: 1200px)">
    <title>Import tabulky do databáze</title>
    <style>
        .header { margin-bottom: 15px; }
        .header span { font-weight: bold; }
        form { margin-top: 20px; margin-bottom: 20px; }
        input[type="file"] { margin-bottom: 10px; }
        button { padding: 6px 14px; font-size: 14px; }
        .error { color: #a00; }
        .success { color: #080; }
    </style>
</head>
<body>

<?php
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = $_SESSION['uzivatel']['id']        ?? 1234;
    $prihlasenJmeno     = $_SESSION['uzivatel']['jmeno']     ?? 'Jméno';
    $prihlasenPrijmeni  = $_SESSION['uzivatel']['prijmeni']  ?? 'Příjmení';
    $prihlasenOpravneni = $_SESSION['uzivatel']['opravneni'] ?? 4;

    if ($prihlasenOpravneni <= 2 ){
        echo "<table class=\"tabulka-prihlasen\"><tr><td><div>Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>";
        switch ($prihlasenOpravneni){
            case 1:  echo "admin"; break;
            case 2:  echo "moderator"; break;
            case 3:  echo "uživatel"; break;
            case 4:  echo "veřejnost"; break;    
            default: echo "úrovně č.: " .$prihlasenOpravneni; break;
        }
        echo "</span></div></td></tr></table>";
    } else {
        header("Location: Prihlaseni.php");
    }
}

function zapisDoLogu($textzaznamu) {
    $logDir = __DIR__ . '/Logy';
    if (!is_dir($logDir)) mkdir($logDir, 0777, true);
    $datumlogu = date('Y-m-d');
    $logFile   = "{$logDir}/log-{$datumlogu}.log";

    $user = ($_SESSION['uzivatel']['jmeno'] ?? 'Neznámý').' '.($_SESSION['uzivatel']['prijmeni'] ?? '');
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] ($user) $textzaznamu".PHP_EOL, FILE_APPEND | LOCK_EX);
}
?>

<table class="tabulka-ikony">
<tr><td><div>
<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" title="Odhlásit se"></a>
<a href="Uvodni.php"><img width="50" height="50" src="Home.png" title="Zpět na úvodní stránku"></a>
</div></td></tr>
</table>

<table class="tabulka-import">
<tr><td>
<form action="Auta-import.php" method="post" enctype="multipart/form-data">
    <label for="excelFile"><b>VYBER TABULKU PRO IMPORT</b><br>(XLSX, XLS, CSV, ODS)</label>
</td></tr>
<tr><td>
    <input type="file" id="excelFile" name="excelFile" accept=".xlsx,.xls,.csv,.ods" required>
</td></tr>
<tr><td>
    <div align="right">
        <button type="submit" class="zaoblene-tlacitko-fialove" name="import"
            onmouseover="this.style.backgroundColor='purple';"
            onmouseout="this.style.backgroundColor='darkviolet';">
            Importovat soubor
        </button>
    </div>
</td></tr>
</form>
</table>

<?php
// 5) Po odeslání formuláře
if (isset($_POST['import'])) {
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při nahrávání souboru. Zkuste to prosím znovu.</div></td></tr></table>';
    } else {

        zapisDoLogu("Zapsáno do databáze auta z importovaného souboru:");

        $tmpPath  = $_FILES['excelFile']['tmp_name'];
        $origName = $_FILES['excelFile']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 6) Reader
        try {
            switch ($ext) {
                case 'xls':  $reader = IOFactory::createReader('Xls');  break;
                case 'xlsx': $reader = IOFactory::createReader('Xlsx'); break;
                case 'csv':  $reader = IOFactory::createReader('Csv');  break;
                case 'ods':  $reader = IOFactory::createReader('Ods');  break;
                default:     $reader = IOFactory::createReader('Xlsx');
            }
            $reader->setReadDataOnly(true);
        } catch (Throwable $e) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Nepodařilo se vytvořit čtečku: '
                 . htmlspecialchars($e->getMessage()) . '</div></td></tr></table>';
            exit();
        }

        // 7) Načtení
        try { $excel = $reader->load($tmpPath); }
        catch (Throwable $e) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při načítání souboru: '
                 . htmlspecialchars($e->getMessage()) . '</div></td></tr></table>';
            exit();
        }

        $sheet = $excel->getActiveSheet();

        // 8) Hlavičky (1. řádek) – normalizace + speciální "barva/barvy" a "jezdec/jezdci"
        $highestColumn      = $sheet->getHighestColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        $headers               = []; // normalizovaná jména (pro SQL)
        $headerColumns         = []; // 1-based indexy ve vstupu (0 = odvozené/konstantní)
        $cisloSloupceRokIndex  = -1;
        $barvaSourceFileCol    = 0;  // 1-based index "barva/barvy" ve vstupu, 0 = není
        $jezdecSourceFileCol   = 0;  // 1-based index "jezdec/jezdci" ve vstupu, 0 = není

        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $addr = Coordinate::stringFromColumnIndex($col) . '1';
            $raw  = trim((string) $sheet->getCell($addr)->getValue());
            if ($raw === '') continue;

            // 1) normalizace (např. "Číslo:" -> "cislo")
            $name = normalizeHeaderName($raw);
            if ($name === '') {
                echo '<table class="tabulka-hlaska"><tr><td><div class="error">Neplatný název sloupce v hlavičce: '
                    . htmlspecialchars($raw)
                    . '. Po normalizaci je prázdný. Uprav prosím hlavičku.</div></td></tr></table>';
                exit();
            }

            // 2) "barva"/"barvy" – jen zapamatuj zdroj, NEpřidávej do $headers
            if ($name === 'barva' || $name === 'barvy') {
                $barvaSourceFileCol = $col;
                continue;
            }
            // 2b) "jezdec"/"jezdci" – jen zapamatuj zdroj, NEpřidávej do $headers
            if ($name === 'jezdec' || $name === 'jezdci') {
                $jezdecSourceFileCol = $col;
                continue;
            }

            // 3) přeskoč technické
            if ($name === 'id' || $name === 'pridano') continue;

            // 4) duplicitní po normalizaci
            if (in_array($name, $headers, true)) {
                echo '<table class="tabulka-hlaska"><tr><td><div class="error">Duplicitní název sloupce (ignoruje se diakritika): <b>'
                    . htmlspecialchars($name) . '</b>. Uprav prosím hlavičku souboru.</div></td></tr></table>';
                exit();
            }

            $headers[]       = $name;
            $headerColumns[] = $col;

            if ($name === 'rok') {
                $cisloSloupceRokIndex = count($headers) - 1;
            }
        }

        if (count($headers) === 0 && $barvaSourceFileCol === 0 && $jezdecSourceFileCol === 0) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Soubor nemá žádné použitelné hlavičky v prvním řádku.</div></td></tr></table>';
            exit();
        }

        // === BARVY: pokud je ve vstupu "barva/barvy", doplň odvozené barva1..barva5 (nejsou v excelu)
        $derivedBarvaMap = []; // index v $headers -> pořadí 1..5
        if ($barvaSourceFileCol > 0) {
            for ($n = 1; $n <= 5; $n++) {
                $nm = 'barva' . $n;
                if (!in_array($nm, $headers, true)) {
                    $headers[]       = $nm;
                    $headerColumns[] = 0; // 0 = odvozené (není v excelu)
                    $derivedBarvaMap[count($headers)-1] = $n;
                }
            }
        }

        // === JEZDCI: pokud je ve vstupu "jezdec/jezdci", doplň odvozené jezdec1..jezdec3
        $derivedJezdecMap = []; // index v $headers -> pořadí 1..3
        if ($jezdecSourceFileCol > 0) {
            for ($n = 1; $n <= 3; $n++) {
                $nm = 'jezdec' . $n;
                if (!in_array($nm, $headers, true)) {
                    $headers[]       = $nm;
                    $headerColumns[] = 0; // 0 = odvozené
                    $derivedJezdecMap[count($headers)-1] = $n;
                }
            }
        }

        $tableName = 'auta';

        // 8.5) Kontrola schématu DB + načtení typů sloupců
        $colsRes = mysqli_query($connection, "SHOW COLUMNS FROM `$tableName`");
        if (!$colsRes) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při čtení schématu tabulky: '
                . htmlspecialchars(mysqli_error($connection)) . '</div></td></tr></table>';
            exit();
        }
        $dbCols     = [];
        $dbColTypes = []; // jméno => typ (varchar(255), int(11), datetime, ...)
        while ($c = mysqli_fetch_assoc($colsRes)) {
            $dbCols[] = $c['Field'];
            $dbColTypes[$c['Field']] = strtolower($c['Type']);
        }

        // === DOPLNĚNÍ CHYBĚJÍCÍCH TEXTOVÝCH SLOUPCŮ Z DB ===
        $fillEmptyTextIdx = []; // index v $headers => true (sloupec má být vždy '')
        foreach ($dbCols as $colname) {
            if ($colname === 'id' || $colname === 'pridano') continue; // technické
            if (in_array($colname, $headers, true)) continue;          // už je tam
            $t = $dbColTypes[$colname] ?? '';
            if (preg_match('/char|text|enum|set|json/i', $t)) {
                $headers[]       = $colname;
                $headerColumns[] = 0;         // 0 = odvozený/konstantní
                $fillEmptyTextIdx[count($headers)-1] = true; // nastavíme '' v každém řádku
            }
        }

        // Teprve teď zkontroluj, že vše co chceme vkládat, v DB existuje
        $missing = array_diff($headers, $dbCols);
        if (!empty($missing)) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">V tabulce `'
                . htmlspecialchars($tableName)
                . '` chybí sloupce: <b>' . htmlspecialchars(implode(', ', $missing))
                . '</b>. Přidej je do DB nebo uprav hlavičky.</div></td></tr></table>';
            exit();
        }

        // === MAPA TEXTOVÝCH SLOUPCŮ (pro převod NULL -> '') ===
        $isTextCol = []; // index v $headers => bool
        foreach ($headers as $idx => $hname) {
            $t = $dbColTypes[$hname] ?? '';
            $isTextCol[$idx] = (bool) preg_match('/char|text|enum|set|json/i', $t);
        }

        // technický sloupec vždy na konec
        $headers[] = 'pridano';

        $columnList   = '`' . implode('`,`', $headers) . '`';
        $placeholders = implode(',', array_fill(0, count($headers), '?'));

        $insertSql = "INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)";
        $stmt = mysqli_prepare($connection, $insertSql);
        if (!($stmt instanceof mysqli_stmt)) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">'
                . 'Chyba při přípravě SQL: ' . htmlspecialchars(mysqli_error($connection))
                . '<br>Dotaz: <code>' . htmlspecialchars($insertSql) . '</code>'
                . '</div></td></tr></table>';
            exit();
        }

        // 10) Projdeme datové řádky
        $rowCount    = 0;
        $highestRow  = $sheet->getHighestRow();
        $columnCount = count($headers);  // včetně 'pridano' a odvozených sloupců
        $types       = str_repeat('s', $columnCount);
        $aktualiRokDvouciferne = (int) date('y');

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];
            $allBlank = true;

            // Předpočítání barev pro řádek
            $parsedBarvy = [];
            if ($barvaSourceFileCol > 0) {
                $addrBarva = Coordinate::stringFromColumnIndex($barvaSourceFileCol) . $row;
                $valBarva  = $sheet->getCell($addrBarva)->getValue();
                $parsedBarvy = extrahujBarvyZTextu((string)$valBarva);
                if (!empty(array_filter($parsedBarvy, fn($v)=>$v!==null && $v!==''))) {
                    $allBlank = false;
                }
            }

            // Předpočítání jezdců pro řádek
            $parsedJezdci = [];
            if ($jezdecSourceFileCol > 0) {
                $addrJezdec = Coordinate::stringFromColumnIndex($jezdecSourceFileCol) . $row;
                $valJezdec  = $sheet->getCell($addrJezdec)->getValue();
                $parsedJezdci = extrahujJezdceZTextu((string)$valJezdec);
                if (!empty(array_filter($parsedJezdci, fn($v)=>$v!==null && $v!==''))) {
                    $allBlank = false;
                }
            }

            // Čtení / odvození hodnot
            for ($i = 0; $i < ($columnCount - 1); $i++) { // -1 kvůli 'pridano' přidávanému níže
                $val = null;

                if (isset($derivedBarvaMap[$i]) && $headerColumns[$i] === 0) {
                    // odvozené barva1..barva5
                    $n   = $derivedBarvaMap[$i]; // 1..5
                    $val = $parsedBarvy[$n - 1] ?? '';
                    if ($val !== '') $allBlank = false;

                } elseif (isset($derivedJezdecMap[$i]) && $headerColumns[$i] === 0) {
                    // odvozené jezdec1..jezdec3
                    $n   = $derivedJezdecMap[$i]; // 1..3
                    $val = $parsedJezdci[$n - 1] ?? '';
                    if ($val !== '') $allBlank = false;

                } elseif (!empty($fillEmptyTextIdx[$i]) && $headerColumns[$i] === 0) {
                    // chybějící textový sloupec z DB → vždy prázdný string
                    $val = '';

                } else {
                    // běžná buňka z excelu
                    $colNum = $headerColumns[$i]; // 1-based index ve vstupu
                    $addr   = Coordinate::stringFromColumnIndex($colNum) . $row;
                    $cell   = $sheet->getCell($addr);
                    $val    = $cell->getValue();

                    // Úprava roku (podle indexu v $headers)
                    if ($i === $cisloSloupceRokIndex) {
                        switch (true) {
                            case is_numeric($val) && $val < 100 && $val <= $aktualiRokDvouciferne:
                                $val = (int)str_pad((string)$val, 2, '0', STR_PAD_LEFT) + 2000; break;
                            case is_numeric($val) && $val < 100 && $val >  $aktualiRokDvouciferne:
                                $val = (int)str_pad((string)$val, 2, '0', STR_PAD_LEFT) + 1900; break;
                            case is_numeric($val) && $val < 1900 && $val >= 100:
                                $val = null; break;
                            case is_string($val):
                                $val = null; break;
                            default:
                                break;
                        }
                    }

                    // Textovým sloupcům dej místo NULL prázdný string
                    if ($val === null && !empty($isTextCol[$i])) {
                        $val = '';
                    }

                    if ($val !== null && $val !== '') {
                        $allBlank = false;
                    }
                }

                $values[] = $val;
            }

            if ($allBlank) {
                continue; // prázdný řádek
            } else {
                $values[] = date('Y-m-d H:i:s'); // 'pridano'
            }

            // bind + insert
            $bindParams = [];
            $bindParams[] = &$stmt;
            $bindParams[] = &$types;
            for ($i = 0; $i < $columnCount; $i++) $bindParams[] = &$values[$i];

            zapisDoLogu(implode(', ', array_map(function($v){
                if ($v === null) return 'NULL';
                if (is_scalar($v)) return (string)$v;
                return json_encode($v, JSON_UNESCAPED_UNICODE);
            }, $values)));

            if (!call_user_func_array('mysqli_stmt_bind_param', $bindParams)) {
                echo '<table class="tabulka-hlaska"><tr><td><div class="error">Nepodařilo se provést bind_param.</div></td></tr></table>';
                exit();
            }
            if (!mysqli_stmt_execute($stmt)) {
                echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při vkládání na řádku ' . $row . ': '
                     . htmlspecialchars(mysqli_stmt_error($stmt)) . '</div></td></tr></table>';
                exit();
            }
            $rowCount++;
        }

        mysqli_stmt_close($stmt);
        echo '<table class="tabulka-hlaska"><tr><td><div class="success">Importováno řádků: <strong>' . $rowCount . '</strong>.</div></td></tr></table>';
    }
}
?>

</body>
</html>
