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
- **Bulk gegen Einzeln in `Http::fake()`:** `…/items/*` und `…/items` sind
  zwei getrennte Muster — das abschließende `*` darf leer sein, der `/`
  davor nicht fehlen. Wer eine Route vergisst, schickt eine echte Anfrage
  ins Netz.
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
- **Aufklappbare Abschnitte** gibt es nicht als Element: `list-section` hat
  keine tappbare Überschrift. Kopfzeile und Zeilen als lose `list-item`
  direkt in die `native:list` hängen — der Renderer mischt das mit
  Abschnitten. Zustand, der den Remount überleben soll, in ein Singleton.
- **Farben an `list-item`-Props** (`headline-color`, …) haben keine
  Dark-Mode-Entsprechung. Statt eines festen Werts den Helfer
  `theme('on-surface-variant')` benutzen: er löst beim Rendern nach der
  aktuellen Darstellung auf.
- **`Http::fake([...])` lässt unpassende Anfragen ins echte Netz.** Jede
  Route, die der Test auslösen kann, braucht ein eigenes Muster.
- **Callback mit String-Argument** nie direkt ins Attribut schreiben
  (`@press="tu('{{ $id }}')"` bricht den Validator). Stattdessen
  `@php($press = "tu('{$id}')")` und `@press="{{ $press }}"`.
- **Migrationen laufen auf dem Gerät automatisch** beim Entpacken des Payloads;
  kein `Artisan::call('migrate')`, kein `db:seed` — Startdaten in eine
  Migration. Nach einer neuen Migration braucht es `native:run`, PHP-Hot-Reload
  reicht nicht.
- **Native Dialoge** über `Dialog::alert($titel, $text, $buttons)`; Buttons als
  String oder `['label' => …, 'style' => 'default'|'cancel'|'destructive']`. Das
  Ergebnis kommt als `->buttonPressed(fn (ButtonPressed $e) => …)` zurück, die
  Closure wird an die lebende Komponente gebunden. `show()` ist optional — der
  Destruktor zeigt den Dialog. Im Test: `assertNativeCalled('Dialog.Alert', …)`
  und `->emitNative(ButtonPressed::class, ['index' => …, 'label' => …])`; ohne
  `id` im Payload korreliert der einzige offene Callback der Event-Klasse.
- **`navigationOptions()` wird bei jedem Render neu ausgewertet** — bedingte
  Top-Bar-Actions gehören dorthin. `NavBarOptions::action()` ist additiv, die
  Aufrufreihenfolge ist die Reihenfolge in der Bar; Knotentyp im Baum ist
  `top_bar_action` mit `props.a11y_label`.
- **Gruppenreihenfolge im Katalog:** Obst & Gemüse, Brot & Backwaren, Kühlregal,
  Tiefkühl, Lebensmittel, Getränke, Haushalt, Drogerie.
- **Kein Mid-Handler-Render:** Der Runloop rendert erst *nach* dem Handler.
  Netzwerkaufrufe mit sichtbarem Ladezustand gehören darum in
  `$this->async(static fn () => …)->finished(…)->failed(…)`; die Closure muss
  `static` sein und darf nur Serialisierbares einfangen. `->failed()` ist die
  einzige Garantie, dass ein Spinner wieder ausgeht. Im Test `AsyncTask::fake()`.
- **`TestableComponent::tree()` ist der zuletzt publizierte Baum**, kein frisches
  Rendern. Handler-interne Zustände nur über `$screen->get('prop')` prüfen;
  `$screen->set('prop', …)` rendert neu.
- **Secure Storage im Test:** `fakeSecureStore(?string $anfangswert)` in
  `tests/Pest.php` ist ein In-Memory-Keystore über `Native::fakeBridge()`.
  `tests/Pest.php` räumt `FakeBridge` und `AsyncTask` in einem `afterEach` weg.
- **Optionale Bool-Props (`disabled`, `loading`) stehen nur im Baum, wenn wahr** —
  Erwartungen mit `?? false` schreiben.
- **Mealie-Konfiguration** in `config/mealie.php` (`url`, `shopping_list_id`,
  `timeout`), das Token ausschließlich über `App\Mealie\Token`.
- **Mealie-Daten:** Laden/Parsen in `App\Mealie\Einkaufsliste` (statisch,
  serialisierbar für `async`), Sitzungszustand im Singleton `App\Mealie\Sitzung`,
  Label→Gruppe über `App\Mealie\Gruppenzuordnung` (`config/mealie.php`:
  `label_aliase`, `gruppe_ohne_label`). Das Zusammenlegen mit den eigenen
  Artikeln macht `App\Einkaufen\Uebersicht` (`abschnitte()`, `anzahl()`).
- **Screen-Lebenszyklus:** `mount()` beim Öffnen/Tab-Wechsel, `onResume()` beim
  Zurückkehren von einem gepushten Screen (im Test `->follow()` … `->goBack()`).
  Der App-Vordergrund kommt nicht aus dem Framework, sondern aus dem lokalen
  Plugin `ben182/app-lifecycle` als `#[On(AppForegrounded::class)]`.
- **Zustand über Screenwechsel hinweg** gehört in ein Singleton aus
  `AppServiceProvider::register()` — Komponenten werden bei jedem Tab-Wechsel
  neu gemountet.
- **Kein `@if` innerhalb der Attributliste eines `native:`-Tags** (ParseError);
  bedingte Attribute als `:attr="$wert ?? ''"`. `native:button` braucht
  `label="…"` statt Slot-Inhalt.
- **Ein Singleton wirkt erst, wenn es in `AppServiceProvider::register()` steht.**
  Ohne den Eintrag gibt `app(X::class)` bei jedem Aufruf eine *neue* Instanz —
  der `finished()`-Callback schreibt dann in eine Wegwerf-Instanz und der
  Screen zeichnet leer. Jede neue Sitzungsklasse braucht ihre Zeile dort.
- **`native:list` verträgt lose Kinder jedes Typs:** der Renderer schickt jedes
  direkte Kind, das kein `list_section` ist, durch `NodeView`. Eine Überschrift
  mit eigener Farbe geht deshalb als `native:text` direkt in die Liste —
  `list-section` hat nur `header`/`footer` und kennt keine Farbe.
- **`native:pressable` ist der tappbare Container** (Kern-Element wie `row` /
  `column`, `@press` kommt aus der Element-Basis). Der Collector setzt
  `a11y-label` generisch für *jedes* Element, auch wenn dessen
  `applyAttributes()` es gar nicht kennt — also landet es als `props.a11y_label`.
