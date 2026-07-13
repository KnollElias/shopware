<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Privileges;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Privileges\AppCapability;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppCapability::class)]
class AppCapabilityTest extends TestCase
{
    public function testCanReturnsTrueWhenActionGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read', Payments::PERMISSION]]);

        static::assertTrue((new AppCapability($privileges))->can($appId, Payments::PERMISSION));
    }

    public function testCanReturnsFalseWhenActionNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Payments::PERMISSION));
    }

    public function testCanReturnsFalseWhenAppUnknown(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }
}
