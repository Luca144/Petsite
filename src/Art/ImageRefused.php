<?php

declare(strict_types=1);

namespace Felkyo\Art;

/**
 * Thrown when an upload is not accepted — always with a sentence a person
 * can act on.
 *
 * @package Felkyo\Art
 *
 * WHY AN EXCEPTION AND NOT A RESULT OBJECT: the validator is a pipeline of a
 * dozen checks across four classes, and any one of them may be the reason.
 * Threading a result through all of them would bury the checks under
 * plumbing. An exception lets each check say "no, because…" in one line and
 * lets the ONE caller that talks to a person (the controller) catch it and
 * show the message. Nothing else ever catches it.
 *
 * The message is user-facing by contract (golden rule 5: plain words) and
 * must never contain anything from the file itself — a hostile filename
 * echoed back into a page is exactly the thing escaping exists to prevent,
 * and the messages here are fixed strings for that reason.
 */
final class ImageRefused extends \RuntimeException
{
}
