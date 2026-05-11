<?PHP

class Menu {
  /**
   * Array of categories
   * @var    array
   * @access private
   */
  public $_categories = array();

  /**
   * Array of permissible link for this person
   * @var    array
   * @access public
   */
  public $permissible = array();

  /**
   * Adds a category box to the menu
   * @param   string    $name
   * @access  public
   */
  function addCategory($name)
    {
      $this->_categories[$name] = array();

      return $name;
    }

  /**
   * Adds a link to the menu
   * @param   string    $link
   * @param   string    $cat
   * @access  public
   */
  function addLink($link, $cat = "")
    {
      if(empty($cat)) //if no category was passed to the function use the last category 
	{
	  end($this->_categories);
	  $cat = key($this->_categories);
	}

      
      if(!isset($this->_categories["$cat"])) //if the category doesn't exist yet
	{
	  $this->addCategory($cat);
	}

      array_push($this->_categories["$cat"], $link);
      
    }
  /**
   * Adds a simple text link to the menu  
   * @param   string   the display text of the link
   *          string   the link target
   *          string   the frame target
   * @access  public
   */
  function addSimpleLink($link, $target, $frame="", $cat = "")
    {
      if(empty($cat)) //if no category was passed to the function use the last category 
	{
	  end($this->_categories);
	  $cat = key($this->_categories);
	}

      
      if(!isset($this->_categories["$cat"])) //if the category doesn't exist yet
	{
	  $this->addCategory($cat);
	}


      if(!empty($frame))
    {
      $frame = "target=\"$frame\"";
    }

      // Build onclick that navigates the current window (no frames)
      $onclick = 'onclick="window.location.href=\'' . $target . '\'; return false;"';

      $link_html = "<a href=\"$target\" $frame " . $onclick . ">$link</a><br>";

      array_push($this->_categories["$cat"], $link_html);
	      
    }

  function toHtml()
    {
      $html = ""; // Initialize $html before use
      foreach($this->_categories as $cat=>$cat_contents)
	{
	  if(!empty($cat_contents)) //if there are links in this category...
	    {
	      $cat_class = strtolower(str_replace([' ', '&'], ['', ''], $cat)); // Create CSS class from category name
	      $html .= "\n <div class=\"box\"> \n 
                   <H5 class=\"cat-$cat_class\">$cat</H5> \n 
                       <div class=\"body\"> \n 
                           <div class=\"content odd\">";

	      $html .= implode("\n", $cat_contents);
	  
	      $html .= "</div></div></div>";
	    }
	}

      return $html;
    
    }


}


?>