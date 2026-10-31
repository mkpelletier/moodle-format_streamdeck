@format @format_streamdeck @javascript @accessibility
Feature: Keyboard and remote navigation in Streamdeck courses
  In order to take a course with a keyboard or a TV remote
  As a student
  I need a sensible focus order, arrow keys on the episode shelf, and a TV display

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
      | page     | SC1    | 1       | Reading notes |                                             |
      | url      | SC1    | 2       | Lecture video | https://www.youtube.com/watch?v=dQw4w9WgXcQ |
      | page     | SC1    | 2       | Extra reading |                                             |
      | page     | SC1    | 3       | Summary notes |                                             |

  Scenario: The episode comes before its related items in the focus order
    Given I am on the "SC1" "Course" page logged in as "student1"
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    # The related column fades in, and cannot take focus until it is visible.
    And I wait "2" seconds
    # Clicking the empty middle of the episode navigation sets where Tab continues from.
    When I click on ".streamdeck-episode-nav" "css_element"
    And I press tab
    Then the focused element is "Previous episode" "link"
    And I press tab
    And the focused element is "Next episode" "link"
    And I press tab
    And the focused element is "Extra reading" "link"
    # The accessibility checker cannot run across the embedded player, so check the episode page's own parts.
    And the ".streamdeck-episode-header-wrapper" "css_element" should meet accessibility standards
    And the ".streamdeck-episode-nav" "css_element" should meet accessibility standards
    And the ".streamdeck-related-block" "css_element" should meet accessibility standards

  Scenario: Arrow keys move along the episode shelf
    Given I am on the "SC1" "Course" page logged in as "student1"
    # Clicking the shelf heading sets where Tab continues from.
    When I click on ".streamdeck-strip-title" "css_element"
    And I press tab
    Then the focused element is "Section 1" "link"
    And I press the right key
    And the focused element is "Section 2" "link"
    And I press the right key
    And the focused element is "Section 3" "link"
    And I press the left key
    And the focused element is "Section 2" "link"
    And the page should meet accessibility standards

  Scenario: The TV display is chosen from the view menu, moves focus with arrows and leaves episodes with Back
    Given I am on the "SC1" "Course" page logged in as "student1"
    When I click on "View options" "button" in the ".hero" "css_element"
    And I click on "TV" "button" in the ".hero .dropdown-menu" "css_element"
    Then "html[data-streamdeck-display='tv']" "css_element" should exist
    And I click on ".streamdeck-strip-title" "css_element"
    And I press tab
    And the focused element is "Section 1" "link"
    And I press the right key
    And the focused element is "Section 2" "link"
    And the page should meet accessibility standards
    And I reload the page
    And "html[data-streamdeck-display='tv']" "css_element" should exist
    And I click on "Section 2" "link" in the ".streamdeck-main-strip" "css_element"
    And "Play full screen" "button" should exist
    And I press the escape key
    And I should see "Section 1" in the ".streamdeck-main-strip" "css_element"
