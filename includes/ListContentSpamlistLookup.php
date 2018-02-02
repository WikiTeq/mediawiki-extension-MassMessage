<?php

namespace MediaWiki\MassMessage;

use Title;
use Revision;

class ListContentSpamlistLookup extends SpamlistLookup {

	/**
	 * @var Title
	 */
	protected $spamlist;

	public function __construct( Title $spamlist ) {
		$this->spamlist = $spamlist;
	}

	/**
	 * Get an array of targets from a page with the MassMessageListContent model
	 * @return array
	 */
	public function fetchTargets() {
		global $wgCanonicalServer;

		$targets = Revision::newFromTitle( $this->spamlist )->getContent()->getValidTargets();
		foreach ( $targets as &$target ) {
			if ( array_key_exists( 'site', $target ) ) {
				$target['wiki'] = DatabaseLookup::getDBName( $target['site'] );
			} else {
				$target['site'] = UrlHelper::getBaseUrl( $wgCanonicalServer );
				$target['wiki'] = wfWikiId();
			}
		}
		return $targets;
	}
}