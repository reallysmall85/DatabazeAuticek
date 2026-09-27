(() => {
    const spustit = document.getElementById('nacti-qr-polozky');
    const model = document.getElementById('nacti-qr-modelu');
    const krabicka = document.getElementById('nacti-qr-krabicky');
    let polozkaId = null;
    function tlacitka() {
        spustit.disabled = nacitani || !ukonceno;
        model.disabled = nacitani || !ukonceno || !polozkaId;
        krabicka.disabled = model.disabled;
    }
    const dialog = document.getElementById('qr-dialog');
    const zavrit = document.getElementById('qr-zavrit');
    const zprava = document.getElementById('qr-zprava');
    function zobrazZpravu(text, typ = 'info') {
        zprava.className = 'sklad-zprava sklad-zprava--' + typ;
        zprava.textContent = text;
    }
    let skener = null;
    let start = null;
    let konec = null;
    let ukonceno = true;
    let nacitani = false;
    const bunky = {
        cislo: 'cislo-modelu', nazev: 'nazev-modelu', upresneni: 'upresneni-modelu',
        umisteniauta: 'umisteni-modelu', umistenikrabicky: 'umisteni-krabicky'
    };

    async function nacistPolozku(obsah) {
        nacitani = true;
        polozkaId = null;
        Object.values(bunky).forEach(id => { document.getElementById(id).textContent = ''; });
        const cislo = document.getElementById('databazove-cislo');
        cislo.textContent = 'Načítání…';
        zobrazZpravu('');
        await zastavit();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const parametry = new URLSearchParams({ nactiPolozku: '1', id: obsah.trim() });
            const odpoved = await fetch('Auta-sklad.php?' + parametry, {
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            const data = await odpoved.json();
            if (!odpoved.ok) throw new Error(data.chyba || 'Položku se nepodařilo načíst.');
            if (!data.polozka) {
                cislo.textContent = 'položka nenalezena';
                zobrazZpravu('Položka nenalezena.', 'chyba');
                return;
            }
            polozkaId = data.polozka.id;
            cislo.textContent = data.polozka.id;
            Object.entries(bunky).forEach(([pole, id]) => {
                document.getElementById(id).textContent = data.polozka[pole] ?? '';
            });
            zobrazZpravu('Položka načtena.', 'uspech');
        } catch (chyba) {
            cislo.textContent = '';
            zobrazZpravu(chyba.name === 'AbortError'
                ? 'Načítání trvá příliš dlouho. Zkus QR načíst znovu.'
                : (chyba.message || 'Položku se nepodařilo načíst.'), 'chyba');
        } finally {
            clearTimeout(timeout);
            nacitani = false;
            tlacitka();
        }
    }

    async function ulozitUmisteni(obsah, cil) {
        const id = polozkaId;
        nacitani = true;
        await zastavit();
        zobrazZpravu('Ukládání umístění…');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const odpoved = await fetch('Auta-sklad.php', {
                method: 'POST', credentials: 'same-origin', signal: controller.signal,
                body: new URLSearchParams({ id, cil, umisteni: obsah,
                    csrf: document.querySelector('meta[name="sklad-csrf"]').content })
            });
            const data = await odpoved.json();
            if (!odpoved.ok) {
                if (odpoved.status === 404) {
                    polozkaId = null;
                    Object.values(bunky).forEach(id => { document.getElementById(id).textContent = ''; });
                    document.getElementById('databazove-cislo').textContent = 'položka nenalezena';
                }
                throw new Error(data.chyba || 'Umístění se nepodařilo uložit.');
            }
            const pole = cil === 'krabicka' ? 'umistenikrabicky' : 'umisteniauta';
            document.getElementById(bunky[pole]).textContent = data[pole];
            zobrazZpravu(cil === 'krabicka'
                ? 'Umístění krabičky bylo uloženo.' : 'Umístění modelu bylo uloženo.', 'uspech');
        } catch (chyba) {
            zobrazZpravu(chyba.name === 'AbortError'
                ? 'Server včas neodpověděl. Načti položku znovu a ověř, zda se umístění uložilo.'
                : chyba.message, 'chyba');
        } finally {
            clearTimeout(timeout);
            nacitani = false;
            tlacitka();
        }
    }

    function zastavit() {
        if (konec) return konec;
        ukonceno = true;
        dialog.close();
        // Při zavření během žádosti o oprávnění počkáme na výsledek startu.
        konec = (async () => {
            try {
                if (start) await start.catch(() => {});
                if (skener && skener.isScanning) await skener.stop();
            } catch (chyba) {
                // Záložní ukončení streamu, pokud knihovna nedokončí stop().
                document.querySelectorAll('#qr-kamera video').forEach(video => {
                    if (video.srcObject) video.srcObject.getTracks().forEach(track => track.stop());
                });
            } finally {
                if (skener) {
                    try { skener.clear(); } catch (chyba) { /* Stream už je zastavený. */ }
                }
                skener = null;
                start = null;
                tlacitka();
            }
        })();
        return konec;
    }

    async function otevritKameru(rezim) {
        if (nacitani || !ukonceno || (rezim !== 'polozka' && !polozkaId)) return;
        zobrazZpravu('');
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            zobrazZpravu('Fotoaparát vyžaduje HTTPS a prohlížeč s podporou kamery.', 'chyba');
            return;
        }
        if (typeof Html5Qrcode === 'undefined') {
            zobrazZpravu('Čtečku QR se nepodařilo načíst. Obnov stránku a zkus to znovu.', 'chyba');
            return;
        }
        spustit.disabled = true;
        ukonceno = false;
        konec = null;
        tlacitka();
        document.getElementById('qr-nadpis').textContent = rezim === 'krabicka' ? 'Načti nové QR krabičky'
            : (rezim === 'model' ? 'Načti nové QR modelu' : 'Načti QR položky');
        dialog.showModal();
        try {
            skener = new Html5Qrcode('qr-kamera', {
                formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
                verbose: false
            });
            start = skener.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: (sirka, vyska) => {
                        const rozmer = Math.floor(Math.min(sirka, vyska) * 0.75);
                        return { width: rozmer, height: rozmer };
                    }
                },
                obsah => {
                    if (ukonceno) return;
                    if (rezim !== 'polozka') void ulozitUmisteni(obsah, rezim);
                    else void nacistPolozku(obsah);
                },
                () => {} // Průběžné nenalezení kódu není chyba pro uživatele.
            );
            await start;
            if (ukonceno) await zastavit();
        } catch (chyba) {
            if (!ukonceno) {
                zobrazZpravu('Fotoaparát se nepodařilo otevřít. Povol přístup ke kameře v prohlížeči a ověř, že ji nepoužívá jiná aplikace.', 'chyba');
            }
            await zastavit();
        }
    }
    spustit.addEventListener('click', () => otevritKameru('polozka'));
    model.addEventListener('click', () => otevritKameru('model'));
    krabicka.addEventListener('click', () => otevritKameru('krabicka'));
    zavrit.addEventListener('click', () => { void zastavit(); });
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        void zastavit();
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && !ukonceno) void zastavit();
    });
    window.addEventListener('pagehide', () => {
        if (!ukonceno) void zastavit();
    });
})();
