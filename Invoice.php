<?php

namespace Pohoda;

use SimpleXMLElement;
use DateTime;

class Invoice
{

	public static $NS_INVOICE = 'http://www.stormware.cz/schema/version_2/invoice.xsd';

	private $withVAT = false;

	public $type = 'issuedInvoice';
	private $paymentType = 'draft';

	private $varNum;
	private $date;
	private $dateTax;
	private $dateAccounting;
	private $dateDue;
	private $text;
	private $bankShortcut = 'FIO';
	private $note;

	public $paymentTypeCzech = 'příkazem';
	private $accounting;
	public $symbolicNumber = '0308';

	public $quantity = '1.0';
	public $coefficient = '1.0';

	public $priceTotal = 0;
	public $priceWithoutVAT = 0;
	public $priceOnlyVAT;
	/** Zakazka
	 * @var string
	 */
	public $contract;

	private $myIdentity = [];

	private $partnerIdentity = [];

	private $id;
	private $errors = [];
	private $reqErrors = [];
	private $required = ['date', 'varNum', 'text'];

	public function __construct($id)
	{
		$this->id = $id;
		$this->setProviderIdentity([]);
		$this->setPurchaserIdentity([]);

	}

	public function setWithVat($bool)
	{
		if (is_bool($bool) === false)
			throw new \InvalidArgumentException("setWithVat must use boolead value");
		$this->withVAT = $bool;
	}

	public function getId()
	{
		return $this->id;
	}

	public function isValid()
	{
		return $this->checkRequired() && empty($this->errors);
	}

	private function checkRequired()
	{
		$result = true;
		$this->reqErrors = [];

		foreach ($this->required as $param) {
			if (!isset($this->$param)) {
				$result = false;
				$this->reqErrors[] = 'Není nastaven povinný prvek ' . $param;
			}
		}

		return $result;
	}

	private function validateItem($name, $value, $maxLength = false, $isNumeric = false, $isDate = false)
	{

		if ($maxLength !== false) {
			if (strlen($value) > $maxLength) {
				$this->errors[] = $name . '="' . $value . '" - překročilo maximální délku ' . $maxLength;
			}
		}

		if ($isNumeric) {
			if (!is_numeric($value)) {
				$this->errors[] = $name . '="' . $value . '" - není číslo';
			}
		}

		if ($isDate) {
			if (!($value instanceof DateTime) && !date_create($value)) {
				$this->errors[] = $name . '="' . $value . '" - není datum';
			}
		}
	}

	private function removeSpaces($value)
	{
		return preg_replace('/\s+/', '', $value);
	}

	private function convertDate($date)
	{
		if ($date instanceof DateTime)
			return $date->format("Y-m-d");

		return $date;
	}

	public function getErrors()
	{
		$arr = array_merge($this->errors, $this->reqErrors);

		$fce = function ($row) {
			return $this->id . ':' . $row;
		};
		$arr = array_map($fce, $arr);

		return $arr;
	}

	public function setVariableNumber($value)
	{
		$value = $this->removeSpaces($value);
		$this->validateItem('variable number', $value, 20, true);
		$this->varNum = $value;
	}

	public function setDateCreated($value)
	{
		$this->validateItem('date created', $value, false, false, true);
		$this->date = $this->convertDate($value);
	}

	public function setDateTax($value)
	{
		$this->validateItem('date tax', $value, false, false, true);
		$this->dateTax = $this->convertDate($value);
	}

	public function setDateAccounting($value)
	{
		$this->validateItem('date accounting', $value, false, false, true);
		$this->dateAccounting = $this->convertDate($value);
	}

	public function setDateDue($value)
	{
		$this->validateItem('date due', $value, false, false, true);
		$this->dateDue = $this->convertDate($value);
	}

	public function setText($value)
	{
		$this->validateItem('text', $value, 240);
		$this->text = $value;
	}

	public function setBank($value)
	{
		$this->validateItem('bank shortcut', $value, 19);
		$this->bankShortcut = $value;
	}

	public function setPaymentType($value)
	{
		$payments = [
			"draft" => "příkazem",
			"cash" => "hotově",
			"postal" => "složenkou",
			"delivery" => "dobírka",
			"creditcard" => "platební kartou",
			"advance" => "zálohová faktura",
			"encashment" => "inkasem",
			"cheque" => "šekem",
			"compensation" => "zápočtem"
		];

		if (is_null($value) || !isset($payments[$value])) {
			$this->errors[] = "Payment type $value is not supported. Use one of these: " . explode(",", array_keys($payments));
		}

	}

	public function setPaymentTypeCzech($value)
	{
		$this->validateItem('payment type czech', $value, 19);
		$this->paymentTypeCzech = $value;
	}