- **`native:icon` legt den Namen in `props.name`**, nicht in `props.icon`.
- **Browser-Plugin:** `Browser::open($url)` ruft `nativephp_call('Browser.Open')`
  und ist damit über `assertNativeCalled('Browser.Open', …)` prüfbar. Der
  `BrowserServiceProvider` muss in `NativeServiceProvider::plugins()` stehen,
  sonst fehlt er im nativen Build (`php artisan native:plugin:list` zeigt es).
- **Wochenplan-Daten:** Laden/Parsen in `App\Wochenplan\Plan` (statisch,
  serialisierbar für `async`), Woche als Wertobjekt `App\Wochenplan\Woche`
  (Montag–Sonntag, `text()`, `schluessel()`), Sitzungszustand je Woche im
  Singleton `App\Wochenplan\Sitzung`, die sieben Tage baut
  `App\Wochenplan\Uebersicht::tage()`.
- **`Http::fake()` zweimal aufrufen ersetzt nichts** — für aufeinanderfolgende
  Antworten `Http::fakeSequence('<muster>')->push(…)`.

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

## 2026-09-20 - EKL-005

Der Einkaufen-Screen hat eine zweite Top-Bar-Action bekommen: Häkchen-Icon
(`checkmark.circle` / `done_all`) mit `a11y-label` „Alles abhaken“, links vom
Zahnrad und nur sichtbar, solange `EigeneListe::anzahl() > 0`. Ihr Tap öffnet
`Dialog::alert('Alles abhaken?', …, ['Abbrechen' (cancel), 'Abhaken' (default)])`;
der Text ist singular- bzw. pluralrichtig („1 Artikel wandert…“ / „n Artikel
wandern…“). Die Bestätigung hängt an `->buttonPressed()`: nur das Label
„Abhaken“ räumt über die neue `EigeneListe::alleEntfernen()` ab, danach greift
der Leerzustand aus EKL-003 und die Action verschwindet mit. „Abbrechen“ und
ein Dialog ohne Button-Event lassen alles stehen.

**Dateien**

- `app/NativeComponents/Einkaufen.php` — `navigationOptions()` baut die Actions
  jetzt bedingt, neue Methode `alleAbhakenBestaetigen()`
- `app/Liste/EigeneListe.php` — `alleEntfernen()`
- `tests/Feature/EinkaufenTest.php` — 6 neue Tests

59 Tests, 409 Assertions, grün. Pint sauber. `native:validate` unverändert rot
mit denselben 7 „Unknown native element type“-Meldungen wie nach EKL-004, keine
neuen.

**Learnings für die nächsten Iterationen**

- **Native Dialoge sind im Harness voll testbar**, ohne eigenen Seam:
  `->press('handler')` löst ihn aus, `assertNativeCalled('Dialog.Alert', fn ($params) => …)`
  prüft `title` / `message` / `buttons` / `id` / `event` auf der Wire-Ebene, und
  `->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Abhaken'])`
  spielt den Tap im Dialog zurück. Ein Dialog, der ohne Button weggeht, ist
  schlicht „kein `emitNative`“ — auch das ein Test.
- **Ohne `id` im `emitNative`-Payload greift die Fallback-Korrelation**
  (`NativeCallbacks::resolveByEvent`): ein einziger offener Callback pro
  Event-Klasse genügt. Die Alert-ID ist eine frische UUID pro Aufruf, sie im
  Test zu beschaffen wäre unnötige Kopplung.
- **`PendingAlert::__destruct()` zeigt den Dialog von selbst.** `->show()` ist
  optional; `Dialog::alert(…)->buttonPressed(…)` als Statement reicht. Wer das
  Objekt in einer Variablen festhält, verzögert damit die Anzeige bis zum Ende
  des Scopes.
- **Buttons entweder als String oder als `['label' => …, 'style' => …]`**
  (`default` / `cancel` / `destructive`). Ein unbekannter Style wirft schon im
  Konstruktor. Die Unterscheidung im Callback läuft über `$event->label`, nicht
  über `$event->index` — das Label steht auch im Akzeptanzkriterium.
- **`navigationOptions()` wird bei jedem Render neu ausgewertet**, taugt also
  für bedingte Actions. `NavBarOptions::action()` ist fluent und additiv; die
  Reihenfolge der `action()`-Aufrufe ist die Reihenfolge in der Bar.
- **Gruppenreihenfolge im Katalog:** Obst & Gemüse, Brot & Backwaren,
  **Kühlregal**, Tiefkühl, Lebensmittel, Getränke, Haushalt, Drogerie. Kühlregal
  steht *vor* Lebensmittel — in `listenAbschnitte()`-Erwartungen leicht verdreht.
- Für EKL-007 (Mealie): `alleAbhakenBestaetigen()` zählt und räumt nur die
  eigenen Artikel. Sobald Mealie-Zeilen auf dem Screen stehen, müssen die
  Sichtbarkeitsbedingung der Action, die Zahl im Dialogtext und das Abräumen
  gemeinsam auf „alle offenen Artikel“ umgestellt werden — dieselbe Stelle wie
  `navSubtitle()` und der Leerzustand.
---
<!-- chief-timing story="EKL-005" duration_ms=236214 cost=12.414086 in=144 out=509 cache_create=235748 cache_read=5302317 -->

## 2026-09-20 - EKL-006

Das SecureStorage-Plugin aus `~/Herd/kitchen-sink` liegt jetzt unter
`plugins/secure-storage` mit eigenem Vendor-Namespace (`Ben182\SecureStorage`,
`de.ben182.securestorage`, `ben182/secure-storage`), hängt über das bereits
vorhandene `plugins/*`-Path-Repo in `composer.json` und steht in
`NativeServiceProvider::plugins()`. `native:plugin:list` führt es mit 3
Bridge-Funktionen auf, `native:plugin:validate` meldet OK.

Der Einstellungen-Screen verwaltet darüber das Mealie-Token: Server-URL als
Text, maskiertes Feld „API-Token“, „Speichern“ (Toast „Bitte Token eingeben“
bzw. „Token gespeichert“, Feld wird geleert), Statuszeile mit den vier
`SecureStorageStatus`-Ausgängen, „Verbindung testen“ (nur aktiv mit Token,
Ladezustand am Knopf) und „Token löschen“ (destruktiv, mit Bestätigungsdialog).

