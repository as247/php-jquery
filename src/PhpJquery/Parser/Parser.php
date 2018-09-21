<?php
/**
 * Created by PhpStorm.
 * User: alt
 * Date: 21-Sep-18
 * Time: 1:13 PM
 */

namespace PhpJquery\Parser;


use PhpJquery\Dom\AbstractNode;

interface Parser
{
    public function __construct($options);

    /**
     * @param $content
     * @return AbstractNode
     */
    function parse($content);
}