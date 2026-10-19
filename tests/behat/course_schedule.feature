@local @local_sis_courseschedule @javascript
Feature: Course access schedules
  In order to limit online access to a course at specific times
  As a teacher
  I can configure a schedule while keeping other courses available

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student1 | Schedule  | Student  | student@example.com |
      | teacher1 | Schedule  | Teacher  | teacher@example.com |
    And the following "courses" exist:
      | fullname     | shortname | format |
      | Schedule A   | CSA       | topics |
      | Schedule B   | CSB       | topics |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | CSA    | student        |
      | student1 | CSB    | student        |
      | teacher1 | CSA    | editingteacher |
    And the following "activities" exist:
      | activity | course | name            | intro           |
      | page     | CSA    | Closed content  | Restricted text |
      | page     | CSB    | Open content    | Available text  |

  Scenario: A closed course shows its schedule and another course remains accessible
    Given the course "CSA" is closed by its access schedule
    When I log in as "student1"
    And I am on "CSA" course homepage
    Then I should see "It is not possible to access this course at the moment."
    And I should see "America/Fortaleza"
    When I am on "CSB" course homepage
    Then I should see "Open content"

  Scenario: A teacher can save the course schedule through the form
    When I log in as "teacher1"
    And I am on "CSA" course homepage
    And I navigate to "Access schedules" in current page administration
    And I expand all fieldsets
    And I set the field "Course time zone" to "America/Fortaleza"
    And I press "Add period (08:00-12:00)"
    And I press "Save changes"
    Then I should see "America/Fortaleza"
    And I should see "Monday: 08:00 - 12:00"
