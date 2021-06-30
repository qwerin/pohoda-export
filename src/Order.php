<?php

namespace Pohoda;

use Pohoda\Export\Address;
use SimpleXMLElement;
use DateTime;


class Order
{
    const NS = 'http://www.stormware.cz/schema/version_2/order.xsd';

    const ORDER_TYPE = 'receivedOrder'; //Prijata objednavka
    const CORRECTIVE_TYPE = 'issuedCorrectiveTax'; //opravny danovy doklad

    private $cancel; //cislo stornovaneho dokumentu
    private $cancelNumber; //ciselna rada pro storno

    private $delete; //cislo delete dokumentu

    private $update=false;

    private $withVAT = false;

    public $type = self::ORDER_TYPE;
    private $paymentType = 'draft';
    private $roundingDocument = 'math2one';
    private $roundingVAT = 'none';


    private $date;
    private $dateDelivery;
    private $dateFrom;
    private $dateTo;
    private $text;
    private $bankShortcut = 'FIO';
    private $note;

    private $paymentTypeString; //forma uhrady



    private $priceNone;
    private $priceLow;
    private $priceLowVAT;
    private $priceLowSum;
    private $priceHigh;
    private $priceHightVAT;
    private $priceHighSum;

    private $numberOrder; //vlastni cislo objednavky

    private $centre; //stredisko
    private $activity; //cinnost
    private $priceLevel; //Cenová hladinu odběratele. Jen přijaté objednávky.
    private $isReserved; //Rezervováno, pouze přijaté objednávky. Při importu dokladu je možné zásoby zarezervovat na skladě.

    private $regVATinEU;

    private $MOSS;

    private $evidentiaryResourcesMOSS;

    /** Zakazka
     * @var string
     */
    private $contract;

    private $items = [];

    private $myIdentity = [];

    /** @var Address */
    private $customerAddress;

    private $id;

    /** @var string - kod meny */
    private $foreignCurrency;

    /** @var float kurz meny - např. 25.5 pro euro */
    private $rate;

    /** @var int mnozstvi cizi meny pro kurzovy prepocet  */
    private $amount = 1;


    private $required = ['date',  'text'];

    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @param $bool
     * @throws OrderException
     */
    public function setWithVat($bool)
    {
        if (is_bool($bool) === false)
            throw new OrderException($this->getId() . ": setWithVat must use boolean value");
        $this->withVAT = $bool;
    }

    public function getId()
    {
        return $this->id;
    }

    public function isValid()
    {
        return $this->checkRequired();
    }

    /**
     * @throws OrderException
     * @return bool
     */
    private function checkRequired()
    {
        foreach ($this->required as $param) {
            if (!isset($this->$param)) {
                $result = false;
                throw new OrderException($this->getId() . ": required " . $param . " is not set");
            }
        }
        return true;
    }


    /**
     * @throws OrderException
     * @param string $name
     * @param string $value
     * @param bool $maxLength
     * @param bool $isNumeric
     * @param bool $isDate
     */
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
            throw new OrderException($this->getId() . ": " . $name . " - " . $e->getMessage(), 0, $e);
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

    public function addItem(OrderItem $item)
    {
        $this->items[] = $item;
    }

    public function setNumberOrder($order)
    {
        $this->numberOrder = $order;
    }


    /**
     * @param $value
     * @throws OrderException
     * Datum vystavení / Datum zápisu. Tento element je vyžadován při vytvoření dokladu.
     */
    public function setDateCreated($value)
    {
        $this->validateItem('date created', $value, false, false, true);
        $this->date = $this->convertDate($value);
    }

    /**
     * @param $value
     * @throws OrderException
     * Datum dodání. Jen vydané objednávky.
     */

    public function setDateDelivery($value)
    {
        $this->validateItem('date delivery', $value, false, false, true);
        $this->dateDelivery = $this->convertDate($value);
    }


    /**
     * @param $value
     * @throws OrderException
     * Vyřídit od. Jen přijaté objednávky.
     */
    public function setDateFrom($value)
    {
        $this->validateItem('date from', $value, false, false, true);
        $this->datFrom = $this->convertDate($value);
    }
    /**
     * @param $value
     * @throws OrderException
     * math2one,none
     */
    public function setRoundingDocument($value){
        $this->roundingDocument=$value;
    }

