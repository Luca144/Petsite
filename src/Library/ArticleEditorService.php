<?php

declare(strict_types=1);

namespace Felkyo\Library;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Users\User;

/**
 * The rules for writing the Felkyo Gazette in the panel (M5.4).
 *
 * @package Felkyo\Library
 *
 * Same shape as every other panel editor: one plain sentence back
 * (ContentEditResult), an audit row for every change. The routes are behind
 * AdminGate with the artist role. The body is stored as plain text and
 * escaped when shown, so nothing typed here can become HTML on the page.
 */
final class ArticleEditorService
{
    private const TITLE_MAX = 120;
    private const SUMMARY_MAX = 300;
    private const BODY_MAX = 20000;

    // Pictures that make sense at the top of a newspaper piece.
    private const ALLOWED_ART = [ArtKind::Library, ArtKind::Portrait, ArtKind::Background, ArtKind::Card];

    public function __construct(
        private ArticleRepository $articles,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
    ) {
    }

    public function create(User $actor, string $title, string $ip): ContentEditResult
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            return ContentEditResult::refused(sprintf('A headline needs 1–%d characters.', self::TITLE_MAX));
        }

        // Addresses are made from the headline; a clash gets a number on the end.
        $base = trim(substr((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), 0, 70), '-');
        $base = $base === '' ? 'article' : $base;
        $slug = $base;
        for ($attempt = 2; $this->articles->slugExists($slug); $attempt++) {
            $slug = $base . '-' . $attempt;
        }

        $id = $this->articles->create($slug, $title);
        $this->audit($actor, $id, null, ['title' => $title], $ip);

        return ContentEditResult::ok('Draft started. Write it, then publish it when it\'s ready.', $id);
    }

    public function saveWords(User $actor, int $id, string $title, string $summary, string $body, string $ip, string $kind = Article::KIND_GAZETTE): ContentEditResult
    {
        $article = $this->articles->findById($id);
        if ($article === null) {
            return ContentEditResult::refused('That article is gone.');
        }
        $title = trim($title);
        $summary = trim($summary);
        $body = trim($body);
        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            return ContentEditResult::refused(sprintf('A headline needs 1–%d characters.', self::TITLE_MAX));
        }
        if (mb_strlen($summary) > self::SUMMARY_MAX) {
            return ContentEditResult::refused(sprintf('The summary is too long — %d characters is the most.', self::SUMMARY_MAX));
        }
        if (mb_strlen($body) > self::BODY_MAX) {
            return ContentEditResult::refused(sprintf('The article is too long — %d characters is the most.', self::BODY_MAX));
        }

        $this->articles->updateWords($id, $title, $summary === '' ? null : $summary, $body === '' ? null : $body);
        if (in_array($kind, [Article::KIND_GAZETTE, Article::KIND_UPDATE], true)) {
            $this->articles->setKind($id, $kind);
        }
        $this->audit($actor, $id, ['title' => $article->title], ['title' => $title], $ip);

        return ContentEditResult::ok('Saved.', $id);
    }

    public function setPublished(User $actor, int $id, bool $publish, string $ip): ContentEditResult
    {
        $article = $this->articles->findById($id);
        if ($article === null) {
            return ContentEditResult::refused('That article is gone.');
        }

        $status = $publish ? Article::STATUS_PUBLISHED : Article::STATUS_DRAFT;
        $this->articles->setStatus($id, $status);
        $this->audit($actor, $id, ['status' => $article->status], ['status' => $status], $ip);

        return ContentEditResult::ok($publish ? 'Published — it\'s in the Gazette now.' : 'Back to draft; it\'s gone from the Gazette.', $id);
    }

    public function delete(User $actor, int $id, string $ip): ContentEditResult
    {
        $article = $this->articles->findById($id);
        if ($article === null) {
            return ContentEditResult::refused('That article is already gone.');
        }

        $this->articles->delete($id);
        $this->audit($actor, $id, ['title' => $article->title], null, $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted.', $article->title));
    }

    public function choosePicture(User $actor, int $id, int $artFileId, string $ip): ContentEditResult
    {
        if ($this->articles->findById($id) === null) {
            return ContentEditResult::refused('That article is gone.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null || !in_array($picture->kind, self::ALLOWED_ART, true)) {
                return ContentEditResult::refused('That picture can\'t go in the Gazette.');
            }
            $chosenId = $picture->id;
        }

        $this->articles->setArt($id, $chosenId);
        $this->audit($actor, $id, null, ['art_id' => $chosenId], $ip);

        return ContentEditResult::ok($chosenId === null ? 'Picture unlinked.' : 'Picture linked.', $id);
    }

    /** The art kinds the picker offers (the controller filters by these). */
    public function allowedArtKinds(): array
    {
        return self::ALLOWED_ART;
    }

    private function audit(User $actor, int $id, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::ArticleSaved,
            subjectType: 'article',
            subjectId: $id,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }
}
