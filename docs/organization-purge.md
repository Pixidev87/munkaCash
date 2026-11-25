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
