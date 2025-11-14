<?php foreach (glob("config/*.php") as $f){ $v=require $f; if(!is_array($v)) echo basename($f), ": ", gettype($v), PHP_EOL; }
