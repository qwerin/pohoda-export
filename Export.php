<?php

namespace Pohoda;

class Export
{

	public static $NS_TYPE = 'http://www.stormware.cz/schema/version_2/type.xsd';

	public $ico = '';

	private $invoices = [];
	private $lastId = 0;
	private $exportFolder;

	public function __construct($ico)
	{
		$this->ico = $ico;
	}

	/**
	 * Set absolute path to folder for xml export
	 * @param string $path
	 * @return $this
	 */
	public function setExportFolder($path)
	{
		$path = rtrim($path, DIRECTORY_SEPARATOR);
		if (is_writable($path) === false)
			throw new InvalidArgumentException("$path is not writable");

		$this->exportFolder = $path;
		return $this;
	}

	public function setInvoice($invoice)
	{
		$this->invoices[] = $invoice;
	}

	public function exportToFile($exportId, $application, $fileName, $errorsNo, $note = '')
	{

		$xml = $this->export($exportId, $application, $note);
		$incomplete = '';
		if ($errorsNo > 0) {
			$incomplete = '_incomplete';
		}
		$xml->asXML((is_null($this->exportFolder) ? dirname(__FILE__) : $this->exportFolder) . '/' . $fileName . '_lastId-' . $this->lastId . $incomplete . '.xml');
	}

	public function exportAsXml($exportId, $application, $note = '')
	{
		header("Content-Type:text/xml; charset=utf-8");
		$xml = $this->export($exportId, $application, $note);
		echo $xml->asXML();
	}

	public function exportAsString($exportId, $application, $note = '')
	{
		$xml = $this->export($exportId, $application, $note);
		echo $xml->asXML();
	}

	private function export($exportId, $application, $note = '')
	{
		$xmlText = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<dat:dataPack id=\"" . $exportId . "\" ico=\"" . $this->ico . "\" application=\"" . $application . "\" version = \"2.0\" note=\"" . $note . "\" xmlns:dat=\"http://www.stormware.cz/schema/version_2/data.xsd\"></dat:dataPack>";
		$xml = simplexml_load_string($xmlText);

		$i = 0;
		/** @var Invoice $item */
		foreach ($this->invoices as $item) {
			$i++;
			$dataItem = $xml->addChild("dat:dataPackItem");
			$dataItem->addAttribute('version', "2.0");
			$dataItem->addAttribute('id', $exportId . '-' . $i);

			$item->export($dataItem);

			if ($item->varNum > $this->lastId) {
				$this->lastId = $item->varNum;
			}
		}

		return $xml;
	}
}