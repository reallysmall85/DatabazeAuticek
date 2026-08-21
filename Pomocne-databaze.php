<?php
session_start();

// 1) Kontrola přihlášení
if (!isset($_SESSION['uzivatel'])) {
    header("Location: Prihlaseni.php");
    exit();
}

// 2) Připojení k databázi (mysql­i)
require_once __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

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


<?php

/* =========================================================
   POMOCNÉ FUNKCE
   ========================================================= */


/*
 * Ořízne text na maximálně 255 znaků.
 */
function orezPomocnouHodnotu($hodnota) {

    $hodnota = trim($hodnota);

    return mb_substr($hodnota, 0, 255, 'UTF-8');
}


/*
 * Aktualizace všech existujících položek jedné pomocné databáze.
 */
function ulozPomocnePolozky(
    $connection,
    $tabulka,
    $sloupec,
    $postNazev
) {

    if (
        !isset($_POST[$postNazev]) ||
        !is_array($_POST[$postNazev])
    ) {
        return;
    }


    $sql = "
        UPDATE " . $tabulka . "
        SET " . $sloupec . " = ?
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($connection, $sql);

    if (!$stmt) {
        die(
            "Chyba při přípravě UPDATE: "
            . mysqli_error($connection)
        );
    }


    foreach ($_POST[$postNazev] as $id => $hodnota) {

        /*
         * ID musí být číslo.
         */
        if (!ctype_digit((string)$id)) {
            continue;
        }

        $id = (int)$id;

        $hodnota = orezPomocnouHodnotu($hodnota);


        mysqli_stmt_bind_param(
            $stmt,
            "si",
            $hodnota,
            $id
        );


        if (!mysqli_stmt_execute($stmt)) {

            die(
                "Chyba při ukládání položky ID "
                . $id
                . ": "
                . mysqli_stmt_error($stmt)
            );
        }
    }


    mysqli_stmt_close($stmt);
}



/*
 * Přidání nové položky.
 *
 * Předpokládá AUTO_INCREMENT u sloupce id.
 */
function pridejPomocnouPolozku(
    $connection,
    $tabulka,
    $sloupec,
    $hodnota
) {

    $hodnota = orezPomocnouHodnotu($hodnota);

    /*
     * Prázdnou položku nepřidáváme.
     */
    if ($hodnota === '') {
        return;
    }


    $sql = "
        INSERT INTO " . $tabulka . "
        (" . $sloupec . ")
        VALUES (?)
    ";

    $stmt = mysqli_prepare($connection, $sql);

    if (!$stmt) {
        die(
            "Chyba při přípravě INSERT: "
            . mysqli_error($connection)
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $hodnota
    );


    if (!mysqli_stmt_execute($stmt)) {

        die(
            "Chyba při přidávání položky: "
            . mysqli_stmt_error($stmt)
        );
    }


    mysqli_stmt_close($stmt);
}



/*
 * Smazání jedné položky.
 */
function smazPomocnouPolozku(
    $connection,
    $tabulka,
    $id
) {

    if (!ctype_digit((string)$id)) {
        return;
    }

    $id = (int)$id;


    $sql = "
        DELETE FROM " . $tabulka . "
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($connection, $sql);

    if (!$stmt) {
        die(
            "Chyba při přípravě DELETE: "
            . mysqli_error($connection)
        );
    }


    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );


    if (!mysqli_stmt_execute($stmt)) {

        die(
            "Chyba při mazání položky: "
            . mysqli_stmt_error($stmt)
        );
    }


    mysqli_stmt_close($stmt);
}



