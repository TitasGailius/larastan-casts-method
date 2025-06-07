<?php

namespace TitasGailius\LarastanExtended\Properties;

use Illuminate\Database\Eloquent\Model;
use Larastan\Larastan\Properties\ModelCastHelper as LarastanModelCastHelper;
use Larastan\Larastan\Properties\ModelProperty;
use Larastan\Larastan\Properties\ModelPropertyHelper;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;
use ReflectionException;
use ReflectionMethod;

class ModelCastsExtension implements PropertiesClassReflectionExtension
{
    /**
     * Cached model casts.
     *
     * @var array<class-string, array<string, string>>
     */
    protected array $casts = [];

    /**
     * Instantiate a new helper instance.
     *
     * @param  string[]  $ignoredModels
     */
    public function __construct(
        protected ModelPropertyHelper $modelPropertyHelper,
        protected LarastanModelCastHelper $modelCastHelper,
        protected array $ignoredModels,
    ) {}

    /**
     * Determine if the property exists.
     */
    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        foreach ($this->ignoredModels as $ignoredModel) {
            if ($classReflection->is($ignoredModel)) {
                return false;
            }
        }

        return $this->modelPropertyHelper->hasDatabaseProperty($classReflection, $propertyName)
            && isset($this->getModelCasts($classReflection)[$propertyName]);
    }

    /**
     * Get casted property.
     */
    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $cast = $this->getModelCasts($classReflection)[$propertyName];

        $original = $this->modelPropertyHelper->getDatabaseProperty($classReflection, $propertyName);

        $readableType = $this->modelCastHelper->getReadableType($cast, $original->getReadableType());
        $writableType = $this->modelCastHelper->getWriteableType($cast, $original->getWritableType());

        if ($this->isNullable($original->getWritableType())) {
            $readableType = TypeCombinator::addNull($readableType);
            $writableType = TypeCombinator::addNull($writableType);
        }

        return new ModelProperty($classReflection, $readableType, $writableType);
    }

    /**
     * Get model casts.
     *
     * @return array<string, string>
     */
    protected function getModelCasts(ClassReflection $reflection): array
    {
        if (! $reflection->is(Model::class)) {
            return [];
        }

        if ($reflection->isAbstract()) {
            return [];
        }

        if (isset($this->casts[$reflection->getName()])) {
            return $this->casts[$reflection->getName()];
        }

        try {
            /** @var Model $modelInstance */
            $modelInstance = $reflection->getNativeReflection()->newInstanceWithoutConstructor();
        } catch (ReflectionException) {
            throw new ShouldNotHappenException;
        }

        // @phpstan-ignore-next-line
        (new ReflectionMethod($modelInstance, 'initializeHasAttributes'))->invoke($modelInstance);

        return $this->casts[$reflection->getName()] = $modelInstance->getCasts();
    }

    /**
     * Determine if the given type is nullable.
     */
    protected function isNullable(Type $type): bool
    {
        $types = $type instanceof UnionType
            ? $type->getTypes()
            : [$type];

        foreach ($types as $type) {
            if ($type->isNull()->yes()) {
                return true;
            }
        }

        return false;
    }
}
