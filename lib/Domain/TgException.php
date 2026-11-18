<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * Business error with a stable machine readable code. The controllers translate the code into an HTTP status and a
 * localized message.
 */
class TgException extends \RuntimeException {
	/** @param list<string> $fields */
	public function __construct(
		private string $errorCode,
		private array $fields = [],
		private array $extra = [],
	) {
		parent::__construct($errorCode);
	}

	public function errorCode(): string {
		return $this->errorCode;
	}

	/** @return list<string> */
	public function fields(): array {
		return $this->fields;
	}

	public function extra(): array {
		return $this->extra;
	}
}
