<?php
# ex: tabstop=8 shiftwidth=8 noexpandtab
/**
* @package MediaWiki
* @subpackage WEvotes
* @author Jim Tittsler <jim@OERfoundation.org>
* @licence GPL2
*/

define('DEBUG_WEVOTES', true);
define('DEBUG_WEVOTES_FILE', '/tmp/vote.log');

if( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
}

require_once('sag/src/Sag.php');

$wgExtensionCredits['parserhook'][] = array(
	'path'           => __FILE__,
	'name'           => 'WEvotes',
	'version'        => '0.1',
	'url'            => 'http://WikiEducator.org/Extension:WEvotes',
	'author'         => '[http://WikiEducator.org/User:JimTittsler Jim Tittsler]',
        'description'    => 'add API calls for voting on items in a page',
);

$wgAPIModules['wevotes'] = 'APIWEvotes';

class APIWEvotes extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent :: __construct( $query, $moduleName, 'vo' );
	}

	function dlog($s) {
		if (DEBUG_WEVOTES) {
			error_log($s, 3, DEBUG_WEVOTES_FILE);
		}
	}

	public function execute() {
		global $wgUser, $wgServer;
		global $wgWEvotesHost, $wgWEvotesPort;
		global $wgWEvotesDB;
		global $wgWEvotesUser, $wgWEvotesPasswd, $wgWEvotesTags;
		global $wgWEvotesPostLimit;
		$id = NULL;
		$user = $wgUser->getId();
		$params = $this->extractRequestParams();

		if ( $user <= 0 ) {
			$this->dieUsage('must be logged in to vote',
				'notloggedin');
		}
		if (!isset($params['pid'])) {
			$this->dieUsage('pid argument not supplied',
				'missingpid');
		}
		$pid = preg_replace('/[^-_.a-z0-9]/i', '', $params['pid']);
		if (($vid <> $params['pid']) || (strlen($pid) == 0)) {
			$this->dieUsage('invalid pid argument',
				'invalidpid');
		}
		if (!isset($params['vid'])) {
			$this->dieUsage('vid argument not supplied',
				'missingvid');
		}
		$vid = preg_replace('/[^-_.a-z0-9]/i', '', $params['vid']);
		if (($vid <> $params['vid']) || (strlen($vid) == 0)) {
			$this->dieUsage('invalid vid argument',
				'invalidvid');
		}

		if (!isset($params['vote'])) {
			$this->dieUsage('vote argument not supplied',
				'missingvote');
		}
		if (!in_array(intval($params['vote']), array(-1, 0, 1))) {
			$this->dieUsage('bad vote value',
				'badvote');
		}
		if (!isset($params['page'])) {
			$this->dieUsage('page argument not supplied',
				'missingpage');
		}

		list($usec, $ts) = explode(' ', microtime());
		$tstamp = date('Y-m-d\TH:i:s.000\Z', $ts);
		$sag = new Sag($wgWEvotesHost, $wgWEvotesPort);
		$sag->setDatabase($wgWEvotesDB);
		$sag->login($wgWEvotesUser, $wgWEvotesPasswd);
		# see if the user has already voted on this item
		$user = $wgUser->getName();
		$url = "/_design/vote/_view/voted?key=" . rawurlencode("[$pid,$vid,\"$user\"]") . "&include_docs=true";
		$this->dlog("url: $url\n");
		$v = $sag->get($url)->body;
		$this->dlog("fetched:\n");
		$this->dlog(print_r($v, true));
		if (count($v->rows) > 0) {
			$vote = $v->rows[0];
			$this->dlog("old vote:\n");
			$this->dlog(print_r($vote, true));
			$data = $vote->doc;
			$this->dlog("data\n");
			$this->dlog(print_r($data, true));
			$data->vote = intval($params['vote']);
			$data->timestamp = $tstamp;
			$data->page = intval($params['page']);
			$id = $data->_id;
			$this->dlog("new vote for $id\n");
			$this->dlog(print_r($data, true));
			$sag->put($id, $data);
		} else {
			# it is a new vote
			$data = array(
				'pid' => intval($params['pid']),
				'vid' => intval($params['vid']),
				'vote' => intval($params['vote']),
				'page' => intval($params['page']),
				'user' => $wgUser->getName(),
				'timestamp' => $tstamp
			);
			$sag->post($data);
		}
		$result = $this->getResult();
		$result->addValue('vote', $this->getModuleName(), true);
	}

	public function getAllowedParams() {
		return array (
			'pid' => null,
			'vid' => null,
			'vote' => null,
			'page' => null,
		);
	}

	public function getParamDescription() {
		return array (
			'pid' => 'voting group id',
			'vid' => 'vote item id',
			'vote' => 'vote value for this item',
			'page' => 'page the voting group appears on',
		);
	}

	public function getDescription() {
		return 'record a vote for a logged in user';
	}

	protected function getExamples() {
		return array (
			'api.php?action=wevotes&pid=1&vid=0&vote=-1&page=150999',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': 0';
	}
}

