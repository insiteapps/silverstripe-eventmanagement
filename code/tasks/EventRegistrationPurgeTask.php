<?php
/**
 * A task to remove unconfirmed event registrations that are older than the
 * cutoff date to free up the places.
 *
 * @package silverstripe-eventmanagement
 */
class EventRegistrationPurgeTask extends BuildTask {

	public function getTitle() {
		return 'Event Registration Purge Task';
	}

	public function getDescription() {
		return 'Cancels unconfirmed and unsubmitted registrations older than '
			.  'the cut-off date to free up the places.';
	}

	public function run($request) {
		$query = new SQLQuery();
		$conn    = DB::getConn();

		$query->select('"EventRegistration"."ID"');
		$query->from('"EventRegistration"');

		$query->innerJoin('CalendarDateTime', '"TimeID" = "DateTime"."ID"', 'DateTime');
		$query->innerJoin('CalendarEvent', '"DateTime"."EventID" = "Event"."ID"', 'Event');
		$query->innerJoin('RegisterableEvent', '"Event"."ID" = "Registerable"."ID"', 'Registerable');

		$query->where('"Registerable"."ConfirmTimeLimit" > 0');
		$query->where('"Status"', 'Unconfirmed');

		$created = $conn->formattedDatetimeClause('"EventRegistration"."Created"', '%U');
		$query->where(sprintf(
			'%s < %s', $created . ' + "Registerable"."ConfirmTimeLimit"', time()
		));

		if ($ids = $query->execute()->column()) {
			$count = count($ids);

			DB::query(sprintf(
				'UPDATE "EventRegistration" SET "Status" = \'Canceled\' WHERE "ID" IN (%s)',
				implode(', ', $ids)
			));
		} else {
			$count = 0;
		}

		echo "$count unconfirmed registrations were canceled.\n";
	}

}