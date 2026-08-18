<?php

declare(strict_types=1);

/**
 * Front controller — the single entry point for every web request to Felkyo.
 *
 * @package Felkyo
 *
 * WHAT "FRONT CONTROLLER" MEANS: the web server is configured so that every URL
 * on the site is handled by this one file. That gives us a single, predictable
 * place where a request begins, where we wire the pieces together (the "objects
 * that do the work"), and where we list the routes the site responds to.
 *
 * HOW TO READ THIS FILE: it goes top to bottom in the order a request needs —
 * start up, build the shared tools, build the repositories/services, work out who
 * is logged in, build the controllers, list the routes, then dispatch and send.
 * This manual wiring (plain "new") is deliberate: it is fully visible and easy to
 * follow, with no hidden framework magic (build plan section 2).
 */

use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Core\Database;
use Felkyo\Core\FileLogger;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreaturePurchaseService;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureMoments;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\FeedingService;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\MoodCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\ItemFinder;
use Felkyo\Safety\ContactDetailDetector;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Safety\ReportRepository;
use Felkyo\Safety\ReportService;
use Felkyo\Safety\TextGuard;
use Felkyo\Safety\WordBlocklist;
use Felkyo\Economy\ItemDisposalService;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\ItemRewardGranter;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookPanel;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Controllers\BioController;
use Felkyo\Http\Controllers\BrowseController;
use Felkyo\Http\Controllers\CollectionController;
use Felkyo\Http\Controllers\CommunityController;
use Felkyo\Http\Controllers\CreatureRenameController;
use Felkyo\Http\Controllers\CreatureController;
use Felkyo\Http\Controllers\ExplorationController;
use Felkyo\Http\Controllers\FavouriteController;
use Felkyo\Http\Controllers\FeedController;
use Felkyo\Http\Controllers\GuestbookController;
use Felkyo\Http\Controllers\HomeController;
use Felkyo\Http\Controllers\InventoryController;
use Felkyo\Http\Controllers\ItemController;
use Felkyo\Http\Controllers\ProfileController;
use Felkyo\Http\Controllers\ReportController;
use Felkyo\Http\Controllers\SearchController;
use Felkyo\Http\Controllers\LoginController;
use Felkyo\Http\Controllers\LogoutController;
use Felkyo\Http\Controllers\PetController;
use Felkyo\Http\Controllers\RegisterController;
use Felkyo\Http\Controllers\ShopController;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Http\Router;
use Felkyo\Security\RateLimiter;
use Felkyo\Security\RateLimitRepository;
use Felkyo\Users\AvatarSet;
use Felkyo\Users\PlayerFinder;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\ProfileService;
use Felkyo\Users\UserRepository;
use Felkyo\Users\UserValidator;
use League\Plates\Engine;

// ---- Start up ----
$config = require dirname(__DIR__) . '/config/bootstrap.php';
$request = Request::fromGlobals();

// ---- Shared tools ----
$logger = new FileLogger(dirname(__DIR__) . '/logs/app.log');

// Connecting to the database is the first thing that can fail for reasons outside
// the code — wrong credentials, a database that is not running, or a PHP build
// without the MySQL driver. Every one of those happened while first deploying this
// site, and each produced a completely blank page, because the failure happened
// before the error handling further down had been set up.
//
// So it gets its own guard. The visitor sees a short, human sentence; the actual
// reason goes to the log (and therefore to the hosting platform's log stream),
// where whoever is fixing it can read it. We deliberately do NOT show the
// exception text to visitors: it names the database host and user.
try {
    $pdo = Database::connect($config['database']);
} catch (\Throwable $error) {
    $logger->error('Could not connect to the database: ' . $error->getMessage());

    // A plain page, not the themed layout: the layout needs machinery (sessions,
    // the CSRF helper) that may not be ready this early, and an error page that
    // itself errors helps nobody.
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>Felkyo Creatures — back shortly</title></head><body>'
        . '<h1>Felkyo is having a quiet moment</h1>'
        . '<p>We can&rsquo;t reach the creatures right now. Please try again in a little while.</p>'
        . '</body></html>';
    exit;
}

