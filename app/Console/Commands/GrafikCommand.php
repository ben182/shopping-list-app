<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * Rendert App-Icon und Splash-Bilder aus den beiden SVG-Quellen in
 * `resources/grafik` nach `public/`, wo NativePHP sie beim Build abholt.
 *
 * Die fertigen PNGs liegen trotzdem im Repo: Ein Build soll nicht daran
 * scheitern, dass auf dem Rechner kein Imagick installiert ist. Das Kommando
 * ist also nur nötig, wenn sich eine der Quellen ändert.
 *
 * Es gibt genau zwei Quellen — das Motiv und das Splash-Layout. Alles andere
 * ist Einfärben und Einsetzen: Das Icon-Layout (Motiv auf voller Indigo-Fläche)
 * entsteht hier, weil es außer den Maßen nichts enthält.
 */
final class GrafikCommand extends Command
{
    private const ICON_KANTE = 1024;

    private const SPLASH_BREITE = 1280;

    private const SPLASH_HOEHE = 1920;

    /**
     * Anteil der Kantenlänge, den das Motiv auf dem Icon einnimmt. Android
     * schneidet das Adaptive Icon rund, abgerundet oder als Squircle zu; die
     * mittleren 66 % überleben jede dieser Masken. Das Motiv bleibt darunter,
     * damit auch die weiche Kante des Renderers innerhalb liegt — und weil ein
     * randvolles Icon im Launcher gedrängt wirkt.
     */
    private const MOTIV_ANTEIL = 0.62;

    /**
     * Die Farbe, in der beide Quellen gezeichnet sind. Sie steht im SVG als
     * Vorgabe, damit die Dateien allein betrachtet richtig aussehen.
     *
     * @var array<string, string>
     */
    private const HELL = [
        'motiv' => '#4F46E5',
        'hintergrund' => '#F8FAFC',
        'schrift' => '#0F172A',
    ];

    /**
     * Dieselben Rollen im dunklen Modus. `strtr()` tauscht alle drei in einem
     * Durchgang — nacheinander ginge es nicht, weil `#0F172A` einmal als
     * Schrift eingeht und einmal als Hintergrund herauskommt.
     *
     * @var array<string, string>
     */
    private const DUNKEL = [
        'motiv' => '#818CF8',
        'hintergrund' => '#0F172A',
        'schrift' => '#F8FAFC',
    ];

    protected $signature = 'grafik
        {--ziel= : Zielverzeichnis, ohne Angabe public/}';

    protected $description = 'Rendert App-Icon und Splash-Bilder aus den SVG-Quellen in resources/grafik';

    public function handle(): int
    {
        if (! extension_loaded('imagick')) {
            $this->error('Ohne die PHP-Erweiterung Imagick lässt sich kein SVG rendern.');

            return self::FAILURE;
        }

        $ziel = rtrim($this->option('ziel') ?: public_path(), DIRECTORY_SEPARATOR);
        File::ensureDirectoryExists($ziel);

        $this->schreiben($this->iconQuelle(), self::ICON_KANTE, self::ICON_KANTE, $ziel.'/icon.png', self::HELL['motiv']);

        foreach (['splash' => self::HELL, 'splash-dark' => self::DUNKEL] as $name => $farben) {
            $quelle = $this->splashQuelle($farben);

            foreach ([1 => '', 2 => '@2x', 3 => '@3x'] as $faktor => $zusatz) {
                $this->schreiben(
                    $quelle,
                    self::SPLASH_BREITE * $faktor,
                    self::SPLASH_HOEHE * $faktor,
                    $ziel."/{$name}{$zusatz}.png",
                );
            }
        }

        $this->components->info("Icon und Splash-Bilder liegen in {$ziel}.");

        return self::SUCCESS;
    }

