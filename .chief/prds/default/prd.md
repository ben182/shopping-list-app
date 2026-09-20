# PRD: Einkaufsliste als NativePHP-App mit Mealie-Anbindung

## 1. Einführung

Die bestehende Einkaufslisten-Webapp (`~/Code/shopping-list`, Nuxt 4 + Nitro + SQLite auf einem Server) wird als native Android-App mit **NativePHP for Mobile v4** und **Edge-Komponenten** (`nativephp/mobile-ui`) neu gebaut. Der Funktionsumfang der alten App bleibt erhalten: ein fester Katalog von 113 Artikeln in 8 Warengruppen, jeder Artikel ist entweder „im Vorrat“ oder „auf der Liste“, Tap wechselt den Zustand.

Neu dazu kommt die Anbindung an die selbst gehostete **Mealie**-Instanz (`https://mealie.example.test`). Die offenen Artikel der Mealie-Einkaufsliste erscheinen zusammen mit den eigenen Artikeln in **einer** Einkaufs-Übersicht, gruppiert nach Warengruppe, und lassen sich direkt in Mealie abhaken. Ein dritter Tab zeigt den Mealie-Wochenplan.

Problem, das gelöst wird: Beim Einkaufen musste man bisher zwei Listen parallel offen halten (eigene App und Mealie-Web-UI) und zweimal durch den Laden laufen. Außerdem hing die alte App an einem Server, der nun wegfällt.

Der Datenbestand der eigenen Liste liegt **lokal auf dem Gerät** (SQLite). Es gibt keinen Server und keinen Sync zwischen Geräten.

## 2. Ziele

- Alle Funktionen der alten App sind in der nativen App vorhanden: Vorrat, Einkaufsliste, Suche im Vorrat, „Alles abhaken“ mit Bestätigung, Gruppierung nach Warengruppe, Leerzustände.
- Die eigene Liste funktioniert vollständig offline und ohne Mealie.
- Offene Mealie-Artikel und eigene Artikel erscheinen in einer zusammengeführten Liste, gruppiert nach Warengruppe, mit einer einzigen Geste („Tap = erledigt“) für beide Quellen.
- Neue oder umbenannte Mealie-Labels brauchen kein App-Update: unbekannte Labels erscheinen automatisch als eigene Gruppe.
- Der Wochenplan der aktuellen Kalenderwoche ist in der App sichtbar, mit Blättern in andere Wochen.
- Mealie-Ausfälle und fehlende Verbindung werden sichtbar angezeigt; die letzte bekannte Mealie-Liste bleibt verfügbar.
- Das Mealie-API-Token liegt im geräteeigenen Secure Storage, nicht im App-Bundle.
- Die App läuft auf Android; der Code ist so gebaut, dass ein späterer iOS-Build ohne Umbau möglich ist.

## 3. User Stories

Prefix: `EKL`.

### EKL-001: App-Grundgerüst mit drei Tabs
**Status:** done
**Priority:** 1
**Description:** Als Nutzer möchte ich die App auf meinem Android-Gerät starten und zwischen den drei Bereichen Einkaufen, Vorrat und Wochenplan wechseln, damit die Grundstruktur der App steht.

**Acceptance Criteria:**
- [x] Ein neues Laravel-13-Projekt mit `nativephp/mobile` (^4.5) und `nativephp/mobile-ui` (^0.3) liegt in `/Users/ben/Code/shopping-list-app`; die App heißt auf dem Homescreen „Einkaufsliste“, die Bundle-ID ist `de.ben182.einkaufsliste`.
- [x] Nach dem Start erscheint eine native Tab-Leiste mit genau drei Tabs in dieser Reihenfolge: „Einkaufen“ (Einkaufswagen-Icon), „Vorrat“ (Archiv-/Inventar-Icon), „Wochenplan“ (Kalender-Icon). Jeder Tab zeigt einen Screen mit einem Titel in der Top-Bar.
- [x] Der Einkaufen-Tab ist beim Start aktiv; die Top-Bar des Einkaufen-Tabs enthält rechts eine Zahnrad-Action „Einstellungen“ (mit `a11y-label`), die einen noch leeren Einstellungen-Screen als gepushten Screen mit Zurück-Navigation öffnet.
- [x] Die Primärfarbe des Themes ist Indigo (`#4F46E5` hell, `#818CF8` dunkel); aktive Tab-Icons, Buttons und Checkboxen erscheinen in dieser Farbe.
- [x] Die App folgt dem System-Dark-Mode: Umschalten des System-Themes wechselt Hintergrund und Textfarben aller Screens ohne Neustart.
- [x] Die Ausrichtung ist auf Hochformat begrenzt.
- [x] Die `.env.example` enthält leere Platzhalter für `NATIVEPHP_APP_ID`, `NATIVEPHP_APP_VERSION`, `NATIVEPHP_APP_VERSION_CODE`, `ANDROID_KEYSTORE_FILE`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` sowie `MEALIE_URL` (vorbelegt mit `https://mealie.example.test`) und `MEALIE_SHOPPING_LIST_ID` (vorbelegt mit `00000000-0000-4000-8000-000000000000`).
- [x] Es gibt keine Livewire-, Inertia- oder WebView-Screens; alle Screens sind `NativeComponent`-Klassen mit Edge-Blade-Views.

### EKL-002: Vorrat anzeigen und Artikel auf die Liste setzen
**Status:** done
**Priority:** 2
**Blocked by:** EKL-001
**Description:** Als Nutzer möchte ich im Vorrat alle Katalog-Artikel sehen, die noch nicht auf meiner Liste stehen, und sie per Tap auf die Liste setzen, damit ich meinen Einkauf zusammenstellen kann.

**Acceptance Criteria:**
- [x] Der Katalog enthält exakt die 8 Gruppen und 113 Artikel aus Anhang A in genau dieser Reihenfolge; er ist als Konfiguration im Code hinterlegt und in der App nicht editierbar.
- [x] Der Vorrat-Screen zeigt alle Katalog-Artikel, die **nicht** auf der Liste sind, gruppiert nach Warengruppe. Jede Gruppe hat eine Zwischenüberschrift mit dem Gruppennamen (Kleinbuchstaben-Kapitälchen-Stil: kleine Schrift, Großbuchstaben, gedämpfte Farbe). Gruppen ohne sichtbare Artikel werden samt Überschrift ausgeblendet.
- [x] Die Reihenfolge der Gruppen und der Artikel innerhalb einer Gruppe entspricht immer der Katalogreihenfolge, nie alphabetisch oder nach Zeitpunkt.
- [x] Jeder Artikel ist eine `native:list-item`-Zeile mit dem Artikelnamen als Headline und einem Plus-Icon als Trailing-Icon; die ganze Zeile ist tappbar.
- [x] Tap auf einen Artikel entfernt ihn sofort aus dem Vorrat (ohne Bestätigung, ohne sichtbare Verzögerung); der Zustand ist nach Beenden und Neustart der App erhalten.
- [x] Der Untertitel in der Top-Bar lautet „Tippe auf einen Artikel zum Hinzufügen“, wenn die Liste leer ist, sonst „n auf der Liste“ (n = Anzahl eigener Artikel auf der Liste, z. B. „5 auf der Liste“).
- [x] Sind alle Katalog-Artikel auf der Liste, zeigt der Screen einen zentrierten Leerzustand mit einem Häkchen-Icon (`native:icon`, kein Emoji) und dem Text „Alles auf der Liste.“
- [x] Der Listen-Zustand liegt in einer lokalen SQLite-Tabelle; Migrationen laufen beim App-Start. Ein App-Update mit geändertem Katalog löscht den gespeicherten Zustand nicht; gespeicherte Artikel-IDs, die im Katalog nicht mehr existieren, werden nirgends angezeigt und zählen in keiner Anzahl mit.

