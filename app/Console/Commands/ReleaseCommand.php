<?php

namespace App\Console\Commands;

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

        if (! $this->option('skip-tests') && ! $this->testlauf()) {
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

        if (! $this->option('skip-build') && ! $this->bauen()) {
            $env->schreiben($vorherigeWerte);
            $this->error('Der Build ist fehlgeschlagen — Version in der .env zurückgesetzt.');

            return self::FAILURE;
        }

        $apk = $this->gebauteApk($version, $versionCode);

        if ($apk === null) {
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

    private function testlauf(): bool
    {
        $this->components->info('Tests');

        return $this->artisan(['test'], timeout: 900)->successful();
    }

    private function bauen(): bool
    {
        $this->components->info('Build (das dauert)');

        return $this->artisan(['native:package', 'android'], timeout: 3600)->successful();
    }

    /**
     * Die frisch gebaute APK — aber nur, wenn Gradle auch wirklich die Version
     * hineingeschrieben hat, die veröffentlicht werden soll, und nicht der
     * Debug-Schlüssel sie signiert hat.
     */
    private function gebauteApk(Version $version, int $versionCode): ?string
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

        if (! $this->istReleaseSigniert($apk)) {
            return null;
        }

        return $apk;
    }

    /**
     * Eine debug-signierte APK ist die einzige wirklich unumkehrbare Panne:
     * Wer sie installiert, kann sie nie mit einem echten Release aktualisieren,
     * sondern muss deinstallieren — samt aller Daten auf dem Gerät.
     */
    private function istReleaseSigniert(string $apk): bool
    {
        $zertifikat = Process::run(['keytool', '-printcert', '-jarfile', $apk]);

        if (! $zertifikat->successful()) {
            $this->warn('keytool nicht gefunden — die Signatur der APK wurde nicht geprüft.');

            return true;
        }

        if (str_contains($zertifikat->output(), 'CN=Android Debug')) {
            $this->error('Die APK ist mit dem Debug-Schlüssel signiert. Sie ließe sich auf den Geräten nie aktualisieren.');

            return false;
        }

        return true;
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

        if ($this->option('draft')) {
            $befehl[] = '--draft';
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
        $this->line('  <comment>Add App</comment> → '.trim($this->git(['remote', 'get-url', 'origin'])->output()));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $argumente
     */
    private function artisan(array $argumente, int $timeout): ProcessResult
    {
        return Process::path(base_path())
            ->timeout($timeout)
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
