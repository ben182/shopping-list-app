## Codebase Patterns

- **Screens:** `app/NativeComponents/<Name>.php` extends `Native\Mobile\Edge\NativeComponent`
  direkt (keine Basisklasse — `native:validate` meldet abstrakte Klassen in diesem
  Ordner als Fehler). `render(): Element` gibt **`$this->view('<name>')`** zurück,
  nicht `view('native.<name>')`: `$this->view()` setzt das `native.`-Präfix selbst
  und ist die einzige Form, die `php artisan native:validate` erkennt.
- **Routen** stehen in `routes/mobile.php`. Die Datei wird **nicht** in
  `bootstrap/app.php` registriert — der Package-Provider lädt sie selbst.
  `bootstrap/app.php` hat kein `web:` und es gibt keine `routes/web.php`.
- **Layouts:** `app/Layouts/TabsLayout.php` (Root-Screens) und `StackLayout.php`
  (gepushte Screens). Beide überschreiben `usesNativeChrome(): true` — sonst wird
  die Chrome als Column nachgebaut und es gibt kein `native_root_tabs` /
  `native_root_stack` im Wire-Tree (und `assertHasTabBar()` schlägt fehl).
- **Screen-eigene NavBar-Actions** über `navigationOptions(): ?NavBarOptions` auf
  dem Screen; das merged auf die vom Layout gebaute NavBar.
- **Untertitel:** Die Layouts rufen `method_exists($screen, 'navSubtitle')` ab.
  Ein Screen bekommt einen Untertitel, indem er `navSubtitle(): ?string` definiert.
- **Icons** nur über die generierten Enums (`App\Icons\Ios`, `App\Icons\Android`),
  benannte Argumente: `->icon(ios: Ios::Gearshape, android: Android::Settings)`,
  `Tab::link('Vorrat', '/vorrat', ios: …, android: …)`. Neu generieren mit
  `php artisan native-ui:generate-icons`.
- **Farben** ausschließlich als Theme-Klassen (`bg-theme-background`,
  `text-theme-on-surface`, `text-theme-primary`). Der Parser schickt dann zu jeder
  Farbe eine Dark-Mode-Entsprechung mit; eine feste Farbe tut das nicht und fällt
  im Dark-Mode-Test auf.
- **Blade:** keine Blade-Components mit Slot um native Container. Blade rendert den
  Slot vor dem Component-Body, der EDGE-Collector sammelt in Ausführungsreihenfolge
  — der Baum verdreht sich. Sub-Komponenten also ohne Slot schreiben.
- **Tests** am Harness `Native\Mobile\Testing\Native`. Nützlich:
  `Native::visit($uri, platform: 'android')`, `assertNavTitle`, `assertHasTab`,
  `assertTabActive`, `assertHasTabBar`, `assertTabBarHidden`, `assertAccessible`,
  `assertElement($typ, $matcher)`, `press('methodenName')`, `assertNavigatedTo`.
  Der rohe Wire-Tree kommt aus `->tree()`.
- **Platform in Tests:** ohne `platform:` ist die Plattform unbekannt und
  `->icon(ios: …, android: …)` löst zu **null** auf. Wer Icons prüfen will, muss
  `platform: 'android'` übergeben.
- **Leere `.env`-Platzhalter schlagen `env()`-Defaults:** `FOO=` liefert `''`, nicht
  `null`, also greift der zweite `env()`-Parameter nicht. In `config/nativephp.php`
  deshalb `env('NATIVEPHP_APP_ID') ?: 'de.ben182.einkaufsliste'`.
- **Qualitäts-Gate:** `./vendor/bin/pint`, `php artisan test`,
  `php artisan native:validate`. Build-Kommandos (`native:run`, `native:package`)
  werden nur ausgegeben, nicht ausgeführt. **`native:validate` ist seit EKL-002
  dauerhaft rot**: sein Analyzer kennt nur die Core-Elemente und meldet jedes
  mobile-ui-Tag (`list`, `list-item`, `list-section`, …) als „Unknown native
  element type". Nur prüfen, dass keine *anderen* Fehler dazukommen.
