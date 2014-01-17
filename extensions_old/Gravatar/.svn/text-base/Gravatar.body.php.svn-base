<?php
/**
 * @author Jean-Lou Dupont
 * @package Gravatar
 * @version @@package-version@@
 * @Id $Id$
 */
//<source lang=php>
class Gravatar
{
	const thisType = 'other';
	const thisName = 'Gravatar';

	// For Messages
	static $msg = array();

	// Error Codes
	const codeMissingParameter  = 1;
	const codeListEmpty         = 2;

	/*
	 * m: mandatory parameter
	 * s: sanitization required
	 * l: which parameters to pick from list
	 * d: default value
	 */
	static $parameters = array(
		// Parameters:
		'email_p1'	=> array( 'm' => true,  's' => true, 'l' => false, 'd' => null,   'sq' => true, 'dq' => true  ),
		'email_p2'	=> array( 'm' => true,  's' => true, 'l' => false, 'd' => null,   'sq' => true, 'dq' => true  ),
		'size'		=> array( 'm' => false, 's' => true, 'l' => false, 'd' => '40',   'sq' => true, 'dq' => true  ),
		'default'	=> array( 'm' => false, 's' => true, 'l' => false, 'd' => null,   'sq' => true, 'dq' => true  ),
		'width'		=> array( 'm' => false, 's' => true, 'l' => true,  'd' => null,   'sq' => true, 'dq' => true  ),
		'height'	=> array( 'm' => false, 's' => true, 'l' => true,  'd' => null,   'sq' => true, 'dq' => true  ),		
		'alt'		=> array( 'm' => false, 's' => true, 'l' => true,  'd' => null,   'sq' => true, 'dq' => true  ),		
		'title'		=> array( 'm' => false, 's' => true, 'l' => true,  'd' => null,   'sq' => true, 'dq' => true  ),		
	);
	/**
	 * Initialize the messages
	 */
	public function __construct()
	{
		global $wgMessageCache;

		foreach( self::$msg as $key => $value )
			$wgMessageCache->addMessages( self::$msg[$key], $key );		
	}	 
	/**
	 * {{#gravatar_raw: email_p1=John.Smith | email_p2=gmail.com [|optional parameters] }}
	 */
	public function mg_gravatar_raw( &$parser )
	{
		$params = func_get_args();
		$liste = StubManager::processArgList( $params, true );		
		
		$code = $this->getParameters( $liste, $attrListe, $email, $id, $src );

		if ( $code !== true )
			return $code;
			
		return $src;
	}
	/**
	 * {{#gravatar: email_p1=John.Smith | email_p2=gmail.com [|optional parameters] }}
	 */
	public function mg_gravatar( &$parser )
	{
		$params = func_get_args();
		$liste = StubManager::processArgList( $params, true );		
		
		$output = $this->renderEntry( $liste );

		return array( $output, 'noparse' => true, 'isHTML' => true );
	}
	/**
	 * Returns 1 fully rendered HTML element
	 */
	protected function renderEntry( &$liste )
	{
		$code = $this->getParameters( $liste, $attrListe, $email, $id, $src );
		if ( $code !== true )
			return $code;
		
		return "<img src='$src' $attrListe/>";
	}
	/**
	 *
	 */	
	protected function getParameters( &$liste, &$attrListe, &$email, &$id, &$src )
	{
		// check mandatory parameters
		$sliste= ExtHelper::doListSanitization( $liste, self::$parameters );
		if (empty( $sliste ))
			return $this->getErrorMsg( self::codeListEmpty );
		
		if (!is_array( $sliste ))
			return $this->getErrorMsg( self::codeMissingParameter, $sliste);

		$attrListe = null;
		$r = ExtHelper::doSanitization( $sliste, self::$parameters );
		$attrListe = ExtHelper::buildList( $sliste, self::$parameters );
		
		$email = $sliste['email_p1']."@".$sliste['email_p2'];;

		$id = md5( $email );
		$default = isset( $sliste['default'] ) ? $sliste['default']:null;
		$size    = isset( $sliste['size'] ) ? $sliste['size']:null;		

		$src = $this->getSrc( $id, $default, $size );
		
		return true;
	}	 
	/**
	 * Returns a fully formatted 'src' attribute
	 * Sanitization of parameters must have been performed.
	 */
	protected function getSrc( $id, $default, $size )	 
	{
		if (!empty( $default ))
			$default = '&amp;default='.$default;
			
		if (!empty( $size ))
			$size = '&amp;size='.$size;
			
		return "http://en.gravatar.com/avatar/${id}${default}${size}";
	}
	
// %%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%%

	/**
	 * Returns the corresponding error message
	 */
	protected function getErrorMsg( $code, $param = null )
	{
		return wfMsgForContent( 'gravatar'.$code, $param );	
	}

} // end class
require 'Gravatar.i18n.php';
//</source>
