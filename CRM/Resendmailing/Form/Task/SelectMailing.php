<?php

use CRM_Resendmailing_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Resendmailing_Form_Task_SelectMailing extends CRM_Contact_Form_Task {
  public function buildQuickForm() {

    // Get a list of all mailings.
    $mailings = civicrm_api3('Mailing', 'get', [
      'return'  => ['name', 'created_date', 'scheduled_date'],
      'options' => ['limit' => 0, 'sort' => 'created_date DESC'],
    ]);

    $options = [];
    foreach ($mailings['values'] as $mid => $details) {
      $options[$mid] = (empty($details['scheduled_date']) ? '[DRAFT] ' : '[SENT] ')
        . ($details['name'] ?? '(untitled)')
        . " (created $details[created_date])";
    }

    // add form elements
    $this->add(
      'select', // field type
      'mailing_id', // field name
      'Mailing to copy', // field label
      $options,
      TRUE // is required
    );
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Create Mailing'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    if (!$values['mailing_id']) {
      // Should not happen, as it's required.
      throw new \Exception("Missing mailing ID");
    }

    // Clone the original mailing.
    $clone = civicrm_api3('Mailing', 'clone', ['id' => $values['mailing_id']]);

    // Create a hidden group for these contacts.
    list ($groupId, $ssId) = $this->createHiddenGroup();

    // Alter the mailng groups in the mailing.
    $params = [
      'id' => $clone['id'],
      'groups' => [
        'include' => [$groupId],
        'exclude' => [],
        'base' => [],
      ],
      'mailings' => [
        'include' => [],
        'exclude' => [],
      ],
    ];
    civicrm_api3('Mailing', 'create', $params);

    // Redirect to the mailing.
    CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/a/', NULL, TRUE, '/mailing/' . $clone['id']));
  }

}