- **Katalog** in `config/katalog.php` (8 Gruppen, 112 Artikel — nicht 113, wie
  die PRD an mehreren Stellen schreibt). Zugriff nur über `App\Katalog\Katalog`
  (`gruppen()`, `artikelIds()`, `kennt()`, `gruppiert()`), der Listen-Zustand nur
  über `App\Liste\EigeneListe`. Gespeichert wird ausschließlich die Artikel-ID;
  IDs ohne Katalog-Eintrag werden beim Lesen gefiltert.
- **Callback mit String-Argument** nie direkt ins Attribut schreiben
  (`@press="tu('{{ $id }}')"` bricht den Validator). Stattdessen
  `@php($press = "tu('{$id}')")` und `@press="{{ $press }}"`.
- **Migrationen laufen auf dem Gerät automatisch** beim Entpacken des Payloads;
  kein `Artisan::call('migrate')`, kein `db:seed` — Startdaten in eine
  Migration. Nach einer neuen Migration braucht es `native:run`, PHP-Hot-Reload
  reicht nicht.

---

## 2026-09-20 - EKL-001

Laravel-13-Projekt aus `nativephp/mobile-starter` erzeugt und auf den PRD-Stack
hochgezogen (`laravel/framework` ^13, `nativephp/mobile` ^4.5.1,
`nativephp/mobile-ui` ^0.3.0, PHP-Runtime 8.5.10 via `nativephp.lock`). Drei
Root-Tabs, ein gepushter Einstellungen-Screen, Indigo-Theme, Hochformat.

**Geänderte / neue Dateien**

- `composer.json` / `composer.lock` — Stack, `plugins/*`-Path-Repo, ohne Vite-Skripte
- `bootstrap/app.php` — kein `web:`-Routing mehr
- `routes/mobile.php` — vier Routen in zwei `nativeGroup`s
- `app/Layouts/TabsLayout.php`, `app/Layouts/StackLayout.php`
- `app/NativeComponents/{Einkaufen,Vorrat,Wochenplan,Einstellungen}.php`
- `resources/views/native/{einkaufen,vorrat,wochenplan,einstellungen}.blade.php`
- `app/Icons/{Ios,Android,AndroidOutlined}.php` — generiert
- `app/Providers/NativeServiceProvider.php` — `NativeUIServiceProvider` in `plugins()`
- `config/native-ui.php` — Primär Indigo; `config/nativephp.php` — App-ID-Default,
  Android-Dialogfarben, Keystore-Keys in `cleanup_env_keys`; `config/app.php` — Name
- `.env.example`, `phpunit.xml`, `.gitignore`
- `tests/Pest.php`, `tests/Feature/{GrundgeruestTest,ThemeTest,AppIdentitaetTest}.php`,
  `tests/Unit/ArchTest.php`

21 Tests, 109 Assertions, grün. Pint und `native:validate` sauber.

**Learnings für die nächsten Iterationen**

- Das Starter-Template `nativephp/mobile-starter` ist **Laravel 12 / mobile ^3.0**.
  Der Sprung auf den PRD-Stack heißt: `composer.json` von Hand schreiben,
  `composer install`, dann `native:install`. `native:install` publiziert dabei
  *nicht* `config/native-ui.php`, `NativeServiceProvider` oder die Icon-Enums —
  das sind drei eigene Schritte
  (`vendor:publish --tag=native-ui-config`, `--tag=nativephp-plugins-provider`,
  `native-ui:generate-icons`).
- Die vom v3-Starter mitgelieferte `config/nativephp.php` ist veraltet; die aus
  `vendor/nativephp/mobile/config/nativephp.php` kopieren. Der aus dem PoC bekannte
  Dreifach-`runtime`-Bug steckt in 4.5.1 **nicht** mehr drin.
- Ohne Eintrag in `NativeServiceProvider::plugins()` ist ein Plugin nicht in der
  Allowlist; `php artisan native:plugin:list` zeigt, was wirklich registriert ist.
- `config('app.name')` landet beim Build als `android:label` im Manifest,
  `config('nativephp.app_id')` als `applicationId` in `build.gradle`. Beides greift
  erst zur Build-Zeit — deshalb `tests/Feature/AppIdentitaetTest.php` als Wächter.
