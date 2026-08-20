<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * The four staff roles, and what each one is for.
 *
 * @package Felkyo\Admin
 *
 * WHY AN ENUM AND NOT A STRING. A role name arrives from a form (the owner's
 * role screen) and from a command line (bin/grant-role.php). If it were a plain
 * string, a request could invent a fifth role and every later check would have
 * to guess what it means. An enum means only these four values can ever exist
 * in the code, and a test (SchemaTest) asserts the database contains nothing
 * else either.
 *
 * ROLES ARE ADDITIVE: one person can hold several (the artist can also be an
 * owner). Holding a role grants its own screens and nothing else — except
 * OWNER, which passes every check, because the build plan defines owner as
 * "everything, including security settings and role assignment".
 *
 * HOW TO ADD A ROLE: add a case here, a label and description below, and the
 * matching tiles in AdminHomeController. The database column is plain text, so
 * no migration is needed — the allow-list here IS the rule.
 */
enum Role: string
{
    case Owner = 'owner';
    case Moderator = 'moderator';
    case Artist = 'artist';
    case Coder = 'coder';

    /**
     * What a person sees this role called, e.g. on the roles screen.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Moderator => 'Moderator',
            self::Artist => 'Artist',
            self::Coder => 'Coder',
        };
    }

    /**
     * One plain sentence saying what holding this role means — shown beside
     * the role wherever it can be granted, so the person granting it knows
     * exactly what they are handing over (golden rule 5: plain words).
     */
    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Everything — including security settings and giving out these roles.',
            self::Moderator => 'The report queue, account history, notices and moderation actions.',
            self::Artist => 'Content: creatures, items, shops, cards, themes and the library.',
            self::Coder => 'Technical settings and maintenance actions.',
        };
    }

    /**
     * Turn a value from a form (or the command line) into a role, or null if
     * it is not one of ours. Returns null rather than throwing: an unknown
     * value means somebody sent something odd, which is a request to refuse
     * politely, not an error worth a stack trace.
     */
    public static function fromFormValue(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
