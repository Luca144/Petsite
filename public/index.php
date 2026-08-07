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
use Felkyo\Creatures\AdoptionService;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Economy\InventoryRepository;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookPanel;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Controllers\AdoptionController;
use Felkyo\Http\Controllers\BioController;
use Felkyo\Http\Controllers\BrowseController;
use Felkyo\Http\Controllers\CollectionController;
use Felkyo\Http\Controllers\CreatureController;
use Felkyo\Http\Controllers\ExplorationController;
use Felkyo\Http\Controllers\GuestbookController;
use Felkyo\Http\Controllers\HomeController;
use Felkyo\Http\Controllers\InventoryController;
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

// ---- Services (own the business rules) ----
$passwordHasher = new PasswordHasher();
$userValidator = new UserValidator($config['security']);
$registrationService = new RegistrationService($userRepository, $userValidator, $passwordHasher);
$authenticator = new Authenticator($userRepository, $passwordHasher);
$rateLimiter = new RateLimiter($rateLimitRepository);
$starterCreatureService = new StarterCreatureService(
    $speciesRepository, $creatureRepository, $config['gameplay']['creature_names']
);
$adoptionService = new AdoptionService(
    $speciesRepository,
    $creatureRepository,
    $userRepository,
    [
        'cooldown_seconds' => $config['gameplay']['adoption']['cooldown_seconds'],
        'names' => $config['gameplay']['creature_names'],
    ]
);
$growthCalculator = new GrowthCalculator(
    $config['gameplay']['growth']['xp_per_level'],
    $config['gameplay']['growth']['stage_start_levels']
);
$pettingService = new PettingService(
    $pettingRepository,
    $creatureRepository,
    $userRepository,
    $config['gameplay']['petting'] + ['currency_per_pet' => $config['gameplay']['currency']['per_pet']]
);
$creatureProfileBuilder = new CreatureProfileBuilder(
    $speciesRepository, $userRepository, $growthCalculator, $pettingRepository
);
$purchaseService = new PurchaseService(
    $pdo, $shopRepository, $userRepository, $inventoryRepository
);
$creatureBioService = new CreatureBioService(
    $creatureRepository,
    new ContentFilter($config['moderation']['blocked_words']),
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

// Share these with every template (used by the shared layout's navigation).
$templates->addData([
    'currentUser' => $currentUser,
    'currentPath' => $request->path(),
    // The label for the currency (e.g. "coins"), shown next to the balance.
    'currencyName' => $config['gameplay']['currency']['name'],
    // Whether to offer the "sign up" link, and whether to show the demo banner.
    // Both are closed-demo settings — see config/config.php under "app".
    'registrationOpen' => $config['app']['registration_open'],
    'showDemoNotice' => $config['app']['show_demo_notice'],
]);

// ---- Controllers ----
$homeController = new HomeController($templates, $session, $creatureRepository, $config['app']['name']);
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
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $guestbookPanel
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
$collectionController = new CollectionController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder
);
$adoptionController = new AdoptionController(
    $templates, $session, $csrf, $adoptionService, $rateLimiter, $config['security']['rate_limit_adopt']
);
$explorationController = new ExplorationController(
    $templates, $session, $csrf, $explorationService, $rateLimiter,
    [
        'areas' => $config['gameplay']['exploration']['areas'],
        'rate_limit' => $config['security']['rate_limit_explore'],
    ]
);
$inventoryController = new InventoryController($templates, $session, $inventoryRepository);
$shopController = new ShopController(
    $templates, $session, $csrf, $shopRepository, $purchaseService, $rateLimiter,
    [
        'slug' => 'general-store',
        'rate_limit' => $config['security']['rate_limit_purchase'],
    ]
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

// Accounts.
$router->get('/register', [$registerController, 'show']);
$router->post('/register', [$registerController, 'submit']);
$router->get('/login', [$loginController, 'show']);
$router->post('/login', [$loginController, 'submit']);
$router->post('/logout', [$logoutController, 'submit']);

// The player's own collection of creatures.
$router->get('/creatures', [$collectionController, 'show']);

// Daily adoption.
$router->get('/adopt', [$adoptionController, 'show']);
$router->post('/adopt', [$adoptionController, 'adopt']);

// Exploration: the list of areas, one area's scene, and searching it.
$router->get('/explore', [$explorationController, 'index']);
$router->get('/explore/{area}', [$explorationController, 'show']);
$router->post('/explore/{area}', [$explorationController, 'search']);

// The economy: the shop (view + buy) and the player's inventory.
$router->get('/shop', [$shopController, 'show']);
$router->post('/shop/buy', [$shopController, 'buy']);
$router->get('/inventory', [$inventoryController, 'show']);

// A single creature's page. {id} is captured from the URL, e.g. /creature/42.
$router->get('/creature/{id}', [$creatureController, 'show']);
// Petting a creature (a state-changing action, so POST + CSRF).
$router->post('/creature/{id}/pet', [$petController, 'pet']);
// Saving a creature's bio (owner only).
$router->post('/creature/{id}/bio', [$bioController, 'update']);
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
