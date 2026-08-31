<?php

declare(strict_types=1);

namespace Felkyo\Creatures;

/**
 * The word-rules of the species editor: names, text lengths, slugs.
 *
 * @package Felkyo\Creatures
 *
 * Split out of SpeciesEditorService so the service reads as its seven
 * actions and this file as the small dictionary of what words are allowed.
 * Every refusal is a plain sentence ready to show the artist.
 */
final class SpeciesWording
{
    /**
     * @param array $speciesConfig the species_* length caps (config 'art').
     */
    public function __construct(private array $speciesConfig)
    {
    }

    /**
     * A problem with a proposed name, or null if it is fine.
     */
    public function nameProblem(string $name): ?string
    {
        if ($name === '') {
            return 'Every species needs a name.';
        }
        if (mb_strlen($name) > (int) $this->speciesConfig['species_name_max_length']) {
            return sprintf('That name is too long — %d characters is the most.', $this->speciesConfig['species_name_max_length']);
        }

        return null;
    }

    /**
     * Trimmed text, null when empty — or a refusal sentence when over cap.
     * The caller reads a SpeciesEditResult as "refuse with this".
     *
     * @return string|null|SpeciesEditResult
     */
    public function textOrNull(string $text, int $maxLength, string $whatItIs)
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (mb_strlen($text) > $maxLength) {
            return SpeciesEditResult::refused(sprintf('The %s is too long — %d characters is the most.', $whatItIs, $maxLength));
        }

        return $text;
    }

    public function blurbCap(): int
    {
        return (int) $this->speciesConfig['species_blurb_max_length'];
    }

    public function loreCap(): int
    {
        return (int) $this->speciesConfig['species_lore_max_length'];
    }

    /**
     * "Mossy Wumple" → "mossy-wumple": lowercase letters, digits, dashes —
     * the shape a slug must have because it appears in URLs and the legacy
     * art-path convention.
     */
    public function slugFrom(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim(substr($slug, 0, 50), '-');
    }
}
