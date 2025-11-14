<?php

class PromptTemplate
{
    private string $name;
    private string $content;

    public function __construct(string $name, string $content)
    {
        $this->name = $name;
        $this->content = $content;
    }

    public function render(array $variables = []): string
    {
        $output = $this->content;

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $output = str_replace($placeholder, (string) $value, $output);
            } else {
                $output = str_replace($placeholder, json_encode($value));
            }
        }

        return $output;
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Prompt template not found: {$path}");
        }

        return new self(basename($path), (string) file_get_contents($path));
    }
}


