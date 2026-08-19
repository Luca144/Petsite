<?php

declare(strict_types=1);

/**
 * Renders the site's key pages as PNGs at phone and desktop width, so the GUI can
 * be checked BY EYE before a milestone is called done (CLAUDE.md golden rule 12).
 *
 * @package Felkyo\Bin
 *
 * RUN IT WITH:  C:\xampp\php\php.exe bin/gui-shots.php
 * Pictures land in gui-shots/ (gitignored). Open them and look at every one.
 *
 * WHY THIS EXISTS. Neither test suite can see a stylesheet: both read HTML, and
 * HTML was perfectly correct on the day the whole logged-out site rendered inside
 * a 250px strip, and on the day the inventory said "Acorn2 Treat" because a count
 * was glued to a wrapping name. Both shipped with everything green. The only
 * check that catches how a page LOOKS is looking at it, and this script makes
 * that a one-command habit instead of a favour.
 *
 * TWO HARD-WON FACTS ABOUT HEADLESS BROWSERS, so nobody re-learns them:
 *
 * 1. THE WINDOW WILL NOT GO BELOW ~480px. Ask Edge's new headless mode for a
 *    360px window and it lays the page out at ~480 and CROPS the screenshot to
 *    360 — which looks exactly like the site overflowing sideways, and an hour
 *    was spent chasing that phantom bug. Phone shots therefore render the page
 *    inside a 360px-wide <iframe> in a wider window: an iframe is a real layout
 *    viewport, so media queries and wrapping behave honestly.
 *
 * 2. SCREENSHOTS FIRE BEFORE THE PAGE SETTLES unless told otherwise. The shell's
 *    fade-in animation and half-loaded images photograph as a washed-out ghost
 *    page. --virtual-time-budget fast-forwards through both.
 *
 * The pages are fetched as a REAL logged-in player (fresh account, starred
 * creature, written bio) so every component that only an owner sees is in shot.
 */

require __DIR__ . '/../vendor/autoload.php';

const PORT = 8766; // NOT 8765 — the smoke test owns that one, and they may overlap.
const BASE = 'http://127.0.0.1:' . PORT;

// The pages worth eyes on. Add a line when a milestone adds a page.
const PAGES = [
    'guest-home' => '/',
    'login' => '/login',
    'home' => '/',              // fetched again once logged in
    'creature' => '{creature}',
    'shop' => '/shop',
    'community' => '/community',
    'inventory' => '/inventory',
    'collection' => '/creatures',
    'profile' => '{profile}',
    'play' => '{creature}/play',
];

// ---- Refuse to run anywhere but a development machine ----
$config = require __DIR__ . '/../config/config.php';
if (($config['app']['environment'] ?? 'production') === 'production') {
    echo "Refusing to run: this is a production configuration.\n";
    exit(1);
}

$outDir = dirname(__DIR__) . '/gui-shots';
@mkdir($outDir);
@mkdir($outDir . '/pages');

// ---- A browser that can screenshot ----
$browser = null;
foreach ([
    'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    'C:\Program Files\Google\Chrome\Application\chrome.exe',
] as $candidate) {
    if (is_file($candidate)) {
        $browser = $candidate;
        break;
    }
}
if ($browser === null) {
    echo "No Edge or Chrome found to render with.\n";
    exit(1);
}

// ---- Clear this machine's rate limits so the run is repeatable ----
$pdo = Felkyo\Core\Database::connect($config['database']);
$pdo->prepare("DELETE FROM rate_limit_hits WHERE identifier IN ('127.0.0.1', '::1')")->execute();

// ---- Start the site ----
$serverLog = sys_get_temp_dir() . '/felkyo-gui-server.log';
$server = proc_open(
    escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . PORT
    . ' -t ' . escapeshellarg(dirname(__DIR__) . '/public')
    . ' > ' . escapeshellarg($serverLog) . ' 2>&1',
    [],
    $pipes
);
$cookieJar = sys_get_temp_dir() . '/felkyo-gui-cookies.txt';
@unlink($cookieJar);

