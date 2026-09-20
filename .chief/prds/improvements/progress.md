# Fortschritt — PRD „improvements“

## Codebase Patterns
- **Bildquellen liegen als SVG in `resources/grafik/`, gerendert wird per
  Imagick über `php artisan grafik`.** Der SVG-Renderer von Imagick kann nur
  `fill`, `rect rx`, Pfade (`M/L/A/Q/Z`) und `transform` an einem `<g>` mit
  `translate`/`scale` — kein `stroke`, kein `<text>` (es sind null Schriften
  registriert), kein `transform` an einem `<rect>`, kein `rotate` um einen
  Mittelpunkt. Runde Enden also über Halbkreis-Bögen, Schrift als Pfad.

- **Sprache durchgängig Deutsch.** Klassen-, Methoden- und Variablennamen,
  Kommentare, Commit-Messages, Testnamen — alles auf Deutsch. Fachbegriffe
  (`Cache`, `Token`, `Bridge`) bleiben, wie sie sind.
- **Schichtung.** `app/<Fachbereich>/` hält die Domänenklassen (`App\Mealie`,
  `App\Wochenplan`, `App\Liste`, `App\Erscheinungsbild`), `app/Models/` nur
  die Eloquent-Models, `app/NativeComponents/` die Screens,
  `resources/views/native/*.blade.php` deren Views. Keine neuen Basisordner
  ohne Not.
- **Persistenz-Muster.** Tabellen mit `string('schluessel')->primary()` plus
  Nutzlast plus `timestamps()` — so machen es `mealie_cache` und
  `einstellungen`. Davor liegt eine schlanke Domänenklasse
  (`Mealie\Cache`, `Erscheinungsbild\Auswahl`), die das Model kapselt; Screens
  fassen Models nie direkt an.
- **Geheimes in den Secure Storage, alles andere in SQLite.** Das Mealie-Token
  liegt im Keystore (`App\Mealie\Token`), Vorlieben und Caches in der lokalen
  Datenbank.
- **Test-Seam ist immer `Native::visit('/route')`.** Geprüft wird der
  Wire-Tree, den das Gerät bekäme, nicht der interne Zustand. Helfer dafür
  stehen in `tests/Pest.php`: `knotenMitRef()`, `farbPaare()`,
  `listenAbschnitte()`, `tabLabels()`, `sichtbarerText()`,
  `fakeSecureStore()`, `jsonFixture()`. Vor einem neuen Walk erst dort
  nachsehen.
- **Tests laufen ohne die lokale `.env`.** `$_SERVER`-Variablen schlagen
  `phpunit.xml`, deshalb die Suite mit
  `env -u MEALIE_URL -u MEALIE_SHOPPING_LIST_ID php artisan test` starten —
  sonst zeigen die Tests auf die echte Mealie-Instanz.
- **Native Fähigkeiten kommen aus lokalen Plugins unter `plugins/`.** Muster:
  `nativephp.json` (Bridge-Funktionen, Init-Funktion), `src/` mit
  Service Provider plus Bridge-Klasse und Facade, `resources/android/*.kt`
  und `resources/ios/*.swift`. Einhängen über `composer require … @dev` und
  `NativeServiceProvider::plugins()` — ohne den zweiten Schritt wird nichts
  in den nativen Build kompiliert. Bestehende Plugins tragen englische
  Bezeichner, der Rest der App deutsche.
- **Alle Screens erben von `App\NativeComponents\Screen`.** Dort hängt, was
  jeden Screen angeht (heute: das Erscheinungsbild anwenden). Wer beim
  Zurückkommen aus dem Hintergrund etwas nachladen will, überschreibt
  `wiederImVordergrund()`, nicht den `#[On]`-Handler.
- **Farben kommen aus dem Theme, nie fest in die View.** `text-theme-primary`
  & Co. lesen bei jedem Render aus `Native\Mobile\UI\Theme`; wer sie zur
  Laufzeit ändern will, ruft `Theme::merge()` — der Merge überlebt den
  Prozess nicht, gehört also in den Konstruktor von `Screen`. Was nativ
  gezeichnet wird (Systemdialoge, Date-Picker, Splash) hängt dagegen an
  `config/nativephp.php` und folgt dem Theme nicht.
- **Was nativ gefärbt wird, steht nicht im Wire-Tree.** Tab-Leiste, Buttons
  und Checkboxen tragen dort keine Farbe — der Seam dafür ist
  `Theme::get('light.primary')` nach einem `Native::visit()`.
- **Was `native:install` ins Projekt generiert, veraltet still.** Die
  Android-Theme-Dateien, das Icon, der Splash und die App-ID entstehen aus
  `config/nativephp.php` und landen im gitignorierten `nativephp/`-Ordner;
  Gradle liest danach nur noch diesen Ordner. Wer eine solche Config ändert,
  muss den Release-Ablauf nachziehen lassen (`App\Release\AndroidTheme`) —
  ein erneutes `native:install` taugt im Build nicht, es fragt interaktiv nach
  der Bundle-ID und löscht ohne `--no-force` das ganze Verzeichnis.

- **Tailwind-Maße stehen in `node['layout']`, Farben in `node['style']`.**
  `h-14` → `layout.height === 56.0` (float!), Skala in
  `TailwindParser::SPACING`. Ein Knoten ohne Farbklasse hat gar keinen
  `style`-Key — das ist das Mittel für „unsichtbar in Hell und Dunkel“.
  `native:column` darf direktes Kind einer `native:list` sein und ist hier
  das Muster für Abstandhalter am Listenende (`ref="listenende"`).
- **Caches mit Wegwerf-Logik räumen an der Sitzung auf, nicht am Cache.**
  `Cache::speichern()` bleibt dumm, `Sitzung::setzen()` ruft danach
  `aufraeumen()`. Nur so lässt sich im Test ein gewachsener Altbestand
  herstellen, der dann aufgeräumt wird.
