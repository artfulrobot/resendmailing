console.log("loaded 16");
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
          'return'    : 'contact_id,email',
          'contact_id': "user_contact_id",
          'is_primary': 1,
          'sequential': 1,
          'api.Contact.get' : {'return' : "display_name", sequential: 1},
        })
        .then(r => {
          var vals = r.values[0];
          vals.display_name = (vals['api.Contact.get'].values || [{display_name: null}])[0].display_name;
          return vals;
        });
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

    this.contactID = loggedIn.contact_id;
    this.toEmail = loggedIn.email;
    this.contactName = loggedIn['api.Contact.get']

    this.updateEmail = () => {
      var oldToEmail = this.toEmail;
      if (this.contactID) {

        crmApi('Email', 'get', {
          contact_id: this.contactID,
          sequential: 1,
          options: {sort: ['is_primary DESC', 'is_bulkmail DESC']}
        })
        .then(r => {
          r = r.values;
          // If original email is in this list, use that.
          var found = false;
          r.forEach(e => {
            if (e.email === oldToEmail) {
              this.toEmail = oldToEmail;
              found = true;
            }
          });
          if (found) {
            return;
          }

          if (r[0] && r[0].email) {
            this.toEmail = r[0].email;
          }
        })
        .catch(e => {
          console.error(e);
        });
      }
    };
    //$scope.$watch('$scope.contactID', this.updateEmail);

    this.send = function() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Sending...'), success: ts('Sent')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Mailing', 'resend', {
          id: ctrl.mailingID,
          toEmail: ctrl.toEmail,
          contactID: ctrl.contactID
        })
      );
    };
  });

})(angular, CRM.$, CRM._);
