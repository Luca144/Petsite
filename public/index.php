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

use Felkyo\Admin\AdminGate;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\Role;
use Felkyo\Admin\RoleAssignmentService;
use Felkyo\Admin\RoleRepository;
use Felkyo\Admin\SecondFactorRepository;
use Felkyo\Admin\Totp;
use Felkyo\Admin\SettingsCatalog;
use Felkyo\Admin\SiteStatsService;
use Felkyo\Admin\SiteSettingsRepository;
use Felkyo\Admin\UnfinishedListService;
use Felkyo\Moderation\ModerationRepository;
use Felkyo\Moderation\ModerationService;
use Felkyo\Moderation\MutedVoice;
use Felkyo\Moderation\NoticeRepository;
use Felkyo\Art\ArtRepository;
use Felkyo\Art\ArtService;
use Felkyo\Art\ImageValidator;
use Felkyo\Art\VolumeImageStore;
use Felkyo\Auth\Authenticator;
use Felkyo\Auth\PasswordHasher;
use Felkyo\Auth\RegistrationService;
use Felkyo\Auth\Session;
use Felkyo\Core\Database;
use Felkyo\Core\FileLogger;
use Felkyo\Core\PendingMigrations;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreaturePurchaseService;
use Felkyo\Creatures\CreatureBioService;
use Felkyo\Creatures\CreatureInteractions;
use Felkyo\Creatures\CreatureMoments;
use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Creatures\FeedingService;
use Felkyo\Creatures\GrowthCalculator;
use Felkyo\Creatures\MoodCalculator;
use Felkyo\Creatures\PettingRepository;
use Felkyo\Creatures\PettingService;
use Felkyo\Creatures\PlayableGames;
use Felkyo\Creatures\PlayService;
use Felkyo\Admin\ContentVersionRepository;
use Felkyo\Creatures\SpeciesEditorService;
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
use Felkyo\Economy\ItemEditorService;
use Felkyo\Economy\ItemRepository;
use Felkyo\Economy\PurchaseService;
use Felkyo\Economy\ShopEditorService;
use Felkyo\Economy\ShopRepository;
use Felkyo\Http\Controllers\AdminItemEditController;
use Felkyo\Http\Controllers\AdminItemsController;
use Felkyo\Http\Controllers\AdminShopEditController;
use Felkyo\Http\Controllers\AdminShopsController;
use Felkyo\Exploration\AreaEditorService;
use Felkyo\Exploration\AreaRepository;
use Felkyo\Exploration\ExplorationRepository;
use Felkyo\Exploration\LootLineParser;
use Felkyo\Http\Controllers\AdminAreaEditController;
use Felkyo\Http\Controllers\AdminAreasController;
use Felkyo\Http\Controllers\AdminSettingsController;
use Felkyo\Http\Controllers\AdminStatsController;
use Felkyo\Http\Controllers\AdminUnfinishedController;
use Felkyo\Http\Controllers\AdminReportsController;
use Felkyo\Http\Controllers\AdminAccountController;
use Felkyo\Http\Controllers\NoticeController;
use Felkyo\Exploration\ExplorationService;
use Felkyo\Exploration\ItemRewardGranter;
use Felkyo\Exploration\WeightedPicker;
use Felkyo\Guestbook\GuestbookMessages;
use Felkyo\Guestbook\GuestbookPanel;
use Felkyo\Guestbook\GuestbookRepository;
use Felkyo\Guestbook\GuestbookService;
use Felkyo\Http\Controllers\AdminAuditController;
use Felkyo\Http\Controllers\AdminDoorController;
use Felkyo\Http\Controllers\AdminEnrolController;
use Felkyo\Http\Controllers\AdminHomeController;
use Felkyo\Http\Controllers\AdminCreatureEditController;
use Felkyo\Http\Controllers\AdminCreaturePickerController;
use Felkyo\Http\Controllers\AdminCreaturePreviewController;
use Felkyo\Http\Controllers\AdminCreaturesController;
use Felkyo\Http\Controllers\AdminPicturesController;
use Felkyo\Http\Controllers\AdminRolesController;
use Felkyo\Http\Controllers\ArtController;
use Felkyo\Http\Controllers\BioController;
use Felkyo\Http\Controllers\BrowseController;
use Felkyo\Http\Controllers\LibraryController;
use Felkyo\Http\Controllers\AdminFormsController;
use Felkyo\Http\Controllers\AdminGazetteController;
use Felkyo\Library\FormEditorService;
use Felkyo\Library\ArticleEditorService;
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
use Felkyo\Http\Controllers\PlayController;
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
use Felkyo\Library\ArticleRepository;
use Felkyo\Character\CharacterSheet;
use Felkyo\Character\LadderRepository;
use Felkyo\Character\TraitAwarder;
use Felkyo\Character\TraitRepository;
use Felkyo\Creatures\FreezeService;
use Felkyo\Pound\PoundRepository;
use Felkyo\Pound\PoundService;
use Felkyo\Http\Controllers\PoundController;
use Felkyo\Http\Controllers\WelcomeController;
use Felkyo\Http\Controllers\BugReportController;
use Felkyo\Http\Controllers\AdminLookController;
use Felkyo\Http\Controllers\AdminTextsController;
use Felkyo\Http\Controllers\AdminBadgesController;
use Felkyo\Http\Controllers\AdminGiftsController;
use Felkyo\Admin\GiftService;
use Felkyo\Http\Controllers\AdminPostcardsController;
use Felkyo\Social\PostcardEditorService;
use Felkyo\Http\Controllers\AdminRemovalController;
use Felkyo\Admin\ContentRemovalService;
use Felkyo\Http\Controllers\CompanyController;
use Felkyo\Http\Controllers\PostOfficeController;
use Felkyo\Social\BottleRepository;
use Felkyo\Social\BottleService;
use Felkyo\Social\CompanyPanel;
use Felkyo\Social\CompanyRepository;
use Felkyo\Social\PostcardRepository;
use Felkyo\Social\PostOfficeService;
use Felkyo\Http\Controllers\ThemeController;
use Felkyo\Http\Controllers\AdminThemesController;
use Felkyo\Themes\ThemeRepository;
use Felkyo\Themes\ThemeEditorService;
use Felkyo\Http\Controllers\AdminCharacterController;
use Felkyo\Character\CharacterEditorService;
use Felkyo\Library\CreatureFormRoller;
use Felkyo\Library\SpeciesFormRepository;
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

// IS THE DATABASE BEHIND THE CODE? Code deploys automatically; database structure
// does not (docs/deployment-guide.md), so a release with a new migration needs
// that migration run by hand afterwards. The day that is forgotten, every page
// becomes a fatal error from inside a repository naming a column nobody was asking
// about — which is exactly what happened, with three migrations outstanding.
//
// So it is checked here instead, once, before anything tries to read a row. The
// site is still down — nothing here can invent a missing column — but it is down
// with a sentence a visitor can read and a log line naming the command to run.
// See PendingMigrations for why it counts files rather than checking columns.
$pendingMigrations = PendingMigrations::count($pdo, dirname(__DIR__) . '/migrations');
if ($pendingMigrations > 0) {
    $logger->error(sprintf(
        'The database is behind the code: %d migration(s) have not been run. '
        . 'Run: php vendor/robmorgan/phinx/bin/phinx migrate -e %s',
        $pendingMigrations,
        $config['app']['environment'] === 'production' ? 'production' : 'development'
    ));

    // The same wording as the connection failure above, on purpose: from a
    // visitor's side these are the same event — the site cannot reach its data —
    // and inviting them to guess which is not a kindness.
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        . '<title>Felkyo Creatures — back shortly</title></head><body>'
        . '<h1>Felkyo is having a quiet moment</h1>'
        . '<p>We&rsquo;re part-way through some tidying up. Please try again in a little while.</p>'
        . '</body></html>';
    exit;
}

