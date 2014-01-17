<?php
/*
Need to refactor this to deal with new vs. preexisting box (right now - 200703010 - TableEdit makes new box directly)
changes:
v 0.7 
	change wikibox_db to a variable; row updates its row_id on save
v 0.4
	change insert statement to use null for autocomplete field
v 0.3
	add support for extra column rules via template.  Template headings are in rows; add extra info 
	delimited by ||
v 0.2 
	serialize data persistence
*/
class wikiBox{

	function __construct($id = null){
		global $wgUser, $wgTableEditDatabase;			
		#set up variables
		$this->box_id = null;
		$this->template = null;
		$this->page_name = null;
		$this->page_uid = null;
		$this->box_uid = $id;
		$this->type = 0;
		$this->headings = null;
		$this->heading_style = null;
		$this->box_style = null;
		$this->timestamp = null;
		$this->uid = null;
		$this->rows = array();
		$this->column_names = array();
		$this->column_rules = array();
		$this->colnum = null;
		$this->rownum = null;
		if (isset($wgUser)){
			$this->uid = $wgUser->getID();
		}else{
			$this->uid = 0;
		}
		return;
	}
	function set_from_DB(){
		global $wgTableEditDatabase;			
		if(is_null($this->box_uid)) return;
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = "select * from $wgTableEditDatabase.box WHERE box_uid = '$this->box_uid'";
		$result = $dbr->query($sql);
		if (!$result) return false;  ("Error:That box is not in the database");
		$i = 0; 
		if (count($dbr->numRows($result)) > 1) return false;  ("Error:box_id should be unique");
		$x = $dbr->fetchObject ( $result );
		$arr = get_object_vars($x);
		foreach ($arr as $key=>$val){
			$this->$key = $val; 
		}
		
		# Get information about the table headings.  Headings can come from either a template page or from a field in the box table.  
		# If a template is specified, it gets precedence.

		$templatePage = Revision::newFromTitle(Title::makeTitle(NS_TEMPLATE, $this->template));
		if (! $templatePage){
			#echo "headings:$this->headings";
		}else{
			$template_text = trim($templatePage->getText());
			$template_text = '<xml>'.$template_text.'</xml>';
			$xml_parser = xml_parser_create();
			$parse = xml_parse_into_struct($xml_parser, $template_text, $values, $index); 			
			xml_parser_free($xml_parser);
			if (isset ($index['HEADINGS'])){
				$heading_list = $values[$index['HEADINGS'][0]]['value'];
			}else{
				$heading_list = $values[$index['XML'][0]]['value'];
			}
			if (isset ($index['HEADING_STYLE'])) $this->heading_style = trim($values[$index['HEADING_STYLE'][0]]['value']);
			if (isset ($index['TABLE_STYLE'])) $this->box_style = trim($values[$index['TABLE_STYLE'][0]]['value']);
			if (isset ($index['BOX_STYLE'])) $this->box_style = trim($values[$index['BOX_STYLE'][0]]['value']);
			if (isset ($index['STYLE'])) $this->heading_style = trim($values[$index['STYLE'][0]]['value']);
			if (isset ($index['COLUMN_STYLE'])) $this->column_style = trim($values[$index['COLUMN_STYLE'][0]]['value']);
			if (isset ($index['TYPE'])) $this->type(trim($values[$index['TYPE'][0]]['value']));
			$heading_list = explode("\n",trim($heading_list));
			$this->headings = "";
			$i = 0;
			foreach ($heading_list as $heading){
				$tmp = explode("||", $heading);
				$this->headings .= $tmp[0]."\n";
				if (isset($tmp[1])){
					$tmp2 = explode('|', $tmp[1]);
					$this->column_names[$i] = array_shift($tmp2);
					$this->column_rules[$i] = $tmp2;
				}else{
					$this->column_names[$i] = $heading;
				}
				$i++;
			}
			$this->headings = trim($this->headings);
		}
		$this->colnum = substr_count($this->headings,"\n")+1; 
		$this->rows = $this->make_rows_from_DB();
		$dbr->freeResult( $result );
		return true;
	}
	
	function set_from_serialized($data){
		$arr = unserialize($data);
		foreach ($arr as $key=>$val){
			$this->$key = $val;
		}
		$this->colnum = substr_count($this->headings,"\n")+1; 
		return true;
	}
	
	function get_serialized(){
		$boxVars = array('box_id','page_name', 'page_uid','box_uid','template','headings','heading_style','rows','timestamp','type','column_names','column_rules','rownum', 'table_style');
		foreach ($boxVars as $var){
			$arr[$var] = $this->$var;
		}
		return (serialize($arr));
	}
	
	function make_rows_from_DB(){
		global $wgTableEditDatabase;			
		# Get the row data
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = "SELECT * FROM $wgTableEditDatabase.row WHERE box_id = '$this->box_id' ORDER BY row_sort_order";
		$result = $dbr->query($sql);
	#	print_r($result);
		$rows = array();
		if (!$result){ 
			$rows[0] = '';
		}else{
			$i = 0;
			while( $x = $dbr->fetchObject ( $result ) ) {
				$row = new wikiBoxRow;
				#print_r($x);
				$arr = get_object_vars($x);
				foreach ($arr as $key=>$val){
					$row->$key = $val;
				}
				$row->row_sort_order = $i;
				$row->row_index = $i;
				$rows[] = $row;
				$this->rownum = $i;
				$i++;
			}
		}
		$dbr->freeResult( $result );
		return $rows;
	}
	function type($type){
		if(isset($type)) $this->type = $type;
		return $this->type;
	}

