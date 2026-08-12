<?php

declare(strict_types=1);

namespace Felkyo\Safety;

use Felkyo\Creatures\CreatureRepository;
use Felkyo\Users\ProfileRepository;

/**
 * The rules for reporting something, and what happens next.
 *
 * @package Felkyo\Safety
 *
 * THE THREE SECURITY QUESTIONS (CLAUDE.md section 6), answered before this was
 * written, because reporting touches user data and can hide other people's words:
 *
 * 1. WHO IS ALLOWED, AND HOW IS IT ENFORCED? Any logged-in player may report
 *    anything they can see. Logged-out visitors cannot, because a report from
 *    nobody cannot be judged, followed up, or counted — and an anonymous report
 *    button is a button anybody can press a thousand times. The check is the
 *    session, in the controller.
 *
 * 2. WHAT IS THE WORST A MALICIOUS PLAYER COULD DO?
 *    - Report the same thing repeatedly to bury the queue. Closed by the unique
 *      index: one report per person per thing, enforced by the database.
 *    - Hide an innocent player's bio out of spite, since reporting hides it.
 *      THIS IS REAL AND IS NOT FULLY CLOSED. It is a deliberate trade: the
 *      alternative is harmful text in public all night with nobody awake. What
 *      limits it is that hiding needs a DIFFERENT person each time, every report
 *      is stored with its reporter, and M2.7's queue must surface people whose
 *      reports are always dismissed. That last part is a requirement of M2.7, not
 *      an optional extra — without it this trade goes bad.
 *    - Report something that does not exist, to make the queue point at nothing.
 *      Closed by looking the subject up and refusing if it is not there.
 *    - Send a subject type or reason we never offered. Closed by both being
 *      enums, so an unknown value simply is not one.
 *    - Learn things by reporting: whether an id exists, who owns what. Closed by
 *      giving the same answer whatever happens.
 *
 * 3. WHAT DOES THE TEST SUITE PROVE? ReportServiceTest proves each: a repeat
 *    report is not stored twice, an unknown subject is refused, a bio hides and a
 *    name does not, an invented reason is refused, and the reporter is thanked
 *    identically whether or not anything was recorded.
 *
 * THE ACKNOWLEDGEMENT IS NOT POLITENESS. Silence teaches people not to report,
 * and reporting is the main safety mechanism on this site. So every report ends
 * with the site saying, plainly, that it arrived and a person will look.
 */
final class ReportService
{
    public function __construct(
        private ReportRepository $reports,
        private CreatureRepository $creatures,
        private ProfileRepository $profiles,
    ) {
    }

    /**
     * File a report about something.
     *
     * Returns the same reassuring message whether the report was newly stored, or
     * was one this person had already made. Telling them apart would serve nobody
     * and would confirm to a curious person what is already in the database.
     */
    public function report(int $reporterUserId, ReportSubject $subject, int $subjectId, ReportReason $reason): ReportResult
    {
        $aboutUserId = $this->whoIsThisAbout($subject, $subjectId);

        if ($aboutUserId === null) {
            return ReportResult::refused('That doesn’t seem to be here any more.');
        }

        // Reporting yourself is almost always a misunderstanding of what the
        // button does, so it is refused kindly rather than filed.
        if ($aboutUserId === $reporterUserId) {
            return ReportResult::refused(
                'That’s yours — you can change it yourself instead. If you need help, use the Felkyo email.'
            );
        }

        $this->reports->file($reporterUserId, $subject, $subjectId, $aboutUserId, $reason);

        // Hiding happens whether or not this particular report was new: if a
        // second person reports something already hidden, nothing changes, and if
        // the first report somehow failed to hide it, this puts that right.
        if ($subject->hidesUntilReviewed()) {
            $this->hide($subject, $subjectId);
        }

        return ReportResult::received($this->thankYouFor($subject));
    }

    /**
     * Whose account is this thing attached to — or null if it is not there.
     *
     * Every kind has to be answerable here, which is the point: a report has to
     * be pinned to an account, because judging reports one at a time is exactly
     * how a repeat offender stays under the line.
     */
    private function whoIsThisAbout(ReportSubject $subject, int $subjectId): ?int
    {
        return match ($subject) {
            ReportSubject::CreatureName, ReportSubject::CreatureBio =>
                $this->creatures->findById($subjectId)?->ownerId,
            ReportSubject::Username, ReportSubject::ProfileAbout =>
                $this->profiles->findById($subjectId)?->id,
            // A guestbook entry is reported by its id; who it is about is the
            // person who signed it. That lookup arrives with the guestbook
            // reporting in M6.6, so for now this kind is not offered anywhere.
            ReportSubject::GuestbookEntry => null,
        };
    }

    private function hide(ReportSubject $subject, int $subjectId): void
    {
        match ($subject) {
            ReportSubject::CreatureBio => $this->reports->hideCreatureBio($subjectId),
            ReportSubject::ProfileAbout => $this->reports->hideProfileAbout($subjectId),
            default => null,
        };
    }

    /**
     * What the site says back. It promises only what is actually true: that it
     * arrived, and that a person will read it.
     */
    private function thankYouFor(ReportSubject $subject): string
    {
        $hidden = $subject->hidesUntilReviewed()
            ? ' It’s been hidden while we look.'
            : '';

        return 'Thank you — we’ve received your report about ' . $subject->label() . '.'
            . $hidden
            . ' Someone will read it. If you’re worried about your safety, please tell a grown-up you trust.';
    }
}
