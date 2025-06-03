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



if ($searchQueryLower === 'duplicity') {
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
} else {
    // Standardní vyhledávání
    $where = "WHERE id IS NOT NULL";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Databaze aut</title>
    <style>
        body, html {
            width: 100%;
            max-width: 100%;
        }
        table {
            max-width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            padding: 8px;
            border: 1px solid black;
            max-width:150px;
            word-wrap: break-word;
        }
        .fixed-arrow-nahoru {
            position: fixed;
            bottom: 20px; /* Vzdálenost od spodního okraje */
            left: 20px;  /* Vzdálenost od levého okraje */
            z-index: 1000; /* Zajišťuje, že bude nad ostatním obsahem */
        }
        .fixed-arrow-dolu {
            position: fixed;
            top: 150px; /* Vzdálenost od horního okraje */
            left: 20px;  /* Vzdálenost od levého okraje */
            z-index: 1000; /* Zajišťuje, že bude nad ostatním obsahem */
        }
        
        .zelenePozadi {
            background-color: #f0fff0;
        }

    </style>
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
</head>
<body>
<?php include("phpqrcode/qrlib.php");
if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = isset($_SESSION['uzivatel']['id']) ? $_SESSION['uzivatel']['id'] : 1234;
    $prihlasenJmeno     = isset($_SESSION['uzivatel']['jmeno']) ? $_SESSION['uzivatel']['jmeno'] : 'Jméno';
    $prihlasenPrijmeni  = isset($_SESSION['uzivatel']['prijmeni']) ? $_SESSION['uzivatel']['prijmeni'] : 'Příjmení';
    $prihlasenOpravneni = isset($_SESSION['uzivatel']['opravneni']) ? $_SESSION['uzivatel']['opravneni'] : 'Chyba oprávnění';
    echo "Přihlášen: <span style='color:green;'>".$prihlasenJmeno." ".$prihlasenPrijmeni."</span> s oprávněním: <span style='color:green;'>".$prihlasenOpravneni."</span><br>";

}



?>

<a href="Prihlaseni.php"><img width="50" height="50" src="Logout.png" name="Prihlasovaci stranka" title="Odhlásit se"></a>
<a href="Uvodni.php">
<img width="50" height="50" src="Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku">
</a>
<br>
<?php
if ($prihlasenOpravneni == "admin" || $prihlasenOpravneni == "moderator"){
    echo "<input type='button' value='NOVÁ POLOŽKA' style='background-color: orange; color: white; border: none; padding: 10px 20px; cursor: pointer; box-sizing: border-box;'
                  onmouseover=\"this.style.backgroundColor='darkorange';\" onmouseout=\"this.style.backgroundColor='orange';\" 
                  onclick=\"window.open('Auta-edit.php?polozka=nova', '_blank');\">";
    echo "<input type='button' value='IMPORT' style='background-color: darkviolet; color: white; border: none; padding: 10px 20px; margin-left: 5px; cursor: pointer; box-sizing: border-box;'
                  onmouseover=\"this.style.backgroundColor='purple';\" onmouseout=\"this.style.backgroundColor='darkviolet';\" 
                  onclick=\"window.open('Auta-import.php', '_blank');\">";
    #echo "<a href=\"Auta-edit.php?polozka=nova\" target=\"_blank\">Nová položka</a>";
}
?>
<!-- Filtrační formulář -->
<div style="text-align: center; font-size: 20px;" id="zacatek">
  <form method="get" action="Auta-main.php" autocomplete="off">
    <input type="text" name="q" placeholder="Zadejte klíčová slova (nebo 'duplicity')" 
           value="<?php echo htmlspecialchars(isset($_GET['q']) ? $_GET['q'] : ''); ?>" 
           style="width: 500px; font-size: 20px; background-color: #e0f8e0; padding: 10px 20px; box-sizing: border-box;" autocomplete="off">
    <input type="submit" value="Hledat" 
           style="background-color: green; color: white; border: none; padding: 10px 20px; font-size: 20px; cursor: pointer; box-sizing: border-box;" 
           onmouseover="this.style.backgroundColor='darkgreen';" 
           onmouseout="this.style.backgroundColor='green';">
  </form>
</div>

<br>
<a href="#konec" class="fixed-arrow-dolu"><img src="sipka_dolu.jpg" width="30" height="30" title="Posun na konec stránky" style="opacity: 0.5;"></a>
<?php
echo "Počet nálezů: " .$totalRecords;


$queryParams = [];
if (!empty($searchQuery)) {
    $queryParams['q'] = $searchQuery;
}

    $queryString = http_build_query($queryParams);
    

?>

<br>
<!-- Výpis dat v tabulce -->
<div style="max-width: 100%; overflow-x: auto;">
<table>
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
        <?php if ($prihlasenOpravneni == "admin" || $prihlasenOpravneni == "moderator") { echo "<th>EDIT</th>"; } ?>
    </tr>
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
        if ($prihlasenOpravneni == "admin" || $prihlasenOpravneni == "moderator"){
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
        echo "<td><button onclick=\"printQR('{$cestaQRauta}')\">Tisk QR</button></td>";
        if ($prihlasenOpravneni == "admin" || $prihlasenOpravneni == "moderator"){
            echo "<td><input type='button' value='EDIT' style='background-color: blue; color: white; border: none; padding: 10px 20px; cursor: pointer;'
                  onmouseover=\"this.style.backgroundColor='darkblue';\" onmouseout=\"this.style.backgroundColor='blue';\" 
                  onclick=\"window.open('Auta-edit.php?polozka={$row['id']}', '_blank');\"><input type='button' value='COPY' style='background-color: khaki; color: white; border: none; padding: 10px 20px; margin-left: 5px; cursor: pointer;'
                  onmouseover=\"this.style.backgroundColor='darkkhaki';\" onmouseout=\"this.style.backgroundColor='khaki';\" 
                  onclick=\"window.open('Auta-edit.php?polozka={$row['id']}&duplikace=1', '_blank');\"></td>";
        }
        echo "</tr>";
    }
    ?>
</table>
</div>
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

for ($a = 1; $a <= $totalPages; $a++) {
    $queryParams['stranka'] = $a;
    $queryString = http_build_query($queryParams);
    
    if ($a == $stranka){
        echo "<input type='button' value='{$a}' style='background-color: darkgrey; color: orange; border: 1px solid black; padding: 8px; cursor: pointer;'
      onmouseover=\"this.style.color='darkorange';\" onmouseout=\"this.style.color='orange';\" 
      onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=$srovnani'\">";

    }
    else {
         echo "<input type='button' value='{$a}' style='background-color: grey; color: white; border: none; padding: 5px; cursor: pointer;'
      onmouseover=\"this.style.color='orange';\" onmouseout=\"this.style.color='white';\" 
      onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=$srovnani'\">";
    }

 
}
?>
</div>
</body>
</html>
