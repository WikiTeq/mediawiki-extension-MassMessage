<?php

namespace MediaWiki\MassMessage;

use Title;
use Wikipage;
use WikitextContent;

/**
 * Tests for Category Spamlist function related to target processing
 * @group extension-MassMessage
 */
class CategorySpamlistLookupTest extends MassMessageTestCase {

	/**
	 * @covers \MediaWiki\MassMessage\SpamlistLookup::getTargets
	 * @covers \MediaWiki\MassMessage\CategorySpamlistLookup::fetchTargets
	 */
	public static function testGetTargets() {
		$page = Title::newFromText( 'Talk:Testing1234' );
		$wikipage = WikiPage::factory( $page );
		$wikipage->doEditContent( new WikitextContent( '[[Category:Spamlist1234]]' ), 'edit summary' );

		$cat = Title::newFromText( 'Category:Spamlist1234' );
		$targets = SpamlistLookup::getTargets( $cat );
		self::assertSame( 1, count( $targets ) );
		$values = array_values( $targets );
		self::assertEquals( 'Talk:Testing1234', $values[0]['title'] );
	}
}