	function rownum(){
		return count($this->rows);
	}
	function insert_row($data,$owner='',$style=''){
		$row = new wikiBoxRow;
		$row->box_id = $this->box_id; #echo "making new row";
		$row->row_data = $data;
		$row->owner_uid = $owner;
		$row->row_index = $this->rownum();
		$this->rownum++;
		$row->row_sort_order = $this->rownum;
		$this->rows[] = $row;
		return $row;
	}

	function delete_row($row_id){
		$this->rows[$row_id]->delete_row();
		return;
	}

	function save_to_db(){
		global $wgTableEditDatabase;			
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = "UPDATE $wgTableEditDatabase.box SET 
			template ='".mysql_real_escape_string($this->template)."',".
			"page_name ='".mysql_real_escape_string($this->page_name)."',".
			"page_uid ='".mysql_real_escape_string($this->page_uid)."',".
			"box_uid ='".mysql_real_escape_string($this->box_uid)."',".
			"type ='".mysql_real_escape_string($this->type)."',".
			"headings ='".mysql_real_escape_string($this->headings)."',".
			"heading_style ='".mysql_real_escape_string($this->heading_style)."',".
			"box_style ='".mysql_real_escape_string($this->box_style)."',".
			"timestamp ='".time()."'".
			" WHERE box_id = '$this->box_id'";
		$result = $dbr->query($sql); #echo "<pre>";print_r($this->rows);echo "</pre>";
		foreach ($this->rows as $key=>$row){
			if (is_int($key)) $row->db_save_row(); # somehow a non-wikiboxrow gets added to the row array.
		}
		return;
	}

}

class wikiBoxRow{

	# note that row_id is the row_id in the database.  This may not be the same as the row index in box->rows[row_id]
	function __construct($box_id = 0, $row_id = null){
		global $wgTableEditDatabase;			
		$this->box_id = $box_id;
		$this->row_data = null;
		$this->row_style = null;
		$this->row_sort_order = null;
		$this->timestamp = null;
		$this->is_current = true; #set to false when deleting a row
		$this->row_index = null;
		return;
	}

	function set_fromDb(){
		global $wgTableEditDatabase;			
		if ($this->row_id === null || $this->box_id == 0) return;
		#check both box_id and row_id in case of screwups
		$dbr =& wfGetDB( DB_SLAVE );
		$sql = "select * FROM $wgTableEditDatabase.row WHERE box_id = '$this->box_id' and row_id = '$this->row_id'";
		$result = $dbr->query($sql);
		if (!$result || count($dbr->numRows($result)) == 0){
			$this->is_current = false;
			return false;
		}
		if (count($dbr->numRows($result)) > 1) return; #("Error:box_id should be unique");;

		$x = $dbr->fetchObject ( $result );
		$arr = get_object_vars($x);
		foreach ($arr as $key=>$val){
			$this->$key = $val;
		}
		$this->is_current = true;
		$dbr->freeResult( $result );
		return true;
	}
	
	function db_save_row(){
		global $wgTableEditDatabase;			
		# $this->row_id set when data previously pulled from database
		# for a row only set in temp space, should be undef
		$dbr =& wfGetDB( DB_SLAVE ); 
		if ($this->row_data == '') return; # don't save rows with no data or || delimiters
		$this->row_data = mysql_real_escape_string($this->row_data);		
		if (!$this->row_id){
			$sql = "INSERT INTO $wgTableEditDatabase.row VALUES(
				null,
				'$this->box_id',
				'$this->owner_uid',
				'$this->row_data',
				'$this->row_style',
				'$this->row_sort_order',
				'".time()."'
				)";
			$result = $dbr->query($sql); 
			$this->row_id = $dbr->insertId();
		}elseif($this->is_current === true){
			# it's in the DB and it's current, update it.
			$sql = "UPDATE $wgTableEditDatabase.row SET 
				owner_uid='$this->owner_uid',
				row_data='$this->row_data', 
				row_style = '$this->row_style',
				row_sort_order = '$this->row_sort_order',
				timestamp = '".time()."'
				WHERE row_id = '$this->row_id'";
			$result = $dbr->query($sql); 
		}else{
			#it's in the DB but it's not current.  Delete it from the DB
			$sql = "DELETE FROM $wgTableEditDatabase.row WHERE row_id = '$this->row_id'"; 
			$result = $dbr->query($sql); 
		}
	return;		
	}
	#delete and undelete only work on the temporary row info, not on the db!
	function row_data(){
		return $this->row_data;
	}

	function delete_row(){
		$this->is_current = null;
	}

	function undelete_row(){
		$this->is_current = true;
	}

}
#		echo "<br><br><br><br><br><br><br><br><br><br>This after construct:<pre>";print_r($this);echo "</pre><br>";

?>