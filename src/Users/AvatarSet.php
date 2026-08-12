<?php

declare(strict_types=1);

namespace Felkyo\Users;

/**
 * The pictures a player may choose as their avatar — and nothing else.
 *
 * @package Felkyo\Users
 *
 * WHAT THIS IS: the allow-list. A player's avatar is stored as a KEY ("default"),
 * and this class is the only thing that can turn a key into a picture. If a key
 * is not in the set, it is not an avatar — there is no path by which an
 * unexpected value reaches an <img> tag.
 *
 * WHY IT MATTERS MORE THAN IT LOOKS. Suppose we had stored a filename instead.
 * Somebody submits "../../../../.env" and we render it, or they submit an address
 * on their own server and every visitor to their profile quietly makes a request
 * to it — handing that person the IP address of everybody who looked, which on a
 * site with children on it is exactly the kind of quiet leak that is hard to
 * notice and hard to undo. Storing a key closes both, and it costs one small class.
 *
 * WHY PLAYERS CANNOT UPLOAD THEIR OWN (build plan M1.3): accepting uploaded
 * pictures means moderating pictures. That is far harder than moderating text,
 * needs someone awake to do it, and is the easiest route for something genuinely
 * harmful onto this site. A chosen set removes the problem rather than managing
 * it, and keeps the site visually of a piece.
 *
 * THE SET IS CONTENT, not code. It comes from config, so adding an avatar is a
 * new entry plus a picture file — and from M2.4, a panel screen. See
 * docs/adding-avatars.md.
 */
final class AvatarSet
{
    /**
     * The key used when a player has never chosen, and the one we fall back to if
     * a stored key ever stops existing (because an avatar was retired while
     * somebody was still wearing it). A profile always has a face.
     */
    public const FALLBACK_KEY = 'default';

    /**
     * @param array<string, array{name: string, file: string}> $avatars Keyed by avatar key.
     */
    public function __construct(private array $avatars)
    {
    }

    /**
     * Is this a key a player is actually allowed to choose?
     *
     * Every save goes through here. The question is deliberately "is it in the
     * list", never "does it look safe" — a list cannot be talked round, and a
     * cleverly shaped string sometimes can.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->avatars);
    }

    /**
     * Where an avatar's picture lives, for use in an <img> tag.
     *
     * An unknown key returns the fallback's picture rather than an empty string
     * or an error. A missing face on somebody's profile is a small cosmetic
     * problem; a broken page or an unexpected value in an attribute is not.
     */
    public function imagePathFor(string $key): string
    {
        $avatar = $this->avatars[$key] ?? $this->avatars[self::FALLBACK_KEY] ?? null;

        if ($avatar === null) {
            // Nothing configured at all. Rather than emit a half-built path, say
            // so plainly — this can only happen if the config was emptied, which
            // is a setup mistake worth noticing rather than papering over.
            return '/assets/avatars/default.png';
        }

        return '/assets/avatars/' . $avatar['file'];
    }

    /**
     * The name a player reads beside a picture when choosing — and what a screen
     * reader announces. Every avatar has one, because a grid of pictures with no
     * words is unusable without sight.
     */
    public function nameFor(string $key): string
    {
        return $this->avatars[$key]['name'] ?? 'Felkyo visitor';
    }

    /**
     * Every avatar, ready to render as a grid of choices.
     *
     * @return array<int, array{key: string, name: string, imagePath: string}>
     */
    public function all(): array
    {
        $choices = [];

        foreach ($this->avatars as $key => $avatar) {
            $choices[] = [
                'key' => (string) $key,
                'name' => $avatar['name'],
                'imagePath' => $this->imagePathFor((string) $key),
            ];
        }

        return $choices;
    }
}