- **Zustand über einen Handler-Aufruf hinaus braucht `singleton()`** in
  `AppServiceProvider::register()`. `app(X::class)` liefert sonst jedes Mal
  eine frische Instanz, und das Geschriebene ist beim nächsten Render weg.
- **Screen-Lebenszyklus im Test**: Ein Tab-Wechsel mountet den Screen neu
  (`mount()` läuft), ein Ausflug zu den Einstellungen nicht — der Rückweg
  feuert nur `onResume()`. Der Harness-Weg dafür ist
  `press(...)->followNavigation()->goBack()`; nach einem `navigate()` blockt
  er jede weitere Interaktion auf dem alten Screen.
- **`press()` sieht nur Handler des letzten Renders.** Wer eine Interaktion
  prüfen will, muss erst den Zustand herstellen, in dem ihr Element im Baum
  steht.
- **`vendor/bin/pint --dirty`** vor jedem Commit.

---

## 2026-09-20 - FEIN-001 - Hell/Dunkel-Modus wählen und merken

Der Einstellungen-Screen hat oberhalb des Mealie-Abschnitts einen neuen
Abschnitt „Erscheinungsbild“ mit einem Segmented Control
(`<native:button-group>`) über „System“, „Hell“, „Dunkel“. Der Tipp auf eine
Option gilt sofort, die Wahl landet in der neuen SQLite-Tabelle
`einstellungen` und übersteht den App-Neustart.

**Geänderte Dateien**

- `database/migrations/2026_09_20_163931_create_einstellungen_table.php` (neu)
- `app/Models/Einstellung.php` (neu)
- `app/Erscheinungsbild/Modus.php` (neu) — Enum mit Beschriftung, Position, Fallbacks
- `app/Erscheinungsbild/Auswahl.php` (neu) — Lesen/Schreiben der Vorliebe
- `app/NativeComponents/Einstellungen.php` — Prop `$erscheinungsbild` (Position), Handler `erscheinungsbildGewaehlt()`
- `resources/views/native/einstellungen.blade.php` — neuer Abschnitt ganz oben
- `tests/Pest.php` — Helfer `sichtbarerText()`
- `tests/Feature/ErscheinungsbildTest.php` (neu) — 10 Tests

**Learnings for future iterations:**

- **Segmented Control = `<native:button-group>`** aus `nativephp/mobile-ui`.
  Props: `:options` (Liste von Strings), `:value` (int-Index),
  `@change="handler"` (Handler bekommt `int $position`), `a11y-label`.
  Im Test heißt die Interaktion `->changeTab($ref, $index)` —
  `EVENT_TAB_CHANGE`, nicht `select()`.
- **`ButtonGroup::getStyle()` gibt bewusst `[]` zurück** („Model 3“: Farben
  kommen aus den Theme-Tokens, nicht per Instanz). Darum taucht der Knoten in
  `farbPaare()` gar nicht auf und der Theme-Test bleibt automatisch grün.
  Umgekehrt heißt das: `bg-…`/`text-…`-Klassen am Element haben keine Wirkung.
- **Selected/Not-Selected für den Screenreader kommt vom Renderer**, nicht aus
  PHP: Android `SegmentedButton(selected = …)`, iOS
  `.accessibilityAddTraits([.isButton, .isSelected])`. Für AC-Punkte dieser Art
  reicht also das `a11y-label` an der Gruppe.
- **Es gibt keinen Appearance-Override.** Weder Bridge-Call noch Facade: die
  nativen Renderer lesen das Farbschema direkt vom System
  (`isSystemInDarkTheme()` / `colorScheme`) und suchen sich danach
  `NativeUITheme.light` oder `.dark`. Die gewählte Option ändert deshalb noch
  nichts am Bild.
  Der einzige verfügbare Hebel wäre `Native\Mobile\UI\Theme::merge()`: schreibt
  man die dunklen Tokens *auch* in den `light`-Block, zeichnet das Gerät in
  beiden Systemzuständen dunkel. Zwei Fallstricke dabei:
  1. `Theme::$tokens` ist statisch und überlebt den Test; `Theme::load()` läuft
     zwar bei jedem App-Boot aus der Config, wer aber außerhalb davon merged,
     muss selbst aufräumen.
  2. Ein Provider-Hook, der die Vorliebe beim Boot aus SQLite liest, kollidiert
     mit `RefreshDatabase` — beim ersten Test existiert die Tabelle zur
     Boot-Zeit noch nicht.
  Das gehört in eine eigene Story, nicht als Anhängsel hier hinein.
- **`sichtbarerText($screen)`** ist der neue Helfer für „X steht über Y“ —
  sammelt `content`/`text`/`label`/`header`/`headline` in Render-Reihenfolge.
  `assertSee()` kann das nicht.
- Der komplette Screen-Text ist ein gutes Regressionsnetz: der Test „lässt den
  Mealie-Abschnitt unangetastet“ vergleicht die ganze Liste gegen ein Literal
  und schlägt an, sobald jemand am Abschnitt darunter etwas verschiebt.

---
<!-- chief-timing story="FEIN-001" duration_ms=396382 cost=19.054897 in=192 out=794 cache_create=344797 cache_read=8351682 -->

## 2026-09-20 - FEIN-002 - Gewählter Modus wirkt auf die ganze App

Die Wahl aus FEIN-001 wird jetzt auch wirksam. Dafür gibt es ein drittes
lokales Plugin, `ben182/appearance`, mit einer Bridge-Funktion
`Appearance.Set` (`mode`: `system` | `light` | `dark`). Angewandt wird sie an
drei Stellen: beim Tippen auf eine Option, beim Entstehen jedes Screens
(also beim App-Start und bei jeder Navigation) und bei der Rückkehr der App
in den Vordergrund.

**Geänderte Dateien**

- `plugins/appearance/` (neu) — `composer.json`, `nativephp.json`,
  `src/Appearance.php`, `src/AppearanceStyle.php`, `src/Facades/Appearance.php`,
  `src/AppearanceServiceProvider.php`,
  `resources/android/AppearanceFunctions.kt`,
  `resources/ios/AppearanceFunctions.swift`
