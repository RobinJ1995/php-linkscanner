# LinkScanner

PHP library to recursively scan a website for links.

## Usage

The `LinkScanner` class has only 3 public methods: the constructor, `scan` and `findBrokenLink`.

* The constructor takes one parameter; the base url.
* The `scan` method takes one parameter: `$includeOutbound`. If `true`, scan results will contain links which are not on the same domain as the base url (external links will not be recursively added).
* The `findBrokenLinks` method takes no parameters and will return any links that do not return an HTTP 200 status code (including outbound links).

## Example

```php
<?php
require_once ('LinkScanner.php');

$scanner = new LinkScanner ('https://robinj.be/');
$links = $scanner->scan (true);

echo '# ' . count ($links) . ' links found' . PHP_EOL . PHP_EOL;
foreach ($links as $link)
	echo '* ' . $link . PHP_EOL;
```
