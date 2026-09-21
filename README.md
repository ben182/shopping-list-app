# Einkaufsliste

Native Android-App (NativePHP for Mobile v4 + `nativephp/mobile-ui`) für die eigene
Einkaufsliste, mit Anbindung an eine selbst gehostete Mealie-Instanz.

Ersetzt die bisherige Nuxt-Webapp: fester Artikelkatalog, lokale SQLite auf dem Gerät,
kein Server, kein Geräte-Sync. Mealie-Artikel und eigene Artikel laufen in einer
Einkaufs-Übersicht zusammen; ein dritter Tab zeigt den Mealie-Wochenplan.

Anforderungen und User Stories: `.chief/prds/default/prd.md`

---

## 1. Voraussetzungen

| Was | Version | Anmerkung |
| --- | --- | --- |
| PHP | **8.5** | Auf dem Gerät läuft 8.5.10 (`nativephp.lock`). `composer.json` erlaubt formal ab 8.3 — entwickelt und getestet wird auf 8.5. |
| Composer | 2.x | |
| JDK | **17** | `brew install openjdk@17` (keg-only, muss von Hand in den `PATH`) |
| Android SDK | Platform 35 + 36, Build-Tools 35.0.0 + 36.0.0, Platform-Tools, NDK 27, Emulator | Über Android Studio (SDK Manager) oder die Command-line-Tools |
| Node/npm | — | **Nicht nötig.** Die App bringt kein Vite-Frontend mit; alles Sichtbare ist native. |

Android Studio braucht man nicht zum Bauen — `native:run` ruft Gradle selbst auf. Für
SDK-Verwaltung, Emulator und Logcat ist es trotzdem der bequemste Weg.
`php artisan native:open` öffnet das generierte Projekt in Android Studio.

Umgebungsvariablen (bei mir in `~/.zshrc`):

```sh
export JAVA_HOME="/opt/homebrew/opt/openjdk@17"
export ANDROID_HOME="$HOME/Library/Android/sdk"   # Symlink auf /opt/homebrew/share/android-commandlinetools
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/emulator:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
```

Findet NativePHP JDK oder SDK trotzdem nicht, lassen sich die Pfade in
`config/nativephp.php` unter `android.gradle_jdk_path` / `android.android_sdk_path`
festnageln (`NATIVEPHP_GRADLE_PATH`, `NATIVEPHP_ANDROID_SDK_LOCATION`).
`php artisan native:debug` zeigt, was NativePHP gefunden hat.

---

## 2. Einrichtung

```sh
composer install

cp .env.example .env
php artisan key:generate
php artisan migrate

php artisan native:install
```

Die ersten drei Schritte nach `composer install` macht auch `composer setup` in einem
Rutsch.

Zu `.env`: die Datei ist gitignored und wird nie eingecheckt. Die
`NATIVEPHP_*`-Platzhalter dürfen leer bleiben — `config/nativephp.php` und
`config/mealie.php` setzen die Werte dieser App per `?:` selbst
(App-ID `de.ben182.einkaufsliste`, Mealie-URL, Listen-ID).

> **Achtung, alte Falle:** Ein leerer Platzhalter `FOO=` liefert `''` und nicht `null`.
> Der zweite Parameter von `env()` greift dann *nicht*. Deshalb steht in den
> Konfigurationsdateien überall `env('FOO') ?: 'default'` statt `env('FOO', 'default')`.

`php artisan native:install` legt das native Projekt unter `nativephp/` an. Der Ordner
ist generiert und gitignored — nichts darin von Hand ändern, das ist beim nächsten
`native:install` weg.

---

## 3. Entwickeln und Bauen

Emulator starten (oder ein per USB angestöpseltes Gerät mit aktiviertem
USB-Debugging benutzen):

```sh
emulator -avd Pixel_7_API_35      # oder: php artisan native:emulator
```

Debug-Build bauen, installieren und starten:

```sh
php artisan native:run android
```

Mit Hot-Reload — beim Speichern einer PHP-, Blade-, JSON- oder CSS-Datei lädt der
Screen auf dem Gerät neu, ohne neuen Build:

```sh
php artisan native:run android --watch      # kurz: -W
```

