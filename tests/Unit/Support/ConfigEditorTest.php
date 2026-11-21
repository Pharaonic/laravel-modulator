<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Support;

use Pharaonic\Laravel\Modulator\Support\ConfigEditor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigEditorTest extends TestCase
{
    private ConfigEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = new ConfigEditor;
    }

    public function test_it_adds_to_the_published_default_and_keeps_everything_else(): void
    {
        $code = (string) file_get_contents(__DIR__.'/../../../config/modulator.php');
        $edited = $this->editor->add($code, 'Shop/Orders');

        $this->assertSame(['Shop/Orders'], $this->editor->read($edited));
        $this->assertStringContainsString("        // 'Analytics',\n        'Shop/Orders',\n    ],", $edited);
        // Only the new line differs.
        $this->assertSame($code, str_replace("        'Shop/Orders',\n", '', $edited));

        $removed = $this->editor->remove($edited, 'Shop/Orders');
        $this->assertSame($code, $removed);
    }

    public function test_it_edits_multiline_lists_with_or_without_a_trailing_comma(): void
    {
        $code = "<?php\n\nreturn [\n    'disabled' => [\n        'Blog', // keep\n        'Shop'\n    ],\n    'pools' => [],\n];\n";
        $edited = $this->editor->add($code, 'Analytics');

        $this->assertSame("<?php\n\nreturn [\n    'disabled' => [\n        'Blog', // keep\n        'Shop',\n        'Analytics',\n    ],\n    'pools' => [],\n];\n", $edited);
        $this->assertSame("<?php\n\nreturn [\n    'disabled' => [\n        'Blog', // keep\n        'Analytics',\n    ],\n    'pools' => [],\n];\n", $this->editor->remove($edited, 'Shop'));
    }

    public function test_it_edits_inline_lists(): void
    {
        $this->assertSame("<?php return ['disabled' => ['Shop'], 'x' => 1];", $this->editor->add("<?php return ['disabled' => [], 'x' => 1];", 'Shop'));
        $this->assertSame("<?php return ['disabled' => ['Blog', 'Shop']];", $this->editor->add("<?php return ['disabled' => ['Blog']];", 'Shop'));
        $this->assertSame("<?php return ['disabled' => ['Blog']];", $this->editor->remove("<?php return ['disabled' => ['Blog', 'Shop']];", 'Shop'));
        $this->assertSame("<?php return ['disabled' => ['Shop']];", $this->editor->remove("<?php return ['disabled' => ['Blog', 'Shop']];", 'Blog'));
        $this->assertSame("<?php return array('disabled' => array('Shop'));", $this->editor->add("<?php return array('disabled' => array());", 'Shop'));
    }

    public function test_it_adds_the_key_when_the_file_has_none(): void
    {
        $edited = $this->editor->add("<?php\n\nreturn [\n    'pools' => [],\n];\n", 'Shop');

        $this->assertSame(['Shop'], $this->editor->read($edited));
        $this->assertStringContainsString("'pools' => [],", $edited);
    }

    public function test_adding_a_listed_module_or_removing_an_unlisted_one_changes_nothing(): void
    {
        $code = "<?php return ['disabled' => ['Shop']];";

        $this->assertSame($code, $this->editor->add($code, 'Shop'));
        $this->assertSame($code, $this->editor->remove($code, 'Blog'));
    }

    public function test_only_the_top_level_key_is_edited(): void
    {
        $code = "<?php return ['pools' => ['x' => ['disabled' => ['Nested']]], 'disabled' => ['Shop']];";

        $this->assertSame(['Shop'], $this->editor->read($code));
        $this->assertSame("<?php return ['pools' => ['x' => ['disabled' => ['Nested']]], 'disabled' => ['Shop', 'Blog']];", $this->editor->add($code, 'Blog'));
    }

    #[DataProvider('computedLists')]
    public function test_computed_lists_are_refused(string $code): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot be edited safely');

        $this->editor->add($code, 'Shop');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function computedLists(): array
    {
        return [
            'env' => ["<?php return ['disabled' => explode(',', env('DISABLED', ''))];"],
            'variable' => ["<?php \$d = []; return ['disabled' => \$d];"],
            'keys' => ["<?php return ['disabled' => ['Shop' => true]];"],
            'call inside' => ["<?php return ['disabled' => ['Shop', strtoupper('blog')]];"],
            'spread' => ["<?php return ['disabled' => [...\$x]];"],
        ];
    }

    public function test_broken_or_ambiguous_files_are_refused(): void
    {
        foreach ([
            '<?php return [',
            "<?php return ['disabled' => [], 'disabled' => []];",
            '<?php return config();',
        ] as $code) {
            try {
                $this->editor->add($code, 'Shop');
                $this->fail('Expected a refusal for: '.$code);
            } catch (RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }
}
