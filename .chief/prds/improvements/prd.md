# PRD: Feinschliff – Erscheinungsbild, Undo, Icon & Splash, Aufräumen

## 1. Einführung

Die Einkaufslisten-App (NativePHP for Mobile, native UI über `nativephp/mobile-ui`, Android zuerst) funktioniert, wirkt aber an mehreren Stellen noch unfertig: Es gibt keine Nutzereinstellungen zum Erscheinungsbild, der Start zeigt einen schwarzen Bildschirm mit „Loading…", das App-Icon ist das Paket-Standard-Icon, „Alles abhaken" fragt vorher nach statt hinterher einen Rückweg zu bieten, zwei Listen laufen bis an die Tab-Leiste, der Wochenplan-Cache wächst unbegrenzt, und im Projekt liegen ungenutzte Framework-Reste.

Diese PRD bündelt diese Verbesserungen. Sie fügt keine neuen Mealie-Funktionen hinzu.

## 2. Ziele

- Nutzer können Hell/Dunkel-Modus und Akzentfarbe in der App wählen; die Wahl gilt sofort, überall (inklusive Tab-Leiste und Systemdialogen) und über Neustarts hinweg.
- „Alles abhaken" läuft ohne Nachfrage und lässt sich direkt danach vollständig rückgängig machen.
- Die letzte Zeile jeder Liste steht beim Scrollen frei über der Tab-Leiste, in allen drei Tabs gleich.
- Der Wochenplan-Cache hält höchstens neun Wochen (aktuelle Woche ± vier).
- App-Icon und Splash Screen zeigen ein eigenes Motiv und gehen nahtlos in den ersten Screen über.
- Das Projekt enthält keine ungenutzten Auth-/Mail-/Queue-Reste mehr; auf dem Gerät schreibt kein Request mehr Sessions oder Cache in die Datenbank.

## 3. User Stories

### FEIN-001: Hell/Dunkel-Modus wählen und merken
**Status:** done
**Priority:** 1
**Description:** Als Nutzer möchte ich in den Einstellungen zwischen „System", „Hell" und „Dunkel" wählen, damit die App so aussieht, wie ich es will, unabhängig vom Systemthema.

**Acceptance Criteria:**
- [x] Die Einstellungen-Seite zeigt einen neuen Abschnitt mit der Überschrift „Erscheinungsbild" **oberhalb** des bestehenden Mealie-Abschnitts.
- [x] Der Abschnitt enthält einen Segmented Control mit genau drei Optionen in dieser Reihenfolge: „System", „Hell", „Dunkel".
- [x] Bei erster Nutzung ist „System" ausgewählt.
- [x] Ein Tipp auf eine Option übernimmt sie sofort; es gibt keinen Speichern-Knopf für diesen Abschnitt.
- [x] Nach Beenden und Neustart der App ist weiterhin die zuletzt gewählte Option ausgewählt.
- [x] Die Auswahl wird lokal in SQLite gespeichert (neue Tabelle nach dem Muster der bestehenden Mealie-Cache-Tabelle), nicht im Secure Storage.
- [x] Der Mealie-Abschnitt darunter bleibt unverändert (URL, Token-Feld, Speichern, Statuszeile, „Verbindung testen", „Token löschen").
- [x] Der Segmented Control trägt ein Accessibility-Label „Erscheinungsbild"; jede Option ist per Screenreader als ausgewählt/nicht ausgewählt erkennbar.
- [x] Der bestehende Theme-Test (jede gesendete Farbe hat eine abweichende Dunkel-Entsprechung) gilt auch für die Einstellungen-Seite mit dem neuen Abschnitt.

### FEIN-002: Gewählter Modus wirkt auf die ganze App
**Status:** done
**Priority:** 2
**Blocked by:** FEIN-001
**Description:** Als Nutzer möchte ich, dass „Hell" oder „Dunkel" die komplette App umstellt, inklusive Tab-Leiste, Navigationsleiste und Systemdialogen, damit nichts halb hell und halb dunkel aussieht.

