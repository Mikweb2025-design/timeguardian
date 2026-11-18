<?php

declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TimeGuardian\Domain;

/** All texts of the domain in de / en / it. Placeholders: {h} hours, {p} minutes, {n} {name} {day} {kind} {from} {to}. */
final class Messages {
	public const LANGS = ['de', 'en', 'it'];

	/** maps any locale string to one of the three supported languages (unknown/empty: de, other known locales: en) */
	public static function lang(?string $l): string {
		$l = strtolower(trim((string)$l));
		if ($l === '') {
			return 'de';
		}
		$p = substr($l, 0, 2);
		return in_array($p, self::LANGS, true) ? $p : 'en';
	}

	public const RULE = [
		'de' => [
			'DAY10' => 'Mehr als 10 Stunden gearbeitet ({h}) · § 3 ArbZG',
			'DAY8' => 'Über 8 Stunden ({h}): braucht Ausgleich (Ø 8 h in 6 Monaten / 24 Wochen)',
			'BREAK30' => 'Pause zu kurz: {p} min, nötig sind 30 min bei mehr als 6 h · § 4 ArbZG',
			'BREAK45' => 'Pause zu kurz: {p} min, nötig sind 45 min bei mehr als 9 h · § 4 ArbZG',
			'REST11' => 'Ruhezeit nur {h} (nötig: 11 h) · § 5 ArbZG',
			'SUN' => 'Sonntagsarbeit ({h}): nur mit Ausnahme · § 9 ArbZG',
			'WEEK48' => 'Woche mit {h}: über 48 h (6 × 8 h)',
			'ABSENT' => 'Für diesen Tag ist eine Abwesenheit eingetragen',
		],
		'en' => [
			'DAY10' => 'More than 10 hours worked ({h}) · § 3 ArbZG',
			'DAY8' => 'Over 8 hours ({h}): needs compensation (8 h average in 6 months / 24 weeks)',
			'BREAK30' => 'Break too short: {p} min, 30 min needed above 6 h · § 4 ArbZG',
			'BREAK45' => 'Break too short: {p} min, 45 min needed above 9 h · § 4 ArbZG',
			'REST11' => 'Rest only {h} (11 h needed) · § 5 ArbZG',
			'SUN' => 'Sunday work ({h}): only with an exception · § 9 ArbZG',
			'WEEK48' => 'Week with {h}: above 48 h (6 × 8 h)',
			'ABSENT' => 'An absence is registered for this day',
		],
		'it' => [
			'DAY10' => 'Più di 10 ore lavorate ({h}) · § 3 ArbZG',
			'DAY8' => 'Oltre 8 ore ({h}): serve compensazione (media 8 h in 6 mesi / 24 settimane)',
			'BREAK30' => 'Pausa troppo corta: {p} min, ne servono 30 oltre 6 h · § 4 ArbZG',
			'BREAK45' => 'Pausa troppo corta: {p} min, ne servono 45 oltre 9 h · § 4 ArbZG',
			'REST11' => 'Riposo di soli {h} (servono 11 h) · § 5 ArbZG',
			'SUN' => 'Lavoro domenicale ({h}): solo con eccezione · § 9 ArbZG',
			'WEEK48' => 'Settimana con {h}: oltre 48 h (6 × 8 h)',
			'ABSENT' => 'Per questo giorno è registrata un\'assenza',
		],
	];

