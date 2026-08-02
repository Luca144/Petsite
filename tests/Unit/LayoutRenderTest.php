<?php

declare(strict_types=1);

namespace Felkyo\Tests\Unit;

use League\Plates\Engine;
use PHPUnit\Framework\TestCase;

/**
 * Checks that pages render inside the real themed layout (increment 0.3).
 *
 * @package Felkyo\Tests\Unit
 *
 * WHAT THIS IS: a template test. It renders the welcome page through Plates —
 * exactly as the website does — and checks the important structural and
 * accessibility pieces are present in the HTML. It does not touch the database.
 *
 * WHY THESE CHECKS: they guard the things that are easy to break by accident and
 * that matter for accessibility and branding — the landmarks, the skip link, the
 * stylesheets, the wordmark — and they enforce the "no dropdown menus" rule.
 */
final class LayoutRenderTest extends TestCase
{
    private string $html;

    /**
     * Render the welcome page once, the same way public/index.php does, and keep
     * the resulting HTML for the individual checks below.
     */
    protected function setUp(): void
    {
        $templates = new Engine(dirname(__DIR__, 2) . '/templates');
        $this->html = $templates->render('pages/hello', ['appName' => 'Felkyo Creatures']);
    }

    public function testPageDeclaresEnglishLanguage(): void
    {
        // The <html lang="en"> tells screen readers to use English pronunciation.
        $this->assertStringContainsString('<html lang="en">', $this->html);
    }

    public function testPageHasTheSharedLandmarks(): void
    {
        // Real landmark elements let screen-reader users navigate the page.
        $this->assertStringContainsString('<header', $this->html);
        $this->assertStringContainsString('<main id="main">', $this->html);
        $this->assertStringContainsString('<footer', $this->html);
        $this->assertStringContainsString('<nav', $this->html);
    }

    public function testPageHasASkipToContentLink(): void
    {
        // Keyboard users must be able to skip the header straight to the content.
        $this->assertStringContainsString('class="skip-link"', $this->html);
        $this->assertStringContainsString('href="#main"', $this->html);
    }

    public function testPageLoadsAllThreeStylesheets(): void
    {
        // The themed look depends on these three stylesheets being linked.
        $this->assertStringContainsString('/css/theme.css', $this->html);
        $this->assertStringContainsString('/css/layout.css', $this->html);
        $this->assertStringContainsString('/css/components.css', $this->html);
    }

    public function testPageShowsTheBrandWordmark(): void
    {
        $this->assertStringContainsString('site-header__wordmark', $this->html);
        $this->assertStringContainsString('Creatures', $this->html);
    }

    /**
     * Enforces CLAUDE.md section 8: no dropdown menus, anywhere, ever. If someone
     * later adds a <select> to a template, this test fails and explains why.
     */
    public function testPageContainsNoDropdownMenu(): void
    {
        $this->assertStringNotContainsString(
            '<select',
            $this->html,
            'Dropdown menus (<select>) are forbidden by CLAUDE.md section 8 — '
            . 'use tiles, button groups, or radio-style cards instead.'
        );
    }
}
