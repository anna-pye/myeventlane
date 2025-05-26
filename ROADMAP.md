ROADMAP.md

# MyEventLane – Project Roadmap

This roadmap outlines the major development goals and priorities for MyEventLane, a community-based Drupal 11 event listing and ticketing platform.

---

## 🎯 Overall Goals

- ✅ Enable user-submitted events with RSVP/ticketing functionality
- ✅ Clean UI with mobile-first responsive design
- ✅ DDEV-based local dev environment with version control and config sync
- ✅ Minimal, community-friendly ticketing fees

---

## 🧱 Backend Development

### In Progress
- [ ] Finalize custom content types (`event`, `rsvp_submission`)
- [ ] Add user reference fields for RSVP tracking
- [ ] Implement redirect logic after RSVP submit
- [ ] Enable taxonomy for event categories and tags

### Planned
- [ ] Add configurable thank-you page entity
- [ ] Integrate event recurrence and timezone support
- [ ] Add calendar feed export (.ics)

---

## 🎨 Frontend UI

### In Progress
- [ ] Theme RSVP forms with clear, accessible layout
- [ ] Fix mobile display issues on calendar view
- [ ] Apply branding and color palette
- [ ] Add CSS animations for RSVP success message

### Planned
- [ ] Public-facing calendar view with filters
- [ ] Light/dark mode toggle
- [ ] User dashboard to manage submitted events

---

## 🛠️ Troubleshooting & DevOps

### In Progress
- [ ] Resolve config import errors (UUID conflicts, deleted content types)
- [ ] Composer/Drush clean-up and module audit
- [ ] Refactor `.gitignore` and file tracking

### Planned
- [ ] Automated config sync command for local setup
- [ ] Add Lando compatibility alongside DDEV
- [ ] Implement deployment script (GitHub Actions or DDEV pull)

---

## 🧭 Timeline
