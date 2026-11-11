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
 * are said so honestly, with the increment named — a moderator with no queue
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
                'sentence' => 'Who can do what — grant and take away the four staff roles.',
                'href' => '/admin/roles',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Gifts',
                'sentence' => 'Give a player gems, items or a creature — to make things right, or just because.',
                'href' => '/admin/gifts',
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
                'sentence' => 'The art store — upload sprites, icons, portraits, cards and backgrounds.',
                'href' => '/admin/pictures',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Creatures',
                'sentence' => 'One page per species — sprites, words, stats; draft it, preview it, publish it.',
                'href' => '/admin/creatures',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Items',
                'sentence' => 'What can be owned, eaten and sold — prices, effects, icons.',
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
                'sentence' => 'The places to explore — scene, search spots, and the loot table.',
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
                'title' => 'Traits & titles',
                'sentence' => 'Who a creature is: its traits, the word for its level, and how its age is said.',
                'href' => '/admin/character',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Gazette & updates',
                'sentence' => 'Write the newspaper, and post site updates for the home page.',
                'href' => '/admin/gazette',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'The welcome',
                'sentence' => 'What the shopkeeper says to show a new player around.',
                'href' => '/admin/welcome',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Badges',
                'sentence' => 'Keepsakes creatures earn by being kept a long time, and special ones for players.',
                'href' => '/admin/badges',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Postcards',
                'sentence' => 'The cards on the post office rack: picture, words and price.',
                'href' => '/admin/postcards',
                'arrives' => null,
            ];
            $sections[] = [
                'title' => 'Themes',
                'sentence' => 'Colour sets players can choose — readability checked while you pick.',
                'href' => '/admin/themes',
                'arrives' => null,
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
            $sections[] = [
                'title' => 'Bug reports',
                'sentence' => 'What players told us is broken, from the page where it happened.',
                'href' => '/admin/bugs',
                'arrives' => null,
            ];
        }

        return $sections;
    }
}
