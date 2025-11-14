<?php

class ContextSlice
{
    public string $type;
    public array $payload;
    public int $priority;
    public int $approxTokens;
    public bool $sensitive;

    public function __construct(string $type, array $payload, int $priority = 50, int $approxTokens = 0, bool $sensitive = false)
    {
        $this->type = $type;
        $this->payload = $payload;
        $this->priority = $priority;
        $this->approxTokens = max(0, $approxTokens);
        $this->sensitive = $sensitive;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'payload' => $this->payload,
            'priority' => $this->priority,
            'approx_tokens' => $this->approxTokens,
            'sensitive' => $this->sensitive,
        ];
    }
}


