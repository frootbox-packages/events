<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Runner;
use Frootbox\Core\Http\Exception\HttpException;
use Frootbox\Core\I18n\Translator;
use Frootbox\Core\I18n\VisitorFormality;
use Frootbox\Core\Language\Languages;
use Frootbox\Core\Support\Message;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The booking form on the website at POST /_events/booking (decision
 * 0351), like the forms of the shop: passes the fields to the action
 * frootbox/events/booking.place and leads back to the page (303), with a
 * message and the entries in the session; works without JavaScript. Only
 * from pages of this website (Origin). A filled-in trap field never books.
 */
final readonly class BookingForm implements RequestHandlerInterface
{
    public const string ROUTE = '/_events/booking';

    /** The field people do not see and bots fill in. */
    public const string TRAP = 'website';

    public function __construct(
        private Runner $runner,
        private BookingSession $session,
        private Translator $translator,
        private VisitorFormality $formality,
        private Languages $languages,
        private ResponseFactoryInterface $responses,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!self::sameOrigin($request)) {
            throw new HttpException(403, 'The booking form comes only from pages of this website.');
        }

        $body = $request->getParsedBody();
        $body = \is_array($body) ? $body : [];
        $language = \is_string($body['_language'] ?? null) && $this->languages->has($body['_language']) ? $body['_language'] : $this->languages->default()->code;
        $back = self::back(\is_string($body['_return'] ?? null) ? $body['_return'] : null);
        $input = self::input($body);

        if (\is_string($body[self::TRAP] ?? null) && '' !== trim($body[self::TRAP])) {
            $this->session->flash($this->translate($language, new Message('Please check your entries.')), [], $input);

            return $this->redirect($back);
        }

        try {
            $result = $this->runner->call(PlaceBooking::NAME, $input);
        } catch (InvalidInputException $error) {
            $errors = [];

            foreach ($error->messages as $pointer => $message) {
                $errors[ltrim($pointer, '/')] = $this->translate($language, $message);
            }

            $this->session->flash($this->translate($language, new Message('Please check your entries.')), $errors, $input);

            return $this->redirect($back);
        } catch (ActionException|EntityNotFoundException $error) {
            $this->session->flash($this->translate($language, $error instanceof ActionException ? $error->template : new Message('This date is no longer there.')), [], $input);

            return $this->redirect($back);
        }

        $message = $result instanceof BookingConfirmation && '' !== $result->number
            ? new Message('Thank you for your booking {number}. A confirmation is on its way by email.', ['number' => $result->number])
            : new Message('Thank you for your booking. A confirmation is on its way by email.');
        $this->session->flash($this->translate($language, $message), success: true);

        return $this->redirect($back);
    }

    /**
     * The input of the action from the fields of the form.
     *
     * @param array<mixed> $body
     *
     * @return array<string, mixed>
     */
    private static function input(array $body): array
    {
        $input = [
            'date' => \is_string($body['date'] ?? null) ? $body['date'] : '',
            'key' => \is_string($body['key'] ?? null) ? $body['key'] : '',
            'expectedGross' => is_numeric($body['expectedGross'] ?? null) ? (int) $body['expectedGross'] : 0,
            'booker' => self::strings($body['booker'] ?? null),
            'attendees' => array_values(array_map(self::strings(...), array_filter(\is_array($body['attendees'] ?? null) ? $body['attendees'] : [], \is_array(...)))),
            'fields' => self::strings($body['fields'] ?? null),
            'consents' => array_values(array_filter(\is_array($body['consents'] ?? null) ? $body['consents'] : [], \is_string(...))),
        ];

        if (\is_string($body['message'] ?? null) && '' !== trim($body['message'])) {
            $input['message'] = $body['message'];
        }

        return $input;
    }

    /**
     * @return array<string, string>
     */
    private static function strings(mixed $values): array
    {
        $strings = [];

        foreach (\is_array($values) ? $values : [] as $name => $value) {
            if (\is_string($value)) {
                $strings[(string) $name] = $value;
            }
        }

        return $strings;
    }

    private function translate(string $language, Message $message): string
    {
        return $this->translator->in($language, $message->text, ['frootbox/events'], $message->parameters, $this->formality->current());
    }

    private function redirect(string $location): ResponseInterface
    {
        return $this->responses->createResponse(303)->withHeader('Location', $location)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Whether the form was sent from a page of this website: the header
     * Origin, else Referer, names this host.
     */
    private static function sameOrigin(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri();
        $own = strtolower($uri->getScheme() . '://' . $uri->getAuthority());
        $origin = $request->getHeaderLine('Origin');

        if ('' !== $origin) {
            return strtolower(rtrim($origin, '/')) === $own;
        }

        $referer = $request->getHeaderLine('Referer');

        return '' === $referer || str_starts_with(strtolower($referer), $own . '/');
    }

    /**
     * The path to lead back to: one on this website, else the start.
     */
    private static function back(?string $return): string
    {
        return null !== $return && 1 === preg_match('~^/(?![/\\\\])~', $return) && 0 === preg_match('~[\x00-\x1f]~', $return) ? $return : '/';
    }
}
