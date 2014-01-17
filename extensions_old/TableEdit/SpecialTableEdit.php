<?php
/*
TableEdit is a system for providing a more user-friendly interface for editing simple tables with constant numbers of columns.  
SpecialTableEdit sets up a special page for the editing interface.  General documentation is in TableEdit/Readme
*/
# Not a valid entry point, skip unless MEDIAWIKI is defined
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install this extension, put the following line in LocalSettings.php:
require_once( "$IP/extensions/TableEdit/SpecialTableEdit.php" );
EOT;
        exit( 1 );
}
require_once (dirname(__FILE__) . '/SpecialTableEdit.i18n.php');
$wgAutoloadClasses['TableEdit'] = dirname(__FILE__) . '/SpecialTableEdit.body.php';
$wgSpecialPages['TableEdit'] = 'TableEdit';
$wgHooks['LoadAllMessages'][] = 'TableEdit::loadMessages';

$wgExtensionFunctions[] = 'tableeditsetup';
function tableeditsetup() {
	global $wgMessageCache, $wgTableEditMessages;
	$wgMessageCache->addMessage('tableedit', 'Table Edit');
	foreach( $wgTableEditMessages as $key => $value ) {
		$wgMessageCache->addMessages( $wgTableEditMessages[$key], $key );
	}

}


?>