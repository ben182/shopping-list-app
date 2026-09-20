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
  werden nur ausgegeben, nicht ausgeführt.

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