$fetch = static function (string $path, array $post = []) use ($cookieJar): string {
    $h = curl_init(BASE . $path);
    curl_setopt_array($h, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($post !== []) {
        curl_setopt($h, CURLOPT_POST, true);
        curl_setopt($h, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = (string) curl_exec($h);
    curl_close($h);
    return $body;
};
$token = static function (string $html): string {
    preg_match('~name="_csrf_token" value="([^"]+)"~', $html, $m);
    return $m[1] ?? '';
};

for ($attempt = 0; $attempt < 20; $attempt++) {
    usleep(300_000);
    if ($fetch('/') !== '') {
        break;
    }
}

// ---- A logged-in world with everything on it ----
// Saved BEFORE logging in, so the guest pages are really guest pages.
$saved = [];
$save = static function (string $name, string $html) use ($outDir, &$saved): void {
    // Root-relative URLs (/css/..., /assets/...) resolve against a <base>, so one
    // tag makes the saved file render against the running server.
    $html = preg_replace('~<head>~', '<head><base href="' . BASE . '/">', $html, 1);
    file_put_contents($outDir . '/pages/' . $name . '.html', $html);
    // The 360px iframe wrapper — see hard-won fact #1 in the docblock.
    file_put_contents(
        $outDir . '/pages/wrap-' . $name . '.html',
        '<!doctype html><html><head><style>*{margin:0;padding:0}body{background:#666}'
        . 'iframe{border:0;display:block;width:360px;height:3150px}</style></head>'
        . '<body><iframe src="' . $name . '.html"></iframe></body></html>'
    );
    $saved[] = $name;
};

$save('guest-home', $fetch('/'));
$save('login', $fetch('/login'));

$name = 'shot' . random_int(1000, 9999);
$fetch('/register', [
    '_csrf_token' => $token($fetch('/register')),
    'username' => $name,
    'email' => $name . '@example.test',
    'password' => 'a-long-enough-password',
]);
preg_match('~href="(/creature/\d+)"~', $fetch('/creatures'), $m);
$creature = $m[1];
$fetch($creature . '/favourite', ['_csrf_token' => $token($fetch($creature)), 'from' => 'creature']);
$fetch($creature . '/bio', [
    '_csrf_token' => $token($fetch($creature)),
    'bio' => 'A small brave thing who naps in sunbeams and hoards acorns under the dresser.',
]);

foreach (PAGES as $pageName => $path) {
    if (in_array($pageName, ['guest-home', 'login'], true)) {
        continue;
    }
    $path = str_replace(['{creature}', '{profile}'], [$creature, '/player/' . $name], $path);
    $save($pageName, $fetch($path));
}

// ---- Render ----
echo "Rendering with " . basename($browser) . "...\n";
$shoot = static function (string $file, string $size, string $png) use ($browser, $outDir): void {
    $profile = sys_get_temp_dir() . '/felkyo-gui-browser';
    exec(
        '"' . $browser . '" --headless=new --disable-gpu --hide-scrollbars'
        . ' --force-device-scale-factor=1 --user-data-dir=' . escapeshellarg($profile)
        . ' --virtual-time-budget=10000 --window-size=' . $size
        . ' --screenshot=' . escapeshellarg($outDir . '/' . $png)
        . ' ' . escapeshellarg('file:///' . str_replace('\\', '/', $outDir) . '/pages/' . $file)
        . ' 2>nul'
    );
};

foreach ($saved as $pageName) {
    $shoot('wrap-' . $pageName . '.html', '400,3180', $pageName . '-360.png');
    $shoot($pageName . '.html', '1280,2600', $pageName . '-1280.png');
    echo "  {$pageName}\n";
}

proc_terminate($server);

echo "\nDone. Now LOOK at every picture in gui-shots\\ — that is the entire point.\n";
echo "(The account \"{$name}\" was left behind; delete it if it bothers you.)\n";
