<?php

namespace MediaWiki\MassMessage;

/**
 * @group extension-MassMessage
 */
class DatabaseLookupTest extends MassMessageTestCase {

	public static function provideGetDBName() {
		return [
			[ 'en.wikipedia.org', 'enwiki' ],
			[ 'fr.wikipedia.org', 'frwiki' ],
			[ 'de.wikipedia.org', 'dewiki' ],
			[ 'not.a.wiki.known.to.us', null ],
		];
	}

	/**
	 * @covers \MediaWiki\MassMessage\DatabaseLookup::getDBName
	 * @dataProvider provideGetDBName
	 * @param string $url
	 * @param string $expected
	 */
	public function testGetDBName( $url, $expected ) {
		$dbname = DatabaseLookup::getDBName( $url );
		$this->assertEquals( $expected, $dbname );
	}
}
