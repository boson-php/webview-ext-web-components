<?php

declare(strict_types=1);

namespace Boson\WebView\Api\WebComponents\Context\Internal\Content;

use Boson\WebView\Api\Data\SyncDataRetrieverInterface;
use Boson\WebView\Api\Scripts\ScriptEvaluatorInterface;
use Boson\WebView\Api\WebComponents\Context\MutableContentProviderInterface;

/**
 * @internal this is an internal library class, please do not use it in your code
 * @psalm-internal Boson\WebView\Api\WebComponents
 */
final class MutableComponentContentProvider extends ComponentContentProvider implements
    MutableContentProviderInterface
{
    public string $html {
        /** @phpstan-ignore-next-line PHPStan does not support property inheritance */
        get => parent::$html::get();
        set(\Stringable|string $html) {
            $this->scripts->eval(\sprintf(
                'this.innerHTML = `%s`',
                \addcslashes((string) $html, '`'),
            ));
        }
    }

    public string $text {
        /** @phpstan-ignore-next-line PHPStan does not support property inheritance */
        get => parent::$text::get();
        set(\Stringable|string $text) {
            $this->scripts->eval(\sprintf(
                'this.textContent = `%s`',
                \addcslashes((string) $text, '`'),
            ));
        }
    }

    public function __construct(
        private readonly ScriptEvaluatorInterface $scripts,
        SyncDataRetrieverInterface $data,
    ) {
        parent::__construct($data);
    }
}