	/** error code => text */
	public const ERR = [
		'de' => [
			'unauthorized' => 'Nicht angemeldet.',
			'forbidden' => 'Dafür fehlt die Berechtigung.',
			'not_in_team' => 'Du gehörst nicht zur Zeiterfassungs-Gruppe.',
			'not_found' => 'Nicht gefunden.',
			'validation' => 'Eingabe ungültig.',
			'invalid_code' => 'Der QR-Code ist abgelaufen oder ungültig. Bitte den aktuellen Code am Kiosk scannen.',
			'code_required' => 'Zum Einstempeln muss der QR-Code am Kiosk gescannt werden.',
			'invalid_transition' => 'Diese Aktion passt nicht zum aktuellen Status.',
			'too_fast' => 'Zu schnell: zwischen zwei Stempelungen müssen mindestens 2 Minuten liegen.',
			'rate_limited' => 'Zu viele Anfragen. Bitte später noch einmal.',
			'locked' => 'Älter als 7 Tage: bitte einen Korrekturantrag stellen.',
			'overlap' => 'Für diese Zeit gibt es schon eine Buchung.',
			'already_decided' => 'Darüber wurde schon entschieden.',
			'token_invalid' => 'Der Kopplungs-Code ist ungültig.',
			'token_expired' => 'Der Kopplungs-Code ist abgelaufen. Bitte in der App einen neuen erzeugen.',
			'token_used' => 'Der Kopplungs-Code wurde schon benutzt.',
			'csrf' => 'Sitzung abgelaufen. Bitte die Seite neu laden.',
			'disabled' => 'Diese Funktion ist abgeschaltet.',
			'server_error' => 'Interner Fehler.',
			'day' => 'Datum ungültig.', 'day_future' => 'Zeiten in der Zukunft kann man nicht buchen.', 'day_old' => 'Zu weit zurück: bitte Korrekturantrag oder Chef fragen.',
			'start' => 'Beginn ungültig.', 'end' => 'Ende ungültig.', 'order' => 'Das Ende muss nach dem Beginn liegen.', 'future' => 'Das Ende liegt in der Zukunft.',
			'pause' => 'Pause: ganze Minuten, 0 bis 240.', 'pause_long' => 'Die Pause ist länger als der Arbeitsblock.', 'project' => 'Projekt unbekannt.',
			'kind' => 'Art unbekannt.', 'date' => 'Datum ungültig.', 'range' => 'Zeitraum außerhalb des erlaubten Bereichs.', 'long' => 'Zeitraum zu lang.',
			'reason' => 'Bitte eine Begründung angeben (3 bis 500 Zeichen).', 'entryId' => 'Buchung unbekannt.', 'mode' => 'Modus ungültig.', 'lang' => 'Sprache ungültig.',
			'weeklyMin' => 'Wochenstunden ungültig.', 'daysMask' => 'Arbeitstage ungültig.', 'decision' => 'Entscheidung ungültig.',
		],
		'en' => [
			'unauthorized' => 'Not signed in.',
			'forbidden' => 'You are not allowed to do that.',
			'not_in_team' => 'You are not a member of the time tracking group.',
			'not_found' => 'Not found.',
			'validation' => 'Invalid input.',
			'invalid_code' => 'The QR code has expired or is invalid. Please scan the current code at the kiosk.',
			'code_required' => 'To clock in, the QR code at the kiosk must be scanned.',
			'invalid_transition' => 'This action does not fit the current status.',
			'too_fast' => 'Too fast: at least 2 minutes are needed between two punches.',
			'rate_limited' => 'Too many requests. Please try again later.',
			'locked' => 'Older than 7 days: please file a correction request.',
			'overlap' => 'There is already an entry for this time.',
			'already_decided' => 'This has already been decided.',
			'token_invalid' => 'The pairing code is invalid.',
			'token_expired' => 'The pairing code has expired. Please create a new one in the app.',
			'token_used' => 'The pairing code has already been used.',
			'csrf' => 'Session expired. Please reload the page.',
			'disabled' => 'This feature is switched off.',
			'server_error' => 'Internal error.',
			'day' => 'Invalid date.', 'day_future' => 'You cannot book time in the future.', 'day_old' => 'Too far back: please file a correction or ask your manager.',
			'start' => 'Invalid start.', 'end' => 'Invalid end.', 'order' => 'The end must be after the start.', 'future' => 'The end is in the future.',
			'pause' => 'Break: whole minutes, 0 to 240.', 'pause_long' => 'The break is longer than the work block.', 'project' => 'Unknown project.',
			'kind' => 'Unknown type.', 'date' => 'Invalid date.', 'range' => 'Period outside the allowed range.', 'long' => 'Period too long.',
			'reason' => 'Please give a reason (3 to 500 characters).', 'entryId' => 'Unknown entry.', 'mode' => 'Invalid mode.', 'lang' => 'Invalid language.',
			'weeklyMin' => 'Invalid weekly hours.', 'daysMask' => 'Invalid working days.', 'decision' => 'Invalid decision.',
		],
		'it' => [
			'unauthorized' => 'Non autenticato.',
			'forbidden' => 'Non hai l\'autorizzazione.',
			'not_in_team' => 'Non fai parte del gruppo di rilevazione presenze.',
			'not_found' => 'Non trovato.',
			'validation' => 'Dati non validi.',
			'invalid_code' => 'Il codice QR è scaduto o non valido. Scansiona il codice attuale al chiosco.',
			'code_required' => 'Per timbrare l\'entrata va scansionato il codice QR al chiosco.',
			'invalid_transition' => 'Questa azione non è compatibile con lo stato attuale.',
			'too_fast' => 'Troppo veloce: servono almeno 2 minuti tra due timbrature.',
			'rate_limited' => 'Troppe richieste. Riprova più tardi.',
			'locked' => 'Più vecchio di 7 giorni: invia una richiesta di correzione.',
			'overlap' => 'Per questo orario esiste già una registrazione.',
			'already_decided' => 'Su questo è già stato deciso.',
			'token_invalid' => 'Il codice di abbinamento non è valido.',
			'token_expired' => 'Il codice di abbinamento è scaduto. Creane uno nuovo nell\'app.',
			'token_used' => 'Il codice di abbinamento è già stato usato.',
			'csrf' => 'Sessione scaduta. Ricarica la pagina.',
			'disabled' => 'Questa funzione è disattivata.',
			'server_error' => 'Errore interno.',
			'day' => 'Data non valida.', 'day_future' => 'Non si possono registrare ore nel futuro.', 'day_old' => 'Troppo indietro: invia una correzione o chiedi al responsabile.',
			'start' => 'Inizio non valido.', 'end' => 'Fine non valida.', 'order' => 'La fine deve essere dopo l\'inizio.', 'future' => 'La fine è nel futuro.',
			'pause' => 'Pausa: minuti interi, da 0 a 240.', 'pause_long' => 'La pausa è più lunga del blocco di lavoro.', 'project' => 'Progetto sconosciuto.',
			'kind' => 'Tipo sconosciuto.', 'date' => 'Data non valida.', 'range' => 'Periodo fuori dall\'intervallo consentito.', 'long' => 'Periodo troppo lungo.',
			'reason' => 'Indica un motivo (da 3 a 500 caratteri).', 'entryId' => 'Registrazione sconosciuta.', 'mode' => 'Modalità non valida.', 'lang' => 'Lingua non valida.',
			'weeklyMin' => 'Ore settimanali non valide.', 'daysMask' => 'Giorni lavorativi non validi.', 'decision' => 'Decisione non valida.',
		],
	];

