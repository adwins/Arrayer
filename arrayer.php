<?php

/**
 * A class to work with the POST data (or other string array data) as a uniform object and its collection of objects
 * according to object oriented programming style.
 *
 * Requirements:
 *
 * - UTF-8 support
 * - PCRE with UTF-8 support
 * - iconv extension
 * - mbstring extension
 *
 * This class is full compatible with the Kohana framework.
 *
 * @package    Arrayer
 * @author     Alexei Shabalin aka Ash
 * @copyright  (c) 2011 Alexei Shabalin aka Ash
 * @license    http://www.gnu.org/licenses/lgpl-2.1.html
 */

class Arrayer implements IteratorAggregate {

	const CHARSET='utf-8';
	const COUNTRY_CODE='7';

	/**
	 * Current array of objects
	 * @var array
	 */
	protected $_vars=array();

	/**
	 * Current value of the object
	 * @var string
	 */
	protected $_var;

	/**
	 * Initial value of the object
	 * @var string
	 */
	protected $_initial_value;

	/**
	 * Arrayer validate object
	 * @var object
	 */
	protected $_validate;

	/**
	 * Current error of the object
	 * @var string
	 */
	protected $_error;

	/**
	 * Constructs a new object and its collection of objects of itself
	 *
	 * @param   array $data Parameter for constucting a collection of objects
	 * @param   array $expected An array of expected variables with default values on missing
	 * @param   boolean $trim Option to trim string values while constructing
	 * @return  void
	 */
	public function __construct($data=null, $expected=null, $trim=true){
		if ($data===null){
			$data=$_POST;
		}

		if (is_array($data)){
			$this->values($data, $expected, $trim);
		} else {
			$this->_var=$data;

			if (get_magic_quotes_gpc()){
				$this->stripslashes();
			}

			if ($trim)	$this->trim();

			$this->_initial_value=$data;
		}
	}

	/**
	 * Detects if the object is a value object
	 *
	 * @return  boolean
	 */
	public function _has_var(){
		return isset($this->_var);
	}

	/**
	 * Checks if object data is set.
	 *
	 * @param  string $var Variable name
	 * @return boolean
	 */
	public function __isset($var){
		return isset($this->_vars[$var]);
	}

	/**
	 * Unsets object data.
	 *
	 * @param  string $var Variable name
	 * @return void
	 */
	public function __unset($var){
		unset ($this->_vars[$var]);
	}

	/**
	 * Displays the current value of the object.
	 *
	 * @return string
	 */
	public function __toString(){
		return $this->_has_var() ? (string)$this->_var : 'Instance of '.__CLASS__;
	}

	public function __call($method, array $args){
		if (function_exists($method)){
			array_unshift($args, $this->_has_var() ? $this->_var : $this->as_array());
			return $method($args);
		} else {
			return 'Function '.$method.' does not exist';
		}
	}

	/**
	 * Handles retrieval of object value.
	 *
	 * @param   string $var Variable name
	 * @return  mixed
	 */
	public function __get($var){
		return $this->_has_var() ? $this->_var : $this->get($var);
	}

	/**
	 * Sets or adds a value or a collection of objects to the object.
	 *
	 * @param  string $var  Variable name
	 * @param  mixed $value   Variable value or an array of objects
	 * @return void
	 */
	public function __set($var, $value){
		$this->add($var, $value);
	}

	/**
	 * Sets a value to the object.
	 *
	 * @param  mixed $value   Variable value
	 * @param   boolean $trim Option to trim strings
	 * @return Arrayer
	 */
	public function set($value, $trim=false){
		if ($this->_has_var()){
			$this->_var=$value;
			if ($trim)	$this->trim();
		}
		return $this;
	}

	/**
	 * Gets a value of a variable.
	 *
	 * @param  mixed $value   Variable value
	 * @param   boolean $trim Option to trim strings
	 * @return mixed | false
	 */
	public function get($var){
		return $this->has($var) ? $this->_vars[$var] : false;
	}

	/**
	 * Checks if the object has a variable.
	 *
	 * @param  mixed $var   Variable name
	 * @return boolean
	 */
	public function has($var){
		return isset($this->_vars[$var]);
	}

	/**
	 * Adds or sets a value or a collection of objects to the object.
	 *
	 * @param  string $key  Variable name
	 * @param  mixed $value   Variable value or an array of objects
	 * @param   boolean $trim Option to trim strings
	 * @return Arrayer
	 */
	public function add($key, $value, $trim=false){
		if ($value instanceof static){
			$this->_vars[$key]=$value;
		} else {
			if ($this->has($key)){
				$this->_vars[$key]->set($value, $trim);
			} else {
				$this->_vars[$key]=new static($value, $trim);
			}
		}
	}

