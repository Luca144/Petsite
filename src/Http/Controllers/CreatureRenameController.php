<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Auth\Session;
use Felkyo\Creatures\ContentFilter;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Csrf;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Security\RateLimiter;
use Felkyo\Users\UserValidator;

/**
 * Renames a creature (the player-chosen name, like "Biscuit").
 *
 * @package Felkyo\Http\Controllers
 *
 * WHAT THIS IS: lets a creature's owner rename it. Renaming is rate-limited
 * to prevent spam, and the new name is validated (same rules as creature creation).
 *
 * WHO CAN DO IT: only the creature's owner, and only if they're logged in.
 * The WHERE clause in the repository enforces this (see CLAUDE.md, the owned-thing model).
 */
final class CreatureRenameController
{
    public function __construct(
        private Session $session,
        private Csrf $csrf,
        private CreatureRepository $creatures,
        private ContentFilter $filter,
        private UserValidator $validator,
        private RateLimiter $rateLimiter,
        private array $rateLimitConfig,
        private int $maxNameLength,
    ) {
    }

    /**
     * Update a creature's name. POST only. Validates CSRF, rate limits, and validates
     * the new name before saving.
     *
     * @param array<string, string> $parameters Captured route parameters (creature id).
     */
    public function update(Request $request, array $parameters): Response
    {
        $creatureId = (int) ($parameters['id'] ?? 0);
        $creaturePath = '/creature/' . $creatureId;

        // Must be logged in.
        $userId = $this->session->get('user_id');
        if (!is_int($userId)) {
            return Response::redirect('/login');
        }

        // The creature must exist.
        $creature = $this->creatures->findById($creatureId);
        if ($creature === null) {
            return Response::redirect('/');
        }

        // Only the owner may rename. The repository enforces this in the WHERE clause,
        // so we check here too (fail safe + feedback).
        if ($creature->ownerId !== $userId) {
            return Response::redirect($creaturePath);
        }

        // CSRF token required.
        if (!$this->csrf->isValid($request->input('_csrf_token'))) {
            return Response::redirect($creaturePath);
        }

        // Rate limit: don't let one user rename too many creatures in quick succession.
        if (!$this->rateLimiter->isAllowed(
            'rename',
            $request->clientIp(),
            $this->rateLimitConfig['max_attempts'],
            $this->rateLimitConfig['window_seconds']
        )) {
            $this->session->flash('You are renaming creatures a little too fast — take a breather.');
            return Response::redirect($creaturePath);
        }
        $this->rateLimiter->record('rename', $request->clientIp());

        // Validate the new name (same rules as creation).
        $newName = trim((string) $request->input('name'));
        $error = $this->validator->nameError($newName, $this->maxNameLength);
        if ($error !== null) {
            $this->session->flash($error);
            return Response::redirect($creaturePath);
        }

        // Check for unsafe content (slurs, contact details, etc.).
        $filtered = $this->filter->filter($newName);
        if ($filtered->isUnsafe()) {
            $this->session->flash('That name contains something not allowed here. Try something else.');
            return Response::redirect($creaturePath);
        }

        // All checks passed: save the new name.
        $this->creatures->updateName($creatureId, $userId, $newName);
        $this->session->flash('You renamed ' . $creature->name . ' to ' . $newName . '!');

        return Response::redirect($creaturePath);
    }
}
