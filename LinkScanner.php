<?php

require_once ('Curl.php');

class LinkScanner
{
	private $base;
	private $links = [];
	private $outboundLinks = [];
	private $brokenLinks = [];
	private $checkedBrokenOutbound = [];
	public $debug = false;
	public $timeout = 5;
	
	public function __construct ($base)
	{
		$this->base = $this->trailingSlash ($base);
	}
	
	public function scan ($includeOutbound = false, $maxDepth = NULL)
	{
		$this->init ();
		
		$this->loadLinks (NULL, $includeOutbound, NULL, $maxDepth);
		
		return array_merge ($this->links, $this->outboundLinks);
	}
	
	public function findBrokenLinks ($maxDepth = NULL)
	{
		$this->init ();
		
		$this->loadLinks (NULL, false, true, $maxDepth);
		
		return $this->brokenLinks;
	}
	
	private function loadLinks ($url = NULL, $collectOutbound = false, $findBroken = false, $maxDepth = NULL, $depth = 1)
	{
		if ($maxDepth !== NULL && $depth > $maxDepth)
			return $this->links;
		
		if ($url === NULL)
			$url = $this->base;
		
		$this->addLink ($url);
		
		$this->log ('Loading: ' . $url, $depth);
		$curl = new Curl ($url);
		$curl->followlocation = true;
		$curl->timeout = $this->timeout;
		$curl->connecttimeout = $this->timeout;
		$curl->useragent = 'PHP LinkScanner';
		
		try
		{
			$curlResult = $curl->exec ();
		}
		catch (CurlException $ex)
		{
			if ($findBroken && $ex->code != 28) // Curl error code 28 == Timeout //
				$this->addBrokenLink ($url);
			
			$this->log ('Loading ' . $url . ' failed: ' . $ex->getMessage ());
			
			return $this->links;
		}
		
		if ($findBroken && $curlResult->http_code != 200)
			$this->addBrokenLink ($url);
		
		if ($curlResult->content_type === NULL || ! $this->startsWith ($curlResult->content_type, 'text/html'))
			return [];
		
		libxml_use_internal_errors (true); // It doesn't like HTML5 tags //
		
		$doc = new \DOMDocument ();
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
			
			$outbound = ! $this->startsWith ($href, $this->httphttps ($this->base));
			if ($collectOutbound || ! $outbound)
				$this->addLink ($href, $outbound) && $this->loadLinks ($href, $collectOutbound, $findBroken, $maxDepth, $depth + 1);
			else if ($outbound && $findBroken && ($maxDepth === NULL || $depth <= $maxDepth))
				$this->checkBroken ($href);
		}
		
		return $this->links;
	}
	
	private function init ()
	{
		$this->links = [];
		$this->outboundLinks = [];
		$this->brokenLinks = [];
		$this->checkedBrokenOutbound = [];
	}
	
	private function checkBroken ($url)
	{
		if (in_array ($url, $this->checkedBrokenOutbound))
			return false;
		
		$this->log ('Checking if link is broken: ' . $url);
		
		$curl = new Curl ($url);
		$curl->followlocation = true;
		$curl->header = true;
		$curl->nobody = true;
		$curl->timeout = $this->timeout;
		$curl->connecttimeout = $this->timeout;
		$curl->useragent = 'PHP LinkScanner';
		
		try
		{
			$curlResult = $curl->exec ();
		}
		catch (CurlException $ex)
		{
			$this->log ('Loading ' . $url . ' failed: ' . $ex->getMessage ());
			
			if ($ex->code != 28) // Curl error code 28 == Timeout //
			{
				$this->addBrokenLink ($url);
				$this->checkedBrokenOutbound[] = $url;
				
				return true;
			}
			
			return false;
		}
		
		$this->log ('Link ' . $url . ' returned HTTP status ' . $curlResult->http_code);
		
		$broken = $curlResult->http_code != 200;
		if ($broken)
			$this->addBrokenLink ($url);
		$this->checkedBrokenOutbound[] = $url;
		
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
	
	private function httphttps ($url)
	{
		if ($this->startsWith ($url, 'http://'))
			return [ $url, substr_replace ($url, 's', 4, 0) ];
		else
			return [ substr_replace ($url, '', 4, 1), $url ];
	}
	
	private function log ($message, $tag = NULL)
	{
		if ($this->debug)
			echo '[LinkScanner' . (empty ($tag) ? '' : ':' . $tag) . '] ' . $message . PHP_EOL;
	}
}
