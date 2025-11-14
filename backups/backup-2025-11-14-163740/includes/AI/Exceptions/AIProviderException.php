<?php

class AIProviderException extends RuntimeException
{
    public const CODE_RATE_LIMIT = 'rate_limit';
    public const CODE_AUTH = 'auth';
    public const CODE_SERVICE = 'service';
    public const CODE_VALIDATION = 'validation';
    public const CODE_INTERNAL = 'internal';

    private string $category;
    private ?array $context;

    public function __construct(string $message, string $category = self::CODE_INTERNAL, ?array $context = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->category = $category;
        $this->context = $context;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}


