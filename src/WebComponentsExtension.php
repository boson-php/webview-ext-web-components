<?php

declare(strict_types=1);

namespace Boson\WebView\Api\WebComponents;

use Boson\Dispatcher\EventListener;
use Boson\WebView\Api\Bindings\BindingsExtensionInterface;
use Boson\WebView\Api\Data\DataExtensionInterface;
use Boson\WebView\Api\Scripts\ScriptsExtensionInterface;
use Boson\WebView\Api\WebComponents\Component\HasAttributesInterface;
use Boson\WebView\Api\WebComponents\Component\HasClassNameInterface;
use Boson\WebView\Api\WebComponents\Component\HasMethodsInterface;
use Boson\WebView\Api\WebComponents\Component\HasPropertiesInterface;
use Boson\WebView\Api\WebComponents\Exception\BuiltinComponentMethodNameException;
use Boson\WebView\Api\WebComponents\Exception\BuiltinComponentNameException;
use Boson\WebView\Api\WebComponents\Exception\BuiltinComponentPropertyNameException;
use Boson\WebView\Api\WebComponents\Exception\ComponentAlreadyDefinedException;
use Boson\WebView\Api\WebComponents\Exception\InvalidComponentMethodNameException;
use Boson\WebView\Api\WebComponents\Exception\InvalidComponentNameException;
use Boson\WebView\Api\WebComponents\Exception\InvalidComponentPropertyNameException;
use Boson\WebView\Api\WebComponents\Internal\WebViewComponentBuilder;
use Boson\WebView\Api\WebComponents\Internal\WebViewComponentInstances;
use Boson\WebView\Api\WebViewExtension;
use Boson\WebView\Event\WebViewNavigating;
use Boson\WebView\WebView;

/**
 * @template-implements \IteratorAggregate<non-empty-string, class-string>
 */
