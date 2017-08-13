<?php

namespace CliApp;

/* ISO 8601
 * See: https://en.wikipedia.org/wiki/ISO_8601
 */
class TimeStamp{

	public function makeTimeStamp(){
	
		return date ("Y-m-d H:i:s");
	}
}

?>