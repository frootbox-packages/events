<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Visitor\VisitorSession;

/**
 * The booking form in the session of the visitor (decisions 0312, 0351):
 * a message with the errors and entries for the next page, or the
 * booking that went through.
 */
final readonly class BookingSession
{
    public const string PACKAGE = 'frootbox/events';

    public function __construct(private VisitorSession $session) {}

    /**
     * Bookings come only from a visitor in a browser; the REST API and
     * MCP get 409.
     */
    public function require(): void
    {
        if (!$this->session->available()) {
            throw new ConflictException('This works only for a visitor of the website in a browser.');
        }
    }

    /**
     * @param array<string, string> $errors by field
     * @param array<string, mixed>  $values the entries to show again
     */
    public function flash(string $message, array $errors = [], array $values = [], bool $success = false): void
    {
        if ($this->session->available()) {
            $this->session->set(self::PACKAGE, 'flash', ['message' => $message, 'errors' => $errors, 'values' => $values, 'success' => $success]);
        }
    }

    /**
     * The message for this page, once.
     *
     * @return array{message: string, errors: array<string, string>, values: array<string, mixed>, success: bool}|null
     */
    public function takeFlash(): ?array
    {
        if (!$this->session->active()) {
            return null;
        }

        $flash = $this->session->get(self::PACKAGE, 'flash');

        if (!\is_array($flash)) {
            return null;
        }

        $this->session->remove(self::PACKAGE, 'flash');
        $errors = [];

        foreach (\is_array($flash['errors'] ?? null) ? $flash['errors'] : [] as $field => $message) {
            if (\is_string($message)) {
                $errors[(string) $field] = $message;
            }
        }

        $values = [];

        foreach (\is_array($flash['values'] ?? null) ? $flash['values'] : [] as $field => $value) {
            $values[(string) $field] = $value;
        }

        return [
            'message' => \is_string($flash['message'] ?? null) ? $flash['message'] : '',
            'errors' => $errors,
            'values' => $values,
            'success' => true === ($flash['success'] ?? false),
        ];
    }
}
