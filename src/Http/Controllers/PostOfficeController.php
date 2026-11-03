<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Pound\PoundResult;
use Felkyo\Security\RateLimiter;
use Felkyo\Social\BottleService;
use Felkyo\Social\BottleRepository;
use Felkyo\Social\CompanyRepository;
use Felkyo\Social\PostcardRepository;
use Felkyo\Social\PostOfficeService;
use League\Plates\Engine;

/**
 * The post office (M6.4) and the shore (M6.5) — the two ways players send
 * each other warmth in words the project wrote.
 *
 *   GET  /post-office          card designs as tiles + who to send to
 *   POST /post-office          buy and send one card
 *   GET  /shore                set a bottle adrift; look for one
 *   POST /shore/send           (one a day)
 *   POST /shore/find           (one a day; a fruitless walk is given back)
 *
 * @package Felkyo\Http\Controllers
 *
 * Logged-in players only; CSRF on every POST; a per-account rate limit on
 * top of the services' own daily caps. The rules themselves live in
 * PostOfficeService and BottleService.
 */
final class PostOfficeController
{
    /** @param array{max_attempts: int, window_seconds: int} $rateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private PostcardRepository $postcards,
        private PostOfficeService $postOffice,
        private CompanyRepository $company,
        private BottleService $bottleService,
        private BottleRepository $bottles,
        private RateLimiter $rateLimiter,
        private array $rateLimit,
    ) {
    }

    public function postOffice(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        return Response::html($this->templates->render('pages/post-office', [
            'designs' => $this->postcards->designsOnSale(),
            'friends' => $this->company->friendsOf($userId),
            // Arriving from someone's page ("send them a card") fills the name in.
            'to' => mb_substr($request->query('to'), 0, 40),
        ]));
    }

    public function sendCard(Request $request): Response
    {
        return $this->guarded($request, '/post-office', fn (int $userId) => $this->postOffice->send(
            $userId, (int) $request->input('design_id', '0'), $request->input('recipient')
        ));
    }

    public function shore(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        $found = $this->bottles->foundBy($userId, 10);
        foreach ($found as $index => $bottle) {
            $found[$index]['words'] = $this->bottleService->wordsFor($bottle['message_key']);
        }

        return Response::html($this->templates->render('pages/shore', [
            'messages' => $this->bottleService->messages(),
            'canSend' => $this->bottleService->canSendToday($userId),
            'canFind' => $this->bottleService->canFindToday($userId),
            'adrift' => $this->bottles->countAdriftFrom($userId),
            'found' => $found,
        ]));
    }

    public function sendBottle(Request $request): Response
    {
        return $this->guarded($request, '/shore', fn (int $userId) =>
            $this->bottleService->send($userId, $request->input('message')));
    }

    public function findBottle(Request $request): Response
    {
        return $this->guarded($request, '/shore', fn (int $userId) => $this->bottleService->find($userId));
    }

    /** @param callable(int): PoundResult $action */
    private function guarded(Request $request, string $backTo, callable $action): Response
    {
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }
        if (!$this->rateLimiter->isAllowed('post', 'user:' . $userId, $this->rateLimit['max_attempts'], $this->rateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of post — give it a little while.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('post', 'user:' . $userId);

        $this->session->flash($action($userId)->message);

        return Response::redirect($backTo);
    }
}
