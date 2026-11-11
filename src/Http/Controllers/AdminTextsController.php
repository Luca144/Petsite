<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Feedback\BugReportService;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Texts\TextCatalogue;
use Felkyo\Texts\TextEditorService;
use Felkyo\Texts\TextScanner;
use Felkyo\Users\UserRepository;
use League\Plates\Engine;

/**
 * The site's words: the panel's list, saving one, and editing mode.
 *
 *   GET  /admin/texts?where=…&q=…   every sentence, by page, with search
 *   POST /admin/texts/save          change one (or put the original back)
 *   POST /admin/texts/mode          editing mode on / off for yourself
 *
 * @package Felkyo\Http\Controllers
 *
 * All three are behind the AdminGate (artist role; owner passes), so a
 * stolen session alone can't change the site's words — the panel's own
 * door (password and authenticator) stands in front of every save.
 *
 * Saving comes back to the page it came from ("back", checked to be a path
 * on this site), so clicking a sentence in editing mode, changing it and
 * pressing Save lands you on the same page, showing the new words.
 */
final class AdminTextsController
{
    /** The session flag that switches editing mode on for one person. */
    public const MODE_KEY = 'text_edit_mode';

    /** @param array{max_attempts: int, window_seconds: int} $saveRateLimit */
    public function __construct(
        private Engine $templates,
        private Session $session,
        private Csrf $csrf,
        private UserRepository $users,
        private TextEditorService $editor,
        private TextCatalogue $catalogue,
        private TextScanner $scanner,
        private RateLimiter $rateLimiter,
        private array $saveRateLimit,
    ) {
    }

    public function index(Request $request): Response
    {
        $where = $request->query('where');
        $search = mb_strtolower(trim($request->query('q')));

        // Every sentence in one list: template texts by the file they're in,
        // code messages under "Messages".
        $rows = [];
        foreach ($this->scanner->templateTexts() as $key => $text) {
            $rows[] = ['key' => $key, 'where' => $text['file'], 'original' => $text['default']];
        }
        foreach ($this->scanner->messages() as $key => $message) {
            $rows[] = ['key' => $key, 'where' => 'messages', 'original' => TextScanner::friendly($message['original'])];
        }
        $places = array_values(array_unique(array_column($rows, 'where')));
        sort($places);

        $shown = [];
        foreach ($rows as $row) {
            $row['words'] = $this->wordsFor($row['key'], $row['original']);
            $row['changed'] = $this->catalogue->isChanged($row['key']);
            $matchesSearch = $search === '' || str_contains(mb_strtolower($row['words'] . ' ' . $row['original'] . ' ' . $row['key']), $search);
            $matchesPlace = $where === '' ? ($search !== '' || $row['changed']) : $row['where'] === $where;
            if ($matchesSearch && $matchesPlace) {
                $shown[] = $row;
            }
        }

        return Response::html($this->templates->render('pages/admin-texts', [
            'places' => $places,
            'where' => $where,
            'search' => $request->query('q'),
            'rows' => array_slice($shown, 0, 300),
            'total' => count($rows),
            'isEditing' => $this->session->get(self::MODE_KEY) === true,
        ]));
    }

    public function save(Request $request): Response
    {
        $backTo = BugReportService::sitePath($request->input('back', '/admin/texts'));
        $actor = $this->users->findById((int) $this->session->get('user_id'));
        if ($actor === null) {
            return Response::redirect('/login');
        }
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }
        $bucket = 'user:' . $actor->id;
        if (!$this->rateLimiter->isAllowed('content_save', $bucket, $this->saveRateLimit['max_attempts'], $this->saveRateLimit['window_seconds'])) {
            $this->session->flash('That\'s a lot of changes for one hour. Take a breather, then carry on.');

            return Response::redirect($backTo);
        }
        $this->rateLimiter->record('content_save', $bucket);

        $words = $request->input('reset') === '1' ? '' : $request->input('words');
        $this->session->flash($this->editor->save($actor, $request->input('key'), $words, $request->clientIp())->message);

        return Response::redirect($backTo);
    }

    public function mode(Request $request): Response
    {
        $backTo = BugReportService::sitePath($request->input('back', '/'));
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            $this->session->flash('That took too long — please try again.');

            return Response::redirect($backTo);
        }
        $turningOn = $this->session->get(self::MODE_KEY) !== true;
        $turningOn ? $this->session->set(self::MODE_KEY, true) : $this->session->remove(self::MODE_KEY);
        $this->session->flash($turningOn
            ? 'Editing mode is on: click (or tab to) any sentence with a dotted line to change it.'
            : 'Editing mode is off.');

        return Response::redirect($backTo);
    }

    /** What a sentence says now, in the form the artist edits (gaps as {1}…). */
    private function wordsFor(string $key, string $original): string
    {
        return $this->catalogue->words($key, $original);
    }
}
