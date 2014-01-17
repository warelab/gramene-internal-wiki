<?php
# version 0.7
require_once("class.wikiBox.php");

class TableEdit extends SpecialPage{

	function TableEdit() {
		SpecialPage::SpecialPage("TableEdit");
	}
	
	function execute( $par ) {
		global $wgRequest, $wgOut, $wgUser, $wgScript, $wgParser;
		self::loadMessages();
		$this->setHeaders();

		# Initialize 
		$this->getRequestData($wgRequest);
		$this->uid = $wgUser->mId; 
		$this->old_table = '';
		if ($this->act == 'test'){
			include(dirname(__FILE__) . '/tests.php');
			exit;
		}else{
			# Start processing the request
			$box = new wikiBox();
			$box->page_name = $this->page_name;
			session_start();
			if (isset($this->table_id) && $this->table_id != '' && ($this->act == '' || !isset($this->serialized))){
				$box->box_uid = $this->table_id;
				if ($box->set_from_DB()){
					$this->box_id = $box->box_id; #echo "set from db<br>";
					$box->page_name = $this->page_name;
					unset ($_SESSION['TableEditData.'.$this->table_id]);
				}else{
					# wasn't in the database!
					$title = Title::newFromDBkey($this->page_name);
				}
			}elseif(isset($this->serialized) && $this->serialized !=''){
				$box->set_from_serialized($this->serialized); #echo "set from session<br>";
				$this->box_uid = $box->box_uid;
			}
			# get a Title object
			$title = Title::newFromID($box->page_uid);
			# this will fail if page_uid is 0, which happens if the table was created as part of the first article save.  So look to the title.
			if (!is_object($title))	$title = Title::newFromDBkey($this->page_name);

			if (is_object($title) && !$title->exists() || !isset ($box->page_uid) || $box->page_uid != $this->page) {
				#bail out to documentation
				$output = "";
				include(dirname(__FILE__) . '/SpecialTableEdit.docs.php');
			}elseif(!is_object($title)){
				$output = "<p>".wfMsg('titleProblem')."</p>";
			}else{

				# OK, now we can actually do edit views!	
				$box->page_title = $title->getDBkey();
				$output = "";
				if (!$wgUser->isAllowed('edit')) $output = "<p>".wfMsg('insufficientRights')."</p>";				
				$table = "";

				# attempt to recover the data from the wiki in case someone edited it without TableEdit TODO
				# $this->recover_wiki_table($title, &$box);
#	echo "<br><br><br><br><br><br><br><br><br><br>this:<pre>";#print_r($this);echo "</pre><br>";
#	echo "box:";print_r($box);echo "</pre><br>";
#	echo "session:<pre>";print_r($_SESSION);echo "</pre><br>";
#	echo "request:<pre>";print_r($_REQUEST);echo "</pre><br>";
				
				switch ($this->act){
					case 'save':
						# Test for edit conflict
						if($this->check_conflict($box)){
							# Save back to the page
							$this->save_to_page($title, $box);
						}else{
							$table = $this->display_conflict($box);
						}
						break;	
					case 'force_save':
						$this->save_to_page($title, $box);
						break;	
					case 'update_conflict':
						$table = $this->display_conflict($box);
						break;	
					case 'editheadings':
						$table = $this->edit_headings($box);
						break;
					case 'editrow':
						$table = $this->edit_row($box);
						break;
					case 'saverow':
						if ($this->submit == "Update"){
							$table = $this->edit_row($box);					
						}else{
							$table = $this->save_row($box);
						}
						break;
					case 'saveheadings':
						$data = implode("\n",$this->field);
						$box->headings = $data;
						$box->heading_style = $this->style;
						$table = $this->make_box($box);
						break;
					case 'revert':
						$box->set_from_DB();
						unset ($_SESSION['TableEditData.'.$this->table_id]);
						$table = $this->make_box($box);	
						break;
					case 'flip':
						if ($box->type == 1){ 
							$box->type(0);
						}elseif ($box->type == 0){
							$box->type(1);
						}
					default:
						$table = $this->make_box($box);	
				}
			}
			$output .=$table;
		}
		# save state
		$_SESSION['TableEditData.'.$this->table_id] = $box->get_serialized();  

#	echo "<br><br><br><br><br><br><br><br><br>box:<pre>";print_r($box);echo "</pre><br>";
		
		# Output
		$wgOut->addHTML( $output );
	}