// Sessions: the cookie is HTTPS-only in production, but not in local http dev
// (where a Secure cookie would never be sent). Production MUST run over HTTPS.
$session = new Session(cookieSecure: $config['app']['environment'] === 'production');
$session->start();
$csrf = new Csrf($session);

$templates = new Engine(dirname(__DIR__) . '/templates');

// Make a csrf_field() helper available to every template. It outputs the hidden
// field that protects a form; calling it in each <form> is how CSRF protection is
// applied automatically and consistently (CLAUDE.md section 6).
$templates->registerFunction('csrf_field', function () use ($csrf): string {
    $token = htmlspecialchars($csrf->token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
});

// ---- Repositories (own the database queries) ----
$userRepository = new UserRepository($pdo);
$rateLimitRepository = new RateLimitRepository($pdo);
$speciesRepository = new SpeciesRepository($pdo);
$creatureRepository = new CreatureRepository($pdo);
$pettingRepository = new PettingRepository($pdo);
$explorationRepository = new ExplorationRepository($pdo);
$shopRepository = new ShopRepository($pdo);
$inventoryRepository = new InventoryRepository($pdo);
$guestbookRepository = new GuestbookRepository($pdo);
$profileRepository = new ProfileRepository($pdo);

// The avatar allow-list. The set is content/config, so adding an avatar needs no
// code change (docs/adding-avatars.md). Built here (not further down with the
// profile controller) because the sidebar on EVERY page shows the logged-in
// player's avatar, so the layout needs it before any controller runs.
$avatarSet = new AvatarSet($config['avatars']);

// ---- Services (own the business rules) ----
$passwordHasher = new PasswordHasher();
$userValidator = new UserValidator($config['security']);
// The safety layer for every piece of text a player can write. There are only
// three such places on the whole site — an account name, a creature's name, and a
// bio or about text — and they all go through this one guard so they cannot drift
// into having three different sets of gaps. See docs/free-text-safety.md.
$impersonationGuard = new ImpersonationGuard();
$textGuard = new TextGuard(
    new WordBlocklist($config['moderation']['blocked_words']),
    new ContactDetailDetector(),
    $impersonationGuard
);

$registrationService = new RegistrationService(
    $userRepository, $userValidator, $passwordHasher,
    $textGuard, $impersonationGuard,
    $config['security']['username_max_length']
);
$authenticator = new Authenticator($userRepository, $passwordHasher);
$rateLimiter = new RateLimiter($rateLimitRepository);
$starterCreatureService = new StarterCreatureService(
    $speciesRepository, $creatureRepository, $config['gameplay']['creature_names'],
    // The last two give a new player a few treats along with their creature, so
    // feeding is something they can try on their first minute rather than an
    // errand they have to earn their way to. See the service for the reasoning.
    $pdo, $inventoryRepository
);
// Buying a creature. This replaced daily adoption in M2: a creature used to be
// one free arrival every 24 hours, which meant the only way to want another was
// to wait. Now gems buy them, and gems come from visiting other people's
// creatures — so the way you get a creature is the way the site is meant to be
// used. See CreaturePurchaseService for the whole reasoning.
$creaturePurchaseService = new CreaturePurchaseService(
    $pdo,
    $speciesRepository,
    $creatureRepository,
    $userRepository,
    $config['gameplay']['creature_names'],
    $config['gameplay']['currency']['name']
);
$growthCalculator = new GrowthCalculator(
    $config['gameplay']['growth']['xp_per_level'],
    $config['gameplay']['growth']['stage_start_levels']
);
// Petting needs the connection because paying for a pet and recording it must
// happen together, in one transaction (see PettingService for the whole reason).
// The currency knobs are folded into the petting settings so the service reads
// one array rather than three separate numbers.
// How creatures feel, and how that changes with time. One calculator, shared by
// everything that shows or changes a mood, so a creature can never look happier
// on its own page than it does on the card beside it.
$moodCalculator = new MoodCalculator($config['gameplay']['mood']);

$pettingService = new PettingService(
    $pdo,
    $pettingRepository,
    $creatureRepository,
    $userRepository,
    $moodCalculator,
    $config['gameplay']['petting'] + [
        'currency_per_pet' => $config['gameplay']['currency']['per_pet'],
        'currency_daily_cap' => $config['gameplay']['currency']['daily_cap'],
        'currency_cap_window_seconds' => $config['gameplay']['currency']['daily_cap_window_seconds'],
    ],
    $config['gameplay']['currency']['name']
);
$creatureProfileBuilder = new CreatureProfileBuilder(
    $speciesRepository, $userRepository, $growthCalculator, $pettingRepository, $moodCalculator
);
$purchaseService = new PurchaseService(
    $pdo, $shopRepository, $userRepository, $inventoryRepository,
    $config['gameplay']['currency']['name']
);
$creatureBioService = new CreatureBioService(
    $creatureRepository,
    $textGuard,
    $config['gameplay']['bio_max_length']
);
// The guestbook. The catalogue of choosable messages is shared by the service
// (which validates a choice) and the panel (which displays them), so it is built
// once here and handed to both.
$guestbookMessages = new GuestbookMessages($config['gameplay']['guestbook']['messages']);
$guestbookService = new GuestbookService(
    $guestbookRepository,
    $guestbookMessages,
    $config['gameplay']['guestbook']['edit_cooldown_seconds']
);
$guestbookPanel = new GuestbookPanel(
    $guestbookRepository,
    $guestbookMessages,
    $config['gameplay']['guestbook']['entries_shown']
);
$explorationService = new ExplorationService(
    $explorationRepository,
    new WeightedPicker(),
    $creatureRepository,
    $speciesRepository,
    new ItemRewardGranter($pdo, $inventoryRepository),
    [
        'clicks_per_visit' => $config['gameplay']['exploration']['clicks_per_visit'],
        'window_seconds' => $config['gameplay']['exploration']['window_seconds'],
        'creature_names' => $config['gameplay']['creature_names'],
    ]
);

// ---- Who is logged in? ----
// If the session holds a user id, load that user so the layout can greet them and
// show the log-out control. A guest simply has null here.
$currentUser = null;
$currentUserId = $session->get('user_id');
if (is_int($currentUserId)) {
    $currentUser = $userRepository->findById($currentUserId);
}

// A creature moment: every few clicks, one of the player's creatures pops up in
// a speech bubble at the top of the page. Most requests this stays null — the
// rarity is deliberate (see CreatureMoments for the whole reasoning).
$creatureMoment = null;

// The sidebar's identity pieces (redesign 2026-08-14): the player's avatar and
// their favourite creature as a keepsake card. Guests simply have nulls here —
// the sidebar shows them the door (log in / sign up) instead.
$favouriteSummary = null;
$currentAvatarPath = null;
$currentAvatarName = null;
// The treats in the player's satchel, so the keepsake card can offer them.
$keepsakeTreats = [];

if ($currentUser !== null) {
    // One database trip for the player's creatures serves both displays below:
    // the rare creature-moment roll AND the sidebar's favourite card.
    $creatureSummaries = $creatureProfileBuilder->summariesFor(
        $creatureRepository->findByOwner($currentUser->id)
    );

    $creatureMoment = (new CreatureMoments(
        $config['gameplay']['creature_moments']['lines'],
        $config['gameplay']['creature_moments']['chance_percent']
    ))->maybeFor($creatureSummaries);

    // The keepsake is the FIRST favourite by the player's own ordering
    // (featured_order), the same creature the profile page spotlights. A player
    // who never chose one simply has no keepsake card — nothing is invented.
    foreach ($creatureSummaries as $summary) {
        $order = $summary['creature']->featuredOrder;
        if ($order !== null
            && ($favouriteSummary === null || $order < $favouriteSummary['creature']->featuredOrder)) {
            $favouriteSummary = $summary;
        }
    }

    // Only look up treats when there is a keepsake card to put them on. A player
    // who has not chosen a favourite has nowhere to use them from here, so the
    // query would be work nobody asked for on every single page.
    if ($favouriteSummary !== null) {
        $keepsakeTreats = $inventoryRepository->findTreatsForUser($currentUser->id);
    }

    // The avatar: stored as a key, turned into a picture only by the AvatarSet
    // allow-list (see that class for why this is a safety boundary, not a lookup).
    $profileForSidebar = $profileRepository->findById($currentUser->id);
    $avatarKey = $profileForSidebar?->avatarKey ?? AvatarSet::FALLBACK_KEY;
    $currentAvatarPath = $avatarSet->imagePathFor($avatarKey);
    $currentAvatarName = $avatarSet->nameFor($avatarKey);
}

// Share these with every template (used by the shared layout's shell).
$templates->addData([
    'currentUser' => $currentUser,
    'creatureMoment' => $creatureMoment,
    'favouriteSummary' => $favouriteSummary,
    'currentAvatarPath' => $currentAvatarPath,
    'currentAvatarName' => $currentAvatarName,
    'keepsakeTreats' => $keepsakeTreats,
    'currentPath' => $request->path(),
    // The label for the currency (e.g. "coins"), shown next to the balance.
    'currencyName' => $config['gameplay']['currency']['name'],
    // Whether to offer the "sign up" link, and whether to show the demo banner.
    // Both are closed-demo settings — see config/config.php under "app".
    'registrationOpen' => $config['app']['registration_open'],
    'showDemoNotice' => $config['app']['show_demo_notice'],
]);

// ---- Controllers ----
$homeController = new HomeController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $config['app']['name']
);
$browseController = new BrowseController(
    $templates, $creatureRepository, $creatureProfileBuilder, $config['gameplay']['browse_recent_limit']
);
$registerController = new RegisterController(
    $templates, $csrf, $session, $registrationService, $starterCreatureService, $rateLimiter,
    $config['security'], $config['app']['registration_open']
);
$loginController = new LoginController(
    $templates, $csrf, $session, $authenticator, $userRepository, $rateLimiter, $config['security']
);
$logoutController = new LogoutController($session, $csrf);
$creatureController = new CreatureController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $guestbookPanel,
    $inventoryRepository,
    $config['gameplay']['creature_name_max_length']
);
$guestbookController = new GuestbookController(
    $session, $csrf, $creatureRepository, $guestbookService, $guestbookRepository, $rateLimiter,
    $config['security']['rate_limit_guestbook']
);
$petController = new PetController(
    $session, $csrf, $creatureRepository, $pettingService, $rateLimiter, $config['security']['rate_limit_pet']
);
$bioController = new BioController(
    $session, $csrf, $creatureRepository, $creatureBioService, $rateLimiter, $config['security']['rate_limit_bio']
);
// Renaming reuses the bio rate limit: both are "the owner changing words on their
// own creature", they happen about as often, and one number is easier to retune
// than two that mean the same thing.
// Giving a creature a treat. The service owns every rule about who may feed what
// (read its docblock); the controller only carries the request in and out.
$feedController = new FeedController(
    $session, $csrf, $creatureRepository,
    new FeedingService($pdo, $creatureRepository, $inventoryRepository, $moodCalculator),
    $rateLimiter, $config['security']['rate_limit_feed']
);
$creatureRenameController = new CreatureRenameController(
    $session, $csrf, $creatureRepository, new ContentFilter($config['moderation']['blocked_words']), $rateLimiter,
    $config['security']['rate_limit_bio'],
    $config['gameplay']['creature_name_max_length']
);
$collectionController = new CollectionController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder
);
$explorationController = new ExplorationController(
    $templates, $session, $csrf, $explorationService, $rateLimiter,
    [
        'areas' => $config['gameplay']['exploration']['areas'],
        'rate_limit' => $config['security']['rate_limit_explore'],
    ]
);
// The finder narrows the economy pages (category pills + name search). It is
// shared by both controllers so the two pages can never filter by different rules.
$itemFinder = new ItemFinder();
$inventoryController = new InventoryController(
    $templates, $session, $inventoryRepository, $itemFinder,
    $config['gameplay']['finder']['search_shown_from']
);
$shopController = new ShopController(
    $templates, $session, $csrf, $shopRepository, $purchaseService, $creaturePurchaseService,
    $rateLimiter, $itemFinder,
    [
        'slug' => 'general-store',
        'rate_limit' => $config['security']['rate_limit_purchase'],
        'search_shown_from' => $config['gameplay']['finder']['search_shown_from'],
    ]
);
$itemController = new ItemController(
    $templates, $session, $csrf, $inventoryRepository,
    new ItemDisposalService($pdo, $userRepository, $inventoryRepository, $config['gameplay']['currency']['name']),
    $rateLimiter,
    $config['security']['rate_limit_item_disposal']
);