// ---- Panel-tuned settings lie over config from here on (M2.8) ----
//
// Config stays where every value is DEFINED and explained; the panel's
// changes are OVERRIDES from the site_settings table, merged here so
// everything below keeps reading plain config arrays. The catalog
// re-validates every stored value, so a bad row is ignored, never
// applied. (This runs after the migration guard above, so the table is
// guaranteed to exist by the time it is read.)
$settingsCatalog = new SettingsCatalog();
$siteSettingsRepository = new SiteSettingsRepository($pdo);
$siteOverrides = $siteSettingsRepository->overrides();
$config = $settingsCatalog->apply($config, $siteOverrides);
// The site-wide look (default theme, section order, announcement, fine-tuning
// CSS) lives in the same rows under "look." keys — read by the line above
// already, so it costs nothing extra. See Felkyo\Look\SiteLook.
$siteLook = \Felkyo\Look\SiteLook::fromSettings($siteOverrides);

// ---- Uploaded pictures are served HERE, before anything else is built ----
//
// A creature page will embed several /art/... pictures (M2.3), and each one
// is its own request. Letting those run the whole front controller would
// start a session, mint a CSRF token and run the sidebar's queries — per
// picture, for nothing: an image response uses none of it. Worse than waste,
// the session added headers that FOUGHT the image's own caching (PHP's
// session machinery sends "Expires: 1981 / no-cache" with everything, plus a
// Set-Cookie — meaning cookie-less fetchers minted a session file per image).
//
// So pictures exit early: database connected (needed for the lookup),
// migrations checked, and nothing else. ArtController owns everything about
// serving safely — see its file-level docblock.
if (str_starts_with($request->path(), '/art/')) {
    $artRouter = new Router();
    $artRouter->get('/art/{name}', [
        new ArtController(new ArtRepository($pdo), new VolumeImageStore($config['art']['storage_path'])),
        'show',
    ]);
    $artRouter->dispatch($request)->send();
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

// ---- The site's words ----
//
// Every sentence in a template is written $this->t('key', 'The original
// words'), so the artist can change it in the panel (or by clicking it in
// editing mode) — see Felkyo\Texts\TextCatalogue. Three functions:
//   t()       a sentence, as safe HTML (in editing mode, wrapped so it can be clicked)
//   ta()      the same for INSIDE an attribute (alt, placeholder): never wrapped
//   message() a message the code sent (the flash, form errors)
// $textEditMode is decided further down, once we know who is looking; the
// functions read it by reference.
$textCatalogue = new \Felkyo\Texts\TextCatalogue(new \Felkyo\Texts\SiteTextRepository($pdo));
$textScanner = new \Felkyo\Texts\TextScanner(dirname(__DIR__) . '/templates', dirname(__DIR__) . '/src');
$textEditMode = false;
\Felkyo\Texts\TextTemplateFunctions::register($templates, $textCatalogue, $textScanner, static function () use (&$textEditMode): bool {
    return $textEditMode;
});

// ---- Repositories (own the database queries) ----
$userRepository = new UserRepository($pdo);
$rateLimitRepository = new RateLimitRepository($pdo);
$speciesRepository = new SpeciesRepository($pdo);
// M5.3: alternate forms. The roller gives a new creature its (rare) chance of
// being born in one; the repository runs every new creature through it.
$speciesFormRepository = new SpeciesFormRepository($pdo);
$creatureFormRoller = new CreatureFormRoller($speciesFormRepository, $config['gameplay']['forms']['birth_chance_percent']);
$creatureRepository = new CreatureRepository($pdo, $creatureFormRoller);
$articleRepository = new ArticleRepository($pdo);
$pettingRepository = new PettingRepository($pdo);
$explorationRepository = new ExplorationRepository($pdo);
$shopRepository = new ShopRepository($pdo);
$inventoryRepository = new InventoryRepository($pdo);
$guestbookRepository = new GuestbookRepository($pdo);
$profileRepository = new ProfileRepository($pdo);
// The creator's panel (M2.1): who holds which staff role, and the log of
// everything staff do. Both are read on admin requests only — except one
// cheap indexed role lookup per page for logged-in users, which decides
// whether the sidebar shows the panel link at all.
$roleRepository = new RoleRepository($pdo);
$auditLogRepository = new AuditLogRepository($pdo);

// The avatar allow-list. The set is content/config, so adding an avatar needs no
// code change (docs/adding-avatars.md). Built here (not further down with the
// profile controller) because the sidebar on EVERY page shows the logged-in
// player's avatar, so the layout needs it before any controller runs.
// The avatar choices: the set in config, plus every picture uploaded to the
// art store as an "avatar" (keyed art-<id>, so the stored key is still an
// allow-listed key, never a path a player could send). One small query.
$uploadedAvatars = [];
foreach ((new ArtRepository($pdo))->ofKind(\Felkyo\Art\ArtKind::Avatar) as $avatarArt) {
    $uploadedAvatars['art-' . $avatarArt->id] = [
        'name' => ucfirst(trim((string) preg_replace('/[-_]+/', ' ', pathinfo($avatarArt->originalName, PATHINFO_FILENAME)))),
        'path' => $avatarArt->url(),
    ];
}
$avatarSet = new AvatarSet($config['avatars'] + $uploadedAvatars);

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

// The panel's own machinery (M2.1). The gate is the one door every /admin
// route is registered through (see the route section); the second-factor
// pieces are what make an admin login need the phone as well as the password.
$secondFactorRepository = new SecondFactorRepository($pdo, $passwordHasher);
$totp = new Totp();
$adminGate = new AdminGate(
    $session, $userRepository, $roleRepository, $templates,
    $config['security']['admin']
);
$roleAssignmentService = new RoleAssignmentService(
    $pdo, $roleRepository, $userRepository, $auditLogRepository
);
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

// The writes that petting, feeding and playing make to a creature: the row
// lock, the mood, the XP, the play cooldown. One object shared by all three,
// because every one of those methods has to run inside a transaction that
// holds the lock — see CreatureInteractions for the whole reasoning.
$creatureInteractions = new CreatureInteractions($pdo, $growthCalculator);

$pettingService = new PettingService(
    $pdo,
    $pettingRepository,
    $creatureInteractions,
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
    $speciesRepository, $userRepository, $growthCalculator, $pettingRepository, $moodCalculator,
    $speciesFormRepository
);
// M6 — postcards, bottles, and company.
$companyRepository = new CompanyRepository($pdo);
$postcardRepository = new PostcardRepository($pdo);
$bottleRepository = new BottleRepository($pdo);
$companyPanel = new CompanyPanel($companyRepository, $postcardRepository, new \Felkyo\Character\BadgeRepository($pdo));

// M7 — the first-run welcome.
$welcomeRepository = new \Felkyo\Users\WelcomeRepository($pdo);

// M4 — themes.
$themeRepository = new ThemeRepository($pdo);

// M3 — the pound, and who a creature is.
$traitRepository = new TraitRepository($pdo);
$ladderRepository = new LadderRepository($pdo);
$traitAwarder = new TraitAwarder($traitRepository);
$badgeRepository = new \Felkyo\Character\BadgeRepository($pdo);
$characterSheet = new CharacterSheet($traitRepository, $traitAwarder, $ladderRepository, $badgeRepository);
$poundRepository = new PoundRepository($pdo, $config['gameplay']['pound']['grace_days']);
$poundService = new PoundService($pdo, $poundRepository, $creatureRepository, $traitAwarder);
$freezeService = new FreezeService($pdo, $creatureRepository, $growthCalculator);
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
$moderationRepository = new ModerationRepository($pdo);
$noticeRepository = new NoticeRepository($pdo);
$currentUser = null;
$currentUserId = $session->get('user_id');
if (is_int($currentUserId)) {
    $currentUser = $userRepository->findById($currentUserId);

    // A SUSPENDED account's live session ends here, at its very next
    // request — before any page or action below can run as them. Checked
    // fresh every time (M2.7): lifting a suspension works immediately, and
    // an expired one needs nobody to flip a switch. The login page is
    // where they land, and it explains the dates when they try to log in.
    if ($currentUser !== null && $moderationRepository->isSuspended($currentUser->id)) {
        $session->logout();
        $currentUser = null;
    }
}

// The unread official notices for the top of this player's page (M2.7).
// One indexed query per logged-in request; empty for almost everyone.
$unreadNotices = $currentUser !== null ? $noticeRepository->unreadFor($currentUser->id) : [];

// Does the sidebar show the way to the creator's panel? Only for staff. This
// is CONVENIENCE, not security — the AdminGate re-checks roles on every
// admin request; hiding the link just keeps players' screens honest about
// what is theirs. One indexed lookup, only for logged-in users.
$currentRoles = $currentUser !== null ? $roleRepository->rolesFor($currentUser->id) : [];
$showPanelLink = $currentRoles !== [];

// May this person change the site's words, and have they switched editing
// mode on? Artist or owner only. Showing the dotted lines is convenience —
// every SAVE goes through the panel's AdminGate (and its door) regardless.
$canEditTexts = in_array(Role::Artist, $currentRoles, true) || in_array(Role::Owner, $currentRoles, true);
$textEditMode = $canEditTexts && $session->get(\Felkyo\Http\Controllers\AdminTextsController::MODE_KEY) === true;

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

// THE ONE-TIME MESSAGE, TAKEN ONCE, HERE.
//
// It used to be each page's own job: five controllers called takeFlash() and eight
// templates drew the paragraph. Which meant a page that forgot simply swallowed
// the message — and the home page had forgotten. So feeding a creature from the
// keepsake card, which lands you on the home page, said nothing at all. Worse than
// nothing: the message stayed in the session and appeared later on whatever
// unrelated page happened to ask for one.
//
// Taken here and handed to the layout, every page shows it and no new page can
// forget. Controllers still only ever flash-and-redirect, which is what makes one
// read per request correct.
$flash = $session->takeFlash();

// The "something nice just happened" flag, read the same way and for the same
// reason. It drives the heart animation, and it used to be read by the creature
// page alone — so petting or feeding from the keepsake card, which lands you
// somewhere else entirely, animated nothing. Worse, nothing consumed it there, so
// the flag sat in the session and made the NEXT creature page celebrate for no
// reason. One read, one truth, every template.
$justPetted = $session->takeCelebration() === 'pet';

// Whether the sidebar panel was left folded away. A pure UI preference from a
// cookie the browser owns: it is only ever COMPARED against one string, never
// trusted as data — and reading it on the server is what lets the panel arrive
// already-closed instead of springing open and snapping shut on every page
// (see sidebar-fold.js, and the Product Owner's request for exactly this).
$sidebarOpen = ($_COOKIE['felkyo_sidebar'] ?? 'open') !== 'closed';

// THE THEME THIS PAGE WEARS: the player's own choice if they made one,
// otherwise the default the artist picked in the panel (also for guests),
// otherwise null — the site's own look in theme.css.
$activeTheme = $currentUser !== null ? $themeRepository->forUser($currentUser->id) : null;
if ($activeTheme === null && $siteLook->defaultThemeId !== null) {
    $activeTheme = $themeRepository->findPublished($siteLook->defaultThemeId);
}

// The fine-tuning CSS: what everyone sees — unless THIS person is trying a
// draft (the panel's Preview), which only their own session holds. A draft is
// re-checked by the same rules as published CSS before it is printed.
$previewCss = $currentUser !== null ? $session->get(\Felkyo\Http\Controllers\AdminLookController::PREVIEW_KEY) : null;
$isPreviewingCss = is_string($previewCss) && \Felkyo\Look\CustomCssRules::problemWith($previewCss) === null;

// Share these with every template (used by the shared layout's shell).
$templates->addData([
    // The official notices (M2.7): cards above the flash until each is
    // acknowledged, with the appeal address every card names.
    'unreadNotices' => $unreadNotices,
    'appealEmail' => $config['security']['moderation']['appeal_email'],
    'flash' => $flash,
    'justPetted' => $justPetted,
    'sidebarOpen' => $sidebarOpen,
    'currentUser' => $currentUser,
    // M4: the theme this page wears (null = the site's own look).
    'activeTheme' => $activeTheme,
    // The panel's site look: printed raw in head.php ONLY because
    // CustomCssRules has checked it (on save and again just now).
    'customCss' => $isPreviewingCss ? $previewCss : $siteLook->customCss,
    'isPreviewingCss' => $isPreviewingCss,
    'announcement' => $siteLook->announcement,
    // The order of the sections on creature and player pages.
    'sectionOrders' => $siteLook->orders,
    'creatureMoment' => $creatureMoment,
    'favouriteSummary' => $favouriteSummary,
    'currentAvatarPath' => $currentAvatarPath,
    'currentAvatarName' => $currentAvatarName,
    'keepsakeTreats' => $keepsakeTreats,
    'currentPath' => $request->path(),
    // Whether to show the panel link (staff only — see above).
    'showPanelLink' => $showPanelLink,
    // The site's words: may this person edit them, and is editing mode on?
    'canEditTexts' => $canEditTexts,
    'textEditMode' => $textEditMode,
    // The label for the currency (e.g. "coins"), shown next to the balance.
    'currencyName' => $config['gameplay']['currency']['name'],
    // Whether to offer the "sign up" link, and whether to show the demo banner.
    // Both are closed-demo settings — see config/config.php under "app".
    'registrationOpen' => $config['app']['registration_open'],
    'showDemoNotice' => $config['app']['show_demo_notice'],
]);

// ---- Controllers ----
$homeController = new HomeController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $config['app']['name'],
    $articleRepository, $welcomeRepository, $shopRepository
);
$welcomeController = new WelcomeController(
    $templates, $session, $csrf, $userRepository, $welcomeRepository, $auditLogRepository
);
$libraryController = new LibraryController(
    $templates, $speciesRepository, $speciesFormRepository, $articleRepository,
    array_keys($config['gameplay']['growth']['stage_start_levels']),
    // Its own ItemRepository: the shared one is built further down the file.
    new ItemRepository($pdo), new \Felkyo\Library\ItemSourcesRepository($pdo)
);
$poundController = new PoundController(
    $templates, $session, $csrf, $poundRepository, $poundService, $freezeService,
    $creatureProfileBuilder, $rateLimiter, $config['security']['rate_limit_pound']
);
$companyController = new CompanyController(
    $templates, $session, $csrf, $userRepository, $companyRepository,
    $rateLimiter, $config['security']['rate_limit_company']
);
$postOfficeController = new PostOfficeController(
    $templates, $session, $csrf, $postcardRepository,
    new PostOfficeService(
        $pdo, $postcardRepository, $companyRepository, $userRepository,
        $config['gameplay']['postcards']['daily_cap'], $config['gameplay']['currency']['name']
    ),
    $companyRepository,
    new BottleService($pdo, $bottleRepository, $config['gameplay']['bottles']['messages']),
    $bottleRepository, $rateLimiter, $config['security']['rate_limit_company']
);
$themeController = new ThemeController(
    $templates, $session, $csrf, $themeRepository, $rateLimiter, $config['security']['rate_limit_pound']
);
$browseController = new BrowseController(
    $templates, $creatureRepository, $creatureProfileBuilder, $config['gameplay']['browse_recent_limit']
);
$registerController = new RegisterController(
    $templates, $csrf, $session, $registrationService, $starterCreatureService, $rateLimiter,
    $config['security'], $config['app']['registration_open']
);
$loginController = new LoginController(
    $templates, $csrf, $session, $authenticator, $userRepository, $moderationRepository,
    $rateLimiter, $config['security']
);
$logoutController = new LogoutController($session, $csrf);
$creatureController = new CreatureController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder, $guestbookPanel,
    $inventoryRepository,
    $config['gameplay']['creature_name_max_length'],
    $characterSheet,
    $config['app']['url']
);
$guestbookController = new GuestbookController(
    $session, $csrf, $creatureRepository, $guestbookService, $guestbookRepository,
    new MutedVoice($moderationRepository), $rateLimiter,
    $config['security']['rate_limit_guestbook']
);
$petController = new PetController(
    $session, $csrf, $creatureRepository, $pettingService, $rateLimiter, $config['security']['rate_limit_pet']
);
$bioController = new BioController(
    $session, $csrf, $creatureRepository, $creatureBioService,
    new MutedVoice($moderationRepository), $rateLimiter, $config['security']['rate_limit_bio']
);
// Renaming reuses the bio rate limit: both are "the owner changing words on their
// own creature", they happen about as often, and one number is easier to retune
// than two that mean the same thing.
// Playing a game with a creature. THREE GUESSING GAMES, cycled at random, with
// the answer held on the server — because a game running in the browser cannot be
// trusted to report its own result, so a reward for winning would really be a
// reward for asking. Read PlayableGames for the whole reasoning.
$playableGames = new PlayableGames($config['gameplay']['play']['games']);
$playController = new PlayController(
    $templates, $session, $csrf, $creatureRepository,
    new PlayService($pdo, $creatureInteractions, $playableGames, $moodCalculator, $config['gameplay']['play']),
    $playableGames,
    $rateLimiter, $config['security']['rate_limit_play']
);