### EKL-003: Eigene Artikel auf dem Einkaufen-Screen abhaken
**Status:** done
**Priority:** 3
**Blocked by:** EKL-002
**Description:** Als Nutzer möchte ich auf dem Einkaufen-Screen meine Artikel gruppiert sehen und per Tap zurück in den Vorrat schicken, damit ich beim Einkaufen abhaken kann.

**Acceptance Criteria:**
- [x] Der Einkaufen-Screen zeigt alle eigenen Artikel, die auf der Liste sind, gruppiert nach Warengruppe mit denselben Zwischenüberschriften und derselben Reihenfolge wie im Vorrat (Katalogreihenfolge); leere Gruppen sind ausgeblendet.
- [x] Jeder Artikel ist eine `native:list-item`-Zeile mit einer leeren Checkbox vorn (Leading-Checkbox, nicht angehakt) und dem Artikelnamen als Headline. Tap auf die ganze Zeile entfernt den Artikel sofort von der Liste; er erscheint danach wieder im Vorrat.
- [x] Ein im Vorrat hinzugefügter Artikel erscheint ohne Neuladen beim Wechsel auf den Einkaufen-Tab an seiner Katalogposition.
- [x] Der Untertitel in der Top-Bar zeigt „n Artikel“ (Anzahl aller offenen Artikel auf dem Screen; solange keine Mealie-Anbindung existiert, nur eigene Artikel). Bei 0 Artikeln wird kein Untertitel angezeigt.
- [x] Ist die Liste leer, zeigt der Screen einen zentrierten Leerzustand mit Einkaufswagen-Icon (`native:icon`), dem Text „Liste ist leer.“ und darunter kleiner, gedämpft: „Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.“
- [x] Die Liste ist scrollbar und Inhalte liegen nicht hinter der Tab-Leiste.

### EKL-004: Vorrat durchsuchen
**Status:** done
**Priority:** 4
**Blocked by:** EKL-002
**Description:** Als Nutzer möchte ich den Vorrat per Suchfeld filtern, damit ich einen Artikel unter 113 schnell finde.

**Acceptance Criteria:**
- [x] Über der Vorrat-Liste sitzt ein natives Suchfeld (`native:outlined-text-input`) mit Lupen-Icon vorn und Platzhalter „Artikel suchen…“; kein Autofokus beim Öffnen des Tabs, keine Autokorrektur oder automatische Großschreibung.
- [x] Während der Eingabe filtert die Liste live (Debounce höchstens 250 ms): Ein Artikel bleibt sichtbar, wenn sein Name den eingegebenen Text enthält, unabhängig von Groß-/Kleinschreibung; führende und abschließende Leerzeichen der Eingabe werden ignoriert. Gruppen ohne Treffer verschwinden samt Überschrift.
- [x] Sobald das Suchfeld Text enthält, erscheint darin hinten ein Löschen-Button (X-Icon, `a11y-label` „Suche leeren“); Tap leert die Suche und zeigt wieder den kompletten Vorrat.
- [x] Gibt es keine Treffer, zeigt der Screen einen Leerzustand mit „Suche ohne Ergebnis“-Icon (`native:icon`) und dem Text „Keine Treffer für „<Eingabe>“.“ mit deutschen Anführungszeichen (öffnend „, schließend “).
- [x] Tap auf einen gefilterten Artikel setzt ihn auf die Liste; der Suchtext bleibt erhalten und die Liste zeigt die restlichen Treffer.
- [x] Das Suchfeld wird beim Verlassen und erneuten Öffnen des Vorrat-Tabs geleert.

### EKL-005: „Alles abhaken“ für eigene Artikel mit Bestätigung
**Status:** done
**Priority:** 5
**Blocked by:** EKL-003
**Description:** Als Nutzer möchte ich nach dem Einkauf alle eigenen Artikel mit zwei Taps zurück in den Vorrat schicken, damit die Liste wieder leer ist.

**Acceptance Criteria:**
- [x] Die Top-Bar des Einkaufen-Screens zeigt eine Action „Alles abhaken“ (Text-Action oder Icon mit `a11y-label` „Alles abhaken“) nur dann, wenn mindestens ein Artikel auf dem Screen offen ist.
- [x] Tap öffnet einen nativen Bestätigungsdialog (`Dialog::alert`) mit Titel „Alles abhaken?“, dem Text „1 Artikel wandert zurück in den Vorrat.“ bei genau einem bzw. „n Artikel wandern zurück in den Vorrat.“ bei mehreren, und den Buttons „Abbrechen“ und „Abhaken“.
- [x] „Abhaken“ entfernt alle eigenen Artikel von der Liste; der Screen zeigt danach den Leerzustand aus EKL-003, der Vorrat zeigt alle Artikel.
- [x] „Abbrechen“ oder Schließen des Dialogs verändert nichts.

### EKL-006: SecureStorage-Plugin und Einstellungen-Screen für das Mealie-Token
**Status:** done
**Priority:** 6
**Blocked by:** EKL-001
**Description:** Als Nutzer möchte ich mein Mealie-API-Token einmalig in der App hinterlegen und die Verbindung testen, damit die App auf Mealie zugreifen kann, ohne dass das Token im App-Code liegt.

**Acceptance Criteria:**
- [x] Das lokale Plugin `plugins/secure-storage` aus `~/Herd/kitchen-sink` ist in das Projekt übernommen, mit eigenem Vendor-Namespace (PHP `Ben182\SecureStorage`, Kotlin-Package `de.ben182.securestorage`, Composer-Name `ben182/secure-storage`), in `composer.json` als Path-Repository eingebunden und in der Plugin-Allowlist des `NativeServiceProvider` registriert; `php artisan native:plugin:list` führt es als registriert auf.
- [x] Der Einstellungen-Screen (Titel „Einstellungen“) zeigt die Mealie-URL als nicht editierbaren Text (Wert aus der Konfiguration, hart hinterlegt: `https://mealie.example.test`).
- [x] Darunter ein maskiertes Eingabefeld „API-Token“ (`native:outlined-text-input`, `secure`) und ein Button „Speichern“. Speichern mit leerem Feld zeigt den Toast „Bitte Token eingeben“ und speichert nichts. Erfolgreiches Speichern zeigt den Toast „Token gespeichert“; das Feld wird geleert und der Status zeigt „Token hinterlegt“.
- [x] Der Status-Text unter dem Feld zeigt beim Öffnen des Screens einen der Zustände: „Kein Token hinterlegt“, „Token hinterlegt“, „Gerät gesperrt, Token nicht lesbar“ oder „Fehler beim Lesen: <Code>“. Das Token selbst wird nie im Klartext angezeigt.
- [x] Ein Button „Verbindung testen“ ist nur aktiv, wenn ein Token hinterlegt ist. Tap ruft Mealie auf und zeigt bei Erfolg den Text „Verbunden: <Nutzername>, Mealie <Version>“ (Nutzername aus `/api/users/self`, Version aus `/api/app/about`). Bei HTTP 401 zeigt er „Token ungültig“, bei Netzwerkfehler oder Timeout „Mealie nicht erreichbar“. Während des Tests zeigt der Button einen Ladezustand.
- [x] Ein Button „Token löschen“ (destruktive Variante) öffnet einen Dialog „Token löschen?“ mit „Abbrechen“ / „Löschen“; „Löschen“ entfernt das Token aus dem Secure Storage, der Status wechselt auf „Kein Token hinterlegt“.
- [x] Ein gespeichertes Token ist nach Beenden und Neustart der App weiterhin hinterlegt.
- [x] Im Testkontext (ohne native Bridge) meldet der Screen „Fehler beim Lesen: BRIDGE_UNAVAILABLE“ statt abzustürzen.

