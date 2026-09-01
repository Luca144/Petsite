<?php

declare(strict_types=1);

namespace Felkyo\Moderation;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Admin\RoleRepository;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Safety\ReportReason;
use Felkyo\Safety\ReportSubject;
use Felkyo\Users\ProfileRepository;
use Felkyo\Users\User;
use PDO;

/**
 * The rules of moderating: every action a moderator can take, and every
 * guard on them (M2.7).
 *
 * @package Felkyo\Moderation
 *
 * EVERY ACTION IS THE SAME SHAPE: guard, then one transaction holding
 * the change + the notice + the audit row + (when the action answers a
 * report) closing that report as actioned. If any piece fails, none of
 * it happened — a player must never get a "your bio was removed" notice
 * for a bio that is still there.
 *
 * TWO GUARDS APPLY TO EVERYTHING:
 *  - Nobody moderates themselves. Acting on your own account is always
 *    a mistake or an experiment; both deserve a refusal.
 *  - Nobody moderates STAFF. A moderator muting the owner (or another
 *    moderator) would be privilege escalation through the side door —
 *    staff matters go through the owner and the roles screen, where the
 *    audit trail already lives.
 *
 * FORCED WORDS ARE OUR OWN. The neutral names come from config, never
 * from the moderator's keyboard, and skip the player-facing validators
 * on purpose — they are not player input. The player's notice says they
 * can pick a new name themselves.
 *
 * DELIBERATELY OVER THE 300-LINE GUIDE, like the four editor services:
 * one action per public method, all the same shape, and splitting "what
 * moderators can do" across files would hide exactly the surface a
 * security review most needs to read in one sitting (CLAUDE.md §4
 * escape hatch, used knowingly).
 */
final class ModerationService
{
    public function __construct(
        private PDO $connection,
        private ModerationRepository $moderation,
        private NoticeRepository $notices,
        private AuditLogRepository $auditLog,
        private RoleRepository $roles,
        private CreatureRepository $creatures,
        private ProfileRepository $profiles,
        private ImpersonationGuard $impersonation,
        private array $rules,
    ) {
    }

    // ---- The queue, ready for its screen ----

    /**
     * Every open report with its subject resolved to showable words: the
     * actual reported name or text (staff see hidden text — judging it is
     * the job), who reported it, how many different voices, and the
     * reporter's own track record.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queue(): array
    {
        $rows = [];
        foreach ($this->moderation->openReports() as $report) {
            $rows[] = $report + [
                'reason' => ReportReason::tryFrom((string) $report['category'])?->label() ?? $report['category'],
                'subject_words' => $this->subjectWords((string) $report['subject_type'], (int) $report['subject_id']),
            ];
        }

        return $rows;
    }

    // ---- Closing a report without action ----

    /**
     * Dismiss: the report was judged and nothing was wrong. What the
     * report hid comes back up — a dismissed report must never leave an
     * innocent player's words invisible.
     */
    public function dismiss(User $actor, int $reportId, string $ip): ContentEditResult
    {
        $report = $this->moderation->findReport($reportId);
        if ($report === null || $report['status'] !== 'open') {
            return ContentEditResult::refused('That report was already handled.');
        }

        return $this->withOneBreath(function () use ($actor, $report, $ip): ContentEditResult {
            $this->moderation->closeReport((int) $report['id'], 'dismissed');
            $this->unhide((string) $report['subject_type'], (int) $report['subject_id']);
            $this->audit($actor, AuditAction::ReportDismissed, 'report', (int) $report['id'], $report, null, $ip);

            return ContentEditResult::ok('Dismissed. Anything the report hid is visible again.');
        });
    }

    // ---- Actions on content ----

