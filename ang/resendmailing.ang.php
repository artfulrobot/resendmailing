<?php
// This file declares an Angular module which can be autoloaded
// in CiviCRM. See also:
// \https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules/n
return [
  'js' => [
    'ang/resendmailing.js',
    'ang/resendmailing/*.js',
    'ang/resendmailing/*/*.js',
  ],
  'css' => [
    'ang/resendmailing.css',
  ],
  'partials' => [
    'ang/resendmailing',
  ],
  'requires' => [
    'crmUi',
    'crmUtil',
    'ngRoute',
  ],
  'settings' => [],
];