- `composer.json` / `composer.lock` — `ben182/appearance: @dev`
- `app/Providers/NativeServiceProvider.php` — Plugin in die Allow-List
- `app/Erscheinungsbild/Modus.php` — `stil(): AppearanceStyle`
- `app/Erscheinungsbild/Auswahl.php` — `anwenden()`, von `waehlen()` mitgerufen
- `app/NativeComponents/Screen.php` (neu) — gemeinsamer Unterbau aller Screens
- `app/NativeComponents/{Einkaufen,Vorrat,Wochenplan,Einstellungen}.php` —
  erben von `Screen`; die beiden `#[On(AppForegrounded)]`-Handler heißen
  jetzt `wiederImVordergrund()`
- `tests/Feature/ErscheinungsbildTest.php` — 10 weitere Tests (jetzt 20)

**Auf dem Emulator verifiziert** (Pixel_7_API_35): Screens, Tab-Leiste,
Status-/Navigationsleiste und der native „Token löschen?"-Dialog folgen der
Wahl gegen das Systemthema; „System" gibt die App zurück und übernimmt einen
Live-Wechsel des Systemthemas; nach `force-stop` startet sie im gewählten
Modus.

**Learnings for future iterations:**

- **Der Hebel auf Android ist `UiModeManager.setApplicationNightMode()`**
  (API 31, App-minSdk ist 33), nicht `AppCompatDelegate`: die MainActivity ist
  eine `FragmentActivity` ohne AppCompat. `MODE_NIGHT_AUTO` heißt dort nicht
  „nach Uhrzeit", sondern räumt die Überschreibung ab — das ist „System".
  Weil die Activity `configChanges="uiMode"` trägt, wird sie nicht neu
  erzeugt: Compose liest `isSystemInDarkTheme()` neu und NativePHPs
  `configureStatusBar()` färbt die Systemleisten um. Beides ohne eine Zeile
  PHP.
- **`Native\Mobile\UI\Theme::merge()` war der falsche Weg** (so stand es noch
  in FEIN-001): Tokens färben nur, was die App selbst zeichnet — native
  Dialoge und Systemleisten bleiben davon unberührt.
- **Ein Screen kann pro Ereignis nur einen `#[On]`-Handler haben.**
  `$nativeEventListeners` ist eine Map Ereignis → *ein* Methodenname, und eine
  überschreibende Kindmethode verdrängt die der Basisklasse. Deshalb ist
  `Screen::appImVordergrund()` `final` und ruft den Hook
  `wiederImVordergrund()`; wer den Handler selbst überschreibt, verliert
  lautlos, was die Basisklasse dort tut.
- **`mountComponent()` ist `final`** — ein „läuft beim Öffnen jedes Screens"-
  Hook geht nur über den Konstruktor der Basisklasse. Der greift auch im
  Test: `TestableComponent` aktiviert die FakeBridge, *bevor* es die
  Komponente baut, `Native::visit()` sieht den Aufruf also.
- **`withoutCapability('X.Y')` an der FakeBridge** ist der Seam für „das
  Plugin gibt es auf dieser Plattform nicht": `nativephp_can()` sagt dann
  nein, und der Test prüft, dass die App das aushält.
- **Ein neues Plugin braucht vier Handgriffe**: Verzeichnis unter `plugins/`,
  `composer require ben182/<name> @dev` (Path-Repository `plugins/*` steht
  schon), Eintrag in `NativeServiceProvider::plugins()` — sonst landet es
  nicht im nativen Build — und `php artisan package:discover`. Kontrolle:
  `nativephp/android/app/src/nativephp/kotlin/.../PluginBridgeFunctionRegistration.kt`
  nach dem Build.
- **Geräte-Verifikation per adb ist billig**: `adb shell cmd uimode night
  yes|no` schaltet das Systemthema, `adb shell input tap X Y` bedient die App,
  `adb exec-out screencap -p > /tmp/x.png` liefert das Bild zum Ansehen. Die
  Koordinaten aus einem Screenshot müssen mit dem Skalierungsfaktor
  zurückgerechnet werden.
---
<!-- chief-timing story="FEIN-002" duration_ms=947178 cost=37.500810 in=320 out=1395 cache_create=538906 cache_read=18191265 -->

## 2026-09-20 - FEIN-003 - Akzentfarbe wählen

Unter dem Modus-Umschalter steht jetzt eine Reihe aus sechs farbigen
Kreisen. Ein Tipp schreibt die Wahl in die Zeile `akzentfarbe` der
`einstellungen`-Tabelle und legt sie als Theme-Tokens (`primary`,
`on-primary`, je hell und dunkel) über die Konfiguration — damit färbt
sich alles um, was `text-theme-primary` / `bg-theme-primary` trägt oder
die Primärfarbe aus dem nativen Theme-Store liest: Tab-Leiste, gefüllte
Buttons, Checkboxen, Banner-Knöpfe, das Datum des heutigen Tags im
Wochenplan.

**Geänderte Dateien**

- `app/Erscheinungsbild/Akzentfarbe.php` (neu) — Enum mit Beschriftung,
  Hell-/Dunkel-Primärfarbe, passender Schriftfarbe, `tokens()`, `a11yLabel()`
- `app/Erscheinungsbild/Farbwahl.php` (neu) — Lesen/Schreiben der Vorliebe,
  `anwenden()` über `Theme::merge()`
- `app/NativeComponents/Screen.php` — wendet die Farbe bei jedem
  entstehenden Screen mit an
- `app/NativeComponents/Einstellungen.php` — Prop `$akzentfarbe`, Handler
  `akzentfarbeGewaehlt()`, `akzentfarben()`
- `resources/views/native/einstellungen.blade.php` — Kreis-Reihe aus
  `native:pressable`, Häkchen im aktiven Kreis