**Dateien**

- `plugins/secure-storage/{composer.json,nativephp.json,src/SecureStorageServiceProvider.php,resources/android/SecureStorageFunctions.kt,resources/ios/SecureStorageFunctions.swift}`
- `composer.json` / `composer.lock` — `ben182/secure-storage: @dev`
- `app/Providers/NativeServiceProvider.php` — Allowlist
- `config/mealie.php` — `url`, `shopping_list_id`, `timeout`
- `app/Mealie/Token.php` — Schlüssel `einkaufsliste.mealie-token`, `read()`/`set()`/`delete()`
- `app/Mealie/Verbindung.php` — statischer Verbindungstest gegen Mealie
- `app/NativeComponents/Einstellungen.php`, `resources/views/native/einstellungen.blade.php`
- `tests/Pest.php` — `afterEach`-Aufräumen, Helfer `fakeSecureStore()`
- `tests/Feature/EinstellungenTest.php` — 23 Tests

82 Tests, 554 Assertions, grün. Pint sauber. `native:validate` rot mit 8 statt 7
„Unknown native element type“-Meldungen — die neue ist das
`outlined-text-input` im Einstellungen-Blade, also wieder nur ein
mobile-ui-Tag, das der Core-Analyzer nicht kennt. Keine andere Fehlerart.

**Learnings für die nächsten Iterationen**

- **Path-Repo-Plugins brauchen `@dev` als Constraint.** `composer require
  ben182/secure-storage:"*"` scheitert an `minimum-stability: stable`, weil
  Composer dem Pfad-Paket die Version des aktuellen Git-Branchs gibt
  (`dev-chief/default`). `:"@dev"` löst das und bleibt beim Branch-Wechsel gültig.
- **Der PHP-Teil von SecureStorage ist schon im Core** (`Native\Mobile\SecureStorage`,
  `SecureStorageResult`, `SecureStorageStatus`, Facade). Das Plugin liefert nur
  die native Hälfte und bindet die Core-Klasse als Singleton. Ohne Bridge gibt
  `read()` von sich aus `SecureStorageResult::failure('BRIDGE_UNAVAILABLE', …)`
  zurück — der „Testkontext ohne Bridge“-Zustand braucht keinen eigenen Code.
- **`Native::fakeBridge()->respondTo()` nimmt Closures** und wird damit zum
  kleinen In-Memory-Keystore (`fakeSecureStore()` in `tests/Pest.php`): Set
  merkt sich den Wert, Get gibt ihn zurück, Delete leert ihn. Wichtig: **keine
  Pfeilfunktion** für den Get-Handler — `fn () => $gespeichert` bindet per Wert
  und sieht für immer den Anfangswert. `function () use (&$…)` ist Pflicht.
- **FakeBridge und AsyncTask-Fake sind statischer Paket-Zustand.** `tests/Pest.php`
  räumt beide jetzt in einem `afterEach` weg (`FakeBridge::disable()`,
  `AsyncTask::clearFake()`), sonst lecken sie in den nächsten Test.
- **Es gibt keinen Mid-Handler-Render.** Der Runloop ist
  `render → publish → wait → handler → loop`; ein synchrones `Http::get()` im
  Press-Handler friert die UI ein und jeder Spinner, den man davor setzt, wird
  nie gezeichnet. Der Weg dafür ist `$this->async(static fn () => …)` mit
  `->finished()` / `->failed()`. Die Closure muss **static** sein (läuft in einem
  eigenen Interpreter), darf nur Serialisierbares einfangen, und `->failed()`
  ist die einzige Garantie, dass ein Spinner wieder ausgeht — auch wenn der Task
  gar nicht erst startet.
- **Im Test `AsyncTask::fake()` setzen**, dann läuft die Arbeit inline und
  `Http::fake()` greift. Ohne `fake()` liefert der Transport `false` und nur
  `->failed()` feuert.
- **`TestableComponent::tree()` ist der *zuletzt publizierte* Baum**, kein
  frisches Rendern. Ein Zustand, der nur innerhalb eines Handlers existiert
  (z. B. `$testLaeuft`), steht in keinem Baum — prüfbar ist er über
  `$screen->get('prop')` aus einem `Http::fake()`-Callback heraus; dass der
  Knopf ihn trägt, zeigt danach `$screen->set('prop', true)` (das rendert neu).
- **`disabled` und `loading` am `native:button` stehen nur im Baum, wenn sie
  wahr sind** (`if (! empty($attrs[…]))`). Erwartungen also mit `?? false`
  schreiben, sonst „Undefined array key“.
- **Toasts:** `Dialog::toast($text)` → Bridge-Call `Dialog.Toast` mit `message`
  und `duration`; im Test `assertNativeCalled('Dialog.Toast', fn ($p) => …)`.
- Für EKL-007/011: Token-Zugriff ausschließlich über `App\Mealie\Token`
  (Schlüssel `einkaufsliste.mealie-token`, geschrieben mit `AfterFirstUnlock`),
  Basis-URL und Timeout aus `config/mealie.php`. `App\Mealie\Verbindung` ist
  bewusst statisch und abhängigkeitsfrei — alles, was über `async` läuft, muss
  sich serialisieren lassen.
---
<!-- chief-timing story="EKL-006" duration_ms=885065 cost=38.459031 in=348 out=1202 cache_create=761202 cache_read=16060749 -->

## 2026-09-20 - EKL-007

Der Einkaufen-Screen zeigt jetzt eigene Artikel und offene Mealie-Artikel in
einer Liste. Geladen wird beim Mount, bei `onResume()` (Rückkehr von den
Einstellungen), bei Pull-to-Refresh (`on-refresh="neuLaden"`) und auf das
Native-Event `App\Events\AppImVordergrund` — alles über `async`, damit der
Runloop nicht hängt. Label-Zuordnung: exakter Gruppenname → Alias-Tabelle
(`config/mealie.php`, Anhang B) → eigene Gruppe; ohne Label „Sonstiges“.
Katalog-Gruppen zuerst in Katalogreihenfolge, Label-Gruppen danach
alphabetisch (Umlaute auf Grundbuchstaben normalisiert). Innerhalb einer
Gruppe eigene Artikel zuerst, dann Mealie nach `position`, dann `createdAt`.

**Dateien**

