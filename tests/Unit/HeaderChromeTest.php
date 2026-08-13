<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use Felkyo\Creatures\Creature;
use Felkyo\Creatures\Species;
use Felkyo\Users\User;
use League\Plates\Engine;
use PHPUnit\Framework\TestCase;

/**
 * Checks the logged-in header chrome: the purse chip and the creature moment.
 *
 * @package Felkyo\Tests\Unit
 *
 * WHAT THIS IS: a template test, like LayoutRenderTest but for the signed-in
 * view. It renders the home page through the real layout with a fake user (and
 * optionally a fake creature moment) and checks what every logged-in page
 * inherits: the balance is visible in the header, the moment bubble carries the
 * portrait and the line, and player-written creature names arrive escaped.
 */
final class HeaderChromeTest extends TestCase
{
    /** Render the home page as a signed-in player, optionally with a moment. */
    private function render(?array $moment = null): string
    {
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        // The logged-in footer contains the log-out form, which calls this helper.
        $templates->registerFunction('csrf_field', static fn (): string => '');

        // Shared via addData, exactly as public/index.php shares them — data
        // passed only to render() would reach the page template but NOT the
        // layout, and the purse and the moment both live in the layout.
        $templates->addData([
            'currentUser' => new User(7, 'wren', 'wren@example.com', 'hash', 52, null, null),
            'currencyName' => 'coins',
            'creatureMoment' => $moment,
        ]);

        return $templates->render('pages/hello', ['appName' => 'Felkyo Creatures']);
    }

    private function moment(string $creatureName, string $line): array
    {
        return [
            'summary' => [
                'creature' => new Creature(9, 7, 1, $creatureName, 40, 2, null, null, true, null, null, null),
                'species' => new Species(1, 'pebblewing', 'Pebblewing', null, true, true),
                'level' => 3,
                'stage' => 'juvenile',
            ],
            'line' => $line,
        ];
    }

    public function testTheHeaderShowsTheBalanceWithoutScrolling(): void
    {
        $html = $this->render();

        // The chip and its readable form ("You're carrying 52 coins").
        $this->assertStringContainsString('site-purse', $html);
        $this->assertStringContainsString('52', $html);
        $this->assertStringContainsString('coins', $html);
    }

    public function testNoMomentMeansNoBubble(): void
    {
        $this->assertStringNotContainsString('creature-moment', $this->render());
    }

    public function testAMomentRendersThePortraitTheLineAndTheLink(): void
    {
        $html = $this->render($this->moment('Bramble', 'Bramble kept your seat warm.'));

        $this->assertStringContainsString('creature-moment', $html);
        $this->assertStringContainsString('Bramble kept your seat warm.', $html);
        // The portrait follows the same convention as the creature cards:
        // species slug + current growth stage.
        $this->assertStringContainsString('/assets/creatures/pebblewing/juvenile.gif', $html);
        // The whole bubble links to the creature's page.
        $this->assertStringContainsString('href="/creature/9"', $html);
    }

    public function testAHostileCreatureNameArrivesEscaped(): void
    {
        // Creature names are player text; the moment must escape them like
        // every other surface does.
        $html = $this->render($this->moment('<b>x</b>', 'A <b>x</b> moment.'));

        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }
}