/* =========================================================
   ZPRACOVÁNÍ FORMULÁŘE
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
     * Nejprve uložíme případné změny ve všech existujících
     * textarea.
     *
     * Díky tomu například upravíte firmu a rovnou kliknete
     * na "Přidat" u jiné položky a změna se také uloží.
     */

    ulozPomocnePolozky(
        $connection,
        "autafirmy",
        "firma",
        "inputpomocnefirmy"
    );

    ulozPomocnePolozky(
        $connection,
        "autazavody",
        "zavod",
        "inputpomocnezavody"
    );

    ulozPomocnePolozky(
        $connection,
        "autaserie",
        "serie",
        "inputpomocneserie"
    );

    ulozPomocnePolozky(
        $connection,
        "autabarvy",
        "barva",
        "inputpomocnebarvy"
    );


    /* =====================================================
       MAZÁNÍ
       ===================================================== */

    if (isset($_POST['smazatpomocnoufirmu'])) {

        smazPomocnouPolozku(
            $connection,
            "autafirmy",
            $_POST['smazatpomocnoufirmu']
        );
    }


    if (isset($_POST['smazatpomocnyzavod'])) {

        smazPomocnouPolozku(
            $connection,
            "autazavody",
            $_POST['smazatpomocnyzavod']
        );
    }


    if (isset($_POST['smazatpomocnouserii'])) {

        smazPomocnouPolozku(
            $connection,
            "autaserie",
            $_POST['smazatpomocnouserii']
        );
    }


    if (isset($_POST['smazatpomocnoubarvu'])) {

        smazPomocnouPolozku(
            $connection,
            "autabarvy",
            $_POST['smazatpomocnoubarvu']
        );
    }



    /* =====================================================
       PŘIDÁVÁNÍ
       ===================================================== */

    if (
        isset($_POST['pridatpomocnoufirmu']) &&
        isset($_POST['novapomocnafirma'])
    ) {

        pridejPomocnouPolozku(
            $connection,
            "autafirmy",
            "firma",
            $_POST['novapomocnafirma']
        );
    }


    if (
        isset($_POST['pridatpomocnyzavod']) &&
        isset($_POST['novypomocnyzavod'])
    ) {

        pridejPomocnouPolozku(
            $connection,
            "autazavody",
            "zavod",
            $_POST['novypomocnyzavod']
        );
    }


    if (
        isset($_POST['pridatpomocnouserii']) &&
        isset($_POST['novapomocnaserie'])
    ) {

        pridejPomocnouPolozku(
            $connection,
            "autaserie",
            "serie",
            $_POST['novapomocnaserie']
        );
    }


    if (
        isset($_POST['pridatpomocnoubarvu']) &&
        isset($_POST['novapomocnabarva'])
    ) {

        pridejPomocnouPolozku(
            $connection,
            "autabarvy",
            "barva",
            $_POST['novapomocnabarva']
        );
    }

 /* =====================================================
   NAČTENÍ VŠECH FIREM Z DATABÁZE AUT
   ===================================================== */

if (isset($_POST['nacistvsechnyfirmy'])) {

    $sql = "
        INSERT INTO autafirmy (firma)

        SELECT nalez.firma

        FROM (

            SELECT DISTINCT TRIM(firma1) AS firma
            FROM auta
            WHERE firma1 IS NOT NULL
              AND TRIM(firma1) <> ''

            UNION

            SELECT DISTINCT TRIM(firma2) AS firma
            FROM auta
            WHERE firma2 IS NOT NULL
              AND TRIM(firma2) <> ''

        ) AS nalez

        LEFT JOIN autafirmy
            ON autafirmy.firma = nalez.firma

        WHERE autafirmy.id IS NULL
    ";


    if (!mysqli_query($connection, $sql)) {

        die(
            "Chyba při načítání firem z databáze aut: "
            . mysqli_error($connection)
        );
    }


    /*
     * Počet skutečně přidaných firem.
     */
    $pocetNactenychFirem = mysqli_affected_rows($connection);
}

    /*
     * Redirect je důležitý hlavně při přidávání.
     * Refresh stránky pak nepřidá stejnou položku znovu.
     */
    if (isset($pocetNactenychFirem)) {

    header(
        "Location: "
        . $_SERVER['PHP_SELF']
        . "?nactenofirem="
        . (int)$pocetNactenychFirem
    );

} else {

    header(
        "Location: "
        . $_SERVER['PHP_SELF']
        . "?pomocneulozeno=1"
    );
}

