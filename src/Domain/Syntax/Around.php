<?php

declare(strict_types=1);

namespace JeroenG\Explorer\Domain\Syntax;

use Webmozart\Assert\Assert;

class Around implements SyntaxInterface {
	private string $field;

	private mixed $value;

	private float $tolerance;

	private bool $percentage;

	private bool $date;

	private ?float $boost;

	public function __construct(string $field, $value, float $tolerance = 5, ?bool $percentage = false, ?bool $date = false, ?float $boost = 1.0) {
		$this->field = $field;
		$this->value = $value;
		$this->tolerance = $tolerance;
		$this->percentage = $percentage;
		$this->date = $date;
		$this->boost = $boost;
	}

	public function build(): array {
		if ($this->date) {
			$days = (int) $this->tolerance;
			$lte = date('Y-m-d H:i:s', strtotime("+{$days} day", strtotime($this->value)));
			$gte = date('Y-m-d H:i:s', strtotime("-{$days} day", strtotime($this->value)));
		} else {
			$modifier = $this->percentage ? $this->value * $this->tolerance : $this->tolerance;
			$lte = $this->value + $modifier;
			$gte = $this->value - $modifier;
		}

		return ['range' => [
			$this->field => ['lte' => $lte, 'gte' => $gte, 'boost' => $this->boost],
		]];
	}
}
