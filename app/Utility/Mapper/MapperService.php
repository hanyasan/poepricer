<?php

namespace App\Utility\Mapper;

use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\Mapper\Source\Exception\InvalidSource;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\MapperBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final readonly class MapperService implements MapperServiceContract
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T
     *
     * @throws MapInvalidSourceException
     * @throws MappingErrorException
     */
    #[\Override]
    public function mapFromJson(string $dtoClass, string $data): object
    {
        try {
            $res = (new MapperBuilder)->allowSuperfluousKeys()->mapper()
                ->map(
                    $dtoClass,
                    Source::json($data)
                );
        } catch (MappingError) {
            throw new MappingErrorException;
        } catch (InvalidSource) {
            throw new MapInvalidSourceException;
        }

        return $res;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T[]
     *
     * @throws MappingErrorException
     * @throws MapInvalidSourceException
     */
    #[\Override]
    public function mapArrayFromJson(string $dtoClass, string $data): array
    {
        try {
            $res = (new MapperBuilder)->allowSuperfluousKeys()->mapper()
                ->map('array<'.$dtoClass.'>', Source::json($data));
        } catch (MappingError) {
            throw new MappingErrorException;
        } catch (InvalidSource) {
            throw new MapInvalidSourceException;
        }

        /** @var T[] */
        return $res;
    }

    /**
     * @template T of object
     * @template TModel of Model
     *
     * @param  class-string<T>  $dtoClass
     * @param  Collection<int, TModel>  $data
     * @return T[]
     *
     * @throws MappingErrorException
     */
    #[\Override]
    public function mapEloquentCollection(string $dtoClass, Collection $data): array
    {
        try {
            $mapper = (new MapperBuilder)->allowSuperfluousKeys()->mapper();

            $res = $mapper->map('array<'.$dtoClass.'>', Source::array($data->toBase()->toArray()));
        } catch (MappingError) {
            throw new MappingErrorException;
        }

        /** @var T[] */
        return $res;
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T
     *
     * @throws MappingErrorException
     */
    #[\Override]
    public function mapEloquentModel(string $dtoClass, Model $model): object
    {
        try {
            $mapper = (new MapperBuilder)->allowSuperfluousKeys()->mapper();

            $res = $mapper->map($dtoClass, Source::array($model->toArray()));
        } catch (MappingError) {
            throw new MappingErrorException;
        }

        return $res;
    }
}
