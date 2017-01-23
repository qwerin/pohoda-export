<?php

namespace Pohoda;

use SimpleXMLElement;
use DateTime;

class Invoice
{

	public static $NS_INVOICE = 'http://www.stormware.cz/schema/version_2/invoice.xsd';

	private $withVAT = false;

	public $type = 'issuedInvoice'; //normalni faktura
	private $paymentType = 'draft';
	private $roundingDocument = 'math2one';
	private $roundingVAT = 'none';

	private $varNum;
	private $date;
	private $dateTax;
	private $dateAccounting;
	private $dateDue;
	private $text;
	private $bankShortcut = 'FIO';
	private $note;

	private $paymentTypeCzech; //identifikator formy uhrady
	private $accounting;
	private $symbolicNumber = '0308';

	private $priceNone;
	private $priceLow;
	private $priceLowVAT;
	private $priceLowSum;
	private $priceHigh;
	private $priceHightVAT;
	private $priceHighSum;

	/** Zakazka
	 * @var string
	 */
	private $contract;

	private $items = [];

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
		try {
			if ($maxLength)
				Validators::assertMaxLength($value, $maxLength);
			if ($isNumeric)
				Validators::assertNumeric($value);
			if ($isDate)
				Validators::assertDate($value);

		} catch (\InvalidArgumentException $e) {
			$this->errors[] = $name . " " . $e->getMessage();
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

	public function addItem(InvoiceItem $item)
	{
		$this->items[] = $item;
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

	/**
	 * Set price in nullable VAT
	 * @param float $priceNone
	 */
	public function setPriceNone($priceNone)
	{
		$this->priceNone = $priceNone;
	}

	/**
	 * Set price without VAT (DPH - 15%)
	 * @param float $priceLow
	 */
	public function setPriceLow($priceLow)
	{
		$this->priceLow = $priceLow;
	}

	/**
	 * Set only VAT (DPH - 15%)
	 * @param float $priceLowVAT
	 */
	public function setPriceLowVAT($priceLowVAT)
	{
		$this->priceLowVAT = $priceLowVAT;
	}

	/**
	 * Set price with VAT (DPH - 15%)
	 * @param float $priceLowSum
	 */
	public function setPriceLowSum($priceLowSum)
	{
		$this->priceLowSum = $priceLowSum;
	}

	/**
	 * Set price without VAT (DPH - 21%)
	 * @param float $priceHigh
	 */
	public function setPriceHigh($priceHigh)
	{
		$this->priceHigh = $priceHigh;
	}

	/**
	 * Set only VAT (DPH - 21%)
	 * @param float $priceHightVAT
	 */
	public function setPriceHightVAT($priceHightVAT)
	{
		$this->priceHightVAT = $priceHightVAT;
	}

	/**
	 * Set price with VAT (DPH - 21%)
	 * @param float $priceHighSum
	 */
	public function setPriceHighSum($priceHighSum)
	{
		$this->priceHighSum = $priceHighSum;
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

	/**
	 * Get shop identity
	 * @return array
	 */
	public function getProviderIdentity() {
		return $this->myIdentity;
	}


	public function setPurchaserIdentity($value)
	{
		if (isset($value['zip'])) {
			$value['zip'] = $this->removeSpaces($value['zip']);
		}
		if (isset($value['ico'])) {
			$value['ico'] = $this->removeSpaces($value['ico']);
		}

		if (isset($value['name'])) {
			$this->validateItem('purchaser - name', $value['name'], 32);
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

		$this->partnerIdentity = $value;
	}

	public function export(SimpleXMLElement $xml)
	{
		$xmlInvoice = $xml->addChild("inv:invoice", null, self::$NS_INVOICE);
		$xmlInvoice->addAttribute('version', "2.0");


		$this->exportHeader($xmlInvoice->addChild("inv:invoiceHeader", null, self::$NS_INVOICE));
		if (!empty($this->items)) {
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
		if(!is_null($this->paymentTypeCzech)) {
			$paymentType->addChild('typ:ids', $this->paymentTypeCzech, Export::$NS_TYPE);
		}

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
		foreach ($this->items AS $product) {
			/** @var InvoiceItem $product */
			$item = $detail->addChild("inv:invoiceItem");
			$item->addChild("inv:text", $product->getText());
			$item->addChild("inv:quantity", $product->getQuantity());
			$item->addChild("inv:unit", $product->getUnit());
			$item->addChild("inv:coefficient", $product->getCoefficient());
			$item->addChild("inv:payVAT", $product->isPayVAT() ? 'true' : 'false');
			if ($product->getRateVAT())
				$item->addChild("inv:rateVAT", $product->getRateVAT());
			if ($product->getDiscountPercentage())
				$item->addChild("inv:discountPercentage", $product->getDiscountPercentage());

			if (!empty($product->getHomeCurrency())) {
				$hc = $item->addChild("inv:homeCurrency");
				if ($product->getUnitPrice())
					$hc->addChild("typ:unitPrice", $product->getUnitPrice(), Export::$NS_TYPE);
				if($product->getPrice())
					$hc->addChild("typ:price", $product->getPrice(), Export::$NS_TYPE);
				if($product->getPriceVAT())
					$hc->addChild("typ:priceVAT", $product->getPriceVAT(), Export::$NS_TYPE);
			}

			$item->addChild("inv:note", $product->getNote());
			$item->addChild("inv:code", $product->getCode());
			$item->addChild("inv:guarantee", $product->getGuarantee());
			$item->addChild("inv:guaranteeType", $product->getGuaranteeType());

			//info o skladove polozce
			if ($product->getStockItem()) {
				$stock = $item->addChild("inv:stockItem");
				$stockItem = $stock->addChild("typ:stockItem", null, Export::$NS_TYPE);
				$stockItem->addChild("typ:ids", $product->getStockItem(), Export::$NS_TYPE);
			}
		}
	}

	private function exportAddress(SimpleXMLElement $xml, Array $data)
	{

		$address = $xml->addChild('typ:address', null, Export::$NS_TYPE);

		if (isset($data['company'])) {
			$address->addChild('typ:company', $data['company']);
		}

		if (isset($data['name'])) {
			$address->addChild('typ:name', $data['name']);
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

		$summary->addChild('inv:roundingDocument', $this->roundingDocument); //matematicky na koruny
		$summary->addChild('inv:roundingVAT', $this->roundingVAT);

		$hc = $summary->addChild("inv:homeCurrency");
		if(is_null($this->priceNone) === false)
			$hc->addChild('typ:priceNone', $this->priceNone, Export::$NS_TYPE); //cena v nulove sazbe dph
		if(is_null($this->priceLow) === false)
			$hc->addChild('typ:priceLow', $this->priceLow, Export::$NS_TYPE); //cena bez dph ve snizene sazbe (15)
		if(is_null($this->priceLowVAT) === false)
			$hc->addChild('typ:priceLowVAT', $this->priceLowVAT, Export::$NS_TYPE); //dph ve snizene sazbe
		if(is_null($this->priceLowSum) === false)
			$hc->addChild('typ:priceLowSum', $this->priceLowSum, Export::$NS_TYPE); //s dph ve snizene sazbe
		if(is_null($this->priceHigh) === false)
			$hc->addChild('typ:priceHigh', $this->priceHigh, Export::$NS_TYPE); //cena bez dph ve zvysene sazbe (21)
		if(is_null($this->priceHightVAT) === false)
			$hc->addChild('typ:priceHighVAT', $this->priceHightVAT, Export::$NS_TYPE);
		if(is_null($this->priceHighSum) === false)
			$hc->addChild('typ:priceHighSum', $this->priceHighSum, Export::$NS_TYPE);

		$round = $hc->addChild('typ:round', null, Export::$NS_TYPE);
		$round->addChild('typ:priceRound', 0, Export::$NS_TYPE); //Celková suma zaokrouhleni

	}
}

