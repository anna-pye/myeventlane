<?php

namespace Drupal\myeventlane_theme\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MELThemeExtension extends AbstractExtension {
  public function getFunctions() {
    return [
      new TwigFunction('create_dump_file', [$this, 'createDumpFile']),
    ];
  }

  public function createDumpFile($title, $mode, $count) {
    $msg = "🧪 Twig test: $title | mode: $mode | products: $count\n";
    file_put_contents('/tmp/_twig_node_debug.txt', $msg, FILE_APPEND);
    return '';
  }
}