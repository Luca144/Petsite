<?php

declare(strict_types=1);

namespace Felkyo\Admin;

use Felkyo\Creatures\SpeciesEditorService;
use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Economy\ItemEditorService;
use Felkyo\Economy\ItemRepository;
use Felkyo\Economy\ShopRepository;
use Felkyo\Exploration\AreaEditorService;
use Felkyo\Exploration\AreaRepository;

/**
 * The unfinished list: everything across the panel that still needs a hand.
 *
 * @package Felkyo\Admin
 *
 * One generated answer to "what still needs doing?", so nobody holds that
 * list in their head or asks another person (build plan M2.6). It READS
 * and never writes. Two tiers, because two different questions:
 *
 *  - BLOCKED: drafts that cannot publish yet, with the missing pieces in
 *    the editors' own words — the four editors' publish rules are REUSED
 *    here (missingForPublish), never copied, so this screen and the
 *    publish buttons can never disagree.
 *  - NICETIES: published content the site renders with a fallback — an
 *    item wearing its category's icon, an area scene on the glow
 *    placeholder, a keeper with nothing to say. Nothing is broken; this
 *    is the artist's to-do list, and the screen says so.
 *
 * Every row carries the editor link that fixes it (golden rule 3 — never
 * a dead end).
 */
final class UnfinishedListService
{
    public function __construct(
        private SpeciesRepository $species,
        private ItemRepository $items,
        private ShopRepository $shops,
        private AreaRepository $areas,
        private SpeciesEditorService $speciesEditor,
        private ItemEditorService $itemEditor,
        private AreaEditorService $areaEditor,
    ) {
    }

    /**
     * Everything unfinished, gathered fresh on every view. Four all()
     * reads on content counted in dozens — the same queries the four list
     * screens run. If content ever reaches thousands, cache the result;
     * don't restructure.
     *
     * @return array{blocked: array<int, array{what: string, name: string, href: string, needs: string}>,
     *               niceties: array<int, array{what: string, name: string, href: string, needs: string}>}
     */
    public function gather(): array
    {
        $blocked = [];
        $niceties = [];

        foreach ($this->species->all() as $species) {
            $href = '/admin/creatures/' . $species->id;
            if (!$species->isPublished()) {
                $blocked[] = $this->row('creature', $species->name, $href,
                    $this->blockedWords($this->speciesEditor->missingForPublish($species)));
                continue; // a draft is one job ("finish it"), not two rows of nagging
            }
            $lacks = [];
            // Lore and library art aren't publish requirements — the library
            // itself arrives in M5 — but writing them NOW is how M5 opens
            // with a full shelf instead of an empty one.
            if (trim((string) $species->lore) === '') {
                $lacks[] = 'its library lore';
            }
            if ($species->libraryArtId === null) {
                $lacks[] = 'its library art';
            }
            if ($lacks !== []) {
                $niceties[] = $this->row('creature', $species->name, $href, 'could still use ' . implode(' and ', $lacks));
            }
        }

        foreach ($this->items->all() as $item) {
            $href = '/admin/items/' . $item->id;
            if (!$item->isPublished()) {
                $blocked[] = $this->row('item', $item->name, $href,
                    $this->blockedWords($this->itemEditor->missingForPublish($item)));
                continue;
            }
            // No icon of its own means the three-source cascade fell through
            // to the CATEGORY icon: not broken, but every item in that
            // category then looks the same in a pocket.
            if (!$item->hasStoredIcon() && !$this->conventionIconExists($item->slug)) {
                $niceties[] = $this->row('item', $item->name, $href,
                    'has no icon of its own — it wears its category\'s icon for now');
            }
        }

        foreach ($this->shops->allShops() as $shop) {
            $href = '/admin/shops/' . $shop->id;
            if (!$shop->isPublished()) {
                // A shop's publish minimum is simply its name (which creating
                // it already required) — so a draft shop is never "missing"
                // anything, it is just waiting for the word.
                $blocked[] = $this->row('shop', $shop->name, $href, 'ready — just not published yet');
                continue;
            }
            $lacks = [];
            if (trim((string) $shop->keeperName) === '') {
                $lacks[] = 'a keeper';
            } elseif (trim((string) $shop->keeperGreeting) === '') {
                $lacks[] = 'the keeper\'s greeting';
            }
            if ($shop->backgroundArtId === null) {
                $lacks[] = 'a shopfront picture';
            }
            if ($lacks !== []) {
                $niceties[] = $this->row('shop', $shop->name, $href, 'could still use ' . implode(' and ', $lacks));
            }
        }

        foreach ($this->areas->all() as $area) {
            $href = '/admin/areas/' . $area->id;
            if (!$area->isPublished()) {
                $blocked[] = $this->row('area', $area->name, $href,
                    $this->blockedWords($this->areaEditor->missingForPublish($area)));
                continue;
            }
            if ($area->backgroundArtId === null) {
                $niceties[] = $this->row('area', $area->name, $href,
                    'has no scene picture — players see the lamplight glow');
            }
        }

        return ['blocked' => $blocked, 'niceties' => $niceties];
    }

    /** One printable row. */
    private function row(string $what, string $name, string $href, string $needs): array
    {
        return ['what' => $what, 'name' => $name, 'href' => $href, 'needs' => $needs];
    }

    /** The blocked tier's sentence, from an editor's own missing-pieces list. */
    private function blockedWords(array $missing): string
    {
        return $missing === []
            ? 'ready — just not published yet'
            : 'still needs ' . implode(', ', $missing);
    }

    /**
     * Does the pre-panel convention file exist for this item? The same
     * on-disk question the item editor answers when it says which icon
     * source an item is using.
     */
    private function conventionIconExists(string $slug): bool
    {
        return is_file(dirname(__DIR__, 2) . '/public/assets/items/' . $slug . '.png');
    }
}
