<?php
session_start();

// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

// 2) Připojení k databázi (mysql­i)
include __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Nepodařilo se připojit k databázi: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

// 3) Kontrola oprávnění
$opravneni = isset($_SESSION['uzivatel']['opravneni']) 
    ? $_SESSION['uzivatel']['opravneni'] 
    : 4;
$jmeno     = isset($_SESSION['uzivatel']['jmeno']) 
    ? $_SESSION['uzivatel']['jmeno'] 
    : '???';
$prijmeni  = isset($_SESSION['uzivatel']['prijmeni']) 
    ? $_SESSION['uzivatel']['prijmeni'] 
    : '???';

if ($opravneni > 2) {
    header("Location: Prihlaseni.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
	
<?php
    $polozka = $_GET["polozka"];
    $hledaniNazvuPolozky = mysqli_query($connection, "SELECT * FROM auta WHERE id='$polozka'");
    $nalezenyNazevPolozky = mysqli_fetch_array($hledaniNazvuPolozky);
   
    






if ($polozka == "nova"){

		$polozka = Time();
		$caspridani = date('Y-m-d H:i:s');
		$hledaniExistence = mysqli_query($connection, "SELECT * FROM auta WHERE id='$polozka'");
        if (mysqli_num_rows($hledaniExistence) > 0) {
          $polozka = $polozka + 1;
        }

		mysqli_query($connection, "INSERT INTO auta (id, pridano) values ('$polozka', '$caspridani')"); #zalozi radek

        $parts = [];
        $parts[] = "Do tabulky auta bylo přidán nový záznam:";
        $parts[] = "id='"              . $polozka            . "'";

        // spojím oddělovačem a pošlu do logu
        zapisDoLogu(implode(', ', $parts));




       header("Location: Auta-edit.php?polozka=" . $polozka);
        exit();

}



if (isset($_GET['duplikace'])) {
    $puvodniPolozka = (int)$_GET["polozka"];

    $novaPolozka = Time();
    $caspridani = date('Y-m-d H:i:s');
    $hledaniExistence = mysqli_query($connection, "SELECT 1 FROM auta WHERE id='$novaPolozka'");
    if (mysqli_num_rows($hledaniExistence) > 0) {
      $novaPolozka++;
    }

    mysqli_query($connection, "
      INSERT INTO auta (
        id,firma,firma2,cislo,nazev,upresneni,
        barva1,barva2,barva3,barva4,barva5,
        serie,zavod,startovnicislo,tym,reklama,
        jezdec1,jezdec2,jezdec3,rok,cena,popis,
        poznamka,umisteniauta,umistenikrabicky,mame,pridano
      )
      SELECT
        '$novaPolozka', firma,firma2,cislo,nazev,upresneni,
        barva1,barva2,barva3,barva4,barva5,
        serie,zavod,startovnicislo,tym,reklama,
        jezdec1,jezdec2,jezdec3,rok,cena,popis,
        poznamka,umisteniauta,umistenikrabicky,mame,'$caspridani'
      FROM auta
      WHERE id='$puvodniPolozka'
    "); #duplikuje radek

        $parts = [];
        $parts[] = "Do tabulky auta bylo duplikován záznam, nový řádek pod č.:";
        $parts[] = "id='"              . $novaPolozka            . "'";
        $parts[] = "kopie vytvořena z řádku č.:";
        $parts[] = "id='"              . $puvodniPolozka            . "'";

        // spojím oddělovačem a pošlu do logu
        zapisDoLogu(implode(', ', $parts));
    
   header("Location: Auta-edit.php?polozka=" . $novaPolozka ."&byloduplikovano");
    exit();

}

if ($nalezenyNazevPolozky['nazev'] == ""){
    echo "<title>Edit: ".$polozka."</title>";

}
else {
    echo "<title>Edit: ".$nalezenyNazevPolozky['nazev']."</title>";
}


if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
}




$polozkaKontrola = 'p_'.$polozka;
$adresarSlozkyFotekTempPolozky = "Fotky/temp/".$polozka."/";

if (!isset($_SESSION[$polozkaKontrola])) {
    // Toto se provede jen při prvním načtení stránky
    $_SESSION[$polozkaKontrola] = true;

function vymazaniTempFotek($adresarSlozkyFotekTempPolozky) {
    if (!is_dir($adresarSlozkyFotekTempPolozky)) {
        return false;
    }
    $fotkyKVymazani = scandir($adresarSlozkyFotekTempPolozky);
    foreach ($fotkyKVymazani as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $filePath = $adresarSlozkyFotekTempPolozky . DIRECTORY_SEPARATOR . $file;
        // Pokud je položka složka, rekurzivně smažeme její obsah a pak samotnou složku
        if (is_dir($filePath)) {
            vymazaniTempFotek($filePath);
            rmdir($filePath);
        } else {
            unlink($filePath);
        }
    }
    return true;
}

vymazaniTempFotek($adresarSlozkyFotekTempPolozky);
// a zároveň smažeme v localStorage JSON pro tuhle položku
echo "<script>
localStorage.removeItem('formData_". addslashes($polozka) ."');
</script>";
}

    
if (!is_dir($adresarSlozkyFotekTempPolozky)){
     mkdir ($adresarSlozkyFotekTempPolozky, 0777, true);
     chmod ($adresarSlozkyFotekTempPolozky, 0777);
}


$adresaslozkykvytvoreni = "Fotky/".$polozka."/";

    	if (!is_dir($adresaslozkykvytvoreni)){
    	mkdir ($adresaslozkykvytvoreni);
    	chmod ($adresaslozkykvytvoreni, 0777);
    	}

$nalezeneUlozeneFotky = glob($adresaslozkykvytvoreni . '*');

foreach ($nalezeneUlozeneFotky as $fotka) {
    copy($fotka, $adresarSlozkyFotekTempPolozky . basename($fotka));

}

?>

<script>
    const sessionIDUzivatele = <?php echo json_encode($prihlasenId); ?>;
</script>


<script>
		function dotazkmazani(){
		dialogoveokno=window.confirm("Opravdu chcete smazat záznam?");
		if(dialogoveokno) document.formularauta.potvrzeniMazani.value='potvrzeno';
		}
</script>



 <script>
        // Funkce pro tisk QR kódu
        function printQR(imageSrc) {
            const printWindow = window.open('', '_blank'); // Otevření nového okna
            printWindow.document.write('<html><head><title>Tisk QR kódu</title></head><body>');
            printWindow.document.write('<img src="' + imageSrc + '" style="width:300px;height:300px;">'); // Nastavení velikosti obrázku
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print(); // Vyvolání tisku
        }
    </script>
    
    
<script>

document.addEventListener("DOMContentLoaded", function() {
    let dropArea = document.getElementById('drop-area');

// Zamezení výchozímu chování (otevírání souboru v prohlížeči)
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
  dropArea.addEventListener(eventName, preventDefaults, false);
});

function showTemporaryMessage() {
  // Zobrazíme hlášku
  const messageDiv = document.getElementById('message');
  messageDiv.style.display = 'block';
  

  setTimeout(function() {
    messageDiv.style.display = 'none';
    window.location.reload();
  }, 2000); // 1000 milisekund = 1 sekunda
}



function preventDefaults(e) {
  e.preventDefault();
  e.stopPropagation();
}

// Zvýraznění oblasti při přetažení
['dragenter', 'dragover'].forEach(eventName => {
  dropArea.addEventListener(eventName, () => dropArea.classList.add('hover'), false);
});

['dragleave', 'drop'].forEach(eventName => {
  dropArea.addEventListener(eventName, () => dropArea.classList.remove('hover'), false);
});

// Zpracování drop události
dropArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
  let dt = e.dataTransfer;
  let files = dt.files;
  // Filtrace: vybereme pouze soubory, jejichž MIME typ začíná na "image/"
  let validFiles = [...files].filter(file => file.type.startsWith("image/"));

  if (validFiles.length === 0) {
    console.log("Nebyly přetaženy žádné obrázkové soubory.");
    alert("Prosím, přetáhněte obrázek (jpg, jpeg, png, apod.).");
    return;
  }

  handleFiles(validFiles);
}


function handleFiles(files) {
    // Zobrazíme hlášku "Načítám..."
    document.getElementById("loadingMessage").style.display = 'block';
    // Pro každý soubor zavoláme uploadFile a počkáme, dokud se všechny neuploadnou
     ([...files]).forEach(uploadFile);
}
  
  
function uploadFile(file) {
  // Získáme GET parametr "polozka" z aktuální URL, pokud existuje
  let params = new URLSearchParams(window.location.search);
  let polozka = params.get('polozka') || '';
  let prihlasen = params.get('sessionIDUzivatele') || '';
  
  // Připojíme parametr "polozka" do URL
  let url = 'upload.php?polozka=' + encodeURIComponent(polozka);
  
  let formData = new FormData();
  formData.append('file', file);

  fetch(url, {
    method: 'POST',
    body: formData
  })
  .then(response => response.text())
  .then(data => {
    console.log(data);
    showTemporaryMessage();
  })
  .catch(() => { console.error('Chyba při nahrávání souboru') });
}


document.getElementById("fileElem").addEventListener("change", function() {
  if (this.files && this.files.length > 0) {
    // Vyfiltrujeme pouze soubory, jejichž MIME typ začíná na "image/"
    const validFiles = [...this.files].filter(file => file.type.startsWith("image/"));
    
    if (validFiles.length === 0) {
      console.log("Žádný obrázkový soubor nebyl vybrán.");
      alert("Prosím, vyberte obrázek (jpg, jpeg, png, apod.).");
      return;
    }
    
    console.log("Vybrané obrázkové soubory:", validFiles);
    handleFiles(validFiles);  // Voláme funkci pro nahrávání pouze s validními soubory
  } else {
    console.log("Žádný soubor nebyl vybrán.");
  }
});
    const form = document.querySelector('form[name="formularauta"]');
    if (!form) return;

  // 1) Klíč pro localStorage
  const polozka = new URLSearchParams(window.location.search).get('polozka') || '';
  const storageKey = 'formData_' + polozka;
  // 2) Načteme existující data (nebo prázdný objekt)
  const formData = JSON.parse(localStorage.getItem(storageKey) || '{}');


  // Pomocné funkce
  function markUnsaved(textarea) {
    textarea.classList.add('unsaved');
  }
  function saveField(name, value) {
    formData[name] = value;
    localStorage.setItem(storageKey, JSON.stringify(formData));
  }
  function updateTextarea(name, value) {
    const ta = document.querySelector(`textarea[name="${name}"]`);
    if (!ta) return;
    ta.value = value;
    markUnsaved(ta);
    saveField(name, value);
  }

  // 3a) Pro každé textarea:
  document.querySelectorAll("textarea[name]").forEach(ta => {
    // a) pokud máme v localStorage uloženou hodnotu, naplníme ji
    if (formData.hasOwnProperty(ta.name)) {
      ta.value = formData[ta.name];
      markUnsaved(ta);
    }
    // b) při psaní se hned ukládá a barví červeně
    ta.addEventListener("input", () => {
      markUnsaved(ta);
      saveField(ta.name, ta.value);
    });
  });

  // 3b) Pro každý klíč začínající na hidden- v formData
  Object.entries(formData).forEach(([fieldName, flag]) => {
    if (!fieldName.startsWith('hidden-')) return;

    const idSafe = fieldName.slice('hidden-'.length);
    const img    = form.elements[`obrazek-${idSafe}`];
    const btnX   = form.elements[`tlacitkoX-${idSafe}`];
    const btnReload   = form.elements[`tlacitkoReload-${idSafe}`];

    if (flag === 'smazano') {
      if (img)  img.style.opacity       = 0.3;
      if (btnReload) btnReload.style.display      = 'flex';
      if (btnX) btnX.style.display      = 'none';
    } else {
      if (img)  img.style.opacity       = 1;
      if (btnReload) btnReload.style.display      = 'none';
      if (btnX) btnX.style.display      = 'flex';
    }
  });

  // 4) Zachytíme i kliknutí na všechna tlačítka "načíst ->"
  document.querySelectorAll('input[type="button"][value="načíst ->"]').forEach(btn => {
    btn.addEventListener("click", function() {
      const oncl = btn.getAttribute("onclick") || "";
      // vyparsujeme z inline-onclick cílové a zdrojové jméno políčka
      const m = oncl.match(
        /document\.formularauta\.([a-zA-Z0-9_]+)\.value=document\.formularauta\.([a-zA-Z0-9_]+)\.value/
      );
      if (m) {
        const [, destName, srcName] = m;
        const val = this.form[srcName].value;
        updateTextarea(destName, val);
      }
    });
  });

  form.addEventListener('submit', function() {
    // 1) vymažeme data z localStorage
    localStorage.removeItem(storageKey);
    // 2) odebereme červené označení ze všech textarea
    form.querySelectorAll('textarea.unsaved').forEach(ta => {
      ta.classList.remove('unsaved');
    });
    // formulář se normálně odešle na server…
  });

});

