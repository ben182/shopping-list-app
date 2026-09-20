# Einkaufsliste

Native Android-App (NativePHP for Mobile v4 + `nativephp/mobile-ui`) für die eigene
Einkaufsliste, mit Anbindung an eine selbst gehostete Mealie-Instanz.

Ersetzt die bisherige Nuxt-Webapp: fester Artikelkatalog, lokale SQLite auf dem Gerät,
kein Server, kein Geräte-Sync. Mealie-Artikel und eigene Artikel laufen in einer
Einkaufs-Übersicht zusammen; ein dritter Tab zeigt den Mealie-Wochenplan.

Anforderungen und User Stories: `.chief/prds/default/prd.md`

## Entwicklungsumgebung

- PHP 8.4 + Composer, Laravel 13
- JDK 17 (`openjdk@17` via Homebrew, keg-only)
- Android SDK: Platform 35 + 36, Build-Tools 35.0.0 + 36.0.0, Platform-Tools, NDK 27, Emulator

Die Umgebungsvariablen stehen in `~/.zshrc`:

```sh
export JAVA_HOME="/opt/homebrew/opt/openjdk@17"
export ANDROID_HOME="$HOME/Library/Android/sdk"   # Symlink auf /opt/homebrew/share/android-commandlinetools
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/emulator:$ANDROID_HOME/cmdline-tools/latest/bin:$ANDROID_HOME/platform-tools:$PATH"
```

Emulator starten: `emulator -avd Pixel_7_API_35`
