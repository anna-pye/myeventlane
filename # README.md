# README.md

**MyEventLane** is a community-based event listing and ticketing platform built on Drupal 11.  
It supports user-submitted events, RSVP and ticketing logic, and calendar integrations.

## Tech Stack

- Drupal 11
- DDEV (local dev environment)
- Composer (dependency management)
- Gin Admin Theme (for UI)
- MySQL
- Docker

## Project Areas

- 🔧 **Backend:** Custom content types, field config, RSVP logic, integration with The Events Calendar
- 🎨 **Frontend:** Mobile-first theming, layout, accessibility, and user-facing calendar displays
- 🛠️ **DevOps & Troubleshooting:** Docker, DDEV, Drush, config syncs, Composer and MySQL errors

## Local Setup

```bash
ddev start
ddev drush cim -y
ddev drush cr