	function getRequestData($wgRequest){
		$this->table_id = $wgRequest->getText('id');
		$this->page = $wgRequest->getText('page');
		$this->page_name = $wgRequest->getText('pagename');
		$this->act = $wgRequest->getText('act');
		$this->submit = $wgRequest->getText('submit');
		$this->field = $wgRequest->getArray('field');
		$this->box_id = $wgRequest->getText('box_id');
		$this->box_type = $wgRequest->getText('type');
		$this->row_index = $wgRequest->getText('row_index');
		$this->style = $wgRequest->getText('style');
	#	$this->serialized = $wgRequest->getText('serialized');
		if (isset($_SESSION['TableEditData.'.$this->table_id])) $this->serialized = $_SESSION["TableEditData.".$this->table_id];
		$this->new_row = $wgRequest->getText('new_row');
		$this->row_owner = $wgRequest->getText('row_owner');
		$this->headings = $wgRequest->getText('headings');
		$this->heading_style = $wgRequest->getText('heading_style');
		$this->row_data = $wgRequest->getText('row_data');
	}
	
	function make_box($box){
		global $wgUser, $wgParser, $wgTitle;		
		$this->serialized = $box->get_serialized();
		$table = "<p>".wfMsg('changesNotSavedUntil')."</p>";
		switch ($box->type){
			case 1:
				$table .= "<table ".$this->prettytable($box).">";
				$tablerows = array();
				$headings = explode("\n", $box->headings);
				$i = 0;
				foreach ($headings as $heading){
					$i++;
					$tablerows[$i] = "<tr><th $box->heading_style>$heading</th>";
				}
				$i++;
				$tablerows[$i] = "<tr><td>";
				if(!isset($box->template) || $box->template == '' ) $tablerows[$i] .= "{{{EDITHEADINGS}}}";
				$tablerows[$i] .= "</td>";
				
				foreach ($box->rows as $row_index=>$row){
					if($row->is_current){
						$editrowform = 
							$this->form_top().
							$this->form_hidden('row_index',$row_index).
							$this->form_act('editrow');
						# row owner values:
						#	0 public
						#	>0 owner uid
						if (isset($row->owner_uid)) $owner_uid = $row->owner_uid;		
						if($owner_uid == $wgUser->getID() || in_array('bureaucrat', $wgUser->getEffectiveGroups())){
							$editrowform .= $this->form_button('Edit')." ".$this->form_button('Delete')."</form>";
						}elseif($owner_uid == 0){	
							$editrowform .= $this->form_button('Edit')." ".$this->form_button('Delete')." public</form>";
						}else{
							$editrowform .= 'protected';
						}
						$editrowforms[$row_index] = $editrowform;
						$data = explode('||',$row->row_data);
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						for ($j=1; $j<$i; $j++){
							$tablerows[$j] .= "<td>".str_replace("\n",'',nl2br($data[$j-1]))."</td>";
						}
						$tablerows[$i] .= "<td>{{{EDITROWFORM$row_index}}}</td>";
					}
				}				
				foreach ($tablerows as $tablerow){
					$table .= "$tablerow</tr>\n";
				}
				# make buttons on the bottom for Add row, Save Table to Wiki
				$cols = 2 + $box->rownum;
				break;
			default:
				$table .= "<table class = 'sortable' ".$this->prettytable($box).">";
				$table .="<tr $box->heading_style>\n<th>".str_replace("\n","</th><th>",$box->headings);
				$table .="</th><th>{{{EDITHEADINGS}}}</th></tr>\n";				
				# make rows
				foreach ($box->rows as $row_index=>$row){
					if($row->is_current){
						$editrowform = 
							$this->form_top().
							$this->form_hidden('row_index',$row_index).
							$this->form_act('editrow');
						# row owner values:
						#	0 public
						#	>0 owner uid
						if (isset($row->owner_uid)) $owner_uid = $row->owner_uid;		
						if($owner_uid == $wgUser->getID() || in_array('bureaucrat', $wgUser->getEffectiveGroups())){
							$editrowform .= $this->form_button('Edit')." ".$this->form_button('Copy')." ".$this->form_button('Delete')."</form>";
						}elseif($owner_uid == 0){	
							$editrowform .= $this->form_button('Edit')." ".$this->form_button('Copy')." ".$this->form_button('Delete')." public</form>";
						}else{
							$editrowform .= 'protected';
						}
						$editrowforms[$row_index] = $editrowform;
						$padding = ''; 
						while (count(explode('||',$row->row_data.$padding)) < $box->colnum){
							$padding .= " || "; 
						}
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						$table .="<tr $row_style ><td>".str_replace("||","</td><td>",str_replace("\n",'',nl2br($row->row_data)).$padding)."</td><td>{{{EDITROWFORM$row_index}}}</td></tr>\n";		
					}
				}
						
				# make buttons on the bottom for Add row, Save Table to Wiki
				$cols = 1 + $box->colnum;
				$table .= "</table>";
		}
		
		# fix and parse output
		$table = str_replace("\'","'", $table);
		$table = str_replace('\n',"\n", $table);
		$output = $wgParser->parse(
			$table,
			$wgTitle,
			ParserOptions::newFromUser( $wgUser )
		);
		$table = $output->getText();
		# put back forms
			$editheadings = '';				
		if(!isset($box->template) ||$box->template =='' ) $editheadings = $this->form_top().$this->form_act('editheadings').$this->form_button('Edit Headings')."</form>";	
		$table = str_replace('>{{{EDITHEADINGS}}}'," class = 'unsortable'>{{{EDITHEADINGS}}}", $table);
		$table = str_replace('{{{EDITHEADINGS}}}',$editheadings, $table);
		foreach ($editrowforms as $row_index=>$form){
			$table = str_replace("{{{EDITROWFORM$row_index}}}", $editrowforms[$row_index],$table); 
			#echo "$row_index $editrowforms[$row_index]\n";
		}
		# add bottom buttons
		$table .="<table><tr><td>".$this->form_button_only('editrow','Add data', $this->form_hidden('new_row','new'))."</td><td> ".
		$this->form_button_only('save',"Save Table to Wiki Page:$box->page_title")."</td><td width = '50%' align = 'right'> ".
		$this->form_button_only('revert',"Revert Table to saved").
		"</td></tr></table>";
	#	$table .= "</table>\n";

	#	$table .= "More messages here";
		if ($box->type <= 1 && $box->template == ''){
			$table .= $this->form_button_only('flip',"Rotate table");
		}
		return $table;
	}
	
