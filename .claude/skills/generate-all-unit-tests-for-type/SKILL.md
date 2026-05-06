---
name: generate-all-unit-tests-for-type
description: Generates PHPUnit unit tests for every PHP class of a given type (block, controller, helper, service, observer, plugin, model, viewmodel, command, validator) in the AlgoliaSearch module, then auto-runs the newly created tests.
---

Generate unit tests for all PHP classes matching the type: $ARGUMENTS

Follow these steps exactly:

## Step 1 — Map the type to source directories

Use the following mapping to resolve the type argument to one or more directories (paths are relative to the module root):

| Type argument                       | Directories to scan                                    |
|-------------------------------------|--------------------------------------------------------|
| `block`                             | `Block/`                                               |
| `controller`                        | `Controller/`                                          |
| `helper`                            | `Helper/`                                              |
| `service`                           | `Service/`                                             |
| `observer`                          | `Observer/`                                            |
| `plugin`                            | `Plugin/`                                              |
| `model`                             | `Model/Indexer/`, `Model/Backend/`, `Model/Source/`    |
| `indexer`                           | `Model/Indexer/`                                       |
| `viewmodel` or `view-model`         | `ViewModel/`                                           |
| `command` or `console`              | `Console/`                                             |
| `validator`                         | `Validator/`                                           |
| `other` or `others`                 | `Console/`, `Validator/`                               |

If the argument doesn't match any entry, report: "Unknown type '$ARGUMENTS'. Valid types are: block, controller, helper, service, observer, plugin, model, indexer, viewmodel, command, validator, other." Then stop.

## Step 2 — Discover source files

Use Bash `find` to recursively discover all `.php` files in the matched directories. Exclude:
- Any file whose path contains `Test/` (already a test)
- Interface files (`*Interface.php`)

Collect the full list of source file paths.

## Step 3 — Check for existing test files

For each source file, determine the expected test file path by mirroring the module structure under `Test/Unit/`:
- `Block/Adminhtml/Queue/Status.php` → `Test/Unit/Block/Adminhtml/Queue/StatusTest.php`
- `Plugin/StockItemObserver.php` → `Test/Unit/Plugin/StockItemObserverTest.php`

Categorise each file as:
- **New**: no test file exists yet
- **Existing**: a test file already exists (will be checked for new public methods not yet covered)

## Step 4 — Confirmation

Present the following summary to the user and **wait for confirmation before proceeding**:

```
Type:    <type>
Directories: <dirs>

Files found:      <total>
  - No test yet:  <new_count>  (test file will be created)
  - Has test:     <existing_count>  (will be checked for missing coverage)

Files to process:
  <list of source paths, one per line>

Proceed? (yes / no)
```

Do not generate any test code until the user confirms.

## Step 5 — Load the foundation rules and type guide

Read `.claude/testing-core.md`.

Then read the type-specific guide:

| Type                                  | Guide                                         |
|---------------------------------------|-----------------------------------------------|
| `block`                               | `.claude/types/testing-blocks.md`             |
| `controller`                          | `.claude/types/testing-controllers.md`        |
| `helper`                              | `.claude/types/testing-helpers.md`            |
| `service`                             | `.claude/types/testing-services.md`           |
| `observer`                            | `.claude/types/testing-observers.md`          |
| `plugin`                              | `.claude/types/testing-plugins.md`            |
| `model` / `indexer`                   | `.claude/types/testing-models.md`             |
| `viewmodel` / `view-model`            | `.claude/types/testing-view-models.md`        |
| `command` / `console` / `validator` / `other` | `.claude/types/testing-others.md`   |

Read both files **once** before processing any individual file. These rules apply to every file in the batch.

## Step 6 — Generate tests for each file

Process files one at a time, in order. For each file:

1. **Check for class-specific rules**: extract the class name (e.g. `Configuration` from `Block/Configuration.php`) and check whether `.claude/specifics/testing-<ClassName>.md` exists. If it does, read it — its rules override the type guide for this file only.
2. **Read the source file**: understand constructor dependencies, public methods, guard clauses, and any use of `addCommitCallback` or external service calls.
3. **Check for an existing test file**: if one exists, read it first to avoid duplicating tests that are already written.
4. **Apply the generation rules** from Step 5 (core + type guide + any class-specific guide):
   - Generate at least one test per public method, except `@deprecated` methods and trivial delegating methods with no transformation, guard clause, or state change.
   - Additional tests per method are driven by cyclomatic complexity.
   - Focus on user-observable behavior: return values, thrown exceptions, which collaborator is called with which arguments.
   - Do not test implementation details.
   - Use `@dataProvider` (or `#[DataProvider]`) for multiple input variants of the same behavior.
   - Never write a test that just returns what is mocked.
   - Test names read as plain-English sentences: `testReturnsEmptyArrayWhenProductIsDisabled`.
   - Use the nullable property pattern: `protected null|(Foo&MockObject) $foo = null;`
   - For `addCommitCallback` patterns, capture and invoke the closure inline.
5. **Write the test file** to the output path. It must:
   - Extend `\Algolia\AlgoliaSearch\Test\TestCase`
   - Declare `strict_types=1`
   - Use the correct namespace (e.g. `Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Queue`)
   - Import all used classes at the top
6. **Report progress**: after each file, output one line — `✓ <TestFilePath> (<N> tests)`.

If a source file should be skipped entirely per the type guide (e.g. button blocks, source models, route-only controllers), output: `— Skipped <SourceFilePath> (<reason>)` and move on.

## Step 7 — Run the generated tests

After all files have been written, run phpunit on the test directory for this type. Determine the Magento root by navigating up from the module root (the module lives at `app/code/Algolia/AlgoliaSearch` inside the Magento root).

```bash
cd <magento_root> && vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  app/code/Algolia/AlgoliaSearch/Test/Unit/<TypeTestDir>/
```

Where `<TypeTestDir>` mirrors the source directory (e.g. `Block/` for blocks, `Plugin/` for plugins).

For types that span multiple directories (e.g. `model` covers `Model/Indexer/`, `Model/Backend/`, `Model/Source/`), run a single phpunit invocation passing all test directories.

Report the full test output. If tests fail, list each failing test with its error message. Do **not** automatically fix failing tests — report them to the user and stop.