Beobachtet werden `app/`, `resources/`, `routes/`, `config/` und `public/`
(`nativephp.hot_reload.watch_paths`). Rechner und Gerät müssen im selben Netz sein.

**Was Hot-Reload kann und was nicht:**

| Geändert | Reicht Hot-Reload? |
| --- | --- |
| PHP, Blade, Konfiguration, Routen | **Ja** — speichern genügt |
| Kotlin oder Swift in `plugins/*/resources/` | **Nein** — neuer `php artisan native:run android` |
| `plugins/*/nativephp.json`, neue Plugins, `config/nativephp.php` (App-ID, SDK, Theme, Orientierung) | **Nein** — neuer Build, bei Manifest-Änderungen vorher `php artisan native:install` |

Das ist die wichtigste Regel im Alltag: **alles unter `app/` und `resources/` ist
sofort da, alles Native braucht einen Build.** Wer an der Kotlin-Hälfte des
SecureStorage-Plugins schraubt und sich wundert, dass nichts passiert, hat den Build
vergessen.

---

## 4. Das Mealie-Token

Die App spricht mit der Mealie-Instanz aus `config/mealie.php`. URL und Listen-ID
gehören zur App und stehen im Code. Das **API-Token gehört zum Nutzer und steht
nirgends im Repo — auch nicht in `.env`.**

**Token in Mealie erzeugen**

1. In Mealie oben rechts aufs eigene Profil → **Profil** (`/user/profile`).
2. Dort **API-Tokens** (englisch: *Manage → API Tokens*).
3. **Neues Token erstellen**, Name z. B. `Einkaufsliste (Handy)`, kein Ablaufdatum
   nötig.
4. Das Token wird **genau einmal** angezeigt. Jetzt kopieren.

**Token in der App hinterlegen**

1. App öffnen, Tab **Einkaufen**.
2. Oben rechts aufs **Zahnrad** → Screen *Einstellungen*.
3. Token ins Feld einfügen → **Speichern**. Das Feld leert sich danach; angezeigt wird
   nur noch ein Status, nie das Token selbst.
4. **Verbindung testen** ruft `GET /api/users/self` und `GET /api/app/about` auf und
   antwortet mit einem Satz: „Verbunden: <Nutzer>, Mealie <Version>“, „Token ungültig“
   oder „Mealie nicht erreichbar“.

Gespeichert wird über die Bridge-Funktion `SecureStorage.Set` unter dem Schlüssel
`einkaufsliste.mealie-token` im Android Keystore (siehe Abschnitt 6), geschrieben mit
`SecureStorageAccessibility::AfterFirstUnlock`. Gelesen wird ausschließlich über
`App\Mealie\Token::lesen()`, das ein `SecureStorageResult` zurückgibt — „kein Token
hinterlegt“ (`not_found`) und „Gerät gesperrt, Token nicht lesbar“ (`unavailable`)
sehen als `null` gleich aus, dürfen aber nie gleich behandelt werden.

**Warum nicht in `.env`:** Die `.env` wird beim Bundeln in die APK kopiert. Ein Token
dort wäre in jeder APK enthalten und für jeden lesbar, der sie entpackt — und es wäre
bei jedem Build für jedes Gerät dasselbe. Für Entwicklung und Test liegt mein Token in
`~/Code/mealie/.env` (`MEALIE_TOKEN`), von dort wird es in die App getippt.

---

## 5. Release-Build und Android-Signierung

**Keystore einmalig erzeugen:**

```sh
php artisan native:credentials android
```

Das Kommando legt einen JKS-Keystore unter `credentials/` an, ergänzt `/credentials/`
in der `.gitignore` und schreibt vier Variablen in die `.env`:

| Variable | Bedeutung |
| --- | --- |
| `ANDROID_KEYSTORE_FILE` | Pfad zur `.jks`-Datei |
| `ANDROID_KEYSTORE_PASSWORD` | Passwort des Keystores |
| `ANDROID_KEY_ALIAS` | Alias des Schlüssels im Keystore |
| `ANDROID_KEY_PASSWORD` | Passwort des Schlüssels |

