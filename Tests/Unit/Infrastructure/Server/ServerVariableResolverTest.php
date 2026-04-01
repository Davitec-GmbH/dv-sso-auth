<?php

declare(strict_types=1);

namespace Davitec\DvSsoAuth\Tests\Unit\Infrastructure\Server;

use Davitec\DvSsoAuth\Infrastructure\Server\ServerVariableResolver;
use PHPUnit\Framework\TestCase;

final class ServerVariableResolverTest extends TestCase
{
    public function testReturnsExactMatchFirst(): void
    {
        $resolver = new ServerVariableResolver();

        $value = $resolver->resolve(['REMOTE_USER' => 'john'], 'REMOTE_USER');

        self::assertSame('john', $value);
    }

    public function testFallsBackToPrefixedVariant(): void
    {
        $resolver = new ServerVariableResolver();

        $value = $resolver->resolve(['REDIRECT_mail' => 'john@example.org'], 'mail');

        self::assertSame('john@example.org', $value);
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $resolver = new ServerVariableResolver();

        $value = $resolver->resolve(['SOME_OTHER_KEY' => 'x'], 'mail');

        self::assertNull($value);
    }
}
