# Fortschritt — PRD „improvements“

## Codebase Patterns

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
