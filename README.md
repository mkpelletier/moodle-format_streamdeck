# Streamdeck Course Format for Moodle

A streaming-inspired, episode-based course format that applies familiar media UI patterns to Moodle to reduce cognitive load and keep learners focused on the content.

## Features

- **Netflix-style course layout** — Sections are presented as episode cards in a visual grid, with a hero banner showing course metadata, instructor details, and overall completion progress.
- **Episode view** — Each section opens as an episode page with the primary learning activity (lesson, SCORM, H5P, LTI, video, etc.) front and centre.
- **Related resources and activities** — PDFs, documents, quizzes, assignments, and forums associated with a section appear in a sidebar alongside the episode content.
- **Forum and assignment previews** — Inline modal previews for forums (recent discussions, post counts) and assignments (due dates, submission status) without leaving the course page.
- **Continue watching** — A "continue watching" row highlights the next incomplete section so learners can pick up where they left off.
- **Completion gating** — A "Next" button appears only when all activities in the current section are complete.
- **Syllabus editor** — A built-in syllabus editor (overview, learning outcomes, assessments, materials) accessible via a "More info" modal on the course hero.
- **Course schedule integration** — Embeds course schedule data when the `mod_courseschedule` plugin is installed.
- **YouTube auto-embedding** — Detects YouTube URLs in URL activities and embeds them directly.
- **Configurable instructor roles and labels** — Site admins can choose which roles appear as instructors and customise the label (e.g., "Teacher", "Facilitator", "Instructor").

## Requirements

- Moodle 5.0+ (version 2024110400 or later)
- PHP 8.2+

## Installation

1. Download or clone this repository into `course/format/streamdeck` in your Moodle installation.
2. Visit **Site administration > Notifications** to trigger the database upgrade.
3. Set a course's format to **Streamdeck** under **Course settings > Course format**.

## Configuration

Navigate to **Site administration > Plugins > Course formats > Streamdeck** to configure:

- **Hero instructor roles** — Select which roles appear as instructors in the course hero.
- **Instructor label** — Customise the singular and plural labels (e.g., "Facilitator" / "Facilitators").
- **Hero title font** — Choose the font family for the course title in the hero area (Lobster, Montserrat, Poppins, etc.).
- **Show right drawer toggle** — Optionally display the right drawer toggle in view mode so students can access blocks.

## Running tests

### PHPUnit

```bash
cd /path/to/moodle
php admin/tool/phpunit/cli/init.php
php vendor/bin/phpunit --testsuite format_streamdeck_testsuite
```

### GitHub Actions CI

The repository includes a GitHub Actions workflow (`.github/workflows/ci.yml`) that runs on every push and pull request. It executes:

- PHP lint, PHPMD, code checker, and PHPDoc validation
- Plugin validation and savepoint checks
- Mustache lint and Grunt build
- PHPUnit tests
- Behat acceptance tests

## Inspiration

This course format was inspired by the concepts presented by **Lewis Carr** at the **Moodle iMoot** in October 2025:

> **Reimagining course design for the streaming generation: why user interface matters**
> [https://youtu.be/ilQOBxH0K9c](https://youtu.be/ilQOBxH0K9c)

Lewis's presentation made a compelling case for applying the design language of modern streaming platforms to learning management systems — reducing cognitive load, improving visual hierarchy, and meeting learners where they already are. The Streamdeck course format is a direct implementation of those ideas within Moodle.

## Licence

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/licenses/gpl-3.0.txt).

## Author

Mathieu Pelletier — [South African Theological Seminary](https://www.sats.ac.za)
