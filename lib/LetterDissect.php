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
			if($param->attribute=='CHARSET') {
				$charset = strtolower($param->value);
			}
		}
		if($charset=='windows-1252') {
			$body = mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
		}
		elseif($charset=='iso-8859-1') {
			$body = mb_convert_encoding($body, 'UTF-8', 'ISO-8859-1');
		}
		return $body;
	}
}
