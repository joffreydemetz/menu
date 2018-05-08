<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Menu;

use stdClass;

/**
 * Menu
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Menu  
{
  /** 
   * Active route
   * 
   * @var   string
   */
  protected $activeRoute;
  
  /** 
   * Root node
   * 
   * @var   MenuNode
   */
  protected $_root;
  
  /** 
   * Current node
   * 
   * @var   MenuNode
   */
  protected $_current;
  
  /** 
   * Mechanical dropdown
   * 
   * @var   bool
   */
  protected $dropdown = true;
  
  /** 
   * Dropdown class name
   * 
   * @var   string
   */
  protected $dropdownClass = 'dropdown';
  
  /** 
   * List children
   * 
   * @var   bool
   */
  protected $showChildren = true;
  
  /** 
   * Only return submenus
   * 
   * @var   bool
   */
  protected $onlyChildren = null;
  
  /** 
   * Only show node classes
   * 
   * @var   array
   */
  protected $onlyClasses = null;
  
  /** 
   * Ignore node classes
   * 
   * @var   array
   */
  protected $ignoreClasses = null;
  
  /** 
   * Callback to parse menu nodes by component
   * 
   * @var   [\callable]
   */
  protected $nodeParserCallbacks = [];
  
  /** 
   * Callback to modify the node title
   * 
   * @var   \callable
   */
  protected $nodeTitleCallback = null;
  
  /** 
   * Callback to find active link
   * 
   * @var   \callable
   */
  protected $nodeActiveCallback;
  
  /** 
   * Callback to modify the node route
   * 
   * @var   \callable
   */
  protected $nodeRouteCallback = null;
  
  /**
   * Create instance
   * 
   * @param   array   $data   Key/value pairs
   * @return  MenuNode
   */
  public static function create()
  {
    $Class = get_called_class();
    return new $Class();
  }
  
  /**
   * Constructor
   */
  public function __construct()
  {
    $this->_root = MenuNode::create()
      ->setRoot();
  }
  
  public function setActiveRoute($activeRoute)
  {
    $this->activeRoute = $activeRoute;
    return $this;
  }
  
  public function setDropdown($dropdown)
  {
    $this->dropdown = $dropdown;
    return $this;
  }
  
  public function setDropdownClass($dropdownClass)
  {
    $this->dropdownClass = $dropdownClass;
    return $this;
  }
  
  public function setShowChildren($showChildren)
  {
    $this->showChildren = $showChildren;
    return $this;
  }
  
  public function setOnlyChildren($onlyChildren)
  {
    $this->onlyChildren = $onlyChildren;
    return $this;
  }
  
  public function setOnlyClasses(array $onlyClasses)
  {
    $this->onlyClasses = $onlyClasses;
    return $this;
  }
  
  public function setIgnoreClasses(array $ignoreClasses)
  {
    $this->ignoreClasses = $ignoreClasses;
    return $this;
  }
  
  /**
   * Set node title callback
   * 
   * @param  string     $component  Filter for menu component
   * @param  \callable  $callback   Node title callback function
   * @return $this
   */
  public function setNodeParserCallback($component, $callback)
  {
    $this->nodeParserCallbacks[$component] = $callback;
    return $this;
  }
  
  /**
   * Set node title callback
   * 
   * @param  \callable   $callback   Node title callback function
   * @return $this
   */
  public function setNodeActiveCallback($callback)
  {
    $this->nodeActiveCallback = $callback;
    return $this;
  }
  
  /**
   * Set node title callback
   * 
   * @param  \callable   $callback   Node title callback function
   * @return $this
   */
  public function setNodeTitleCallback($callback)
  {
    $this->nodeTitleCallback = $callback;
    return $this;
  }
  
  /**
   * Set node title callback
   * 
   * @param  \callable   $callback   Node route callback function
   * @return $this
   */
  public function setNodeRouteCallback($callback)
  {
    $this->nodeRouteCallback = $callback;
    return $this;
  }
  
  /**
   * Add nodes to the current menu
   * 
   * @return $this
   */
  public function setMenu()
  {
    $items = $this->prepareItems();
    
    $this->_current = $this->_root;
    $this->appendItems($items);
    
    $this->_current = $this->_root;
    $this->setActiveItems();
    
    return $this;
  }
  
  /**
   * Export items for display
   * 
   * @return array 
   */
  public function toTemplate()
  {
    $rootNode = $this->_root;
    
    $items = [];
    
    if ( $this->onlyChildren ){
      foreach($rootNode->getChildren() as $node){
        if ( !$node->isActive() ){
          continue;
        }
        
        $rootNode = $node;
        break;
      }
    }
    
    foreach($rootNode->getChildren() as $node){
      if ( $item = $this->render($node) ){
        $items[] = $item;
      }
    }
    
    return $items;
  }
  
  /**
   * Prepare menu items
   * 
   * @return array 
   */
  protected function prepareItems()
  {
    return [];
  }
  
  /**
   * Create nodes
   * 
   * @param  array $items  The submenu dataset
   * @return void
   */
  protected function appendItems(array $items=[])
  {
    foreach($items as $item){
      if ( $item->separator ){
        $node = MenuNode::create()
          ->setClass($item->class)
          ->setSeparator()
          ->setTitle($item->title)
          ->setParent($this->_current);
        
        $this->_current->addChild($node);
        continue;
      }
      
      $node = MenuNode::create()
        ->setTitle($item->title)
        ->setLink($item->link)
        ->setId($item->id)
        ->setClass($item->class)
        ->setIcon($item->icon)
        ->setTarget($item->target)
        ->setSlug($item->slug)
        ->setComponent($item->component)
        ->setModal($item->modal)
        ->setHome($item->home)
        ->setParams($item->params)
        ->setParent($this->_current);
      
      $this->_current->addChild($node);
      
      if ( count($item->children) ){
        $this->_current = $node;
        $this->appendItems($item->children);
        $this->_current = $this->_current->getParent();
      }
    }
  }
  
  /**
   * Set active nodes
   * 
   * @return void
   */
  protected function setActiveItems()
  {
    foreach($this->_current->getChildren() as $node){
      if ( $node->hasChildren() ){
        $this->_current = $node;
        $this->setActiveItems();
        $this->_current = $node->getParent();
      }
      
      if ( $this->isActiveItem($node) ){
        $node->setActive();
      }
    }
  }
  
  /**
   * Check active item
   * 
   * @param  MenuNode  $node  A node to check if active
   * @return bool
   */
  protected function isActiveItem(MenuNode $node)
  {
    $link = $node->getLink();
    
    if ( !$node->isInternal() ){
      return false;
    }
    
    if ( $link = $node->getLink() ){
      if ( $link === $this->activeRoute ){
        return true;
      }
    }
    
    if ( $node->isSeparator() ){
      return false;
    }
    
    if ( '' === $link && $this->activeRoute === '/' ){
      return true;
    }
    
    if ( isset($this->nodeActiveCallback) && is_callable($this->nodeActiveCallback) ){
      return call_user_func($this->nodeActiveCallback, $node);
    }
    
    return false;
  }
  
  /**
   * Render the menu
   */
  protected function render(MenuNode $node)
  {
    if ( $this->onlyClasses ){
      if ( !$node->hasClass($this->onlyClasses) ){
        return false;
      }
    }
    
    if ( $this->ignoreClasses ){
      if ( $node->hasClass($this->ignoreClasses) ){
        return false;
      }
    }
    
    list($containerAttrs, $linkAttrs) = $this->nodeParseAttributes($node);
    
    $item = [];
    
    if ( !empty($containerAttrs) ){
      $item['containerAttrs'] = $containerAttrs;
    }
    
    if ( !empty($linkAttrs) ){
      $item['linkAttrs'] = $linkAttrs;
    }
    
    $item['home']      = $node->isHome();
    $item['separator'] = $node->isSeparator();
    $item['active']    = $node->isActive();
    $item['modal']     = $node->isModal();
    $item['slug']      = $node->getSlug();
    $item['route']     = $node->getRoute();
    $item['icon']      = $node->getIcon();
    $item['target']    = $node->getTarget();
    $item['id']        = $node->getId();
    $item['title']     = $this->nodeTitle($node);
    
    if ( $node->hasChildren() ){
      $item['children'] = [];
      
      foreach($node->getChildren() as $child){
        if ( $_item = $this->render($child) ){
          $item['children'][] = $_item;
        }
      }
      
      if ( !$item['children'] ){
        unset($item['children']);
      }
    }
    
    return $item;
  }
  
  /**
   * Parse node for specified component
   * 
   * @param  string    $component  The node component
   * @param  stdClass  $item       The node data
   * @return string
   */
  protected function nodeParser(stdClass $item)
  {
    if ( isset($this->nodeParserCallbacks[$item->component]) && is_callable($this->nodeParserCallbacks[$item->component]) ){
      return call_user_func($this->nodeParserCallbacks[$item->component], $item);
    }
    
    if ( isset($this->nodeParserCallbacks['_']) && is_callable($this->nodeParserCallbacks['_']) ){
      return call_user_func($this->nodeParserCallbacks['_'], $item);
    }

    return $item;
  }
  
  /**
   * Eventually modify node title or return as is
   * 
   * @param   string  $title  The node title
   * @return  string
   */
  protected function nodeTitle(MenuNode $node)
  {
    if ( is_callable($this->nodeTitleCallback) ){
      $title = call_user_func($this->nodeTitleCallback, $node);
    }
    else {
      $title = $node->getTitle();
    }
    return $title;
  }
  
  /**
   * Eventually modify node title or return as is
   * 
   * @param   string  $title  The node title
   * @return  string
   */
  protected function nodeRoute(MenuNode $node)
  {
    return $node->getLink();
    
    if ( $node->isHome() ){
      $route = '/';
    } elseif ( is_callable($this->nodeRouteCallback) ){
      $route = call_user_func($this->nodeRouteCallback, $node);
    } elseif ( $link = $node->getLink() ){
      $route = $node->getLink();
    } else {
      $route = null;
    }
    return $route;
  }
  
  protected function nodeParseAttributes(MenuNode $node)
  {
    $containerAttrs = [];
    $linkAttrs = [];
    return [ $containerAttrs, $linkAttrs ];
  }
}