	function edit_headings($box){
		switch ($this->submit){
			case 'Add heading':
				$box->headings = $box->headings."\nNew Heading";
				$box->colnum++;
				break;
			case 'Delete last heading':
				$heading_arr = explode("\n",$box->headings);
				array_pop($heading_arr);
				$box->headings = implode("\n",$heading_arr);
				$box->colnum--;
				break;
		}
		$this->serialized = $box->get_serialized();
		$table = $this->form_top();
		$table .= "<table ".$this->prettytable($box).">";
		$headings = explode("\n",trim($box->headings,"\n"));
		$table_headings = '';
		$first_row = '';
		$i = 0;
		foreach ($headings as $heading){
			$i++;
			$table_headings .= "<th $box->heading_style>Heading $i</th>";
			if ($box->template == ''){
				$first_row .= "<td>".$this->form_field($heading)."</td>";
			}else{
				$first_row .= "<td>".$this->form_hidden('field[]', $heading)."$heading</td>";				
			}
		}
		$table .= "<tr $box->heading_style>$table_headings</tr><tr>$first_row</tr>";
		if (!isset($this->style) || $this->style == '') $style = addslashes($box->heading_style);
		$table .= "<tr><td colspan = '$i'>Style ".
			$this->form_value('style', $style, 40)." (e.g. 'bgcolor = #ccccff' to make the heading background light blue)</td></tr>";
		$table .= "<tr><td colspan = '$i'>
					<table><tr><td>".
					$this->form_act('saveheadings').
					$this->form_button('Save').
					"</form>	</td>";
		if ($box->template == ''){
			$table .= 
			"<td>".$this->form_button_only('editheadings','Add heading')."</td>".
			"<td>".$this->form_button_only('editheadings','Delete last heading')."</td>";
		}else{
			$table .="The headings for this box are controlled by the template page <b> Template:".$box->template."</b>. Editing them may change the behavior of boxes on other pages in unexpected ways. However, you can change the style of the heading display for this box without affecting other boxes.";
		}
		$table .= "</tr></table></td></tr></table>\n";
		return $table;
	}

