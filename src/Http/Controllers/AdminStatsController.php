<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\SiteStatsService;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * The stats page (M2.9) — read-only, no input, aggregate only.
 *
 * @package Felkyo\Http\Controllers
 *
 * The gate (Role::Artist) is on the route; the privacy rule (counts and
 * sums, never people) is enforced in SiteStatsService and pinned by its
 * test. This controller only carries the numbers to the template.
 */
final class AdminStatsController
{
    public function __construct(
        private Engine $templates,
        private SiteStatsService $stats,
    ) {
    }

    public function show(Request $request): Response
    {
        return Response::html($this->templates->render('pages/admin-stats', [
            'stats' => $this->stats->gather(),
        ]));
    }
}
