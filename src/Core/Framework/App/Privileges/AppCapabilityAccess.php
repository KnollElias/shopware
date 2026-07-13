<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Privileges;

use Shopware\Core\Framework\Log\Package;

/**
 * Answers whether an app has been granted a capability permission, i.e. whether Shopware
 * is allowed to push checkout data to the app's tax provider, payment or checkout gateway
 * handler. Reads the granted privileges (acl_role.privileges); permissions that are only
 * requested (pending consent) do not count.
 *
 * @internal
 */
#[Package('framework')]
class AppCapabilityAccess
{
    public function __construct(private readonly Privileges $privileges)
    {
    }

    public function isGranted(string $appId, string $privilege): bool
    {
        $granted = $this->privileges->getPrivileges([$appId])[$appId] ?? [];

        return \in_array($privilege, $granted, true);
    }
}
