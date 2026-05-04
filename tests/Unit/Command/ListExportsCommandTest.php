<?php

declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ListExportsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ListExportsCommandTest extends TestCase
{
    public function testListExportsShowsSecretFirstFeedPath(): void
    {
        $root = $this->makeProjectRoot();

        file_put_contents($root . '/config/calendars.yaml', <<<'YAML'
sources:
  source_a:
    label: "Source A"
    url: "https://example.com/source.ics"
    cache_ttl: "15m"
exports:
  export_a:
    title: "Export A"
    slug: "export-a"
    token: "secret-token"
    cache_ttl: "10m"
    include_sources:
      - source: source_a
YAML);

        $tester = new CommandTester(new ListExportsCommand($root));
        $exit = $tester->execute([]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('/feed/secret-token/export-a.ics', $tester->getDisplay());
    }

    private function makeProjectRoot(): string
    {
        $root = sys_get_temp_dir() . '/ical_cmd_' . uniqid('', true);
        mkdir($root . '/config', 0777, true);

        return $root;
    }
}
