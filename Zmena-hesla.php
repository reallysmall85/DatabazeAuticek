<?php

session_start();

if (!isset($_SESSION['uzivatel'])) {
	header("Location: Prihlaseni.php");
    exit();
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta name="author" content="martin"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mobile-styly.css" media="(max-width: 767px)">
	<link rel="stylesheet" href="desktop-styly.css" media="(min-width: 768px)">
	<title>Změna hesla</title>

<?php

#Pripojeni souboru s pripojovacimi daty k databazi. Diky tomu, ze je to v PHP to nikdo nemuze cist pres WEB.

include ("Pripojeni/pripojeniDatabaze.php");



// Create a database connection
$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);

// Check if the connection was successful
if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_query($connection, "SET CHARACTER SET utf8");

#Ten set character set utf8 zajisti, ze se bude v databazi (phpmyadminu) dobre zobrazovat diakritika.

?>

 
</head>
<body>

<?php include("phpqrcode/qrlib.php");
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
	$prihlasenHeslo = isset($_SESSION['uzivatel']['heslo']) ? $_SESSION['uzivatel']['heslo'] : 'Chyba hesla';
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


$vypisDatUzivatele=mysqli_query($connection, "SELECT * FROM autauzivatele WHERE id='$prihlasenId'");
		$nactenaDataUzivatele = mysqli_fetch_array($vypisDatUzivatele);

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

<?php


if ($_REQUEST["potvrzeni"]){

		
	
		if ($_REQUEST["useroldheslo"] == $nactenaDataUzivatele["heslo"] and $_REQUEST["usernewheslo"] == $_REQUEST["usernewheslokontrola"]){
			

			mysqli_query($connection, "UPDATE autauzivatele SET heslo= '".$_REQUEST["usernewheslo"]."' WHERE id='$prihlasenId'");

			$parts = [];
			$parts[] = "Uživatel ";
			$parts[] = "(jmeno)'"     . $nactenaDataUzivatele['jmeno']     . "'";
			$parts[] = "(prijmeni)'"  . $nactenaDataUzivatele['prijmeni']  . "'";
			$parts[] = " si změnil heslo";
		
			// spojím oddělovačem a pošlu do logu
			zapisDoLogu(implode(', ', $parts));

			echo "<script>window.alert(\"Bylo úspěšně změněno na nové heslo: ".$_REQUEST["usernewheslo"].".\");</script>";
			echo "<script>window.close();</script>";
		}
		

	
		else{
			?><script>window.alert("Chyba! Něco bylo špatně zadáno.");</script><?php 
			ZobrazeniFormulareZmenahesla ($prihlasenId, $connection);
		}



}
else{
		ZobrazeniFormulareZmenahesla ($prihlasenId, $connection);
}



function ZobrazeniFormulareZmenahesla ($prihlasenId, $connection){?>



<?php echo "<form method=\"post\" action=\"Zmena-hesla.php\" name=\"zmenaHesla\">";?>
<table class="tabulka-zmena-hesla">

	<tr><th colspan="2">ZMĚNA HESLA</th></tr>

	<tr><td>Zadej své staré heslo: </td><td><input name="useroldheslo" size="10" row="1" type="password"></td></tr>
	<tr><td>Zadej své nové heslo: </td><td><input name="usernewheslo" size="10" row="1" type="password"></td></tr>
	<tr><td>Zadej ještě jednou nové heslo (kontrola): </td><td><input name="usernewheslokontrola" size="10" row="1" type="password"></td></tr>
	<tr>	
		<td colspan="2">
			<div align="right"><input type="Submit" class="zaoblene-tlacitko-zelene" name="potvrzeni" value="OK" onmouseover="this.style.backgroundColor='darkgreen';" onmouseout="this.style.backgroundColor='green';"></div>
			
		</td>
	</tr>
</table>



</form>
<?php 
}

?>

</body>
</html>

