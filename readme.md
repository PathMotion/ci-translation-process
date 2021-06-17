# Tools box to manage translation process

## Import translation from PoEditor
### Description:
  Import translation files from PoEditor.com project.

### Usage:
  poeditor-import [options]

### Options:
```
  -d, --destination=DESTINATION  Destination of imported files
      --api-key=API-KEY          Name of the environment variable, that contains the PoEditor API key. [default: "PO_EDITOR_API_KEY"]
  -p, --project=PROJECT          Po editor project identifiers
  -t, --type=TYPE                Imported file type/format [default: "mo"]
  -c, --context                  Export translation contexts to separate files to avoid overlapping
  -m, --mask=MASK                Output file mask. That should allow you to override destination pattern. by default it follow linux locales organization. [default: "/%s/LC_MESSAGES/default"]
      --code=CODE                Language code format (IETF tag lang) that will be used to generate your output mask. (posix|iso_639_1) [default: "POSIX"]
  -h, --help                     Display this help message
  -q, --quiet                    Do not output any message
  -V, --version                  Display this application version
      --ansi                     Force ANSI output
      --no-ansi                  Disable ANSI output
  -n, --no-interaction           Do not ask any interactive question
  -v|vv|vvv, --verbose           Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```

## Update translation files by scanning code
### Description:
  Update PO files from source code.

### Usage:
  update-po-from-code [options]

### Options:
```
  -o, --output=OUTPUT       Directory where output po files
  -s, --source=SOURCE       Source code path directory
  -l, --language=LANGUAGE   Source code language (php, php-with-blade) (multiple values allowed)
      --remove-unused       Remove a translation if it not present in source code
      --disable-unused      Disable a translation if it not present in source code
      --add-comment-unused  Add a comment for a translation if it not present in source code
  -h, --help                Display this help message
  -q, --quiet               Do not output any message
  -V, --version             Display this application version
      --ansi                Force ANSI output
      --no-ansi             Disable ANSI output
  -n, --no-interaction      Do not ask any interactive question
  -v|vv|vvv, --verbose      Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug
```