	public const HOLIDAY = [
		'de' => ['newyear' => 'Neujahr', 'goodfriday' => 'Karfreitag', 'eastersunday' => 'Ostersonntag', 'eastermonday' => 'Ostermontag', 'labourday' => 'Tag der Arbeit',
			'ascension' => 'Christi Himmelfahrt', 'whitsunday' => 'Pfingstsonntag', 'whitmonday' => 'Pfingstmontag', 'unityday' => 'Tag der Deutschen Einheit', 'christmas1' => '1. Weihnachtstag',
			'christmas2' => '2. Weihnachtstag', 'epiphany' => 'Heilige Drei Könige', 'womensday' => 'Internationaler Frauentag', 'corpuschristi' => 'Fronleichnam', 'assumption' => 'Mariä Himmelfahrt',
			'childrensday' => 'Weltkindertag', 'reformation' => 'Reformationstag', 'allsaints' => 'Allerheiligen', 'repentance' => 'Buß- und Bettag'],
		'en' => ['newyear' => 'New Year\'s Day', 'goodfriday' => 'Good Friday', 'eastersunday' => 'Easter Sunday', 'eastermonday' => 'Easter Monday', 'labourday' => 'Labour Day',
			'ascension' => 'Ascension Day', 'whitsunday' => 'Whit Sunday', 'whitmonday' => 'Whit Monday', 'unityday' => 'German Unity Day', 'christmas1' => 'Christmas Day',
			'christmas2' => 'Boxing Day', 'epiphany' => 'Epiphany', 'womensday' => 'International Women\'s Day', 'corpuschristi' => 'Corpus Christi', 'assumption' => 'Assumption Day',
			'childrensday' => 'World Children\'s Day', 'reformation' => 'Reformation Day', 'allsaints' => 'All Saints\' Day', 'repentance' => 'Day of Repentance and Prayer'],
		'it' => ['newyear' => 'Capodanno', 'goodfriday' => 'Venerdì Santo', 'eastersunday' => 'Domenica di Pasqua', 'eastermonday' => 'Lunedì dell\'Angelo', 'labourday' => 'Festa del Lavoro',
			'ascension' => 'Ascensione', 'whitsunday' => 'Pentecoste', 'whitmonday' => 'Lunedì di Pentecoste', 'unityday' => 'Giorno dell\'Unità tedesca', 'christmas1' => 'Natale',
			'christmas2' => 'Santo Stefano', 'epiphany' => 'Epifania', 'womensday' => 'Giornata internazionale della donna', 'corpuschristi' => 'Corpus Domini', 'assumption' => 'Assunzione',
			'childrensday' => 'Giornata mondiale dei bambini', 'reformation' => 'Giorno della Riforma', 'allsaints' => 'Ognissanti', 'repentance' => 'Giorno di penitenza e preghiera'],
	];