- `config/native-ui.php` — `dark.on-primary` von `#FFFFFF` auf `#0F172A`
- `tests/Pest.php` — Helfer `knotenMitRefPraefix()`
- `tests/Feature/ErscheinungsbildTest.php` — 10 weitere Tests (jetzt 30)
- `tests/Feature/ThemeTest.php` — Kontrast- und Dunkel-Entsprechungs-Test
  je Preset (jetzt 17 Fälle)
- `tests/Feature/WochenplanTest.php` — das heutige Datum folgt der Wahl

**Learnings for future iterations:**

- **Der Hebel für Farben ist `Native\Mobile\UI\Theme::merge()`** — genau
  das, was in FEIN-002 für Hell/Dunkel der falsche Weg war. Tokens färben,
  was die App selbst zeichnet; Android-Systemdialoge und der Date-Picker
  hängen an `config/nativephp.php` (`color_primary` / `color_primary_night`)
  und bleiben deshalb Indigo. Für FEIN-003 ist das so gewollt.
- **`Theme::merge()` wirkt sofort in beide Richtungen**: Der
  Theme-Resolver in `TailwindParser` liest bei jedem Render frisch aus
  `Theme::get()`, und `syncConfig()` spiegelt die Tokens nach
  `config('native-ui.theme.*')`, wo der `theme()`-Helfer nachsieht. Nativ
  ist `NativeUITheme` ein `mutableStateOf`-Store, Compose zeichnet also
  neu, ohne dass ein Screen neu gebaut werden muss.
- **Der Merge überlebt den Prozess nicht** — `Theme::load()` setzt beim
  Provider-Boot wieder die Konfiguration. Deshalb steht `anwenden()` im
  Konstruktor von `Screen`, an derselben Stelle wie der Hell/Dunkel-Modus.
  Im Test ist das ein Segen: jeder Test bootet frisch, der statische
  Token-Stand leckt nicht.
- **Tab-Leiste, Buttons und Checkboxen tragen im Wire-Tree keine Farbe** —
  die holen sie sich nativ aus dem Theme-Store. Der prüfbare Seam für „alles
  umgefärbt“ ist deshalb `Theme::get('light.primary')` nach dem Besuch eines
  Screens, nicht der Baum. Nur was die App selbst einfärbt (das heutige
  Datum im Wochenplan) steht als `props.color` drin.
- **`Theme::pushToNative()` schweigt unter `runningUnitTests()`** — ein
  `assertNativeCalled('NativeUI.Theme.Set')` gibt es im Test also nicht.
- **Arbiträre Tailwind-Werte mit Dark-Variante funktionieren**:
  `bg-[#4F46E5] dark:bg-[#818CF8]` landet als `style.bg_color` +
  `props.dark_bg_color` und besteht damit den Theme-Test. Für Icons gehen
  `color` / `dark-color` als Attribute.
- **`native:pressable` ist der Knopf ohne Aussehen**: `w-10 h-10
  rounded-full items-center justify-center` plus `a11y-label` plus
  `@press` ergibt einen Farbkreis. Einen Auswahlzustand meldet er dem
  Screenreader nicht von sich aus — der muss ins Label („…, ausgewählt“).
- **`knotenMitRefPraefix()`** ist der neue Helfer für „diese Knöpfe in
  dieser Reihenfolge“; mit Präfix `''` liefert er die vollständige Ref-Liste
  eines Screens und damit ein Regressionsnetz wie `sichtbarerText()`.
- **Weiß reicht auf hellen Dunkelmodus-Tönen nicht** (Indigo-400 gegen Weiß:
  2,98:1). Alle sechs Presets tragen im Dunkelmodus `#0F172A`; die
  WCAG-Formel steht als `kontrast()` im `ThemeTest` und rechnet unabhängig
  von der Palette nach.
- **Nicht auf dem Emulator verifiziert** — an der nativen Hälfte hat sich
  nichts geändert, ein Rebuild wäre nur für den Augenschein gewesen.
<!-- chief-timing story="FEIN-003" duration_ms=545023 cost=23.716805 in=226 out=850 cache_create=331841 cache_read=11618431 -->

## 2026-09-20 - FEIN-004 - „Alles abhaken“ mit Rückgängig statt Nachfrage

Der Bestätigungsdialog ist weg. Ein Tipp auf die Action räumt die eigenen
Artikel zurück in den Vorrat und hakt alle offenen Mealie-Artikel in einem
Bulk-Update ab. Stattdessen steht unten — über dem Block „Abgehakt“, und wo
der fehlt, direkt über der Tab-Leiste — eine Leiste mit „N Artikel
abgehakt“, einem Ghost-Knopf „Rückgängig“ und einem Schließen-Kreuz. Sie
verschwindet ohne Timer bei der ersten Interaktion (Zeile, Checkbox,
Abgehakt-Kopf, Pull-to-Refresh), beim Tab-Wechsel, beim Öffnen der
Einstellungen, beim Zurückkommen aus dem Hintergrund und beim Tipp auf das
Kreuz.

**Geänderte Dateien**

- `app/Einkaufen/Abhakvorgang.php` (neu) — der zurücknehmbare Vorgang:
  eigene Artikel-IDs plus Mealies Darstellung der abgehakten Artikel,
  `text()`, `leer()`, `mealieVergessen()`
- `app/Einkaufen/Rueckgaengig.php` (neu) — hält höchstens einen Vorgang;
  ein leer gewordener zählt als keiner
- `app/Providers/AppServiceProvider.php` — `Rueckgaengig` als Singleton
- `app/NativeComponents/Einkaufen.php` — `alleAbhaken()` statt
  `alleAbhakenBestaetigen()`, `rueckgaengigMachen()`, `leisteSchliessen()`,
  `mealieZurueckholen()`, `leisteVerwerfen()` an allen Interaktionen
- `resources/views/native/einkaufen.blade.php` — die Leiste
- `tests/Pest.php` — `texteIn()`, `rueckgaengigLeiste()`, `farbPaare()`
  nimmt jetzt auch Randfarben mit