final class WebComponentsExtension extends WebViewExtension implements
    WebComponentsExtensionInterface,
    \IteratorAggregate
{
    /**
     * ```
     * PCENChar
     *  ::= '-' | '.' | [0-9] | '_' | [a-z] | #xB7 | [#xC0-#xD6] | [#xD8-#xF6]
     *    | [#xF8-#x37D] | [#x37F-#x1FFF] | [#x200C-#x200D] | [#x203F-#x2040]
     *    | [#x2070-#x218F] | [#x2C00-#x2FEF] | [#x3001-#xD7FF] | [#xF900-#xFDCF]
     *    | [#xFDF0-#xFFFD] | [#x10000-#xEFFFF]
     * ```
     *
     * @link https://html.spec.whatwg.org/multipage/custom-elements.html#prod-pcenchar
     *
     * @var non-empty-string
     */
    private const string CE_PCEN_CHAR = '[-._\xB70-9a-z\xC0-\xD6\xD8-\xF6\xF8-\x{037D}\x{037F}-\x{1FFF}\x{200C}-\x{200D}\x{203F}-\x{2040}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{010000}-\x{0EFFFF}]';

    /**
     * ```
     * PotentialCustomElementName
     *      ::= [a-z] (PCENChar)* '-' (PCENChar)*
     * ```
     *
     * @link https://html.spec.whatwg.org/multipage/custom-elements.html#prod-potentialcustomelementname
     */
    private const string CE_POTENTIAL_CUSTOM_ELEMENT_NAME = '[a-z]'
        . '(?:' . self::CE_PCEN_CHAR . ')*'
        . '\-'
        . '(?:' . self::CE_PCEN_CHAR . ')*';

    /**
     * @link https://html.spec.whatwg.org/multipage/custom-elements.html#valid-custom-element-name
     *
     * @var non-empty-string
     */
    private const string CUSTOM_ELEMENT_NAME_PCRE = '/^' . self::CE_POTENTIAL_CUSTOM_ELEMENT_NAME . '$/u';

    /**
     * List of builtin tag names.
     *
     * @link https://www.webcomponents.org/community/articles/how-should-i-name-my-element
     *
     * @var non-empty-list<non-empty-lowercase-string>
     */
    private const array BUILTIN_CUSTOM_ELEMENT_NAMES = [
        'annotation-xml',
        'color-profile',
        'font-face',
        'font-face-src',
        'font-face-uri',
        'font-face-format',
        'font-face-name',
        'missing-glyph',
    ];

    /**
     * List of builtin element property and methods (properties with functions) names.
     *
     * @var non-empty-list<non-empty-string>
     */
    private const array BUILTIN_CUSTOM_ELEMENT_PROPERTIES = [
        'shadowRoot',
        'formAssociated',
        'observedAttributes',
        // Methods
        'constructor',
        'connectedCallback',
        'disconnectedCallback',
        'attributeChangedCallback',
        'adoptedCallback',
        'connectedMoveCallback',
    ];

    /**
     * @see WebComponentsExtensionCreateInfo::$classNamePrefix
     */
    private readonly string $classNamePrefix;

    /**
     * A map containing a link between a tag name and a component class.
     *
     * @var array<non-empty-lowercase-string, class-string>
     */
    private array $components = [];

    /**
     * List of loaded (instantiated) components.
     */
    private readonly WebViewComponentInstances $instances;

    private readonly WebViewComponentBuilder $builder;

    public function __construct(
        WebView $context,
        EventListener $listener,
        WebComponentsExtensionCreateInfo $info,
        private readonly BindingsExtensionInterface $bindings,
        private readonly ScriptsExtensionInterface $scripts,
        private readonly DataExtensionInterface $data,
    ) {
        parent::__construct($context, $listener);

        $this->classNamePrefix = $info->classNamePrefix;

        $this->instances = new WebViewComponentInstances(
            webview: $context,
            instantiator: $info->instantiator,
            data: $this->data,
            scripts: $this->scripts,
        );

        $this->builder = new WebViewComponentBuilder(
            app: $context->window->app,
        );

        $this->registerDefaultFunctions();
        $this->registerDefaultEventListener();
    }

    private function registerDefaultEventListener(): void
    {
        $this->listen(WebViewNavigating::class, function () {
            $this->instances->destroyAll();
        });
    }

    private function registerDefaultFunctions(): void
    {
        $this->bindings->bind('boson.components.created', $this->onCreated(...));
        $this->bindings->bind('boson.components.connected', $this->onConnected(...));
        $this->bindings->bind('boson.components.disconnected', $this->onDisconnected(...));
        $this->bindings->bind('boson.components.attributeChanged', $this->onAttributeChanged(...));
        $this->bindings->bind('boson.components.propertyChanged', $this->onPropertyChanged(...));
        $this->bindings->bind('boson.components.invoke', $this->onInvoke(...));
        $this->bindings->bind('boson.components.fire', $this->onFire(...));
    }

    private function onCreated(string $tag, string $id): ?string
    {
        $component = $this->components[$tag] ?? null;

        if ($component === null || $id === '' || $tag === '') {
            return null;
        }

        return $this->instances->create($id, $tag, $component);
    }

    private function onConnected(string $id): ?string
    {
        if ($id === '') {
            return null;
        }

        return $this->instances->notifyConnect($id);
    }

    private function onDisconnected(string $id): void
    {
        if ($id === '') {
            return;
        }

        $this->instances->notifyDisconnect($id);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private function onInvoke(string $id, string $method, array $arguments): mixed
    {
        if ($id === '' || $method === '') {
            return null;
        }

        return $this->instances->notifyInvoke($id, $method, $arguments);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    private function onFire(string $id, string $method, array $arguments): void
    {
        if ($id === '' || $method === '') {
            return;
        }

        $this->instances->notifyFire($id, $method, $arguments);
    }

    private function onAttributeChanged(string $id, string $name, ?string $value, ?string $previous): void
    {
        if ($id === '' || $name === '') {
            return;
        }

        $this->instances->notifyAttributeChange($id, $name, $value, $previous);
    }

    private function onPropertyChanged(string $id, string $name, string $value): void
    {
        if ($id === '' || $name === '') {
            return;
        }

        $decoded = \json_decode($value, true, flags: \JSON_THROW_ON_ERROR);

        $this->instances->notifyPropertyChange($id, $name, $decoded);
    }

    /**
     * Returns {@see true} in case of custom element name is valid.
     *
     * @link https://html.spec.whatwg.org/multipage/custom-elements.html#valid-custom-element-name
     */
    private function isValidComponentTagName(string $name): bool
    {
        return \preg_match(self::CUSTOM_ELEMENT_NAME_PCRE, $name) >= 1;
    }

    /**
     * Returns {@see true} in case of component tag name is builtin.
     *
     * @link https://www.webcomponents.org/community/articles/how-should-i-name-my-element
     *
     * @param non-empty-lowercase-string $name
     */
    private function isBuiltinComponentTagName(string $name): bool
    {
        return \in_array($name, self::BUILTIN_CUSTOM_ELEMENT_NAMES, true);
    }

    /**
     * @link https://tc39.es/ecma262/multipage/ecmascript-language-lexical-grammar.html#prod-IdentifierName
     */
    private function isValidComponentPropertyName(string $name): bool
    {
        return \preg_match('/^[_$a-zA-Z\xA0-\x{FFFF}][_$a-zA-Z0-9\xA0-\x{FFFF}]*$/u', $name) >= 1;
    }

    /**
     * @param non-empty-string $name
     */
    private function isBuiltinComponentPropertyName(string $name): bool
    {
        return \in_array($name, self::BUILTIN_CUSTOM_ELEMENT_PROPERTIES, true);
    }

    public function add(string $name, string $component): void
    {
        $name = $this->getTagName($name, $component);

        if ($this->isBuiltinComponentTagName($name)) {
            throw BuiltinComponentNameException::becauseComponentNameIsBuiltin($name);
        }

        if (\is_subclass_of($component, HasMethodsInterface::class, true)) {
            foreach ($component::getMethodNames() as $method) {
                if (!$this->isValidComponentPropertyName($method)) {
                    throw InvalidComponentMethodNameException::becauseMethodNameIsInvalid($name, $method);
                }

                if ($this->isBuiltinComponentPropertyName($method)) {
                    throw BuiltinComponentMethodNameException::becauseMethodNameIsInvalid($name, $method);
                }
            }
        }

        if (\is_subclass_of($component, HasAttributesInterface::class, true)) {
            foreach ($component::getAttributeNames() as $attribute) {
                if (!$this->isValidComponentPropertyName($attribute)) {
                    throw InvalidComponentPropertyNameException::becausePropertyNameIsInvalid($name, $attribute);
                }

                if ($this->isBuiltinComponentPropertyName($attribute)) {
                    throw BuiltinComponentPropertyNameException::becausePropertyNameIsBuiltin($name, $attribute);
                }
            }
        }

        if (\is_subclass_of($component, HasPropertiesInterface::class, true)) {
            foreach ($component::getPropertyNames() as $property) {
                if (!$this->isValidComponentPropertyName($property)) {
                    throw InvalidComponentPropertyNameException::becausePropertyNameIsInvalid($name, $property);
                }

                if ($this->isBuiltinComponentPropertyName($property)) {
                    throw BuiltinComponentPropertyNameException::becausePropertyNameIsBuiltin($name, $property);
                }
            }
        }

        $this->components[$name] = $component;

        $this->scripts->add($this->builder->build(
            tagName: $name,
            className: $this->getClassName($component),
            component: $component,
        ));
    }

    /**
     * @param non-empty-string $name
     * @param class-string $component
     *
     * @return non-empty-lowercase-string
     */
    private function getTagName(string $name, string $component): string
    {
        $lower = \strtolower($name);

        if ($this->has($lower)) {
            throw ComponentAlreadyDefinedException::becauseComponentAlreadyDefined($name, $component);
        }

        if (!$this->isValidComponentTagName($lower)) {
            throw InvalidComponentNameException::becauseComponentNameIsInvalid($name);
        }

        return $lower;
    }

    /**
     * @param non-empty-string $component
     *
     * @return non-empty-string
     */
    private function getClassName(string $component): string
    {
        if (\is_subclass_of($component, HasClassNameInterface::class, true)) {
            return $component::getClassName();
        }

        return $this->classNamePrefix . \str_replace('\\', '_', $component);
    }

    public function has(string $name): bool
    {
        return isset($this->components[\strtolower($name)]);
    }

    public function getIterator(): \Traversable
    {
        /** @var \ArrayIterator<non-empty-lowercase-string, class-string> */
        return new \ArrayIterator($this->components);
    }

    public function count(): int
    {
        return \count($this->components);
    }
}
