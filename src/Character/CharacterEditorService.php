<?php

declare(strict_types=1);

namespace Felkyo\Character;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Users\User;

/**
 * The panel's rules for traits and the two ladders (M3.2, M3.2c).
 *
 * @package Felkyo\Character
 *
 * Every value from the form is checked against an allow-list or a range
 * here before it reaches the repository. One limit worth knowing: the XP
 * bonus is capped at 25% — traits are flavour plus ONE small bonus, by
 * design (build plan M3.2), and the cap keeps a typo from becoming a
 * mechanic.
 */
final class CharacterEditorService
{
    private const BONUS_CAP = 25;
    private const LADDER_MAX_RUNGS = 40;

    public function __construct(
        private TraitRepository $traits,
        private LadderRepository $ladders,
        private AuditLogRepository $auditLog,
    ) {
    }

    /**
     * Create (id 0) or update a trait from the panel form's raw values.
     *
     * @param array<string, string> $input name, description, tier, earn_kind,
     *        earn_threshold, chance_percent, xp_bonus_percent
     */
    public function saveTrait(User $actor, int $id, array $input, string $ip): ContentEditResult
    {
        $name = trim($input['name'] ?? '');
        $description = trim($input['description'] ?? '');
        $tier = $input['tier'] ?? '';
        $earnKind = $input['earn_kind'] ?? '';

        if ($name === '' || mb_strlen($name) > 40) {
            return ContentEditResult::refused('A trait needs a name of 1–40 characters.');
        }
        if ($description === '' || mb_strlen($description) > 200) {
            return ContentEditResult::refused('A trait needs a description of 1–200 characters.');
        }
        if (!in_array($tier, CreatureTrait::TIERS, true)) {
            return ContentEditResult::refused('Pick which kind of trait it is.');
        }
        if ($tier === CreatureTrait::TIER_EARNED && !array_key_exists($earnKind, CreatureTrait::EARN_KINDS)) {
            return ContentEditResult::refused('An earned trait needs to say what earns it.');
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($id === 0 && ($slug === '' || $this->traits->slugExists($slug))) {
            return ContentEditResult::refused('There is already a trait with that name.');
        }

        $fields = [
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'tier' => $tier,
            'earn_kind' => $tier === CreatureTrait::TIER_EARNED ? $earnKind : null,
            'earn_threshold' => $tier === CreatureTrait::TIER_EARNED ? max(1, (int) ($input['earn_threshold'] ?? 1)) : null,
            'chance_percent' => $tier === CreatureTrait::TIER_GIFT ? min(100, max(0, (int) ($input['chance_percent'] ?? 0))) : 0,
            'xp_bonus_percent' => min(self::BONUS_CAP, max(0, (int) ($input['xp_bonus_percent'] ?? 0))),
        ];
        $this->traits->save($id, $fields);
        $this->audit($actor, 'trait', $id, $fields, $ip);

        return ContentEditResult::ok(sprintf('"%s" saved.', $name));
    }

    public function deleteTrait(User $actor, int $id, string $ip): ContentEditResult
    {
        $trait = $this->traits->findById($id);
        if ($trait === null) {
            return ContentEditResult::refused('That trait is already gone.');
        }
        $this->traits->delete($id);
        $this->audit($actor, 'trait', $id, ['deleted' => $trait->name], $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted — and gone from every creature that had it.', $trait->name));
    }

    /**
     * Replace a ladder from a block of lines like "6: Sprightly".
     * Lines that don't fit that shape are refused by number, so the artist
     * knows exactly which one to fix.
     */
    public function saveLadder(User $actor, string $ladder, string $text, string $ip): ContentEditResult
    {
        if (!in_array($ladder, [LadderRepository::LEVEL_TITLE, LadderRepository::AGE], true)) {
            return ContentEditResult::refused('That list doesn\'t exist.');
        }

        $rungs = [];
        $lines = preg_split('/\R/', $text) ?: [];
        foreach ($lines as $number => $line) {
            if (trim($line) === '') {
                continue;
            }
            if (!preg_match('/^\s*(\d{1,5})\s*:\s*(.{1,60}?)\s*$/u', $line, $match)) {
                return ContentEditResult::refused(sprintf(
                    'Line %d should look like "6: Sprightly" — a number, a colon, then the words (up to 60 characters).',
                    $number + 1
                ));
            }
            $rungs[(int) $match[1]] = $match[2];
        }
        if ($rungs === [] || count($rungs) > self::LADDER_MAX_RUNGS) {
            return ContentEditResult::refused(sprintf('A list needs between 1 and %d lines.', self::LADDER_MAX_RUNGS));
        }

        ksort($rungs);
        $this->ladders->replace($ladder, $rungs);
        $this->audit($actor, 'ladder', 0, ['ladder' => $ladder, 'rungs' => $rungs], $ip);

        return ContentEditResult::ok('List saved — every page shows the new words now.');
    }

    private function audit(User $actor, string $type, int $id, array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::CharacterSaved,
            subjectType: $type,
            subjectId: $id === 0 ? null : $id,
            after: $after,
            ip: $ip,
        ));
    }
}
