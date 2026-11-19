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

The developer agent follows these steps (see [Working with agents](#working-with-agents)).
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

## Working with agents

The issues are worked on by three agents, defined in `.claude/agents/`, each in its own git
worktree: a **developer**, who resolves the issue and opens its PR, a **reviewer**, who
reviews that PR, and a **tester**, who checks in the MiniPavi emulator what a Minitel shows
with it. The developer and the reviewer are strong in architecture, OOP and testing; all three
follow this file. The main session doesn't write the code: it starts the agents, carries
messages between them, and keeps the user informed. It does write the Claude files
(`CLAUDE.md`, `.claude/`) itself. The order of the issues is the pinned roadmap, #80.

1. **Start on the user's word.** When the user says to start an issue (or the next one on
   the roadmap), start a new developer: the `Agent` tool with `subagent_type: "developer"`
   and `isolation: "worktree"`, giving it the issue number and any decision the user has
   already made. One issue at a time. Say then whether the issue changes what a Minitel
   shows (the bytes written, a page's layout, the Videotex engine), and so needs the tester.
2. **Questions go to the user.** When the developer reports a question or an ambiguity, ask
   the user (with the developer's options and recommendation), and send the answer back with
   `SendMessage`, to the same developer.
3. **Review once the PR is pushed.** When the developer reports its PR, start a new reviewer
   (`subagent_type: "reviewer"`, `isolation: "worktree"`) with the PR and issue numbers. When
   the issue changes what a Minitel shows, start a new tester too (`subagent_type: "tester"`,
   `isolation: "worktree"`), with the PR and issue numbers and any case to check. Its report
   goes to the reviewer with the developer's answer, from the next round on.
4. **Relay every round.** Give the user each review's verdict and findings (numbered,
   blocking or not, and the developer's answer to each from round 2 on), and the tester's
   verdict and cases (what the emulator showed on `main` and on the branch), then carry on
   without waiting, unless the user steps in.
5. **Loop until approved.** On `CHANGES REQUESTED`, or a case the tester says doesn't show
   as expected (a blocking finding), send the findings, unedited, to the same developer
   (`SendMessage`). When it has pushed, send its answer to the same reviewer, and to the same
   tester when the screen may have changed, for the next round. Keep the agents for the whole
   issue, so they keep their context.
6. **Three rounds at most.** A round is one review (and the tester's check with it). If the
   reviewer hasn't approved, or the tester still sees a blocking case, after the third, stop
   and ask the user what to do: list each open finding with both sides' arguments, and your
   own view.
7. **Approved: hand over.** Once the reviewer approves and the tester (if any) sees what the
   issue expects, tell the user the PR is ready, with its link and a summary of the rounds.
   The user reviews and merges it; the agents never merge.

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
  (Mago's linter, analyzer and guard, `mago.toml`, failing on any issue; the guard checks
  each namespace's dependencies, so a new use of a layer or library may need its rule). Add
  or update tests for the changed behaviour. A fix starts with a test that fails without it.
  CI runs `cs`, `analyse` and `test` on PHP 8.2–8.5 (`.github/workflows/ci.yml`); the PR
  must be green.
- **Coverage and mutants**: `composer coverage` fails below its minimum share of lines (in
  `composer.json`): raise it when tests are added, never lower it. `composer mutation`
  (Infection) fails below its minimum MSI (in `infection.json5`), raised the same way, and
  lists the escaped mutants in `build/infection/infection.log`: a changed behaviour's
  mutants should not escape. CI runs both on PHP 8.4. Both need pcov or Xdebug.
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
  (`vendor/bin/mago lint --generate-baseline`, set in `mago.toml`, with
  `mago lint --fail-on-out-of-sync-baseline` back in `composer analyse` so that stale entries
  fail).
- Document classes and methods with PHPDoc (`/** ... */`, typed `@param` / `@return`).
- Errors in page XML must give the file and line (`PageXmlException`), as they do now.