- `nativephp/mobile-ui` 0.3 hat andere Theme-Defaults als der Kitchen Sink
  (Background hell `#F8FAFC`, dunkel `#0F172A`). Nur `primary` wurde laut PRD
  überschrieben.
- **Offen, bewusst so gelassen:** `dark.on-primary` steht auf `#FFFFFF` (Paket-Default).
  Gegen das helle Indigo `#818CF8` ergibt das ca. 2,6:1 und reißt WCAG AA. Die PRD
  sagt „Rest bei den Paket-Standards", deshalb unverändert — sobald der erste
  gefüllte Button im Dark Mode zu sehen ist, sollte das ein dunkler Ton werden
  (z.B. `#1E1B4B`).
- Die Screens zeigen vorerst nur ihren Leerzustand aus der PRD. EKL-002/003 füllen
  Einkaufen und Vorrat, EKL-006 die Einstellungen, EKL-011 den Wochenplan.
- `plugins/` enthält nur `.gitkeep`; das Path-Repo in `composer.json` steht schon
  bereit für das SecureStorage-Plugin aus EKL-006.

**Build auf dem Gerät** (nicht ausgeführt, bitte selbst laufen lassen):

```sh
cp .env.example .env && php artisan key:generate
emulator -avd Pixel_7_API_35      # oder Gerät per USB
php artisan native:run android
```
<!-- chief-timing story="EKL-001" duration_ms=1130003 cost=43.882863 in=376 out=1265 cache_create=540946 cache_read=22426407 -->

## 2026-09-20 - EKL-002

Katalog als Konfiguration, Vorrat-Screen mit Gruppen und Tap-to-Add, eigener
Listen-Zustand in SQLite.

**Geänderte / neue Dateien**

- `config/katalog.php` — 8 Gruppen, 112 Artikel, 1:1 aus
  `~/Code/shopping-list/shared/items.ts`, verschachtelt als
  `gruppen.<id>.artikel.<id> => Name`; die Array-Reihenfolge *ist* die
  Anzeigereihenfolge
- `app/Katalog/{Katalog,Gruppe,Artikel}.php` — liest die Konfiguration,
  `gruppen()`, `artikelIds()`, `kennt()`, `gruppiert(array $ids)`
- `app/Liste/EigeneListe.php` — `artikelIds()`, `vorratIds()`, `anzahl()`,
  `hinzufuegen()`, `entfernen()`; filtert unbekannte IDs beim **Lesen**
- `app/Models/ListenArtikel.php` + Migration `create_listen_artikel_table`
  (String-Primärschlüssel `artikel_id`, sonst nur Timestamps)
- `app/NativeComponents/Vorrat.php`, `resources/views/native/vorrat.blade.php`
- `tests/Pest.php` — Helfer `listenAbschnitte()`, `navUntertitel()`
- `tests/Feature/VorratTest.php` — 10 Tests

31 Tests, 190 Assertions, grün. Pint sauber.

**Learnings für die nächsten Iterationen**

- **Anhang A hat 112 Artikel, nicht 113.** Der Story-Text und FR-1 nennen 113;
  nachgezählt in der Quelle (`shared/items.ts`) und in Anhang A selbst sind es
  112 (17/5/26/7/35/7/8/7). Der Katalog ist bewusst 1:1 die Quelle. Wenn in
  einer späteren Story wieder „113" steht: es bleiben 112.
- **`php artisan native:validate` meldet die mobile-ui-Elemente als Fehler**
  (`Unknown native element type: 'list' / 'list-section' / 'list-item'`).
  `Native\Mobile\Validation\BladeTemplateAnalyzer::KNOWN_ELEMENTS` ist eine
  hartkodierte Konstante mit nur den Core-Elementen, und `ValidateCommand`
  baut den Analyzer mit `new` — kein Container, kein Hook, keine Option. Das
  ist ein Paket-Gap in mobile 4.5.1, kein Fehler im Code; die Elemente
  rendern nachweislich (Wire-Tree-Tests). **Ab hier ist `native:validate`
  dauerhaft rot**, sobald ein Screen mobile-ui benutzt — vor dem Commit
  prüfen, dass *nur* „Unknown native element type" für mobile-ui-Tags
  übrig bleibt.
