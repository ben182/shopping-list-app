# Einkaufsliste

Native Android-App (NativePHP for Mobile v4 + `nativephp/mobile-ui`) für die eigene
Einkaufsliste, mit Anbindung an eine selbst gehostete Mealie-Instanz.

Ersetzt die bisherige Nuxt-Webapp: fester Artikelkatalog, lokale SQLite auf dem Gerät,
kein Server, kein Geräte-Sync. Mealie-Artikel und eigene Artikel laufen in einer
Einkaufs-Übersicht zusammen; ein dritter Tab zeigt den Mealie-Wochenplan.

Anforderungen und User Stories: `.chief/prds/default/prd.md`

## Entwicklungsumgebung

- PHP 8.4 + Composer, Laravel 13
- JDK 17 (`JAVA_HOME=$(/usr/libexec/java_home -v 17)`)
- Android SDK unter `~/Library/Android/sdk` (`ANDROID_HOME`), API 36, Build-Tools, Platform-Tools