	public const KIND = [
		'de' => ['vacation' => 'Urlaub', 'sick' => 'Krank', 'flex' => 'Gleitzeit', 'trip' => 'Dienstreise'],
		'en' => ['vacation' => 'Vacation', 'sick' => 'Sick leave', 'flex' => 'Flex time', 'trip' => 'Business trip'],
		'it' => ['vacation' => 'Ferie', 'sick' => 'Malattia', 'flex' => 'Permesso (banca ore)', 'trip' => 'Trasferta'],
	];

	/** notification subjects: key => [title, message] (placeholders in braces) */
	public const NOTIF = [
		'de' => [
			'live_day10' => ['10-Stunden-Grenze naht', 'Du arbeitest seit {h}. In etwa 30 Minuten ist die 10-Stunden-Grenze erreicht (§ 3 ArbZG).'],
			'live_break' => ['Pause fällig', 'Du arbeitest seit {h} ohne ausreichende Pause. Bitte jetzt Pause machen (§ 4 ArbZG).'],
			'live_forgot' => ['Ausstempeln vergessen?', 'Seit {h} läuft deine Zeiterfassung. Hast du vergessen auszustempeln? Du kannst die Zeit in der App korrigieren.'],
			'violation_user' => ['Regelverstoß erkannt', '{text}'],
			'violation_chef' => ['Regelverstoß im Team', '{name}, {day}: {text}'],
			'absence_request' => ['Neuer Abwesenheitsantrag', '{name}: {kind} vom {from} bis {to}'],
			'absence_approved' => ['Abwesenheit genehmigt', '{kind} vom {from} bis {to} wurde genehmigt.'],
			'absence_rejected' => ['Abwesenheit abgelehnt', '{kind} vom {from} bis {to} wurde abgelehnt.'],
			'correction_request' => ['Neuer Korrekturantrag', '{name}: Korrektur für {day}'],
			'correction_approved' => ['Korrektur genehmigt', 'Deine Korrektur für {day} wurde genehmigt.'],
			'correction_rejected' => ['Korrektur abgelehnt', 'Deine Korrektur für {day} wurde abgelehnt.'],
			'weekly_report' => ['Wochenbericht Arbeitszeit', 'KW {kw}: {verstoss} Verstöße, {hinweis} Hinweise im Team. Gesamt {h}.'],
		],
		'en' => [
			'live_day10' => ['10-hour limit approaching', 'You have been working for {h}. The 10-hour limit (§ 3 ArbZG) is reached in about 30 minutes.'],
			'live_break' => ['Break due', 'You have been working for {h} without a sufficient break. Please take a break now (§ 4 ArbZG).'],
			'live_forgot' => ['Forgot to clock out?', 'Your time tracking has been running for {h}. Did you forget to clock out? You can correct the time in the app.'],
			'violation_user' => ['Rule violation detected', '{text}'],
			'violation_chef' => ['Rule violation in the team', '{name}, {day}: {text}'],
			'absence_request' => ['New absence request', '{name}: {kind} from {from} to {to}'],
			'absence_approved' => ['Absence approved', '{kind} from {from} to {to} was approved.'],
			'absence_rejected' => ['Absence rejected', '{kind} from {from} to {to} was rejected.'],
			'correction_request' => ['New correction request', '{name}: correction for {day}'],
			'correction_approved' => ['Correction approved', 'Your correction for {day} was approved.'],
			'correction_rejected' => ['Correction rejected', 'Your correction for {day} was rejected.'],
			'weekly_report' => ['Weekly working time report', 'CW {kw}: {verstoss} violations, {hinweis} hints in the team. Total {h}.'],
		],
		'it' => [
			'live_day10' => ['Si avvicina il limite di 10 ore', 'Lavori da {h}. Tra circa 30 minuti si raggiunge il limite di 10 ore (§ 3 ArbZG).'],
			'live_break' => ['Pausa dovuta', 'Lavori da {h} senza una pausa sufficiente. Fai una pausa ora (§ 4 ArbZG).'],
			'live_forgot' => ['Hai dimenticato di timbrare l\'uscita?', 'La tua rilevazione è attiva da {h}. Hai dimenticato di timbrare l\'uscita? Puoi correggere l\'orario nell\'app.'],
			'violation_user' => ['Violazione rilevata', '{text}'],
			'violation_chef' => ['Violazione nel team', '{name}, {day}: {text}'],
			'absence_request' => ['Nuova richiesta di assenza', '{name}: {kind} dal {from} al {to}'],
			'absence_approved' => ['Assenza approvata', '{kind} dal {from} al {to} è stata approvata.'],
			'absence_rejected' => ['Assenza rifiutata', '{kind} dal {from} al {to} è stata rifiutata.'],
			'correction_request' => ['Nuova richiesta di correzione', '{name}: correzione per il {day}'],
			'correction_approved' => ['Correzione approvata', 'La tua correzione per il {day} è stata approvata.'],
			'correction_rejected' => ['Correzione rifiutata', 'La tua correzione per il {day} è stata rifiutata.'],
			'weekly_report' => ['Rapporto settimanale', 'Sett. {kw}: {verstoss} violazioni, {hinweis} avvisi nel team. Totale {h}.'],
		],
	];

