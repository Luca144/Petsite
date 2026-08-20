<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Auth\PasswordHasher;
use PDO;

/**
 * Stores each admin account's TOTP secret and one-time recovery codes.
 *
 * @package Felkyo\Admin
 *
 * WHAT THIS CLASS IS: the only place that runs SQL for admin_second_factors
 * and admin_recovery_codes. Keeping the TOTP secret behind this one class —
 * and out of the users table — means the everywhere-used UserRepository can
 * never accidentally load it onto a page.
 *
 * SECRETS VS CODES: the TOTP secret is stored as plain text because the site
 * must COMPUTE codes from it (a hash would make that impossible — this is the
 * same reason a session key can't be hashed). Recovery codes are the
 * opposite: the site only ever needs to CHECK one, never regenerate it, so
 * they are hashed like passwords and a copy of the database is not a copy of
 * the keys.
 */
final class SecondFactorRepository
{
    public function __construct(
        private PDO $connection,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * The user's TOTP secret, or null if they have not enrolled yet — which
     * is also how the panel door decides whether to ask for a code.
     */
    public function findSecret(int $userId): ?string
    {
        $statement = $this->connection->prepare(
            'SELECT totp_secret FROM admin_second_factors WHERE user_id = :user_id LIMIT 1'
        );
        $statement->execute([':user_id' => $userId]);

        $secret = $statement->fetchColumn();

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    /**
     * Save (or replace) the user's TOTP secret. Replacing supports the
     * lost-phone path: bin/reset-second-factor.php clears the enrolment and
     * the person enrols again with a fresh secret.
     */
    public function saveSecret(int $userId, string $secret): void
    {
        // The unique user_id index turns this into "insert, or overwrite the
        // existing enrolment" in one atomic statement.
        $statement = $this->connection->prepare(
            'INSERT INTO admin_second_factors (user_id, totp_secret)
             VALUES (:user_id, :secret)
             ON DUPLICATE KEY UPDATE totp_secret = VALUES(totp_secret), enrolled_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([':user_id' => $userId, ':secret' => $secret]);
    }

    /**
     * Remove an enrolment entirely — secret AND recovery codes — so the
     * account is back to "not enrolled" and must set up a fresh app at the
     * next panel entry. Used only by the lost-phone CLI script.
     */
    public function reset(int $userId): void
    {
        // Two deletes, one intent: an enrolment without its codes (or codes
        // without their enrolment) would be a half-state nothing expects.
        $this->connection->prepare(
            'DELETE FROM admin_second_factors WHERE user_id = :user_id'
        )->execute([':user_id' => $userId]);

        $this->connection->prepare(
            'DELETE FROM admin_recovery_codes WHERE user_id = :user_id'
        )->execute([':user_id' => $userId]);
    }

    /**
     * Store a fresh set of recovery codes (hashed), replacing any old ones.
     * Called at enrolment; the plain codes are shown to the person exactly
     * once and never stored.
     *
     * @param string[] $plainCodes
     */
    public function replaceRecoveryCodes(int $userId, array $plainCodes): void
    {
        // Old codes die with the old enrolment — a code written down last
        // year must not open the door after a re-enrolment.
        $this->connection->prepare(
            'DELETE FROM admin_recovery_codes WHERE user_id = :user_id'
        )->execute([':user_id' => $userId]);

        $insert = $this->connection->prepare(
            'INSERT INTO admin_recovery_codes (user_id, code_hash) VALUES (:user_id, :code_hash)'
        );
        foreach ($plainCodes as $plainCode) {
            $insert->execute([
                ':user_id' => $userId,
                ':code_hash' => $this->passwordHasher->hash($plainCode),
            ]);
        }
    }

    /**
     * Try to spend one recovery code. Returns true if the code matched an
     * UNUSED one, which is then marked used; false otherwise.
     *
     * SINGLE-USE IS ENFORCED ATOMICALLY: the UPDATE only stamps used_at where
     * it is still NULL, so if the same code arrives twice at once, exactly one
     * request wins the row and the other is refused — a recovery code can
     * never open the door twice.
     */
    public function spendRecoveryCode(int $userId, string $plainCode): bool
    {
        // Codes are hashed, so we cannot look one up — we check the typed
        // code against each unused hash. An account has at most a handful of
        // codes, so this loop is a few verify() calls, not a scan.
        $statement = $this->connection->prepare(
            'SELECT id, code_hash FROM admin_recovery_codes
              WHERE user_id = :user_id AND used_at IS NULL'
        );
        $statement->execute([':user_id' => $userId]);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!$this->passwordHasher->verify($plainCode, (string) $row['code_hash'])) {
                continue;
            }

            // The atomic claim described above.
            $claim = $this->connection->prepare(
                'UPDATE admin_recovery_codes SET used_at = NOW()
                  WHERE id = :id AND used_at IS NULL'
            );
            $claim->execute([':id' => (int) $row['id']]);

            return $claim->rowCount() > 0;
        }

        return false;
    }

    /**
     * How many recovery codes this user still has unspent — shown after the
     * door so a person notices their supply shrinking (spent codes they did
     * not spend themselves are the alarm).
     */
    public function countUnusedRecoveryCodes(int $userId): int
    {
        $statement = $this->connection->prepare(
            'SELECT COUNT(*) FROM admin_recovery_codes
              WHERE user_id = :user_id AND used_at IS NULL'
        );
        $statement->execute([':user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }
}
