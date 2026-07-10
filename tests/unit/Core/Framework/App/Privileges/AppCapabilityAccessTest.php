<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Privileges;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Privileges\AppCapabilityAccess;
use Shopware\Core\Framework\App\Privileges\AppCapabilityPermission;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AppCapabilityAccess::class)]
class AppCapabilityAccessTest extends TestCase
{
    public function testIsGrantedReturnsTrueWhenMarkerPresent(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read', 'payment']]);

        $access = new AppCapabilityAccess($privileges);

        static::assertTrue($access->isGranted($appId, AppCapabilityPermission::PAYMENT));
    }

    public function testIsGrantedReturnsFalseWhenMarkerMissing(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        $access = new AppCapabilityAccess($privileges);

        static::assertFalse($access->isGranted($appId, AppCapabilityPermission::PAYMENT));
    }

    public function testIsGrantedReturnsFalseWhenAppUnknown(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([]);

        $access = new AppCapabilityAccess($privileges);

        static::assertFalse($access->isGranted($appId, AppCapabilityPermission::TAX_PROVIDER));
    }
}
