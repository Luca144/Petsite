<?php

declare(strict_types=1);

namespace Felkyo\Character;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Users\User;

/**
 * The panel's rules for keepsake badges.
 *
 * @package Felkyo\Character
 *
 * Creature badges need a number of days (1–3650): they arrive by themselves
 * once a creature has been in Felkyo that long. Player badges have no
 * number: only the owner gives them, from the gift desk. Pictures must be
 * art filed as "badge". Every change is audit-logged.
 */
final class BadgeEditorService
{
    public function __construct(
        private BadgeRepository $badges,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
    ) {
    }

    /** Create (id 0) or update a badge from the form's raw values. */
    public function save(User $actor, int $id, string $name, string $description, string $audience, int $keptDays, string $ip): ContentEditResult
    {
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name) > 40) {
            return ContentEditResult::refused('A badge needs a name of 1–40 characters.');
        }
        if ($description === '' || mb_strlen($description) > 200) {
            return ContentEditResult::refused('A badge needs a few words (up to 200 characters).');
        }
        if (!in_array($audience, ['creature', 'player'], true)) {
            return ContentEditResult::refused('Pick who the badge is for.');
        }
        if ($audience === 'creature' && ($keptDays < 1 || $keptDays > 3650)) {
            return ContentEditResult::refused('A creature badge needs a number of days between 1 and 3650.');
        }
        if ($id !== 0 && $this->badges->findById($id) === null) {
            return ContentEditResult::refused('That badge is gone.');
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($id === 0 && ($slug === '' || $this->badges->slugExists($slug))) {
            return ContentEditResult::refused('There is already a badge with that name.');
        }

        $days = $audience === 'creature' ? $keptDays : null;
        $this->badges->save($id, $slug, $name, $description, $audience, $days);
        $this->audit($actor, $id, ['name' => $name, 'awarded_to' => $audience, 'kept_days' => $days], $ip);

        return ContentEditResult::ok(sprintf('"%s" saved.', $name));
    }

    public function delete(User $actor, int $id, string $ip): ContentEditResult
    {
        $badge = $this->badges->findById($id);
        if ($badge === null) {
            return ContentEditResult::refused('That badge is already gone.');
        }
        $this->badges->delete($id);
        $this->audit($actor, $id, ['deleted' => $badge['name']], $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted — and gone from everyone who had it.', $badge['name']));
    }

    public function choosePicture(User $actor, int $id, int $artFileId, string $ip): ContentEditResult
    {
        if ($this->badges->findById($id) === null) {
            return ContentEditResult::refused('That badge is gone.');
        }
        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null || $picture->kind !== ArtKind::Badge) {
                return ContentEditResult::refused('Only pictures filed as "badge" fit here — upload it under that kind first.');
            }
            $chosenId = $picture->id;
        }
        $this->badges->setArt($id, $chosenId);
        $this->audit($actor, $id, ['art_id' => $chosenId], $ip);

        return ContentEditResult::ok($chosenId === null ? 'Picture unlinked.' : 'Picture linked.');
    }

    private function audit(User $actor, int $id, array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::CharacterSaved,
            subjectType: 'badge',
            subjectId: $id === 0 ? null : $id,
            after: $after,
            ip: $ip,
        ));
    }
}
