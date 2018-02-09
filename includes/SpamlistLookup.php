<?php

/**
 * Functions related to target processing
 */

namespace MediaWiki\MassMessage;

use MediaWiki\MediaWikiServices;
use WANObjectCache;

use Title;

abstract class SpamlistLookup {

	/**
	 * Get an array of targets via the getTarget function
	 * @return array
	 */
	abstract public function fetchTargets();

	/**
	 * Get an array of targets given a title; returns null if invalid.
	 *
	 * Each target is an associative array with the following keys:
	 * title: The title of the target
	 * wiki: The ID of the wiki (wfWikiID() for the local wiki)
	 * site: The hostname and port (if exists) of the wiki
	 *
	 * Normalized targets are briefly cached because it can be expensive to parse PF targets on both
	 * preview and save in SpecialMassMessage.
	 *
	 * @param Title $spamlist
	 * @param bool $normalize Whether to normalize and deduplicate the targets
	 * @return array|null
	 */
	public static function getTargets( Title $spamlist, $normalize = true ) {
		if ( !$spamlist->exists() && !$spamlist->inNamespace( NS_CATEGORY ) ) {
			return null;
		}

		$lookup = self::factory( $spamlist );
		$callback = function ( $old = null, &$ttl = null ) use ( $lookup, $spamlist, $normalize ) {
			$targets = $lookup->fetchTargets();
			if ( $targets && $normalize ) {
				$value = self::normalizeTargets( $targets );
			} else {
				$value = $targets;
				// Do not cache negatives (null or []) nor non-normalized lists
				$ttl = WANObjectCache::TTL_UNCACHEABLE;
			}

			return $value;
		};

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $lookup->isCachable()
			? $cache->getWithSetCallback(
				$cache->makeKey(
					'massmessage',
					'targets',
					$spamlist->getLatestRevId(),
					$spamlist->getTouched()
				),
				$cache::TTL_HOUR,
				$callback
			)
			: $callback();
	}

	/**
	 * Gets a Title and returns an object  depending on the content of $title.
	 * Returns null if invalid
	 * @param Title $title
	 * @return object
	 */
	public static function factory( Title $title ) {
		if ( $title->inNamespace( NS_CATEGORY ) ) {
			return new CategorySpamlistLookup( $title );
		} elseif ( $title->hasContentModel( 'MassMessageListContent' ) ) {
			return new ListContentSpamlistLookup( $title );
		} elseif ( $title->hasContentModel( CONTENT_MODEL_WIKITEXT ) ) {
			return new ParserFunctionSpamlistLookup( $title );
		} else {
			return null;
		}
	}

	/**
	 * Returns True
	 * @return Bool
	 */
	public function isCachable() {
		return true;
	}

	/**
	 * Get array of normalized targets with duplicates removed
	 * @param array $data
	 * @return array
	 */
	protected static function normalizeTargets( array $data ) {
		global $wgNamespacesToConvert;

		foreach ( $data as &$target ) {
			if ( $target['wiki'] === wfWikiID() ) {
				$title = Title::newFromText( $target['title'] );
				if ( $title === null ) {
					continue;
				}
				if ( isset( $wgNamespacesToConvert[$title->getNamespace()] ) ) {
					$title = Title::makeTitle( $wgNamespacesToConvert[$title->getNamespace()],
						$title->getText() );
				}
				$title = UrlHelper::followRedirect( $title );
				if ( $title === null ) {
					continue; // Interwiki redirect
				}
				$target['title'] = $title->getPrefixedText();
			}
		}

		// Return $data with duplicates removed
		return array_unique( $data, SORT_REGULAR );
	}

}
