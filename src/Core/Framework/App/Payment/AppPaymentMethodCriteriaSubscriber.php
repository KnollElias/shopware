<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Privileges\AppCapability;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Event\SalesChannelProcessCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Hides an app's payment method in sales-channel scope until the app has been granted the payment
 * permission, so a not-yet-consented app is never offered and never receives order/customer data.
 *
 * @internal
 */
#[Package('checkout')]
class AppPaymentMethodCriteriaSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AppCapability $appCapability,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.payment_method.process.criteria' => 'hideUngrantedAppPaymentMethods',
        ];
    }

    public function hideUngrantedAppPaymentMethods(SalesChannelProcessCriteriaEvent $event): void
    {
        /** @var list<string> $appIds */
        $appIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(app_id)) FROM app_payment_method WHERE app_id IS NOT NULL'
        );

        $ungranted = array_values(array_filter(
            $appIds,
            fn (string $appId): bool => !$this->appCapability->can($appId, Payments::PERMISSION)
        ));

        if ($ungranted === []) {
            return;
        }

        $event->getCriteria()->addFilter(
            new NandFilter([new EqualsAnyFilter('appPaymentMethod.appId', $ungranted)])
        );
    }
}