Sie lassen sich alternativ als Flags an `native:package` übergeben
(`--keystore`, `--keystore-password`, `--key-alias`, `--key-password`).

**Signiertes Paket bauen:**

```sh
php artisan native:package android                       # signierte APK
php artisan native:package android --build-type=bundle   # AAB für den Play Store
```

Die Artefakte landen unter `nativephp/android/app/build/outputs/`.

### Veröffentlichen für Obtainium

Verteilt wird über GitHub-Releases: [Obtainium](https://obtainium.imranr.dev) beobachtet
das Repo, vergleicht den Tag-Namen mit der installierten Version und lädt die APK aus
den Release-Assets. `php artisan release` macht den ganzen Weg in einem Kommando.

```sh
php artisan release              # Patch-Stelle hoch: 1.0.0 -> 1.0.1
php artisan release --minor      # 1.0.1 -> 1.1.0
php artisan release 2.0.0        # feste Version (beim ersten Mal nötig)
```

Der Ablauf, in dieser Reihenfolge: Vorbedingungen prüfen (sauberes
Arbeitsverzeichnis, Tag noch frei, `gh` angemeldet, Keystore-Variablen gesetzt) —
Tests — `NATIVEPHP_APP_VERSION` und `NATIVEPHP_APP_VERSION_CODE` in der `.env`
setzen — `native:package android` — prüfen, dass die gebaute APK die erwartete
Version trägt und nicht debug-signiert ist — Tag setzen und pushen — Release mit
der APK als `einkaufsliste-<version>.apk` anlegen. Bricht der Build ab, werden
Version und Version-Code in der `.env` zurückgesetzt; ein Tag entsteht erst, wenn
die APK vorliegt.

| Flag | Wirkung |
| --- | --- |
| `--skip-tests` | Testlauf überspringen |
| `--skip-build` | vorhandene APK aus dem Ausgabeverzeichnis nehmen |
| `--draft` | Release als Entwurf anlegen |
| `--prerelease` | Release als Vorabversion markieren |
| `--notes="…"` | eigene Release-Notes statt der aus den Commits generierten |

**In Obtainium einmalig einrichten:** *Add App* -> Repo-URL ->
Source `GitHub`, App-ID zur Verifikation `de.ben182.einkaufsliste`.

Drei Dinge, an denen Updates still scheitern:

- **Ein anderer Keystore.** Android lehnt das Update dann ab; die App muss
  deinstalliert werden und nimmt die Daten mit. Deshalb vergleicht `release` den
  SHA-256 der APK-Signatur (`apksigner verify`) mit dem Zertifikat im Keystore
  aus der `.env` und bricht bei Abweichung ab.
- **Ein nicht erhöhter `NATIVEPHP_APP_VERSION_CODE`.** Obtainium bietet das Update
  an, Android hält es für bereits installiert. Das Kommando zählt ihn selbst hoch.
- **Ein AAB statt einer APK.** Obtainium installiert nur APKs — also nie
  `--build-type=bundle` veröffentlichen.

Die APK enthält nur `arm64-v8a` (`nativephp/android/app/build.gradle.kts`). Auf
echten Geräten ist das unkritisch, x86-Emulatoren installieren sie nicht.

### Wo die Geheimnisse bleiben

- **`credentials/` ist gitignored** (`.gitignore`, zusammen mit `*.keystore` und
  `*.jks`). Keystore und PEM-Zertifikat gehören auf den Build-Rechner und in ein
  Backup — nie ins Repo.
- **`.env` ist gitignored.** Die Passwörter stehen nur dort und im Passwortmanager.
  `.env.example` enthält die vier Variablen absichtlich **leer**, damit sichtbar ist,
  dass es sie gibt, ohne dass ein Wert im Repo steht.
- **Die Signierungs-Variablen werden vor dem Bundeln aus der `.env` entfernt.** Dafür
  stehen `ANDROID_KEYSTORE_*` und `ANDROID_KEY_PASSWORD` in `cleanup_env_keys` in
  `config/nativephp.php`. Sie waren dort nicht ab Werk drin — das Standardmuster
  `*_SECRET` fängt diese Namen nicht. Der Build-Rechner braucht sie, das Gerät nie.
- **Der Keystore ist unersetzlich.** Geht er verloren, lässt sich eine einmal
  veröffentlichte App nicht mehr aktualisieren. Im Play Store hilft dann nur ein
  Upload-Key-Reset über Google.

`tests/Feature/GeheimnisseTest.php` hält diese Zusagen fest: verschwindet eine
`.gitignore`-Zeile oder ein `cleanup_env_keys`-Eintrag, wird der Test rot.

---

## 6. Katalog und Label-Aliase pflegen

### Katalog-Artikel (`config/katalog.php`)

Der feste Artikelkatalog — 8 Gruppen, 112 Artikel — steht in `config/katalog.php`:

```php
'gruppen' => [
    'obst-gemuese' => [
        'name' => 'Obst & Gemüse',
        'laeden' => ['lidl', 'rewe'],
        'artikel' => [
            'aepfel'  => 'Äpfel',
            'bananen' => 'Bananen',
            // …
        ],
    ],
    // …
],
```

- **Reihenfolge ist Anzeigereihenfolge.** Sowohl die der Gruppen als auch die der
  Artikel innerhalb einer Gruppe. Sie ist bewusst nicht alphabetisch, sondern folgt dem
  Weg durch den Laden. Ein Artikel wandert im Regal, indem man seine Zeile verschiebt.
- **Neuer Artikel:** eine Zeile `'id' => 'Anzeigename'` in der passenden Gruppe.
- **Artikel umbenennen:** nur den Wert ändern, **nicht die ID.** Gespeichert wird im
  Listen-Zustand ausschließlich die ID; wer sie ändert, verliert den Zustand dieses
  Artikels (die App filtert unbekannte IDs beim Lesen still weg).
- **Artikel entfernen:** Zeile löschen. Steht er gerade auf der Liste, verschwindet er
  dort ebenfalls — ohne Fehler.

Gelesen wird der Katalog nie direkt, sondern über `App\Katalog\Katalog`
(`gruppen()`, `artikelIds()`, `kennt()`, `gruppiert()`, `imLaden()`).

### Läden (`laeden`)

Über Einkaufsliste **und** Vorrat stehen dieselben Filter-Chips: **Alle · Lidl ·
Rewe · Getränkemarkt**. Woher die App weiß, was es wo gibt, steht ebenfalls im
Katalog.

- **Pro Gruppe:** `'laeden' => ['lidl', 'rewe']`. Jeder Artikel der Gruppe erbt das.
- **Pro Artikel:** wo einer abweicht, steht statt des Anzeigenamens ein Array —
  `'tempeh' => ['name' => 'Tempeh', 'laeden' => ['rewe']]`. Beide Schreibweisen
  dürfen in derselben Gruppe stehen.
- **Erlaubte Schlüssel:** `lidl`, `rewe`, `getraenkemarkt` (`App\Katalog\Laden`).
  Ein unbekannter Schlüssel fällt still weg.
- **Jeder Artikel gehört in genau einen Laden.** Steht er in zweien, trennen die
  Chips nichts mehr und man läuft doch wieder durch die ganze Liste. Die
  Aufteilung folgt dem Einkauf: `lidl` für Grundnahrungsmittel und alles Günstige
  (98 Artikel), `rewe` für die veganen Spezialprodukte, die Lidl nicht führt (7),
  `getraenkemarkt` für alles Trinkbare (7). Zwei Tests in
  `tests/Feature/EinkaufenLaedenTest.php` halten die Regel fest — sie fallen um,
  sobald ein Artikel in zwei oder in keinem Laden steht.
- **Mehrere Läden je Artikel sind technisch weiterhin möglich.** Wer bewusst
  abweicht, passt den Test mit an.
- **Ohne `laeden` stünde ein Artikel in jedem Filter.** Dasselbe gilt für
  Mealie-Artikel unter einer Überschrift, die es im Katalog nicht gibt: sie erben
  nichts und bleiben deshalb überall stehen — ein übersehener Artikel wiegt
  schwerer als eine Zeile zu viel.

Der gewählte Laden gilt für die ganze Sitzung (`App\Katalog\Ladenfilter`, ein
Singleton) und übersteht Tab-Wechsel, nicht aber den App-Start. **Einkaufen und
Vorrat teilen sich die Wahl** — „ich bin bei Lidl“ ist ein Zustand der Sitzung,
kein Zustand eines Screens.

- **Einkaufen:** „Alles abhaken“ nimmt nur mit, was gerade dasteht, und der
  Untertitel nennt beide Zahlen („5 von 12 Artikeln“). Der Block „Abgehakt“
  bleibt ungefiltert.
- **Vorrat:** Laden und Suche greifen zusammen (UND). Bleibt bei gesetztem
  Suchbegriff nichts übrig, sagt der Leerzustand dazu, dass ein Laden filtert —
  sonst sucht man einen Artikel, den der Chip gerade wegblendet.

Das Markup der Chips steht einmal in `resources/views/native/laden-filter.blade.php`
und wird von beiden Screens per `@include` eingebunden. Ein Include läuft nicht im
Kontext der Komponente: `$laeden` und `$gewaehlterLaden` gehen als Parameter rein,
den Handler `ladenWaehlen()` muss der einbindende Screen mitbringen.

> **Katalogänderungen brauchen ein App-Update.** Der Katalog ist eine
> Konfigurationsdatei und wird beim Bauen in die APK gepackt. Es gibt bewusst keine
> UI, um Artikel anzulegen. Nach einer Änderung also `php artisan native:run android`
> (bzw. `native:package` und neu installieren) — die gespeicherte Liste überlebt das
> Update, die Migrationen löschen den Listen-Zustand nie.

### Label-Aliase (`config/mealie.php`)

Mealie-Artikel werden auf dem Einkaufen-Screen in die Katalog-Gruppen einsortiert. Die
Zuordnung läuft in dieser Reihenfolge:

1. Das Mealie-Label heißt (nach `trim()`, exakt) wie eine Katalog-Gruppe → diese
   Gruppe.
2. Das Label steht in `label_aliase` → die dort genannte Gruppe.
3. Nichts davon → eine **eigene Gruppe mit dem Label als Überschrift**, unterhalb der
   Katalog-Gruppen.
4. Kein Label → die Gruppe aus `gruppe_ohne_label` (`Sonstiges`).

```php
'label_aliase' => [
    'Gemüse'        => 'Obst & Gemüse',
    'Tiefkühlware'  => 'Tiefkühl',
    'Milchprodukte' => 'Kühlregal',
    // …
],
```

Links steht der Mealie-Label-Name, rechts der **Name** der Katalog-Gruppe (nicht die
ID). Für ein neues Mealie-Label muss man nichts tun — es erscheint von allein als
eigene Gruppe. Ein Alias ist nur nötig, wenn es mit einer bestehenden Gruppe
verschmelzen soll. Auch das ist eine Konfigurationsdatei: **Aliase ändern heißt neu
bauen.**

---

## 7. Das SecureStorage-Plugin

`nativephp/mobile` bringt die PHP-Hälfte von Secure Storage mit (Facade,
`SecureStorageResult`, `SecureStorageStatus`), aber nicht die native Hälfte. Die liegt
als lokales Plugin im Repo:

```
plugins/secure-storage/
├── composer.json                                 Paket ben182/secure-storage, type: nativephp-plugin
├── nativephp.json                                Manifest: Bridge-Funktionen, Mindest-SDK, Abhängigkeiten
├── src/
│   └── SecureStorageServiceProvider.php          bindet Native\Mobile\SecureStorage als Singleton
└── resources/
    ├── android/SecureStorageFunctions.kt         de.ben182.securestorage — EncryptedSharedPreferences
    └── ios/SecureStorageFunctions.swift          Keychain (nie gebaut, Android-first)
```

`nativephp.json` meldet drei Bridge-Funktionen an — `SecureStorage.Set`, `.Get`,
`.Delete` — und jeweils, welche Kotlin- bzw. Swift-Klasse sie bedient. Auf Android
liegen die Werte in `EncryptedSharedPreferences` (`nativephp_secure_store`), deren
Master-Key im hardwaregestützten Android Keystore sitzt: Werte AES-256-GCM, Schlüssel
deterministisch verschlüsselt, damit sie nachschlagbar bleiben. Die Abhängigkeit
`androidx.security:security-crypto:1.1.0-alpha06` ist eine Alpha — bekannt und
akzeptiert.

Eingebunden ist das Plugin an drei Stellen:

1. **Path-Repository** `plugins/*` in `composer.json`.
2. **Abhängigkeit** `ben182/secure-storage: @dev` in `composer.json`.
3. **Allowlist** in `app/Providers/NativeServiceProvider.php` → `plugins()`. **Ohne
   diesen Eintrag liefert jeder Aufruf `BRIDGE_UNAVAILABLE`** — transitive
   Abhängigkeiten sollen sich nicht von selbst in den Build schleichen. Dort steht
   neben `SecureStorageServiceProvider` auch das zweite eigene Plugin
   `plugins/app-lifecycle` (Event `AppForegrounded`, treibt den Cache-Refresh).

Prüfen: `php artisan native:plugin:list` und `php artisan native:plugin:validate`.

**Änderungen an den Kotlin-Dateien wirken erst nach einem neuen
`php artisan native:run android`** — sie werden beim Build in das Gradle-Projekt unter
`nativephp/` kopiert und kompiliert. Hot-Reload rührt sie nicht an. Änderungen an der
PHP-Hälfte (ServiceProvider, `App\Mealie\Token`, Screens) sind dagegen sofort da.
Wer `nativephp.json` anfasst — neue Bridge-Funktion, neue Gradle-Abhängigkeit, neue
Permission — führt vorher `php artisan native:install` aus, damit das Manifest neu
gemerged wird.

---

## 8. Tests und Qualität

```sh
./vendor/bin/pint            # Formatierung (nach jeder PHP-Änderung)
php artisan test             # Pest 4
php artisan native:validate  # statische Prüfung der Screens
```

Getestet wird am Harness `Native\Mobile\Testing\Native` gegen die Screen-Klassen
(`Native::visit('/…')`, `tap`, `input`, `assertSee`). Mealie hängt an `Http::fake()`
mit JSON-Fixtures unter `tests/Fixtures/`, Secure Storage an
`Native::fakeBridge()->respondTo('SecureStorage.Get', …)`. Kein Test braucht ein
Gerät, einen Emulator oder eine erreichbare Mealie-Instanz.

> **`native:validate` ist dauerhaft rot.** Sein Analyzer kennt nur die Core-Elemente
> und meldet jedes Tag aus `nativephp/mobile-ui` (`list`, `list-item`, `list-section`,
> …) als „Unknown native element type“. Beim Prüfen geht es nur darum, dass keine
> *andere* Fehlerart dazukommt.

---

## 9. Wo was liegt

```
app/
├── NativeComponents/   Screens: Einkaufen, Vorrat, Wochenplan, Einstellungen
├── Layouts/            TabsLayout (Root-Screens), StackLayout (gepushte Screens)
├── Katalog/            Katalog, Gruppe, Artikel, Laden, Ladenfilter — config/katalog.php
├── Liste/              EigeneListe — der lokale Listen-Zustand in SQLite
├── Einkaufen/          Zusammenführung eigener und Mealie-Artikel
├── Mealie/             Token, Verbindung, Einkaufsliste, Sitzung, Cache, Fehler
├── Wochenplan/         Wochenberechnung und Mealplan-Sitzung
└── Icons/              generierte Icon-Enums (php artisan native-ui:generate-icons)

config/katalog.php      Artikelkatalog (8 Gruppen, 112 Artikel) samt Laden-Zuordnung
config/mealie.php       Mealie-URL, Listen-ID, Timeout, Label-Aliase
config/nativephp.php    App-ID, SDK-Versionen, Theme, cleanup_env_keys, Hot-Reload
routes/mobile.php       Screen-Routen (wird vom Package-Provider geladen, nicht in bootstrap/app.php)
resources/views/native/ Blade-Views der Screens
plugins/                lokale NativePHP-Plugins (secure-storage, app-lifecycle)
nativephp/              generiertes Android-Projekt — gitignored, nicht von Hand ändern
credentials/            Keystore und Zertifikate — gitignored, niemals einchecken
```
