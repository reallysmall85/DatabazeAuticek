<?php
if (!isset($_GET["polozka"])) {
    exit("Chybí parametr polozka");
}
$polozka = $_GET["polozka"];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    exit("Žádný validní soubor nebyl odeslán.");
}

$uploadDirectory = "Fotky/temp/{$polozka}/";
// Ujistíme se, že složka existuje
if (!is_dir($uploadDirectory)) {
    mkdir($uploadDirectory, 0777, true);
}

// 1) Získáme původní jméno a rozdělíme ho
$originalName = $_FILES['file']['name'];
$info   = pathinfo($originalName);
$base   = $info['filename'];               // jméno bez přípony
$ext    = isset($info['extension']) 
            ? strtolower($info['extension']) 
            : '';

// 2) vyčistíme
$cleanBase = preg_replace('/[^A-Za-z0-9]/', '', $base);

// 3) nastavíme dvě proměnné – jedna je jméno bez přípony, druhá je přípona s tečkou
$baseName    = 'img-' . $cleanBase;           // např. "img-1234"
$extension   = $ext ? '.' . $ext : '';        // např. ".jpg"

// 4) vygenerujeme první návrh:
$fileName    = $baseName . $extension;        // "img-1234.jpg"

// 5) pokud už existuje, přičteme přírůstkové číslo
$counter = 2;
while (file_exists($uploadDirectory . $fileName)) {
    $fileName = $baseName . '-' . $counter . $extension;  // "img-1234-2.jpg", "img-1234-3.jpg"…
    $counter++;
}

// 6) teprve teď přesuneme nahraný soubor
if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDirectory . $fileName)) {
    echo "Soubor '{$fileName}' byl úspěšně nahrán.";
} else {
    echo "Chyba při ukládání souboru.";
}

?>