// Giving a creature a treat. The service owns every rule about who may feed what
// (read its docblock); the controller only carries the request in and out.
$feedController = new FeedController(
    $session, $csrf, $creatureRepository,
    new FeedingService($pdo, $creatureInteractions, $inventoryRepository, $moodCalculator, $speciesRepository),
    $rateLimiter, $config['security']['rate_limit_feed']
);
$creatureRenameController = new CreatureRenameController(
    $session, $csrf, $creatureRepository, new ContentFilter($config['moderation']['blocked_words']),
    new MutedVoice($moderationRepository), $rateLimiter,
    $config['security']['rate_limit_bio'],
    $config['gameplay']['creature_name_max_length']
);
$collectionController = new CollectionController(
    $templates, $session, $creatureRepository, $creatureProfileBuilder
);
// Areas are database content since M2.5 (they lived in config before) —
// the controller reads them through the repository, published only.
$areaRepository = new AreaRepository($pdo);
$explorationController = new ExplorationController(
    $templates, $session, $csrf, $explorationService, $rateLimiter, $areaRepository,
    [
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
    $creatureRepository, $creatureProfileBuilder, $avatarSet,
    new MutedVoice($moderationRepository), $rateLimiter,
    $config['profile'],
    $config['security']['rate_limit_profile'],
    $companyPanel
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

// The creator's panel controllers (M2.1). All of them sit BEHIND the
// AdminGate — see the admin route block below, where the gate is the only
// way an /admin route can be registered.
$adminDoorController = new AdminDoorController(
    $templates, $session, $csrf, $userRepository, $passwordHasher, $totp,
    $secondFactorRepository, $auditLogRepository, $adminGate, $rateLimiter,
    $config['security']['rate_limit_admin_door']
);
$adminEnrolController = new AdminEnrolController(
    $templates, $session, $csrf, $userRepository, $totp,
    $secondFactorRepository, $auditLogRepository, $adminGate, $pdo
);
$adminHomeController = new AdminHomeController($templates, $session, $roleRepository);
$adminRolesController = new AdminRolesController(
    $templates, $session, $csrf, $userRepository, $roleRepository,
    $roleAssignmentService, $rateLimiter,
    $config['security']['rate_limit_admin_role_change']
);
$adminAuditController = new AdminAuditController($templates, $auditLogRepository);

// The art store (M2.2). The bytes live on the Railway volume (a folder on a
// development machine) behind ImageStore; the table remembers what is there.
// See docs/plan/2026-08-21-m2-2-where-files-live.md for why a volume.
// SERVING a picture is not wired here — /art/ requests exited early, near
// the top of this file, before the session existed. This block is only the
// panel's side: uploading, listing, deleting.
$imageStore = new VolumeImageStore($config['art']['storage_path']);
$artRepository = new ArtRepository($pdo);
$artService = new ArtService($pdo, $artRepository, $imageStore, $auditLogRepository, $roleRepository, $speciesRepository);
// Sprites can live on the art volume since M2.3; PixelArt has to know where
// that is to measure them (see PixelArt::configureArtFolder).
\Felkyo\Design\PixelArt::configureArtFolder($config['art']['storage_path']);
$adminPicturesController = new AdminPicturesController(
    $templates, $session, $csrf, $userRepository, $artRepository, $artService,
    new ImageValidator($config['art']), $rateLimiter,
    $config['security']['rate_limit_art_upload'], $config['art']
);

// The species editor (M2.3) — one page per creature. The stage list comes
// from the growth config's keys, so the screens and the validator follow a
// fourth stage the day config grows one.
$stageNames = array_keys($config['gameplay']['growth']['stage_start_levels']);
// One undo engine for every editor (M2.4) — species, items and shops all
// keep their snapshots in content_versions through this.
$contentVersionRepository = new ContentVersionRepository($pdo);
$speciesEditorService = new SpeciesEditorService(
    $pdo, $speciesRepository, $contentVersionRepository, $artRepository,
    $auditLogRepository, $config['art'], $stageNames
);
$adminCreaturesController = new AdminCreaturesController(
    $templates, $session, $csrf, $userRepository, $speciesRepository,
    $speciesEditorService, $rateLimiter, $config['security']['rate_limit_content_save'], $stageNames
);
// M5 — the library in the panel: alternate forms and the Gazette desk.
$formEditorService = new FormEditorService(
    $speciesFormRepository, $speciesRepository, $artRepository, $auditLogRepository, $stageNames
);
$adminFormsController = new AdminFormsController(
    $templates, $session, $csrf, $userRepository, $speciesRepository, $speciesFormRepository,
    $artRepository, $formEditorService, $rateLimiter, $config['security']['rate_limit_content_save'], $stageNames
);
$articleEditorService = new ArticleEditorService($articleRepository, $artRepository, $auditLogRepository);
$adminGazetteController = new AdminGazetteController(
    $templates, $session, $csrf, $userRepository, $articleRepository, $artRepository,
    $articleEditorService, $rateLimiter, $config['security']['rate_limit_content_save']
);
// Deleting a species or an item for good (draft only, typed name, nobody loses out).
$adminRemovalController = new AdminRemovalController(
    $templates, $session, $csrf, $userRepository, $speciesRepository, new ItemRepository($pdo),
    new ContentRemovalService($pdo, $speciesRepository, new ItemRepository($pdo), $auditLogRepository),
    $rateLimiter, $config['security']['rate_limit_content_save']
);

// The postcard editor (the designs on the post office rack).
$adminPostcardsController = new AdminPostcardsController(
    $templates, $session, $csrf, $userRepository, $postcardRepository, $artRepository,
    new PostcardEditorService($postcardRepository, $artRepository, $auditLogRepository),
    $rateLimiter, $config['security']['rate_limit_content_save']
);

// Bug reports: players telling us what's broken, and the panel's list.
$bugReportRepository = new \Felkyo\Feedback\BugReportRepository($pdo);
$bugReportController = new BugReportController(
    $templates, $session, $csrf,
    new \Felkyo\Feedback\BugReportService($bugReportRepository, $config['gameplay']['bug_reports']),
    $bugReportRepository, $rateLimiter, $config['security']['rate_limit_bug_report'], $auditLogRepository
);

// The owner's gift desk (gems, items, a creature — capped and audit-logged).
$adminGiftsController = new AdminGiftsController(
    $templates, $session, $csrf, $userRepository, $speciesRepository, new ItemRepository($pdo),
    new GiftService(
        $pdo, $userRepository, $speciesRepository, new ItemRepository($pdo), $creatureRepository,
        $inventoryRepository, $auditLogRepository, $config['gameplay']['gifts'], $badgeRepository
    ),
    $rateLimiter, $config['security']['rate_limit_gift'], $config['gameplay']['gifts'], $badgeRepository
);

// Keepsake badges in the panel.
$adminBadgesController = new AdminBadgesController(
    $templates, $session, $csrf, $userRepository, $badgeRepository, $artRepository,
    new \Felkyo\Character\BadgeEditorService($badgeRepository, $artRepository, $auditLogRepository),
    $rateLimiter, $config['security']['rate_limit_content_save']
);

// M4 in the panel: the theme editor.
$adminThemesController = new AdminThemesController(
    $templates, $session, $csrf, $userRepository, $themeRepository,
    new ThemeEditorService($themeRepository, $auditLogRepository, $artRepository),
    $rateLimiter, $config['security']['rate_limit_content_save'], $artRepository
);

// The site's words: the panel's list, saving one, editing mode.
$adminTextsController = new AdminTextsController(
    $templates, $session, $csrf, $userRepository,
    new \Felkyo\Texts\TextEditorService(new \Felkyo\Texts\SiteTextRepository($pdo), $textCatalogue, $textScanner, $auditLogRepository),
    $textCatalogue, $textScanner, $rateLimiter, $config['security']['rate_limit_content_save']
);

// The panel's site look: default theme, section order, announcement, and
// the fine-tuning CSS with its history.
$customCssRepository = new \Felkyo\Look\CustomCssRepository($pdo);
$adminLookController = new AdminLookController(
    $templates, $session, $csrf, $userRepository, $siteLook,
    new \Felkyo\Look\SiteLookService($siteSettingsRepository, $themeRepository, $customCssRepository, $auditLogRepository),
    $themeRepository, $customCssRepository, $rateLimiter, $config['security']['rate_limit_content_save']
);

// M3 in the panel: traits and the level-title / age ladders.
$adminCharacterController = new AdminCharacterController(
    $templates, $session, $csrf, $userRepository, $traitRepository, $ladderRepository,
    new CharacterEditorService($traitRepository, $ladderRepository, $auditLogRepository),
    $rateLimiter, $config['security']['rate_limit_content_save']
);
$adminCreatureEditController = new AdminCreatureEditController(
    $templates, $session, $csrf, $userRepository, $speciesRepository,
    $contentVersionRepository, $speciesEditorService, $inventoryRepository,
    $rateLimiter, $config['security']['rate_limit_content_save'], $stageNames
);
$adminCreaturePickerController = new AdminCreaturePickerController(
    $templates, $session, $csrf, $userRepository, $speciesRepository, $artRepository,
    $speciesEditorService, $rateLimiter, $config['security']['rate_limit_content_save'], $stageNames
);
$adminCreaturePreviewController = new AdminCreaturePreviewController(
    $templates, $session, $speciesRepository, $moodCalculator, $config['gameplay']['growth']
);

// The item and shop editors (M2.4). WHERE ITEMS ARE WOVEN IN: the starter
// gift and the exploration loot tables both name items by slug — collected
// here so an item's editor page can say "this slug is spoken for" instead
// of leaving the artist to wonder. Slugs are minted once and never
// editable, which is what keeps these references safe.
// The loot references come from the areas tables since M2.5; the starter
// gift is APPENDED, not assigned — one item can be several things at once
// (acorn-treat is a starter gift AND forest loot), and the editor page
// should say all of them.
$referencedItemSlugs = $areaRepository->lootItemReferences();
foreach (array_keys(StarterCreatureService::STARTER_TREATS) as $starterSlug) {
    $referencedItemSlugs[$starterSlug][] = 'part of every new player\'s starter gift';
}

$itemRepository = new ItemRepository($pdo);
$itemEditorService = new ItemEditorService(
    $pdo, $itemRepository, $contentVersionRepository, $artRepository, $auditLogRepository,
    $config['art'] + ['maximum_sell_fraction_of_price' => $config['gameplay']['economy']['maximum_sell_fraction_of_price']],
    $referencedItemSlugs
);
$shopEditorService = new ShopEditorService(
    $pdo, $shopRepository, $itemRepository, $contentVersionRepository, $artRepository,
    $auditLogRepository, $config['art']
);
$adminItemsController = new AdminItemsController(
    $templates, $session, $csrf, $userRepository, $itemRepository, $itemEditorService,
    $rateLimiter, $config['security']['rate_limit_content_save']
);
$adminItemEditController = new AdminItemEditController(
    $templates, $session, $csrf, $userRepository, $itemRepository, $contentVersionRepository,
    $itemEditorService, $artRepository, $rateLimiter, $config['security']['rate_limit_content_save']
);
$adminShopsController = new AdminShopsController(
    $templates, $session, $csrf, $userRepository, $shopRepository, $shopEditorService,
    $rateLimiter, $config['security']['rate_limit_content_save']
);
$adminShopEditController = new AdminShopEditController(
    $templates, $session, $csrf, $userRepository, $shopRepository, $itemRepository,
    $contentVersionRepository, $shopEditorService, $artRepository, $rateLimiter,
    $config['security']['rate_limit_content_save']
);

// The area editor (M2.5) — the panel side of the tables above.
$areaEditorService = new AreaEditorService(
    $pdo, $areaRepository, $itemRepository, $contentVersionRepository,
    $artRepository, $auditLogRepository, new LootLineParser(), $config['art']
);
$adminAreasController = new AdminAreasController(
    $templates, $session, $csrf, $userRepository, $areaRepository, $areaEditorService,
    $rateLimiter, $config['security']['rate_limit_content_save']
);
$adminAreaEditController = new AdminAreaEditController(
    $templates, $session, $csrf, $userRepository, $areaRepository,
    $contentVersionRepository, $areaEditorService, $artRepository,
    new LootLineParser(), $rateLimiter, $config['security']['rate_limit_content_save']
);

// The unfinished list (M2.6): the read-only "what still needs doing" screen.
// It reuses the four editor services so its idea of "missing" can never
// disagree with the publish buttons.
$adminUnfinishedController = new AdminUnfinishedController(
    $templates,
    new UnfinishedListService(
        $speciesRepository, $itemRepository, $shopRepository, $areaRepository,
        $speciesEditorService, $itemEditorService, $areaEditorService
    )
);

// Moderation (M2.7): the queue's service, the two moderator screens, and
// the player's notice-acknowledge endpoint. MutedVoice is handed to every
// controller that accepts free text (bio, about, names, guestbooks).
$mutedVoice = new MutedVoice($moderationRepository);
$moderationService = new ModerationService(
    $pdo, $moderationRepository, $noticeRepository, $auditLogRepository,
    $roleRepository, $creatureRepository, $profileRepository, $impersonationGuard,
    $config['security']['moderation']
);
$adminReportsController = new AdminReportsController(
    $templates, $session, $csrf, $userRepository, $moderationService, $moderationRepository,
    $rateLimiter, $config['security']['rate_limit_moderation'], $config['security']['moderation']
);
$adminAccountController = new AdminAccountController(
    $templates, $session, $csrf, $userRepository, $profileRepository, $creatureRepository,
    $moderationService, $moderationRepository, $noticeRepository, $auditLogRepository,
    $rateLimiter, $config['security']['rate_limit_moderation'], $config['security']['moderation']
);
$noticeController = new NoticeController(
    $session, $csrf, $noticeRepository, $rateLimiter, $config['security']['rate_limit_notice_ack']
);

// Settings & maintenance (M2.8): the Coder's screen. It gets the LIVE
// (already-overridden) config so it shows each setting's honest value.
$adminSettingsController = new AdminSettingsController(
    $templates, $session, $csrf, $userRepository, $settingsCatalog, $siteSettingsRepository,
    $rateLimitRepository, $auditLogRepository, $rateLimiter,
    $config['security']['rate_limit_settings'], $config
);

// The stats page (M2.9): read-only aggregates, the artist's weather report.
$adminStatsController = new AdminStatsController($templates, new SiteStatsService($pdo));

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

// M6 — friends, the post office, the shore. The four friend/block actions
// share one handler; the closure tells it which one this route is.
$router->get('/friends', [$companyController, 'show']);
foreach (['friend', 'unfriend', 'block', 'unblock'] as $companyAction) {
    $router->post('/player/{username}/' . $companyAction, static function ($request, array $parameters) use ($companyController, $companyAction) {
        return $companyController->act($request, $parameters, $companyAction);
    });
}
$router->get('/post-office', [$postOfficeController, 'postOffice']);
$router->post('/post-office', [$postOfficeController, 'sendCard']);
$router->get('/shore', [$postOfficeController, 'shore']);
$router->post('/shore/send', [$postOfficeController, 'sendBottle']);
$router->post('/shore/find', [$postOfficeController, 'findBottle']);

// "Something broken?" — logged-in players (checked in the controller).
$router->get('/bug-report', [$bugReportController, 'form']);
$router->post('/bug-report', [$bugReportController, 'send']);

// M7 — dismissing the welcome.
$router->post('/welcome/dismiss', [$welcomeController, 'dismiss']);

// M4 — choosing a theme.
$router->get('/themes', [$themeController, 'show']);
$router->post('/themes', [$themeController, 'choose']);

// M3 — the pound, and keeping a creature at a stage.
$router->get('/pound', [$poundController, 'show']);
$router->post('/pound/{id}/adopt', [$poundController, 'adopt']);
$router->post('/pound/{id}/reclaim', [$poundController, 'reclaim']);
$router->post('/creature/{id}/to-pound', [$poundController, 'toPound']);
$router->post('/creature/{id}/freeze', [$poundController, 'freeze']);
$router->post('/creature/{id}/unfreeze', [$poundController, 'unfreeze']);
// M5 — the library and the Gazette (public, read-only).
$router->get('/library', [$libraryController, 'index']);
$router->get('/library/{slug}', [$libraryController, 'species']);
$router->get('/library/item/{id}', [$libraryController, 'item']);
$router->get('/gazette/{slug}', [$libraryController, 'article']);
$router->get('/updates', [$libraryController, 'updates']);

// Accounts.
$router->get('/register', [$registerController, 'show']);
$router->post('/register', [$registerController, 'submit']);
$router->get('/login', [$loginController, 'show']);
$router->post('/login', [$loginController, 'submit']);
$router->post('/logout', [$logoutController, 'submit']);

// ---- The creator's panel (M2.1) ----
// EVERY admin route is registered through the AdminGate — protect() for
// normal screens (staff check + role check + door freshness, on every
// request), protectDoorway() for the door and enrolment themselves (staff
// check only; the door cannot stand behind itself). There is deliberately no
// bare $router->get('/admin/...') anywhere: a route that skipped the gate
// would be an unauthorised admin page, and the AdminGateTest matrix exists
// to catch one ever appearing.
//
// The panel home: any staff role may see it.
$router->get('/admin', $adminGate->protect(null, [$adminHomeController, 'show']));
// The door: password (and code) to enter; see AdminDoorController.
$router->get('/admin/door', $adminGate->protectDoorway([$adminDoorController, 'show']));
$router->post('/admin/door', $adminGate->protectDoorway([$adminDoorController, 'submit']));
// Connecting an authenticator app (first entry only; see AdminEnrolController).
$router->get('/admin/enrol', $adminGate->protectDoorway([$adminEnrolController, 'show']));
$router->post('/admin/enrol', $adminGate->protectDoorway([$adminEnrolController, 'submit']));
// Roles and the audit log: the owner's screens.
$router->get('/admin/roles', $adminGate->protect(Role::Owner, [$adminRolesController, 'show']));
$router->post('/admin/roles/grant', $adminGate->protect(Role::Owner, [$adminRolesController, 'grant']));
$router->post('/admin/roles/revoke', $adminGate->protect(Role::Owner, [$adminRolesController, 'revoke']));
$router->get('/admin/audit', $adminGate->protect(Role::Owner, [$adminAuditController, 'show']));
// The art store: the artist's screens (owner passes everything).
$router->get('/admin/pictures', $adminGate->protect(Role::Artist, [$adminPicturesController, 'show']));
$router->post('/admin/pictures/upload', $adminGate->protect(Role::Artist, [$adminPicturesController, 'upload']));
$router->post('/admin/pictures/{id}/delete', $adminGate->protect(Role::Artist, [$adminPicturesController, 'delete']));
// The species editor — one page per creature (M2.3).
$router->get('/admin/creatures', $adminGate->protect(Role::Artist, [$adminCreaturesController, 'show']));
$router->post('/admin/creatures/create', $adminGate->protect(Role::Artist, [$adminCreaturesController, 'create']));
$router->get('/admin/creatures/{id}', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'show']));
$router->post('/admin/creatures/{id}/words', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'saveWords']));
$router->post('/admin/creatures/{id}/stage/{stage}', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'saveStage']));
$router->post('/admin/creatures/{id}/settings', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'saveSettings']));
$router->post('/admin/creatures/{id}/publish', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'publish']));
$router->post('/admin/creatures/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'unpublish']));
$router->post('/admin/creatures/{id}/revert', $adminGate->protect(Role::Artist, [$adminCreatureEditController, 'revert']));
$router->get('/admin/creatures/{id}/picture/{slot}', $adminGate->protect(Role::Artist, [$adminCreaturePickerController, 'show']));
$router->post('/admin/creatures/{id}/picture/{slot}', $adminGate->protect(Role::Artist, [$adminCreaturePickerController, 'choose']));
$router->get('/admin/creatures/{id}/preview', $adminGate->protect(Role::Artist, [$adminCreaturePreviewController, 'show']));
// The item editor (M2.4).
$router->get('/admin/items', $adminGate->protect(Role::Artist, [$adminItemsController, 'show']));
$router->post('/admin/items/create', $adminGate->protect(Role::Artist, [$adminItemsController, 'create']));
$router->get('/admin/items/{id}', $adminGate->protect(Role::Artist, [$adminItemEditController, 'show']));
$router->post('/admin/items/{id}/words', $adminGate->protect(Role::Artist, [$adminItemEditController, 'saveWords']));
$router->post('/admin/items/{id}/numbers', $adminGate->protect(Role::Artist, [$adminItemEditController, 'saveNumbers']));
$router->post('/admin/items/{id}/publish', $adminGate->protect(Role::Artist, [$adminItemEditController, 'publish']));
$router->post('/admin/items/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminItemEditController, 'unpublish']));
$router->post('/admin/items/{id}/revert', $adminGate->protect(Role::Artist, [$adminItemEditController, 'revert']));
$router->get('/admin/items/{id}/icon', $adminGate->protect(Role::Artist, [$adminItemEditController, 'pickIcon']));
$router->post('/admin/items/{id}/icon', $adminGate->protect(Role::Artist, [$adminItemEditController, 'chooseIcon']));
// The shop editor (M2.4).
$router->get('/admin/shops', $adminGate->protect(Role::Artist, [$adminShopsController, 'show']));
$router->post('/admin/shops/create', $adminGate->protect(Role::Artist, [$adminShopsController, 'create']));
$router->get('/admin/shops/{id}', $adminGate->protect(Role::Artist, [$adminShopEditController, 'show']));
$router->post('/admin/shops/{id}/words', $adminGate->protect(Role::Artist, [$adminShopEditController, 'saveWords']));
$router->post('/admin/shops/{id}/keeper', $adminGate->protect(Role::Artist, [$adminShopEditController, 'saveKeeper']));
$router->post('/admin/shops/{id}/publish', $adminGate->protect(Role::Artist, [$adminShopEditController, 'publish']));
$router->post('/admin/shops/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminShopEditController, 'unpublish']));
$router->post('/admin/shops/{id}/revert', $adminGate->protect(Role::Artist, [$adminShopEditController, 'revert']));
$router->post('/admin/shops/{id}/shelf', $adminGate->protect(Role::Artist, [$adminShopEditController, 'putListing']));
$router->post('/admin/shops/{id}/shelf/{itemId}/remove', $adminGate->protect(Role::Artist, [$adminShopEditController, 'removeListing']));
$router->get('/admin/shops/{id}/picture/{slot}', $adminGate->protect(Role::Artist, [$adminShopEditController, 'pickPicture']));
$router->post('/admin/shops/{id}/picture/{slot}', $adminGate->protect(Role::Artist, [$adminShopEditController, 'choosePicture']));
// The area editor (M2.5).
$router->get('/admin/unfinished', $adminGate->protect(Role::Artist, [$adminUnfinishedController, 'show']));
// Moderation (M2.7): the queue, its per-report actions, and account history.
$router->get('/admin/reports', $adminGate->protect(Role::Moderator, [$adminReportsController, 'show']));
$router->post('/admin/reports/{id}/dismiss', $adminGate->protect(Role::Moderator, [$adminReportsController, 'dismiss']));
$router->post('/admin/reports/{id}/warn', $adminGate->protect(Role::Moderator, [$adminReportsController, 'warn']));
$router->post('/admin/reports/{id}/remove-text', $adminGate->protect(Role::Moderator, [$adminReportsController, 'removeText']));
$router->post('/admin/reports/{id}/rename', $adminGate->protect(Role::Moderator, [$adminReportsController, 'rename']));
$router->post('/admin/reports/{id}/mute', $adminGate->protect(Role::Moderator, [$adminReportsController, 'mute']));
$router->post('/admin/reports/{id}/suspend', $adminGate->protect(Role::Moderator, [$adminReportsController, 'suspend']));
$router->get('/admin/accounts/{id}', $adminGate->protect(Role::Moderator, [$adminAccountController, 'show']));
$router->post('/admin/accounts/{id}/warn', $adminGate->protect(Role::Moderator, [$adminAccountController, 'warn']));
$router->post('/admin/accounts/{id}/mute', $adminGate->protect(Role::Moderator, [$adminAccountController, 'mute']));
$router->post('/admin/accounts/{id}/suspend', $adminGate->protect(Role::Moderator, [$adminAccountController, 'suspend']));
$router->post('/admin/accounts/{id}/unmute', $adminGate->protect(Role::Moderator, [$adminAccountController, 'unmute']));
$router->post('/admin/accounts/{id}/unsuspend', $adminGate->protect(Role::Moderator, [$adminAccountController, 'unsuspend']));
// The player's one notice action: "I've read this."
$router->post('/notices/{id}/read', [$noticeController, 'acknowledge']);
// Settings & maintenance (M2.8), behind the most technical role.
$router->get('/admin/settings', $adminGate->protect(Role::Coder, [$adminSettingsController, 'show']));
$router->post('/admin/settings/{key}', $adminGate->protect(Role::Coder, [$adminSettingsController, 'save']));
$router->post('/admin/settings/{key}/reset', $adminGate->protect(Role::Coder, [$adminSettingsController, 'reset']));
$router->post('/admin/maintenance/clear-rate-limits', $adminGate->protect(Role::Coder, [$adminSettingsController, 'clearRateLimits']));
$router->get('/admin/maintenance/art-backup', $adminGate->protect(Role::Coder, [$adminSettingsController, 'artBackup']));
// The stats page (M2.9).
$router->get('/admin/stats', $adminGate->protect(Role::Artist, [$adminStatsController, 'show']));
// Deleting for good. Artist role.
$router->get('/admin/creatures/{id}/delete', $adminGate->protect(Role::Artist, [$adminRemovalController, 'confirmSpecies']));
$router->post('/admin/creatures/{id}/delete', $adminGate->protect(Role::Artist, [$adminRemovalController, 'deleteSpecies']));
$router->get('/admin/items/{id}/delete', $adminGate->protect(Role::Artist, [$adminRemovalController, 'confirmItem']));
$router->post('/admin/items/{id}/delete', $adminGate->protect(Role::Artist, [$adminRemovalController, 'deleteItem']));

// Postcards. Artist role.
$router->get('/admin/postcards', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'index']));
$router->post('/admin/postcards/create', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'create']));
$router->get('/admin/postcards/{id}', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'show']));
$router->post('/admin/postcards/{id}/save', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'save']));
$router->post('/admin/postcards/{id}/publish', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'publish']));
$router->post('/admin/postcards/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'unpublish']));
$router->post('/admin/postcards/{id}/delete', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'delete']));
$router->get('/admin/postcards/{id}/picture', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'pickPicture']));
$router->post('/admin/postcards/{id}/picture', $adminGate->protect(Role::Artist, [$adminPostcardsController, 'choosePicture']));

