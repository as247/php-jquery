<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 21-Sep-18
 * Time: 1:16 PM
 */

namespace PhpJquery\Parser;


use App\Console\Base\simple_html_dom_node;
use PhpJquery\Dom\AbstractNode;
use PhpJquery\Dom\HtmlNode;
use PhpJquery\Dom\TextNode;
use PhpJquery\Exceptions\StrictException;
use PhpJquery\Options;

class TinyParser implements Parser
{
    protected $nodeClass;
    protected $textNodeClass;
    protected $content;
    protected $pos;
    protected $length;
    protected $selfClosing = [
        'img',
        'br',
        'input',
        'meta',
        'link',
        'hr',
        'base',
        'embed',
        'spacer',
    ];
    /**
     * The following 4 strings are tags that are important to us.
     *
     * @var string
     */
    protected $blank = " \t\r\n";
    protected $equal = ' =/>';
    protected $slash = " />\r\n\t";
    protected $attr = ' >';
    /**
     * @var Options
     */
    protected $options;
    public function __construct($options)
    {
        $this->options=$options;
    }
    /**
     * Adds the tag (or tags in an array) to the list of tags that will always
     * be self closing.
     *
     * @param string|array $tag
     * @return $this
     */
    public function addSelfClosingTag($tag)
    {
        if ( ! is_array($tag)) {
            $tag = [$tag];
        }
        foreach ($tag as $value) {
            $this->selfClosing[] = $value;
        }

        return $this;
    }

    /**
     * Removes the tag (or tags in an array) from the list of tags that will
     * always be self closing.
     *
     * @param string|array $tag
     * @return $this
     */
    public function removeSelfClosingTag($tag)
    {
        if ( ! is_array($tag)) {
            $tag = [$tag];
        }
        $this->selfClosing = array_diff($this->selfClosing, $tag);

        return $this;
    }

    /**
     * Sets the list of self closing tags to empty.
     *
     * @return $this
     */
    public function clearSelfClosingTags()
    {
        $this->selfClosing = [];

        return $this;
    }
    /**
     * @param $content
     * @return HtmlNode
     */
    function parse($content)
    {
        $this->content=$content;
        $this->pos=0;
        $this->length=strlen($content);
        return $this->runParse();
    }

    /**
     * Attempts to parse the html in content.
     */
    protected function runParse()
    {
        // add the root node
        $root= new HtmlNode('root');
        $activeNode = $root;
        while ( ! is_null($activeNode)) {
            //echo 'Pos now: '.$this->pos;
            $str = $this->copyUntil('<');
            if ($str == '') {
                //echo 'Found tag:';
                $info = $this->parseTag();
                if(!$info['node']) {
                    //print_r($info);
                    //echo 'pos:'.$this->pos.PHP_EOL;
                }else{
                    //print_r($info['node']);
                    //echo $info['node'] . PHP_EOL;
                }
                if ( ! $info['status']) {
                    // we are done here
                    $activeNode = null;
                    continue;
                }
                // check if it was a closing tag
                if ($info['closing']) {
                    $originalNode = $activeNode;
                    while ($activeNode->getTag()->name() != $info['tag']) {
                        $activeNode = $activeNode->getParent();
                        if (is_null($activeNode)) {
                            // we could not find opening tag
                            $activeNode = $originalNode;
                            break;
                        }
                    }
                    if ( ! is_null($activeNode)) {
                        $activeNode = $activeNode->getParent();
                    }
                    continue;
                }
                if ( ! isset($info['node'])) {
                    continue;
                }
                /** @var AbstractNode $node */
                $node = $info['node'];
                $activeNode->addChild($node);
                // check if node is self closing
                if ( ! $node->getTag()->isSelfClosing()) {
                    $activeNode = $node;
                }
            } else if ($this->options->whitespaceTextNode ||
                trim($str) != ''
            ) {
                //echo 'Found text: '.var_dump($str).PHP_EOL;
                // we found text we care about
                $textNode = new TextNode($str);
                $activeNode->addChild($textNode);
            }
        }
        return $root;
    }
    /**
     * Attempt to parse a tag out of the content.
     *
     * @return array
     * @throws StrictException
     */
    protected function parseTag()
    {
        $return = [
            'status'  => false,
            'closing' => false,
            'node'    => null,
        ];
        if ($this->char() != '<') {
            return $return;
        }
        // check if this is a closing tag
        if ($this->fastForward(1)->char() == '/') {
            // end tag
            $tag = $this->fastForward(1)
                ->copyByToken('slash', true);
            // move to end of tag
            $this->copyUntil('>');
            $this->fastForward(1);
            // check if this closing tag counts
            $tag = strtolower($tag);
            if (in_array($tag, $this->selfClosing)) {
                $return['status'] = true;
                return $return;
            } else {
                $return['status']  = true;
                $return['closing'] = true;
                $return['tag']     = strtolower($tag);
            }
            return $return;
        }
        $tag  = strtolower($this->copyByToken('slash', true));
        $node = new HtmlNode($tag);
        // attributes
        while ($this->char() != '>' &&
            $this->char() != '/') {
            $space = $this->skipByToken('blank', true);
            if (empty($space)) {
                $this->fastForward(1);
                continue;
            }
            $name = $this->copyByToken('equal', true);
            if ($name == '/') {
                break;
            }
            if (empty($name)) {
                $this->fastForward(1);
                continue;
            }
            $this->skipByToken('blank');
            if ($this->char() == '=') {
                $attr = [];
                $this->fastForward(1)
                    ->skipByToken('blank');
                switch ($this->char()) {
                    case '"':
                        $attr['doubleQuote'] = true;
                        $this->fastForward(1);
                        $string = $this->copyUntil('"', true, true);
                        do {
                            $moreString = $this->copyUntilUnless('"', '=>');
                            $string .= $moreString;
                        } while ( ! empty($moreString));
                        $attr['value'] = $string;
                        $this->fastForward(1);
                        $node->getTag()->$name = $attr;
                        break;
                    case "'":
                        $attr['doubleQuote'] = false;
                        $this->fastForward(1);
                        $string = $this->copyUntil("'", true, true);
                        do {
                            $moreString = $this->copyUntilUnless("'", '=>');
                            $string .= $moreString;
                        } while ( ! empty($moreString));
                        $attr['value'] = $string;
                        $this->fastForward(1);
                        $node->getTag()->$name = $attr;
                        break;
                    default:
                        $attr['doubleQuote']   = true;
                        $attr['value']         = $this->copyByToken('attr', true);
                        $node->getTag()->$name = $attr;
                        break;
                }
            } else {
                // no value attribute
                if ($this->options->strict) {
                    // can't have this in strict html
                    $character = $this->getPosition();
                    throw new StrictException("Tag '$tag' has an attribute '$name' with out a value! (character #$character)");
                }
                $node->getTag()->$name = [
                    'value'       => null,
                    'doubleQuote' => true,
                ];
                if ($this->char() != '>') {
                    $this->rewind(1);
                }
            }
        }
        $this->skipByToken('blank');
        if ($this->char() == '/') {
            // self closing tag
            $node->getTag()->selfClosing();
            $this->fastForward(1);
        } elseif (in_array($tag, $this->selfClosing)) {
            // Should be a self closing tag, check if we are strict
            if ($this->options->strict) {
                $character = $this->getPosition();
                throw new StrictException("Tag '$tag' is not self closing! (character #$character)");
            }
            // We force self closing on this tag.
            $node->getTag()->selfClosing();
        }
        $this->fastForward(1);
        $return['status'] = true;
        $return['node']   = $node;
        return $return;
    }



