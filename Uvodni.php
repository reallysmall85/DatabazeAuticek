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
	<title>Uvodni stranka</title>
	<link rel="stylesheet" type="text/css" href="styly-formulare.css" media="all" />
	<link rel="stylesheet" type="text/css" href="tisk-formulare.css" media="print" />	

	 <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        table {
            display: flex;
            justify-content: center;
            align-items: center;
            text-decoration: none;
            text-color: black;
            text-align: center;
			font-weight: bold;
			font-size: 18px;
			
        }
        a,
		a:visited,
		a:hover,
		a:active {
 			 text-decoration: none; /* vypne podtržení */
 			 color: black;          /* nastaví barvu textu na černou */
		}
    </style>


</head>
<body>
<?php
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
    echo "Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>";
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
         echo "</span><br>";

}
?>
<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<br>
<h1 align="center">ÚVODNÍ STRÁNKA</h1>
<br>
<table>
	<tr>
		<td>
			<a href="Auta-main.php?stranka=1">
				<img width="310" height="310" src="seznam.jpg" name="Seznam aut">
			</a>
		</td>
		<td>
			<a href="Uzivatele.php">
				<img width="310" height="310" src="uzivatele.jpg" name="Uzivatele">
			</a>
		</td>
	</tr>
	<tr>
		<td>
			<a href="Auta-main.php?stranka=1">
				SEZNAM AUT
			</a>
		</td>
		<td>
			<a href="Uzivatele.php">
				UŽIVATELÉ
			</a>
		</td>
	</tr>

</table>


</body>
</html>
