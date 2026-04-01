<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Unit\Domain\Provisioning;

use Davitec\DvSsoAuth\Domain\Provisioning\AffiliationParser;
use PHPUnit\Framework\TestCase;

final class AffiliationParserTest extends TestCase
{
    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        $parser = new AffiliationParser();

        self::assertSame([], $parser->parse(null));
        self::assertSame([], $parser->parse('   '));
    }

    public function testSplitsTrimsRemovesDomainsAndDeduplicates(): void
    {
        $parser = new AffiliationParser();

        $affiliations = $parser->parse('member@org.test; admin ;member@org.test;editor@foo');

        self::assertSame(['member', 'admin', 'editor'], $affiliations);
    }
}
