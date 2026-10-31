# Streamdeck Course Format for Moodle

A streaming-inspired, episode-based course format that applies familiar media UI patterns to Moodle to reduce cognitive load and keep learners focused on the content.

## Features

- **Netflix-style course layout** — Sections are presented as episode cards in a visual grid, with a hero banner showing course metadata, instructor details, and overall completion progress.
- **Episode view** — Each section opens as an episode page with the primary learning activity (lesson, SCORM, H5P, LTI, video, etc.) front and centre. A unit with no learning content, such as a summative quiz or assignment, puts the assessment centre stage with its key facts (dates, time limit, attempts) and a Start/Continue/Review button.
- **Seasons** — Group episodes into seasons (or "Parts", "Modules": the label is set per course) by naming the first episode of each group. The course page shows one tab per season.
- **Episode metadata** — Cards show the running time, a "New" badge, upcoming deadlines and how locked episodes unlock.
- **About this episode** — An optional card of learning objectives on each episode page, set in the unit settings.
- **TV display and remote navigation** — A large-screen display for TV browsers, picked from the View menu, by a `?display=tv` link, or automatically on smart TVs, with arrow-key navigation and full-screen playback. (Apple TV has no browser and is not covered.)
- **Moodle App** — The course opens in the Moodle App as a streaming home with season tabs, episode cards and episode pages.
- **Featured activity** — Teachers can pin any activity to the stage in the section settings; otherwise it is chosen automatically.
- **Related resources and activities** — PDFs, documents, quizzes, assignments, and forums associated with a section appear in a sidebar alongside the episode content.
- **Light and dark modes** — Learners choose Light, Dark or Auto (follows the device) from the hero, the episode page or any activity page; the choice is saved per user. Every activity page in the course shares one look, built from a small set of design tokens. When a Moodle 5.3+ site turns on Boost's own colour modes, Streamdeck follows them instead.
- **Distraction-free navigation** — The course index is not shown to learners (editors keep it in edit mode). Activity pages get a top bar back to the episode and the course.
- **Forum and assignment previews** — Inline modal previews for forums (recent discussions, post counts) and assignments (due dates, submission status) without leaving the course page.
- **Continue watching** — A "continue watching" row highlights the next incomplete section so learners can pick up where they left off.
- **Completion gating** — The "Next episode" button appears only when all activities in the current section are complete; "Previous episode" is always available.
- **Syllabus editor** — A built-in syllabus editor (overview, learning outcomes, assessments, materials) accessible via a "More info" modal on the course hero.
- **Course schedule integration** — Embeds course schedule data when the `mod_courseschedule` plugin is installed.
- **YouTube auto-embedding** — Detects YouTube URLs in URL activities and embeds them directly.
- **Configurable instructor roles and labels** — Site admins can choose which roles appear as instructors and customise the label (e.g., "Teacher", "Facilitator", "Instructor").

## Requirements

- Moodle 5.1+ (version 2025100600 or later); tested on 5.3
- PHP 8.2+ (8.3+ on Moodle 5.2 and later)

## Installation

1. Download or clone this repository into `course/format/streamdeck` in your Moodle installation.
2. Visit **Site administration > Notifications** to trigger the database upgrade.
3. Set a course's format to **Streamdeck** under **Course settings > Course format**.

## Configuration

Navigate to **Site administration > Plugins > Course formats > Streamdeck** to configure:

- **Hero instructor roles** — Select which roles appear as instructors in the course hero.
- **Instructor label** — Customise the singular and plural labels (e.g., "Facilitator" / "Facilitators").
- **Default colour mode** — The mode learners see until they pick their own (Dark by default).
Per course (**Course settings > Course format**):

- **Hero title font** — Choose the font family for the course title in the hero area (Lobster, Montserrat, Poppins, etc.).
- **Show right drawer toggle** — Optionally display the right drawer toggle in view mode so students can access blocks.
- **Accent colour** — The colour of buttons, progress, badges and highlights (cherry red by default). It is adjusted where needed to stay legible in light and dark mode.

## Running tests

### PHPUnit

```bash
cd /path/to/moodle
php admin/tool/phpunit/cli/init.php
php vendor/bin/phpunit --testsuite format_streamdeck_testsuite
```

### Behat

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config /path/to/behatdata/behatrun/behat/behat.yml --tags=@format_streamdeck
```

### GitHub Actions CI

The repository includes a GitHub Actions workflow (`.github/workflows/ci.yml`) that runs on every push and pull request, against Moodle 5.1, 5.2 and main. It executes:

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

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Author

Mathieu Pelletier — [South African Theological Seminary](https://www.sats.ac.za)
