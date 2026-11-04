<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Users\UserRepository;
use Felkyo\Users\WelcomeRepository;
use League\Plates\Engine;

/**
 * The first-run welcome (M7): dismissing it, and editing it in the panel.
 *
 *   POST /welcome/dismiss        a player: "don't show me this again"
 *   GET  /admin/welcome          the panel: Ruinily's lines, one per line
 *   POST /admin/welcome
 *
 * @package Felkyo\Http\Controllers
 *
 * The bubble itself is drawn on the home page (HomeController hands it the
 * steps). Each step's line in the panel is "words | /link | link words";
 * a link must point inside the site (start with one "/"), so the welcome can
 * never send a new player somewhere else.
 */
final class WelcomeController
{
    private const MAX_STEPS = 12;

    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private WelcomeRepository $welcome,
        private AuditLogRepository $auditLog,
    ) {
    }

    public function dismiss(Request $request): Response
    {
        $userId = $this->session->get('user_id');
        if (is_int($userId) && $this->csrf->isValid($request->input('_csrf_token'))) {
            $this->welcome->dismiss($userId);
        }

        return Response::redirect('/');
    }

    public function edit(Request $request): Response
    {
        $lines = array_map(static function (array $step): string {
            return $step['link_href'] === null
                ? $step['words']
                : $step['words'] . ' | ' . $step['link_href'] . ' | ' . $step['link_label'];
        }, $this->welcome->steps());

        return Response::html($this->templates->render('pages/admin-welcome', ['lines' => implode("\n", $lines)]));
    }

    public function save(Request $request): Response
    {
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null || !$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect('/admin/welcome');
        }

        $steps = [];
        foreach (preg_split('/\R/', $request->input('lines')) ?: [] as $number => $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $step = ['words' => $parts[0], 'link_href' => $parts[1] ?? null, 'link_label' => $parts[2] ?? null];
            $problem = $this->problemWith($step);
            if ($problem !== null) {
                $this->session->flash(sprintf('Line %d: %s', $number + 1, $problem));

                return Response::redirect('/admin/welcome');
            }
            $steps[] = $step;
        }
        if ($steps === [] || count($steps) > self::MAX_STEPS) {
            $this->session->flash(sprintf('The welcome needs between 1 and %d lines.', self::MAX_STEPS));

            return Response::redirect('/admin/welcome');
        }

        $this->welcome->replaceSteps($steps);
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id, action: AuditAction::CharacterSaved, subjectType: 'welcome',
            after: ['steps' => count($steps)], ip: $request->clientIp(),
        ));
        $this->session->flash('Ruinily has learned her new lines.');

        return Response::redirect('/admin/welcome');
    }

    /** @param array{words: string, link_href: ?string, link_label: ?string} $step */
    private function problemWith(array $step): ?string
    {
        if ($step['words'] === '' || mb_strlen($step['words']) > 300) {
            return 'her words need to be 1–300 characters.';
        }
        if ($step['link_href'] === null || $step['link_href'] === '') {
            return null;
        }
        if (!preg_match('~^/(?!/)[A-Za-z0-9/_\-?=&.]*$~', $step['link_href'])) {
            return 'a link must be a page on this site, starting with / (like /shop).';
        }
        if ($step['link_label'] === null || $step['link_label'] === '' || mb_strlen($step['link_label']) > 60) {
            return 'a link needs a few words for its button (up to 60 characters), after a second |.';
        }

        return null;
    }
}
