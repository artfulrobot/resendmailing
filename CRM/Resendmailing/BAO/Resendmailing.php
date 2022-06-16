<?php

use CRM_Resendmailing_ExtensionUtil as E;

class CRM_Resendmailing_BAO_Resendmailing {

  /**
   * Deletes an existing civicrm_activity_contact record to avoid a
   * "DB error: already exists" fatal error, which can lead to other bugs.
   *
   * A lot of code here is copied from CRM_Mailing_BAO_MailingJob::writeToDB().
   */
  public static function deleteExistingActivityContactRecord($params) {
    $mailing = \Civi\Api4\Mailing::get(FALSE)
      ->addSelect('sms_provider_id')
      ->addWhere('id', '=', $params['mailing_id'])
      ->execute()
      ->single();

    if ($mailing->sms_provider_id) {
      $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Mass SMS');
    }
    else {
      $activityTypeID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_Activity', 'activity_type_id', 'Bulk Email');
    }
    if (!$activityTypeID) {
      throw new CRM_Core_Exception(ts('No relevant activity type found when recording Mailing Event delivered Activity'));
    }

    // Fetch the activity linked to this mailing
    $query = "SELECT id
      FROM civicrm_activity
      WHERE civicrm_activity.activity_type_id = %1
        AND civicrm_activity.source_record_id = %2";
 
    $activityID = CRM_Core_DAO::singleValueQuery($query, [
      1 => [$activityTypeID, 'Positive'], 
      2 => [$params['mailing_id'], 'Positive'], 
    ]);

    $targetRecordID = CRM_Core_PseudoConstant::getKey('CRM_Activity_BAO_ActivityContact', 'record_type_id', 'Activity Targets');

    if ($activityID) {
      // Delete the existing record, because writeToDB will re-create it
      $sql = "DELETE FROM civicrm_activity_contact
        WHERE activity_id = %1
          AND contact_id = %2
          AND record_type_id = %3";

      CRM_Core_DAO::executeQuery($sql, [
        1 => [$activityID, 'Positive'],
        2 => [$params['contact_id'], 'Positive'],
        3 => [$targetRecordID, 'Positive'],
      ]);
    }
  }

}
