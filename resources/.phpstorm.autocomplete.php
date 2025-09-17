<?php

declare(strict_types=1);

namespace Boson\WebView;

use Boson\WebView\Api\WebComponents\WebComponentsExtensionInterface;

class WebView
{
    /**
     * Gets access to the Web Components API of the webview.
     */
    public readonly WebComponentsExtensionInterface $components;
}
