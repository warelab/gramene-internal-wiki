<?php
/*
TableEdit is a system to create and manage simple tables in mediawiki.  See README for more information and requirements.
NOTE: this will not work just by adding it to LocalSettings.php!!!
*/
# Load required supporting files

require_once (dirname(__FILE__) . '/SpecialTableEdit.php');
require_once (dirname(__FILE__) . '/SpecialTableEdit.i18n.php');
require_once(dirname(__FILE__) ."/class.wikiBox.php");

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'TableEdit',
	'author' => 'Jim Hu',
	'version'=>'0.75',
	'description' => 'adds a forms-based table editor as a special page',
	'url' => 'http://www.mediawiki.org/wiki/Extension:TableEdit'
);



# Register hooks
$wgHooks['ArticleSave'][] = 'wfNewEditTable' ;


function wfNewEditTable( &$article, &$user, &$page_text, &$summary, $minor, $watch, $sectionanchor, &$flags){
	global $wgMessageCache, $wgTableEditMessages, $wgScript, $wgServerName, $wgHooks, $wgTableEditDatabase;

	# abort if this is a template page
	$title = $article->getTitle();
	if ($title->getNamespace() == 10 || $title->getNamespace() == 8) return true;

	foreach( $wgTableEditMessages as $key => $value ) {
		$wgMessageCache->addMessages( $wgTableEditMessages[$key], $key );
	}

	# parsing functionality modified from Parser.php
	# end up with a string, $stripped, where each instance is replaced by -newTableEdit-00000001-QINU
	# where the 8 digit numbers increment, and $matches, an array of useful info, including the
	# strings to replace in $stripped and the parameters passed by the enclosed tags, if any.
	static $n = 1;
	$stripped = '';
	$matches = array();

	$taglist = "newTableEdit|newVTableEdit";
	$start = "/<($taglist)(\\s+[^>]*?|\\s*?)(\/?>)/i";
	$text = $page_text;
	while ( '' != $text ) {
		$p = preg_split( $start, $text, 2, PREG_SPLIT_DELIM_CAPTURE );
		$stripped .= $p[0];
		if( count( $p ) < 5 ) {
			break;
		}
		if( count( $p ) > 5 ) {
			// comment
			$element    = $p[4];
			$attributes = '';
			$close      = '';
			$inside     = $p[5];
		} else {
			// tag
			$element    = $p[1];
			$attributes = $p[2];
			$close      = $p[3];
			$inside     = $p[4];
		}

		$uniq_prefix = dechex(mt_rand(0, 0x7fffffff)) . dechex(mt_rand(0, 0x7fffffff));
		$marker = "$uniq_prefix-$element-" . sprintf('%08X', $n++) . '-QINU';
		$stripped .= $marker;

		if ( $close === '/>' ) {
			// Empty element tag, <tag />
			$content = null;
			$text = $inside;
			$tail = null;
		} else {
			if( $element == '!--' ) {
				$end = '/(-->)/';
			} else {
				$end = "/(<\\/$element\\s*>)/i";
			}
			$q = preg_split( $end, $inside, 2, PREG_SPLIT_DELIM_CAPTURE );
			$content = $q[0];
			if( count( $q ) < 3 ) {
				# No end tag -- let it run out to the end of the text.
				$tail = '';
				$text = '';
			} else {
				$tail = $q[1];
				$text = $q[2];
			}
		}
		
		$matches[$marker] = array( $element,
			$content,
			Sanitizer::decodeTagAttributes( $attributes ),
			"<$element$attributes$close$content$tail" );
	}
	$pagename = $article->mTitle->getPrefixedURL();
	$page_uid = $article->getID();
	$box_uid = md5($wgServerName).".$page_uid.".uniqid(chr(rand(65,90)));
	$type = "";
	
	# gather nowiki content
	preg_match_all('/<nowiki>.*<\/nowiki>/i', $stripped, $nowiki_matches);
	$nowiki = " ".implode('',$nowiki_matches[0]);

	foreach ($matches as $key=>$match){
		# key is the stuff to replace at the end
		# $match[0] is the element
		
		# put back calls from inside nowiki
		if (strpos($nowiki,$key) > 0){
			$replacement = "<".$match[0];
			if (isset($match[1])){ 
				$replacement .= ">".$match[1]."</".$match[0].">";
			}else $replacement .= "/>";
			$stripped = str_replace($key,$replacement,$stripped);
			continue;
		}
		
		switch ($match[0]){
			case 'newTableEdit':
				$type = 0;
				break;
			case 'newVTableEdit':
				$type = 1;
				break;
		
		}
		# $match[1] is the parameters.
		$data = trim($match[1]);
		if (count(explode("\n",$data)) < 2  || strpos("_".$data,"Template:") == 1){
			#assume it's a template
			$template = str_replace("Template:", "", $data);
			$headings = '';
		}else{
			$template = "";
			$headings = $data;
		}
		
		$dbr =& wfGetDB( DB_SLAVE );
		# first blank is for autoincrement of box_id, last is for headings_style

		$sql = "INSERT INTO $wgTableEditDatabase.box VALUES (null, '$template','$pagename','$page_uid','$box_uid','$type','$headings','','','".time()."')";
		$result = $dbr->query($sql);
		$box_id = mysql_insert_id(); # couldn't figure out how to recover this using Database functions.
		$tableEdit = new TableEdit;
		$box = new wikiBox($box_uid);
		$box->set_from_DB();# print_r($box); exit;
		$replacement = $tableEdit->make_wikibox($box);

		if (in_array('wfCheckProtectSection',$wgHooks['EditFilter'])) $replacement = "<protect>".$replacement."</protect>";


		$stripped = str_replace($key,$replacement,$stripped);
	}

	$page_text = $stripped;
	return true;
}


?>