- `tests/Feature/EinkaufenTest.php`, `tests/Feature/EinkaufenMealieTest.php`
  — die Dialog-Tests umgeschrieben, Rücknahme und Abräumen ergänzt
- `tests/Feature/ThemeTest.php` — ein Fall, der die Leiste im Baum hat

**Learnings for future iterations:**

- **`app(X::class)` ist ohne `singleton()` jedes Mal eine neue Instanz** —
  eine Fachklasse, die Zustand über einen Handler-Aufruf hinaus halten soll,
  braucht den Eintrag in `AppServiceProvider::register()`. Ohne ihn
  verschwindet der Zustand lautlos zwischen „Handler schreibt“ und „View
  liest“, und der Test zeigt nur einen fehlenden Knoten.
- **„Verschwindet beim Tab-Wechsel“ ist geschenkt**: Der Einkaufen-Screen
  wird dabei neu gemountet — ein `verwerfen()` in `mount()` genügt. „Beim
  Öffnen der Einstellungen“ dagegen nicht: dort bleibt die Instanz auf dem
  Stapel stehen und bekommt beim Rückweg nur `onResume()`.
- **Der Test dafür ist `followNavigation()->goBack()`** — das ist im Harness
  genau der Geräteweg (Screen auf den Stapel, zurück mit `onResume()` und
  erhaltenem Zustand). Ein zweites `Native::visit('/')` wäre tautologisch,
  weil es ohnehin neu mountet.
- **`press()` findet nur Handler, die im letzten Render registriert sind.**
  `abgehakteUmklappen` gibt es nur mit abgehakten Mealie-Artikeln, `neuLaden`
  nur mit gefüllter Liste, die Top-Bar-Action nur, wenn etwas offen ist.
  Ein Dataset über „alle Interaktionen“ scheitert daran; die Fälle gehören
  in die Testdatei, die den passenden Zustand herstellt.
- **Nach `navigate()` blockt das Harness jede weitere Interaktion** mit
  „Cannot interact: the component navigated away“. Entweder
  `followNavigation()` oder vorher fertig prüfen.
- **`variant="ghost"`** ist der Textknopf ohne Fläche; seine Schrift holt
  sich das Gerät aus dem Theme (Primärfarbe). Im Wire-Tree steht deshalb
  `props.variant`, keine Farbe — derselbe Fall wie Tab-Leiste und Checkboxen.
- **`border-theme-*` funktioniert** und liefert `style.border_color` plus
  `props.dark_border_color`. `farbPaare()` nimmt das jetzt mit; wer einen
  festen Rand setzt, fällt damit im Theme-Test auf. `border-t` gibt es
  weiterhin nicht — seitenweise Ränder kennt der Parser nicht, dafür bleibt
  die Haarlinie (`h-px` + `bg-theme-outline-variant`) das Mittel.
- **Der Theme-Test sieht nur, was der Ausgangszustand rendert.** Ein
  Element, das erst nach einer Interaktion erscheint, braucht einen eigenen
  Fall mit genau dieser Interaktion — sonst ist das Kriterium „folgt dem
  bestehenden Theme-Test“ nur auf dem Papier erfüllt.
- **Rückrollen eines Bulk-Updates muss den Vorgang mitkorrigieren**: Nimmt
  Mealie das Abhaken nicht an, stehen die Artikel wieder offen da und gehören
  nicht mehr zu dem, was „Rückgängig“ zurückholt. Bleibt der Vorgang danach
  leer, darf gar keine Leiste mehr stehen — sonst steht dort „0 Artikel
  abgehakt“.
- **Nicht auf dem Emulator verifiziert** — an der nativen Hälfte hat sich
  nichts geändert.
---
<!-- chief-timing story="FEIN-004" duration_ms=580167 cost=22.985644 in=180 out=673 cache_create=401633 cache_read=10267900 -->

## 2026-09-20 - FEIN-005

**Was umgesetzt wurde**

Am Ende jeder scrollbaren Liste steht jetzt ein Abstandhalter von einer
Listenzeilenhöhe (56 dp), damit die letzte Zeile beim vollständigen Scrollen
frei über der Tab-Leiste steht statt an ihr zu kleben: im Vorrat unter der
letzten Warengruppe, im Wochenplan unter dem Sonntags-Block, im
Einkaufen-Tab unter der Liste — dort aber nur, solange kein Block „Abgehakt“
darunter sitzt. Mit dem Block bleibt es bei der schmalen Luft von 24 dp, die
die Naht zu ihm markiert. Der Abstandhalter trägt weder Fläche noch Rand noch
Inhalt; der Hintergrund der Liste steht durch, hell wie dunkel. Die
Leerzustände rendern gar keine Liste und damit auch keine Luft.

**Geänderte Dateien**

- `resources/views/native/vorrat.blade.php`,
  `resources/views/native/wochenplan.blade.php` — je ein
  `<native:column ref="listenende" class="w-full h-14" />` als letztes Kind
  der Liste
- `resources/views/native/einkaufen.blade.php` — der bestehende Abstandhalter
  bekommt ein `ref` und eine bedingte Höhe (`h-14` ohne, `h-6` mit dem Block
  „Abgehakt“)
- `tests/Pest.php` — `listenLuft()`: der letzte Knoten der ersten Liste im
  Baum, wenn er der Abstandhalter ist
- `tests/Feature/VorratTest.php`, `tests/Feature/WochenplanTest.php`,
  `tests/Feature/EinkaufenTest.php`, `tests/Feature/EinkaufenMealieTest.php`
  — je ein Fall für die Luft, die Leerzustands-Tests um „kein `listenende`“
  ergänzt; `wochenplanInhalt()` überspringt textlose Knoten
- `tests/Feature/ThemeTest.php` — ein Fall, der den Abstandhalter farblos und
  leer festnagelt

**Learnings for future iterations:**

