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
 * @param array<string, string|CURLFile> $post Sending this makes it a POST.
 *        A CURLFile value makes it a real multipart file upload, exactly as
 *        a browser's <input type="file"> sends one.
 * @return array{status: int, body: string, contentType: string}
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
        // curl sends "Expect: 100-continue" with file uploads, and PHP's
        // built-in development server answers that with "417 Expectation
        // Failed" instead of the page — so every upload here would fail
        // for a reason that has nothing to do with the site (the live
        // server handles the header fine). An empty Expect header tells
        // curl not to ask.
        CURLOPT_HTTPHEADER => ['Expect:'],
    ]);

    if ($post !== []) {
        curl_setopt($handle, CURLOPT_POST, true);
        // An array with a CURLFile in it must be handed to curl AS an array —
        // that is what makes it multipart. Everything else is a plain form.
        $hasFile = array_filter($post, static fn ($value): bool => $value instanceof CURLFile) !== [];
        curl_setopt($handle, CURLOPT_POSTFIELDS, $hasFile ? $post : http_build_query($post));
    }

    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);

    return ['status' => $status, 'body' => $body, 'contentType' => $contentType];
}

/**
 * The CSRF token from a page, so forms can be submitted the way a browser does.
 */
function tokenFrom(string $path): string
{
    return tokenIn(request($path)['body']);
}

/**
 * The CSRF token out of HTML we have already fetched.
 *
 * WHY THIS EXISTS SEPARATELY: tokenFrom() makes its own request, and for most
 * pages that is harmless. It is not harmless for /creature/{id}/play, because
 * OPENING that page starts a new round — so fetching a token from it threw away
 * the round under test and replaced it with a different, randomly chosen game.
 * The hint-game checks were then submitting guesses against a one-shot game and
 * failing for a reason that had nothing to do with the code.
 */
