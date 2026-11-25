# Zárt MunkaCash pilot fiktív adatokkal

A pilot külön SQLite-adatbázist, alkalmazáskulcsot, munkameneteket, háttérsort és helyi Mailpit-postafiókot használ. A futtatáshoz PHP 8.4 vagy 8.5, a PHP SQLite- és PCNTL-bővítménye, Composer, Node.js és a PATH-on elérhető Mailpit szükséges. A webfelület és a Mailpit kizárólag a futtató gép visszacsatolási címére figyel.

## Indítás

A repó gyökerében:

```sh
composer install
npm ci
npm run build
bash bin/pilot init
bash bin/pilot demo
```

A demóparancs új, fiktív fiókokat hoz létre, és egyszer írja ki az egyedi jelszavukat. A tesztpéldányt csak fiktív adatokkal használd. Új, független példányhoz adj meg egy új `PILOT_STATE_DIR` könyvtárat minden parancshoz. Az alapértelmezett könyvtár `~/.munkacash-closed-pilot`.

Indítsd el a négy folyamatot külön terminálon ugyanazokkal a beállításokkal:

```sh
bash bin/pilot mail
bash bin/pilot web
bash bin/pilot worker
bash bin/pilot scheduler
```

| Felület | Helyi cím |
| --- | --- |
| MunkaCash | http://127.0.0.1:8088 |
| Mailpit | http://127.0.0.1:8026 |

A portok a `PILOT_WEB_PORT`, `PILOT_MAIL_PORT` és `PILOT_SMTP_PORT` változóval módosíthatók. Az SMTP alapértelmezett portja 1026. A meglévő munkanaptár és a 30 perces jóváhagyási ablak a pilotban is érvényes. A [fiktív adatos kipróbálás](pilot-demo.md#kipróbálás) leírja a két cég és a három tesztfiók használatát.

## Üzemeltetési ellenőrzés

A folyamatok indítása után egy perc múlva futtasd a `bash bin/pilot check` parancsot. A parancs hibakóddal tér vissza, ha eltűnik az ütemező vagy a feldolgozó életjele, két percnél régebben feldolgozható feladat marad a sorban, vagy friss, bizonytalan SMTP-átadás látható. A parancs időzített helyi ellenőrzésből is futtatható. Az eredmény darabszámokat mutat, címzettek és levéltartalom nélkül.

Bizonytalan átvételnél hasonlítsd össze a tesztküldési naplót a Mailpitben látható üzenetekkel. A feldolgozó ilyen esetben nem ismétli meg automatikusan a küldést.
