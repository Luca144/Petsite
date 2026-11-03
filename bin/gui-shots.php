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
 * 3. LARGE ANIMATED GIFs MAY NOT PAINT AT ALL in headless screenshot mode.
 *    The 679 KB not-found.gif photographs as an empty frame however it is
 *    served — proven by an A/B shot of the SAME file from the static assets
 *    path and from /art/, both blank, while curl and GD read the bytes fine
 *    and small GIFs on the same page render. So: an empty frame where a big
 *    animated picture should be is (probably) this renderer, not the site —
 *    confirm in a real browser before treating it as a bug. An hour went
 *    into ruling out the site; it was innocent.
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
    // M5 — the library.
    'library' => '/library',
    'library-species' => '/library/foxlen',
    'gazette' => '/library?tab=gazette',
    'gazette-article' => '/gazette/the-gazette-opens',
    // M3 — the pound.
    'pound' => '/pound',
    // M4 — themes.
    'themes' => '/themes',
    // M6 — company.
    'friends' => '/friends',
    'post-office' => '/post-office',
    'shore' => '/shore',
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
    // The router script: without it the built-in server answers /art/...
    // with its own 404 and every uploaded picture renders broken.
    . ' ' . escapeshellarg(__DIR__ . '/dev-server-router.php')
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
        // No "Expect: 100-continue" — PHP's built-in server answers it with
        // a 417 and the upload never happens (see bin/smoke-test.php).
        CURLOPT_HTTPHEADER => ['Expect:'],
    ]);
    if ($post !== []) {
        curl_setopt($h, CURLOPT_POST, true);
        // A CURLFile in the fields means a real multipart upload (the art store).
        $hasFile = array_filter($post, static fn ($value): bool => $value instanceof CURLFile) !== [];
        curl_setopt($h, CURLOPT_POSTFIELDS, $hasFile ? $post : http_build_query($post));
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

// ---- The creator's panel (M2.1) ----
// These pages sit behind role + door + second factor, so the walk mirrors the
// smoke test: found the account as an owner with the CLI script, pass the
// door, enrol (computing the TOTP code with the site's own RFC-pinned class),
// and photograph every screen along the way.
shell_exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/grant-role.php')
    . ' ' . escapeshellarg($name) . ' owner 2>&1'
);

$doorPage = $fetch('/admin/door');
$save('admin-door', $doorPage);

$enrolPage = $fetch('/admin/door', [
    '_csrf_token' => $token($doorPage),
    'password' => 'a-long-enough-password',
]);
$save('admin-enrol', $enrolPage);

preg_match('~admin-enrol__secret[^>]*>([A-Z2-7]+)<~', $enrolPage, $secretMatch);
$recoveryPage = $fetch('/admin/enrol', [
    '_csrf_token' => $token($enrolPage),
    'code' => (new Felkyo\Admin\Totp())->codeAt($secretMatch[1] ?? '', time()),
]);
$save('admin-recovery-codes', $recoveryPage);

// Grant a second role so the roles screen shows a holder with several chips.
$fetch('/admin/roles/grant', [
    '_csrf_token' => $token($fetch('/admin/roles')),
    'username' => $name,
    'role' => 'artist',
]);

// Two pictures in the art store, so the grid has something to show: the
// site's own animated 404 creature (a real sprite) and a hand-built one.
$shotArt = sys_get_temp_dir() . '/felkyo-gui-anim.gif';
file_put_contents($shotArt, Felkyo\Tests\Support\Pictures::animatedGif(random_int(11, 60000)));
foreach ([
    [dirname(__DIR__) . '/public/assets/art/not-found.gif', 'bramble-lost.gif', 'sprite'],
    [$shotArt, 'tiny-test.gif', 'icon'],
] as [$file, $shownAs, $kind]) {
    $fetch('/admin/pictures/upload', [
        '_csrf_token' => $token($fetch('/admin/pictures')),
        'kind' => $kind,
        'picture' => new CURLFile($file, 'image/gif', $shownAs),
    ]);
}
@unlink($shotArt);

$save('admin-home', $fetch('/admin'));
$save('admin-roles', $fetch('/admin/roles'));
$save('admin-audit', $fetch('/admin/audit'));
$save('admin-pictures', $fetch('/admin/pictures'));

