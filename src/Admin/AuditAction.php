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
        };
    }
}
