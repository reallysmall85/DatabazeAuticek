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

// 4) Zapojíme PhpSpreadsheet (Composer autoload)
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// (volitelné) pro větší soubory
// ini_set('memory_limit', '512M');
// set_time_limit(120);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Import tabulky do databáze</title>
    <style>
        .header { margin-bottom: 15px; }
        .header span { font-weight: bold; }
        form { margin-top: 20px; margin-bottom: 20px; }
        input[type="file"] { margin-bottom: 10px; }
        button { padding: 6px 14px; font-size: 14px; }
        .error { color: #a00; }
        .success { color: #080; }

        .tabulka-hlavni {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: white;
            margin-left: auto; 
            margin-right: auto; 
            margin-top: auto;
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
            border-radius: 6px;
            overflow: hidden;
            border: none;
        }
        .tabulka-hlavni th, .tabulka-hlavni td {
            padding: 8px;
            border: none;
            word-wrap: break-word;
        }
        .tabulka-hlaska {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: white;
            margin-left: auto; 
            margin-right: auto; 
            margin-top: 20px;
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
            border-radius: 6px;
            overflow: hidden; 
            border: none;
        }
        .tabulka-hlaska th, .tabulka-hlaska td {
            padding: 8px;
            border: none;
            word-wrap: break-word;
        }
        .tabulka-prihlasen {
            background-color: white;
            margin-left: 5px;; 
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
            border-radius: 6px;
            overflow: hidden;
        }
        .tabulka-prihlasen th, .tabulka-prihlasen td {
            padding: 8px;
            word-wrap: break-word;
            max-width: none;
            border: none;
            white-space: nowrap;
        }
        .tabulka-ikony {
            background-color: white;
            margin-left: 5px;; 
            margin-top: 5px;
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
            border-radius: 6px;
            overflow: hidden;
        }
        .tabulka-ikony th, .tabulka-ikony td {
            padding: 8px;
            word-wrap: break-word;
            max-width: none;
            border: none;
            white-space: nowrap;
        }

        .zaoblene-tlacitko-fialove {
            background-color: darkviolet; 
            color: white; 
            border: 1px solid black;
            border: none;
            padding: 8px 15px; 
            cursor: pointer; 
            box-sizing: border-box;
            border-radius: 6px;
            margin-right: 2px;
            margin-left: 2px;
        }

    </style>
</head>
<body style="background-image: url(pozadi-auticka5.png); background-position: top left; background-repeat: repeat;  background-size: 40%;">

<?php
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
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
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $datumlogu = date('Y-m-d');
    $logFile   = "{$logDir}/log-{$datumlogu}.log";

    $user =
        (isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Neznámý')
        . ' ' .
        (isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : '');

    $time = date('Y-m-d H:i:s');
    $line = "[$time] ($user) $textzaznamu" . PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
?>

<table class="tabulka-ikony">
<tr>
<td>
<div>
<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<a href="Uvodni.php"><img width="50" height="50" src="Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku"></a>
</div>
</td>
</tr>
</table>

<table class="tabulka-hlavni">
<tr><td>
<form action="Auta-import.php" method="post" enctype="multipart/form-data">
    <label for="excelFile">
        <b>VYBER TABULKU PRO IMPORT</b> <br>
        (podporované formáty: XLSX, XLS, CSV, ODS):
    </label>
</td></tr>
<tr><td>
    <input 
        type="file" 
        id="excelFile" 
        name="excelFile" 
        accept=".xlsx,.xls,.csv,.ods" 
        required
    >
</td></tr>
<tr><td>
    <div align="right">
        <button type="submit" class="zaoblene-tlacitko-fialove" name="import" onmouseover="this.style.backgroundColor='purple';" onmouseout="this.style.backgroundColor='darkviolet';">
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

        // 6) Vybereme odpovídající reader (PhpSpreadsheet)
        try {
            switch ($ext) {
                case 'xls':
                    $reader = IOFactory::createReader('Xls');
                    break;
                case 'xlsx':
                    $reader = IOFactory::createReader('Xlsx');
                    break;
                case 'csv':
                    $reader = IOFactory::createReader('Csv');
                    // případně přizpůsob CSV
                    // $reader->setDelimiter(';');
                    // $reader->setEnclosure('"');
                    // $reader->setInputEncoding('UTF-8'); // nebo 'CP1250' dle zdroje
                    break;
                case 'ods':
                    $reader = IOFactory::createReader('Ods');
                    break;
                default:
                    $reader = IOFactory::createReader('Xlsx');
            }
            $reader->setReadDataOnly(true);
        } catch (Throwable $e) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Nepodařilo se vytvořit čtečku pro tento formát: '
                 . htmlspecialchars($e->getMessage())
                 . '</div></td></tr></table>';
            exit();
        }

        // 7) Načteme spreadsheet
        try {
            $excel = $reader->load($tmpPath);
        } catch (Throwable $e) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při načítání souboru: '
                 . htmlspecialchars($e->getMessage())
                 . '</div></td></tr></table>';
            exit();
        }

        $sheet = $excel->getActiveSheet();

        // 8) Načteme hlavičky (1. řádek)
$highestColumn = $sheet->getHighestColumn();
$highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

$headers = [];
$headerColumns = [];          // mapování na skutečné 1-based indexy sloupců v souboru
$cisloSloupceRokIndex = -1;   // index v $headers (0-based)

// projdeme buňky 1. řádku
for ($col = 1; $col <= $highestColumnIndex; $col++) {
    $addr = Coordinate::stringFromColumnIndex($col) . '1';
    $cellValue = trim((string) $sheet->getCell($addr)->getValue());
    if ($cellValue === '') {
        continue;
    }

    // validace názvu
    if (!preg_match('/^[A-Za-z0-9_]+$/', $cellValue)) {
        echo '<table class="tabulka-hlaska"><tr><td><div class="error">Neplatný název sloupce v hlavičce: '
            . htmlspecialchars($cellValue)
            . '. Sloupce mohou obsahovat jen písmena, číslice a podtržítko.</div></td></tr></table>';
        exit();
    }

    if (strcasecmp($cellValue, 'id') === 0) {
        // necháme MySQL, ať id dopočítá sama
        continue;
    }

    // pokud by soubor už obsahoval 'pridano', ignoruj ho (vždy ho přidáme sami na konec)
    if (strcasecmp($cellValue, 'pridano') === 0) {
        continue;
    }

    $headers[] = $cellValue;
    $headerColumns[] = $col;

    if (strcasecmp($cellValue, 'rok') === 0) {
        $cisloSloupceRokIndex = count($headers) - 1;
    }
}

if (count($headers) === 0) {
    echo '<table class="tabulka-hlaska"><tr><td><div class="error">Soubor nemá žádné platné hlavičky v prvním řádku.</div></td></tr></table>';
    exit();
}

// náš technický sloupec přidáme vždy na konec (unikátně)
$headers[] = 'pridano';



$tableName = 'auta';

// 8.5) Ověř, že všechny sloupce z $headers existují v DB
$colsRes = mysqli_query($connection, "SHOW COLUMNS FROM `$tableName`");
if (!$colsRes) {
    echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při čtení schématu tabulky: '
        . htmlspecialchars(mysqli_error($connection)) . '</div></td></tr></table>';
    exit();
}
$dbCols = [];
while ($c = mysqli_fetch_assoc($colsRes)) {
    $dbCols[] = $c['Field'];
}

$missing = array_diff($headers, $dbCols);
if (!empty($missing)) {
    echo '<table class="tabulka-hlaska"><tr><td><div class="error">V tabulce `'
        . htmlspecialchars($tableName)
        . '` chybí sloupce: <b>' . htmlspecialchars(implode(', ', $missing))
        . '</b>. Přidej je do DB nebo uprav hlavičky.</div></td></tr></table>';
    exit();
}

$columnList   = '`' . implode('`,`', $headers) . '`';
$placeholders = implode(',', array_fill(0, count($headers), '?'));

$insertSql = "INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)";
$stmt = mysqli_prepare($connection, $insertSql);

// POZOR: některá PHP/MySQL komba vrátí `false`, některá i `null` při chybě
if (!($stmt instanceof mysqli_stmt)) {
    echo '<table class="tabulka-hlaska"><tr><td><div class="error">'
        . 'Chyba při přípravě SQL: ' . htmlspecialchars(mysqli_error($connection))
        . '<br>Dotaz: <code>' . htmlspecialchars($insertSql) . '</code>'
        . '</div></td></tr></table>';
    exit();
}


// 10) Projdeme datové řádky (od druhého řádku)
$rowCount    = 0;
$highestRow  = $sheet->getHighestRow();
$columnCount = count($headers);          // včetně 'pridano'
$types       = str_repeat('s', $columnCount);
$aktualiRokDvouciferne = (int) date('y');

for ($row = 2; $row <= $highestRow; $row++) {
    $values = [];
    $allBlank = true;

    // Čteme jen reálné sloupce podle mapy $headerColumns; poslední je náš 'pridano'
    for ($i = 0; $i < ($columnCount - 1); $i++) {
        $colNum = $headerColumns[$i]; // 1-based číslo sloupce v souboru
        $addr   = Coordinate::stringFromColumnIndex($colNum) . $row;

        $cell = $sheet->getCell($addr);
        $val  = $cell->getValue();

        if ($val !== null && $val !== '') {
            $allBlank = false;
        }

        // Úprava roku – kontrolujeme index v $headers, ne číslo sloupce
        if ($i === $cisloSloupceRokIndex) {
            switch (true) {
                case is_numeric($val) && $val < 100 && $val <= $aktualiRokDvouciferne:
                    $val = (int)str_pad((string)$val, 2, '0', STR_PAD_LEFT) + 2000;
                    break;
                case is_numeric($val) && $val < 100 && $val > $aktualiRokDvouciferne:
                    $val = (int)str_pad((string)$val, 2, '0', STR_PAD_LEFT) + 1900;
                    break;
                case is_numeric($val) && $val < 1900 && $val >= 100:
                    $val = null;
                    break;
                case is_string($val):
                    $val = null;
                    break;
                default:
                    // ponech
                    break;
            }
        }

        $values[] = $val;
    }

    if ($allBlank) {
        continue; // celý řádek prázdný → přeskočíme
    } else {
        $caspridani = date('Y-m-d H:i:s');
        $values[] = $caspridani; // sloupec 'pridano'
    }

    // Dynamické bind_param – předáváme reference
    $bindParams = [];
    $bindParams[] = &$stmt;   // procedurální varianta vyžaduje stmt jako 1. argument
    $bindParams[] = &$types;  // např. "ssss..."
    for ($i = 0; $i < $columnCount; $i++) {
        $bindParams[] = &$values[$i]; // POZOR: musí být reference
    }

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
             . htmlspecialchars(mysqli_stmt_error($stmt))
             . '</div></td></tr></table>';
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
