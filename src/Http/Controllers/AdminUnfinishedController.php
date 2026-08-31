<?php

declare(strict_types=1);

namespace Felkyo\Http\Controllers;

use Felkyo\Admin\UnfinishedListService;
use Felkyo\Http\Request;
use Felkyo\Http\Response;
use League\Plates\Engine;

/**
 * The unfinished list's one screen (M2.6).
 *
 * @package Felkyo\Http\Controllers
 *
 * Read-only, no input, no writes — the smallest controller in the panel.
 * The gate (Role::Artist) is on the route in public/index.php, like every
 * panel door; the gathering itself lives in UnfinishedListService.
 */
final class AdminUnfinishedController
{
    public function __construct(
        private Engine $templates,
        private UnfinishedListService $unfinished,
    ) {
    }

    public function show(Request $request): Response
    {
        $gathered = $this->unfinished->gather();

        return Response::html($this->templates->render('pages/admin-unfinished', [
            'blocked' => $gathered['blocked'],
            'niceties' => $gathered['niceties'],
        ]));
    }
}