    /**
     * Das Icon: das Motiv weiß, so groß wie es in den sicheren Bereich passt,
     * mittig auf einer randlosen Indigo-Fläche. Randlos, weil Android selbst
     * abrundet und iOS ohnehin quadratisch will.
     */
    private function iconQuelle(): string
    {
        [$breite, $hoehe] = $this->motivMasse();

        $platz = self::ICON_KANTE * self::MOTIV_ANTEIL;
        $faktor = min($platz / $breite, $platz / $hoehe);
        $x = (self::ICON_KANTE - $breite * $faktor) / 2;
        $y = (self::ICON_KANTE - $hoehe * $faktor) / 2;

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d">'
            .'<rect width="%1$d" height="%1$d" fill="%2$s"/>'
            .'<g transform="translate(%3$s %4$s) scale(%5$s)" fill="#FFFFFF">%6$s</g>'
            .'</svg>',
            self::ICON_KANTE,
            self::HELL['motiv'],
            round($x, 2),
            round($y, 2),
            round($faktor, 6),
            $this->motivInhalt(),
        );
    }

    /**
     * Das Splash-Layout mit eingesetztem Motiv, eingefärbt für einen Modus.
     *
     * @param  array<string, string>  $farben
     */
    private function splashQuelle(array $farben): string
    {
        $layout = str_replace('<!--motiv-->', $this->motivInhalt(), $this->quelle('splash.svg'));

        return strtr($layout, array_combine(self::HELL, $farben));
    }

    /**
     * Der Inhalt von `motiv.svg` ohne seine Hülle: Position, Größe und Farbe
     * bestimmt die Gruppe, in die er eingesetzt wird.
     */
    private function motivInhalt(): string
    {
        $motiv = $this->quelle('motiv.svg');

        if (preg_match('/<svg\b[^>]*>(.*)<\/svg>/s', $motiv, $treffer) !== 1) {
            throw new RuntimeException('motiv.svg hat kein <svg>-Element.');
        }

        return trim($treffer[1]);
    }

    /**
     * Breite und Höhe der viewBox von `motiv.svg`. Sie liegt auf den
     * Außenkanten der Zeichnung, taugt hier also direkt als Maß.
     *
     * @return array{float, float}
     */
    private function motivMasse(): array
    {
        if (preg_match('/viewBox="0 0 ([\d.]+) ([\d.]+)"/', $this->quelle('motiv.svg'), $treffer) !== 1) {
            throw new RuntimeException('motiv.svg hat keine viewBox, die bei 0 0 beginnt.');
        }

        return [(float) $treffer[1], (float) $treffer[2]];
    }

    private function quelle(string $dateiname): string
    {
        $pfad = resource_path('grafik/'.$dateiname);

        if (! File::exists($pfad)) {
            throw new RuntimeException("Die Quelle {$pfad} fehlt.");
        }

        return File::get($pfad);
    }

    /**
     * Rendert das SVG in genau diese Pixelmaße — skaliert wird also der Vektor,
     * nicht das fertige Bild.
     *
     * `$deckfarbe` füllt den Alphakanal auf: NativePHP weist ein Icon ab, das
     * auch nur ein durchsichtiges Pixel hat.
     */
    private function schreiben(string $svg, int $breite, int $hoehe, string $ziel, ?string $deckfarbe = null): void
    {
        $bild = new Imagick;
        $bild->setBackgroundColor(new ImagickPixel($deckfarbe ?? 'transparent'));
        $bild->readImageBlob($this->masse($svg, $breite, $hoehe), 'grafik.svg');
        $bild->setImageFormat('png');

        if ($deckfarbe !== null) {
            $bild->setImageBackgroundColor(new ImagickPixel($deckfarbe));
            $bild->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $bild->setImageFormat('png24');
        }

        $bild->writeImage($ziel);
        $bild->clear();

        $this->line("  <info>✓</info> {$ziel} <comment>({$breite} × {$hoehe})</comment>");
    }

    /**
     * Setzt Breite und Höhe im `<svg>`-Element. Der Hintergrund-`<rect>` trägt
     * dieselben Zahlen, deshalb bleibt der Ausdruck innerhalb des Start-Tags.
     */
    private function masse(string $svg, int $breite, int $hoehe): string
    {
        return preg_replace(
            '/(<svg\b[^>]*?)width="\d+" height="\d+"/',
            '${1}width="'.$breite.'" height="'.$hoehe.'"',
            $svg,
            limit: 1,
        );
    }
}
