<?php
/*
LetterDissect is licensed under the Apache License 2.0 license
https://github.com/TRP-Solutions/letter-dissect/blob/main/LICENSE
*/

class LetterDissect {
	private $mbox;
	private $message_num;
	private $structure = [];

	function __construct($mbox,$message_num) {
		$this->mbox = $mbox;
		$this->message_num = $message_num;
		$structure = imap_fetchstructure($this->mbox, $message_num);
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

	public function fetchbody($section) {
		if(!isset($this->structure[$section])) {
			throw new \Exception('Invalid section');
		}

		$body = imap_fetchbody($this->mbox,$this->message_num,$section);
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

	private static function charset_decode($string,$charset) {
		$charset = strtolower($charset);
		if($charset == null) {
			return $string;
		}
		elseif($charset=='iso-8859-1') {
			return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
		}
		elseif($charset=='utf-8') {
			return $string;
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
