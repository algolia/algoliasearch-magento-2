<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

use Algolia\AlgoliaSearch\Block\Adminhtml\System\Config\Form\Field\Checkboxes;
use Algolia\AlgoliaSearch\Test\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CheckboxesTest extends TestCase
{
    protected null|(Checkboxes&MockObject) $field = null;

    protected function setUp(): void
    {
        $this->field = $this->getMockBuilder(Checkboxes::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    public function testGetCheckboxHtmlRendersCheckedInputWithDescription(): void
    {
        $html = $this->invokeMethod(
            $this->field,
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
            $this->field,
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
            $this->field,
            'getCheckboxHtml',
            ['my_id', 'fallback_name', 'val', false, null, null]
        );

        $this->assertStringContainsString('fallback_name', $html);
    }
}
