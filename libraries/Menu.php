class Menu {
    /**
     * Array of categories
     * @var    array
     * @access private
     */
  var $_categories = array();

    /**
     * Adds a category box to the menu
     * @param   string    $name
     * @access  public
     */
  function addCategory($name)
    {
      $$name = array();
      array_push($this->_categories, $$name);
      return $name;
    }

    /**
     * Adds a link to the menu
     * @param   string    $name
     * @access  public
     */
  function addLink($link, $cat)
    {
     array_push($this->_categories[$cat], $link);
    }

  function toHtml()
    {
      $str = print_r($this->_categoies);
    }
}