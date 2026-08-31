<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * Reads a PNG chunk by chunk — to measure it, to tell a still from an
 * animated one (APNG), and to produce metadata-free bytes to store.
 *
 * @package Felkyo\Art
 *
 * A PNG is an 8-byte signature followed by CHUNKS, each: a 4-byte big-endian
 * length, a 4-letter type, the data, and a 4-byte checksum. The first chunk
 * is IHDR (width, height, colour details) and the last is IEND. An animated
 * PNG adds an acTL chunk near the start and carries its frames in fcTL/fdAT
 * chunks — a plain viewer just shows the first frame; a browser plays them.
 *
 * TWO PATHS, one rule: metadata goes, pixels stay.
 *  - STILL: decoded and re-encoded through GD. The output is a clean PNG
 *    with only the chunks GD writes — no text, no timestamps, no EXIF. The
 *    pixels are identical (a test proves it); only the compression differs.
 *  - ANIMATED: never decoded — GD would keep one frame and lose the rest,
 *    which CLAUDE.md forbids. Instead the text/time/EXIF chunks are dropped
 *    during the walk and everything else is copied byte-for-byte.
 */
final class PngFile
{
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    // Chunks that carry words about the picture rather than the picture.
    // Dropped from animated files (still ones are re-encoded, which drops
    // them anyway).
    private const METADATA_CHUNKS = ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'];

    /**
     * @return array{width: int, height: int, animated: bool, stripped: string}
     * @throws ImageRefused if the bytes are not a well-formed PNG.
     */
    public function read(string $bytes): array
    {
        if (strlen($bytes) < 33 || substr($bytes, 0, 8) !== self::SIGNATURE) {
            throw new ImageRefused('That file isn\'t a PNG picture.');
        }

        // Walk every chunk once: measure from IHDR, notice acTL, and collect
        // the chunks an animated file would keep.
        $offset = 8;
        $width = 0;
        $height = 0;
        $animated = false;
        $kept = self::SIGNATURE;
        $sawEnd = false;

        while ($offset + 12 <= strlen($bytes)) {
            $dataLength = unpack('N', substr($bytes, $offset, 4))[1];
            $type = substr($bytes, $offset + 4, 4);
            $chunkLength = 12 + $dataLength;

            if ($offset + $chunkLength > strlen($bytes)) {
                throw new ImageRefused('That PNG is cut short — the file ends in the middle of the picture.');
            }

            if ($type === 'IHDR') {
                $width = unpack('N', substr($bytes, $offset + 8, 4))[1];
                $height = unpack('N', substr($bytes, $offset + 12, 4))[1];
            } elseif ($type === 'acTL') {
                $animated = true;
            }

            if (!in_array($type, self::METADATA_CHUNKS, true)) {
                $kept .= substr($bytes, $offset, $chunkLength);
            }

            $offset += $chunkLength;

            if ($type === 'IEND') {
                $sawEnd = true;
                break; // anything after IEND is not part of the picture
            }
        }

        if (!$sawEnd || $width === 0 || $height === 0) {
            throw new ImageRefused('That PNG is damaged — it has no proper beginning or end.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'animated' => $animated,
            // The chunk-stripped copy, for BOTH kinds. For a still PNG the
            // validator goes one step further and calls reencodeStill() —
            // but only AFTER it has checked the dimensions, because that
            // step decodes the pixels and a forged header could otherwise
            // make it allocate a mountain. Order matters; see ImageValidator.
            'stripped' => $kept,
        ];
    }

    /**
     * Decode and re-encode a still PNG through GD, keeping transparency. GD
     * writes only the chunks a picture needs, so every text/time/EXIF chunk
     * is gone — and the pixels come out exactly as they went in.
     *
     * NEVER call this on an animated PNG (GD keeps one frame) and never
     * before the dimensions have been checked against the cap (this is the
     * one step that allocates memory for the pixels).
     */
    public function reencodeStill(string $bytes): string
    {
        // @: GD reports a corrupt file with a warning; we want a refusal.
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ImageRefused('That PNG couldn\'t be read — it may be damaged.');
        }

        // Keep the alpha channel instead of flattening it onto black. For a
        // palette image GD keeps the transparent index by itself.
        if (imageistruecolor($image)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        ob_start();
        $written = imagepng($image);
        $clean = (string) ob_get_clean();
        imagedestroy($image);

        if (!$written || $clean === '') {
            throw new ImageRefused('That PNG couldn\'t be re-saved cleanly.');
        }

        return $clean;
    }
}
