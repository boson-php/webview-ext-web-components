<?php

declare(strict_types=1);

namespace Boson\WebView;

use Boson\WebView\Api\WebComponents\WebComponentsApiInterface;

class WebView
{
    /**
     * Gets access to the Web Components API of the webview.
     */
    public readonly WebComponentsApiInterface $components;
}
