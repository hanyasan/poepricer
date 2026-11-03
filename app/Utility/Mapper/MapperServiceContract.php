<?php

namespace App\Utility\Mapper;

use App\Exceptions\Mapping\MapInvalidSourceException;
use App\Exceptions\Mapping\MappingErrorException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface MapperServiceContract
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
    public function mapFromJson(string $dtoClass, string $data): object;

    /**
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T[]
     *
     * @throws MappingErrorException
     * @throws MapInvalidSourceException
     */
    public function mapArrayFromJson(string $dtoClass, string $data): array;

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
    public function mapEloquentCollection(string $dtoClass, Collection $data): array;

    /**
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T
     *
     * @throws MappingErrorException
     */
    public function mapEloquentModel(string $dtoClass, Model $model): object;
}
