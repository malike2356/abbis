<?php

interface ContextBuilderInterface
{
    /**
     * Identifier that will be used to select this builder.
     */
    public function getKey(): string;

    /**
     * Determine whether the builder can handle the supplied request options.
     */
    public function supports(array $options): bool;

    /**
     * Build one or more context slices.
     *
     * @return ContextSlice[]
     */
    public function build(array $options): array;
}


