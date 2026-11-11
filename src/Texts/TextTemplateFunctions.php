<?php

declare(strict_types=1);

namespace Felkyo\Texts;

use Closure;
use League\Plates\Engine;

/**
 * Registers the three template functions for the site's words:
 *
 *   $this->t('key', 'Original words', ['name' => $x])   a sentence, as safe HTML
 *   $this->ta('key', 'Original words')                  the same, for INSIDE an
 *                                                       attribute (never wrapped)
 *   $this->message($text)                               a message the code sent
 *
 * @package Felkyo\Texts
 *
 * One class so the site (public/index.php) and the tests register exactly
 * the same functions — a test that rendered pages with a different t()
 * would prove nothing about the real one.
 *
 * EDITING MODE: when $isEditing() says yes (artist or owner who switched it
 * on), t() and message() wrap their words in a focusable span carrying the
 * key, the current words and the original, which text-editor.js turns into
 * the click-to-edit popup. Everything inside is still escaped.
 */
final class TextTemplateFunctions
{
    /** @param Closure(): bool $isEditing asked on every call, so it can be decided after registering */
    public static function register(Engine $engine, TextCatalogue $catalogue, ?TextScanner $scanner = null, ?Closure $isEditing = null): void
    {
        $isEditing ??= static fn (): bool => false;

        $engine->registerFunction('t', static function (string $key, string $original, array $values = []) use ($catalogue, $isEditing): string {
            $words = $catalogue->words($key, $original);
            $html = TextCatalogue::toHtml($words, $values);

            return $isEditing() ? self::editable($key, $words, $original, $html) : $html;
        });

        $engine->registerFunction('ta', static function (string $key, string $original, array $values = []) use ($catalogue): string {
            $words = $catalogue->words($key, $original);
            foreach ($values as $name => $value) {
                $words = str_replace('{' . $name . '}', (string) $value, $words);
            }

            // No bold or slanted inside an attribute: the stars are dropped.
            return htmlspecialchars(str_replace('*', '', $words), ENT_QUOTES, 'UTF-8');
        });

        $engine->registerFunction('message', static function (string $shown) use ($catalogue, $scanner, $isEditing): string {
            $html = htmlspecialchars($catalogue->message($shown), ENT_QUOTES, 'UTF-8');
            if (!$isEditing() || $scanner === null) {
                return $html;
            }
            // Editing mode only (staff): find which pattern this message came
            // from, so it can be clicked and changed like any other sentence.
            foreach ($scanner->messages() as $key => $message) {
                if (TextCatalogue::valuesIn($message['original'], $shown) !== null) {
                    $original = TextScanner::friendly($message['original']);

                    return self::editable($key, $catalogue->words($key, $original), $original, $html);
                }
            }

            return $html;
        });
    }

    /** The click-to-edit wrapper: every attribute value escaped. */
    private static function editable(string $key, string $words, string $original, string $html): string
    {
        $attribute = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return '<span class="text-edit" tabindex="0" data-text-key="' . $attribute($key)
            . '" data-text-words="' . $attribute($words) . '" data-text-original="' . $attribute($original) . '">'
            . $html . '</span>';
    }
}