- `config/mealie.php` — `label_aliase`, `gruppe_ohne_label`
- `app/Mealie/Einkaufsliste.php` — statischer Loader + Parser (inkl.
  Rezeptnamen aus `recipeReferences`, Nachladen über `/api/recipes/{id}`)
- `app/Mealie/Eintrag.php`, `app/Mealie/Sitzung.php` (Singleton),
  `app/Mealie/Gruppenzuordnung.php`
- `app/Einkaufen/{Zeile,Abschnitt,Uebersicht}.php` — das Zusammenlegen
- `app/Events/AppImVordergrund.php`
- `app/Providers/AppServiceProvider.php` — `Sitzung` als Singleton
- `app/NativeComponents/Einkaufen.php`, `resources/views/native/einkaufen.blade.php`
- `tests/Feature/EinkaufenMealieTest.php` — 31 Tests

113 Tests, 699 Assertions, grün. Pint sauber. `native:validate` rot mit 9
„Unknown native element type“-Meldungen (eine mehr als nach EKL-006: die
zweite `list-item`-Variante im Einkaufen-Blade), keine andere Fehlerart, keine
Warnungen.

**Zweites Plugin: `plugins/app-lifecycle`**

NativePHP Mobile 4.5 brückt den App-Lebenszyklus nicht nach PHP — in
`Events/App/` liegt allein `UpdateInstalled`, `NativePHPLifecycle.ON_RESUME`
(Kotlin) und `NativePHP.didBecomeActive` (Swift) bleiben auf der Geräteseite.
Für den Trigger „App kehrt in den Vordergrund zurück“ gibt es deshalb ein
zweites lokales Plugin, `ben182/app-lifecycle` (Namespace `Ben182\AppLifecycle`,
Kotlin-Package `de.ben182.applifecycle`). Es hat **keine Bridge-Funktion**,
sondern nur eine **Init-Funktion** (`android.init_function` /
`ios.init_function` in `nativephp.json`): die wird beim Start einmal
aufgerufen, abonniert den Lebenszyklus und legt
`Ben182\AppLifecycle\Events\AppForegrounded` über
`NativeElementBridge.sendNativeEvent` in die Element-Event-Queue — derselbe
Weg, den der eingebaute ShakeDetector für `ShakeDetected` nimmt. Der
Einkaufen-Screen hört mit `#[On(AppForegrounded::class)]` darauf.

`native:plugin:list` führt es auf, `native:plugin:validate` meldet dafür eine
Warnung („No bridge_functions defined in manifest“) — erwartet für ein
Init-only-Plugin, kein Fehler. **Die native Hälfte ist ungebaut und damit
ungetestet** (wie die von `secure-storage`): Kotlin und Swift werden erst beim
ersten `native:run` kompiliert.

**Learnings für die nächsten Iterationen**

- **`onResume()` ist der Hook für „der gepushte Screen ist weg“.** Der Router
  (`NativeRouter.php:496-501`) mountet nur bei `$freshPush`; beim Pop läuft
  `onResume()` auf dem darunterliegenden, **lebenden** Screen. Im Harness:
  `$screen->press('oeffneEinstellungen')->follow()` … `->goBack()` — das feuert
  `onResume()` und rendert neu. Damit ist „Token speichern und zurückkehren“
  ein einziger durchgehender Test.
- **`$this->async()` funktioniert schon in `mount()`** und läuft mit
  `AsyncTask::fake()` inline durch, `finished()` inklusive. Der Screen ist nach
  `Native::visit()` also bereits im Nach-Lade-Zustand.
- **Ein zweites `Http::fake()` ersetzt das erste nicht** — die neue Regel landet
  *hinter* der alten, und die alte trifft weiter zuerst. Für „erst A, dann B“
  gibt es `Http::fakeSequence('<muster>')->push(…)->push(…)`.
- **Zustand, der den Screenwechsel überleben soll, gehört in ein Singleton**
  (`AppServiceProvider::register()`), nicht in eine Property: jeder Tab-Wechsel
  mountet den Root-Screen neu. Im Test überlebt das Singleton genau einen Test,
  also verhält sich „zweiter `Native::visit()` im selben Test“ wie ein
  Tab-Wechsel auf dem Gerät — praktisch für „nur beim ersten Mal“-Regeln.
- **„Nur beim ersten Ladevorgang“ lässt sich am Seam prüfen**, indem man den
  Screen erst ohne Token besucht (kein Laden), dann das Token in den Fake-Store
  schreibt und `neuLaden` auslöst: Jetzt existiert der Harness *während* des
  ersten Ladevorgangs, und `$screen->get('…')` aus einem `Http::fake()`-Callback
  heraus sieht den Zwischenzustand.
- **Blade: kein `@if` innerhalb der Attributliste eines `native:`-Tags.** Der
  Precompiler zerlegt das Tag und der Rumpf landet als `<?php else: ?>` ohne
  `if` → ParseError. Bedingte Attribute als `:attr="$wert ?? ''"` schreiben oder
  das ganze Tag doppeln.
- **`native:button` braucht `label="…"`**, keinen Slot-Inhalt — sonst warnt
  `native:validate` („without 'label' attribute“) und das Label fehlt.
- **Trailing-Icon am `list-item`:** `:trailingIconIos` / `:trailingIconAndroid`
  (camelCase, kebab wird für diese nicht übersetzt), dazu
  `trailing-a11y-label="…"` → Props `trailing_icon`, `trailing_a11y_label`.
  Keine Farbe setzen: `ListItemDefaults` zeichnet Trailing-Icons ohnehin in
  `onSurfaceVariant`, und `trailingIconColor` nimmt nur feste Hex-Werte ohne
  Dark-Mode-Entsprechung.
- **Mealies Listen-Antwort trägt die Rezeptnamen schon mit**: oben auf der
  Liste steht `recipeReferences[].recipe.name`, am Artikel nur `recipeId`. Die
  Zuordnung läuft über diese Tabelle; nur was dort fehlt, wird einzeln über
  `/api/recipes/{id}` nachgeladen. In der echten Instanz war das nie nötig.
- **Echte Zahlen der Instanz** (Stand 2026-09-20): 62 Artikel auf der Liste,
  davon 9 offen; Labels `Gemüse, Obst, Backwaren, Milchprodukte, Konserven,
  Gewürze, Würzmittel, Getreide, Snacks, Getränke, Obst & Gemüse, Sonstiges` —
  alle bis auf „Obst & Gemüse“, „Getränke“ und „Sonstiges“ laufen über die
  Alias-Tabelle. `position` ist bei allen 0, die Reihenfolge entscheidet also
  `createdAt`.
