<?php

// Zapnutí všech chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);

function Smaz ($radek, $connection){

			
			$parts = [];
        $parts[] = "Byl smazán záznam:";
        $parts[] = "firma='"            . $_REQUEST['inputfirmy']           . "'";
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
		$parts[] = "id='"              . $radek            . "'";

    
        // spojím oddělovačem a pošlu do logu
        zapisDoLogu(implode(', ', $parts));
			
		
		mysqli_query($connection, "DELETE FROM auta WHERE id ='$radek'");

		$adresarFotek = "Fotky/".$radek."/";



$nalezeneFotky = glob($adresarFotek . '*');


foreach ($nalezeneFotky as $fotka){
	unlink($fotka);
}
rmdir($adresarFotek);
}

?>


