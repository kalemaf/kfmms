<?php
//no need in reinventing the wheel, eh?
#############################################################
#
# -=[ MySQL Search Class ]=-
#
#      version 2 
#
# (c) 2002 Stephen Bartholomew
#
# Functionality to search through a MySQL database, across
# all columns, for multiple keywords
#
# Usage:
#
#    Required:
#        $mysearch = new MysqlSearch;
#        $mysearch->set_Identifier("MyPrimaryKey");
#        $mysearch->set_SearchTable("MyTable");
#        $results_array = $mysearch->find($mysearchterms);
#
#    Class Variables:
#        Will return the number of entries found
#        $mysearch->get_NumResults();
#
#        Will return the mysql error string if any errors are found
#        $mysearch->get_ErrorStr();
#
#    Optional:
#        This will force the columns that are searched
#        $mysearch->setsearchcolumns("Name, Description");
#
#             Set the ORDER BY attribute for SQL query
#            $mysearch->set_OrderBy("Name");
#
##############################################################

class MysqlSearch
{
    // Properties
    public $error_str;
    public $num_results;
    public $searchtable;
    public $searchcolumns;
    public $orderby;
    public $entry_identifier;

    function __construct()
    {
        $this->error_str = "";
        $this->num_results = 0;
        $this->searchtable = "";
        $this->searchcolumns = "";
        $this->orderby = "";
        $this->entry_identifier = "";
    }

    function set_ErrorStr($error)
    {
        $this->error_str = $error;
    }

    function get_ErrorStr()
    {
        return $this->error_str;
    }
	
    function get_NumResults()
    {
	return $this->num_results;
    }

    function set_SearchTable($table)
    {
        # Set which table we are searching
        $this->searchtable = $table;
    }

    function set_SearchColumns($columns)
    {
        $this->searchcolumns = $columns;
    }

    function set_OrderBy($orderby)
    {
        $this->orderby = $orderby;
    }

    function set_Identifier($entry_identifier)
    {
        # Set the db entry identifier
        # This is the column that the user wants returned in
        # their results array.  Generally this should be the
        # primary key of the table.
        $this->entry_identifier = $entry_identifier;
    }

    // Methods
    function find($keywords)
    {
        # Create a keywords array
        $keywords_array = explode(" ",$keywords);

        # Select data query
        if(!$this->searchcolumns)
        {
            $this->searchcolumns = "*";
        }

        //$search_data_sql = "SELECT ".$this->searchcolumns.",".$this->entry_identifier." FROM ".$this->searchtable;
	$search_data_sql = "SELECT wo. * , m.lname, m.fname, m.id, p.description as description  FROM work_orders AS wo LEFT JOIN mechanics AS m ON wo.mechanic_id = m.id LEFT JOIN priority AS p ON wo.priority = p.priority ";

        # Add an ORDER BY statement
        # if setorderby() has been called
        if($this->orderby)
        {
                $search_data_sql .= " ORDER BY ".$this->orderby;
        }

        # Run query, assigning ref
        global $connection;
        $search_data_ref = mysqli_query($connection, $search_data_sql);

        # catch any errors
        if(!$search_data_ref)
        {
            $this->error_str = mysqli_error($connection);
        }

        # Define $search_results_array, ready for population
        # with refined results
        $search_results_array = array();

        if($search_data_ref)
        {
            while($all_data_array = mysqli_fetch_array($search_data_ref))
            {
                # Initialize the keywords_found_array for each entry
                $keywords_found_array = array();

                # Get an entry indentifier (guard missing keys)
                $my_ident = null;
                if ($this->entry_identifier !== '' && isset($all_data_array[$this->entry_identifier])) {
                    $my_ident = $all_data_array[$this->entry_identifier];
                } else {
                    // fallback: use first value if identifier not present
                    $vals = array_values($all_data_array);
                    $my_ident = isset($vals[0]) ? $vals[0] : null;
                }

                # Cycle each value in the product entry
                foreach($all_data_array as $entry_key=>$entry_value)
                {
                    # Cycle each keyword in the keywords_array
                    foreach($keywords_array as $keyword)
                    {
                        # If the keyword exists...
                        if($keyword)
                        {
                            # Check if the entry_value contains the keyword

                            if(!is_null($entry_value) && stristr($entry_value,$keyword))
                            {
                                # If it does, increment the keywords_found_[keyword] array value
                                # This array can also be used for relevence results
                                $keywords_found_array[$keyword]++;
                            }
                        }
                        else
                        {
                            # This is a fix for when a user enters a keyword with a space
                            # after it.  The trailing space will cause a NULL value to
                            # be entered into the array and will not be found.  If there
                            # is a NULL value, we increment the keywords_found value anyway.
                            $keywords_found_array[$keyword]++;
                        }
                        unset($keyword);
                    }

                    # Now we compare the value of $keywords_found against
                    # the number of elements in the keywords array.
                    # If the values do not match, then the entry does not
                    # contain all keywords so do not show it.
                    if(sizeof($keywords_found_array) == sizeof($keywords_array))
                    {
                        # If the entry contains the keywords, push the identifier onto an
                        # results array, then break out of the loop.  We're not searching for relevence,
                        # only the existence of the keywords, therefore we no longer need to continue searching
                        array_push($search_results_array,"$my_ident");
                        break;
                    }
                }
                unset($keywords_found_array);
                unset($entry_key);
                unset($entry_value);
            }
        }

        $this->num_results = sizeof($search_results_array);
        # Return the results array
        return $search_results_array;
    }
}

?>