- **Ein Plugin ohne Bridge-Funktion ist möglich und oft das Richtige**, wenn es
  nur *von* der Geräteseite *nach* PHP melden soll: `bridge_functions: []` plus
  `android.init_function` (Top-Level-Kotlin-Funktion, bekommt `context`) und
  `ios.init_function` (Swift-Funktion ohne Argumente). Beide werden aus
  `PluginBridgeFunctionRegistration` heraus einmal beim Start aufgerufen. Ein
  eigener Wächter gegen Doppelanmeldung gehört dazu — eine neu erzeugte
  Activity ruft die Init-Funktion erneut auf.
- **Der Weg Gerät → PHP heißt `NativeElementBridge.sendNativeEvent(name, json)`**
  (Kotlin) bzw. `LaravelBridge.shared.send?(name, [:])` (Swift). Als `name`
  reicht der voll qualifizierte PHP-Klassenname; `#[On(Klasse::class)]` findet
  ihn mit und ohne `native:`-Präfix.
- Für EKL-008/010: Mealie-Zeilen haben schon `ref="mealie-<itemId>"` und
  tragen die Mealie-Artikel-ID; `Sitzung::alle()` liefert auch die abgehakten.
- **`Http::fake()` kennt kein Umschalten:** die zuerst registrierte passende
  Regel gewinnt für immer. Zwei Phasen in einem Test brauchen *eine* Closure
  mit einem Schalter darin, nicht zwei `Http::fake()`-Aufrufe.
- **Persistenz prüft man mit `app()->forgetInstance(...)`** — der
  App-Neustart im Test. SQLite bleibt, die Singletons gehen.
- **`disabled` am `list-item` sperrt den Tap vollständig**
  (`clickable(enabled = !disabled)`): eine gesperrte Zeile kann keinen
  erklärenden Toast mehr auslösen. Wer beides will, dämpft die Zeile von
  Hand über `theme('on-surface-variant')` statt über `disabled`.
<!-- chief-timing story="EKL-007" duration_ms=1121031 cost=69.944824 in=538 out=2271 cache_create=766285 cache_read=36932390 -->

## 2026-09-20 - EKL-008

Mealie-Zeilen sind jetzt tappbar. Ein Tap legt den Haken sofort lokal um
(`Sitzung::haken()`), zeichnet Liste und Untertitel neu und schickt über
`async` ein `PUT /api/households/shopping/items/{id}` mit Mealies eigener
Artikeldarstellung plus umgelegtem `checked`. Antwortet Mealie nicht mit
2xx (oder läuft der Aufruf in einen `ConnectionException`), springt der
Artikel zurück und `Dialog::toast('Mealie: Änderung fehlgeschlagen')`
meldet es. Abgehaktes sammelt sich in einem einklappbaren Block am Ende
der Liste („Abgehakt (n)“, Chevron zeigt den Zustand, standardmäßig zu);
der Auf-/Zu-Zustand liegt in der `Sitzung` und überlebt den Tab-Wechsel.

**Dateien**

- `app/Mealie/Artikelstatus.php` — neu, der PUT
- `app/Mealie/Eintrag.php` — trägt jetzt `roh` (Mealies ganze Darstellung)
  und hat `mitHaken()`
- `app/Mealie/Einkaufsliste.php` — reicht `roh` durch
- `app/Mealie/Sitzung.php` — `abgehakte()`, `finden()`, `haken()`,
  `abgehakteAufgeklappt()`, `abgehakteUmklappen()`
- `app/Einkaufen/Uebersicht.php` — `abgehakte()`
- `app/NativeComponents/Einkaufen.php` — `mealieUmschalten()`,
  `abgehakte` (Computed), `listeNeuZeichnen()`
- `resources/views/native/einkaufen.blade.php`
- `tests/Feature/EinkaufenMealieTest.php` — 9 neue Tests

123 Tests, 797 Assertions, grün. Pint sauber. `native:validate` rot mit 11
„Unknown native element type“-Meldungen (zwei mehr als nach EKL-007: die
beiden neuen `list-item`-Varianten), keine andere Fehlerart.

**Manueller Gegentest an der echten Instanz** (Stand 2026-09-20): PUT mit
`{...artikel, checked: true}` auf `/api/households/shopping/items/{id}`
antwortet 200, der anschließende GET der Liste zeigt `checked: true`,
`display` und `label` unverändert; der Rückweg mit `checked: false`
stellt den Ausgangszustand wieder her (9 offene Artikel vorher wie
nachher). Die Web-UI liest denselben Zustand.

**Learnings für die nächsten Iterationen**

- **`list-section`-Überschriften sind nicht tappbar** (`ListSection` kennt nur
  `header`/`footer`, keinen Callback). Ein aufklappbarer Abschnitt wird
  deshalb aus einer `list-item`-Kopfzeile plus Zeilen gebaut, die *direkt*
  in der `native:list` hängen. Der Android-Renderer mischt Abschnitte und
  lose Zeilen problemlos (`ListRenderer.kt`: alles, was kein `list_section`
  ist, wird als eigenes `item` gezeichnet).
- **`listenAbschnitte()` sieht nur `list_section`-Knoten.** Lose Zeilen am
  Listenende liest der neue Helfer `abgehaktBlock()` / `abgehaktZeilen()`
  aus den direkten `list_item`-Kindern der Liste — deshalb blieben alle
  EKL-007-Erwartungen unverändert gültig.
- **`headline-color` & Co. am `list-item` kennen keine Dark-Mode-Hälfte**:
  `getColor()` (Kotlin) liest genau einen Schlüssel, ein `dark_headline_color`
  gibt es nicht, und `resolveColorValue()` versteht nur Palette/Hex, keine
  Theme-Token. Der Ausweg ist der Helfer **`theme('on-surface-variant')`**:
  er löst den Wert schon beim Rendern nach `System::isDarkMode()` auf
  (Fallback „light“, wenn keine Bridge da ist) und liest aus derselben
  `config/native-ui.php`, die auch die Renderer benutzen.
- **`disabled` am `list-item` dämpft nicht nur, es sperrt den Tap**
  (`clickable(enabled = !disabled)`) — für „abgehakt, aber weiter antippbar“
  also unbrauchbar.
