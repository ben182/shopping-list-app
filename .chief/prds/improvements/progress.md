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
