# Changelog

All notable changes to the Streamdeck course format plugin are documented in this file.

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
