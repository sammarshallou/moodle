@tool @tool_task
Feature: See running scheduled tasks
  In order to configure scheduled tasks
  As an admin
  I need to be see if tasks are running, and also if they are completely disabled

  Background:
    Given I log in as "admin"

  Scenario: If no tasks are running and tasks are not disabled, I should not see those messages
    When I navigate to "Server > Tasks > Scheduled tasks" in site administration
    Then I should not see "Background processing is disabled"
    And I should see "No tasks are running now"

  Scenario: If tasks are disabled, I should see a message which links me to the setting
    When the following config values are set as admin:
      | task_disable_processing | 1 |
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration
    Then I should see "Background processing is disabled"
    And I follow "Settings"
    And I should see "Disable background tasks"

  @javascript
  Scenario: If tasks are running, I should see a message informing me about that
    When I pretend that the following tasks are running:
      | type      | classname                            | seconds |
      | scheduled | \core\task\automated_backup_task     | 5       |
      | adhoc     | \core\task\asynchronous_backup_task  | 17      |
      | adhoc     | \core\task\asynchronous_restore_task | 121     |
    And I navigate to "Server > Tasks > Scheduled tasks" in site administration
    Then I should see "Tasks running now"
    And I should see "Last updated"

    # Check task details.
    And I should see "Scheduled" in the "Automated backups" "table_row"
    And I should see "Ad-hoc" in the "core\task\asynchronous_backup_task" "table_row"
    And I should see "Ad-hoc" in the "core\task\asynchronous_restore_task" "table_row"

    # Check times (either seconds or the other format for longer ones).
    And I should see "secs" in the "Automated backups" "table_row"
    And I should see "secs" in the "core\task\asynchronous_backup_task" "table_row"
    And I should see "0d 0h 2m" in the "core\task\asynchronous_restore_task" "table_row"

    # Check the AJAX refresh after finishing 2 tasks.
    And I pretend that the following tasks are running:
      | type      | classname                            | seconds |
      | scheduled | \core\task\automated_backup_task     |         |
      | adhoc     | \core\task\asynchronous_restore_task |         |
    And I press "Refresh"
    And I should not see "Automated backups" in the ".tool_task_running" "css_element"
    And I should not see "core\task\asynchronous_restore_task" in the ".tool_task_running" "css_element"
    And I should see "core\task\asynchronous_backup_task" in the ".tool_task_running" "css_element"