function previewInNewWindow(src) {
  // vypočítáme 80 % šířky a výšky obrazovky
  const w = Math.floor(window.screen.availWidth * 0.8);
  const h = Math.floor(window.screen.availHeight * 0.8);
  const features = `width=${w},height=${h},resizable=yes,scrollbars=yes`;
  const win = window.open('', '_blank', features);

  // do nového okna vypíšeme jen samotný obrázek
  win.document.write(`
    <html><head>
      <title>Náhled obrázku</title>
      <style>body,html{margin:0;padding:0;height:100%;overflow:auto;}
             img{display:block;width:100%;height:auto;}</style>
    </head><body>
      <img src="${src}" alt="Preview">
    </body></html>
  `);
  win.document.close();
}


</script>    
    

<style>
        table {
           
            max-width: 100%;         /* Maximální šířka 100 % obrazovky */
            border-collapse: collapse; /* Spojí okraje buněk */
        }
        
        .hlavnitabulkaeditace {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background-color: white;
            margin-left: auto; 
            margin-right: auto; 
            margin-top: auto;
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden; 
            border: none; 
   			
        }
        .hlavnitabulkaeditace th,
        .hlavnitabulkaeditace td {
            padding: 8px;
            border: none;
            word-wrap: break-word;
        }
        .hlavnitabulkaeditace td + td,
        .hlavnitabulkaeditace th + th {
            border-left: 1px solid black;
        }
        .hlavnitabulkaeditace tr + tr td {
            border-top: 1px solid black;
        }
        
        .tabulka-fotky {
            background-color: white;
            margin-left: auto;
            margin-right: auto;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden;
            border: none;
            padding-bottom: 8px;
        }
        .tabulka-fotky th,
		.tabulka-fotky td {
			padding: 8px;
            word-wrap: break-word;
            max-width: none;
			border: none !important;
            white-space: nowrap;
		}
        .tabulka-prihlasen {
            background-color: white;
            margin-left: 5px;; 
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden;
        }
        .tabulka-prihlasen th,
		.tabulka-prihlasen td {
			padding: 8px;
            word-wrap: break-word;
            max-width: none;
			border: none;
            white-space: nowrap;
		}
        .tabulka-ikony {
            background-color: white;
            margin-left: 5px;; 
            margin-top: 5px;
            font-size: 16px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.5);
   			border-radius: 6px;
   			overflow: hidden;
        }
		.tabulka-ikony th, 
		.tabulka-ikony td {
			padding: 8px;
            word-wrap: break-word;
            max-width: none;
			border: none;
            white-space: nowrap;
		}
              
        .barevnost1 {
            background-color: #f5f5f5;
        }
        .barevnost2 {
            background-color: #fffff0;
        }
        #drop-area {
            border: 2px dashed #ccc;
            border-radius: 20px;
            width: 300px;
            margin: 50px auto;
            padding: 20px;
            text-align: center;
        }

        #drop-area.hover {
            border-color: #333;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }

        textarea.unsaved {
            color: red;
        }
        .QR-box {
            display: inline-flex; 
            align-items: center;
            gap: 8px;
        }
        .QR-box img {
            max-width: 180px;          
           
        }
        .QR-box button {
            padding: 6px 12px;
}

        
    </style>

