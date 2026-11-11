<?php

declare(strict_types=1);

namespace Felkyo\Admin;

/**
 * Every kind of action the admin audit log can record.
 *
 * @package Felkyo\Admin
 *
 * WHY AN ENUM: the audit log is the site's memory of what its staff did, and a
 * memory full of free-typed action names would be unsearchable ("was it
 * 'grant', 'granted' or 'role-add' that week?"). An enum means each kind of
 * action has exactly one name, and adding a new kind is a deliberate edit here
 * — which is also the moment to decide what its before/after snapshots hold.
 *
 * HOW THIS GROWS: every M2 increment that adds admin actions adds its cases
 * here (content publishing in M2.3, moderation actions in M2.7, maintenance
 * buttons in M2.8). The dot prefix groups related actions so the log reads in
 * families: "role.", "panel.", "second_factor.".
 */
enum AuditAction: string
{
    // A role was handed to an account, or taken from one.
    case RoleGranted = 'role.granted';
    case RoleRevoked = 'role.revoked';

    // Somebody passed the panel door (password, and code once enrolled).
    // Logged so "who was in the panel, and when" is always answerable.
    case PanelEntered = 'panel.entered';

    // An authenticator app was connected to an admin account, or reset by
    // the CLI script after a lost phone.
    case SecondFactorEnrolled = 'second_factor.enrolled';
    case SecondFactorReset = 'second_factor.reset';

    // The door was passed with a one-time recovery code instead of the app.
    // Worth its own entry: recovery codes being spent is a signal the owner
    // should see (one spent code is a lost phone; several is an attack).
    case RecoveryCodeUsed = 'recovery_code.used';

    // A picture was uploaded to, or removed from, the art store (M2.2).
    case ArtUploaded = 'art.uploaded';
    case ArtDeleted = 'art.deleted';

    // The species editor (M2.3). Saves snapshot before/after; publish and
    // unpublish are their own entries because "when did this go live?" is
    // the question the log will actually be asked.
    case SpeciesCreated = 'species.created';
    case SpeciesSaved = 'species.saved';
    case SpeciesPublished = 'species.published';
    case SpeciesUnpublished = 'species.unpublished';
    case SpeciesReverted = 'species.reverted';

    // The item and shop editors (M2.4) — the same five life events each.
    case ItemCreated = 'item.created';
    case ItemSaved = 'item.saved';
    case ItemPublished = 'item.published';
    case ItemUnpublished = 'item.unpublished';
    case ItemReverted = 'item.reverted';
    case ShopCreated = 'shop.created';
    case ShopSaved = 'shop.saved';
    case ShopPublished = 'shop.published';
    case ShopUnpublished = 'shop.unpublished';
    case ShopReverted = 'shop.reverted';

    // The area editor (M2.5) — the same five life events.
    case AreaCreated = 'area.created';
    case AreaSaved = 'area.saved';
    case AreaPublished = 'area.published';
    case AreaUnpublished = 'area.unpublished';
    case AreaReverted = 'area.reverted';

    // Moderation (M2.7) — the report queue and the account actions.
    case ReportDismissed = 'report.dismissed';
    case CreatureTextRemoved = 'creature.text_removed';
    case PlayerTextRemoved = 'player.text_removed';
    case CreatureRenamed = 'creature.renamed';
    case PlayerRenamed = 'player.renamed';
    case PlayerWarned = 'player.warned';
    case PlayerMuted = 'player.muted';
    case PlayerUnmuted = 'player.unmuted';
    case PlayerSuspended = 'player.suspended';
    case PlayerUnsuspended = 'player.unsuspended';

    // Settings & maintenance (M2.8).
    case SettingChanged = 'setting.changed';
    case SettingReset = 'setting.reset';
    case RateLimitsCleared = 'maintenance.rate_limits_cleared';
    case ArtBackupDownloaded = 'maintenance.art_backup_downloaded';

