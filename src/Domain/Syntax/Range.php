<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Domain\Syntax;

use Webmozart\Assert\Assert;

class Range implements SyntaxInterface
{
    public const RELATIONS = ['gt', 'gte', 'lt', 'lte'];

    private string $field;

    private array $definitions;

    private ?float $boost;

    private ?bool $date;

    public function __construct(string $field, array $definitions, ?float $boost = 1.0, ?bool $date = false)
    {
        $this->field = $field;
        $this->definitions = $definitions;
        $this->boost = $boost;
        $this->date = $date;
        $this->validateDefinitions($definitions);
    }

    public function build(): array
    {
        return ['range' => [
            $this->field => array_merge($this->definitions, ['boost' => $this->boost]),
        ]];
    }

    private function validateDefinitions(array $definitions): void
    {
        foreach ($definitions as $key => $value) {
            Assert::inArray($key, self::RELATIONS);
            Assert::notNull($value);
            
            if (!$this->date) {
                Assert::numeric($value);
            }
        }
    }
}