- **Ein Callback-Argument in Anführungszeichen direkt im Attribut** —
  `@press="aufDieListe('{{ $id }}')"` — lässt denselben Analyzer
  `'aufDieListe('` als Methodennamen extrahieren (seine Regex bricht am
  ersten `'` ab) und einen zweiten Fehler melden. Deshalb den Aufruf vorher
  in eine Variable legen: `@php($press = "aufDieListe('{$id}')")` und dann
  `@press="{{ $press }}"`. Numerische Argumente (`@press="open({{ $id }})"`)
  sind unproblematisch.
- **Migrationen laufen von selbst.** Der native Host ruft
  `artisan migrate --force` selbst auf, sobald das PHP-Payload frisch
  entpackt wurde (Android: `LaravelEnvironment.kt` `runBaseArtisanCommands()`,
  iOS: `AppUpdateManager.swift` `runMigrationsAndClearCaches()`). Kein
  `Artisan::call('migrate')` im App-Code. Konsequenz: **reines PHP-Hot-Reload
  führt keine neue Migration aus** — dafür braucht es `native:run`.
  Ebenso gibt es auf dem Gerät **kein `db:seed`**; Startdaten gehören in die
  `up()` einer Migration.
- Die Geräte-Datenbank liegt außerhalb des austauschbaren Payloads
  (Android `…/persisted_data/database/database.sqlite`, iOS
  `Application Support/database/database.sqlite`) und überlebt App-Updates.
  `database/database.sqlite` aus dem Repo wird beim Build ausgeschlossen.
- **`native:list-section` rendert nichts ohne Zeilen** und die Android-Seite
  setzt den Header selbst auf `uppercase`, `onSurfaceVariant`, 13sp, SemiBold
  (`vendor/nativephp/mobile-ui/resources/android/ListRenderer.kt`,
  `SectionHeader`). Der geforderte Kapitälchen-Stil kommt also aus dem
  Renderer — den Gruppennamen normal schreiben, nicht selbst großschreiben.
- **`trailingIcon` ist ein reiner String**, keine Enum. Enums gehören in
  `:trailingIconIos` / `:trailingIconAndroid` (gleiches Muster für
  `leadingIcon*`).
- **`on_press` steht auf Knotenebene, nicht in `props`** —
  `assertElement('list_item', fn ($n) => isset($n['on_press']))`.
- **`tap('Sichtbarer Text')` im Harness** findet das nächste pressable
  Element, dessen Teilbaum den Text zeigt — deutlich lesbarer als
  `press('methode(\'id\')')`, das die exakte Ausdrucks-Zeichenkette braucht
  (`press('methode')` allein trifft in einer Liste die *erste* Registrierung).
- **`#[Computed]`-Methoden heißen wie Properties** (`gruppen()` → `$this->gruppen`)
  und werden nach jedem UI-Event automatisch verworfen; `unset($this->gruppen)`
  im Handler ist trotzdem sinnvoll, wenn der Handler direkt gerufen wird.
- Der Untertitel kommt im Wire-Tree als `nav_subtitle` an und wird von
  `assertSee()` mit durchsucht — für genaue Prüfungen aber besser den Helfer
  `navUntertitel()` benutzen.
- **Zustand statt Zeitpunkt speichern:** `listen_artikel` hält nur die ID.
  Damit kostet eine Katalog-Änderung nichts, und die „unbekannte ID"-Regel
  aus FR-5 ist eine einzige Filterstelle (`EigeneListe::artikelIds()`).
  EKL-003 sollte für den Einkaufen-Screen dieselben zwei Klassen benutzen
  (`Katalog::gruppiert()` + `EigeneListe`), nicht eigene Abfragen bauen.
---
<!-- chief-timing story="EKL-002" duration_ms=566229 cost=43.549752 in=452 out=1310 cache_create=854140 cache_read=18286398 -->