// A player's page. The avatar set and the profile repository are built near the
// top of this file (the sidebar needs them on every page); the profile limits
// are content/config, so changing how much someone may write needs no code change.
//
// ONE ProfileService, shared by the profile form and the favourite star. Both
// change which creatures a player features, and the cap on how many is a rule
// that must not exist in two places — see toggleFavourite() for why.
$profileService = new ProfileService(
    $profileRepository,
    $creatureRepository,
    $avatarSet,
    $textGuard,
    $config['profile']
);

$profileController = new ProfileController(
    $templates, $session, $csrf, $profileRepository, $profileService,
    $creatureRepository, $creatureProfileBuilder, $avatarSet, $rateLimiter,
    $config['profile'],
    $config['security']['rate_limit_profile']
);

// The star on a creature, which is the other way to choose a favourite.
$favouriteController = new FavouriteController(
    $session, $csrf, $creatureRepository, $profileService, $rateLimiter,
    $config['security']['rate_limit_profile']
);

// Reporting. This is the safety mechanism the filters exist to support: every
// filter beneath it only catches the obvious, and a person noticing catches the
// rest. See docs/free-text-safety.md.
$reportController = new ReportController(
    $templates, $session, $csrf,
    new ReportService(new ReportRepository($pdo), $creatureRepository, $profileRepository),
    $rateLimiter,
    $config['security']['rate_limit_report']
);

