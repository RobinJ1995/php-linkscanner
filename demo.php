<?php

require_once ('LinkScanner.php');

$scanner = new LinkScanner ('https://robinj.be/');
$links = $scanner->scan (true);
$broken = $scanner->findBrokenLinks ();

echo '# ' . count ($links) . ' links found' . PHP_EOL . PHP_EOL;
foreach ($links as $link)
	echo '* ' . $link . PHP_EOL;

echo PHP_EOL;

echo '# ' . count ($broken) . ' broken links found' . PHP_EOL . PHP_EOL;
foreach ($broken as $brokenLink)
	echo '* ' . $brokenLink . PHP_EOL;