	public function setAccounting($value)
	{
		$this->validateItem('accounting', $value, 19);
		$this->accounting = $value;
	}

	public function setNote($value)
	{
		$this->note = $value;
	}

	public function setContract($value)
	{
		$this->validateItem('contract', $value, 10);
		$this->contract = $value;
	}

	public function setSymbolicNumber($value)
	{
		$value = $this->removeSpaces($value);
		$this->validateItem('symbolic number', $value, 20, true);
		$this->symbolicNumber = $value;
	}

	public function setPrice($value)
	{
		$this->validateItem('price', $value, false, true);
		$this->priceTotal = $value;
	}

	public function setPriceWithoutVAT($value)
	{
		$this->validateItem('price without VAT', $value, false, true);
		$this->priceWithoutVAT = round($value, 2);
	}

	public function setPriceOnlyVAT($value)
	{
		$this->validateItem('price only VAT', $value, false, true);
		$this->priceOnlyVAT = round($value, 2);
	}

	public function setQuantity($value)
	{
		$this->validateItem('price', $value, false, true);
		$this->quantity = $value;
	}

	public function setProviderIdentity($value)
	{
		if (isset($value['zip'])) {
			$value['zip'] = $this->removeSpaces($value['zip']);
		}
		if (isset($value['ico'])) {
			$value['ico'] = $this->removeSpaces($value['ico']);
		}

		if (isset($value['company'])) {
			$this->validateItem('provider - company', $value['company'], 96);
		}
		if (isset($value['street'])) {
			$this->validateItem('provider - street', $value['street'], 64);
		}
		if (isset($value['zip'])) {
			$this->validateItem('provider - zip', $value['zip'], 15, true);
		}
		if (isset($value['city'])) {
			$this->validateItem('provider - city', $value['city'], 45);
		}
		if (isset($value['ico'])) {
			$this->validateItem('provider - ico', $value['ico'], 15, true);
		}
		if (isset($value['number'])) {
			$this->validateItem('provider - number', $value['number'], 10);
		}

		$this->myIdentity = $value;
	}

	public function setPurchaserIdentity($value)
	{
		if (isset($value['zip'])) {
			$value['zip'] = $this->removeSpaces($value['zip']);
		}
		if (isset($value['ico'])) {
			$value['ico'] = $this->removeSpaces($value['ico']);
		}

		if (isset($value['company'])) {
			$this->validateItem('purchaser - company', $value['company'], 96);
		}
		if (isset($value['division'])) {
			$this->validateItem('purchaser - division', $value['division'], 32);
		}
		if (isset($value['street'])) {
			$this->validateItem('purchaser - street', $value['street'], 64);
		}
		if (isset($value['zip'])) {
			$this->validateItem('purchaser - zip', $value['zip'], 15, true);
		}
		if (isset($value['city'])) {
			$this->validateItem('purchaser - city', $value['city'], 45);
		}
		if (isset($value['ico'])) {
			$this->validateItem('purchaser - ico', $value['ico'], 15, true);
		}
		if (isset($value['number'])) {
			$this->errors[] = 'purchaser nesmi mit nastaven type: number';
		}

		$this->partnerIdentity = $value;
	}

	public function export(SimpleXMLElement $xml)
	{
		$xmlInvoice = $xml->addChild("inv:invoice", null, self::$NS_INVOICE);
		$xmlInvoice->addAttribute('version', "2.0");


		$this->exportHeader($xmlInvoice->addChild("inv:invoiceHeader", null, self::$NS_INVOICE));
		if ($this->withVAT) {
			$this->exportDetail($xmlInvoice->addChild("inv:invoiceDetail", null, self::$NS_INVOICE));
		}
		$this->exportSummary($xmlInvoice->addChild("inv:invoiceSummary", null, self::$NS_INVOICE));
	}