// Finding a player. Prefix matching only, a minimum length, a small result cap
// and a rate limit — four separate answers to the same threat, which is somebody
// scripting their way to a list of everybody here. There is deliberately no
// "newest members" listing anywhere on this site.
//
// It is ONE object shared by both pages that offer search. That is not a saving,
// it is the point: when the community page searched players through its own copy
// of this logic, it shipped without any of the four guards. One door, one set of
// rules, no second copy to forget.
$playerFinder = new PlayerFinder(
    $profileRepository, $avatarSet, $rateLimiter,
    $config['search'],
    $config['security']['rate_limit_search']
);

$searchController = new SearchController(
    $templates, $session, $playerFinder, $config['search']['minimum_length']
);

// The community page: the creatures of Felkyo and the people who keep them,
// behind two tabs on one page.
$communityController = new CommunityController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $playerFinder,
    $config['gameplay']['browse_recent_limit'],
    $config['search']['minimum_length']
);

// ---- Routes ----
$router = new Router();

// When no route matches, show the friendly themed 404 page.
$router->setNotFoundHandler(function () use ($templates): Response {
    return Response::html(
        $templates->render('pages/not-found', ['message' => 'We couldn\'t find that page.']),
        404
    );
});

// The home page (welcome for guests; the player's creatures when logged in).
$router->get('/', [$homeController, 'show']);

