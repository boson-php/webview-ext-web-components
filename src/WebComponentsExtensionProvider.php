<?php

declare(strict_types=1);

namespace Boson\WebView\Api\WebComponents;

use Boson\Contracts\Id\IdentifiableInterface;
use Boson\Dispatcher\EventListener;
use Boson\Extension\Attribute\AvailableAs;
use Boson\Extension\Attribute\DependsOn;
use Boson\Extension\ExtensionProvider;
use Boson\WebView\Api\Bindings\BindingsExtensionInterface;
use Boson\WebView\Api\Bindings\BindingsExtensionProvider;
use Boson\WebView\Api\Data\DataExtensionInterface;
use Boson\WebView\Api\Data\DataExtensionProvider;
use Boson\WebView\Api\Scripts\ScriptsExtensionInterface;
use Boson\WebView\Api\Scripts\ScriptsExtensionProvider;
use Boson\WebView\WebView;

/**
 * @template-extends ExtensionProvider<WebView>
 */
#[AvailableAs(['components', WebComponentsExtensionInterface::class])]
#[DependsOn(BindingsExtensionProvider::class)]
#[DependsOn(ScriptsExtensionProvider::class)]
#[DependsOn(DataExtensionProvider::class)]
final class WebComponentsExtensionProvider extends ExtensionProvider
{
    /**
     * @var non-empty-string
     */
    private const string BOSON_CLIENT_API = __DIR__ . '/../resources/dist/main.js.php';

    public function __construct(
        private readonly WebComponentsExtensionCreateInfo $info = new WebComponentsExtensionCreateInfo(),
    ) {}

    public function load(IdentifiableInterface $ctx, EventListener $listener): WebComponentsExtension
    {
        $scripts = $ctx->get(ScriptsExtensionInterface::class);
        /** @phpstan-ignore-next-line : Allow to pass second parameter */
        $scripts->preload((string) @\file_get_contents(self::BOSON_CLIENT_API), true);

        return new WebComponentsExtension(
            context: $ctx,
            listener: $listener,
            info: $this->info,
            bindings: $ctx->get(BindingsExtensionInterface::class),
            scripts: $scripts,
            data: $ctx->get(DataExtensionInterface::class),
        );
    }
}