**Acceptance Criteria:**
- [x] Wähle ich „Dunkel", stellt sich die App unmittelbar auf die dunkle Palette um, auch wenn das System auf Hell steht. Das gilt für alle Screens, die Tab-Leiste, die Navigationsleiste und die nativen Bestätigungsdialoge (z. B. „Token löschen?").
- [x] Wähle ich „Hell", gilt dasselbe umgekehrt.
- [x] Wähle ich „System", folgt die App wieder dem Systemthema, und ein Wechsel des Systemthemas bei laufender App wird übernommen.
- [x] Der gespeicherte Modus wird beim App-Start und bei jeder Rückkehr in den Vordergrund erneut angewendet, sodass die App nie im falschen Modus startet.
- [x] Die Umsetzung erfolgt als eigenes lokales natives Plugin nach dem Muster der beiden vorhandenen Plugins (Secure Storage, App-Lifecycle) mit Android- und iOS-Hälfte; der PHP-Teil ist gegen die Fake-Bridge getestet. Nur Android muss in dieser PRD auf dem Gerät verifiziert werden.
- [x] Ist das Plugin auf der Plattform nicht verfügbar (z. B. im Test), bleibt die Einstellung trotzdem gespeichert und die App läuft ohne Fehler weiter.

### FEIN-003: Akzentfarbe wählen
**Priority:** 3
**Blocked by:** FEIN-001
**Description:** Als Nutzer möchte ich eine von sechs Akzentfarben wählen, damit die App meinem Geschmack entspricht.

**Acceptance Criteria:**
- [ ] Im Abschnitt „Erscheinungsbild" steht direkt unter dem Modus-Umschalter eine Reihe aus sechs farbigen Kreisen in dieser Reihenfolge: Indigo, Blau, Grün, Orange, Rosa, Violett.
- [ ] Der aktive Kreis zeigt ein Häkchen; die anderen sind leer. Es gibt keine Textbeschriftung je Farbe.
- [ ] Jeder Kreis trägt ein Accessibility-Label nach dem Muster „Akzentfarbe Grün" bzw. „Akzentfarbe Grün, ausgewählt".
- [ ] Bei erster Nutzung ist Indigo ausgewählt (die heutigen Werte `#4F46E5` hell / `#818CF8` dunkel bleiben das Indigo-Preset).
- [ ] Ein Tipp übernimmt die Farbe sofort, ohne Speichern-Knopf, und die Wahl überlebt einen Neustart.
- [ ] Nach dem Tipp sind auf allen Screens sofort alle Elemente umgefärbt, die heute die Primärfarbe tragen: Tab-Leiste, gefüllte Buttons, Checkboxen, Banner-Knöpfe, das Datum des heutigen Tags im Wochenplan, der „Rückgängig"-Knopf aus FEIN-004.
- [ ] Jedes Preset definiert eine eigene Hell- und eine eigene Dunkel-Primärfarbe; der bestehende Theme-Test (jede Farbe hat eine abweichende Dunkel-Entsprechung) muss für jedes der sechs Presets bestehen.
- [ ] Für jedes Preset erreicht die Textfarbe auf der Primärfarbe (on-primary) in Hell **und** Dunkel ein Kontrastverhältnis von mindestens 4,5:1. Ist Weiß zu schwach, nutzt das Preset in diesem Modus eine dunkle Textfarbe.
- [ ] Android-Systemdialoge (Alert-Buttons, Date-Picker) bleiben bewusst Indigo; das ist kein Fehler.

### FEIN-004: „Alles abhaken" mit Rückgängig statt Nachfrage
**Priority:** 4
**Description:** Als Nutzer möchte ich „Alles abhaken" ohne Nachfrage auslösen und es direkt danach rückgängig machen können, damit der Normalfall schnell ist und der Fehlgriff nichts kostet.

