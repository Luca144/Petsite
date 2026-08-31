<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * Reads a WebP chunk by chunk — to measure it, to tell still from animated,
 * and to copy it out without its EXIF/XMP metadata.
 *
 * @package Felkyo\Art
 *
 * A WebP is a RIFF container: "RIFF", a 4-byte little-endian size, "WEBP",
 * then CHUNKS of a 4-letter name, a 4-byte size and the data (padded to an
 * even length). A simple file has one chunk: "VP8 " (lossy) or "VP8L"
 * (lossless), whose first bytes carry the width and height. An extended
 * file starts with "VP8X" — a flags byte (animation, EXIF, XMP, alpha, ICC)
 * and a canvas size — followed by the chunks the flags promise, including
 * "ANIM"/"ANMF" for animation and "EXIF"/"XMP " for metadata.
 *
 * WHAT IS DROPPED: the EXIF and XMP chunks, and the two flag bits that
 * announce them. Everything else — every frame, every delay, the colour
 * profile, the alpha — is copied byte-for-byte. The RIFF size is then
 * recomputed, because it must equal the bytes that follow it.
 */
final class WebpFile
{
    private const FLAG_ANIMATION = 0x02;
    private const FLAG_XMP = 0x04;
    private const FLAG_EXIF = 0x08;

    private const METADATA_CHUNKS = ['EXIF', 'XMP '];

    /**
     * @return array{width: int, height: int, animated: bool, stripped: string}
     * @throws ImageRefused if the bytes are not a well-formed WebP.
     */
    public function read(string $bytes): array
    {
        if (strlen($bytes) < 20 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            throw new ImageRefused('That file isn\'t a WebP picture.');
        }

        $offset = 12;
        $width = 0;
        $height = 0;
        $animated = false;
        $keptChunks = '';
        $sawPicture = false;

        while ($offset + 8 <= strlen($bytes)) {
            $type = substr($bytes, $offset, 4);
            $dataLength = unpack('V', substr($bytes, $offset + 4, 4))[1];
            // Chunk data is padded to an even number of bytes.
            $paddedLength = $dataLength + ($dataLength % 2);

            if ($offset + 8 + $dataLength > strlen($bytes)) {
                throw new ImageRefused('That WebP is cut short — the file ends in the middle of the picture.');
            }

            $data = substr($bytes, $offset + 8, $dataLength);

            if ($type === 'VP8X') {
                [$width, $height, $animated] = $this->readExtendedHeader($data);
                // Clear the two metadata flags: the chunks they announce are
                // about to be dropped, and a browser trusts the flags.
                $data[0] = chr(ord($data[0]) & ~(self::FLAG_EXIF | self::FLAG_XMP));
            } elseif ($type === 'VP8 ' && $width === 0) {
                [$width, $height] = $this->readLossyDimensions($data);
                $sawPicture = true;
            } elseif ($type === 'VP8L' && $width === 0) {
                [$width, $height] = $this->readLosslessDimensions($data);
                $sawPicture = true;
            } elseif ($type === 'VP8 ' || $type === 'VP8L' || $type === 'ANMF') {
                $sawPicture = true;
            } elseif ($type === 'ANIM') {
                $animated = true;
            }

            if (!in_array($type, self::METADATA_CHUNKS, true)) {
                // Re-emit the chunk with its declared length and even padding.
                $keptChunks .= $type . pack('V', $dataLength) . $data . str_repeat("\0", $paddedLength - $dataLength);
            }

            $offset += 8 + $paddedLength;
        }

        if (!$sawPicture || $width === 0 || $height === 0) {
            throw new ImageRefused('That WebP is damaged — it has no picture data in it.');
        }

        // RIFF size = "WEBP" (4) + every chunk that follows.
        $stripped = 'RIFF' . pack('V', 4 + strlen($keptChunks)) . 'WEBP' . $keptChunks;

        return ['width' => $width, 'height' => $height, 'animated' => $animated, 'stripped' => $stripped];
    }

    /**
     * VP8X: flags byte, three reserved bytes, then canvas width-1 and
     * height-1 as 24-bit little-endian numbers.
     *
     * @return array{int, int, bool}
     */
    private function readExtendedHeader(string $data): array
    {
        if (strlen($data) < 10) {
            throw new ImageRefused('That WebP is damaged — its header is too short.');
        }

        $flags = ord($data[0]);
        $width = 1 + (ord($data[4]) | (ord($data[5]) << 8) | (ord($data[6]) << 16));
        $height = 1 + (ord($data[7]) | (ord($data[8]) << 8) | (ord($data[9]) << 16));

        return [$width, $height, (bool) ($flags & self::FLAG_ANIMATION)];
    }

    /**
     * "VP8 " (lossy): a 3-byte frame tag, the start code 9D 01 2A, then
     * width and height as 14-bit little-endian numbers (the top two bits of
     * each 16-bit word are a scaling hint we ignore).
     *
     * @return array{int, int}
     */
    private function readLossyDimensions(string $data): array
    {
        if (strlen($data) < 10 || substr($data, 3, 3) !== "\x9d\x01\x2a") {
            throw new ImageRefused('That WebP is damaged — its picture data doesn\'t start the way it should.');
        }

        $width = (ord($data[6]) | (ord($data[7]) << 8)) & 0x3FFF;
        $height = (ord($data[8]) | (ord($data[9]) << 8)) & 0x3FFF;

        return [$width, $height];
    }

    /**
     * "VP8L" (lossless): a 0x2F signature byte, then 28 bits packed
     * little-endian: width-1 in the low 14, height-1 in the next 14.
     *
     * @return array{int, int}
     */
    private function readLosslessDimensions(string $data): array
    {
        if (strlen($data) < 5 || $data[0] !== "\x2f") {
            throw new ImageRefused('That WebP is damaged — its picture data doesn\'t start the way it should.');
        }

        $packed = unpack('V', substr($data, 1, 4))[1];
        $width = ($packed & 0x3FFF) + 1;
        $height = (($packed >> 14) & 0x3FFF) + 1;

        return [$width, $height];
    }
}
