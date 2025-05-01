<?php
session_start();

if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

include("Pripojeni/pripojeniDatabaze.php");

// Připojení k databázi
$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="author" content="martin"/>
	<meta name="keywords" content="uvod"/>
	<title>Import</title>
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
</a><br><br>


<form action="import.php" method="post" enctype="multipart/form-data">
  <label>Vyberte Excel (XLSX, CSV, XLS nebo ODS):</label>
  <input type="file" name="excelFile" accept=".xlsx,.csv,.xls,.ods" required>
  <button type="submit" name="import">Importovat soubor</button>
</form>

<?php
//require 'vendor/autoload.php'; // PhpSpreadsheet
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;


if (isset($_POST['import']) && isset($_FILES['excelFile'])) {
    $tmpPath = $_FILES['excelFile']['tmp_name'];


    $ext = strtolower(pathinfo($_FILES['excelFile']['name'], PATHINFO_EXTENSION));

    switch ($ext) {
    case 'xls':
        $reader = IOFactory::createReader('Xls');
        break;
    case 'ods':
        $reader = IOFactory::createReader('Ods');
        break;
    case 'csv':
        $reader = IOFactory::createReader('Csv');
        // případně nastavte delimiter, enclosure atd.
        break;
    default:     // .xlsx
        $reader = IOFactory::createReader('Xlsx');
        break;
    }


    $spreadsheet = $reader->load($_FILES['excelFile']['tmp_name']);
    $sheet       = $spreadsheet->getActiveSheet();



    // 4) Předpokládejme, že první řádek jsou HEADERY:
    $headers = [];
    foreach ($sheet->getRowIterator(1,1)->current()->getCellIterator() as $cell) {
        $headers[] = trim($cell->getValue());
    }

    // 5) Připravíme insert (dynamicky podle počtu sloupců)
    $placeholders = implode(',', array_fill(0, count($headers), '?'));
    $columns      = implode(',', array_map(function($h){ return "`$h`"; }, $headers));
    $stmt = $pdo->prepare("INSERT INTO auta ($columns) VALUES ($placeholders)");

    // 6) Projdeme řádky od druhého dolů
    $rowCount = 0;
    foreach ($sheet->getRowIterator(2) as $row) {
        $values = [];
        foreach ($row->getCellIterator() as $cell) {
            $values[] = $cell->getValue();
        }
        // vložíme do DB
        $stmt->execute($values);
        $rowCount++;
    }

    echo "<p>Importováno řádků: {$rowCount}</p>";
} else {
    echo "<p>Vyberte prosím soubor k importu.</p>";
}
?>


</body>
</html>

