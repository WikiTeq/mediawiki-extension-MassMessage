<?php

class MassMessageListContentHandler extends TextContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'MassMessageListContent' ) {
		parent::__construct( $modelId, array( CONTENT_FORMAT_JSON ) );
	}

	/**
	 * @param string $text
	 * @param string $format
	 * @return MassMessageListContent
	 */
	public function unserializeContent( $text, $format = null ) {
		$this->checkFormat( $format );
		$content = new MassMessageListContent( $text );
		if ( !$content->isValid() ) {
			throw new MWContentSerializationException( 'The delivery list is invalid.' );
		}
		return $content;
	}

	/**
	 * @return MassMessageListContent
	 */
	public function makeEmptyContent() {
		return new MassMessageListContent( '{"description":"","targets":[]}' );
	}

}