    // M5 — the library: alternate forms and the Gazette.
    case FormSaved = 'form.saved';
    case ArticleSaved = 'article.saved';
    // M3 — traits and the level-title / age ladders.
    case CharacterSaved = 'character.saved';
    // M4 — themes.
    case ThemeSaved = 'theme.saved';
    // Deleting content for good (draft only, typed confirmation).
    case SpeciesDeleted = 'species.deleted';
    case ItemDeleted = 'item.deleted';
    // Postcard designs, and gifts from the owner.
    case PostcardSaved = 'postcard.saved';
    case GiftGiven = 'gift.given';
    // Bug reports ticked off; the site's words, look and fine-tuning CSS.
    case BugReportHandled = 'bug_report.handled';
    case TextSaved = 'text.saved';
    case SiteLookSaved = 'site_look.saved';
    case CustomCssSaved = 'custom_css.saved';

    /**
     * What a person reading the audit page sees, in plain words.
     */
    public function label(): string
    {
        return match ($this) {
            self::RoleGranted => 'granted a role',
            self::RoleRevoked => 'took a role away',
            self::PanelEntered => 'entered the panel',
            self::SecondFactorEnrolled => 'connected an authenticator app',
            self::SecondFactorReset => 'reset an authenticator app',
            self::RecoveryCodeUsed => 'used a recovery code at the door',
            self::ArtUploaded => 'uploaded a picture',
            self::ArtDeleted => 'deleted a picture',
            self::SpeciesCreated => 'started a new species',
            self::SpeciesSaved => 'saved a species',
            self::SpeciesPublished => 'published a species',
            self::SpeciesUnpublished => 'took a species back to draft',
            self::SpeciesReverted => 'restored an older version of a species',
            self::ItemCreated => 'started a new item',
            self::ItemSaved => 'saved an item',
            self::ItemPublished => 'published an item',
            self::ItemUnpublished => 'took an item back to draft',
            self::ItemReverted => 'restored an older version of an item',
            self::ShopCreated => 'started a new shop',
            self::ShopSaved => 'saved a shop',
            self::ShopPublished => 'published a shop',
            self::ShopUnpublished => 'took a shop back to draft',
            self::ShopReverted => 'restored an older version of a shop',
            self::AreaCreated => 'started a new area',
            self::AreaSaved => 'saved an area',
            self::AreaPublished => 'published an area',
            self::AreaUnpublished => 'took an area back to draft',
            self::AreaReverted => 'restored an older version of an area',
            self::ReportDismissed => 'dismissed a report',
            self::CreatureTextRemoved => 'removed a creature\'s bio',
            self::PlayerTextRemoved => 'removed a player\'s about text',
            self::CreatureRenamed => 'gave a creature a neutral name',
            self::PlayerRenamed => 'gave a player a neutral name',
            self::PlayerWarned => 'sent a player a warning notice',
            self::PlayerMuted => 'muted a player for a while',
            self::PlayerUnmuted => 'lifted a player\'s mute',
            self::PlayerSuspended => 'suspended an account for a while',
            self::PlayerUnsuspended => 'lifted an account\'s suspension',
            self::SettingChanged => 'changed a site setting',
            self::SettingReset => 'put a site setting back to its built-in value',
            self::RateLimitsCleared => 'cleared the rate-limit slate',
            self::ArtBackupDownloaded => 'downloaded the art backup',
            self::FormSaved => 'changed an alternate form',
            self::ArticleSaved => 'changed a Gazette article',
            self::CharacterSaved => 'changed traits or titles',
            self::ThemeSaved => 'changed a theme',
            self::SpeciesDeleted => 'deleted a species',
            self::ItemDeleted => 'deleted an item',
            self::PostcardSaved => 'changed a postcard design',
            self::GiftGiven => 'gave a player a gift',
            self::BugReportHandled => 'ticked off a bug report',
            self::TextSaved => 'changed some of the site\'s words',
            self::SiteLookSaved => 'changed the site\'s look',
            self::CustomCssSaved => 'changed the fine-tuning CSS',
        };
    }
}
