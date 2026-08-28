<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

use Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field\Checkboxes;
use Algolia\AlgoliaSearch\Test\TestCase;

class CheckboxesTest extends TestCase
{
    /**
     * Checkboxes extends Magento\Backend\Block\Template, whose constructor reaches into the
     * ObjectManager — unavailable in a unit test. getCheckboxHtml() touches no collaborator, so
     * rather than mocking the class (which would need a fake expectation just to dodge the
     * constructor), a constructor-less reflection instance is used instead.
     */
    protected function createObjectToTest(): Checkboxes
    {
        return (new \ReflectionClass(Checkboxes::class))->newInstanceWithoutConstructor();
    }

    public function testGetCheckboxHtmlRendersCheckedInputWithDescription(): void
    {
        $html = $this->invokeMethod(
            $this->createObjectToTest(),
            'getCheckboxHtml',
            ['my_id', 'my_name', 'my_value', true, 'My Label', 'My description']
        );

        $this->assertStringContainsString('checked', $html);
        $this->assertStringContainsString('My Label', $html);
        $this->assertStringContainsString('My description', $html);
        $this->assertStringContainsString('value="my_value"', $html);
        $this->assertStringContainsString('id="my_id"', $html);
    }

    public function testGetCheckboxHtmlRendersUncheckedInputWithoutDescription(): void
    {
        $html = $this->invokeMethod(
            $this->createObjectToTest(),
            'getCheckboxHtml',
            ['my_id', 'my_name', 'my_value', false, 'My Label', null]
        );

        $this->assertStringNotContainsString('checked', $html);
        $this->assertStringNotContainsString('<span class="description">', $html);
        $this->assertStringContainsString('My Label', $html);
    }

    public function testGetCheckboxHtmlUsesNameAsLabelFallback(): void
    {
        $html = $this->invokeMethod(
            $this->createObjectToTest(),
            'getCheckboxHtml',
            ['my_id', 'fallback_name', 'val', false, null, null]
        );

        $this->assertStringContainsString('fallback_name', $html);
    }
}