### EKL-007: Offene Mealie-Artikel in der Einkaufs-Übersicht anzeigen
**Status:** done
**Priority:** 7
**Blocked by:** EKL-003, EKL-006
**Description:** Als Nutzer möchte ich die offenen Artikel meiner Mealie-Einkaufsliste zusammen mit meinen eigenen Artikeln in einer nach Warengruppen gruppierten Liste sehen, damit ich einmal durch den Laden laufe.

**Acceptance Criteria:**
- [x] Beim Öffnen des Einkaufen-Tabs, beim Zurückkehren der App in den Vordergrund und per Pull-to-Refresh (`native:list` mit `on-refresh`) lädt die App die Mealie-Liste mit der konfigurierten Listen-ID (`GET /api/households/shopping/lists/{id}`, Bearer-Token aus dem Secure Storage, Timeout 10 s).
- [x] Eigene Artikel sind sofort sichtbar; während des ersten Ladevorgangs einer Sitzung erscheint direkt unter der Top-Bar eine kleine Zeile mit `native:activity-indicator` und dem Text „Mealie wird geladen…“. Weitere Ladevorgänge zeigen keine Zeile (Pull-to-Refresh zeigt den nativen Spinner).
- [x] Nicht abgehakte Mealie-Artikel werden in die Gruppen einsortiert. Zuordnung eines Mealie-Labels zu einer Gruppe, in dieser Reihenfolge: (1) Label-Name ist exakt gleich einem Katalog-Gruppennamen → diese Gruppe; (2) Label-Name steht in der Alias-Tabelle aus Anhang B → die dort genannte Gruppe; (3) sonst → eine eigene Gruppe mit dem Label-Namen als Überschrift. Artikel ohne Label → Gruppe „Sonstiges“.
- [x] Die 8 Katalog-Gruppen erscheinen zuerst in Katalogreihenfolge; danach folgen die zusätzlichen Label-Gruppen alphabetisch. Leere Gruppen sind ausgeblendet.
- [x] Innerhalb einer Gruppe stehen eigene Artikel zuerst in Katalogreihenfolge, danach Mealie-Artikel in der Reihenfolge, die Mealie liefert (Feld `position`, dann Erstellzeitpunkt).
- [x] Eine Mealie-Zeile ist eine `native:list-item`-Zeile mit leerer Leading-Checkbox, Mealies `display`-Text als Headline (z. B. „400 g mehligkochende Kartoffeln“), dem Rezeptnamen als Supporting-Text, wenn der Artikel mindestens einen Rezeptbezug hat (bei mehreren: durch „ · “ getrennt), und einem dezenten Trailing-Icon in gedämpfter Farbe (Besteck-/Restaurant-Icon) mit `a11y-label` „aus Mealie“. Eigene Zeilen haben kein Trailing-Icon.
- [x] Der Untertitel „n Artikel“ zählt eigene plus offene Mealie-Artikel. Der Leerzustand aus EKL-003 erscheint nur, wenn weder eigene noch offene Mealie-Artikel vorhanden sind.
- [x] Ist kein Token hinterlegt, erscheint direkt unter der Top-Bar eine Hinweiszeile mit Info-Icon, dem Text „Mealie nicht verbunden“ und einem Text-Button „Einstellungen“, der den Einstellungen-Screen öffnet. Eigene Artikel werden normal angezeigt; kein Mealie-Aufruf findet statt.
- [x] Nach dem Speichern eines Tokens in den Einstellungen und Zurückkehren lädt der Einkaufen-Screen die Mealie-Liste ohne weiteres Zutun.

### EKL-008: Mealie-Artikel abhaken und zurückholen
**Status:** done
**Priority:** 8
**Blocked by:** EKL-007
**Description:** Als Nutzer möchte ich Mealie-Artikel mit derselben Geste wie eigene Artikel abhaken und versehentlich Abgehaktes zurückholen, damit ich beim Einkaufen nicht die Mealie-Web-UI brauche.

**Acceptance Criteria:**
- [x] Tap auf eine Mealie-Zeile entfernt sie sofort aus ihrer Gruppe (optimistisch) und sendet das Abhaken an Mealie (`PUT /api/households/shopping/items/{itemId}` mit `checked: true`, restliche Felder unverändert). Der Untertitel-Zähler sinkt sofort.
- [x] Am Ende der Liste erscheint ein Abschnitt „Abgehakt (n)“ mit n = Anzahl abgehakter Mealie-Artikel, standardmäßig eingeklappt; der Abschnitt fehlt, wenn n = 0. Tap auf die Abschnitts-Überschrift klappt ihn auf oder zu (Chevron-Icon zeigt den Zustand). Der Auf-/Zu-Zustand bleibt innerhalb einer App-Sitzung erhalten.
- [x] Aufgeklappt zeigt der Abschnitt die abgehakten Mealie-Artikel als Zeilen mit angehakter Checkbox, gedämpfter Textfarbe und `display`-Text, ohne Gruppierung, in der Reihenfolge von Mealie.
- [x] Tap auf eine abgehakte Zeile holt den Artikel sofort zurück in seine Gruppe (optimistisch) und sendet `checked: false` an Mealie.
- [x] Schlägt ein Abhaken oder Zurückholen fehl (HTTP-Fehler, Timeout), springt der Artikel in seinen vorherigen Zustand zurück und ein Toast „Mealie: Änderung fehlgeschlagen“ erscheint.
- [x] Nach einem erfolgreichen Abhaken zeigt die Mealie-Web-UI den Artikel als abgehakt (manueller Test).
- [x] Eigene Artikel erscheinen nie im Abschnitt „Abgehakt“; sie werden weiterhin durch Tap in den Vorrat zurückgeschickt.

### EKL-009: Mealie-Cache und Fehlerzustand auf dem Einkaufen-Screen
**Status:** done
**Priority:** 9
**Blocked by:** EKL-008
**Description:** Als Nutzer möchte ich meine Mealie-Artikel auch sehen, wenn Mealie gerade nicht erreichbar ist (z. B. im Supermarkt-Keller), damit die Übersicht beim Einkaufen nicht plötzlich schrumpft.

**Acceptance Criteria:**
- [x] Jede erfolgreich geladene Mealie-Liste wird lokal (SQLite) mit Zeitstempel gespeichert und ersetzt den vorherigen Cache. Der Cache überlebt App-Neustarts.
- [x] Beim Öffnen des Einkaufen-Tabs werden die gecachten Mealie-Artikel sofort angezeigt, noch bevor die Antwort des Neuladens da ist; die Antwort ersetzt sie dann ohne sichtbares Flackern.
- [x] Schlägt das Laden fehl (Netzwerkfehler, Timeout, HTTP 5xx), bleiben die gecachten Artikel sichtbar und direkt unter der Top-Bar erscheint ein Banner mit Warn-Icon und dem Text „Mealie nicht erreichbar · Stand HH:MM“ (Zeit des letzten erfolgreichen Ladens; bei anderem Tag „Stand DD.MM. HH:MM“). Das Banner enthält einen Text-Button „Erneut versuchen“.
- [x] Antwortet Mealie mit HTTP 401, lautet der Banner-Text „Mealie-Token ungültig“ mit Text-Button „Einstellungen“.
- [x] Solange das Banner sichtbar ist, sind Mealie-Zeilen tappbar-deaktiviert (Checkbox ausgegraut, `disabled`), ein Tap zeigt den Toast „Offline: Mealie-Artikel können gerade nicht geändert werden“; eigene Artikel bleiben voll bedienbar.
- [x] Ein erfolgreiches Neuladen (Pull-to-Refresh, „Erneut versuchen“, Rückkehr in den Vordergrund) entfernt das Banner und aktiviert die Mealie-Zeilen wieder.
- [x] Schlägt das Laden fehl und es gibt keinen Cache, erscheint nur das Banner; die Liste zeigt eigene Artikel bzw. den Leerzustand.
- [x] Löschen des Tokens in den Einstellungen löscht auch den Mealie-Cache; der Einkaufen-Screen zeigt danach nur eigene Artikel und die Hinweiszeile „Mealie nicht verbunden“.

