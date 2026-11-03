<?php

declare(strict_types=1);

namespace Felkyo\Library;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Art\ArtKind;
use Felkyo\Art\ArtRepository;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Users\User;

/**
 * The rules for editing alternate forms in the panel (M5.3).
 *
 * @package Felkyo\Library
 *
 * Every change answers with a ContentEditResult — one plain sentence the
 * panel flashes — and every successful change is written to the audit log.
 * Who may call this at all is decided before it is reached: the routes are
 * wrapped by AdminGate with the artist role.
 */
final class FormEditorService
{
    private const NAME_MAX = 60;
    private const BLURB_MAX = 500;

    /** @param string[] $stageNames the growth stages, in order. */
    public function __construct(
        private SpeciesFormRepository $forms,
        private SpeciesRepository $species,
        private ArtRepository $art,
        private AuditLogRepository $auditLog,
        private array $stageNames,
    ) {
    }

    /** Start a new draft form for a species. */
    public function create(User $actor, int $speciesId, string $name, string $ip): ContentEditResult
    {
        $name = trim($name);
        $species = $this->species->findById($speciesId);
        if ($species === null) {
            return ContentEditResult::refused('That species is gone.');
        }
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            return ContentEditResult::refused(sprintf('A form needs a name of 1–%d characters.', self::NAME_MAX));
        }

        // The slug is only for tidiness and uniqueness inside one species.
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($slug === '' || $this->forms->slugTaken($speciesId, $slug)) {
            return ContentEditResult::refused('This species already has a form by that name — pick another.');
        }

        $formId = $this->forms->create($speciesId, $slug, $name);
        $this->audit($actor, $formId, null, ['name' => $name, 'species' => $species->name], $ip);

        return ContentEditResult::ok(sprintf('"%s" started as a draft. Give it pictures, then publish it.', $name), $formId);
    }

    public function saveWords(User $actor, int $formId, string $name, string $blurb, string $ip): ContentEditResult
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            return ContentEditResult::refused('That form is gone.');
        }
        $name = trim($name);
        $blurb = trim($blurb);
        if ($name === '' || mb_strlen($name) > self::NAME_MAX) {
            return ContentEditResult::refused(sprintf('A form needs a name of 1–%d characters.', self::NAME_MAX));
        }
        if (mb_strlen($blurb) > self::BLURB_MAX) {
            return ContentEditResult::refused(sprintf('The blurb is too long — %d characters is the most.', self::BLURB_MAX));
        }

        $this->forms->updateWords($formId, $name, $blurb === '' ? null : $blurb);
        $this->audit($actor, $formId, ['name' => $form->name], ['name' => $name], $ip);

        return ContentEditResult::ok('Words saved.', $formId);
    }

    /** Publish (true) or take back to draft (false). */
    public function setPublished(User $actor, int $formId, bool $publish, string $ip): ContentEditResult
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            return ContentEditResult::refused('That form is gone.');
        }

        $status = $publish ? SpeciesForm::STATUS_PUBLISHED : SpeciesForm::STATUS_DRAFT;
        $this->forms->setStatus($formId, $status);
        $this->audit($actor, $formId, ['status' => $form->status], ['status' => $status], $ip);

        return ContentEditResult::ok($publish
            ? sprintf('"%s" is published — it shows in the library, and new creatures can now be born in it.', $form->name)
            : sprintf('"%s" is a draft again. Creatures already wearing it keep it.', $form->name), $formId);
    }

    public function delete(User $actor, int $formId, string $ip): ContentEditResult
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            return ContentEditResult::refused('That form is already gone.');
        }

        $this->forms->delete($formId);
        $this->audit($actor, $formId, ['name' => $form->name], null, $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted. Any creatures wearing it look like their species again.', $form->name));
    }

    /** Link (art id) or unlink (0) a stage's sprite. Only sprites fit. */
    public function choosePicture(User $actor, int $formId, string $stage, int $artFileId, string $ip): ContentEditResult
    {
        $form = $this->forms->findById($formId);
        if ($form === null) {
            return ContentEditResult::refused('That form is gone.');
        }
        if (!in_array($stage, $this->stageNames, true)) {
            return ContentEditResult::refused('That stage doesn\'t exist.');
        }

        $chosenId = null;
        if ($artFileId !== 0) {
            $picture = $this->art->findById($artFileId);
            if ($picture === null || $picture->kind !== ArtKind::Sprite) {
                return ContentEditResult::refused('Only pictures filed as sprites fit here — upload it under "sprite" first.');
            }
            $chosenId = $picture->id;
        }

        $this->forms->setStageArt($formId, $stage, $chosenId);
        $this->audit($actor, $formId, null, ['stage' => $stage, 'art_id' => $chosenId], $ip);

        return ContentEditResult::ok($chosenId === null ? 'Picture unlinked.' : 'Picture linked.', $formId);
    }

    private function audit(User $actor, int $formId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::FormSaved,
            subjectType: 'species_form',
            subjectId: $formId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }
}
