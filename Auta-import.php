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
    : 4;
$jmeno     = isset($_SESSION['uzivatel']['jmeno']) 
    ? $_SESSION['uzivatel']['jmeno'] 
    : '???';
$prijmeni  = isset($_SESSION['uzivatel']['prijmeni']) 
    ? $_SESSION['uzivatel']['prijmeni'] 
    : '???';

if ($opravneni > 2 ) {
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
        .tabulka-hlavni th,
        .tabulka-hlavni td {
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
        .tabulka-hlaska th,
        .tabulka-hlaska td {
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
        .tabulka-prihlasen th,
		.tabulka-prihlasen td {
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
		.tabulka-ikony th, 
		.tabulka-ikony td {
			padding: 8px;
            word-wrap: break-word;
            max-width: none;
			border: none;
            white-space: nowrap;
		}


    </style>
</head>
<body style="background-image: url(pozadi-auticka3.png); background-position: top left; background-repeat: repeat;  background-size: 40%;">


<?php

    if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
    if ($prihlasenOpravneni <= 2 ){
        echo "<table class=\"tabulka-prihlasen\"><tr><td><div>Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>";
        switch ($prihlasenOpravneni){
            case 1:
                echo "admin";
                break;
            case 2:
                echo "moderator";
                break;
            case 3:
                echo "uživatel";
                break;
            case 4:
                echo "veřejnost";
                break;    
            default:
                echo "úrovně č.: " .$prihlasenOpravneni;
                break;

        }
        echo "</span></div></td></tr></table>";

    }
    else {
        header("Location: Prihlaseni.php");
    }
   

}

function zapisDoLogu($textzaznamu) {
    // složka pro logy
    $logDir = __DIR__ . '/Logy';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);

    }

    $datumlogu = date('Y-m-d');
    $logFile   = "{$logDir}/log-{$datumlogu}.log";

    // připravíme řádek
    $user = 
    (isset($_SESSION['uzivatel']['jmeno'])
        ? $_SESSION['uzivatel']['jmeno']
        : 'Neznámý')
  . ' '
  . (isset($_SESSION['uzivatel']['prijmeni'])
        ? $_SESSION['uzivatel']['prijmeni']
        : '');

    $time = date('Y-m-d H:i:s');
    $line = "[$time] ($user) $textzaznamu" . PHP_EOL;

    // přidáme na konec souboru (vytvoří, pokud neexistuje) a uzamkneme
    file_put_contents(
        $logFile,
        $line,
        FILE_APPEND | LOCK_EX
    );
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
        <tr>
            <td>

    <form action="Auta-import.php" method="post" enctype="multipart/form-data">
        <label for="excelFile">
            <b>VYBER TABULKU PRO IMPORT</b> <br>
            (podporované formáty: XLSX, XLS, CSV, ODS): 
        </label>
        
            </td>
        </tr>
        <tr>
            <td>

        <input 
            type="file" 
            id="excelFile" 
            name="excelFile" 
            accept=".xlsx,.xls,.csv,.ods" 
            required
        >
            </td>
        </tr>
        <tr>
            <td>

            <div align="right"><button type="submit" name="import" style="background-color: darkviolet; color: white; border: none; padding: 10px 20px; margin-left: 5px; cursor: pointer; box-sizing: border-box;"
                  onmouseover="this.style.backgroundColor='purple';" onmouseout="this.style.backgroundColor='darkviolet';">Importovat soubor</button></div>


       
    </form>
    </td>
        </tr>
            </table>

<?php
// 4) Zapojíme PHPExcel (uloženo v /Classes/)
require_once __DIR__ . '/Classes/PHPExcel.php';
require_once __DIR__ . '/Classes/PHPExcel/IOFactory.php';

// 5) Po odeslání formuláře
if (isset($_POST['import'])) {
    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při nahrávání souboru. Zkuste to prosím znovu.</div></td></tr></table>';
    } else {

        zapisDoLogu("Zapsáno do databáze auta z importovaného souboru:");

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
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Nepodařilo se vytvořit čtečku pro tento formát: '
                 . htmlspecialchars($e->getMessage())
                 . '</div></td></tr></table>';
            exit();
        }

        // 7) Načteme spreadsheet
        try {
            $excel = $reader->load($tmpPath);
        } catch (Exception $e) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při načítání souboru: '
                 . htmlspecialchars($e->getMessage())
                 . '</div></td></tr></table>';
            exit();
        }

        $sheet = $excel->getActiveSheet();

        // 8) Načteme hlavičky (1. řádek)
        $highestColumn = $sheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
        $headers = [];
        $cisloSloupceRok = 0;
        for ($col = 0; $col < $highestColumnIndex; $col++) {
            $cellValue = trim((string) $sheet->getCellByColumnAndRow($col, 1)->getValue());
            if ($cellValue !== '') {
                // Validujeme, aby sloupce byly jen alfanumerické + podtržítko
                if (!preg_match('/^[A-Za-z0-9_]+$/', $cellValue)) {
                    echo '<table class="tabulka-hlaska"><tr><td><div class="error">Neplatný název sloupce v hlavičce: '
                         . htmlspecialchars($cellValue)
                         . '. Sloupce mohou obsahovat jen písmena, číslice a podtržítko.</div></td></tr></table>';
                    exit();
                }
                $headers[] = $cellValue;
            }
            if ($cellValue == "rok"){
                $cisloSloupceRok = $col;
            }
        }

        if (count($headers) === 0) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Soubor nemá žádné platné hlavičky v prvním řádku.</div></td></tr></table>';
            exit();
        }

        // 9) Připravíme SQL INSERT
        $columnList   = '`' . implode('`,`', $headers) . '`';
        $placeholders = implode(',', array_fill(0, count($headers), '?'));
        $tableName    = 'auta'; // název tabulky v DB

        $insertSql = "INSERT INTO `$tableName` ($columnList) VALUES ($placeholders)";
        $stmt = mysqli_prepare($connection, $insertSql);
        if ($stmt === false) {
            echo '<table class="tabulka-hlaska"><tr><td><div class="error">Chyba při přípravě SQL dotazu: '
                 . htmlspecialchars(mysqli_error($connection))
                 . '</div></td></tr></table>';
            exit();
        }

        // 10) Projdeme datové řádky (od druhého řádku)
        $rowCount    = 0;
        $highestRow  = $sheet->getHighestRow();
        $columnCount = count($headers);

        // Pro mysqli bind_param potřebujeme řetězec typů – všechny jako "s" (string)
        $types = str_repeat('s', $columnCount);

        $aktualiRokDvouciferne = date('y');
    

        for ($row = 2; $row <= $highestRow; $row++) {
            $values = [];
            $allBlank = true;
            for ($col = 0; $col < $columnCount; $col++) {
                $val = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                if ($val !== null && $val !== '') {
                    $allBlank = false;
                }
                if ($col == $cisloSloupceRok){
                    switch (true){
                        case is_numeric($val) && $val < 100 && $val <= $aktualiRokDvouciferne:
                            $val = str_pad($val, 2, '0', STR_PAD_LEFT) + 2000;
                            break;
                        case is_numeric($val) && $val < 100 && $val > $aktualiRokDvouciferne:
                            $val = str_pad($val, 2, '0', STR_PAD_LEFT) + 1900;
                            break;
                        case is_numeric($val) && $val < 1900 && $val >= 100:
                            $val = null;
                            break;
                        case is_string($val):
                            $val = null;
                            break;
                        default:
                            break;
                    }

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

   


            zapisDoLogu(implode(', ', $bindParams));


            call_user_func_array(
                array($stmt, 'bind_param'),
                $bindParams
            );

            // Spustíme INSERT
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
