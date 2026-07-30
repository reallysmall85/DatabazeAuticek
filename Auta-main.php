<?php
session_start();

// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

// 2) Připojení k databázi (mysqli)
include __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Nepodařilo se připojit k databázi: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

// Získání filtrů z GET parametrů
$firmaFilter  = $_GET['firma1']   ?? '';
$firma2Filter = $_GET['firma2']  ?? '';
$cisloFilter  = $_GET['cislo']   ?? '';
$nazevFilter  = $_GET['nazev']   ?? '';
$upresneniFilter = $_GET['upresneni'] ?? '';
$barvaFilter  = $_GET['barva']   ?? '';
$serieFilter  = $_GET['serie']   ?? '';
$zavodFilter  = $_GET['zavod']   ?? '';
$startovnicisloFilter = $_GET['startovnicislo'] ?? '';
$tymFilter    = $_GET['tym']     ?? '';
$reklamaFilter= $_GET['reklama'] ?? '';
$jezdecFilter = $_GET['jezdec']  ?? '';
$rokFilter    = $_GET['rok']     ?? '';
$poznamkaFilter = $_GET['poznamka'] ?? '';

$searchQuery = $_GET['q'] ?? '';
$searchQueryLowerDiakritika = mb_strtolower(trim($searchQuery), 'UTF-8');
$searchQueryLower = iconv('UTF-8', 'ASCII//TRANSLIT', $searchQueryLowerDiakritika);

$filtraceSloupecFirma = $_GET['filtrfirma'] ?? '';
$filtraceSloupecCislo = $_GET['filtrcislo'] ?? '';
$filtraceSloupecNazev = $_GET['filtrnazev'] ?? '';
$filtraceSloupecUpresneni = $_GET['filtrupresneni'] ?? '';
$filtraceSloupecBarva = $_GET['filtrbarva'] ?? '';
$filtraceSloupecZavody = $_GET['filtrzavody'] ?? '';
$filtraceSloupecSerie = $_GET['filtrserie'] ?? '';
$filtraceSloupecStartTymReklama = $_GET['filtrstarttymreklama'] ?? '';
$filtraceSloupecJezdec = $_GET['filtrjezdec'] ?? '';
$filtraceSloupecRok = $_GET['filtrrok'] ?? '';

$queryFiltrFirma = "SELECT DISTINCT firma FROM autafirmy ORDER BY firma";
$resultFiltrFirma = mysqli_query($connection, $queryFiltrFirma);

$queryFiltrBarva = "SELECT DISTINCT barva FROM autabarvy ORDER BY barva";
$resultFiltrBarva = mysqli_query($connection, $queryFiltrBarva);

$queryFiltrSerie = "SELECT DISTINCT serie FROM autaserie ORDER BY serie";
$resultFiltrSerie = mysqli_query($connection, $queryFiltrSerie);

$queryFiltrZavody = "SELECT DISTINCT zavod FROM autazavody ORDER BY zavod";
$resultFiltrZavody = mysqli_query($connection, $queryFiltrZavody);

if (isset($_SESSION['uzivatel'])) {
    $prihlasenId        = $_SESSION['uzivatel']['id']        ?? 1234;
    $prihlasenJmeno     = $_SESSION['uzivatel']['jmeno']     ?? 'Jméno';
    $prihlasenPrijmeni  = $_SESSION['uzivatel']['prijmeni']  ?? 'Příjmení';
    $prihlasenOpravneni = $_SESSION['uzivatel']['opravneni'] ?? 4;
}

if (isset($_GET['zobrazpozadavky']) && $_GET['zobrazpozadavky'] === "ano" && $prihlasenOpravneni <= 2){
    $zobrazujpozadavky = "ano";
} else {
    $zobrazujpozadavky = "ne";
}

if (!empty($_GET['datumod'])) {
    $datumod = strtotime($_GET['datumod']);
}
if (!empty($_GET['datumdo'])) {
    $datumdo = strtotime($_GET['datumdo']);
}

if ($searchQueryLower === 'duplicity' && $zobrazujpozadavky === 'ne') {
    // Vypiš duplicitní cislo (kromě prázdných)
    $dupQueryPart = " WHERE cislo IN (
                        SELECT cislo 
                        FROM auta 
                        WHERE cislo <> '' 
                        GROUP BY cislo 
                        HAVING COUNT(*) > 1
                     ) ";
}

