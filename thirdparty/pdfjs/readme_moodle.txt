# PDF.js 3.11.174

## Upstream

- Project: Mozilla PDF.js
- Version: 3.11.174
- Repository: https://github.com/mozilla/pdf.js
- Release: https://github.com/mozilla/pdf.js/releases/tag/v3.11.174
- Distribution archive: https://github.com/mozilla/pdf.js/releases/download/v3.11.174/pdfjs-3.11.174-dist.zip
- License: Apache License 2.0 (`LICENSE` in this directory)

## Files included

- `pdf.min.js` from `build/pdf.min.js` in the official distribution archive.
- `pdf.worker.min.js` from `build/pdf.worker.min.js` in the official distribution archive.

## Reproduction

1. Download `pdfjs-3.11.174-dist.zip` from the release URL above.
2. Verify that the archive is published by the Mozilla PDF.js project.
3. Extract the archive without modifying its contents.
4. Copy `build/pdf.min.js` and `build/pdf.worker.min.js` into this directory.
5. Retain the upstream Apache-2.0 license file.
6. Verify the bundled files with SHA-256:

```
5B5799E6F8C680663207AC5B42EE14EED2A406FA7AF48F50C154F0C0B1566946  pdf.min.js
FEABDF309770ED24BBA31A5467836CDC8CF639C705AF27D52B585B041BB8527B  pdf.worker.min.js
```

No source modifications are applied by Underly.it.