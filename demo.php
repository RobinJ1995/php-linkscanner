<?php

require_once ('LinkScanner.php');

$scanner = new LinkScanner ('https://robinj.be/');
$links = $scanner->scan (true);

echo '# ' . count ($links) . ' links found' . PHP_EOL . PHP_EOL;
foreach ($links as $link)
	echo '* ' . $link . PHP_EOL;

