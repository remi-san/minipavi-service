<?php

declare(strict_types=1);

namespace MiniPaviService\Tests\Support;

use MiniPaviService\Http\Runner;

/**
 * Runner counting its runs
 */
final class RecordingRunner implements Runner
{
    public int $runs = 0;

    public function run(): void
    {
        $this->runs++;
    }
}
