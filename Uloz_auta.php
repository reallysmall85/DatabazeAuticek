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
    SET firma1 = '".$_REQUEST["inputfirmy1"]."',
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

$parts = [];
$parts[] = "Do tabulky auta bylo změněno:";
$parts[] = "firma1='"            . $_REQUEST['inputfirmy1']           . "'";
$parts[] = "firma2='"           . $_REQUEST['inputfirmy2']          . "'";
$parts[] = "cislo='"            . $_REQUEST['inputcisla']           . "'";
$parts[] = "nazev='"            . $_REQUEST['inputnazev']           . "'";
$parts[] = "upresneni='"        . $_REQUEST['inputupresneni']       . "'";
$parts[] = "barva1='"           . $_REQUEST['inputbarvy1']          . "'";
$parts[] = "barva2='"           . $_REQUEST['inputbarvy2']          . "'";
$parts[] = "barva3='"           . $_REQUEST['inputbarvy3']          . "'";
$parts[] = "barva4='"           . $_REQUEST['inputbarvy4']          . "'";
$parts[] = "barva5='"           . $_REQUEST['inputbarvy5']          . "'";
$parts[] = "serie='"            . $_REQUEST['inputserie']           . "'";
$parts[] = "zavod='"            . $_REQUEST['inputzavod']           . "'";
$parts[] = "startovnicislo='"   . $_REQUEST['inputstartovnicislo']  . "'";
$parts[] = "tym='"              . $_REQUEST['inputtym']             . "'";
$parts[] = "reklama='"          . $_REQUEST['inputreklama']         . "'";
$parts[] = "jezdec1='"          . $_REQUEST['inputjezdec1']         . "'";
$parts[] = "jezdec2='"          . $_REQUEST['inputjezdec2']         . "'";
$parts[] = "jezdec3='"          . $_REQUEST['inputjezdec3']         . "'";
$parts[] = "rok='"              . $_REQUEST['inputroku']            . "'";
$parts[] = "cena='"             . $_REQUEST['inputceny']            . "'";
$parts[] = "popis='"            . $_REQUEST['inputpopis']           . "'";
$parts[] = "poznamka='"         . $_REQUEST['inputpoznamka']        . "'";
$parts[] = "umisteniauta='"     . $_REQUEST['inputumisteniauta']    . "'";
$parts[] = "umistenikrabicky='" . $_REQUEST['inputumistenikrabicky']. "'";
$parts[] = "mame='"              . $_REQUEST['inputmame']            . "'";
$parts[] = "id='"              . $radekAuta            . "'";

// spojím oddělovačem a pošlu do logu
zapisDoLogu(implode(', ', $parts));




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
	$hodnotaHledaniFirmy=mysqli_query($connection, "SELECT * FROM autafirmy WHERE id IS NOT NULL ORDER BY firma");
	$existenceFirmy1 = "neexistuje";
	$existenceFirmy2 = "neexistuje";
	while ($nalezHledaniFirmy = mysqli_fetch_array($hodnotaHledaniFirmy)){
		if ($nalezHledaniFirmy["firma"] == $_REQUEST["inputfirmy1"]){
			$existenceFirmy1 = "existuje";
		}
		if ($nalezHledaniFirmy["firma"] == $_REQUEST["inputfirmy2"]){
			$existenceFirmy2 = "existuje";
		}
	}
	if ($existenceFirmy1 == "neexistuje" and $_REQUEST["inputfirmy1"] != ""){
	$kodnovefirmy1 = Time() +1;
	mysqli_query($connection, "INSERT INTO autafirmy (id) values ('$kodnovefirmy1')"); #zalozi radek
	mysqli_query($connection, "UPDATE autafirmy SET firma= '".$_REQUEST["inputfirmy1"]."' WHERE id='$kodnovefirmy1'");
	$parts = [];
	$parts[] = "Do tabulky autafirmy bylo změněno:";
	$parts[] = "firma='"            . $_REQUEST['inputfirmy1']           . "'";
	$parts[] = "id='"              . $kodnovefirmy1            . "'";
	// spojím oddělovačem a pošlu do logu
	zapisDoLogu(implode(', ', $parts));

	}
	if ($existenceFirmy2 == "neexistuje" and $_REQUEST["inputfirmy2"] != "" and $_REQUEST["inputfirmy1"] != $_REQUEST["inputfirmy2"]){
	$kodnovefirmy2 = Time() +2;
	mysqli_query($connection, "INSERT INTO autafirmy (id) values ('$kodnovefirmy2')"); #zalozi radek
	mysqli_query($connection, "UPDATE autafirmy SET firma= '".$_REQUEST["inputfirmy2"]."' WHERE id='$kodnovefirmy2'");
	$parts = [];
	$parts[] = "Do tabulky autafirmy bylo změněno:";
	$parts[] = "firma='"            . $_REQUEST['inputfirmy2']           . "'";
	$parts[] = "id='"              . $kodnovefirmy2            . "'";
	// spojím oddělovačem a pošlu do logu
	zapisDoLogu(implode(', ', $parts));
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
	$parts = [];
	$parts[] = "Do tabulky autazavody bylo změněno:";
	$parts[] = "zavod='"            . $_REQUEST['inputzavod']           . "'";
	$parts[] = "id='"              . $kodnovehozavodu            . "'";
	// spojím oddělovačem a pošlu do logu
	zapisDoLogu(implode(', ', $parts));
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
	$parts = [];
	$parts[] = "Do tabulky autaserie bylo změněno:";
	$parts[] = "serie='"            . $_REQUEST['inputserie']           . "'";
	$parts[] = "id='"              . $kodnoveserie            . "'";
	// spojím oddělovačem a pošlu do logu
	zapisDoLogu(implode(', ', $parts));
	}




} # konec funkce



?>
