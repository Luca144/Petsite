<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Art\GifFile;
use Felkyo\Art\ImageRefused;
use Felkyo\Art\ImageValidator;
use Felkyo\Art\PngFile;
use Felkyo\Art\WebpFile;
use Felkyo\Tests\Support\Pictures;
use PHPUnit\Framework\TestCase;

/**
 * The upload gate: what it accepts, what it refuses, and what it strips.
 *
 * @package Felkyo\Tests\Unit
 *
 * The M2.2 plan promised each refusal a test: a disguised non-image, a
 * truncated one, an oversized one, a forged huge header, a hostile
 * filename. And for what it accepts: animated files come out byte-identical
 * in their frames with their metadata gone, and a re-encoded still PNG has
 * the same pixels it went in with.
 */
final class ImageValidatorTest extends TestCase
{
    private ImageValidator $validator;

    protected function setUp(): void
    {
        // The real caps from config, so the test proves the real rule.
        $config = require dirname(__DIR__, 2) . '/config/config.php';
        $this->validator = new ImageValidator($config['art']);
    }

    // --- Refusals ---

    public function testAPhpScriptCalledSpriteGifIsRefused(): void
    {
        $this->expectException(ImageRefused::class);
        $this->validator->validate(Pictures::asUpload('<?php echo "hello"; ?>', 'sprite.gif'));
    }

    public function testAnHtmlPageCalledPngIsRefused(): void
    {
        $this->expectException(ImageRefused::class);
        $this->validator->validate(Pictures::asUpload('<!doctype html><script>alert(1)</script>', 'art.png'));
    }

    public function testATruncatedPictureIsRefused(): void
    {
        $this->expectException(ImageRefused::class);
        $this->validator->validate(Pictures::asUpload(substr(Pictures::stillPng(), 0, 40), 'cut.png'));
    }

    public function testAFileOverTheSizeCapIsRefusedByItsReportedSizeAlone(): void
    {
        $upload = Pictures::asUpload(Pictures::stillGif());
        $upload['size'] = 6 * 1024 * 1024; // what PHP would report for a 6 MB file

        $this->expectException(ImageRefused::class);
        $this->expectExceptionMessage('too big');
        $this->validator->validate($upload);
    }

    public function testAHeaderClaimingHugeDimensionsIsRefusedWithoutDecoding(): void
    {
        // 60000×60000 in the header of a 60-byte file. If anything tried to
        // allocate that, this test would run out of memory instead of passing.
        $this->expectException(ImageRefused::class);
        $this->expectExceptionMessage('biggest the site takes');
        $this->validator->validate(Pictures::asUpload(Pictures::gifClaimingHugeSize()));
    }

