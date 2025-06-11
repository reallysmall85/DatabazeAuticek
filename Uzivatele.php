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
	<meta name="keywords" content="uvod"/>
	<title>Uživatelé</title>
	<style>
        body, html {
            width: 100%;
            max-width: 100%;
        }
        table {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            padding: 8px;
            border: 1px solid black;
            word-wrap: break-word;
        }

    </style>



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
<script>
		function dotazkmazani(){
		dialogoveokno=window.confirm("Opravdu chcete smazat záznam?");
		if(dialogoveokno) document.uzivateleFormular.potvrzeniMazani.value='potvrzeno';
		}

 

  // Když je DOM načtený, najdeme oběma elementy podle ID
  document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('heslo');
    var btn   = document.getElementById('zobrazheslo');

    btn.addEventListener('click', function() {
      // Přepneme typ inputu
      if (input.type === 'password') {
        input.type = 'text';
        btn.setAttribute('aria-label','Skrýt heslo');
      } else {
        input.type = 'password';
        btn.setAttribute('aria-label','Zobrazit heslo');
      }
    });
  });
</script>


 
</head>


<body>

<?php include("phpqrcode/qrlib.php");
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
    echo "Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>";
        switch ($prihlasenOpravneni){
            case 1:
                echo "admin ";
                break;
            case 2:
                echo "moderator ";
                break;
            case 3:
                echo "uživatel ";
                break;
            case 4:
                echo "veřejnost ";
                break;    
            default:
                echo "úrovně č.: " .$prihlasenOpravneni;
                break;

        }
         echo "</span><a href=\"Zmena-hesla.php\" target=\"_blank\"><input type=\"button\" name=\"zmenaHesla\" value=\"Změnit heslo\"></a><br>";

}
?>

<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<a href="Uvodni.php">
<img width="50" height="50" src="Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku">
</a><br><br>

<?php

