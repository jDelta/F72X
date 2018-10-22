<?php

/**
 * MÓDULO DE EMISIÓN ELECTRÓNICA F72X
 * UBL 2.1
 * Version 1.1
 * 
 * Copyright 2018, Jaime Cruz
 */

namespace F72X\Sunat\Document;

use F72X\Company;
use F72X\UblComponent\Invoice;
use F72X\Sunat\InvoiceDocument;
use F72X\Sunat\InvoiceItems;
use F72X\Sunat\Catalogo;
use F72X\Sunat\SunatVars;
use F72X\Tools\UblHelper;
use F72X\UblComponent\OrderReference;
use F72X\UblComponent\Party;
use F72X\UblComponent\PartyIdentification;
use F72X\UblComponent\PartyName;
use F72X\UblComponent\AccountingSupplierParty;
use F72X\UblComponent\AccountingCustomerParty;
use F72X\UblComponent\PartyLegalEntity;
use F72X\UblComponent\TaxTotal;
use F72X\UblComponent\TaxSubTotal;
use F72X\UblComponent\TaxCategory;
use F72X\UblComponent\TaxScheme;
use F72X\UblComponent\LegalMonetaryTotal;
use F72X\UblComponent\InvoiceLine;
use F72X\UblComponent\PricingReference;
use F72X\UblComponent\AlternativeConditionPrice;
use F72X\UblComponent\Item;
use F72X\UblComponent\SellersItemIdentification;
use F72X\UblComponent\CommodityClassification;
use F72X\UblComponent\Price;

abstract class SunatDocument extends Invoice {

    const UBL_VERSION_ID = '2.1';
    const CUSTUMIZATION_ID = '2.0';

    /** @var InvoiceDocument */
    private $invoiceDocument;

    /** @var InvoiceItems */
    private $_detailMatrix;

    public function __construct(InvoiceDocument $Invoice) {
        $this->invoiceDocument = $Invoice;
        $currencyType = $Invoice->getCurrencyType();
        $Items = $Invoice->getItems();
        // Invoice Type
        $this->setInvoiceTypeCode($Invoice->getInvoiceType());
        // ID
        $this->setID($Invoice->getInvoiceId());
        // Tipo de operación
        $this->setProfileID($Invoice->getOperationType());
        // Fecha de emisión
        $this->setIssueDate($Invoice->getIssueDate());
        // Tipo de moneda
        $this->setDocumentCurrencyCode($currencyType);
        // Orden de compra
        $this->addInvoiceOrderReference();
        // Información de la empresa
        $this->addInvoiceAccountingSupplierParty();
        // Información del cliente
        $this->addInvoiceAccountingCustomerParty();
        // Total items
        $this->setLineCountNumeric($Invoice->getTotalItems());
        // Detalle
        $this->addInvoiceItems();
        // Impuestos
        $this->addInvoiceTaxes();
        // Descuentos globales
        $ac = $Invoice->getAllowancesAndCharges();
        $baseAmount = $Items->getTotalTaxableAmount();
        UblHelper::addAllowancesCharges($this, $ac, $baseAmount, $currencyType);
        // Totales
        $this->addInvoiceLegalMonetaryTotal();
    }

    public function getInvoiceDocument() {
        return $this->invoiceDocument;
    }

    /**
     * 
     * @return InvoiceItems
     */
    public function getItems() {
        return $this->invoiceDocument->getItems();
    }

    public function getDetailMatrix() {
        return $this->_detailMatrix;
    }

    public function setDetailMatrix(InvoiceItems $DetailMatrix) {
        $this->_detailMatrix = $DetailMatrix;
        return $this;
    }

    private function addInvoiceItems() {
        $ln = $this->invoiceDocument->getTotalItems();
        // Loop
        for ($i = 0; $i < $ln; $i++) {
            $this->addInvoiceItem($i);
        }
    }

    private function addInvoiceOrderReference() {
        $orderNumer = $this->invoiceDocument->getPurchaseOrder();
        if ($orderNumer) {
            // Xml Node
            $orderRef = new OrderReference();
            // Añadir al documento
            $this->setOrderReference($orderRef
                            ->setID($orderNumer));
        }
    }

