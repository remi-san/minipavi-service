<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Cli;

use MiniPaviService\Cli\Input;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

final class InputTest extends TestCase
{
    public function testReadsTheStringsOfAListArgument(): void
    {
        self::assertSame(
            ['home.xml', 'menu.xml'],
            Input::strings($this->input(['home.xml', 'menu.xml']), argument: 'paths'),
        );
    }

    public function testSkipsTheValuesOfAListArgumentThatAreNotStrings(): void
    {
        // A command run from PHP (an ArrayInput) may be given any value
        self::assertSame(
            ['home.xml', 'menu.xml'],
            Input::strings($this->input([42, 'home.xml', null, 'menu.xml']), argument: 'paths'),
        );
    }

    public function testReadsAnArgumentThatIsNotAListAsEmpty(): void
    {
        self::assertSame([], Input::strings($this->input('home.xml'), argument: 'paths'));
    }

    private function input(mixed $paths): ArrayInput
    {
        return new ArrayInput(['paths' => $paths], new InputDefinition([
            new InputArgument('paths', InputArgument::IS_ARRAY),
        ]));
    }
}
