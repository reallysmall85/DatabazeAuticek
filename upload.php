<?php

if(isset($_GET["polozka"])){
    $polozka = $_GET["polozka"];
}

if(isset($_FILES['file'])) {
    $errors = [];
    //$file_name = "img-".$_FILES['file']['name'];
    
    // původní jméno souboru
    $originalName = $_FILES['file']['name'];
    // rozdělíme cestu: název bez přípony a příponu
    $info   = pathinfo($originalName);
    $base   = $info['filename'];   // název bez teček
    $ext    = isset($info['extension']) ? $info['extension'] : '';
    // odstraníme všechny speciální znaky kromě písmen a číslic
    $cleanBase = preg_replace('/[^A-Za-z0-9]/', '', $base);
    // složíme nové jméno
    $file_name = 'img-' . $cleanBase . ($ext ? '.' . $ext : '');
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
