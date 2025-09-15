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





// Získání filtrů z GET parametrů
$firmaFilter  = isset($_GET['firma'])  ? $_GET['firma']  : '';
$firma2Filter = isset($_GET['firma2'])  ? $_GET['firma2']  : '';
$cisloFilter  = isset($_GET['cislo'])  ? $_GET['cislo']  : '';
$nazevFilter  = isset($_GET['nazev'])    ? $_GET['nazev']    : '';
$upresneniFilter  = isset($_GET['upresneni'])  ? $_GET['upresneni']  : '';
$barvaFilter  = isset($_GET['barva'])  ? $_GET['barva']  : '';
$serieFilter  = isset($_GET['serie'])  ? $_GET['serie']  : '';
$zavodFilter  = isset($_GET['zavod'])  ? $_GET['zavod']  : '';
$startovnicisloFilter  = isset($_GET['startovnicislo'])  ? $_GET['startovnicislo']  : '';
$tymFilter   = isset($_GET['tym'])   ? $_GET['tym']   : '';
$reklamaFilter   = isset($_GET['reklama'])   ? $_GET['reklama']   : '';
$jezdecFilter = isset($_GET['jezdec']) ? $_GET['jezdec'] : '';
$rokFilter    = isset($_GET['rok'])    ? $_GET['rok']    : '';
$poznamkaFilter    = isset($_GET['poznamka'])    ? $_GET['poznamka']    : '';

$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';
$searchQueryLowerDiakritika = mb_strtolower(trim($searchQuery), 'UTF-8');
$searchQueryLower = iconv('UTF-8', 'ASCII//TRANSLIT', $searchQueryLowerDiakritika);

$filtraceSloupecFirma = isset($_GET['filtrfirma']) ? $_GET['filtrfirma'] : '';
$filtraceSloupecCislo = isset($_GET['filtrcislo']) ? $_GET['filtrcislo'] : '';
$filtraceSloupecNazev = isset($_GET['filtrnazev']) ? $_GET['filtrnazev'] : '';
$filtraceSloupecUpresneni = isset($_GET['filtrupresneni']) ? $_GET['filtrupresneni'] : '';
$filtraceSloupecBarva = isset($_GET['filtrbarva']) ? $_GET['filtrbarva'] : '';
$filtraceSloupecZavody = isset($_GET['filtrzavody']) ? $_GET['filtrzavody'] : '';
$filtraceSloupecSerie = isset($_GET['filtrserie']) ? $_GET['filtrserie'] : '';
$filtraceSloupecStartTymReklama = isset($_GET['filtrstarttymreklama']) ? $_GET['filtrstarttymreklama'] : '';
$filtraceSloupecJezdec = isset($_GET['filtrjezdec']) ? $_GET['filtrjezdec'] : '';
$filtraceSloupecRok = isset($_GET['filtrrok']) ? $_GET['filtrrok'] : '';


$queryFiltrFirma = "SELECT DISTINCT firma FROM autafirmy ORDER BY firma";
$resultFiltrFirma = mysqli_query($connection, $queryFiltrFirma);

$queryFiltrBarva = "SELECT DISTINCT barva FROM autabarvy ORDER BY barva";
$resultFiltrBarva = mysqli_query($connection, $queryFiltrBarva);

$queryFiltrSerie = "SELECT DISTINCT serie FROM autaserie ORDER BY serie";
$resultFiltrSerie = mysqli_query($connection, $queryFiltrSerie);

$queryFiltrZavody = "SELECT DISTINCT zavod FROM autazavody ORDER BY zavod";
$resultFiltrZavody = mysqli_query($connection, $queryFiltrZavody);


if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 4;
}



if (isset($_GET['zobrazpozadavky']) && $_GET['zobrazpozadavky'] == "ano" && $prihlasenOpravneni <= 2){
    $zobrazujpozadavky = "ano";
}
else {
    $zobrazujpozadavky = "ne";
}

if (isset($_GET['datumod']) && !empty($_GET['datumod'])) {
    $datumod = strtotime($_GET['datumod']);
}

if (isset($_GET['datumdo']) && !empty($_GET['datumdo'])) {
    $datumdo = strtotime($_GET['datumdo']);
}


if ($searchQueryLower === 'duplicity' && $zobrazujpozadavky == 'ne') {
    // Definujeme poddotaz, který vybere řádky, kde se ve sloupci cislo vyskytuje více než jednou
    $dupQueryPart = " WHERE cislo IN (
                        SELECT cislo 
                        FROM auta 
                        WHERE cislo <> '' 
                        GROUP BY cislo 
                        HAVING COUNT(*) > 1
                     ) ";
}

