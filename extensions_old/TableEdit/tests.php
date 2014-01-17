<pre>Hello!
<?php

#require "class.wikiBox.php";

$box = new wikiBox();
#echo "new box\n";
#print_r($box->rows);
$box->box_id = 7;
$box->set_from_DB();
#echo "\n set from DB\n";
#print_r($box->rows);
$box->page_title = 'Foo';
$box->insert_row("a new temp row");
#echo "\n Insert a new row\n";
#print_r($box->rows);
$box->delete_row(1);
#echo "\n delete row 1\n";

echo "Before\n";
#print_r($box);
$stored = $box->get_serialized();
$box = new wikiBox();
echo "==========================\nNew box\n";
print_r($box);

$box->set_from_serialized($stored);
echo "==========================\nAfter\n";
print_r($box);


?>



Done.
</pre>