- **Tailwind-Maße landen in `node['layout']`, nicht in `node['style']`.**
  `h-14` → `layout.height === 56.0` (float, nicht int — `toBe(56)` scheitert).
  Die Skala steht in `TailwindParser::SPACING`; `style` hält nur Farben,
  deshalb sieht `farbPaare()` einen reinen Abstandhalter gar nicht.
- **Ein Knoten ohne Farbklasse ist im Wire-Tree farblos** — kein `style`-Key,
  kein Eintrag in `farbPaare()`. Für „unsichtbar in Hell und Dunkel“ ist das
  das Mittel der Wahl: keine Farbe schlägt zwei gepflegte Farben.
- **`native:column` ist als direktes Kind einer `native:list` erlaubt**, auch
  neben `list-section`-Geschwistern — der Weg für einen Abstandhalter am
  Listenende. Es gäbe auch `native:spacer`, in dieser App ist die leere
  Column das etablierte Muster.
- **Ein neues Kind der Liste bricht Helfer, die alle Listenkinder aufzählen.**
  `wochenplanInhalt()` hängte den textlosen Abstandhalter als
  `['text' => null]` an und ließ den großen Wochen-Test auflaufen. Wer so
  einen Helfer hat, filtert Knoten ohne Inhalt heraus, statt die Erwartung im
  Test um einen `null`-Eintrag zu erweitern.
- **Leerzustände prüft man am besten in den vorhandenen Leerzustands-Tests**
  statt in einem eigenen Dataset: „sieht unverändert aus“ heißt genau, dass
  dort eine Zeile mehr steht und sonst nichts.
- **Nicht auf dem Emulator verifiziert** — an der nativen Hälfte hat sich
  nichts geändert.
---
<!-- chief-timing story="FEIN-005" duration_ms=314981 cost=12.541151 in=146 out=473 cache_create=198923 cache_read=5849120 -->

## 2026-09-20 - FEIN-006

**Was umgesetzt wurde**

Nach jedem erfolgreichen Laden einer Woche räumt der Wochenplan seinen
Zwischenspeicher auf: Alle gecachten Wochen, deren Montag mehr als vier Wochen
vor oder nach dem Montag der aktuellen Kalenderwoche liegt, fallen weg — es
bleiben höchstens neun. Die gerade geladene Woche bleibt in jedem Fall stehen,
auch wenn der Nutzer weit aus dem Fenster geblättert hat. Ein gescheitertes
Laden räumt nichts auf, und die Einkaufslisten-Zeile in derselben Tabelle
bleibt unberührt.

**Geänderte Dateien**

- `app/Wochenplan/Cache.php` — `aufraeumen(string $behalten)` plus die
  Konstante `FENSTER = 4`
- `app/Wochenplan/Sitzung.php` — `setzen()` ruft nach `speichern()` das
  `aufraeumen()`
- `tests/Feature/WochenplanTest.php` — vier Fälle (Beispiel aus der Story,
  geladene Woche außerhalb des Fensters, fehlgeschlagenes Laden,
  Einkaufsliste), dazu die Helfer `wochenplanImCache()` und
  `wochenplanBlaettern()`

**Learnings for future iterations:**

- **Das Aufräumen gehört in `Sitzung::setzen()`, nicht in `Cache::speichern()`.**
  Sonst kann ein Test keinen „alten, gewachsenen Cache" mehr herstellen: jedes
  Schreiben räumte sofort auf, und die vier Wochen aus dem Beispiel der Story
  liegen 15 Wochen auseinander — über den Screen ist dieser Zustand also
  grundsätzlich nicht erreichbar, egal in welcher Reihenfolge oder mit welchem
  `setTestNow` man blättert. Arrange über die öffentliche API der Cache-Klasse
  (`wochenplanImCache()`), Assert weiter über `Native::visit()`.
- **Die Cache-Schlüssel sind `wochenplan:Y-m-d`** — der Textvergleich in SQL
  ordnet sie deshalb genauso wie der Kalender, ein Datums-`where` braucht keine
  Konvertierung. Das `where('schluessel', 'like', 'wochenplan:%')` ist trotzdem
  Pflicht, sonst fiele `einkaufsliste` (lexikografisch davor) mit weg.
- **Mutationstest statt Bauchgefühl.** Drei gezielte Mutationen am fertigen
  Code (`whereKeyNot` weg, LIKE-Präfix weg, `aufraeumen()` zusätzlich im
  Fehlerpfad) haben je genau den zuständigen neuen Test rot gemacht — ein
  billiger Beweis, dass keiner der Fälle nur zufällig grün ist. Bei
  Lösch-Features lohnt sich das, weil „nichts passiert" leicht mit „das
  Richtige passiert" verwechselt wird.
- **Zwei Sitzungen, zwei Neustarts.** Ein „App-Neustart" im Test ist
  `app()->forgetInstance(...)` — Wochenplan und Einkaufen haben getrennte
  Singletons (`App\Wochenplan\Sitzung`, `App\Mealie\Sitzung`), ein Test über
  beide Tabs muss beide vergessen.
- **Tests in anderen Dateien sind keine Bibliothek.** `mealieArtikel()` und
  `appNeuStarten()` stehen in `EinkaufenMealieTest.php`; wer sie in
  `WochenplanTest.php` bräuchte, nimmt stattdessen `jsonFixture(
  'mealie-einkaufsliste.json')` und `assertSee()` — sonst hängt der Lauf einer
  einzelnen Datei an der Ladereihenfolge. Nur `tests/Pest.php` ist geteilt.
- **Nicht auf dem Emulator verifiziert** — an der nativen Hälfte hat sich
  nichts geändert.
---
<!-- chief-timing story="FEIN-006" duration_ms=272555 cost=8.170659 in=92 out=373 cache_create=167214 cache_read=3337361 -->

## 2026-09-20 - FEIN-007

**Was umgesetzt wurde**

