<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit;

use PHPUnit\Framework\TestCase;

class BoostResourcesTest extends TestCase
{
    private const ROOT = __DIR__.'/../../resources/boost';

    public function test_the_guideline_exists_and_names_the_skill(): void
    {
        $guideline = (string) file_get_contents(self::ROOT.'/guidelines/core.blade.php');

        $this->assertStringContainsString('pharaonic/laravel-modulator', $guideline);
        $this->assertStringContainsString('modulator-development', $guideline);
        $this->assertSame(substr_count($guideline, '@verbatim'), substr_count($guideline, '@endverbatim'), 'Every code snippet is wrapped in @verbatim.');
        $this->assertSame(substr_count($guideline, '<code-snippet'), substr_count($guideline, '@verbatim'), 'Every code snippet is inside @verbatim.');
    }

    public function test_every_skill_has_frontmatter_matching_its_folder(): void
    {
        $skills = glob(self::ROOT.'/skills/*/SKILL.md') ?: [];
        $this->assertNotSame([], $skills);

        foreach ($skills as $path) {
            $contents = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $this->assertSame(1, preg_match('/\A---\n(.*?)\n---\n/s', $contents, $match), $path.' starts with YAML frontmatter.');

            $fields = [];

            foreach (explode("\n", $match[1]) as $line) {
                if (preg_match('/^([a-z]+):\s*(.+)$/', $line, $field) === 1) {
                    $fields[$field[1]] = $field[2];
                }
            }

            $this->assertSame(basename(dirname($path)), $fields['name'] ?? null, 'The skill name is its folder name.');
            $this->assertNotSame('', trim($fields['description'] ?? ''), 'The skill has a description.');
        }
    }
}
