<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Privileges;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;

/**
 * Capability permissions implied by the handlers an app declares in its manifest.
 *
 * They behave like the <crud> shorthand: declaring a tax provider, payment method or
 * checkout gateway implicitly requests the matching permission, so Shopware only pushes
 * cart/order/customer data to the handler once that permission has been granted. For
 * merchant-installed apps the grant happens at install; for services it happens on
 * consent. This closes the gap where a not-yet-consented service still received PII.
 *
 * The values are stored verbatim in acl_role.privileges next to the CRUD privileges.
 * They intentionally have no `entity:operation` shape so they are never mistaken for a
 * CRUD privilege and land in the "additional privileges" bucket on the consent screen.
 *
 * @internal
 */
#[Package('framework')]
enum AppCapabilityPermission: string
{
    case TAX_PROVIDER = 'tax_provider';
    case PAYMENT = 'payment';
    case CHECKOUT_GATEWAY = 'checkout_gateway';

    /**
     * @return list<string>
     */
    public static function impliedPrivileges(Manifest $manifest): array
    {
        $privileges = [];

        if (($manifest->getTax()?->getTaxProviders() ?? []) !== []) {
            $privileges[] = self::TAX_PROVIDER->value;
        }

        if (($manifest->getPayments()?->getPaymentMethods() ?? []) !== []) {
            $privileges[] = self::PAYMENT->value;
        }

        if ($manifest->getGateways()?->getCheckout() !== null) {
            $privileges[] = self::CHECKOUT_GATEWAY->value;
        }

        return $privileges;
    }
}