exit();
}

?>


<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8" />
    <meta name="author" content="martin" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="desktop-styly.css?v=<?php echo filemtime(__DIR__ . '/desktop-styly.css'); ?>">
    
    <title>Pomocne databaze</title>
    

</head>


<body>

<?php




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

<div class="horni-fixni-panel" id="horniFixniPanel">
    <div class="horni-segment horni-segment-navigace">
        <a href="Uvodni.php"><img width="50" height="50" src="Ikony/Home.png" name="Uvodni stranka" title="Zpět na úvodní stránku"></a>
        <a href="Prihlaseni.php" title="Odhlásit se">
            <img width="50" height="50" src="Ikony/Logout.png" alt="Odhlásit se">
        </a>
    </div>

</div>


<?php

function vypisPomocnouDatabazi(
    $connection,
    $nadpis,
    $tabulka,
    $sloupec,
    $postNazev,
    $novaPolozkaNazev,
    $pridatTlacitkoNazev,
    $smazatTlacitkoNazev
) {


    $sql = "
        SELECT id, " . $sloupec . "
        FROM " . $tabulka . "
        WHERE id IS NOT NULL
        ORDER BY " . $sloupec . "
    ";


    $result = mysqli_query(
        $connection,
        $sql
    );


    if (!$result) {

        die(
            "Chyba při načítání "
            . htmlspecialchars(
                $nadpis,
                ENT_QUOTES,
                'UTF-8'
            )
            . ": "
            . mysqli_error($connection)
        );
    }



    echo "<div class=\"pomocna-databaze\">";

    echo "<h2>"
        . htmlspecialchars(
            $nadpis,
            ENT_QUOTES,
            'UTF-8'
        )
        . "</h2>";


    echo "<table class=\"pomocna-tabulka\">";


    while ($row = mysqli_fetch_assoc($result)) {

        $id = (int)$row['id'];

        $hodnota = htmlspecialchars(
            $row[$sloupec],
            ENT_QUOTES,
            'UTF-8'
        );


        /*
         * Například:
         *
         * name="inputpomocnefirmy[125]"
         *
         * PHP potom vytvoří:
         *
         * $_POST['inputpomocnefirmy'][125]
         */
        $name =
            $postNazev
            . "["
            . $id
            . "]";


        /*
         * ID HTML prvku bez hranatých závorek.
         *
         * Například:
         * inputpomocnefirmy_125
         */
        $htmlId =
            $postNazev
            . "_"
            . $id;



        echo "<tr>";


        echo "<td>";

        echo "<textarea
                name=\"" . $name . "\"
                id=\"" . $htmlId . "\"
                maxlength=\"255\"
                class=\"pomocna-textarea\"
              >"
              . $hodnota
              . "</textarea>";

        echo "</td>";



        echo "<td>";

        echo "<button
                type=\"submit\"
                name=\"" . $smazatTlacitkoNazev . "\"
                value=\"" . $id . "\"
                class=\"zaoblene-tlacitko-cervene\"
                onmouseover=\"this.style.backgroundColor='darkred';\"
                onmouseout=\"this.style.backgroundColor='red';\"
                onclick=\"return confirm('Opravdu chcete tuto položku smazat?');\"
              >
                DEL
              </button>";

        echo "</td>";


        echo "</tr>";
    }



    /*
     * Řádek pro přidání nové položky.
     */

    echo "<tr class=\"pomocna-nova-polozka\">";


    echo "<td>";

    echo "<textarea
            name=\"" . $novaPolozkaNazev . "\"
            maxlength=\"255\"
            class=\"pomocna-textarea\"
            placeholder=\"Nová položka\"
          ></textarea>";

    echo "</td>";


    echo "<td>";

    echo "<button
            type=\"submit\"
            name=\"" . $pridatTlacitkoNazev . "\"
            value=\"1\"
            class=\"zaoblene-tlacitko\"
            onmouseover=\"this.style.backgroundColor='grey';\"
            onmouseout=\"this.style.backgroundColor='lightgrey';\"
          >
            PŘIDAT
          </button>";

    echo "</td>";


    echo "</tr>";


    /*
 * Pouze u databáze firem zobrazíme možnost
 * načíst všechny firmy použité v databázi auta.
 */
if ($tabulka === "autafirmy") {

    echo "<tr class=\"pomocna-nacist-vse\">";

   echo "<td class=\"pomocna-popis-nacti\">
        Pokud v seznamu výše něco z firem chybí a v celkové databázi aut to je, dej Načti
      </td>";

    echo "<td>
            <button
                type=\"submit\"
                name=\"nacistvsechnyfirmy\"
                value=\"1\"
                class=\"zaoblene-tlacitko\"
                onmouseover=\"this.style.backgroundColor='grey';\"
                onmouseout=\"this.style.backgroundColor='lightgrey';\"
                onclick=\"return confirm('Načíst všechny chybějící firmy z databáze aut?');\"
            >
                NAČTI
            </button>
          </td>";

    echo "</tr>";
}

    echo "</table>";

    echo "</div>";
}



