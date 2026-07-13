<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Permission\Permissions;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(PermissionLifecycleService::class)]
class PermissionLifecycleServiceTest extends TestCase
{
    private Connection&Stub $connection;

    private Privileges&MockObject $permissions;

    private PermissionLifecycleService $service;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->permissions = $this->createMock(Privileges::class);
        $this->service = new PermissionLifecycleService($this->connection, $this->permissions, new NativeClock());
    }

    public function testUpdatePrivilegesAutoAcceptsIfFlagIsSpecified(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $manifest = $this->manifestWithPermissions(['customer' => ['read', 'update']]);

        $this->permissions->expects($this->once())
            ->method('setPrivileges')
            ->with($appId, ['customer:read', 'customer:update'], $context);

        $this->service->updatePrivileges($manifest, $appId, true, $context);
    }

    public function testUpdatePrivilegesDoesNotAutoAcceptIfFlagIsNotSpecified(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $manifest = $this->manifestWithPermissions(['customer' => ['read', 'update']]);

        $this->permissions->expects($this->once())
            ->method('requestPrivileges')
            ->with($appId, ['customer:read', 'customer:update'], $context);

        $this->service->updatePrivileges($manifest, $appId, false, Context::createDefaultContext());
    }

    public function testUpdatePrivilegesAddsImpliedCapabilityPrivileges(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $manifest = $this->manifestWithPermissions(['customer' => ['read']], [CheckoutGateway::PERMISSION]);

        $this->permissions->expects($this->once())
            ->method('requestPrivileges')
            ->with($appId, ['customer:read', CheckoutGateway::PERMISSION], $context);

        $this->service->updatePrivileges($manifest, $appId, false, $context);
    }

    /**
     * @param array<string, list<string>> $permissions
     * @param list<string> $impliedPrivileges
     */
    private function manifestWithPermissions(array $permissions, array $impliedPrivileges = []): Manifest&Stub
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPermissions')->willReturn(Permissions::fromArray(['permissions' => $permissions]));
        $manifest->method('getImpliedPrivileges')->willReturn($impliedPrivileges);

        return $manifest;
    }
}