Ein eigenes Motiv — eine Liste mit drei abgehakten Einträgen — als einzige
SVG-Quelle, dazu ein Splash-Layout mit demselben Motiv über dem Schriftzug
„Einkaufsliste". Das Kommando `php artisan grafik` rendert daraus per Imagick
`public/icon.png` (1024 × 1024, weißes Motiv auf Indigo `#4F46E5`, kein
Alphakanal) und sechs Splash-PNGs (hell `#F8FAFC`/`#4F46E5`/`#0F172A`, dunkel
`#0F172A`/`#818CF8`/`#F8FAFC`, je 1×/2×/3× von 1280 × 1920). Alle PNGs sind
eingecheckt. `--ziel` schreibt woandershin, damit der Test in ein Temp-
Verzeichnis rendern kann.

**Geänderte Dateien**

- `resources/grafik/motiv.svg` — das Motiv, viewBox auf den Außenkanten
- `resources/grafik/splash.svg` — das Layout, mit `<!--motiv-->` als Einsatzstelle
- `app/Console/Commands/GrafikCommand.php` — Kommando `grafik`
- `public/icon.png`, `splash.png`, `splash@2x.png`, `splash@3x.png`,
  `splash-dark.png`, `splash-dark@2x.png`, `splash-dark@3x.png`
- `tests/Feature/GrafikTest.php` — sieben Fälle über die eingecheckten Dateien
  plus ein Kommandotest

**Learnings for future iterations:**

- **Imagick rendert SVG mit seinem internen MSVG-Renderer, und der kann fast
  nichts.** `queryFormats('*SVG*')` meldet MSVG/SVG/SVGZ, aber es gibt kein
  librsvg-Delegate und `Imagick::queryFonts('*')` liefert **null** Schriften.
  Konkret ausprobiert: `<text>` wird komplett weggelassen; `stroke` zeichnet
  weder in der angegebenen Farbe noch in der angegebenen Breite (ein `<line>`
  kommt als schwarzes Haar heraus, ein `<path fill="none" stroke="…">` gar
  nicht); `transform` an einem `<rect>` wird ignoriert; `rotate(a cx cy)` dreht
  trotzdem um den Ursprung. Was **funktioniert**: `fill` (auch vererbt von
  `<svg>`/`<g>`), `<rect rx>`, Pfade mit `M/L/A/Q/Z`, und `transform` an einem
  `<g>` mit `translate(x y) scale(s)`. Also: alles als gefüllte Pfade bauen,
  runde Enden über Halbkreis-Bögen (`A r r 0 0 0 …`), platzieren nur über
  `<g transform>`.
- **Schrift im Bild heißt Schrift als Pfad.** Der Schriftzug wurde einmalig mit
  `fontTools` (venv in `/tmp`) aus Roboto Medium (Apache 2.0, geholt über die
  Google-Fonts-CSS-API) in Umrisse umgewandelt und liegt jetzt als `<path>` in
  `splash.svg`. Kein Font im Repo, keine Laufzeitabhängigkeit.
- **Das Icon darf kein einziges durchsichtiges Pixel haben.** NativePHP prüft in
  `InstallsAppIcon::validateIosIcon()` sieben Stichproben auf Alpha und wirft
  das Icon sonst kommentarlos weg — genauso bei „nicht quadratisch" und
  „< 1024". Deshalb `setImageAlphaChannel(ALPHACHANNEL_REMOVE)` plus
  `setImageFormat('png24')`.
- **„Loading…" verschwindet von allein.** `MainActivity.kt` zeichnet
  `SplashText()` nur, wenn `splashResourceId == 0` — also nur, solange kein
  Splash-Drawable existiert. Es gibt in dieser Paketversion keinen Schalter
  dafür und keine Splash-Hintergrundfarbe in der Config.
- **Android skaliert den Splash auf genau 1280 × 1920 hoch (xxxhdpi) und
  zeichnet ihn mit `ContentScale.Crop`.** Auf einem 9:19,5-Telefon bleibt die
  volle Höhe und rund 68 % der Breite stehen — die 60-%-Regel aus der PRD hat
  also Luft. Quelle größer als 1280 × 1920 bringt auf Android nichts, die
  iOS-Varianten brauchen es trotzdem.
- **Ein Test, der nur Maße prüft, prüft zu wenig.** Die Mutation „Motiv nicht in
  das Splash-Layout einsetzen" lief zuerst grün durch: Der Schriftzug allein
  liegt immer noch innerhalb der mittleren 60 %. Erst die Prüfung auf den
  **Mittelpunkt** des belegten Bereichs (ohne Motiv rutscht er in die untere
  Hälfte) macht sie rot. Zweite Mutation: Motiv auf 80 % der Icon-Kante
  aufblasen → der Sicherheitsbereich-Test schlägt an.
- **Der Motiv-Anteil im Kommando (0,62) und die Grenze im Test (0,66) sind mit
  Absicht verschieden.** Bei exakt 66 % liegen die weichgezeichneten Randpixel
  genau auf der Grenze und der Test wird flackerig; außerdem wirkt ein
  randvolles Icon im Launcher gedrängt.
- **GD frisst Speicher: Ein 3840 × 5760 großes PNG kostet 88 MB.** Zwei davon in
  einem Suite-Lauf sprengen das 256-MB-Limit. Pixelprüfungen deshalb nur auf den
  1×-Dateien; für Existenz und Maße reicht `getimagesize()`, das nichts
  dekodiert.
- **Offen und außerhalb des Repos:** `nativephp/…/drawable/ic_launcher_background.xml`
  kommt als weiße Fläche aus der Paketvorlage und lässt sich nicht über die
  Config setzen. Das Adaptive Icon legt unser fertiges Icon zu 69 % auf diese
  weiße Fläche. Im Emulator ist das als heller Ring um die Indigo-Scheibe zu
  sehen, weil der Pixel-Launcher eine größere Maske als die 72 dp der Norm
  benutzt. Der Ordner ist gitignoriert und wird bei `native:install`
  neu geschrieben — gehört also zu FEIN-008, nicht hierher.