</head>




<body style="background-image: url(pozadi-auticka5.png); background-position: top left; background-repeat: repeat;  background-size: 40%;">

<?php



include ("Uloz_auta.php");
include ("Smaz.php");

include("phpqrcode/qrlib.php");


if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
    if ($prihlasenOpravneni <= 2){
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
    else {
        header("Location: Prihlaseni.php");
    }
   

}

function zapisDoLogu($textzaznamu) {
    // složka pro logy
    $logDir = __DIR__ . '/Logy';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);

    }

    $datumlogu = date('Y-m-d');
    $logFile   = "{$logDir}/log-{$datumlogu}.log";

    // připravíme řádek
    $user = 
    (isset($_SESSION['uzivatel']['jmeno'])
        ? $_SESSION['uzivatel']['jmeno']
        : 'Neznámý')
  . ' '
  . (isset($_SESSION['uzivatel']['prijmeni'])
        ? $_SESSION['uzivatel']['prijmeni']
        : '');

    $time = date('Y-m-d H:i:s');
    $line = "[$time] ($user) $textzaznamu" . PHP_EOL;

    // přidáme na konec souboru (vytvoří, pokud neexistuje) a uzamkneme
    file_put_contents(
        $logFile,
        $line,
        FILE_APPEND | LOCK_EX
    );
}
?>