	function edit_row($box){
		global $wgUser;
		$table = ''; $newrow ='';
		$uid = $wgUser->getID();
		$owner_uid = $box->rows[$this->row_index]->owner_uid;	
		if (isset($box->rows[$this->row_index])) $row_data = explode('||',$box->rows[$this->row_index]->row_data);
		if (isset($this->field)) $row_data = $this->field;

		switch($this->submit){ 
			case 'Copy':
				$box->insert_row($box->rows[$this->row_index]->row_data(),$uid);
				$table = $this->make_box($box);	
				return $table;
			case 'Delete':
				if($owner_uid == $uid  || $owner_uid == 0 || in_array('bureaucrat', $wgUser->getEffectiveGroups())){
					$table =  "<br>Row deleted<br>";
					$box->delete_row($this->row_index);
					$table = $this->make_box($box);
					return $table;
				}	
				$table = "<br>Sorry, you don't own that data.<br>";
				$table .= $this->make_box($box);
				return $table;
			case 'Add data':
				# row is not created until save.
				# make an extra input value to pass to save_edit.
				$newrow = $this->form_hidden('new_row','new');
				$owner_uid = $uid;
				$row_data = array();
				break;	
			default:
				if ($this->new_row) $newrow = $this->form_hidden('new_row','new');
		}		
		$table .= 
			$this->form_top().
			$this->form_hidden('row_index',$this->row_index);
		$table .= "<table ".$this->prettytable($box)." align=left>";
		$headings = explode("\n",$box->headings);
		$i = 0; 
		$need_update_button = false;
		foreach ($headings as $heading){
			$table .= "<tr><th $box->heading_style>$heading</th>";
			if (isset($box->column_rules[$i])){
				# apply rules
				$rule_fields = $box->column_rules[$i];
				switch ($rule_fields[0]){
					case 'select':
						$table .= "<td><select name = 'field[]'>";
						$options = array_slice($rule_fields,1);
						foreach ($options as $option){
							$selected = '';
							if (isset ($row_data[$i]) && $option == $row_data[$i]) $selected = 'selected';
							$table .= "<option label='$option' value='$option' $selected>$option</option>";
						}
						$table .= "</select></td></tr>";
						break;
					case 'text':
						$table .= "<td>".$this->form_field($row_data[$i],40,'text')."</td></tr>";						
	 					break;
					case 'lookup':
						$need_update_button = true;
						$dbr =& wfGetDB( DB_SLAVE );
						$sql = $rule_fields[1];
						$j = 0;
						foreach ($row_data as $value){
							$sql = str_replace('{{{'.$box->column_names[$j].'}}}',$value,$sql);
							$j++;
						}
						$result = $dbr->query($sql);
						$x = $dbr->fetchObject ( $result );
						$arr = get_object_vars($x);							
						$dbr->freeResult( $result );
						if (count($arr) == 0){
							$table .= "<td>not found".$this->form_hidden('field[]',"")."</td></tr>";						
						}else{
							$table .= "<td>".$arr[$rule_fields[2]].$this->form_hidden('field[]',$arr[$rule_fields[2]])."</td></tr>";						
						}
						break;
					case 'lookupcalc':
						$need_update_button = true;
						$dbr =& wfGetDB( DB_SLAVE );
						$sql = $rule_fields[1];
						$row_data2 = explode('||',$box->rows[$this->row_index]->row_data);
						$j = 0;
						foreach ($row_data as $value){
							$sql = str_replace('{{{'.$box->column_names[$j].'}}}',$value,$sql);
							$j++;
						}
						$result = $dbr->query($sql);
						$x = $dbr->fetchObject ( $result );
						$arr = get_object_vars($x);						
						$dbr->freeResult( $result );
						if (count($arr) == 0){
							$table .= "<td>not found".$this->form_hidden('field[]',"")."</td></tr>";						
						}else{
							# do calc
							$tmp = $this->do_calc($box, array_slice($rule_fields, 3), $row_data2, $arr[$rule_fields[2]]); 
							$table .= "<td>".$tmp.$this->form_hidden('field[]',$tmp)."</td></tr>";						
						}
						break;
					case 'calc':
						$table .= "<td>". $this->do_calc($box, array_slice($rule_fields, 1),$row_data)."</td></tr>";
						break;
					default:
						$table .= "<td>".$this->form_field($row_data[$i])."</td></tr>";
				}
			}else{
				$table .= "<td>".$this->form_field(@$row_data[$i])."</td></tr>";
			}
			$i++;
		}
		$select_owner = '';#echo"owner: $owner_uid uid:$uid<br>";
		if ($owner_uid > 0  && $owner_uid == $uid && $newrow == ''){
			$select_owner = "<select name='row_owner'>
				<option label='Public' value='0'>Public</option>
				<option label='Private' value='$wgUser->mId' selected>Private</option></select>";
		}elseif($owner_uid == $uid || $newrow !=''){
			$select_owner = "<select name='row_owner'>
				<option label='Public' value='0' selected>Public</option>
				<option label='Private' value='$wgUser->mId' >Private</option></select>";
		}elseif(in_array('bureaucrat', $wgUser->getEffectiveGroups())){
			$select_owner = "<select name='row_owner'>
				<option label='Keep' value='$owner_uid' selected>Keep original owner</option>
				<option label='Public' value='0' selected>Public</option>
				<option label='Private' value='$wgUser->mId' >Private</option></select>";
		}
		$table .= "<tr><td colspan = '2'>".$newrow.
			$select_owner.
			$this->form_act('saverow');
		if($need_update_button) $table .= $this->form_button('Update');
		$table .= $this->form_button('Save').	"</form></td></tr></table>\n";
		if ($newrow !='') $table.= wfMsg('explainOwnerRules');	
		if (isset($owner_id) && ($owner_uid == $uid || $owner_id == 0)) $table .= "<br>".
			"Edit row style:".
			$this->form_value('style',$box->rows[$this->row_index]->row_style, 40);
		$table .= "</form>";	
		return $table;
	}
	function save_row($box){
		global $wgUser;
		$data = implode('||',$this->field); 
		if ($this->new_row == 'new'){
			$row = $box->insert_row($data);
			$this->row_index = $box->rownum;
		}else{
			$row = $box->rows[$this->row_index];
		}
		$row->row_data = $data;
		$i = 0;
		# lookup doesn't execute when new data is entered and you hit save	
		$tmp_row_data = '';
		$row_data = explode('||',$row->row_data);		
		$headings = explode("\n",$box->headings);
		foreach ($headings as $heading){
			if ($i>0) $tmp_row_data .= '||';
			if (isset($box->column_rules[$i])){
				# apply rules
				$rule_fields = $box->column_rules[$i];
				switch ($rule_fields[0]){
					case 'lookup':
						$dbr =& wfGetDB( DB_SLAVE );
						$sql = $rule_fields[1];
						$row_data2 = explode('||',$box->rows[$this->row_index]->row_data);
						$j = 0;
						foreach ($row_data as $value){
							$sql = str_replace('{{{'.$box->column_names[$j].'}}}',$value,$sql);
							$j++;
						}
						$result = $dbr->query($sql);
						$x = $dbr->fetchObject ( $result );
						$arr = get_object_vars($x);						
						$dbr->freeResult( $result );
						if (count($arr) == 0){
							$tmp_row_data .= "";						
						}else{
							$tmp_row_data .= str_replace('_',' ',$arr[$rule_fields[2]]);						
						}
						break;
					case 'lookupcalc':
						$dbr =& wfGetDB( DB_SLAVE );
						$sql = $rule_fields[1];
						$row_data2 = explode('||',$box->rows[$this->row_index]->row_data);
						$j = 0;
						foreach ($row_data as $value){
							$sql = str_replace('{{{'.$box->column_names[$j].'}}}',$value,$sql);
							$j++;
						}
						$result = $dbr->query($sql);
						$x = $dbr->fetchObject ( $result );
						$arr = get_object_vars($x);						
						$dbr->freeResult( $result );
						if (count($arr) == 0){
							$tmp_row_data .= "";						
						}else{
							# do calc
							$tmp = $this->do_calc($box, array_slice($rule_fields, 3),$row_data2,$arr[$rule_fields[2]]);
							$tmp_row_data .= str_replace('_',' ',$tmp);						
						}
						break;
					case 'calc':
						$tmp_row_data .= $this->do_calc($box, array_slice($rule_fields, 1),$row_data);
						break;
					default:
						$tmp_row_data .= $row_data[$i];
				}
			}else{
				$tmp_row_data .= $row_data[$i]; 
			}
			$i++;
		}
		$row->row_data = $tmp_row_data; 
		$row->row_style = $this->style;			
		$row->owner_uid = $this->row_owner;	
		$table = $this->make_box($box);	
		return $table;
	}
	function do_calc($box, $calc_arr, $row_data, $inputstring=''){
		$string = '';
		switch ($calc_arr[0]){
			case 'split':
				$tmp = explode($calc_arr[1],$inputstring);
				$fields = array_slice($calc_arr,2); 
				foreach ($fields as $field){
					$string .= $tmp[$field].' ';
				}
				break;
			case 'reqcomplete':
				$string = 'complete';
				$fields = array_slice($calc_arr,1);
				foreach ($fields as $field){
					for ($i = 0; $i<= $box->colnum; $i++){
						if ($box->column_names[$i] == $field && (!isset($row_data[$i]) || trim($row_data[$i]) == '')) $string = 'required field missing';
					}	
				}
				break;
			default:
				$string = implode(";", $calc_arr);
		
		}
		return trim(str_replace('_',' ',$string));
	}
	function form_top(){
		return "<form method='post'>".
			$this->form_hidden('id',$this->table_id);
		#	$this->form_hidden('serialized',$this->serialized)."\n";
	}	
	function form_field($value, $size=40, $type='textarea'){
		if ($type == 'textarea') return "<textarea name='field[]' cols='$size' rows='4'>$value</textarea>";
		return	"<input name='field[]' type='text' value='$value' size='$size' maxlength='255'>\n";
	}
	function form_value($name, $value, $size=20){
		return	"<input name='$name' type='text' value='$value' size='$size' maxlength='255'>\n";
	}
	function form_act($act){
		return	"<input name='act' type='hidden' value='$act'>";
	}
	function form_hidden($name, $value){
		return	"<input name='$name' type='hidden' value='".$value."'>";
	}
	function form_button($submit){
		return	"<input name='submit' type='submit' value='$submit'>";
	}
	function form_button_only($act, $submit, $extra =''){
		$form = $this->form_top();
		if ($extra !='') $form .= $extra;
		$form .= $this->form_act($act).
			$this->form_button($submit)."</form>";
		return $form;
	}
	function prettytable($box){
		$table_style = 'border="2" cellpadding="4" cellspacing="0" style="margin: 1em 1em 1em 0; border: 1px #aaa solid; border-collapse: collapse;"';
		if (isset($box->box_style)) $table_style .= $box->box_style;
		return $table_style;
	}
	function check_conflict($box){
		$box2 = new wikiBox();
		$box2->box_uid = $box->box_uid;
		$box2->set_from_DB();
		if ($box2->timestamp == $box->timestamp) return true;
		foreach ($box2->rows as $row2){
			$match = false;
			foreach ($box->rows as $row){
				if ($row2->row_id == $row->row_id){
					$match = true;
					break;
				}
			}
			if (!$match){
				# add the row to our working set
				$newRow = $box->insert_row($row2->row_data,$row2->owner_uid,$row2->row_style);
				$newRow->row_id = $row2->row_id;
				
			}
		}
		return false;
	}
	
