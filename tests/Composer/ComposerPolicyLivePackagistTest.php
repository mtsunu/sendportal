<?php

declare(strict_types=1);

const LIVE_PROCESS_TIMEOUT_SECONDS = 900.0;
const LIVE_PROCESS_CHANNEL_LIMIT = 67108864;
const EXPECTED_COMPOSER_DIGEST = '5ee7125f8a30a34d246cefdc0bc85b8a783b28f2aec968994118512350d28027';

function liveFail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");

    exit(1);
}

function liveAssert(bool $condition, string $message): void
{
    if (! $condition) {
        liveFail($message);
    }
}

function liveRemoveDirectory(string $path): void
{
    if (! file_exists($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($path);
}

function liveCopyRepository(string $source, string $destination): void
{
    if (! mkdir($destination, 0700, true) && ! is_dir($destination)) {
        liveFail("Could not create temporary checkout {$destination}.");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source) + 1);
        $topLevel = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];

        if (in_array($topLevel, ['.git', '.planning', 'vendor'], true) || $relative === 'composer.lock') {
            continue;
        }

        $target = $destination.'/'.$relative;

        if ($item->isLink()) {
            liveFail("Temporary live proof does not copy symbolic link {$relative}.");
        }

        if ($item->isDir()) {
            if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
                liveFail("Could not create temporary directory {$target}.");
            }

            continue;
        }

        $parent = dirname($target);

        if (! is_dir($parent) && ! mkdir($parent, 0700, true) && ! is_dir($parent)) {
            liveFail("Could not create temporary directory {$parent}.");
        }

        if (! copy($item->getPathname(), $target)) {
            liveFail("Could not copy {$relative} into the temporary checkout.");
        }

        chmod($target, $item->getPerms() & 0777);
    }
}

function liveDirectoryIsEmpty(string $path): bool
{
    $entries = scandir($path);

    return is_array($entries) && array_values(array_diff($entries, ['.', '..'])) === [];
}

/**
 * @param list<string> $command
 * @param array<string, string> $environment
 * @return array{status: int, stdout: string, stderr: string}
 */
function liveRun(array $command, array $environment, string $workingDirectory): array
{
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        $workingDirectory,
        $environment,
    );

    if (! is_resource($process)) {
        liveFail('Could not start the live Composer command.');
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + LIVE_PROCESS_TIMEOUT_SECONDS;
    $lastStatus = null;

    while (true) {
        $read = [];

        foreach ([1, 2] as $descriptor) {
            if (! feof($pipes[$descriptor])) {
                $read[] = $pipes[$descriptor];
            }
        }

        if ($read !== []) {
            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, 200000);

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);

                if ($stream === $pipes[1]) {
                    $stdout .= $chunk;
                } else {
                    $stderr .= $chunk;
                }

                if (strlen($stdout) > LIVE_PROCESS_CHANNEL_LIMIT || strlen($stderr) > LIVE_PROCESS_CHANNEL_LIMIT) {
                    proc_terminate($process);
                    liveFail('Live Composer output exceeded the per-channel limit.');
                }
            }
        }

        $lastStatus = proc_get_status($process);

        if (! $lastStatus['running'] && feof($pipes[1]) && feof($pipes[2])) {
            break;
        }

        if (microtime(true) >= $deadline) {
            proc_terminate($process);
            liveFail('Live Composer command timed out.');
        }
    }

    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedStatus = proc_close($process);
    $exitStatus = $closedStatus;

    if ($exitStatus < 0 && is_array($lastStatus) && is_int($lastStatus['exitcode']) && $lastStatus['exitcode'] >= 0) {
        $exitStatus = $lastStatus['exitcode'];
    }

    return ['status' => $exitStatus, 'stdout' => $stdout, 'stderr' => $stderr];
}

/**
 * @return array<string, string>
 */
