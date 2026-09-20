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
- **Icons in `leading-icon` / `icon`** (Textfeld, Button) sind ein *einzelner*
  String — die `:iconIos`/`:iconAndroid`-Paare von `native:icon` und `list-item`
  gibt es dort nicht. Den Namen in der Komponente auswählen
  (`IconResolver::resolve(null, Ios::X, Android::Y)['icon'] ?? Android::Y->value`)
  und per `$this->view('name', [...])` ins Blade reichen.
- **`native:outlined-text-input`:** `@change="handler"` → `public function
  handler(string $eingabe)`. `sync-mode="debounce"` + `debounce-ms="…"` steuern,
  wann getippter Text ankommt; `value="{{ $prop }}"` spiegelt den Zustand zurück.
  Im Harness tippt `->input('<ref>', 'text')`. **`native:model` und `@change`
  vertragen sich nicht** — beide belegen `_change`.
- **Trailing-Icons in Textfeldern sind rein dekorativ** (kein `@trailingPress`).
  Ein Löschen-Knopf muss ein eigenes `native:button` in derselben `native:row`
  sein. Analog fehlen `autofocus` / `autocorrect` / `autocapitalize` komplett
  (offene TODOs in `vendor/nativephp/mobile-ui/SHIPPING-CHECKLIST.md` 3.1) —
  `keyboard="url"` schaltet Autokorrektur und Auto-Großschreibung auf beiden
  Plattformen ab und ist der Ersatz, bis die Props kommen.
- **Prop-Namen sind nicht die Attributnamen:** `icon=` am Button landet als
  `leading_icon`, `a11y-label` als `a11y_label`, `leading-icon` am Textfeld als
  `leading_icon`. Vor dem Test-Schreiben einmal `dumpTree()` oder die
  Element-Klasse lesen.
- **`ref` steht auf Knotenebene, nicht in `props`** — dafür gibt es in
  `tests/Pest.php` den Helfer `knotenMitRef($screen, 'ref')`.
- **`follow()` gibt einen *neuen* Harness zurück.** Der alte ist danach
  „suspended" und wirft bei jeder Interaktion. Also
  `$neu = $alt->tap('Tab')->assertReplacedWith('/x')->follow();` und ab da nur
  noch mit `$neu` weiterarbeiten.
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

## 2026-09-20 - EKL-003

Der Einkaufen-Screen zeigt jetzt die eigenen Listen-Artikel gruppiert in
Katalogreihenfolge, jede Zeile mit leerer Leading-Checkbox; Tap auf die Zeile
schickt den Artikel zurück in den Vorrat. Untertitel „n Artikel" (bei 0 kein
Untertitel), Leerzustand mit Einkaufswagen-Icon bleibt erhalten.

Der Screen ist das Spiegelbild des Vorrats: dieselben zwei Fachklassen
(`Katalog::gruppiert()` + `EigeneListe`), nur `artikelIds()` statt
`vorratIds()` und `entfernen()` statt `hinzufuegen()`. Kein neuer Zustand,
keine eigene Abfrage.

**Dateien**

- `app/NativeComponents/Einkaufen.php` — `navSubtitle()`, `#[Computed] gruppen()`,
  `abhaken()`; Zahnrad-Action aus EKL-001 unverändert
- `resources/views/native/einkaufen.blade.php` — Liste + bestehender Leerzustand
- `tests/Feature/EinkaufenTest.php` — 10 Tests

41 Tests, 260 Assertions, grün. Pint sauber. `native:validate` unverändert rot
mit genau den 6 bekannten „Unknown native element type"-Meldungen (3 pro
Listen-Blade), keine neuen.

**Learnings für die nächsten Iterationen**

- **Ein Tab-Wechsel ist `replace`, keine `navigate`.** `tap('Einkaufen')` auf der
  Tab-Leiste funktioniert im Harness, aber danach braucht es
  `->assertReplacedWith('/')->follow()`. Ohne `follow()` bleibt der alte Screen
  im Baum stehen und der Test prüft stumm das Falsche —
  `assertNavigatedTo()` schlägt hier mit „got [replace]" fehl. Damit lässt sich
  „ohne Neuladen beim Tab-Wechsel" echt testen statt über einen frischen
  `Native::visit()`.
- **Boolesche `list-item`-Attribute gebunden übergeben:** `:leadingCheckbox="false"`.
  Als `leadingCheckbox="false"` käme die *Zeichenkette* „false" an, und
  `ListItem::applyAttributes()` castet mit `(bool)` — die Checkbox wäre angehakt.
  Die Prüfung `isset($attrs['leadingCheckbox'])` ist auf `false` trotzdem wahr,
  die Checkbox erscheint also.
- **`leadingCheckbox` setzt zwei Props:** `leading_type = 'checkbox'` und
  `leading_checked`. Beide zusammen prüfen — `leading_checked === false` allein
  wäre auch ohne Checkbox erfüllt (fehlendes Prop ≠ false, aber der `?? null`
  in der Matcher-Kette verschleiert das leicht).
- **Kein Untertitel = `navSubtitle(): null`.** `NavBar::subtitle(null)` lässt das
  `nav_subtitle`-Prop weg, `navUntertitel()` liefert dann `null`. Ein leerer
  String wäre etwas anderes.
