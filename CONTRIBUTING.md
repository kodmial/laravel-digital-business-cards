# Contributing

Thank you for considering a contribution.

## Development setup

Fork and clone the repository, then install the PHP dependencies:

```bash
composer install
```

Run the package checks before opening a pull request:

```bash
composer validate --strict --no-check-publish
composer test
npm ci
npm run test:coverage
```

The PHP tests use Orchestra Testbench and an in-memory SQLite database.
The JavaScript tests use the package-local Vitest configuration and jsdom.

## Pull requests

- Keep changes focused and include tests for behavior changes.
- Preserve backward compatibility unless the change is clearly documented.
- Update `README.md` and `CHANGELOG.md` when behavior or public APIs change.
- Do not commit credentials, production data, generated dependencies, or local
  environment files.

By contributing, you agree that your contribution is licensed under the MIT
License included with the project.