if (isset($dupQueryPart)) {
    $baseQuery  = "FROM auta " . $dupQueryPart;
    $query      = "SELECT * " . $baseQuery . " ORDER BY cislo";
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
} else {
    // Standardní vyhledávání
    $where = "WHERE id IS NOT NULL";

    if ($zobrazujpozadavky === 'ne'){
        $where .= " AND (mame = 'ANO' OR mame = '' OR mame IS NULL)";
    } else {
        $where .= " AND mame = 'NE'";
    }

    if (isset($datumod) && isset($datumdo)){
        $datumodpromysql = date('Y-m-d', $datumod);
        $datumdopromysql = date('Y-m-d', strtotime('+1 day', $datumdo));
        $where .= " AND pridano BETWEEN '$datumodpromysql' AND '$datumdopromysql'";
    }

    // ---- Per-sloupcové filtry s COALESCE ----
    $tokensFirma = preg_split('/\s+/', trim($filtraceSloupecFirma), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensFirma)) {
        $or = [];
        foreach ($tokensFirma as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "(COALESCE(firma1,'') LIKE '%$w%' OR COALESCE(firma2,'') LIKE '%$w%')";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensCislo = preg_split('/\s+/', trim($filtraceSloupecCislo), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensCislo)) {
        $or = [];
        foreach ($tokensCislo as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "COALESCE(cislo,'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensNazev = preg_split('/\s+/', trim($filtraceSloupecNazev), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensNazev)) {
        $or = [];
        foreach ($tokensNazev as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "COALESCE(nazev,'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensUpresneni = preg_split('/\s+/', trim($filtraceSloupecUpresneni), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensUpresneni)) {
        $or = [];
        foreach ($tokensUpresneni as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "COALESCE(upresneni,'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensBarva = preg_split('/\s+/', trim($filtraceSloupecBarva), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensBarva)) {
        $or = [];
        foreach ($tokensBarva as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "(COALESCE(barva1,'') LIKE '%$w%' 
                  OR COALESCE(barva2,'') LIKE '%$w%' 
                  OR COALESCE(barva3,'') LIKE '%$w%' 
                  OR COALESCE(barva4,'') LIKE '%$w%' 
                  OR COALESCE(barva5,'') LIKE '%$w%')";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensZavody = preg_split('/\s+/', trim($filtraceSloupecZavody), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensZavody)) {
        $or = [];
        foreach ($tokensZavody as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "COALESCE(zavod,'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensSerie = preg_split('/\s+/', trim($filtraceSloupecSerie), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensSerie)) {
        $or = [];
        foreach ($tokensSerie as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "COALESCE(serie,'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensSTR = preg_split('/\s+/', trim($filtraceSloupecStartTymReklama), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensSTR)) {
        $or = [];
        foreach ($tokensSTR as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "(COALESCE(startovnicislo,'') LIKE '%$w%' OR COALESCE(tym,'') LIKE '%$w%' OR COALESCE(reklama,'') LIKE '%$w%')";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensJezdec = preg_split('/\s+/', trim($filtraceSloupecJezdec), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensJezdec)) {
        $or = [];
        foreach ($tokensJezdec as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            $or[] = "(COALESCE(jezdec1,'') LIKE '%$w%' OR COALESCE(jezdec2,'') LIKE '%$w%' OR COALESCE(jezdec3,'') LIKE '%$w%')";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    $tokensRok = preg_split('/\s+/', trim($filtraceSloupecRok), -1, PREG_SPLIT_NO_EMPTY);
    if (!empty($tokensRok)) {
        $or = [];
        foreach ($tokensRok as $tok) {
            $w = mysqli_real_escape_string($connection, $tok);
            // Rok jako text (aby LIKE fungoval i na INT/YEAR)
            $or[] = "COALESCE(CAST(rok AS CHAR),'') LIKE '%$w%'";
        }
        $where .= " AND (" . implode(" OR ", $or) . ")";
    }

    // ---- Fulltext přes více sloupců s COALESCE ----
    if (!empty($searchQuery)) {
        $words = preg_split('/\s+/', trim($searchQuery), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($words as $word) {
            $w = mysqli_real_escape_string($connection, $word);
            $where .= " AND (
                COALESCE(firma1,'')            LIKE '%$w%' OR 
                COALESCE(firma2,'')           LIKE '%$w%' OR 
                COALESCE(cislo,'')            LIKE '%$w%' OR 
                COALESCE(nazev,'')            LIKE '%$w%' OR 
                COALESCE(upresneni,'')        LIKE '%$w%' OR 
                COALESCE(barva1,'')           LIKE '%$w%' OR 
                COALESCE(barva2,'')           LIKE '%$w%' OR 
                COALESCE(barva3,'')           LIKE '%$w%' OR 
                COALESCE(barva4,'')           LIKE '%$w%' OR 
                COALESCE(barva5,'')           LIKE '%$w%' OR 
                COALESCE(serie,'')            LIKE '%$w%' OR 
                COALESCE(zavod,'')            LIKE '%$w%' OR 
                COALESCE(startovnicislo,'')   LIKE '%$w%' OR 
                COALESCE(tym,'')              LIKE '%$w%' OR 
                COALESCE(reklama,'')          LIKE '%$w%' OR 
                COALESCE(jezdec1,'')          LIKE '%$w%' OR 
                COALESCE(jezdec2,'')          LIKE '%$w%' OR 
                COALESCE(jezdec3,'')          LIKE '%$w%' OR 
                COALESCE(poznamka,'')         LIKE '%$w%' OR 
                COALESCE(CAST(rok AS CHAR),'') LIKE '%$w%'
            )";
        }
    }

    $baseQuery  = "FROM auta $where";
    $srovnani   = $_GET['srovnani'] ?? "firma1";
    $query      = "SELECT * " . $baseQuery . " ORDER BY " . $srovnani . " , nazev ";
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
}

// Stránkování
$stranka = isset($_GET['stranka']) ? (int)$_GET['stranka'] : 1;
if ($stranka < 1) $stranka = 1;
$limit  = 100;
$offset = ($stranka - 1) * $limit;

// Počet záznamů
$countResult   = mysqli_query($connection, $countQuery);
$countRow      = mysqli_fetch_assoc($countResult);
$totalRecords  = (int)$countRow['total'];
$totalPages    = (int)ceil($totalRecords / $limit);

// Data pro stránku
$query .= " LIMIT $limit OFFSET $offset";
$result = mysqli_query($connection, $query);
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="desktop-styly.css?v=<?php echo filemtime(__DIR__ . '/desktop-styly.css'); ?>">
    <title>Databaze aut</title>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@4.2.1/dist/jspdf.umd.min.js"></script>
    <script>

    

    function applyFilters() {
        var firma1           = document.getElementById('selectfirma1').value;
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
        if(firma1)          url += '&firma1='           + encodeURIComponent(firma1);
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
    function printQR(imageSrc) {
    const w = window.open('', '_blank', 'width=600,height=600');

    if (!w) {
        alert('Prohlížeč zablokoval vyskakovací okno.');
        return;
    }

    w.document.write(`
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <title>Tisk QR kódu</title>

            <style>
                @page {
                    size: 50mm 50mm;
                    margin: 0;
                }

                html,
                body {
                    width: 50mm;
                    height: 50mm;
                    margin: 0;
                    padding: 0;
                }

                body {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                img {
                    width: 40mm;
                    height: 40mm;
                    display: block;
                }
            </style>
        </head>

        <body>
            <img id="qr-image" src="${imageSrc}" alt="QR kód">
        </body>
        </html>
    `);

    w.document.close();

    const image = w.document.getElementById('qr-image');

    image.onload = function () {
        w.focus();
        w.print();
    };

    image.onerror = function () {
        alert('QR kód se nepodařilo načíst.');
        w.close();
    };
}

function openQRLabelPDF(imageSrc) {
    const labelWidth = 62;   // šířka štítku v mm
    const labelHeight = 35;  // výška štítku v mm

    const qrSize = 24;       // velikost jednoho QR kódu v mm
    const gap = 4;           // mezera mezi QR kódy v mm

    const { jsPDF } = window.jspdf;

    const pdf = new jsPDF({
        orientation: 'landscape',
        unit: 'mm',
        format: [labelWidth, labelHeight],
        compress: true
    });

    // Celková šířka obou QR kódů včetně mezery
    const totalWidth = (qrSize * 2) + gap;

    // Vystředění celé dvojice na štítku
    const startX = (labelWidth - totalWidth) / 2;
    const y = (labelHeight - qrSize) / 2;

    // Levý QR kód
    pdf.addImage(
        imageSrc,
        'PNG',
        startX,
        y,
        qrSize,
        qrSize
    );

    // Pravý QR kód
    pdf.addImage(
        imageSrc,
        'PNG',
        startX + qrSize + gap,
        y,
        qrSize,
        qrSize
    );

    const pdfBlob = pdf.output('blob');
    const pdfUrl = URL.createObjectURL(pdfBlob);

    window.open(pdfUrl, '_blank');

    setTimeout(function () {
        URL.revokeObjectURL(pdfUrl);
    }, 60000);
}




let reloadInProgress = false;
let autoReloadEnabled = false;

if ('scrollRestoration' in history) {
  history.scrollRestoration = 'manual';
}

function saveCurrentPosition() {

    sessionStorage.setItem(
        'pageScrollY',
        String(window.scrollY)
    );

    const wrap = document.querySelector('.hlavnitabulka-wrap');

    if (wrap) {
        sessionStorage.setItem(
            'tableScrollTop',
            String(wrap.scrollTop)
        );

        sessionStorage.setItem(
            'tableScrollLeft',
            String(wrap.scrollLeft)
        );
    }

    sessionStorage.setItem('restoreScroll', '1');
}


function safeReload(
    reason = '',
    force = false,
    useSavedPosition = false
) {

    if (!force && !autoReloadEnabled) {
        return;
    }

    if (reloadInProgress) {
        return;
    }

    reloadInProgress = true;

    /*
     * Při běžném reloadu pozici uložíme nyní.
     * Při mazání použijeme pozici uloženou před otevřením okna.
     */
    if (!useSavedPosition) {
        saveCurrentPosition();
    } else {
        sessionStorage.setItem('restoreScroll', '1');
    }

    location.reload();
}

window.addEventListener('message', function (event) {

    if (event.origin !== window.location.origin) {
        return;
    }

    if (
        !event.data ||
        event.data.type !== 'auta-data-changed'
    ) {
        return;
    }

    deleteWindowOpen = false;

    if (deleteWindowCheck !== null) {
        window.clearInterval(deleteWindowCheck);
        deleteWindowCheck = null;
    }

    safeReload(
        event.data.action || 'edit-window',
        true,
        true
    );
});


// 1) Návrat do karty
document.addEventListener('visibilitychange', () => {

    if (
        document.visibilityState === 'visible' &&
        !deleteWindowOpen
    ) {
        safeReload('visibility');
    }

});


// 2) Focus okna
window.addEventListener('focus', () => {

    if (!deleteWindowOpen) {
        safeReload('focus');
    }

});


// 3) BFCache
window.addEventListener('pageshow', (e) => {
  if (e.persisted) {
    safeReload('pageshow-bfcache');
  }
});


// 4) Chrome resume
document.addEventListener?.('resume', () => {
  safeReload('resume');
});


// 5) Návrat internetu
window.addEventListener('online', () => {
  safeReload('online');
});

    let deleteWindowOpen = false;
let deleteWindowCheck = null;

function dotazkmazani(id) {

    if (!window.confirm("Opravdu chcete smazat záznam?")) {
        return;
    }

    /*
     * Pozici uložíme ještě před otevřením nové karty,
     * kdy je hlavní stránka aktivní.
     */
    saveCurrentPosition();

    deleteWindowOpen = true;

    const editWindow = window.open(
        'Auta-edit.php?polozka=' +
            encodeURIComponent(id) +
            '&smazpolozku=1',
        '_blank'
    );

    if (!editWindow) {
        deleteWindowOpen = false;

        window.alert(
            'Prohlížeč zablokoval otevření okna.'
        );

        return;
    }

    /*
     * Pojistka pro případ, že postMessage nebude doručeno.
     */
    deleteWindowCheck = window.setInterval(function () {

        if (!editWindow.closed) {
            return;
        }

        window.clearInterval(deleteWindowCheck);
        deleteWindowCheck = null;

        if (!reloadInProgress) {
            deleteWindowOpen = false;

            safeReload(
                'delete-window-closed',
                true,
                true
            );
        }

    }, 300);
}

    (function () {
      function addToken(inputId, token) {
        var inp = document.getElementById(inputId);
        if (!inp) return;
        token = (token || '').trim();
        if (!token) return;
        inp.value = inp.value ? (inp.value + ' ' + token) : token;
        inp.focus();
        try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch(e){}
      }
      document.addEventListener('click', function (e) {
            var row = e.target.closest('.filtr-row');

            if (row) {
                if (row.dataset.firma) {
                    addToken('filtrfirma', row.dataset.firma);
                }

                if (row.dataset.barva) {
                    addToken('filtrbarva', row.dataset.barva);
                }

                if (row.dataset.serie) {
                    addToken('filtrserie', row.dataset.serie);
                }

                if (row.dataset.zavody) {
                    addToken('filtrzavody', row.dataset.zavody);
                }

                // Zavře roletku, ze které byla položka vybrána
                var otevrenaRoletka = row.closest('.filtr-panel');

                if (otevrenaRoletka) {
                    otevrenaRoletka.open = false;
                }

                return;
            }

            // Kliknutí mimo kteroukoliv roletku zavře všechny otevřené roletky
            if (!e.target.closest('.filtr-panel')) {
                document.querySelectorAll('.filtr-panel[open]').forEach(function (panel) {
                    panel.open = false;
                });
            }
        });
      document.addEventListener('toggle', function (e) {
          var otevrenyPanel = e.target;

          if (
              !otevrenyPanel.matches ||
              !otevrenyPanel.matches('.filtr-panel') ||
              !otevrenyPanel.open
          ) {
              return;
          }

          document.querySelectorAll('.filtr-panel[open]').forEach(function (panel) {
              if (panel !== otevrenyPanel) {
                  panel.open = false;
              }
          });
      }, true);
      document.addEventListener('keydown', function (e) {
        var row = e.target.closest('.filtr-row'); if (!row) return;
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          if (row.dataset.firma) addToken('filtrfirma', row.dataset.firma);
          if (row.dataset.barva) addToken('filtrbarva', row.dataset.barva);
          if (row.dataset.serie) addToken('filtrserie', row.dataset.serie);
          if (row.dataset.zavody) addToken('filtrzavody', row.dataset.zavody);
        }
      });
    })();
    
document.addEventListener('DOMContentLoaded', () => {

  const wrap = document.querySelector('.hlavnitabulka-wrap');

  if (!wrap) {
    return;
  }


  // Obnov stav hintu
  if (sessionStorage.getItem('hintDismissed') === '1') {
    wrap.classList.add('hint-dismissed');
  }


  

});

window.addEventListener('load', () => {

  const restoreScroll =
    sessionStorage.getItem('restoreScroll') === '1';

  const savedPageScrollY =
    parseInt(
      sessionStorage.getItem('pageScrollY') || '0',
      10
    );

  const savedTableScrollTop =
    parseInt(
      sessionStorage.getItem('tableScrollTop') || '0',
      10
    );

  const savedTableScrollLeft =
    parseInt(
      sessionStorage.getItem('tableScrollLeft') || '0',
      10
    );


  const wrap =
    document.querySelector('.hlavnitabulka-wrap');


  if (restoreScroll) {

    const restorePosition = () => {

      window.scrollTo(
        0,
        savedPageScrollY
      );

      if (wrap) {
        wrap.scrollTop = savedTableScrollTop;
        wrap.scrollLeft = savedTableScrollLeft;
      }

    };


    // Obnovit ihned
    restorePosition();

    // A ještě několikrát po dosednutí layoutu
    setTimeout(restorePosition, 50);
    setTimeout(restorePosition, 150);
    setTimeout(restorePosition, 300);


    setTimeout(() => {

      sessionStorage.removeItem('restoreScroll');
      sessionStorage.removeItem('pageScrollY');
      sessionStorage.removeItem('tableScrollTop');
      sessionStorage.removeItem('tableScrollLeft');

      // Teprve nyní povolit automatické reloady
      autoReloadEnabled = true;

    }, 500);

  } else {

    // Normální otevření stránky
    setTimeout(() => {
      autoReloadEnabled = true;
    }, 500);

  }

});



document.addEventListener('DOMContentLoaded', () => {
    const filtryToggle = document.getElementById('filtryToggle');
    const filtryDetails = document.getElementById('filtryDetails');

    if (!filtryToggle || !filtryDetails) {
        return;
    }

    function updateFiltryButton() {
        const jsouOtevrene = filtryDetails.open;

        filtryToggle.setAttribute(
            'aria-expanded',
            String(jsouOtevrene)
        );

        filtryToggle.textContent = jsouOtevrene
            ? 'SKRÝT FILTRY'
            : 'ZOBRAZIT FILTRY';
    }

    filtryToggle.addEventListener('click', () => {
        filtryDetails.open = !filtryDetails.open;
        updateFiltryButton();
    });

    filtryDetails.addEventListener('toggle', updateFiltryButton);

    updateFiltryButton();
});

document.addEventListener('DOMContentLoaded', () => {
    const horniLista = document.getElementById('horniFixniPanel');
    const spodniLista = document.getElementById('spodniLista');

    function aktualizovatVyskyList() {
        const vyskaHorniListy = horniLista
            ? Math.ceil(horniLista.getBoundingClientRect().height)
            : 0;

        const vyskaSpodniListy = spodniLista
            ? Math.ceil(spodniLista.getBoundingClientRect().height)
            : 0;

        document.documentElement.style.setProperty(
            '--vyska-horni-listy',
            vyskaHorniListy + 'px'
        );

        document.documentElement.style.setProperty(
            '--vyska-spodni-listy',
            vyskaSpodniListy + 'px'
        );
    }

    aktualizovatVyskyList();

    window.addEventListener('resize', aktualizovatVyskyList);

    if ('ResizeObserver' in window) {
        const observer = new ResizeObserver(aktualizovatVyskyList);

        if (horniLista) {
            observer.observe(horniLista);
        }

        if (spodniLista) {
            observer.observe(spodniLista);
        }
    }
});

</script>
</head>
<body>

<?php
include("phpqrcode/qrlib.php");
?>

<div class="horni-fixni-panel" id="horniFixniPanel">

    <div class="horni-segment horni-segment-akce">
        <?php if ($prihlasenOpravneni <= 2): ?>
            <input
                class="zaoblene-tlacitko-oranzove"
                type="button"
                value="NOVÁ POLOŽKA"
                onmouseover="this.style.backgroundColor='darkorange';"
                onmouseout="this.style.backgroundColor='orange';"
                onclick="window.open('Auta-edit.php?polozka=nova', '_blank');"
            >

            <input
                class="zaoblene-tlacitko-oranzove"
                type="button"
                value="IMPORT"
                onmouseover="this.style.backgroundColor='darkorange';"
                onmouseout="this.style.backgroundColor='orange';"
                onclick="window.open('Auta-import.php', '_blank');"
            >
        <?php endif; ?>
    </div>

    <div class="horni-segment horni-segment-hledani">
      <div class="tabulka-hledani">
        <div class="search-box">
          <form class="search-form" method="get" action="Auta-main.php" autocomplete="off">
            <div class="search-main">
              <label for="hlavniHledani">Hledání:</label>

              <input
                id="hlavniHledani"
                type="search"
                class="search-input"
                name="q"
                placeholder="Sem zadej klíčová slova (nebo 'duplicity')"
                value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
              >

              <button
                type="button"
                class="filtry-tlacitko"
                id="filtryToggle"
                aria-expanded="false"
                aria-controls="filtryDetails"
              >
                Zobrazit filtry
              </button>
                <div class="actions">
                  <input
                    type="hidden"
                    name="zobrazpozadavky"
                    value="<?php echo htmlspecialchars($zobrazujpozadavky); ?>"
                  >
                  <button
                    type="submit"
                    class="zaoblene-tlacitko-zelene"
                    onmouseover="this.style.backgroundColor='darkgreen';"
                    onmouseout="this.style.backgroundColor='green';"
                  >
                  HLEDEJ
                  </button>
                  <input
                    type="button"
                    class="zaoblene-tlacitko-zelene"
                    value="HLEDEJ VŠE"
                    onmouseover="this.style.backgroundColor='darkgreen';"
                    onmouseout="this.style.backgroundColor='green';"
                    onclick="window.location.replace('Auta-main.php?stranka=1');"
                  >
                </div>
    
            </div>

                <?php
                $zapnuteFiltry = [];

                $prehledFiltru = [
                    'firma'                   => $filtraceSloupecFirma,
                    'číslo'                   => $filtraceSloupecCislo,
                    'název'                   => $filtraceSloupecNazev,
                    'upřesnění'               => $filtraceSloupecUpresneni,
                    'barva'                   => $filtraceSloupecBarva,
                    'závody'                  => $filtraceSloupecZavody,
                    'série'                   => $filtraceSloupecSerie,
                    'start. č./tým/reklama'   => $filtraceSloupecStartTymReklama,
                    'jezdec'                  => $filtraceSloupecJezdec,
                    'rok'                     => $filtraceSloupecRok
                ];

                foreach ($prehledFiltru as $nazevFiltru => $hodnotaFiltru) {
                    $hodnotaFiltru = trim((string)$hodnotaFiltru);

                    if ($hodnotaFiltru !== '') {
                        $zapnuteFiltry[] =
                            htmlspecialchars($nazevFiltru, ENT_QUOTES, 'UTF-8')
                            . "='"
                            . htmlspecialchars($hodnotaFiltru, ENT_QUOTES, 'UTF-8')
                            . "'";
                    }
                }
                ?>
            <details
              class="filtry-hledani-pozitivni"
              id="filtryDetails"
            >
              <summary class="filtry-skryte-summary">
                  Filtry
              </summary>
                <div class="filters-grid">
                    <div class="filter-field">
                        <i>Do kolonek zadávej výrazy jen s mezerami</i>
                    </div>
                </div>
                <div class="datum-filtr-row">
                  <span>Datum přidání od:</span>
                  <input
                      type="date"
                      class="date-input"
                      name="datumod"
                      value="<?php echo htmlspecialchars($_GET['datumod'] ?? ''); ?>"
                      autocomplete="off"
                  >
                  <span>do:</span>
                  <input
                    type="date"
                    class="date-input"
                    name="datumdo"
                    value="<?php echo htmlspecialchars($_GET['datumdo'] ?? ''); ?>"
                    autocomplete="off"
                  >
                </div>
                <div class="filters-grid">  
                  <!-- Firma -->
                  <div class="filter-field">
                    <input id="filtrfirma" type="search" class="date-input" name="filtrfirma" placeholder="Filtr firem" value="<?php echo htmlspecialchars($_GET['filtrfirma'] ?? ''); ?>" autocomplete="off"/>
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
                  <!-- Číslo -->
                  <div class="filter-field">
                    <input id="filtrcislo" type="search" class="date-input" name="filtrcislo" placeholder="Filtr čísla" value="<?php echo htmlspecialchars($_GET['filtrcislo'] ?? ''); ?>" autocomplete="off" />
                  </div>

                  <!-- Název -->
                  <div class="filter-field">
                    <input id="filtrnazev" type="search" class="date-input" name="filtrnazev" placeholder="Filtr názvu" value="<?php echo htmlspecialchars($_GET['filtrnazev'] ?? ''); ?>" autocomplete="off" />
                  </div>

                  <!-- Upřesnění -->
                  <div class="filter-field">
                    <input id="filtrupresneni" type="search" class="date-input" name="filtrupresneni" placeholder="Filtr upřesnění" value="<?php echo htmlspecialchars($_GET['filtrupresneni'] ?? ''); ?>" autocomplete="off" />
                  </div>

                  <!-- Barvy + menu -->
                  <div class="filter-field">
                    <input id="filtrbarva" type="search" class="date-input" name="filtrbarva" placeholder="Filtr barev" value="<?php echo htmlspecialchars($_GET['filtrbarva'] ?? ''); ?>" autocomplete="off" />
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
                    <input id="filtrserie" type="search" class="date-input" name="filtrserie" placeholder="Filtr série" value="<?php echo htmlspecialchars($_GET['filtrserie'] ?? ''); ?>" autocomplete="off" />
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
                    <input id="filtrzavody" type="search" class="date-input" name="filtrzavody" placeholder="Filtr závodů" value="<?php echo htmlspecialchars($_GET['filtrzavody'] ?? ''); ?>" autocomplete="off" />
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

                  <!-- Start.č./Tým/Reklama -->
                  <div class="filter-field">
                    <input id="filtrstarttymreklama" type="search" class="date-input" name="filtrstarttymreklama" placeholder="Filtr st.č./tým/reklama" value="<?php echo htmlspecialchars($_GET['filtrstarttymreklama'] ?? ''); ?>" autocomplete="off" />
                  </div>

                  <!-- Jezdec -->
                  <div class="filter-field">
                    <input id="filtrjezdec" type="search" class="date-input" name="filtrjezdec" placeholder="Filtr jezdec" value="<?php echo htmlspecialchars($_GET['filtrjezdec'] ?? ''); ?>" autocomplete="off" />
                  </div>

                  <!-- Rok -->
                  <div class="filter-field">
                    <input id="filtrrok" type="search" class="date-input" name="filtrrok" placeholder="Filtr rok" value="<?php echo htmlspecialchars($_GET['filtrrok'] ?? ''); ?>" autocomplete="off" />
                  </div>
              </div>
            </details>
          </form>
        </div>

<?php
$queryParams = [];
if (!empty($searchQuery)) { $queryParams['q'] = $searchQuery; }
$queryParams['zobrazpozadavky'] = $zobrazujpozadavky;
if (!empty($_GET['datumod']) && !empty($_GET['datumdo'])){
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

?>

<div class="prehled-vysledku">

    <div class="pocet-nalezu">
        Počet nálezů:
        <b><?php echo $totalRecords; ?></b>

        <?php if (isset($datumod) && isset($datumdo)): ?>
            a zobrazené období:
            <b><?php echo date('d.m.Y', $datumod); ?></b>
            až
            <b><?php echo date('d.m.Y', $datumdo); ?></b>
        <?php endif; ?>
    </div>

    <?php if (!empty($zapnuteFiltry)): ?>
        <div class="zapnute-filtry">
            Zapnuté filtry:
            <b><?php echo implode(', ', $zapnuteFiltry); ?></b>
        </div>
    <?php endif; ?>

</div>


      </div>
    </div>
    <div class="horni-segment horni-segment-navigace">
        <a href="Uvodni.php" title="Zpět na úvodní stránku">
            <img width="50" height="50" src="Ikony/Home.png" alt="Domů">
        </a>

        <a href="Prihlaseni.php" title="Odhlásit se">
            <img width="50" height="50" src="Ikony/Logout.png" alt="Odhlásit se">
        </a>
    </div>

</div>






<div class="hlavnitabulka-wrap">

<table class="hlavnitabulka">
    <thead>
    <tr>
        <th>Firma <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=firma1'\">";?></th>
        <th>Číslo <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=cislo'\">";?></th>
        <th>Název <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=nazev'\">";?></th>
        <th>Upřesnění</th>
        <th>Barvy</th>
        <th>Série / Závod</th>
        <th>Start.č. / Tým / Reklama</th>
        <th>Jezdec</th>
        <th>Rok <?php echo "<input type='button' value='↓' onclick=\"window.location.href='Auta-main.php?{$queryString}&srovnani=rok'\">";?></th>
        <th>Cena</th>
        <th>QR</th>
        <th>Tisk QR</th>
        <?php if ($prihlasenOpravneni <= 2 ) { echo "<th class='col-edit'>EDIT</th>"; } ?>
    </tr>
    </thead>
    <tbody>
    <?php
    while ($row = mysqli_fetch_assoc($result)) {
        if ($row['mame'] == "ANO"){
            echo "<tr id=\"{$row['id']}\" class=\"zelenePozadi\">";
        } else {
            echo "<tr id=\"{$row['id']}\">";
        }

        echo "<td>";
            $firmy = array_filter([$row['firma1'], $row['firma2']]);
            echo implode(", ", $firmy);
        echo "</td>";

        echo "<td>{$row['cislo']}</td>";
        echo "<td>{$row['nazev']}</td>";
        echo "<td>{$row['upresneni']}</td>";

        echo "<td>";
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

        $cestaQRauta = "QR-auta/{$row['id']}.png";
        if (!file_exists($cestaQRauta)) {
            QRcode::png($row['id'], $cestaQRauta);
        }
        echo "<td><img src='{$cestaQRauta}' alt='QR kód' class=\"bunkaQR\"></td>";
        echo "<td>
              <button
                type='button'
                class='zaoblene-tlacitko tlacitko-tisk'
                title='Tisk QR'
                onmouseover=\"this.style.backgroundColor='grey';\"
                onmouseout=\"this.style.backgroundColor='lightgrey';\"
                onclick=\"openQRLabelPDF('{$cestaQRauta}')\"
              >🖨</button>
              </td>";

        if ($prihlasenOpravneni <= 2 ){
            echo "<td class='col-edit' style=\"word-wrap: normal; word-break: normal; white-space: nowrap;\"><div>
                <input class='zaoblene-tlacitko' type='button' value='EDIT' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" onclick=\"window.open('Auta-edit.php?polozka={$row['id']}', '_blank');\">
                <input class='zaoblene-tlacitko' type='button' value='COPY' onmouseover=\"this.style.backgroundColor='grey';\" onmouseout=\"this.style.backgroundColor='lightgrey';\" onclick=\"window.open('Auta-edit.php?polozka={$row['id']}&duplikace=1', '_blank');\">
                <input class='zaoblene-tlacitko-cervene' type='button' value='DEL' onmouseover=\"this.style.backgroundColor='darkred';\" onmouseout=\"this.style.backgroundColor='red';\" onclick=\"dotazkmazani({$row['id']});\">
            </div></td>";
        }
        echo "</tr>";
    }
    ?>
    </tbody>
</table>
</div>


<div class="spodni-strankovani" id="spodniLista">
    <span class="strankovani-popis">STRÁNKY:</span>

    <div class="strankovani-tlacitka">
        <?php
$queryParams = [];
if (!empty($searchQuery)) { $queryParams['q'] = $searchQuery; }
$queryParams['zobrazpozadavky'] = $zobrazujpozadavky;
if (!empty($_GET['datumod']) && !empty($_GET['datumdo'])){
    $queryParams['datumod'] = date('Y-m-d', $datumod);
    $queryParams['datumdo'] = date('Y-m-d', $datumdo);
}

if (!empty($_GET['srovnani'])) { $queryParams['srovnani'] = $srovnani; }

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
    elseif (
    ($a != 1) &&
    ($a != $totalPages) &&
    (($a < ($stranka - 10)) || ($a > ($stranka + 10)))
) {
    echo "<div
        style=\"display:inline-block; cursor:pointer;\"
        onclick=\"window.location.href='Auta-main.php?{$queryString}'\"
        onmouseover=\"this.textContent='{$a}'\"
        onmouseout=\"this.textContent='.'\"
    >.</div>";
}
    else {
        echo "<input type='button' value='{$a}' style='background-color: grey; color: white; border: none; padding: 5px; cursor: pointer;'
      onmouseover=\"this.style.color='orange';\" onmouseout=\"this.style.color='white';\" 
      onclick=\"window.location.href='Auta-main.php?{$queryString}'\">";
    }
}
?>
</div>
</div>
</body>
</html>
