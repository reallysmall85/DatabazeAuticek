<?php

// Zapnutí všech chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);




function Uloz ($radekAuta, $connection, $prihlasenId){

	if (!$connection) {
    die("Database connection failed: " . mysqli_connect_error());
}
	

			mysqli_query($connection, "
    UPDATE auta 
    SET firma = '".$_REQUEST["inputfirmy"]."',
  		firma2 = '".$_REQUEST["inputfirmy2"]."',
        cislo = '".$_REQUEST["inputcisla"]."',
        nazev = '".$_REQUEST["inputnazev"]."',
        upresneni = '".$_REQUEST["inputupresneni"]."',
        barva1 = '".$_REQUEST["inputbarvy1"]."',
        barva2 = '".$_REQUEST["inputbarvy2"]."',
        barva3 = '".$_REQUEST["inputbarvy3"]."',
        barva4 = '".$_REQUEST["inputbarvy4"]."',
        barva5 = '".$_REQUEST["inputbarvy5"]."',
        serie = '".$_REQUEST["inputserie"]."',
        zavod = '".$_REQUEST["inputzavod"]."',
        startovnicislo = '".$_REQUEST["inputstartovnicislo"]."',
        tym = '".$_REQUEST["inputtym"]."',
        reklama = '".$_REQUEST["inputreklama"]."',
        jezdec1 = '".$_REQUEST["inputjezdec1"]."',
        jezdec2 = '".$_REQUEST["inputjezdec2"]."',
        jezdec3 = '".$_REQUEST["inputjezdec3"]."',
        rok = '".$_REQUEST["inputroku"]."',
        cena = '".$_REQUEST["inputceny"]."',
        popis = '".$_REQUEST["inputpopis"]."',
        poznamka = '".$_REQUEST["inputpoznamka"]."',
        umisteniauta = '".$_REQUEST["inputumisteniauta"]."',
        umistenikrabicky = '".$_REQUEST["inputumistenikrabicky"]."',
        mame = '".$_REQUEST["inputmame"]."'
    WHERE id = '$radekAuta'
");

$adresarTempFotek = "Fotky/temp/".$radekAuta."/";
$adresaslozkykvytvoreni = "Fotky/".$radekAuta."/";

if (!is_dir($adresaslozkykvytvoreni)){
mkdir ($adresaslozkykvytvoreni);
chmod ($adresaslozkykvytvoreni, 0777);
}

$nalezeneFotkyTemp = glob($adresarTempFotek . '*');
$nalezeneFotkyStavajici = glob($adresaslozkykvytvoreni . '*');

foreach ($nalezeneFotkyStavajici as $fotkaStavajici){
	unlink($fotkaStavajici);
}


foreach ($nalezeneFotkyTemp as $fotka) {
	copy($fotka, $adresaslozkykvytvoreni . basename($fotka));
}
	

# ------------ FIRMY --------------
	$hodnotaHledaniFirmy=mysqli_query($connection, "SELECT * FROM autafirmy WHERE id IS NOT NULL ORDER BY id");
	$existenceFirmy1 = "neexistuje";
	$existenceFirmy2 = "neexistuje";
	while ($nalezHledaniFirmy = mysqli_fetch_array($hodnotaHledaniFirmy)){
		if ($nalezHledaniFirmy["firma"] == $_REQUEST["inputfirmy"]){
			$existenceFirmy1 = "existuje";
		}
		if ($nalezHledaniFirmy["firma"] == $_REQUEST["inputfirmy2"]){
			$existenceFirmy2 = "existuje";
		}
	}
	if ($existenceFirmy1 == "neexistuje" and $_REQUEST["inputfirmy"] != ""){
	$kodnovefirmy1 = Time() +1;
	mysqli_query($connection, "INSERT INTO autafirmy (id) values ('$kodnovefirmy1')"); #zalozi radek
	mysqli_query($connection, "UPDATE autafirmy SET firma= '".$_REQUEST["inputfirmy"]."' WHERE id='$kodnovefirmy1'");
	}
	if ($existenceFirmy2 == "neexistuje" and $_REQUEST["inputfirmy2"] != ""){
	$kodnovefirmy2 = Time() +2;
	mysqli_query($connection, "INSERT INTO autafirmy (id) values ('$kodnovefirmy2')"); #zalozi radek
	mysqli_query($connection, "UPDATE autafirmy SET firma= '".$_REQUEST["inputfirmy2"]."' WHERE id='$kodnovefirmy2'");
	}


# ------------ ZAVOD --------------
	$hodnotaHledaniZavody=mysqli_query($connection, "SELECT * FROM autazavody WHERE id IS NOT NULL ORDER BY id");
	$existenceZavodu = "neexistuje";
	while ($nalezHledaniZavody = mysqli_fetch_array($hodnotaHledaniZavody)){
		if ($nalezHledaniZavody["zavod"] == $_REQUEST["inputzavod"]){
			$existenceZavodu = "existuje";
		}
	}
	if ($existenceZavodu == "neexistuje" and $_REQUEST["inputzavod"] != ""){
	$kodnovehozavodu = Time() +8;
	mysqli_query($connection, "INSERT INTO autazavody (id) values ('$kodnovehozavodu')"); #zalozi radek
	mysqli_query($connection, "UPDATE autazavody SET zavod= '".$_REQUEST["inputzavod"]."' WHERE id='$kodnovehozavodu'");
	}
	
# ------------ SERIE --------------
	$hodnotaHledaniSerie=mysqli_query($connection, "SELECT * FROM autaserie WHERE id IS NOT NULL ORDER BY id");
	$existenceSerie = "neexistuje";
	while ($nalezHledaniSerie = mysqli_fetch_array($hodnotaHledaniSerie)){
		if ($nalezHledaniSerie["serie"] == $_REQUEST["inputserie"]){
			$existenceSerie = "existuje";
		}
	}
	if ($existenceSerie == "neexistuje" and $_REQUEST["inputserie"] != ""){
	$kodnoveserie = Time() +9;
	mysqli_query($connection, "INSERT INTO autaserie (id) values ('$kodnoveserie')"); #zalozi radek
	mysqli_query($connection, "UPDATE autaserie SET serie= '".$_REQUEST["inputserie"]."' WHERE id='$kodnoveserie'");
	}




} # konec funkce



?>
