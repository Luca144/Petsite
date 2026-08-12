<?php

declare(strict_types=1);

namespace Felkyo\Http;

/**
 * Adds a version number to a stylesheet or script address.
 *
 * @package Felkyo\Http
 *
 * WHAT IT DOES: turns "/css/theme.css" into "/css/theme.css?v=1760284800", where
 * the number is when that file was last saved.
 *
 * WHY IT EXISTS — this fixed a real bug, and the bug is worth understanding
 * because it will happen again to anybody who does not know about it.
 *
 * A browser remembers a stylesheet by its address. When a stylesheet CHANGES, the
 * address stays the same, so returning visitors keep using the copy they already
 * have — sometimes for days. Brand-new stylesheets are fetched immediately, and
 * that is what makes this so confusing to diagnose: half the page picks up the new
 * design and half of it does not, seemingly at random.
 *
 * It is also nearly invisible to whoever wrote the change, because their browser
 * was reloading while they worked. The first person to see it is a player.
 *
 * Putting the file's modification time in the address means a changed file has a
 * NEW address, so every browser fetches it exactly once and then caches it happily
 * until the next change. Nobody has to remember to do anything.
 *
 * WHY IT IS A STATIC METHOD, not one of the template helpers registered in
 * public/index.php: fourteen test files build their own template engine, and a
 * helper each of them has to remember to register is a helper that will be
 * missing from the fifteenth. A plain function called directly from the template
 * cannot be forgotten.
 */
final class AssetUrl
{
    /**
     * The public folder, which is where every asset address is relative to.
     */
    private const PUBLIC_DIRECTORY = __DIR__ . '/../../public';

    /**
     * The versioned address for a file, ready to put in an href.
     *
     * The result is escaped here rather than by the caller, because it goes
     * straight into an attribute and being sure is cheaper than remembering.
     *
     * A file that cannot be found gets version "0" instead of an error: a missing
     * stylesheet is already visible as an unstyled page, and throwing here would
     * turn one broken stylesheet into a blank site.
     */
    public static function versioned(string $path): string
    {
        $file = self::PUBLIC_DIRECTORY . $path;
        $version = is_file($file) ? (string) filemtime($file) : '0';

        return htmlspecialchars($path . '?v=' . $version, ENT_QUOTES, 'UTF-8');
    }
}