- **`Http::fake([...])` ist keine Wand gegen echte Anfragen:** was kein Muster
  trifft, geht wirklich ins Netz. Wer eine zweite Route benutzt, muss sie
  mit faken — in `mitMealie()` steht die Artikel-Route deshalb *vor*
  `mealieAntwortet()` (überschneidungsfreie Muster, Reihenfolge egal, aber
  die Regel „das erste passende gewinnt“ bleibt).
- **Mealie nimmt beim Artikel-Update die ganze GET-Darstellung entgegen**
  (Pydantic ignoriert, was das Update-Schema nicht kennt). Deshalb hält
  `Eintrag::$roh` die Originalnutzlast — „restliche Felder unverändert“ ist
  damit wörtlich erfüllt, ohne dass die App Mealies Schema nachbauen muss.
  Für EKL-009 heißt das: der Cache muss `roh` mitspeichern.
- **Optimistische Änderungen brauchen zwei Wege zurück:** `finished()` mit
  `ok: false` *und* `failed()`. Die Fachklasse fängt `ConnectionException`
  selbst ab und meldet `ok: false`, `failed()` bleibt für alles, was den
  Async-Lauf selbst zerlegt.
- **Der Leerzustand und der Abschnitt „Abgehakt“ schließen sich nicht aus.**
  Wer alles abgehakt hat, sieht „Liste ist leer.“ *und* darunter den
  Abschnitt — sonst käme er an die abgehakten Artikel nie wieder heran.
  Der Leerzustand gibt sein `flex-1` dafür ab, wenn der Abschnitt da ist.
- Für EKL-010: das Bulk-Update geht an `PUT /api/households/shopping/items`
  mit einem Array — `Artikelstatus::setzen()` ist die Vorlage, die
  Rücknahme-Logik (`mealieAenderungZuruecknehmen`) lässt sich pro Artikel
  wiederverwenden.
<!-- chief-timing story="EKL-008" duration_ms=562585 cost=19.720382 in=174 out=688 cache_create=279820 cache_read=9613031 -->

## 2026-09-20 - EKL-009

Die Mealie-Liste liegt jetzt in SQLite. Jede erfolgreich geladene Liste
ersetzt den Datensatz in `mealie_cache` (ein Schlüssel `einkaufsliste`,
Artikel als JSON, `geladen_am`), die `Sitzung` füllt sich beim ersten
Zugriff daraus und zeigt die Artikel deshalb sofort — auch beim ersten
Tab-Öffnen nach einem App-Neustart, während die neue Antwort noch
unterwegs ist. Ein Tap auf eine Mealie-Zeile schreibt den umgelegten
Haken mit in den Cache, lässt den `Stand` aber stehen: der meint den
letzten *Ladevorgang*.

Scheitert das Laden, merkt sich die Sitzung den Grund (`App\Mealie\Fehler`:
Netz / Http / Token) und der Screen zeichnet unter der Top-Bar ein Banner
(`App\Mealie\Fehlerzustand` liefert Text und Knopfbeschriftung):
„Mealie nicht erreichbar · Stand HH:MM", bei einem Stand von einem anderen
Tag „· Stand DD.MM. HH:MM", ohne Cache ohne den Zusatz; bei HTTP 401
„Mealie-Token ungültig" mit „Einstellungen" statt „Erneut versuchen".
Solange das Banner steht, tragen alle Mealie-Zeilen `disabled` und
`mealieUmschalten()` antwortet nur noch mit dem Toast „Offline:
Mealie-Artikel können gerade nicht geändert werden". Ein gelungenes
Neuladen (Pull-to-Refresh, Banner-Knopf, Rückkehr in den Vordergrund)
räumt Fehler und Sperre ab. Das Löschen des Tokens — in den Einstellungen
und, als zweiter Riegel, beim nächsten Blick des Einkaufen-Screens auf
einen leeren Keystore — wirft Liste und Cache weg.

**Dateien**

- `database/migrations/2026_09_20_114535_create_mealie_cache_table.php`,
  `app/Models/MealieCache.php` — neu
- `app/Mealie/Cache.php`, `app/Mealie/Fehler.php`,
  `app/Mealie/Fehlerzustand.php` — neu
- `app/Mealie/Sitzung.php` — cache-gestützt, `fehlerMelden()`,
  `fehlerzustand()`, `vergessen()`
- `app/Mealie/Eintrag.php` — `daten()` als Rückweg in die flache Form
- `app/NativeComponents/Einkaufen.php` — `banner()`, Fehler aus `finished`
  und `failed`, Sperre in `mealieUmschalten()`
- `app/NativeComponents/Einstellungen.php` — löscht mit dem Token den Cache
- `resources/views/native/einkaufen.blade.php` — Banner, `:disabled`
- `config/app.php` — `timezone` auf `Europe/Berlin`
- `tests/Feature/EinkaufenMealieTest.php` — 11 neue Tests,
  `tests/Feature/EinstellungenTest.php` — 1 neuer Test

138 Tests, 917 Assertions, grün. Pint sauber. `native:validate` rot mit
denselben 11 „Unknown native element type"-Meldungen wie nach EKL-008,
keine andere Fehlerart.

**Offener Widerspruch in der Akzeptanz (EKL-009, Kriterium 5)**