### EKL-010: „Alles abhaken“ inklusive Mealie-Artikel
**Status:** done
**Priority:** 10
**Blocked by:** EKL-005, EKL-008
**Description:** Als Nutzer möchte ich nach dem Einkauf mit „Alles abhaken“ auch alle offenen Mealie-Artikel abhaken, damit ich nicht zwei Handgriffe brauche.

**Acceptance Criteria:**
- [x] Der Dialog „Alles abhaken?“ nennt beide Zahlen, jede nur wenn größer 0, jeweils als eigener Satz: „n eigene Artikel wandern zurück in den Vorrat.“ (Singular: „1 eigener Artikel wandert zurück in den Vorrat.“) und „m Mealie-Artikel werden abgehakt.“ (Singular: „1 Mealie-Artikel wird abgehakt.“).
- [x] „Abhaken“ entfernt alle eigenen Artikel von der Liste und sendet für alle offenen Mealie-Artikel ein Abhaken an Mealie (Bulk-Update über `PUT /api/households/shopping/items` mit einem Array aller Artikel, `checked: true`). Die Mealie-Artikel wandern in den Abschnitt „Abgehakt“.
- [x] Schlägt der Mealie-Aufruf fehl, bleiben die eigenen Artikel entfernt, die Mealie-Artikel kehren in ihre Gruppen zurück und ein Toast „Mealie: Abhaken fehlgeschlagen“ erscheint.
- [x] Ist Mealie im Fehlerzustand (Banner aus EKL-009 sichtbar) oder kein Token hinterlegt, nennt der Dialog nur die eigenen Artikel und hakt nur diese ab.
- [x] Die Action „Alles abhaken“ ist sichtbar, sobald mindestens ein eigener oder offener Mealie-Artikel vorhanden ist.

### EKL-011: Wochenplan anzeigen
**Status:** done
**Priority:** 11
**Blocked by:** EKL-006
**Description:** Als Nutzer möchte ich den Mealie-Wochenplan der aktuellen Woche in der App sehen und in andere Wochen blättern, damit ich weiß, was wir kochen und was ich einkaufen muss.

**Acceptance Criteria:**
- [x] Der Wochenplan-Screen zeigt oben eine Wochen-Navigation: Pfeil links, Text „KW n · DD.MM.–DD.MM.“ (Montag bis Sonntag), Pfeil rechts; die Pfeile haben `a11y-label` „Vorherige Woche“ / „Nächste Woche“. Beim Öffnen ist die Kalenderwoche des heutigen Tages gewählt. Ein Tap auf den Wochen-Text springt zur aktuellen Woche zurück.
- [x] Beim Öffnen des Tabs, beim Wechsel der Woche, beim Zurückkehren der App in den Vordergrund und per Pull-to-Refresh lädt die App die Einträge der gewählten Woche (`GET /api/households/mealplans?start_date=<Montag>&end_date=<Sonntag>&perPage=100`). Während des Ladens einer Woche ohne Cache zeigt der Inhaltsbereich einen zentrierten `native:activity-indicator`.
- [x] Für jeden der 7 Tage erscheint ein Abschnitt mit Überschrift „<Wochentag ausgeschrieben>, DD.MM.“ (z. B. „Montag, 21.09.“). Der heutige Tag ist hervorgehoben (Überschrift in Primärfarbe und Zusatz „Heute“).
- [x] Jeder Eintrag ist eine `native:list-item`-Zeile mit dem Mahlzeitentyp als Overline in Deutsch (breakfast → „Frühstück“, lunch → „Mittag“, dinner → „Abend“, side → „Beilage“, snack → „Snack“, drink → „Getränk“, dessert → „Dessert“), dem Rezeptnamen als Headline und dem Rezeptbild als quadratisches Leading-Image (`/api/media/recipes/{recipeId}/images/min-original.webp`); ohne Bild ein Platzhalter-Icon. Einträge ohne Rezept zeigen ihren `title` als Headline und `text` als Supporting-Text, ohne Bild.
- [x] Einträge eines Tages sind sortiert: Frühstück, Mittag, Abend, Beilage, Snack, Getränk, Dessert.
- [x] Ein Tag ohne Einträge zeigt unter seiner Überschrift eine Zeile in gedämpfter Farbe „Nichts geplant“.
- [x] Tap auf einen Eintrag mit Rezept öffnet `https://mealie.example.test/g/home/r/<slug>` im System-Browser (Plugin `nativephp/mobile-browser`). Einträge ohne Rezept reagieren nicht auf Tap.
- [x] Ist kein Token hinterlegt, zeigt der Screen statt der Tage einen Leerzustand mit Kalender-Icon, dem Text „Mealie nicht verbunden“ und einem Button „Zu den Einstellungen“.

### EKL-012: Wochenplan-Cache und Fehlerzustand
**Status:** done
**Priority:** 12
**Blocked by:** EKL-011
**Description:** Als Nutzer möchte ich den zuletzt geladenen Wochenplan auch ohne Verbindung sehen, damit ich im Laden nachschauen kann, wofür ich einkaufe.

**Acceptance Criteria:**
- [x] Jede erfolgreich geladene Woche wird lokal mit Zeitstempel gespeichert, pro Wochenbereich ein Eintrag; ein erneutes Laden derselben Woche ersetzt ihn.
- [x] Beim Wechsel auf eine Woche mit Cache werden die gecachten Einträge sofort angezeigt und nach erfolgreichem Neuladen ersetzt.
- [x] Schlägt das Laden fehl, bleiben gecachte Einträge sichtbar und unter der Wochen-Navigation erscheint das Banner „Mealie nicht erreichbar · Stand HH:MM“ mit „Erneut versuchen“; bei HTTP 401 „Mealie-Token ungültig“ mit „Einstellungen“ (gleiche Optik wie EKL-009).
- [x] Schlägt das Laden für eine Woche ohne Cache fehl, zeigt der Inhaltsbereich das Banner und darunter einen Leerzustand mit Warn-Icon und dem Text „Wochenplan konnte nicht geladen werden“.
- [x] Löschen des Tokens löscht alle gecachten Wochen.

### EKL-013: Automatisierte Tests der Kern-Flows
**Status:** done
**Priority:** 13
**Blocked by:** EKL-004, EKL-005, EKL-009, EKL-010, EKL-012
**Description:** Als Entwickler möchte ich die Kern-Flows der vier Screens automatisiert prüfen, damit Änderungen am Katalog, an der Gruppierung oder an der Mealie-Anbindung nicht unbemerkt etwas kaputt machen.

