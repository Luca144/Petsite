<?php

declare(strict_types=1);

/**
 * Drives the real website over HTTP and checks it actually works.
 *
 * @package Felkyo\Bin
 *
 * RUN IT WITH:  C:\xampp\php\php.exe bin/smoke-test.php
 *
 * WHY THIS EXISTS, AND IT IS WORTH READING ONCE.
 *
 * The unit and integration tests call the code directly. That is fast and it
 * catches most things — but it cannot catch a mistake in how the code is WIRED
 * TOGETHER, because the test does the wiring itself, and it wires it the way the
 * person writing the test believed it worked.
 *
 * That is not a theoretical worry. It happened here. The search page read its
 * query from posted form values, while the search form is a GET form that puts
 * the query in the address. Search never once worked. The integration test passed
 * every time, because it built the request by hand and put the query where the
 * controller expected it — proving the controller worked given input it could
 * never receive.
 *
 * A green test suite said the feature was fine. Loading the page in a browser
 * would have shown the truth in four seconds.
 *
 * So this script does what a browser does: it starts the site, registers an
 * account through the real form, walks every page, submits the real forms, and
 * complains if anything is broken. It is the difference between "the code is
 * correct" and "the website works", which are not the same claim.
 *
 * IT IS NOT A REPLACEMENT for the test suite — it is much slower and much less
 * precise about WHY something failed. Run both. This one runs last, before
 * telling anybody the work is finished.
 */

require __DIR__ . '/../vendor/autoload.php';

const PORT = 8765;
const BASE = 'http://127.0.0.1:' . PORT;

$failures = [];
$checks = 0;

/**
 * Record a check. Everything in this script funnels through here so the summary
 * at the end is complete and the exit code is honest.
 */
