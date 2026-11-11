<?php

declare(strict_types=1);

namespace Felkyo\Texts;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Users\User;

/**
 * Changing one of the site's sentences, or putting the original back.
 *
 * @package Felkyo\Texts
 *
 * WHAT IS CHECKED:
 *   - the key must be a sentence that really exists (found by TextScanner),
 *     so nobody can fill the table with made-up keys;
 *   - at most MAX_LENGTH characters;
 *   - {gaps} may be moved or left out, but not invented — an unknown {gap}
 *     would show up on the page as it is, braces and all;
 *   - empty words, or words equal to the original, mean "the original".
 *
 * Who may (artist role; owner passes) is the AdminGate's job. Every change
 * goes in the audit log with the words before and after.
 */
final class TextEditorService
{
    public const MAX_LENGTH = 1000;

    public function __construct(
        private SiteTextRepository $texts,
        private TextCatalogue $catalogue,
        private TextScanner $scanner,
        private AuditLogRepository $auditLog,
    ) {
    }

    public function save(User $actor, string $key, string $words, string $ip): ContentEditResult
    {
        $source = $this->originalOf($key);
        if ($source === null) {
            return ContentEditResult::refused('That sentence isn\'t one we know — it may have been removed from the site.');
        }
        [$original, $storedOriginal] = $source;

        $words = trim(str_replace("\r\n", "\n", $words));
        if (mb_strlen($words) > self::MAX_LENGTH) {
            return ContentEditResult::refused(sprintf('Please keep it to %d characters.', self::MAX_LENGTH));
        }
        $unknownGaps = array_diff(self::gaps($words), self::gaps($original));
        if ($unknownGaps !== []) {
            return ContentEditResult::refused(sprintf(
                'The gap %s isn\'t one this sentence has. You can use: %s.',
                '{' . reset($unknownGaps) . '}',
                self::gaps($original) === [] ? 'none here' : '{' . implode('}, {', self::gaps($original)) . '}'
            ));
        }

        $before = $this->catalogue->words($key, $original);
        if ($words === '' || $words === $original) {
            $this->texts->reset($key);
            $this->audit($actor, $key, $before, $original, $ip);

            return ContentEditResult::ok('Back to the original words.');
        }
        $this->texts->set($key, $words, $storedOriginal, $actor->id);
        $this->audit($actor, $key, $before, $words, $ip);

        return ContentEditResult::ok('Saved — everyone sees the new words now.');
    }

    /**
     * The original words for a key, as the artist sees them, and the raw
     * pattern to store with a message (null for template texts).
     *
     * @return array{0: string, 1: ?string}|null
     */
    public function originalOf(string $key): ?array
    {
        if (str_starts_with($key, 'message.')) {
            $pattern = $this->scanner->messages()[$key]['original'] ?? null;

            return $pattern === null ? null : [TextScanner::friendly($pattern), $pattern];
        }
        $default = $this->scanner->templateTexts()[$key]['default'] ?? null;

        return $default === null ? null : [$default, null];
    }

    /** The {gap} names in some words. @return string[] */
    private static function gaps(string $words): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)\}/', $words, $found);

        return array_values(array_unique($found[1]));
    }

    private function audit(User $actor, string $key, string $before, string $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id, action: AuditAction::TextSaved, subjectType: 'text',
            before: ['key' => $key, 'words' => $before], after: ['words' => $after], ip: $ip,
        ));
    }
}