**Acceptance Criteria:**
- [x] Ein Pest-Test besucht den Vorrat, tippt einen Artikel an und prüft, dass er im Vorrat verschwindet und auf dem Einkaufen-Screen in der richtigen Gruppe erscheint; ein weiterer Test prüft die Suche (Treffer, Groß-/Kleinschreibung, Leerzustand „Keine Treffer für …“).
- [x] Ein Pest-Test prüft „Alles abhaken“: Dialog-Text mit korrekten Zahlen, Leerzustand danach.
- [x] Ein Pest-Test füttert den Einkaufen-Screen über `Http::fake()` mit einer Mealie-Fixture (JSON-Datei mit mindestens: ein Artikel mit Label „Haushalt“, einer mit „Tiefkühlware“, einer mit einem unbekannten Label „Asia-Laden“, einer ohne Label, einer abgehakt) und prüft: „Haushalt“ verschmilzt mit der Katalog-Gruppe, „Tiefkühlware“ landet in „Tiefkühl“, „Asia-Laden“ erscheint als eigene Gruppe hinter den Katalog-Gruppen, der Artikel ohne Label unter „Sonstiges“, der abgehakte nur im Abschnitt „Abgehakt (1)“.
- [x] Ein Pest-Test prüft das Abhaken eines Mealie-Artikels: der `PUT`-Aufruf enthält `checked: true` für die richtige Artikel-ID; bei gefaktem HTTP 500 kehrt der Artikel zurück.
- [x] Ein Pest-Test prüft den Fehlerzustand: mit gefülltem Cache und gefaktem Netzwerkfehler erscheint das Banner mit „Stand“, die gecachten Artikel bleiben sichtbar.
- [x] Ein Pest-Test prüft den Einstellungen-Screen mit `Native::fakeBridge()`: Speichern ruft `SecureStorage.Set` mit dem eingegebenen Wert auf; Status „Kein Token hinterlegt“ bei `not_found`, „Gerät gesperrt, Token nicht lesbar“ bei `unavailable`.
- [x] Ein Pest-Test prüft den Wochenplan mit Fixture: 7 Tagesüberschriften, deutsche Mahlzeitentyp-Labels, „Nichts geplant“ für leere Tage, Sortierung innerhalb eines Tages.
- [x] Ein Architektur-Test stellt sicher, dass alle Klassen unter `App\NativeComponents` von `NativeComponent` erben und `render` implementieren.

### EKL-014: README mit Setup, Build und Betrieb
**Status:** done
**Priority:** 14
**Blocked by:** EKL-006
**Description:** Als Entwickler möchte ich in einer README nachlesen, wie ich die App baue, das Token hinterlege und den Katalog pflege, damit ich das in einem halben Jahr noch kann.

**Acceptance Criteria:**
- [x] Die README (Deutsch) beschreibt: Voraussetzungen (PHP 8.5, Composer, Android Studio/SDK), `composer install`, `.env` anlegen, `php artisan native:install`, `php artisan native:run android`, Hot-Reload mit `--watch`.
- [x] Sie erklärt, wo das Mealie-Token erzeugt wird (Mealie → Profil → API-Tokens) und dass es in der App unter Einstellungen eingegeben wird, nie in `.env`.
- [x] Sie erklärt die Android-Signing-Variablen und dass Keystore und Passwörter außerhalb des Repos bleiben (`credentials/` ist gitignored).
- [x] Sie erklärt, wie man Katalog-Artikel und Label-Aliase ändert (Konfigurationsdateien) und dass Katalogänderungen ein App-Update erfordern.
- [x] Sie nennt die Struktur des SecureStorage-Plugins und dass Kotlin-Änderungen einen neuen `native:run` erfordern, PHP-Änderungen nicht.

## 4. Funktionale Anforderungen

**Katalog und eigene Liste**
- FR-1: Der Katalog besteht aus den 8 Gruppen und 113 Artikeln aus Anhang A in genau dieser Reihenfolge und ist im Code konfiguriert.
- FR-2: Jeder Katalog-Artikel ist zu jedem Zeitpunkt entweder „im Vorrat“ oder „auf der Liste“; der Zustand liegt lokal in SQLite und überlebt App-Neustarts und -Updates.
- FR-3: Tap auf einen Vorrat-Artikel setzt ihn auf die Liste; Tap auf einen eigenen Listen-Artikel setzt ihn in den Vorrat. Beides ohne Bestätigung und ohne Verzögerung.
- FR-4: Beide Screens gruppieren nach Warengruppe in Katalogreihenfolge, sortieren Artikel innerhalb der Gruppe in Katalogreihenfolge und blenden leere Gruppen aus.
- FR-5: Gespeicherte Artikel-IDs ohne Katalog-Eintrag werden ignoriert und nicht gezählt.
- FR-6: Der Vorrat ist per Suchfeld live filterbar (Teilstring, Groß-/Kleinschreibung ignoriert, getrimmt); Gruppen ohne Treffer werden ausgeblendet.

**Mealie-Verbindung**
- FR-7: Die Mealie-URL und die Listen-ID sind in der Konfiguration hinterlegt; das API-Token liegt ausschließlich im Secure Storage des Geräts.
- FR-8: Token-Lesen erfolgt immer über `read()` mit Auswertung der Status Found / NotFound / Unavailable / Failed; „Unavailable“ wird nie als „kein Token“ behandelt.
- FR-9: Alle Mealie-Aufrufe senden `Authorization: Bearer <Token>` und haben ein Timeout von 10 Sekunden.
- FR-10: Die Einstellungen erlauben Token speichern, Token löschen und Verbindung testen; das Token wird nie im Klartext angezeigt.

**Einkaufs-Übersicht**
- FR-11: Der Einkaufen-Screen zeigt eigene Listen-Artikel und offene Mealie-Artikel in einer Liste, gruppiert nach Warengruppe.
- FR-12: Ein Mealie-Label wird in dieser Reihenfolge zugeordnet: exakter Namenstreffer auf eine Katalog-Gruppe; Alias-Tabelle (Anhang B); sonst eigene Gruppe mit dem Label-Namen. Artikel ohne Label gehören zur Gruppe „Sonstiges“.
- FR-13: Katalog-Gruppen erscheinen zuerst in Katalogreihenfolge, zusätzliche Label-Gruppen danach alphabetisch. Innerhalb einer Gruppe stehen eigene Artikel vor Mealie-Artikeln.
- FR-14: Tap auf einen offenen Mealie-Artikel setzt ihn in Mealie auf `checked: true`; Tap auf einen abgehakten Mealie-Artikel setzt ihn auf `checked: false`. Beides optimistisch mit Rücknahme und Toast bei Fehler.
- FR-15: Abgehakte Mealie-Artikel stehen in einem standardmäßig eingeklappten Abschnitt „Abgehakt (n)“ am Ende der Liste.
- FR-16: „Alles abhaken“ verlangt eine Bestätigung, nennt die Anzahl eigener und Mealie-Artikel getrennt und hakt beide Mengen ab; im Mealie-Fehlerzustand nur die eigenen.
- FR-17: Die Mealie-Liste wird beim Öffnen des Tabs, bei Rückkehr in den Vordergrund und per Pull-to-Refresh geladen.
- FR-18: Die zuletzt erfolgreich geladene Mealie-Liste wird lokal mit Zeitstempel gecacht und bei Fehlern angezeigt; ein Banner nennt den Fehler und den Stand; Mealie-Artikel sind dann nicht änderbar.
- FR-19: Ohne Token zeigt der Einkaufen-Screen eine Hinweiszeile mit Link zu den Einstellungen und macht keine Mealie-Aufrufe.

**Wochenplan**
- FR-20: Der Wochenplan zeigt eine Kalenderwoche Montag bis Sonntag, standardmäßig die aktuelle, mit Blättern um jeweils eine Woche und Rücksprung zur aktuellen Woche.
- FR-21: Pro Tag erscheinen alle Einträge, sortiert nach Mahlzeitentyp in der Reihenfolge Frühstück, Mittag, Abend, Beilage, Snack, Getränk, Dessert, mit deutschem Typ-Label, Rezeptname und Bild; Einträge ohne Rezept zeigen Titel und Text.
- FR-22: Tap auf einen Rezept-Eintrag öffnet die Rezeptseite in Mealie im System-Browser.
- FR-23: Geladene Wochen werden pro Wochenbereich gecacht; Fehler- und Token-Zustände verhalten sich wie auf dem Einkaufen-Screen.
- FR-24: Löschen des Tokens löscht alle Mealie-Caches (Liste und Wochen).

**Allgemein**
- FR-25: Alle sichtbaren Texte sind Deutsch; Datumsformate sind `DD.MM.` bzw. `DD.MM.YYYY`, Uhrzeiten `HH:MM`.
- FR-26: Die App folgt dem System-Dark-Mode und verwendet ausschließlich Theme-Farbklassen; Icons kommen ausschließlich aus `native:icon`, keine Emoji.

