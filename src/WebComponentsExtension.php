<?php

declare(strict_types=1);

namespace Boson\WebView\Api\WebComponents;

use Boson\Contracts\Id\IdentifiableInterface;
use Boson\Dispatcher\EventListener;
use Boson\Extension\Attribute\AvailableAs;
use Boson\Extension\Attribute\DependsOn;
use Boson\Extension\Extension;
use Boson\WebView\Api\Bindings\BindingsApiInterface;
use Boson\WebView\Api\Bindings\BindingsExtension;
use Boson\WebView\Api\Data\DataExtension;
use Boson\WebView\Api\Data\DataRetrieverInterface;
use Boson\WebView\Api\Scripts\ScriptsApiInterface;
use Boson\WebView\Api\Scripts\ScriptsExtension;
use Boson\WebView\WebView;

/**
 * @template-extends Extension<WebView>
 */
#[AvailableAs('components', WebComponentsApiInterface::class)]
#[DependsOn(BindingsExtension::class)]
#[DependsOn(ScriptsExtension::class)]
#[DependsOn(DataExtension::class)]
final class WebComponentsExtension extends Extension
{
    /**
     * @var non-empty-string
     */
    private const string BOSON_CLIENT_API = __DIR__ . '/../resources/dist/main.js.php';

    public function __construct(
        private readonly WebComponentsExtensionCreateInfo $info = new WebComponentsExtensionCreateInfo(),
    ) {}

    public function load(IdentifiableInterface $ctx, EventListener $listener): WebComponentsApi
    {
        $scripts = $ctx->get(ScriptsApiInterface::class);
        /** @phpstan-ignore-next-line : Allow to pass second parameter */
        $scripts->preload((string) @\file_get_contents(self::BOSON_CLIENT_API), true);

        return new WebComponentsApi(
            context: $ctx,
            listener: $listener,
            info: $this->info,
            bindings: $ctx->get(BindingsApiInterface::class),
            scripts: $scripts,
            data: $ctx->get(DataRetrieverInterface::class),
        );
    }
}
