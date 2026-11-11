<?php

declare(strict_types=1);

namespace Felkyo\Texts;

/**
 * Finds every editable sentence: the $this->t('key', 'words') calls in the
 * templates, and the messages the code sends (flash messages and the
 * refused / ok results of the services).
 *
 * @package Felkyo\Texts
 *
 * WHY READ THE FILES. The list of sentences IS the code — there is no
 * second list to forget to update. Adding a $this->t(...) to a template
 * makes it editable, and nothing else is needed.
 *
 * It reads ~300 files, so it runs only when needed: the panel's text list,
 * saving a text, and pages shown in editing mode. Ordinary page views never
 * use it.
 *
 * MESSAGES. A message is recognised by its pattern: 'Moved "%s" %s.' is
 * shown to the artist as 'Moved "{1}" {2}.' — the numbered gaps are the
 * names and numbers filled in on the day. Only messages written as plain
 * text in the code are found; one put together in pieces can't be.
 */
final class TextScanner
{
    /** A single-quoted PHP string, escapes included. */
    private const QUOTED = "'((?:[^'\\\\]|\\\\.)*)'";

    public function __construct(private string $templatesDir, private string $srcDir)
    {
    }

    /** @return array<string, array{default: string, file: string}> key => where it's from */
    public function templateTexts(): array
    {
        $texts = [];
        foreach ($this->phpFiles($this->templatesDir) as $file) {
            preg_match_all('/\$this->t\(\s*' . self::QUOTED . '\s*,\s*' . self::QUOTED . '/', (string) file_get_contents($file), $found, PREG_SET_ORDER);
            foreach ($found as [, $key, $default]) {
                $texts[self::unquote($key)] ??= ['default' => self::unquote($default), 'file' => basename($file, '.php')];
            }
        }
        ksort($texts);

        return $texts;
    }

    /** @return array<string, array{original: string, file: string}> message key => its pattern */
    public function messages(): array
    {
        $messages = [];
        $call = '/(?:->flash|::(?:refused|ok|failed|rejected|saved|reward|accepted))\(\s*(?:sprintf\(\s*)?' . self::QUOTED . '/';
        foreach ($this->phpFiles($this->srcDir) as $file) {
            preg_match_all($call, (string) file_get_contents($file), $found, PREG_SET_ORDER);
            foreach ($found as [, $original]) {
                $original = self::unquote($original);
                if (trim($original) !== '' && preg_match('/[A-Za-z]/', $original) === 1) {
                    $messages[self::messageKey($original)] ??= ['original' => $original, 'file' => basename($file, '.php')];
                }
            }
        }

        return $messages;
    }

    /** The key a message pattern is stored under. */
    public static function messageKey(string $original): string
    {
        return 'message.' . substr(hash('sha256', $original), 0, 16);
    }

    /** 'Moved "%s" %s.' → 'Moved "{1}" {2}.' — how a pattern is shown and edited. */
    public static function friendly(string $original): string
    {
        $number = 0;
        $friendly = preg_replace_callback('/%(?:\d+\$)?[sd]/', static function () use (&$number): string {
            return '{' . ++$number . '}';
        }, $original) ?? $original;

        return str_replace('%%', '%', $friendly);
    }

    /** @return string[] */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $walker = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($walker as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /** The text inside a single-quoted PHP string. */
    private static function unquote(string $quoted): string
    {
        return str_replace(["\\'", '\\\\'], ["'", '\\'], $quoted);
    }
}