// Gifts. OWNER role only — this makes value out of nothing.
// Bug reports. Coder role (the technical one; owner passes every check).
$router->get('/admin/bugs', $adminGate->protect(Role::Coder, [$bugReportController, 'index']));
$router->post('/admin/bugs/{id}/status', $adminGate->protect(Role::Coder, [$bugReportController, 'setStatus']));

$router->get('/admin/gifts', $adminGate->protect(Role::Owner, [$adminGiftsController, 'show']));
$router->post('/admin/gifts', $adminGate->protect(Role::Owner, [$adminGiftsController, 'give']));

// Badges. Artist role.
$router->get('/admin/badges', $adminGate->protect(Role::Artist, [$adminBadgesController, 'index']));
$router->post('/admin/badges/{id}', $adminGate->protect(Role::Artist, [$adminBadgesController, 'save']));
$router->post('/admin/badges/{id}/delete', $adminGate->protect(Role::Artist, [$adminBadgesController, 'delete']));
$router->get('/admin/badges/{id}/picture', $adminGate->protect(Role::Artist, [$adminBadgesController, 'pickPicture']));
$router->post('/admin/badges/{id}/picture', $adminGate->protect(Role::Artist, [$adminBadgesController, 'choosePicture']));

// The welcome's words. Artist role.
$router->get('/admin/welcome', $adminGate->protect(Role::Artist, [$welcomeController, 'edit']));
$router->post('/admin/welcome', $adminGate->protect(Role::Artist, [$welcomeController, 'save']));

