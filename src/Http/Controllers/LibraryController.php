<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Creatures\SpeciesRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use Felkyo\Library\ArticleRepository;
use Felkyo\Library\SpeciesFormRepository;
use League\Plates\Engine;

/**
 * The library (M5) — Felkyo's book of creatures, and its newspaper.
 *
 * @package Felkyo\Http\Controllers
 *
 * Three PUBLIC pages, open to guests too — the library is how anybody works
 * out what lives in Felkyo and what to hope for:
 *
 *   GET /library             the shelf: a book per species (tab "creatures"),
 *                            or the Gazette's front page (tab "gazette")
 *   GET /library/{slug}      one species' book: every stage with its blurb,
 *                            its alternate forms, and the long story
 *   GET /gazette/{slug}      one Gazette article
 *
 * Only PUBLISHED species, forms and articles ever appear; drafts are the
 * artist's secret. Read-only pages, so no CSRF and no rate limit are needed.
 */
final class LibraryController
{
    /**
     * @param string[] $stages the life stages in growth order (config keys).
     */
    public function __construct(
        private Engine $templates,
        private SpeciesRepository $species,
        private SpeciesFormRepository $forms,
        private ArticleRepository $articles,
        private array $stages,
        // Item pages ("where does this come from?"). Optional so older
        // wiring still builds.
        private ?\Felkyo\Economy\ItemRepository $items = null,
        private ?\Felkyo\Library\ItemSourcesRepository $itemSources = null,
    ) {
    }

    /**
     * One item's library page: what it is, and where to get it — the shops
     * that sell it and the areas whose loot can turn it up (Skerro,
     * 2026-10-13). Only published items have a page.
     *
     * @param array{id: string} $parameters
     */
    public function item(Request $request, array $parameters): Response
    {
        $item = $this->items?->findById((int) ($parameters['id'] ?? 0));
        if ($item === null || !$item->isPublished() || $this->itemSources === null) {
            return $this->notFound();
        }

        return Response::html($this->templates->render('pages/library-item', [
            'item' => $item,
            'shops' => $this->itemSources->shopsSelling($item->id),
            'areas' => $this->itemSources->areasDropping($item->id),
        ]));
    }

    /** The shelf, on one of its two tabs. */
    public function index(Request $request): Response
    {
        $tab = $request->query('tab') === 'gazette' ? 'gazette' : 'creatures';

        return Response::html($this->templates->render('pages/library', [
            'tab' => $tab,
            'shelf' => $tab === 'creatures' ? $this->species->findPublished() : [],
            'articles' => $tab === 'gazette' ? $this->articles->published(50) : [],
            // The shelf shows each species as it looks grown up — the stage
            // a player is hoping to see.
            'coverStage' => $this->stages[count($this->stages) - 1] ?? 'adult',
        ]));
    }

    /** @param array{slug: string} $parameters */
    public function species(Request $request, array $parameters): Response
    {
        $species = $this->species->findPublishedBySlug((string) ($parameters['slug'] ?? ''));
        if ($species === null) {
            return $this->notFound();
        }

        return Response::html($this->templates->render('pages/library-species', [
            'species' => $species,
            'stages' => $this->stages,
            'forms' => $this->forms->forSpecies($species->id, true),
        ]));
    }

    /** Every site update, newest first — the site's timeline. */
    public function updates(Request $request): Response
    {
        return Response::html($this->templates->render('pages/updates', [
            'updates' => $this->articles->published(200, \Felkyo\Library\Article::KIND_UPDATE),
        ]));
    }

    /** @param array{slug: string} $parameters */
    public function article(Request $request, array $parameters): Response
    {
        $article = $this->articles->findPublishedBySlug((string) ($parameters['slug'] ?? ''));
        if ($article === null) {
            return $this->notFound();
        }

        return Response::html($this->templates->render('pages/gazette-article', ['article' => $article]));
    }

    private function notFound(): Response
    {
        return Response::html($this->templates->render('pages/not-found', [
            'message' => 'That page isn\'t on the library shelves. Try the library itself — everything published lives there.',
        ]), 404);
    }
}
