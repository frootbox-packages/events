# frootbox/events

Events for the Frootbox CMS: dates with time, place, drafts, versions and
planned publishing, categories, tags, a calendar file for each event, two
blocks for any theme, and courses with free seats and a booking form from
a provider.

- **Events** (`frootbox/events/EventList`) is the view of the plugin: a page that shows a space of the events lists the
  upcoming ones at `plugin()` from the next one on, page by page,
  filtered by category (`?category=`) and tag (`?tag=`), and the past
  ones with `?past=1`.
  Each published event gets an address below that page, e.g.
  `/events/roasting-course`; there the view shows the event with its place, a map after consent,
  structured data for search engines and a link to the calendar file.
- **Next events** (`frootbox/events/EventTeaser`) shows the next few of
  all spaces or of one, optionally only those with a tag, with a link to
  the list.

An event is upcoming until it is over: until its end, else its start, or
until the end of its last day when it lasts all day long. When one is
over, the page cache is cleared on the next run of the scheduler.

The calendar file of an event (iCalendar) lives at
`/_events/calendar/<event id>/<language>.ics`.

## Courses from a provider

A provider of courses, such as `avaro/events`, brings courses with
price per attendee and the least and most attendees, and their dates
with free seats, booking window and the questions they ask. Nothing is
mirrored: the blocks ask the provider, and a page with free seats stays
at most a minute in the page cache.

- **Course with dates** (`frootbox/events/CourseDates`): a course with
  its facts and coming dates, each with a link to the booking or
  "sold out".
- **Courses soon** (`frootbox/events/NextCourseDates`): the next dates
  that can be booked, over all courses or one.
- **Course booking** (`frootbox/events/CourseBooking`): the form for
  `?date=<id>` on the first page with this block. It posts to
  `POST /_events/booking` (only from pages of this website), which runs
  `frootbox/events/booking.place` and leads back with a message. It
  works without JavaScript.

Queries: `frootbox/events/course.list`, `frootbox/events/date.list`,
`frootbox/events/date.show`. The booking works only for a visitor in a
browser; the REST API and MCP get 409.

A provider implements `Frootbox\Events\Courses\Provider\CourseProvider`
and registers itself:

```php
$registry->add(CourseProviderEntry::class, new CourseProviderEntry(MyCourses::class, 'My courses'));
```

With more than one installed, the setting `provider` of
`frootbox/events` names the class to use.

## Actions and queries

Everything goes through actions, also from the REST API and the AI:
`frootbox/events/event.create`, `.update`, `.publish`, `.unpublish`,
`.schedule`, `.discard`, `.delete`, the queries `frootbox/events/event.list`
and `.show`. Categories are the ones of the core in the
group `frootbox/events/categories`: `category.create` with that group, `.update`,
`.move`, `.delete` and `category.list`; the actions take `categories` as
a list of their ids. Tags are the common stock of the core: the actions take `tags` as ids, keys or labels, and new words
become tags unless `config/tags.yaml` closes the list for
`frootbox/events/event` or the space. The role "editor" gets `frootbox/events/*` on
installation.

Further fields: a view of the theme declares them under
`object: fields:` in its header, e.g. an override of `EventList`, and
the actions take them as `fields`; `object.fields` with the type
`frootbox/events/event` lists them. The form in the backend shows them under
"More fields". In the editing mode editors change them right on the page
(`object.update-field`), and the editor bar publishes the event with the page.

## In a template

```twig
<a href="{{ url('frootbox/events/event:' ~ id) }}">…</a>
{{ object_field(event, 'subtitle') }}  {# a further field, event as the view gets it #}
```

## Backend

With `frootbox/backend` installed, the screens "Events" and "Categories"
appear in the sidebar.

## Source and issues

This package is developed in the [Frootbox monorepo](https://github.com/Frootbox/frootbox)
and mirrored here read-only. Questions, bugs and ideas go there, see the
[documentation](https://github.com/Frootbox/frootbox/tree/main/documentation).

## License

MIT