<table class="tabulka-ikony">
<tr>
<td>
<div>
<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<a href="Uvodni.php"><img width="50" height="50" src="Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku"></a>
</div>
</td>
</tr>
</table>


<?php

if(isset($_GET["byloduplikovano"])){
    echo "<script>window.alert(\"Duplikováno a uloženo!\");</script>";
}

if (isset($_REQUEST["uloz"])) {

    $hledaniIdPolozky = mysqli_query($connection, "SELECT * FROM auta WHERE id='$polozka'");
    $nalezeneIdPolozky = mysqli_fetch_array($hledaniIdPolozky);

    if (!$nalezeneIdPolozky) {
        echo "<script>window.alert(\"Položka neexistuje, není uloženo!\");</script>";
        //ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $polozka, $connection);
        echo "<script>window.close();</script>";
    }
    else {

    $adresarSlozkyFotekTempPolozky = "Fotky/temp/".$polozka."/";


    foreach ($_POST as $name => $value) {
        $upraveny = str_replace('_', '.', $name);
        if (strpos($upraveny, 'hidden-') === 0 && $value === 'smazano') {
            // získáme čisté jméno souboru
            $filename = substr($upraveny, strlen('hidden-'));
            $fullpath = $adresarSlozkyFotekTempPolozky . $filename;
            // pokud existuje, smažeme
            if (is_file($fullpath)) {
                unlink($fullpath);
            }
        }
    }

	
		Uloz($polozka, $connection, $prihlasenId);
		?><script>window.alert("Uloženo!");</script>
		<?php
		ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $polozka, $connection);
}

}

elseif (isset($_REQUEST["smaz"]) || isset($_GET['smazpolozku'])){

	if (($_REQUEST["potvrzeniMazani"] == "potvrzeno") || isset($_GET['smazpolozku'])){
		Smaz ($polozka, $connection);
		?><script>window.alert("Mazání bylo úspěšně provedeno.");</script>
		<?php
		echo "<script>window.close();</script>";
		}
		else{?><script>window.alert("Mazání bylo zrušeno.");</script>
		<?php
		ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $polozka, $connection);
		}	


}

else{

ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $polozka, $connection);
}


function ZobrazeniFormulare ($prihlasenId, $prihlasenOpravneni, $polozka, $connection){


echo "<form method=\"post\" action=\"Auta-edit.php?polozka=".$polozka."\" name=\"formularauta\">";

	$hodnotaHledaniAut = mysqli_query($connection, "SELECT * FROM auta WHERE id='$polozka'");
	if (!$hodnotaHledaniAut) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}
	$nalezHledaniAut = mysqli_fetch_array($hodnotaHledaniAut);

        $parts = [];
        $parts[] = "Byl načten záznam:";
        $parts[] = "firma='"            . $nalezHledaniAut['firma']           . "'";
        $parts[] = "firma2='"           . $nalezHledaniAut['firma2']          . "'";
        $parts[] = "cislo='"            . $nalezHledaniAut['cislo']           . "'";
        $parts[] = "nazev='"            . $nalezHledaniAut['nazev']           . "'";
        $parts[] = "upresneni='"        . $nalezHledaniAut['upresneni']       . "'";
        $parts[] = "barva1='"           . $nalezHledaniAut['barva1']          . "'";
        $parts[] = "barva2='"           . $nalezHledaniAut['barva2']          . "'";
        $parts[] = "barva3='"           . $nalezHledaniAut['barva3']          . "'";
        $parts[] = "barva4='"           . $nalezHledaniAut['barva4']          . "'";
        $parts[] = "barva5='"           . $nalezHledaniAut['barva5']          . "'";
        $parts[] = "serie='"            . $nalezHledaniAut['serie']           . "'";
        $parts[] = "zavod='"            . $nalezHledaniAut['zavod']           . "'";
        $parts[] = "startovnicislo='"   . $nalezHledaniAut['startovnicislo']  . "'";
        $parts[] = "tym='"              . $nalezHledaniAut['tym']             . "'";
        $parts[] = "reklama='"          . $nalezHledaniAut['reklama']         . "'";
        $parts[] = "jezdec1='"          . $nalezHledaniAut['jezdec1']         . "'";
        $parts[] = "jezdec2='"          . $nalezHledaniAut['jezdec2']         . "'";
        $parts[] = "jezdec3='"          . $nalezHledaniAut['jezdec3']         . "'";
        $parts[] = "rok='"              . $nalezHledaniAut['rok']            . "'";
        $parts[] = "cena='"             . $nalezHledaniAut['cena']            . "'";
        $parts[] = "popis='"            . $nalezHledaniAut['popis']           . "'";
        $parts[] = "poznamka='"         . $nalezHledaniAut['poznamka']        . "'";
        $parts[] = "umisteniauta='"     . $nalezHledaniAut['umisteniauta']    . "'";
        $parts[] = "umistenikrabicky='" . $nalezHledaniAut['umistenikrabicky']. "'";
        $parts[] = "mame='"              . $nalezHledaniAut['mame']            . "'";
        $parts[] = "pridano='"           . $nalezHledaniAut['pridano']         . "'";
        $parts[] = "id='"              . $nalezHledaniAut['id']            . "'";
        
    
        // spojím oddělovačem a pošlu do logu
        zapisDoLogu(implode(', ', $parts));


	$hodnotaHledaniFirmy = mysqli_query($connection, "SELECT * FROM autafirmy WHERE id IS NOT NULL ORDER BY firma");
	if (!$hodnotaHledaniFirmy) {
    die("Chyba při načítání dat: " . mysqli_error($connection));
	}
	$hodnotaHledaniZavody = mysqli_query($connection, "SELECT * FROM autazavody WHERE id IS NOT NULL ORDER BY zavod");
	$hodnotaHledaniSerie = mysqli_query($connection, "SELECT * FROM autaserie WHERE id IS NOT NULL ORDER BY serie");
	$hodnotaHledaniBarvy = mysqli_query($connection, "SELECT * FROM autabarvy WHERE id IS NOT NULL ORDER BY barva");