    public function testAMissingFileIsRefusedPolitely(): void
    {
        $this->expectException(ImageRefused::class);
        $this->expectExceptionMessage('Choose a picture');
        $this->validator->validate(['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE]);
    }

    // --- Acceptance, and what comes out ---

    public function testTheThreeStillFormatsAreRecognisedByTheirBytesNotTheirNames(): void
    {
        // Every one is deliberately misnamed. The bytes decide.
        $gif = $this->validator->validate(Pictures::asUpload(Pictures::stillGif(), 'actually.png'));
        $png = $this->validator->validate(Pictures::asUpload(Pictures::stillPng(), 'actually.webp'));
        $webp = $this->validator->validate(Pictures::asUpload(Pictures::stillWebp(), 'actually.gif'));

        $this->assertSame('gif', $gif->type);
        $this->assertSame('png', $png->type);
        $this->assertSame('webp', $webp->type);
        foreach ([$gif, $png, $webp] as $picture) {
            $this->assertSame(40, $picture->width);
            $this->assertSame(24, $picture->height);
            $this->assertFalse($picture->isAnimated);
        }
    }

    public function testTheStoredNameIsTheContentHashPlusTheRealExtension(): void
    {
        $picture = $this->validator->validate(Pictures::asUpload(Pictures::stillGif(), 'whatever.txt'));

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.gif$/', $picture->storedName());
        $this->assertSame(hash('sha256', $picture->bytes) . '.gif', $picture->storedName());
    }

    public function testAHostileFilenameBecomesAHarmlessLabel(): void
    {
        $picture = $this->validator->validate(
            Pictures::asUpload(Pictures::stillGif(), "../../../.env\x00evil\n" . str_repeat('x', 300))
        );

        $this->assertStringNotContainsString('/', $picture->originalName);
        $this->assertStringNotContainsString('..', $picture->originalName);
        $this->assertStringNotContainsString("\x00", $picture->originalName);
        $this->assertLessThanOrEqual(100, mb_strlen($picture->originalName));
    }

    public function testAStillPngIsReencodedWithIdenticalPixelsAndNoMetadata(): void
    {
        $original = Pictures::stillPng();
        $picture = $this->validator->validate(Pictures::asUpload($original, 'dot.png'));

        $before = imagecreatefromstring($original);
        $after = imagecreatefromstring($picture->bytes);

        $this->assertSame(imagecolorat($before, 3, 4), imagecolorat($after, 3, 4), 'the red dot must survive');
        $this->assertSame(imagecolorat($before, 0, 0), imagecolorat($after, 0, 0), 'the transparent corner must survive');
        $this->assertStringNotContainsString('tEXt', $picture->bytes);
    }

    public function testAnAnimatedGifKeepsItsFramesAndLoopButLosesItsMetadata(): void
    {
        $picture = $this->validator->validate(Pictures::asUpload(Pictures::animatedGif(), 'anim.gif'));

        $this->assertTrue($picture->isAnimated);
        $this->assertStringContainsString('NETSCAPE2.0', $picture->bytes, 'the loop block is kept');
        $this->assertStringNotContainsString('secret-note', $picture->bytes, 'the comment is gone');
        $this->assertStringNotContainsString('XMP', $picture->bytes, 'the XMP block is gone');
        $this->assertStringNotContainsString('script', $picture->bytes, 'bytes after the trailer are gone');
        $this->assertSame(2, substr_count($picture->bytes, chr(0x2C) . pack('v4', 0, 0, 2, 2)), 'both frames are kept');
        // What comes out is itself a well-formed GIF that reads back the same.
        $this->assertTrue((new GifFile())->read($picture->bytes)['animated']);
    }

    public function testAnAnimatedPngIsNotFlattenedAndLosesItsTextChunk(): void
    {
        $picture = $this->validator->validate(Pictures::asUpload(Pictures::animatedPng(), 'anim.png'));

        $this->assertTrue($picture->isAnimated);
        $this->assertStringContainsString('acTL', $picture->bytes, 'the animation control chunk is kept');
        $this->assertStringNotContainsString('secret-note', $picture->bytes, 'the text chunk is gone');
        $this->assertSame(40, (new PngFile())->read($picture->bytes)['width']);
    }

    public function testAnAnimatedWebpKeepsItsFramesAndLosesItsExif(): void
    {
        $picture = $this->validator->validate(Pictures::asUpload(Pictures::animatedWebp(), 'anim.webp'));

        $this->assertTrue($picture->isAnimated);
        $this->assertSame(40, $picture->width);
        $this->assertStringContainsString('ANMF', $picture->bytes, 'the frame is kept');
        $this->assertStringNotContainsString('secret-note', $picture->bytes, 'the EXIF chunk is gone');
        // The VP8X flags no longer promise an EXIF chunk, and the RIFF size
        // matches the bytes that follow it.
        $again = (new WebpFile())->read($picture->bytes);
        $this->assertTrue($again['animated']);
        $this->assertSame(strlen($picture->bytes) - 8, unpack('V', substr($picture->bytes, 4, 4))[1]);
        $this->assertSame(0, ord($picture->bytes[20]) & 0x08, 'the EXIF flag is cleared');
    }

    public function testTheSameBytesAlwaysGetTheSameName(): void
    {
        $first = $this->validator->validate(Pictures::asUpload(Pictures::stillGif(), 'a.gif'));
        $second = $this->validator->validate(Pictures::asUpload(Pictures::stillGif(), 'b.gif'));

        $this->assertSame($first->storedName(), $second->storedName());
    }
}
