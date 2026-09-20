<?php

namespace App\Release;

use InvalidArgumentException;

/**
 * Die Android-Theme-Dateien entstehen nur bei `native:install` und veralten
 * still: Gradle liest `config/nativephp.php` nie, es baut, was im
 * gitignorierten `nativephp/`-Ordner liegt. Eine geänderte Primärfarbe kam
 * deshalb nie auf dem Gerät an — Alert-Knöpfe und Picker blieben schwarz.
 * Der Release-Ablauf trägt die Config-Farben darum vor jedem Build erneut ein.
 */
final class AndroidTheme
{
    /** Alles, was die Primärfarbe trägt — hell bzw. dunkel je nach Datei. */
    private const PRIMAER_EINTRAEGE = [
        'colorPrimary',
        'colorPrimaryVariant',
        'colorAccent',
        'android:colorAccent',
    ];

    public function __construct(
        private string $androidWurzel,
        private string $primaer,
        private string $primaerDunkel,
        private string $aufPrimaer,
    ) {}

    public static function ausKonfiguration(string $androidWurzel): self
    {
        return new self(
            $androidWurzel,
            (string) config('nativephp.android.theme.color_primary'),
            (string) config('nativephp.android.theme.color_primary_night'),
            (string) config('nativephp.android.theme.color_on_primary'),
        );
    }

    /**
     * @return list<string> die Dateien, die dabei anders geworden sind
     */
    public function anwenden(): array
    {
        $geaendert = [];

        foreach (['values' => $this->primaer, 'values-night' => $this->primaerDunkel] as $ordner => $primaer) {
            $pfad = $this->androidWurzel."/app/src/main/res/{$ordner}/themes.xml";

            if (! is_file($pfad)) {
                continue;
            }

            $vorher = (string) file_get_contents($pfad);
            $nachher = $this->gefaerbt($vorher, $primaer);

            if ($nachher !== $vorher) {
                file_put_contents($pfad, $nachher);
                $geaendert[] = $pfad;
            }
        }

        return $geaendert;
    }

    private function gefaerbt(string $xml, string $primaer): string
    {
        $farben = array_fill_keys(self::PRIMAER_EINTRAEGE, $primaer) + ['colorOnPrimary' => $this->aufPrimaer];

        foreach ($farben as $eintrag => $farbe) {
            $wert = $this->mitAlpha($farbe);

            $xml = (string) preg_replace_callback(
                '/(<item name="'.preg_quote($eintrag, '/').'">)[^<]*(<\/item>)/',
                fn (array $treffer): string => $treffer[1].$wert.$treffer[2],
                $xml,
            );
        }

        return $xml;
    }

    /**
     * Android will `#AARRGGBB`. Eine unbrauchbare Farbe hier abzufangen ist
     * billiger als eine APK, die sie in der Systemdialog-Farbe zeigt.
     */
    private function mitAlpha(string $farbe): string
    {
        if (preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $farbe) !== 1) {
            throw new InvalidArgumentException("„{$farbe}“ ist keine Farbe für das Android-Theme. Erwartet wird #RRGGBB oder #AARRGGBB.");
        }

        $hex = strtoupper(ltrim($farbe, '#'));

        return '#'.(strlen($hex) === 6 ? 'FF'.$hex : $hex);
    }
}
