<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Backend;

use Frootbox\Backend\View\BackendView;
use Frootbox\Core\Action\Runner;
use Frootbox\Events\Event\ListEvents;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The screen "Events": the events, newest first, with a search and pages
 * (decision 0220).
 */
final readonly class EventsHandler implements RequestHandlerInterface
{
    public const string PERMISSION = 'frootbox/events/event.list';

    private const int PER_PAGE = 50;

    public function __construct(
        private BackendView $view,
        private Runner $runner,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (null === $this->view->user()) {
            return $this->view->requireUser($request) ?? $this->view->redirect('/');
        }

        $params = $request->getQueryParams();
        $search = \is_string($params['q'] ?? null) ? mb_substr(trim($params['q']), 0, 100) : '';
        $page = \is_string($params['page'] ?? null) && ctype_digit($params['page']) ? max(1, (int) $params['page']) : 1;
        $list = $this->runner->ask(new ListEvents(search: '' === $search ? null : $search, page: $page, perPage: self::PER_PAGE));

        return $this->view->render($request, '@frootbox/events/backend/events.twig', [
            'list' => $list,
            'search' => $search,
        ]);
    }
}
