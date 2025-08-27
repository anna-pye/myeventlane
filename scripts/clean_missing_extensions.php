<?php
// scripts/clean_missing_extensions.php
// Run with: ddev drush scr scripts/clean_missing_extensions.php
use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$syncDir = getenv('CONFIG_SYNC_DIRECTORY') ?: 'config/sync'; // adjust if custom

$toRemoveModules = [
  'easy_email',
  'easy_email_override',
  'jquery_ui',
  'jquery_ui_resizable',
  'login_emailusername',
  'sam',
];
$toRemoveThemes = [
  'drupal_cms_olivero',
];

// Extra config files to delete from sync (patterns)
$deleteGlobs = [
  // Easy Email stuff
  "$syncDir/easy_email.*.yml",
  "$syncDir/easy_email.yml",
  "$syncDir/core.entity_view_display.easy_email.*.yml",
  "$syncDir/core.entity_form_display.easy_email.*.yml",
  "$syncDir/field.field.easy_email.*.yml",
  "$syncDir/field.storage.easy_email.*.yml",
  "$syncDir/editor.editor.easy_email.yml",
  "$syncDir/filter.format.easy_email.yml",
  "$syncDir/easy_email_override.*.yml",

  // Your custom messaging templates if you’re NOT shipping the module now:
  "$syncDir/myeventlane_messaging.template.*.yml",

  // SAM module config if present
  "$syncDir/sam.settings.yml",
];

// 1) Remove from ACTIVE core.extension.
$edit = \Drupal::configFactory()->getEditable('core.extension');
$changed = false;
foreach ($toRemoveModules as $m) {
  if ($edit->get("module.$m") !== NULL) {
    $edit->clear("module.$m"); $changed = true;
  }
}
foreach ($toRemoveThemes as $t) {
  if ($edit->get("theme.$t") !== NULL) {
    $edit->clear("theme.$t"); $changed = true;
  }
}
if ($changed) {
  $edit->save();
  print "[active] Cleaned core.extension\n";
} else {
  print "[active] core.extension already clean\n";
}

// 2) Edit SYNC copy of core.extension.yml (remove missing extensions).
$coreExtFile = "$syncDir/core.extension.yml";
if (file_exists($coreExtFile)) {
  $yaml = file_get_contents($coreExtFile);
  if ($yaml === false) {
    throw new \RuntimeException("Cannot read $coreExtFile");
  }
  // Super simple key-stripper: remove lines matching our keys.
  // (Avoids requiring symfony/yaml in this script context.)
  $lines = explode("\n", $yaml);
  $keys = [];
  foreach ($toRemoveModules as $m) { $keys[] = "  $m:"; }
  foreach ($toRemoveThemes as $t)   { $keys[] = "  $t:"; }

  $out = [];
  $skipCount = 0;
  foreach ($lines as $line) {
    $trim = ltrim($line);
    $shouldSkip = false;
    // Match module.* or theme.* sections
    foreach ($keys as $needle) {
      if (strpos($trim, $needle) === 0) {
        $shouldSkip = true;
        $skipCount++;
        break;
      }
    }
    if (!$shouldSkip) { $out[] = $line; }
  }
  file_put_contents($coreExtFile, implode("\n", $out));
  print "[sync] core.extension.yml stripped $skipCount entr".($skipCount===1?'y':'ies')."\n";
} else {
  print "[sync] $coreExtFile not found (check CONFIG_SYNC_DIRECTORY)\n";
}

// 3) Remove dependent YAMLs from sync so they don’t resurrect refs.
$deleted = 0;
foreach ($deleteGlobs as $pattern) {
  foreach (glob($pattern) ?: [] as $file) {
    if (@unlink($file)) {
      $deleted++;
      print "[sync] deleted: $file\n";
    }
  }
}
print "[sync] removed $deleted stray file".($deleted===1?'':'s')."\n";

print "Done. Now run: drush cr; drush cim -y; drush updb -y; drush cr\n";
