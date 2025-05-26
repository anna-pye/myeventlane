backend.md

# Backend Development – MyEventLane

## Active Features
- Custom content types: Event, RSVP Submission
- Field definitions: location, date, user reference
- Views integration

## To-Do
- Finalize RSVP submission workflow
- Redirect after RSVP submit
- Integrate with The Events Calendar plugin

## Notes
- Using DDEV for local dev
- All config stored in `/config/sync/`


## 📦 Content Types

### 🟦 Event

Used for publishing and listing community events.

| Field Machine Name        | Label                  | Field Type     | Notes                          |
|---------------------------|------------------------|----------------|--------------------------------|
| field_date_time           | Date & Time            | datetime       | Event start/end datetime       |
| field_location            | Location               | text           | Freeform text for venue or URL |
| field_description         | Description            | text_long      | Event body/summary             |
| field_flyer_promo_image   | Flyer / Promo Image    | image          | Single image                   |
| field_rsvp_enabled        | RSVP Enabled?          | boolean        | Controls RSVP visibility       |
| field_category            | Category               | entity_reference (taxonomy_term) | Optional category tagging |
| field_user_reference      | Created By             | entity_reference (user) | Automatically populated or selected |

---

### 🟨 RSVP Submission

Used for user-submitted responses to an event.

| Field Machine Name        | Label                  | Field Type     | Notes                          |
|---------------------------|------------------------|----------------|--------------------------------|
| field_name                | Name                   | text           | Plain text name                |
| field_email               | Email                  | email          | Validated email address        |
| field_event_reference     | Related Event          | entity_reference (node) | References an Event          |
| field_user_reference      | Submitted By (optional)| entity_reference (user) | If logged-in user             |
