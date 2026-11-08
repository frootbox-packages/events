<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use Frootbox\Core\Action\Channel;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Application;
use Frootbox\Core\Extension\Registry;
use Frootbox\Core\Http\MiddlewareDispatcher;
use Frootbox\Core\Page\PageHandler;
use Frootbox\Core\Page\PublishPages;
use Frootbox\Core\Visitor\VisitorSession;
use Frootbox\Events\Courses\Provider\CourseProviderEntry;
use Frootbox\Events\Tests\Support\FakeCourses;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * The events with courses from a provider in memory (decision 0351), and
 * the browser of a visitor with its cookie.
 */
abstract class CoursesTestCase extends EventsTestCase
{
    protected ?string $cookie = null;

    protected function setUp(): void
    {
        FakeCourses::reset();
        parent::setUp();
        $this->cookie = null;
    }

    protected function register(Registry $registry): void
    {
        $registry->add(CourseProviderEntry::class, new CourseProviderEntry(FakeCourses::class, 'Fake'));
    }

    /**
     * A published page with blocks in its main area.
     *
     * @param list<array<string, mixed>> $blocks
     */
    protected function published(?string $parentId, string $title, array $blocks = []): string
    {
        $id = $this->page($parentId, $title, $blocks);
        $this->runner()->run(new PublishPages([$id]));

        return $id;
    }

    /**
     * A request of the browser of the visitor through all middleware.
     *
     * @param array<string, mixed>|null $post
     * @param array<string, string>     $headers
     */
    protected function browse(string $path, ?array $post = null, array $headers = []): ResponseInterface
    {
        $headers += null === $post ? [] : ['Origin' => 'https://example.org'];
        $request = new ServerRequest(null === $post ? 'GET' : 'POST', 'https://example.org' . $path, $headers);
        parse_str((string) parse_url($path, \PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query)->withCookieParams(null === $this->cookie ? [] : [VisitorSession::COOKIE => $this->cookie]);

        if (null !== $post) {
            $request = $request->withParsedBody($post);
        }

        try {
            $response = new MiddlewareDispatcher(array_map($this->get(...), Application::MIDDLEWARE), $this->get(PageHandler::class))->handle($request);
        } finally {
            $this->get(CurrentContext::class)->set(Context::system(Channel::Console));
        }

        foreach ($response->getHeader('Set-Cookie') as $header) {
            if (1 === preg_match('/^fb_visitor=([0-9a-f]*);/', $header, $match)) {
                $this->cookie = '' === $match[1] ? null : $match[1];
            }
        }

        return $response;
    }

    protected function visit(string $path): string
    {
        return (string) $this->browse($path)->getBody();
    }
}
