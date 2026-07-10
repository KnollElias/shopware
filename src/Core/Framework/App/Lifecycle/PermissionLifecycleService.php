<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Privileges\AppCapabilityPermission;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionLifecycleService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Privileges $privileges,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @internal only for use by the app-system
     */
    public function updatePrivileges(Manifest $manifest, string $appId, bool $acceptPermissions, Context $context): void
    {
        $permissions = $manifest->getPermissions();
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];
        $privileges = array_values(array_unique([...$privileges, ...AppCapabilityPermission::impliedPrivileges($manifest)]));

        if ($acceptPermissions) {
            $this->privileges->setPrivileges($appId, $privileges, $context);

            return;
        }

        $this->privileges->requestPrivileges($appId, $privileges, $context);
    }

    /**
     * @internal only for use by the app-system
     */
    public function removeRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `acl_role` WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
            ]
        );
    }

    public function softDeleteRole(string $roleId): void
    {
        $this->connection->executeStatement(
            'UPDATE `acl_role` SET `deleted_at` = :datetime WHERE id = :id',
            [
                'id' => Uuid::fromHexToBytes($roleId),
                'datetime' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
