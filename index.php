<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Autíčkárium</title>
  <style>
    :root{
      --bg: #fffeeb;
      --text: #0f172a; /* slate-900 */
      --muted: #475569; /* slate-600 */
      --accent: #2563eb; /* blue-600 */
      --accent-2: #22c55e; /* green-500 (subtle flourish) */
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
      padding-top: var(--header-h); /* prostor na lištu */
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
    .nav-wrap { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .brand { display:flex; align-items:center; margin-right: 8px; min-width:0; }
    .brand img{ display:block; height: clamp(64px, 15vh, 104px); width:auto; object-fit: contain; margin-bottom: -40px; }
    @media (max-width: 480px){ .brand img{ height: clamp(32px, 6vh, 64px); margin-bottom: -4px; } }

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
    }
    .lista-btn:hover {
      transform: translateY(-1px);
      background: rgba(15,23,42,0.08);
      box-shadow: 0 8px 20px rgba(15,23,42,0.15);
    }
    .lista-btn.active {
      background: var(--text);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 10px 24px rgba(15,23,42,0.25);
    }

    /* Vstupenky – červený variant */
    .lista-btn-red {
      --pad-x: 14px; --pad-y: 10px;
      display: inline-flex; align-items: center; justify-content: center;
      padding: var(--pad-y) var(--pad-x);
      border-radius: 999px;
      font-weight: 600; font-size: 14px;
      color: red;
      background: rgba(220, 38, 38, 0.08);
      border: 1px solid rgba(220, 38, 38, 0.20);
      transition: transform 160ms ease, box-shadow 160ms ease,
                  background 160ms ease, color 160ms ease, border-color 160ms ease;
      position: relative; overflow: hidden;
    }
    .lista-btn-red:hover {
      transform: translateY(-1px);
      background: rgba(220, 38, 38, 0.14);
      border-color: rgba(220, 38, 38, 0.28);
      box-shadow: 0 8px 20px rgba(220, 38, 38, 0.20);
    }
    .lista-btn-red.active {
      background: #dc2626;
      color: #fff;
      border-color: #dc2626;
      box-shadow: 0 10px 24px rgba(220, 38, 38, 0.35);
    }

    /* Login link */
    .login-link { position: fixed; top: 12px; right: 16px; z-index: 1100; }

    /* Sections */
    section { scroll-margin-top: var(--header-h); }
    .container { max-width: 1100px; margin: 0 auto; padding: 32px 16px; }

    /* ===== Hero full-viewport se slideshow ===== */
    #domu.hero-full { 
      position: relative; 
      min-height: calc(100dvh - var(--header-h)); /* přes celou výšku okna pod lištou */
      isolation: isolate; /* vlastní stacking kontext pro překryvy */
      display: flex;
      align-items: center; /* vertikální centrování obsahu */
    }
    #domu.hero-full .container { width: 100%; }
    /* vrstva s pozadím */
    .hero-bg { position: absolute; inset: 0; z-index: 0; overflow: hidden; }
    .hero-bg .slide {
      position: absolute; inset: 0;
      background-size: cover; background-position: center;
      opacity: 0; 
      animation: heroFade 18s infinite ease-in-out;
      animation-delay: calc(var(--i) * 6s); /* 0s, 3s, 6s */
      will-change: opacity;
      transform: translateZ(0); /* HW akcelerace kde to jde */
    }
    /* Přidej jemné ztmavení kvůli čitelnosti obsahu */
    .hero-bg::after{
      content:"";
      position: absolute; inset: 0;
      background: linear-gradient(180deg, rgba(0,0,0,0.30), rgba(0,0,0,0.20));
      z-index: 1;
      pointer-events: none;
    }
    @keyframes heroFade {
      /* 3s na snímek, s rychlým náběhem a doběhem */
      0%   { opacity: 0; }
      5%   { opacity: 1; }
      30%  { opacity: 1; }
      35%  { opacity: 0; }
      100% { opacity: 0; }
    }
    /* obsah je nad pozadím */
    .hero { 
      position: relative; 
      z-index: 2; 
      display: grid; grid-template-columns: 1.2fr 0.8fr; 
      gap: 24px; align-items: center; 
      padding: 48px 0; 
      color: #fff; /* lepší kontrast na tmavším podkladu */
    }
    .hero > * { min-width: 0; }
    .hero-card { background: rgba(0,0,0,0.35); border: 1px solid rgba(255,255,255,0.25); border-radius: var(--radius); padding: 28px; box-shadow: 0 10px 24px rgba(0,0,0,0.25); }
    .hero h1 { margin: 0 0 10px; font-size: clamp(28px, 5vw, 48px); line-height: 1.2; }
    .hero p.lead { color: #f1f5f9; font-size: 16px; margin: 0; }
    .hero img { width: 100%; height: 100%; object-fit: cover; display: block; opacity: 0.85; }

    /* aktuality */
    .hero-aktuality {
      aspect-ratio: 4/3; width: 100%;
      background: rgba(255,255,255,0.12);
      border-radius: var(--radius);
      border: 1px dashed rgba(255,255,255,0.35);
      display: grid; place-items: center; color: #e2e8f0;
      font-weight: 600; overflow: hidden;
      backdrop-filter: blur(2px);
    }
    .hero-aktuality img { width: 100%; height: 100%; object-fit: cover; display: block; opacity: 0.85; }
    
    /* Výrazný (important) styl pro box */
.hero-aktuality.is-important{
  position: relative;
  background: linear-gradient(180deg,
              rgba(255, 213, 79, 0.62),   /* #FFD54F */
              rgba(255, 193, 7, 0.58));   /* #FFC107 */
  border: 1px solid rgba(255, 193, 7, 0.55);
  border-radius: var(--radius);
  box-shadow:
    0 12px 30px rgba(217, 119, 6, 0.35),  /* teplejší stín */
    inset 0 1px 0 rgba(255,255,255,0.55);  /* jemný vnitřní lesk */
  color: #1f2937; /* tmavý text kvůli kontrastu */
  font-size: clamp(14px, 3vw, 30px);
  backdrop-filter: none; /* zruší rozmazání pozadí, ať je čistá žlutá */
  transition: transform 160ms ease, box-shadow 160ms ease;
  text-shadow:
    0 0 2px rgba(255,255,255,.95),
    0 0 6px rgba(255,255,255,.55),
    0 0 10px rgba(255,255,255,.35);
}

/* jemný zvýrazňující proužek nahoře */
.hero-aktuality.is-important::before{
  content:"";
  position:absolute; inset:0;
  border-radius: inherit;
  box-shadow: inset 0 10px 24px rgba(255,255,255,0.25);
  pointer-events:none;
}


/* interakce */
.hero-aktuality.is-important:hover{
  transform: translateY(-1px);
  box-shadow:
    0 16px 40px rgba(217, 119, 6, 0.42),
    inset 0 1px 0 rgba(255,255,255,0.6);
}

/* pokud uvnitř necháš obrázek jako backgroundový prvek */
.hero-aktuality.is-important img{
  opacity: 0.25;                 /* ať nepřebije text */
  filter: saturate(90%) contrast(95%);
}


    /* Content blocks (další sekce) */
    .content-card { background: var(--card); border: 1px solid #eef0f2; border-radius: var(--radius); padding: 24px; box-shadow: 0 8px 18px rgba(0,0,0,0.04); }
    .stack { display: grid; gap: 14px; }
    h2 { margin: 0 0 6px; font-size: clamp(20px, 3vw, 28px); }
    .muted { color: var(--muted); }

    /* Gallery 2x2 */
    .gallery-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .gallery-item { aspect-ratio: 3 / 4; border-radius: 12px; overflow: hidden; border: 1px solid #eef0f2; background: #f8fafc; display: grid; place-items: center; color: #64748b; font-weight: 600; }
    .gallery-item img {width: 100%; height: 100%; object-fit: cover; display: block; }

    /* Footer spacer */
    footer { text-align: center; color: #64748b; padding: 40px 16px 60px; }

    /* Mobile nav & hamburger (ponecháno) */
    .menu-toggle{ display:none; align-items:center; justify-content:center; width:42px; height:42px; border-radius:10px; border:1px solid rgba(15,23,42,0.12); background: rgba(15,23,42,0.06); cursor:pointer; transition: transform 160ms ease, background 160ms ease, box-shadow 160ms ease; }
    .menu-toggle:hover{ transform: translateY(-1px); background: rgba(15,23,42,0.1); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }
    .menu-toggle .bars{ position:relative; width:22px; height:2px; background: var(--text); border-radius:2px; }
    .menu-toggle .bars::before, .menu-toggle .bars::after{ content:""; position:absolute; left:0; width:22px; height:2px; background: var(--text); border-radius:2px; }
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
    .mobile-menu a{ display:block; padding:12px 14px; border-radius:10px; font-weight:600; color: var(--text); background: rgba(15,23,42,0.04); border:1px solid rgba(15,23,42,0.06); }
    .mobile-menu a:hover{ background: rgba(15,23,42,0.08); }

    body.menu-open{ overflow:hidden; }

    /* Responsive tweaks */
    @media (max-width: 860px) {
      .hero { grid-template-columns: 1fr; }
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
  <a href="Prihlaseni.php" class="login-link">Přihlášení</a>

  <header class="site-header">
    <div class="nav-wrap">
      <div class="brand"><a href="#domu"><img src="pozadi-auticka6.png" alt="logo" loading="lazy" /></a></div>
      <button class="menu-toggle" aria-label="Otevřít menu" aria-expanded="false" aria-controls="mobile-menu">
        <span class="bars" aria-hidden="true"></span>
      </button>
      <nav aria-label="Hlavní navigace">
        <ul>
          <li><a class="lista-btn" href="#domu">Domů</a></li>
          <li><a class="lista-btn" href="#aktuality">Aktuality</a></li>
          <li><a class="lista-btn" href="#galerie">Galerie</a></li>
          <li><a class="lista-btn" href="#onas">O nás</a></li>
          <li><a class="lista-btn" href="#kontakt">Kontakt</a></li>
          <li><a class="lista-btn lista-btn-red" href="#vstupenky">Vstupenky</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <div id="mobile-menu" class="mobile-menu" hidden>
    <ul>
      <li><a href="#domu">Domů</a></li>
      <li><a href="#aktuality">Aktuality</a></li>
      <li><a href="#galerie">Galerie</a></li>
      <li><a href="#onas">O nás</a></li>
      <li><a href="#kontakt">Kontakt</a></li>
      <li><a href="#vstupenky">Vstupenky</a></li>
    </ul>
  </div>

  <!-- Domů / Full hero se slideshow -->
  <section id="domu" class="hero-full">
    <!-- POZADÍ: nastav sem svoje 3 obrázky -->
    <div class="hero-bg" aria-hidden="true">
      <div class="slide" style="--i:0; background-image:url('Fotky/Slidy/uvod-foto1.JPG');"></div>
      <div class="slide" style="--i:1; background-image:url('Fotky/Slidy/uvod-foto2.JPG');"></div>
      <div class="slide" style="--i:2; background-image:url('Fotky/Slidy/uvod-foto3.JPG');"></div>
    </div>

    <div class="container">
      <div class="hero">
        <div class="hero-card">
          <h1>Vítejte v Autíčkáriu</h1>
          <p class="lead">Tyto stránky jsou ve výrobě.</p>
          <p class="lead">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer tempor, odio a vehicula aliquet, lectus lorem euismod risus, id vulputate turpis libero vel justo. Aenean varius, metus nec finibus rutrum, urna lorem facilisis orci, sed condimentum mauris nibh vitae nibh.</p>
          <!-- <img src="pozadi-auticka6.png" alt="logo" loading="lazy" /> -->
        </div>
        
        <div class="hero-aktuality is-important">
  			
  			<div style="position:relative; padding:18px;">
    			<strong>Připravujeme to!</strong>
    			<p>Zatím na tom makáme, ale brzy tu budou super stránky :)</p>
  			</div>
		</div>
      </div>
    </div>
  </section>

  <!-- Aktuality -->
  <section id="aktuality">
    <div class="container">
      <div class="content-card stack">
        <h2>Aktuality</h2>
        <p class="muted">Co je nového v našem světě modelů.</p>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer tempor, odio a vehicula aliquet, lectus lorem euismod risus, id vulputate turpis libero vel justo. Aenean varius, metus nec finibus rutrum, urna lorem facilisis orci, sed condimentum mauris nibh vitae nibh.</p>
        <p>Curabitur bibendum, velit at maximus aliquet, urna massa tincidunt ipsum, a viverra nunc felis sit amet arcu. Pellentesque vel metus at dui fermentum accumsan. Suspendisse vel est eget ipsum fringilla bibendum sed in dolor.</p>
      </div>
    </div>
  </section>

  <!-- Galerie 2x2 -->
  <section id="galerie">
    <div class="container">
      <div class="content-card stack">
        <h2>Galerie</h2>
        <p class="muted">Ochutnávka z naší výstavy, přijďte se podívat sami.</p>
        <div class="gallery-grid">
          <div class="gallery-item">
            <img src="Fotky/Galerie/galerie1.JPG" alt="Ukázka 1" loading="lazy" />
          </div>
          <div class="gallery-item">
            <img src="Fotky/Galerie/galerie2.JPG" alt="Ukázka 2" loading="lazy" />
          </div>
          <div class="gallery-item">
            <img src="Fotky/Galerie/galerie3.JPG" alt="Ukázka 3" loading="lazy" />
          </div>
          <div class="gallery-item">
            <img src="Fotky/Galerie/galerie4.JPG" alt="Ukázka 4" loading="lazy" />
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- O nás -->
  <section id="onas">
    <div class="container">
      <div class="content-card stack">
        <h2>O nás</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed vitae congue urna, vitae bibendum eros. Donec ac quam sit amet magna convallis hendrerit. Phasellus nec mi eu justo pellentesque accumsan.</p>
        <p>Aliquam eget felis ut ante elementum sodales. Mauris viverra, augue non tempus fringilla, ipsum tellus sollicitudin ipsum, ut viverra tortor libero id augue.</p>
      </div>
    </div>
  </section>

  <!-- Kontakt -->
  <section id="kontakt">
    <div class="container">
      <div class="content-card stack">
        <h2>Kontakt</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Praesent molestie nunc at enim auctor, at fermentum augue luctus. Integer gravida, orci a auctor pulvinar, justo metus dignissim nunc, ut volutpat nibh purus at massa.</p>
        <p>Fusce ac mi vel velit aliquam ultricies. Pellentesque sed nisl vitae justo tincidunt luctus. Vestibulum posuere velit et diam porttitor, id faucibus tellus dictum.</p>
      </div>
    </div>
  </section>

  <!-- Vstupenky -->
  <section id="vstupenky">
    <div class="container">
      <div class="content-card stack">
        <h2>Vstupenky</h2>
        <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque non lorem ac mi dapibus euismod in vitae quam. Nam at sapien fermentum, faucibus sem non, facilisis felis.</p>
        <p>Vivamus ullamcorper, dui ac dictum bibendum, neque mauris ultrices purus, non efficitur erat nibh non dolor. Cras vitae interdum est. Integer vitae mi in massa convallis venenatis.</p>
         <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Quisque non lorem ac mi dapibus euismod in vitae quam. Nam at sapien fermentum, faucibus sem non, facilisis felis.</p>
        <p>Vivamus ullamcorper, dui ac dictum bibendum, neque mauris ultrices purus, non efficitur erat nibh non dolor. Cras vitae interdum est. Integer vitae mi in massa convallis venenatis.</p>
      </div>
    </div>
  </section>

  <footer>
    © <span id="year"></span> Autíčkárium
  </footer>

  <script>
    // dyn year
    document.getElementById('year').textContent = new Date().getFullYear();

    // highlight active nav item while scrolling
    const sections = Array.from(document.querySelectorAll('section[id]'));
    const links = new Map(Array.from(document.querySelectorAll('a.lista-btn')).map(a => [a.getAttribute('href').replace('#',''), a]));

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        const id = entry.target.id;
        const link = links.get(id);
        if (!link) return;
        if (entry.isIntersecting) {
          document.querySelectorAll('.lista-btn.active').forEach(el => el.classList.remove('active'));
          link.classList.add('active');
        }
      });
    }, { rootMargin: `-40% 0px -55% 0px`, threshold: 0.01 });

    sections.forEach(sec => observer.observe(sec));

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
  </script>
</body>
</html>
