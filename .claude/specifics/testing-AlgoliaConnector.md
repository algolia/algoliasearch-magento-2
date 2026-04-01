#Testing Guidelines specific to the AlgoliaConnector Service

## Component Types & Testing Approaches
- AlgoliaConnector is a service located at Service/AlgoliaConnector.php 

## Specifics
- As mentioned in the doc/ARCHITECTURE.md file : `AlgoliaConnector` is the single gateway to the Algolia PHP API client. All API calls
  flow through it.
- As such, we can expect more than the expected limit of 10 tests.
- Focus on the methods calling the client rather than utility methods

## Mistakes to avoid
- Make sure to test how we leverage the Algolia PHP Client, not the Client itself.
- `SearchClient::generateSecuredApiKey` is a **static method** in the Algolia PHP client v4 — PHPUnit cannot mock it. Do not write tests that try to configure it on a mock object; they will throw `BadMethodCallException`.