    private function addInvoiceTaxes() {
        $Invoice                   = $this->invoiceDocument;
        $currencyID                = $Invoice->getCurrencyType();              // Tipo de moneda
        $totalTaxableOperations    = $Invoice->getTotalTaxableOperations();    // Total operaciones gravadas
        $totalTaxes                = $Invoice->getTotalTaxes();                // Total operaciones gravadas
        $Igv                       = $Invoice->getIGV();                       // Total IGV
        $totalExemptedOperations   = $Invoice->getTotalExemptedOperations();   // Total operaciones exoneradas
        $totalUnaffectedOperations = $Invoice->getTotalUnaffectedOperations(); // Total operaciones inafectas
        $totalFreeOpertions        = $Invoice->getTotalFreeOperations();       // Total operaciones gratuitas

        // XML nodes
        $TaxTotal = new TaxTotal();

        // Operaciones gravadas
        if ($Igv) {
            UblHelper::addTaxSubtotal($TaxTotal, $currencyID, $Igv, $totalTaxableOperations, Catalogo::CAT5_IGV);
        }
        // Total operaciones exoneradas
        if ($totalExemptedOperations) {
            UblHelper::addTaxSubtotal($TaxTotal, $currencyID, 0, $totalExemptedOperations,   Catalogo::CAT5_EXO);
        }
        // Total operaciones inafectas
        if ($totalUnaffectedOperations) {
            UblHelper::addTaxSubtotal($TaxTotal, $currencyID, 0, $totalUnaffectedOperations, Catalogo::CAT5_INA);
        }
        // Total operaciones gratuitas solo aplica a FACTURA
        if ($totalFreeOpertions && $Invoice->getInvoiceType() === Catalogo::CAT1_FACTURA) {
            UblHelper::addTaxSubtotal($TaxTotal, $currencyID, 0, $totalFreeOpertions,        Catalogo::CAT5_GRA);
        }

        // Total impuestos
        $TaxTotal
                ->setCurrencyID($currencyID)
                ->setTaxAmount($totalTaxes);
        // Anadir al documento
        $this->setTaxTotal($TaxTotal);
    }