if (isset($_GET['pomocneulozeno'])) {

    echo "
        <div class=\"ulozeno-hlaska\">
            Změny byly uloženy.
        </div>
    ";
}


if (isset($_GET['nactenofirem'])) {

    $pocet = (int)$_GET['nactenofirem'];

    if ($pocet === 0) {

        echo "
            <div class=\"ulozeno-hlaska\">
                Nebyla nalezena žádná nová firma.
            </div>
        ";

    } elseif ($pocet === 1) {

        echo "
            <div class=\"ulozeno-hlaska\">
                Byla přidána 1 nová firma.
            </div>
        ";

    } elseif ($pocet >= 2 && $pocet <= 4) {

        echo "
            <div class=\"ulozeno-hlaska\">
                Byly přidány " . $pocet . " nové firmy.
            </div>
        ";

    } else {

        echo "
            <div class=\"ulozeno-hlaska\">
                Bylo přidáno " . $pocet . " nových firem.
            </div>
        ";
    }
}

?>

<form method="post">

<?php

vypisPomocnouDatabazi(
    $connection,
    "Firmy",
    "autafirmy",
    "firma",
    "inputpomocnefirmy",
    "novapomocnafirma",
    "pridatpomocnoufirmu",
    "smazatpomocnoufirmu"
);



vypisPomocnouDatabazi(
    $connection,
    "Závody",
    "autazavody",
    "zavod",
    "inputpomocnezavody",
    "novypomocnyzavod",
    "pridatpomocnyzavod",
    "smazatpomocnyzavod"
);



vypisPomocnouDatabazi(
    $connection,
    "Série",
    "autaserie",
    "serie",
    "inputpomocneserie",
    "novapomocnaserie",
    "pridatpomocnouserii",
    "smazatpomocnouserii"
);



vypisPomocnouDatabazi(
    $connection,
    "Barvy",
    "autabarvy",
    "barva",
    "inputpomocnebarvy",
    "novapomocnabarva",
    "pridatpomocnoubarvu",
    "smazatpomocnoubarvu"
);

?>

<div class="spodni-strankovani">

    <button
        type="submit"
        name="ulozitpomocne"
        value="1"
        class="zaoblene-tlacitko-zelene"
        onmouseover="this.style.backgroundColor='darkgreen';"
        onmouseout="this.style.backgroundColor='green';"
    >
        ULOŽIT ZMĚNY
    </button>

</div>

</form>


</body>
</html>

