<?php
/**
 * Modernized HTML_TreeMenu
 * Compatible with PHP 7.x / 8.x
 */

/* =========================
   HTML_TreeMenu
========================= */
class HTML_TreeMenu
{
    public array $items = [];

    public function __construct()
    {
    }

    public function addItem(HTML_TreeNode $node): HTML_TreeNode
    {
        $this->items[] = $node;
        return $node;
    }

    public static function createFromStructure(array $params): HTML_TreeMenu
    {
        $treeMenu    = $params['treeMenu'] ?? new HTML_TreeMenu();
        $nodeOptions = $params['nodeOptions'] ?? [];

        foreach ($params['structure']->nodes->nodes as $node) {
            $tag = $node->getTag();

            $parentNode = $treeMenu->addItem(
                new HTML_TreeNode(array_merge($nodeOptions, $tag))
            );

            if (!empty($node->nodes->nodes)) {
                self::createFromStructure([
                    'structure'   => $node,
                    'nodeOptions' => $nodeOptions,
                    'treeMenu'    => $parentNode
                ]);
            }
        }

        return $treeMenu;
    }

    public static function createFromXML($xml): HTML_TreeMenu
    {
        if (is_string($xml)) {
            require_once 'XML/Tree.php';
            if (!class_exists('XML_Tree')) {
                throw new RuntimeException('XML_Tree class not found');
            }
            $xmlTree = new XML_Tree();
            $xmlTree->getTreeFromString($xml);
        } else {
            $xmlTree = $xml;
        }

        if (!class_exists('Tree')) {
            require_once 'Tree.php';
        }

        if (!class_exists('Tree')) {
            throw new RuntimeException('Tree class not found');
        }

        $treeStructure = Tree::createFromXMLTree($xmlTree, true);

        $treeStructure->nodes->traverse(function ($node) {
            $tagData = $node->getTag();
            $node->setTag($tagData['attributes']);
        });

        return self::createFromStructure([
            'structure' => $treeStructure
        ]);
    }
}

/* =========================
   HTML_TreeNode
========================= */
class HTML_TreeNode
{
    public string $text = '';
    public string $link = '';
    public string $icon = '';
    public string $expandedIcon = '';
    public string $cssClass = '';
    public bool $expanded = false;
    public bool $isDynamic = true;
    public bool $ensureVisible = false;
    public ?string $linkTarget = null;

    public ?HTML_TreeNode $parent = null;
    public array $items = [];
    public array $events = [];

    public function __construct(array $options = [], array $events = [])
    {
        $this->events = $events;

        foreach ($options as $option => $value) {
            if (property_exists($this, $option)) {
                $this->$option = $value;
            }
        }
    }

    public function setOption(string $option, $value): void
    {
        if (property_exists($this, $option)) {
            $this->$option = $value;
        }
    }

    public function addItem(HTML_TreeNode $node): HTML_TreeNode
    {
        $node->parent = $this;
        $this->items[] = $node;

        if ($node->ensureVisible) {
            $this->makeVisible();
        }

        return $node;
    }

    private function makeVisible(): void
    {
        $this->ensureVisible = true;
        $this->expanded = true;

        if ($this->parent) {
            $this->parent->makeVisible();
        }
    }
}

/* =========================
   PRESENTATION BASE
========================= */
abstract class HTML_TreeMenu_Presentation
{
    protected HTML_TreeMenu $menu;

    public function __construct(HTML_TreeMenu $structure)
    {
        $this->menu = $structure;
    }

    abstract public function toHTML(): string;

    public function printMenu(array $options = []): void
    {
        foreach ($options as $option => $value) {
            $this->$option = $value;
        }

        echo $this->toHTML();
    }
}

/* =========================
   DHTML PRESENTATION
========================= */
class HTML_TreeMenu_DHTML extends HTML_TreeMenu_Presentation
{
    public bool $isDynamic = true;
    public string $images = 'images';
    public string $linkTarget = '_self';
    public bool $usePersistence = true;
    public string $defaultClass = '';
    public bool $noTopLevelImages = false;

