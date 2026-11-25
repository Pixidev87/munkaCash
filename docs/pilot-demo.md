# MunkaCash pilotbemutató fiktív adatokkal

Használj külön, üres helyi adatbázist és `APP_ENV=local` beállítást. A már futó Sail környezetben alkalmazd a migrációkat, majd töltsd be a bemutató adatait.

```sh
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan munkacash:demo
```

A parancs kiírja a cégek azonosítóit, a három bejelentkezéshez tartozó egyedi jelszót és a Hárs Műhelyhez használható kétsoros CSV-t. A jelszavak csak ezen a futáson láthatók. Az ütköző demónevek és e-mail-címek esetén a parancs leáll. Új bemutatóhoz használj másik üres helyi adatbázist.

| Bejelentkezés                    | Hozzáférés              |
| -------------------------------- | ----------------------- |
| `hars-tulajdonos@munkacash.test` | Hárs Műhely, tulajdonos |
| `hars-munkatars@munkacash.test`  | Hárs Műhely, munkatárs  |
| `duna-tulajdonos@munkacash.test` | Duna Stúdió, tulajdonos |

## Kipróbálás

1. Jelentkezz be a Hárs Műhely tulajdonosaként. A **Számlák** oldalon a `DEMO-001` bruttó összege 120 000 Ft, fennálló összege 80 000 Ft. A levélelőnézetben 80 000 Ft és `tolgy-penzugy@munkacash.test` jelenik meg. A `DEMO-002` vitatott, ezért blokkolt az emlékeztetője.
2. Másold a parancs által kiírt két CSV-sort egy UTF-8 fájlba, és töltsd fel a Hárs Műhely **CSV-import** oldalán. Az előnézetben egy új számlát kell látnod. Erősítsd meg az importot, majd töltsd fel ugyanazt a fájlt újra. A második köteg ugyanazt a számlát változatlanként jelzi.
3. Lépj be külön böngészőprofilban a Duna Stúdió tulajdonosaként. A saját `DEMO-001` számlája 90 000 Ft-os. A Hárs Műhely számlaoldalának elmentett linkje ebből a fiókból nem nyílik meg. A Hárs Műhely munkatársa megnyithatja a Hárs Műhely számláit, a tulajdonosi exporthoz viszont csak a tulajdonos fér hozzá.
4. Tesztlevélhez a Hárs Műhely tulajdonosa engedélyezheti az emlékeztetőket és jóváhagyhatja az első levélelőnézetet. A tesztküldést a munkanaptár és a 30 perces jóváhagyási ablak szabályozza. A feldolgozáshoz az ütemező és a `reminder-tests` sorhoz tartozó feldolgozó fusson. A fogadott tesztlevél a helyi Mailpit felületén ellenőrizhető.

A demó létrehozásakor mindkét cég küldése szünetel, jóváhagyás és küldési feladat nem keletkezik. A teszt a munkatársi tagság visszavonása utáni hozzáférésvesztést is ellenőrzi.
