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

if ($opravneni > 2) {
    header("Location: Prihlaseni.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="desktop-styly.css?v=<?php echo filemtime(__DIR__ . '/desktop-styly.css'); ?>">
    
    <title>Pomocne databaze</title>
    

</head>


<body>

<?php




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

<div class="horni-fixni-panel" id="horniFixniPanel">
    <div class="horni-segment horni-segment-navigace">
        <a href="Uvodni.php"><img width="50" height="50" src="Ikony/Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku"></a>
        <a href="Prihlaseni.php" title="Odhlásit se">
            <img width="50" height="50" src="Ikony/Logout.png" alt="Odhlásit se">
        </a>
    </div>

</div>




<?php


ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $connection);


function ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $connection){


echo "<form method=\"post\" action=\"Pomocne-databaze.php name=\"formularpomocnedatabaze\">";

	

	$hodnotaHledaniFirmy = mysqli_query($connection, "SELECT * FROM autafirmy WHERE id IS NOT NULL ORDER BY firma");
	if (!$hodnotaHledaniFirmy) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}
	$hodnotaHledaniZavody = mysqli_query($connection, "SELECT * FROM autazavody WHERE id IS NOT NULL ORDER BY zavod");
    if (!$hodnotaHledaniZavody) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}
	$hodnotaHledaniSerie = mysqli_query($connection, "SELECT * FROM autaserie WHERE id IS NOT NULL ORDER BY serie");
    if (!$hodnotaHledaniSerie) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}
	$hodnotaHledaniBarvy = mysqli_query($connection, "SELECT * FROM autabarvy WHERE id IS NOT NULL ORDER BY barva");
    if (!$hodnotaHledaniBarvy) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}




echo "<select name=\"selectfirmy\">";
    while ($nalezHledaniFirmy = mysqli_fetch_array($hodnotaHledaniFirmy)){
        echo "<option value=\"" .$nalezHledaniFirmy["firma"] ."\">".$nalezHledaniFirmy["firma"]."</option>";
    }
echo "</select>";



echo "<select name=\"selectbarvy\">";
    while ($nalezHledaniBarvy = mysqli_fetch_array($hodnotaHledaniBarvy)) {
        echo "<option value=\"" . $nalezHledaniBarvy["barva"] . "\">" . $nalezHledaniBarvy["barva"] . "</option>";
    }
echo "</select>";

echo "<select name=\"selectzavod\">";
    while ($nalezHledaniZavody = mysqli_fetch_array($hodnotaHledaniZavody)) {
        echo "<option value=\"" . $nalezHledaniZavody["zavod"] . "\">" . $nalezHledaniZavody["zavod"] . "</option>";
    }
echo "</select>";

echo "<select name=\"selectserie\">";
    while ($nalezHledaniSerie = mysqli_fetch_array($hodnotaHledaniSerie)) {
        echo "<option value=\"" . $nalezHledaniSerie["serie"] . "\">" . $nalezHledaniSerie["serie"] . "</option>";
    }
echo "</select>";
?>
		
</form>
<?php
} #konec funkce ZobrazeniFormulare
?>


</body>
</html>

