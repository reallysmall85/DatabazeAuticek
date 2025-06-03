<?php
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
    : 'nezname';
$jmeno     = isset($_SESSION['uzivatel']['jmeno']) 
    ? $_SESSION['uzivatel']['jmeno'] 
    : '???';
$prijmeni  = isset($_SESSION['uzivatel']['prijmeni']) 
    ? $_SESSION['uzivatel']['prijmeni'] 
    : '???';

if ($opravneni !== 'admin' && $opravneni !== 'moderator') {
    header("Location: Prihlaseni.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Import tabulky do databáze</title>
    <style>
        /*body { font-family: Arial, sans-serif; margin: 20px; }*/
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
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 'Chyba oprávnění';
    if ($prihlasenOpravneni == "admin" || $prihlasenOpravneni == "moderator"){
         echo "Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>".$prihlasenOpravneni."</span><br>";
    }
    else {
        header("Location: Prihlaseni.php");
    }
   

}
?>

<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<a href="Uvodni.php">
<img width="50" height="50" src="Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku">
</a>
<br>



    <hr>

    <form action="Auta-import.php" method="post" enctype="multipart/form-data">
        <label for="excelFile">
            <b>VYBER TABULKU PRO IMPORT</b> <br>
            (podporované formáty: XLSX, XLS, CSV, ODS): 
        </label><br><br>
        <input 
            type="file" 
            id="excelFile" 
            name="excelFile" 
            accept=".xlsx,.xls,.csv,.ods" 
            required
        ><br>

        <button type="submit" name="import" style="background-color: darkviolet; color: white; border: none; padding: 10px 20px; margin-left: 5px; cursor: pointer; box-sizing: border-box;"
                  onmouseover="this.style.backgroundColor='purple';" onmouseout="this.style.backgroundColor='darkviolet';">Importovat soubor</button>


       
    </form>

<?php
// 4) Zapojíme PHPExcel (uloženo v /Classes/)
require_once __DIR__ . '/Classes/PHPExcel.php';
require_once __DIR__ . '/Classes/PHPExcel/IOFactory.php';

// 5) Po odeslání formuláře
if (isset($_POST['import'])) {
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="error">Chyba při nahrávání souboru. Zkuste to prosím znovu.</div>';
    } else {
        $tmpPath  = $_FILES['excelFile']['tmp_name'];
        $origName = $_FILES['excelFile']['name'];
        $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 6) Vybereme odpovídající reader
        try {
            if ($ext === 'xls') {
                $reader = PHPExcel_IOFactory::createReader('Excel5');
            } elseif ($ext === 'xlsx') {
                $reader = PHPExcel_IOFactory::createReader('Excel2007');
            } elseif ($ext === 'csv') {
                $reader = PHPExcel_IOFactory::createReader('CSV');
                // pokud CSV používá jiný delimiter:
                // $reader->setDelimiter(';');
                // $reader->setEnclosure('"');
            } elseif ($ext === 'ods') {
                // PHPExcel umí ODS díky staršímu readeru „OOCalc“
                $reader = PHPExcel_IOFactory::createReader('OOCalc');
            } else {
                // implicitně zkusíme Excel2007 (pro .xlsx i .XLSX)
                $reader = PHPExcel_IOFactory::createReader('Excel2007');
            }
        } catch (Exception $e) {
            echo '<div class="error">Nepodařilo se vytvořit čtečku pro tento formát: '
                 . htmlspecialchars($e->getMessage())
                 . '</div>';
            exit();
        }

        // 7) Načteme spreadsheet
        try {
            $excel = $reader->load($tmpPath);
        } catch (Exception $e) {
            echo '<div class="error">Chyba při načítání souboru: '
                 . htmlspecialchars($e->getMessage())
                 . '</div>';
            exit();
        }

        $sheet = $excel->getActiveSheet();

        // 8) Načteme hlavičky (1. řádek)
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
        $headers = [];
        for ($col = 0; $col < $highestColumnIndex; $col++) {
            $cellValue = trim((string) $sheet->getCellByColumnAndRow($col, 1)->getValue());
            if ($cellValue !== '') {
                // Validujeme, aby sloupce byly jen alfanumerické + podtržítko
                if (!preg_match('/^[A-Za-z0-9_]+$/', $cellValue)) {
                    echo '<div class="error">Neplatný název sloupce v hlavičce: '
                         . htmlspecialchars($cellValue)
                         . '. Sloupce mohou obsahovat jen písmena, číslice a podtržítko.</div>';
                    exit();
                }
                $headers[] = $cellValue;
            }
        }

        if (count($headers) === 0) {
            echo '<div class="error">Soubor nemá žádné platné hlavičky v prvním řádku.</div>';
            exit();
        }

        // 9) Připravíme SQL INSERT
        $columnList   = '`' . implode('`,`', $headers) . '`';
        $placeholders = implode(',', array_fill(0, count($headers), '?'));
        $tableName    = 'auta'; // název tabulky v DB

        $insertSql = "INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)";
        $stmt = mysqli_prepare($connection, $insertSql);
        if ($stmt === false) {
            echo '<div class="error">Chyba při přípravě SQL dotazu: '
                 . htmlspecialchars(mysqli_error($connection))
                 . '</div>';
            exit();
        }

        // 10) Projdeme datové řádky (od druhého řádku)
        $rowCount    = 0;
        $highestRow  = $sheet->getHighestRow();
        $columnCount = count($headers);

        // Pro mysqli bind_param potřebujeme řetězec typů – všechny jako "s" (string)
        $types = str_repeat('s', $columnCount);

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];
            $allBlank = true;
            for ($col = 0; $col < $columnCount; $col++) {
                $val = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($val !== null && $val !== '') {
                    $allBlank = false;
                }
                $values[] = $val;
            }
            if ($allBlank) {
                // celý řádek prázdný → přeskočíme
                continue;
            }

            // Dynamické bind_param – musíme předat reference v poli
            $bindParams = [];
            $bindParams[] = & $types;
            for ($i = 0; $i < $columnCount; $i++) {
                $bindParams[] = & $values[$i];
            }
            call_user_func_array(
                array($stmt, 'bind_param'),
                $bindParams
            );

            // Spustíme INSERT
            if (!mysqli_stmt_execute($stmt)) {
                echo '<div class="error">Chyba při vkládání na řádku ' . $row . ': '
                     . htmlspecialchars(mysqli_stmt_error($stmt))
                     . '</div>';
                exit();
            }
            $rowCount++;
        }

        mysqli_stmt_close($stmt);
        echo '<div class="success">Importováno řádků: <strong>' . $rowCount . '</strong>.</div>';
    }
}
?>

</body>
</html>