function liveEnvironment(string $home, string $cache, string $xdgCache): array
{
    $environment = getenv();
    liveAssert(is_array($environment), 'Could not read the live process environment.');

    foreach ([
        'COMPOSER',
        'COMPOSER_BIN',
        'COMPOSER_POLICY',
        'COMPOSER_NO_AUDIT',
        'COMPOSER_NO_BLOCKING',
        'COMPOSER_NO_SECURITY_BLOCKING',
        'COMPOSER_IGNORE_PLATFORM_REQ',
        'COMPOSER_IGNORE_PLATFORM_REQS',
        'COMPOSER_POLICY_ADVISORIES_BLOCK',
        'COMPOSER_POLICY_MALWARE_BLOCK',
        'COMPOSER_POLICY_ABANDONED_BLOCK',
        'COMPOSER_SECURITY_BLOCKING_ABANDONED',
        'COMPOSER_AUDIT_ABANDONED',
        'COMPOSER_DISABLE_NETWORK',
        'COMPOSER_MIRROR_PATH_REPOS',
        'COMPOSER_REPOSITORIES',
        'COMPOSER_ROOT_VERSION',
    ] as $name) {
        unset($environment[$name]);
    }

    $environment['COMPOSER_HOME'] = $home;
    $environment['COMPOSER_CACHE_DIR'] = $cache;
    $environment['XDG_CACHE_HOME'] = $xdgCache;

    return $environment;
}

function liveAssertExactManifest(string $path): void
{
    $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $rationale = 'This internal-only application has residual risk accepted by the project owner. The exception expires at Phase 2 lockfile review or when a compatible stable SendPortal Core release permits a Laravel upgrade, whichever comes first.';
    $expectedIds = [
        'PKSA-3r5d-mb8f-1qw9' => $rationale,
        'PKSA-m5cs-t1y6-qpcs' => $rationale,
        'PKSA-mdq4-51ck-6kdq' => $rationale,
    ];
    $policy = $manifest['config']['policy'] ?? null;
    $advisories = is_array($policy) ? ($policy['advisories'] ?? null) : null;
    $policyKeys = is_array($policy) ? array_keys($policy) : [];
    $advisoryKeys = is_array($advisories) ? array_keys($advisories) : [];
    sort($policyKeys);
    sort($advisoryKeys);

    liveAssert(($manifest['require']['php'] ?? null) === '^8.2', 'The live proof requires the exact PHP ^8.2 contract.');
    liveAssert(($manifest['require']['laravel/framework'] ?? null) === '^11.0', 'The live proof requires the exact Laravel ^11.0 contract.');
    liveAssert(($manifest['require']['mettle/sendportal-core'] ?? null) === '^3.0', 'The live proof requires the exact Core ^3.0 contract.');
    liveAssert(! isset($manifest['require']['roave/security-advisories']) && ! isset($manifest['require-dev']['roave/security-advisories']), 'Roave must remain absent.');
    liveAssert(! isset($manifest['config']['platform']) && ! isset($manifest['config']['audit']), 'Platform emulation and legacy audit configuration must remain absent.');
    liveAssert($policyKeys === ['advisories', 'ignore-unreachable'] && ($policy['ignore-unreachable'] ?? null) === false, 'The exact fail-closed policy keys are required.');
    liveAssert($advisoryKeys === ['audit', 'block', 'ignore-id'], 'The exact advisory policy keys are required.');
    liveAssert(($advisories['block'] ?? null) === true && ($advisories['audit'] ?? null) === 'fail', 'Advisory blocking and audit failure must remain enabled.');
    liveAssert(($advisories['ignore-id'] ?? null) === $expectedIds, 'Only the three owner-approved advisory IDs and complete rationales are allowed.');
}

function liveAssertDirectMetadata(string $stdout, string $stderr, string $label): int
{
    $log = $stdout."\n".$stderr;
    preg_match_all('~(?:Downloading|GET)\s+https://repo\.packagist\.org/~i', $log, $matches);
    $count = count($matches[0]);

    liveAssert($count > 0, "{$label} must contain direct repo.packagist.org metadata-download markers.");
    liveAssert(preg_match('/Reading .* from cache|offline mode|COMPOSER_DISABLE_NETWORK/i', $log) !== 1, "{$label} must not use cache or offline fallback evidence.");

    return $count;
}

