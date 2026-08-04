<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Creatures\CreatureProfileBuilder;
use Felkyo\Creatures\CreatureRepository;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * Shows the public "browse" page — recently met creatures from around Felkyo.
 *
 * @package Felkyo\Http\Controllers
 *
 * This is a PUBLIC page: anyone, logged in or not, can browse the recent public
 * creatures and click through to their pages. Private creatures are never listed
 * (the query only returns public ones).
 */
final class BrowseController
{
    public function __construct(
        private Engine $templates,
        private CreatureRepository $creatures,
        private CreatureProfileBuilder $profileBuilder,
        private int $recentLimit,
    ) {
    }

    public function show(Request $request): Response
    {
        $creatures = $this->creatures->findRecentPublic($this->recentLimit);
        $summaries = $this->profileBuilder->summariesFor($creatures);

        $html = $this->templates->render('pages/browse', [
            'summaries' => $summaries,
        ]);

        return Response::html($html);
    }
}
