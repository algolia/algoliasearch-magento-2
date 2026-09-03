#Core Testing Guidelines

## Information
- The files we are testing are located in a Magento2 module
- The test files are located in the Test/Unit directory, create all the tests in this parent's directory
- Follow the same structure as in the module (ex: tests for the Service/Product/RecordBuilder.php should be located in the Test/Unit/Service/Product directory)

## Tech Stack
- Use PHPUnit 12
- PHP 8.5
- Magento 2.4.9

### Test Strategy
- Don't test Event Manager's dispatch methods
- Use @dataProvider annotations when it's needed
  - use #[DataProvider('myProvider')]
  - not /**
        * @dataProvider myProvider
        */
- Do not test methods coming from the parent classes (Magento core)
- Do not use assertObjectHasProperty assertions
- We use PHPUnit 12, make sure there is nothing outdated from earlier versions like `addMethods` or `setMethods`.
- We still want to be backward compatible with PHPUnit 10 and PHPUnit 11. Do not use any classes that are not available for these versions.
- Extend `\Algolia\AlgoliaSearch\Test\TestCase` (not PHPUnit's TestCase directly) so inherited helpers like `invokeMethod()`, `setPrivateProperty()`, and `getPrivateProperty()` are available
- Some parts of the codebase are very old and cannot be blindly considered as valid. When behavior looks unclear or possibly unintended, flag it both in the test (as a comment) and in the final summary.
- Cover every reachable path that produces observably different behavior, but when a path's intended behavior is unclear or looks unintended, the agent must NOT silently assert it as correct. Instead comment with
  // CHARACTERIZATION: encodes current behavior, needs confirmation and surface it in the run summary for human confirmation.

### Mocking Strategy
- DO NOT instantiate the tested class in the `setUp()` method by mocking every object that needs to be passed in its constructor, do it in a method called `createObjectToTest()` instead.
- Only put in `setUp()` the following:
    - Plain value fixtures and constants used across tests.
    - A real (non mocked) helper the tested object needs, such as an in memory implementation or a serializer.
    - Reset of static or global state.
- Use nullable types for the testing class properties. ex:
    - protected ?ConfigHelper $configHelper;
      or
    - protected null|(CategoryResourceModel&MockObject) $categoryResource = null;
- Choose the mocking method by the role the dependency object plays in the test:
    - Use createStub() when the dependency only feeds canned data into the class under test (indirect input). Assert on what the class returns or does, never on the stub.
    - Use createMock() with expects() only when the call itself is the behavior under test (indirect output: dispatch, persist, log, an outbound API call). The interaction is the contract you are proving.
    - Default a dependency to a stub. Promote it to a mock only when you are asserting the interaction.
- Prefer usage of `$this->callback('is_callable')` over `$this->callable()`
- `expects` must be derived from the specification for the test, never from the test's own wiring.

**DO Mock:**
- Every object that needs to be passed in the constructor of the tested class

**DON'T Mock:**
- The tested class itself

### Example

```php
class InstantSearchHelperTest extends TestCase
{
    protected ?InstantSearchHelper $instantSearchHelper;
    
    protected ?Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createStub(Serializer::class);
    }
    
    protected function createObjectToTest(
        ?ScopeConfigInterface $configInterface = null,
        ?WriterInterface $configWriter = null,
        ?Serializer $serializer = null
    ): InstantSearchHelper {
        return new InstantSearchHelper(
            $configInterface ?? $this->createStub(ScopeConfigInterface::class),
            $configWriter ?? $this->createStub(WriterInterface::class),
            $serializer ?? $this->serializer,
        );
    }
    
    public function testExample(): void
    {
        $configInterface = $this->createStub(ScopeConfigInterface::class);
        $configInterface->method('getValue')->willReturn('foo');
    
        $instantSearchHelper = $this->createObjectToTest(configInterface: $configInterface);
        
        //...
    }
//...
}
```
