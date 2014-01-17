<?php
$allMessages = array(
        'en' => array( 
      		'tableedit' => 'Table Edit'
        )
);
$wgTableEditMessages['en'] = array(
	'newTableHere' => 'Create Table Here',
		
	'insufficientRights' => "<p style='color:red'><b>You don't have sufficient rights on this wiki to edit tables.  Perhaps you need to log in. Changes you make in the Table editor will not be saved back to the wiki</b></p>
		<p>See <a href = '{{SCRIPTPATH}}/index.php/Help:Help'>Help</a> for Help on this wiki.  See <a href = '{{SCRIPTPATH}}/index.php/Special:TableEdit'>the documentation</a> for how to use the table editor",
		
	'tableEditEditLink' => 'edit table',
		
	'titleProblem' => "Sorry! There was a problem identifying the correct page for this table.  Try going back and recreating it after saving the page.  See <a href = '{{SCRIPTPATH}}/index.phpSpecial:TableEdit'>the documentation</a> for how to use the table editor",
	
	'explainOwnerRules' => "Public rows can be edited or deleted by any user who can edit<br>
			Private rows can be edited or deleted by their creator, or by admins",
	'changesNotSavedUntil' => "Changes are not saved permanently until you save the table back to the wiki page",

	'pleaseDontEditHere' => "\n<!--
******************************************************************************************
*
*   ** PLEASE DON'T EDIT THIS TABLE DIRECTLY.  Use the edit table link under the table. **
*
****************************************************************************************** -->"
	
);
?>