**Acceptance Criteria:**
- [ ] Ein Tipp auf „Alles abhaken" in der Navigationsleiste hakt sofort alle eigenen und alle offenen Mealie-Artikel ab; der bisherige Bestätigungsdialog erscheint nicht mehr.
- [ ] Wie heute werden Mealie-Artikel nur einbezogen, wenn kein Banner steht und ein Token vorhanden ist; die Anzahl im Leistentext zählt nur die tatsächlich abgehakten Artikel.
- [ ] Unmittelbar danach erscheint unten eine schwebende Leiste **oberhalb** des „Abgehakt"-Blocks bzw. der Tab-Leiste, wenn der Block fehlt. Inhalt: links der Text „12 Artikel abgehakt" (Singular „1 Artikel abgehakt"), rechts ein Textknopf „Rückgängig" in der Akzentfarbe, ganz rechts ein Schließen-Kreuz mit Accessibility-Label „Schließen".
- [ ] Die Leiste hat den Hintergrund der Surface-Farbe, einen Rand in der Outline-Farbe und ist in Hell und Dunkel korrekt gefärbt (bestehender Theme-Test).
- [ ] „Rückgängig" holt genau die Artikel dieses Vorgangs zurück: Eigene Artikel stehen wieder auf der Liste (in denselben Warengruppen wie vorher), Mealie-Artikel werden per einem Bulk-Update wieder auf „offen" gesetzt und wandern aus dem Abgehakt-Block zurück in die Liste.
- [ ] Schlägt das Mealie-Bulk-Update beim Rückgängigmachen fehl, bleiben die eigenen Artikel trotzdem zurückgeholt, die Mealie-Artikel bleiben abgehakt, und es erscheint der Toast „Mealie: Zurückholen fehlgeschlagen".
- [ ] Die Leiste verschwindet ohne Timer bei der ersten Interaktion auf dem Screen (Tipp auf eine Zeile, Checkbox, Abgehakt-Kopf, Pull-to-Refresh), beim Tab-Wechsel, beim Öffnen der Einstellungen, wenn die App in den Hintergrund geht, oder beim Tipp auf das Kreuz. Danach ist der Vorgang nicht mehr rückgängig zu machen.
- [ ] Ein erneutes „Alles abhaken", während die Leiste steht, ersetzt sie durch eine neue Leiste für den neuen Vorgang; der alte Vorgang ist nicht mehr rückgängig zu machen.
- [ ] Ist nichts abzuhaken, passiert beim Tipp nichts und es erscheint keine Leiste (die Aktion ist wie heute ohnehin nur sichtbar, wenn die Liste Artikel hat).
- [ ] Der Fehlerfall des ursprünglichen Abhakens bleibt wie heute: Mealie-Artikel werden lokal zurückgerollt und der Toast „Mealie: Abhaken fehlgeschlagen" erscheint; die Leiste zeigt in diesem Fall nur die Anzahl der eigenen Artikel und ihr Rückgängig betrifft nur diese.

### FEIN-005: Luft am Listenende in allen Tabs
**Priority:** 5
**Description:** Als Nutzer möchte ich in Vorrat und Wochenplan bis zur letzten Zeile scrollen können, ohne dass sie an der Tab-Leiste klebt, so wie es im Einkaufen-Tab durch den Abgehakt-Block wirkt.

**Acceptance Criteria:**
- [ ] Im Vorrat endet die scrollbare Liste mit einem festen Abstand von einer Listenzeilenhöhe (56 dp), sodass die letzte Zeile beim vollständigen Scrollen ganz frei über der Tab-Leiste steht.
- [ ] Im Wochenplan gilt dasselbe unterhalb des Sonntags-Blocks.
- [ ] Im Einkaufen-Tab gilt dasselbe am Ende der Liste, wenn kein Abgehakt-Block angezeigt wird; ist der Block da, bleibt das heutige Verhalten.
- [ ] Die Leerzustände (Vorrat ohne Treffer / alles auf der Liste, Einkaufen leer, Wochenplan ohne Cache) sehen unverändert aus.
- [ ] Der Abstand ist in Hell und Dunkel unsichtbar (nimmt die Hintergrundfarbe an) und fällt nicht als Block auf.

### FEIN-006: Wochenplan-Cache aufräumen
**Priority:** 6
**Description:** Als Nutzer möchte ich, dass die App auf dem Gerät nicht für immer alte Wochen aufhebt, damit die Datenbank klein bleibt.

**Acceptance Criteria:**
- [ ] Nach jedem erfolgreichen Laden einer Woche werden alle gecachten Wochen gelöscht, deren Montag mehr als vier Wochen vor oder mehr als vier Wochen nach dem Montag der aktuellen Kalenderwoche liegt (Bezug: heutiges Datum zum Zeitpunkt des Ladens). Es bleiben also höchstens neun Wochen.
- [ ] Die gerade geladene Woche wird nie gelöscht, auch wenn sie außerhalb des Fensters liegt (sie ist ja gerade aufgeschlagen).
- [ ] Die Einkaufslisten-Zeile in derselben Tabelle bleibt unberührt.
- [ ] Beispiel: Heute ist der 20.09.2026 (KW 38). Gecacht sind die Wochen mit Montag 20.07., 17.08., 21.09. und 02.11. Nach einem erfolgreichen Laden von KW 38 sind 20.07. und 02.11. gelöscht, 17.08. und 21.09. bleiben.
- [ ] Beobachtbar: Blättere ich nach dem Aufräumen und einem App-Neustart ohne Mealie-Verbindung in eine gelöschte Woche, zeigt sie das Banner und den Leerzustand „keine gecachte Woche" statt alter Einträge.
- [ ] Ein fehlgeschlagenes Laden räumt nichts auf.

### FEIN-007: Eigenes App-Icon und Splash Screen
**Priority:** 7
**Description:** Als Nutzer möchte ich beim Start und auf dem Homescreen ein eigenes Motiv sehen statt eines schwarzen Bildschirms und des Paket-Standard-Icons.

**Acceptance Criteria:**
- [ ] Motiv: eine stilisierte Liste mit abgehakten Einträgen (Haken). Icon und Splash nutzen dasselbe Motiv.
- [ ] Icon: weißes Motiv auf Indigo-Fläche (`#4F46E5`), quadratisch, mindestens 1024 × 1024 px, liegt als `public/icon.png`. Das Motiv liegt vollständig im inneren Bereich von 66 % der Kantenlänge, damit es beim Android-Adaptive-Icon (rund, abgerundet, Squircle) nicht angeschnitten wird.
- [ ] Splash hell (`public/splash.png`): Hintergrund im hellen App-Hintergrund `#F8FAFC`, Motiv in Indigo `#4F46E5`, darunter der Schriftzug „Einkaufsliste" in der hellen On-Surface-Farbe. Größe 1280 × 1920 px (Hochformat 2:3).
- [ ] Splash dunkel (`public/splash-dark.png`): Hintergrund im dunklen App-Hintergrund `#0F172A`, Motiv in hellem Indigo `#818CF8`, Schriftzug in der dunklen On-Surface-Farbe. Gleiche Größe.
- [ ] Motiv und Schriftzug sind auf dem Splash zentriert und liegen innerhalb der mittleren 60 % von Breite und Höhe, weil NativePHP das Bild bildschirmfüllend zuschneidet (Crop).
- [ ] Zusätzlich liegen die iOS-Varianten `splash@2x.png`, `splash@3x.png`, `splash-dark@2x.png`, `splash-dark@3x.png` in `public/`.
- [ ] Die Quellen sind SVG-Dateien im Repo (ein Motiv, ein Splash-Layout). Ein Artisan-Kommando (`--no-interaction`-fähig) rendert daraus per Imagick alle oben genannten PNGs nach `public/`. Die PNGs werden trotzdem eingecheckt, damit der Build nicht von Imagick abhängt.
- [ ] Ein Test prüft, dass alle genannten Dateien existieren, PNG sind und die Mindestmaße einhalten (Icon ≥ 1024 quadratisch, Splash ≥ 1280 × 1920).
- [ ] Nach einem Release-Build zeigt die installierte App beim Start das Splash-Bild passend zum aktiven Modus, ohne „Loading…"-Text, und auf dem Homescreen das neue Icon.

### FEIN-008: Framework-Reste entfernen
**Priority:** 8
**Description:** Als Entwickler möchte ich, dass das Projekt nur noch enthält, was die App braucht, damit auf dem Gerät keine unnötigen Datenbankschreibzugriffe passieren und niemand beim Lesen des Codes über Auth oder Mail stolpert.

**Acceptance Criteria:**
- [ ] Entfernt sind: das User-Model, die User-Factory (samt Factory-Namespace in `composer.json`), der Database-Seeder, `config/auth.php`, `config/mail.php`, der erzwungene HTTPS-Aufruf im App-Service-Provider sowie die Migrationen für `users`, `password_reset_tokens`, `sessions`, `cache`/`cache_locks` und `jobs`/`job_batches`/`failed_jobs`.
- [ ] `config/session.php`, `config/cache.php` und `config/queue.php` bleiben, weil die nativen Routen in der Web-Middleware mit Session laufen und die Callback-Verwaltung des Pakets den Laravel-Cache anspricht.
- [ ] In `.env` und `.env.example` steht `SESSION_DRIVER=array`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Tote Keys sind entfernt: `BCRYPT_ROUNDS`, alle `MAIL_*`, `APP_FAKER_LOCALE`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_LIFETIME`, `APP_MAINTENANCE_DRIVER`, `FILESYSTEM_DISK`, `BROADCAST_CONNECTION`.
- [ ] Nach `php artisan migrate:fresh` enthält die App-Datenbank aus eigenen Migrationen nur `listen_artikel`, `mealie_cache` und die Einstellungen-Tabelle aus FEIN-001 (plus `migrations` und was das NativePHP-Paket selbst anlegt).
- [ ] Der bestehende Geheimnisse-Test, der Arch-Test und die gesamte Suite laufen unverändert grün.
- [ ] Nach einem Release-Build enthalten die generierten Android-Theme-Dateien (`values/themes.xml`, `values-night/themes.xml`) die Indigo-Werte aus `config/nativephp.php` (`#4F46E5` bzw. `#818CF8`) statt Schwarz/Weiß, sodass Alert- und Picker-Buttons Indigo sind.

## 4. Funktionale Anforderungen

- FR-1: Die App speichert Nutzereinstellungen lokal in SQLite in einer Schlüssel-Wert-Tabelle; Einstellungen überleben Neustarts und werden nie an Mealie gesendet.
- FR-2: Die Einstellungen-Seite hat einen Abschnitt „Erscheinungsbild" oberhalb des Mealie-Abschnitts mit Modus-Umschalter (System/Hell/Dunkel) und Akzentfarben-Reihe (sechs Presets).
- FR-3: Änderungen im Abschnitt „Erscheinungsbild" gelten sofort ohne Speichern-Knopf.
- FR-4: Der gewählte Modus erzwingt die helle bzw. dunkle Darstellung der gesamten App inklusive nativer Chrome-Elemente und Systemdialoge; „System" folgt dem Betriebssystem. Der Modus wird bei App-Start und Rückkehr in den Vordergrund erneut angewendet.
- FR-5: Die gewählte Akzentfarbe ersetzt die Primärfarbe (hell und dunkel) in allen Paket-Komponenten; Android-Systemdialoge bleiben Indigo.
- FR-6: Jedes Akzent-Preset erfüllt in Hell und Dunkel einen Kontrast von mindestens 4,5:1 zwischen Primärfarbe und On-Primary-Text.
- FR-7: „Alles abhaken" läuft ohne Bestätigungsdialog und zeigt danach eine Undo-Leiste mit Anzahl, „Rückgängig" und Schließen-Kreuz.
- FR-8: „Rückgängig" stellt genau die im letzten Vorgang abgehakten eigenen und Mealie-Artikel wieder her; Mealie per Bulk-Update mit optimistischer Anzeige und Rollback plus Toast bei Fehler.
- FR-9: Die Undo-Leiste verschwindet bei der nächsten Interaktion auf dem Screen, beim Verlassen des Screens, beim Tab-Wechsel, beim Hintergrundwechsel oder per Kreuz; sie hat keinen Timer.
- FR-10: Alle drei Tab-Listen enden mit einem Abstand von 56 dp; im Einkaufen-Tab nur, wenn kein Abgehakt-Block angezeigt wird.
- FR-11: Nach jedem erfolgreichen Laden einer Wochenplan-Woche löscht die App alle gecachten Wochen außerhalb von aktueller Woche ± 4 Wochen, ausgenommen die gerade geladene Woche und die Einkaufslisten-Zeile.
- FR-12: `public/` enthält Icon und Splash-Bilder (hell/dunkel, plus iOS 2x/3x) mit dem Motiv „abgehakte Liste"; ein Artisan-Kommando erzeugt sie aus SVG-Quellen im Repo.
- FR-13: Das Projekt enthält keine Auth-, Mail-, Queue- oder Session-Datenbank-Reste mehr; Session, Cache und Queue laufen ohne Datenbank.
- FR-14: Nach einem Release-Build tragen die generierten Android-Theme-Dateien die konfigurierten Indigo-Farben.

## 5. Nicht-Ziele

- Keine freie Farbwahl (Farbpicker), nur die sechs Presets.
- Keine Anpassung der Android-Systemdialogfarben an die gewählte Akzentfarbe (Build-Zeit).
- Kein zeitgesteuertes Ausblenden der Undo-Leiste.
- Kein Undo für einzelnes Abhaken, nur für „Alles abhaken".
- Keine Änderung an Mealie-Funktionen: kein Anlegen/Bearbeiten von Mealie-Artikeln, kein Rezept-Detail in der App, kein Zutaten-auf-die-Liste.
- Keine Freitext-Artikel, keine Mengen/Notizen an eigenen Artikeln.
- Keine Mehrsprachigkeit.
- Kein Umbau von `config/session.php`, `config/cache.php`, `config/queue.php`.
- Keine Verifikation auf iOS-Geräten in dieser PRD (iOS-Hälfte des Plugins und iOS-Splash-Varianten werden geliefert, aber nur Android wird auf dem Gerät geprüft).
- Kein Aufräumen des Mealie-Einkaufslisten-Cache (eine Zeile, unproblematisch).

## 6. Design & Frontend

### Screens

- **Einstellungen** (Stack-Layout, gepusht, Back-Button): neuer Abschnitt „Erscheinungsbild" oberhalb des Mealie-Abschnitts. Aufbau von oben nach unten: Abschnittsüberschrift „Erscheinungsbild" (gleiche Typografie wie die Mealie-Überschrift), Segmented Control „System | Hell | Dunkel", darunter eine Reihe aus sechs Farbkreisen (Durchmesser ca. 36 dp, Abstand `gap-3`, linksbündig mit `px-4`), aktiver Kreis mit weißem Häkchen (im Dunkelmodus Häkchen in On-Primary des Presets). Danach der unveränderte Mealie-Abschnitt.
- **Einkaufen**: unverändert bis auf die Undo-Leiste (siehe unten) und den Abstand am Listenende, wenn der Abgehakt-Block fehlt.
- **Vorrat / Wochenplan**: unverändert bis auf den Abstand am Listenende.
- **Splash**: bildschirmfüllend, Motiv und Schriftzug zentriert innerhalb der mittleren 60 %.

### Zustände

- Einstellungen „Erscheinungsbild": kein Lade-, Fehler- oder Leerzustand; die Werte kommen synchron aus SQLite, Default System/Indigo.
- Undo-Leiste: nur zwei Zustände, sichtbar oder nicht. Während das Mealie-Bulk-Update für „Rückgängig" läuft, ist die Leiste bereits verschwunden (die Interaktion mit „Rückgängig" beendet sie), und die Liste zeigt optimistisch den zurückgeholten Zustand.
- Undo-Leiste bei ausschließlich eigenen Artikeln, bei gemischtem Vorgang und bei fehlgeschlagenem Mealie-Abhaken: immer derselbe Aufbau, nur die Zahl unterscheidet sich.

