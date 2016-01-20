<?php
namespace RedCat\Stylize;
class Parser extends \Leafo\ScssPhp\Parser{
	protected $scssFile;
	function __construct($sourceName, $sourceIndex = 0, $encoding = 'utf-8', $scssFile = null){
		$this->scssFile = $scssFile;
		parent::__construct($sourceName, $sourceIndex, $encoding);
    }
    function parse($buffer){
        $buffer = $this->scssFile->phpScssSupport($buffer);
        return parent::parse($buffer);
    }
}