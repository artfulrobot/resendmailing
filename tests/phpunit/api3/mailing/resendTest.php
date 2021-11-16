<?php

use CRM_Resendmailing_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 *
 * Tips:
 *  - With HookInterface, you may implement CiviCRM hooks directly in the test class.
 *    Simply create corresponding functions (e.g. "hook_civicrm_post(...)" or similar).
 *  - With TransactionalInterface, any data changes made by setUp() or test****() functions will
 *    rollback automatically -- as long as you don't manipulate schema or truncate tables.
 *    If this test needs to manipulate schema or truncate tables, then either:
 *       a. Do all that using setupHeadless() and Civi\Test.
 *       b. Disable TransactionalInterface, and handle all setup/teardown yourself.
 *
 * @group headless
 */
class api3_mailing_resendTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {
  use \Civi\Test\Api3TestTrait;

  public static $sentMail = [];

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->install('org.civicrm.flexmailer')
      ->apply();
  }

  public function setUp() {
    // I think this is the default anyway.
    // civicrm_api3('setting', 'create', ['write_activity_record' => 1]);
    civicrm_api3('setting', 'create', ['flexmailer_traditional' => 'flexmailer']);
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   */
  public function setupFixture() {
    Civi::settings()->set('mailing_backend', [
      'outBound_option' => CRM_Mailing_Config::OUTBOUND_OPTION_MOCK,
      'preSendCallback' => [static::class, 'captureMailSent'],
    ]);

    // Create 2 contacts.
    $wilmaID = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'wilma@example.org',
    ])['id'];

    $bettyID = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'betty@example.org',
    ])['id'];

    $barneyID = $this->callAPISuccess('Contact', 'create', [
      'contact_type' => 'Individual',
      'email' => 'barney@example.org',
    ])['id'];

    // Create the mailing group
    $mailingGroupID = $this->callAPISuccess('Group', 'create', [
      'title' => 'test_group',
      'name' => 'test_group',
      'group_type' => "Mailing List",
    ])['id'];

    // Add the contacts to a mailing group.
    list($total, $added, $notAdded) = CRM_Contact_BAO_GroupContact::addContactsToGroup([$wilmaID, $bettyID], $mailingGroupID);
    $this->assertEquals(2, $total);
    $this->assertEquals(2, $added);
    $this->assertEquals(0, $notAdded);

    // Create default from address.
    $fromEmailOptionValue = civicrm_api3('OptionValue', 'create', [
      'label' => 'test <test@example.org>',
      'option_group_id' => 'from_email_address',
      'is_default' => 1,
      'is_active' => 1,
    ]);

    // Create default mail account.
    $result = civicrm_api3('MailSettings', 'create', [
      'sequential' => 1,
      'name' => "default",
      'domain_id' => 1,
      'domain' => "example.org",
      'localpart' => "test",
      'is_default' => 1,
      'source' => "/tmp",
    ]);

    // Create a mailing
    $mailingID = $this->callAPISuccess('Mailing', 'create', [
      'name' => __FUNCTION__,
      'subject' => __FUNCTION__,
      'body_text' => __FUNCTION__,
      'from_name' => __FUNCTION__,
      'from_email' => "test@example.org",
      'scheduled_date' => date('Y-m-d'),
      'scheduled_id' => 1,
      'groups' => [
        'include' => [$mailingGroupID]
      ]
    ])['id'];

    // Send the mailing.
    $processMailingResult = civicrm_api3('Job', 'process_mailing');
    $this->assertEquals(2, count(static::$sentMail));
    $this->countJobs($mailingID, "after initial mailing send", 2, 2);

    // Reset our little counter.
    static::$sentMail = [];

    return [$wilmaID, $bettyID, $barneyID, $mailingGroupID, $mailingID];
  }

  /**
   */
  public function testResendToNewRecipient() {
    list($wilmaID, $bettyID, $barneyID, $mailingGroupID, $mailingID) = $this->setupFixture();

    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(a.id) c
      FROM civicrm_activity a
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3
      WHERE a.source_record_id = $mailingID
    ");
    $this->assertTrue($dao->fetch());
    $this->assertEquals(2, $dao->c);

    // Now call resend
    $result = civicrm_api3('Mailing', 'resend', [
      'mailing_id' => $mailingID,
      'contact_id' => $barneyID,
    ]);
    $this->assertEquals(1, count(static::$sentMail));
    $this->countJobs($mailingID, "after resend", 3, 3);

    $dao = CRM_Core_DAO::executeQuery("SELECT COUNT(a.id) c
      FROM civicrm_activity a
      INNER JOIN civicrm_activity_contact ac ON ac.activity_id = a.id AND ac.record_type_id = 3
      WHERE a.source_record_id = $mailingID
    ");
    $this->assertTrue($dao->fetch());
    $this->assertEquals(3, $dao->c);

    $this->assertProcessMailingSendsNoMail($mailingID);
  }

  /**
   */
  public function testResendToExistingRecipient() {
    list($wilmaID, $bettyID, $barneyID, $mailingGroupID, $mailingID) = $this->setupFixture();

    // Now call resend
    $result = civicrm_api3('Mailing', 'resend', [
      'mailing_id' => $mailingID,
      'contact_id' => $bettyID,
    ]);
    $this->assertEquals(1, count(static::$sentMail));
    $this->assertEquals('<betty@example.org>', static::$sentMail[0][1]);

    $this->assertProcessMailingSendsNoMail($mailingID);
  }

  /**
   */
  public function testResendToExistingRecipientDifferentEmail() {
    list($wilmaID, $bettyID, $barneyID, $mailingGroupID, $mailingID) = $this->setupFixture();

    // Create a 2nd email for betty.
    $emailID = Civi\Api4\Email::create(FALSE)->addValue('email', 'betty2@example.org')->addValue('contact_id', $bettyID)->execute()->first()['id'];

    // @todo count jobs table.

    // Now call resend
    $result = civicrm_api3('Mailing', 'resend', [
      'mailing_id' => $mailingID,
      'contact_id' => $bettyID,
      'email_id' => $emailID,
    ]);
    $this->assertEquals(1, count(static::$sentMail));
    $this->assertEquals('<betty2@example.org>', static::$sentMail[0][1]);

    $this->assertProcessMailingSendsNoMail($mailingID);
  }

  /**
   */
  public function testResendToExistingRecipientWithCrash() {
    list($wilmaID, $bettyID, $barneyID, $mailingGroupID, $mailingID) = $this->setupFixture();
    $GLOBALS['MailingResendTestShouldDie'] = 1;

    try {
      // Now call resend - this should throw exception.
      $result = civicrm_api3('Mailing', 'resend', [
        'mailing_id' => $mailingID,
        'contact_id' => $bettyID,
      ]);
      $this->fail("exception should have been thrown");
    }
    catch (Exception $e) {
      $this->assertEquals('Deliberate exception for testing.', $e->getMessage());
    }
    unset($GLOBALS['resendTestShouldDie']);

    // We still expect one mailing to have been sent, as that was done before the crash?
    $this->assertEquals(1, count(static::$sentMail));
    // But we expect that it wasn't recorded... @todo

    $this->assertProcessMailingSendsNoMail($mailingID);
  }

  /**
   * Capture sent mail.
   *
   * Nb. the params are a bit of a guess...
   */
  public static function captureMailSent(\Mail_mock $mailMock, $recipientEmail, $headers) {
    static::$sentMail[] = func_get_args();
  }

  public function assertProcessMailingSendsNoMail($mailingID) {
    list($origJobs, $origEvents) = $this->countJobs($mailingID);
    static::$sentMail = [];
    civicrm_api3('Job', 'process_mailing');
    $this->assertEquals(0, count(static::$sentMail));
    $this->countJobs($mailingID, "After re-running Job.process_mailing do not expect more jobs or events.", $origJobs, $origEvents);
  }
  public function countJobs($mailingID, $note = NULL, $expectedJobs = 0, $expectedEvents = 0) {
    $sql = "SELECT COUNT(ev.id) events, COUNT(distinct j.id) jobs
      FROM civicrm_mailing_job j
      LEFT JOIN civicrm_mailing_event_queue ev ON ev.job_id = j.id
      WHERE j.mailing_id = $mailingID";
    $dao = CRM_Core_DAO::executeQuery($sql);
    $dao->fetch();
    if ($note === NULL) {
      return [$dao->jobs, $dao->events];
    }
    $this->assertEquals($expectedJobs, $dao->jobs, "$note: expected jobs differs.");
    $this->assertEquals($expectedEvents, $dao->events, "$note: expected jobs differs.");
  }
}
