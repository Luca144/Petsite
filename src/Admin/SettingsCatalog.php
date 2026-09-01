<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * The fixed list of settings the panel may change (M2.8).
 *
 * @package Felkyo\Admin
 *
 * THIS ALLOW-LIST IS THE SECURITY BOUNDARY: only what is named here is
 * ever editable, readable from the overrides table, or applied to
 * config. There is deliberately no way to edit an arbitrary config key —
 * a "config editor" is a slower query console, and the build plan's rule
 * against those is absolute.
 *
 * Each entry says where the value lives in config (its path), what type
 * it is, its bounds, and — because the screen shows these words — what
 * changing it actually does, in plain language. TO MAKE ANOTHER SETTING
 * EDITABLE: add an entry here and nothing else; the screen, validation,
 * apply-on-boot and audit all follow from the catalog.
 *
 * Bounds are enforced BOTH at save and at read (apply()): a value
 * planted straight into the database that fails validation is ignored,
 * so the table cannot smuggle what the form refuses.
 */
final class SettingsCatalog
{
    /**
     * type 'toggle' — on/off; 'number' — whole number in [min, max];
     * 'words' — a list, one per line, at least min_items entries.
     */
    private const CATALOG = [
        'registration_open' => [
            'label' => 'Registration',
            'type' => 'toggle',
            'path' => ['app', 'registration_open'],
            'explanation' => 'Whether new players can sign up at all. The .env flag sets the default; a change here wins over it.',
        ],
        'pet_cooldown_seconds' => [
            'label' => 'Petting cooldown (seconds)',
            'type' => 'number',
            'min' => 5,
            'max' => 86400,
            'path' => ['gameplay', 'petting', 'cooldown_seconds'],
            'explanation' => 'How long the same person waits before petting the same creature again. Short = lively, long = calm.',
        ],
        'xp_per_pet' => [
            'label' => 'XP per pet',
            'type' => 'number',
            'min' => 1,
            'max' => 200,
            'path' => ['gameplay', 'petting', 'xp_per_pet'],
            'explanation' => 'How much growing one pet is worth.',
        ],
        'xp_per_level' => [
            'label' => 'XP needed per level',
            'type' => 'number',
            'min' => 5,
            'max' => 1000,
            'path' => ['gameplay', 'growth', 'xp_per_level'],
            'explanation' => 'Careful: levels are worked out from XP on every page view, so changing this changes every creature\'s level and stage immediately - nothing is lost, and putting it back puts them back.',
        ],
        'gems_per_pet' => [
            'label' => 'Gems per pet',
            'type' => 'number',
            'min' => 0,
            'max' => 100,
            'path' => ['gameplay', 'currency', 'per_pet'],
            'explanation' => 'What petting somebody else\'s creature pays the visitor. Zero switches the reward off.',
        ],
        'gems_daily_cap' => [
            'label' => 'Daily gem cap',
            'type' => 'number',
            'min' => 1,
            'max' => 100000,
            'path' => ['gameplay', 'currency', 'daily_cap'],
            'explanation' => 'The most one account can earn from petting in a rolling day - the alt-account farming ceiling. Honest players never notice it; farmers hit it fast.',
        ],
        'explore_clicks_per_visit' => [
            'label' => 'Searches per exploration visit',
            'type' => 'number',
            'min' => 1,
            'max' => 50,
            'path' => ['gameplay', 'exploration', 'clicks_per_visit'],
            'explanation' => 'How many spots a player may search per area before the allowance refreshes.',
        ],
        'blocked_words' => [
            'label' => 'The word blocklist',
            'type' => 'words',
            'min_items' => 1,
            'path' => ['moderation', 'blocked_words'],
            'explanation' => 'Words refused in every name, bio and about text - one per line, matched inside words too. It can grow and shrink here, but never be emptied: an empty list would switch the filter off everywhere at once.',
        ],
    ];

    /** @return array<string, array<string, mixed>> the whole catalog, for the screen. */
    public function all(): array
    {
        return self::CATALOG;
    }

    public function has(string $key): bool
    {
        return isset(self::CATALOG[$key]);
    }

    /** The current value of one entry inside a config array. */
    public function currentFrom(string $key, array $config): mixed
    {
        $value = $config;
        foreach (self::CATALOG[$key]['path'] as $step) {
            $value = $value[$step] ?? null;
        }

        return $value;
    }

    /**
     * Turn one submitted raw value into a typed one — or say what is
     * wrong, in the words the screen shows.
     *
     * @return array{value?: mixed, error?: string}
     */
    public function validate(string $key, string $raw): array
    {
        $entry = self::CATALOG[$key];

        return match ($entry['type']) {
            'toggle' => ['value' => $raw === 'on'],
            'number' => $this->validateNumber($entry, trim($raw)),
            'words' => $this->validateWords($entry, $raw),
        };
    }

    /**
     * Lay the stored overrides over a config array. Every value passes
     * the same validation as the form — an override that fails (a
     * hand-planted row, or bounds tightened since it was saved) is
     * IGNORED, so the built-in value quietly holds (class note).
     *
     * @param array<string, mixed> $overrides catalog key → decoded value
     */
    public function apply(array $config, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (!$this->has($key) || !$this->storedValueIsSane($key, $value)) {
                continue;
            }

            // Walk the path by reference and set the leaf.
            $slot = &$config;
            foreach (self::CATALOG[$key]['path'] as $step) {
                $slot = &$slot[$step];
            }
            $slot = $value;
            unset($slot);
        }

        return $config;
    }

    /** @return array{value?: int, error?: string} */
    private function validateNumber(array $entry, string $raw): array
    {
        if (!preg_match('/^\d+$/', $raw)) {
            return ['error' => 'That needs to be a whole number.'];
        }
        $number = (int) $raw;
        if ($number < $entry['min'] || $number > $entry['max']) {
            return ['error' => sprintf('That one goes from %d to %d.', $entry['min'], $entry['max'])];
        }

        return ['value' => $number];
    }

    /** @return array{value?: string[], error?: string} */
    private function validateWords(array $entry, string $raw): array
    {
        $words = [];
        foreach (explode("\n", $raw) as $line) {
            $word = mb_strtolower(trim($line));
            if ($word !== '' && !in_array($word, $words, true)) {
                $words[] = $word;
            }
        }
        if (count($words) < $entry['min_items']) {
            return ['error' => 'The blocklist can shrink, but never be emptied — it guards every name and bio at once.'];
        }

        return ['value' => $words];
    }

    /** Is a value from the DATABASE still within this entry's rules? */
    private function storedValueIsSane(string $key, mixed $value): bool
    {
        $entry = self::CATALOG[$key];

        return match ($entry['type']) {
            'toggle' => is_bool($value),
            'number' => is_int($value) && $value >= $entry['min'] && $value <= $entry['max'],
            'words' => is_array($value)
                && count($value) >= $entry['min_items']
                && $value === array_values(array_filter($value, 'is_string')),
        };
    }
}