	/**
	 * Checks if the object has a variable and sets the default value on false.
	 *
	 * @param  mixed $var   Variable name
	 * @return Arrayer
	 */
	public function check($var, $default=''){
		if (!$this->has($var))	$this->add($var, $default);
		return $this->get($var);
	}

	/**
	 * Adds a collection of objects
	 *
	 * @param   array $values An array of values
	 * @param   array $expected An array of expected variables with default values on missing
	 * @param   boolean $trim Option to trim string values
	 * @return  Arrayer
	 */
	public function values($values, $expected=null, $trim=false){
		if (is_array($expected)){
			foreach ($expected as $key=>$value){
				if (isset($values[$key])){
					$this->add($key, $values[$key], $trim);
				} else {
					$this->add($key, $value, $trim);
				}
			}
		} else {
			foreach ($values as $key=>$value){
				$this->add($key, $value, $trim);
			}
		}
		return $this;
	}

	/**
	 * Creates a copy of the object
	 *
	 * @return  new Arrayer
	 */
	public function copy(){
		return new static($this->as_array(), false);
	}

	/**
	 * Gets a copy of the object with initial state.
	 *
	 * @param   boolean $trim Option to trim string values
	 * @return  new Arrayer
	 */
	public function initial($trim=false){
		if ($this->_vars){
			$_=array();
			foreach ($this->_vars as $key=>$value){
				$_[$key]=$value->initial($trim);
			}
			return new static($_, $trim);
		} else {
			return new static($this->_initial_value, $trim);
		}
	}

	/**
	 * Reset initial state of the object.
	 *
	 * @return  Arrayer
	 */
	public function reset(){
		if ($this->_has_var()){
			$this->_initial_value=$this->_var;
		}
		return $this;
	}

	/**
	 * Retrieves initial state of the object.
	 *
	 * @return  Arrayer
	 */
	public function undo(){
		if ($this->_has_var()){
			$this->_var=$this->_initial_value;
		}
		return $this;
	}

	/**
	 * Unsets a variable.
	 *
	 * @param  mixed $var   Variable name
	 * @return Arrayer
	 */
	public function delete($var){
		unset($this->_vars[$var]);
		return $this;
	}


	// ITERATE METHODS

    public function getIterator(){
        return new ArrayIterator($this->_vars);
    }


	// MEASURING METHODS

	/**
	 * Counts the length of a string value.
	 *
	 * @param  string $charset   Specifies a charset of the string
	 * @return int
	 */
	public function length($charset=self::CHARSET){
		return mb_strlen($this->_var, $charset);
	}

	/**
	 * Counts the bytes of a string value.
	 *
	 * @return int
	 */
	public function bytes(){
		return strlen($this->_var);
	}


	// STRING MANIPULATION METHODS

	/**
	 * Cleans the object.
	 *
	 * @return Arrayer
	 */
	public function clean(){
		if ($this->_has_var()){
			$this->trim();
			$this->_var=preg_replace(array('#[ \t]+#', '#[\n\r]+#'), array(' ', PHP_EOL), $this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->clean();
			}
		}
		return $this;
	}

	/**
	 * Trims the object.
	 *
	 * @param  string $charlist   Characters to trim off
	 * @return Arrayer
	 */
	public function trim($charlist=''){
		if ($this->_has_var()){
			$this->_var=$charlist ? trim($this->_var, $charlist) : trim($this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->trim($charlist);
			}
		}
		return $this;
	}