// ---- The species editor (M2.3): a species mid-build, for honest shots ----
$speciesEditor = $fetch('/admin/creatures/create', [
    '_csrf_token' => $token($fetch('/admin/creatures')),
    'name' => 'Bramble Wisp',
]);
preg_match('~action="/admin/creatures/(\d+)/words"~', $speciesEditor, $sm);
$shotSpeciesId = $sm[1] ?? '0';
// Link the uploaded sprite to the baby stage so the editor shows a real
// mix: one stage with art, the others honestly saying "no picture yet".
$pickerPage = $fetch('/admin/creatures/' . $shotSpeciesId . '/picture/baby');
preg_match('~name="art_id" value="(\d+)"~', $pickerPage, $am);
$fetch('/admin/creatures/' . $shotSpeciesId . '/picture/baby', [
    '_csrf_token' => $token($pickerPage),
    'art_id' => $am[1] ?? '0',
]);
$fetch('/admin/creatures/' . $shotSpeciesId . '/words', [
    '_csrf_token' => $token($pickerPage),
    'name' => 'Bramble Wisp',
    'flavour_text' => 'A tangle of thorns that decided to be friendly.',
    'lore' => '',
]);
$save('admin-creatures', $fetch('/admin/creatures'));
$save('admin-creature-edit', $fetch('/admin/creatures/' . $shotSpeciesId));
// The species book, shot OPEN as well (a <details> starts closed).
$save('library-book-open', str_replace('<details class="book">', '<details class="book" open>', $fetch('/library/foxlen')));
$save('admin-forms', $fetch('/admin/creatures/1/forms'));
$save('admin-gazette', $fetch('/admin/gazette'));
$save('admin-character', $fetch('/admin/character'));
$save('admin-themes', $fetch('/admin/themes'));
preg_match('~href="/admin/themes/(\d+)"~', $fetch('/admin/themes'), $themeM);
$save('admin-theme-edit', $fetch('/admin/themes/' . ($themeM[1] ?? '0')));
$save('admin-creature-picker', $fetch('/admin/creatures/' . $shotSpeciesId . '/picture/juvenile'));
$save('admin-creature-preview', $fetch('/admin/creatures/' . $shotSpeciesId . '/preview'));

// ---- The item and shop editors (M2.4) ----
// Give the general store a greeting so the shopfront header has something
// to show, then re-capture the player-facing shop page with it.
preg_match('~href="/admin/shops/(\d+)"~', $fetch('/admin/shops'), $shopM);
$shotShopId = $shopM[1] ?? '0';
$fetch('/admin/shops/' . $shotShopId . '/keeper', [
    '_csrf_token' => $token($fetch('/admin/shops/' . $shotShopId)),
    'keeper_name' => 'Ruinily',
    'keeper_greeting' => 'Come in out of the stars, love — the kettle is on.',
    'keeper_refusal' => 'Ruinily doesn’t have anything like that on the shelves.',
]);
$save('admin-items', $fetch('/admin/items'));
preg_match('~href="/admin/items/(\d+)"~', $fetch('/admin/items'), $itemM);
$save('admin-item-edit', $fetch('/admin/items/' . ($itemM[1] ?? '0')));
$save('admin-shops', $fetch('/admin/shops'));
$save('admin-shop-edit', $fetch('/admin/shops/' . $shotShopId));
$save('shop', $fetch('/shop')); // re-captured WITH the shopfront greeting