    public function __construct(
        HTML_TreeMenu $structure,
        array $options = [],
        bool $isDynamic = true
    ) {
        parent::__construct($structure);
        $this->isDynamic = $isDynamic;

        foreach ($options as $option => $value) {
            $this->$option = $value;
        }
    }

    public function toHTML(): string
    {
        static $count = 0;
        $menuObj = 'objTreeMenu_' . ++$count;

        $html  = "\n<script>\n";
        $html .= sprintf(
            '%s = new TreeMenu("%s", "%s", "%s", "%s", %s, %s);',
            $menuObj,
            $this->images,
            $menuObj,
            $this->linkTarget,
            $this->defaultClass,
            $this->usePersistence ? 'true' : 'false',
            $this->noTopLevelImages ? 'true' : 'false'
        );
        $html .= "\n";

        foreach ($this->menu->items as $item) {
            $html .= $this->nodeToHTML($item, $menuObj);
        }

        $html .= "\n{$menuObj}.drawMenu();";

        if ($this->usePersistence && $this->isDynamic) {
            $html .= "\n{$menuObj}.resetBranches();";
        }

        $html .= "\n</script>\n";

        return $html;
    }

    private function nodeToHTML(HTML_TreeNode $node, string $prefix, string $return = 'newNode'): string
    {
        $expanded  = $this->isDynamic ? ($node->expanded ? 'true' : 'false') : 'true';
        $dynamic   = $this->isDynamic ? ($node->isDynamic ? 'true' : 'false') : 'false';

        $html = sprintf(
            "\t%s = %s.addItem(new TreeNode('%s', %s, %s, %s, %s, '%s', '%s', %s));\n",
            $return,
            $prefix,
            addslashes($node->text),
            $node->icon ? "'{$node->icon}'" : 'null',
            $node->link ? "'{$node->link}'" : 'null',
            $expanded,
            $dynamic,
            $node->cssClass,
            $node->linkTarget,
            $node->expandedIcon ? "'{$node->expandedIcon}'" : 'null'
        );

        foreach ($node->events as $event => $handler) {
            $html .= sprintf(
                "\t%s.setEvent('%s', '%s');\n",
                $return,
                $event,
                addslashes($handler)
            );
        }

        foreach ($node->items as $i => $child) {
            $html .= $this->nodeToHTML($child, $return, "{$return}_" . ($i + 1));
        }

        return $html;
    }
}

/* =========================
   LISTBOX PRESENTATION
========================= */
class HTML_TreeMenu_Listbox extends HTML_TreeMenu_Presentation
{
    public string $promoText = 'Select...';
    public string $indentChar = '&nbsp;';
    public int $indentNum = 2;
    public string $linkTarget = '_self';
    public string $submitText = 'Go';

    public function toHTML(): string
    {
        static $count = 0;
        $count++;

        $html = '';
        foreach ($this->menu->items as $item) {
            $html .= $this->nodeToHTML($item);
        }

        return sprintf(
            '<form target="%s" onsubmit="var l=this.sel.value;if(l){location.href=l;return false;}">
            <select name="sel">
            <option value="">%s</option>%s
            </select>
            <input type="submit" value="%s">
            </form>',
            $this->linkTarget,
            $this->promoText,
            $html,
            $this->submitText
        );
    }

    private function nodeToHTML(HTML_TreeNode $node, string $prefix = ''): string
    {
        $html = sprintf(
            '<option value="%s">%s%s</option>',
            $node->link,
            $prefix,
            $node->text
        );

        foreach ($node->items as $child) {
            $html .= $this->nodeToHTML(
                $child,
                $prefix . str_repeat($this->indentChar, $this->indentNum)
            );
        }

        return $html;
    }
}