// Pokud se pracuje s duplicity, sestavíme dotazy podle tohoto nastavení
if (isset($dupQueryPart)) {
    $baseQuery = "FROM auta " . $dupQueryPart;
    $query = "SELECT * " . $baseQuery . " ORDER BY cislo";
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
} 

else {
    // Standardní vyhledávání
    $where = "WHERE id IS NOT NULL";

    if ($zobrazujpozadavky == 'ne'){
        $where .= " AND (
            mame = 'ANO' OR 
            mame = '' OR 
            mame IS NULL
        )";
    }

    else {
        $where .= " AND mame = 'NE'";
    }
    
    if (isset($datumod) && isset($datumdo)){
        $datumodpromysql = date('Y-m-d', $datumod);
        $datumdopromysql = date('Y-m-d', strtotime('+1 day', $datumdo));
        $where .= " AND pridano BETWEEN '$datumodpromysql' AND '$datumdopromysql'";
    }
    
    if (isset($filtraceSloupecFirma)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsFirma = preg_split('/\s+/', trim($filtraceSloupecFirma));
            foreach ($wordsFirma as $wordFirma) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeFirma = mysqli_real_escape_string($connection, $wordFirma);
            $where .= "(firma LIKE '%$wordSafeFirma%' OR firma2 LIKE '%$wordSafeFirma%')";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
    }
    
    if (isset($filtraceSloupecCislo)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsCislo = preg_split('/\s+/', trim($filtraceSloupecCislo));
            foreach ($wordsCislo as $wordCislo) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeCislo = mysqli_real_escape_string($connection, $wordCislo);
            $where .= "cislo LIKE '%$wordSafeCislo%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
    }
    
    if (isset($filtraceSloupecNazev)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsNazev = preg_split('/\s+/', trim($filtraceSloupecNazev));
            foreach ($wordsNazev as $wordNazev) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeNazev = mysqli_real_escape_string($connection, $wordNazev);
            $where .= "nazev LIKE '%$wordSafeNazev%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }
    
    if (isset($filtraceSloupecUpresneni)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsUpresneni = preg_split('/\s+/', trim($filtraceSloupecUpresneni));
            foreach ($wordsUpresneni as $wordUpresneni) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeUpresneni = mysqli_real_escape_string($connection, $wordUpresneni);
            $where .= "upresneni LIKE '%$wordSafeUpresneni%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }
    
    if (isset($filtraceSloupecBarva)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsBarva = preg_split('/\s+/', trim($filtraceSloupecBarva));
            foreach ($wordsBarva as $wordBarva) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeBarva = mysqli_real_escape_string($connection, $wordBarva);
            $where .= "(barva1 LIKE '%$wordSafeBarva%' 
              OR barva2 LIKE '%$wordSafeBarva%' 
              OR barva3 LIKE '%$wordSafeBarva%' 
              OR barva4 LIKE '%$wordSafeBarva%' 
              OR barva5 LIKE '%$wordSafeBarva%')";

            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";

        
    }
    
    if (isset($filtraceSloupecZavody)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsZavody = preg_split('/\s+/', trim($filtraceSloupecZavody));
            foreach ($wordsZavody as $wordZavody) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeZavody = mysqli_real_escape_string($connection, $wordZavody);
            $where .= "zavod LIKE '%$wordSafeZavody%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }
    
    if (isset($filtraceSloupecSerie)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsSerie = preg_split('/\s+/', trim($filtraceSloupecSerie));
            foreach ($wordsSerie as $wordSerie) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeSerie = mysqli_real_escape_string($connection, $wordSerie);
            $where .= "serie LIKE '%$wordSafeSerie%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }

    if (isset($filtraceSloupecStartTymReklama)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsStartTymReklama = preg_split('/\s+/', trim($filtraceSloupecStartTymReklama));
            foreach ($wordsStartTymReklama as $wordStartTymReklama) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeStartTymReklama = mysqli_real_escape_string($connection, $wordStartTymReklama);
            $where .= "(startovnicislo LIKE '%$wordSafeStartTymReklama%' OR tym LIKE '%$wordSafeStartTymReklama%' OR reklama LIKE '%$wordSafeStartTymReklama%')";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }
    
    if (isset($filtraceSloupecJezdec)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsJezdec = preg_split('/\s+/', trim($filtraceSloupecJezdec));
            foreach ($wordsJezdec as $wordJezdec) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeJezdec = mysqli_real_escape_string($connection, $wordJezdec);
            $where .= "(jezdec1 LIKE '%$wordSafeJezdec%' OR jezdec2 LIKE '%$wordSafeJezdec%' OR jezdec3 LIKE '%$wordSafeJezdec%')";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }

    if (isset($filtraceSloupecRok)){
            $pocetZadani = 1;
            $where .= " AND (";
            $wordsRok = preg_split('/\s+/', trim($filtraceSloupecRok));
            foreach ($wordsRok as $wordRok) {
                if ($pocetZadani > 1){
                    $where .= " OR ";
                }
            $wordSafeRok = mysqli_real_escape_string($connection, $wordRok);
            $where .= "rok LIKE '%$wordSafeRok%'";
            $pocetZadani = $pocetZadani + 1;
            }
            $where .= ")";
        
    }


    if (!empty($searchQuery)) {
        // Rozdělení zadaného textu na jednotlivá slova
        $words = preg_split('/\s+/', trim($searchQuery));
        foreach ($words as $word) {
            $wordSafe = mysqli_real_escape_string($connection, $word);
            // Přidáme podmínku, že alespoň v jednom z relevantních sloupců musí být hledané slovo
            $where .= " AND (
                firma LIKE '%$wordSafe%' OR 
                firma2 LIKE '%$wordSafe%' OR 
                cislo LIKE '%$wordSafe%' OR 
                nazev LIKE '%$wordSafe%' OR 
                upresneni LIKE '%$wordSafe%' OR 
                barva1 LIKE '%$wordSafe%' OR 
                barva2 LIKE '%$wordSafe%' OR 
                barva3 LIKE '%$wordSafe%' OR 
                barva4 LIKE '%$wordSafe%' OR 
                barva5 LIKE '%$wordSafe%' OR 
                serie LIKE '%$wordSafe%' OR 
                zavod LIKE '%$wordSafe%' OR 
                startovnicislo LIKE '%$wordSafe%' OR 
                tym LIKE '%$wordSafe%' OR 
                reklama LIKE '%$wordSafe%' OR 
                jezdec1 LIKE '%$wordSafe%' OR 
                jezdec2 LIKE '%$wordSafe%' OR 
                jezdec3 LIKE '%$wordSafe%' OR 
                poznamka LIKE '%$wordSafe%' OR 
                rok LIKE '%$wordSafe%'
            )";
        }
    }
    $baseQuery = "FROM auta $where";
    $srovnani = isset($_GET['srovnani']) ? $_GET['srovnani'] : "firma";
    $query = "SELECT * " . $baseQuery . " ORDER BY " .$srovnani;
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
}

// Stránkování
$stranka = isset($_GET['stranka']) ? (int)$_GET['stranka'] : 1;
if ($stranka < 1) {
    $stranka = 1;
}
$limit = 100;
$offset = ($stranka - 1) * $limit;

// Zjištění celkového počtu záznamů pro paginaci
$countResult = mysqli_query($connection, $countQuery);
$countRow = mysqli_fetch_assoc($countResult);
$totalRecords = $countRow['total'];
$totalPages = ceil($totalRecords / $limit);

// Načtení dat pouze pro aktuální stránku (přidáme LIMIT a OFFSET)
$query .= " LIMIT $limit OFFSET $offset";
$result = mysqli_query($connection, $query);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mobile-styly.css" media="(max-width: 1199px)">
	<link rel="stylesheet" href="desktop-styly.css" media="(min-width: 1200px)">
    <title>Databaze aut</title>
    

    <script>
    // Funkce, která načte vybrané filtry a přesměruje na stránku s odpovídajícími GET parametry
    function applyFilters() {
    var firma           = document.getElementById('selectfirma').value;
    var firma2          = document.getElementById('selectfirma2').value;
    var cislo           = document.getElementById('selectcislo').value;
    var nazev           = document.getElementById('selectnazev').value;
    var upresneni       = document.getElementById('selectupresneni').value;
    var barva           = document.getElementById('selectbarva').value;
    var serie           = document.getElementById('selectserie').value;
    var zavod           = document.getElementById('selectzavod').value;
    var startovnicislo  = document.getElementById('selectstartovnicislo').value;
    var tym             = document.getElementById('selecttym').value;
    var reklama         = document.getElementById('selectreklama').value;
    var jezdec          = document.getElementById('selectjezdec').value;
    var rok             = document.getElementById('selectroku').value;
    var poznamka        = document.getElementById('selectpoznamka').value;
    
    var url = 'Auta-main.php?stranka=1';
    if(firma)          url += '&firma='           + encodeURIComponent(firma);
    if(firma2)         url += '&firma2='          + encodeURIComponent(firma2);
    if(cislo)          url += '&cislo='           + encodeURIComponent(cislo);
    if(nazev)          url += '&nazev='           + encodeURIComponent(nazev);
    if(upresneni)      url += '&upresneni='       + encodeURIComponent(upresneni);
    if(barva)          url += '&barva='           + encodeURIComponent(barva);
    if(serie)          url += '&serie='           + encodeURIComponent(serie);
    if(zavod)          url += '&zavod='           + encodeURIComponent(zavod);
    if(startovnicislo) url += '&startovnicislo='  + encodeURIComponent(startovnicislo);
    if(tym)            url += '&tym='             + encodeURIComponent(tym);
    if(reklama)        url += '&reklama='         + encodeURIComponent(reklama);
    if(jezdec)         url += '&jezdec='          + encodeURIComponent(jezdec);
    if(rok)            url += '&rok='             + encodeURIComponent(rok);
    if(poznamka)       url += '&poznamka='        + encodeURIComponent(poznamka);
    
    window.location.href = url;
}
    // Funkce pro tisk QR kódu
    function printQR(imageSrc) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Tisk QR kódu</title></head><body>');
        printWindow.document.write('<img src="' + imageSrc + '" style="width:300px;height:300px;">');
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
    
    function skryvaniQR() {
    var cells = document.querySelectorAll('.bunkaQR');
    cells.forEach(function(cell) {
        if (cell.style.display === 'none') {
            cell.style.display = '';
        } else {
            cell.style.display = 'none';
        }
    });
}
    
    </script>
    <script>
  document.addEventListener('visibilitychange', () => {
    // když se uživatel přepne *na* tento panel
    if (document.visibilityState === 'visible') {
      // např. znovu načíst
      location.reload();
    }
  });
</script>
<script>
		function dotazkmazani(polozkakmazani){
		dialogoveokno=window.confirm("Opravdu chcete smazat záznam?");
		if(dialogoveokno) window.open('Auta-edit.php?polozka=' + polozkakmazani + '&smazpolozku=1', '_blank');
		}
</script>

<script>
(function () {
  function addFirma(token) {
    var inp = document.getElementById('filtrfirma');
    if (!inp) return;
    token = (token || '').trim();
    if (!token) return;

    // prosté přidání s mezerou
    inp.value = inp.value ? (inp.value + ' ' + token) : token;

    inp.focus();
    // caret na konec (když typ=search, funguje v moderních prohlížečích)
    try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch(e){}
  }

  // delegace kliků (bez inline onclick)
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    addFirma(row.dataset.firma);
  });

  // klávesnice: Enter/mezerník na řádku
  document.addEventListener('keydown', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      addFirma(row.dataset.firma);
    }
  });
})();
</script>