    /**
     * Remove a creature's bio: cleared (not hidden — gone), the owner
     * told why, the answering report closed as actioned.
     */
    public function removeCreatureBio(User $actor, int $creatureId, ?int $reportId, string $ip): ContentEditResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return ContentEditResult::refused('That creature is gone.');
        }
        $guard = $this->guardTarget($actor, $creature->ownerId);
        if ($guard !== null) {
            return $guard;
        }

        return $this->withOneBreath(function () use ($actor, $creature, $reportId, $ip): ContentEditResult {
            $this->moderation->clearCreatureBio($creature->id);
            $this->notices->send($creature->ownerId, 'bio_removed');
            $this->audit($actor, AuditAction::CreatureTextRemoved, 'creature', $creature->id,
                ['bio' => $creature->bio], null, $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok('The bio is gone, and the owner has been told.');
        });
    }

    /**
     * Remove a player's about text — the profile twin of the above.
     */
    public function removeProfileAbout(User $actor, int $userId, ?int $reportId, string $ip): ContentEditResult
    {
        $profile = $this->profiles->findById($userId);
        if ($profile === null) {
            return ContentEditResult::refused('That player is gone.');
        }
        $guard = $this->guardTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }

        return $this->withOneBreath(function () use ($actor, $profile, $userId, $reportId, $ip): ContentEditResult {
            $this->moderation->clearProfileAbout($userId);
            $this->notices->send($userId, 'about_removed');
            $this->audit($actor, AuditAction::PlayerTextRemoved, 'user', $userId,
                ['about' => $profile->about], null, $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok('The about text is gone, and the player has been told.');
        });
    }

    /**
     * Force a player's name to the neutral default. The skeleton travels
     * with it (see ModerationRepository); the notice says they may pick a
     * new name themselves.
     */
    public function renamePlayer(User $actor, int $userId, ?int $reportId, string $ip): ContentEditResult
    {
        $profile = $this->profiles->findById($userId);
        if ($profile === null) {
            return ContentEditResult::refused('That player is gone.');
        }
        $guard = $this->guardTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }

        // The id makes the neutral name unique without consulting anyone:
        // "Wanderer4821" cannot collide with another forced name, and the
        // impersonation skeleton keeps it from colliding by lookalike.
        $neutralName = $this->rules['neutral_player_prefix'] . $userId;

        return $this->withOneBreath(function () use ($actor, $profile, $userId, $neutralName, $reportId, $ip): ContentEditResult {
            $this->moderation->forceUsername($userId, $neutralName, $this->impersonation->skeletonOf($neutralName));
            $this->notices->send($userId, 'player_renamed');
            $this->audit($actor, AuditAction::PlayerRenamed, 'user', $userId,
                ['username' => $profile->username], ['username' => $neutralName], $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok(sprintf('They are %s now, and they have been told.', $neutralName));
        });
    }

    /**
     * Force a creature's name to the neutral default (its owner gets the
     * notice — creatures take no post).
     */
    public function renameCreature(User $actor, int $creatureId, ?int $reportId, string $ip): ContentEditResult
    {
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return ContentEditResult::refused('That creature is gone.');
        }
        $guard = $this->guardTarget($actor, $creature->ownerId);
        if ($guard !== null) {
            return $guard;
        }

        $neutralName = (string) $this->rules['neutral_creature_name'];

        return $this->withOneBreath(function () use ($actor, $creature, $neutralName, $reportId, $ip): ContentEditResult {
            $this->moderation->forceCreatureName($creature->id, $neutralName);
            $this->notices->send($creature->ownerId, 'creature_renamed');
            $this->audit($actor, AuditAction::CreatureRenamed, 'creature', $creature->id,
                ['name' => $creature->name], ['name' => $neutralName], $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok(sprintf('The creature is called %s now, and the owner has been told.', $neutralName));
        });
    }

    // ---- Actions on the account ----

    /**
     * Warn: a notice and nothing else — the something-between-silence-
     * and-suspension the build plan asks for.
     */
    public function warn(User $actor, int $userId, ?int $reportId, string $ip): ContentEditResult
    {
        $guard = $this->guardKnownTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }

        return $this->withOneBreath(function () use ($actor, $userId, $reportId, $ip): ContentEditResult {
            $this->notices->send($userId, 'warned');
            $this->audit($actor, AuditAction::PlayerWarned, 'user', $userId, null, null, $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok('They will see the note at the top of their next page.');
        });
    }

    /**
     * Mute for one of the allowed durations: no new free text until it
     * expires; everything else plays on. Muting again just moves the date.
     */
    public function mute(User $actor, int $userId, int $days, ?int $reportId, string $ip): ContentEditResult
    {
        return $this->timedState($actor, $userId, $days, $reportId, $ip,
            setUntil: fn (string $until) => $this->moderation->setMutedUntil($userId, $until),
            noticeKind: 'muted',
            action: AuditAction::PlayerMuted,
            said: 'Muted until %s. They have been told, kindly.');
    }

    /**
     * Suspend for one of the allowed durations: no logging in until it
     * expires, and a live session ends at its next request.
     */
    public function suspend(User $actor, int $userId, int $days, ?int $reportId, string $ip): ContentEditResult
    {
        return $this->timedState($actor, $userId, $days, $reportId, $ip,
            setUntil: fn (string $until) => $this->moderation->setSuspendedUntil($userId, $until),
            noticeKind: 'suspended',
            action: AuditAction::PlayerSuspended,
            said: 'Suspended until %s. The notice will greet them when they return.');
    }

    /** Lift a mute early. No notice — good news needs no card. */
    public function unmute(User $actor, int $userId, string $ip): ContentEditResult
    {
        // The same guard as every action, for consistency: staff can never
        // BE muted, so this refuses nothing new — but an asymmetric guard
        // is the kind of thing a security review trips over twice.
        $guard = $this->guardKnownTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }

        return $this->withOneBreath(function () use ($actor, $userId, $ip): ContentEditResult {
            $this->moderation->setMutedUntil($userId, null);
            $this->audit($actor, AuditAction::PlayerUnmuted, 'user', $userId, null, null, $ip);

            return ContentEditResult::ok('The mute is lifted.');
        });
    }

    /** Lift a suspension early. */
    public function unsuspend(User $actor, int $userId, string $ip): ContentEditResult
    {
        $guard = $this->guardKnownTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }

        return $this->withOneBreath(function () use ($actor, $userId, $ip): ContentEditResult {
            $this->moderation->setSuspendedUntil($userId, null);
            $this->audit($actor, AuditAction::PlayerUnsuspended, 'user', $userId, null, null, $ip);

            return ContentEditResult::ok('The suspension is lifted.');
        });
    }

    // ---- The shared machinery ----

    /** Both timed states are the same dance; this is the dance. */
    private function timedState(
        User $actor,
        int $userId,
        int $days,
        ?int $reportId,
        string $ip,
        callable $setUntil,
        string $noticeKind,
        AuditAction $action,
        string $said,
    ): ContentEditResult {
        $guard = $this->guardKnownTarget($actor, $userId);
        if ($guard !== null) {
            return $guard;
        }
        // Durations come from a fixed list (config), not a typed number —
        // there is no "suspend for 3650 days" to fat-finger.
        if (!in_array($days, $this->rules['timeout_days'], true)) {
            return ContentEditResult::refused('That is not one of the offered durations.');
        }

        $until = (new \DateTimeImmutable(sprintf('+%d days', $days)))->format('Y-m-d H:i:s');
        $untilDay = substr($until, 0, 10);

        return $this->withOneBreath(function () use ($actor, $userId, $reportId, $ip, $setUntil, $noticeKind, $action, $said, $until, $untilDay): ContentEditResult {
            $setUntil($until);
            $this->notices->send($userId, $noticeKind, $untilDay);
            $this->audit($actor, $action, 'user', $userId, null, ['until' => $until], $ip);
            $this->closeAsActioned($reportId);

            return ContentEditResult::ok(sprintf($said, $untilDay));
        });
    }

    /**
     * The two guards every action shares — see the class note. Null means
     * "go ahead".
     */
    private function guardTarget(User $actor, int $targetUserId): ?ContentEditResult
    {
        if ($targetUserId === $actor->id) {
            return ContentEditResult::refused('That is your own account — moderation actions need a second person.');
        }
        if ($this->roles->rolesFor($targetUserId) !== []) {
            return ContentEditResult::refused('That account is staff. Staff matters go through the owner and the roles screen.');
        }

        return null;
    }

    /** guardTarget, plus "the player must exist" for account-level actions. */
    private function guardKnownTarget(User $actor, int $targetUserId): ?ContentEditResult
    {
        if ($this->profiles->findById($targetUserId) === null) {
            return ContentEditResult::refused('That player is gone.');
        }

        return $this->guardTarget($actor, $targetUserId);
    }

    /**
     * One transaction around an action's pieces — the change, the notice,
     * the audit row, the report closing — so none can exist without the
     * others (class note).
     */
    private function withOneBreath(callable $action): ContentEditResult
    {
        $this->connection->beginTransaction();
        try {
            $result = $action();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        }
    }

    /** Mark the answering report actioned — quietly; it may be null or raced. */
    private function closeAsActioned(?int $reportId): void
    {
        if ($reportId !== null) {
            $this->moderation->closeReport($reportId, 'actioned');
        }
    }

    /** What the report hid, brought back — the dismiss path's other half. */
    private function unhide(string $subjectType, int $subjectId): void
    {
        match (ReportSubject::tryFrom($subjectType)) {
            ReportSubject::CreatureBio => $this->moderation->unhideCreatureBio($subjectId),
            ReportSubject::ProfileAbout => $this->moderation->unhideProfileAbout($subjectId),
            default => null,
        };
    }

    /**
     * The reported thing itself, as words a queue row can show — staff
     * see hidden text here, because judging it is the job.
     */
    private function subjectWords(string $subjectType, int $subjectId): string
    {
        $words = match (ReportSubject::tryFrom($subjectType)) {
            ReportSubject::CreatureName => $this->creatures->findById($subjectId)?->name,
            ReportSubject::CreatureBio => $this->creatures->findById($subjectId)?->bio,
            ReportSubject::Username => $this->profiles->findById($subjectId)?->username,
            ReportSubject::ProfileAbout => $this->profiles->findById($subjectId)?->about,
            default => null,
        };

        return $words === null || trim($words) === '' ? '(gone now, or empty)' : $words;
    }

    private function audit(User $actor, AuditAction $action, string $subjectType, int $subjectId, ?array $before, ?array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: $action,
            subjectType: $subjectType,
            subjectId: $subjectId,
            before: $before,
            after: $after,
            ip: $ip,
        ));
    }
}
