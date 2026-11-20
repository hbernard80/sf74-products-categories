# Git hooks

Activate the versioned hooks for each clone:

```sh
git config --local core.hooksPath .githooks
```

The pre-commit hook runs the YAML and Twig linters, PHPStan, and PHPUnit,
matching the checks in `.github/workflows/ci.yml`. It checks the current
working tree, including changes that have not been staged.

Install dependencies with `composer install` first. PHP must be available
in PATH, with the extensions required by the CI workflow, including pdo_sqlite.
Git for Windows supplies the shell used to execute the hook.

Each run creates and removes a temporary SQLite database in `var/`.
The development database and `var/test.db` are not used by the tests in the hook.
A failed check blocks the commit; fix the error and retry the commit.

To run the checks manually without making a commit:

```sh
git hook run pre-commit
```
