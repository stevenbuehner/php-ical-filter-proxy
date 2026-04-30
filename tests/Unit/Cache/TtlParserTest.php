<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Cache\TtlParser;
use PHPUnit\Framework\TestCase;

final class TtlParserTest extends TestCase
{
    public function testParsesUnits(): void
    {
        $parser = new TtlParser();

        self::assertSame(30, $parser->parse('30s'));
        self::assertSame(900, $parser->parse('15m'));
        self::assertSame(3600, $parser->parse('1h'));
        self::assertSame(86400, $parser->parse('1d'));
    }
}
