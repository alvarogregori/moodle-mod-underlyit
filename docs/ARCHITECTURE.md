# Underly.it Moodle activity architecture

## Migration boundary

The legacy standalone application remains untouched. Its authentication, tokens, SQLite database, PHP router, internal groups and storage directory are neither shipped nor called by this plugin. Moodle provides identity, sessions, enrolments, roles and file access.

## Implemented MVP

Each activity owns one File API document. PDF, EPUB, DOCX, HTML and TXT files are extracted in the browser into a normalized HTML reading view. Headings, paragraphs, lists and quotations are preserved when the source exposes them; PDF structure is heuristic.

Highlights use text offsets in that normalized view. Personal highlights and the tutor reference are stored in XMLDB tables. Learners see only their own highlights and, when the tutor makes it visible, the reference. Reference mode is available only to users with `mod/underlyit:manageannotations`. Readers can see visible highlights even if they cannot create their own. The abbreviated view contains highlighted fragments and their heading context.

The analytics report calculates reading reach, unique highlighted proportion, course mean and Jaccard overlap with the explicit reference. A manager registers the normalized character count; students cannot change it. Custom completion can require reading reach and/or reference overlap.

Group sharing, notes and comments are not part of this release; they will return together with their interface.

## Document replacement

`underlyit_document.contenthash` tracks the File API content hash. Re-saving identical bytes preserves anchors. Replacing bytes, including under the same filename, transactionally removes highlights and resets `textlength`; stale offsets are never silently reused.

## Known trust boundary

Extraction still runs in the browser. Only managers may register document length, and highlight offsets are shape- and range-validated, but the server does not yet possess canonical normalized text with which to prove that submitted text matches an offset. Completion analytics therefore remain unsuitable as a high-stakes assessment until deterministic server-side normalization is implemented.

## Moodle integration

- File API and `pluginfile.php` for source documents.
- XMLDB tables for documents and highlights.
- AJAX external functions with context/capability checks.
- Events, Privacy API, backup/restore and custom completion. Reference changes recalculate learners' completion in the `refresh_completion` ad hoc task.

## Marketplace roadmap

Add deterministic server-side conversion and versioned block IDs, PHPUnit/Behat coverage on supported Moodle/PHP/database matrices, accessibility and mobile testing, and group sharing, notes and comments with their interface.