    /**
     * @param $value
     * @throws OrderException
     * Vyřídit do. Jen přijaté objednávky.
     */
    public function setDateTo($value)
    {
        $this->validateItem('date to', $value, false, false, true);
        $this->dateTo = $this->convertDate($value);
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

    /**
     * @param string $value
     * @throws OrderException
     */
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
            throw new OrderException($this->getId() . ": payment type $value is not supported. Use one of these: " . implode(",", array_keys($payments)));
        }
        $this->paymentType = $value;
    }

    /** @deprecated */
    public function setPaymentTypeCzech($value)
    {
        return $this->setPaymentTypeString($value);
    }

    public function setPaymentTypeString($value)
    {
        $this->validateItem('payment type string', $value, 20);
        $this->paymentTypeString = $value;
    }


    public function setNote($value)
    {
        $this->note = $value;
    }

    public function setContract($value)
    {
        $this->validateItem('contract', $value, 19);
        $this->contract = $value;
    }


    public function setCentre($value)
    {
        $this->validateItem('centre', $value, 19);
        $this->centre = $value;
    }

    public function regVATinEU($value)
    {
        $this->regVATinEU = $value;
    }

    public function MOSS($moss)
    {
        $this->MOSS = $moss;
    }

    public function evidentiaryResourcesMOSS($evidentiaryResourcesMOSS)
    {
        return $this->evidentiaryResourcesMOSS=$evidentiaryResourcesMOSS;
    }



    /**
     * @param $value
     * @throws OrderException
     * Cenová hladinu odběratele. Jen přijaté objednávky.
     */
    public function setPriceLevel($value)
    {
        $this->priceLevel = $value;
    }

    /**
     * @param $value
     * @throws OrderException
     * Cenová hladinu odběratele. Jen přijaté objednávky.
     */
    public function setIsReserved($value)
    {
        $this->isReserved = $value;
    }


    public function setActivity($value)
    {
        $this->validateItem('activity', $value, 19);
        $this->activity = $value;
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



    public function setForeignCurrency(string $currency, float $rate=null)
    {
        $this->foreignCurrency = $currency;
        $this->rate = $rate;
    }

    public function setProviderIdentity($value)
    {
        if (isset($value['company'])) {
            $this->validateItem('provider - company', $value['company'], 96);
        }
        if (isset($value['ico'])) {
            $value['ico'] = $this->removeSpaces($value['ico']);
            $this->validateItem('provider - ico', $value['ico'], 15, true);
        }
        if (isset($value['street'])) {
            $this->validateItem('provider - street', $value['street'], 64);
        }
        if (isset($value['zip'])) {
            $value['zip'] = $this->removeSpaces($value['zip']);
            $this->validateItem('provider - zip', $value['zip'], 15, true);
        }
        if (isset($value['city'])) {
            $this->validateItem('provider - city', $value['city'], 45);
        }

        $this->myIdentity = $value;
    }

    /**
     * @throws OrderException
     * @param array $customerAddress
     * @param null $identity
     * @param array $shippingAddress
     */
    public function createCustomerAddress(array $customerAddress, $identity = null, array $shippingAddress = [])
    {
        try {
            $address = new Address(
                new \Pohoda\Object\Identity(
                    $identity, //identifikator zakaznika [pokud neni zadan, neprovede se import do adresare]
                    new \Pohoda\Object\Address($customerAddress), //adresa zakaznika
                    new \Pohoda\Object\Address($shippingAddress) //pripadne dodaci adresa
                )
            );

            $this->setCustomerAddress($address);

            return $address;

        } catch (\InvalidArgumentException $e) {
            throw new OrderException($this->getId() . ": " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get shop identity
     * @return array
     */
    public function getProviderIdentity()
    {
        return $this->myIdentity;
    }


    /**
     * @param Address $address
     */
    public function setCustomerAddress(Address $address)
    {
        $this->customerAddress = $address;
    }

    /** storno */
    public function cancelDocument($id)
    {
        $this->cancel = $id;
    }

    public function update($val){
        $this->update=$val;
    }

    public function cancelNumber($number)
    {
        $this->cancelNumber = $number;
    }
    public function orderDelete($id){
        $this->delete = $id;
    }


    public function export(SimpleXMLElement $xml)
    {
        $xmlOrder = $xml->addChild("ord:order", null, self::NS);
        $xmlOrder->addAttribute('version', "2.0");

        if($this->cancel) {
            $xmlOrder
                ->addChild('cancelDocument')
                ->addChild('sourceDocument', null, Export::NS_TYPE)
                ->addChild('number',  $this->cancel, Export::NS_TYPE);
            $this->exportCancelHeader($xmlOrder->addChild("ord:orderHeader", null, self::NS));
        }elseif ($this->delete){
            $this->exportDeleteHeader($xmlOrder->addChild("ord:actionType", null, self::NS));
        } else {
            if($this->update) {

                $this->exportActionTypeUpdate($xmlOrder->addChild("ord:actionType", null, self::NS));
            }


            $this->exportHeader($xmlOrder->addChild("ord:orderHeader", null, self::NS));

            if (!empty($this->items)) {
                $this->exportDetail($xmlOrder->addChild("ord:orderDetail", null, self::NS));
            }
            $this->exportSummary($xmlOrder->addChild("ord:orderSummary", null, self::NS));
        }

    }

    private function exportCancelHeader(SimpleXMLElement $header)
    {
        $header->addChild("orderType", $this->type);
        $header->addChild("ord:text", $this->text);

        if($this->cancelNumber !== null) {
            $num = $header->addChild("ord:numberOrder");
            $num->addChild('typ:ids', $this->cancelNumber, Export::NS_TYPE);
        }

    }

    private function exportDeleteHeader(SimpleXMLElement $header)
    {
        $detete=  $header->addChild("ord:delete");
        $filter= $detete->addChild("ftr:filter", $this->text, Export::NS_FTR);
        $filter->addAttribute('agenda','prijate_objednavky');

        if($this->delete !== null) {
            $filter->addChild('ftr:number', $this->delete, Export::NS_FTR);
        }

    }
    private function exportActionTypeUpdate(SimpleXMLElement $actionType)
    {
        $update=  $actionType->addChild("ord:update");
        $filter= $update->addChild("ftr:filter", '',Export::NS_FTR);
        $filter->addAttribute('agenda','prijate_objednavky');
        if($this->numberOrder !== null) {
            $filter->addChild('ftr:number', $this->numberOrder, Export::NS_FTR);
        }

    }

    private function exportHeader(SimpleXMLElement $header)
    {

        $header->addChild("ord:orderType", $this->type);
        $header->addChild("ord:numberOrder", $this->numberOrder);




        $header->addChild("ord:date", $this->date);
        if (!is_null($this->dateDelivery))
            $header->addChild("ord:dateDelivery", $this->dateDelivery);
        if (!is_null($this->dateFrom))
            $header->addChild("ord:dateFrom", $this->dateFrom);
        if (!is_null($this->dateTo))
            $header->addChild("ord:dateTo", $this->dateTo);


        /*$classification = $header->addChild("ord:classificationVAT");
        if ($this->withVAT) {
            //tuzemske plneni
            $classification->addChild('typ:classificationVATType', 'inland', Export::NS_TYPE);
        } else {
            //nezahrnovat do dph
            $classification->addChild('typ:ids', 'UN', Export::NS_TYPE);
            $classification->addChild('typ:classificationVATType', 'nonSubsume', Export::NS_TYPE);
        }*/



        $header->addChild("ord:text", $this->text);

        $paymentType = $header->addChild("ord:paymentType");
        $paymentType->addChild('typ:paymentType', $this->paymentType, Export::NS_TYPE);
        if (!is_null($this->paymentTypeString)) {
            $paymentType->addChild('typ:ids', $this->paymentTypeString, Export::NS_TYPE);
        }



        if (isset($this->note)) {
            $header->addChild("ord:note", $this->note);
        }

        $header->addChild("ord:intNote", 'Tento doklad byl vytvořen importem přes XML.');

        if (isset($this->contract)) {
            $contract = $header->addChild("ord:contract");
            $contract->addChild('typ:ids', $this->contract, Export::NS_TYPE);
        }

        if(isset($this->centre)) {
            $centre = $header->addChild("ord:centre");
            $centre->addChild('typ:ids', $this->centre, Export::NS_TYPE);
        }

        if(isset($this->activity)) {
            $activity = $header->addChild("ord:activity");
            $activity->addChild('typ:ids', $this->activity, Export::NS_TYPE);
        }


        if(isset($this->priceLevel)) {
            $pricelevel = $header->addChild("ord:priceLevel");
            $pricelevel->addChild('typ:ids', $this->priceLevel, Export::NS_TYPE);
        }
        if(isset($this->isReserved)) {
            $header->addChild("ord:isReserved",$this->isReserved? 'true' : 'false');

        }
        if(isset($this->regVATinEU)) {
            $pricelevel = $header->addChild("ord:regVATinEU");
            $pricelevel->addChild('typ:ids', $this->regVATinEU, Export::NS_TYPE);
        }

        if(isset($this->MOSS)) {
            $moss = $header->addChild("ord:MOSS");
            $moss->addChild('typ:ids', $this->MOSS, Export::NS_TYPE);
        }

        if(isset($this->evidentiaryResourcesMOSS)) {
            $evidentiaryResourcesMOSS = $header->addChild("ord:evidentiaryResourcesMOSS");
            $evidentiaryResourcesMOSS->addChild('typ:ids', $this->evidentiaryResourcesMOSS, Export::NS_TYPE);
        }





        /** Pouze pri exportu z pohody.
         * $liq = $header->addChild("ord:liquidation");
         * $liq->addChild('typ:amountHome', $this->priceTotal, Export::NS_TYPE);
         */

        $myIdentity = $header->addChild("ord:myIdentity");
        $this->exportAddress($myIdentity, $this->myIdentity);

        $partnerIdentity = $header->addChild("ord:partnerIdentity");
        $this->customerAddress->exportAddress($partnerIdentity);


        if (isset($this->dateOrder)) {
            $header->addChild("ord:dateOrder", $this->dateOrder);
        }
    }

    private function exportDetail(SimpleXMLElement $detail)
    {
        foreach ($this->items AS $product) {
            /** @var OrderItem $product */
            $item = $detail->addChild("ord:orderItem");
            $item->addChild("ord:text", $product->getText());
            $item->addChild("ord:quantity", $product->getQuantity());
            $item->addChild("ord:unit", $product->getUnit());
            $item->addChild("ord:coefficient", $product->getCoefficient());
            $item->addChild("ord:payVAT", $product->isPayVAT() ? 'true' : 'false');
            if ($product->getRateVAT())
                $item->addChild("ord:rateVAT", $product->getRateVAT());
            if ($product->getDiscountPercentage())
                $item->addChild("ord:discountPercentage", $product->getDiscountPercentage());

            if ($this->foreignCurrency === null) {
                $hc = $item->addChild("ord:homeCurrency");
                if ($product->getUnitPrice() !==null)
                    $hc->addChild("typ:unitPrice", str_replace(',','.',$product->getUnitPrice()), Export::NS_TYPE);
                if ($product->getPrice()!==null)
                    $hc->addChild("typ:price", str_replace(',','.',$product->getPrice()), Export::NS_TYPE);
                if ($product->getPriceVAT()!==null)
                    $hc->addChild("typ:priceVAT", str_replace(',','.',$product->getPriceVAT()), Export::NS_TYPE);
            } else {
                $fc = $item->addChild('ord:foreignCurrency');
                if ($product->getForeignUnitPrice()!==null)
                    $fc->addChild("typ:unitPrice", str_replace(',','.',$product->getForeignUnitPrice()),Export::NS_TYPE);
                if ($product->getForeignPrice()!==null)
                    $fc->addChild("typ:price", str_replace(',','.',$product->getForeignPrice()), Export::NS_TYPE);
                if ($product->getForeignPriceVAT()!==null)
                    $fc->addChild("typ:priceVAT", str_replace(',','.',$product->getForeignPriceVAT()), Export::NS_TYPE);
            }

            if($product->getNote()!=null) {
                $item->addChild("ord:note", $product->getNote());
            }
            $item->addChild("ord:code", $product->getCode());

            //info o skladove polozce
            if ($product->getStockItem()) {
                $stock = $item->addChild("ord:stockItem");
                $stockItem = $stock->addChild("typ:stockItem", null, Export::NS_TYPE);
                $stockItem->addChild("typ:ids", $product->getStockItem(), Export::NS_TYPE);
                if($product->getStoreId()||$product->getStoreIds()){
                    $store = $stock->addChild("typ:store", null, Export::NS_TYPE);
                    if($product->getStoreId()) {
                        $store->addChild("typ:id", $product->getStoreId(), Export::NS_TYPE);
                    }
                    if($product->getStoreIds()) {
                        $store->addChild("typ:ids", $product->getStoreIds(), Export::NS_TYPE);
                    }
                }
            }
        }
    }

    private function exportAddress(SimpleXMLElement $xml, Array $data, $type = "address")
    {

        $address = $xml->addChild('typ:' . $type, null, Export::NS_TYPE);

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

        if (isset($data['country'])) {
            $address->addChild('typ:country')->addChild('typ:ids', $data['country']);
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

        if (isset($data['phone'])) {
            $address->addChild('typ:phone', $data['phone']);
        }

        if (isset($data['email'])) {
            $address->addChild('typ:email', $data['email']);
        }

    }

    private function exportSummary(SimpleXMLElement $summary)
    {

        $summary->addChild('ord:roundingDocument', $this->roundingDocument); //matematicky na koruny
        $summary->addChild('ord:roundingVAT', $this->roundingVAT);

        $hc = $summary->addChild("ord:homeCurrency");
        if (is_null($this->priceNone) === false)
            $hc->addChild('typ:priceNone', str_replace(',','.',$this->priceNone), Export::NS_TYPE); //cena v nulove sazbe dph
        if (is_null($this->priceLow) === false)
            $hc->addChild('typ:priceLow', str_replace(',','.',$this->priceLow), Export::NS_TYPE); //cena bez dph ve snizene sazbe (15)
        if (is_null($this->priceLowVAT) === false)
            $hc->addChild('typ:priceLowVAT',str_replace(',','.', $this->priceLowVAT), Export::NS_TYPE); //dph ve snizene sazbe
        if (is_null($this->priceLowSum) === false)
            $hc->addChild('typ:priceLowSum', str_replace(',','.',$this->priceLowSum), Export::NS_TYPE); //s dph ve snizene sazbe
        if (is_null($this->priceHigh) === false)
            $hc->addChild('typ:priceHigh', str_replace(',','.',$this->priceHigh), Export::NS_TYPE); //cena bez dph ve zvysene sazbe (21)
        if (is_null($this->priceHightVAT) === false)
            $hc->addChild('typ:priceHighVAT', str_replace(',','.',$this->priceHightVAT), Export::NS_TYPE);
        if (is_null($this->priceHighSum) === false)
            $hc->addChild('typ:priceHighSum', str_replace(',','.',$this->priceHighSum), Export::NS_TYPE);

        if($this->foreignCurrency !== null) {
            $fc = $summary->addChild('ord:foreignCurrency');
            $fc->addChild('typ:currency', null, Export::NS_TYPE)->addChild('typ:ids', $this->foreignCurrency, Export::NS_TYPE);
            if($this->rate !== null) {
                $fc->addChild('typ:rate', $this->rate, Export::NS_TYPE);
            }
            $fc->addChild('typ:amount', $this->amount, Export::NS_TYPE);
        }

        $round = $hc->addChild('typ:round', null, Export::NS_TYPE);
        $round->addChild('typ:priceRound', 0, Export::NS_TYPE); //Celková suma zaokrouhleni

    }
}

class OrderException extends \Exception {};

