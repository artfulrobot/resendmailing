<?php
use CRM_Resendmailing_ExtensionUtil as E;

/**
 * Mailing.Resend API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_mailing_Resend_spec(&$spec) {
  $spec['magicword']['api.required'] = 1;

  $spec['id'] = [
    'api.required' => 1,
    'api.aliases' => ['id', 'mailing_id'],
    'description' => 'Mailing ID (of completed mailing)',
  ];

  $spec['contact_id'] = [
    'api.required' => 1,
    'description' => 'Contact ID',
  ];

  $spec['email'] = [
    'description' => 'Email address to send to. If ommitted the primary email of the contact will be used. The email must not be on hold.',
  ];
}

/**
 * Mailing.Resend API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 *
 * @throws API_Exception
 */
function civicrm_api3_mailing_Resend($params) {


  // Look up the contact, make sure they exist.
  civicrm_api3('Contact', 'getsingle', [
    'contact_id' => $params['contact_id'],
    'is_deleted' => 0,
    'is_deceased' => 0,
  ]);

  // Look up the mailing, make sure it exists.
  $mailing = civicrm_api3('Mailing', 'getsingle', [
    'id' => $params['id'],
  ])['values'];

  if (empty($params['email'])) {
    $email = Civi\Api4\Email::get(FALSE)
      ->setCheckPermissions(FALSE)
      ->addWhere('contact_id', '=', $params['contact_id'])
      ->addWhere('on_hold', '=', 0)
      ->addOrderBy('is_primary', 'DESC')
      ->addOrderBy('is_bulkmail', 'DESC')
      ->execute()->first();
    if (!($email['email'] ?? NULL)) {
      throw new API_Exception("Failed to find a suitable email for Contact $params[contact_id]");
    }
  }
  else {
    if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
      throw new API_Exception("Given email is invalid.");
    }
  }

  // This logic is a copy of civicrm/ang/crmMailing/services.js which has a sendTest method.


  // Begin with a copy of the api3 values.
  $mailingParams = $mailing;

  // Add in our email.
  $mailingParams['email'] = $params['email'];
  // Add a chain call.
  $mailingParams['api.Mailing.send_test'] = [
    'mailing_id' => '$value.id',
    'test_email' => $params['email'],
    'test_group' => NULL,
  ];
  // options:  {force_rollback: 1}, // Test mailings include tracking features, so the mailing must be persistent

  // WORKAROUND: Mailing.create (aka CRM_Mailing_BAO_Mailing::create()) interprets scheduled_date
  // as an *intent* to schedule and creates tertiary records. Saving a draft with a scheduled_date
  // is therefore not allowed. Remove this after fixing Mailing.create's contract.
  unset($mailingParams['scheduled_date']);

  unset($mailingParams['jobs']);
  unset($mailingParams['recipients']);
  // skip recipient rebuild while sending test mail
  $mailingParams['_skip_evil_bao_auto_recipients_'] = 1;

  $result = civicrm_api3('Mailing', 'create', $params);

  $returnValues = $result['values'][$result['id']]['api.Mailing.send_test']['values'] ?? NULL;

  return civicrm_api3_create_success($returnValues, $params, 'Mailing', 'Resend');
}
