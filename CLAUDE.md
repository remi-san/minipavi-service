# CLAUDE.md

## What this project is

**`remi-san/minipavi-service`**: a PHP 8.2 library (namespace `MiniPaviService`) for writing
Minitel services behind a MiniPavi gateway. It provides XML pages and templates, controllers,
routing, the user context, and Videotex (`.vdt`) handling. Its README (`README.md`) documents
the API and the page XML format. Keep the README up to date when behaviour or the XML format
changes, and the XML Schemas (`schema/page.xsd`, `schema/messages.xsd`) when the XML formats
change. `bin/minipavi` is its command line (`lint`, `preview`, `vdt-offset`), `vendor/bin/minipavi`
in a service.

It is on [Packagist](https://packagist.org/packages/remi-san/minipavi-service), and versioned
(tags such as `v0.1.0`).

## Its users

Services hold a fixed version of the framework, and move to a new one when they choose to.
Changing the framework doesn't need checking or updating them: change its API freely, without
deprecation shims. Do mark each breaking change (`BREAKING CHANGE:` in the commit footer, and
in the PR), so that it shows when a service updates.

## Workflow for fixing an issue

Work is tracked in GitHub issues on this repository. The framework's open issues were moved
here from remi-san/minipavi-stack; its closed ones (prefixed `framework:`) stay there, and the
moved ones link to them as `remi-san/minipavi-stack#<n>`. For each issue:

1. **Read the issue** (`gh issue view <n>`) and the code it points to. If the proposal no longer matches the code, or the issue is ambiguous, say so
   before starting.
2. **Create a branch from an up-to-date `main`**. Never commit to `main` directly.
   ```sh
   git switch main && git pull --ff-only
   git switch -c <type>/<issue-number>-<short-slug>   # e.g. fix/1-change-page-state
   ```
   Fix one issue per branch, and open its PR against `main`: don't stack a PR on another
   PR's branch (branches aren't deleted on merge, so GitHub doesn't retarget it, and it would
   be merged into a dead branch).
3. **Make small commits** that follow the Karma convention (below). Each commit does one
   thing and leaves the code working. A refactoring, a behaviour change, and the docs update are
   separate commits.
4. **Verify** before pushing (see [Checking a change](#checking-a-change)).
5. **Push and open a PR** against `main`:
   ```sh
   git push -u origin HEAD
   gh pr create --base main --title "<karma-style title>" --body "..."
   ```
   The PR body states what changed and why, how it was checked, and any follow-up. It ends with
   `Closes #<n>`. Don't merge the PR:
   the user reviews and merges it.

## Commit messages: Karma convention

```
<type>(<scope>): <subject>

<body>

<footer>
```

- **type**:
  - `feat`: new feature
  - `fix`: bug fix
  - `docs`: documentation only
  - `style`: formatting, no code change
  - `refactor`: neither a fix nor a feature
  - `perf`: performance
  - `test`: tests
  - `chore`: build, tooling, dependencies
- **scope**: the part touched, e.g. `vdt`, `page`, `router`, `context`, `controller`, `client`,
  `http`, `cli`.
- **subject**: imperative, present tense, lowercase, no final period, about 70 characters at
  most. For example, `fix(context): fall back to the default route when it is missing`.
- **body** (optional): why the change was made and how it differs from before, wrapped at 72
  columns.
- **footer**: `Refs #<n>` on intermediate commits, and `Closes #<n>` on the commit that
  resolves the issue (if the PR body doesn't already say it). Put `BREAKING CHANGE: ...` there
  when a public API changes.

## Checking a change

- **Syntax**: `php -l` on every changed file
  (`git diff --name-only main -- '*.php' | xargs -n1 php -l`).
- **Formatting**: `composer cs` (Mago's formatter).
- **Tests and static analysis**: `composer test` (PHPUnit, `tests/`) and `composer analyse`
  (Mago's linter and analyzer, `mago.toml`, failing on any issue). Add or update tests for
  the changed behaviour. A fix starts with a test that fails without it. CI runs `cs`,
  `analyse` and `test` on PHP 8.2–8.5 (`.github/workflows/ci.yml`); the PR must be green.
- **Videotex output**: `tests/Vdt` compares `offset()` with golden files. After an intended
  change to the bytes written, regenerate them with `UPDATE_GOLDEN=1 composer test`, and
  explain the change in the commit.
- **What a user sees**: for a change to what is shown on a Minitel, try it in a service on a
  local MiniPavi stack (the skeleton's README says how), pointing the service at a local clone
  with a `path` repository (see "Installing" in the README), without committing that.

Say in the PR which of these checks were run, and which weren't.

## Code conventions

- PHP 8.2+, PSR-4 under `src/`. Prefer `final` and `readonly` classes, enums over string
  constants, and typed parameters and returns.
- The style is Mago's formatter (PER Coding Style), with `declare(strict_types=1)`. Run
  `composer install` once, then `composer cs` before committing (`composer cs-fix` formats).
  Don't mix reformatting into a fix.
- Don't turn off or ignore a Mago rule to make an issue go away: fix the code. When a rule
  conflicts with a convention, or Mago's analyzer can't follow correct code, say why in
  `mago.toml`, ignoring it only in the files concerned. There is no lint baseline: turning a
  rule on means fixing its issues, unless the user asks for a baseline
  (`vendor/bin/mago lint --generate-baseline`, set in `mago.toml`).
- Document classes and methods with PHPDoc (`/** ... */`, typed `@param` / `@return`).
- Errors in page XML must give the file and line (`PageXmlException`), as they do now.