function tokenIn(string $body): string
{
    preg_match('~name="_csrf_token" value="([^"]+)"~', $body, $m);

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
// The router script makes the built-in server hand /art/... addresses to the
// app instead of answering "no such file" itself — see bin/dev-server-router.php.
$command = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . PORT . ' -t ' . escapeshellarg(__DIR__ . '/../public')
    . ' ' . escapeshellarg(__DIR__ . '/dev-server-router.php');
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

/*
 * The prefix used to search for this run's own account.
 *
 * It is the name minus its last character, not the first four. "smok" WAS the
 * first four, and it broke: every run leaves its account behind on purpose, so
 * after twenty runs "smok" matched twenty accounts, hit the result cap, and this
 * run's own name was no longer among them. The test failed while search worked
 * perfectly — a false alarm, which is the second-worst kind of test.
 *
 * A near-complete prefix is still a genuine prefix search (it proves matching on
 * a partial name works) and stays unique however many accounts pile up.
 */
$searchPrefix = substr($name, 0, -1);

$pages = [
    '/', '/creatures', '/explore', '/shop', '/inventory', '/browse',
    '/players', '/players?q=' . $searchPrefix, '/profile/edit', '/player/' . $name,
    // The community hub and BOTH of its tabs. Loading only the default tab would
    // have missed the people half entirely — and the people half is the one that
    // runs a query and the one with the guards on it.
    '/community', '/community?tab=creatures',
    '/community?tab=users', '/community?tab=users&q=' . $searchPrefix,
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
    $captured['/players?q=' . $searchPrefix],
    $found
);
check(
    'search finds a player by name prefix',
    in_array($name, $found[1] ?? [], true),
    'searching for a name that definitely exists returned ' . count($found[1] ?? []) . ' results'
);
check(
    'search remembers what was typed',
    str_contains($captured['/players?q=' . $searchPrefix], 'value="' . $searchPrefix . '"')
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
    $captured['/community?tab=users&q=' . $searchPrefix],
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

    // -----------------------------------------------------------------------
    // Moods, treats and the keepsake card
    //
    // The heart of M2, and the part a unit test cannot vouch for: whether the
    // buttons a player would actually press are on the page, and whether
    // pressing them changes what the page then says.
    // -----------------------------------------------------------------------

    echo "\nMoods and treats:\n";

    check(
        'the creature page says how the creature feels, in words',
        str_contains(request($creaturePath)['body'], 'Smoketest is ')
    );
    check(
        'the creature page draws the mood bars',
        str_contains(request($creaturePath)['body'], 'mood-bar__fill--happiness')
    );

    // A brand-new player is given a few treats along with their creature, so
    // feeding is something they can try immediately. That gift is the reason this
    // section needs no shopping trip — and checking it here is also how we know
    // the gift actually happens, which is easy to break and invisible if it does.
    check(
        'a new player starts with treats to give',
        str_contains(request('/inventory')['body'], 'Honey Treat')
    );

    // Which treat is which, read off the page the way a player would.
    $creaturePage = request($creaturePath)['body'];
    check(
        'the creature page offers the treats to its owner',
        str_contains($creaturePage, 'action="' . $creaturePath . '/feed"')
    );
    check(
        'each treat says what it does',
        str_contains($creaturePage, 'happiness') && str_contains($creaturePage, 'energy')
    );

    preg_match_all(
        '~<label class="creature__treat">.*?value="(\d+)".*?creature__treat-name">\s*([^<]+?)\s*<~s',
        $creaturePage,
        $treatMatches,
        PREG_SET_ORDER
    );
    $honeyItemId = null;
    foreach ($treatMatches as $match) {
        if (str_contains($match[2], 'Honey Treat')) {
            $honeyItemId = $match[1];
            break;
        }
    }
    check('the honey treat is one of the choices', $honeyItemId !== null);

    if ($honeyItemId !== null) {
        // Feeding is the whole point: the treat must go, and the page must say so.
        //
        // The ANSWER TO THE POST is what gets checked, not a page fetched after
        // it. curl follows the redirect, so the post's own response already is
        // the creature page — and the flash message is one-time, so a second
        // request would find it already spent and quietly pass nothing.
        $fed = request($creaturePath . '/feed', [
            '_csrf_token' => tokenFrom($creaturePath),
            'item_id' => $honeyItemId,
        ]);
        // The message names the creature and the treat. WHICH of the three
        // reactions it is depends on what this species thinks of honey, and that
        // pairing is content the artist owns — so the check is on the claim that
        // survives a retune, not on one particular sentence.
        check(
            'feeding says what happened, naming the creature and the treat',
            str_contains($fed['body'], 'Smoketest') && str_contains($fed['body'], 'Honey Treat')
        );
        // A taste reaction really fired, whichever way it went. The three lines are
        // the whole point of tastes, so a feed that produced the plain "enjoyed"
        // line would mean the species' pairings never loaded.
        check(
            'and reacts to its taste',
            str_contains($fed['body'], 'absolutely loves')
                || str_contains($fed['body'], 'made a face')
                || str_contains($fed['body'], 'enjoyed the')
        );
        check(
            'the treat was really eaten',
            !str_contains(request('/inventory')['body'], 'Honey Treat')
        );

        // Feeding the same treat again must be refused, not silently repeated.
        // This is the double-tap that would otherwise spend one item twice.
        $fedAgain = request($creaturePath . '/feed', [
            '_csrf_token' => tokenFrom($creaturePath),
            'item_id' => $honeyItemId,
        ]);
        check(
            'feeding a treat you no longer have is refused',
            str_contains($fedAgain['body'], 'do not have one')
        );
    }

    // -----------------------------------------------------------------------
    // Playing a game
    //
    // The check that matters is the LAST one: the answer must not be anywhere in
    // the page. The whole reason these are guessing games rather than arcade games
    // is that the server keeps the answer — if it ever leaked into the markup, the
    // reward would become a button that says "I won".
    // -----------------------------------------------------------------------

    echo "\nPlaying a game:\n";

    check(
        'the creature page offers a game to its owner',
        str_contains(request($creaturePath)['body'], 'href="' . $creaturePath . '/play"')
    );

    $gamePage = request($creaturePath . '/play');
    check('a game opens', $gamePage['status'] === 200 && str_contains($gamePage['body'], 'play__choice'));
    check(
        'and says what happens if you guess wrong',
        str_contains($gamePage['body'], 'still has a lovely time')
    );

    // Every choice is its own submit button, so the page works with no JavaScript.
    preg_match_all('~name="choice"\s+value="(\d+)"~', $gamePage['body'], $choices);
    $choiceCount = count($choices[1] ?? []);
    check('there is more than one thing to choose', $choiceCount >= 2, $choiceCount . ' choices');

    // THE ANSWER IS NOT IN THE PAGE. Nothing in the markup may name it — not in a
    // hidden field, not in a data attribute, not in a comment.
    check(
        'the answer is nowhere in the page',
        !preg_match('~\b(answer|correct|solution|winning)\b~i', $gamePage['body'])
    );

    // THE HINT GAME, which runs over several turns.
    //
    // Which game a round opens is random, and guessing 1 wins one time in twenty,
    // so this keeps opening rounds until it has actually seen a wrong guess get a
    // hint. What is being checked is that a genuinely multi-turn mechanic exists
    // and behaves — not which game you happened to be dealt.
    $hintGameSeen = false;
    $sawHint = false;
    $hintLine = null;

    for ($attempt = 0; $attempt < 40 && !$sawHint; $attempt++) {
        $page = request($creaturePath . '/play');

        if (!str_contains($page['body'], 'play--kind-narrow')) {
            continue;
        }

        if (!$hintGameSeen) {
            $hintGameSeen = true;
            check('a hint game says how many guesses are left', str_contains($page['body'], 'guesses left'));
        }

        // The token comes out of the page we already have. Asking for it again
        // would open a FRESH round and could hand us a different game entirely —
        // which is what made this check fail once for reasons unrelated to the code.
        $answered = request($creaturePath . '/play', [
            '_csrf_token' => tokenIn($page['body']),
            'choice' => '0',
        ]);

        if (str_contains($answered['body'], 'play__hint')) {
            $sawHint = true;
            preg_match('~class="play__hint"[^>]*>([^<]+)<~', $answered['body'], $matched);
            $hintLine = trim($matched[1] ?? '');
        }
        // Otherwise that guess won outright and the round is over; the loop opens
        // another one.
    }

    check('the hint game turns up at all', $hintGameSeen);
    check('a wrong guess gets a hint and another go', $sawHint);
    check(
        'the hint says a direction and never the number',
        $sawHint && $hintLine !== null && !preg_match('~\d~', $hintLine),
        (string) ($hintLine ?? '(no hint seen)')
    );

    if ($choiceCount >= 2) {
        // Guess. It is one-in-two or one-in-three, so this may win or lose — and
        // BOTH must produce a warm answer and leave the creature no worse off.
        $guessed = request($creaturePath . '/play', [
            '_csrf_token' => tokenFrom($creaturePath . '/play'),
            'choice' => '0',
        ]);
        check(
            'guessing gets an answer either way',
            str_contains($guessed['body'], 'class="flash"')
        );

        // Re-posting the same guess must find no round. This is the retry-until-you-win
        // hole, and it is closed by clearing the round before judging it.
        $replayed = request($creaturePath . '/play', [
            '_csrf_token' => tokenFrom($creaturePath),
            'choice' => '0',
        ]);
        check(
            'answering a finished game is refused',
            str_contains($replayed['body'], 'That game has finished')
        );
    }

    // The shop still sells treats, for the players who run out of the free ones.
    $shopPage = request('/shop')['body'];
    check('the shop sells treats', str_contains($shopPage, 'Honey Treat'));

    // -----------------------------------------------------------------------
    // Buying a creature
    //
    // This replaced daily adoption, so the checks are: is the offer on the page,
    // does it name a price, and does trying to buy one you cannot afford say
    // exactly how short you are rather than just refusing.
    // -----------------------------------------------------------------------

    check('the shop offers creatures', str_contains($shopPage, 'creature-shop__offer'));
    check('each creature names its price', str_contains($shopPage, 'creature-shop__price'));

    preg_match('~name="species_id" value="(\d+)"~', $shopPage, $speciesMatch);
    $speciesId = $speciesMatch[1] ?? null;
    check('a creature can be chosen', $speciesId !== null);

    if ($speciesId !== null) {
        // A brand-new player has no gems, so this is the refusal path — and the
        // refusal is the more important half: it has to say the exact gap and
        // where gems come from, or somebody with an empty purse is stuck.
        $tooPoor = request('/shop/creature', [
            '_csrf_token' => tokenFrom('/shop'),
            'species_id' => $speciesId,
        ]);
        check(
            'trying to buy without gems says how many are missing',
            str_contains($tooPoor['body'], 'You need') && str_contains($tooPoor['body'], 'more gems')
        );
        check(
            'and says where gems come from',
            str_contains($tooPoor['body'], 'petting other players')
        );
    }

    // The old adoption address still leads somewhere sensible rather than a 404.
    check(
        'the retired /adopt page sends you to the shop',
        str_contains(request('/adopt')['body'], 'creature-shop__offer')
    );

    // The keepsake card only appears once a favourite has been chosen, and the
    // star on the creature's own page is how a player would choose one. Pressing
    // it here is also how we know the star is REACHABLE — favourites used to be
    // set only from the profile settings page, which most people never opened.
    check(
        'the creature page offers the favourite star to its owner',
        str_contains(request($creaturePath)['body'], 'action="' . $creaturePath . '/favourite"')
    );

    $starred = request($creaturePath . '/favourite', [
        '_csrf_token' => tokenFrom($creaturePath),
        'from' => 'creature',
    ]);
    check('starring says what happened', str_contains($starred['body'], 'one of your favourites'));
    check(
        'the star now shows as pressed',
        str_contains(request($creaturePath)['body'], 'aria-pressed="true"')
    );

    $home = request('/')['body'];
    check('choosing a favourite puts the keepsake card on the page', str_contains($home, 'class="keepsake"'));
    check('the keepsake card offers a pet button', str_contains($home, 'keepsake__actions'));
    check('the keepsake card shows how the creature feels', str_contains($home, 'keepsake__mood'));

    // THE CARD'S OWN BUTTONS MUST ANSWER. They redirect to the home page, and the
    // home page had no flash block — so petting or feeding from the card said
    // nothing whatsoever, which is the one thing golden rule 4 forbids. The
    // message is now drawn by the layout, so no page can forget it again.
    $pettedFromCard = request($creaturePath . '/pet', [
        '_csrf_token' => tokenFrom('/'),
        'from' => 'home',
    ]);
    check(
        'petting from the keepsake card lands you back on the home page',
        str_contains($pettedFromCard['body'], 'class="keepsake"')
    );
    check(
        'and says what happened there',
        str_contains($pettedFromCard['body'], 'class="flash"')
    );
    check(
        'and plays the celebration on the card',
        str_contains($pettedFromCard['body'], 'mood-bar__fill--just-changed')
    );

    // THE CARD IS SHORT ENOUGH TO SIT IN A COLUMN. It used to carry a radio card
    // per treat, which made the sidebar taller than a laptop screen — and since the
    // column is sticky, that put the card itself out of reach until you scrolled to
    // the very bottom of the page. Feeding from the card is now one button and the
    // server picks the treat, so the list must not come back.
    check(
        'the keepsake card has no treat list in it',
        !str_contains($home, 'keepsake__treats')
    );
    check(
        'the keepsake card offers pet, feed and play',
        substr_count($home, 'keepsake__btn') >= 3
    );

    // Feeding with no treat named: the server chooses. This is the card's button.
    $fedFromCard = request($creaturePath . '/feed', [
        '_csrf_token' => tokenFrom('/'),
        'from' => 'home',
    ]);
    check(
        'feeding from the card picks a treat and says which',
        str_contains($fedFromCard['body'], 'Treat') || str_contains($fedFromCard['body'], 'Bundle')
            || str_contains($fedFromCard['body'], 'no treats just now')
    );
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
// The creator's panel (M2.1), walked exactly as staff will walk it
//
// The whole arc in one sitting: a plain player cannot even see the panel; the
// CLI script founds them as an owner; the door demands their password; first
// entry walks through connecting an authenticator app (the TOTP code is
// computed here with the same class the site uses — this run IS the
// authenticator); the roles screen grants and revokes; the audit page
// remembers all of it.
// ---------------------------------------------------------------------------

echo "\nThe creator's panel:\n";

// Before any role: the panel does not exist for a player.
check(
    'a plain player gets a 404 from /admin',
    request('/admin')['status'] === 404,
    'status ' . request('/admin')['status']
);

// Found the first owner the only way it can be done: the CLI script.
$grantOutput = (string) shell_exec(
    escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/grant-role.php')
    . ' ' . escapeshellarg($name) . ' owner 2>&1'
);
check('bin/grant-role.php founds an owner', str_contains($grantOutput, 'is now: Owner'), $grantOutput);

// The panel now exists — and leads to the door, not straight in.
$towardsPanel = request('/admin');
check('an owner is met by the panel door', str_contains($towardsPanel['body'], 'The panel door'));

// Password at the door. Not enrolled yet, so this lands on enrolment.
$enrolPage = request('/admin/door', [
    '_csrf_token' => tokenIn($towardsPanel['body']),
    'password' => 'a-long-enough-password',
]);
check('the door leads first entry to enrolment', str_contains($enrolPage['body'], 'add an account by entering a key'));

// Read the secret off the page and compute the current code from it — the
// same maths the site and every authenticator app run (Totp is RFC-pinned).
preg_match('~admin-enrol__secret[^>]*>([A-Z2-7]+)<~', $enrolPage['body'], $secretMatch);
$enrolSecret = $secretMatch[1] ?? '';
check('the enrolment page shows a secret', $enrolSecret !== '');

$totp = new Felkyo\Admin\Totp();
$recoveryPage = request('/admin/enrol', [
    '_csrf_token' => tokenIn($enrolPage['body']),
    'code' => $totp->codeAt($enrolSecret, time()),
]);
check('one correct code completes enrolment', str_contains($recoveryPage['body'], 'recovery'));
check(
    'the recovery codes are shown, once',
    (bool) preg_match('/[0-9A-F]{4}-[0-9A-F]{4}/', $recoveryPage['body'])
);

// Enrolment also opened the door — the panel home renders.
check('the panel opens after enrolment', str_contains(request('/admin')['body'], 'You&rsquo;re here as'));

// The roles screen: grant this account the Artist role, see it appear,
// take it away again, see it go.
$rolesPage = request('/admin/roles');
check('the roles screen renders for an owner', str_contains($rolesPage['body'], 'Hand out a role'));

$afterGrant = request('/admin/roles/grant', [
    '_csrf_token' => tokenIn($rolesPage['body']),
    'username' => $name,
    'role' => 'artist',
]);
check('granting a role says so', str_contains($afterGrant['body'], 'now has the Artist role'));

$afterRevoke = request('/admin/roles/revoke', [
    '_csrf_token' => tokenIn($afterGrant['body']),
    'username' => $name,
    'role' => 'artist',
]);
check('revoking it says so', str_contains($afterRevoke['body'], 'no longer has the Artist role'));

// The audit page remembers the founding, the door, and both role changes.
$auditPage = request('/admin/audit')['body'];
check('the audit log shows the panel entry', str_contains($auditPage, 'entered the panel'));
check('the audit log shows the role changes', str_contains($auditPage, 'granted a role') && str_contains($auditPage, 'took a role away'));

// ---------------------------------------------------------------------------
// The art store (M2.2), through the real upload form
//
// A real multipart upload of a real animated GIF (built by the test support
// class, with a comment block and script after the trailer that must NOT come
// back out), a disguised script that must be refused with a reason, the
// served picture's headers, and the delete button.
// ---------------------------------------------------------------------------

echo "\nThe art store:\n";

$picturesPage = request('/admin/pictures');
check('the Pictures screen renders for an owner', str_contains($picturesPage['body'], 'Add a picture'));

// A frame delay unique to this run makes the bytes — and so the content
// hash — unique to this run. Otherwise a picture left behind by an earlier
// run (or by gui-shots, which uploads the same test GIF) would answer
// "already here" instead of "is in".
$animatedGif = sys_get_temp_dir() . '/felkyo-smoke-anim.gif';
file_put_contents($animatedGif, Felkyo\Tests\Support\Pictures::animatedGif(random_int(11, 60000)));
$afterUpload = request('/admin/pictures/upload', [
    '_csrf_token' => tokenIn($picturesPage['body']),
    'kind' => 'sprite',
    'picture' => new CURLFile($animatedGif, 'image/gif', 'smoke-anim.gif'),
]);
check('uploading an animated GIF says it is in', str_contains($afterUpload['body'], 'is in'));
check('the grid shows it, labelled animated', str_contains($afterUpload['body'], 'smoke-anim.gif') && str_contains($afterUpload['body'], 'animated'));

preg_match('~/art/([a-f0-9]{64}\.gif)~', $afterUpload['body'], $artMatch);
$servedPicture = request('/art/' . ($artMatch[1] ?? 'missing'));
check('the picture is served as image/gif', str_starts_with($servedPicture['contentType'], 'image/gif'), $servedPicture['contentType']);
check('the served bytes are a GIF', str_starts_with($servedPicture['body'], 'GIF89a'));
check('the comment block did not survive upload', !str_contains($servedPicture['body'], 'secret-note'));
check('the bytes after the trailer did not survive upload', !str_contains($servedPicture['body'], 'after-the-end'));

$disguised = sys_get_temp_dir() . '/felkyo-smoke-disguised.gif';
file_put_contents($disguised, '<?php echo "not a picture"; ?>');
$refused = request('/admin/pictures/upload', [
    '_csrf_token' => tokenIn($afterUpload['body']),
    'kind' => 'sprite',
    'picture' => new CURLFile($disguised, 'image/gif', 'sneaky.gif'),
]);
// The page escapes the apostrophe (&#039;), so compare against the decoded text.
check('a script called .gif is refused with a reason', str_contains(html_entity_decode($refused['body'], ENT_QUOTES), 'isn\'t a GIF, PNG or WebP'));
check('and does not appear in the grid', !str_contains($refused['body'], 'sneaky.gif'));

// The delete form INSIDE OUR picture's card — not the first delete form on the
// page, which may belong to a picture an earlier run or gui-shots left behind.
$ourCard = substr($refused['body'], (int) strpos($refused['body'], 'smoke-anim.gif'));
preg_match('~/admin/pictures/(\d+)/delete~', $ourCard, $deleteMatch);
$afterDelete = request('/admin/pictures/' . ($deleteMatch[1] ?? '0') . '/delete', [
    '_csrf_token' => tokenIn($refused['body']),
]);
// The confirmation sentence itself names the file, so "gone from the grid"
// is checked on the grid's own markup — the <img alt> — not the whole page.
check('deleting says so', str_contains($afterDelete['body'], 'is deleted'));
check('and the picture is gone from the grid', !str_contains($afterDelete['body'], 'alt="smoke-anim.gif"'));
check('and it is no longer served', request('/art/' . ($artMatch[1] ?? 'missing'))['status'] === 404);
@unlink($animatedGif);
@unlink($disguised);

// ---------------------------------------------------------------------------
// The species editor (M2.3): a whole species built through the real forms
//
// The M2 definition of done, walked by machine ahead of the artist: start a
// draft, hang a real uploaded sprite on every stage, write its words, watch
// the shop REFUSE to show a draft, publish, and watch the shop shelve it.
// ---------------------------------------------------------------------------

echo "\nThe species editor:\n";

$speciesName = 'Wumple' . random_int(1000, 9999);
$creaturesPage = request('/admin/creatures');
check('the species list renders for an owner', str_contains($creaturesPage['body'], 'Start a new species'));

$editorPage = request('/admin/creatures/create', [
    '_csrf_token' => tokenIn($creaturesPage['body']),
    'name' => $speciesName,
]);
check('creating a species lands on its editor', str_contains($editorPage['body'], $speciesName));
preg_match('~action="/admin/creatures/(\d+)/words"~', $editorPage['body'], $speciesMatch);
$speciesId = $speciesMatch[1] ?? '0';

// A sprite for it, uploaded fresh so this run owns it.
$spriteFile = sys_get_temp_dir() . '/felkyo-smoke-species.gif';
file_put_contents($spriteFile, Felkyo\Tests\Support\Pictures::animatedGif(random_int(11, 60000)));
$afterSpriteUpload = request('/admin/pictures/upload', [
    '_csrf_token' => tokenIn($editorPage['body']),
    'kind' => 'sprite',
    'picture' => new CURLFile($spriteFile, 'image/gif', $speciesName . '.gif'),
]);
@unlink($spriteFile);

// Hang it on every stage through the real picker pages.
$stagesLinked = true;
foreach (['baby', 'juvenile', 'adult'] as $stageName) {
    $pickerPage = request('/admin/creatures/' . $speciesId . '/picture/' . $stageName);
    preg_match('~name="art_id" value="(\d+)"~', $pickerPage['body'], $artMatch2);
    $chose = request('/admin/creatures/' . $speciesId . '/picture/' . $stageName, [
        '_csrf_token' => tokenIn($pickerPage['body']),
        'art_id' => $artMatch2[1] ?? '0',
    ]);
    $stagesLinked = $stagesLinked && str_contains($chose['body'], 'Picture linked');
}
check('a sprite can be chosen for every stage', $stagesLinked);

$afterWords = request('/admin/creatures/' . $speciesId . '/words', [
    '_csrf_token' => tokenIn($editorPage['body']),
    'name' => $speciesName,
    'flavour_text' => 'A small brave smoke-being.',
    'lore' => 'Its long story waits for the library.',
]);
check('its words save', str_contains($afterWords['body'], 'Words saved'));

$afterSettings = request('/admin/creatures/' . $speciesId . '/settings', [
    '_csrf_token' => tokenIn($afterWords['body']),
    'gem_price' => '5', 'is_adoptable' => '1',
    'favourite_item_id' => '', 'disliked_item_id' => '',
]);
check('its settings save', str_contains($afterSettings['body'], 'Settings saved'));

// THE refusal that matters: a draft, adoptable and priced, must not be on
// the shop shelf.
check('the shop does NOT shelve a draft', !str_contains(request('/shop')['body'], $speciesName));

$previewPage = request('/admin/creatures/' . $speciesId . '/preview?stage=juvenile');
check('the preview shows it on the real sheet', str_contains($previewPage['body'], $speciesName) && str_contains($previewPage['body'], 'juvenile'));

$afterPublish = request('/admin/creatures/' . $speciesId . '/publish', [
    '_csrf_token' => tokenIn($afterSettings['body']),
]);
check('publishing says it is live', str_contains($afterPublish['body'], 'is live'));
check('and NOW the shop shelves it', str_contains(request('/shop')['body'], $speciesName));

// Tidy the shelf: back to draft so repeated runs do not fill the shop.
request('/admin/creatures/' . $speciesId . '/unpublish', ['_csrf_token' => tokenIn($afterPublish['body'])]);
check('back to draft, off the shelf again', !str_contains(request('/shop')['body'], $speciesName));

// ---------------------------------------------------------------------------
// The item and shop editors (M2.4), through the real forms
//
// Build an item, watch the shop refuse the draft, publish and shelve it,
// buy one; give the keeper a greeting and see it on the real shop page.
// Everything is tidied afterwards so repeated runs stay clean.
// ---------------------------------------------------------------------------

echo "\nThe item and shop editors:\n";

$itemName = 'Smokeberry' . random_int(1000, 9999);
$itemsPage = request('/admin/items');
check('the item list renders for an owner', str_contains($itemsPage['body'], 'Start a new item'));

preg_match('~name="category_id" value="(\d+)"~', $itemsPage['body'], $categoryMatch);
$itemEditor = request('/admin/items/create', [
    '_csrf_token' => tokenIn($itemsPage['body']),
    'name' => $itemName,
    'category_id' => $categoryMatch[1] ?? '0',
]);
check('creating an item lands on its editor', str_contains($itemEditor['body'], $itemName));
preg_match('~action="/admin/items/(\d+)/words"~', $itemEditor['body'], $itemMatch);
$itemId = $itemMatch[1] ?? '0';

// The margin rule refuses at save, in plain words.
$refusedNumbers = request('/admin/items/' . $itemId . '/numbers', [
    '_csrf_token' => tokenIn($itemEditor['body']),
    'price' => '10', 'sell_value' => '9',
    'happiness_bonus' => '0', 'energy_bonus' => '0',
    'category_id' => $categoryMatch[1] ?? '0',
]);
check('a sell value over the margin is refused at save', str_contains($refusedNumbers['body'], 'at most 8'));

request('/admin/items/' . $itemId . '/numbers', [
    '_csrf_token' => tokenIn($refusedNumbers['body']),
    'price' => '3', 'sell_value' => '2',
    'happiness_bonus' => '0', 'energy_bonus' => '0',
    'category_id' => $categoryMatch[1] ?? '0',
]);
check('the shop does NOT shelve or show a draft item', !str_contains(request('/shop')['body'], $itemName));

$afterItemPublish = request('/admin/items/' . $itemId . '/publish', ['_csrf_token' => tokenIn($itemEditor['body'])]);
check('publishing the item says it is live', str_contains($afterItemPublish['body'], 'is live'));

// Shelve it in the general store through the shop editor.
$shopsPage = request('/admin/shops');
preg_match('~href="/admin/shops/(\d+)"~', $shopsPage['body'], $shopMatch);
$shopId = $shopMatch[1] ?? '0';
$shopEditor = request('/admin/shops/' . $shopId);
$afterShelf = request('/admin/shops/' . $shopId . '/shelf', [
    '_csrf_token' => tokenIn($shopEditor['body']),
    'item_id' => $itemId, 'available_from' => '', 'available_to' => '',
]);
check('shelving says so', str_contains($afterShelf['body'], 'on the shelf'));
check('and the real shop now sells it', str_contains(request('/shop')['body'], $itemName));

// The keeper speaks — set a greeting, see it on the player-facing page.
$greetingWord = 'Willkommen' . random_int(100, 999);
request('/admin/shops/' . $shopId . '/keeper', [
    '_csrf_token' => tokenIn($afterShelf['body']),
    'keeper_name' => 'Ruinily',
    'keeper_greeting' => $greetingWord . ', wanderer!',
    'keeper_refusal' => 'Ruinily doesn’t have anything like that on the shelves.',
]);
check('the keeper\'s greeting appears on the real shop page', str_contains(request('/shop')['body'], $greetingWord));

// Buy one — the whole point of a shelf.
$shopPage = request('/shop');
$bought = request('/shop/buy', ['_csrf_token' => tokenIn($shopPage['body']), 'item_id' => $itemId]);
check('buying it works or explains the gap in gems', str_contains($bought['body'], 'You bought') || str_contains($bought['body'], 'more gem'));

// Tidy: off the shelf, back to draft, greeting cleared to the seeded state.
request('/admin/shops/' . $shopId . '/shelf/' . $itemId . '/remove', ['_csrf_token' => tokenIn($shopPage['body'])]);
request('/admin/items/' . $itemId . '/unpublish', ['_csrf_token' => tokenIn($shopPage['body'])]);
request('/admin/shops/' . $shopId . '/keeper', [
    '_csrf_token' => tokenIn($shopPage['body']),
    'keeper_name' => 'Ruinily', 'keeper_greeting' => '',
    'keeper_refusal' => 'Ruinily doesn’t have anything like that on the shelves.',
]);
check('tidied: the item is off the real shelf again', !str_contains(request('/shop')['body'], $itemName));

// ---------------------------------------------------------------------------
// The area editor (M2.5), through the real forms
//
// Edit the real whispering-wood loot table through the plain-line field,
// see the change land, watch a refused save keep the typing, and put the
// original table back word for word so repeated runs stay clean.
// ---------------------------------------------------------------------------

echo "\nThe area editor:\n";

$areasPage = request('/admin/areas');
check('the area list renders for an owner', str_contains($areasPage['body'], 'Start a new area'));

// Find the seeded wood among the cards — its id differs between a fresh
// install and a migrated one, so we ask each editor page who it is.
preg_match_all('~href="/admin/areas/(\d+)"~', $areasPage['body'], $areaLinks);
$woodId = '0';
$woodEditor = ['status' => 0, 'body' => ''];
foreach (array_unique($areaLinks[1]) as $candidateId) {
    $candidate = request('/admin/areas/' . $candidateId);
    if (str_contains($candidate['body'], 'The Whispering Wood')) {
        $woodId = $candidateId;
        $woodEditor = $candidate;
        break;
    }
}
check('the whispering wood has an editor page', $woodId !== '0');

// The loot field reopens holding the current table — kept for restoring.
preg_match('~name="loot"[^>]*>(.*?)</textarea>~s', $woodEditor['body'], $lootMatch);
$originalLoot = html_entity_decode($lootMatch[1] ?? '', ENT_QUOTES, 'UTF-8');
check('the loot field holds the seeded table', str_contains($originalLoot, 'acorn-treat'));

// A real edit: one more line, typed into the real field.
$marker = 'Smokeleaf' . random_int(1000, 9999) . ' drifts past.';
$afterSave = request('/admin/areas/' . $woodId . '/loot', [
    '_csrf_token' => tokenIn($woodEditor['body']),
    'loot' => $originalLoot . "\n1; nothing; " . $marker,
]);
check('saving the table says so', str_contains($afterSave['body'], 'The table is saved'));
check('and the editor shows the new line', str_contains($afterSave['body'], $marker));
check('the real wood still opens for a player', request('/explore/whispering-wood')['status'] === 200);

// A refused save: nothing changes, and the typing survives — exactly once.
$refusedLoot = request('/admin/areas/' . $woodId . '/loot', [
    '_csrf_token' => tokenIn($afterSave['body']),
    'loot' => '0; nothing; a weight of zero is unwritable',
]);
check('a broken table is refused with nothing saved', str_contains($refusedLoot['body'], 'Nothing was saved'));
check('the refused typing is kept in the field', str_contains($refusedLoot['body'], 'a weight of zero is unwritable'));
check('the saved table itself is unharmed', str_contains($refusedLoot['body'], $marker));
$secondVisit = request('/admin/areas/' . $woodId);
check(
    'the field goes back to the real table on the next visit',
    !str_contains($secondVisit['body'], 'a weight of zero is unwritable')
        && str_contains($secondVisit['body'], $marker)
);

// Tidy: the original seeded table, word for word.
request('/admin/areas/' . $woodId . '/loot', [
    '_csrf_token' => tokenIn($secondVisit['body']),
    'loot' => $originalLoot,
]);
check('tidied: the seeded table is back', !str_contains(request('/admin/areas/' . $woodId)['body'], $marker));

// ---------------------------------------------------------------------------
// The unfinished list (M2.6)
//
// The M2.4 chapter above deliberately left its test item as a DRAFT — so
// the site itself should now be pointing at it. That makes this check
// honest: the list is generated from real state, not from anything this
// chapter set up for itself.
// ---------------------------------------------------------------------------

echo "\nThe unfinished list:\n";

$unfinishedPage = request('/admin/unfinished');
check('the unfinished list renders', str_contains($unfinishedPage['body'], 'Waiting to publish'));
check(
    'the draft item from the shop chapter is on it',
    str_contains($unfinishedPage['body'], $itemName),
    'expected to find ' . $itemName
);
check(
    'and its row names the editor page that fixes it',
    str_contains($unfinishedPage['body'], '/admin/items/' . $itemId)
);

// ---------------------------------------------------------------------------
// Moderation (M2.7)
//
// The queue and the account-history page render over real HTTP, and the
// self-moderation guard refuses through the real form — the deeper flows
// (dismiss/rename/mute and the notices) live in the integration tests,
// which build their own players to act on.
// ---------------------------------------------------------------------------

echo "\nModeration:\n";

// The whole loop, through the real forms: a report filed the way a player
// files one, the queue showing it, the account page reached through the
// queue's own link, and dismiss tidying it all away again.
preg_match('~/creature/(\d+)~', $creaturePath, $ownMatch);
$ownCreatureId = (int) ($ownMatch[1] ?? 0);

// Reporting something of your OWN is refused kindly — the button's most
// common misunderstanding, answered without filing anything.
$ownReportPage = request('/report/creature_name/' . $ownCreatureId);
$selfReport = request('/report', [
    '_csrf_token' => tokenIn($ownReportPage['body']),
    'subject' => 'creature_name', 'subject_id' => (string) $ownCreatureId,
    'reason' => 'rude_words',
]);
check('reporting your own creature is refused kindly', str_contains($selfReport['body'], 'yours'));

// Somebody else's creature (earlier runs leave plenty behind on purpose).
preg_match_all('~/creature/(\d+)~', request('/browse')['body'], $browseMatches);
$reportedId = null;
foreach (array_unique($browseMatches[1]) as $candidateId) {
    if ((int) $candidateId !== $ownCreatureId) {
        $reportedId = (int) $candidateId;
        break;
    }
}
check('somebody else\'s creature is there to report', $reportedId !== null);

$reportPage = request('/report/creature_name/' . $reportedId);
request('/report', [
    '_csrf_token' => tokenIn($reportPage['body']),
    'subject' => 'creature_name', 'subject_id' => (string) $reportedId,
    'reason' => 'rude_words',
]);

$queuePage = request('/admin/reports');
check('the filed report is in the queue with its reason', str_contains($queuePage['body'], 'rude word'));
preg_match('~/admin/accounts/(\d+)~', $queuePage['body'], $accountMatch);
preg_match('~/admin/reports/(\d+)/dismiss~', $queuePage['body'], $reportMatch);
check('the queue links to the account behind the report', isset($accountMatch[1]));

$accountPage = request('/admin/accounts/' . ($accountMatch[1] ?? '0'));
check('the account-history page renders', str_contains($accountPage['body'], 'Account #' . ($accountMatch[1] ?? '?')));

// Dismiss through the real form — which is also the tidy-up.
$afterDismiss = request('/admin/reports/' . ($reportMatch[1] ?? '0') . '/dismiss', [
    '_csrf_token' => tokenIn($queuePage['body']),
]);
check('dismissing says what happened', str_contains($afterDismiss['body'], 'Dismissed'));
check(
    'and the report is out of the queue',
    !str_contains(request('/admin/reports')['body'], '/admin/reports/' . ($reportMatch[1] ?? '?') . '/dismiss')
);

// ---------------------------------------------------------------------------
// Logging out
//
// This runs LAST, because everything after it would be a logged-out request.
//
// It was reported as broken, and "there is a log out button" is not the claim
// worth checking — the claim is that pressing it ends the session. So this posts
// the real form with the real token and then asks a page that requires an
// account whether it still knows who you are.
// ---------------------------------------------------------------------------

echo "\nLogging out:\n";
$logout = request('/logout', ['_csrf_token' => tokenFrom('/player/' . $name)]);
check('logging out sends you somewhere', in_array($logout['status'], [200, 302, 303], true), 'status ' . $logout['status']);

// /profile/edit is for logged-in players only, so a guest is sent to the log-in
// page. If we are still logged in, it renders the edit form instead.
$afterLogout = request('/profile/edit');
check(
    'you are really logged out afterwards',
    !str_contains($afterLogout['body'], 'name="avatar_key"'),
    'the profile edit form still rendered, so the session survived'
);
check(
    'the world bar offers a way back in',
    str_contains(request('/')['body'], 'href="/login"')
);

// The logged-out shell must not claim to have a sidebar. It has none, and the
// wide-screen layout is a two-column grid — so a shell that says otherwise puts
// the whole site inside the narrow sidebar column. That shipped, and it looked
// like the site had collapsed into a strip beside the wallpaper.
$loggedOutHome = request('/')['body'];
check(
    'the logged-out shell does not ask for a sidebar column',
    !str_contains($loggedOutHome, 'site-shell--with-side')
);
check(
    'and draws no empty sidebar panel',
    !str_contains($loggedOutHome, 'class="site-side"')
);
check(
    'the log-in page still renders for a guest',
    str_contains(request('/login')['body'], 'name="password"')
);

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