// The site's words. Artist role (owner passes).
$router->get('/admin/texts', $adminGate->protect(Role::Artist, [$adminTextsController, 'index']));
$router->post('/admin/texts/save', $adminGate->protect(Role::Artist, [$adminTextsController, 'save']));
$router->post('/admin/texts/mode', $adminGate->protect(Role::Artist, [$adminTextsController, 'mode']));

// The site look and the fine-tuning CSS. Artist role.
$router->get('/admin/look', $adminGate->protect(Role::Artist, [$adminLookController, 'show']));
$router->post('/admin/look/default-theme', $adminGate->protect(Role::Artist, [$adminLookController, 'defaultTheme']));
$router->post('/admin/look/sections', $adminGate->protect(Role::Artist, [$adminLookController, 'moveSection']));
$router->post('/admin/look/sections/reset', $adminGate->protect(Role::Artist, [$adminLookController, 'resetSections']));
$router->post('/admin/look/announcement', $adminGate->protect(Role::Artist, [$adminLookController, 'announcement']));
$router->get('/admin/look/css', $adminGate->protect(Role::Artist, [$adminLookController, 'css']));
$router->post('/admin/look/css/preview', $adminGate->protect(Role::Artist, [$adminLookController, 'preview']));
$router->post('/admin/look/css/stop-preview', $adminGate->protect(Role::Artist, [$adminLookController, 'stopPreview']));
$router->post('/admin/look/css/publish', $adminGate->protect(Role::Artist, [$adminLookController, 'publishCss']));
$router->post('/admin/look/css/restore/{id}', $adminGate->protect(Role::Artist, [$adminLookController, 'restoreCss']));

