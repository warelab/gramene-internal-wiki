<?php
/*
 * TableEditLinks.php - An extension 'module' for the TableEdit extension.
 * @author Jim Hu (jimhu@tamu.edu)
 * @version 0.1
 * @copyright Copyright (C) 2007 Jim Hu
 * @license The MIT License - http://www.opensource.org/licenses/mit-license.php 
 */

if ( ! defined( 'MEDIAWIKI' ) ) die();

# Credits
$wgExtensionCredits['other'][] = array(
    'name'=>'TableEditLinks',
    'author'=>'Jim Hu &lt;jimhu@tamu.edu&gt;',
    'description'=>'Add links to table entries for TableEdit.',
    'version'=>'0.1'
);


# Register hooks ('PagesOnDemand' hook is provided by the PagesOnDemand extension).
$wgHooks['TableEditBeforeSave'][] = 'wfTableEditLinks';

/**
* Loads a demo page if the title matches a particular pattern.
* @param Title title The Title to check or create.
*/
function wfTableEditLinks( $article, $table ){
	
	$dbr =& wfGetDB( DB_SLAVE );
	
	# Convert _ to space
#	$table = str_replace('_',' ',$table);
	
	# Do links to GONUTS
	$pattern = "/GO:\d+/";
	preg_match_all($pattern, $table, $matches);
	foreach ($matches[0] as $match){
		$sql = "SELECT page_title from GO_archive.term WHERE go_id = '$match' ORDER BY term_update DESC LIMIT 1";
		$result = $dbr->query($sql);
		$x = $dbr->fetchObject ( $result );
		$arr = get_object_vars($x);						
		$table = str_replace("|$match", "|[http://gowiki.tamu.edu/GO/wiki/index.php/Category:".$arr['page_title']." $match]", $table);
	}

	# Do links to PMID pages
	$table = preg_replace('/PMID:([ _]|%20)*/','PMID:', $table);
	$pattern = "/PMID:\d+/";
	preg_match_all($pattern, $table, $matches);
	foreach ($matches[0] as $match){
		$table = str_replace("|$match", "|[[$match]]<ref name=$match/>", $table);
		$table = str_replace("\n$match", "\n[[$match]]<ref name=$match/>", $table);
	}

	return true;
}
?>