### Komponenten

- Wiederverwenden: die bestehenden Abschnittsüberschriften und Listenzeilen der Einstellungen-Seite, `native:list-item`, `native:column`, `native:row`, `native:text`, das Toast-Muster (`Dialog::toast`) für Fehler, `Dialog::alert` bleibt für „Token löschen".
- Segmented Control: die Paket-Komponente von `nativephp/mobile-ui`, falls vorhanden; sonst drei nebeneinanderliegende Buttons mit gefülltem Aktiv-Zustand in der Primärfarbe.
- Farbkreise: `native:row` aus antippbaren runden Flächen in der jeweiligen Preset-Farbe (hell/dunkel je Modus), aktiver Kreis mit Haken-Icon.
- Undo-Leiste: das Floating-Overlay des Pakets (schwebt über Inhalt und Tab-Leiste), unten positioniert mit Offset so, dass es über dem Abgehakt-Block bzw. der Tab-Leiste sitzt; Inhalt eine `native:row` mit Text, Textknopf „Rückgängig" in der Primärfarbe und Icon-Button „Schließen"; Hintergrund Surface, Rand Outline, Innenabstand `px-4 py-3`, Eckenradius wie Listenkarten.
- Abstand am Listenende: eine leere `native:column` mit fester Höhe 56 dp in Hintergrundfarbe als letztes Kind der Liste.
- Neu (nativ): kleines Plugin „appearance" mit einem Bridge-Call „Modus setzen" (system/light/dark).

