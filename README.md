# lexbor-php

Zero-runtime-dependency userland PHP port of Lexbor.

The upstream C project is kept locally at `upstream/lexbor` as reference source
and test material. That checkout is ignored by Git so it can be refreshed
without mixing vendored C sources into this PHP package.

## Status

The first implemented slice is `Lexbor\Punycode\Punycode`, ported from
`source/lexbor/punycode/punycode.c` with PHPUnit coverage based on
`test/lexbor/punycode/base.c`.

Track component progress in [docs/dashboard.html](docs/dashboard.html).

## Development

Install dev tools:

```sh
composer install
```

Run tests:

```sh
composer test
```

