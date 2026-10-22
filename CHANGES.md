## 0.15.0

- Changed the declared maturity from alpha to beta.
- Added Moodle GPL, package, copyright and license headers to PHP and AMD source files.
- Added reproducible provenance documentation and checksums for PDF.js and JSZip.
- Added a Grunt build workflow and regenerated genuinely minified AMD distribution files.
- Adopted the conventional `moodle-mod_studymark` repository name.
## 0.14.2

- Added a learner control to show or hide the tutor reference when the tutor makes it available.
- Rendered tutor reference highlighting as a translucent underline layer so personal highlighting remains visually dominant.

## 0.14.1

- Renamed tutor report metrics using plain educational language.
- Added report guidance, per-student interpretation and an overall status badge.
- Reports now sort learners by Balance (F1) by default.
- Added a simple view that hides technical columns, with accessible explanations on metric headings.

# Changes

## 0.14.0

- Made tutor reference highlights private to managers by default.
- Added a persistent manager control to show or hide the reference for learners.
- Prevented hidden reference intervals from being sent to learner browsers.

## 0.13.1

- Shortened the course-average comparison label to keep report cells compact.

## 0.13.0

- Added traffic-light status badges and position markers to tutor-report metrics.
- Added course-average comparisons for progress, detail, Precision, Recall, F1 and Jaccard.
- Added a Jaccard breakdown showing matched, extra and missing highlighted characters.

## 0.12.1

- Enabled explicit reference highlighting mode by default for managers.
- Clarified that the reference-mode control must be active when creating reference highlights.

## 0.12.0

- Added Precision, Recall and F1-score to the tutor analytics report.
- Centralised comparison formulas and added deterministic test coverage.
- Kept the existing Jaccard reference match as a separate metric and completion criterion.

## 0.11.1

- Existing editable highlights can now be selected and recoloured from the palette.
- Replaced click-to-delete with an explicit delete button for the selected highlight.

## 0.11.0

- Added an accessible five-colour highlight palette for new annotations.
- Refined the reader toolbar, outline and typography using Moodle/Bootstrap theme variables.
- Improved responsive toolbar behaviour on tablets and phones.

## 0.10.0

- Fixed Moodle custom completion rule discovery and descriptions.
- Made document replacement content-hash aware and destructive for stale annotations.
- Removed student control over normalized document length and validated selectors/colours.
- Added explicit reference-highlighting mode and visible reference highlights.
- Added structured HTML/TXT extraction and malformed EPUB handling.
- Fixed selection/delete interaction and read-only highlight rendering.
- Raised the minimum supported Moodle version to 4.2.
- Added normalization fixtures and corrected architecture documentation.

# Change log

## 0.9.2 — 2026-10-19

- Mark document outline sections that already contain highlights.
- Add an accessible toolbar control to collapse and reopen the document outline.
- Update outline progress immediately after highlights are created or deleted.
## 0.9.1 — 2026-10-19

- Moved all reader, document extraction and analytics UI text to Moodle language strings.
- Added complete English and Spanish string sets with matching keys.
- Localised the legacy PDF viewer controls and accessibility labels.
## 0.1.0 — 2026-10-18

- Initial installable MVP.
- Native Moodle identity, contexts, roles, groups and groupings.
- File API documents and XMLDB annotation tables.
- AJAX document, highlight and comment services.
- Events, privacy provider, backup and restore.
- Reused text-offset highlighting approach from the standalone StudyMark frontend.
