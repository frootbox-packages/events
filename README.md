# frootbox/events

Events for the Frootbox CMS: dates with time, place, drafts, versions and
planned publishing, categories, a calendar file for each event and two
blocks for any theme.

- **Events** (`frootbox/events/EventList`) is the view of the plugin
  (decision 0232): a page that shows a space of the events lists the
  upcoming ones at `plugin()` from the next one on, page by page,
  filtered by category (`?category=`), and the past ones with `?past=1`.
  Each published event gets an address below that page, e.g.
  `/events/roasting-course`; there the view shows the event with its place, a map after consent,
  structured data for search engines and a link to the calendar file.
- **Next events** (`frootbox/events/EventTeaser`) shows the next few of
  all spaces or of one, with a link to the list.

An event is upcoming until it is over: until its end, else its start, or
until the end of its last day when it lasts all day long. When one is
over, the page cache is cleared on the next run of the scheduler.

The calendar file of an event (iCalendar) lives at
`/_events/calendar/<event id>/<language>.ics`.

## Actions and queries

Everything goes through actions, also from the REST API and the AI:
`frootbox/events/event.create`, `.update`, `.publish`, `.unpublish`,
`.schedule`, `.discard`, `.delete`, the queries `frootbox/events/event.list`
and `.show`, and `frootbox/events/category.create`, `.update`, `.delete`
and `.list`. The role "editor" gets `frootbox/events/*` on installation.

## In a template

```twig
<a href="{{ url('frootbox/events/event:' ~ id) }}">…</a>
```

## Backend

With `frootbox/backend` installed, the screens "Events" and "Categories"
appear in the sidebar.

## License

MIT