	function display_conflict($box){
		global $wgUser;
		if ($this->headings != '') $box->headings = $this->headings;
		if ($this->heading_style != '') $box->heading_style = $this->heading_style;
		if ($this->type != '') $box->type = $this->type;
		if ($this->row_index != ''){
			$box->rows[$this->row_index]->is_current = $box->rows[$this->row_index]->set_fromDb();	
		}	
		$this->serialized = $box->get_serialized();
		$box2 = new wikiBox();
		$box2->box_uid = $box->box_uid;
		$box2->set_from_DB();
		$table = "The box has been edited since you started working on it.<br>";
		$table .= "Saved version:<br>".$this->display_box($box2)."";
		$table .= "Suggested resolution:<br>";
		
		$table .= "<table ".$box->box_style.">";
		switch ($box->type){
			case 1:
				$tablerows = array();
				$headings = explode("\n", $box->headings);
				$i = 0;
				foreach ($headings as $heading){
					$i++;
					$tablerows[$i] = "<tr><th $box->heading_style>$heading</th>";
				}
				$i++;
				$tablerows[$i] = "<tr><td>".
				
				$tablerows[$i] .= "</td>";
				foreach ($box->rows as $row_index=>$row){
					foreach ($box2->rows as $row2){
						if ($row2->row_id == $row->row_id) break;
					}
					if($row->is_current || $row2->is_current){
						$editrowform = 
							$this->form_top().
							$this->form_hidden('row_index',$row_index).
							$this->form_act('update_conflict');
						# row owner values:
						#	0 public
						#	>0 owner uid
						if ($row2->row_data != $row->row_data || $row2->is_current != $row->is_current){
							if (isset($row->owner_uid)) $owner_uid = $row->owner_uid;		
							if($owner_uid == $wgUser->getID() || in_array('bureaucrat', $wgUser->getEffectiveGroups()) || $owner_uid == 0){
								$editrowform .= $this->form_button('Revert to Saved')."</form>";
							}else{
								$editrowform .= 'protected';
							}
						}
						$data = explode('||',$row->row_data);
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						for ($j=1; $j<$i; $j++){
							$tablerows[$j] .= "<td>".$data[$j-1]."</td>";
						}
						$tablerows[$i] .= "<td>$editrowform</td>";
					}
				}				
				foreach ($tablerows as $tablerow){
					$table .= "$tablerow</tr>\n";
				}
				# make buttons on the bottom for Add row, Save Table to Wiki
				$cols = 1 + $box->colnum;
				$table .= "<tr valign = 'top'><td align = 'center' colspan = '$cols'>
				<table><tr>\n".
				"<td>".$this->form_button_only('force_save',"Save Table to Wiki Page:$box->page_title").
				"</td></tr></table>
				</td></tr>";
				break;
			default:
				$table .="<tr $box->heading_style>\n<th>".
				str_replace("\n","</th><th>",$box->headings)."</th><th></th>";
				$table .= "</tr>\n";
		
				# make rows
				foreach ($box->rows as $row_index=>$row){
					foreach ($box2->rows as $row2){
						if ($row2->row_id == $row->row_id) break;
					}

					if($row->is_current || $row2->is_current){
						$editrowform = 
							$this->form_top().
							$this->form_hidden('row_index',$row_index).
							$this->form_act('update_conflict');
						# row owner values:
						#	0 public
						#	>0 owner uid
						if ($row2->row_data != $row->row_data || $row2->is_current != $row->is_current){
							if (isset($row->owner_uid)) $owner_uid = $row->owner_uid;		
							if($owner_uid == $wgUser->getID() || in_array('bureaucrat', $wgUser->getEffectiveGroups()) || $owner_uid == 0){
								$editrowform .= $this->form_button('Revert to Saved')."</form>";
							}else{
								$editrowform .= 'protected';
							}
						}
						$padding = ''; 
						while (count(explode('||',$row->row_data.$padding)) < $box->colnum){
							$padding .= " || "; 
						}
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						$table .="<tr $row_style ><td>".str_replace("||","</td><td>",$row->row_data.$padding)."</td><td>$editrowform</td></tr>\n";		
					}
				}
						
				# make buttons on the bottom for Add row, Save Table to Wiki
				$cols = 1 + $box->colnum;
				$table .= "<tr valign = 'top'><td align = 'center' colspan = '$cols'>
				<table><tr>\n".
				"<td>".$this->form_button_only('force_save',"Save Table to Wiki Page:$box->page_title").
				"</td></tr></table>
				</td></tr>";
		}
		$table .= "</table>\n";

	#	$table .= "More messages here";
	
		if ($box->template == ''){
			if ($box->headings != $box2->headings) $table .= $this->form_button_only('update_conflict','Use saved headings',$this->form_hidden('headings',$box2->headings))."<br>";
			if ($box->heading_style != $box2->heading_style) $table .= $this->form_button_only('update_conflict','Use saved heading style',$this->form_hidden('heading_style',$box2->heading_style))."<br>";
			if ($box->type != $box2->type) $table .= $this->form_button_only('update_conflict','Use saved orientation',$this->form_hidden('type',$box2->type))."<br>";

		}
		
		
		return $table;
	}

