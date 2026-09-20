<?php
require_once __DIR__ . '/Auta-hledani.php';
$hledaniParametry = [];
$chybaHledani = null;
// Společná filtrace pro přehled i export; dotaz zde nemá stránkování.
// Volající předá $connection a $prihlasenOpravneni po ověření přihlášení.
foreach ($_GET as $hodnotaParametru) {
    if (!is_string($hodnotaParametru)) {
        http_response_code(400);
        exit('Neplatný parametr filtru.');
    }
}
$srovnani = $_GET['srovnani'] ?? 'firma1';
if (!in_array($srovnani, ['firma1', 'cislo', 'nazev', 'rok'], true)) {
    $srovnani = 'firma1';
}

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

    // Slova a fráze se spojují AND, sloupce uvnitř výrazu OR.
    try {
        [$hledaniSql, $hledaniParametry] = autaRozebratHledani($searchQuery);
        if ($hledaniSql !== '') {
            $where .= ' AND ' . $hledaniSql;
        }
    } catch (InvalidArgumentException $chyba) {
        $chybaHledani = $chyba->getMessage();
        $where .= ' AND 1 = 0';
        http_response_code(400);
    }

    $baseQuery  = "FROM auta $where";
    $query      = "SELECT * " . $baseQuery . " ORDER BY " . $srovnani . " , nazev ";
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
}

