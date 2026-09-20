<?php

namespace App\Console\Commands;

use App\Release\AndroidTheme;
use App\Release\EnvDatei;
use App\Release\Stufe;
use App\Release\Version;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

/**
 * Veröffentlicht eine Version: Version hochzählen, signierte APK bauen, Tag
 * setzen, GitHub-Release mit der APK anlegen. Obtainium holt sich den Rest.
 *
 * Die Reihenfolge ist Absicht: Erst prüfen, dann bauen, dann taggen. Ein Tag
 * entsteht nur für eine APK, die es wirklich gibt.
 */
final class ReleaseCommand extends Command
{
    private const AUSGABEVERZEICHNIS = 'nativephp/android/app/build/outputs/apk/release';

    protected $signature = 'release
        {version? : Neue Version, z. B. 1.2.0 — ohne Angabe wird die Patch-Stelle erhöht}
        {--major : Erste Stelle erhöhen}
        {--minor : Zweite Stelle erhöhen}
        {--patch : Dritte Stelle erhöhen (Vorgabe)}
        {--skip-tests : Testlauf vor dem Build überspringen}
        {--skip-build : Bereits gebaute APK aus dem Ausgabeverzeichnis verwenden}
        {--draft : GitHub-Release als Entwurf anlegen}
        {--prerelease : Release als Vorabversion markieren}
        {--notes= : Release-Notes; ohne Angabe generiert GitHub sie aus den Commits}';

    protected $description = 'Baut eine signierte APK und veröffentlicht sie als GitHub-Release für Obtainium';