function UzivateleUkladani ($zobrazovaneId, $connection){



		
	mysqli_query($connection, "UPDATE autauzivatele SET 
        jmeno= '".$_REQUEST['jmeno']."', 
        prijmeni= '".$_REQUEST['prijmeni']."', 
        titulpred= '".$_REQUEST['titulpred']."', 
        titulza= '".$_REQUEST['titulza']."', 
        telefon= '".$_REQUEST['telefon']."', 
        email= '".$_REQUEST['email']."', 
        opravneni= '".$_REQUEST['opravneni']."', 
        aktivni= '".$_REQUEST['aktivni']."'
        WHERE id='$zobrazovaneId'");



} #konec funkce UzivateleUkladani




if ($_REQUEST["ulozit"]){
    $zobrazovaneId = $_REQUEST["skryteID"];
	UzivateleUkladani ($zobrazovaneId, $connection);
	

?><script>window.alert("Data byla uložena.");</script><?php 
	ZobrazeniFormulareUzivatele ($prihlasenId, $zobrazovaneId, $prihlasenOpravneni, $connection);
}

elseif ($_REQUEST["nactiuzivatele"]){
    $zobrazovaneId = $_REQUEST["idnacteni"];
    ZobrazeniFormulareUzivatele ($prihlasenId, $zobrazovaneId, $prihlasenOpravneni, $connection);
}

elseif ($_REQUEST["novy"]){
	$zobrazovaneId = Time();
	mysqli_query($connection, "INSERT INTO autauzivatele (id, heslo, opravneni, aktivni) values ('$zobrazovaneId', '1234', '4', 'on')"); #zalozi radek
	
?><script>window.alert("Uživatel byl založen. Heslo je: 1234, vyplň údaje a ulož");</script><?php 
ZobrazeniFormulareUzivatele ($prihlasenId, $zobrazovaneId, $prihlasenOpravneni, $connection);
}

elseif (isset($_REQUEST["smazat"])){
    $zobrazovaneId = $_REQUEST["skryteID"];
	if ($_REQUEST["potvrzeniMazani"] == "potvrzeno"){
		mysqli_query($connection, "DELETE FROM autauzivatele WHERE id ='$zobrazovaneId'");
		?><script>window.alert("Mazání bylo úspěšně provedeno.");</script>
		<?php
        ZobrazeniFormulareUzivatele ($prihlasenId, $prihlasenId, $prihlasenOpravneni, $connection);
		}
		else{?><script>window.alert("Mazání bylo zrušeno.");</script>
		<?php
		ZobrazeniFormulareUzivatele ($prihlasenId, $zobrazovaneId, $prihlasenOpravneni, $connection);
		}	


}



else{
	ZobrazeniFormulareUzivatele ($prihlasenId, $prihlasenId, $prihlasenOpravneni, $connection);
}




function ZobrazeniFormulareUzivatele ($prihlasenId, $zobrazovaneId, $prihlasenOpravneni, $connection){

echo "<form method=\"post\" action=\"Uzivatele.php\" name=\"uzivateleFormular\">";

$vypisDatUzivatele=mysqli_query($connection, "SELECT * FROM autauzivatele WHERE id='$zobrazovaneId'");
		$nactenaDataUzivatele = mysqli_fetch_array($vypisDatUzivatele);


        if ($prihlasenOpravneni <= 2){
        
        echo "<input type=\"Submit\" name=\"novy\" value=\"Založit uživatele\" onmouseover=\"this.style.backgroundColor='darkorange';\" onmouseout=\"this.style.backgroundColor='orange';\"  style=\"background-color: orange; color: white; border: none; padding: 10px 20px; cursor: pointer;\">";
      
        echo "<br><br><div>Načti uživatele:";
        
            echo "<select name=\"idnacteni\" onchange=\"document.getElementsByName('nactiuzivatele')[0].click()\">";
                
                    $vypisDatNalezenychUzivatelu=mysqli_query($connection, "SELECT * FROM autauzivatele WHERE id IS NOT NULL ORDER BY prijmeni");
                    while ($nalezUzivatelu = mysqli_fetch_array($vypisDatNalezenychUzivatelu)) {
                        if ($prihlasenId == $nalezUzivatelu["id"] || $prihlasenOpravneni < $nalezUzivatelu["opravneni"]){
                            echo "<option value=\"" . $nalezUzivatelu["id"] ."\"";
                            if ($nalezUzivatelu["id"] == $zobrazovaneId){
                                echo " selected";
                            }
                            echo ">" . $nalezUzivatelu["titulpred"] ." " .$nalezUzivatelu["jmeno"] ." " .$nalezUzivatelu["prijmeni"] ." " .$nalezUzivatelu["titulza"] ."</option>";
                        }   
                    }
                
            echo "</select>";
            
	            echo "<input type=\"Submit\" name=\"nactiuzivatele\" value=\"Načti data\" style=\"display: none;\">";
            }?>
        </div>
        <br>
            <input name="skryteID" type="hidden" value="<?php echo $nactenaDataUzivatele["id"];?>" >

<table>
	<tr>
        <td>Titul před jménem: </td><td><input autocomplete="off" name="titulpred" value="<?php echo $nactenaDataUzivatele["titulpred"];?>" size="10" row="1"></td>
    </tr>
    <tr>
        <td>Jméno: </td><td><input autocomplete="off" name="jmeno" value="<?php echo $nactenaDataUzivatele["jmeno"];?>" size="20" row="1"></td>
    </tr>
    <tr>
        <td>Příjmení: </td><td><input autocomplete="off" name="prijmeni" value="<?php echo $nactenaDataUzivatele["prijmeni"];?>" size="20" row="1"></td>
    </tr>
    <tr>
        <td>Titul za jménem: </td><td><input autocomplete="off" name="titulza" value="<?php echo $nactenaDataUzivatele["titulza"];?>" size="10" row="1"></td>
    </tr>
    	<tr>
		<td>Telefonní číslo: </td><td><input autocomplete="off" name="telefon" value="<?php echo $nactenaDataUzivatele["telefon"];?>" size="20" row="1"></td>
	</tr>
	<tr>
		<td>e-mail: </td><td><input autocomplete="off" name="email" value="<?php echo $nactenaDataUzivatele["email"];?>" size="20" row="1"></td>
	</tr>

	<?php

    if ($prihlasenOpravneni <= 2){
        echo "<tr>";
            echo "<td>Oprávnění: </td><td>";
            echo "<select name=\"opravneni\">";
                if ($prihlasenOpravneni <= 1){
                    echo "<option value=\"1\"";
                    if ($nactenaDataUzivatele["opravneni"] == 1){echo " selected";}
                    echo ">admin</option>";
                }
                if ($prihlasenOpravneni <= 2){
                    echo "<option value=\"2\"";
                    if ($nactenaDataUzivatele["opravneni"] == 2){echo " selected";}
                    echo ">moderátor</option>";
                }
                echo "<option value=\"3\""; 
                if ($nactenaDataUzivatele["opravneni"] == 3){echo " selected";}
                echo ">uživatel</option>";
                echo "<option value=\"4\"";
                if ($nactenaDataUzivatele["opravneni"] == 4){echo " selected";}
                echo ">veřejnost</option>";
            echo "</select>";
           
            echo "</td>";
        echo "</tr>";
	    echo "<tr>";
		    echo "<td>Aktivní: </td><td><input name=\"aktivni\" type=\"checkbox\""; if ($nactenaDataUzivatele["aktivni"] == "on"){ echo " checked";}else{ echo " unchecked";} echo "></td>";
	    echo "</tr>";
	    }
        else {
            echo "<input name=\"opravneni\" type=\"hidden\" value=\"".$nactenaDataUzivatele["opravneni"]."\">";
            echo "<input name=\"aktivni\" type=\"hidden\" value=\"".$nactenaDataUzivatele["aktivni"]."\">";
        }

        if ($prihlasenOpravneni <= 1){
            echo "<tr><td>Heslo:</td><td><div>
        <input
          type=\"password\"
          value=\"".$nactenaDataUzivatele["heslo"]."\"
          readonly
          id=\"heslo\"
        >
        <button
          type=\"button\"
          aria-label=\"Zobrazit heslo\"
          id=\"zobrazheslo\"
        >👁️</button>
      </div></td></tr>";
        }
	?>
	
 
</table>




		

<?php
echo "<input type=\"Submit\" name=\"ulozit\" value=\"Uložit\" onmouseover=\"this.style.backgroundColor='darkgreen';\" onmouseout=\"this.style.backgroundColor='green';\"  style=\"background-color: green; color: white; border: none; padding: 10px 20px; cursor: pointer;\">";





if ($prihlasenOpravneni <= 2){
    $jmeno    = htmlspecialchars($nactenaDataUzivatele["jmeno"],    ENT_QUOTES, 'UTF-8');
    $prijmeni = htmlspecialchars($nactenaDataUzivatele["prijmeni"], ENT_QUOTES, 'UTF-8');
  
    echo "<input"
       . " type=\"submit\"" 
       . " name=\"smazat\""
       . " value=\"Smazat {$jmeno} {$prijmeni}\""
       . " onclick=\"dotazkmazani();\""
       . " onmouseover=\"this.style.backgroundColor='darkred';\""
       . " onmouseout=\"this.style.backgroundColor='red';\""
       . " style=\"background-color: red; color: white; border: none;"
       . " padding: 10px 20px; margin-left: 5px; cursor: pointer;\""
       . ">";


}?>

<input type="hidden" name="potvrzeniMazani" value="nepotvrzeno" />
</form>

<?php 					
} #konec zobrazeni formulare					
?>


</body>
</html>