## 5. Non-Goals (Out of Scope)

- Kein iOS-Build und kein Test der Swift-Plugin-Hälfte in dieser PRD (Code bleibt iOS-fähig, aber ungetestet).
- Kein Hinzufügen, Bearbeiten oder Löschen von Artikeln der Mealie-Liste aus der App heraus (nur abhaken/enthaken).
- Kein Übertragen eigener Katalog-Artikel nach Mealie.
- Kein Bearbeiten des Wochenplans (kein Anlegen, Verschieben, Löschen von Einträgen).
- Keine Rezept-Detailansicht in der App; Rezepte öffnen im Browser.
- Keine UI zum Zuordnen von Mealie-Labels zu Gruppen; Aliase werden im Code gepflegt.
- Kein Bearbeiten des Katalogs in der App (keine Freitext-Artikel, keine Mengen, keine Notizen, keine Favoriten).
- Kein Sync der eigenen Liste zwischen Geräten, kein Server, kein Benutzerkonto.
- Keine Push-Benachrichtigungen, keine Widgets, kein Tablet-Layout, keine Querformat-Unterstützung.
- Keine Änderungen am alten Server oder Repository `~/Code/shopping-list`; Abschalten ist ein separater Schritt.
- Keine Migration des alten Server-Zustands (welche Artikel auf der Liste waren) in die neue App.

## 6. Design & Frontend

**Screens und Navigation**
- Drei Root-Tabs in einem `TabsLayout` (NavBar + TabBar): `/` Einkaufen, `/vorrat` Vorrat, `/wochenplan` Wochenplan. Tab-Icons: Einkaufswagen, Archiv/Inventar, Kalender. Labels immer sichtbar.
- Gepushter Screen `/einstellungen` in einem `StackLayout` (NavBar mit Zurück, keine TabBar), erreichbar über die Zahnrad-Action in der NavBar des Einkaufen-Screens sowie über die „Einstellungen“-Links in Hinweiszeile, Banner und Wochenplan-Leerzustand.
- NavBar-Titel: „Einkaufen“, „Vorrat“, „Wochenplan“, „Einstellungen“. Untertitel: Einkaufen „n Artikel“ (nur bei n > 0), Vorrat „Tippe auf einen Artikel zum Hinzufügen“ bzw. „n auf der Liste“.

**Zustände pro Screen**

| Screen | Leer | Laden | Fehler | Kein Token |
|---|---|---|---|---|
| Einkaufen | Einkaufswagen-Icon, „Liste ist leer.“, „Tippe auf den Vorrat-Tab, um Artikel hinzuzufügen.“ | Erste Sitzung: Zeile mit Spinner „Mealie wird geladen…“; danach nur nativer Pull-to-Refresh-Spinner; eigene Artikel immer sofort | Banner „Mealie nicht erreichbar · Stand HH:MM“ + „Erneut versuchen“ bzw. „Mealie-Token ungültig“ + „Einstellungen“; gecachte Mealie-Zeilen deaktiviert | Hinweiszeile „Mealie nicht verbunden“ + „Einstellungen“; eigene Artikel normal |
| Vorrat | Häkchen-Icon „Alles auf der Liste.“; bei Suche: Such-Icon „Keine Treffer für „…“.“ | keins (lokal) | keins | n/a |
| Wochenplan | pro Tag „Nichts geplant“ | zentrierter Spinner, wenn kein Cache für die Woche | Banner wie Einkaufen; ohne Cache zusätzlich Warn-Icon „Wochenplan konnte nicht geladen werden“ | Kalender-Icon „Mealie nicht verbunden“ + Button „Zu den Einstellungen“ |
| Einstellungen | Status „Kein Token hinterlegt“ | Ladezustand auf „Verbindung testen“ | Statustexte „Token ungültig“ / „Mealie nicht erreichbar“ / „Gerät gesperrt, Token nicht lesbar“ / „Fehler beim Lesen: <Code>“ | ist der Normalzustand |

**Komponenten**
- Listen: `native:list` mit `separator` und `on-refresh` (Einkaufen, Wochenplan) bzw. ohne Refresh (Vorrat). Gruppen-Überschriften als `native:list-section` bzw. `native:text` in kleiner Großbuchstaben-Schrift, gedämpfte Farbe (`text-theme-on-surface-variant`).
- Zeilen: `native:list-item`. Einkaufen: `leadingCheckbox` (offen: leer, abgehakt: angehakt + gedämpfter Text), Headline, optional Supporting (Rezeptname), Mealie-Zeilen mit Trailing-Icon (Restaurant/Besteck). Vorrat: Headline + `trailingIcon` Plus. Wochenplan: `overline` Mahlzeitentyp, `headline` Rezeptname, `supporting` Text, `leadingImage` Rezeptbild.
- Eingaben: `native:outlined-text-input` für Suche (Lupen-Icon vorn, X-Button hinten, `native:model.debounce.250ms`) und Token (`secure`).
- Buttons: `native:button` primär („Speichern“, „Verbindung testen“, „Zu den Einstellungen“), `variant="secondary"` für Text-Buttons in Banner/Hinweiszeile, `variant="destructive"` für „Token löschen“.
- Dialoge und Toasts: `Dialog::alert` für Bestätigungen, `Dialog::toast` für Fehlermeldungen und Erfolgsmeldungen.
- Banner und Hinweiszeile: `native:row` mit Icon, Text und Text-Button, Hintergrund `bg-theme-surface`, Warnfarbe für das Icon im Fehlerfall, direkt unter der NavBar, volle Breite.
- Abschnitt „Abgehakt (n)“: tappbare Überschrift (`native:pressable` mit `native:row`: Text links, Chevron rechts).
- Wochen-Navigation: `native:row` mit zwei Icon-Buttons (Chevron links/rechts, `a11y-label`) und zentriertem tappbaren Text.
- Icons ausschließlich über `native:icon` mit den generierten Enums (`App\Icons\Android`, `App\Icons\Ios`), nie Emoji.

**Interaktion**
- Tap auf eine ganze Zeile ist die einzige Geste zum Ändern eines Artikels; keine Swipe-Actions, kein Long-Press.
- Alle Änderungen sind optimistisch: die Zeile wechselt sofort, Fehler nehmen den Wechsel zurück und zeigen einen Toast.
- „Alles abhaken“ verlangt eine Bestätigung im nativen Dialog; kein Undo.
- Pull-to-Refresh auf Einkaufen und Wochenplan; Rückkehr in den Vordergrund lädt beide neu.
- Keine Ein-/Ausblende-Animationen jenseits der nativen Listen-Standards.

**Visuelle Richtung**
- Native Material-3-Optik über Edge, nicht Nachbau der Web-Optik. Ruhig, dicht, textlastig: eine Zeile pro Artikel, keine Karten pro Artikel.
- Theme in `config/native-ui.php`: Primär Indigo `#4F46E5` (hell) / `#818CF8` (dunkel), Rest bei den Paket-Standards (Surface/Background neutral). Destruktiv rot aus dem Theme. Systemschrift.
- Gruppen-Überschriften als kleine Großbuchstaben-Zeilen, Mealie-Kennzeichnung nur durch Trailing-Icon und Supporting-Text, keine Farbcodierung.
- Leerzustände zentriert, Icon groß und gedämpft, ein Satz Haupttext, optional ein kleinerer Hinweissatz.

**Responsive & Accessibility**
- Nur Hochformat, nur Smartphone. Inhalte respektieren Safe Areas und enden nicht hinter der TabBar.
- Jeder Icon-only-Button hat ein `a11y-label`. Checkbox-Zeilen tragen den Artikelnamen als zugängliches Label; Mealie-Zeilen ergänzen „aus Mealie“.
- Textgrößen aus dem Theme, kein fester Pixelwert unter 14; Kontraste aus den Theme-Tokens.