# -----------NADPIS----------

echo "<table class=\"hlavnitabulkaeditace\">";
echo "<tr>";
echo "<th colspan=\"4\">EDITACE ZÁZNAMU (vytvořen: ". $nalezHledaniAut['pridano'] .")</th>";
echo "</tr>";



# ----------- FIRMA ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Firma:</td>";
echo "<td rowspan=\"2\"><select name=\"selectfirmy\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    while ($nalezHledaniFirmy = mysqli_fetch_array($hodnotaHledaniFirmy)){
        echo "<option value=\"" .$nalezHledaniFirmy["firma"] ."\">".$nalezHledaniFirmy["firma"]."</option>";
    }
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputfirmy.value=document.formularauta.selectfirmy.value;\"></td>";
if (isset($_REQUEST["inputfirmy"]) && $_REQUEST["inputfirmy"]) {
    echo "<td><textarea name=\"inputfirmy\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputfirmy"] . "</textarea></td>";
} elseif ($nalezHledaniAut["firma"]) {
    echo "<td><textarea name=\"inputfirmy\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["firma"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputfirmy\" oninput=\"this.value = this.value.slice(0,80;\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- FIRMA č. 2 (nebo úpravce) ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Firma č. 2 (nebo úpravce):</td>";

echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputfirmy2.value=document.formularauta.selectfirmy.value;\"></td>";
if (isset($_REQUEST["inputfirmy2"]) && $_REQUEST["inputfirmy2"]) {
    echo "<td><textarea name=\"inputfirmy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputfirmy2"] . "</textarea></td>";
} elseif ($nalezHledaniAut["firma2"]) {
    echo "<td><textarea name=\"inputfirmy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["firma2"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputfirmy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- ČÍSLO ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Číslo:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputcisla"]) && $_REQUEST["inputcisla"]) {
    echo "<td><textarea name=\"inputcisla\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputcisla"] . "</textarea></td>";
} elseif ($nalezHledaniAut["cislo"]) {
    echo "<td><textarea name=\"inputcisla\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["cislo"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputcisla\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- NÁZEV ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Název:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputnazev"]) && $_REQUEST["inputnazev"]) {
    echo "<td><textarea name=\"inputnazev\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputnazev"] . "</textarea></td>";
} elseif ($nalezHledaniAut["nazev"]) {
    echo "<td><textarea name=\"inputnazev\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["nazev"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputnazev\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- UPŘESNENÍ ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Upřesnění (např. generace, taxi, hasiči apod.):</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputupresneni"]) && $_REQUEST["inputupresneni"]) {
    echo "<td><textarea name=\"inputupresneni\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputupresneni"] . "</textarea></td>";
} elseif ($nalezHledaniAut["upresneni"]) {
    echo "<td><textarea name=\"inputupresneni\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["upresneni"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputupresneni\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- BARVA ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Barva:</td>";
echo "<td  rowspan=\"5\" valign=\"top\"><select name=\"selectbarvy\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    while ($nalezHledaniBarvy = mysqli_fetch_array($hodnotaHledaniBarvy)) {
        echo "<option value=\"" . $nalezHledaniBarvy["barva"] . "\">" . $nalezHledaniBarvy["barva"] . "</option>";
    }
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputbarvy1.value=document.formularauta.selectbarvy.value;\"></td>";
if (isset($_REQUEST["inputbarvy1"]) && $_REQUEST["inputbarvy1"]) {
    echo "<td><textarea name=\"inputbarvy1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputbarvy1"] . "</textarea></td>";
} elseif ($nalezHledaniAut["barva1"]) {
    echo "<td><textarea name=\"inputbarvy1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["barva1"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputbarvy1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\"><td></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputbarvy2.value=document.formularauta.selectbarvy.value;\"></td>";
if (isset($_REQUEST["inputbarvy2"]) && $_REQUEST["inputbarvy2"]) {
    echo "<td><textarea name=\"inputbarvy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputbarvy2"] . "</textarea></td>";
} elseif ($nalezHledaniAut["barva2"]) {
    echo "<td><textarea name=\"inputbarvy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["barva2"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputbarvy2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\"><td></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputbarvy3.value=document.formularauta.selectbarvy.value;\"></td>";
if (isset($_REQUEST["inputbarvy3"]) && $_REQUEST["inputbarvy3"]) {
    echo "<td><textarea name=\"inputbarvy3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputbarvy3"] . "</textarea></td>";
} elseif ($nalezHledaniAut["barva3"]) {
    echo "<td><textarea name=\"inputbarvy3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["barva3"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputbarvy3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\"><td></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputbarvy4.value=document.formularauta.selectbarvy.value;\"></td>";
if (isset($_REQUEST["inputbarvy4"]) && $_REQUEST["inputbarvy4"]) {
    echo "<td><textarea name=\"inputbarvy4\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputbarvy4"] . "</textarea></td>";
} elseif ($nalezHledaniAut["barva4"]) {
    echo "<td><textarea name=\"inputbarvy4\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["barva4"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputbarvy4\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\"><td></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputbarvy5.value=document.formularauta.selectbarvy.value;\"></td>";
if (isset($_REQUEST["inputbarvy5"]) && $_REQUEST["inputbarvy5"]) {
    echo "<td><textarea name=\"inputbarvy5\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputbarvy5"] . "</textarea></td>";
} elseif ($nalezHledaniAut["barva5"]) {
    echo "<td><textarea name=\"inputbarvy5\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["barva5"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputbarvy5\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- ZÁVOD ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Závod:</td>";
echo "<td><select name=\"selectzavod\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    while ($nalezHledaniZavody = mysqli_fetch_array($hodnotaHledaniZavody)) {
        echo "<option value=\"" . $nalezHledaniZavody["zavod"] . "\">" . $nalezHledaniZavody["zavod"] . "</option>";
    }
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputzavod.value=document.formularauta.selectzavod.value;\"></td>";
if (isset($_REQUEST["inputzavod"]) && $_REQUEST["inputzavod"]) {
    echo "<td><textarea name=\"inputzavod\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputzavod"] . "</textarea></td>";
} elseif ($nalezHledaniAut["zavod"]) {
    echo "<td><textarea name=\"inputzavod\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["zavod"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputzavod\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- SERIE ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Série:</td>";
echo "<td><select name=\"selectserie\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    while ($nalezHledaniSerie = mysqli_fetch_array($hodnotaHledaniSerie)) {
        echo "<option value=\"" . $nalezHledaniSerie["serie"] . "\">" . $nalezHledaniSerie["serie"] . "</option>";
    }
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputserie.value=document.formularauta.selectserie.value;\"></td>";
if (isset($_REQUEST["inputserie"]) && $_REQUEST["inputserie"]) {
    echo "<td><textarea name=\"inputserie\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputserie"] . "</textarea></td>";
} elseif ($nalezHledaniAut["serie"]) {
    echo "<td><textarea name=\"inputserie\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["serie"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputserie\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- STARTOVNÍ ČÍSLO ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Startovní číslo:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputstartovnicislo"]) && $_REQUEST["inputstartovnicislo"]) {
    echo "<td><textarea name=\"inputstartovnicislo\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputstartovnicislo"] . "</textarea></td>";
} elseif ($nalezHledaniAut["startovnicislo"]) {
    echo "<td><textarea name=\"inputstartovnicislo\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["startovnicislo"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputstartovnicislo\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- TÝM ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Tým:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputtym"]) && $_REQUEST["inputtym"]) {
    echo "<td><textarea name=\"inputtym\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputtym"] . "</textarea></td>";
} elseif ($nalezHledaniAut["tym"]) {
    echo "<td><textarea name=\"inputtym\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["tym"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputtym\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- REKLAMA ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Reklama:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputreklama"]) && $_REQUEST["inputreklama"]) {
    echo "<td><textarea name=\"inputreklama\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputreklama"] . "</textarea></td>";
} elseif ($nalezHledaniAut["reklama"]) {
    echo "<td><textarea name=\"inputreklama\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["reklama"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputreklama\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- JEZDEC ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Jezdci:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputjezdec1"]) && $_REQUEST["inputjezdec1"]) {
    echo "<td><textarea name=\"inputjezdec1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputjezdec1"] . "</textarea></td>";
} elseif ($nalezHledaniAut["jezdec1"]) {
    echo "<td><textarea name=\"inputjezdec1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["jezdec1"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputjezdec1\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\">";
echo "<td></td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputjezdec2"]) && $_REQUEST["inputjezdec2"]) {
    echo "<td><textarea name=\"inputjezdec2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputjezdec2"] . "</textarea></td>";
} elseif ($nalezHledaniAut["jezdec2"]) {
    echo "<td><textarea name=\"inputjezdec2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["jezdec2"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputjezdec2\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

echo "<tr class=\"barevnost1\">";
echo "<td></td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputjezdec3"]) && $_REQUEST["inputjezdec3"]) {
    echo "<td><textarea name=\"inputjezdec3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputjezdec3"] . "</textarea></td>";
} elseif ($nalezHledaniAut["jezdec3"]) {
    echo "<td><textarea name=\"inputjezdec3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["jezdec3"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputjezdec3\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- ROK ---------------
$rok = StrFTime("%Y", Time());			
echo "<tr class=\"barevnost2\">";
echo "<td>Rok:</td>";
echo "<td><select name=\"selectroku\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    for($rokfor = 1970; $rokfor <= $rok; $rokfor++){
        echo "<option value=\"" .$rokfor ."\">".$rokfor."</option>";
    }
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputroku.value=document.formularauta.selectroku.value;\"></td>";