    /**
     * Returns the current position of the content.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->pos;
    }
    /**
     * Gets the current character we are at.
     *
     * @param int $char
     * @return string
     */
    public function char($char = null)
    {
        $pos = $this->pos;
        if ( ! is_null($char)) {
            $pos = $char;
        }
        if ( ! isset($this->content[$pos])) {
            return '';
        }
        return $this->content[$pos];
    }
    /**
     * Moves the current position forward.
     *
     * @param int $count
     * @return $this
     */
    public function fastForward($count)
    {
        $this->pos += $count;
        return $this;
    }
    /**
     * Moves the current position backward.
     *
     * @param int $count
     * @return $this
     */
    public function rewind($count)
    {
        $this->pos -= $count;
        if ($this->pos < 0) {
            $this->pos = 0;
        }
        return $this;
    }
    /**
     * Copy the content until we find the given string.
     *
     * @param string $string
     * @param bool $char
     * @param bool $escape
     * @return string
     */
    public function copyUntil($string, $char = false, $escape = false)
    {
        if ($this->pos >= $this->length) {
            // nothing left
            return '';
        }
        if ($escape) {
            $position = $this->pos;
            $found    = false;
            while ( ! $found) {
                $position = strpos($this->content, $string, $position);
                if ($position === false) {
                    // reached the end
                    $found = true;
                    continue;
                }
                if ($this->char($position - 1) == '\\') {
                    // this character is escaped
                    ++$position;
                    continue;
                }
                $found = true;
            }
        } elseif ($char) {
            $position = strcspn($this->content, $string, $this->pos);
            $position += $this->pos;
        } else {
            $position = strpos($this->content, $string, $this->pos);
        }
        if ($position === false) {
            // could not find character, just return the remaining of the content
            $return    = substr($this->content, $this->pos, $this->length - $this->pos);

            //echo $string,'----'.$this->pos.'|';
            //echo $this->content[$this->pos];
            //echo '-----'.PHP_EOL;
            $this->pos = $this->length;

            return $return;
        }
        if ($position == $this->pos) {
            // we are at the right place
            return '';
        }
        $return = substr($this->content, $this->pos, $position - $this->pos);
        // set the new position
        $this->pos = $position;
        return $return;
    }
    /**
     * Copies the content until the string is found and return it
     * unless the 'unless' is found in the substring.
     *
     * @param string $string
     * @param string $unless
     * @return string
     */
    public function copyUntilUnless($string, $unless)
    {
        $lastPos = $this->pos;
        $this->fastForward(1);
        $foundString = $this->copyUntil($string, true, true);
        $position = strcspn($foundString, $unless);
        if ($position == strlen($foundString)) {
            return $string.$foundString;
        }
        // rewind changes and return nothing
        $this->pos = $lastPos;
        return '';
    }
    /**
     * Copies the content until it reaches the token string.,
     *
     * @param string $token
     * @param bool $char
     * @param bool $escape
     * @return string
     * @uses $this->copyUntil()
     */
    public function copyByToken($token, $char = false, $escape = false)
    {
        $string = $this->$token;
        return $this->copyUntil($string, $char, $escape);
    }
    /**
     * Skip a given set of characters.
     *
     * @param string $string
     * @param bool $copy
     * @return $this|string
     */
    public function skip($string, $copy = false)
    {
        $len = strspn($this->content, $string, $this->pos);
        // make it chainable if they don't want a copy
        $return = $this;
        if ($copy) {
            $return = substr($this->content, $this->pos, $len);
        }
        // update the position
        $this->pos += $len;
        return $return;
    }
    /**
     * Skip a given token of pre-defined characters.
     *
     * @param string $token
     * @param bool $copy
     * @return null|string
     * @uses $this->skip()
     */
    public function skipByToken($token, $copy = false)
    {
        $string = $this->$token;
        return $this->skip($string, $copy);
    }
}