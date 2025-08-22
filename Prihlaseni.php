<?php
session_start();
$_SESSION = array();
ini_set('display_errors', 1);
error_reporting(E_ALL);
//echo "Metoda: " . $_SERVER['REQUEST_METHOD'];
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="cs">
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<title>Prihlaseni</title>

<style>
        body, html {
            width: 100%;
            max-width: 100%;
        }
        .prihlaseni {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden; 
			position: fixed;
  			top: 50%;
  			left: 50%; 
  			transform: translate(-50%, -50%);
  			margin: 0;
			font-size: 20px;
			   			
        }
        .prihlaseni th, 
		.prihlaseni td {
            padding: 8px 12px;
            border: none;
            word-wrap: break-word;
        }
		.chyba {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: white;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden; 
			margin-left: auto;
			margin-right: auto;
			margin-top: auto;
			font-size: 20px;
			border: none;
        }
        .chyba th, 
		.chyba td {
            padding: 8px 12px;
            border: none;
            word-wrap: break-word;
        }
        
    .zaoblene-tlacitko-zelene {
            background-color: green; 
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

$hodnotaHledaniUzivatele = mysqli_query($connection, "SELECT * FROM autauzivatele WHERE id IS NOT NULL ORDER BY id");




if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	ZobrazeniFormularePrihlaseni($hodnotaHledaniUzivatele);
    exit();
}

if (isset($_POST["potvrzeniPrihlaseni"])){

	$zadanyUzivatelId = $_POST['navstevnik'];
	$zadaneHeslo = $_POST['userheslo'];
	
	// Reset ukazatele vysledku:
	mysqli_data_seek($hodnotaHledaniUzivatele, 0);

	$nalezeno = false;
	while ($nalezHledaniUzivatele = mysqli_fetch_array($hodnotaHledaniUzivatele)){
		if ($zadanyUzivatelId == $nalezHledaniUzivatele["id"] and $zadaneHeslo == $nalezHledaniUzivatele["heslo"]){
			$nalezeno = true;
			$_SESSION['uzivatel'] = [
            'id' => $nalezHledaniUzivatele['id'],
            'jmeno' => $nalezHledaniUzivatele['jmeno'],
            'prijmeni' => $nalezHledaniUzivatele['prijmeni'],
            'opravneni' => $nalezHledaniUzivatele['opravneni'],
            'aktivni' => $nalezHledaniUzivatele['aktivni']
        ];
			header("Location: Uvodni.php");
      		exit();
		}
		
	}
	
	if (!$nalezeno) {
        echo "<table class=\"chyba\"><tr><td><p style='color:red;'>Chybné přihlašovací údaje!</p></td></tr></table>";
        // Reset ukazatele výsledku před znovuzobrazením formuláře
        mysqli_data_seek($hodnotaHledaniUzivatele, 0);
        ZobrazeniFormularePrihlaseni($hodnotaHledaniUzivatele);
    }
	
}




function ZobrazeniFormularePrihlaseni ($hodnotaHledaniUzivatele){
?>

<body class="pruhledny" style="background-image: url(pozadi-auticka5.png); background-position: top left; background-repeat: repeat;  background-size: 40%;">

<form method="post" action="Prihlaseni.php" name="kartaPrihlaseni">

<table class="prihlaseni">
	<tr>
	<th colspan="2" style="padding: 20px 0px 20px;">DATABÁZE AUTÍČEK</th>
	</tr>
	<tr><td>Najdi se v seznamu: </td><td><select name="navstevnik">
<?php
				mysqli_data_seek($hodnotaHledaniUzivatele, 0);
				while ($nalezHledaniUzivatele = mysqli_fetch_array($hodnotaHledaniUzivatele)){
					if ($nalezHledaniUzivatele["aktivni"] == "on"){
						echo "<option value=\"".$nalezHledaniUzivatele["id"]."\">".$nalezHledaniUzivatele["jmeno"]." ".$nalezHledaniUzivatele["prijmeni"]."</option>";
					}
					
				}
?>
</select>
			</td>
	</tr>
	<tr>
		<td>
			Zadej heslo: 
		</td>
		<td>
			<input name="userheslo" size="20" row="1" type="password">
		</td>
	</tr>
	<tr>
		<td>
		</td>	
		<td>
			<div align="right"><input type="Submit" class="zaoblene-tlacitko-zelene" name="potvrzeniPrihlaseni" value="Přihlásit" onmouseover="this.style.backgroundColor='darkgreen';" onmouseout="this.style.backgroundColor='green';"></div>
			
		</td>
	</tr>

	

</table>

</form>
</body>
</html>


<?php
}
?>