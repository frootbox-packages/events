<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\AccessDeniedException;
use Frootbox\Core\Action\Context;
use Frootbox\Core\Action\CurrentContext;
use Frootbox\Core\Action\InvalidInputException;
use Frootbox\Core\Page\ProtectPage;
use Frootbox\Core\Page\UnprotectPage;
use Frootbox\Core\Url\Target;
use Frootbox\Database\EntityNotFoundException;
use Frootbox\Events\Blocks\CourseBooking;
use Frootbox\Events\Blocks\CourseDates;
use Frootbox\Events\Courses\BookingForm;
use Frootbox\Events\Courses\ChannelAccess;
use Frootbox\Events\Courses\ChannelChoices;
use Frootbox\Events\Courses\CourseChoices;
use Frootbox\Events\Courses\ListAttendees;
use Frootbox\Events\Courses\ListDates;
use Frootbox\Events\Courses\Provider\ChannelChoice;
use Frootbox\Events\Courses\Provider\CourseDate;
use Frootbox\Events\Tests\Support\FakeCourses;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A further, restricted channel of the provider (decision 0360) and the
 * names of who is booked (decision 0359): the page for partners of a first-aid
 * school with a password and a booking page of its own below.
 */
#[CoversClass(ChannelAccess::class)]
#[CoversClass(ChannelChoices::class)]
#[CoversClass(ListAttendees::class)]
#[CoversClass(CourseDates::class)]
#[CoversClass(CourseBooking::class)]
final class ChannelsTest extends CoursesTestCase
{
    private const string PASSWORD = 'Kaffeebohne42';

