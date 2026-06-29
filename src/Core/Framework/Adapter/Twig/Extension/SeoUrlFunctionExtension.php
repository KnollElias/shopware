<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\Extension;

use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Adapter\Twig\TwigContextHelper;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class SeoUrlFunctionExtension extends AbstractExtension
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RoutingExtension $routingExtension,
        private readonly SeoUrlPlaceholderHandlerInterface $seoUrlReplacer,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('seoUrl', $this->seoUrl(...), [
                'is_safe_callback' => $this->routingExtension->isUrlGenerationSafe(...),
                'needs_context' => true,
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string|int, mixed> $parameters
     */
    public function seoUrl(array $context, string $name, array $parameters = []): string
    {
        if (str_contains($name, '.')) {
            return $this->seoUrlReplacer->generate($name, $parameters);
        }

        $primaryKey = array_first($parameters);
        if (!\is_string($primaryKey)) {
            return $this->seoUrlReplacer->generate($name, $parameters);
        }

        try {
            return $this->entityRouteResolver->generateSeoUrlPlaceholder(
                $name,
                $primaryKey,
                $this->isSalesChannelHeadless($context)
            );
        } catch (SeoUrlRouteConfigException) {
            return $this->seoUrlReplacer->generate($name, $parameters);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function isSalesChannelHeadless(array $context): bool
    {
        $salesChannelContext = TwigContextHelper::getSalesChannelContext($context);

        return $salesChannelContext?->isHeadless() === true;
    }
}