	function display_box($box){
		global $wgUser;
		$table = "<table ".$this->prettytable($box).">";
		switch ($box->type){
			case 1:
				$tablerows = array();
				$headings = explode("\n", $box->headings);
				$i = 0;
				foreach ($headings as $heading){
					$i++;
					$tablerows[$i] = "<tr><th $box->heading_style>$heading</th>";
				}
				foreach ($box->rows as $row_index=>$row){
					if($row->is_current){
						$data = explode('||',$row->row_data);
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						for ($j=1; $j<=$i; $j++){
							$tablerows[$j] .= "<td>".str_replace("\n",'',nl2br($data[$j-1]))."</td>";
						}
					}
				}				
				foreach ($tablerows as $tablerow){
					$table .= "$tablerow</tr>\n";
				}
				break;
			default:
				$table .="<tr $box->heading_style>\n<th>".
				str_replace("\n","</th><th>",$box->headings)."</th></tr>\n";
		
				# make rows
				foreach ($box->rows as $row_index=>$row){
					if($row->is_current){
						$padding = ''; 
						while (count(explode('||',$row->row_data.$padding)) < $box->colnum){
							$padding .= " || "; 
						}
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						$table .="<tr $row_style ><td>".str_replace("||","</td><td>",str_replace("\n",'',nl2br($row->row_data)).$padding)."</td></tr>\n";		
					}
				}
		}
		$table .= "</table>\n";
	#	$table .= "More messages here";
		return $table;
	}
	