    private string $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $start = $this->published(null, 'Start', [['block' => 'frootbox/events/CourseDates', 'fields' => ['title' => 'Alle Kurse']]]);
        $this->published($start, 'Anmeldung', [['block' => 'frootbox/events/CourseBooking']]);
        $this->published($start, 'Offen', [['block' => 'frootbox/events/CourseDates', 'options' => ['channel' => 'partner', 'attendees' => true]]]);
        $this->partner = $this->published($start, 'Partner', [['block' => 'frootbox/events/CourseDates', 'options' => ['channel' => 'partner', 'attendees' => true], 'fields' => ['title' => 'Partnerkurse']]]);
        $this->published($this->partner, 'Buchung', [['block' => 'frootbox/events/CourseBooking', 'options' => ['channel' => 'partner']]]);
        $this->runner()->run(new ProtectPage($this->partner, self::PASSWORD, branch: true));
    }

    public function testARestrictedChannelShowsOnlyBehindThePasswordWithTheNames(): void
    {
        $open = $this->visit('/offen');
        self::assertStringNotContainsString('date=30', $open, 'On an open page the channel shows nothing.');
        self::assertStringNotContainsString('Kurz', $open);

        self::assertSame(401, $this->browse('/partner')->getStatusCode());
        $this->unlock('/partner', self::PASSWORD);
        $html = $this->visit('/partner');

        self::assertStringContainsString('Partnerkurse', $html);
        self::assertStringContainsString('href="/partner/buchung?date=30"', $html, 'To the booking page of the same channel.');
        self::assertStringNotContainsString('date=10', $html, 'Only the dates of the channel.');
        self::assertStringContainsString('2 gebucht', $html);
        self::assertStringContainsString('Jana Kurz', $html);
        self::assertMatchesRegularExpression('~Malte Lang <span class="events-date__booked">\(gebucht am 03\.10\.2026, 09:30\)</span>~u', $html);

        $public = $this->visit('/');
        self::assertStringContainsString('href="/anmeldung?date=10"', $public, 'The own channel books on its own page.');
        self::assertStringNotContainsString('events-date__attendees', $public, 'No names without the option.');
    }

    public function testWithoutNamesFromTheProviderTheDatesStayWithAHint(): void
    {
        $this->unlock('/partner', self::PASSWORD);
        FakeCourses::$namesDown = true;

        $html = $this->visit('/partner');
        self::assertStringContainsString('href="/partner/buchung?date=30"', $html);
        self::assertStringContainsString('Die Namen der Gebuchten lassen sich gerade nicht laden.', $html);
        self::assertStringNotContainsString('Jana Kurz', $html);
    }

    public function testTheBookingOfTheChannelNeedsTheAccessOfItsPage(): void
    {
        $this->unlock('/partner', self::PASSWORD);
        $form = $this->visit('/partner/buchung?date=30');
        self::assertStringContainsString('name="channel" value="partner"', $form);
        self::assertMatchesRegularExpression('~name="access" value="[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+"~', $form);

        $post = $this->post($form);
        self::assertSame(303, $this->browse('/_events/booking', $post)->getStatusCode());
        self::assertCount(1, FakeCourses::bookings());
        self::assertSame('30', FakeCourses::bookings()[0][0]->date);

        // Without the access, or after a new password, nobody books past it.
        $this->browse('/_events/booking', ['access' => '', 'key' => 'other-key-1'] + $post);
        $this->runner()->run(new ProtectPage($this->partner, 'Neue-Bohne-7', branch: true));
        $this->browse('/_events/booking', ['key' => 'other-key-2'] + $post);
        self::assertCount(1, FakeCourses::bookings());
    }

    public function testTheAccessHoldsForItsChannelPageAndPasswordADay(): void
    {
        $access = $this->get(ChannelAccess::class);
        $page = Target::page($this->partner);
        $now = new DateTimeImmutable('2026-11-09 12:00');

        self::assertNull($access->issue('', $page), 'The own channel needs none.');
        $key = $access->issue('partner', $page, $now);
        self::assertNotNull($key);

        $this->asGuest(static function () use ($access, $key, $now): void {
            $access->check('', null);
            $access->check('partner', $key, $now->modify('+23 hours'));
        });

        foreach ([[null, $now], [$key . 'x', $now], [$key, $now->modify('+25 hours')]] as [$tried, $at]) {
            $this->assertDenied(static fn() => $access->check('partner', $tried, $at));
        }

        FakeCourses::$channels[] = new ChannelChoice('other', 'Andere');
        $this->assertDenied(static fn() => $access->check('other', $key, $now), 'Only for its channel.');

        $this->runner()->run(new UnprotectPage($this->partner));
        $this->assertDenied(static fn() => $access->check('partner', $key, $now), 'Switching the password off ends it.');
        self::assertNull($access->issue('partner', $page), 'And no page without password hands one out.');

        // Who has the permission needs no access.
        $access->check('partner', null);
        $this->addToAssertionCount(1);
    }

    public function testThePublicQueriesOfARestrictedChannelNeedTheAccess(): void
    {
        $this->assertDenied(fn() => $this->runner()->call('frootbox/events/date.list', ['channel' => 'partner']));

        $key = $this->get(ChannelAccess::class)->issue('partner', Target::page($this->partner));
        $dates = $this->asGuest(fn() => $this->runner()->ask(new ListDates(channel: 'partner', access: $key)));
        self::assertSame(['30'], array_map(static fn(CourseDate $date): string => $date->id, $dates));

        $this->expectException(EntityNotFoundException::class);
        $this->runner()->ask(new ListDates(channel: 'unknown'));
    }

    public function testTheNamesForEditorsAndTheApi(): void
    {
        $names = $this->runner()->ask(new ListAttendees(['30', '10', '99'], 'partner'));
        self::assertSame([30], array_keys($names), 'Only dates of the channel.');
        self::assertSame('Jana Kurz', ($names[30] ?? [])[0]->name());
        self::assertSame([], $this->runner()->ask(new ListAttendees(['10'])));

        try {
            $this->runner()->ask(new ListAttendees([]));
            self::fail('Without dates.');
        } catch (InvalidInputException) {
        }

        $this->expectException(AccessDeniedException::class);
        $this->asGuest(fn() => $this->runner()->ask(new ListAttendees(['30'], 'partner')));
    }

    public function testTheChoicesNameTheChannelsAndTheirCourses(): void
    {
        $channels = $this->runner()->ask(new ChannelChoices());
        self::assertSame([['value' => '', 'label' => 'Standard'], ['value' => 'partner', 'label' => 'Partner (nur mit Passwort)']], $channels);

        $courses = $this->runner()->ask(new CourseChoices());
        self::assertSame(['', '2', '7'], array_column($courses, 'value'));
        self::assertSame('Partnerkurs (Partner)', $courses[2]['label'], 'A course only the channel shows, with its title.');

        FakeCourses::$channels = [];
        self::assertSame([], $this->runner()->ask(new ChannelChoices()), 'One channel only: no choice.');
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function asGuest(callable $callback): mixed
    {
        return $this->get(CurrentContext::class)->runAs(Context::guest(), $callback);
    }

    private function assertDenied(callable $callback, string $message = ''): void
    {
        try {
            $this->asGuest($callback);
            self::fail('Denied: ' . $message);
        } catch (AccessDeniedException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * The fields of the booking form as a browser sends them, filled in.
     *
     * @return array<string, mixed>
     */
    private function post(string $form): array
    {
        preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)">~', $form, $hidden, \PREG_SET_ORDER);
        $post = [];

        foreach ($hidden as [, $name, $value]) {
            $post[$name] = html_entity_decode($value);
        }

        return $post + [
            'booker' => ['salutation' => 'ms', 'firstName' => 'Jana', 'lastName' => 'Kurz', 'street' => 'Kreuzweg', 'houseNumber' => '3', 'postalCode' => '52062', 'city' => 'Aachen', 'email' => 'jana@example.test'],
            'attendees' => [['firstName' => '', 'lastName' => '', 'dateOfBirth' => '1985-02-14']],
            'fields' => ['kosten-ueber-firma' => '1'],
            'consents' => ['Terms'],
            BookingForm::TRAP => '',
        ];
    }
}