<script>
(function () {
  function addBarva(token) {
    var inp = document.getElementById('filtrbarva');
    if (!inp) return;
    token = (token || '').trim();
    if (!token) return;

    // prosté přidání s mezerou
    inp.value = inp.value ? (inp.value + ' ' + token) : token;

    inp.focus();
    // caret na konec (když typ=search, funguje v moderních prohlížečích)
    try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch(e){}
  }

  // delegace kliků (bez inline onclick)
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    addBarva(row.dataset.barva);
  });

  // klávesnice: Enter/mezerník na řádku
  document.addEventListener('keydown', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      addBarva(row.dataset.barva);
    }
  });
})();
</script>

<script>
(function () {
  function addSerie(token) {
    var inp = document.getElementById('filtrserie');
    if (!inp) return;
    token = (token || '').trim();
    if (!token) return;

    // prosté přidání s mezerou
    inp.value = inp.value ? (inp.value + ' ' + token) : token;

    inp.focus();
    // caret na konec (když typ=search, funguje v moderních prohlížečích)
    try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch(e){}
  }

  // delegace kliků (bez inline onclick)
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    addSerie(row.dataset.serie);
  });

  // klávesnice: Enter/mezerník na řádku
  document.addEventListener('keydown', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      addSerie(row.dataset.serie);
    }
  });
})();
</script>

