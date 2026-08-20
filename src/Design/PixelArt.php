<?php

declare(strict_types=1);

namespace Felkyo\Design;

/**
 * Works out how big a pixel-art sprite may be drawn without going mushy.
 *
 * @package Felkyo\Design
 *
 * THE PROBLEM THIS SOLVES. The creature sprites arrive at whatever size the
 * artist drew them — 34×28, 52×42, 101×79 — and the templates used to squeeze
 * every one into a fixed 96×96 box with object-fit. That scales a sprite by
 * whatever fraction happens to fit (2.8×, 1.8×, 0.95×), and pixel art scaled by
 * a NON-INTEGER factor smears: some source pixels become 3 screen pixels wide
 * and their neighbours 2, so lines wobble and the crispness dies. The Product
 * Owner spotted it immediately ("are they ×2 or just 'stretch to fit'?").
 * `image-rendering: pixelated` cannot save a fractional scale — it only chooses
 * HOW to interpolate, not how much.
 *
 * THE RULE: a sprite is only ever drawn at a WHOLE-NUMBER multiple of its native
 * size — the largest one that fits the box it is given, and never less than 1×.
 * The box itself stays fixed (cards keep their rhythm); the sprite sits centred
 * inside it with honest, even pixels.
 *
 * Sizes are read from the real files and cached for the request, so a page of
 * twelve cards stats each distinct sprite once.
 */
final class PixelArt
{
    /** @var array<string, array{int, int}> path => native [width, height] */
    private static array $nativeSizes = [];

    /**
     * The display size for a sprite inside a square box: the largest integer
     * multiple of its native size that fits, as [width, height].
     *
     * A sprite LARGER than its box gets 1× and overflows gently rather than
     * being shrunk by a fraction (the wrapper crops it); a missing file gets the
     * box itself, so a broken path degrades to exactly the old behaviour.
     *
     * @return array{int, int}
     */
    public static function displaySize(string $publicPath, int $box): array
    {
        [$width, $height] = self::nativeSize($publicPath);

        if ($width < 1 || $height < 1) {
            return [$box, $box];
        }

        $factor = max(1, intdiv($box, max($width, $height)));

        return [$width * $factor, $height * $factor];
    }

    /**
     * @return array{int, int}
     */
    private static function nativeSize(string $publicPath): array
    {
        if (!isset(self::$nativeSizes[$publicPath])) {
            $file = dirname(__DIR__, 2) . '/public' . $publicPath;
            $info = is_file($file) ? @getimagesize($file) : false;
            self::$nativeSizes[$publicPath] = $info === false
                ? [0, 0]
                : [(int) $info[0], (int) $info[1]];
        }

        return self::$nativeSizes[$publicPath];
    }
}