### Interaktion

- Modus und Akzentfarbe: Tipp → sofort persistieren → sofort anwenden. Kein Toast, keine Bestätigung.
- Undo-Leiste: erscheint ohne Animation direkt nach dem Abhaken; „Rückgängig" tippen → Leiste weg, Liste zeigt optimistisch den alten Stand; Fehler → Toast. Jede andere Interaktion auf dem Screen räumt die Leiste weg, ohne die Interaktion selbst zu blockieren (der Tipp auf eine Zeile hakt sie ab **und** entfernt die Leiste).
- Pull-to-Refresh, App-Foregrounding und Rückkehr aus den Einstellungen räumen die Leiste ebenfalls weg.

### Visuelle Richtung

- Alles bleibt im vorhandenen Material-3-Stil des Pakets. Keine neuen Farben außer den fünf zusätzlichen Presets. Icon und Splash schlicht: ein Motiv, eine Farbe, viel Fläche, kein Verlauf, kein Schatten.

### Accessibility

- Alle neuen Bedienelemente haben deutsche Accessibility-Labels (Segmented Control „Erscheinungsbild", Kreise „Akzentfarbe X[, ausgewählt]", „Rückgängig", „Schließen").
- Kontrast: Presets 4,5:1 auf On-Primary in beiden Modi; Undo-Leiste nutzt Surface/On-Surface, also die bereits geprüften Paare.
- Fokusreihenfolge in den Einstellungen: Erscheinungsbild-Elemente vor den Mealie-Elementen, in Leserichtung.

