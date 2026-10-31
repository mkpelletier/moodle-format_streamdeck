# Changelog

## [1.0-beta12] - 2026-10-31

### Added
- Moodle App support: streaming course home, season tabs, episode cards and episode pages.
- Seasons: group episodes under named seasons, shown as tabs. The label is configurable per course.
- Episode cards show running time, "New", "Due soon" and unlock conditions.
- "About this episode" card with optional title and icon.
- TV display: large type, safe margins and remote navigation. Set from the View menu, a `?display=tv` link, or detected on TV browsers.
- Arrow-key navigation on the episode shelf.
- Rebuilt episode discussions: a threaded panel with an inline composer. Options, attachments and tags sit in a single bar of chips; the thread refreshes in place after posting.
- The course's discussion forum (hero) opens in the same panel: a list of discussions, with "Start a discussion".
- Accent colour course setting, with a colour picker. It replaces the default red everywhere in the course, including the Moodle App, and is adjusted where needed to stay legible in light and dark mode.
- Announcements open as a "What's new" feed: date tiles, "New" badges, the newest open and the rest expanding in place. Teachers post, edit and delete from the feed.

### Changed
- Focus order follows the page: episode before related items; hidden navbar no longer focusable; locked cards reachable.
- One visible focus ring for all controls.
- Colour mode menu replaced by a View menu (Appearance and Display).
- Completion read once per page from core's cache.

### Fixed
- Once announcements had been opened on a device, the badge stopped showing new ones; it now follows the forum's read tracking.
- Copyleaks plagiarism notice illegible in dark mode.
- Right drawer toggle did nothing on Moodle 5.3.
- Empty episodes showed no navigation.
- Completed activities could show as "To do".

## [1.0-beta11] - 2026-10-30

Requires Moodle 5.1 or later.

### Added
- Light, Dark and Auto colour modes per learner, with a site default. Defers to Boost's colour modes when enabled.
- Consistent styling for every activity page, including the full quiz flow.
- Activity page toolbar: back to the episode and the course.
- Assessment-only units put the quiz or assignment centre stage.
- "Featured activity" section setting.
- "Previous episode" button on every unit.
- Locked sections shown as locked cards.
- Reduced-motion support.
- Privacy provider for the colour mode preference.

### Changed
- Course index hidden from learners outside edit mode.
- JavaScript rewritten on `core/modal` and `core/str`, with accessibility fixes.
- Stylesheet reorganised around design tokens.
- Hardcoded English moved to language strings.
- CI runs on Moodle 5.1, 5.2 and main.

### Fixed
- Grades modal title.
- Unnamed sections titled "Orphaned activities".
- Announcements icon missing on a course's first view.
- Activity descriptions missing from episode pages.
- Subsections shown as episodes.
- `install.xml` schema path and a missing capability string.

## [1.0-beta10] - 2026-04-07

### Added
- Section thumbnail upload.
- Circular quiz timer.
- "Back to course" button on quiz and assignment pages.
- Dark quiz theme.
- Course settings: hero font, right drawer toggle, instructor labels.
- Auto-hiding navbar.
- Privacy provider.

### Changed
- Right drawer opens on toggle and closes on click outside.
- Forum previews restyled.

### Fixed
- N+1 queries on the course page.
- Completion badges illegible on dark backgrounds.
- Coding standards and language string order.

## [1.0-beta6] - 2026-03-21

### Added
- PHPUnit tests and GitHub Actions CI.

### Fixed
- Related activities hidden when a section had no resources.
- Duplicate admin settings page warning.

## [1.0-beta5] - 2026-02-11

### Added
- General discussion forum and participants links in the hero.

## [1.0-beta4] - 2026-02-11

### Added
- First release: episode grid with hero, episode pages with related items, forum and assignment previews, completion-gated "Next", syllabus editor, course schedule integration, YouTube embedding, configurable instructor labels.