	function make_wikibox($box){
		global $wgScript, $wgUser;
		$delimiter = "<!--box uid=$box->box_uid-->";		
		$table = '';
		$warning = wfMsg('pleaseDontEditHere');
		$editlink = str_replace('//','/',"[{{SERVER}}/$wgScript?title=Special:TableEdit&id=$box->box_uid&page=$box->page_uid&pagename={{FULLPAGENAMEE}} ".wfMsg('tableEditEditLink')."]");
		if(!isset($box->box_style) || $box->box_style == '') $box->box_style = '{{Prettytable}}';
		switch ($box->type){
			case 1:
				$table .= "\n{|".$box->box_style."\n";
				$tablerows = array();
				$headings = explode("\n", $box->headings);
				$i = 0;
				foreach ($headings as $heading){
					$i++;
					$tablerows[$i] = "|-\n!$box->heading_style |$heading\n";
				}
				$i++;
				foreach ($box->rows as $row_index=>$row){
					if($row->is_current){
						$data = explode('||',$row->row_data);
						$row_style = '';
						if (isset($row->row_style)) $row_style = $row->row_style;
						for ($j=1; $j<$i; $j++){
							$tablerows[$j] .= "|\n".@$data[$j-1]."\n";
						}
					}
				}		
				foreach ($tablerows as $tablerow){
					$table .= "$tablerow";
				}
				$cols = $box->rownum() + 1; 
				$table .= "|-\n|colspan='$cols'|$editlink\n|}\n";
				break;
			default:
				$table .= "\n{|class = 'sortable' ".$box->box_style." id='".$box->box_id."' \n";
				$headings = "|-";
				if (isset($box->heading_style) && $box->heading_style != '') $headings .= " $box->heading_style";
				$headings .= "\n!".str_replace("\n","!!",$box->headings)."\n";
				
				$table .= $headings;
				foreach ($box->rows as $row){
					if ($row->is_current){
						$table .="|-\n|\n";
						if (isset($row->row_style) && $row->row_style != '') $table .= " $row->row_style";
						$padding = ''; 
						while (count(explode('||',$row->row_data.$padding)) < $box->colnum){
							$padding .= " || "; 
						}
						$data = $row->row_data." $padding\n";
						$table .= str_replace('||',"\n|\n",$data);
					}
				}
				$table .= "|-class='sortbottom'\n|$editlink";
				for ($i = 1; $i < $box->colnum; $i++) $table .= " ||";
				$table .= "\n|}\n";
		}
		$table = str_replace("\'","'",$table);
		$table = str_replace('\n',"\n",$table);
		wfRunHooks( 'TableEditBeforeSave', array( &$this, &$table ) );
		$replacement = $delimiter.$warning.$table.$delimiter;
		return $replacement;			
	}
	function save_to_page($title, $box){
		global $wgScript, $wgUser, $wgCommandLineMode;
		if ($wgUser->isAllowed('edit') || $wgCommandLineMode){
			if (isset($this->serialized)) $box->set_from_serialized($this->serialized);	
	# 1. construct table/box
			$replacement = $this->make_wikibox($box);
	# 2. Get the old text
	# 3. Get the old table
			$this->get_old_table($title, $box);
	# 4. Replace the old table with the new table to make new text
			$new_page = str_replace($this->old_table, $replacement, $this->old_page_text);
			if (trim($this->old_page_text) != trim($new_page)){
				$article = new Article($title);
	# 5. Save to the page
				# check again that the page doesn't already exist (just in case)
				$article->doEdit( $new_page, "edited by $wgUser->mName via TableEdit", EDIT_UPDATE | EDIT_FORCE_BOT );
			}
	# 6. Tell the box to save itself to the database
			$box->save_to_db();	
		} # end if $wgUser->isAllowed
		if (isset($this->table_id)) unset($_SESSION['TableEditData.'.$this->table_id]);
	# 7. Redirect to the changed page
		$article = new Article($title);	
		$article->doRedirect();
	}
	
