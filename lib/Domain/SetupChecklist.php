<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/**
 * The setup checklist shown to managers, computed live from a few facts. Steps with kind "auto" are required
 * for the setup to count as complete; the step with kind "hint" (app installed) can only be judged by the
 * browser, so the server reports done = null for it.
 */
final class SetupChecklist {
	public const STEPS = ['roles', 'region', 'kiosk', 'phone', 'install', 'punch'];

	/**
	 * @param array{chefs:int,members:int,stateSet:bool,targetSet:bool,kioskSeenAt:?int,devices:int,punches:int} $f
	 * @return array{steps:list<array{id:string,kind:string,done:?bool,params:array<string,mixed>}>,required:int,doneCount:int,complete:bool}
	 */
	public static function build(array $f): array {
		$steps = [
			['id' => 'roles', 'kind' => 'auto', 'done' => $f['chefs'] >= 1 && $f['members'] >= 1, 'params' => ['chefs' => $f['chefs'], 'members' => $f['members']]],
			['id' => 'region', 'kind' => 'auto', 'done' => $f['stateSet'] && $f['targetSet'], 'params' => []],
			['id' => 'kiosk', 'kind' => 'auto', 'done' => $f['kioskSeenAt'] !== null, 'params' => ['seenAt' => $f['kioskSeenAt']]],
			['id' => 'phone', 'kind' => 'auto', 'done' => $f['devices'] >= 1, 'params' => ['devices' => $f['devices']]],
			['id' => 'install', 'kind' => 'hint', 'done' => null, 'params' => []],
			['id' => 'punch', 'kind' => 'auto', 'done' => $f['punches'] >= 1, 'params' => ['punches' => $f['punches']]],
		];
		$required = 0;
		$done = 0;
		foreach ($steps as $s) {
			if ($s['kind'] === 'auto') {
				$required++;
				if ($s['done'] === true) {
					$done++;
				}
			}
		}
		return ['steps' => $steps, 'required' => $required, 'doneCount' => $done, 'complete' => $done === $required];
	}
}