## 7. Technische Überlegungen

- **Stack:** Laravel 13, `nativephp/mobile` ^4.5, `nativephp/mobile-ui` ^0.3, `nativephp/mobile-browser` ^1.0 (Rezept öffnen), Pest 4, PHP 8.5 auf dem Gerät (`nativephp.lock`). Start aus dem `nativephp/mobile-starter`-Template wie im Kitchen Sink.
- **Referenz-Implementierung:** `~/Herd/kitchen-sink`. Muster für Layouts (`app/Layouts/TabsLayout.php`, `StackLayout.php`), Routen (`routes/mobile.php` mit `Route::nativeGroup`), Screens (`app/NativeComponents/Vault.php` für SecureStorage, `Notes/NoteList.php` für `native:list` mit Refresh), Tests (`tests/Feature/DeviceLabTest.php`, `tests/Unit/ArchTest.php`).
- **SecureStorage-Plugin:** `plugins/secure-storage` aus dem Kitchen Sink kopieren (composer.json, nativephp.json, ServiceProvider, Kotlin, Swift), Namespaces umbenennen (`Ben182\SecureStorage`, `de.ben182.securestorage`, `ben182/secure-storage`), Pfade in `nativephp.json` anpassen, Path-Repository in `composer.json`, `php artisan vendor:publish --tag=nativephp-plugins-provider`, Provider in `NativeServiceProvider::plugins()` eintragen, `php artisan native:plugin:validate` und `native:plugin:list` prüfen. Ohne Eintrag in der Allowlist liefert jeder Aufruf `BRIDGE_UNAVAILABLE`.
- **Token-Zugriff:** Facade `Native\Mobile\Facades\SecureStorage`, Schlüssel `einkaufsliste.mealie-token`, Schreiben mit `SecureStorageAccessibility::AfterFirstUnlock`. Lesen nur über `read()` und `SecureStorageStatus`.
- **Bekannte Risiken aus dem PoC:** `androidx.security:security-crypto:1.1.0-alpha06` ist eine Alpha-Abhängigkeit; die Swift-Hälfte wurde nie gebaut; `config/nativephp.php` aus dem Paket enthält den Schlüssel `runtime` dreimal (beim Publizieren bereinigen); `ANDROID_KEYSTORE_PASSWORD` fällt nicht unter `cleanup_env_keys` (ergänzen).
- **Mealie-API (v3.27.0):** Liste `GET /api/households/shopping/lists/{id}` (Feld `listItems`, je Artikel `display`, `checked`, `label.name`, `position`, `recipeReferences[]`, `createdAt`); Artikel ändern `PUT /api/households/shopping/items/{itemId}` (kompletter Artikel mit geändertem `checked`); Bulk `PUT /api/households/shopping/items` (Array); Wochenplan `GET /api/households/mealplans?start_date&end_date&perPage=100` (Feld `items[]`, je Eintrag `date`, `entryType`, `title`, `text`, `recipe.name`, `recipe.slug`, `recipe.id`, `recipe.image`); Nutzer `GET /api/users/self` (`username`); Version `GET /api/app/about` (`version`). Rezeptnamen für Supporting-Text: `recipeReferences[].recipe.name`, falls im Payload enthalten, sonst über `GET /api/recipes/{recipeId}` nachladen und pro Sitzung cachen. Rezept-URL im Browser: `{MEALIE_URL}/g/home/r/{slug}`. Rezeptbild: `{MEALIE_URL}/api/media/recipes/{recipeId}/images/min-original.webp`.
- **Token für Entwicklung und Test:** liegt in `~/Code/mealie/.env` (`MEALIE_TOKEN`), wird in der App über die Einstellungen eingegeben und nie ins Repo geschrieben.
- **Datenhaltung:** SQLite auf dem Gerät. Tabellen: Listen-Zustand (Artikel-ID, Zeitstempel), Mealie-Cache (Schlüssel, JSON, Zeitstempel). Kein `db:seed` auf dem Gerät; alle Daten kommen aus Migrationen oder aus der App. Migrationen müssen auf frischer Installation und auf Update sicher laufen und dürfen den Listen-Zustand nie löschen.
- **Katalog und Aliase:** je eine Konfigurationsdatei (Gruppen mit ID und Name, Artikel mit ID, Name, Gruppen-ID; Alias-Tabelle Label-Name → Gruppen-ID). Vergleich der Label-Namen exakt nach `trim()`.
- **Wochenberechnung:** Wochenstart Montag, Kalenderwoche nach ISO 8601, Zeitzone des Geräts. Datumsformatierung Deutsch.
- **Netzwerk:** Laravel `Http`-Facade, Timeout 10 s, keine Retries im Hintergrund. Fehlerklassifizierung: 401 → Token ungültig; alles andere → nicht erreichbar.
- **Build:** `php artisan native:install`, `php artisan native:run android` (Build-Kommandos gibt der Implementierer als Anweisung aus, führt sie nicht selbst aus). Kotlin-Änderungen erfordern einen neuen Build, PHP-Änderungen nicht.
- **Code-Konventionen aus dem Kitchen Sink:** Tailwind-Klassen statt `style`, `bg-theme-*`/`text-theme-*` für Farben, Pint nach PHP-Änderungen, Blade-Sub-Komponenten ohne Slot (Edge sammelt in Ausführungsreihenfolge).

## 8. Testentscheidungen