    /**
     * 
     * @param int $itemIndex Index del item
     */
    private function addInvoiceItem($itemIndex) {

        // XML Nodes
        $InvoiceLine      = new InvoiceLine();
        $PricingReference = new PricingReference();
        $TaxTotal         = new TaxTotal();
        $TaxSubTotal      = new TaxSubTotal();
        $TaxCategory      = new TaxCategory();
        $TaxCategory
                ->setElementAttributes('ID', [
                    'schemeID'         => 'UN/ECE 5305',
                    'schemeName'       => 'Tax Category Identifier',
                    'schemeAgencyName' => 'United Nations Economic Commission for Europe'])
                ->setElementAttributes('TaxExemptionReasonCode', [
                    'listAgencyName'   => 'PE:SUNAT',
                    'listName'         => 'SUNAT:Codigo de Tipo de Afectación del IGV',
                    'listURI'          => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo07']);

        $TaxScheme = new TaxScheme();
        $TaxScheme
                ->setElementAttributes('ID', [
                    'schemeID'         => 'UN/ECE 5153',
                    'schemeName'       => 'Tax Scheme Identifier',
                    'schemeAgencyName' => 'United Nations Economic Commission for Europe']);

        $AlternativeConditionPrice  = new AlternativeConditionPrice();
        $Item                       = new Item();
        $SellersItemIdentification  = new SellersItemIdentification();
        $CommodityClassification    = new CommodityClassification();
        $Price                      = new Price();
        // Detail Operation Matrix
        $Items = $this->invoiceDocument->getItems();
        // Vars
        $productCode        = $Items->getProductCode($itemIndex);
        $sunatProductCode   = $Items->getUNPSC($itemIndex);
        $unitCode           = $Items->getUnitCode($itemIndex);
        $quantity           = $Items->getQunatity($itemIndex);
        $description        = $Items->getDescription($itemIndex);
        $currencyType       = $Items->getCurrencyCode($itemIndex);
        $unitBillableValue  = $Items->getUnitBillableValue($itemIndex);
        $priceTypeCode      = $Items->getPriceTypeCode($itemIndex);
        $taxTypeCode        = $Items->getTaxTypeCode($itemIndex);
        $igvAffectationType = $Items->getIgvAffectationType($itemIndex);

        $itemValue          = $Items->getItemValue($itemIndex);
        $ac                 = $Items->getAllowancesAndCharges($itemIndex);
        $itemTaxableAmount  = $Items->getTaxableAmount($itemIndex);
        $itemTaxAmount      = $Items->getIgv($itemIndex);
        $unitPrice          = $Items->getUnitTaxedValue($itemIndex);

        // Catálogo 5 Ipuesto aplicable
        $cat5Item = Catalogo::getCatItem(5, $taxTypeCode);

        // Descuentos y cargos
        UblHelper::addAllowancesCharges($InvoiceLine, $ac, $itemValue, $currencyType);

        // Config Item
        $Item->setDescription($description); // Descripción
        // Código de producto
        if ($productCode) {
            $Item->setSellersItemIdentification($SellersItemIdentification->setID($productCode));
        }
        // Código de producto SUNAT
        if ($sunatProductCode) {
            $Item->setCommodityClassification($CommodityClassification->setItemClassificationCode($sunatProductCode));
        }
        $InvoiceLine
                ->setCurrencyID($currencyType)                    // Tipo de moneda
                ->setID($itemIndex + 1)                         // Número de orden
                ->setUnitCode($unitCode)                        // Codigo de unidad de medida
                ->setInvoicedQuantity($quantity)                // Cantidad
                ->setLineExtensionAmount($itemTaxableAmount)    // Valor de venta del ítem, sin impuestos
                ->setPricingReference($PricingReference
                        ->setAlternativeConditionPrice($AlternativeConditionPrice
                                ->setCurrencyID($currencyType)            // Tipo de moneda
                                ->setPriceAmount($unitPrice)            // Precio de venta unitario
                                ->setPriceTypeCode($priceTypeCode)))    // Price
                ->setTaxTotal($TaxTotal
                        ->setCurrencyID($currencyType)
                        ->setTaxAmount($itemTaxAmount)
                        ->addTaxSubTotal($TaxSubTotal
                                ->setCurrencyID($currencyType)            // Tipo de moneda
                                ->setTaxableAmount($itemTaxableAmount)  // Valor de venta del item sin impuestos
                                ->setTaxAmount($itemTaxAmount)          // IGV
                                ->setTaxCategory($TaxCategory
                                        ->setID($cat5Item['categoria'])                     // Codigo de categoria de immpuestos @CAT5
                                        ->setPercent(SunatVars::IGV_PERCENT)                // Porcentaje de IGV (18.00)
                                        ->setTaxExemptionReasonCode($igvAffectationType)    // Código de afectación del IGV
                                        ->setTaxScheme($TaxScheme
                                                ->setID($taxTypeCode)                       // Codigo de categoria de impuesto
                                                ->setName($cat5Item['name'])
                                                ->setTaxTypeCode($cat5Item['UN_ECE_5153'])))))
                ->setItem($Item)
                ->setPrice($Price
                        ->setCurrencyID($currencyType)    // Tipo de moneda
                        ->setPriceAmount($unitBillableValue)    // Precio unitario del item
        );
        // Añade item
        $this->addInvoiceLine($InvoiceLine);
    }

    private function addInvoiceLegalMonetaryTotal() {
        $Invoice            = $this->invoiceDocument;
        $Items              = $this->getItems();
        $currencyID         = $this->getDocumentCurrencyCode(); // Tipo de moneda
        $totalAllowances    = $Invoice->getTotalAllowances();   // Total descuentos
        $payableAmount      = $Invoice->getPayableAmount();     // Total a pagar
        $billableAmount     = $Invoice->getBillableValue();
        // LegalMonetaryTotal
        $LegalMonetaryTotal = new LegalMonetaryTotal();
        $LegalMonetaryTotal
                ->setCurrencyID($currencyID)
                ->setLineExtensionAmount($billableAmount)
                ->setTaxInclusiveAmount($payableAmount)
                ->setAllowanceTotalAmount($totalAllowances)
                ->setPayableAmount($payableAmount);

        $this->setLegalMonetaryTotal($LegalMonetaryTotal);
    }

    private function addInvoiceAccountingSupplierParty() {
        // Info
        $partyName  = Company::getBusinessName();
        $regName    = Company::getCompanyName();
        $docNumber  = Company::getRUC();
        $docType    = Catalogo::IDENTIFICATION_DOC_RUC;

        // XML nodes
        $AccountingSupplierParty    = new AccountingSupplierParty();
        $Party                      = new Party();
        $PartyIdentification        = new PartyIdentification();
        $PartyIdentification
                ->setElementAttributes('ID', [
                    'schemeAgencyName'  => 'PE:SUNAT',
                    'schemeID'          => $docType,
                    'schemeName'        => 'Documento de Identidad',
                    'schemeURI'         => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06']);
        $PartyName                  = new PartyName();
        $PartyLegalEntity           = new PartyLegalEntity();

        $AccountingSupplierParty
                ->setParty($Party
                        ->setPartyIdentification($PartyIdentification
                                ->setID($docNumber))
                        ->setPartyName($PartyName
                                ->setName($partyName))
                        ->setPartyLegalEntity($PartyLegalEntity
                                ->setRegistrationName($regName)));
        // Add to Document
        $this->setAccountingSupplierParty($AccountingSupplierParty);
    }

    private function addInvoiceAccountingCustomerParty() {
        $Invoice   = $this->invoiceDocument;
        // Info
        $regName   = $Invoice->getCustomerRegName();
        $docNumber = $Invoice->getCustomerDocNumber();
        $docType   = $Invoice->getCustomerDocType();

        // XML nodes
        $AccountingCustomerParty    = new AccountingCustomerParty();
        $Party                      = new Party();
        $PartyIdentification        = new PartyIdentification();
        $PartyLegalEntity           = new PartyLegalEntity();
        $PartyIdentification
                ->setElementAttributes('ID', [
                    'schemeAgencyName'  => 'PE:SUNAT',
                    'schemeID'          => $docType,
                    'schemeName'        => 'Documento de Identidad',
                    'schemeURI'         => 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo06']);

        $AccountingCustomerParty
                ->setParty($Party
                        ->setPartyIdentification($PartyIdentification
                                ->setID($docNumber))
                        ->setPartyLegalEntity($PartyLegalEntity
                                ->setRegistrationName($regName)));
        // Add to Document
        $this->setAccountingCustomerParty($AccountingCustomerParty);
    }

    /**
     *    
     * @return string Nombre del comprobante de acuerdo con las especificaciones de la SUNAT
     */
    public function getBillName() {
        return $this->invoiceDocument->getBillName();
    }

}
