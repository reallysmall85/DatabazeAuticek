<?php
session_start();

// Připojení k databázi (mysqli)
include __DIR__ . "/Pripojeni/pripojeniDatabaze.php";

$connection = mysqli_connect(SQL_HOST, SQL_USERNAME, SQL_PASSWORD, SQL_DBNAME);
if (!$connection) {
    die("Nepodařilo se připojit k databázi: " . mysqli_connect_error());
}
mysqli_set_charset($connection, "utf8");

// Pokud máš hlavní stránku pod jiným názvem, uprav:
$HOME_URL_BASE = 'index.php';
$SELF_URL = basename($_SERVER['PHP_SELF'] ?? 'kosik.php');

// ===== Načtení aktivních druhů vstupenek z DB =====
// Klíč pro košík/sessionStorage bude ve tvaru "id_5"
$TICKET_DEFS = [];

$sqlVstupenky = "
    SELECT id, nazev, cena, popis, aktivni
    FROM druhyvstupenekdelta
    WHERE LOWER(TRIM(aktivni)) = 'ano'
    ORDER BY id ASC
";

$resultVstupenky = mysqli_query($connection, $sqlVstupenky);
if (!$resultVstupenky) {
    die("Chyba při načítání vstupenek: " . mysqli_error($connection));
}

while ($row = mysqli_fetch_assoc($resultVstupenky)) {
    $id = (int)$row['id'];
    $key = 'id_' . $id;

    $nazev = trim((string)$row['nazev']);
    if ($nazev === '') {
        $nazev = 'Vstupenka #' . $id;
    }

    // Předpoklad: cena je v Kč a používáš celé Kč
    // Pokud bys v budoucnu používal desetinné ceny, upravíme výpočet na haléře.
    $cena = (int) round((float)$row['cena']);

    $TICKET_DEFS[$key] = [
        'id'    => $id,
        'label' => $nazev,
        'price' => $cena,
        'popis' => (string)$row['popis'],
    ];
}

mysqli_free_result($resultVstupenky);
mysqli_close($connection);

function sanitize_qty($value): int {
  $n = (int)$value;
  if ($n < 0) $n = 0;
  if ($n > 99) $n = 99;
  return $n;
}

function normalize_cart(array $rawCart, array $defs): array {
  $cart = [];
  foreach ($defs as $key => $def) {
    $cart[$key] = sanitize_qty($rawCart[$key] ?? 0);
  }
  return $cart;
}

function calc_cart_totals(array $cart, array $defs): array {
  $totalQty = 0;
  $totalPrice = 0;
  $itemTypes = 0;

  foreach ($defs as $key => $def) {
    $qty = sanitize_qty($cart[$key] ?? 0);
    if ($qty <= 0) continue;

    $itemTypes++;
    $totalQty += $qty;
    $totalPrice += $qty * (int)$def['price'];
  }

  return [
    'item_types'  => $itemTypes,
    'total_qty'   => $totalQty,
    'total_price' => $totalPrice, // v Kč
    'currency'    => 'CZK',
  ];
}

function generate_order_no(): string {
  return 'AK-' . date('Ymd-His') . '-' . random_int(1000, 9999);
}

// ===== POST akce: sync_cart / clear_checkout =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'clear_checkout') {
    unset($_SESSION['ticket_checkout']);
    header('Location: ' . $SELF_URL);
    exit;
  }

  if ($action === 'sync_cart') {
    $payload = $_POST['cart_payload'] ?? '';
    $decoded = json_decode($payload, true);

    if (!is_array($decoded)) {
      $decoded = [];
    }

    $cart = normalize_cart($decoded, $TICKET_DEFS);
    $totals = calc_cart_totals($cart, $TICKET_DEFS);

    if ($totals['total_qty'] > 0) {
      $prev = $_SESSION['ticket_checkout'] ?? [];

      $_SESSION['ticket_checkout'] = [
        'order_no'   => $prev['order_no'] ?? generate_order_no(),
        'cart'       => $cart,
        'totals'     => $totals,
        'created_at' => $prev['created_at'] ?? time(),
        'updated_at' => time(),

        'customer' => $prev['customer'] ?? [
          'name'   => '',
          'email'  => '',
          'phone'  => '',
          'adresa' => '',
        ],
      ];
    } else {
      unset($_SESSION['ticket_checkout']);
    }

    header('Location: ' . $SELF_URL . '?step=udaje');
    exit;
  }
}

