# Testing DexPaprika SDK

This directory contains tests for the DexPaprika SDK. The test suite includes unit tests for each API class and utility, as well as integration tests that can be run against the live API.

## Running Tests

To run the entire test suite:

```bash
composer test
```

This command runs all unit tests but skips integration tests by default.

## Unit Tests

Unit tests use mocked HTTP responses and do not make real API calls. They are designed to test the functionality of each class in isolation.

```bash
# Run all unit tests
vendor/bin/phpunit --exclude-group integration

# Run tests for a specific class
vendor/bin/phpunit tests/DexPaprika/TokensApiTest.php
```

## Integration Tests

Integration tests make real API calls to the DexPaprika API. They are skipped by default to avoid unnecessary API requests during development and CI workflows.

To run integration tests, you need to set the `DEXPAPRIKA_RUN_INTEGRATION_TESTS` environment variable:

```bash
# Run only integration tests
DEXPAPRIKA_RUN_INTEGRATION_TESTS=1 vendor/bin/phpunit --group integration

# Run all tests including integration tests
DEXPAPRIKA_RUN_INTEGRATION_TESTS=1 vendor/bin/phpunit
```

## Test Coverage

To generate a test coverage report:

```bash
vendor/bin/phpunit --coverage-html coverage
```

This will generate an HTML coverage report in the `coverage` directory.

## Test Structure

- `tests/DexPaprika/` - Unit tests for API classes
- `tests/DexPaprika/Utils/` - Unit tests for utility classes
- `tests/DexPaprika/Integration/` - Integration tests that make real API calls

## Adding Tests

When adding new functionality to the SDK, please ensure:

1. Unit tests are created for each new class and method
2. Existing tests are updated if you modify existing functionality
3. Integration tests are added for new API endpoints

## Continuous Integration

Tests are automatically run in CI pipelines to ensure code quality and prevent regressions. The CI pipeline runs all unit tests but skips integration tests. 