	private function exportHeader(SimpleXMLElement $header)
	{

		$header->addChild("inv:invoiceType", $this->type);
		$num = $header->addChild("inv:number");
		$num->addChild('typ:numberRequested', $this->getId(), Export::$NS_TYPE);

		$header->addChild("inv:symVar", $this->varNum);

		$header->addChild("inv:date", $this->date);
		if (!is_null($this->dateTax))
			$header->addChild("inv:dateTax", $this->dateTax);
		if (!is_null($this->dateDue))
			$header->addChild("inv:dateDue", $this->dateDue);
		if (!is_null($this->dateAccounting))
			$header->addChild("inv:dateAccounting", $this->dateAccounting);


		$classification = $header->addChild("inv:classificationVAT");
		if ($this->withVAT) {
			//tuzemske plneni
			$classification->addChild('typ:classificationVATType', 'inland', Export::$NS_TYPE);
		} else {
			//nezahrnovat do dph
			$classification->addChild('typ:ids', 'UN', Export::$NS_TYPE);
			$classification->addChild('typ:classificationVATType', 'nonSubsume', Export::$NS_TYPE);
		}

		if (!is_null($this->accounting)) {
			$accounting = $header->addChild("inv:accounting");
			$accounting->addChild('typ:ids', $this->accounting, Export::$NS_TYPE);
		}

		$header->addChild("inv:text", $this->text);

		$paymentType = $header->addChild("inv:paymentType");
		$paymentType->addChild('typ:paymentType', $this->paymentType, Export::$NS_TYPE);
		$paymentType->addChild('typ:ids', $this->paymentTypeCzech, Export::$NS_TYPE);

		$account = $header->addChild("inv:account");
		$account->addChild('typ:ids', $this->bankShortcut, Export::$NS_TYPE);

		if (isset($this->note)) {
			$header->addChild("inv:note", $this->note);
		}

		$header->addChild("inv:intNote", 'Tento doklad byl vytvořen importem přes XML.');

		if (isset($this->contract)) {
			$contract = $header->addChild("inv:contract");
			$contract->addChild('typ:ids', $this->contract, Export::$NS_TYPE);
		}

		$header->addChild("inv:symConst", $this->symbolicNumber);

		/** Pouze pri exportu z pohody.
		 * $liq = $header->addChild("inv:liquidation");
		 * $liq->addChild('typ:amountHome', $this->priceTotal, Export::$NS_TYPE);
		 */

		$myIdentity = $header->addChild("inv:myIdentity");
		$this->exportAddress($myIdentity, $this->myIdentity);

		$partnerIdentity = $header->addChild("inv:partnerIdentity");
		$this->exportAddress($partnerIdentity, $this->partnerIdentity);


	}

	private function exportDetail(SimpleXMLElement $detail)
	{

		$item = $detail->addChild("inv:invoiceItem");
		$item->addChild("inv:quantity", $this->quantity);
		$item->addChild("inv:coefficient", $this->coefficient);
		$item->addChild("inv:payVAT", $this->withVAT ? 'true' : 'false');
		$item->addChild("inv:rateVAT", 'high');
		$item->addChild("inv:discountPercentage", '0.0');

		$hc = $item->addChild("inv:homeCurrency");
		$hc->addChild('typ:unitPrice', $this->priceWithoutVAT, Export::$NS_TYPE);
		$hc->addChild('typ:price', $this->priceWithoutVAT, Export::$NS_TYPE);
		$hc->addChild('typ:priceVAT', $this->priceOnlyVAT, Export::$NS_TYPE);
		$hc->addChild('typ:priceSum', $this->priceTotal, Export::$NS_TYPE);

	}

	private function exportAddress($xml, Array $data)
	{

		$address = $xml->addChild('typ:address', null, Export::$NS_TYPE);

		if (isset($data['company'])) {
			$address->addChild('typ:company', $data['company']);
		}

		if (isset($data['division'])) {
			$address->addChild('typ:division', $data['division']);
		}

		if (isset($data['city'])) {
			$address->addChild('typ:city', $data['city']);
		}

		if (isset($data['street'])) {
			$address->addChild('typ:street', $data['street']);
		}

		if (isset($data['number'])) {
			$address->addChild('typ:number', $data['number']);
		}

		if (isset($data['zip'])) {
			$address->addChild('typ:zip', $data['zip']);
		}

		if (isset($data['ico'])) {
			$address->addChild('typ:ico', $data['ico']);
		}

		if (isset($data['dic'])) {
			$address->addChild('typ:dic', $data['dic']);
		}
	}

	private function exportSummary(SimpleXMLElement $summary)
	{

		$summary->addChild("inv:roundingDocument", 'up2one');
		$summary->addChild("inv:roundingVAT", 'none');

		$hc = $summary->addChild("inv:homeCurrency");
		$hc->addChild('typ:priceNone', $this->priceTotal, Export::$NS_TYPE);
		$hc->addChild('typ:priceLow', 0, Export::$NS_TYPE);
		$hc->addChild('typ:priceLowVAT', 0, Export::$NS_TYPE);
		$hc->addChild('typ:priceLowSum', 0, Export::$NS_TYPE);
		$hc->addChild('typ:priceHigh', 0, Export::$NS_TYPE);
		$hc->addChild('typ:priceHighVAT', 0, Export::$NS_TYPE);
		$hc->addChild('typ:priceHighSum', 0, Export::$NS_TYPE);

		$round = $hc->addChild('typ:round', null, Export::$NS_TYPE);
		$round->addChild('typ:priceRound', 0, Export::$NS_TYPE);


	}
}

