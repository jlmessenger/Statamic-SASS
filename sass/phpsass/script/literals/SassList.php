<?php
/* SVN FILE: $Id$ */
/**
 * SassBoolean class file.
 * @author      Chris Yates <chris.l.yates@gmail.com>
 * @copyright   Copyright (c) 2010 PBM Web Development
 * @license      http://phamlp.googlecode.com/files/license.txt
 * @package      PHamlP
 * @subpackage  Sass.script.literals
 */

require_once('SassLiteral.php');

/**
 * SassBoolean class.
 * @package      PHamlP
 * @subpackage  Sass.script.literals
 */
class SassList extends SassLiteral {

  var $seperator = ' ';

  /**
   * SassBoolean constructor
   * @param string value of the boolean type
   * @return SassBoolean
   */
  public function __construct($value, $seperator = 'auto') {
    if (is_array($value)) {
      $this->value = $value;
      $this->seperator = ($seperator == 'auto' ? ', ' : $seperator);
    }
    else if (list($list, $seperator) = $this->_parse_list($value, $seperator, true, SassScriptParser::$context)) {
      $this->value = $list;
      $this->seperator = ($seperator == ',' ? ', ' : ' ');
    }
    else {
      throw new SassListException('Invalid SassList', SassScriptParser::$context->node);
    }
  }

  function nth($i) {
    $i = $i - 1; # SASS uses 1-offset arrays
    if (isset($this->value[$i])) {
      return $this->value[$i];
    }
    return new SassBoolean(false);
  }

  function length() {
    return count($this->value);
  }

  function append($other, $seperator = null) {
    if ($seperator) {
      $this->seperator = $seperator;
    }
    if ($other instanceof SassList) {
      $this->value = array_merge($this->value, $other->value);
    }
    else if ($other instanceof SassLiteral) {
      $this->value[] = $other;
    }
    else {
      throw new SassListException('Appendation can only occur with literals', SassScriptParser::$context->node);
    }
  }

  /**
   * Returns the value of this boolean.
   * @return boolean the value of this boolean
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * Returns a string representation of the value.
   * @return string string representation of the value.
   */
  public function toString() {
    $this->seperator = trim($this->seperator) . ' ';
    return implode($this->seperator, $this->value);
  }

  /**
   * Returns a value indicating if a token of this type can be matched at
   * the start of the subject string.
   * @param string the subject string
   * @return mixed match at the start of the string or false if no match
   */
  public static function isa($subject) {
    list($list, $seperator) = self::_parse_list($subject, 'auto', false);
    return count($list) > 1 ? $subject : FALSE;
  }

  public static function _parse_list($list, $seperator = 'auto', $lex = true, $context = null) {
    if ($seperator == 'auto') {
      $seperator = ',';
      $list = $list = self::_build_list($list, ',');
      if (count($list) < 2) {
        $seperator = ' ';
        $list = self::_build_list($list, ' ');
      }
    }
    else {
      $list = self::_build_list($list, $seperator);
    }

    if ($lex) {
      $context = new SassContext($context);
      foreach ($list as $k => $v) {
        $list[$k] = SassScriptParser::$instance->evaluate($v, $context);
      }
    }
    return array($list, $seperator);
  }

  public static function _build_list($list, $seperator = ',') {
    if (is_object($list)) {
      $list = $list->value;
    }

    if (is_array($list)) {
      $newlist = array();
      foreach ($list as $listlet) {
        list($newlist, $seperator) = array_merge($newlist, self::_parse_list($listlet, $seperator, false));
      }
      $list = implode(', ', $newlist);
    }

    $out = array();
    $size = 0;
    $braces = 0;
    $quotes = false;
    $stack = '';
    for($i = 0; $i < strlen($list); $i++) {
      $char = substr($list, $i, 1);
      switch ($char) {
        case '"':
        case "'":
          if (!$quotes) {
            $quotes = $char;
          }
          else if ($quotes && $quotes == $char) {
            $quotes = false;
          }
          $stack .= $char;
          break;
        case '(':
          $braces++;
          $stack .= $char;
          break;
        case ')':
          $braces--;
          $stack .= $char;
          break;
        case $seperator:
          if ($braces === 0 && !$quotes) {
            $out[] = $stack;
            $stack = '';
            $size++;
            break;
          }
        default:
          $stack .= $char;
      }
    }
    if (strlen($stack)) {
      if (($braces || $quotes) && count($out)) {
        $out[count($out) - 1] .= $stack;
      } else {
        $out[] = $stack;
      }
    }

    foreach ($out as $k => $v) {
      $v = trim($v, ', ');
      $out[$k] = $v;
    }

    return $out;
  }
}
