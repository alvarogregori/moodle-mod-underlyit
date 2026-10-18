# StudyMark Moodle activity architecture

## Migration boundary

The legacy standalone application is treated as read-only source material. Its users, bearer tokens, SQLite database, PHP router, internal groups and `storage/` directory are not shipped and are not called by the Moodle plugin.

| Legacy concept | Moodle implementation |
|---|---|
| `users`, login and `auth_tokens` | Moodle users, sessions, enrolments, roles and `require_login()` |
| `study_groups` and membership tables | Course groups, groupings and activity group mode |
| SQLite `documents` plus upload directory | `studymark_document` metadata plus Moodle File API |
| Router/controllers | Moodle external functions exposed to `core/ajax` |
| `highlights` | `studymark_highlight`, scoped by document/user/native group |
| Notes and discussion | Highlight note plus `studymark_comment` |
| Direct file endpoint with token | `pluginfile.php` with module context and capability checks |

## MVP request flow

1. `view.php` requires course login and `mod/studymark:view`.
2. The AMD client calls registered AJAX external functions. Moodle validates the session and `sesskey` for writes.
3. Every external function resolves the course module context and checks capabilities.
4. Shared rows carry a Moodle `groupid`; visibility follows the activity's group mode and grouping.
5. Document bytes stay in Moodle's File API. Plugin tables store only searchable metadata and annotations.

## Data model

- `studymark`: activity instance.
- `studymark_document`: one row per File API document.
- `studymark_highlight`: user-owned text/page anchors, note, colour and sharing scope.
- `studymark_comment`: document/highlight discussion in the active Moodle group.

## MVP scope and next quality milestone

Each activity owns exactly one required document. The MVP supports managed PDF display, text/HTML reading, persisted text-offset highlights, comments, native permissions/groups, privacy export/deletion, events and backup/restore. Before a Marketplace release, add an EPUB renderer bundled under an approved third-party library declaration, full Behat/PHPUnit coverage, accessibility testing, antivirus/authorised upload AJAX flow, annotation editing UI, migrations from the standalone data model, and CI against supported Moodle/PHP/database matrices.
