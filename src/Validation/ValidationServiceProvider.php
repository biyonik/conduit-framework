<?php

declare(strict_types=1);

namespace Conduit\Validation;

use Conduit\Core\ServiceProvider;
use Conduit\Validation\Contracts\ValidationSchemaInterface;

/**
 * ValidationServiceProvider
 * 
 * Registers validation services in the container.
 * 
 * @package Conduit\Validation
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider
     * 
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(ValidationSchemaInterface::class, ValidationSchema::class);

        // Register validation types as singletons for reuse
        $this->registerValidationTypes();
    }

    /**
     * Bootstrap the service provider
     * 
     * @return void
     */
    public function boot(): void
    {
        // Custom validation rules can be registered here
    }

    /**
     * Register validation type factories
     * 
     * @return void
     */
    protected function registerValidationTypes(): void
    {
        // Helper methods can be added to create validation types easily
        // For now, types are instantiated directly when needed
    }
}