	/** one-line texts used by integrations (Deck card, Talk message) */
	public const MISC = [
		'de' => ['w_title' => 'Zeit-Wächter', 'w_open' => 'Öffnen', 'w_empty' => 'Noch keine Daten', 'w_week' => 'KW {kw}: {net} von {target}', 'w_balance' => 'Zeitkonto: {bal}', 'w_team' => 'Team: {n} anwesend, {v} Verstöße diese Woche', 'w_pending' => '{n} offene Anträge', 'w_state_working' => 'Arbeitet seit {t}', 'w_state_pause' => 'In Pause', 'w_state_done' => 'Heute fertig', 'w_state_none' => 'Nicht eingestempelt', 'w_today' => 'Heute {net}', 'present' => 'anwesend', 'absent' => 'abwesend', 'state_working' => 'arbeitet', 'state_pause' => 'Pause', 'state_done' => 'fertig', 'state_none' => 'nicht eingestempelt',
			'calendar_name' => 'Arbeitszeit', 'work_block' => 'Arbeitszeit {h} netto', 'deck_title' => '⚠ {name} {day} · {rule}', 'auto' => 'Automatisch von TimeGuardian erstellt. Entscheiden tut ein Mensch.',
			'talk_violation' => 'Regelverstoß: {name}, {day}: {text}'],
		'en' => ['w_title' => 'TimeGuardian', 'w_open' => 'Open', 'w_empty' => 'No data yet', 'w_week' => 'CW {kw}: {net} of {target}', 'w_balance' => 'Time account: {bal}', 'w_team' => 'Team: {n} present, {v} violations this week', 'w_pending' => '{n} open requests', 'w_state_working' => 'Working since {t}', 'w_state_pause' => 'On break', 'w_state_done' => 'Done for today', 'w_state_none' => 'Not clocked in', 'w_today' => 'Today {net}', 'present' => 'present', 'absent' => 'absent', 'state_working' => 'working', 'state_pause' => 'break', 'state_done' => 'done', 'state_none' => 'not clocked in',
			'calendar_name' => 'Working time', 'work_block' => 'Working time {h} net', 'deck_title' => '⚠ {name} {day} · {rule}', 'auto' => 'Created automatically by TimeGuardian. A human decides.',
			'talk_violation' => 'Rule violation: {name}, {day}: {text}'],
		'it' => ['w_title' => 'Guardiano ore', 'w_open' => 'Apri', 'w_empty' => 'Ancora nessun dato', 'w_week' => 'Sett. {kw}: {net} su {target}', 'w_balance' => 'Conto ore: {bal}', 'w_team' => 'Team: {n} presenti, {v} violazioni questa settimana', 'w_pending' => '{n} richieste aperte', 'w_state_working' => 'Al lavoro dalle {t}', 'w_state_pause' => 'In pausa', 'w_state_done' => 'Finito per oggi', 'w_state_none' => 'Non timbrato', 'w_today' => 'Oggi {net}', 'present' => 'presente', 'absent' => 'assente', 'state_working' => 'al lavoro', 'state_pause' => 'pausa', 'state_done' => 'finito', 'state_none' => 'non timbrato',
			'calendar_name' => 'Orario di lavoro', 'work_block' => 'Orario di lavoro {h} nette', 'deck_title' => '⚠ {name} {day} · {rule}', 'auto' => 'Creato automaticamente da TimeGuardian. Decide una persona.',
			'talk_violation' => 'Violazione: {name}, {day}: {text}'],
	];

