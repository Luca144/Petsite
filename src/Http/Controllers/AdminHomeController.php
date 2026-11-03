<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\Role;
use Felkyo\Admin\RoleRepository;
use Felkyo\Auth\Session;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * The panel's front page: tiles for the sections YOUR roles open.
 *
 * @package Felkyo\Http\Controllers
 *
 * Each staff member sees only their own doors (build plan M2.1: "each seeing
 * only what it needs"). Sections whose screens arrive in a later increment
 * are said so honestly, with the increment named â a moderator with no queue
 * yet deserves "your tools arrive with M2.7", not a broken tile and a guess.
 *
 * HOW THIS GROWS: each M2 increment that adds a section adds its tile in
 * sectionsFor() below, keyed to the role that owns it. The gate on the ROUTE
 * is what protects a section; these tiles are only signposts.
 */
final class AdminHomeController
{
    public function __construct(
        private Engine $templates,
        private Session $session,
        private RoleRepository $roles,
    ) {
    }

    public function show(Request $request): Response
    {
        $heldRoles = $this->roles->rolesFor((int) $this->session->get('user_id'));

        return Response::html($this->templates->render('pages/admin-home', [
            'heldRoles' => $heldRoles,
            'sections' => $this->sectionsFor($heldRoles),
        ]));
    }

    /**
     * The tiles this set of roles may see. Owner sees everything, matching
     * the AdminGate rule, so the signposts and the doors always agree.
     *
     * Each tile: title, one plain sentence, and either a link (the screen
     * exists) or the increment it arrives with (it does not yet).
     *
     * @param Role[] $heldRoles
     * @return array<int, array{title: string, sentence: string, href: ?string, arrives: ?string}>
     */
    private function sectionsFor(array $heldRoles): array
    {
        $isOwner = in_array(Role::Owner, $heldRoles, true);
        $sections = [];

        if ($isOwner) {
            $sections[] = [
                'title' => 'Roles',
                'sentence' => 'Who can do what â grant and take away the four staff roles.',
                'href' => '/admin/roles',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Audit log',
                'sentence' => 'Everything staff have done here, newest first.',
                'href' => '/admin/audit',
                'arrives' => null,
            ];
        }

        if ($isOwner || in_array(Role::Artist, $heldRoles, true)) {
            $sections[] = [
                'title' => 'Pictures',
                'sentence' => 'The art store â upload sprites, icons, portraits, cards and backgrounds.',
                'href' => '/admin/pictures',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Creatures',
                'sentence' => 'One page per species â sprites, words, stats; draft it, preview it, publish it.',
                'href' => '/admin/creatures',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Items',
                'sentence' => 'What can be owned, eaten and sold â prices, effects, icons.',
                'href' => '/admin/items',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Shops',
                'sentence' => 'The shopfronts: keeper, background, and what is on the shelves when.',
                'href' => '/admin/shops',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Areas',
                'sentence' => 'The places to explore â scene, search spots, and the loot table.',
                'href' => '/admin/areas',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Unfinished',
                'sentence' => 'What still needs a hand — the site keeps the list so you need not.',
                'href' => '/admin/unfinished',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Stats',
                'sentence' => 'The game\'s health in aggregate — who plays, what gets adopted, where people wander.',
                'href' => '/admin/stats',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'The Gazette',
                'sentence' => 'Write the newspaper: stories about the people of Felkyo, read in the library.',
                'href' => '/admin/gazette',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Postcards & themes',
                'sentence' => 'Each editor arrives with its content: cards with M6, themes with M4.',
                'href' => null,
                'arrives' => 'M4/M6',
            ];
        }

        if ($isOwner || in_array(Role::Moderator, $heldRoles, true)) {
            $sections[] = [
                'title' => 'Moderation',
                'sentence' => 'The report queue, account history and moderation actions.',
                'href' => '/admin/reports',
                'arrives' => null,
            ];
        }

        if ($isOwner || in_array(Role::Coder, $heldRoles, true)) {
            $sections[] = [
                'title' => 'Settings & maintenance',
                'sentence' => 'The game\'s tunable numbers, and the named maintenance buttons.',
                'href' => '/admin/settings',
                'arrives' => null,
            ];
        }

        return $sections;
    }
}
