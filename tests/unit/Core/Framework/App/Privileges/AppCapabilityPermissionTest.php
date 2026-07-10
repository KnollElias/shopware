<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Privileges;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\Gateways;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\PaymentMethod;
use Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod\Payments;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\TaxProvider;
use Shopware\Core\Framework\App\Privileges\AppCapabilityPermission;

/**
 * @internal
 */
#[CoversClass(AppCapabilityPermission::class)]
class AppCapabilityPermissionTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('manifestProvider')]
    public function testImpliedPrivileges(bool $tax, bool $payment, bool $checkout, array $expected): void
    {
        $manifest = static::createStub(Manifest::class);

        $taxElement = static::createStub(Tax::class);
        $taxElement->method('getTaxProviders')->willReturn($tax ? [static::createStub(TaxProvider::class)] : []);
        $manifest->method('getTax')->willReturn($taxElement);

        $payments = static::createStub(Payments::class);
        $payments->method('getPaymentMethods')->willReturn($payment ? [static::createStub(PaymentMethod::class)] : []);
        $manifest->method('getPayments')->willReturn($payments);

        $gateways = static::createStub(Gateways::class);
        $gateways->method('getCheckout')->willReturn($checkout ? static::createStub(CheckoutGateway::class) : null);
        $manifest->method('getGateways')->willReturn($gateways);

        static::assertSame($expected, AppCapabilityPermission::impliedPrivileges($manifest));
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool, 3: list<string>}>
     */
    public static function manifestProvider(): iterable
    {
        yield 'nothing declared' => [false, false, false, []];
        yield 'tax provider' => [true, false, false, ['tax_provider']];
        yield 'payment' => [false, true, false, ['payment']];
        yield 'checkout gateway' => [false, false, true, ['checkout_gateway']];
        yield 'all three' => [true, true, true, ['tax_provider', 'payment', 'checkout_gateway']];
    }

    public function testImpliedPrivilegesWithEmptyManifest(): void
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getTax')->willReturn(null);
        $manifest->method('getPayments')->willReturn(null);
        $manifest->method('getGateways')->willReturn(null);

        static::assertSame([], AppCapabilityPermission::impliedPrivileges($manifest));
    }
}
