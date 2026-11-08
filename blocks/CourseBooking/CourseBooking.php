<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Blocks;

use Frootbox\Core\Action\ActionException;
use Frootbox\Core\Component\BlockContext;
use Frootbox\Core\Component\BlockController;
use Frootbox\Core\Page\EditMode;
use Frootbox\Core\Url\Urls;
use Frootbox\Core\View\Delivery;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Courses\BookingForm;
use Frootbox\Events\Courses\BookingSession;
use Frootbox\Events\Courses\CourseRendering;
use Frootbox\Events\Courses\Courses;
use Frootbox\Events\Courses\Provider\Detail;
use Frootbox\Events\Courses\Provider\Field;
use Frootbox\Events\Courses\Provider\FieldOption;
use Frootbox\Events\Courses\Provider\LegalText;

/**
 * The booking form for the date in ?date= (decision 0351): the date with
 * price and free seats, the number of attendees (a form of its own, so
 * fields and total fit), the person who books, the attendees, the fields
 * of the date, the legal texts and the button. Never in the page cache.
 */
final readonly class CourseBooking implements BlockController
{
    private const string ACTION = 'frootbox/events/date.show';

    public function __construct(
        private Courses $courses,
        private CourseRendering $rendering,
        private BookingSession $session,
        private Urls $urls,
        private Delivery $delivery,
        private EditMode $editMode,
    ) {}

    public function data(BlockContext $context): array
    {
        $this->delivery->withoutCache();
        $flash = $this->session->takeFlash();
        $id = $context->query('date') ?? '';
        $base = ['editing' => $this->editMode->active(), 'flash' => $flash];

        if (!$this->courses->connected()) {
            return ['state' => 'none', ...$base];
        }

        // After the booking the date stays below the thanks, so the person sees what they booked.
        if (true === ($flash['success'] ?? false)) {
            try {
                $done = '' === $id ? null : $this->rendering->date($this->courses->date(self::ACTION, $id), $context->language, false);
            } catch (ActionException|EntityNotFoundException) {
                $done = null;
            }

            return ['state' => 'done', 'date' => $done, ...$base];
        }

        if ('' === $id) {
            return ['state' => 'choose', ...$base];
        }

        try {
            $channel = $this->courses->channel(self::ACTION);
            $date = $this->courses->date(self::ACTION, $id);
        } catch (EntityNotFoundException) {
            return ['state' => 'missing', ...$base];
        } catch (ActionException $error) {
            return ['state' => 'error', 'error' => (string) $error->template, ...$base];
        }

        $shown = $this->rendering->date($date, $context->language, false);

        if (!$date->bookable()) {
            return ['state' => 'closed', 'date' => $shown, ...$base];
        }

        $values = $flash['values'] ?? [];
        $max = $date->maxAttendees($channel);
        $sent = \is_array($values['attendees'] ?? null) ? \count($values['attendees']) : 0;
        $wanted = $context->query('attendees');
        $count = max(1, min($max, null !== $wanted && ctype_digit($wanted) ? (int) $wanted : max(1, $sent)));
        $page = null === $context->target ? '/' : $this->urls->href($context->target, $context->language) ?? '/';
        $here = $page . '?' . http_build_query(['date' => $date->id, 'attendees' => $count]);

        return [
            'state' => 'ready',
            ...$base,
            'date' => $shown,
            'page' => $page,
            'hidden' => ['_return' => $here, '_language' => $context->language, 'date' => $date->id, 'key' => bin2hex(random_bytes(16)), 'expectedGross' => (string) $date->totalGross($count)],
            'trap' => BookingForm::TRAP,
            'count' => $count,
            'counts' => $max > 1 ? range(1, $max) : [],
            'total' => 0 === $date->totalGross($count) ? null : CourseRendering::money($date->totalGross($count), $date->currency, $context->language),
            'address' => $channel->requires(Detail::Address),
            'phone' => $channel->requires(Detail::Phone),
            'birth' => $channel->requires(Detail::DateOfBirth),
            'fields' => array_map(static fn(Field $field): array => [
                'key' => $field->key,
                'title' => $field->title,
                'kind' => $field->kind?->value,
                'required' => $field->required,
                'options' => array_map(static fn(FieldOption $option): array => ['value' => $option->value, 'title' => $option->title], $field->options),
            ], array_values(array_filter($date->fields, static fn(Field $field): bool => null !== $field->kind))),
            'legal' => array_map(static fn(LegalText $text): array => ['key' => $text->key, 'title' => $text->title, 'url' => $text->url, 'text' => $text->text, 'required' => $text->required], $channel->legal),
            'values' => $values,
            'errors' => $flash['errors'] ?? [],
        ];
    }
}
