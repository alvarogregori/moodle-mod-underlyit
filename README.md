# Underly.it (`mod_studymark`)

Underly.it is a Moodle activity plugin for active and collaborative document reading.

## Install

Copy this directory to `mod/studymark`, visit **Site administration > Notifications**, then add an Underly.it activity to a course. Each activity accepts one PDF, EPUB, DOCX, HTML or TXT document.

This release is a public beta. See `docs/ARCHITECTURE.md` for implemented behaviour, limitations and the Marketplace roadmap.

## Requirements

- Moodle 4.2 or later (the plugin uses the `core_external` namespace introduced in 4.2).
- A PHP and database version supported by the selected Moodle release.

## License

GPL-3.0-or-later.

## Development build

The distributable AMD modules in `amd/build` are generated from `amd/src` with Grunt:

```bash
npm ci
npm run build
```

`node_modules` is development-only and must not be included in release packages. Moodle administrators do not need Node.js, npm or Grunt to install the plugin.

## Third-party libraries

Underly.it bundles PDF.js 3.11.174 and JSZip 3.10.1. Their licenses, exact upstream sources, reproduction steps and SHA-256 checksums are documented in each library's `readme_moodle.txt` and in `thirdpartylibs.xml`.

## Source, issues and documentation

- Source: https://github.com/alvarogregori/moodle-mod-underlyit
- Issues: https://github.com/alvarogregori/moodle-mod-underlyit/issues
- Architecture: `docs/ARCHITECTURE.md`