// The public browse page — recent public creatures.
$router->get('/browse', [$browseController, 'show']);

// The community hub: browse creatures and find players from one place.
$router->get('/community', [$communityController, 'show']);

// Accounts.
$router->get('/register', [$registerController, 'show']);
$router->post('/register', [$registerController, 'submit']);
$router->get('/login', [$loginController, 'show']);
$router->post('/login', [$loginController, 'submit']);
$router->post('/logout', [$logoutController, 'submit']);

// The player's own collection of creatures.
$router->get('/creatures', [$collectionController, 'show']);

// /adopt was daily adoption, retired in M2 when creatures moved into the shop.
// It stays as a redirect rather than a 404: people have it bookmarked, and
// "the thing you wanted is now over here" is a better answer than "gone".
$router->get('/adopt', static fn (): Response => Response::redirect('/shop'));

// Exploration: the list of areas, one area's scene, and searching it.
$router->get('/explore', [$explorationController, 'index']);
$router->get('/explore/{area}', [$explorationController, 'show']);
$router->post('/explore/{area}', [$explorationController, 'search']);

// The economy: the shop (view + buy) and the player's inventory.
$router->get('/shop', [$shopController, 'show']);
$router->post('/shop/buy', [$shopController, 'buy']);
// Buying a creature. A separate route from buying an item, so "a pile of treats"
// and "a living thing" are never told apart by a value the browser sent.
$router->post('/shop/creature', [$shopController, 'buyCreature']);
$router->get('/inventory', [$inventoryController, 'show']);
// One owned item, and the two ways to part with it. Selling and discarding are
// separate routes rather than one route with a flag, so the difference between
// "get paid" and "lose it for nothing" is never a value the browser sends.
$router->get('/inventory/{id}', [$itemController, 'show']);
$router->post('/inventory/{id}/sell', [$itemController, 'sell']);
$router->post('/inventory/{id}/discard', [$itemController, 'discard']);

