# StudyMark Moodle activity architecture

## Migration boundary

The legacy standalone application remains untouched. Its authentication, tokens, SQLite database, PHP router, internal groups and storage directory are neither shipped nor called by this plugin. Moodle provides identity, sessions, enrolments, roles, groups, groupings and file access.

## Implemented MVP

Each activity owns one File API document. PDF, EPUB, DOCX, HTML and TXT files are extracted in the browser into a normalized HTML reading view. Headings, paragraphs, lists and quotations are preserved when the source exposes them; PDF structure is heuristic.

Highlights use text offsets in that normalized view. Private, group-shared and explicit reference highlights are stored in XMLDB tables. Reference mode is available only to users with `mod/studymark:manageannotations`. Readers can see visible highlights even if they cannot create their own. The abbreviated view contains highlighted fragments and their heading context.

The analytics report calculates reading reach, unique highlighted proportion, course mean and Jaccard overlap with the explicit reference. A manager registers the normalized character count; students cannot change it. Custom completion can require reading reach and/or reference overlap.

Comments and highlight-update web services exist and enforce visibility/ownership, but this MVP currently exposes no comments or annotation-editing interface.

## Document replacement

`studymark_document.contenthash` tracks the File API content hash. Re-saving identical bytes preserves anchors. Replacing bytes, including under the same filename, transactionally removes highlights and comments and resets `textlength`; stale offsets are never silently reused.

## Known trust boundary

Extraction still runs in the browser. Only managers may register document length, and highlight offsets are shape- and range-validated, but the server does not yet possess canonical normalized text with which to prove that submitted text matches an offset. Completion analytics therefore remain unsuitable as a high-stakes assessment until deterministic server-side normalization is implemented.

## Moodle integration

- File API and `pluginfile.php` for source documents.
- XMLDB tables for documents, highlights and comments.
- AJAX external functions with context/capability checks.
- Moodle groups/groupings for shared visibility.
- Events, Privacy API, backup/restore and custom completion.

## Marketplace roadmap

Add deterministic server-side conversion and versioned block IDs, PHPUnit/Behat coverage on supported Moodle/PHP/database matrices, accessibility and mobile testing, asynchronous reference-completion recalculation, an annotation editing/discussion UI, and formal third-party library declarations.
