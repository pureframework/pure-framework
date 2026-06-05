# Pure Framework

[![Tests](https://github.com/pureframework/pure-framework/actions/workflows/tests.yml/badge.svg)](https://github.com/pureframework/pure-framework/actions/workflows/tests.yml)

Thin PHP library: file-based routing, PDO database helpers, forms with constraints, templates, and `html()` escaping.

**License:** [MIT](LICENSE)

## Install

```bash
composer require pureframework/pure-framework:^1.3
```

Packagist: [pureframework/pure-framework](https://packagist.org/packages/pureframework/pure-framework) (synced from GitHub automatically).

```php
require __DIR__ . '/vendor/autoload.php';

use PureFramework\Router;
use PureFramework\DB;
```

## Documentation

Full guides are in **[docs/](docs/README.md)**:

- [Getting started](docs/getting-started.md)
- [Example site layout](docs/example-site.md)
- [Installation](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Architecture](docs/architecture.md)
- [Router](docs/router.md) · [Database](docs/database.md) · [Forms](docs/forms-and-validation.md) · [CSRF](docs/csrf.md) · [Session](docs/session.md) · [Phrase](docs/phrase.md) · [Security](docs/security.md) · [Templates & Display](docs/templates-and-layout.md)
- [License](docs/license.md)

## Development

```bash
composer install
composer test          # PHPUnit (tests/Unit/)
composer test:smoke    # alias to phpunit via tests/smoke-test.php
```

Scaffold a new application:

```bash
vendor/bin/pure-new-site /path/to/my-app --name="My App"
```

Generate row classes from SQL:

```bash
vendor/bin/pure-generate-classes /path/to/sql /path/to/dbGeneratedClasses.php
```

## Philosophy

Routes map to handler PHP files. Handlers call entity functions and render co-located templates. No container, no ORM.
