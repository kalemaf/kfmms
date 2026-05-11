<?php
 function create_combo($database, $table, $control_name, $selected, $first) {
  global $userName ;
  global $password ;
  global $hostName ;
  global $connection;

/*  personnel_selection.inc.php - Chris Morris cmorris@pictsweet.com   */
/*  function for creating HTML code for a form selection control where  */
/*  each option is a row in a mysql database table */ 
/*  arguments are (database name to query, table to query, name of the control) */                    

/*  use existing mysqli connection from config.inc.php */

/*  make an array to hold the rows  */
 $arr = array();

/*  construct a query */
 if ($table == 'equipment') {
   $sql = "SELECT id, description FROM $table ORDER BY description ASC";
 } else {
   $sql = "SELECT id, lname, fname FROM $table ORDER BY lname ASC";
 }

/*  execute the query */
   $query = mysqli_query($connection, $sql) or die("Query Failed <br> " . mysqli_error($connection));

/*  package each row of the results in an array */
 while($op_row = mysqli_fetch_object($query)) {
    array_push($arr, $op_row);
  }

/*  make the HTML code for a selection box of names*/

 $str="<select name=\"$control_name\">";

 /* if a first row was used (i.e. 'pick a mechanic...', make an entry for it*/

 if(isset($first)){$str .= "<option value=\"0\">$first</option>";}

foreach ($arr as $ele){
  
  $str .= "<option value=\"$ele->id\" ";

  if (($ele->id == $selected) && $selected != 0){$str .= "selected=\"selected\"";}

  $str .= ">";

  if ($table == 'equipment') {
    $str .= $ele->description;
  } else {
    $str .= $ele->lname;
    if(!$ele->lname== "" && !$ele->fname== ""){ $str .= ", ";} //if there is no last name do not add a comma
    $str .= $ele->fname;
  }

  $str .= "</option>\n";
}

 $str .= "</select> ";

 return $str;
}

?>