<script>
(function () {
  function addZavody(token) {
    var inp = document.getElementById('filtrzavody');
    if (!inp) return;
    token = (token || '').trim();
    if (!token) return;

    // prosté přidání s mezerou
    inp.value = inp.value ? (inp.value + ' ' + token) : token;

    inp.focus();
    // caret na konec (když typ=search, funguje v moderních prohlížečích)
    try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch(e){}
  }

  // delegace kliků (bez inline onclick)
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    addZavody(row.dataset.zavody);
  });

  // klávesnice: Enter/mezerník na řádku
  document.addEventListener('keydown', function (e) {
    var row = e.target.closest('.filtr-row');
    if (!row) return;
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      addZavody(row.dataset.zavody);
    }
  });
})();
</script>



</head>
<body>
<?php 

include("phpqrcode/qrlib.php");

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





?>

<table class="tabulka-ikony" id="zacatek">
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
if ($prihlasenOpravneni <= 2 ){
    echo "<table class=\"tabulka-ikony\"><tr><td><div style=\"margin-top: 2px; margin-bottom: 2px\"><input class='zaoblene-tlacitko' type='button' value='NOVÁ POLOŽKA' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" 
                  onclick=\"window.open('Auta-edit.php?polozka=nova', '_blank');\">";
    echo "<input class='zaoblene-tlacitko' type='button' value='IMPORT' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" 
                  onclick=\"window.open('Auta-import.php', '_blank');\"></div></td></tr></table>";
    #echo "<a href=\"Auta-edit.php?polozka=nova\" target=\"_blank\">Nová položka</a>";
}
?>