// Finding a player by name.
$router->get('/players', [$searchController, 'show']);

// The report button. A page rather than a pop-up, so it works without JavaScript,
// works with a screen reader, and cannot be lost to a mis-tap.
$router->get('/report/{subject}/{id}', [$reportController, 'show']);
$router->post('/report', [$reportController, 'submit']);
// A player's page. The edit routes come BEFORE the {username} one is reached in
// practice because they are different paths entirely — /profile/... is always the
// logged-in player's own, and /player/{username} is anybody's. Keeping the two
// apart means no route ever has to work out "is this me?" from the URL.
$router->get('/player/{username}', [$profileController, 'show']);
$router->get('/profile/edit', [$profileController, 'edit']);
$router->post('/profile', [$profileController, 'save']);

// A single creature's page. {id} is captured from the URL, e.g. /creature/42.
$router->get('/creature/{id}', [$creatureController, 'show']);
// Petting a creature (a state-changing action, so POST + CSRF).
$router->post('/creature/{id}/pet', [$petController, 'pet']);
// Saving a creature's bio (owner only).
$router->post('/creature/{id}/bio', [$bioController, 'update']);
// Renaming a creature (owner only, rate-limited).
$router->post('/creature/{id}/rename', [$creatureRenameController, 'update']);
// Giving a creature a treat (owner only — you feed your own).
$router->post('/creature/{id}/feed', [$feedController, 'feed']);
// The star: making one of your creatures a favourite, or not (owner only).
$router->post('/creature/{id}/favourite', [$favouriteController, 'toggle']);
// Signing a creature's guestbook (any logged-in visitor, one entry each).
$router->post('/creature/{id}/guestbook', [$guestbookController, 'sign']);
// Removing a guestbook entry — only the creature's OWNER may do this.
$router->post('/creature/{id}/guestbook/{entryId}/delete', [$guestbookController, 'delete']);

// ---- Dispatch and send ----
// Any unexpected error is logged and turned into a plain 500 page, so we never
// leak internal details (like a stack trace) to visitors.
try {
    $response = $router->dispatch($request);
} catch (\Throwable $error) {
    $logger->error($error->getMessage() . ' @ ' . $error->getFile() . ':' . $error->getLine());
    $response = Response::html('Something went wrong. Please try again.', 500);
}

$response->send();
