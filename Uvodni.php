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
	<link rel="stylesheet" href="mobile-styly.css" media="(max-width: 1199px)">
	<link rel="stylesheet" href="desktop-styly.css" media="(min-width: 1200px)">
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


<table class="tabulka-uvodni" role="presentation">
<tr>
  <td colspan="3">
    <div class="menu-grid">
      <div class="tile">
        <a href="Auta-main.php?stranka=1">
          <img src="seznam.png" alt="Seznam aut">
          <div>SEZNAM AUT</div>
        </a>
      </div>

      <?php if ($prihlasenOpravneni <= 2): ?>
      <div class="tile">
        <a href="Auta-main.php?stranka=1&zobrazpozadavky=ano">
          <img src="pozadavky.png" alt="Seznam požadavků">
          <div>SEZNAM POŽADAVKŮ</div>
        </a>
      </div>
      <?php endif; ?>

      <div class="tile">
        <a href="Uzivatele.php">
          <img src="uzivatele.png" alt="Uživatelé">
          <div>UŽIVATELÉ</div>
        </a>
      </div>
    </div>
  </td>
</tr>

</table>




</body>
</html>
