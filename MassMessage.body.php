<?php

/**
 * Some core functions needed by the ex.
 *
 * @file
 * @author Kunal Mehta
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class MassMessage {

	/**
	 * Function to follow redirects
	 *
	 * @param $title Title
	 * @return Title|null null if the page is an interwiki redirect
	 */
	public static function followRedirect( $title ) {
		if ( !$title->isRedirect() ) {
			return $title;
		}
		$wikipage = WikiPage::factory( $title );

		$target = $wikipage->followRedirect();
		if ( $target instanceof Title ) {
			return $target;
		} else {
			return null; // Interwiki redirect
		}
	}

	/**
	 * Sets up the messenger account for our use if it hasn't been already.
	 * Based on code from AbuseFilter
	 * https://mediawiki.org/wiki/Extension:AbuseFilter
	 *
	 * @return User
	 */
	public static function getMessengerUser() {
		global $wgMassMessageAccountUsername;
		// Function kinda copied from the AbuseFilter
		$user = User::newFromName( $wgMassMessageAccountUsername );
		$user->load();
		if ( $user->getId() && $user->mPassword == '' ) {
			// We've already stolen the account
			return $user;
		}

		if ( !$user->getId() ) {
			$user->addToDatabase();
			$user->saveSettings();

			// Increment site_stats.ss_users
			$ssu = new SiteStatsUpdate( 0, 0, 0, 0, 1 );
			$ssu->doUpdate();
		} else {
			// Someone already created the account, lets take it over.
			$user->setPassword( null );
			$user->setEmail( null );
			$user->saveSettings();
		}

		// Make the user a bot so it doesn't look weird
		$user->addGroup( 'bot' );

		return $user;
	}

	/**
	 * Returns the basic hostname and port using wfParseUrl
	 * @param  string $url URL to parse
	 * @return string
	 */
	public static function getBaseUrl( $url ) {
		$parse = wfParseUrl( $url );
		$site = $parse['host'];
		if ( isset( $parse['port'] ) ) {
			$site .= ':' . $parse['port'];
		}

		return $site;
	}

	/**
	 * Get database name from URL hostname
	 * Requires $wgConf to be set up properly
	 * Tries to read from cache if possible
	 * @param  string $host
	 * @return string
	 */
	public static function getDBName( $host ) {
		global $wgConf, $wgMemc;
		static $mapping = null;
		if ( $mapping === null ) {
			// Don't use wfMemcKey since it splits cache per wiki
			$key = 'massmessage:urltodb';
			$data = $wgMemc->get( $key );
			if ( $data === false ) {
				$dbs = $wgConf->getLocalDatabases();
				$mapping = array();
				foreach ( $dbs as $dbname ) {
					$url = WikiMap::getWiki( $dbname )->getCanonicalServer();
					$parse = wfParseUrl( $url );
					$mapping[$parse['host']] = $dbname;
				}
				$wgMemc->set( $key, $mapping, 60 * 60 * 24 * 7 );
			} else {
				$mapping = $data;
			}
		}
		if ( isset( $mapping[$host] ) ) {
			return $mapping[$host];
		}
		return null; // Couldn't find anything
	}

	/**
	 * Perform various normalization functions on the target data
	 * @param  array $data
	 * @return array
	 */
	public static function normalizeTargets( $data ) {
		global $wgDBname;
		$targets = array();
		foreach ( $data as $target ) {

			if ( !isset( $target['dbname'] ) ) {
				$dbname = self::getDBName( $target['site'] );
				if ( $dbname == null ) {
					// Not set in $wgConf
					continue;
				}
				$target['dbname'] = $dbname;
			}

			if ( $target['dbname'] == $wgDBname ) {
				$title = Title::newFromText( $target['title'] );
				if ( $title === null ) {
					continue;
				}
				$title = self::followRedirect( $title );
				if ( $title === null ) {
					continue; // Interwiki redirect
				}
				$target['title'] = $title->getPrefixedText();
			}

			// Use an assoc array to clear dupes
			$targets[$target['title'] . '<' . $target['dbname']] = $target;
			// Use a funky delimiter so people can't mess with it by using
			// "creative" page names
		}

		return $targets;
	}

	/**
	 * Get an array of targets via the #target parser function
	 * @param  Title $spamlist
	 * @param  IContextSource $context
	 * @return array
	 */
	public static function getParserFunctionTargets( $spamlist, $context ) {
		$page = WikiPage::factory( $spamlist );
		$content = $page->getContent( Revision::RAW );
		if ( $content instanceof TextContent ) {
			$text = $content->getNativeData();
		} else {
			return 'massmessage-spamlist-doesnotexist';
		}

		// Prep the parser
		if ( !defined( 'MASSMESSAGE_PARSE' ) ) { // Unit tests call this function multiple times
			define( 'MASSMESSAGE_PARSE', true );
		}
		$parserOptions = $page->makeParserOptions( $context );
		$parser = new Parser();

		// Parse
		$output = $parser->parse( $text, $spamlist, $parserOptions );
		$data = unserialize( $output->getProperty( 'massmessage-targets' ) );

		if ( $data ) {
			return self::normalizeTargets( $data );
		} else {
			return array(); // No parser functions on page
		}
	}

	/**
	 * Helper function for MassMessageHooks::ParserFunction
	 * Inspired from the Cite extension
	 * @param $msg string message key
	 * @param $param string parameter for the message
	 * @return array
	 */
	public static function parserError( $msg, $param ) {
		return array (
			'<strong class="error">' .
			wfMessage( $msg )->params( $param )->plain() .
			'</strong>',
			'noparse' => false
		);
	}
}
