<?php

require_once ('Curl.php');

class LinkScanner
{
	private $base;
	private $links = [];
	private $outboundLinks = [];
	private $brokenLinks = [];
	private $debug = false;
	
	public function __construct ($base)
	{
		$this->base = $this->trailingSlash ($base);
	}
	
	public function scan ($includeOutbound = false)
	{
		$this->links = [];
		$this->outboundLinks = [];
		$this->brokenLinks = [];
		
		$this->loadLinks (NULL, $includeOutbound);
		
		return array_merge ($this->links, $this->outboundLinks);
	}
	
	public function findBrokenLinks ()
	{
		$this->links = [];
		$this->outboundLinks = [];
		$this->brokenLinks = [];
		
		$this->loadLinks (NULL, false, true);
		
		return $this->brokenLinks;
	}
	
	private function loadLinks ($url = NULL, $collectOutbound = false, $findBroken = false)
	{
		if ($url === NULL)
			$url = $this->base;
		
		$this->addLink ($url);
		
		$this->log ('Loading: ' . $url);
		$curl = new Curl ($url);
		$curl->followlocation = true;
		$curl->useragent = 'PHP LinkScanner';
		$curlResult = $curl->exec ();
		
		if ($findBroken && $curlResult->http_code != 200)
			$this->addBrokenLink ($url);
		
		if ($curlResult->content_type === NULL || ! $this->startsWith ($curlResult->content_type, 'text/html'))
			return [];
		
		libxml_use_internal_errors (true); // It doesn't like HTML5 tags //
		
		$doc = new DOMDocument ();
		$doc->loadHTML ($curlResult->content);
		$linkElements = $doc->getElementsByTagName ('a');
		$this->log ('Links found: ' . $linkElements->length);
		
		foreach ($linkElements as $linkElement)
		{
			$href = $this->urlStripHashtag ($linkElement->getAttribute ('href'));
			$this->log ('Processing link: ' . $href);
			
			if (empty ($href) || $this->startsWith ($href, ['tel:', 'mailto:']))
				continue;
			else if ($this->startsWith ($href, ['http://', 'https://']))
				{}
			else if ($this->startsWith ($href, '/') || ctype_alnum ($href[0]))
				$href = $this->base . ltrim ($href, '/');
			else
				continue;
			
			$outbound = ! $this->startsWith ($href, $this->base);
			if ($collectOutbound || ! $outbound)
				$this->addLink ($href, $outbound) && $this->loadLinks ($href, $collectOutbound);
			else if ($outbound && $findBroken)
				$this->checkBroken ($href);
		}
		
		return $this->links;
	}
	
	private function checkBroken ($url)
	{
		$this->log ('Checking if link is broken: ' . $url);
		
		$curl = new Curl ($url);
		$curl->followlocation = true;
		$curl->header = true;
		$curl->nobody = true;
		$curl->timeout = 5;
		$curl->useragent = 'PHP LinkScanner';
		$curlResult = $curl->exec ();
		
		$this->log ('Link ' . $url . ' returned HTTP status ' . $curlResult->http_code);
		
		$broken = $curlResult->http_code != 200;
		if ($broken)
			$this->addBrokenLink ($url);
		
		return $broken;
	}
	
	private function addLink ($link, $outbound = false)
	{
		if (in_array ($link, $this->links))
			return false;
		
		$this->log ('Adding link: ' . $link);
		if ($outbound)
			$this->outboundLinks[] = $link;
		else
			$this->links[] = $link;
		
		return ! $outbound;
	}
	
	private function addBrokenLink ($link)
	{
		if (in_array ($link, $this->brokenLinks))
			return false;
		
		$this->log ('Adding broken link: ' . $link);
		$this->brokenLinks[] = $link;
		
		return true;
	}
	
	private function startsWith ($haystack, $needles)
	{
		foreach ((array) $needles as $needle)
		{
			if (substr ($haystack, 0, strlen ($needle)) === $needle)
				return true;
		}
		
		return false;
	}
	
	
	private static function endsWith ($haystack, $needles)
	{
		foreach ((array) $needles as $needle)
		{
			if ((string) $needle === substr ($haystack, -strlen ($needle)))
				return true;
		}
		
		return false;
	}
	
	
	private function urlStripHashtag ($url)
	{
		$hashtagPos = strrpos ($url, '#');
	
		if ($hashtagPos === false)
			return $url;
	
		return substr ($url, 0, $hashtagPos);
	}
	
	private function trailingSlash ($str)
	{
		if (! $this->endsWith ($str, '/'))
			$str .= '/';
	
		return $str;
	}
	
	private function log ($message)
	{
		if ($this->debug)
			echo '[LinkScanner] ' . $message . PHP_EOL;
	}
}
