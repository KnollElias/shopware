<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Privileges\AppCapabilityPermission;
use Shopware\Core\Framework\App\Privileges\Utils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class PermissionsDeltaProvider extends AbstractAppDeltaProvider
{
    final public const DELTA_NAME = 'permissions';

    public function getDeltaName(): string
    {
        return self::DELTA_NAME;
    }

    /**
     * @return array<string, PermissionCollection>
     */
    public function getReport(Manifest $manifest, AppEntity $app): array
    {
        $privileges = $this->privilegesFromManifest($manifest);

        if ($privileges === []) {
            return [];
        }

        return Utils::makeCategorizedPermissions($privileges);
    }

    public function hasDelta(Manifest $manifest, AppEntity $app): bool
    {
        $newPrivileges = $this->privilegesFromManifest($manifest);

        if ($newPrivileges === []) {
            return false;
        }

        $aclRole = $app->getAclRole();

        if (!$aclRole) {
            return true;
        }

        $privilegesDelta = array_diff($newPrivileges, $aclRole->getPrivileges());

        return $privilegesDelta !== [];
    }

    /**
     * @return list<string>
     */
    private function privilegesFromManifest(Manifest $manifest): array
    {
        $permissions = $manifest->getPermissions();
        $privileges = $permissions ? $permissions->asParsedPrivileges() : [];

        return array_values(array_unique([...$privileges, ...AppCapabilityPermission::impliedPrivileges($manifest)]));
    }
}
