<?php
# ex: tabstop=8 shiftwidth=8 noexpandtab
/**
* @package MediaWiki
* @subpackage WEvotes
* @author Jim Tittsler <jimt@oer.me>
* @licence GPL2
*/

define( 'WEVOTES_VERSION', '2.0.0' );

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
}

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'WEvotes',
	'version'        =>  WEVOTES_VERSION,
	'url'            => 'http://WikiEducator.org/Extension:WEvotes',
	'author'         => '[http://WikiEducator.org/User:JimTittsler Jim Tittsler]',
	'description'    => 'add API calls for voting on scenario planning items in a page',
);

$wgAPIModules['wevotes'] = 'APIWEvotes';
$wgHooks['LoadExtensionSchemaUpdates'][] = 'WEvotesHooks::onLoadExtensionSchemaUpdates';

$wgResourceModules['ext.WEvotes'] = array(
	'scripts'       => array( 'WEvotes.js' ),
	'dependencies'  => array( 'mediawiki.api', 'mediawiki.api.edit' ),
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'WEvotes',
);

$wgHooks['ParserAfterTidy'][] = 'fnWEvotesParserAfterTidy';

function fnWEvotesParserAfterTidy( &$parser, &$text ) {
	if ( strpos( $text, 'wevote' ) === false ) {
		return true;
	}
	$parser->getOutput()->addModules( 'ext.WEvotes' );
	return true;
}

class WEvotesHooks {
	/**
	 * Register WEvotes database schema updates.
	 *
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater = null ) {
		if ( $updater === null ) {
			return true;
		}
		$dbType = $updater->getDB()->getType();
		if ( $dbType === 'mysql' || $dbType === 'sqlite' ) {
			$sql = dirname( __FILE__ ) . '/wevotes.sql';
			$updater->addExtensionTable( 'wevotes', $sql );
		}
		return true;
	}
}

class APIWEvotes extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent :: __construct( $query, $moduleName, 'vo' );
	}

	public function execute() {
		global $wgUser;
		$params = $this->extractRequestParams();
		$mode = isset( $params['mode'] ) ? $params['mode'] : 'vote';

		if ( !isset( $params['pid'] ) ) {
			$this->dieUsage( 'pid argument not supplied', 'missingpid' );
		}
		$pid = preg_replace( '/[^-_.a-z0-9]/i', '', $params['pid'] );
		if ( ( $pid <> $params['pid'] ) || ( strlen( $pid ) == 0 ) ) {
			$this->dieUsage( 'invalid pid argument', 'invalidpid' );
		}

		if ( $mode === 'get' ) {
			$dbr = wfGetDB( DB_SLAVE );

			// Fetch totals grouped by vid
			$res = $dbr->select(
				'wevotes',
				array( 'wev_vid', 'SUM(wev_vote) AS vote_sum' ),
				array( 'wev_pid' => $pid ),
				__METHOD__,
				array( 'GROUP BY' => 'wev_vid' )
			);
			$totals = array();
			foreach ( $res as $row ) {
				$totals[$row->wev_vid] = intval( $row->vote_sum );
			}

			// Fetch current user's votes
			$myVotes = array();
			if ( $wgUser->isLoggedIn() ) {
				$resMy = $dbr->select(
					'wevotes',
					array( 'wev_vid', 'wev_vote' ),
					array(
						'wev_pid' => $pid,
						'wev_user_name' => $wgUser->getName(),
					),
					__METHOD__
				);
				foreach ( $resMy as $row ) {
					$myVotes[$row->wev_vid] = intval( $row->wev_vote );
				}
			}

			$result = $this->getResult();
			$data = array(
				'totals' => $totals,
				'myvotes' => $myVotes,
			);
			$result->addValue( null, $this->getModuleName(), $data );
			return;
		}

		// Otherwise: mode === 'vote' (write vote)
		$user = $wgUser->getId();
		if ( $user <= 0 ) {
			$this->dieUsage( 'must be logged in to vote', 'notloggedin' );
		}

		if ( !isset( $params['vid'] ) ) {
			$this->dieUsage( 'vid argument not supplied', 'missingvid' );
		}
		$vid = preg_replace( '/[^-_.a-z0-9]/i', '', $params['vid'] );
		if ( ( $vid <> $params['vid'] ) || ( strlen( $vid ) == 0 ) ) {
			$this->dieUsage( 'invalid vid argument', 'invalidvid' );
		}

		if ( !isset( $params['vote'] ) ) {
			$this->dieUsage( 'vote argument not supplied', 'missingvote' );
		}
		if ( !in_array( intval( $params['vote'] ), array( -1, 0, 1 ) ) ) {
			$this->dieUsage( 'bad vote value', 'badvote' );
		}

		if ( !isset( $params['page'] ) ) {
			$this->dieUsage( 'page argument not supplied', 'missingpage' );
		}

		list( $usec, $ts ) = explode( ' ', microtime() );
		$tstamp = date( 'Y-m-d\TH:i:s.000\Z', $ts );

		$dbw = wfGetDB( DB_MASTER );
		$userName = $wgUser->getName();

		$row = $dbw->selectRow(
			'wevotes',
			array( 'wev_vote' ),
			array(
				'wev_pid' => $pid,
				'wev_vid' => $vid,
				'wev_user_name' => $userName,
			),
			__METHOD__
		);

		if ( $row ) {
			$dbw->update(
				'wevotes',
				array(
					'wev_vote' => intval( $params['vote'] ),
					'wev_timestamp' => $tstamp,
					'wev_page' => intval( $params['page'] ),
				),
				array(
					'wev_pid' => $pid,
					'wev_vid' => $vid,
					'wev_user_name' => $userName,
				),
				__METHOD__
			);
		} else {
			$dbw->insert(
				'wevotes',
				array(
					'wev_pid' => $pid,
					'wev_vid' => $vid,
					'wev_user_name' => $userName,
					'wev_vote' => intval( $params['vote'] ),
					'wev_page' => intval( $params['page'] ),
					'wev_timestamp' => $tstamp,
				),
				__METHOD__
			);
		}

		$result = $this->getResult();
		$result->addValue( 'vote', $this->getModuleName(), true );
	}

	public function getAllowedParams() {
		return array (
			'pid' => null,
			'vid' => null,
			'vote' => null,
			'page' => null,
			'mode' => array(
				ApiBase::PARAM_TYPE => array( 'vote', 'get' ),
				ApiBase::PARAM_DFLT => 'vote',
			),
		);
	}

	public function getParamDescription() {
		return array (
			'pid' => 'voting group id',
			'vid' => 'vote item id',
			'vote' => 'vote value for this item',
			'page' => 'page the voting group appears on',
			'mode' => 'operation mode: vote (cast a vote) or get (fetch totals and user votes)',
		);
	}

	public function getDescription() {
		return 'record a vote or retrieve voting state';
	}

	protected function getExamples() {
		return array (
			'api.php?action=wevotes&vopid=1&vovid=0&vovote=-1&vopage=150999',
			'api.php?action=wevotes&vopid=1&vomode=get',
		);
	}

}