<div class="tabulka-hledani">

<!-- Filtrační formulář -->
<div class="search-box">
  <form class="search-form" method="get" action="Auta-main.php" autocomplete="off">
  <!-- 1) Fulltext -->
  <div class="search-main">Hledání:
    <input
      type="search"
      class="search-input"
      name="q"
      placeholder="Sem zadej klíčová slova (nebo 'duplicity')"
      value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
      autocomplete="off"
    >
  </div>

  <!-- 2) Datum od / do vedle sebe -->
  <div class="dates">Datum přidání:
    <input
      type="search"
      class="date-input"
      name="datumod"
      placeholder="Datum od"
      value="<?php echo htmlspecialchars($_GET['datumod'] ?? ''); ?>"
      autocomplete="off"
    >
    <input
      type="search"
      class="date-input"
      name="datumdo"
      placeholder="Datum do"
      value="<?php echo htmlspecialchars($_GET['datumdo'] ?? ''); ?>"
      autocomplete="off"
    >
  </div>
<details class="filtry-hledani-pozitivni">
<summary title="Zobrazit/skrýt filtry">Filtry</summary>
  <!-- 3) Mřížka všech filtrů (každý filtr = dlaždice .filter-field) -->
  <div class="filters-grid">

    <!-- Firma -->
    <div class="filter-field">
      <input
        id="filtrfirma"
        type="search"
        class="date-input"
        name="filtrfirma"
        placeholder="Filtr firem"
        value="<?php echo htmlspecialchars($_GET['filtrfirma'] ?? ''); ?>"
        autocomplete="off"
      />
      <details class="filtr-panel">
        <summary class="filtr-toggle" title="Zobrazit/skrýt výpis firem">Výběr firem</summary>
        <div class="filtracni-scroll">
          <table class="filtracnitabulka">
            <?php while ($row = mysqli_fetch_assoc($resultFiltrFirma)): 
              $firma    = (string)$row['firma'];
              $firmaTxt = htmlspecialchars($firma, ENT_NOQUOTES, 'UTF-8');
              $firmaAttr= htmlspecialchars($firma, ENT_QUOTES,   'UTF-8'); ?>
              <tr class="filtr-row" data-firma="<?php echo $firmaAttr; ?>"><td><?php echo $firmaTxt; ?></td></tr>
            <?php endwhile; ?>
          </table>
        </div>
      </details>
    </div>

    <!-- Číslo (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrcislo"
        type="search"
        class="date-input"
        name="filtrcislo"
        placeholder="Filtr čísla"
        value="<?php echo htmlspecialchars($_GET['filtrcislo'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>

    <!-- Název (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrnazev"
        type="search"
        class="date-input"
        name="filtrnazev"
        placeholder="Filtr názvu"
        value="<?php echo htmlspecialchars($_GET['filtrnazev'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>

    <!-- Upřesnění (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrupresneni"
        type="search"
        class="date-input"
        name="filtrupresneni"
        placeholder="Filtr upřesnění"
        value="<?php echo htmlspecialchars($_GET['filtrupresneni'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>

    <!-- Barvy + menu -->
    <div class="filter-field">
      <input
        id="filtrbarva"
        type="search"
        class="date-input"
        name="filtrbarva"
        placeholder="Filtr barev"
        value="<?php echo htmlspecialchars($_GET['filtrbarva'] ?? ''); ?>"
        autocomplete="off"
      />
      <details class="filtr-panel">
        <summary class="filtr-toggle" title="Zobrazit/skrýt výpis barev">Výběr barev</summary>
        <div class="filtracni-scroll">
          <table class="filtracnitabulka">
            <?php while ($row = mysqli_fetch_assoc($resultFiltrBarva)): 
              $barva    = (string)$row['barva'];
              $barvaTxt = htmlspecialchars($barva, ENT_NOQUOTES, 'UTF-8');
              $barvaAttr= htmlspecialchars($barva, ENT_QUOTES,   'UTF-8'); ?>
              <tr class="filtr-row" data-barva="<?php echo $barvaAttr; ?>"><td><?php echo $barvaTxt; ?></td></tr>
            <?php endwhile; ?>
          </table>
        </div>
      </details>
    </div>

    <!-- Série + menu -->
    <div class="filter-field">
      <input
        id="filtrserie"
        type="search"
        class="date-input"
        name="filtrserie"
        placeholder="Filtr série"
        value="<?php echo htmlspecialchars($_GET['filtrserie'] ?? ''); ?>"
        autocomplete="off"
      />
      <details class="filtr-panel">
        <summary class="filtr-toggle" title="Zobrazit/skrýt výpis serií">Výběr serií</summary>
        <div class="filtracni-scroll">
          <table class="filtracnitabulka">
            <?php while ($row = mysqli_fetch_assoc($resultFiltrSerie)):
              $serie    = (string)$row['serie'];
              $serieTxt = htmlspecialchars($serie, ENT_NOQUOTES, 'UTF-8');
              $serieAttr= htmlspecialchars($serie, ENT_QUOTES,   'UTF-8'); ?>
              <tr class="filtr-row" data-serie="<?php echo $serieAttr; ?>"><td><?php echo $serieTxt; ?></td></tr>
            <?php endwhile; ?>
          </table>
        </div>
      </details>
    </div>

    <!-- Závody + menu -->
    <div class="filter-field">
      <input
        id="filtrzavody"
        type="search"
        class="date-input"
        name="filtrzavody"
        placeholder="Filtr závodů"
        value="<?php echo htmlspecialchars($_GET['filtrzavody'] ?? ''); ?>"
        autocomplete="off"
      />
      <details class="filtr-panel">
        <summary class="filtr-toggle" title="Zobrazit/skrýt výpis závodů">Výběr závodů</summary>
        <div class="filtracni-scroll">
          <table class="filtracnitabulka">
            <?php while ($row = mysqli_fetch_assoc($resultFiltrZavody)):
              $zavod    = (string)$row['zavod'];
              $zavodTxt = htmlspecialchars($zavod, ENT_NOQUOTES, 'UTF-8');
              $zavodAttr= htmlspecialchars($zavod, ENT_QUOTES,   'UTF-8'); ?>
              <tr class="filtr-row" data-zavody="<?php echo $zavodAttr; ?>"><td><?php echo $zavodTxt; ?></td></tr>
            <?php endwhile; ?>
          </table>
        </div>
      </details>
    </div>

    <!-- Start.č./Tým/Reklama (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrstarttymreklama"
        type="search"
        class="date-input"
        name="filtrstarttymreklama"
        placeholder="Filtr st.č./tým/reklama"
        value="<?php echo htmlspecialchars($_GET['filtrstarttymreklama'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>

    <!-- Jezdec (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrjezdec"
        type="search"
        class="date-input"
        name="filtrjezdec"
        placeholder="Filtr jezdec"
        value="<?php echo htmlspecialchars($_GET['filtrjezdec'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>

    <!-- Rok (bez menu) -->
    <div class="filter-field">
      <input
        id="filtrrok"
        type="search"
        class="date-input"
        name="filtrrok"
        placeholder="Filtr rok"
        value="<?php echo htmlspecialchars($_GET['filtrrok'] ?? ''); ?>"
        autocomplete="off"
      />
    </div>
  </div>
</details>

  <!-- 4) Akce vpravo dole -->
  <div class="actions">
    <input type="hidden" name="zobrazpozadavky" value="<?php echo htmlspecialchars($zobrazujpozadavky); ?>">
    <button type="submit" class="btn-search">Hledat</button>
  </div>
</form>

</div>


</div>




<?php


$queryParams = [];
if (!empty($searchQuery)) {
    $queryParams['q'] = $searchQuery;
}
    $queryParams['zobrazpozadavky'] = $zobrazujpozadavky;
    if (isset($_GET['datumod']) && isset ($_GET['datumdo']) && !empty($_GET['datumod']) && !empty($_GET['datumdo'])){
        $queryParams['datumod'] = date('Y-m-d', $datumod);
        $queryParams['datumdo'] = date('Y-m-d', $datumdo);
    }
    

    
    
    
    $queryParams['filtrfirma'] = $filtraceSloupecFirma;
    $queryParams['filtrcislo'] = $filtraceSloupecCislo;
    $queryParams['filtrnazev'] = $filtraceSloupecNazev;
    $queryParams['filtrupresneni'] = $filtraceSloupecUpresneni;
    $queryParams['filtrbarva'] = $filtraceSloupecBarva;
    $queryParams['filtrzavody'] = $filtraceSloupecZavody;
    $queryParams['filtrserie'] = $filtraceSloupecSerie;
    $queryParams['filtrstarttymreklama'] = $filtraceSloupecStartTymReklama;
    $queryParams['filtrjezdec'] = $filtraceSloupecJezdec;
    $queryParams['filtrrok'] = $filtraceSloupecRok;
    
    $queryString = http_build_query($queryParams);
    
    
    
    
echo "<div style=\"font-size: 14px; text-align: left; padding-top: 0px;\">Počet nálezů: <b>" .$totalRecords ."</b>";
if (isset($datumod) && isset($datumdo)){echo " a zobrazené období: <b>".date('d.m.Y', $datumod) ."</b> až <b>".date('d.m.Y', $datumdo)."</b></div>";}

?>

<a href="#konec" class="fixed-arrow-dolu"><img src="sipka_dolu.jpg" width="30" height="30" title="Posun na konec stránky" style="opacity: 0.5;"></a>
<!-- Výpis dat v tabulce -->


<table class="hlavnitabulka">
    <thead>
    <tr>
        <th>Firma <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=firma'\">";?></th>
        <th>Číslo <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=cislo'\">";?></th>
        <th>Název <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=nazev'\">";?></th>
        <th>Upřesnění</th>
        <th>Barvy</th>
        <th>Série / Závod</th>
        <th>Start.č. / Tým / Reklama</th>
        <th>Jezdec</th>
        <th>Rok <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=rok'\">";?></th>
        <th>Cena</th>
        <th>QR
        <button onclick="skryvaniQR()">Skrýt/Zobrazit</button>
        </th>
        <th>Tisk QR</th>
        <?php if ($prihlasenOpravneni <= 2 ) { echo "<th>EDIT</th>"; } ?>
    </tr>
    </thead>
    <tbody>
    <?php
    while ($row = mysqli_fetch_assoc($result)) {
        #tento if-else ve finální verzi smazat:
            if ($row['mame'] == "ANO"){
                echo "<tr id=\"{$row['id']}\" class=\"zelenePozadi\">";
                
            }
            else {
                 echo "<tr id=\"{$row['id']}\">";
            }
       
        echo "<td>";
            $firmy = array_filter([$row['firma'], $row['firma2']]);
            echo implode(", ", $firmy);
        echo "</td>";
        echo "<td>{$row['cislo']}</td>";
        echo "<td>{$row['nazev']}</td>";
        echo "<td>{$row['upresneni']}</td>";
        echo "<td>";
            // Sestavení seznamu barev, pokud jsou nastaveny
            $barvy = array_filter([$row['barva1'], $row['barva2'], $row['barva3'], $row['barva4'], $row['barva5']]);
            echo implode(", ", $barvy);
        echo "</td>";
        echo "<td>";
            $zavody = array_filter([$row['serie'], $row['zavod']]);
            echo implode(", ", $zavody);
        echo "</td>";
        echo "<td>";
            $team = array_filter([$row['startovnicislo'], $row['tym'], $row['reklama']]);
            echo implode(", ", $team);
        echo "</td>";
        echo "<td>";
            $jezdec = array_filter([$row['jezdec1'], $row['jezdec2'], $row['jezdec3']]);
            echo implode(", ", $jezdec);
        echo "</td>";
        echo "<td>{$row['rok']}</td>";
        if ($prihlasenOpravneni <= 2 ){
            echo "<td>{$row['cena']}</td>";
        } else {
            echo "<td><i>nelze zobrazit</i></td>";
        }
        // QR kód
        $cestaQRauta = "QR-auta/{$row['id']}.png";
        if (!file_exists($cestaQRauta)) {
            QRcode::png($row['id'], $cestaQRauta);
        }
        echo "<td><img src='{$cestaQRauta}' alt='QR kód' class=\"bunkaQR\" style=\"display: none;\"></td>";
        echo "<td><input class='zaoblene-tlacitko' type='button' value='Tisk QR' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\"  onclick=\"printQR('{$cestaQRauta}')\"></td>";
        if ($prihlasenOpravneni <= 2 ){
            echo "<td style=\"word-wrap: normal; word-break: normal; white-space: nowrap;\"><div><input class='zaoblene-tlacitko' type='button' value='EDIT' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" 
                  onclick=\"window.open('Auta-edit.php?polozka={$row['id']}', '_blank');\"><input class='zaoblene-tlacitko' type='button' value='COPY' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" 
                  onclick=\"window.open('Auta-edit.php?polozka={$row['id']}&duplikace=1', '_blank');\"><input class='zaoblene-tlacitko-cervene' type='button' value='DEL' onmouseover=\"this.style.backgroundColor='darkred';\" onmouseout=\"this.style.backgroundColor='red';\" 
                  onclick=\"dotazkmazani({$row['id']});\"></div></td>";
        }
        echo "</tr>";
    }
    ?>
    </tbody>
</table>

<a href="#zacatek" class="fixed-arrow-nahoru"><img src="sipka_nahoru.jpg" width="30" height="30" title="Posun na začátek stránky" style="opacity: 0.5;"></a>
<!-- Stránkování -->
<div style="text-align: center; margin: 20px;" id="konec">
STRÁNKY:
<br>
<?php
// Při generování odkazů na jednotlivé stránky jsou do URL připojeny i aktuálně nastavené filtry.
$queryParams = [];
if (!empty($searchQuery)) {
    $queryParams['q'] = $searchQuery;
}
$queryParams['zobrazpozadavky'] = $zobrazujpozadavky;
if (isset($_GET['datumod']) && isset ($_GET['datumdo']) && !empty($_GET['datumod']) && !empty($_GET['datumdo'])){
        $queryParams['datumod'] = date('Y-m-d', $datumod);
        $queryParams['datumdo'] = date('Y-m-d', $datumdo);
    }
    $queryParams['filtrfirma'] = $filtraceSloupecFirma;
    $queryParams['filtrcislo'] = $filtraceSloupecCislo;
    $queryParams['filtrnazev'] = $filtraceSloupecNazev;
    $queryParams['filtrupresneni'] = $filtraceSloupecUpresneni;
    $queryParams['filtrbarva'] = $filtraceSloupecBarva;
    $queryParams['filtrzavody'] = $filtraceSloupecZavody;
    $queryParams['filtrserie'] = $filtraceSloupecSerie;
    $queryParams['filtrstarttymreklama'] = $filtraceSloupecStartTymReklama;
    $queryParams['filtrjezdec'] = $filtraceSloupecJezdec;
    $queryParams['filtrrok'] = $filtraceSloupecRok;

for ($a = 1; $a <= $totalPages; $a++) {
    $queryParams['stranka'] = $a;
    $queryString = http_build_query($queryParams);
    
    if ($a == $stranka){
        echo "<input type='button' value='{$a}' style='background-color: darkgrey; color: orange; border: 1px solid black; padding: 8px; cursor: pointer;'
      onmouseover=\"this.style.color='darkorange';\" onmouseout=\"this.style.color='orange';\" 
      onclick=\"window.location.href='Auta-main.php?{$queryString}'\">";

    }
    else {
         echo "<input type='button' value='{$a}' style='background-color: grey; color: white; border: none; padding: 5px; cursor: pointer;'
      onmouseover=\"this.style.color='orange';\" onmouseout=\"this.style.color='white';\" 
      onclick=\"window.location.href='Auta-main.php?{$queryString}'\">";
    }

 
}

?>
</div>
</body>
</html>
