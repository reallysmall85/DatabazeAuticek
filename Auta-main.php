<?php
session_start();

// AJAX ukládání stavu Máme obsluhuje přímo tato stránka.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akce'] ?? '') === 'ulozit-mame') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $odpoved = static function ($status, $data) {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };
    if (!isset($_SESSION['uzivatel'])) {
        $odpoved(401, ['chyba' => 'Přihlášení vypršelo. Přihlaste se znovu.']);
    }
    if (($_SESSION['uzivatel']['opravneni'] ?? 4) > 2) {
        $odpoved(403, ['chyba' => 'Nemáte oprávnění měnit stav auta.']);
    }
    if (!is_string($_POST['csrf'] ?? null) || !isset($_SESSION['auta_mame_csrf'])
        || !hash_equals($_SESSION['auta_mame_csrf'], $_POST['csrf'])) {
        $odpoved(403, ['chyba' => 'Platnost stránky vypršela. Obnovte ji a zkuste to znovu.']);
    }
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $mame = $_POST['mame'] ?? null;
    if (!$id || !in_array($mame, ['ANO', 'NE'], true)) {
        $odpoved(400, ['chyba' => 'Neplatný požadavek.']);
    }
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require_once __DIR__ . '/Pripojeni/pripojeniDatabaze.php';
        $stmt = $connection->prepare('SELECT id FROM auta WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $odpoved(404, ['chyba' => 'Auto již v databázi neexistuje.']);
        }
        $stmt->close();
        $stmt = $connection->prepare('UPDATE auta SET mame = ? WHERE id = ?');
        $stmt->bind_param('si', $mame, $id);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('Uložení mame: ' . $e->getMessage());
        $odpoved(500, ['chyba' => 'Změnu se nepodařilo uložit. Zkuste to znovu.']);
    }
    $logDir = __DIR__ . '/Logy';
    $user = str_replace(["\r", "\n"], ' ', ($_SESSION['uzivatel']['jmeno'] ?? '') . ' ' . ($_SESSION['uzivatel']['prijmeni'] ?? ''));
    $line = '[' . date('Y-m-d H:i:s') . "] ($user) Do tabulky auta bylo změněno: mame='$mame', id='$id'" . PHP_EOL;
    if ((!is_dir($logDir) && !@mkdir($logDir, 0777, true) && !is_dir($logDir))
        || @file_put_contents($logDir . '/log-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('Nepodařilo se zapsat změnu mame do logu pro auto ' . $id);
    }
    $odpoved(200, ['mame' => $mame]);
}


// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

if (empty($_SESSION['auta_mame_csrf'])) {
    $_SESSION['auta_mame_csrf'] = bin2hex(random_bytes(32));
}

// 2) Připojení k databázi (mysqli)
require_once __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

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

require __DIR__ . '/Auta-filtry.php';

// Stránkování
$stranka = isset($_GET['stranka']) ? (int)$_GET['stranka'] : 1;
if ($stranka < 1) $stranka = 1;
$limit  = 100;
$offset = ($stranka - 1) * $limit;

// Počet záznamů
$countResult   = autaProvestDotaz($connection, $countQuery, $hledaniParametry);
$countRow      = mysqli_fetch_assoc($countResult);
$totalRecords  = (int)$countRow['total'];
$totalPages    = (int)ceil($totalRecords / $limit);

// Data pro stránku
$query .= " LIMIT $limit OFFSET $offset";
$result = autaProvestDotaz($connection, $query, $hledaniParametry);
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

