# JSZip 3.10.1

## Upstream

- Project: JSZip
- Version: 3.10.1
- Repository: https://github.com/Stuk/jszip
- Release tag: https://github.com/Stuk/jszip/releases/tag/v3.10.1
- npm package: https://www.npmjs.com/package/jszip/v/3.10.1
- License: MIT (`LICENSE.markdown` in this directory)

## File included

- `jszip.min.js` from `dist/jszip.min.js` in the official npm package.

## Reproduction

1. In an empty temporary directory, run `npm pack jszip@3.10.1`.
2. Extract `jszip-3.10.1.tgz`.
3. Copy `package/dist/jszip.min.js` into this directory.
4. Copy `package/LICENSE.markdown` and retain it unchanged.
5. Verify the bundled file with SHA-256:

```
ACC7E41455A80765B5FD9C7EE1B8078A6D160BBBCA455AEAE854DE65C947D59E  jszip.min.js
```

No source modifications are applied by Underly.it.