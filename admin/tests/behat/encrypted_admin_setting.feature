@core @core_admin @mdl65818
Feature: Encrypted admin setting UI works
  In order to edit encrypted admin settings
  As an admin
  I need for the admin editing interface to actually work

  Scenario: Set the admin setting with Behat step
    When the following config values are set as admin:
      | sillypassword | Frogs! | | encrypted |
    And I log in as "admin"
    And I am on site homepage
    Then I should see "The silly password is: Frogs!"

