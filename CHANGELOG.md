# Changelog

All notable changes to the Streamdeck course format plugin are documented in this file.

## [1.0-beta10] - 2026-04-07

### Added
- Privacy API implementation (`classes/privacy/provider.php`) — null provider declaration.
- Section thumbnail upload field in section settings (replaces requiring images in section descriptions).
- Circular SVG countdown timer on quiz attempt pages with colour transitions (green/yellow/red) based on remaining time percentage.
- "Back to course" button on quiz overview, summary, review, and assignment pages (suppressed inside iframes).
- Quiz page dark theme: overrides for formulation, info, tables, badges, navigation, and timer elements.
- Per-course settings: hero font selector, right drawer toggle, instructor labels.
- Navbar auto-hide with toggle pill on course and activity pages.
- Forum and announcements modal restyle with cinematic streaming aesthetic.
- Unified grader pages reset to white background for readability.
- Left drawer space reclamation on quiz, forum, and assignment pages.
- Missing `nextepisode` language string definition.

### Fixed
- N+1 database query performance: batch-prefetch URL, advurl, H5P, forum counts, roles, and user records before loops.
- Activity icon fuchsia SVG filter no longer overridden by plugin styles.
- Completion condition badges (`mix-blend-mode: multiply`) now legible on dark backgrounds.
- Right drawer toggle correctly scoped; auto-hides by default using custom `.streamdeck-drawer-open` class.
- Quiz "Next page" button changed from bright red to ghost style matching "Previous".
- Forum grade button opens in new tab inside assignment iframes.
- Syllabus modal chrome stripped (transparent background, floating close button).
- PHPCS, stylelint, and mustache lint compliance.
- Language string ordering (alphabetical).

### Changed
- Right drawer opens only on toggle click, closes on click-outside (bypasses Boost's `.show` persistence).
- Quiz timer reads total duration from quiz settings for accurate ring progress.
- Forum reply button and multi-discussion list restyled for streaming aesthetic.

## [1.0-beta6] - 2026-03-21

### Fixed
- Related activities sidebar now displays independently of related resources. Previously, activities (quizzes, assignments, forums) were hidden when no resources (PDFs, documents) were present in a section.

### Added
- PHPUnit test suite with 22 tests covering the format class, syllabus CRUD, content output, and YouTube URL extraction.
- GitHub Actions CI workflow (`ci.yml`) running lint, code checks, PHPUnit, and Behat against Moodle 5.0 with PHP 8.2/8.3 and PostgreSQL/MariaDB.
- `.gitignore` for development artefacts.

### Changed
- Fixed duplicate admin settings page registration that produced "Duplicate admin page name" warnings during install and PHPUnit init.

## [1.0-beta5] - 2026-02-11

### Added
- General discussions forum and participants links in the course hero section.

## [1.0-beta4] - 2026-02-11

### Added
- Initial commit of the Streamdeck course format plugin.
- Netflix-style episode grid with hero banner, section cards, and continue-watching row.
- Episode view with related resources and related activities sidebar.
- Inline forum and assignment preview modals.
- Completion gating with "Next" button.
- Syllabus editor (overview, outcomes, assessments, materials).
- Course schedule integration via `mod_courseschedule`.
- YouTube auto-embedding for URL activities.
- Configurable instructor roles and labels in admin settings.
