<?php

declare(strict_types=1);

namespace Felkyo\Tests\Support;

/**
 * Makes small real pictures — and small hostile ones — for the art tests.
 *
 * @package Felkyo\Tests\Support
 *
 * The still pictures come from GD, so they are exactly what a real export
 * tool produces. The ANIMATED ones are built by hand, block by block,
 * because GD cannot write animation — and building them by hand is also
 * what lets a test put a comment block or an EXIF chunk exactly where a
 * metadata-stripping bug would miss it.
 *
 * Every method returns raw bytes; asUpload() wraps bytes in the array shape
 * Request::file() hands the validator (a temp file on disk, like PHP does).
 */
final class Pictures
{
    /** A 40×24 still PNG with a transparent background and one red pixel at (3,4). */
    public static function stillPng(): string
    {
        $image = self::redDotCanvas();
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** The same picture as a still GIF. */
    public static function stillGif(): string
    {
        $image = self::redDotCanvas();
        ob_start();
        imagegif($image);

        return (string) ob_get_clean();
    }

    /** The same picture as a lossless WebP. */
    public static function stillWebp(): string
    {
        $image = self::redDotCanvas();
        ob_start();
        imagewebp($image, null, IMG_WEBP_LOSSLESS);

        return (string) ob_get_clean();
    }

    /**
     * A hand-built 2×2, two-frame GIF carrying everything the stripper must
     * handle: a NETSCAPE loop block (keep), a comment block saying
     * "secret-note" (drop), an XMP application block (drop), and script
     * text after the trailer (drop).
     */
    public static function animatedGif(int $frameDelay = 10): string
    {
        $header = 'GIF89a' . pack('v', 2) . pack('v', 2) . chr(0x80) . chr(0) . chr(0)
            . "\x00\x00\x00\xff\xff\xff"; // a 2-colour global colour table
        $loop = chr(0x21) . chr(0xFF) . chr(11) . 'NETSCAPE2.0' . chr(3) . chr(1) . pack('v', 0) . chr(0);
        $comment = chr(0x21) . chr(0xFE) . chr(11) . 'secret-note' . chr(0);
        $xmp = chr(0x21) . chr(0xFF) . chr(11) . 'XMP DataXMP' . chr(4) . '<xmp' . chr(0);

        // $frameDelay is kept in the output (it lives in the graphic-control
        // extension), so a caller wanting bytes unique to one run — the
        // smoke test, whose store may already hold an earlier run's copy —
        // passes a different delay and gets a different content hash.
        return $header . $loop . $comment . self::gifFrame($frameDelay) . $xmp . self::gifFrame($frameDelay)
            . chr(0x3B) . '<script>after-the-end</script>';
    }

    /**
     * A still PNG turned into an APNG shell: an acTL chunk (which is what
     * declares animation) and a tEXt chunk saying "secret-note" inserted
     * after IHDR. Enough for the reader to recognise "animated, with
     * metadata" — the frames themselves are not needed to test the walk.
     */
    public static function animatedPng(): string
    {
        $png = self::stillPng();
        // IHDR is the first chunk: 8 (signature) + 4 + 4 + 13 + 4 = byte 33.
        $afterIhdr = 33;

        return substr($png, 0, $afterIhdr)
            . self::pngChunk('acTL', pack('N', 2) . pack('N', 0))
            . self::pngChunk('tEXt', "Comment\0secret-note")
            . substr($png, $afterIhdr);
    }

    /**
     * A hand-built animated WebP: VP8X (animation + EXIF flags, 40×24
     * canvas), an ANIM chunk, one ANMF frame wrapping GD's lossless frame,
     * and an EXIF chunk saying "secret-note" (drop).
     */
    public static function animatedWebp(): string
    {
        // GD's lossless output is RIFF/WEBP with one VP8L chunk; take the chunk.
        $frameChunk = substr(self::stillWebp(), 12);

        $vp8x = self::webpChunk('VP8X', chr(0x02 | 0x08) . "\0\0\0" . self::u24(40 - 1) . self::u24(24 - 1));
        $anim = self::webpChunk('ANIM', pack('V', 0xFFFFFFFF) . pack('v', 0));
        // ANMF: x/3, y/3, width-1/3, height-1/3, duration/3, flags/1, then the frame.
        $anmf = self::webpChunk('ANMF', self::u24(0) . self::u24(0) . self::u24(39) . self::u24(23) . self::u24(100) . chr(0) . $frameChunk);
        $exif = self::webpChunk('EXIF', 'secret-note');

        $chunks = $vp8x . $anim . $anmf . $exif;

        return 'RIFF' . pack('V', 4 + strlen($chunks)) . 'WEBP' . $chunks;
    }

    /** A real GIF whose header claims to be far larger than the cap. */
    public static function gifClaimingHugeSize(): string
    {
        $header = 'GIF89a' . pack('v', 60000) . pack('v', 60000) . chr(0x80) . chr(0) . chr(0)
            . "\x00\x00\x00\xff\xff\xff";

        return $header . self::gifFrame() . chr(0x3B);
    }

    /**
     * Wrap bytes as the upload array Request::file() produces, backed by a
     * real temp file — the validator reads from disk, as it would in life.
     *
     * @return array{name: string, tmp_name: string, size: int, error: int}
     */
    public static function asUpload(string $bytes, string $browserName = 'picture.gif'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'felkyo-upload-');
        file_put_contents($path, $bytes);

        return ['name' => $browserName, 'tmp_name' => $path, 'size' => strlen($bytes), 'error' => UPLOAD_ERR_OK];
    }

    // ---- The small building blocks ----

    private static function redDotCanvas(): \GdImage
    {
        $image = imagecreatetruecolor(40, 24);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagesetpixel($image, 3, 4, imagecolorallocate($image, 200, 50, 50));

        return $image;
    }

    /** One 2×2 GIF frame: a graphic-control extension, then the image block. */
    private static function gifFrame(int $frameDelay = 10): string
    {
        $graphicControl = chr(0x21) . chr(0xF9) . chr(4) . chr(0) . pack('v', $frameDelay) . chr(0) . chr(0);
        $imageDescriptor = chr(0x2C) . pack('v4', 0, 0, 2, 2) . chr(0);
        $pixels = chr(2) . chr(2) . "\x44\x01" . chr(0); // LZW min code size, one sub-block, terminator

        return $graphicControl . $imageDescriptor . $pixels;
    }

    private static function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private static function webpChunk(string $type, string $data): string
    {
        return $type . pack('V', strlen($data)) . $data . (strlen($data) % 2 === 1 ? "\0" : '');
    }

    private static function u24(int $value): string
    {
        return chr($value & 0xFF) . chr(($value >> 8) & 0xFF) . chr(($value >> 16) & 0xFF);
    }
}