function check(string $what, bool $passed, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;

    if ($passed) {
        echo "  ok    {$what}\n";
        return;
    }

    $failures[] = $what . ($detail !== '' ? " — {$detail}" : '');
    echo "  FAIL  {$what}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

/**
 * Fetch a page, keeping cookies between calls so we stay logged in.
 *
 * @param array<string, string> $post Sending this makes it a POST.
 * @return array{status: int, body: string}
 */
function request(string $path, array $post = []): array
{
    $handle = curl_init(BASE . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/felkyo-smoke-cookies.txt',
        CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/felkyo-smoke-cookies.txt',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($post !== []) {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    return ['status' => $status, 'body' => $body];
}

/**
 * The CSRF token from a page, so forms can be submitted the way a browser does.
 */
function tokenFrom(string $path): string
{
    preg_match('~name="_csrf_token" value="([^"]+)"~', request($path)['body'], $m);

    return $m[1] ?? '';
}

// ---------------------------------------------------------------------------
// Refuse to run anywhere but a development machine
// ---------------------------------------------------------------------------

// This script creates accounts and clears rate-limit records. Both are perfectly
// fine on a laptop and completely unacceptable on the live site, so it checks
// before doing anything at all rather than trusting whoever typed the command.
$config = require __DIR__ . '/../config/config.php';

if (($config['app']['environment'] ?? 'production') === 'production') {
    echo "Refusing to run: this is a production configuration.\n";
    echo "The smoke test creates accounts and clears rate limits. Run it on a development machine.\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Clear this machine's rate-limit records
// ---------------------------------------------------------------------------

// Registration is capped at three new accounts an hour from one address, which is
// exactly right for the real site and stops this script running four times in a
// row. Clearing the local address's records keeps the script repeatable — and
// finding this out the hard way is why the script now says what it is doing.
$pdo = Felkyo\Core\Database::connect($config['database']);
$cleared = $pdo->prepare("DELETE FROM rate_limit_hits WHERE identifier IN ('127.0.0.1', '::1')");
$cleared->execute();
echo "Cleared " . $cleared->rowCount() . " local rate-limit records so the run is repeatable.\n";

// ---------------------------------------------------------------------------
// Start the site
// ---------------------------------------------------------------------------

echo "Starting the site on port " . PORT . "...\n";
$serverLog = sys_get_temp_dir() . '/felkyo-smoke-server.log';
// PHP_BINARY is the exact interpreter running this script. Using it means the
// server starts whether or not "php" happens to be on the system PATH — which on
// Windows it very often is not, and the failure looks like a hang rather than an
// error.
$command = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . PORT . ' -t ' . escapeshellarg(__DIR__ . '/../public');
$server = proc_open($command . ' > ' . escapeshellarg($serverLog) . ' 2>&1', [], $pipes);

// Give it a moment, then confirm it is actually answering before going further —
// otherwise every check below fails for one boring reason and hides the real ones.
for ($attempt = 0; $attempt < 20; $attempt++) {
    usleep(300_000);
    if (request('/')['status'] === 200) {
        break;
    }
}

@unlink(sys_get_temp_dir() . '/felkyo-smoke-cookies.txt');

if (request('/')['status'] !== 200) {
    echo "The site did not start. Log:\n" . file_get_contents($serverLog) . "\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// Sign up, the way a real person does
// ---------------------------------------------------------------------------

echo "\nSigning up:\n";
$name = 'smoke' . random_int(1000, 9999);
$signup = request('/register', [
    '_csrf_token' => tokenFrom('/register'),
    'username' => $name,
    'email' => $name . '@example.test',
    'password' => 'a-long-enough-password',
]);
check('registration succeeds', $signup['status'] === 200 && !str_contains($signup['body'], 'error'));
check('registration logs you in', str_contains(request('/')['body'], $name));

// ---------------------------------------------------------------------------
// Every page a logged-in player can open
// ---------------------------------------------------------------------------

echo "\nEvery page loads:\n";
$pages = [
    '/', '/creatures', '/adopt', '/explore', '/shop', '/inventory', '/browse',
    '/players', '/players?q=' . substr($name, 0, 4), '/profile/edit', '/player/' . $name,
    // The community hub and BOTH of its tabs. Loading only the default tab would
    // have missed the people half entirely — and the people half is the one that
    // runs a query and the one with the guards on it.
    '/community', '/community?tab=creatures',
    '/community?tab=users', '/community?tab=users&q=' . substr($name, 0, 4),
];
$captured = [];
foreach ($pages as $path) {
    $page = request($path);
    $captured[$path] = $page['body'];
    check(
        $path,
        $page['status'] === 200 && !str_contains($page['body'], 'Something went wrong'),
        'status ' . $page['status']
    );
}

// ---------------------------------------------------------------------------
// The things a page is FOR, not just that it rendered
// ---------------------------------------------------------------------------

echo "\nPages actually do their job:\n";

// This is the check that would have caught the search bug: not "did the page
// load" but "did it find anything".
// Look inside the RESULT markup, not just anywhere on the page. The logged-in
// player's own name is printed in the header on every page, so a looser check
// passed happily while search was returning nothing at all — the second version
// of the very mistake this script exists to catch.
preg_match_all(
    '~class="search-result__name">([^<]+)<~',
    $captured['/players?q=' . substr($name, 0, 4)],
    $found
);
check(
    'search finds a player by name prefix',
    in_array($name, $found[1] ?? [], true),
    'searching for a name that definitely exists returned ' . count($found[1] ?? []) . ' results'
);
check(
    'search remembers what was typed',
    str_contains($captured['/players?q=' . substr($name, 0, 4)], 'value="' . substr($name, 0, 4) . '"')
);
check('your own page shows your name', str_contains($captured['/player/' . $name], $name));

// The community page is two features behind one address, so both halves are
// checked for what they are FOR — not merely that the page returned 200.
check(
    'the community page shows creatures on its creatures tab',
    str_contains($captured['/community?tab=creatures'], 'creature-collection')
        || str_contains($captured['/community?tab=creatures'], 'No creatures to show yet')
);
preg_match_all(
    '~class="search-result__name">([^<]+)<~',
    $captured['/community?tab=users&q=' . substr($name, 0, 4)],
    $communityFound
);
check(
    'the community page finds a player on its people tab',
    in_array($name, $communityFound[1] ?? [], true),
    'searching a name that definitely exists returned ' . count($communityFound[1] ?? []) . ' results'
);
// The guard that was missing when this page was first written. A one-letter
// query must search nothing at all, or the box is a way to list the playerbase.
check(
    'the community people tab refuses a one-letter query',
    str_contains(
        request('/community?tab=users&q=' . substr($name, 0, 1))['body'],
        'at least'
    )
);
// The way out has to be somewhere you can actually reach on a phone, and on a
// phone that is your own page — the sidebar's copy is desktop-only now.
check(
    'your own page offers a way to log out',
    str_contains($captured['/player/' . $name], 'action="/logout"')
);

echo "\nForms work:\n";
request('/profile', [
    '_csrf_token' => tokenFrom('/profile/edit'),
    'avatar_key' => 'default',
    'about' => 'A quiet wanderer.',
    'is_findable' => 'yes',
]);
check('an about text saves', str_contains(request('/player/' . $name)['body'], 'A quiet wanderer.'));

request('/profile', [
    '_csrf_token' => tokenFrom('/profile/edit'),
    'avatar_key' => 'default',
    'about' => 'find me at example.com',
    'is_findable' => 'yes',
]);
$afterLink = request('/player/' . $name)['body'];
check('a link in an about text is refused', !str_contains($afterLink, 'example.com'));
check('the previous text survives the refusal', str_contains($afterLink, 'A quiet wanderer.'));

// ---------------------------------------------------------------------------
// Renaming a creature
//
// Renaming shipped once as a route with no form anywhere on the site: the
// endpoint answered perfectly and no player could ever reach it. So the first
// check here is not "does renaming work" but "is there a box to type in".
// ---------------------------------------------------------------------------

// The account registered above was given one starter creature; find its page.
preg_match('~href="(/creature/\d+)"~', request('/creatures')['body'], $creatureLink);
$creaturePath = $creatureLink[1] ?? null;

check('the new player has a creature to open', $creaturePath !== null);

if ($creaturePath !== null) {
    $creaturePage = request($creaturePath)['body'];

    check(
        'the creature page offers a rename box to its owner',
        str_contains($creaturePage, 'action="' . $creaturePath . '/rename"')
    );

    request($creaturePath . '/rename', [
        '_csrf_token' => tokenFrom($creaturePath),
        'name' => 'Smoketest',
    ]);
    check('a creature can be renamed', str_contains(request($creaturePath)['body'], 'Smoketest'));

    // An empty name must be refused and must change nothing — the creature keeps
    // the name it had rather than ending up nameless.
    request($creaturePath . '/rename', [
        '_csrf_token' => tokenFrom($creaturePath),
        'name' => '   ',
    ]);
    check('an empty name is refused', str_contains(request($creaturePath)['body'], 'Smoketest'));
}

// ---------------------------------------------------------------------------
// Markup mistakes that are embarrassing to ship
// ---------------------------------------------------------------------------

echo "\nMarkup:\n";
foreach ($captured as $path => $html) {
    // Comments are stripped first: a comment MENTIONING a tag is not that tag.
    $html = (string) preg_replace('~<!--.*?-->~s', '', $html);

    check($path . ' — every image has alt text', preg_match('~<img\b(?![^>]*\balt=)~i', $html) !== 1);

    preg_match_all('~\bid="([^"]+)"~', $html, $ids);
    $duplicates = array_keys(array_filter(array_count_values($ids[1]), static fn (int $n): bool => $n > 1));
    check($path . ' — no repeated ids', $duplicates === [], implode(', ', $duplicates));

    preg_match_all('~<h([1-6])\b~i', $html, $levels);
    $previous = 0;
    $skipped = false;
    foreach ($levels[1] as $level) {
        if ($previous !== 0 && (int) $level > $previous + 1) {
            $skipped = true;
        }
        $previous = (int) $level;
    }
    check($path . ' — heading levels never skip', !$skipped);
}

// ---------------------------------------------------------------------------
// Nothing was logged that should not have been
// ---------------------------------------------------------------------------

echo "\nQuiet logs:\n";
$appLog = glob(__DIR__ . '/../logs/*.log');
$logged = '';
foreach ($appLog as $file) {
    $logged .= (string) file_get_contents($file);
}
check('the application logged no errors', !str_contains($logged, 'ERROR'), substr($logged, 0, 200));

// ---------------------------------------------------------------------------
// Tidy up and report
// ---------------------------------------------------------------------------

if (is_resource($server)) {
    proc_terminate($server);
    proc_close($server);
}
@unlink(sys_get_temp_dir() . '/felkyo-smoke-cookies.txt');

echo "\n" . str_repeat('-', 60) . "\n";

if ($failures === []) {
    echo "All {$checks} checks passed. The website works, not just the code.\n";
    echo "(The test account \"{$name}\" is left behind on purpose — delete it if it bothers you.)\n";
    exit(0);
}

echo count($failures) . " of {$checks} checks FAILED:\n\n";
foreach ($failures as $failure) {
    echo "  - {$failure}\n";
}
exit(1);