	function recover_wiki_table($title, $box){
		$old_table = $this->get_old_table($title, $box);
		# TODO - figure out how to do this!
		return true;	
	}
	
	function get_old_table($title,$box){
		#get the old page
		$old_page = Revision::newFromTitle($title);
		$this->old_page_text = $old_page->getText(); 
		$delimiter = '<\!--box uid='.str_replace('.','\.', $box->box_uid).'-->';	# ! escaped in regex	
		$pattern = "!$delimiter(.*)$delimiter!is";				
		preg_match ($pattern,$this->old_page_text, $matches);
		# something is there
		$this->old_table = $matches[0]; #echo "<pre>$pattern\n\n";print_r($matches);echo "</pre>";
		return $this->old_table;
	}
	function loadMessages() {
		static $messagesLoaded = false;
		global $wgMessageCache;
		if ( $messagesLoaded ) return;
		$messagesLoaded = true;

		require( dirname( __FILE__ ) . '/SpecialTableEdit.i18n.php' );
		foreach ( $allMessages as $lang => $langMessages ) {
			$wgMessageCache->addMessages( $langMessages, $lang );
		}
		foreach( $wgTableEditMessages as $key => $value ) {
			$wgMessageCache->addMessages( $wgTableEditMessages[$key], $key );
		}
		return true;
	}
}
?>