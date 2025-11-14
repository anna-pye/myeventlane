MyEventLane Views Access Plugin Bundle
---------------------------------------

Includes:
- Custom module: myeventlane_views
- Views access plugin: vendor_store_access

Install steps:

1. Copy `myeventlane_views/` to:
   web/modules/custom/myeventlane_views/

2. Run:
   ddev drush en myeventlane_views -y
   ddev drush cr

3. Test access:
   Visit /dashboard/attendees as admin

4. Check logs:
   ddev drush watchdog:show | grep access_debug

You should see:
   👤 Checking access for UID 1...
   ✅ Access granted: UID 1
