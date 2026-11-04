<?php

declare(strict_types=1);

namespace Felkyo\Social;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Users\User;

/**
 * The panel's rules for postcard designs (M6.4).
 *
 * @package Felkyo\Social
 *
 * A design is a picture (art filed as "card") plus the fixed words the site
 * prints over it. The words are the ONLY thing a card can say between two
 * players, so they are written here, by staff, never by players — and they
 * should stay warm, never personal (the same rule as bottle messages).
 *
 * Routes are behind AdminGate (artist role); every change is audit-logged.
 */
final class PostcardEditorService
{
    private const NAME_MAX = 60;
    private const MESSAGE_MAX = 200;
    private const PRICE_MAX = 500;

    public function __construct(
        private PostcardRepository $postcards,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
    ) {
    }

    public function create(User $actor, string $name, string $ip): ContentEditResult
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            return ContentEditResult::refused(sprintf('A card needs a name of 1–%d characters.', self::NAME_MAX));
        }

        // A friendly placeholder message, so a new draft is never blank.
        $id = $this->postcards->createDesign($name, 'Warm wishes from Felkyo!');
        $this->audit($actor, $id, ['created' => $name], $ip);

        return ContentEditResult::ok('Card started as a draft — give it a picture and its words.', $id);
    }

    public function save(User $actor, int $id, string $name, string $message, int $gemPrice, string $ip): ContentEditResult
    {
        if ($this->postcards->findDesign($id) === null) {
            return ContentEditResult::refused('That card is gone.');
        }
        $name = trim($name);
        $message = trim($message);
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            return ContentEditResult::refused(sprintf('A card needs a name of 1–%d characters.', self::NAME_MAX));
        }
        if ($message === '' || mb_strlen($message) > self::MESSAGE_MAX) {
            return ContentEditResult::refused(sprintf('The words need to be 1–%d characters — short enough to sit on the picture.', self::MESSAGE_MAX));
        }
        if ($gemPrice < 1 || $gemPrice > self::PRICE_MAX) {
            return ContentEditResult::refused(sprintf('The price needs to be between 1 and %d gems.', self::PRICE_MAX));
        }

        $this->postcards->updateDesign($id, $name, $message, $gemPrice);
        $this->audit($actor, $id, ['name' => $name, 'message' => $message, 'gem_price' => $gemPrice], $ip);

        return ContentEditResult::ok('Saved.', $id);
    }

    public function setPublished(User $actor, int $id, bool $publish, string $ip): ContentEditResult
    {
        $design = $this->postcards->findDesign($id);
        if ($design === null) {
            return ContentEditResult::refused('That card is gone.');
        }

        $this->postcards->setDesignStatus($id, $publish ? 'published' : 'draft');
        $this->audit($actor, $id, ['status' => $publish ? 'published' : 'draft'], $ip);

        return ContentEditResult::ok($publish
            ? sprintf('"%s" is on the post office rack now.', $design['name'])
            : sprintf('"%s" is off the rack. Cards already sent stay on their mantelpieces.', $design['name']), $id);
    }

    public function choosePicture(User $actor, int $id, int $artFileId, string $ip): ContentEditResult
    {
        if ($this->postcards->findDesign($id) === null) {
            return ContentEditResult::refused('That card is gone.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null || $picture->kind !== ArtKind::Card) {
                return ContentEditResult::refused('Only pictures filed as "card art" fit here — upload it under that kind first.');
            }
            $chosenId = $picture->id;
        }

        $this->postcards->setDesignArt($id, $chosenId);
        $this->audit($actor, $id, ['art_id' => $chosenId], $ip);

        return ContentEditResult::ok($chosenId === null ? 'Picture unlinked.' : 'Picture linked.', $id);
    }

    /** Only a never-sent card can be deleted; a sent one can be retired to draft. */
    public function delete(User $actor, int $id, string $ip): ContentEditResult
    {
        $design = $this->postcards->findDesign($id);
        if ($design === null) {
            return ContentEditResult::refused('That card is already gone.');
        }
        $sent = $this->postcards->timesSent($id);
        if ($sent > 0) {
            return ContentEditResult::refused(sprintf(
                'This card has been sent %d time(s) and sits on people\'s mantelpieces, so it can\'t be deleted — set it back to draft to take it off the rack.',
                $sent
            ));
        }

        $this->postcards->deleteDesign($id);
        $this->audit($actor, $id, ['deleted' => $design['name']], $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted.', $design['name']));
    }

    private function audit(User $actor, int $id, array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::PostcardSaved,
            subjectType: 'postcard_design',
            subjectId: $id,
            after: $after,
            ip: $ip,
        ));
    }
}
