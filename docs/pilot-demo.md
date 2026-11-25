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

A demó létrehozásakor mindkét cég küldése szünetel, jóváhagyás és küldési feladat nem keletkezik. A hozzáférés visszavonását a Hárs Műhely tulajdonosa a **Munkatársak kezelése** oldalon próbálhatja ki. A megerősítés után frissítsd a munkatárs böngészőjét. A Hárs Műhely eltűnik a cégei közül, és az elmentett cégoldalt sem tudja megnyitni.

## Határidő és munkanaptár

Az emlékeztető előnézete a számla eredeti dátumát mutatja. Ha a dátum munkaszüneti vagy pihenőnapra esik, külön megjelenik a következő magyar munkanap. A jóváhagyás az így számított nap után válik elérhetővé. A tesztküldésre továbbra is a küldési időablak vonatkozik.

A pilot jelenleg a `config/reminder-calendar.php` fájlban rögzített 2026-os munkanaptárat használja. Ha a dátum számításához vagy a küldési időablakhoz hiányzik az érintett év ellenőrzött naptára, az adott művelet blokkolt.

## Munkatárs meghívása

A **Munkatársak kezelése** oldalon a tulajdonos megadja a meghívott e-mail-címét és létrehozza a linket. A linket a tulajdonos osztja meg a munkatárssal. A munkatárs a meghívott címhez tartozó, ellenőrzött fiókkal bejelentkezik, megnyitja a linket és elfogadja a meghívást. A link 24 óráig és egy elfogadásra érvényes. Ugyanarra a címre kiadott új link érvényteleníti az előzőt.

A helyreállítás és az éles telepítés során a meghívó útvonalát is érzékeny adatként kezeld. A webszerver és a proxy hozzáférési naplójában a meghívó tokenjét maszkolni kell. Régi mentésből helyreállított adatbázisban újranyitás előtt érvénytelenítsd a függő meghívókat, hogy a mentésben szereplő korábbi linkek ne váljanak ismét használhatóvá.
