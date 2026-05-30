<?php
/**
 * Migrate votes from CouchDB to MariaDB.
 *
 * @file
 * @ingroup Maintenance
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class MigrateWEvotes extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Migrate WEvotes from CouchDB to MariaDB";
	}

	public function execute() {
		global $wgWEvotesHost, $wgWEvotesPort, $wgWEvotesDB;
		global $wgWEvotesUser, $wgWEvotesPasswd;

		if ( !$wgWEvotesHost ) {
			$this->error( "CouchDB configuration is missing. Make sure \$wgWEvotesHost, etc. are set in LocalSettings.php/CommonSettings.php.", true );
		}

		$this->output( "Fetching votes from CouchDB at $wgWEvotesHost:$wgWEvotesPort...\n" );

		$url = "http://" . rawurlencode($wgWEvotesUser) . ":" . rawurlencode($wgWEvotesPasswd) . "@" . $wgWEvotesHost . ":" . $wgWEvotesPort . "/" . $wgWEvotesDB . "/_all_docs?include_docs=true";

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $response === false || $httpCode !== 200 ) {
			$this->error( "Failed to retrieve data from CouchDB. HTTP Status: $httpCode. URL: $url", true );
		}

		$data = json_decode( $response, true );
		if ( !isset( $data['rows'] ) ) {
			$this->error( "Invalid response format from CouchDB.", true );
		}

		$rows = $data['rows'];
		$total = count( $rows );
		$this->output( "Found $total documents in CouchDB. Starting migration to MariaDB...\n" );

		$dbw = wfGetDB( DB_MASTER );
		$inserted = 0;
		$updated = 0;
		$skipped = 0;

		foreach ( $rows as $row ) {
			if ( !isset( $row['doc'] ) ) {
				$skipped++;
				continue;
			}
			$doc = $row['doc'];

			// Skip design documents
			if ( isset( $doc['_id'] ) && strpos( $doc['_id'], '_design/' ) === 0 ) {
				$skipped++;
				continue;
			}

			// Validate essential fields
			if ( !isset( $doc['pid'] ) || !isset( $doc['vid'] ) || !isset( $doc['user'] ) || !isset( $doc['vote'] ) ) {
				$skipped++;
				continue;
			}

			$pid = $doc['pid'];
			$vid = $doc['vid'];
			$user = $doc['user'];
			$vote = intval( $doc['vote'] );
			$page = isset( $doc['page'] ) ? intval( $doc['page'] ) : 0;
			$timestamp = isset( $doc['timestamp'] ) ? $doc['timestamp'] : date( 'Y-m-d\TH:i:s.000\Z' );

			// Check if already exists in MariaDB
			$exists = $dbw->selectField(
				'wevotes',
				'1',
				array(
					'wev_pid' => $pid,
					'wev_vid' => $vid,
					'wev_user_name' => $user
				),
				__METHOD__
			);

			if ( $exists ) {
				// Update
				$dbw->update(
					'wevotes',
					array(
						'wev_vote' => $vote,
						'wev_page' => $page,
						'wev_timestamp' => $timestamp
					),
					array(
						'wev_pid' => $pid,
						'wev_vid' => $vid,
						'wev_user_name' => $user
					),
					__METHOD__
				);
				$updated++;
			} else {
				// Insert
				$dbw->insert(
					'wevotes',
					array(
						'wev_pid' => $pid,
						'wev_vid' => $vid,
						'wev_user_name' => $user,
						'wev_vote' => $vote,
						'wev_page' => $page,
						'wev_timestamp' => $timestamp
					),
					__METHOD__
				);
				$inserted++;
			}
		}

		$this->output( "Migration completed!\n" );
		$this->output( "Inserted: $inserted\n" );
		$this->output( "Updated: $updated\n" );
		$this->output( "Skipped/Design Docs: $skipped\n" );
	}
}

$maintClass = 'MigrateWEvotes';
require_once RUN_MAINTENANCE_IF_MAIN;

