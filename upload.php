<?php

if(isset($_GET["polozka"])){
    $polozka = $_GET["polozka"];
}

if(isset($_FILES['file'])) {
    $errors = [];
    $file_name = "img-".$_FILES['file']['name'];
    $file_tmp  = $_FILES['file']['tmp_name'];
    $uploadDirectory = "Fotky/temp/".$polozka."/";
        
    if(move_uploaded_file($file_tmp, $uploadDirectory . $file_name)) {
        echo "Soubor byl úspěšně nahrán.";
    } else {
        echo "Chyba při nahrávání souboru.";
    }
} else {
    echo "Žádný soubor nebyl odeslán.";
}
?>
