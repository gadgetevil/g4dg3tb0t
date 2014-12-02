<?php
mysql_connect('localhost', 'root', '');
mysql_select_db('ircbot');
$result = mysql_query("SELECT text FROM ChatLog");
echo "<table border='0' width='100%'>";
while($row = mysql_fetch_row($result)) {
	foreach($row as $line) {
    	echo "<tr><td class=\"ircline\">$line</td></tr>\n";
	}
}
mysql_free_result($result);
?>