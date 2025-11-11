<?php

declare(strict_types=1);

namespace Conduit\Database\Exceptions;

/**
 * Model Not Found Exception
 *
 * Model::findOrFail() ile model bulunamadığında fırlatılır.
 * HTTP 404 response için kullanılır.
 */
class ModelNotFoundException extends DatabaseException
{
    protected string $model = '';

    public function __construct(string $model, int|string $id)
    {
        $this->model = $model;
        parent::__construct("Model [{$model}] with ID [{$id}] not found.", 404);
    }

    /**
     * Model sınıf adını al
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
