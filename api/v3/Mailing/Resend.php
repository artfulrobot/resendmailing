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
  $spec['mailing_id'] = [
    'api.required' => 1,
    'description' => 'Mailing ID (of completed mailing)',
  ];

  $spec['contact_id'] = [
    'api.required' => 1,
    'description' => 'Contact ID',
  ];

  $spec['email_id'] = [
    'description' => 'Email ID for email address to send to. If ommitted the bulk (or primary) email of the contact will be used. The email must not be on hold.',
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
    'do_not_email' => 0,
    'is_opt_out' => 0,
  ]);
  //->where('e.on_hold = 0')

  // Look up the mailing, make sure it exists.
  civicrm_api3_verify_mandatory($params,
    'CRM_Mailing_DAO_MailingJob',
    ['mailing_id'],
    FALSE
  );

  // Get the email_id, make sure it's not on hold.
  $emailApi = Civi\Api4\Email::get(FALSE)
    ->setCheckPermissions(FALSE)
    ->addWhere('contact_id', '=', $params['contact_id'])
    ->addWhere('on_hold', '=', 0)
    ->addOrderBy('is_bulkmail', 'DESC')
    ->addOrderBy('is_primary', 'DESC');
  if (!empty($params['email_id'])) {
    $emailApi->addWhere('id', '=', $params['email_id']);
  }
  $email = $emailApi->execute()->first();
  if (!($email['id'] ?? NULL)) {
    throw new API_Exception("Failed to find a suitable email for Contact $params[contact_id]");
  }

  // Check whether the contact already has an activity
  // and delete it, to avoid a "DB error: already exists" fatal
  if (Civi::settings()->get('write_activity_record')) {
    CRM_Resendmailing_BAO_Resendmailing::deleteExistingActivityContactRecord($params);
  }

  // OK, we're going for it.
  try {

    // Create a job.
    $jobParams = [
      'status' => 'Scheduled',
      'is_test' => 0,
      'mailing_id' => $params['mailing_id'],
      // This prevents calling CRM_Mailing_BAO_Mailing::getRecipients()
      'is_calling_function_updated_to_reflect_deprecation' => TRUE,
    ];
    $resendJob = civicrm_api3('MailingJob', 'create', $jobParams);

    // Create a single queue item for the job to resend to our email.
    civicrm_api3('MailingEventQueue', 'create', [
      'job_id'     => $resendJob['id'],
      'email_id'   => $email['id'],
      'contact_id' => $params['contact_id'],
    ]);

    // ---- begin hacked copy of CRM_Mailing_BAO_MailingJob::runJobs();
    $job = new CRM_Mailing_BAO_MailingJob();
    $job->id = $resendJob['id'];
    $job->find(TRUE);

    // still use job level lock for each child job
    $lock = Civi::lockManager()->acquire("data.mailing.job.{$job->id}");
    if (!$lock->isAcquired()) {
      throw new API_Exception("Failed to acquire lock.");
    }

    // Get the mailer
    $mailer = \Civi::service('pear_mail');

    // Compose and deliver each child job
    if (\CRM_Utils_Constant::value('CIVICRM_FLEXMAILER_HACK_DELIVER')) {
      $isComplete = Civi\Core\Resolver::singleton()->call(CIVICRM_FLEXMAILER_HACK_DELIVER, [$job, $mailer, NULL]);
    }
    else {
      $isComplete = $job->deliver($mailer, NULL);
    }

    if (!empty($GLOBALS['MailingResendTestShouldDie'])) {
      throw new Exception("Deliberate exception for testing.");
    }
  }
  catch (\Exception $e) {
    // If an error occurred and we ended up leaving a job Scheduled, it will trigger a whole resend.
    // We don't want that, so we cancel the job.
    if (!empty($resendJob['id'])) {
      // Note deliberate mis-spelling of Cancelled
      civicrm_api3('MailingJob', 'update', ['id' => $resendJob['id'], 'status' => 'Canceled']);
    }
    // re-throw
    throw $e;
  }

  CRM_Utils_Hook::post('create', 'CRM_Mailing_DAO_Spool', $job->id, $isComplete);
  // Update job to completed.
  CRM_Mailing_BAO_MailingJob::create(['id' => $job->id, 'end_date' => date('YmdHis'), 'status' => 'Complete']);

  // Release the child job lock
  if ($lock) {
    $lock->release();
  }

  // Return delivered mail info
  // $mailDelivered = CRM_Mailing_Event_BAO_Delivered::getRows($params['mailing_id'], $resendJob['id'], TRUE, NULL, NULL, NULL, 0);

  return civicrm_api3_create_success(1);
}
