#Core Testing Guidelines

## Informations
- The files we are testing are located in a Magento2 module
- The test files are located in the Test/Unit directory, create all the tests in this parent's directory
- Follow the same structure as in the module (ex: tests for the Service/Product/RecordBuilder.php should be located in the Test/Unit/Service/Product directory)

## Tech Stack
- Use PHPUnit 10
- PHP 8.3
- Magento 2.4.8

### Test Strategy
- Don't test Event Manager's dispatch methods
- Use @dataProvider annotations when it's needed
  - use /**
        * @dataProvider myProvider
        */
  - not #[DataProvider('myProvider')]
- Do not test methods coming from the parent classes (Magento core)
- Do not use assertObjectHasProperty assertions

### Mocking Strategy
- Instantiate the tested class in the setUp() method by mocking every object that needs to be passed in its constructor
- Use nullable types for the testing class properties. ex:
    - protected ?ConfigHelper $configHelper;
      or
    - protected null|(CategoryResourceModel&MockObject) $categoryResource = null;

**DO Mock:**
- Every object that needs to be passed in the constructor of the tested class

**DON'T Mock:**
- The tested class itself

### Example

```php
class InstantSearchHelperTest extends TestCase
{
    protected ?InstantSearchHelper $instantSearchHelper;

    protected ?ScopeConfigInterface $configInterface;
    protected ?WriterInterface $configWriter;
    protected ?Serializer $serializer;

    protected function setUp(): void
    {
        $this->configInterface = $this->createMock(ScopeConfigInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);
        $this->serializer = $this->createMock(Serializer::class);

        $this->instantSearchHelper = new InstantSearchHelper(
            $this->configInterface,
            $this->configWriter,
            $this->serializer
        );
    }
//...
}
```