if (isset($_REQUEST["inputroku"]) && $_REQUEST["inputroku"]) {
    echo "<td><textarea name=\"inputroku\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '').slice(0,4);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputroku"] . "</textarea></td>";
} elseif ($nalezHledaniAut["rok"]) {
    echo "<td><textarea name=\"inputroku\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '').slice(0,4);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["rok"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputroku\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '').slice(0,4);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- CENA ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Cena:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputceny"]) && $_REQUEST["inputceny"]) {
    echo "<td><textarea name=\"inputceny\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '');\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputceny"] . "</textarea></td>";
} elseif ($nalezHledaniAut["cena"]) {
    echo "<td><textarea name=\"inputceny\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '');\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["cena"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputceny\" inputmode=\"numeric\" oninput=\"this.value = this.value.replace(/\D/g, '');\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- POPIS (pro veřejnost) ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Popis (pro veřejnost):</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputpopis"]) && $_REQUEST["inputpopis"]) {
    echo "<td><textarea name=\"inputpopis\" style=\"width:300px; height:100px;\">" . $_REQUEST["inputpopis"] . "</textarea></td>";
} elseif (!empty($nalezHledaniAut["popis"])) {
    echo "<td><textarea name=\"inputpopis\" style=\"width:300px; height:100px;\">" . $nalezHledaniAut["popis"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputpopis\" style=\"width:300px; height:100px;\"></textarea></td>";
}
echo "</tr>";

# ----------- POZNÁMKA ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Poznámka:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputpoznamka"]) && $_REQUEST["inputpoznamka"]) {
    echo "<td><textarea name=\"inputpoznamka\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputpoznamka"] . "</textarea></td>";
} elseif ($nalezHledaniAut["poznamka"]) {
    echo "<td><textarea name=\"inputpoznamka\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["poznamka"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputpoznamka\" oninput=\"this.value = this.value.slice(0,255);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- UMÍSTĚNÍ AUTA ---------------			
echo "<tr class=\"barevnost2\">";
echo "<td>Umístění auta:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputumisteniauta"]) && $_REQUEST["inputumisteniauta"]) {
    echo "<td><textarea name=\"inputumisteniauta\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputumisteniauta"] . "</textarea></td>";
} elseif ($nalezHledaniAut["umisteniauta"]) {
    echo "<td><textarea name=\"inputumisteniauta\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["umisteniauta"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputumisteniauta\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- UMÍSTĚNÍ KRABIČKY ---------------			
echo "<tr class=\"barevnost1\">";
echo "<td>Umístění krabičky:</td>";
echo "<td></td>";
echo "<td></td>";
if (isset($_REQUEST["inputumistenikrabicky"]) && $_REQUEST["inputumistenikrabicky"]) {
    echo "<td><textarea name=\"inputumistenikrabicky\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $_REQUEST["inputumistenikrabicky"] . "</textarea></td>";
} elseif ($nalezHledaniAut["umistenikrabicky"]) {
    echo "<td><textarea name=\"inputumistenikrabicky\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\">" . $nalezHledaniAut["umistenikrabicky"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputumistenikrabicky\" oninput=\"this.value = this.value.slice(0,80);\" style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";

# ----------- MÁME / NEMÁME ---------------
echo "<tr class=\"barevnost2\">";
echo "<td>Máme / Nemáme:</td>";
echo "<td><select name=\"selectmame\">";
    echo "<option value=\"\">---vyber si položku---</option>";
    echo "<option value=\"ANO\">ANO</option>";
    echo "<option value=\"NE\">NE</option>";
echo "</select></td>";
echo "<td><input type=\"Button\" value=\"načíst ->\" onclick=\"document.formularauta.inputmame.value=document.formularauta.selectmame.value;\"></td>";
if (isset($_REQUEST["inputmame"]) && $_REQUEST["inputmame"]) {
    echo "<td><textarea name=\"inputmame\" readonly style=\"width:300px; height:25px;\">" . $_REQUEST["inputmame"] . "</textarea></td>";
} elseif ($nalezHledaniAut["mame"]) {
    echo "<td><textarea name=\"inputmame\" readonly style=\"width:300px; height:25px;\">" . $nalezHledaniAut["mame"] . "</textarea></td>";
} else {
    echo "<td><textarea name=\"inputmame\" readonly style=\"width:300px; height:25px;\"></textarea></td>";
}
echo "</tr>";


# ---------QR -----------

echo "<tr class=\"barevnost1\"><td>QR:<td></td><td></td>";

        $cestaQRauta = "QR-auta/".$nalezHledaniAut["id"].".png";
		
		if(!file_exists($cestaQRauta)){
			QRcode::png($nalezHledaniAut["id"], $cestaQRauta);
		}
		echo "<td><div class=\"QR-box\"><img src='".$cestaQRauta."'>";
        echo "<button onclick=\"printQR('".$cestaQRauta."')\">Tisk</button>"; 
echo "</div></td></tr>";




# ---------FOTKY -----------

echo "<tr><td colspan=\"4\">";


echo "<table class=\"tabulka-fotky\">";
echo "<tr>";
echo "<td><div id=\"message\" style=\"display: none; color: green; font-size: 20px; font-weight: bold;\">
  Úspěšně nahráno!
</div>
    <div id=\"drop-area\">
  <h3>Přetáhněte sem soubor</h3>
  <input type=\"file\" id=\"fileElem\" multiple accept=\"*\" style=\"display:none\">
  <label class=\"button\" for=\"fileElem\">Vyberte soubor ze složky</label>
</div></td></tr></table></td></tr><tr><td colspan=\"4\"><table class=\"tabulka-fotky\"><tr><td colspan=\"4\"><div align=\"left\">FOTKY:</div></td></tr><tr>";
$slozkapolozky = dir("Fotky/temp/".$polozka);
$pocetFotekKZobrazeni = 0;
while($fotkavypis=$slozkapolozky->read()) { 
	if ($fotkavypis=="." || $fotkavypis=="..") continue; 
	    $fotkavypisbeztecky = str_replace('.','_', $fotkavypis);
        
	    
	    echo "<td valign='top'><div style='position: relative; display: inline-block; box-shadow: 0 2px 15px rgba(0,0,0,0.5); border-radius: 6px;'><img src=\"Fotky/temp/$polozka/$fotkavypis\" name=\"obrazek-".$fotkavypisbeztecky."\" style=\"max-width: 180px\" onclick=\"previewInNewWindow(this.src)\"><textarea name='hidden-".$fotkavypisbeztecky."' style='display: none;'>ok</textarea>";
        echo "<input
       type='button'
       name='tlacitkoReload-".$fotkavypisbeztecky."'
       value='&#x21BB;'
       style='
         position: absolute;
         top: 5px;
         right: 5px;
         width: 20px;
         height: 20px;
         background-color: blue;
         color: white;
         border: none;
         font-size: 16px;
         display: none;
         align-items: center;
         justify-content: center;
         cursor: pointer;'
                  onmouseover=\"this.style.backgroundColor='darkblue';\" onmouseout=\"this.style.backgroundColor='blue';\" 
                  onclick=\"
                (function(){
                  var ta = document.formularauta.elements['hidden-".$fotkavypisbeztecky."'];
                  ta.value = 'ok';
                  ta.dispatchEvent(new Event('input'));
                  var obrazek = document.formularauta.elements['obrazek-".$fotkavypisbeztecky."'];
                  obrazek.style.opacity = 1;
                  var tlacitkoX = document.formularauta.elements['tlacitkoX-".$fotkavypisbeztecky."'];
                  tlacitkoX.style.display = 'flex';
                  var tlacitkoReload = document.formularauta.elements['tlacitkoReload-".$fotkavypisbeztecky."'];
                  tlacitkoReload.style.display = 'none';
                })();
              \"
            >";
            echo "<input
       type='button'
       name='tlacitkoX-".$fotkavypisbeztecky."'
       value='×'
       style='
         position: absolute;
         top: 5px;
         right: 5px;
         width: 20px;
         height: 20px;
         background-color: red;
         color: white;
         border: none;
         font-size: 16px;
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;'
                  onmouseover=\"this.style.backgroundColor='darkred';\" onmouseout=\"this.style.backgroundColor='red';\" 
                  onclick=\"
                (function(){
                  var ta = document.formularauta.elements['hidden-".$fotkavypisbeztecky."'];
                  ta.value = 'smazano';
                  ta.dispatchEvent(new Event('input'));
                  var obrazek = document.formularauta.elements['obrazek-".$fotkavypisbeztecky."'];
                  obrazek.style.opacity = 0.3;
                  var tlacitkoReload = document.formularauta.elements['tlacitkoReload-".$fotkavypisbeztecky."'];
                  tlacitkoReload.style.display = 'flex';
                  var tlacitkoX = document.formularauta.elements['tlacitkoX-".$fotkavypisbeztecky."'];
                  tlacitkoX.style.display = 'none';
                })();
              \"
            >";
          echo "</div></td>";
          $pocetFotekKZobrazeni = $pocetFotekKZobrazeni + 1;
          if ($pocetFotekKZobrazeni == 5){
              echo "</tr><tr>";
              $pocetFotekKZobrazeni = 0;
          }
	
} 
$slozkapolozky->close(); 
echo "</tr>";
echo "</table>";
echo "</td></tr><tr><td colspan=\"4\">";



# ---------SUBMIT -----------

echo "<table class=\"tabulka-fotky\">";
echo "<div align=\"right\"><input type=\"Submit\" name=\"uloz\" value=\"Uložit záznam\" onmouseover=\"this.style.backgroundColor='darkgreen';\" onmouseout=\"this.style.backgroundColor='green';\"  style=\"background-color: green; color: white; border: none; padding: 10px 20px; cursor: pointer;\">";


?>

	<input type="hidden" name="potvrzeniMazani" value="nepotvrzeno" />
	<input type="submit" name="smaz" value="Smazat záznam!" onclick="dotazkmazani();" onmouseover="this.style.backgroundColor='darkred';" onmouseout="this.style.backgroundColor='red';" style="background-color: red; color: white; border: none; padding: 10px 20px; cursor: pointer;">
    </div>
</td>
</tr>
</table>
	
    </td>
    </tr>
    <table>
		
</form>
<?php
} #konec funkce ZobrazeniFormulare
?>
<div id="loadingMessage" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 24px; font-weight: bold; color: black; background: rgba(255,255,255,0.8); padding: 20px; border-radius: 10px; z-index: 999;">
  Načítám...
</div>

</body>
</html>

