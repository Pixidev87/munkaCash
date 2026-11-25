# Végleges céges adattörlés

A lezárást a tulajdonos kezdeményezi az alkalmazásban. A törlést az exportablak lejárta után az üzemeltető indítja a kiszolgálón. A parancs futtatása kapcsoló nélkül az aktuális állapotot ellenőrzi.

```sh
php artisan organizations:purge <cégazonosító>
php artisan organizations:purge <cégazonosító> --apply
```

- A parancs egyetlen céghez tartozó ügyfeleket, számlákat, importtételeket, jóváhagyásokat, tesztküldési bejegyzéseket és tagságokat töröl. A globális felhasználói fiókok és a más cégekhez tartozó tagságok megmaradnak.
- A kapcsolt rekordokat az adatbázis idegen kulcsai törlik. A törlés és a cégazonosítóból, valamint az időpontból álló nyilvántartási bejegyzés egy tranzakcióban készül. Ismételt futtatáskor a parancs jelzi a korábbi törlést.
- Az import és az export nem hoz létre tartós CSV-másolatot az alkalmazástárban. A későn futó tesztküldési feladat az adatbázisban keresi a küldési rekordot, és annak hiányában befejeződik.
- A mentések, a korábban letöltött exportok és a Mailpitben már elfogadott üzenetek külön példányok. A mentés visszaállítása után a törlési nyilvántartást egyeztetni kell, az érintett cégek adatait ismét törölve, mielőtt a visszaállított példány forgalmat fogad.

## Törlések visszajátszása mentésből helyreállított adatbázison

1. Készítsd el a törlési nyilvántartás exportját a helyreállítás előtti, hiteles forrásból. Tartsd az adatbázis mentéseitől elkülönítve, hogy a visszaállítás ne írja felül. Az export sikerét ellenőrizd, és a mentési időszakot lefedő, legfrissebb változatot használd. A parancs a jelenlegi alkalmazáskulccsal titkosít és hitelesít.
2. A mentést ugyanannak a telepítésnek egy izolált adatbázisába állítsd vissza. Tiltsd le a külső forgalmat, az ütemezőt, a háttérfolyamatokat és a levélküldést. Az adatbázison futtasd a migrációkat, majd kapcsold be az alkalmazás karbantartási módját. A két alkalmazás azonos titkosítási kulcsot használjon, vagy a helyreállított példánynak legyen hozzáférése a megfelelő korábbi kulcshoz.
3. Futtasd az ellenőrzést az archívum alkalmazása előtt. A parancs kiírja az export időpontját és a visszaállított, ismét törlendő cégek számát. A hitelesítés az archívum tartalmát védi. Az időpontot és a forrást össze kell vetni a helyreállítási nyilvántartással.
4. Az `--apply` minden törlést és a hozzájuk tartozó nyilvántartási rekordot egy tranzakcióban alkalmaz. Eltérő törlési időpont vagy a törlés után létrehozott azonosítójú cég esetén a teljes tranzakció visszagördül. Ismételd meg az ellenőrzést, és csak nulla törlendő céggel folytasd.
5. Az újranyitás előtt ellenőrizd, hogy a következő automatikus cégazonosító nagyobb a forrás és a helyreállított adatbázis valamennyi használt cégazonosítójánál, beleértve a törlési nyilvántartást. Egyeztesd a mentés óta visszavont tagságokat, az egyedileg törölt rekordokat és a várakozó feladatokat. A parancs a végleges cégtörléseket játssza vissza.

A hiteles forráson új, korlátozott jogosultságú fájlba ments:

```sh
umask 077
php artisan organizations:purges:export > /vedett-hely/torlesi-nyilvantartas.enc
```

Az izolált helyreállítási példányon futtasd:

```sh
php artisan down
php artisan organizations:purges:replay /vedett-hely/torlesi-nyilvantartas.enc
php artisan organizations:purges:replay /vedett-hely/torlesi-nyilvantartas.enc --apply
php artisan organizations:purges:replay /vedett-hely/torlesi-nyilvantartas.enc
```

A beépített eljárás archívumonként legfeljebb 10 000 törlést kezel. A fájl olvasási korlátja 4 MiB.