## 7. Technische Hinweise

- **Theme zur Laufzeit:** Das UI-Paket liest `config('native-ui.theme')` in seinem eigenen Service Provider, der per Package-Discovery **vor** dem App-Provider läuft. Ein `config()->set()` im App-Provider kommt zu spät. Der dokumentierte Weg ist `Theme::merge([...])` aus `Native\Mobile\UI\Theme` in `AppServiceProvider::boot()`, gespeist aus der gespeicherten Akzentfarbe. Der `theme()`-Helper liest weiter aus `config()`, das `merge` synchronisiert beides.
- **Hell/Dunkel erzwingen:** Es gibt keine PHP-API dafür. Android-Renderer entscheiden per `isSystemInDarkTheme()`, `appearance` in `config/nativephp.php` gilt nur beim iOS-Build. Das neue Plugin setzt auf Android den Night-Mode der App (z. B. `AppCompatDelegate.setDefaultNightMode`) und auf iOS `overrideUserInterfaceStyle` des Fensters. Das Paket liefert `AppearanceChanged`-Events und `System::appearance()` zum Lesen.
- **Toast ohne Aktion:** `Dialog::toast()` kennt nur Text und Dauer. Für die Undo-Leiste das Floating-Overlay des UI-Pakets nutzen (`floatingOverlayOverride()` am Screen); Sichtbarkeit ist Screen-Zustand.
- **Undo-Daten:** Eigene Artikel werden heute hart gelöscht; vor dem Abhaken die IDs merken. Für Mealie existieren Bulk-Setzen mit `false` und das Sitzungs-Bulk-Umhaken bereits, sie werden nur noch nirgends aufgerufen.
- **Session/Cache:** `routes/mobile.php` läuft unter der `web`-Middleware (Session bei jedem Request); die Callback-Verwaltung des Pakets nutzt `Cache::pull/put`. Daher Treiber umstellen statt Config löschen. Das NativePHP-Paket bringt eigene, bedingte Jobs-Migrationen mit.
- **Android-Theme-Dateien** werden nur bei `native:install` geschrieben und sind im gitignorierten `nativephp/`-Ordner aktuell veraltet (Schwarz/Weiß). Der Release-Ablauf muss sicherstellen, dass sie die Config-Werte tragen.
- **Bilder:** Imagick ist in PHP verfügbar (kein rsvg/ImageMagick-CLI). NativePHP skaliert Splash-Bilder auf 2:3-Dichten und schneidet mit Crop zu; Icons werden auf Launcher- und Adaptive-Foreground-Größen skaliert.
- **Testumgebung:** `phpunit.xml` setzt bereits `SESSION_DRIVER=array`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`; die Umstellung in `.env` gleicht Gerät und Tests an.

## 8. Testentscheidungen

- **Naht:** wie im gesamten Projekt der Screen. `Native::visit($uri, platform: 'android')` mit `Native::fakeBridge()` und `Http::fake()` für Mealie; Assertions gegen den publizierten Wire-Tree über die Helfer in `tests/Pest.php` (`knotenMitRef`, `listenAbschnitte`, `farbPaare`, `checkboxAntippen`, `navUntertitel`). Keine neue Naht für Einstellungen, Undo oder Cache.
- **Persistenz** wird über Neustart-Simulation beobachtet (Singletons vergessen wie `wochenplanNeuStarten()`), nicht über Tabellenabfragen: Einstellung setzen, neu starten, Screen zeigt sie ausgewählt.
- **Plugin** wird über `assertNativeCalled` auf den neuen Bridge-Call mit dem erwarteten Modus geprüft, beim Setzen, beim Mount und beim `AppForegrounded`-Event.
- **Akzentfarbe** über `farbPaare` des Wire-Trees: Nach Wahl von „Grün" tragen Tab-Leiste und Buttons die Grün-Werte; für jedes Preset läuft der bestehende Hell/Dunkel-Paar-Test. Kontrast wird per Test mit einer eigenständigen Kontrastformel (WCAG-Luminanz) gegen die Literale der Presets geprüft.
- **Undo** mirrort `tests/Feature/EinkaufenTest.php` und `EinkaufenMealieTest.php`: nach `press('alleAbhaken')` steht die Leiste mit dem erwarteten Text, nach `press('rueckgaengig')` sind die Artikel wieder in den erwarteten Abschnitten, Mealie erhielt genau ein Bulk-PUT mit `checked=false`; Fehlerpfad über `Http::fake` mit Fehlerantwort und Toast-Assertion. Dismissal-Regeln je als eigener Test (Zeilentipp, Pull-to-Refresh, `AppForegrounded`, Navigation).
- **Cache-Aufräumen** in `WochenplanTest`: Wochen mit `Carbon::setTestNow` außerhalb und innerhalb des Fensters laden, dann aktuelle Woche laden, Neustart, Mealie ausfallen lassen; die gelöschte Woche zeigt Banner und Leerzustand, die behaltene ihre Einträge. Erwartete Daten sind Literale aus dem Beispiel in FEIN-006.
- **Abstand** über den Wire-Tree: letztes Kind der Liste ist der 56-dp-Spacer, im Einkaufen-Tab nur ohne Abgehakt-Block.
- **Icon/Splash:** ein Test liest die PNGs mit GD und prüft Existenz und Maße gegen die Literale aus FEIN-007; das Render-Kommando wird als Kommandotest ausgeführt und erzeugt in ein Temp-Verzeichnis.
- **Aufräumen:** keine eigenen Tests; Suite, Arch-Test und Geheimnisse-Test bleiben grün.
- **Guter Test hier:** beobachtet Verhalten am Screen und Bridge-Aufrufe, nicht Tabellen oder Sitzungsobjekte. Erwartungswerte kommen aus dieser PRD (Texte, Farben, Zahlen), nicht aus dem Code.

## 9. Erfolgsmetriken

- Moduswechsel und Akzentwechsel wirken in unter einer Sekunde ohne App-Neustart und bleiben nach Neustart erhalten (manuell auf dem Android-Gerät verifiziert).
- „Alles abhaken" plus „Rückgängig" braucht zwei Tipps, danach ist die Liste identisch zum Stand davor (gleiche Artikel in gleichen Gruppen).
- Nach vier Wochen Nutzung enthält die Cache-Tabelle höchstens neun Wochenplan-Zeilen.
- Beim Kaltstart ist kein schwarzer Bildschirm und kein „Loading…" mehr sichtbar, sondern das Splash-Bild im passenden Modus.
- Die Datenbank auf dem Gerät hat nach dem Aufräumen keine `sessions`-, `cache`- oder `users`-Tabelle mehr, und die Testsuite bleibt vollständig grün.

## 10. Offene Fragen

Keine. Alles Entscheidbare wurde in Phase 1 festgelegt (Plugin statt Merge-Trick für den Modus, Overlay-Leiste ohne Timer für Undo, ± 4 Wochen für den Cache, Motiv „abgehakte Liste", Treiberumstellung statt Config-Löschung).
