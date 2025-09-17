<?php

declare(strict_types=1);

namespace Boson\WebView\Api\WebComponents\Context;

interface MutableContentProviderInterface extends ContentProviderInterface
{
    public string $html {
        get;
        /**
         * Updates inner html content
         *
         * @link https://developer.mozilla.org/docs/Web/API/Element/innerHTML
         */
        set(\Stringable|string $html);
    }

    public string $text {
        get;
        /**
         * Updates inner text content
         *
         * @link https://developer.mozilla.org/docs/Web/API/Node/textContent
         */
        set(\Stringable|string $text);
    }
}