	/** text of a rule hit */
	public static function rule(string $rule, int $value, string $lang): string {
		$lang = self::lang($lang);
		$t = self::RULE[$lang][$rule] ?? $rule;
		return strtr($t, [
			'{h}' => $rule === 'REST11' ? Dates::fmtHM($value) : Dates::fmtH($value, $lang),
			'{p}' => (string)$value,
		]);
	}

	public static function error(string $code, string $lang): string {
		$lang = self::lang($lang);
		return self::ERR[$lang][$code] ?? self::ERR[$lang]['server_error'];
	}

	public static function holiday(string $key, string $lang): string {
		return self::HOLIDAY[self::lang($lang)][$key] ?? $key;
	}

	public static function kind(string $kind, string $lang): string {
		return self::KIND[self::lang($lang)][$kind] ?? $kind;
	}

	/** @param array<string,string|int> $params @return array{0:string,1:string} title, message */
	public static function notif(string $key, array $params, string $lang): array {
		$lang = self::lang($lang);
		$t = self::NOTIF[$lang][$key] ?? [$key, ''];
		$map = [];
		foreach ($params as $k => $v) {
			$map['{' . $k . '}'] = (string)$v;
		}
		return [strtr($t[0], $map), strtr($t[1], $map)];
	}

	public static function misc(string $key, string $lang, array $params = []): string {
		$t = self::MISC[self::lang($lang)][$key] ?? $key;
		$map = [];
		foreach ($params as $k => $v) {
			$map['{' . $k . '}'] = (string)$v;
		}
		return strtr($t, $map);
	}
}
