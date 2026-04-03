<?php
#ddev-generated

// This script must be run in the context of a Drupal site, e.g. `ddev php drupal-breakpoints.php`.

$bm = \Drupal::service('breakpoint.manager');
foreach ($bm->getGroups() as $group_id => $group_label) {
  foreach ($bm->getBreakpointsByGroup($group_id) as $breakpoint_id => $breakpoint) {
    $label = $breakpoint->getLabel();
    preg_match_all('/(min|max)-width:\s*(\d+)px/', $breakpoint->getMediaQuery(), $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
      $w = (int) $match[2];
      $h = $w < 600 ? $w * 2 : (int) ($w * 0.75);
      $minmax = $match[1] === 'min' ? 'Minimum Width' : 'Maximum Width';

      echo strtr($breakpoint_id, '._', '--') . "-" . $match[1] . ":\n";
      echo "  name: " . $group_label . " " . $label . " - " . $minmax ."\n";
      echo "  viewport_width: $w\n";
      echo "  viewport_height: $h\n\n";
    }
  }
}