// M4 — themes. Artist role.
$router->get('/admin/themes', $adminGate->protect(Role::Artist, [$adminThemesController, 'index']));
$router->post('/admin/themes/create', $adminGate->protect(Role::Artist, [$adminThemesController, 'create']));
$router->get('/admin/themes/{id}', $adminGate->protect(Role::Artist, [$adminThemesController, 'show']));
$router->post('/admin/themes/{id}/save', $adminGate->protect(Role::Artist, [$adminThemesController, 'save']));
$router->post('/admin/themes/{id}/style', $adminGate->protect(Role::Artist, [$adminThemesController, 'saveStyle']));
$router->post('/admin/themes/{id}/publish', $adminGate->protect(Role::Artist, [$adminThemesController, 'publish']));
$router->post('/admin/themes/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminThemesController, 'unpublish']));
$router->post('/admin/themes/{id}/delete', $adminGate->protect(Role::Artist, [$adminThemesController, 'delete']));

// M3 — traits & titles. Artist role.
$router->get('/admin/character', $adminGate->protect(Role::Artist, [$adminCharacterController, 'show']));
$router->post('/admin/character/traits/{id}', $adminGate->protect(Role::Artist, [$adminCharacterController, 'saveTrait']));
$router->post('/admin/character/traits/{id}/delete', $adminGate->protect(Role::Artist, [$adminCharacterController, 'deleteTrait']));
$router->post('/admin/character/ladder/{ladder}', $adminGate->protect(Role::Artist, [$adminCharacterController, 'saveLadder']));
// M5 — alternate forms (on a species) and the Gazette desk. Artist role.
$router->get('/admin/creatures/{id}/forms', $adminGate->protect(Role::Artist, [$adminFormsController, 'show']));
$router->post('/admin/creatures/{id}/forms/create', $adminGate->protect(Role::Artist, [$adminFormsController, 'create']));
$router->post('/admin/forms/{formId}/words', $adminGate->protect(Role::Artist, [$adminFormsController, 'saveWords']));
$router->post('/admin/forms/{formId}/publish', $adminGate->protect(Role::Artist, [$adminFormsController, 'publish']));
$router->post('/admin/forms/{formId}/unpublish', $adminGate->protect(Role::Artist, [$adminFormsController, 'unpublish']));
$router->post('/admin/forms/{formId}/delete', $adminGate->protect(Role::Artist, [$adminFormsController, 'delete']));
$router->get('/admin/forms/{formId}/picture/{stage}', $adminGate->protect(Role::Artist, [$adminFormsController, 'pickPicture']));
$router->post('/admin/forms/{formId}/picture/{stage}', $adminGate->protect(Role::Artist, [$adminFormsController, 'choosePicture']));
$router->get('/admin/gazette', $adminGate->protect(Role::Artist, [$adminGazetteController, 'index']));
$router->post('/admin/gazette/create', $adminGate->protect(Role::Artist, [$adminGazetteController, 'create']));
$router->get('/admin/gazette/{id}', $adminGate->protect(Role::Artist, [$adminGazetteController, 'show']));
$router->post('/admin/gazette/{id}/words', $adminGate->protect(Role::Artist, [$adminGazetteController, 'saveWords']));
$router->post('/admin/gazette/{id}/publish', $adminGate->protect(Role::Artist, [$adminGazetteController, 'publish']));
$router->post('/admin/gazette/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminGazetteController, 'unpublish']));
$router->post('/admin/gazette/{id}/delete', $adminGate->protect(Role::Artist, [$adminGazetteController, 'delete']));
$router->get('/admin/gazette/{id}/picture', $adminGate->protect(Role::Artist, [$adminGazetteController, 'pickPicture']));
$router->post('/admin/gazette/{id}/picture', $adminGate->protect(Role::Artist, [$adminGazetteController, 'choosePicture']));
$router->get('/admin/areas', $adminGate->protect(Role::Artist, [$adminAreasController, 'show']));
$router->post('/admin/areas/create', $adminGate->protect(Role::Artist, [$adminAreasController, 'create']));
$router->get('/admin/areas/{id}', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'show']));
$router->post('/admin/areas/{id}/words', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'saveWords']));
$router->post('/admin/areas/{id}/spots', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'saveSpots']));
$router->post('/admin/areas/{id}/loot', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'saveLoot']));
$router->post('/admin/areas/{id}/publish', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'publish']));
$router->post('/admin/areas/{id}/unpublish', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'unpublish']));
$router->post('/admin/areas/{id}/revert', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'revert']));
$router->get('/admin/areas/{id}/picture', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'pickPicture']));
$router->post('/admin/areas/{id}/picture', $adminGate->protect(Role::Artist, [$adminAreaEditController, 'choosePicture']));

// (Serving an uploaded picture — GET /art/{name} — is NOT registered here:
// it is dispatched near the top of this file, before the session and the
// rest of the world are built. See the comment there.)

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
// Playing a game (owner only). GET opens a round, POST answers it.
$router->get('/creature/{id}/play', [$playController, 'show']);
$router->post('/creature/{id}/play', [$playController, 'answer']);
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