- **Auf dem Emulator verifiziert** (`Pixel_7_API_35`, Debug-Build über
  `native:run`; Icon und Splash entstehen in `native:install` unabhängig vom
  Build-Typ). Screenshot-Serie beim Kaltstart zeigt in Hell wie in Dunkel das
  Splash-Bild im passenden Modus und kein „Loading…"; auf dem Homescreen steht
  das neue Icon. Ein Screenshot-Burst ist hier das Mittel der Wahl — der Splash
  steht keine halbe Sekunde, ein einzelner `screencap` trifft ihn nicht:
  `adb shell "am force-stop …; am start -n …/com.nativephp.mobile.ui.MainActivity"`,
  danach sechsmal `screencap` hintereinander. Dunkelmodus schalten über
  `adb shell cmd uimode night yes|no`.
- Zusatz zur Kontrolle: Unsere Dateien bestehen NativePHPs eigene Validierungen
  (`validateIosIcon`, `validateSplashImage`), werden beim Build also nicht
  stillschweigend übersprungen.
---
<!-- chief-timing story="FEIN-007" duration_ms=1232291 cost=53.598769 in=430 out=1352 cache_create=766445 cache_read=26080050 -->

## 2026-09-20 18:05 — FEIN-008: Framework-Reste entfernen

Aufgeräumt und den Release-Ablauf um das Android-Theme ergänzt.

**Entfernt:** `app/Models/User.php`, `database/factories/UserFactory.php`,
`database/seeders/DatabaseSeeder.php` (samt beider Verzeichnisse und beider
PSR-4-Einträge `Database\Factories\`/`Database\Seeders\` in `composer.json`),
`config/auth.php`, `config/mail.php`, `URL::forceHttps()` (und damit die ganze
`boot()`-Methode) im `AppServiceProvider`, die drei Framework-Migrationen
(`users`/`password_reset_tokens`/`sessions`, `cache`/`cache_locks`,
`jobs`/`job_batches`/`failed_jobs`).

**`.env` und `.env.example`:** `SESSION_DRIVER=array`, `CACHE_STORE=file`,
`QUEUE_CONNECTION=sync` mit einer erklärenden Kommentarzeile; gestrichen sind
`BCRYPT_ROUNDS`, `MAIL_MAILER`, `APP_FAKER_LOCALE`, `SESSION_ENCRYPT`,
`SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_LIFETIME`,
`APP_MAINTENANCE_DRIVER`, `FILESYSTEM_DISK`, `BROADCAST_CONNECTION`.

**Neu:** `app/Release/AndroidTheme.php` — trägt die Farben aus
`config('nativephp.android.theme')` in `values/themes.xml` und
`values-night/themes.xml` ein. `ReleaseCommand::bauen()` ruft das vor
`native:package` auf und nennt die aufgefrischten Dateien.

**Dateien:** die oben genannten plus `app/Console/Commands/ReleaseCommand.php`,
`tests/Unit/ReleaseTest.php` (vier neue Fälle).

**Learnings for future iterations:**

- **`config/auth.php` und `config/mail.php` dürfen einfach weg.** Laravels
  Auth- und Mail-Provider lesen ihre Config erst, wenn jemand `Auth::` oder
  `Mail::` anfasst — das Framework bootet ohne beide Dateien anstandslos. Die
  `MAIL_MAILER`- und `BCRYPT_ROUNDS`-Zeilen in `phpunit.xml` stehen noch da,
  sie zeigen jetzt ins Leere und schaden nicht; wer dort aufräumt, ändert die
  Testumgebung und nicht die App.
- **`migrate:fresh` legt nur noch vier Tabellen an:** `migrations`,
  `listen_artikel`, `mealie_cache`, `einstellungen`. Die Jobs-Migrationen des
  NativePHP-Pakets (`9999_12_31_*`) laufen in der lokalen Umgebung gar nicht
  mit — sie sind auf `Schema::hasTable()` abgesichert und kämen erst im
  Paketkontext zum Zug.
- **Die Android-Theme-Dateien sind der stille Sonderfall.** `native:install`
  schreibt sie einmal aus `config/nativephp.php`; danach liest Gradle nur noch
  `nativephp/android/` (gitignoriert). Jede spätere Farbänderung in der Config
  kommt also **nie** auf dem Gerät an, ohne dass irgendwo ein Fehler auftaucht.
  Dasselbe gilt für alles andere, was `native:install` aus der Config ins
  Android-Projekt generiert (App-ID, Icon, Splash) — wer so etwas ändert,
  muss entweder neu installieren oder den Release-Ablauf nachziehen lassen.
- **`native:install` ist als Build-Schritt keine Option.** `ensureAppIdIsSet()`
  fragt interaktiv nach der Bundle-ID, sobald `NATIVEPHP_APP_ID` in der `.env`
  leer ist (bei uns ist sie leer, die ID kommt aus der Config) — im
  Kindprozess ohne TTY schriebe es eine geratene ID in die `.env`. Außerdem
  löscht der Default (`forcing = true`, abschaltbar nur über `--no-force`) das
  ganze `nativephp/android`-Verzeichnis. Deshalb patcht `AndroidTheme` gezielt
  die `<item name="…">`-Werte, statt die Datei neu zu erzeugen: Was das Paket
  sonst noch ins Theme schreibt, bleibt dabei stehen.
- **Unit-Tests haben keinen Container.** `tests/Pest.php` hängt `TestCase` nur
  an `Feature`; in `tests/Unit` gibt es kein `config()`. Klassen, die dort
  geprüft werden sollen, bekommen ihre Werte über den Konstruktor und eine
  `ausKonfiguration()`-Fabrik daneben — die Fabrik ist dann der einzige
  ungetestete Draht und wurde hier einmal von Hand gegen das echte
  `nativephp/android` laufen gelassen.
---
<!-- chief-timing story="FEIN-008" duration_ms=394467 cost=13.071317 in=140 out=308 cache_create=220901 cache_read=5936149 -->
