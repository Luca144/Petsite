<?php

declare(strict_types=1);

namespace Felkyo\Look;

/**
 * The site-wide look choices made in the panel (/admin/look): which theme
 * everyone sees by default, the order of the sections on creature and
 * player pages, an announcement line, and the live fine-tuning CSS.
 *
 * @package Felkyo\Look
 *
 * WHERE IT LIVES: rows in the site_settings table under "look." keys —
 * the table the front controller already reads once per request, so the
 * whole look costs no extra query. Missing rows mean "the site's own".
 *
 * HOW TO ADD A SECTION TO A PAGE'S ORDER: add it to the list below, then
 * wrap that part of the page template in a named section with the same key
 * (see templates/pages/creature.php). A section missing from a saved order
 * is simply added at the end, so a new section can never vanish.
 */
final class SiteLook
{
    public const KEY_DEFAULT_THEME = 'look.default_theme_id';
    public const KEY_ANNOUNCEMENT = 'look.announcement';
    public const KEY_CUSTOM_CSS = 'look.custom_css';
    public const KEY_ORDER_PREFIX = 'look.sections.';

    /** page => [section key => what the panel calls it], in the site's own order */
    public const SECTIONS = [
        'creature' => [
            'sheet' => 'The creature (picture, petting, feeding)',
            'share' => 'Share box',
            'character' => 'Traits, title and badges',
            'about' => 'About them (their bio)',
            'guestbook' => 'Guestbook',
            'keeping' => 'Keeping (only the owner sees it)',
        ],
        'profile' => [
            'about' => 'About the player',
            'creatures' => 'Their creatures',
            'company' => 'Mantelpiece, badges and friends',
        ],
    ];

    /** What the panel calls each page. */
    public const PAGE_NAMES = ['creature' => 'Creature pages', 'profile' => 'Player pages'];

    /** @param array<string, string[]> $orders page => section keys in order */
    public function __construct(
        public readonly ?int $defaultThemeId = null,
        public readonly array $orders = [],
        public readonly string $announcement = '',
        public readonly string $customCss = '',
    ) {
    }

    /** Build from the site_settings overrides (already decoded). @param array<string, mixed> $settings */
    public static function fromSettings(array $settings): self
    {
        $orders = [];
        foreach (array_keys(self::SECTIONS) as $page) {
            $stored = $settings[self::KEY_ORDER_PREFIX . $page] ?? [];
            $orders[$page] = self::completeOrder($page, is_array($stored) ? $stored : []);
        }
        $themeId = $settings[self::KEY_DEFAULT_THEME] ?? null;
        $css = $settings[self::KEY_CUSTOM_CSS] ?? '';

        return new self(
            is_int($themeId) && $themeId > 0 ? $themeId : null,
            $orders,
            is_string($settings[self::KEY_ANNOUNCEMENT] ?? null) ? $settings[self::KEY_ANNOUNCEMENT] : '',
            // Checked again on the way out: never print CSS that fails the rules.
            is_string($css) && CustomCssRules::problemWith($css) === null ? $css : '',
        );
    }

    /** The section keys of a page, in the chosen order. @return string[] */
    public function orderOf(string $page): array
    {
        return $this->orders[$page] ?? array_keys(self::SECTIONS[$page] ?? []);
    }

    /**
     * Known keys from the stored order first (in that order, unknown or
     * repeated ones dropped), then any known key the stored order lacks.
     *
     * @param mixed[] $stored
     * @return string[]
     */
    public static function completeOrder(string $page, array $stored): array
    {
        $known = array_keys(self::SECTIONS[$page] ?? []);
        $order = array_values(array_unique(array_filter(
            $stored,
            static fn ($key): bool => is_string($key) && in_array($key, $known, true)
        )));

        return array_merge($order, array_values(array_diff($known, $order)));
    }

    /**
     * The order with one section swapped one place up (-1) or down (+1).
     * Moving past either end changes nothing.
     *
     * @param string[] $order
     * @return string[]
     */
    public static function moved(array $order, string $key, int $direction): array
    {
        $from = array_search($key, $order, true);
        $to = $from === false ? false : $from + ($direction < 0 ? -1 : 1);
        if ($from === false || $to < 0 || $to >= count($order)) {
            return $order;
        }
        [$order[$from], $order[$to]] = [$order[$to], $order[$from]];

        return $order;
    }
}
