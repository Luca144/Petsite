<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * The gate every uploaded picture passes through before it is stored.
 *
 * @package Felkyo\Art
 *
 * WHAT IT PROVES, in order — and why the order matters:
 *  1. The upload arrived whole (PHP's own error code), and is under the size
 *     cap. Cheap, and it bounds every later step.
 *  2. The bytes START with a real GIF, PNG or WebP signature. The name the
 *     browser sent and the type the browser declared are ignored entirely:
 *     a PHP script called sprite.gif fails here.
 *  3. The dimensions, read from the file's HEADER, are within the cap. This
 *     runs BEFORE anything decodes pixels, so a 200-byte file claiming to be
 *     a 100000×100000 picture is refused without costing any memory.
 *  4. The whole file is walked structurally by the format reader, which
 *     refuses anything cut short or malformed and returns the bytes to
 *     store — metadata dropped, pixels untouched (see the three readers).
 *
 * Uploads are "the highest-value target on the site" (CLAUDE.md §7): this
 * class is where that sentence becomes code. Every refusal is a fixed
 * plain-words sentence that never echoes anything from the file.
 */
final class ImageValidator
{
    // The bytes each format begins with. This is the ONLY way a file's type
    // is decided.
    private const SIGNATURES = [
        'gif' => ['GIF87a', 'GIF89a'],
        'png' => ["\x89PNG\r\n\x1a\n"],
        'webp' => ['RIFF'], // plus "WEBP" at byte 8, checked by WebpFile
    ];

    /**
     * @param array $artConfig The 'art' block of config: max_bytes, max_dimension.
     */
    public function __construct(
        private array $artConfig,
        private GifFile $gif = new GifFile(),
        private PngFile $png = new PngFile(),
        private WebpFile $webp = new WebpFile(),
    ) {
    }

    /**
     * Turn an upload (as Request::file() describes it) into an UploadedImage,
     * or throw ImageRefused with the reason.
     *
     * @param array{name: string, tmp_name: string, size: int, error: int} $upload
     */
    public function validate(array $upload): UploadedImage
    {
        $this->requireArrivedWhole($upload);

        $bytes = $this->readBytes($upload['tmp_name']);
        $type = $this->typeFromSignature($bytes);

        $reader = match ($type) {
            'gif' => $this->gif,
            'png' => $this->png,
            'webp' => $this->webp,
        };
        $picture = $reader->read($bytes);

        $this->requireWithinDimensions($picture['width'], $picture['height']);

        // A STILL PNG is re-encoded through GD for a thorough metadata strip
        // — and this is the one step that decodes pixels, which is why it
        // happens only here, after the dimension cap has passed (step 3 in
        // the class note). Animated files never take this path.
        $bytesToStore = $picture['stripped'];
        if ($type === 'png' && !$picture['animated']) {
            $bytesToStore = $this->png->reencodeStill($bytes);
        }

        return new UploadedImage(
            type: $type,
            width: $picture['width'],
            height: $picture['height'],
            isAnimated: $picture['animated'],
            bytes: $bytesToStore,
            originalName: $this->displayName($upload['name']),
        );
    }

    /**
     * PHP's own verdict on the upload, then the size cap. PHP's error codes
     * are turned into sentences here so no controller has to know them.
     */
    private function requireArrivedWhole(array $upload): void
    {
        if ($upload['error'] === UPLOAD_ERR_NO_FILE) {
            throw new ImageRefused('Choose a picture first.');
        }

        if ($upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new ImageRefused($this->tooBigMessage());
        }

        if ($upload['error'] !== UPLOAD_ERR_OK) {
            throw new ImageRefused('The upload didn\'t arrive whole — please try again.');
        }

        if ($upload['size'] > (int) $this->artConfig['max_bytes']) {
            throw new ImageRefused($this->tooBigMessage());
        }
    }

    /**
     * Read the bytes, and check the size AGAIN on what was actually read —
     * the 'size' PHP reports and the bytes on disk are the same thing in
     * practice, but the cap is about the bytes.
     */
    private function readBytes(string $temporaryPath): string
    {
        $bytes = is_file($temporaryPath) ? file_get_contents($temporaryPath) : false;
        if ($bytes === false || $bytes === '') {
            throw new ImageRefused('The upload didn\'t arrive whole — please try again.');
        }

        if (strlen($bytes) > (int) $this->artConfig['max_bytes']) {
            throw new ImageRefused($this->tooBigMessage());
        }

        return $bytes;
    }

    /**
     * Which format, by the first bytes alone.
     */
    private function typeFromSignature(string $bytes): string
    {
        foreach (self::SIGNATURES as $type => $signatures) {
            foreach ($signatures as $signature) {
                if (str_starts_with($bytes, $signature)) {
                    return $type;
                }
            }
        }

        throw new ImageRefused('That file isn\'t a GIF, PNG or WebP picture. Those are the three kinds the site can show.');
    }

    private function requireWithinDimensions(int $width, int $height): void
    {
        $max = (int) $this->artConfig['max_dimension'];

        if ($width < 1 || $height < 1) {
            throw new ImageRefused('That picture has no size — it may be damaged.');
        }

        if ($width > $max || $height > $max) {
            throw new ImageRefused(sprintf(
                'That picture is %d×%d pixels; the biggest the site takes is %d×%d.',
                $width, $height, $max, $max
            ));
        }
    }

    /**
     * The browser's filename, made safe for DISPLAY: path separators and
     * control characters gone, length capped. It is only ever shown as a
     * label ("which one was bramble-adult.gif?") and always escaped on
     * output; it is never, anywhere, used as a path.
     */
    private function displayName(string $browserName): string
    {
        $name = basename(str_replace('\\', '/', $browserName));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim($name);

        if ($name === '') {
            return 'untitled';
        }

        return mb_substr($name, 0, 100);
    }

    private function tooBigMessage(): string
    {
        return sprintf(
            'That file is too big — the most the site takes is %d MB.',
            intdiv((int) $this->artConfig['max_bytes'], 1024 * 1024)
        );
    }
}
