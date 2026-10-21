# Changes

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
