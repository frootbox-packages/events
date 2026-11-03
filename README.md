# frootbox/events

Events for the Frootbox CMS: dates with time, place, drafts, versions and
planned publishing, categories, tags, a calendar file for each event and two
blocks for any theme.

- **Events** (`frootbox/events/EventList`) is the view of the plugin
  (decision 0232): a page that shows a space of the events lists the
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

## Actions and queries

Everything goes through actions, also from the REST API and the AI:
`frootbox/events/event.create`, `.update`, `.publish`, `.unpublish`,
`.schedule`, `.discard`, `.delete`, the queries `frootbox/events/event.list`
and `.show`. Categories are the ones of the core (decision 0233) in the
group `frootbox/events/categories`: `category.create` with that group, `.update`,
`.move`, `.delete` and `category.list`; the actions take `categories` as
a list of their ids. Tags are the common stock of the core (decision
0239): the actions take `tags` as ids, keys or labels, and new words
become tags unless `config/tags.yaml` closes the list for
`frootbox/events/event` or the space. The role "editor" gets `frootbox/events/*` on
installation.

Further fields (decision 0240): a view of the theme declares them under
`object: fields:` in its header, e.g. an override of `EventList`, and
the actions take them as `fields`; `object.fields` with the type
`frootbox/events/event` lists them. The form in the backend shows them under
"More fields".

## In a template

```twig
<a href="{{ url('frootbox/events/event:' ~ id) }}">…</a>
{{ object_field(event, 'subtitle') }}  {# a further field, event as the view gets it #}
```

## Backend

With `frootbox/backend` installed, the screens "Events" and "Categories"
appear in the sidebar.

## License

MIT
