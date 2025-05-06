<?php
/*
LetterDissect is licensed under the Apache License 2.0 license
https://github.com/TRP-Solutions/letter-dissect/blob/main/LICENSE
*/
declare(strict_types=1);

class LetterClean {
	public static function run(IMAP\Connection $mbox,DateTime $filter) : object {
		$num_msg = imap_num_msg($mbox);
		$count = 0;
		if($num_msg) {
			for($message_num=1;$message_num<=$num_msg;$message_num++) {
				$header = imap_headerinfo($mbox, $message_num);
				$date = new DateTime($header->date);
				if($date <= $filter) {
					$count++;
					imap_delete($mbox,(string) $message_num);
				}
			}
		}
		imap_expunge($mbox);
		return (object) ['total' => $num_msg,'cleaned' => $count];
	}
}
