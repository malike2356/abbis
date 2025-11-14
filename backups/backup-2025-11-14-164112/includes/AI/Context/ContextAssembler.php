<?php

require_once __DIR__ . '/ContextBuilderInterface.php';
require_once __DIR__ . '/ContextSlice.php';

class ContextAssembler
{
    /**
     * @var ContextBuilderInterface[]
     */
    private array $builders = [];

    private int $maxTokens;

    public function __construct(int $maxTokens = 3200)
    {
        $this->maxTokens = $maxTokens;
    }

    public function registerBuilder(ContextBuilderInterface $builder): void
    {
        $this->builders[$builder->getKey()] = $builder;
    }

    /**
     * Assemble context slices subject to token budget.
     */
    public function assemble(array $options): array
    {
        $slices = [];

        foreach ($this->builders as $builder) {
            if (!$builder->supports($options)) {
                continue;
            }

            $built = $builder->build($options);
            foreach ($built as $slice) {
                if ($slice instanceof ContextSlice) {
                    $slices[] = $slice;
                }
            }
        }

        if (empty($slices)) {
            return [];
        }

        usort($slices, static function (ContextSlice $a, ContextSlice $b) {
            return $a->priority <=> $b->priority;
        });

        $budget = $this->maxTokens;
        $chosen = [];

        foreach ($slices as $slice) {
            $estimated = max(1, $slice->approxTokens ?: 200);

            if ($budget - $estimated < 0) {
                continue;
            }

            $chosen[] = $slice->toArray();
            $budget -= $estimated;
        }

        return $chosen;
    }
}


