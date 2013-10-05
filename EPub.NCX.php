<?php
/**
 * ePub NCX file structure
 *
 * @author A. Grandt <php@grandt.com>
 * @copyright 2009-2013 A. Grandt
 * @license GNU LGPL, Attribution required for commercial implementations, requested for everything else.
 * @version 1.00
 */
class Ncx {
    const VERSION = 1.00;

    const MIMETYPE = "application/x-dtbncx+xml";

    private $navMap = NULL;
    private $uid = NULL;
    private $meta = array();
    private $docTitle = NULL;
    private $docAuthor = NULL;

	private $currentLevel = NULL;
	private $lastLevel = NULL;

	/**
     * Class constructor.
     *
     * @return void
     */
    function __construct($uid = NULL, $docTitle = NULL, $docAuthor = NULL) {
        $this->navMap = new NavMap();
		$this->currentLevel = $this->navMap;
        $this->setUid($uid);
        $this->setDocTitle($docTitle);
        $this->setDocAuthor($docAuthor);
    }

    /**
     * Class destructor
     *
     * @return void
     */
    function __destruct() {
        unset($this->navMap, $this->uid, $this->docTitle, $this->docAuthor);
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $uid
     */
    function setUid($uid) {
        $this->uid = is_string($uid) ? trim($uid) : NULL;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $docTitle
     */
    function setDocTitle($docTitle) {
        $this->docTitle = is_string($docTitle) ? trim($docTitle) : NULL;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $docAuthor
     */
    function setDocAuthor($docAuthor) {
        $this->docAuthor = is_string($docAuthor) ? trim($docAuthor) : NULL;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $navMap
     */
    function setNavMap($navMap) {
        if ($navMap != NULL && is_object($navMap) && get_class($navMap) === "NavMap") {
            $this->navMap = $navMap;
        }
    }

	/**
	 * Add one chapter level.
	 * 
	 * Subsequent chapters will be added to this level. 
	 */
	function subLevel() {
		if ($this->lastLevel !== NULL) {
			$this->currentLevel = $this->lastLevel;
		}
	}

	/**
	 * Step back one chapter level.
	 * 
	 * Subsequent chapters will be added to this chapters parent level. 
	 */
	function backLevel() {
		$this->lastLevel = $this->currentLevel;
		$this->currentLevel = $this->currentLevel->getParent();
	}

	/**
	 * Step back to the root level.
	 * 
	 * Subsequent chapters will be added to the rooot NavMap. 
	 */
	function rootLevel() {
		$this->lastLevel = $this->currentLevel;
		$this->currentLevel = $this->navMap;
	}

	/**
	 * Step back to the given level.
	 * Useful for returning to a previous level from deep within the structure. 
	 * Values below 2 will have the same effect as rootLevel()
	 * 
	 * @param int $newLevel
	 */
	function setCurrentLevel($newLevel) {
		if ($newLevel <= 1) {
			$this->rootLevel();
		} else {
			while ($this->currentLevel->getLevel() > $newLevel) {
				$this->backLevel();
			}
		}
	}

	/**
	 * Get current level count.
	 * The indentation of the current structure point.
	 * 
	 * @return current level count;
	 */
	function getCurrentLevel() {
		return $this->currentLevel->getLevel();
	}

	/**
     * Add child NavPoints to current level.
     *
     * @param NavPoint $navPoint
     */
	function addNavPoint($navPoint) {
		$this->lastLevel = $this->currentLevel->addNavPoint($navPoint);
	}

    /**
     *
     * Enter description here ...
     *
     * @return NULL
     */
    function getNavMap() {
        return $this->navMap;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $name
     * @param unknown_type $content
     */
    function addMetaEntry($name, $content) {
        $name = is_string($name) ? trim($name) : NULL;
        $content = is_string($content) ? trim($content) : NULL;

        if ($name != NULL && $content != NULL) {
            $this->meta[] = array($name => $content);
        }
    }

    /**
     *
     * Enter description here ...
     *
     * @return string
     */
    function finalize() {
        $nav = $this->navMap->finalize();

        $ncx = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
        . "<!DOCTYPE ncx PUBLIC \"-//NISO//DTD ncx 2005-1//EN\"\n"
        . "  \"http://www.daisy.org/z3986/2005/ncx-2005-1.dtd\">\n"
        . "<ncx xmlns=\"http://www.daisy.org/z3986/2005/ncx/\" version=\"2005-1\" xml:lang=\"en\">\n"
        . "\t<head>\n"
        . "\t\t<meta name=\"dtb:uid\" content=\"" . $this->uid . "\" />\n"
        . "\t\t<meta name=\"dtb:depth\" content=\"" . $this->navMap->getNavLevels() . "\" />\n"
        . "\t\t<meta name=\"dtb:totalPageCount\" content=\"0\" />\n"
        . "\t\t<meta name=\"dtb:maxPageNumber\" content=\"0\" />\n";

        if (sizeof($this->meta)) {
            foreach ($this->meta as $metaEntry) {
                list($name, $content) = each($metaEntry);
                $ncx .= "\t\t<meta name=\"" . $name . "\" content=\"" . $content . "\" />\n";
            }
        }

        $ncx .= "\t</head>\n\n\t<docTitle>\n\t\t<text>"
        . $this->docTitle
        . "</text>\n\t</docTitle>\n\n\t<docAuthor>\n\t\t<text>"
        . $this->docAuthor
        . "</text>\n\t</docAuthor>\n\n"
        . $nav;

        return $ncx . "</ncx>\n";
    }
}

/**
 * ePub NavMap class
 */
class NavMap {
    const VERSION = 1.00;

    private $navPoints = array();
    private $navLevels = 0;

    /**
     * Class constructor.
     *
     * @return void
     */
    function __construct() {
    }

    /**
     * Class destructor
     *
     * @return void
     */
    function __destruct() {
        unset($this->navPoints, $this->navLevels);
    }

    /**
     * Add a navPoint to the root of the NavMap.
     *
     * @param unknown_type $navPoint
     */
    function addNavPoint($navPoint) {
        if ($navPoint != NULL && is_object($navPoint) && get_class($navPoint) === "NavPoint") {
			$navPoint->setParent($this);
            $this->navPoints[] = $navPoint;
			return $navPoint;
        }
		return $this;
    }

    /**
     * The final max depth for the "dtb:depth" meta attribute
     * Only available after finalize have been called.
     *
     * @return number
     */
    function getNavLevels() {
        return $this->navLevels+1;
    }

	function getLevel() {
		return 1;
	}

	function getParent() {
		return $this;
	}

    /**
     * Finalize the navMap, the final max depth for the "dtb:depth" meta attribute can be retrieved with getNavLevels after finalization
     *
     */
    function finalize() {
        $playOrder = 0;
        $level = 0;
        $this->navLevels = 0;

        $nav = "\t<navMap>\n";

        if (sizeof($this->navPoints) > 0) {
            $this->navLevels++;
            foreach ($this->navPoints as $navPoint) {
                $retLevel = $navPoint->finalize($nav, $playOrder, 0);
                if ($retLevel > $this->navLevels) {
                    $this->navLevels = $retLevel;
                }
            }
        }

        return $nav . "\t</navMap>\n";
    }
}

/**
 * ePub NavPoint class
 */
class NavPoint {
    const VERSION = 1.00;

    private $label = NULL;
    private $contentSrc = NULL;
    private $id = NULL;
    private $navPoints = array();
	private $parent = NULL;

    /**
     * Class constructor.
     *
     * All three attributes are mandatory, though if ID is set to null (default) the value will be generated.
     *
     * @param String $label
     * @param String $contentSrc
     * @param String $id
     * @return void
     */
    function __construct($label, $contentSrc, $id = NULL) {
        $this->setLabel($label);
        $this->setContentSrc($contentSrc);
        $this->setId($id);
    }

    /**
     * Class destructor
     *
     * @return void
     */
    function __destruct() {
        unset($this->label, $this->contentSrc, $this->id, $this->navPoints);
    }

    /**
     * Set the Text label for the NavPoint.
     *
     * The label is mandatory.
     *
     * @param String $label
     */
    function setLabel($label) {
        $this->label = is_string($label) ? trim($label) : NULL;
    }

	/**
     * Get the Text label for the NavPoint.
	 * 
	 * @return string Label
	 */
	function getLabel() {
		return $this->label;
	}
	
    /**
     * Set the src reference for the NavPoint.
     *
     * The src is mandatory.
     *
     * @param String $contentSrc
     */
    function setContentSrc($contentSrc) {
        $this->contentSrc = is_string($contentSrc) ? trim($contentSrc) : NULL;
    }

	/**
	 * Get the src reference for the NavPoint.
	 *
	 * @return string content src url.
	 */
	function getContentSrc() {
		return $this->contentSrc;
	}
    /**
     * Set the parent for this NavPoint.
     *
     * @param NavPoint or NavMap $parent
     */
    function setParent($parent) {
		if ($parent != NULL && is_object($parent) && 
				(get_class($parent) === "NavPoint" || get_class($parent) === "NavMap") ) {
			$this->parent = $parent;
		}
    }

	/**
	 * Get the parent to this NavPoint.
	 * 
	 * @return NavPoint, or NavMap if the parent is the root.
	 */
	function getParent() {
		return $this->parent;
	}
	
	/**
	 * Get the current level. 1 = document root.
	 * 
	 * @return int level
	 */
	function getLevel() {
		return $this->parent === NULL ? 1 : $this->parent->getLevel()+1;
	}
	
    /**
     * Set the id for the NavPoint.
     *
     * The id must be unique, and is mandatory.
     *
     * @param String $id
     */
    function setId($id) {
        $this->id = is_string($id) ? trim($id) : NULL;
    }

    /**
     * Add child NavPoints for multi level NavMaps.
     *
     * @param NavPoint $navPoint
     */
    function addNavPoint($navPoint) {
        if ($navPoint != NULL && is_object($navPoint) && get_class($navPoint) === "NavPoint") {
			$navPoint->setParent($this);
            $this->navPoints[] = $navPoint;
			return $navPoint;
        }
		return $this;
    }

    /**
     *
     * Enter description here ...
     *
     * @param unknown_type $nav
     * @param unknown_type $playOrder
     * @param unknown_type $level
     * @return unknown
     */
    function finalize(&$nav = "", &$playOrder = 0, $level = 0) {
        $maxLevel = $level;
        $playOrder++;
        if ($this->id == NULL) {
            $this->id = "navpoint-" . $playOrder;
        }

        $nav .= str_repeat("\t", $level) . "\t\t<navPoint id=\"" . $this->id . "\" playOrder=\"" . $playOrder . "\">\n"
        . str_repeat("\t", $level) . "\t\t\t<navLabel>\n"
        . str_repeat("\t", $level) . "\t\t\t\t<text>" . $this->label . "</text>\n"
        . str_repeat("\t", $level) . "\t\t\t</navLabel>\n"
        . str_repeat("\t", $level) . "\t\t\t<content src=\"" . $this->contentSrc . "\" />\n";

        if (sizeof($this->navPoints) > 0) {
            $maxLevel++;
            foreach ($this->navPoints as $navPoint) {
                $retLevel = $navPoint->finalize($nav, $playOrder, ($level+1));
                if ($retLevel > $maxLevel) {
                    $maxLevel = $retLevel;
                }
            }
        }

        $nav .= str_repeat("\t", $level) . "\t\t</navPoint>\n";

        return $maxLevel;
    }
}
?>