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
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Creatures\StarterCreatureService;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Http\Controllers\AdoptionController;
use Felkyo\Http\Controllers\CollectionController;
use Felkyo\Http\Controllers\CreatureController;
use Felkyo\Http\Controllers\ExplorationController;
use Felkyo\Http\Controllers\HomeController;
use Felkyo\Http\Controllers\LoginController;
use Felkyo\Http\Controllers\LogoutController;
use Felkyo\Http\Controllers\PetController;
use Felkyo\Http\Controllers\RegisterController;
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
$pdo = Database::connect($config['database']);

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
    $pettingRepository, $creatureRepository, $config['gameplay']['petting']
);
$creatureProfileBuilder = new CreatureProfileBuilder(
    $speciesRepository, $userRepository, $growthCalculator, $pettingRepository
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
]);

// ---- Controllers ----
$homeController = new HomeController($templates, $session, $creatureRepository, $config['app']['name']);
$registerController = new RegisterController(
    $templates, $csrf, $session, $registrationService, $starterCreatureService, $rateLimiter, $config['security']
);
$loginController = new LoginController(
    $templates, $csrf, $session, $authenticator, $userRepository, $rateLimiter, $config['security']
);
$logoutController = new LogoutController($session, $csrf);
$creatureController = new CreatureController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder
);
$petController = new PetController(
    $session, $csrf, $creatureRepository, $pettingService, $rateLimiter, $config['security']['rate_limit_pet']
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

// ---- Routes ----
$router = new Router();

// The home page (welcome for guests; the player's creatures when logged in).
$router->get('/', [$homeController, 'show']);

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

// A single creature's page. {id} is captured from the URL, e.g. /creature/42.
$router->get('/creature/{id}', [$creatureController, 'show']);
// Petting a creature (a state-changing action, so POST + CSRF).
$router->post('/creature/{id}/pet', [$petController, 'pet']);

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
