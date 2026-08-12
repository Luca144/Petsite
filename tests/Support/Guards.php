<?php

declare(strict_types=1);

namespace Felkyo\Tests\Support;

use Felkyo\Safety\ContactDetailDetector;
use Felkyo\Safety\ImpersonationGuard;
use Felkyo\Safety\TextGuard;
use Felkyo\Safety\WordBlocklist;

/**
 * Builds the text-safety objects that many tests need.
 *
 * @package Felkyo\Tests\Support
 *
 * WHY THIS EXISTS: TextGuard is assembled from three pieces, and a dozen tests
 * need one. Repeating the assembly in each of them would mean that changing the
 * guard's shape meant editing a dozen files — and, worse, that a test could
 * quietly build a guard with a different blocklist from the real one and pass
 * while the site was broken.
 *
 * It is test support, not production code: the real assembly lives in
 * public/index.php, which is the single place the running site is wired together.
 */
final class Guards
{
    /**
     * A guard with whatever blocked words a test cares about.
     *
     * @param array<int, string> $blockedWords
     */
    public static function textGuard(array $blockedWords = ['spam', 'scam']): TextGuard
    {
        return new TextGuard(
            new WordBlocklist($blockedWords),
            new ContactDetailDetector(),
            new ImpersonationGuard()
        );
    }
}
