<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * Reads a GIF's structure block by block — to measure it, to count its
 * frames, and to copy it out WITHOUT its metadata blocks.
 *
 * @package Felkyo\Art
 *
 * WHY WE WALK THE FILE OURSELVES instead of handing it to an image library:
 * a library would DECODE it — which for an animated GIF means keeping one
 * frame and losing the rest, and CLAUDE.md forbids ever baking a static
 * frame from an animated source. Walking the structure lets us do the three
 * things the safety rules need (measure, verify it is well-formed, drop
 * comment/XMP blocks) while leaving every pixel, delay and loop setting
 * exactly as the artist exported it.
 *
 * A GIF, in one paragraph (the shape this class follows): a 6-byte header
 * ("GIF89a"), a 7-byte "logical screen descriptor" carrying the width and
 * height, an optional global colour table, and then a sequence of BLOCKS
 * until a trailer byte (0x3B). Each block is either an image (one frame) or
 * an "extension" — a graphic-control extension (frame delay, transparency),
 * a comment, a plain-text block, or an "application" extension (the one
 * that matters is NETSCAPE2.0, which says "loop forever"). Variable-length
 * data inside blocks comes as "sub-blocks": a length byte, that many bytes,
 * repeat until a zero length. That is the whole grammar.
 *
 * WHAT IS KEPT AND WHAT IS DROPPED: frames, graphic-control extensions and
 * the loop extension are kept byte-for-byte. Comments, plain-text blocks and
 * every other application extension (XMP metadata lives in one) are dropped.
 * Anything after the trailer is dropped too — a browser ignores it, and a
 * "polyglot" file hides its payload there.
 */
final class GifFile
{
    private const BLOCK_IMAGE = 0x2C;
    private const BLOCK_EXTENSION = 0x21;
    private const BLOCK_TRAILER = 0x3B;

    private const EXTENSION_GRAPHIC_CONTROL = 0xF9;
    private const EXTENSION_APPLICATION = 0xFF;

    // The two application identifiers that mean "loop this animation". Kept;
    // every other application extension (XMP, ICC, tool-specific) is dropped.
    private const LOOP_IDENTIFIERS = ['NETSCAPE2.0', 'ANIMEXTS1.0'];

    /**
     * @return array{width: int, height: int, animated: bool, stripped: string}
     * @throws ImageRefused if the bytes are not a well-formed GIF.
     */
    public function read(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 13 || !in_array(substr($bytes, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            throw new ImageRefused('That file isn\'t a GIF picture.');
        }

        // The logical screen descriptor: width and height as little-endian
        // 16-bit numbers, then a packed byte whose top bit says whether a
        // global colour table follows and whose low three bits give its size.
        $width = ord($bytes[6]) | (ord($bytes[7]) << 8);
        $height = ord($bytes[8]) | (ord($bytes[9]) << 8);
        $packed = ord($bytes[10]);
        $offset = 13;

        if ($packed & 0x80) {
            $offset += 3 * (2 ** (($packed & 0x07) + 1));
        }
        $this->requireBytes($bytes, $offset);

        $kept = substr($bytes, 0, $offset);
        $frames = 0;

        // Walk the blocks, copying the ones we keep.
        while (true) {
            $this->requireBytes($bytes, $offset + 1);
            $blockType = ord($bytes[$offset]);

            if ($blockType === self::BLOCK_TRAILER) {
                break;
            }

            if ($blockType === self::BLOCK_IMAGE) {
                $end = $this->endOfImageBlock($bytes, $offset);
                $kept .= substr($bytes, $offset, $end - $offset);
                $frames++;
                $offset = $end;
                continue;
            }

            if ($blockType === self::BLOCK_EXTENSION) {
                $this->requireBytes($bytes, $offset + 2);
                $label = ord($bytes[$offset + 1]);
                $end = $this->endOfSubBlocks($bytes, $offset + 2);

                if ($this->keepsExtension($label, $bytes, $offset + 2)) {
                    $kept .= substr($bytes, $offset, $end - $offset);
                }
                $offset = $end;
                continue;
            }

            throw new ImageRefused('That GIF is damaged — a block in it isn\'t one a GIF can contain.');
        }

        if ($frames === 0) {
            throw new ImageRefused('That GIF has no picture in it.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'animated' => $frames > 1,
            'stripped' => $kept . chr(self::BLOCK_TRAILER),
        ];
    }

    /**
     * Which extensions survive: graphic control (delays, transparency) and
     * the loop extension. Comments, plain text and other application
     * extensions are exactly the metadata the safety rules want gone.
     */
    private function keepsExtension(int $label, string $bytes, int $firstSubBlockOffset): bool
    {
        if ($label === self::EXTENSION_GRAPHIC_CONTROL) {
            return true;
        }

        if ($label === self::EXTENSION_APPLICATION) {
            // The first sub-block of an application extension is 11 bytes:
            // an 8-byte identifier and a 3-byte authentication code.
            $subBlockLength = ord($bytes[$firstSubBlockOffset]);
            $identifier = substr($bytes, $firstSubBlockOffset + 1, $subBlockLength);

            return in_array($identifier, self::LOOP_IDENTIFIERS, true);
        }

        return false;
    }

    /**
     * Where an image block ends: 10 bytes of descriptor (the 0x2C byte plus
     * position, size and a packed byte), an optional local colour table,
     * one byte of LZW minimum code size, then the pixel data as sub-blocks.
     */
    private function endOfImageBlock(string $bytes, int $offset): int
    {
        $this->requireBytes($bytes, $offset + 10);
        $packed = ord($bytes[$offset + 9]);
        $offset += 10;

        if ($packed & 0x80) {
            $offset += 3 * (2 ** (($packed & 0x07) + 1));
        }

        // The LZW minimum code size byte, then the data sub-blocks.
        return $this->endOfSubBlocks($bytes, $offset + 1);
    }

    /**
     * Skip a run of sub-blocks (length byte + data, until a zero length) and
     * return the offset just past the terminator.
     */
    private function endOfSubBlocks(string $bytes, int $offset): int
    {
        while (true) {
            $this->requireBytes($bytes, $offset + 1);
            $subBlockLength = ord($bytes[$offset]);
            $offset += 1 + $subBlockLength;

            if ($subBlockLength === 0) {
                return $offset;
            }
        }
    }

    /**
     * A file that ends before a block does is truncated or forged — refuse,
     * rather than read past the end and guess.
     */
    private function requireBytes(string $bytes, int $needed): void
    {
        if (strlen($bytes) < $needed) {
            throw new ImageRefused('That GIF is cut short — the file ends in the middle of the picture.');
        }
    }
}