function livePackageVersion(string $lockPath, string $packageName): string
{
    $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);

    foreach ([...($lock['packages'] ?? []), ...($lock['packages-dev'] ?? [])] as $package) {
        if (($package['name'] ?? null) === $packageName) {
            return (string) ($package['version'] ?? '');
        }
    }

    liveFail("Package {$packageName} is absent from the temporary lockfile.");
}

liveAssert(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4, 'Run this integration gate under PHP 8.4.x.');

$repositoryRoot = dirname(__DIR__, 2);
$recordPath = $repositoryRoot.'/tools/composer/composer-2.10.2.phar.sha256';
$pharPath = $repositoryRoot.'/tools/composer/composer-2.10.2.phar';
$record = file($recordPath, FILE_IGNORE_NEW_LINES);
liveAssert(is_array($record) && count($record) === 4, 'Composer provenance must contain exactly four lines.');
liveAssert($record[0] === 'release: 2.10.2', 'Composer provenance must name release 2.10.2.');
liveAssert($record[1] === 'source: https://getcomposer.org/download/2.10.2/composer.phar', 'Composer provenance must retain the official release URL.');
liveAssert($record[2] === 'verification: separately downloaded official SHA-256 from https://getcomposer.org/download/2.10.2/composer.phar.sha256sum', 'Composer provenance must retain the checksum verification method.');
liveAssert($record[3] === EXPECTED_COMPOSER_DIGEST.' composer-2.10.2.phar', 'Composer provenance must retain the exact digest record.');
liveAssert(hash_file('sha256', $pharPath) === EXPECTED_COMPOSER_DIGEST, 'The checked-in Composer PHAR digest must match its strict record.');
liveAssertExactManifest($repositoryRoot.'/composer.json');

$ci = (string) file_get_contents($repositoryRoot.'/.github/workflows/ci.yml');
$readme = (string) file_get_contents($repositoryRoot.'/README.md');
liveAssert(str_contains($ci, 'run: php bin/composer-policy install'), 'CI must retain the guarded install route.');
liveAssert(str_contains($readme, 'php bin/composer-policy install --prefer-dist --no-interaction'), 'README must retain the guarded install command.');
liveAssert(str_contains($readme, 'php bin/composer-policy update --prefer-dist --no-interaction'), 'README must retain the guarded update command.');
liveAssert(! file_exists($repositoryRoot.'/composer.lock') && ! is_dir($repositoryRoot.'/vendor'), 'The main checkout must start without composer.lock or vendor.');

$temporaryRoot = sys_get_temp_dir().'/sendportal-live-packagist-'.bin2hex(random_bytes(8));
$resolverRoot = $temporaryRoot.'/resolver';
$installRoot = $temporaryRoot.'/install';

