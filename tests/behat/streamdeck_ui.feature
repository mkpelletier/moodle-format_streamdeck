@format @format_streamdeck
Feature: Streamdeck learner interface
  In order to move through a course like a streaming service
  As a student
  I need the course to stay focused on its episodes, in the colour mode I choose

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname      | shortname | format     | numsections |
      | Stream course | SC1       | streamdeck | 3           |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | SC1    | student |
    And the following "activities" exist:
      | activity | course | section | name          | externalurl                                 |
      | quiz     | SC1    | 1       | Final exam    |                                             |
      | url      | SC1    | 2       | Lecture video | https://www.youtube.com/watch?v=dQw4w9WgXcQ |
      | page     | SC1    | 3       | Reading notes |                                             |

  Scenario: Learners do not get the course index
    When I am on the "SC1" "Course" page logged in as "student1"
    Then "[data-region='course-index']" "css_element" should not exist
    And I am on the "Final exam" "quiz activity" page
    And "[data-region='course-index']" "css_element" should not exist
    And I should see "Learning episodes" in the ".streamdeck-activity-toolbar" "css_element"

  Scenario: An assessment-only unit puts the assessment centre stage
    When I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 1" "link" in the ".streamdeck-main-strip" "css_element"
    Then I should see "Final exam" in the ".streamdeck-episode-title" "css_element"
    And I should see "Start" in the ".streamdeck-stage" "css_element"
    And ".related-sidebar" "css_element" should not exist
    And I should see "Next episode" in the ".streamdeck-episode-nav" "css_element"

  Scenario: A learning activity keeps the stage
    When I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    Then I should see "Lecture video" in the ".streamdeck-episode-title" "css_element"
    And ".streamdeck-episode-video iframe" "css_element" should exist
    And I should see "Previous episode" in the ".streamdeck-episode-nav" "css_element"

  @javascript
  Scenario: Learners choose light or dark and it sticks
    Given I am on the "SC1" "Course" page logged in as "student1"
    And "html[data-bs-theme='dark']" "css_element" should exist
    When I click on "View options" "button" in the ".hero" "css_element"
    And I click on "Light" "button" in the ".hero .dropdown-menu" "css_element"
    Then "html[data-bs-theme='light']" "css_element" should exist
    And I reload the page
    And "html[data-bs-theme='light']" "css_element" should exist

  @javascript
  Scenario: Content playing on the episode stage does not repeat the page chrome
    Given I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 3" "link" in the ".streamdeck-main-strip" "css_element"
    And I should see "Reading notes" in the ".streamdeck-episode-title" "css_element"
    When I switch to "streamdeck-episode-video-iframe" class iframe
    Then I should see "Test page content"
    And ".streamdeck-activity-toolbar" "css_element" should not be visible
    And ".navbar" "css_element" should not be visible
    And "#page-header" "css_element" should not be visible

  @javascript
  Scenario: Teachers group episodes into seasons and learners switch between them
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tia       | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | SC1    | editingteacher |
    And I am on the "SC1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I edit the section "2"
    And I set the field "Starts a new Season" to "Nouns"
    And I set the field "Running time (minutes)" to "12"
    And I press "Save changes"
    And I log out
    When I am on the "SC1" "Course" page logged in as "student1"
    Then I should see "Introduction" in the ".streamdeck-season-tabs" "css_element"
    And I should see "1 episode" in the "#streamdeck-season-1-tab" "css_element"
    And I should see "Nouns" in the ".streamdeck-season-tabs" "css_element"
    And I should see "2 episodes" in the "#streamdeck-season-2-tab" "css_element"
    And I should see "Section 1" in the "#streamdeck-season-1" "css_element"
    And "#streamdeck-season-2" "css_element" should not be visible
    And I click on "#streamdeck-season-2-tab" "css_element"
    And I should see "Section 2" in the "#streamdeck-season-2" "css_element"
    And I should see "12 mins" in the "#streamdeck-season-2" "css_element"
    And "#streamdeck-season-1" "css_element" should not be visible

  @javascript
  Scenario: Teachers describe an episode and learners can fold the description away
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tia       | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | SC1    | editingteacher |
    And I am on the "SC1" "Course" page logged in as "teacher1"
    And I turn editing mode on
    And I edit the section "2"
    And I set the field "Show \"About this episode\"" to "1"
    And I set the field "id_about_editor" to "Name the parts of speech"
    And I press "Save changes"
    And I log out
    When I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    Then I should see "Name the parts of speech" in the ".streamdeck-about-card" "css_element"
    And I click on "About this episode" "text" in the ".streamdeck-about-card" "css_element"
    And I should not see "Name the parts of speech"

  @javascript
  Scenario: Learners open and close the blocks drawer when the course offers it
    Given the following "courses" exist:
      | fullname     | shortname | format     | numsections | enabledrawertoggle |
      | Drawer course | DC1      | streamdeck | 1           | 1                  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | DC1    | student |
    And the following "blocks" exist:
      | blockname    | contextlevel | reference | pagetypepattern | defaultregion |
      | online_users | Course       | DC1       | course-view-*   | side-pre      |
    And I am on the "DC1" "Course" page logged in as "student1"
    And ".drawer.drawer-right" "css_element" should not be visible
    When I click on ".drawer-right-toggle button" "css_element"
    Then I should see "Online users" in the ".drawer.drawer-right" "css_element"
    And I click on "Close block drawer" "button" in the ".drawer.drawer-right" "css_element"
    # Let the drawer finish sliding out.
    And I wait "1" seconds
    And ".drawer.drawer-right" "css_element" should not be visible

  @javascript
  Scenario: Learners reply in an episode's discussion without leaving it
    Given the following "activities" exist:
      | activity | course | section | name              | type    |
      | forum    | SC1    | 2       | Lecture questions | general |
    And the following "mod_forum > discussions" exist:
      | user     | forum             | name            | message                    |
      | student1 | Lecture questions | What is a noun? | Can someone explain nouns? |
    And I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    And I click on "Lecture questions" "link" in the ".streamdeck-related-block" "css_element"
    And I should see "Can someone explain nouns?" in the ".modal .sd-thread" "css_element"
    When I click on "Add a reply…" "button" in the ".modal .sd-thread" "css_element"
    # Let the composer load before looking inside it.
    And I wait "3" seconds
    And I switch to "sdreplyform" iframe
    Then I should not see "Can someone explain nouns?"
    And "#fitem_id_attachments" "css_element" should not be visible
    And ".tox-editor-header" "css_element" should not be visible
    And I click on "Formatting" "button"
    And ".tox-editor-header" "css_element" should be visible
    And I click on "Formatting" "button"
    And I click on "Attach files" "button"
    And "#fitem_id_attachments" "css_element" should be visible
    And I set the field "Message" to "A noun names a person, place or thing."
    And I press "Post to forum"
    And I switch to the main frame
    And I wait "2" seconds
    And I should see "A noun names a person, place or thing." in the ".modal .sd-thread" "css_element"
    And ".modal .sd-composer" "css_element" should not exist
    And I should see "1 reply" in the ".modal .sd-thread-header" "css_element"

  @javascript
  Scenario: Learners pick a discussion from an episode's forum and come back to the list
    Given the following "activities" exist:
      | activity | course | section | name              | type    |
      | forum    | SC1    | 2       | Lecture questions | general |
    And the following "mod_forum > discussions" exist:
      | user     | forum             | name            | message                    |
      | student1 | Lecture questions | What is a noun? | Can someone explain nouns? |
      | student1 | Lecture questions | What is a verb? | And verbs?                 |
    And I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    And I click on "Lecture questions" "link" in the ".streamdeck-related-block" "css_element"
    When I click on "What is a verb?" "button" in the ".modal .sd-discussion-list" "css_element"
    Then I should see "And verbs?" in the ".modal .sd-thread" "css_element"
    And I click on "All discussions" "button" in the ".modal .sd-thread" "css_element"
    And I should see "What is a noun?" in the ".modal .sd-discussion-list" "css_element"

  @javascript
  Scenario: Learners open the course's discussion forum from the hero and start a discussion in it
    Given the following "activities" exist:
      | activity | course | section | name             | type    |
      | forum    | SC1    | 0       | Course community | general |
    And the following "mod_forum > discussions" exist:
      | user     | forum            | name    | message         |
      | student1 | Course community | Welcome | Say hello here. |
    And I am on the "SC1" "Course" page logged in as "student1"
    When I click on ".streamdeck-hero-generalforum-btn" "css_element"
    Then I should see "Welcome" in the ".modal .sd-discussion-list" "css_element"
    And I click on "Start a discussion" "button" in the ".modal .sd-thread" "css_element"
    # Let the composer load before looking inside it.
    And I wait "3" seconds
    And I switch to "sdreplyform" iframe
    And I set the following fields to these values:
      | Subject | Study group        |
      | Message | Who wants to meet? |
    And I press "Post to forum"
    And I switch to the main frame
    And I wait "2" seconds
    And ".modal .sd-composer" "css_element" should not exist
    And I click on "Study group" "button" in the ".modal .sd-discussion-list" "css_element"
    And I should see "Who wants to meet?" in the ".modal .sd-thread" "css_element"
    And I click on "All discussions" "button" in the ".modal .sd-thread" "css_element"
    And I should see "Welcome" in the ".modal .sd-discussion-list" "css_element"

  @javascript
  Scenario: Learners read the course's announcements in a feed, and teachers post them from it
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | SC1    | editingteacher |
    And the following "activities" exist:
      | activity | course | section | name          | type |
      | forum    | SC1    | 0       | Announcements | news |
    And the following "mod_forum > discussions" exist:
      | user     | forum         | name    | message                |
      | teacher1 | Announcements | Welcome | Glad to have you here. |
    And I am on the "SC1" "Course" page logged in as "teacher1"
    When I click on ".streamdeck-hero-announcements-btn" "css_element"
    And I click on "Post an announcement" "button" in the ".modal .sd-news" "css_element"
    # Let the composer load before looking inside it.
    And I wait "3" seconds
    And I switch to "sdreplyform" iframe
    And I set the field "Subject" to "Exam dates"
    And I set the field "Message" to "The exam is on 12 December."
    And I press "Post to forum"
    And I switch to the main frame
    And I wait "2" seconds
    Then ".modal .sd-composer" "css_element" should not exist
    And I should see "Exam dates" in the ".modal .sd-news-item:first-child" "css_element"
    And I log out
    And I am on the "SC1" "Course" page logged in as "student1"
    And I click on ".streamdeck-hero-announcements-btn" "css_element"
    And I should see "The exam is on 12 December." in the ".modal .sd-news-item.is-expanded" "css_element"
    And I should see "New" in the ".modal .sd-news-item:first-child .sd-news-meta" "css_element"
    And "Post an announcement" "button" should not exist in the ".modal" "css_element"
    And I click on "Welcome" "button" in the ".modal .sd-news-feed" "css_element"
    And I should see "Glad to have you here." in the ".modal .sd-news-item:last-child .sd-news-body" "css_element"