async function tiskQRPresAgenta(qrText, tlacitko) {

    if (!tlacitko || tlacitko.disabled) {
        return;
    }

    const puvodniText = tlacitko.textContent;

    tlacitko.disabled = true;
    tlacitko.textContent = '…';
    tlacitko.setAttribute('aria-busy', 'true');

    try {

        const response = await fetch('Tisk-pridej.php', {
            method: 'POST',
            credentials: 'same-origin',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify({
                qr_text: String(qrText),
                csrf: <?php echo json_encode($_SESSION['auta_mame_csrf']); ?>
            })
        });


        const data = await response.json();


        if (!response.ok || !data.success) {
            throw new Error(
                data.message || 'Tiskovou úlohu se nepodařilo vytvořit.'
            );
        }


        // Server tisk přijal
        tlacitko.textContent = '✓';
        tlacitko.title = 'Tisková úloha ' + data.job_id + ' byla odeslána';


        setTimeout(function () {

            tlacitko.textContent = puvodniText;
            tlacitko.title = 'Tisk QR';
            tlacitko.disabled = false;
            tlacitko.removeAttribute('aria-busy');

        }, 1500);


    } catch (error) {

        tlacitko.textContent = '!';

        alert(
            'QR se nepodařilo odeslat k tisku.\n\n' +
            error.message
        );


        setTimeout(function () {

            tlacitko.textContent = puvodniText;
            tlacitko.title = 'Tisk QR';
            tlacitko.disabled = false;
            tlacitko.removeAttribute('aria-busy');

        }, 1500);
    }
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
            ? 'FILTRY ▲'
            : 'FILTRY ▼';
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
<body class="auta-main">

<?php
include("phpqrcode/qrlib.php");
?>

<?php
$queryParams = [];
if ($searchQuery !== '') { $queryParams['q'] = $searchQuery; }
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
$exportUrl = 'Auta-export.php?' . http_build_query(array_merge($queryParams, ['srovnani' => $srovnani]));

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
                placeholder="nápověda: &quot;X&quot;:Y hledá X jen ve sloupci Y, * je zástupný znak"
                
                value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                autocomplete="off"
              >


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
                    value="VYMAZAT FILTRY"
                    onmouseover="this.style.backgroundColor='darkgreen';"
                    onmouseout="this.style.backgroundColor='green';"
                    onclick="window.location.replace('Auta-main.php?stranka=1');"
                  >
                </div>
    
            </div>

            <div class="search-secondary">
              <button
                type="button"
                class="filtry-tlacitko"
                id="filtryToggle"
                aria-expanded="false"
                aria-controls="filtryDetails"
              >
                FILTRY ▼
              </button>
        <?php if ($prihlasenOpravneni <= 2 && $chybaHledani === null): ?>
            <a class="zaoblene-tlacitko-zelene"
               href="<?php echo htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8'); ?>">
                EXPORT DO EXCELU
            </a>
        <?php endif; ?>
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



<?php if ($chybaHledani !== null): ?>
    <p role="alert"><?php echo htmlspecialchars($chybaHledani, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
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
        <th class="col-mame">Máme</th>
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
        echo "<td class=\"bunkaQR-obal\"><img src='{$cestaQRauta}' alt='QR kód' class=\"bunkaQR\"></td>";
        $idAuta = (int)$row['id'];

        echo "<td>
            <button
                type='button'
                class='zaoblene-tlacitko tlacitko-tisk'
                title='Tisk QR'
                onmouseover=\"this.style.backgroundColor='grey';\"
                onmouseout=\"this.style.backgroundColor='lightgrey';\"
                onclick=\"tiskQRPresAgenta({$idAuta}, this)\"
            >🖨</button>
        </td>";

        $stavMame = $row['mame'] === 'ANO' ? 'ANO' : 'NE';
        $tridaMame = $stavMame === 'ANO' ? 'mame-ano' : 'mame-ne';
        echo "<td class='col-mame'>";
        if ($prihlasenOpravneni <= 2) {
            $idAuta = (int)$row['id'];
            echo "<button type='button' class='zaoblene-tlacitko tlacitko-mame $tridaMame' data-id='$idAuta' data-mame='$stavMame' aria-label='Máme: $stavMame. Kliknutím změnit.'>$stavMame</button>";
        } else {
            echo "<span class='$tridaMame'>$stavMame</span>";
        }
        echo "</td>";

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
if ($searchQuery !== '') { $queryParams['q'] = $searchQuery; }
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
<script>
(() => {
    const tabulka = document.querySelector('.hlavnitabulka');
    const edit = tabulka.querySelector('th.col-edit');
    // EDIT mění šířku podle obsahu i mobilního zobrazení.
    if (edit) {
        const nastavOdsazeni = () => tabulka.style.setProperty('--skutecna-sirka-edit', edit.getBoundingClientRect().width + 'px');
        nastavOdsazeni();
        if (typeof ResizeObserver !== 'undefined') {
            new ResizeObserver(nastavOdsazeni).observe(edit);
        }
        window.addEventListener('resize', nastavOdsazeni);
    }
    tabulka.addEventListener('click', async (event) => {
        const tlacitko = event.target.closest('button.tlacitko-mame');
        if (!tlacitko || tlacitko.disabled) return;
        tlacitko.disabled = true;
        tlacitko.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch('Auta-main.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({
                    akce: 'ulozit-mame',
                    id: tlacitko.dataset.id,
                    mame: tlacitko.dataset.mame === 'ANO' ? 'NE' : 'ANO',
                    csrf: <?php echo json_encode($_SESSION['auta_mame_csrf']); ?>
                })
            });
            const data = await response.json();
            if (!response.ok || !['ANO', 'NE'].includes(data.mame)) {
                throw new Error(data.chyba || 'Server nepotvrdil uložení změny.');
            }
            const mame = data.mame === 'ANO';
            tlacitko.dataset.mame = data.mame;
            tlacitko.textContent = data.mame;
            tlacitko.classList.toggle('mame-ano', mame);
            tlacitko.classList.toggle('mame-ne', !mame);
            tlacitko.setAttribute('aria-label', 'Máme: ' + data.mame + '. Kliknutím změnit.');
            tlacitko.closest('tr').classList.toggle('zelenePozadi', mame);
        } catch (error) {
            alert('Změnu se nepodařilo potvrdit. ' + error.message + '\nPři potížích s připojením obnovte stránku pro ověření aktuálního stavu.');
        } finally {
            tlacitko.disabled = false;
            tlacitko.removeAttribute('aria-busy');
        }
    });
})();
</script>
</body>
</html>
