<?php

namespace MediaWiki\MassMessage;

use ContentHandler;
use Title;
use WikiPage;

/**
 * Tests for the API module to edit a MassMessage delivery list.
 * @group API
 * @group Database
 * @group medium
 * @group extension-MassMessage
 * @covers \MediaWiki\MassMessage\Api\ApiEditMassMessageList
 */
class ApiEditMassMessageListTest extends MassMessageApiTestCase {

	protected static $spamlist = 'ApiEditMMListTest_spamlist';

	protected function setUp() : void {
		parent::setUp();
		$title = Title::newFromText( self::$spamlist );
		$page = WikiPage::factory( $title );
		$content = ContentHandler::getForModelID( 'MassMessageListContent' )->makeEmptyContent();
		$page->doEditContent( $content, 'summary' );
	}

	public function testAdd() {
		$sysop = $this->getTestSysop()->getUser();
		$result = $this->doApiRequestWithToken( [
			'action' => 'editmassmessagelist',
			'spamlist' => self::$spamlist,
			'add' => 'A|A|A@en.wikipedia.org|_|_|A@invalid.org|_@invalid.org'
		], null, $sysop );
		$expected = [ 'editmassmessagelist' => [
			'result' => 'Done',
			'added' => [
				[ 'title' => 'A', 'missing' => '' ],
				[ 'title' => 'A', 'site' => 'en.wikipedia.org' ]
			],
			'invalidadd' => [
				[ '*' => '_', 'invalidtitle' => '' ],
				[ '*' => 'A@invalid.org', 'invalidsite' => '' ],
				[ '*' => '_@invalid.org', 'invalidtitle' => '', 'invalidsite' => '' ]
			]
		] ];
		$this->assertEquals( $expected, $result[0] );
	}

	public function testRemove() {
		$sysop = $this->getTestSysop()->getUser();
		$content = ContentHandler::makeContent(
			'{"description":"","targets":[{"title":"B"},{"title":"A","site":"en.wikipedia.org"}]}',
			null,
			'MassMessageListContent'
		);
		$page = WikiPage::factory( Title::newFromText( self::$spamlist ) );
		$page->doEditContent( $content, 'summary' );
		$result = $this->doApiRequestWithToken( [
			'action' => 'editmassmessagelist',
			'spamlist' => self::$spamlist,
			'remove' => 'A|A|B|B|A@en.wikipedia.org|_'
		], null, $sysop );
		$expected = [ 'editmassmessagelist' => [
			'result' => 'Done',
			'removed' => [
				[ 'title' => 'B' ],
				[ 'title' => 'A', 'site' => 'en.wikipedia.org' ]
			],
			'invalidremove' => [
				'A',
				'_'
			]
		] ];
		$this->assertEquals( $expected, $result[0] );
	}

	public function testAddDescription() {
		$content = ContentHandler::makeContent(
			'{"description":"","targets":[{"title":"B"},{"title":"A","site":"en.wikipedia.org"}]}',
			null,
			'MassMessageListContent'
		);
		$page = WikiPage::factory( Title::newFromText( self::$spamlist ) );
		$page->doEditContent( $content, 'summary' );
		$result = $this->doApiRequestWithToken( [
			'action' => 'editmassmessagelist',
			'spamlist' => self::$spamlist,
			'description' => 'example description 123'
		] );
		$expected = [ 'editmassmessagelist' => [
			'result' => 'Success',
			'description' => 'example description 123'
		] ];
		$this->assertEquals( $expected, $result[0] );
	}

	public function testChangeDescription() {
		$content = ContentHandler::makeContent(
			'{"description":"example description 456",
                        "targets":[{"title":"B"},{"title":"A","site":"en.wikipedia.org"}]}',
			null,
			'MassMessageListContent'
		);
		$page = WikiPage::factory( Title::newFromText( self::$spamlist ) );
		$page->doEditContent( $content, 'summary' );
		$result = $this->doApiRequestWithToken( [
			'action' => 'editmassmessagelist',
			'spamlist' => self::$spamlist,
			'description' => 'example description 456'
		] );
		$expected = [ 'editmassmessagelist' => [
			'result' => 'Done',
			'invaliddescription' => 'example description 456'
		] ];
		$this->assertEquals( $expected, $result[0] );
	}

	public function testMixed() {
		$sysop = $this->getTestSysop()->getUser();
		$content = ContentHandler::makeContent(
			'{"description":"","targets":[{"title":"B"},{"title":"A","site":"en.wikipedia.org"}]}',
			null,
			'MassMessageListContent'
		);
		$page = WikiPage::factory( Title::newFromText( self::$spamlist ) );
		$page->doEditContent( $content, 'summary' );
		$result = $this->doApiRequestWithToken( [
			'action' => 'editmassmessagelist',
			'spamlist' => self::$spamlist,
			'add' => 'B|C|D',
			'remove' => 'A@en.wikipedia.org|B|C',
			'description' => 'example description 789'
		], null, $sysop );

		$expected = [ 'editmassmessagelist' => [
			'result' => 'Success',
			'added' => [
				[ 'title' => 'D', 'missing' => '' ]
			],
			'removed' => [
				[ 'title' => 'B' ],
				[ 'title' => 'A', 'site' => 'en.wikipedia.org' ]
			],
			'description' => 'example description 789'
		] ];
		$this->assertEquals( $expected, $result[0] );
	}
}
