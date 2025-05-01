<?php

// Zapnutí všech chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);

function Smaz ($radek, $connection){

			mysqli_query($connection, "DELETE FROM auta WHERE id ='$radek'");
			
			$adresarFotek = "Fotky/".$radek."/";



$nalezeneFotky = glob($adresarFotek . '*');


foreach ($nalezeneFotky as $fotka){
	unlink($fotka);
}
rmdir($adresarFotek);
}

?>


