# Architectural Decisions Record

## ADR-001 - Use View filters to limit the set of results

A calendar view's query very often results in a very large amount of rows, in
particular when the date field is of the recurring smart_date type. There can
potentially be thousands of more of entities to load, each with infinite number
of repeating instances.

As a result, it is often necessary to filter the view in the past and future.

We tried with the `query()` method on the style plugin but it is hard to handle
all potential field with there's own table structure. There is sometimes errors
with the suffix (e.g. `_value`) in the field tables.

It is easier and more sustainable to leave the responsability of building a View
with not-too-much results to the site builders.

**Decision**

Use View filters to reduce the amount of events to load.

**Resources**

* Link to [the online documentation](https://www.drupal.org/docs/contributed-modules/calendar-view/recurring-events-in-calendar-view#s-important-consider-limiting-the-view-results)
