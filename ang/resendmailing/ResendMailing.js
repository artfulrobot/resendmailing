(function(angular, $, _) {

  const sharedRoute = {
    controller: 'ResendmailingResendMailing',
    controllerAs: '$ctrl',
    templateUrl: '~/resendmailing/ResendMailing.html',

    // If you need to look up data when opening the page, list it out
    // under "resolve".
    resolve: {
      mailings: function(crmApi) {
        const threeMonthsAgo = new Date();
        threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
        threeMonthsAgo.setTime(0,0,0);

        return crmApi('Mailing', 'get', {
          "sequential": 1,
          "return": ["id","name","subject","approval_date"],
          "approval_date": {">":threeMonthsAgo.toISOString().substr(0, 10)},
          'options' : {sort : "approval_date DESC"}
        })
        .then(r => r.values || []);
      },
      loggedIn: function(crmApi) {
        return crmApi('Email', 'get', {
          'return'    : 'contact_id,id',
          'contact_id': "user_contact_id",
          'is_primary': 1,
          'sequential': 1,
        })
        .then(r => r.values[0]);
      }
    }
  };
  angular.module('resendmailing').config(function($routeProvider) {
      $routeProvider.when('/resendmailing', sharedRoute);
      $routeProvider.when('/resendmailing/:mlid', sharedRoute);
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('resendmailing').controller('ResendmailingResendMailing', function($scope, $route, crmApi, crmStatus, crmUiHelp, mailings, loggedIn) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('resendmailing');
    //var hs = $scope.hs = crmUiHelp({file: 'CRM/resendmailing/ResendMailing'}); // See: templates/CRM/resendmailing/ResendMailing.hlp
    // Local variable for this controller (needed when inside a callback fn where `this` is not available).
    var ctrl = this;

    // We have myContact available in JS. We also want to reference it in HTML.
    this.mailings = mailings;

    if ($route.current.params.mlid) {
      this.mailingID = $route.current.params.mlid;
    }
    this.contactID = loggedIn.contact_id;
    this.emailID = loggedIn.id;
    this.contactName = loggedIn['api.Contact.get']

    this.updateEmail = () => {
      this.emails = [];

      if (this.contactID) {

        crmApi('Email', 'get', {
          contact_id: this.contactID,
          sequential: 1,
          options: {sort: ['is_bulkmail DESC', 'is_primary DESC']}
        })
        .then(r => {
          // If original email is in this list, use that.
          this.emails = r.values;
          if (this.emails[0]) {
            this.emailID = this.emails[0].id;
          }
        })
        .catch(e => {
          console.error(e);
        });
      }
    };
    this.updateEmail();

    this.send = function() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Sending...'), success: ts('Sent')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Mailing', 'resend', {
          mailing_id: ctrl.mailingID,
          email_id: ctrl.emaiID,
          contact_id: ctrl.contactID
        })
      );
    };
  });

})(angular, CRM.$, CRM._);
