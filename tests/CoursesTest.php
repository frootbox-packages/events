<?php

/*
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Frootbox\Events\Tests;

use DateTimeImmutable;
use Frootbox\Core\Action\ConflictException;
use Frootbox\Core\View\Delivery;
use Frootbox\Events\Blocks\CourseBooking;
use Frootbox\Events\Blocks\CourseDates;
use Frootbox\Events\Blocks\NextCourseDates;
use Frootbox\Events\Courses\BookingForm;
use Frootbox\Events\Courses\CourseChoices;
use Frootbox\Events\Courses\CourseRendering;
use Frootbox\Events\Courses\Courses;
use Frootbox\Events\Courses\ListDates;
use Frootbox\Events\Courses\PlaceBooking;
use Frootbox\Events\Courses\Provider\BookingState;
use Frootbox\Events\Courses\Provider\CourseRef;
use Frootbox\Events\Courses\Provider\CoursesUnavailable;
use Frootbox\Events\Courses\Provider\Salutation;
use Frootbox\Events\Courses\Provider\TotalChanged;
use Frootbox\Events\Tests\Support\FakeCourses;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CourseDates::class)]
#[CoversClass(NextCourseDates::class)]
#[CoversClass(CourseBooking::class)]
#[CoversClass(BookingForm::class)]
#[CoversClass(PlaceBooking::class)]
#[CoversClass(Courses::class)]
#[CoversClass(CourseRendering::class)]
final class CoursesTest extends CoursesTestCase
{
    private string $start;

    protected function setUp(): void
    {
        parent::setUp();

        $this->start = $start = $this->published(null, 'Start', [['block' => 'frootbox/events/NextCourseDates', 'fields' => ['title' => 'Kurstermine in Kürze']]]);
        $this->published($start, 'Erste Hilfe', [['block' => 'frootbox/events/CourseDates', 'options' => ['course' => '2'], 'fields' => ['title' => 'Erste Hilfe Grundkurs', 'datesTitle' => 'Kommende Kurse']]]);
        $this->published($start, 'Anmeldung', [['block' => 'frootbox/events/CourseBooking']]);
    }

    public function testTheCourseShowsPriceAttendeesAndItsDates(): void
    {
        $html = $this->visit('/erste-hilfe');

        self::assertMatchesRegularExpression('~Preis pro Person</th><td>40,00\s€~u', $html);
        self::assertStringContainsString('Mindestens Teilnehmer</th><td>4', $html);
        self::assertStringContainsString('noch 2 Plätze frei', $html);
        self::assertStringContainsString('href="/anmeldung?date=10"', $html);
        self::assertStringContainsString('Leider ausgebucht', $html, 'A sold-out date stays in the list of the course.');
        self::assertStringNotContainsString('date=11', $html);
        self::assertSame(CourseRendering::LIFETIME, $this->get(Delivery::class)->cacheLifetime(), 'Free seats stay fresh.');
    }

    public function testCoursesSoonShowEveryPublishedDateAndSayHowToBook(): void
    {
        // Not bookable online at the provider, e.g. only by phone (decision 0361)
        FakeCourses::$dates['13'] = FakeCourses::make('13', '+14 days 09:00', 9, BookingState::Offline);

        $html = $this->visit('/');

        self::assertStringContainsString('Kurstermine in Kürze', $html);
        self::assertSame(['10', '12'], self::linkedDates($html));
        self::assertStringContainsString('noch 15 Plätze frei', $html);
        self::assertStringContainsString('Leider ausgebucht', $html, 'A sold-out date stays, without a link.');
        self::assertStringContainsString('Anmeldung telefonisch oder persönlich', $html);
        self::assertStringNotContainsString('noch 9 Plätze frei', $html, 'Free seats only where one books online.');
        self::assertStringContainsString('Anmeldung telefonisch oder persönlich', $this->visit('/erste-hilfe'), 'The same note in the section of the course.');
    }

    public function testCoursesSoonShowOnlyTheTickedCoursesMergedByStart(): void
    {
        // The partner course (3) has the earliest date, a second course (4) a later one
        FakeCourses::$dates['20'] = FakeCourses::make('20', '+5 days 09:00', 5, course: new CourseRef('3', 'Partnerkurs'));
        FakeCourses::$dates['21'] = FakeCourses::make('21', '+15 days 09:00', 5, course: new CourseRef('4', 'Erste Hilfe am Kind'));
        $this->published($this->start, 'Ohne Partnerkurs', [['block' => 'frootbox/events/NextCourseDates', 'options' => ['courses' => ['2', '4'], 'count' => 3]]]);
        $this->published($this->start, 'Alle', [['block' => 'frootbox/events/NextCourseDates', 'options' => ['courses' => [], 'count' => 3]]]);

        $without = $this->visit('/ohne-partnerkurs');
        self::assertStringNotContainsString('date=20', $without);
        self::assertSame(['10', '21'], self::linkedDates($without), 'The sold-out date 11 takes the third place.');

        self::assertSame(['20', '10'], self::linkedDates($this->visit('/alle')));
    }

    /**
     * @return list<string> the dates linked to the booking, in order
     */
    private static function linkedDates(string $html): array
    {
        preg_match_all('~/anmeldung\?date=(\d+)"~', $html, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function testWithTheProviderDownTheBlockSaysSoAndThePageIsNotCached(): void
    {
        FakeCourses::unavailable();

        self::assertStringContainsString('Die Termine lassen sich gerade nicht laden.', $this->visit('/'));
        self::assertFalse($this->get(Delivery::class)->cacheable());
    }

    public function testABookingGoesOnceToTheProviderAndThanks(): void
    {
        $form = $this->visit('/anmeldung?date=10');
        self::assertStringContainsString('Kursanmeldung kostenpflichtig abschließen', $form);
        self::assertStringContainsString('Gebühr: <strong>40,00', $form);
        self::assertStringContainsString('name="fields[kosten-ueber-firma]"', $form);
        self::assertStringContainsString('<option value="2">2</option>', $form, 'Two seats are free.');
        self::assertStringNotContainsString('<option value="3">', $form);
        self::assertStringContainsString('Wir speichern Deine Angaben.', $form, 'A notice with its text.');

        $post = $this->post($form);
        $response = $this->browse('/_events/booking', $post);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/anmeldung?date=10&attendees=1', $response->getHeaderLine('Location'));
        self::assertCount(1, FakeCourses::bookings());
        [$request, $key] = FakeCourses::bookings()[0];
        self::assertSame($post['key'], $key);
        self::assertSame(['Anna', 'Weber', '1985-02-14'], [$request->attendees[0]->firstName, $request->attendees[0]->lastName, $request->attendees[0]->dateOfBirth?->format('Y-m-d')], 'Without names the person who books takes part.');
        self::assertSame([Salutation::Ms, 'Kreuzweg', '52062', 'anna@example.test'], [$request->booker->salutation, $request->booker->street, $request->booker->postalCode, $request->booker->email]);
        self::assertSame(['kosten-ueber-firma' => true], $request->fields);
        self::assertSame([['key' => 'Terms', 'version' => '2027-01']], $request->consents);
        self::assertSame(4000, $request->expectedGross);

        $thanks = $this->visit('/anmeldung?date=10&attendees=1');
        self::assertStringContainsString('Danke für Deine Anmeldung B-1.', $thanks);
        self::assertStringNotContainsString('<form', $thanks);
        self::assertStringContainsString('Schulungsraum Aachen', $thanks, 'The booked date stays below the thanks.');
        self::assertStringNotContainsString('Freie Plätze', $thanks);

        $this->browse('/_events/booking', $post);
        self::assertCount(1, FakeCourses::bookings(), 'The same key books once.');
    }

    public function testErrorsComeBackAtTheFieldsWithTheEntries(): void
    {
        $form = $this->visit('/anmeldung?date=10');
        $post = $this->post($form, ['email' => 'anna', 'city' => ''], [['firstName' => '', 'lastName' => '', 'dateOfBirth' => '']], []);

        $this->browse('/_events/booking', $post);
        $html = $this->visit('/anmeldung?date=10&attendees=1');

        self::assertSame([], FakeCourses::bookings());
        self::assertStringContainsString('Bitte prüfe Deine Angaben.', $html);
        self::assertStringContainsString('Bitte gib eine E-Mail-Adresse ein', $html);
        self::assertStringContainsString('Bitte gib ein Datum ein.', $html);
        self::assertStringContainsString('Bitte stimme „Verbindliche Anmeldung“ zu.', $html);
        self::assertMatchesRegularExpression('~name="booker\[city\]" type="text" value=""[^>]*required[^>]*aria-invalid="true"~', $html);
        self::assertStringContainsString('value="Kreuzweg"', $html, 'The entries stay.');
    }

    public function testMoreAttendeesThanFreeSeatsOrAnOldPriceBookNothing(): void
    {
        $form = $this->visit('/anmeldung?date=10&attendees=2');
        self::assertStringContainsString('Teilnehmer 2', $form);
        self::assertStringContainsString('Gebühr: <strong>80,00', $form);

        $post = $this->post($form, attendees: [['firstName' => '', 'lastName' => '', 'dateOfBirth' => '1985-02-14'], ['firstName' => 'Mia', 'lastName' => 'Weber', 'dateOfBirth' => '2010-05-01'], ['firstName' => 'Leo', 'lastName' => 'Weber', 'dateOfBirth' => '2012-05-01']]);
        $this->browse('/_events/booking', $post);
        self::assertStringContainsString('Höchstens 2 Teilnehmer können sich zusammen anmelden.', $this->visit('/anmeldung?date=10&attendees=2'));

        $post = $this->post($this->visit('/anmeldung?date=10'), expectedGross: '3500');
        FakeCourses::$dates['10'] = FakeCourses::make('10', '+10 days 09:00', 2);
        $this->browse('/_events/booking', $post);
        self::assertStringContainsString('Der Preis hat sich inzwischen geändert.', $this->visit('/anmeldung?date=10'));
        self::assertSame([], FakeCourses::bookings());
    }

    public function testAConflictOfTheProviderComesBackAsAMessage(): void
    {
        $post = $this->post($this->visit('/anmeldung?date=10'));
        FakeCourses::$fail = new TotalChanged();
        // The first call of the action is the channel; the error comes from there.
        $this->browse('/_events/booking', $post);

        self::assertStringContainsString('Der Preis hat sich inzwischen geändert.', $this->visit('/anmeldung?date=10'));
    }

    public function testAfterAnErrorSendingAgainUsesTheSameKey(): void
    {
        $post = $this->post($this->visit('/anmeldung?date=10'));
        FakeCourses::$fail = new CoursesUnavailable();
        $this->browse('/_events/booking', $post);
        FakeCourses::$fail = null;

        $again = $this->post($this->visit('/anmeldung?date=10&attendees=1'));
        self::assertSame($post['key'], $again['key'], 'The provider may have booked without answering in time.');

        $this->browse('/_events/booking', $again);
        self::assertCount(1, FakeCourses::bookings());
        self::assertStringContainsString('Danke für Deine Anmeldung', $this->visit('/anmeldung?date=10&attendees=1'));
        self::assertNotSame($post['key'], $this->post($this->visit('/anmeldung?date=10'))['key'], 'After the thanks a new booking gets a new key.');
    }

    public function testASoldOutOrClosedDateShowsNoForm(): void
    {
        self::assertStringContainsString('Dieser Termin ist leider ausgebucht.', $this->visit('/anmeldung?date=11'));
        self::assertStringContainsString('Diesen Termin gibt es nicht mehr.', $this->visit('/anmeldung?date=99'));
        self::assertStringContainsString('Bitte wähle zuerst einen Kurstermin.', $this->visit('/anmeldung'));

        FakeCourses::$dates['12'] = FakeCourses::make('12', '+20 days 09:00', 15, BookingState::Closed);
        $html = $this->visit('/anmeldung?date=12');
        self::assertStringContainsString('Für diesen Termin ist gerade keine Anmeldung möglich.', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    public function testTheTrapAndOtherWebsitesBookNothing(): void
    {
        $post = $this->post($this->visit('/anmeldung?date=10'));

        self::assertSame(403, $this->browse('/_events/booking', $post, ['Origin' => 'https://evil.example'])->getStatusCode());
        $this->browse('/_events/booking', [...$post, BookingForm::TRAP => 'https://spam.example']);

        self::assertSame([], FakeCourses::bookings());
    }

    public function testWithoutABrowserTheBookingRefuses(): void
    {
        $this->expectException(ConflictException::class);

        $this->runner()->call(PlaceBooking::NAME, ['date' => '10', 'booker' => ['firstName' => 'Anna', 'lastName' => 'Weber', 'email' => 'anna@example.test'], 'attendees' => [[]], 'expectedGross' => 4000, 'key' => 'mcp-booking-1']);
    }

    public function testTheQueriesListDatesAndCourses(): void
    {
        $dates = $this->runner()->ask(new ListDates('2', new DateTimeImmutable(), true, 10));
        self::assertSame(['10', '12'], array_map(static fn($date): string => $date->id, $dates));

        $choices = $this->runner()->ask(new CourseChoices());
        self::assertSame(['', '2', '7'], array_column($choices, 'value'), 'Also the courses of the channel.');
        self::assertSame('Erste Hilfe – Grundkurs', $choices[1]['label']);
    }

    /**
     * The fields of the booking form as a browser sends them, filled in.
     *
     * @param array<string, string>            $booker    entries that replace those of Anna Weber
     * @param list<array<string, string>>|null $attendees null: Anna takes part herself
     * @param list<string>                     $consents
     *
     * @return array<string, mixed>
     */
    private function post(string $form, array $booker = [], ?array $attendees = null, array $consents = ['Terms'], ?string $expectedGross = null): array
    {
        preg_match_all('~<input type="hidden" name="([^"]+)" value="([^"]*)">~', $form, $hidden, \PREG_SET_ORDER);
        $post = [];

        foreach ($hidden as [, $name, $value]) {
            $post[$name] = html_entity_decode($value);
        }

        if (null !== $expectedGross) {
            $post['expectedGross'] = $expectedGross;
        }

        return $post + [
            'booker' => [...['salutation' => 'ms', 'firstName' => 'Anna', 'lastName' => 'Weber', 'street' => 'Kreuzweg', 'houseNumber' => '3', 'postalCode' => '52062', 'city' => 'Aachen', 'email' => 'anna@example.test'], ...$booker],
            'attendees' => $attendees ?? [['firstName' => '', 'lastName' => '', 'dateOfBirth' => '1985-02-14']],
            'fields' => ['kosten-ueber-firma' => '1'],
            'consents' => $consents,
            BookingForm::TRAP => '',
        ];
    }
}
