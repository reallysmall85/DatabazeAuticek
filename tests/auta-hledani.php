<?php
// Spustit: php tests/auta-hledani.php (vyžaduje mbstring, nepotřebuje databázi).
require_once __DIR__ . '/../Auta-hledani.php';
function overit($stav, $popis) {
    if (!$stav) {
        throw new RuntimeException($popis);
    }
}
[$sql, $parametry] = autaRozebratHledani('Ferrari "XY 12": číslo žlutá');
overit(count($parametry) === 41, 'Dvě obecná slova a jedna cílená fráze');
overit($parametry[20] === '%XY 12%', 'Zachování mezery uvnitř fráze');
overit(substr_count($sql, "COALESCE(cislo,'')") === 3, 'Cílená fráze pro číslo');
overit(strpos($sql, 'Ferrari') === false, 'Hodnoty nejsou součástí SQL');
foreach (['"XY 12":cislo', '"XY 12" : ČÍSLO', '„XY 12“: číslo'] as $text) {
    [$sql, $parametry] = autaRozebratHledani($text);
    overit($parametry === ['%XY 12%'] && substr_count($sql, '?') === 1, $text);
}
[$sql, $parametry] = autaRozebratHledani('"světle žlutá":barva "Jan Novák":jezdec');
overit(count($parametry) === 8, 'Sdružené sloupce');
[$sql, $parametry] = autaRozebratHledani('"XY 12"');
overit(count($parametry) === 20 && count(array_unique($parametry)) === 1, 'Obecná fráze');
[$sql, $parametry] = autaRozebratHledani('"50%_!":nazev');
overit($parametry === ['%50!%!_!!%'], 'Doslovné speciální znaky LIKE');
[$sql, $parametry] = autaRozebratHledani('0');
overit(count($parametry) === 20 && $parametry[0] === '%0%', 'Nula není prázdné hledání');
overit(autaRozebratHledani('  ') === ['', []], 'Prázdné hledání');
foreach (['"neuzavřeno', '"":cislo', '"x":', '"x":neexistuje', '"x"slovo', 'slovo"x"', "\xFF"] as $text) {
    try {
        autaRozebratHledani($text);
    } catch (InvalidArgumentException $e) {
        continue;
    }
    throw new RuntimeException('Chybělo odmítnutí: ' . $text);
}
foreach ([
    'firmy' => 'firma',
    'čísla' => 'cislo',
    'cisla' => 'cislo',
    'barvy' => 'barva',
    'start.č.' => 'startovnicislo',
    'start.c.' => 'startovnicislo',
    'zavody' => 'zavod',
    'závody' => 'zavod',
] as $alias => $sloupec) {
    overit(
        autaRozebratHledani('"XY 12": ' . $alias) === autaRozebratHledani('"XY 12": ' . $sloupec),
        'Alias sloupce: ' . $alias
    );
}
[$sql, $parametry] = autaRozebratHledani('"FW*14":číslo');
overit(substr_count($sql, 'REGEXP ?') === 1 && count($parametry) === 1, 'Hvězdička v cílené frázi');
foreach (['FW14', 'FW 14', 'FW-14', 'FW_14', 'FW/14', 'FW\\14', 'FW|14', 'FW:14', 'FW;14', 'FW+14', 'Williams FW-14B'] as $hodnota) {
    overit(preg_match('~' . $parametry[0] . '~u', $hodnota) === 1, 'Povolený oddělovač: ' . $hodnota);
}
foreach (['FW', '14', 'FWX14', 'FW--14', 'FW  14', 'FW.14', "FW\t14"] as $hodnota) {
    overit(preg_match('~' . $parametry[0] . '~u', $hodnota) === 0, 'Nepovolená shoda: ' . $hodnota);
}
[$sql, $parametry] = autaRozebratHledani('Ferrari FW*14 žlutá');
overit(count($parametry) === 60 && substr_count($sql, 'REGEXP ?') === 20, 'Kombinace běžných slov a hvězdičky');
[$sql, $parametry] = autaRozebratHledani('"A*B*C":cislo');
overit(preg_match('~' . $parametry[0] . '~u', 'A-B/C') === 1, 'Více oddělovačů');
[$sql, $parametry] = autaRozebratHledani('"A.+(B)*14":cislo');
overit(preg_match('~' . $parametry[0] . '~u', 'A.+(B)-14') === 1, 'Doslovná regex interpunkce');
overit(preg_match('~' . $parametry[0] . '~u', 'AXXXB-14') === 0, 'Regex nelze podstrčit');
echo "OK: parser hledání\n";
