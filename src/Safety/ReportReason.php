<?php

declare(strict_types=1);

namespace Felkyo\Safety;

/**
 * The fixed list of reasons somebody can give for reporting something.
 *
 * @package Felkyo\Safety
 *
 * WHY THE LIST IS FIXED AND SHORT. Two reasons, and both matter.
 *
 * The first is safety. A free-text "tell us what is wrong" box is a channel — one
 * player writing words that another player (the moderator) reads. This site's
 * whole design is that no such channel exists, and a reporting form is a strange
 * place to quietly open one.
 *
 * The second is triage. A report about a rude creature name and a report about an
 * adult trying to reach a child must not sit in the same undifferentiated queue.
 * With a fixed list, the reporter tells us which it is in one tap, and the queue
 * can put the second one at the top. With free text, somebody has to read all of
 * them to find out — and that somebody has a day job.
 *
 * PRIORITY IS A SMALL NUMBER, LOWEST FIRST. It is stored on the report when it is
 * filed, not looked up later, so retuning these values never silently reorders
 * reports that are already waiting.
 *
 * THE WORDING IS THE FEATURE. These are read by children, often upset ones. They
 * are written as plain descriptions of what happened, never as accusations or
 * jargon, and never requiring the reporter to have decided what rule was broken.
 */
enum ReportReason: string
{
    case AdultContact = 'adult_contact';
    case SexualContent = 'sexual_content';
    case ContactDetails = 'contact_details';
    case HatefulOrThreatening = 'hateful';
    case Bullying = 'bullying';
    case RudeWords = 'rude_words';
    case SpamOrAdvertising = 'spam';

    /**
     * What the reporter taps. Written for a worried eleven-year-old, not for a
     * moderator: "someone is asking me to talk somewhere else" is a thing a child
     * can recognise, where "grooming behaviour" is not.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdultContact => 'Someone is asking me to talk somewhere else, or asking about me',
            self::SexualContent => 'Something here is sexual',
            self::ContactDetails => 'There’s a link, or a way to contact someone off Felkyo',
            self::HatefulOrThreatening => 'This is hateful or threatening',
            self::Bullying => 'Someone is being unkind to me or to someone else',
            self::RudeWords => 'There’s a rude word here',
            self::SpamOrAdvertising => 'This is spam or advertising',
        };
    }

    /**
     * How urgently a human is needed. 1 is "wake somebody up".
     *
     * The top two are the ones where a delay can mean real harm to a child. The
     * bottom two are unpleasant but wait comfortably until morning. That ordering
     * is the entire point of asking the question.
     */
    public function priority(): int
    {
        return match ($this) {
            self::AdultContact => 1,
            self::SexualContent => 1,
            self::ContactDetails => 2,
            self::HatefulOrThreatening => 2,
            self::Bullying => 3,
            self::RudeWords => 4,
            self::SpamOrAdvertising => 4,
        };
    }

    /**
     * Is this one of the reasons that should reach a human quickly, rather than
     * waiting for the next time somebody opens the queue?
     *
     * Nothing acts on this yet — there is no alerting until M2.7 — but the
     * distinction is recorded now so that when alerting is built, the question of
     * "which ones" is already answered and not decided in a hurry.
     */
    public function needsSomebodyNow(): bool
    {
        return $this->priority() === 1;
    }

    /**
     * Every reason, in the order they should be offered.
     *
     * Most serious first. That is deliberate: somebody frightened should not have
     * to read past "there's a rude word" to find the one that describes what is
     * happening to them.
     *
     * @return array<int, self>
     */
    public static function inOfferedOrder(): array
    {
        return [
            self::AdultContact,
            self::SexualContent,
            self::ContactDetails,
            self::HatefulOrThreatening,
            self::Bullying,
            self::RudeWords,
            self::SpamOrAdvertising,
        ];
    }

    public static function fromFormValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
