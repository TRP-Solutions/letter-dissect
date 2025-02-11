<?php
/*
LetterDissect is licensed under the Apache License 2.0 license
https://github.com/TRP-Solutions/letter-dissect/blob/main/LICENSE
*/
declare(strict_types=1);

class LetterDissect {
	private $imap;
	private $message_num;
	private $structure = [];

	function __construct($imap,$message_num) {
		$this->imap = $imap;
		$this->message_num = $message_num;
		$structure = imap_fetchstructure($this->imap, $message_num);
		if($structure->type!==TYPEMULTIPART) {
			$this->part($structure,1);
		}
		else {
			foreach($structure->parts as $count => $value) {
				$this->part($value,($count+1));
			}
		}
	}

	private function part($part,$section) {
		if($part->type===TYPEMULTIPART) {
			foreach($part->parts as $count => $value) {
				$this->part($value,$section.'.'.($count+1));
			}
		}
		else {
			$this->structure[$section] = $part;
		}
	}

	public function subtype(...$subtype) {
		if($subtype) {
			$return = [];
			foreach($this->structure as $key => $value) {
				if(in_array($value->subtype,$subtype)) {
					$return[] = $key;
				}
			}
			return $return;
		}
		return array_keys($this->structure);
	}

	public function fetchinfo($section) {
		if(!isset($this->structure[$section])) {
			throw new \Exception('Invalid section');
		}
		$return = [];
		$return['subtype'] = $this->structure[$section]->subtype;
		$return['disposition'] = $this->structure[$section]->disposition;

		foreach($this->structure[$section]->dparameters as $value) {
			if($value->attribute=='filename') {
				$return['filename'] = $value->value;
			}
		}

		return $return;
	}

	public function fetchbody($section) {
		if(!isset($this->structure[$section])) {
			throw new \Exception('Invalid section');
		}

		$body = imap_fetchbody($this->imap,$this->message_num,$section);
		if($this->structure[$section]->encoding==ENCBASE64) {
			$body = base64_decode($body);
		}
		else if($this->structure[$section]->encoding==ENCQUOTEDPRINTABLE) {
			$body = quoted_printable_decode($body);
		}

		$charset = null;
		foreach($this->structure[$section]->parameters as $param) {
			if(strtolower($param->attribute)=='charset') {
				$charset = $param->value;
			}
		}

		return self::charset_decode($body,$charset);
	}

	public static function header_decode($string) {
		$return = '';
		foreach(imap_mime_header_decode($string) as $var) {
			$return .= self::charset_decode($var->text,$var->charset);
		}
		return $return;
	}

	public static function address($string) {
		$address = [];
		foreach(explode(',',$string) as $input) {
			$input = self::header_decode($input);
			if(mb_strpos($input,'<')!==false && mb_strpos($input,'>')!==false) {
				$input = mb_substr($input,mb_strpos($input,'<')+1,(mb_strpos($input,'>')-mb_strpos($input,'<'))-1);
			}
			$address[] = trim($input);
		}
		if(empty($address)) {
			throw new \Exception('Invalid address');
		}
		return $address;
	}

	public static function subaddress($input) {
		$length =  mb_strrpos($input,'@') - mb_strpos($input,'+')-1;
		$subaddress = trim(mb_substr($input,mb_strpos($input,'+')+1,$length));
		if(empty($subaddress)) {
			throw new \Exception('No subaddress');
		}
		return explode('+',$subaddress);
	}

	private static function charset_decode($string,$charset) {
		if($charset === null) {
			return $string;
		}
		$charset = strtolower($charset);
		if($charset=='utf-8') {
			return $string;
		}
		elseif($charset=='iso-8859-1') {
			return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
		}
		elseif($charset=='iso-8859-9') {
			return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-9');
		}
		elseif($charset=='default') {
			return $string;
		}
		elseif($charset=='us-ascii') {
			return $string;
		}
		elseif($charset=='windows-1252') {
			return mb_convert_encoding($string, 'UTF-8', 'Windows-1252');
		}
		throw new \Exception('Invalid charset: '.$charset);
	}
}