	/**
	 * Strips tags off.
	 *
	 * @param  string $allowable_tags   Allowable tags
	 * @return Arrayer
	 */
	public function strip($allowable_tags=''){
		if ($this->_has_var()){
			$this->_var=strip_tags($this->_var, $allowable_tags);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->strip($allowable_tags);
			}
		}
		return $this;
	}

	/**
	 * Strips slashes.
	 *
	 * @return Arrayer
	 */
	public function stripslashes(){
		if ($this->_has_var()){
			$this->_var=stripslashes($this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->stripslashes();
			}
		}
		return $this;
	}

	/**
	 * Adds slashes.
	 *
	 * @return Arrayer
	 */
	public function addslashes(){
		if ($this->_has_var()){
			$this->_var=addslashes($this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->addslashes();
			}
		}
		return $this;
	}

	/**
	 * Transform to lower case.
	 *
	 * @param  string $charset   Specifies a charset of the string
	 * @return Arrayer
	 */
	public function to_lower($charset=self::CHARSET){
		if ($this->_has_var()){
			$this->_var=$charset ? mb_strtolower($this->_var, $charset) : strtolower($this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->to_lower($charset);
			}
		}
		return $this;
	}

	/**
	 * Transform to upper case.
	 *
	 * @param  string $charset   Specifies a charset of the string
	 * @return Arrayer
	 */
	public function to_upper($charset=self::CHARSET){
		if ($this->_has_var()){
			$this->_var=$charset ? mb_strtoupper($this->_var, $charset) : strtolower($this->_var);
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->to_upper($charset);
			}
		}
		return $this;
	}

	/**
	 * Pads the string value with a given string ($string) to speciefied length ($len).
	 *
	 * @return Arrayer
	 */
	public function pad($len, $string=' ', $type=STR_PAD_RIGHT){
		if ((string)$string==='0')	$type=STR_PAD_LEFT;
		$this->_var=str_pad($this->_var, $len, $string, $type);
		return $this;
 	}

	/**
	 * Replaces $search to $replace.
	 *
	 * @return Arrayer
	 */
	public function replace($search, $replace){
		$this->_var=str_replace($search, $replace, $this->_var);
		return $this;
	}

	/**
	 * Converts the object from a charset $from to a charset $to.
	 *
	 * @return Arrayer
	 */
	public function conv_from($from, $to=self::CHARSET){
		if ($this->_has_var()){
			if (strtolower($from)!=strtolower($to)){
				//$this->_var=iconv($from, $to.'//IGNORE', $this->_var);
				$this->_var=mb_convert_encoding($this->_var, $to, $from);
				$this->_initial_value=$this->_var;
			}
		} else {
			foreach ((array)$this->_vars as $key=>$value){
				$value->conv_from($from, $to);
			}
		}
		return $this;
	}

	/**
	 * Converts the object to a charset $to from a charset $from.
	 *
	 * @return Arrayer
	 */
	public function conv_to($to, $from=self::CHARSET){
		return $this->conv_from($from, $to);
	}

	/**
	 * Concatenates a given string (strings, array of strings) to the string value.
	 *
	 * @param  string $string   A string or an array of strings
	 * @param  string $string2   A string ...
	 * @param  string ...
	 * @return Arrayer
	 */
	public function concat(){
		$str=func_get_args();
		if (!$str)	return $this;
		if (is_array($str[0]))	$str=$str[0];
		if (!is_array($str))	$str=(string)$str;
		$this->_var.=implode('', (array)$str);
		return $this;
	}


	// FORMAT METHODS

	/**
	 * Formats a string according to a $format.
	 *
	 * @param  string $format   Php sprintf valid format
	 * @return new Arrayer
	 */
	public function format($format='%s'){
		if ($this->_var!==''){
			if (!$format)	return $this->copy();
			return new static(sprintf($format, $this->_var));
		} else {
			return new static('');
		}
	}

	/**
	 * Formats a string as date.
	 *
	 * @param  string $format   Php date valid format
	 * @return new Arrayer
	 */
	public function as_date($format='Y-m-d'){
		if ($this->_has_var()){
			$copy=$this->copy();
			if ($copy->is_date(true, $format)){
				return $copy->as_string();
			}
			return false;
		}
		return false;
	}

	/**
	 * Gets a string in specified charset ($to_charset).
	 *
	 * @return new Arrayer
	 */
	public function as_string($to_charset=null, $charset=self::CHARSET){
		if ($to_charset and $to_charset!=$charset){
			return (string)$this->copy()->conv_to($to_charset, $charset);
		} else {
			return $this->_var;
		}
	}

	/**
	 * Gets a string in lower case.
	 *
	 * @return new Arrayer
	 */
	public function as_lower($charset=self::CHARSET){
		return $this->copy()->to_lower($charset);
	}

	/**
	 * Gets a string in upper case.
	 *
	 * @return new Arrayer
	 */
	public function as_upper($charset=self::CHARSET){
		return $this->copy()->to_upper($charset);
	}

	/**
	 * Respells (transliterates) the string value.
	 *
	 * @return new Arrayer
	 */
	public function respell($charset=self::CHARSET){
		if ($this->_var){
			$s=$this->copy();
			if ($this->_respell_table_words and $this->_respell_table_words[(string)$s])	return $this->_respell_table_words[(string)$s];
			if ($this->_respell_table)		return new static(strtr($s, $this->_respell_table), false);
			return new static($s->conv_from($charset, 'ASCII'), false);
		} else {
			$new=array();
			foreach ($this->_vars as $key=>$value){
				$new[$key]=$value->respell($charset);
			}
			return $new;
		}
	}

	/**
	 * Table of transliteration
	 * @var array
	 */
	protected $_respell_table=array(
		'а' => 'a',   'б' => 'b',   'в' => 'v',	'г' => 'g',   'д' => 'd',   'е' => 'e',	'ё' => 'e',   'ж' => 'zh',  'з' => 'z',	'и' => 'i',   'й' => 'y',   'к' => 'k',
		'л' => 'l',   'м' => 'm',   'н' => 'n',	'о' => 'o',   'п' => 'p',   'р' => 'r',	'с' => 's',   'т' => 't',   'у' => 'u',	'ф' => 'f',   'х' => 'h',   'ц' => 'c',
		'ч' => 'ch',  'ш' => 'sh',  'щ' => 'sch',	'ь' => '',  'ы' => 'y',   'ъ' => '',	'э' => 'e',   'ю' => 'yu',  'я' => 'ya',  'є' => 'ie',

		'à' => 'a',  'ô' => 'o',  'ď' => 'd',  'ḟ' => 'f',  'ë' => 'e',  'š' => 's',  'ơ' => 'o',  'ă' => 'a',  'ř' => 'r',  'ț' => 't',  'ň' => 'n',  'ā' => 'a',  'ķ' => 'k',  'ĕ' => 'e',
		'ŝ' => 's',  'ỳ' => 'y',  'ņ' => 'n',  'ĺ' => 'l',  'ħ' => 'h',  'ṗ' => 'p',  'ó' => 'o',  'ú' => 'u',  'ě' => 'e',  'é' => 'e',  'ç' => 'c',  'ẁ' => 'w',  'ċ' => 'c',  'õ' => 'o',
		'ṡ' => 's',  'ø' => 'o',  'ģ' => 'g',  'ŧ' => 't',  'ș' => 's',  'ė' => 'e',  'ĉ' => 'c',  'ś' => 's',  'î' => 'i',  'ű' => 'u',  'ć' => 'c',  'ę' => 'e',  'ŵ' => 'w',  'ṫ' => 't',
		'ū' => 'u',  'č' => 'c',  'ö' => 'o',  'è' => 'e',  'ŷ' => 'y',  'ą' => 'a',  'ł' => 'l',  'ų' => 'u',  'ů' => 'u',  'ş' => 's',  'ğ' => 'g',  'ļ' => 'l',  'ƒ' => 'f',  'ž' => 'z',
		'ẃ' => 'w',  'ḃ' => 'b',  'å' => 'a',  'ì' => 'i',  'ï' => 'i',  'ḋ' => 'd',  'ť' => 't',  'ŗ' => 'r',  'ä' => 'a',  'í' => 'i',  'ŕ' => 'r',  'ê' => 'e',  'ü' => 'u',  'ò' => 'o',
		'ē' => 'e',  'ñ' => 'n',  'ń' => 'n',  'ĥ' => 'h',  'ĝ' => 'g',  'đ' => 'd',  'ĵ' => 'j',  'ÿ' => 'y',  'ũ' => 'u',  'ŭ' => 'u',  'ư' => 'u',  'ţ' => 't',  'ý' => 'y',  'ő' => 'o',
		'â' => 'a',  'ľ' => 'l',  'ẅ' => 'w',  'ż' => 'z',  'ī' => 'i',  'ã' => 'a',  'ġ' => 'g',  'ṁ' => 'm',  'ō' => 'o',  'ĩ' => 'i',  'ù' => 'u',  'į' => 'i',  'ź' => 'z',  'á' => 'a',
		'û' => 'u',  'þ' => 'th', 'ð' => 'dh', 'æ' => 'ae', 'i' => 'i',

		'А' => 'A',   'Б' => 'B',   'В' => 'V',	'Г' => 'G',   'Д' => 'D',   'Е' => 'E',	'Ё' => 'E',   'Ж' => 'Zh',  'З' => 'Z',	'И' => 'I',   'Й' => 'Y',   'К' => 'K',
		'Л' => 'L',   'М' => 'M',   'Н' => 'N',	'О' => 'O',   'П' => 'P',   'Р' => 'R',	'С' => 'S',   'Т' => 'T',   'У' => 'U',	'Ф' => 'F',   'Х' => 'H',   'Ц' => 'C',
		'Ч' => 'Ch',  'Ш' => 'Sh',  'Щ' => 'Sch',	'Ь' => '',  'Ы' => 'Y',   'Ъ' => '',	'Э' => 'E',   'Ю' => 'Yu',  'Я' => 'Ya',  'Є' => 'Ye',

		'À' => 'A',  'Ô' => 'O',  'Ď' => 'D',  'Ḟ' => 'F',  'Ë' => 'E',  'Š' => 'S',  'Ơ' => 'O',  'Ă' => 'A',  'Ř' => 'R',  'Ț' => 'T',  'Ň' => 'N',  'Ā' => 'A',  'Ķ' => 'K',  'Ĕ' => 'E',
		'Ŝ' => 'S',  'Ỳ' => 'Y',  'Ņ' => 'N',  'Ĺ' => 'L',  'Ħ' => 'H',  'Ṗ' => 'P',  'Ó' => 'O',  'Ú' => 'U',  'Ě' => 'E',  'É' => 'E',  'Ç' => 'C',  'Ẁ' => 'W',  'Ċ' => 'C',  'Õ' => 'O',
		'Ṡ' => 'S',  'Ø' => 'O',  'Ģ' => 'G',  'Ŧ' => 'T',  'Ș' => 'S',  'Ė' => 'E',  'Ĉ' => 'C',  'Ś' => 'S',  'Î' => 'I',  'Ű' => 'U',  'Ć' => 'C',  'Ę' => 'E',  'Ŵ' => 'W',  'Ṫ' => 'T',
		'Ū' => 'U',  'Č' => 'C',  'Ö' => 'O',  'È' => 'E',  'Ŷ' => 'Y',  'Ą' => 'A',  'Ł' => 'L',  'Ų' => 'U',  'Ů' => 'U',  'Ş' => 'S',  'Ğ' => 'G',  'Ļ' => 'L',  'Ƒ' => 'F',  'Ž' => 'Z',
		'Ẃ' => 'W',  'Ḃ' => 'B',  'Å' => 'A',  'Ì' => 'I',  'Ï' => 'I',  'Ḋ' => 'D',  'Ť' => 'T',  'Ŗ' => 'R',  'Ä' => 'A',  'Í' => 'I',  'Ŕ' => 'R',  'Ê' => 'E',  'Ü' => 'U',  'Ò' => 'O',
		'Ē' => 'E',  'Ñ' => 'N',  'Ń' => 'N',  'Ĥ' => 'H',  'Ĝ' => 'G',  'Đ' => 'D',  'Ĵ' => 'J',  'Ÿ' => 'Y',  'Ũ' => 'U',  'Ŭ' => 'U',  'Ư' => 'U',  'Ţ' => 'T',  'Ý' => 'Y',  'Ő' => 'O',
		'Â' => 'A',  'Ľ' => 'L',  'Ẅ' => 'W',  'Ż' => 'Z',  'Ī' => 'I',  'Ã' => 'A',  'Ġ' => 'G',  'Ṁ' => 'M',  'Ō' => 'O',  'Ĩ' => 'I',  'Ù' => 'U',  'Į' => 'I',  'Ź' => 'Z',  'Á' => 'A',
		'Û' => 'U',  'Þ' => 'Th', 'Ð' => 'Dh', 'Æ' => 'Ae', 'İ' => 'I',
	);

	/**
	 * Table of transliteration for full word translation
	 * @var array
	 */
	protected $_respell_table_words=array();

	/**
	 * Creates a URL safe name from a string, respelling it and cleaning characters.
	 *
	 * @return new Arrayer
	 */
	public function as_safe_name($charset=self::CHARSET){
		return new static(preg_replace(array('#[^\w \t\n\-_]#i', '#[ \t\n]+#'), array('', '_'), $this->as_lower($charset)->respell()));
	}


	// SPECIAL FORMAT METHODS

	/**
	 * Escapes string.
	 *
	 * @return new Arrayer
	 */
	public function escape(){
		return new static(htmlspecialchars($this->_var));
	}

	/**
	 * Hashes string to md5 with given $salt.
	 *
	 * @return new Arrayer
	 */
	public function md5($salt=''){
		return new static(md5($this->_var.$salt));
	}

	/**
	 * Hashes string to sha1 with given $salt.
	 *
	 * @return new Arrayer
	 */
	public function sha1($salt=''){
		return new static(sha1($this->_var.$salt));
	}

	/**
	 * Hashes string to crypt with given $salt.
	 *
	 * @return new Arrayer
	 */
	public function crypt($salt=null){
		return new static(crypt($this->_var, $salt));
	}

	/**
	 * Hashes string to sha256.
	 *
	 * @return new Arrayer
	 */
	public function sha256(){
		return new static(hash('sha256', $this->_var));
	}

	/**
	 * Gets CRC32 of the string value.
	 *
	 * @return new Arrayer
	 */
	public function crc32(){
		return new static(crc32($this->_var));
	}

	/**
	 * Reverses the string value.
	 *
	 * @return new Arrayer
	 */
	public function rev(){
		return new static(strrev($this->_var));
	}

	/**
	 * Gets the substring of the string value specified by the $start and $len parameters.
	 *
	 * @return new Arrayer
	 */
	public function substr($start, $len=null, $charset=self::CHARSET){
		return new static(mb_substr($this->_var, $start, $len, $charset));
	}

	/**
	 * Chunks the string value by $chunklen
	 *
	 * @return new Arrayer
	 */
	public function chunk($chunklen=76, $end="\r\n"){
		return new static(chunk_split($this->_var, $chunklen, $end));
	}

	/**
	 * Encodes the string value with base64.
	 *
	 * @param  boolean $as_mail   If true, formats the result as MIME header value
	 * @return new Arrayer
	 */
	public function base64($as_mail=false, $to_charset=self::CHARSET, $from_charset=self::CHARSET){
		if ($as_mail){
			return new static(sprintf('=?%s?B?%s?=', strtoupper($to_charset), $this->copy()->conv_from($from_charset, $to_charset)->base64()));
		} else {
			return new static(base64_encode($this->_var));
		}
	}

	/**
	 * Decodes the string value with base64.
	 *
	 * @return new Arrayer
	 */
	public function base64_decode(){
		return new static(base64_decode($this->_var));
	}

	/**
	 * Converts the string value to a quoted-printable string.
	 *
	 * @param  boolean $as_mail   If true, formats the result as MIME header value
	 * @return new Arrayer
	 */
	public function quoted($as_mail=false, $to_charset=self::CHARSET, $from_charset=self::CHARSET){
		if ($as_mail){
			return new static(sprintf('=?%s?Q?%s?=', strtoupper($to_charset), $this->copy()->conv_from($from_charset, $to_charset)->quoted()));
		} else {
			return new static(quoted_printable_encode($this->_var));
		}
	}

	/**
	 * Converts the quoted-printable string value to a string value.
	 *
	 * @return new Arrayer
	 */
	public function quoted_decode(){
		return new static(quoted_printable_decode($this->_var));
	}

	/**
	 * Converts the MIME string to a string.
	 *
	 * @return new Arrayer
	 */
	public function mime_string_decode($charset=self::CHARSET){
		if ($_=$this->matches('#=\?([^\?]+)\?(Q|B)\?([^\?]+)\?=#i', true)){
			$text=new static($_[3]);
			switch (strtoupper($_[2])){
				case 'Q':
					return $text->quoted_decode()->conv_from($_[1], $charset);
				case 'B':
					return $text->base_decode()->conv_from($_[1], $charset);
			}
		}
	}

	/**
	 * Converts the IP to a Hex value.
	 *
	 * @return new Arrayer
	 */
	public function ip2hex(){
		return new static(sprintf('%X', ctype_digit($this->_var) ? ip2long(long2ip($this->_var)) : ip2long($this->_var)));
	}

	/**
	 * Converts the Hex value to an IP.
	 *
	 * @return new Arrayer
	 */
	public function hex2ip(){
		return new static(long2ip(hexdec($this->_var)));
	}


	// CHECK METHODS

	/**
	 * Checks if the value is empty
	 *
	 * @return boolean
	 */
	public function is_empty(){
		if ($this->_has_var()){
			if (!$this->_var)	return true;
			return false;
		}
	}

	/**
	 * Checks if the value is string
	 *
	 * @return boolean
	 */
	public function is_string(){
		if ($this->_has_var()){
			if (is_string($this->_var))	return true;
			return false;
		}
	}

	/**
	 * Checks if the value is equal to given $value
	 *
	 * @return boolean
	 */
	public function is($value){
		if ($this->_has_var()){
			if (is_int($value)){
				if (is_numeric($this->_var) and (int)$this->_var===(int)$value)	return false;
			} elseif ($this->_var==(string)$value){
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the value is valid URL
	 *
	 * @return boolean
	 */
	public function is_url(){
		if ($this->_has_var()){
			if ($_=$this->matches('#^https?://(\w[\w\.]+\.\w+)(:\d+)?/?#i', true)){
				if (strlen($_[1]) > 253)	return false;
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the value is valid email
	 *
	 * @param  boolean $check_mx   Set to true to check DNS records
	 * @return boolean
	 */
	public function is_email($check_mx=false){
		if ($this->_has_var()){
			if ($_=$this->matches('#^([\w\.\-\+_]+)@([\w\-]+\.)+(\w{2,6})$#i', true)){
				if ($check_mx and !checkdnsrr(sprintf('%s%s', $_[2], $_[3]), 'MX')){
					return false;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the value is valid phone
	 *
	 * @param  boolean $clean   If true, cleans the phone number
	 * @return boolean
	 */
	public function is_phone($clean=false, $ccode=self::COUNTRY_CODE){
		if ($this->_has_var()){
			if ($this->matches('#[\d\(\) \-\+]+$#')){
				$v=$this->_var;
				$v=preg_replace('#[^\d]#', '', $v);
				if ($clean){
					if ($ccode){
						$v=$ccode.preg_replace('#^('.preg_quote($ccode).'|8)#', '', $v);
					}
					if (strlen($v)>=7){
						$this->_var=$v;
						return true;
					}
				} else {
					if (strlen($v)>=7)	return true;
				}
			}
		}
		return false;
	}

	/**
	 * Checks if the value is valid date
	 *
	 * @param  boolean $clean   If true, cleans the date
	 * @return boolean
	 */
	public function is_date($clean=false, $to_format='Y-m-d'){
		if ($this->_has_var()){
			$from_format='';
			if ($this->matches('#^\d{2}\.\d{2}\.\d{4}$#')){
				$from_format='d.m.Y';
			} elseif ($this->matches('#^\d{2}\.\d{2}\.\d{2}$#')){
				$from_format='d.m.y';
			} elseif ($this->matches('#^\d{2}\-\d{2}\-\d{4}$#')){
				$from_format='d-m-Y';
			} elseif ($this->matches('#^\d{2}\-\d{2}\-\d{2}$#')){
				$from_format='d-m-y';
			} elseif ($this->matches('#^\d{4}\-\d{2}\-\d{2}$#')){
				$from_format='Y-m-d';
			} elseif ($this->matches('#^\d{2}\/\d{2}\/\d{4}$#')){
				$from_format='m/d/Y';
			} elseif ($this->matches('#^\d{2}\/\d{2}\/\d{2}$#')){
				$from_format='m/d/y';
			} else {
				if ($clean)	$this->_var='';
				return false;
			}
			if ($clean and $to_format){
				$this->_var=date_format(date_create_from_format($from_format, $this->_var), $to_format);
			}
			return true;
		}
		return false;
	}

	/**
	 * Checks if the value is integer
	 *
	 * @return boolean
	 */
	public function is_int(){
		if ($this->_has_var()){
			if (ctype_digit($this->_var)){
				$this->_var=(int)$this->_var;
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if the value is in given array
	 *
	 * @return boolean
	 */
	public function in(){
		if ($this->_has_var()){
			$values=func_get_args();
			if (!$values)	return false;
			if (is_array($values[0]))	$values=$values[0];
			if (in_array($this->_var, $values))	return true;
		}
		return false;
	}

	/**
	 * Checks if the value is between $low and $high
	 *
	 * @return boolean
	 */
	public function in_range($low, $high){
		return ($this->_var>=$low and $this->_var<=$high) ? true : false;
	}

	/**
	 * Checks if the value has $length
	 *
	 * @return boolean
	 */
	public function has_length($length, $charset=self::CHARSET){
		return $this->length($charset)==$length;
	}

	/**
	 * Checks if the value length is between $min and $max
	 *
	 * @return boolean
	 */
	public function has_length_between($min, $max, $charset=self::CHARSET){
		$len=$this->length($charset);
		return ($len>=$min and $len<=$max) ? true : false;
	}

	/**
	 * Checks if the value matches PRCE $pattern
	 *
	 * @return boolean
	 */
	public function matches($pattern, $return_matches=false){
		if ($this->_has_var()){
			if (preg_match($pattern, $this->_var, $_))	return $return_matches ? $_ : true;
		}
		return false;
	}

	/**
	 * Checks if the value contains $needle
	 *
	 * @return boolean
	 */
	public function contains($needle){
		$args=func_get_args();
		if (count($args)>1)	$needle=$args;
		if (is_array($needle)){
			foreach ($needle as $_){
				if ($this->contains($_))	return true;
			}
			return false;
		} else {
			return stripos($this->_var, $needle) !== FALSE;
		}
	}

	/**
	 * Checks if the value starts with $needle
	 *
	 * @return boolean
	 */
	public function starts_with($needle){
		$args=func_get_args();
		if (count($args)>1)	$needle=$args;
		if (is_array($needle)){
			foreach ($needle as $_){
				if ($this->starts_with($_))	return true;
			}
			return false;
		} else {
			return stripos($this->_var, $needle) === 0;
		}
	}

	/**
	 * Checks if the value ends with $needle
	 *
	 * @return boolean
	 */
	public function ends_with($needle){
		$args=func_get_args();
		if (count($args)>1)	$needle=$args;
		if (is_array($needle)){
			foreach ($needle as $_){
				if ($this->ends_with($_))	return true;
			}
			return false;
		} else {
			return stripos(strrev($this->_var), strrev($needle)) === 0;
		}
	}


	// VALIDATION METHODS

	/**
	 * Returns a validation object for the current object
	 *
	 * @return Arrayer_Validate
	 */
	public function validate(){
		if (!$this->_validate)	$this->_validate=new Arrayer_Validate($this);
		return $this->_validate;
	}

	/**
	 * Sets a validation error $text to the current object
	 *
	 * @return void
	 */
	public function set_error($text){
		$this->_error=$text;
	}

	/**
	 * Gets validation errors
	 *
	 * @return mixed
	 */
	public function get_errors(){
		if ($this->_has_var()){
			return $this->_error;
		} else {
			$_=array();
			if ($this->_error)	$_['common']=$this->_error;
			foreach ((array)$this->_vars as $key=>$value){
				$er=$value->get_errors();
				if ($er)	$_[$key]=$er;
			}
			return $_;
		}
	}


	// ACTIVE METHODS

	/**
	 * Sends the object as POST data to a URL
	 *
	 * @return string | false
	 */
	public function send($url, $timeout=10){
		if (!$url)	return false;
		$curl=curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL=>$url,
			CURLOPT_POST=>true,
			CURLOPT_RETURNTRANSFER=>true,
			CURLOPT_CONNECTTIMEOUT=>$timeout,
			CURLOPT_POSTFIELDS=>$this->as_query(),
		));
		$response=curl_exec($curl);
		if ($response===false){
			$error=curl_error($curl);
			curl_close($curl);
			return false;
		} else {
			curl_close($curl);
			return $response;
		}
	}


	// EXPORT METHODS

	/**
	 * Exports the object to PHP global $_POST variable
	 *
	 * @return void
	 */
	public function export(){
		$_POST=$this->as_array();
	}


	// PRESENTATIVE METHODS

	/**
	 * Returns the object as valid query string with $concat
	 *
	 * @return string
	 */
	public function as_query($concat='&', $prefix=''){
		if ($this->_vars){
			return http_build_query($this->as_array(), $prefix, $concat);
		} else {
			return urlencode($this->_var);
		}
	}

	/**
	 * Returns the object as valid SQL query
	 *
	 * @return string
	 */
	public function as_sql_query($cols=null, $for_insert=false){
		if ($this->_vars){
			if (!$for_insert){
				$_=array();
				foreach ($this->_vars as $key=>$value){
					if ($cols and !in_array($key, (array)$cols))	continue;
					if ($value->_has_var()){
						$_[]=sprintf('`%s`="%s"', $key, $value->as_sql_query());
					}
				}
				return implode(', ', $_);
			} else {
				$_=array();
				foreach ($this->_vars as $key=>$value){
					if ($cols and !in_array($key, (array)$cols))	continue;
					if ($value->_has_var()){
						$_[$key]=$value->as_sql_query();
					}
				}
				return sprintf('(`%s`) VALUES ("%s")', implode('`, `', array_keys($_)), implode('", "', array_values($_)));
			}
		} else {
			return addcslashes($this->_var, "\0..\37!\"'`@[]\\");
		}
	}

	/**
	 * Returns the object as an array of strings
	 *
	 * @return array
	 */
	public function as_array(){
		if ($this->_has_var()){
			return (string)$this->_var;
		} else {
			$_=array();
			foreach ($this->_vars as $key=>$value){
				$_[$key]=$value->as_array();
			}
			return $_;
		}
	}

	/**
	 * Returns the object as JSON string
	 *
	 * @return string
	 */
	public function as_json(){
		return json_encode($this->as_array());
	}


}






class Arrayer_Validate {

	private $_arrayer;
	private $_result;

	public function __construct($arrayer){
		$this->_arrayer=$arrayer;
	}

	public function __call($method, $args){
		if (!$this->_arrayer->get_errors() and method_exists($this->_arrayer, $method)){
			$this->_result=call_user_func_array(array($this->_arrayer, $method), $args);
		}
		return $this;
	}

	public function undo(){
		$this->_arrayer->undo();
		return $this;
	}

	public function on_empty($var, $on_false='Value is missing'){
		if (!$this->_arrayer->_has_var()){
			if (!$this->_arrayer->has($var)){
				$this->_arrayer->{$var}='';
			}
			if ($this->_arrayer->{$var}->is_empty()){
				$this->_arrayer->{$var}->set_error($on_false);
			}
		}
		return $this;
	}

	public function on_false($str){
		if ($this->_result===false){
			$this->_arrayer->set_error($str);
		}
		return $this;
	}

	public function on_true($str){
		if ($this->_result===true){
			$this->_arrayer->set_error($str);
		}
		return $this;
	}

	public function on($value, $str){
		if (is_bool($value) and $this->_result===$value){
			$this->_arrayer->set_error($str);
		} elseif (is_string($value) and (string)$this->_result==$value){
			$this->_arrayer->set_error($str);
		} elseif (is_array($value) and in_array((string)$this->_result, $value)){
			$this->_arrayer->set_error($str);
		}
		return $this;
	}

	public function unless($value, $str){
		if (is_bool($value) and $this->_result===!$value){
			$this->_arrayer->set_error($str);
		} elseif (is_string($value) and (string)$this->_result!=$value){
			$this->_arrayer->set_error($str);
		} elseif (is_array($value) and !in_array((string)$this->_result, $value)){
			$this->_arrayer->set_error($str);
		}
		return $this;
	}

	public function __toString(){
		return (string)$_result;
	}

	public function is_ok(){
		return $this->_arrayer->get_errors()?false:true;
	}

}


?>