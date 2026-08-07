<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Guestbook\GuestbookMessages;
use PHPUnit\Framework\TestCase;

/**
 * Tests the catalogue of choosable guestbook messages.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHAT MATTERS HERE: the guestbook has no free typing — a visitor can only pick a
 * message from a fixed list in config. So two things must hold. Only a key that is
 * really in the list may be accepted (otherwise someone could post a made-up
 * message key straight to the form). And a key that USED to be in the list but has
 * since been retired must still display sensibly, because old entries in the
 * database still refer to it.
 */
final class GuestbookMessagesTest extends TestCase
{
    private GuestbookMessages $messages;

    protected function setUp(): void
    {
        // A small stand-in catalogue, so these tests do not break every time the
        // Product Owner rewords the real messages in config.
        $this->messages = new GuestbookMessages([
            'lovely-creature' => 'What a lovely creature.',
            'warm-wishes' => 'Warm wishes from a fellow wanderer.',
        ]);
    }

    public function testAKeyFromTheListIsRecognised(): void
    {
        $this->assertTrue($this->messages->has('lovely-creature'));
    }

    public function testAMadeUpKeyIsNotRecognised(): void
    {
        $this->assertFalse($this->messages->has('buy-cheap-pills-now'));
    }

    public function testAnEmptyKeyIsNotRecognised(): void
    {
        $this->assertFalse($this->messages->has(''));
    }

    public function testTheTextOfAKnownKeyIsReturned(): void
    {
        $this->assertSame('Warm wishes from a fellow wanderer.', $this->messages->textFor('warm-wishes'));
    }

    /**
     * The important edge case: the Product Owner deletes a message from config,
     * but entries in the database still point at it. Those entries must still
     * render something readable instead of an empty space or a crash.
     */
    public function testARetiredKeyFallsBackToNeutralWording(): void
    {
        $text = $this->messages->textFor('a-message-that-was-removed');

        $this->assertNotSame('', $text);
        $this->assertSame(GuestbookMessages::RETIRED_MESSAGE_TEXT, $text);
    }

    public function testTheWholeListCanBeReadForBuildingTheChooser(): void
    {
        $all = $this->messages->all();

        $this->assertCount(2, $all);
        $this->assertArrayHasKey('lovely-creature', $all);
        $this->assertSame('What a lovely creature.', $all['lovely-creature']);
    }
}
