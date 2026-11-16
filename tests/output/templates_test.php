<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_underlyit\output;

/**
 * Tests that every template renders its documented example context.
 *
 * Mustache ends a comment at the first closing pair of braces, so an example context with nested objects
 * written without spaces would end the documentation comment early and print the rest of it on the page.
 *
 * @package    mod_underlyit
 * @category   test
 * @copyright  2026 Álvaro Gregori <50322409+alvarogregori@users.noreply.github.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class templates_test extends \advanced_testcase {
    /**
     * Names of the templates of the plugin.
     *
     * @return array
     */
    public static function template_provider(): array {
        $templates = [];
        foreach (glob(__DIR__ . '/../../templates/*.mustache') as $file) {
            $name = basename($file, '.mustache');
            $templates[$name] = [$name];
        }
        return $templates;
    }

    /**
     * The documentation comment ends where it should and the example renders without leftovers.
     *
     * @dataProvider template_provider
     * @param string $name Template name without the component.
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('template_provider')]
    public function test_example_context_renders(string $name): void {
        global $PAGE;
        $this->resetAfterTest();
        $source = file_get_contents(__DIR__ . "/../../templates/{$name}.mustache");

        $tag = strpos($source, '@template');
        $this->assertNotFalse($tag, 'Documentation comment found');
        $start = strrpos(substr($source, 0, $tag), '{{!');
        $end = strpos($source, '}}', $start);
        $comment = substr($source, $start, $end - $start);
        $marker = 'Example context (json):';
        $this->assertStringContainsString($marker, $comment, 'The comment contains the whole example context');
        $example = json_decode(substr($comment, strpos($comment, $marker) + strlen($marker)), true);
        $this->assertIsArray($example, 'The example context is valid JSON');
        $this->assertMatchesRegularExpression(
            '/^\s*$/',
            substr($comment, strrpos($comment, '}') + 1),
            'Nothing follows the example context inside the comment'
        );

        $html = $PAGE->get_renderer('core')->render_from_template("mod_underlyit/{$name}", $example);
        $this->assertStringNotContainsString('@template', $html);
        $this->assertStringNotContainsString('Example context', $html);
        $this->assertStringNotContainsString('"value":', $html);
    }
}
