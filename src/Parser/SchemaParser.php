<?php

declare(strict_types=1);

namespace Phore\Schema\Parser;

use Closure;
use Phore\Schema\Schema\ClassSchema;
use Phore\Schema\Schema\FunctionParameterSchema;
use Phore\Schema\Schema\FunctionReturnSchema;
use Phore\Schema\Schema\FunctionSchema;
use Phore\Schema\Schema\PropertySchema;
use Phore\Schema\Schema\Type\ArraySchemaType;
use Phore\Schema\Schema\Type\PrimitiveSchemaType;
use Phore\Schema\Schema\Type\SchemaType;
use Phore\Schema\Schema\Type\UnionSchemaType;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class SchemaParser
{
    public function __construct(
        private readonly DocBlockParser $docBlockParser = new DocBlockParser(),
        private readonly TypeParser $typeParser = new TypeParser(),
    ) {
    }

    /**
     * @param class-string|object $class
     * @throws ReflectionException
     */
    public function parseClass(string|object $class, bool $publicOnly = true): ClassSchema
    {
        $reflectionClass = new ReflectionClass($class);
        $classDocBlock = $this->docBlockParser->parse($reflectionClass->getDocComment());
        $promotedParameters = $this->collectPromotedParameters($reflectionClass);
        $properties = [];

        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($publicOnly && !$reflectionProperty->isPublic()) {
                continue;
            }

            $properties[] = $this->parseProperty(
                $reflectionProperty,
                $reflectionClass->getName(),
                $promotedParameters[$reflectionProperty->getName()] ?? null,
            );
        }

        return new ClassSchema(
            className: $reflectionClass->getName(),
            shortName: $reflectionClass->getShortName(),
            description: $classDocBlock->description,
            properties: $properties,
            tags: $classDocBlock->tags,
        );
    }

    /**
     * @throws ReflectionException
     */
    public function parseFunction(string|Closure $function): FunctionSchema
    {
        return $this->parseFunctionReflection(new ReflectionFunction($function));
    }

    /**
     * @param class-string|object $class
     * @throws ReflectionException
     */
    public function parseMethod(string|object $class, string $method): FunctionSchema
    {
        return $this->parseFunctionReflection(new ReflectionMethod($class, $method));
    }

    /**
     * @throws ReflectionException
     */
    public function parseCallable(callable $callable): FunctionSchema
    {
        if (is_array($callable) && count($callable) === 2) {
            return $this->parseMethod($callable[0], (string)$callable[1]);
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable, 2);
            return $this->parseMethod($class, $method);
        }

        if (is_object($callable) && !$callable instanceof Closure) {
            return $this->parseMethod($callable, '__invoke');
        }

        return $this->parseFunction($callable instanceof Closure ? $callable : (string)$callable);
    }

    private function parseFunctionReflection(ReflectionFunctionAbstract $function): FunctionSchema
    {
        $docBlock = $this->docBlockParser->parse($function->getDocComment());
        $contextClass = $function instanceof ReflectionMethod ? $function->getDeclaringClass()->getName() : '';
        $parameters = [];
        $docParams = $this->parseParamTags($docBlock->tags['param'] ?? []);

        foreach ($function->getParameters() as $parameter) {
            $parameters[] = $this->parseFunctionParameter($parameter, $docParams[$parameter->getName()] ?? null, $contextClass);
        }

        return new FunctionSchema(
            name: $function instanceof ReflectionMethod ? $function->getName() : $function->getName(),
            description: $docBlock->description,
            parameters: $parameters,
            return: $this->parseFunctionReturn($function, $docBlock->firstTag('return'), $contextClass),
            tags: $docBlock->tags,
            declaringClass: $function instanceof ReflectionMethod ? $function->getDeclaringClass()->getName() : null,
            isMethod: $function instanceof ReflectionMethod,
        );
    }

    /**
     * @param array{type: ?string, description: string}|null $docParam
     */
    private function parseFunctionParameter(ReflectionParameter $parameter, ?array $docParam, string $contextClass): FunctionParameterSchema
    {
        $nativeTypeString = $parameter->getType()?->__toString();
        $nativeType = $this->typeParser->fromReflectionType($parameter->getType(), $contextClass);
        $type = ($docParam['type'] ?? null) !== null
            ? $this->typeParser->fromPhpDocType((string)$docParam['type'], $contextClass)
            : $nativeType;
        $arrayKind = $this->findArrayKind($type);
        [$hasDefaultValue, $defaultValue] = $this->readParameterDefaultValue($parameter);

        return new FunctionParameterSchema(
            name: $parameter->getName(),
            type: $type,
            description: $docParam['description'] ?? '',
            nativeType: $nativeTypeString,
            docType: $docParam['type'] ?? null,
            allowsNull: $this->allowsNull($type),
            hasDefaultValue: $hasDefaultValue,
            defaultValue: $defaultValue,
            isVariadic: $parameter->isVariadic(),
            isPassedByReference: $parameter->isPassedByReference(),
            arrayKind: $arrayKind,
            isArray: $arrayKind !== null,
            isMap: $arrayKind === ArraySchemaType::KIND_MAP,
        );
    }

    private function parseFunctionReturn(ReflectionFunctionAbstract $function, ?string $returnTag, string $contextClass): FunctionReturnSchema
    {
        $docReturn = $this->parseReturnTag($returnTag);
        $nativeTypeString = $function->getReturnType()?->__toString();
        $nativeType = $this->typeParser->fromReflectionType($function->getReturnType(), $contextClass);
        $type = $docReturn['type'] !== null
            ? $this->typeParser->fromPhpDocType($docReturn['type'], $contextClass)
            : $nativeType;
        $arrayKind = $this->findArrayKind($type);

        return new FunctionReturnSchema(
            type: $type,
            description: $docReturn['description'],
            nativeType: $nativeTypeString,
            docType: $docReturn['type'],
            allowsNull: $this->allowsNull($type),
            isVoid: $nativeTypeString === 'void' || $docReturn['type'] === 'void',
            arrayKind: $arrayKind,
            isArray: $arrayKind !== null,
            isMap: $arrayKind === ArraySchemaType::KIND_MAP,
        );
    }

    private function parseProperty(ReflectionProperty $property, string $contextClass, ?ReflectionParameter $promotedParameter): PropertySchema
    {
        $docBlock = $this->docBlockParser->parse($property->getDocComment());
        $docVar = $this->parseVarTag($docBlock->firstTag('var'));
        $nativeTypeString = $property->getType()?->__toString();
        $nativeType = $this->typeParser->fromReflectionType($property->getType(), $contextClass);
        $type = $docVar['type'] !== null
            ? $this->typeParser->fromPhpDocType($docVar['type'], $contextClass)
            : $nativeType;

        [$hasDefaultValue, $defaultValue] = $this->readDefaultValue($property, $promotedParameter);
        $arrayKind = $this->findArrayKind($type);

        return new PropertySchema(
            name: $property->getName(),
            type: $type,
            description: $docBlock->description !== '' ? $docBlock->description : ($docVar['description'] ?? ''),
            nativeType: $nativeTypeString,
            docType: $docVar['type'],
            allowsNull: $this->allowsNull($type),
            hasDefaultValue: $hasDefaultValue,
            defaultValue: $defaultValue,
            arrayKind: $arrayKind,
            isArray: $arrayKind !== null,
            isMap: $arrayKind === ArraySchemaType::KIND_MAP,
            tags: $docBlock->tags,
        );
    }

    /**
     * @return array<string, ReflectionParameter>
     */
    private function collectPromotedParameters(ReflectionClass $class): array
    {
        $constructor = $class->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isPromoted()) {
                $parameters[$parameter->getName()] = $parameter;
            }
        }

        return $parameters;
    }

    /**
     * @param list<string> $paramTags
     * @return array<string, array{type: ?string, description: string}>
     */
    private function parseParamTags(array $paramTags): array
    {
        $params = [];

        foreach ($paramTags as $paramTag) {
            $parsed = $this->parseTypedTag($paramTag);
            $name = $parsed['name'];
            if ($name === null) {
                continue;
            }

            $params[$name] = [
                'type' => $parsed['type'],
                'description' => $parsed['description'],
            ];
        }

        return $params;
    }

    /**
     * @return array{type: ?string, description: string}
     */
    private function parseReturnTag(?string $returnTag): array
    {
        if ($returnTag === null || trim($returnTag) === '') {
            return ['type' => null, 'description' => ''];
        }

        $returnTag = trim($returnTag);
        $type = $this->readLeadingPhpDocType($returnTag);

        return [
            'type' => $type !== '' ? $type : $returnTag,
            'description' => trim(substr($returnTag, strlen($type))),
        ];
    }

    /**
     * @return array{type: ?string, description: string}
     */
    private function parseVarTag(?string $varTag): array
    {
        $parsed = $this->parseTypedTag($varTag);

        return [
            'type' => $parsed['type'],
            'description' => $parsed['description'],
        ];
    }

    /**
     * @return array{type: ?string, name: ?string, description: string}
     */
    private function parseTypedTag(?string $tag): array
    {
        if ($tag === null || trim($tag) === '') {
            return ['type' => null, 'name' => null, 'description' => ''];
        }

        $tag = trim($tag);
        $type = $this->readLeadingPhpDocType($tag);
        $rest = trim(substr($tag, strlen($type)));
        $name = null;

        if (preg_match('/^\$?(\w+)(?:\s+(.*))?$/', $rest, $matches) === 1) {
            $name = $matches[1];
            $rest = trim($matches[2] ?? '');
        }

        return [
            'type' => $type !== '' ? $type : $tag,
            'name' => $name,
            'description' => $rest,
        ];
    }

    private function readLeadingPhpDocType(string $tag): string
    {
        $type = '';
        $depth = 0;
        $length = strlen($tag);

        for ($i = 0; $i < $length; $i++) {
            $char = $tag[$i];

            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth = max(0, $depth - 1);
            }

            if (ctype_space($char) && $depth === 0) {
                break;
            }

            $type .= $char;
        }

        return $type;
    }

    private function allowsNull(SchemaType $type): bool
    {
        if ($type instanceof PrimitiveSchemaType) {
            return $type->getKind() === PrimitiveSchemaType::NULL || $type->getKind() === PrimitiveSchemaType::MIXED;
        }

        if ($type instanceof UnionSchemaType) {
            foreach ($type->types as $innerType) {
                if ($this->allowsNull($innerType)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function findArrayKind(SchemaType $type): ?string
    {
        if ($type instanceof ArraySchemaType) {
            return $type->arrayKind;
        }

        if ($type instanceof UnionSchemaType) {
            foreach ($type->types as $innerType) {
                $arrayKind = $this->findArrayKind($innerType);
                if ($arrayKind !== null) {
                    return $arrayKind;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function readParameterDefaultValue(ReflectionParameter $parameter): array
    {
        if ($parameter->isDefaultValueAvailable()) {
            return [true, $parameter->getDefaultValue()];
        }

        return [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function readDefaultValue(ReflectionProperty $property, ?ReflectionParameter $promotedParameter): array
    {
        if ($promotedParameter !== null && $promotedParameter->isDefaultValueAvailable()) {
            return [true, $promotedParameter->getDefaultValue()];
        }

        if ($property->hasDefaultValue()) {
            return [true, $property->getDefaultValue()];
        }

        return [false, null];
    }
}
