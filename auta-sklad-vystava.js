function pripravGeneratorQr(prefix, nazvy, oznaceni, parametr) {
    const pole = nazvy.map(nazev => document.getElementById(prefix + '-' + nazev));
    const tlacitko = document.getElementById(prefix + '-generuj');
    const vystup = document.getElementById(prefix + '-qr');
    let verze = 0;

    function platne() {
        return pole.every(prvek => /^[0-9]{1,20}$/.test(prvek.value));
    }
    function aktualizovat() {
        pole.forEach(prvek => { prvek.value = prvek.value.replace(/[^0-9]/g, '').slice(0, 20); });
        verze++;
        vystup.replaceChildren(); // Staré QR už po změně polí neodpovídá zadání.
        tlacitko.disabled = !platne();
    }
    pole.forEach(prvek => prvek.addEventListener('input', aktualizovat));
    window.addEventListener('pageshow', aktualizovat);

    tlacitko.addEventListener('click', () => {
        if (!platne()) return;
        const aktualniVerze = ++verze;
        tlacitko.disabled = true;
        vystup.textContent = 'Generování QR…';
        const hodnoty = pole.map(prvek => prvek.value);
        const obsah = hodnoty.map((hodnota, index) => oznaceni[index] + hodnota).join('-');
        const parametry = new URLSearchParams({ [parametr]: '1' });
        nazvy.forEach((nazev, index) => parametry.set(nazev, hodnoty[index]));
        const obrazek = new Image();
        obrazek.alt = obsah;
        obrazek.style.cssText = 'display: block; max-width: 100%; height: auto; margin: auto;';
        obrazek.onload = () => {
            if (aktualniVerze !== verze) return;
            vystup.replaceChildren(obrazek);
            tlacitko.disabled = !platne();
        };
        obrazek.onerror = () => {
            if (aktualniVerze !== verze) return;
            const chyba = document.createElement('span');
            chyba.textContent = 'QR se nepodařilo vytvořit. Zkus to znovu nebo obnov stránku.';
            chyba.style.cssText = 'color: #991b1b; font-weight: bold;';
            vystup.replaceChildren(chyba);
            tlacitko.disabled = !platne();
        };
        obrazek.src = 'Auta-sklad.php?' + parametry;
    });
    aktualizovat();
}
pripravGeneratorQr('vystava', ['sektor', 'vitrina', 'police'], ['SEK', 'VIT', 'POL'], 'qrVystava');
pripravGeneratorQr('sklad', ['sklad', 'skrin', 'krabice'], ['SKL', 'SKR', 'KRA'], 'qrSklad');
