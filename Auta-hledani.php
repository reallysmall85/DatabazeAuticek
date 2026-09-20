<?php
/** Rozebere hlavní hledání; názvy SQL sloupců pocházejí jen z této mapy. */
function autaRozebratHledani(string $text): array
{
    if (!mb_check_encoding($text, 'UTF-8')) {
        throw new InvalidArgumentException('Hledání musí být platný text UTF-8.');
    }
    $mapa = [
        'firma' => ['firma1', 'firma2'],
        'cislo' => ['cislo'], 'nazev' => ['nazev'], 'upresneni' => ['upresneni'],
        'barva' => ['barva1', 'barva2', 'barva3', 'barva4', 'barva5'],
        'serie' => ['serie'], 'zavod' => ['zavod'],
        'startovnicislo' => ['startovnicislo'], 'tym' => ['tym'], 'reklama' => ['reklama'],
        'jezdec' => ['jezdec1', 'jezdec2', 'jezdec3'],
        'rok' => ['rok'], 'poznamka' => ['poznamka'],
    ];
    $vsechny = array_merge(...array_values($mapa));
    $mapa['firmy'] = $mapa['firma'];
    $mapa['cisla'] = $mapa['cislo'];
    $mapa['start.c.'] = $mapa['startovnicislo'];
    $mapa['barvy'] = $mapa['barva'];
    $mapa['zavody'] = $mapa['zavod'];
    foreach ($vsechny as $sloupec) {
        $mapa[$sloupec] = $mapa[$sloupec] ?? [$sloupec];
    }
    // Podpora českých typografických i běžných dvojitých uvozovek.
    $text = str_replace(['„', '“', '”'], '"', $text);
    $podminky = [];
    $parametry = [];
    $pozice = 0;
    while ($pozice < strlen($text)) {
        preg_match('/\G\s*/u', $text, $mezery, 0, $pozice);
        $pozice += strlen($mezery[0]);
        if ($pozice === strlen($text)) {
            break;
        }
        $sloupce = $vsechny;
        if ($text[$pozice] === '"') {
            $konec = strpos($text, '"', $pozice + 1);
            if ($konec === false) {
                throw new InvalidArgumentException('V hledání chybí uzavírací uvozovky.');
            }
            $hodnota = substr($text, $pozice + 1, $konec - $pozice - 1);
            if (trim($hodnota) === '') {
                throw new InvalidArgumentException('Fráze v uvozovkách nesmí být prázdná.');
            }
            $pozice = $konec + 1;
            if (preg_match('/\G\s*:\s*([^\s"]*)/u', $text, $urceni, 0, $pozice)) {
                $nazev = strtr(mb_strtolower($urceni[1], 'UTF-8'), [
                    'á'=>'a', 'č'=>'c', 'ď'=>'d', 'é'=>'e', 'ě'=>'e', 'í'=>'i',
                    'ň'=>'n', 'ó'=>'o', 'ř'=>'r', 'š'=>'s', 'ť'=>'t', 'ú'=>'u', 'ů'=>'u', 'ý'=>'y', 'ž'=>'z',
                ]);
                if (!isset($mapa[$nazev])) {
                    throw new InvalidArgumentException('Chybějící nebo neznámý sloupec za dvojtečkou: ' . $urceni[1]);
                }
                $sloupce = $mapa[$nazev];
                $pozice += strlen($urceni[0]);
            }
            if ($pozice < strlen($text) && !preg_match('/\G\s/u', $text, $shoda, 0, $pozice)) {
                throw new InvalidArgumentException('Jednotlivé výrazy hledání oddělte mezerou.');
            }
        } else {
            preg_match('/\G[^\s"]+/u', $text, $slovo, 0, $pozice);
            $hodnota = $slovo[0];
            $pozice += strlen($hodnota);
            if ($pozice < strlen($text) && $text[$pozice] === '"') {
                throw new InvalidArgumentException('Před frází v uvozovkách musí být mezera.');
            }
        }
        $seZastupnymZnakem = strpos($hodnota, '*') !== false;
        if ($seZastupnymZnakem) {
            // Každá hvězdička znamená nula nebo jeden povolený oddělovač.
            // Ostatní znaky jsou doslovné, uživatel nezadává regulární výraz.
            $casti = array_map(static function ($cast) {
                return strtr($cast, array_combine(
                    str_split('\\.^$|?+()[]{}'),
                    array_map(static function ($znak) { return '\\' . $znak; }, str_split('\\.^$|?+()[]{}'))
                ));
            }, explode('*', $hodnota));
            $vzor = implode('[ _/\\\\|:;+-]?', $casti);
        } else {
            $vzor = '%' . strtr($hodnota, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        $alternativy = [];
        foreach ($sloupce as $sloupec) {
            $vyraz = $sloupec === 'rok' ? 'CAST(rok AS CHAR)' : $sloupec;
            $alternativy[] = $seZastupnymZnakem
                ? "COALESCE($vyraz,'') REGEXP ?"
                : "COALESCE($vyraz,'') LIKE ? ESCAPE '!'";
            $parametry[] = $vzor;
        }
        $podminky[] = '(' . implode(' OR ', $alternativy) . ')';
    }
    return [implode(' AND ', $podminky), $parametry];
}

/** Stejné parametry používá počet nálezů, stránka i export všech nálezů. */
function autaProvestDotaz(mysqli $connection, string $sql, array $parametry)
{
    $stmt = mysqli_prepare($connection, $sql);
    if ($stmt === false) {
        throw new RuntimeException('Nepodařilo se připravit vyhledávání.');
    }
    try {
        if ($parametry) {
            mysqli_stmt_bind_param($stmt, str_repeat('s', count($parametry)), ...$parametry);
        }
        mysqli_stmt_execute($stmt);
        return mysqli_stmt_get_result($stmt);
    } finally {
        mysqli_stmt_close($stmt);
    }
}
