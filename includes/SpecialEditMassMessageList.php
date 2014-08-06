<?php

class SpecialEditMassMessageList extends FormSpecialPage {

	/**
	 * @var Title|null
	 */
	protected $title;

	/**
	 * The revision to edit
	 * @var Revision|null
	 */
	protected $rev;

	/**
	 * The message key for the error encountered while parsing the title, if any
	 * @var string|null
	 */
	protected $errorMsgKey;

	public function __construct() {
		parent::__construct( 'EditMassMessageList' );
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->getOutput()->addModules( 'ext.MassMessage.edit' );
		parent::execute( $par );
	}

	/**
	 * @param string $par
	 */
	protected function setParameter( $par ) {
		if ( $par === null || $par === '' ) {
			$this->errorMsgKey = 'massmessage-edit-invalidtitle';
		} else {
			$title = Title::newFromText( $par );

			if ( !$title
				|| !$title->exists()
				|| !$title->hasContentModel( 'MassMessageListContent' )
			) {
				$this->errorMsgKey = 'massmessage-edit-invalidtitle';
			} else if ( !$title->userCan( 'edit' ) ) {
				$this->errorMsgKey = 'massmessage-edit-nopermission';
			} else {
				$this->title = $title;

				$revId = $this->getRequest()->getInt( 'oldid' );
				if ( $revId > 0 ) {
					$rev = Revision::newFromId( $revId );
					if ( $rev
						&& $rev->getTitle()->equals( $title )
						&& $rev->getContentModel() === 'MassMessageListContent'
						&& $rev->userCan( Revision::DELETED_TEXT, $this->getUser() )
					) {
						$this->rev = $rev;
					} else { // Use the latest revision for the title if $rev is invalid.
						$this->rev = Revision::newFromTitle( $title );
					}
				} else {
					$this->rev = Revision::newFromTitle( $title );
				}
			}
		}
	}

	/**
	 * Override the parent implementation to modify the page title and add a backlink.
	 */
	public function setHeaders() {
		parent::setHeaders();
		if ( $this->title ) {
			$out = $this->getOutput();

			// Page title
			$out->setPageTitle(
				$this->msg( 'massmessage-edit-pagetitle', $this->title->getPrefixedText() )
			);

			// Backlink
			$revId = $this->rev->getId();
			$query = ( $revId !== $this->title->getLatestRevId() ) ?
				array( 'oldid' => $revId ) : array();
			// Modified from OutputPage::addBacklinkSubtitle()
			$out->addSubtitle( $this->msg( 'backlinksubtitle' )->rawParams(
				Linker::link( $this->title, null, array(), $query )
			) );
		}
	}

	/**
	 * @return array
	 */
	protected function getFormFields() {

		// Show a hidden empty form if the title is invalid.
		if ( !$this->title ) {
			return array();
		}

		$content = $this->rev->getContent( Revision::FOR_THIS_USER, $this->getUser() );
		$description = $content->getDescription();
		$targets = $content->getTargetStrings();

		return array(
			'description' => array(
				'type' => 'textarea',
				'rows' => 5,
				'default' => ( $description !== null ) ? $description : '',
				'label-message' => 'massmessage-edit-description',
			),
			'content' => array(
				'type' => 'textarea',
				'default' => ( $targets !== null ) ? implode( "\n", $targets ) : '',
				'label-message' => 'massmessage-edit-content',
			),
			'summary' => array(
				'type' => 'text',
				'maxlength' => 255,
				'size' => 60,
				'label-message' => 'massmessage-edit-summary',
			),
		);
	}

	/**
	 * Hide the form if the title is invalid.
	 * @param HTMLForm $form
	 */
	protected function alterForm( HTMLForm $form ) {
		if ( !$this->title ) {
			$form->setWrapperLegend( false );
			$form->suppressDefaultSubmit( true );
		}
	}

	/**
	 * Return instructions for the form and / or warnings.
	 * @return string
	 */
	protected function preText() {
		global $wgAllowGlobalMessaging;

		if ( $this->title ) {
			// Instructions
			if ( $wgAllowGlobalMessaging && count( MassMessage::getDatabases() ) > 1 ) {
				$headerKey = 'massmessage-edit-headermulti';
			} else {
				$headerKey = 'massmessage-edit-header';
			}
			$html = Html::rawElement( 'p', array(), $this->msg( $headerKey )->parse() );

			// Deleted revision warning
			if ( $this->rev->isDeleted( Revision::DELETED_TEXT ) ) {
				$html .= Html::openElement( 'div', array( 'class' => 'mw-warning plainlinks' ) );
				$html .= Html::rawElement( 'p', array(),
					$this->msg( 'rev-deleted-text-view' )->parse() );
				$html .= Html::closeElement( 'div' );
			}

			// Old revision warning
			if ( $this->rev->getId() !== $this->title->getLatestRevID() ) {
				$html .= Html::rawElement( 'p', array(), $this->msg( 'editingold' )->parse() );
			}
		} else {
			// Error determined in setParameter()
			$html = Html::rawElement( 'p', array(), $this->msg( $this->errorMsgKey )->parse() );
		}
		return $html;
	}

	/**
	 * Return a copyright warning to be displayed below the form.
	 * @return string
	 */
	protected function postText() {
		if ( $this->title ) {
			return EditPage::getCopyrightWarning( $this->title, 'parse' );
		} else {
			return '';
		}
	}

	/**
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		if ( !$this->title ) {
			return Status::newFatal( 'massmessage-edit-invalidtitle' );
		}

		$parseResult = self::parseInput( $data['content'] );
		if ( !$parseResult->isGood() ) {
			// Wikitext list of escaped invalid target strings
			$invalidList = '* ' . implode( "\n* ", array_map( 'wfEscapeWikiText',
				$parseResult->value ) );
			return Status::newFatal( $this->msg( 'massmessage-edit-invalidtargets',
				count( $parseResult->value ), $invalidList ) );
		}

		$editResult = MassMessageListContentHandler::edit(
			$this->title,
			$data['description'],
			$parseResult->value,
			$data['summary'],
			$this->getContext()
		);

		if ( !$editResult->isGood() ) {
			return $editResult;
		}
		$this->getOutput()->redirect( $this->title->getFullUrl() );
	}

	public function onSuccess() {
		// No-op: We have already redirected.
	}

	/**
	 * Parse user input into an array of targets and return it as the value of a Status object.
	 * If input contains invalid data, the value is the array of invalid target strings.
	 * @param string $input
	 * @return Status
	 */
	protected static function parseInput( $input ) {
		$lines = array_filter( explode( "\n", $input ), 'trim' ); // Array of non-empty lines

		$targets = array();
		$invalidTargets = array();
		foreach ( $lines as $line ) {
			$target = MassMessageListContentHandler::extractTarget( $line );
			if ( array_key_exists( 'errors', $target ) ) {
				$invalidTargets[] = $line;
			}
			$targets[] = $target;
		}

		$result = new Status;
		if ( empty( $invalidTargets ) ) {
			$result->setResult( true,
				MassMessageListContentHandler::normalizeTargetArray( $targets ) );
		}else {
			$result->setResult( false, $invalidTargets );
		}
		return $result;
	}
}
