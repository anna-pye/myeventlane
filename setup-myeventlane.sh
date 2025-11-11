#!/bin/bash
set -e

echo "🚀 Starting MyEventLane DDEV Build Setup..."

# ---------------------------------------------------------------------
# 1. Ensure you are inside your DDEV container
# ---------------------------------------------------------------------
ddev start
ddev ssh <<'EOF'

cd /var/www/html

# ---------------------------------------------------------------------
# 2. Update Composer and Drupal Core
# ---------------------------------------------------------------------
composer self-update
composer update drupal/core-recommended drupal/core-composer-scaffold drupal/core-project-message --with-all-dependencies -n

# ---------------------------------------------------------------------
# 3. Core modules + essentials
# ---------------------------------------------------------------------
composer require drupal/pathauto drupal/token drupal/redirect drupal/metatag drupal/field_group \
  drupal/paragraphs drupal/media_library drupal/views_bulk_operations drupal/color_field drupal/fullcalendar_view \
  drupal/leaflet drupal/mailchimp drupal/mailsystem drupal/smtp drupal/flag drupal/config_split drupal/content_moderation -n

# ---------------------------------------------------------------------
# 4. Drupal Commerce stack
# ---------------------------------------------------------------------
composer require drupal/commerce:^3 drupal/commerce_stripe:^1 drupal/rules drupal/inline_entity_form -n

# ---------------------------------------------------------------------
# 5. Admin + UX helpers
# ---------------------------------------------------------------------
composer require drupal/gin drupal/gin_toolbar drupal/claro drupal/admin_toolbar -n

# ---------------------------------------------------------------------
# 6. Enable base modules
# ---------------------------------------------------------------------
drush en -y pathauto token redirect metatag field_group paragraphs media_library color_field \
  fullcalendar_view leaflet mailsystem smtp flag config_split content_moderation \
  commerce commerce_cart commerce_checkout commerce_product commerce_order commerce_price commerce_store commerce_stripe \
  rules inline_entity_form gin gin_toolbar admin_toolbar

# ---------------------------------------------------------------------
# 7. Create directories for custom code
# ---------------------------------------------------------------------
mkdir -p web/modules/custom/{myeventlane_rsvp,myeventlane_tickets,myeventlane_dashboard,myeventlane_boost,myeventlane_checkout}
mkdir -p web/themes/custom/myeventlane/scss
mkdir -p web/themes/custom/myeventlane/templates

# ---------------------------------------------------------------------
# 8. Initialize theme library
# ---------------------------------------------------------------------
echo "libraries:
  global:
    css:
      theme:
        css/style.css: {}
    js:
      js/global.js: {}
    dependencies:
      - core/drupal
      - core/jquery
" > web/themes/custom/myeventlane/myeventlane.libraries.yml

# ---------------------------------------------------------------------
# 9. Enable your theme
# ---------------------------------------------------------------------
drush theme:enable myeventlane
drush config-set system.theme default myeventlane -y
drush config-set system.theme admin gin -y

# ---------------------------------------------------------------------
# 9b. Copy initial theme assets and SCSS base
# ---------------------------------------------------------------------
mkdir -p web/themes/custom/myeventlane/css web/themes/custom/myeventlane/js web/themes/custom/myeventlane/assets/branding

# Add placeholder files so Drupal can attach the library cleanly
echo "/* MyEventLane base styles */" > web/themes/custom/myeventlane/css/style.css
echo "// MyEventLane global JS placeholder" > web/themes/custom/myeventlane/js/global.js

# Optionally copy your logo if you have it in /mnt/data or local path
# cp /path/to/MyEventLane_Logo_Transparent.png web/themes/custom/myeventlane/assets/branding/

# ---------------------------------------------------------------------
# 10. Content types, taxonomies, and fields (basic skeleton)
# ---------------------------------------------------------------------
drush genc --content-type=event
drush genc --content-type=rsvp_submission
drush genc --vocabulary=event_category
drush genc --vocabulary=accessibility_flags

# ---------------------------------------------------------------------
# 11. Enable Commerce store for default vendor
# ---------------------------------------------------------------------
drush php:eval "\$store = \Drupal\commerce_store\Entity\Store::create(['type'=>'default','name'=>'My Event Lane','mail'=>'info@myeventlane.au','default_currency'=>'AUD','timezone'=>'Australia/Sydney']); \$store->save();"

# ---------------------------------------------------------------------
# 12. Rebuild caches and confirm setup
# ---------------------------------------------------------------------
drush cr
drush status

EOF

echo "✅ MyEventLane base stack installed successfully!"
echo "Next steps:"
echo "  1️⃣  Implement your multi-step event form panels (Basics → Design → Tickets → Publish)"
echo "  2️⃣  Begin coding RSVP logic in /web/modules/custom/myeventlane_rsvp/"
echo "  3️⃣  Build your vendor dashboard controller under /web/modules/custom/myeventlane_dashboard/"