`disabled` am `list-item` und „ein Tap zeigt den Toast" schließen sich auf
dem Gerät aus: `ListItemRenderer.kt` baut den Klick als
`clickable(enabled = !disabled)`, ein Tap auf eine gesperrte Zeile erzeugt
also gar kein Event. Umgesetzt ist der Buchstabe der Akzeptanz — `disabled`
steht am Knoten (das graut die Checkbox aus, den Text lässt Material in
Ruhe), der Toast-Handler hängt weiter am `@press` und ist getestet. Auf
Android wird er so aber nie erscheinen. Wer den Toast wirklich will, muss
`:disabled` fallen lassen und die Zeile stattdessen von Hand dämpfen
(`headlineColor` über `theme('on-surface-variant')`, wie im Abschnitt
„Abgehakt"); die Checkbox bliebe dann farbig. Das ist eine
Produktentscheidung, keine technische.

**Learnings für die nächsten Iterationen**

- **`Http::fake()` ist nicht umschaltbar.** Ein zweites `Http::fake()` legt
  seine Regel nur *hinter* die erste, und in `PendingRequest::send` gewinnt
  die erste passende. Wer im selben Test erst eine Antwort und dann einen
  Ausfall braucht, schreibt **eine** Closure mit einem Schalter darin —
  `mealieAntwortetDannNicht()` gibt ihn zurück (`$ausfall()` / `$ausfall(false)`).
- **App-Neustart im Test ist `app()->forgetInstance(Sitzung::class)`**
  (Helfer `appNeuStarten()`): die Singletons fallen weg, SQLite bleibt.
  Genau die Grenze, an der sich zeigt, ob etwas wirklich persistiert.
- **„Zeigt der Screen das schon, bevor die Antwort da ist?"** lässt sich mit
  `AsyncTask::fake()` (inline!) nur *innerhalb* der `Http::fake()`-Closure
  beantworten: dort steht der Ladevorgang, und `app(Uebersicht::class)
  ->abschnitte()` sagt, was der Screen in diesem Moment zeichnen würde.
- **`config('app.timezone')` steht jetzt auf `Europe/Berlin`** — die App
  zeigt Uhrzeiten („Stand HH:MM"), und mit UTC wären die im Sommer zwei
  Stunden daneben. Tests, die Zeiten prüfen, frieren mit
  `CarbonImmutable::setTestNow()` ein.
- **Der Cache ist bewusst breiter geschnitten als diese Story:** die Tabelle
  hat einen `schluessel` und `App\Mealie\Cache` benutzt davon nur
  `einkaufsliste`. EKL-012 braucht einen Eintrag je Wochenbereich — dafür
  reicht eine zweite Klasse neben `Cache` mit demselben Modell.
- **`Fehlerzustand` ist für EKL-012 mitgedacht**: Text und Knopfbeschriftung
  hängen nur an `Fehler` und einem `?CarbonImmutable`, nicht am
  Einkaufen-Screen. Das Wochenplan-Banner kann dieselbe Klasse benutzen.
- **Ein `native:`-Tag mit bedingtem Handler wird doppelt geschrieben**, nicht
  bedingt attribuiert: `@press` unterscheidet sich zwischen
  „Erneut versuchen" und „Einstellungen", und ein `@if` in der Attributliste
  zerlegt der Precompiler (bekanntes Muster, hier zum ersten Mal für zwei
  *Handler* statt zwei Werte gebraucht).
- Für EKL-010: `app(Sitzung::class)->fehlerzustand() !== null` ist die Frage
  „ist Mealie gerade im Fehlerzustand?" — genau das, was der Dialog
  „Alles abhaken?" braucht, um die Mealie-Zahl wegzulassen.
---
<!-- chief-timing story="EKL-009" duration_ms=636217 cost=17.515263 in=146 out=404 cache_create=274516 cache_read=8223732 -->

## 2026-09-20 - EKL-010

„Alles abhaken“ nimmt jetzt beide Seiten mit. Der Dialog setzt sich aus
zwei Sätzen zusammen, jeder nur, wenn seine Zahl größer 0 ist: „n eigene
Artikel wandern zurück in den Vorrat.“ und „m Mealie-Artikel werden
abgehakt.“, beide mit Singularform. Nach „Abhaken“ leert sich die eigene
Liste, alle offenen Mealie-Artikel springen sofort in den Abschnitt
„Abgehakt“ und gehen in **einem** Aufruf an Mealie —
`PUT /api/households/shopping/items` mit einem Array aller Artikel in
Mealies eigener Darstellung, `checked: true`. Lehnt Mealie ab oder läuft
der Aufruf in einen Timeout, kehren nur die Mealie-Artikel in ihre
Gruppen zurück (die eigenen bleiben entfernt — die gingen Mealie nie
etwas an) und ein Toast „Mealie: Abhaken fehlgeschlagen“ erscheint.
Steht das Banner aus EKL-009 oder fehlt das Token, zählt der Dialog nur
die eigenen Artikel und rührt Mealie nicht an. Die Action erscheint
jetzt bei `Uebersicht::anzahl() > 0`, also auch, wenn nur ein
Mealie-Artikel offen ist.

**Dateien**

- `app/Mealie/Artikelstatus.php` — `alleSetzen()` (Bulk-PUT ohne Artikel-ID
  in der URL, Array als Body)
- `app/Mealie/Sitzung.php` — `hakenMehrere(array $ids, bool $abgehakt)`,
  ein Cache-Schreibvorgang statt einem je Artikel
- `app/NativeComponents/Einkaufen.php` — `alleAbhakenFrage()`,
  `mealieZumAbhaken()`, `mealieAlleAbhaken()`,
  `mealieAbhakenZuruecknehmen()`; `navigationOptions()` fragt die
  Übersicht statt der eigenen Liste
- `tests/Feature/EinkaufenMealieTest.php` — 11 neue Tests, `mitMealie()`
  fakt zusätzlich die Bulk-Route
- `tests/Feature/EinkaufenTest.php` — zwei Dialogtexte auf die neue
  Formulierung gezogen

149 Tests, 983 Assertions, grün. Pint sauber. `native:validate` rot mit
denselben 11 „Unknown native element type“-Meldungen wie zuvor, keine
andere Fehlerart.

**Learnings für die nächsten Iterationen**

- **`Http::fake()`-Muster mit und ohne Schrägstrich sind zwei Muster:**
  `*/api/households/shopping/items/*` trifft die Bulk-Route
  `…/items` **nicht** (das `*` am Ende darf leer sein, der `/` davor nicht
  fehlen). Was nicht gefakt ist, geht wirklich ins Netz — `mitMealie()`
  fakt deshalb beide Muster. Sie überschneiden sich nicht.
- **Der Rücknahmeweg braucht die IDs, nicht die Einträge.** Beim Bulk
  merkt sich der Screen `array_map(fn (Eintrag $e) => $e->id, …)` vor dem
  optimistischen Haken; der `finished`/`failed`-Handler hakt genau diese
  Liste wieder auf. Alles neu aus der Sitzung zu lesen wäre falsch — bis
  die Antwort da ist, kann der Nutzer weitergetippt haben.
- **Dialogtexte aus mehreren Sätzen** baut man als `$saetze[]`-Array und
  `implode(' ', …)`, nicht mit verschachtelten Ternaries: die Regel
  „jeder Satz nur, wenn seine Zahl > 0“ bleibt so lesbar und ist genau
  das, was die Akzeptanz beschreibt.
- **„Ist Mealie ansprechbar?“ ist eine Frage, zwei Bedingungen:**
  `banner() !== null || mealieToken() === null`. Sie steckt in
  `mealieZumAbhaken()` und gibt bei Nein ein leeres Array zurück — Dialog
  und Ausführung fragen dieselbe Methode, deshalb können Text und Tat
  nicht auseinanderlaufen.
- Für EKL-011/012: `Uebersicht::anzahl()` ist die Zahl, an der die
  Sichtbarkeit von Screen-Actions hängt (eigene + offene Mealie),
  `EigeneListe::anzahl()` nur noch die halbe Wahrheit.
---
<!-- chief-timing story="EKL-010" duration_ms=232285 cost=7.312694 in=72 out=266 cache_create=173663 cache_read=2690322 -->

## 2026-09-20 - EKL-011

Der dritte Tab zeigt den Mealie-Wochenplan. Oben eine Wochen-Navigation aus
drei `native:pressable` — Pfeil links („Vorherige Woche“), der Wochen-Text
„KW 39 · 21.09.–27.09.“ (Tap springt zur aktuellen Woche) und Pfeil rechts
(„Nächste Woche“). Beim Öffnen des Tabs steht die Kalenderwoche des heutigen
Tages. Geladen wird beim Mounten, beim Wochenwechsel, beim Zurückkehren in
den Vordergrund (`AppForegrounded`) und per Pull-to-Refresh, jeweils
`GET /api/households/mealplans?start_date=<Montag>&end_date=<Sonntag>&perPage=100`
über `async`. Eine Woche, von der die App noch nichts weiß, zeigt währenddessen
einen zentrierten `native:activity-indicator`; eine schon geladene bleibt stehen.

Darunter alle sieben Tage mit Überschrift „Montag, 21.09.“, der heutige in der
Primärfarbe und mit dem Zusatz „· Heute“. Jeder Eintrag ist eine
`native:list-item` mit deutschem Mahlzeitentyp als Overline, Rezeptname als
Headline und dem Rezeptbild als Leading-Image (ohne Bild das Besteck-Icon);
Einträge ohne Rezept zeigen `title` und `text` und reagieren nicht auf Tap.
Sortiert wird je Tag nach Frühstück, Mittag, Abend, Beilage, Snack, Getränk,
Dessert; ein leerer Tag bekommt eine gedämpfte Zeile „Nichts geplant“. Ein Tap
auf eine Rezept-Zeile öffnet `…/g/home/r/<slug>` im System-Browser. Ohne Token
steht statt der Tage ein Leerzustand mit Kalender-Icon, „Mealie nicht
verbunden“ und dem Knopf „Zu den Einstellungen“.

**Dateien**

- `app/Wochenplan/{Woche,Mahlzeitentyp,Eintrag,Tag,Uebersicht,Plan,Sitzung}.php` — neu
- `app/NativeComponents/Wochenplan.php`, `resources/views/native/wochenplan.blade.php`
- `app/Providers/AppServiceProvider.php` — `Wochenplan\Sitzung` als Singleton
- `app/Providers/NativeServiceProvider.php` — `BrowserServiceProvider` in `plugins()`
- `composer.json` / `composer.lock` — `nativephp/mobile-browser` ^1.0 (im PRD-Stack vorgesehen)
- `tests/Feature/WochenplanTest.php` — 22 Tests

171 Tests, 1122 Assertions, grün. Pint sauber. `native:validate` rot mit
denselben „Unknown native element type“-Meldungen wie zuvor, dazu dieselbe
Fehlerart für `pressable` und die vier neuen `list-item` — keine andere Art.

**Learnings für die nächsten Iterationen**

- **`native:validate` kennt auch `pressable` nicht**, obwohl das ein
  Kern-Container ist (`NativeElementCollector::$builtinTypes`, vom Android-
  `NodeView` gerendert). Der Analyzer-Katalog ist schlicht unvollständig; die
  Regel „nur prüfen, dass keine *andere* Fehlerart dazukommt“ gilt weiter.
- **Der Spinner lässt sich nicht über den Netzweg beobachten**, weil
  `AsyncTask::fake()` inline läuft und der Baum erst danach publiziert wird.
  Geprüft wird er deshalb über die öffentliche Eigenschaft:
  `$screen->set('laedt', true)` rendert neu, und `set('montag', '2026-10-05')`
  wechselt auf eine Woche ohne Daten — genau die Kombination, die ihn zeigt.
- **Der heutige Tag braucht eine eigene Farbe, ein `list-section` kann das
  nicht.** Die Tagesüberschriften hängen darum als lose `native:text` in der
  Liste. Der Renderer mischt das anstandslos; nebenbei entfällt dabei Androids
  automatisches `uppercase` auf Abschnitts-Headern, das „Montag, 21.09.“
  zerschrieben hätte.
- **Die Woche ist ein Wertobjekt, der Screen hält nur ihren Montag als String**
  (`public string $montag`) — der Zustand geht über die Wire-Grenze, und ein
  `CarbonImmutable` täte das nicht. `Woche::ausSchluessel()` baut ihn zurück.
- `startOfWeek()` bekommt `CarbonInterface::MONDAY` ausdrücklich mit: Mealie
  bekommt `start_date`/`end_date`, und eine locale-abhängige Woche holte die
  falschen sieben Tage.
- **Für EKL-012 liegt schon bereit:** `App\Wochenplan\Sitzung::fehlerMelden()`
  /`fehler()` merkt sich den `App\Mealie\Fehler` des letzten Ladevorgangs,
  und `App\Mealie\Fehlerzustand` liefert Text und Knopfbeschriftung ohne
  Bezug zum Einkaufen-Screen. Was fehlt, ist der Cache je Woche (eine zweite
  Klasse neben `App\Mealie\Cache` auf demselben `MealieCache`-Modell) und
  das Banner im Blade.
- **Testhelfer in `tests/Feature/WochenplanTest.php`:** `mitWochenplan()`
  (Token + gefaktes Mealie + `AsyncTask::fake()`), `mealplanEintrag()` in
  Mealies echter Form, `wochenplanInhalt()` (Überschriften und Zeilen in
  Render-Reihenfolge), `wochenplanZeile($screen, $headline)` und
  `tagesUeberschriften()`. Zeilen werden über ihre Headline gesucht, nicht
  über `ref` — Mealies IDs sind im Test Rauschen.
---
<!-- chief-timing story="EKL-011" duration_ms=734571 cost=30.661304 in=230 out=790 cache_create=460215 cache_read=14646382 -->
