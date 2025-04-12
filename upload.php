<?php

if(isset($_GET["prihlasen"])){
    $prihlasenId = $_GET["prihlasen"];
}

if(isset($_FILES['file'])) {
    $errors = [];
    $file_name = $_FILES['file']['name'];
    $file_tmp  = $_FILES['file']['tmp_name'];
    $uploadDirectory = "Fotky/temp/".$prihlasenId."/";
        
    if(move_uploaded_file($file_tmp, $uploadDirectory . $file_name)) {
        echo "Soubor byl úspěšně nahrán.";
    } else {
        echo "Chyba při nahrávání souboru.";
    }
} else {
    echo "Žádný soubor nebyl odeslán.";
}
?>
