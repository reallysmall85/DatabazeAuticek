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
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mobile-styly.css" media="(max-width: 767px)">
	<link rel="stylesheet" href="desktop-styly.css" media="(min-width: 768px)">
	<title>Uvodni stranka</title>


	 


</head>
<body>
<?php
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
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

function isMobile() {
    return preg_match('/(android|iphone|ipad|ipod|opera mini|mobile|silk|kindle|blackberry|bb10|iemobile|windows phone)/i', $_SERVER['HTTP_USER_AGENT']);
}

?>
<table class="tabulka-ikony">
<tr>
<td>
<div>
<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
</div>
</td>
</tr>
</table>


<table class="tabulka-uvodni">
	<tr>
	<th colspan="3" style="padding: 20px 0px 20px;">
		AUTÍČKÁRNA
	</th>
	</tr>
	<tr>
		<td>
			<a href="Auta-main.php?stranka=1">
				<div><img width="310" height="310" src="seznam.png" name="Seznam aut"></div>
				<div>SEZNAM AUT</div>
			</a>
		</td>
		
		<?php
		
		if (isMobile()){
			echo "</tr><tr>";
		}
		
		
		if ($prihlasenOpravneni <= 2){
			echo "<td><a href=\"Auta-main.php?stranka=1&zobrazpozadavky=ano\"><div><img width=\"310\" height=\"310\" src=\"pozadavky.png\" name=\"Seznam chybějících\"></div><div>SEZNAM POŽADAVKŮ</div></a></td>";
			
			if (isMobile()){
			echo "</tr><tr>";
			}
		}
		?>
		
		<td>
			<a href="Uzivatele.php">
				<div><img width="310" height="310" src="uzivatele.png" name="Uzivatele"></div>
				<div>UŽIVATELÉ</div>
			</a>
		</td>
	</tr>
	

</table>


</body>
</html>
