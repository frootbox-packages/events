<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Calendar;

use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\Http\SiteUrl;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves the calendar file of an event; 404 without a published event.
 */
final readonly class CalendarHandler implements RequestHandlerInterface
{
    public function __construct(
        private Calendar $calendar,
        private SiteUrl $site,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        $language = $request->getAttribute('language');
        $ics = \is_string($id) && \is_string($language) ? $this->calendar->ics($id, $language, $this->site->base($request)) : null;

        if (null === $ics) {
            throw HttpException::notFound($request->getUri()->getPath());
        }

        return $this->responses->createResponse()
            ->withHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $id . '.ics"')
            ->withHeader('Cache-Control', 'public, max-age=300')
            ->withBody($this->streams->createStream($ics));
    }
}