- Der Leerzustand des Einkaufen-Screens stand seit EKL-001 schon im Blade und
  erfüllte das Akzeptanzkriterium wörtlich — vor dem Nachbauen prüfen, was der
  Grundgerüst-Commit schon hingelegt hat.
- Für EKL-005 („Alles abhaken"): `EigeneListe` hat noch keine Bulk-Operation.
  Die Anzahl für den Dialogtext kommt aus `anzahl()`, das Entfernen müsste eine
  neue Methode (`alleEntfernen()`) werden, damit es ein Query bleibt.
- Für EKL-007 (Mealie): `navSubtitle()` und der Leerzustand hängen beide allein
  an `EigeneListe::anzahl()`. Sobald Mealie-Artikel dazukommen, müssen beide
  Stellen auf eine gemeinsame „offene Artikel"-Zahl umgestellt werden — sonst
  verschwindet der Untertitel, obwohl Mealie-Zeilen sichtbar sind.
---
<!-- chief-timing story="EKL-003" duration_ms=222992 cost=10.644197 in=114 out=413 cache_create=239785 cache_read=4077029 -->

## 2026-09-20 - EKL-004

Der Vorrat-Screen hat jetzt ein Suchfeld über der Liste: `native:outlined-text-input`
mit Lupe vorn, Platzhalter „Artikel suchen…", Debounce 200 ms. Die Eingabe filtert
live über `Katalog::gefiltert()` (contains, ohne Rücksicht auf Groß-/Kleinschreibung,
Begriff getrimmt); weil danach wieder `Katalog::gruppiert()` läuft, fallen Gruppen
ohne Treffer samt Überschrift von selbst weg. Sobald Text im Feld steht, erscheint
daneben ein Löschen-Button (X, `a11y-label` „Suche leeren"). Ohne Treffer zeigt der
Screen „Keine Treffer für „…"." mit `search_off`-Icon. Der Suchtext ist
Komponenten-Zustand (`public string $suche`) und damit beim nächsten Öffnen des
Tabs wieder leer.

**Dateien**

- `app/Katalog/Katalog.php` — neue Methode `gefiltert(array $ids, string $begriff)`
- `app/NativeComponents/Vorrat.php` — `$suche`, `suchen()`, `sucheLeeren()`,
  `iconName()`; `gruppen()` filtert jetzt zusätzlich
- `resources/views/native/vorrat.blade.php` — Column/Row-Gerüst, Suchfeld,
  Löschen-Button, zweiter Leerzustand
- `tests/Pest.php` — Helfer `knotenMitRef()`
- `tests/Feature/VorratTest.php` — 11 neue Tests

53 Tests, 369 Assertions, grün. Pint sauber. `native:validate` rot mit 7 statt 6
„Unknown native element type"-Meldungen — die neue ist `outlined-text-input`,
also wieder nur ein mobile-ui-Tag, das der Core-Analyzer nicht kennt. Keine
andere Fehlerart. `native:row` und `native:button` bemängelt er nicht.

**Learnings für die nächsten Iterationen**

- **Was mobile-ui 0.3 beim Textfeld nicht kann:** kein `autofocus`, kein
  `autocorrect`, kein `autocapitalize`, kein pressbares Trailing-Icon. Die ersten
  drei stehen als offene Punkte in `vendor/nativephp/mobile-ui/SHIPPING-CHECKLIST.md`
  (Abschnitt 3.1). Kein Autofokus ist ohnehin das Verhalten ohne Zutun; gegen
  Autokorrektur/Großschreibung hilft nur die URL-Tastatur (`keyboard="url"`), die
  beides auf iOS wie Android abschaltet. Ein `<native:button>` in derselben Row
  ersetzt den Clear-Button im Feld — optisch dicht dran, aber nicht *im* Rahmen.
- **Zwei Leerzustände brauchen eine Reihenfolge.** „Keine Treffer" muss vor
  „Alles auf der Liste." geprüft werden, sonst schluckt der alte Zweig den neuen.
  Unterscheidungsmerkmal ist der *getrimmte* Begriff, nicht `$suche` — bei einer
  Eingabe aus lauter Leerzeichen filtert nichts, also gilt der alte Zustand.
- **Getrimmt anzeigen, ungetrimmt speichern.** `$suche` hält die Rohreingabe
  (sonst erschiene der Löschen-Button bei „   " nicht), die Anführungszeichen im
  Leerzustand bekommen den getrimmten Begriff.
- **`->input()` löst den Handler sofort aus**, unabhängig von `debounce-ms`. Der
  Debounce ist reine Geräteseite; im Test lässt er sich nur als Prop prüfen
  (`sync_mode`, `debounce_ms`), nicht als Zeitverhalten.
- Für EKL-005 („Alles abhaken") gilt weiterhin: `EigeneListe` hat keine
  Bulk-Operation, `alleEntfernen()` müsste neu dazu.
- Für EKL-007 (Mealie): `Katalog::gefiltert()` arbeitet nur über Katalog-IDs.
  Mealie-Artikel ohne Katalog-Eintrag fielen durch das Raster — dann braucht die
  Suche eine zweite Quelle statt einer ID-Filterung.
---
<!-- chief-timing story="EKL-004" duration_ms=767403 cost=26.335005 in=252 out=863 cache_create=561040 cache_read=10498000 -->