// (Volitelné) lehká ochrana proti staré session z původní hardcoded verze:
$serverCheckout = $_SESSION['ticket_checkout'] ?? null;
if (is_array($serverCheckout) && isset($serverCheckout['cart']) && is_array($serverCheckout['cart'])) {
    $normalizedServerCart = normalize_cart($serverCheckout['cart'], $TICKET_DEFS);
    $recalcTotals = calc_cart_totals($normalizedServerCart, $TICKET_DEFS);

    if (($recalcTotals['total_qty'] ?? 0) > 0) {
        $_SESSION['ticket_checkout']['cart'] = $normalizedServerCart;
        $_SESSION['ticket_checkout']['totals'] = $recalcTotals;
        $serverCheckout = $_SESSION['ticket_checkout'];
    } else {
        // Pokud po změně definic v DB už nic nesedí, session košík raději smaž
        unset($_SESSION['ticket_checkout']);
        $serverCheckout = null;
    }
}

// Definice pro JS (frontend preview košíku)
$TICKET_DEFS_JS = [];
foreach ($TICKET_DEFS as $key => $def) {
    $TICKET_DEFS_JS[$key] = [
        'id'    => (int)$def['id'],
        'label' => (string)$def['label'],
        'price' => (int)$def['price'],
    ];
}

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Autíčkárium - košík</title>
  <style>
    :root{
      --bg: #fffeeb;
      --text: #0f172a; /* slate-900 */
      --muted: #475569; /* slate-600 */
      --accent: #2563eb; /* blue-600 */
      --accent-2: #22c55e; /* green-500 */
      --card: #ffffff;
      --radius: 14px;
      --header-h: 64px;
    }

    /* Reset & base */
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; scroll-padding-top: var(--header-h); }
    body {
      margin: 0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color: var(--text);
      background: linear-gradient(180deg, var(--bg) 0%, #fffdf3 40%, #ffffff 100%);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      padding-top: var(--header-h);
    }

    a { color: var(--accent); text-decoration: none; }
    a:hover { text-decoration: underline; }

    /* Lišta */
    header.site-header {
      position: fixed; inset: 0 0 auto 0; height: var(--header-h);
      display: flex; align-items: center;
      background: rgba(255,255,255,0.8);
      backdrop-filter: saturate(140%) blur(10px);
      -webkit-backdrop-filter: saturate(140%) blur(10px);
      border-bottom: 1px solid #eaeaea;
      box-shadow: 0 4px 20px rgba(0,0,0,0.06);
      overflow: visible;
      z-index: 1000;
    }
    .nav-wrap {
      width: 100%;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }
    .brand { display:flex; align-items:center; margin-right: 8px; min-width:0; }
    .brand img{
      display:block;
      height: clamp(64px, 15vh, 104px);
      width:auto;
      object-fit: contain;
      margin-bottom: -40px;
    }
    @media (max-width: 480px){
      .brand img{ height: clamp(32px, 6vh, 64px); margin-bottom: -4px; }
    }

    nav ul { list-style: none; margin: 0; padding: 0; display: flex; gap: 10px; }

    /* Horní tlačítka v liště */
    .lista-btn {
      --pad-x: 14px; --pad-y: 10px;
      display: inline-flex; align-items: center; justify-content: center;
      padding: var(--pad-y) var(--pad-x);
      border-radius: 999px;
      font-weight: 600; font-size: 14px;
      color: var(--text);
      background: rgba(15,23,42,0.04);
      border: 1px solid rgba(15,23,42,0.06);
      transition: transform 160ms ease, box-shadow 160ms ease, background 160ms ease, color 160ms ease;
      position: relative; overflow: hidden;
      text-decoration: none;
    }
    .lista-btn:hover {
      transform: translateY(-1px);
      background: rgba(15,23,42,0.08);
      box-shadow: 0 8px 20px rgba(15,23,42,0.15);
      text-decoration: none;
    }

    /* Vstupenky – červený variant */
    .lista-btn-red {
      color: #dc2626;
      background: rgba(220, 38, 38, 0.08);
      border: 1px solid rgba(220, 38, 38, 0.20);
    }
    .lista-btn-red:hover {
      background: rgba(220, 38, 38, 0.14);
      border-color: rgba(220, 38, 38, 0.28);
      box-shadow: 0 8px 20px rgba(220, 38, 38, 0.20);
    }

    /* Layout */
    section { scroll-margin-top: var(--header-h); }
    .container { max-width: 1100px; margin: 0 auto; padding: 32px 16px; }

    .content-card {
      background: var(--card);
      border: 1px solid #eef0f2;
      border-radius: var(--radius);
      padding: 24px;
      box-shadow: 0 8px 18px rgba(0,0,0,0.04);
    }
    .stack { display: grid; gap: 14px; }

    h1, h2, h3 { margin: 0; }
    h2 { font-size: clamp(22px, 3vw, 30px); }
    .muted { color: var(--muted); }

    /* Hero-like intro card */
    .cart-hero {
      margin-top: 8px;
      margin-bottom: 8px;
      background:
        linear-gradient(180deg, rgba(15,23,42,0.70), rgba(15,23,42,0.62)),
        url('Fotky/Slidy/uvod-foto1.JPG') center/cover no-repeat;
      color: #fff;
      border-radius: var(--radius);
      padding: 22px;
      border: 1px solid rgba(255,255,255,0.16);
      box-shadow: 0 10px 24px rgba(0,0,0,0.15);
    }
    .cart-hero p { margin: 8px 0 0; color: #e2e8f0; }

    /* Košík */
    .cart-layout {
      display: grid;
      grid-template-columns: 1.25fr 0.75fr;
      gap: 16px;
      align-items: start;
    }

    .cart-list {
      display: grid;
      gap: 10px;
    }

    .cart-row {
      display: grid;
      grid-template-columns: 1fr auto auto;
      gap: 12px;
      align-items: center;
      padding: 12px 14px;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #fff;
    }

    .cart-row-title {
      font-weight: 700;
      line-height: 1.25;
    }

    .cart-row-meta {
      color: var(--muted);
      font-size: 14px;
      text-align: right;
      white-space: nowrap;
    }

    .cart-row-subtotal {
      font-weight: 700;
      white-space: nowrap;
      min-width: 110px;
      text-align: right;
    }

    .cart-summary {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #fff;
      padding: 16px;
      box-shadow: 0 8px 18px rgba(0,0,0,0.03);
      position: sticky;
      top: calc(var(--header-h) + 16px);
    }

    .summary-grid {
      display: grid;
      gap: 10px;
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
    }

    .summary-line.total {
      margin-top: 4px;
      padding-top: 10px;
      border-top: 1px solid #e5e7eb;
      font-weight: 800;
      font-size: 18px;
    }

    .summary-note {
      color: var(--muted);
      font-size: 13px;
      margin: 0;
    }

    .cart-actions {
      display: grid;
      gap: 10px;
      margin-top: 14px;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 42px;
      padding: 10px 14px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 14px;
      border: 1px solid transparent;
      cursor: pointer;
      text-decoration: none;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, border-color 120ms ease;
    }
    .btn:hover { transform: translateY(-1px); text-decoration: none; }

    .btn-primary {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 10px 20px rgba(37,99,235,0.22);
    }
    .btn-primary:hover {
      box-shadow: 0 12px 24px rgba(37,99,235,0.28);
    }

    .btn-ghost {
      background: #fff;
      color: var(--text);
      border-color: #cbd5e1;
    }

    .btn-danger {
      background: rgba(220,38,38,0.06);
      color: #b91c1c;
      border-color: rgba(220,38,38,0.18);
    }

    .btn:disabled,
    .btn[aria-disabled="true"] {
      opacity: 0.5;
      cursor: not-allowed;
      box-shadow: none;
      transform: none;
      pointer-events: none;
    }

    .empty-state {
      border: 1px dashed #d1d5db;
      border-radius: 12px;
      background: #f8fafc;
      padding: 18px;
      color: var(--muted);
    }

    .mini-info {
      margin-top: 8px;
      color: var(--muted);
      font-size: 13px;
    }

    /* ===== Příprava objednávky (serverová session) ===== */
    .checkout-prep-box {
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      background: #fff;
      padding: 14px;
      display: grid;
      gap: 10px;
    }

    .server-cart-list {
      display: grid;
      gap: 8px;
    }

    .server-cart-row {
      display: grid;
      grid-template-columns: 1fr auto auto;
      gap: 10px;
      align-items: center;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
      padding: 10px 12px;
      font-size: 14px;
    }

    .checkout-form {
      display: grid;
      gap: 12px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .form-field {
      display: grid;
      gap: 6px;
    }

    .form-field label {
      font-weight: 600;
      font-size: 14px;
      color: var(--text);
    }

    .form-field input {
      width: 100%;
      height: 42px;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      padding: 0 12px;
      font: inherit;
      color: var(--text);
      background: #fff;
    }

    .form-field input:focus {
      outline: 2px solid rgba(37,99,235,0.18);
      border-color: var(--accent);
    }

    .checkbox-line {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      color: var(--muted);
      font-size: 14px;
    }

    .checkbox-line input {
      margin-top: 3px;
    }

    /* Footer */
    footer { text-align: center; color: #64748b; padding: 40px 16px 60px; }

    /* Mobile nav & hamburger */
    .menu-toggle{
      display:none; align-items:center; justify-content:center;
      width:42px; height:42px; border-radius:10px;
      border:1px solid rgba(15,23,42,0.12);
      background: rgba(15,23,42,0.06);
      cursor:pointer;
      transition: transform 160ms ease, background 160ms ease, box-shadow 160ms ease;
    }
    .menu-toggle:hover{
      transform: translateY(-1px);
      background: rgba(15,23,42,0.1);
      box-shadow: 0 6px 16px rgba(0,0,0,0.08);
    }
    .menu-toggle .bars{
      position:relative; width:22px; height:2px;
      background: var(--text); border-radius:2px;
    }
    .menu-toggle .bars::before, .menu-toggle .bars::after{
      content:""; position:absolute; left:0;
      width:22px; height:2px; background: var(--text); border-radius:2px;
    }
    .menu-toggle .bars::before{ top:-6px; }
    .menu-toggle .bars::after{ top:6px; }
    .menu-toggle[aria-expanded="true"] .bars{ background: transparent; }
    .menu-toggle[aria-expanded="true"] .bars::before{ top:0; transform: rotate(45deg); }
    .menu-toggle[aria-expanded="true"] .bars::after{ top:0; transform: rotate(-45deg); }

    .mobile-menu{
      position: fixed; top: var(--header-h); left: 0; right: 0; bottom: 0;
      overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch;
      background: rgba(255,255,255,0.92);
      backdrop-filter: saturate(140%) blur(10px); -webkit-backdrop-filter: saturate(140%) blur(10px);
      border-bottom: 1px solid #eaeaea; box-shadow: 0 20px 30px rgba(0,0,0,0.08); z-index: 1050;
      padding-bottom: env(safe-area-inset-bottom);
    }
    .mobile-menu ul{ list-style:none; margin:0; padding:10px; display:grid; gap:8px; }
    .mobile-menu a{
      display:block; padding:12px 14px; border-radius:10px; font-weight:600;
      color: var(--text);
      background: rgba(15,23,42,0.04);
      border:1px solid rgba(15,23,42,0.06);
      text-decoration: none;
    }
    .mobile-menu a:hover{ background: rgba(15,23,42,0.08); text-decoration: none; }

    body.menu-open{ overflow:hidden; }

    /* Responsive */
    @media (max-width: 960px) {
      .cart-layout {
        grid-template-columns: 1fr;
      }
      .cart-summary {
        position: static;
      }
      .cart-row {
        grid-template-columns: 1fr;
        gap: 6px;
      }
      .cart-row-meta,
      .cart-row-subtotal {
        text-align: left;
      }

      .server-cart-row {
        grid-template-columns: 1fr;
        gap: 4px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (min-width: 1200px){
      .menu-toggle{ display:none; }
      .mobile-menu{ display:none !important; }
      nav ul{ display:flex; }
    }
    @media (max-width: 1199px){
      nav ul{ display:none; }
      .menu-toggle{ display:inline-flex; }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="nav-wrap">
      <div class="brand">
        <a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#domu">
          <img src="pozadi-auticka6.png" alt="logo" loading="lazy" />
        </a>
      </div>

      <button class="menu-toggle" aria-label="Otevřít menu" aria-expanded="false" aria-controls="mobile-menu">
        <span class="bars" aria-hidden="true"></span>
      </button>

      <nav aria-label="Hlavní navigace">
        <ul>
          <li><a class="lista-btn" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#domu">Domů</a></li>
          <li><a class="lista-btn" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#aktuality">Aktuality</a></li>
          <li><a class="lista-btn" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#galerie">Galerie</a></li>
          <li><a class="lista-btn" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#onas">O nás</a></li>
          <li><a class="lista-btn" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#kontakt">Kontakt</a></li>
          <li><a class="lista-btn lista-btn-red" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#vstupenky">Vstupenky</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div id="mobile-menu" class="mobile-menu" hidden>
    <ul>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#domu">Domů</a></li>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#aktuality">Aktuality</a></li>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#galerie">Galerie</a></li>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#onas">O nás</a></li>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#kontakt">Kontakt</a></li>
      <li><a href="<?= htmlspecialchars($HOME_URL_BASE) ?>#vstupenky">Vstupenky</a></li>
    </ul>
  </div>

  <section id="kosik-page">
    <div class="container">

      <div class="cart-hero">
        <h1 style="margin:0; font-size: clamp(24px, 4vw, 36px); line-height: 1.2;">Košík vstupenek</h1>
        <p>Zkontrolujte vybraný počet vstupenek. V dalším kroku připravíme objednávku a platbu.</p>
      </div>

      <div class="content-card stack">
        <h2>Souhrn objednávky</h2>
        <p class="muted" style="margin:0;">Zkontrolujte si počet a druh vstupenek.</p>

        <div class="cart-layout">
          <div>
            <div id="kosik-vypis" class="cart-list"></div>
            <p class="mini-info">
              Pokud se vrátíte zpět na stránku vstupenek ve stejném panelu, výběr zůstane zachovaný.
            </p>
          </div>

          <aside class="cart-summary" aria-label="Shrnutí ceny">
            <div class="summary-grid" id="cart-summary-box">
              <!-- Vyplní JS -->
            </div>
            <div class="cart-actions">
              <a class="btn btn-ghost" href="<?= htmlspecialchars($HOME_URL_BASE) ?>#vstupenky">Zpět na výběr vstupenek</a>
              <button type="button" class="btn btn-danger" id="clear-cart-btn">Vyprázdnit košík</button>
              <button type="button" class="btn btn-primary" id="continue-btn" aria-disabled="true">Pokračovat k objednávce (další krok)</button>
            </div>
          </aside>
        </div>
      </div>

      <div class="content-card stack" id="checkout-prep">
        <h2>Další krok – objednávka (příprava pro platbu)</h2>

        <?php if (!empty($serverCheckout) && !empty($serverCheckout['totals']['total_qty'])): ?>
          <p class="muted" style="margin:0;">
            Prosím, zkontrolujte si objenávané zboží a vyplňte údaje níže. E-mail adresa bude sloužit pro zaslání e-vstupenek.
          </p>

          <div class="checkout-prep-box">
            <div class="summary-line">
              <span>Číslo objednávky</span>
              <strong><?= htmlspecialchars((string)$serverCheckout['order_no']) ?></strong>
            </div>
            <div class="summary-line">
              <span>Druhy položek</span>
              <strong><?= (int)($serverCheckout['totals']['item_types'] ?? 0) ?></strong>
            </div>
            <div class="summary-line">
              <span>Počet vstupenek</span>
              <strong><?= (int)($serverCheckout['totals']['total_qty'] ?? 0) ?></strong>
            </div>
            <div class="summary-line total">
              <span>Celkem</span>
              <span><?= number_format((int)($serverCheckout['totals']['total_price'] ?? 0), 0, ',', ' ') ?> Kč</span>
            </div>
          </div>

          <div class="server-cart-list">
            <?php foreach ($TICKET_DEFS as $key => $def): ?>
              <?php $qty = (int)($serverCheckout['cart'][$key] ?? 0); ?>
              <?php if ($qty <= 0) continue; ?>
              <div class="server-cart-row">
                <div><?= htmlspecialchars((string)$def['label']) ?></div>
                <div><?= $qty ?> ks × <?= number_format((int)$def['price'], 0, ',', ' ') ?> Kč</div>
                <div><strong><?= number_format($qty * (int)$def['price'], 0, ',', ' ') ?> Kč</strong></div>
              </div>
            <?php endforeach; ?>
          </div>

          <form id="customer-form" class="checkout-form" method="post" action="#" onsubmit="return false;">
            <div class="form-grid">
              <div class="form-field">
                <label for="cust-name">Jméno a příjmení</label>
                <input type="text" id="cust-name" name="name" placeholder="Jan Novák" autocomplete="name"
                  value="<?= htmlspecialchars((string)($serverCheckout['customer']['name'] ?? '')) ?>">
              </div>

              <div class="form-field">
                <label for="cust-email">E-mail</label>
                <input type="email" id="cust-email" name="email" placeholder="jan@priklad.cz" autocomplete="email"
                  value="<?= htmlspecialchars((string)($serverCheckout['customer']['email'] ?? '')) ?>">
              </div>
              <div class="form-field">
                <label for="cust-adresa">Fakturační adresa</label>
                <input type="tel" id="cust-adresa" name="adresa" placeholder="Ulice, Město, PSČ" autocomplete="adresa"
                  value="<?= htmlspecialchars((string)($serverCheckout['customer']['adresa'] ?? '')) ?>">
              </div>

              <div class="form-field">
                <label for="cust-phone">Telefon (volitelné)</label>
                <input type="tel" id="cust-phone" name="phone" placeholder="+420..." autocomplete="tel"
                  value="<?= htmlspecialchars((string)($serverCheckout['customer']['phone'] ?? '')) ?>">
              </div>
            </div>

            <label class="checkbox-line">
              <input type="checkbox" id="agree-terms">
              <span>Souhlasím s obchodními podmínkami a zpracováním údajů pro účel objednávky.</span>
            </label>

            <div class="cart-actions" style="margin-top: 8px;">
              <button type="button" class="btn btn-primary" id="prepare-payment-btn" disabled>
                Pokračovat na platbu (Comgate – připravíme)
              </button>
            </div>

            <p class="summary-note">
              V dalším kroku napojíme odeslání údajů na server a vytvoření platby přes Comgate z této serverové session.
            </p>
          </form>

        <?php else: ?>
          <div class="empty-state">
            <strong>Další krok - vyplnění údajů.</strong><br>
            Nejprve v horní části klikněte na <em>„Pokračovat k objednávce (další krok)”</em> a následně vyplňte osobní údaje.
          </div>
        <?php endif; ?>
      </div>

    </div>
  </section>

  <footer>
    © <span id="year"></span> Autíčkárium
  </footer>

  <!-- Skryté formuláře pro POST na server -->
  <form id="sync-cart-form" method="post" action="<?= htmlspecialchars($SELF_URL) ?>" hidden>
    <input type="hidden" name="action" value="sync_cart">
    <input type="hidden" name="cart_payload" id="cart-payload-input" value="">
  </form>

  <form id="clear-server-cart-form" method="post" action="<?= htmlspecialchars($SELF_URL) ?>" hidden>
    <input type="hidden" name="action" value="clear_checkout">
  </form>

  <script>
    // =============================
    // Základ / UI helpers
    // =============================
    document.getElementById('year').textContent = new Date().getFullYear();

    // Mobile menu toggle
    const menuBtn = document.querySelector('.menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    function closeMenu(){
      if (!menuBtn || !mobileMenu) return;
      menuBtn.setAttribute('aria-expanded', 'false');
      mobileMenu.classList.remove('open');
      mobileMenu.setAttribute('hidden','');
      document.body.classList.remove('menu-open');
    }

    if (menuBtn && mobileMenu) {
      menuBtn.addEventListener('click', () => {
        const isOpen = menuBtn.getAttribute('aria-expanded') === 'true';
        if (isOpen) { closeMenu(); }
        else {
          menuBtn.setAttribute('aria-expanded', 'true');
          mobileMenu.classList.add('open');
          mobileMenu.removeAttribute('hidden');
          document.body.classList.add('menu-open');
        }
      });

      mobileMenu.addEventListener('click', (e) => {
        if (e.target.matches('a')) closeMenu();
      });

      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeMenu(); });
      document.addEventListener('click', (e) => {
        if (!mobileMenu.classList.contains('open')) return;
        const within = mobileMenu.contains(e.target) || menuBtn.contains(e.target);
        if (!within) closeMenu();
      });
    }

        // =============================
    // Košík ze sessionStorage (dynamicky podle DB)
    // =============================
    const TICKET_STORAGE_KEY = 'autickarium_ticket_selection_v1';

    // Definice aktivních vstupenek z DB (klíče id_X)
    const TICKET_DEFS = <?= json_encode($TICKET_DEFS_JS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function sanitizeQty(value) {
      let n = parseInt(value, 10);
      if (!Number.isFinite(n) || n < 0) n = 0;
      if (n > 99) n = 99;
      return n;
    }

    function getTicketKeys() {
      return Object.keys(TICKET_DEFS);
    }

    function createEmptyCartState() {
      const state = {};
      getTicketKeys().forEach((key) => {
        state[key] = 0;
      });
      return state;
    }

    function readTicketState() {
      const emptyState = createEmptyCartState();

      try {
        const parsed = JSON.parse(sessionStorage.getItem(TICKET_STORAGE_KEY)) || {};
        if (!parsed || typeof parsed !== 'object') return emptyState;

        const normalized = { ...emptyState };
        for (const key of Object.keys(normalized)) {
          normalized[key] = sanitizeQty(parsed[key] ?? 0);
        }

        return normalized;
      } catch (e) {
        return emptyState;
      }
    }

    function formatPrice(value) {
      return new Intl.NumberFormat('cs-CZ').format(value) + ' Kč';
    }

    function pluralizeTickets(total) {
      if (total === 1) return 'vstupenka';
      if (total >= 2 && total <= 4) return 'vstupenky';
      return 'vstupenek';
    }

    function renderCart() {
      const state = readTicketState();
      const listEl = document.getElementById('kosik-vypis');
      const summaryEl = document.getElementById('cart-summary-box');
      const continueBtn = document.getElementById('continue-btn');

      if (!listEl || !summaryEl || !continueBtn) return;

      const ticketKeys = getTicketKeys();

      if (ticketKeys.length === 0) {
        listEl.innerHTML = `
          <div class="empty-state">
            <strong>Aktuálně nejsou dostupné žádné aktivní vstupenky.</strong><br>
            V databázi není žádná položka s hodnotou <em>aktivni = ano</em>.
          </div>
        `;

        summaryEl.innerHTML = `
          <div class="summary-line">
            <span>Položky</span>
            <strong>0</strong>
          </div>
          <div class="summary-line">
            <span>Počet vstupenek</span>
            <strong>0</strong>
          </div>
          <div class="summary-line total">
            <span>Celkem</span>
            <span>${formatPrice(0)}</span>
          </div>
          <p class="summary-note">Nejprve aktivujte druhy vstupenek v databázi.</p>
        `;

        continueBtn.setAttribute('aria-disabled', 'true');
        continueBtn.disabled = true;
        return;
      }

      let totalQty = 0;
      let totalPrice = 0;
      let rowsHtml = '';
      let itemTypesCount = 0;

      for (const key of ticketKeys) {
  const def = TICKET_DEFS[key];
  if (!def) continue;

  const qty = sanitizeQty(state[key] || 0);
  if (qty <= 0) continue;

  let unitPrice = parseInt(def.price, 10);
  if (!Number.isFinite(unitPrice) || unitPrice < 0) unitPrice = 0;

  const subtotal = qty * unitPrice;

        itemTypesCount++;
        totalQty += qty;
        totalPrice += subtotal;

        rowsHtml += `
          <div class="cart-row">
            <div>
              <div class="cart-row-title">${String(def.label || 'Vstupenka')}</div>
            </div>
            <div class="cart-row-meta">${qty} ks × ${formatPrice(unitPrice)}</div>
            <div class="cart-row-subtotal">${formatPrice(subtotal)}</div>
          </div>
        `;
      }

      if (totalQty === 0) {
        listEl.innerHTML = `
          <div class="empty-state">
            <strong>Košík je zatím prázdný.</strong><br>
            Vraťte se na stránku vstupenek a vyberte počet kusů.
          </div>
        `;
        summaryEl.innerHTML = `
          <div class="summary-line">
            <span>Položky</span>
            <strong>0</strong>
          </div>
          <div class="summary-line">
            <span>Počet vstupenek</span>
            <strong>0</strong>
          </div>
          <div class="summary-line total">
            <span>Celkem</span>
            <span>${formatPrice(0)}</span>
          </div>
          <p class="summary-note">Platba bude doplněna v dalších krocích.</p>
        `;
        continueBtn.setAttribute('aria-disabled', 'true');
        continueBtn.disabled = true;
        return;
      }

      listEl.innerHTML = rowsHtml;

      summaryEl.innerHTML = `
        <div class="summary-line">
          <span>Druhy položek</span>
          <strong>${itemTypesCount}</strong>
        </div>
        <div class="summary-line">
          <span>Počet vstupenek</span>
          <strong>${totalQty} ${pluralizeTickets(totalQty)}</strong>
        </div>
        <div class="summary-line total">
          <span>Celkem</span>
          <span>${formatPrice(totalPrice)}</span>
        </div>
        <p class="summary-note">Celková částka k úhradě.</p>
      `;

      continueBtn.removeAttribute('aria-disabled');
      continueBtn.disabled = false;
    }

    // Vyprázdnění košíku (sessionStorage + PHP session)
    const clearCartBtn = document.getElementById('clear-cart-btn');
    const clearServerCartForm = document.getElementById('clear-server-cart-form');

    if (clearCartBtn) {
      clearCartBtn.addEventListener('click', () => {
        sessionStorage.removeItem(TICKET_STORAGE_KEY);

        // Smaž i serverovou session objednávku (pokud existuje)
        if (clearServerCartForm) {
          clearServerCartForm.submit();
          return;
        }

        renderCart();
      });
    }

    // Převod košíku ze sessionStorage do PHP session (příprava pro objednávku/Comgate)
    const continueBtn = document.getElementById('continue-btn');
    const syncCartForm = document.getElementById('sync-cart-form');
    const cartPayloadInput = document.getElementById('cart-payload-input');

    if (continueBtn) {
      continueBtn.addEventListener('click', () => {
        const state = readTicketState();
        const normalizedState = {};
        let total = 0;

        for (const key of getTicketKeys()) {
          const qty = sanitizeQty(state[key] || 0);
          normalizedState[key] = qty;
          total += qty;
        }

        if (total <= 0) return;

        if (!syncCartForm || !cartPayloadInput) {
          alert('Nepodařilo se připravit odeslání košíku na server.');
          return;
        }

        cartPayloadInput.value = JSON.stringify(normalizedState);

        continueBtn.disabled = true;
        continueBtn.setAttribute('aria-disabled', 'true');
        continueBtn.textContent = 'Ukládám košík...';

        syncCartForm.submit();
      });
    }

    // Placeholder pro "pokračovat na platbu" – aktivace dle formuláře
    const customerForm = document.getElementById('customer-form');
    const preparePaymentBtn = document.getElementById('prepare-payment-btn');
    const agreeTerms = document.getElementById('agree-terms');
    const custName = document.getElementById('cust-name');
    const custAdresa = document.getElementById('cust-adresa');
    const custEmail = document.getElementById('cust-email');

    function isValidEmail(email) {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email).trim());
    }

    function updatePreparePaymentBtnState() {
      if (!preparePaymentBtn) return;
      const hasName = custName && custName.value.trim().length >= 2;
      const hasAdresa = custAdresa && custAdresa.value.trim().length >= 2;
      const hasValidEmail = custEmail && isValidEmail(custEmail.value);
      const hasTerms = agreeTerms && agreeTerms.checked;

      const enabled = !!(hasName && hasValidEmail && hasTerms && hasAdresa);
      preparePaymentBtn.disabled = !enabled;
      preparePaymentBtn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
    }

    if (customerForm && preparePaymentBtn) {
      [custName, custEmail, agreeTerms, custAdresa].forEach((el) => {
        if (!el) return;
        el.addEventListener('input', updatePreparePaymentBtnState);
        el.addEventListener('change', updatePreparePaymentBtnState);
      });

      preparePaymentBtn.addEventListener('click', () => {
        if (preparePaymentBtn.disabled) return;
        alert('Další krok: uložíme údaje zákazníka do PHP session a vytvoříme backend endpoint pro Comgate (příprava platby).');
      });

      updatePreparePaymentBtnState();
    }

    // Init
    renderCart();

    // Po uložení košíku na server scrollni na část "Další krok"
    const params = new URLSearchParams(window.location.search);
    if (params.get('step') === 'udaje') {
      const prep = document.getElementById('checkout-prep');
      if (prep) {
        prep.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  </script>
</body>
</html>