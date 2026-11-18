<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** RFC 4180 CSV with spreadsheet formula injection protection for text cells. */
final class Csv {
	public static function cell(mixed $v): string {
		if (is_int($v) || is_float($v)) {
			return (string)$v;
		}
		$s = (string)($v ?? '');
		$s = preg_replace('/[\r\n\t]+/', ' ', $s) ?? '';
		if (preg_match('/^[=+\-@]/', $s)) {
			$s = "'" . $s;
		}
		return preg_match('/[",]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
	}

	/** @param list<string> $head @param list<list<mixed>> $rows */
	public static function build(array $head, array $rows, bool $bom = false): string {
		$lines = [implode(',', array_map([self::class, 'cell'], $head))];
		foreach ($rows as $r) {
			$lines[] = implode(',', array_map([self::class, 'cell'], $r));
		}
		return ($bom ? "\xEF\xBB\xBF" : '') . implode("\r\n", $lines) . "\r\n";
	}

	/** @return list<array<string,string>> */
	public static function parse(string $text): array {
		$rows = [];
		$row = [];
		$cell = '';
		$q = false;
		$s = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? '';
		$n = strlen($s);
		for ($i = 0; $i < $n; $i++) {
			$c = $s[$i];
			if ($q) {
				if ($c === '"') {
					if (($s[$i + 1] ?? '') === '"') {
						$cell .= '"';
						$i++;
					} else {
						$q = false;
					}
				} else {
					$cell .= $c;
				}
			} elseif ($c === '"') {
				$q = true;
			} elseif ($c === ',') {
				$row[] = $cell;
				$cell = '';
			} elseif ($c === "\n") {
				$row[] = $cell;
				$rows[] = $row;
				$row = [];
				$cell = '';
			} elseif ($c !== "\r") {
				$cell .= $c;
			}
		}
		if ($cell !== '' || $row) {
			$row[] = $cell;
			$rows[] = $row;
		}
		$head = array_map('trim', array_shift($rows) ?? []);
		$out = [];
		foreach ($rows as $r) {
			if (count($r) <= 1) {
				continue;
			}
			$o = [];
			foreach ($head as $i => $h) {
				$o[$h] = $r[$i] ?? '';
			}
			$out[] = $o;
		}
		return $out;
	}
}
