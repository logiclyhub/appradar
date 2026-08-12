<?php

namespace AppRadar\Agent\Core\Errors;

final class ErrorIgnoreList
{
    /**
     * @param  array<int, string>  $classNames
     */
    public function __construct(
        private readonly array $classNames,
    ) {
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public static function defaults(): self
    {
        return new self([
            'Illuminate\\Validation\\ValidationException',
            'Illuminate\\Auth\\AuthenticationException',
            'Illuminate\\Auth\\Access\\AuthorizationException',
            'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
            'Symfony\\Component\\HttpKernel\\Exception\\MethodNotAllowedHttpException',
        ]);
    }

    public function matches(\Throwable $throwable): bool
    {
        foreach ($this->classNames as $className) {
            if ($className !== '' && $throwable instanceof $className) {
                return true;
            }
        }

        return false;
    }
}
