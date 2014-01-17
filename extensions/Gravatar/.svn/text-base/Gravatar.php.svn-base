<?php
/**
 * @author Jean-Lou Dupont
 * @package Gravatar
 * @version @@package-version@@
 * @Id $Id$
 */
//<source lang=php>
if (!class_exists('StubManager'))
	echo '[[Extension:Gravatar]] <b>requires</b> [[Extension:StubManager]] version >= 1.1.0'."\n";
else
{
	$wgExtensionCredits['other'][] = array( 
		'name'    		=> 'Gravatar',
		'version'		=> '@@package-version@@',
		'author'		=> 'Jean-Lou Dupont',
		'url'			=> 'http://www.mediawiki.org/wiki/Extension:Gravatar',	
		'description' 	=> "Provides integration with [http://site.gravatar.com Gravatar]", 
	);
	
	StubManager::createStub2(	array(	'class' 		=> 'Gravatar', 
										'classfilename'	=> dirname(__FILE__).'/Gravatar.body.php',
										'mgs'			=> array( 'gravatar', 'gravatar_raw' )
									)
							);
}
//</source>
