<?php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

session_start();
if (!isset($_SESSION['uzivatel'])) {
    header('Location: Prihlaseni.php');
    exit();
}

$prihlasenOpravneni = $_SESSION['uzivatel']['opravneni'] ?? 4;
// Zachování omezení exportu z původního souboru.
if ($prihlasenOpravneni > 2) {
    http_response_code(403);
    exit('K exportu nemáte oprávnění.');
}
session_write_close();

// Případný výstup připojovacího souboru nesmí poškodit XLSX.
ob_start();
$docasnySoubor = null;
$sesit = null;
try {
    require_once __DIR__ . '/Pripojeni/pripojeniDatabaze.php';
    require_once __DIR__ . '/vendor/autoload.php';
    require __DIR__ . '/Auta-filtry.php';

    if ($chybaHledani !== null) {
        throw new InvalidArgumentException($chybaHledani);
    }

    // Sdílený dotaz zahrnuje všechny nálezy, bez LIMIT a OFFSET.
    $result = autaProvestDotaz($connection, $query, $hledaniParametry);
    if ($result === false) {
        throw new RuntimeException('Nepodařilo se načíst data exportu.');
    }
    if (mysqli_num_rows($result) > 1048575) {
        throw new RuntimeException('Výsledky přesahují kapacitu listu Excelu. Zužte filtry.');
    }

    $sloupce = [
        'Firma' => ['firma1', 'firma2'],
        'Číslo' => ['cislo'],
        'Název' => ['nazev'],
        'Upřesnění' => ['upresneni'],
        'Barvy' => ['barva1', 'barva2', 'barva3', 'barva4', 'barva5'],
        'Série' => ['serie'],
        'Závod' => ['zavod'],
        'Startovní číslo' => ['startovnicislo'],
        'Tým' => ['tym'],
        'Reklama' => ['reklama'],
        'Jezdec' => ['jezdec1', 'jezdec2', 'jezdec3'],
        'Rok' => ['rok'],
    ];
    if ($prihlasenOpravneni <= 2) {
        $sloupce['Cena'] = ['cena'];
    }

    $sesit = new Spreadsheet();
    $list = $sesit->getActiveSheet();
    $list->setTitle('Auta');
    $sloupec = 1;
    foreach ($sloupce as $nadpis => $pole) {
        $list->setCellValueExplicit([$sloupec++, 1], $nadpis, DataType::TYPE_STRING);
    }
    $radek = 2;
    while ($auto = mysqli_fetch_assoc($result)) {
        $sloupec = 1;
        foreach ($sloupce as $nadpis => $pole) {
            $hodnoty = array_map(static function ($klic) use ($auto) {
                return (string)($auto[$klic] ?? '');
            }, $pole);
            // Import rozpoznává sloučené firmy, barvy a jezdce.
            $hodnota = count($pole) > 1
                ? implode(', ', array_filter($hodnoty, static function ($hodnota) { return $hodnota !== ''; }))
                : $hodnoty[0];
            $typ = DataType::TYPE_STRING;
            if ($nadpis === 'Rok') {
                // Import přijímá čtyřmístný rok jako číselnou buňku, ne jako text.
                if ($hodnota !== '' && is_numeric($hodnota) && (int)$hodnota !== 0) {
                    $hodnota = (int)$hodnota;
                    $typ = DataType::TYPE_NUMERIC;
                } else {
                    $hodnota = '';
                }
            } elseif ($nadpis === 'Cena' && $hodnota !== '' && is_numeric($hodnota)) {
                $hodnota = (float)$hodnota;
                $typ = DataType::TYPE_NUMERIC;
            }
            // Explicitní text zachová úvodní nuly a zabrání interpretaci vzorců.
            $list->setCellValueExplicit([$sloupec++, $radek], $hodnota, $typ);
        }
        $radek++;
    }
    mysqli_free_result($result);

    $posledniSloupec = Coordinate::stringFromColumnIndex(count($sloupce));
    $list->freezePane('A2');
    $list->setAutoFilter('A1:' . $posledniSloupec . max(1, $radek - 1));
    $list->getStyle('A1:' . $posledniSloupec . '1')->getFont()->setBold(true);
    foreach (range(1, count($sloupce)) as $index) {
        $list->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
    }

    // Soubor nejprve dokončíme, až potom odešleme hlavičky ke stažení.
    $docasnySoubor = tempnam(sys_get_temp_dir(), 'auta-export-');
    if ($docasnySoubor === false) {
        throw new RuntimeException('Nelze vytvořit dočasný soubor exportu.');
    }
    $writer = new Xlsx($sesit);
    $writer->save($docasnySoubor);
    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="auta_' . date('Y-m-d_His') . '.xlsx"');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($docasnySoubor);
} catch (Throwable $chyba) {
    ob_end_clean();
    error_log('Export aut: ' . $chyba->getMessage());
    http_response_code($chyba instanceof InvalidArgumentException ? 400 : 500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $chyba instanceof InvalidArgumentException ? $chyba->getMessage() : 'Export se nepodařilo vytvořit. Zkuste zúžit filtry nebo kontaktujte správce.';
} finally {
    if (is_string($docasnySoubor) && is_file($docasnySoubor)) {
        unlink($docasnySoubor);
    }
    if ($sesit instanceof Spreadsheet) {
        $sesit->disconnectWorksheets();
    }
}