- **Seam:** Das NativePHP-Testharness `Native\Mobile\Testing\Native` gegen die Screen-Klassen (`Native::visit('/…')`, `tap`, `input`, `assertSee`, `assertDontSee`, `assertSet`). Das ist der höchste vorhandene Seam; es wird kein neuer eingeführt.
- **Externe Grenzen:** Mealie über `Http::fake()` mit JSON-Fixtures unter `tests/Fixtures/mealie/` (Liste, Wochenplan, `users/self`, `app/about`, Fehlerantworten 401/500, Verbindungsfehler). SecureStorage über `Native::fakeBridge()->respondTo('SecureStorage.Get' | 'Set' | 'Delete', …)` mit `assertNativeCalled` und Payload-Closure.
- **Was einen guten Test ausmacht:** Erwartungswerte kommen aus dieser PRD (Gruppennamen, Reihenfolge, Texte wie „Abgehakt (1)“, „Keine Treffer für „xyz“.“), nicht aus dem Code. Es wird nur geprüft, was ein Nutzer sieht oder was über die Netzwerk-/Bridge-Grenze geht; keine Assertions auf private Eigenschaften, keine direkten Datenbank-Abfragen zur Verifikation, außer für die Persistenz über Neustarts (dann über einen zweiten `Native::visit`).
- **Prior Art:** `~/Herd/kitchen-sink/tests/Feature/DeviceLabTest.php` (fakeBridge + visit + tap + assertNativeCalled), `tests/Feature/NotesTest.php` (Listen-Interaktion), `tests/Unit/ArchTest.php` (Architektur-Regel). `Http::fake()` ist im PoC nicht vorhanden und kommt in dieser App neu dazu.
- **Umgebung:** `phpunit.xml` mit `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `NATIVEPHP_RUNNING=false` wie im Kitchen Sink.

## 9. Erfolgskriterien

- Ein Artikel lässt sich aus dem Vorrat mit einem Tap auf die Liste setzen und mit einem Tap wieder abhaken; die eigene Liste funktioniert im Flugmodus vollständig.
- Ein Einkauf mit eigenen und Mealie-Artikeln lässt sich in der App ohne Öffnen der Mealie-Web-UI abarbeiten; jedes Abhaken ist in Mealie innerhalb von 2 Sekunden sichtbar.
- Ein in Mealie neu angelegtes Label erscheint beim nächsten Laden als eigene Gruppe, ohne App-Update.
- Bei getrennter Verbindung zeigt der Einkaufen-Screen weiterhin alle zuletzt geladenen Mealie-Artikel mit Zeitstempel.
- Der Wochenplan der aktuellen Woche ist nach Öffnen des Tabs mit höchstens einem Tap (Tab) erreichbar; jede andere Woche mit höchstens drei Taps.
- Alle Pest-Tests aus EKL-013 laufen grün; die App ist als signierte Android-APK auf Bens Gerät installiert.

## 10. Offene Fragen

Keine. Alle Entscheidungen wurden in Phase 1 getroffen: Android zuerst; lokale Datenhaltung ohne Sync; URL im Code, Token im Secure Storage über das kopierte Plugin; zusammengeführte Einkaufs-Übersicht mit dynamischer Label-Zuordnung (exakter Treffer → Alias → eigene Gruppe); Abhaken in Mealie inklusive „Alles abhaken“; Wochenplan Mo–So mit Blättern; Cache- und Fehlerverhalten wie in EKL-009/012.

---

## Anhang A: Katalog (1:1 aus `~/Code/shopping-list/shared/items.ts`)

Gruppen in Reihenfolge (ID → Name):
1. `obst-gemuese` → Obst & Gemüse
2. `brot` → Brot & Backwaren
3. `kuehlregal` → Kühlregal
4. `tiefkuehl` → Tiefkühl
5. `lebensmittel` → Lebensmittel
6. `getraenke` → Getränke
7. `haushalt` → Haushalt
8. `drogerie` → Drogerie

Artikel in Reihenfolge (ID → Name):

**Obst & Gemüse** (`obst-gemuese`): `aepfel` → Äpfel · `bananen` → Bananen · `beeren` → Beeren · `zitronen` → Zitronen · `avocado` → Avocado · `tomaten` → Tomaten · `gurken` → Gurken · `salat` → Salat · `paprika` → Paprika · `spinat` → Spinat · `brokkoli` → Brokkoli · `champignons` → Champignons · `zwiebeln` → Zwiebeln · `knoblauch` → Knoblauch · `ingwer` → Ingwer · `karotten` → Karotten · `kartoffeln` → Kartoffeln

**Brot & Backwaren** (`brot`): `brot` → Brot · `broetchen` → Brötchen · `toast` → Toast · `wraps` → Wraps · `hot-dog-broetchen` → Hot Dog Brötchen

**Kühlregal** (`kuehlregal`): `hafermilch` → Hafermilch · `sojamilch` → Sojamilch · `mandelmilch` → Mandelmilch · `sojajoghurt` → Sojajoghurt · `pflanzliche-sahne` → Pflanzliche Sahne · `margarine` → Margarine · `veganer-kaese` → Veganer Käse · `veganer-frischkaese` → Veganer Frischkäse · `tofu` → Tofu · `raeuchertofu` → Räuchertofu · `tempeh` → Tempeh · `seitan` → Seitan · `hummus` → Hummus · `zaziki` → Zaziki · `vegane-wurst` → Vegane Wurst · `vegane-bratwurst` → Vegane Bratwurst · `vegane-leberwurst` → Vegane Leberwurst · `veganer-fleischsalat` → Veganer Fleischsalat · `veganer-aufstrich` → Veganer Aufstrich · `vivera-schnitzel` → Vivera Schnitzel · `veganes-schnitzel` → Veganes Schnitzel · `veganes-cordon-bleu` → Veganes Cordon Bleu · `veganer-streukaese` → Veganer Streukäse · `vegane-creme-fraiche` → Vegane Crème Fraîche · `vegane-mayonnaise` → Vegane Mayonnaise · `hafercreme` → Hafercreme

**Tiefkühl** (`tiefkuehl`): `vegane-pizza` → Vegane Pizza · `pommes` → Pommes · `kartoffelspalten` → Kartoffelspalten · `tk-gemuese` → TK-Gemüse · `tk-beeren` → TK-Beeren · `tk-spinat` → TK-Spinat · `veganes-eis` → Veganes Eis

**Lebensmittel** (`lebensmittel`): `nudeln` → Nudeln · `reis` → Reis · `linsen` → Linsen · `kichererbsen` → Kichererbsen (Dose) · `bohnen` → Bohnen (Dose) · `erbsen` → Erbsen (Dose) · `mais` → Mais (Dose) · `tomaten-dose` → Tomaten (Dose) · `passierte-tomaten` → Passierte Tomaten · `oliven` → Oliven · `ananas-dose` → Ananas (Dose) · `kokosmilch` → Kokosmilch · `mehl` → Mehl · `zucker` → Zucker · `salz` → Salz · `pfeffer` → Pfeffer · `olivenoel` → Olivenöl · `essig` → Essig · `sojasauce` → Sojasauce · `suess-sauer-sauce` → Süß-Sauer-Sauce · `barbecue-sauce` → Barbecue-Sauce · `hefeflocken` → Hefeflocken · `haferflocken` → Haferflocken · `muesli` → Müsli · `kaffee` → Kaffee · `tee` → Tee · `erdnussbutter` → Erdnussbutter · `marmelade` → Marmelade · `ahornsirup` → Ahornsirup · `schokolade` → Schokolade (vegan) · `nuesse` → Nüsse · `kekse` → Kekse · `chips` → Chips · `tortilla-chips` → Tortilla Chips · `erdnuss-flips` → Erdnussflips

**Getränke** (`getraenke`): `wasser-still` → Wasser (still) · `wasser-sprudel` → Wasser (Sprudel) · `saft` → Saft · `bier` → Bier · `wein` → Wein (vegan) · `cola` → Cola · `energy-drink` → Energy Drink

**Haushalt** (`haushalt`): `spuelmittel` → Spülmittel · `waschmittel` → Waschmittel · `weichspueler` → Weichspüler · `muellbeutel` → Müllbeutel · `kuechenrolle` → Küchenrolle · `toilettenpapier` → Toilettenpapier · `backpapier` → Backpapier · `frischhaltefolie` → Frischhaltefolie

**Drogerie** (`drogerie`): `zahnpasta` → Zahnpasta · `duschgel` → Duschgel · `shampoo` → Shampoo · `deo` → Deo · `handseife` → Handseife · `taschentuecher` → Taschentücher · `feuchttuecher-toilette` → Feuchttücher (Toilette)

Die Artikel-IDs bleiben unverändert, damit ein späterer Import des alten Zustands möglich bliebe.

## Anhang B: Alias-Tabelle Mealie-Label → Katalog-Gruppe (Startbestand)

Exakte Namenstreffer (aktuell „Obst & Gemüse“, „Getränke“, „Haushalt“) brauchen keinen Alias. Die Tabelle deckt die heute in Mealie vorhandenen abweichenden Labels ab; alles andere wird zur eigenen Gruppe.

| Mealie-Label | Katalog-Gruppe |
|---|---|
| Gemüse | Obst & Gemüse |
| Obst | Obst & Gemüse |
| Bio-Lebensmittel | Obst & Gemüse |
| Backwaren | Brot & Backwaren |
| Konditorwaren | Brot & Backwaren |
| Milchprodukte | Kühlregal |
| Fleischprodukte | Kühlregal |
| Fleisch | Kühlregal |
| Meeresfrüchte | Kühlregal |
| Tiefkühlware | Tiefkühl |
| Getreide | Lebensmittel |
| Konserven | Lebensmittel |
| Gewürze | Lebensmittel |
| Würzmittel | Lebensmittel |
| Snacks | Lebensmittel |
| Süßwaren | Lebensmittel |
| Alkohol | Getränke |

Nicht in der Tabelle und daher eigene Gruppe: „Sonstiges“ (dient gleichzeitig als Gruppe für Artikel ohne Label) sowie jedes künftig neu angelegte Label.
