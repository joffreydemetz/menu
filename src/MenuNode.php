<?php
/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace JDZ\Menu;

use JDZ\Helpers\StringHelper;
use stdClass;

/**
 * Menu Node
 *
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class MenuNode 
{
  /** 
   * Title
   * 
   * @var   string
   */
  protected $title = '';

  /** 
   * Link
   * 
   * @var   string
   */
  protected $link = '';

  /** 
   * Id
   * 
   * @var   string
   */
  protected $id = false;

  /** 
   * Class
   * 
   * @var   string
   */
  protected $class = '';

  /** 
   * Target
   * 
   * @var   string
   */
  protected $target = '';

  /** 
   * Slug
   * 
   * @var   string
   */
  protected $slug = '';

  /** 
   * Component
   * 
   * @var   string
   */
  protected $component = '';
  
  /** 
   * Icon
   * 
   * @var   string
   */
  protected $icon = '';
  
  /** 
   * Trigger modal
   * 
   * @var   string
   */
  protected $modal = '';

  /** 
   * Root node
   * 
   * @var   bool
   */
  protected $root = false;
  
  /** 
   * Separator
   * 
   * @var   bool
   */
  protected $separator = false;
  
  /** 
   * Home link
   * 
   * @var   bool
   */
  protected $home = false;
  
  /** 
   * Node is active
   * 
   * @var   bool
   */
  protected $active = false;
  
  /** 
   * Some node key/value options
   * 
   * @var   stdClass
   */
  protected $params = null;
  
  /** 
   * Parent node
   * 
   * @var   MenuNode
   */
  protected $parent = null;
  
  /** 
   * Children nodes
   * 
   * @var   [MenuNode]
   */
  protected $children = [];
  
  /**
   * Create instance
   * 
   * @param   array   $data   Key/value pairs
   * @return  MenuNode
   */
  public static function create()
  {
    return new self();
  }
  
  /**
   * Constructor
   */
  public function __construct()
  {
  }
  
  /**
   * Add child to this node
   * If the child already has a parent, the link is unset
   *
   * @param   MenuNode  $child  The child to be added
   * @return   void
   */
  public function addChild(MenuNode $child)
  {
    $child->setParent($this);
  }
  
  /**
   * Set as root
   * 
   * @param   MenuNode  &$parent  The Node for parent to be set or null
   * @return   $this
   */
  public function setRoot()
  {
    $this->root  = true;
    $this->id    = 'root';
    $this->title = 'ROOT';
    return $this;
  }
  
  /**
   * Set as separator
   * 
   * @return   $this
   */
  public function setSeparator()
  {
    $this->separator = true;
    // $this->class     = 'divider';
    return $this;
  }
  
  public function setTitle($title)
  {
    $this->title = $title;
    return $this;
  }
  
  public function setLink($link)
  {
    $this->link = StringHelper::ampReplace($link);
    return $this;
  }
  
  public function setId($id)
  {
    $this->id = $id;
    return $this;
  }

  public function setClass($class, $merge=false)
  {
    if ( !$merge ){
      $classes = [];
    }
    else {
      $classes = $this->class ? explode(' ', $this->class) : [];
    }
    
    $_classes = $class ? explode(' ', $class) : [];
    
    $classes = array_merge($classes, $_classes);
    $classes = array_unique($classes);
    
    $this->class = implode(' ', $classes);
    return $this;
  }
  
  public function setTarget($target)
  {
    $this->target = $target;
    return $this;
  }
  
  public function setSlug($slug)
  {
    $this->slug = $slug;
    return $this;
  }
  
  public function setComponent($component)
  {
    $this->component = $component;
    return $this;
  }
  
  public function setIcon($icon)
  {
    $this->icon = $icon;
    return $this;
  }
  
  public function setModal($modal)
  {
    $this->modal = $modal;
    return $this;
  }
  
  public function setHome($home)
  {
    $this->home = $home;
    return $this;
  }
  
  public function setParams(array $params)
  {
    if ( !isset($this->params) ){
      $this->params = new stdClass();
    }
    
    foreach($params as $key => $value){
      $this->params->{$key} = $value;
    }
    
    return $this;
  }
  
  /**
   * Set the node as active (also sets the parent as active)
   * 
   * @return  $this
   */
  public function setActive()
  {
    $this->active = true;
    
    if ( $this->hasParent() && !$this->parent->isRoot() ){
      $this->parent->setActive();
    }
    
    return $this;
  }
  
  /**
   * Set the parent of a this node
   * If the node already has a parent, the link is unset
   *
   * @param   MenuNode  $parent  The Node for parent to be set
   * @return  void
   */
  public function setParent(MenuNode $parent)
  {
    $hash = spl_object_hash($this);
    $parent->children[$hash] = $this;
    $this->parent = $parent;
    return $this;
  }
  
  public function getTitle()
  {
    return $this->title;
  }

  public function getLink()
  {
    return $this->link;
  }

  public function getId()
  {
    return $this->id;
  }

  public function getClass()
  {
    return $this->class;
  }

  public function getComponent()
  {
    return $this->component;
  }

  public function getSlug()
  {
    return $this->slug;
  }

  public function getTarget()
  {
    return $this->target;
  }

  public function getIcon()
  {
    return $this->icon;
  }

  public function getChildren()
  {
    return $this->children;
  }

  public function getParent()
  {
    return $this->parent;
  }

  public function getModal()
  {
    return $this->modal;
  }
  
  public function getParams()
  {
    if ( !$this->params ){
      $this->params = new stdClass();
    }
    return $this->params;
  }

  public function getParam($key)
  {
    $params = $this->getParams();
    return isset($params->{$key}) ? $params->{$key} : null;
  }

  public function isRoot()
  {
    return true === $this->root;
  }
  
  public function isSeparator()
  {
    return true === $this->separator;
  }
  
  public function isActive()
  {
    return true === $this->active;
  }
  
  public function isHome()
  {
    return true === $this->home;
  }
  
  public function isModal()
  {
    return '' !== $this->modal;
  }
  
  public function isInternal()
  {
    return ( '#' !== $this->link && !preg_match("/https?:\/\//", $this->link) );
  }
  
  /**
   * Test if this node has children
    *
   * @return   bool  True if there are children
   */
  public function hasChildren()
  {
    return count($this->children) > 0;
  }

  /**
   * Test if this node has specific class
    *
   * @param  array|string  $classes  Class(es) to match for
   * @return bool  True if at least one of the classes was found
   */
  public function hasClass($classes)
  {
    if ( !$classes ){
      return true;
    }
    
    if ( !is_array($classes) ){
      $classes = [ $classes ];
    }
    
    $list = $this->class ? explode(' ', $this->class) : [];
    
    foreach($list as $class){
      if ( in_array($class, $classes) ){
        return true;
      }
    }
    
    return false;
  }

  /**
   * Test if this node has a parent
   * 
   * @return   bool  True if there is a parent
   */
  public function hasParent()
  {
    return null !== $this->parent;
  }

  public function getAttrTarget()
  {
    if ( in_array($this->target, ['blank','parent','self']) ){
      $target = '_'.$this->target;
    }
    else {
      $target = null;
    }
    return $target;
  }

  public function getRoute()
  {
    if ( $this->link && '#' !== $this->link ){
      $route = $this->link;
    }
    else {
      $route = null;
    }
    return $route;
  }
  
  /**
   * Build the id 
   * 
   * @return   string  The composed node id
   */
  protected function buildId()
  {
    if ( $this->link !== '' && $this->link !== '#' ){
      if ( strpos($this->link, '?') === false ){
        $path = $this->link;
      }
      else {
        list($path, $query) = explode('?', $this->link);
      }
      
      $parts = [];
      
      $_parts = explode('/', $path);
      foreach($_parts as $i => $p){
        if ( trim($p) === '' ){
          unset($_parts[$i]);
          continue;
        }
        $parts[] = $p;
      }
      
      $this->id = implode('-', $parts);
    }
    
    return $this;
  }
}
