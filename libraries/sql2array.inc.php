<?php

function sql2array($sql, $connection, $arr_type = MYSQLI_BOTH)
{

  $result = mysqli_query($connection, $sql) or die('Query Failed: ' . mysqli_error($connection));

  $rows = array();

  while($row = mysqli_fetch_array($result, $arr_type))  //store the resulting records as an array
{
  array_push($rows, $row);
}

  return($rows);
}