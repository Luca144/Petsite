<?php

declare(strict_types=1);

namespace Felkyo\Look;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Admin\SiteSettingsRepository;
use Felkyo\Themes\ThemeRepository;
use Felkyo\Users\User;

/**
 * Every change to the site-wide look: the default theme, section order,
 * the announcement line and the fine-tuning CSS.
 *
 * @package Felkyo\Look
 *
 * Who may call it (artist role, owner passes) is decided by the AdminGate
 * around the routes. Every change is checked here and written to the audit
 * log, so "who changed the look at 3am?" always has an answer.
 */
final class SiteLookService
{
    public const MAX_ANNOUNCEMENT = 200;

    public function __construct(
        private SiteSettingsRepository $settings,
        private ThemeRepository $themes,
        private CustomCssRepository $cssHistory,
        private AuditLogRepository $auditLog,
    ) {
    }

    /** Everyone who hasn't chosen a theme sees this one. Null = the site's own look. */
    public function setDefaultTheme(User $actor, ?int $themeId, string $ip): ContentEditResult
    {
        if ($themeId === null) {
            $this->settings->reset(SiteLook::KEY_DEFAULT_THEME);
            $this->audit($actor, ['default_theme' => null], $ip);

            return ContentEditResult::ok('Everyone who hasn\'t chosen a theme now sees Felkyo\'s own look.');
        }
        $theme = $this->themes->findPublished($themeId);
        if ($theme === null) {
            return ContentEditResult::refused('Only a published theme can be the default — publish it first.');
        }
        $this->settings->set(SiteLook::KEY_DEFAULT_THEME, $theme->id);
        $this->audit($actor, ['default_theme' => $theme->name], $ip);

        return ContentEditResult::ok(sprintf('"%s" is now the look everyone sees, unless they chose their own.', $theme->name));
    }

    /** Move one section of a page one place up or down. */
    public function moveSection(User $actor, SiteLook $look, string $page, string $section, int $direction, string $ip): ContentEditResult
    {
        if (!isset(SiteLook::SECTIONS[$page][$section])) {
            return ContentEditResult::refused('That section isn\'t one we know.');
        }
        $order = SiteLook::moved($look->orderOf($page), $section, $direction);
        $this->settings->set(SiteLook::KEY_ORDER_PREFIX . $page, $order);
        $this->audit($actor, ['page' => $page, 'order' => $order], $ip);

        return ContentEditResult::ok(sprintf('Moved "%s" %s.', SiteLook::SECTIONS[$page][$section], $direction < 0 ? 'up' : 'down'));
    }

    public function resetOrder(User $actor, string $page, string $ip): ContentEditResult
    {
        if (!isset(SiteLook::SECTIONS[$page])) {
            return ContentEditResult::refused('That page isn\'t one we know.');
        }
        $this->settings->reset(SiteLook::KEY_ORDER_PREFIX . $page);
        $this->audit($actor, ['page' => $page, 'order' => 'reset'], $ip);

        return ContentEditResult::ok('Back to the site\'s own order.');
    }

    /** The line shown at the top of every page. Empty = no announcement. */
    public function setAnnouncement(User $actor, string $words, string $ip): ContentEditResult
    {
        $words = trim(preg_replace('/\s+/', ' ', $words) ?? '');
        if (mb_strlen($words) > self::MAX_ANNOUNCEMENT) {
            return ContentEditResult::refused(sprintf('Keep it to %d characters — it sits on every page.', self::MAX_ANNOUNCEMENT));
        }
        $words === '' ? $this->settings->reset(SiteLook::KEY_ANNOUNCEMENT) : $this->settings->set(SiteLook::KEY_ANNOUNCEMENT, $words);
        $this->audit($actor, ['announcement' => $words], $ip);

        return ContentEditResult::ok($words === '' ? 'The announcement is gone.' : 'The announcement is up on every page.');
    }

    /**
     * Make this CSS the live fine-tuning CSS, keeping it in the history.
     * Empty CSS switches it off (and is kept in the history too).
     */
    public function publishCss(User $actor, string $css, string $note, string $ip): ContentEditResult
    {
        $css = str_replace("\r\n", "\n", $css);
        $problem = CustomCssRules::problemWith($css);
        if ($problem !== null) {
            return ContentEditResult::refused('Not saved: ' . $problem);
        }
        $note = mb_substr(trim($note), 0, 120);

        $this->cssHistory->add($css, $note, $actor->id);
        trim($css) === '' ? $this->settings->reset(SiteLook::KEY_CUSTOM_CSS) : $this->settings->set(SiteLook::KEY_CUSTOM_CSS, $css);
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id, action: AuditAction::CustomCssSaved, subjectType: 'custom_css',
            after: ['length' => mb_strlen($css), 'note' => $note], ip: $ip,
        ));

        return ContentEditResult::ok(trim($css) === ''
            ? 'The fine-tuning CSS is switched off. (Your old versions are kept below.)'
            : 'Published — everyone sees it now. Every version is kept below if you need to go back.');
    }

    /** Put an old version back live (as a new history entry). */
    public function restoreCss(User $actor, int $versionId, string $ip): ContentEditResult
    {
        $css = $this->cssHistory->cssOf($versionId);
        if ($css === null) {
            return ContentEditResult::refused('That version isn\'t in the history.');
        }

        return $this->publishCss($actor, $css, sprintf('Restored version #%d', $versionId), $ip);
    }

    private function audit(User $actor, array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id, action: AuditAction::SiteLookSaved, subjectType: 'site_look', after: $after, ip: $ip,
        ));
    }
}
