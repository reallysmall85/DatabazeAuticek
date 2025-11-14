<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Hra Honzik</title>
  <style>
    html, body { height: 100%; margin: 0; }
    .hero{
      position: relative;
      min-height: 100vh;
      background: url('islands-hero.svg') center / cover no-repeat fixed;
      overflow: hidden;
    }
    #grid{
      position: absolute;
      inset: 0;
      display: grid;
      grid-template-columns: repeat(20, 1fr);
      grid-template-rows: repeat(10, 1fr);
      user-select: none;
      -webkit-user-select: none;
      touch-action: manipulation;
    }
    .cell{
      box-sizing: border-box;
      border: 1px solid rgba(255,255,255,0.28);
      cursor: pointer;
      background-repeat: no-repeat;
      background-position: center;
      background-size: contain;
    }
    .cell:active{
      outline: 2px solid rgba(255,255,255,0.5);
      outline-offset: -2px;
    }
  </style>
</head>
<body>
  <div class="hero">
    <div id="grid" aria-label="Klikací mřížka 20×10" role="grid"></div>
  </div>

  <script>
    const COLS = 20, ROWS = 10;

    // Sady obrázků
    const MONSTERS = ['Prisera1.png', 'Prisera2.png'];
    const HEROES   = ['Hrdina1.png', 'Hrdina2.png', 'Hrdina3.png', 'Hrdina4.png'];

    // Preload všech obrázků pro rychlou odezvu
    [...MONSTERS, ...HEROES].forEach(src => { const i = new Image(); i.src = src; });

    const grid = document.getElementById('grid');

    // Vytvoření buněk
    const frag = document.createDocumentFragment();
    for (let r = 0; r < ROWS; r++) {
      for (let c = 0; c < COLS; c++) {
        const cell = document.createElement('div');
        cell.className = 'cell';
        cell.setAttribute('role', 'gridcell');
        cell.dataset.row = r;
        cell.dataset.col = c;
        frag.appendChild(cell);
      }
    }
    grid.appendChild(frag);

    // Pomocná funkce pro náhodný výběr
    const pick = arr => arr[Math.floor(Math.random() * arr.length)];

    // Levý klik: náhodná příšera (Prisera1/2.png)
    grid.addEventListener('click', (e) => {
      const cell = e.target.closest('.cell');
      if (!cell || cell.dataset.filled === '1') return;
      cell.style.backgroundImage = `url('${pick(MONSTERS)}')`;
      cell.dataset.filled = '1';
    });

    // Pravý klik: náhodný hrdina (Hrdina1–4.png) + potlačit kontextové menu
    grid.addEventListener('contextmenu', (e) => {
      const cell = e.target.closest('.cell');
      if (!cell) return;
      e.preventDefault();
      if (cell.dataset.filled === '1') return;
      cell.style.backgroundImage = `url('${pick(HEROES)}')`;
      cell.dataset.filled = '1';
    });
  </script>
</body>
</html>