try {
    liveCopyRepository($repositoryRoot, $resolverRoot);
    liveCopyRepository($repositoryRoot, $installRoot);
    $contexts = [];

    foreach (['resolver' => $resolverRoot, 'install' => $installRoot] as $label => $checkout) {
        $home = $temporaryRoot.'/'.$label.'-home';
        $cache = $temporaryRoot.'/'.$label.'-cache';
        $xdgCache = $temporaryRoot.'/'.$label.'-xdg-cache';

        foreach ([$home, $cache, $xdgCache] as $directory) {
            liveAssert(mkdir($directory, 0700, true), "Could not create empty {$label} isolation directory.");
            liveAssert(liveDirectoryIsEmpty($directory), "{$label} isolation directories must begin empty.");
        }

        $contexts[$label] = [
            'checkout' => $checkout,
            'home' => $home,
            'cache' => $cache,
            'xdg_cache' => $xdgCache,
            'environment' => liveEnvironment($home, $cache, $xdgCache),
        ];
    }

    $resolver = $contexts['resolver'];
    $resolverValidate = liveRun(
        [PHP_BINARY, $resolver['checkout'].'/bin/composer-policy', '--no-cache', 'validate', '--strict', '--no-check-publish'],
        $resolver['environment'],
        $resolver['checkout'],
    );
    liveAssert($resolverValidate['status'] === 0, "Resolver checkout validation failed:\n{$resolverValidate['stderr']}");
    $resolverUpdate = liveRun(
        [PHP_BINARY, $resolver['checkout'].'/bin/composer-policy', '--no-cache', '-vvv', 'update', '--dry-run', '--prefer-dist', '--no-interaction', '--no-scripts', '--no-progress'],
        $resolver['environment'],
        $resolver['checkout'],
    );
    liveAssert($resolverUpdate['status'] === 0, "Fresh resolver dry-run failed:\n{$resolverUpdate['stderr']}");
    $resolverMarkers = liveAssertDirectMetadata($resolverUpdate['stdout'], $resolverUpdate['stderr'], 'Resolver dry-run');
    liveAssert(! file_exists($resolver['checkout'].'/composer.lock') && ! is_dir($resolver['checkout'].'/vendor'), 'Resolver dry-run must not create dependency artifacts.');

    $install = $contexts['install'];
    $installValidate = liveRun(
        [PHP_BINARY, $install['checkout'].'/bin/composer-policy', '--no-cache', 'validate', '--strict', '--no-check-publish'],
        $install['environment'],
        $install['checkout'],
    );
    liveAssert($installValidate['status'] === 0, "Install checkout validation failed:\n{$installValidate['stderr']}");
    $installRun = liveRun(
        [PHP_BINARY, $install['checkout'].'/bin/composer-policy', '--no-cache', '-vvv', 'install', '--prefer-dist', '--no-interaction', '--no-progress'],
        $install['environment'],
        $install['checkout'],
    );
    liveAssert($installRun['status'] === 0, "Fresh script-enabled install failed:\n{$installRun['stderr']}");
    $installMarkers = liveAssertDirectMetadata($installRun['stdout'], $installRun['stderr'], 'Script-enabled install');
    liveAssert(file_exists($install['checkout'].'/composer.lock') && is_dir($install['checkout'].'/vendor'), 'The temporary install must create its lockfile and vendor tree.');
    liveAssert(file_exists($install['checkout'].'/bootstrap/cache/packages.php'), 'Script-enabled install must complete Laravel package discovery.');
    $audit = liveRun(
        [PHP_BINARY, $install['checkout'].'/bin/composer-policy', '--no-cache', 'audit', '--locked'],
        $install['environment'],
        $install['checkout'],
    );
    liveAssert($audit['status'] === 0, "Configured audit --locked failed:\n{$audit['stderr']}");

    $laravelVersion = livePackageVersion($install['checkout'].'/composer.lock', 'laravel/framework');
    $coreVersion = livePackageVersion($install['checkout'].'/composer.lock', 'mettle/sendportal-core');
    liveAssert(preg_match('/^v?11\.\d+\.\d+$/', $laravelVersion) === 1, 'Laravel must resolve to a tagged stable 11.x release.');
    liveAssert(preg_match('/^v?3\.\d+\.\d+$/', $coreVersion) === 1, 'SendPortal Core must resolve to a tagged stable 3.x release.');

    foreach ($contexts as $label => $context) {
        liveAssert(liveDirectoryIsEmpty($context['home']), "{$label} caller COMPOSER_HOME must remain empty because the guard replaces it.");
        liveAssert(liveDirectoryIsEmpty($context['cache']), "{$label} COMPOSER_CACHE_DIR must remain empty under global --no-cache.");
        liveAssert(liveDirectoryIsEmpty($context['xdg_cache']), "{$label} XDG_CACHE_HOME must remain empty under global --no-cache.");
    }

    liveAssert(! file_exists($repositoryRoot.'/composer.lock') && ! is_dir($repositoryRoot.'/vendor'), 'The live proof must leave dependency artifacts out of the main checkout.');
    liveAssertExactManifest($repositoryRoot.'/composer.json');
    liveAssert(hash_file('sha256', $pharPath) === EXPECTED_COMPOSER_DIGEST, 'The live proof must not change the checked-in Composer PHAR.');

    fwrite(STDOUT, sprintf(
        "Live Packagist proof passed: PHP %s; resolver_markers=%d; install_markers=%d; laravel=%s; core=%s; audit=pass; isolated_empty_homes_and_caches=2.\n",
        PHP_VERSION,
        $resolverMarkers,
        $installMarkers,
        $laravelVersion,
        $coreVersion,
    ));
} finally {
    liveRemoveDirectory($temporaryRoot);
}