// ---- The area editor (M2.5): the wood in a painted scene ----
// Hang a real background on whispering-wood through the real picker, so
// the admin scene box AND the player page are both shot painted; unlink
// afterwards so repeated runs start clean (the shots keep the painting).
preg_match_all('~href="/admin/areas/(\d+)"~', $fetch('/admin/areas'), $areaM);
$shotAreaId = '0';
foreach (array_unique($areaM[1]) as $candidateAreaId) {
    if (str_contains($fetch('/admin/areas/' . $candidateAreaId), 'The Whispering Wood')) {
        $shotAreaId = $candidateAreaId;
        break;
    }
}
$sceneArt = sys_get_temp_dir() . '/felkyo-gui-scene.gif';
file_put_contents($sceneArt, Felkyo\Tests\Support\Pictures::animatedGif(random_int(11, 60000)));
$fetch('/admin/pictures/upload', [
    '_csrf_token' => $token($fetch('/admin/pictures')),
    'kind' => 'background',
    'picture' => new CURLFile($sceneArt, 'image/gif', 'test-scene.gif'),
]);
@unlink($sceneArt);
$areaPicker = $fetch('/admin/areas/' . $shotAreaId . '/picture');
preg_match('~name="art_id" value="([1-9]\d*)"~', $areaPicker, $sceneM);
$fetch('/admin/areas/' . $shotAreaId . '/picture', [
    '_csrf_token' => $token($areaPicker),
    'art_id' => $sceneM[1] ?? '0',
]);
// ---- Moderation (M2.7): a real notice, a real queue row, then tidy ----
// The shot account's own id, read from the database it was just made in.
$shotUserId = (int) $pdo->query("SELECT id FROM users WHERE username = " . $pdo->quote($name))->fetchColumn();
// A notice card on the home page: sent directly (the panel refuses to
// moderate staff, and the shot account IS staff), removed after the shot.
$pdo->prepare('INSERT INTO moderation_notices (user_id, kind) VALUES (?, ?)')->execute([$shotUserId, 'warned']);
$save('home-with-notice', $fetch('/'));
$pdo->prepare('DELETE FROM moderation_notices WHERE user_id = ?')->execute([$shotUserId]);
// A queue with one real row: any two other accounts stand in as reporter
// and reported (dev databases have plenty); the row is removed after.
$standIns = $pdo->query(
    "SELECT u.id FROM users u LEFT JOIN user_roles r ON r.user_id = u.id
      WHERE r.user_id IS NULL AND u.id <> {$shotUserId} ORDER BY u.id LIMIT 2"
)->fetchAll(PDO::FETCH_COLUMN);
if (count($standIns) === 2) {
    $pdo->prepare(
        "INSERT INTO reports (reporter_user_id, subject_type, subject_id, about_user_id, category, priority, status)
         VALUES (?, 'username', ?, ?, 'rude_words', 4, 'open')"
    )->execute([$standIns[0], $standIns[1], $standIns[1]]);
    $save('admin-reports', $fetch('/admin/reports'));
    $pdo->exec("DELETE FROM reports WHERE reporter_user_id = {$standIns[0]} AND subject_id = {$standIns[1]} AND status = 'open'");
    $save('admin-account', $fetch('/admin/accounts/' . $standIns[1]));
} else {
    $save('admin-reports', $fetch('/admin/reports')); // the honest empty queue
}

$save('admin-unfinished', $fetch('/admin/unfinished')); // the M2.6 list
$save('admin-settings', $fetch('/admin/settings'));     // the M2.8 screen
$save('admin-stats', $fetch('/admin/stats'));           // the M2.9 screen
$save('admin-areas', $fetch('/admin/areas'));
$save('admin-area-edit', $fetch('/admin/areas/' . $shotAreaId));
$save('explore', $fetch('/explore'));
$save('explore-area', $fetch('/explore/whispering-wood'));
$fetch('/admin/areas/' . $shotAreaId . '/picture', [
    '_csrf_token' => $token($fetch('/admin/areas/' . $shotAreaId . '/picture')),
    'art_id' => '0', // back to the glow placeholder for the next run
]);

// ---- Render ----
echo "Rendering with " . basename($browser) . "...\n";
$shoot = static function (string $file, string $size, string $png) use ($browser, $outDir): void {
    $profile = sys_get_temp_dir() . '/felkyo-gui-browser';
    exec(
        '"' . $browser . '" --headless=new --disable-gpu --hide-scrollbars'
        . ' --force-device-scale-factor=1 --user-data-dir=' . escapeshellarg($profile)
        // 30s of virtual time, not 10: the Pictures page pulls real uploads
        // through the single-threaded dev server, and a big animated GIF was
        // still streaming when the 10s clock ran out — it photographed as an
        // empty frame while loading perfectly in a real browser.
        . ' --virtual-time-budget=30000 --window-size=' . $size
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