    public function handle(): int
    {
        $env = new EnvDatei(base_path('.env'));

        try {
            $version = $this->naechsteVersion($env);
        } catch (InvalidArgumentException $fehler) {
            $this->error($fehler->getMessage());

            return self::FAILURE;
        }

        $versionCode = (int) ($env->lesen('NATIVEPHP_APP_VERSION_CODE') ?? 0) + 1;

        $hindernisse = $this->hindernisse($env, $version);

        if ($hindernisse !== []) {
            foreach ($hindernisse as $hindernis) {
                $this->error($hindernis);
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line("  Version      <info>{$version}</info>  (bisher: ".($env->lesen('NATIVEPHP_APP_VERSION') ?? '—').')');
        $this->line("  Version-Code <info>{$versionCode}</info>");
        $this->line('  Tag          <info>'.$version->tag().'</info>  auf '.$this->git(['rev-parse', '--short', 'HEAD'])->output());
        $this->newLine();

        if (! $this->confirm('Veröffentlichen?', true)) {
            return self::FAILURE;
        }

        $ohneEnvDatei = array_fill_keys($env->schluessel(), false);

        if (! $this->option('skip-tests') && ! $this->testlauf($ohneEnvDatei)) {
            $this->error('Die Tests sind rot — nichts veröffentlicht.');

            return self::FAILURE;
        }

        $vorherigeWerte = [
            'NATIVEPHP_APP_VERSION' => $env->lesen('NATIVEPHP_APP_VERSION') ?? '',
            'NATIVEPHP_APP_VERSION_CODE' => $env->lesen('NATIVEPHP_APP_VERSION_CODE') ?? '',
        ];

        $env->schreiben([
            'NATIVEPHP_APP_VERSION' => (string) $version,
            'NATIVEPHP_APP_VERSION_CODE' => $versionCode,
        ]);

        if (! $this->option('skip-build') && ! $this->bauen($ohneEnvDatei)) {
            $env->schreiben($vorherigeWerte);
            $this->error('Der Build ist fehlgeschlagen — Version in der .env zurückgesetzt.');

            return self::FAILURE;
        }

        // `native:package` zählt den Version-Code in der .env selbst noch einmal
        // hoch. Ohne diese Korrektur stünde dort ein Code, den keine APK trägt,
        // und das nächste Release übersprünge eine Nummer.
        $env->schreiben(['NATIVEPHP_APP_VERSION_CODE' => $versionCode]);

        $apk = $this->gebauteApk($version, $versionCode, $env);

        if ($apk === null) {
            $env->schreiben($vorherigeWerte);

            return self::FAILURE;
        }

        $asset = base_path('build/einkaufsliste-'.$version.'.apk');
        File::ensureDirectoryExists(dirname($asset));
        File::copy($apk, $asset);

        return $this->veroeffentlichen($version, $asset);
    }

    private function naechsteVersion(EnvDatei $env): Version
    {
        $angabe = $this->argument('version');

        if (is_string($angabe) && $angabe !== '') {
            return Version::ausString($angabe);
        }

        $aktuell = $env->lesen('NATIVEPHP_APP_VERSION');

        if ($aktuell === null) {
            throw new InvalidArgumentException(
                'In der .env steht noch keine Version. Setze die erste explizit: php artisan release 1.0.0'
            );
        }

        $stufe = match (true) {
            (bool) $this->option('major') => Stufe::Major,
            (bool) $this->option('minor') => Stufe::Minor,
            default => Stufe::Patch,
        };

        return Version::ausString($aktuell)->erhoehen($stufe);
    }

    /**
     * Alles, was einer Veröffentlichung im Weg steht — vollständig gesammelt
     * statt beim ersten Fund abgebrochen, damit nicht dreimal nachgebaut wird.
     *
     * @return list<string>
     */
    private function hindernisse(EnvDatei $env, Version $version): array
    {
        $hindernisse = [];

        if ($this->git(['status', '--porcelain'])->output() !== '') {
            $hindernisse[] = 'Das Arbeitsverzeichnis ist nicht sauber. Commit oder stash zuerst — der Tag zeigt sonst auf einen Stand, den die APK nicht hat.';
        }

        if ($this->git(['tag', '--list', $version->tag()])->output() !== ''
            || $this->git(['ls-remote', '--tags', 'origin', 'refs/tags/'.$version->tag()])->output() !== '') {
            $hindernisse[] = 'Den Tag '.$version->tag().' gibt es schon.';
        }

        if (! Process::run(['gh', 'auth', 'status'])->successful()) {
            $hindernisse[] = 'Die GitHub-CLI ist nicht angemeldet: gh auth login';
        }

        foreach (['ANDROID_KEYSTORE_FILE', 'ANDROID_KEYSTORE_PASSWORD', 'ANDROID_KEY_ALIAS', 'ANDROID_KEY_PASSWORD'] as $schluessel) {
            if ($env->lesen($schluessel) === null) {
                $hindernisse[] = "In der .env fehlt {$schluessel}. Ohne Keystore wird die APK mit dem Debug-Schlüssel signiert und lässt sich später nie mehr aktualisieren.";
            }
        }

        $keystore = $env->lesen('ANDROID_KEYSTORE_FILE');

        if ($keystore !== null && ! is_file($this->absolut($keystore))) {
            $hindernisse[] = "Der Keystore {$keystore} liegt nicht da.";
        }

        return $hindernisse;
    }

    /**
     * @param  array<string, false>  $ohneEnvDatei
     */
    private function testlauf(array $ohneEnvDatei): bool
    {
        $this->components->info('Tests');
        $this->cachesLeeren();

        return $this->artisan(['test'], timeout: 900, umgebung: $ohneEnvDatei)->successful();
    }

    /**
     * @param  array<string, false>  $ohneEnvDatei
     */
    private function bauen(array $ohneEnvDatei): bool
    {
        $this->components->info('Build (das dauert)');

        $this->androidThemeAuffrischen();

        // Ohne --no-tty hängt `native:package` seinen Gradle-Prozess an /dev/tty.
        // Als Kindprozess gibt es kein Terminal, der Build bricht dann ohne APK ab.
        $erfolg = $this->artisan(
            ['native:package', 'android', '--no-tty', '--no-interaction'],
            timeout: 3600,
            umgebung: $ohneEnvDatei,
        )->successful();

        $this->cachesLeeren();

        return $erfolg;
    }

    /**
     * Die Theme-Dateien im gitignorierten `nativephp/`-Ordner stammen vom
     * letzten `native:install` und kennen spätere Farbänderungen in
     * `config/nativephp.php` nicht. Ohne diesen Schritt trüge die APK die
     * Farben von damals.
     */
    private function androidThemeAuffrischen(): void
    {
        foreach (AndroidTheme::ausKonfiguration(base_path('nativephp/android'))->anwenden() as $datei) {
            $this->components->info('Android-Theme aufgefrischt: '.str_replace(base_path().'/', '', $datei));
        }
    }

    /**
     * `native:package` installiert die Abhängigkeiten ohne dev-Pakete neu und
     * lässt `bootstrap/cache/packages.php` und `services.php` mit dem Paketstand
     * des Bundles zurück. Wer danach testet, bekommt eine App ohne Testpakete
     * und ohne native Routen zu sehen.
     */
    private function cachesLeeren(): void
    {
        $this->artisan(['optimize:clear', '--quiet'], timeout: 120);
    }

    /**
     * Die frisch gebaute APK — aber nur, wenn Gradle auch wirklich die Version
     * hineingeschrieben hat, die veröffentlicht werden soll, und nicht der
     * Debug-Schlüssel sie signiert hat.
     */
    private function gebauteApk(Version $version, int $versionCode, EnvDatei $env): ?string
    {
        $verzeichnis = base_path(self::AUSGABEVERZEICHNIS);
        $apks = glob($verzeichnis.'/*.apk') ?: [];

        if (count($apks) !== 1) {
            $this->error(count($apks) === 0
                ? "Keine APK unter {$verzeichnis}."
                : "Mehrere APKs unter {$verzeichnis} — unklar, welche veröffentlicht werden soll.");

            return null;
        }

        $apk = $apks[0];
        $metadaten = $verzeichnis.'/output-metadata.json';

        if (is_file($metadaten)) {
            $element = json_decode((string) file_get_contents($metadaten), true)['elements'][0] ?? [];

            if (($element['versionName'] ?? null) !== (string) $version || (int) ($element['versionCode'] ?? 0) !== $versionCode) {
                $this->error('Die gebaute APK trägt Version '.($element['versionName'] ?? '?').' ('.($element['versionCode'] ?? '?').") statt {$version} ({$versionCode}). Mit --skip-build wurde eine alte APK gefunden.");

                return null;
            }
        }

        if (! $this->istMitDemKeystoreSigniert($apk, $env)) {
            return null;
        }

        return $apk;
    }

    /**
     * Prüft, dass die APK mit genau dem Keystore aus der `.env` signiert ist.
     *
     * Eine Signatur vom falschen Schlüssel ist die einzige wirklich
     * unumkehrbare Panne: Wer die APK installiert, kann sie nie mit einem
     * Release des anderen Schlüssels aktualisieren, sondern muss deinstallieren
     * — samt aller Daten auf dem Gerät. `keytool -printcert -jarfile` taugt
     * dafür nicht: Gradle signiert nur noch nach APK Signature Scheme v2/v3,
     * und darin sieht keytool keine Signatur.
     */
    private function istMitDemKeystoreSigniert(string $apk, EnvDatei $env): bool
    {
        $apksigner = $this->apksigner();

        if ($apksigner === null) {
            $this->warn('apksigner nicht gefunden — die Signatur der APK wurde nicht geprüft.');

            return true;
        }

        $ergebnis = Process::run([$apksigner, 'verify', '--print-certs', $apk]);
        $ausgabe = $ergebnis->output().$ergebnis->errorOutput();

        if (! $ergebnis->successful()) {
            if (str_contains($ausgabe, 'DOES NOT VERIFY') || str_contains($ausgabe, 'Missing META-INF')) {
                $this->error('Die APK ist nicht gültig signiert:');
                $this->line($ausgabe);

                return false;
            }

            $this->warn('apksigner ließ sich nicht ausführen — die Signatur der APK wurde nicht geprüft.');

            return true;
        }

        $erwartet = $this->keystoreFingerabdruck($env);

        if ($erwartet === null) {
            $this->warn('Fingerabdruck des Keystores nicht lesbar — die Signatur der APK wurde nicht geprüft.');

            return true;
        }

        if (preg_match('/SHA-256 digest: ([0-9a-f]+)/i', $ausgabe, $treffer) !== 1) {
            $this->warn('apksigner nennt keinen SHA-256-Fingerabdruck — die Signatur der APK wurde nicht geprüft.');

            return true;
        }

        if (! hash_equals($erwartet, strtolower($treffer[1]))) {
            $this->error('Die APK ist mit einem anderen Schlüssel signiert als dem aus der .env. Sie ließe sich auf den Geräten nie aktualisieren.');

            return false;
        }

        return true;
    }

    /** Der neueste `apksigner` aus den Build-Tools des Android-SDK. */
    private function apksigner(): ?string
    {
        $sdk = getenv('ANDROID_HOME') ?: getenv('ANDROID_SDK_ROOT') ?: getenv('HOME').'/Library/Android/sdk';
        $kandidaten = glob($sdk.'/build-tools/*/apksigner') ?: [];

        usort($kandidaten, fn (string $a, string $b) => version_compare(basename(dirname($a)), basename(dirname($b))));

        return $kandidaten === [] ? null : end($kandidaten);
    }

    /** SHA-256 des Zertifikats im Keystore, in Kleinbuchstaben ohne Doppelpunkte. */
    private function keystoreFingerabdruck(EnvDatei $env): ?string
    {
        $keystore = $env->lesen('ANDROID_KEYSTORE_FILE');
        $passwort = $env->lesen('ANDROID_KEYSTORE_PASSWORD');

        if ($keystore === null || $passwort === null) {
            return null;
        }

        $ergebnis = Process::run(['keytool', '-list', '-v', '-keystore', $this->absolut($keystore), '-storepass', $passwort]);

        if (! $ergebnis->successful() || preg_match('/SHA256: ([0-9A-F:]+)/i', $ergebnis->output(), $treffer) !== 1) {
            return null;
        }

        return strtolower(str_replace(':', '', $treffer[1]));
    }

    private function veroeffentlichen(Version $version, string $asset): int
    {
        $meldung = 'Release '.$version;

        if (! $this->git(['tag', '-a', $version->tag(), '-m', $meldung])->successful()
            || ! $this->git(['push', 'origin', $version->tag()])->successful()) {
            $this->error('Der Tag ließ sich nicht setzen oder pushen.');

            return self::FAILURE;
        }

        $notizen = $this->option('notes');

        $befehl = ['gh', 'release', 'create', $version->tag(), $asset, '--title', (string) $version];
        $befehl = array_merge($befehl, is_string($notizen) && $notizen !== ''
            ? ['--notes', $notizen]
            : ['--generate-notes']);

        foreach (['draft', 'prerelease'] as $schalter) {
            if ($this->option($schalter)) {
                $befehl[] = '--'.$schalter;
            }
        }

        $release = Process::path(base_path())->timeout(1800)->run($befehl);

        if (! $release->successful()) {
            $this->error($release->errorOutput());
            $this->warn('Der Tag '.$version->tag().' steht bereits auf origin. Release von Hand nachziehen oder Tag löschen.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Veröffentlicht: '.trim($release->output()));
        $this->line('  Obtainium holt die APK beim nächsten Update-Check. Beim ersten Mal:');
        $repo = preg_replace('/\.git$/', '', trim($this->git(['remote', 'get-url', 'origin'])->output()));
        $this->line('  <comment>Add App</comment> → '.$repo);

        return self::SUCCESS;
    }

    /**
     * Ruft artisan in einem Kindprozess auf — auf Wunsch ohne die Variablen
     * der `.env`.
     *
     * Laravel legt deren Werte in `$_SERVER` ab, von dort erbt sie jeder
     * Kindprozess als echte Umgebungsvariable, und die schlägt sowohl die
     * frisch geschriebene `.env` als auch die `<env>`-Einträge der
     * `phpunit.xml`. Der Testlauf lief sonst gegen `APP_ENV=local` und die
     * echte Mealie-Instanz, und der Build schrieb den Version-Code, den dieser
     * Prozess beim Start gelesen hatte, statt den neuen.
     *
     * @param  list<string>  $argumente
     * @param  array<string, string|false>  $umgebung
     */
    private function artisan(array $argumente, int $timeout, array $umgebung = []): ProcessResult
    {
        return Process::path(base_path())
            ->timeout($timeout)
            ->env($umgebung)
            ->run(array_merge([PHP_BINARY, 'artisan'], $argumente), function (string $art, string $ausgabe): void {
                $this->output->write($ausgabe);
            });
    }

    /**
     * @param  list<string>  $argumente
     */
    private function git(array $argumente): ProcessResult
    {
        return Process::path(base_path())->run(array_merge(['git'], $argumente));
    }

    private function absolut(string $pfad): string
    {
        return str_starts_with($pfad, '/') ? $pfad : base_path($pfad);
    }
}
