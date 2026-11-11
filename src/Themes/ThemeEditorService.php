<?php

declare(strict_types=1);

namespace Felkyo\Themes;

use Felkyo\Admin\AuditAction;
use Felkyo\Admin\AuditEntry;
use Felkyo\Admin\AuditLogRepository;
use Felkyo\Admin\ContentEditResult;
use Felkyo\Users\User;

/**
 * The panel's rules for themes (M4.1, M4.2).
 *
 * @package Felkyo\Themes
 *
 * THE ONE HARD RULE: a theme players can choose must be readable. So a
 * draft may be saved with any colours (that's how you experiment), but
 * publishing — and saving changes to a theme that is already published — is
 * refused while any ThemeTokens rule fails, with the failing rules named.
 * An unreadable theme can never reach a player.
 */
final class ThemeEditorService
{
    public function __construct(
        private ThemeRepository $themes,
        private AuditLogRepository $auditLog,
        // For checking a chosen background/banner really is site art.
        private ?\Felkyo\Art\ArtRepository $art = null,
    ) {
    }

    /**
     * Save a theme's style: fonts, corners, frames, glow, drifting symbols
     * (each checked against ThemeStyle's lists — anything else is dropped)
     * and its background and banner (each must be a picture uploaded as
     * "Site art", or nothing for the site's own).
     *
     * @param array<string, mixed> $choices
     */
    public function saveStyle(User $actor, int $id, array $choices, ?int $backgroundArtId, ?int $bannerArtId, string $ip): ContentEditResult
    {
        if ($this->themes->findById($id) === null) {
            return ContentEditResult::refused('That theme is gone.');
        }
        foreach (['background' => $backgroundArtId, 'banner' => $bannerArtId] as $what => $artId) {
            if ($artId !== null && $this->art?->findById($artId)?->kind !== \Felkyo\Art\ArtKind::Site) {
                return ContentEditResult::refused(sprintf('That %s picture isn\'t in the art store as "Site art".', $what));
            }
        }

        $style = ThemeStyle::fromChoices($choices);
        $this->themes->saveStyle($id, $style, $backgroundArtId, $bannerArtId);
        $this->audit($actor, $id, ['style' => $style->choices, 'background' => $backgroundArtId, 'banner' => $bannerArtId], $ip);

        return ContentEditResult::ok('Style saved. (Players wearing this theme see it straight away.)', $id);
    }

    public function create(User $actor, string $name, string $ip): ContentEditResult
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 40) {
            return ContentEditResult::refused('A theme needs a name of 1–40 characters.');
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        if ($slug === '' || $this->themes->slugExists($slug)) {
            return ContentEditResult::refused('There is already a theme with that name.');
        }

        $id = $this->themes->create($slug, $name);
        $this->audit($actor, $id, ['created' => $name], $ip);

        return ContentEditResult::ok('Theme started as a draft — pick its colours.', $id);
    }

    /** @param array<string, mixed> $colours token => #RRGGBB from the colour pickers */
    public function save(User $actor, int $id, string $name, array $colours, bool $easyReading, string $ip): ContentEditResult
    {
        $theme = $this->themes->findById($id);
        if ($theme === null) {
            return ContentEditResult::refused('That theme is gone.');
        }
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 40) {
            return ContentEditResult::refused('A theme needs a name of 1–40 characters.');
        }

        // Only store what differs from the site's own colours: a theme then
        // keeps following the default for everything it didn't change.
        $tokens = [];
        foreach (ThemeTokens::clean($colours) as $token => $value) {
            if ($value !== ThemeTokens::EDITABLE[$token][1]) {
                $tokens[$token] = $value;
            }
        }

        $problems = ThemeTokens::problems($tokens);
        if ($theme->isPublished() && $problems !== []) {
            return ContentEditResult::refused('Players are using this theme, so it has to stay readable: ' . implode(' ', $problems));
        }

        $this->themes->save($id, $name, $tokens, $easyReading);
        $this->audit($actor, $id, ['tokens' => $tokens, 'easy_reading' => $easyReading], $ip);

        return ContentEditResult::ok($problems === []
            ? 'Saved — and readable everywhere.'
            : 'Saved as a draft. Before it can be published: ' . implode(' ', $problems), $id);
    }

    public function setPublished(User $actor, int $id, bool $publish, string $ip): ContentEditResult
    {
        $theme = $this->themes->findById($id);
        if ($theme === null) {
            return ContentEditResult::refused('That theme is gone.');
        }
        if ($publish && ($problems = ThemeTokens::problems($theme->tokens)) !== []) {
            return ContentEditResult::refused('Not yet — ' . implode(' ', $problems));
        }

        $this->themes->setStatus($id, $publish ? Theme::STATUS_PUBLISHED : Theme::STATUS_DRAFT);
        $this->audit($actor, $id, ['status' => $publish ? 'published' : 'draft'], $ip);

        return ContentEditResult::ok($publish
            ? sprintf('"%s" is published — players can choose it now.', $theme->name)
            : sprintf('"%s" is a draft again. Anyone using it sees the normal look.', $theme->name), $id);
    }

    public function delete(User $actor, int $id, string $ip): ContentEditResult
    {
        $theme = $this->themes->findById($id);
        if ($theme === null) {
            return ContentEditResult::refused('That theme is already gone.');
        }
        $this->themes->delete($id);
        $this->audit($actor, $id, ['deleted' => $theme->name], $ip);

        return ContentEditResult::ok(sprintf('"%s" deleted. Anyone using it is back on the normal look.', $theme->name));
    }

    private function audit(User $actor, int $id, array $after, string $ip): void
    {
        $this->auditLog->record(new AuditEntry(
            actorUserId: $actor->id,
            action: AuditAction::ThemeSaved,
            subjectType: 'theme',
            subjectId: $id,
            after: $after,
            ip: $ip,
        ));
    }
}
