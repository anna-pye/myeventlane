<?php
// Run with: ddev drush scr scripts/clean_sync_core_extension.php
use Drupal\Core\Site\Settings;
use Symfony\Component\Yaml\Yaml;

$mods = ['easy_email','easy_email_override','jquery_ui','jquery_ui_resizable','login_emailusername','sam'];
$themes = ['drupal_cms_olivero'];

$sync = Settings::get('config_sync_directory') ?? 'config/sync';
$file = $sync . '/core.extension.yml';

if (!file_exists($file)) {
  throw new \RuntimeException("Sync file not found: $file");
}

$data = Yaml::parse(file_get_contents($file)) ?? [];
$data['module'] = $data['module'] ?? [];
$data['theme']  = $data['theme']  ?? [];

$changed = false;
foreach ($mods as $m) {
  if (isset($data['module'][$m])) { unset($data['module'][$m]); $changed = true; }
}
foreach ($themes as $t) {
  if (isset($data['theme'][$t])) { unset($data['theme'][$t]); $changed = true; }
}

if ($changed) {
  file_put_contents($file, Yaml::dump($data, 4, 2));
  echo "[sync] core.extension.yml updated\n";
} else {
  echo "[sync] core.extension.yml already clean\n";
}

// Optional: delete dependent YAMLs so they can’t resurrect missing modules.
$patterns = [
  "$sync/easy_email.*.yml",
  "$sync/easy_email.yml",
  "$sync/easy_email_override.*.yml",
  "$sync/core.entity_view_display.easy_email.*.yml",
  "$sync/core.entity_form_display.easy_email.*.yml",
  "$sync/field.field.easy_email.*.yml",
  "$sync/field.storage.easy_email.*.yml",
  "$sync/editor.editor.easy_email.yml",
  "$sync/filter.format.easy_email.yml",
  "$sync/sam.settings.yml",
  // If you are NOT shipping the module right now, also drop these:
  "$sync/myeventlane_messaging.template.*.yml",
];

$deleted = 0;
foreach ($patterns as $p) {
  foreach (glob($p) ?: [] as $f) {
    if (@unlink($f)) { $deleted++; echo "[sync] deleted: $f\n"; }
  }
}
echo "[sync] removed $deleted stray file(s)\n";
