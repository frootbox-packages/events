<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Courses;

use DateTimeImmutable;
use Frootbox\Core\Action\Action;
use Frootbox\Core\Action\AsAction;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Action\Schema\Description;
use Frootbox\Core\Action\Schema\Length;
use Frootbox\Core\Action\Schema\ListOf;
use Frootbox\Core\Support\Message;
use Frootbox\Events\Courses\Provider\Attendee;
use Frootbox\Events\Courses\Provider\Booker;
use Frootbox\Events\Courses\Provider\BookingConfirmation;
use Frootbox\Events\Courses\Provider\BookingRequest;
use Frootbox\Events\Courses\Provider\BookingState;
use Frootbox\Events\Courses\Provider\Channel;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Courses\Provider\Detail;
use Frootbox\Events\Courses\Provider\FieldKind;
use Frootbox\Events\Courses\Provider\FieldOption;
use Frootbox\Events\Courses\Provider\Salutation;

/**
 * Books a date of a course at the provider for a visitor in the browser
 * (decision 0351). Checks first what the channel and the date ask for and
 * reports every error at once; the same key again gives the same booking.
 *
 * @implements Action<BookingConfirmation>
 */
#[AsAction('frootbox/events/booking.place', 'Books a date of a course for one or more attendees at the provider; the same key again gives the same booking', public: true, undoable: false, confirm: true)]
final readonly class PlaceBooking implements Action
{
    public const string NAME = 'frootbox/events/booking.place';

    /** Characters of a line of text at most. */
    private const int LINE = 100;

    /**
     * @param array<mixed> $booker
     * @param array<mixed> $attendees
     * @param array<mixed> $fields
     * @param list<string> $consents
     */
    public function __construct(
        #[Description('The id of the date')]
        #[Length(min: 1, max: 100)]
        public string $date,
        #[Description('The person who books and gets the invoice: firstName, lastName, email, salutation (mr, ms, diverse), company, street, houseNumber, addition, postalCode, city, country, phone')]
        public array $booker,
        #[Description('The attendees, each with firstName, lastName and dateOfBirth (YYYY-MM-DD); without names the first is the person who books')]
        public array $attendees,
        #[Description('The total gross in cents the person saw; if it changed meanwhile, nothing is booked')]
        public int $expectedGross,
        #[Description('A key of the form, so sending twice books once')]
        #[Length(min: 8, max: 100)]
        public string $key,
        #[Description('Answers to the fields of the date by key: true for a ticked box, else text')]
        public array $fields = [],
        #[Length(max: 4000)]
        public ?string $message = null,
        #[Description('Keys of the legal texts the person agreed to')]
        #[ListOf('string')]
        public array $consents = [],
    ) {}

    public function handle(Courses $courses, BookingSession $session): BookingConfirmation
    {
        $session->require();
        $channel = $courses->channel(self::NAME);
        $date = $courses->date(self::NAME, $this->date);
        $errors = [];
        $booker = $this->booker($channel, $errors);
        $attendees = $this->attendees($channel, $date, $booker, $errors);
        $fields = $this->answers($date, $errors);
        $consents = $this->agreed($channel, $errors);

        if ([] !== $errors) {
            throw new InvalidInputException(self::NAME, $errors);
        }

        if (BookingState::Open !== $date->state && BookingState::SoldOut !== $date->state) {
            throw new ConflictException('This date can no longer be booked.');
        }

        if (!$date->seats->fit(\count($attendees))) {
            throw new ConflictException('Not enough seats are free on this date any more.');
        }

        if ($date->totalGross(\count($attendees)) !== $this->expectedGross) {
            throw new ConflictException('The price has changed meanwhile. Please check the booking and send it again.');
        }

        $message = null === $this->message || '' === trim($this->message) ? null : trim($this->message);

        return $courses->book(self::NAME, new BookingRequest($date->id, $booker, $attendees, $this->expectedGross, $fields, $message, $consents), $this->key);
    }

    /**
     * @param array<string, Message> $errors
     */
    private function booker(Channel $channel, array &$errors): Booker
    {
        $value = function (string $name, int $max = self::LINE, bool $required = false) use (&$errors): ?string {
            return $this->text($this->booker, $name, '/booker/' . $name, $max, $required, $errors);
        };
        $address = $channel->requires(Detail::Address);
        $email = $value('email', 200, true);

        if (null !== $email && false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors['/booker/email'] = new Message('Please enter an email address, e.g. name@example.com.');
        }

        $salutation = $this->booker['salutation'] ?? null;
        $country = $value('country', 2);

        if (null !== $country && 1 !== preg_match('/^[A-Za-z]{2}$/', $country)) {
            $errors['/booker/country'] = new Message('Please choose one of the options.');
        }

        $phone = $value('phone', 50, $channel->requires(Detail::Phone));

        if (null !== $phone && 1 !== preg_match('/^[0-9 +()\/.-]{4,50}$/', $phone)) {
            $errors['/booker/phone'] = new Message('Please enter a phone number.');
        }

        return new Booker(
            $value('firstName', required: true) ?? '',
            $value('lastName', required: true) ?? '',
            $email ?? '',
            \is_string($salutation) ? Salutation::tryFrom($salutation) : null,
            $value('company', 200),
            $value('street', required: $address),
            $value('houseNumber', 20, $address),
            $value('addition'),
            $value('postalCode', 20, $address),
            $value('city', required: $address),
            null === $country ? null : strtoupper($country),
            $phone,
        );
    }

    /**
     * @param array<string, Message> $errors
     *
     * @return list<Attendee>
     */
    private function attendees(Channel $channel, CourseDate $date, Booker $booker, array &$errors): array
    {
        $rows = array_values(array_filter($this->attendees, \is_array(...)));
        $max = $date->maxAttendees($channel);

        if ([] === $rows || \count($rows) > $max) {
            $errors['/attendees'] = [] === $rows ? new Message('Please fill in this field.') : new Message('At most {count} attendees can book together.', ['count' => $max]);

            return [];
        }

        $attendees = [];

        foreach ($rows as $index => $row) {
            $pointer = '/attendees/' . $index . '/';
            $quiet = [];
            $first = $this->text($row, 'firstName', $pointer . 'firstName', self::LINE, false, $quiet);
            $last = $this->text($row, 'lastName', $pointer . 'lastName', self::LINE, false, $quiet);

            // Without names the first attendee is the person who books.
            if (0 === $index && null === $first && null === $last) {
                [$first, $last] = [$booker->firstName, $booker->lastName];
            }

            $errors += $quiet;

            foreach (['firstName' => $first, 'lastName' => $last] as $name => $text) {
                if (null === $text || '' === $text) {
                    $errors[$pointer . $name] ??= new Message('Please fill in this field.');
                }
            }

            $attendees[] = new Attendee($first ?? '', $last ?? '', $this->birth($row, $pointer . 'dateOfBirth', $channel->requires(Detail::DateOfBirth), $errors));
        }

        return $attendees;
    }

    /**
     * @param array<mixed>           $row
     * @param array<string, Message> $errors
     */
    private function birth(array $row, string $pointer, bool $required, array &$errors): ?DateTimeImmutable
    {
        $value = \is_string($row['dateOfBirth'] ?? null) ? trim($row['dateOfBirth']) : '';

        if ('' === $value) {
            if ($required) {
                $errors[$pointer] = new Message('Please enter a date.');
            }

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date || $date->format('Y-m-d') !== $value || $date >= new DateTimeImmutable('today') || (int) $date->format('Y') < 1900) {
            $errors[$pointer] = new Message('Please enter a date.');

            return null;
        }

        return $date;
    }

    /**
     * The answers to the fields the date asks for; other keys are left out.
     *
     * @param array<string, Message> $errors
     *
     * @return array<string, bool|string>
     */
    private function answers(CourseDate $date, array &$errors): array
    {
        $answers = [];

        foreach ($date->fields as $field) {
            $pointer = '/fields/' . $field->key;
            $value = $this->fields[$field->key] ?? null;

            if (FieldKind::Checkbox === $field->kind) {
                $ticked = true === $value || \in_array($value, ['1', 'on', 'true', 'yes'], true);

                if ($field->required && !$ticked) {
                    $errors[$pointer] = new Message('Please check this box.');
                }

                $answers[$field->key] = $ticked;

                continue;
            }

            if (null === $field->kind) {
                continue;
            }

            $text = $this->text($this->fields, $field->key, $pointer, 1000, $field->required, $errors);

            if (FieldKind::Choice === $field->kind && null !== $text && !\in_array($text, array_map(static fn(FieldOption $option): string => $option->value, $field->options), true)) {
                $errors[$pointer] = new Message('Please choose one of the options.');

                continue;
            }

            if (null !== $text) {
                $answers[$field->key] = $text;
            }
        }

        return $answers;
    }

    /**
     * The required legal texts with their version; the others are a notice.
     *
     * @param array<string, Message> $errors
     *
     * @return list<array{key: string, version: string}>
     */
    private function agreed(Channel $channel, array &$errors): array
    {
        $consents = [];

        foreach ($channel->legal as $text) {
            if (!$text->required) {
                continue;
            }

            if (\in_array($text->key, $this->consents, true)) {
                $consents[] = ['key' => $text->key, 'version' => $text->version];
            } else {
                $errors['/consents'] ??= new Message('Please agree to “{title}”.', ['title' => $text->title]);
            }
        }

        return $consents;
    }

    /**
     * A trimmed text of the input, null if empty.
     *
     * @param array<mixed>           $input
     * @param array<string, Message> $errors
     */
    private function text(array $input, string $name, string $pointer, int $max, bool $required, array &$errors): ?string
    {
        $value = $input[$name] ?? null;
        $text = \is_string($value) ? trim($value) : (\is_int($value) ? (string) $value : '');

        if ('' === $text) {
            if ($required) {
                $errors[$pointer] = new Message('Please fill in this field.');
            }

            return null;
        }

        if (mb_strlen($text) > $max) {
            $errors[$pointer] = new Message('Please write at most {count} characters.', ['count' => $max]);
        }

        return $text;
    }
}
