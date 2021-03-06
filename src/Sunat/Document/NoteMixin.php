<?php

/**
 * MÓDULO DE EMISIÓN ELECTRÓNICA F72X
 * UBL 2.1
 * Version 1.0
 * 
 * Copyright 2019, Jaime Cruz
 */

namespace F72X\Sunat\Document;

use F72X\UblComponent\DiscrepancyResponse;
use F72X\UblComponent\BillingReference;
use F72X\UblComponent\InvoiceDocumentReference;
use F72X\UblComponent\RequestedMonetaryTotal;

trait NoteMixin {

    public function addDiscrepancyResponse() {

        // Data
        $dataMap = $this->dataMap;
        $affectedDocId = $dataMap->getNoteAffectedDocId();
        $type = $dataMap->getNoteType();
        $description = $dataMap->getNoteDescription();

        // XML Nodes
        $DiscrepancyResponse = new DiscrepancyResponse();
        $DiscrepancyResponse
                ->setReferenceID($affectedDocId)
                ->setResponseCode($type)
                ->setDescription($description);

        // Add Node
        parent::setDiscrepancyResponse($DiscrepancyResponse);
    }

    public function addBillingReference() {

        // Data
        $dataMap = $this->dataMap;
        $affDocId = $dataMap->getNoteAffectedDocId();
        $affDocType = $dataMap->getNoteAffectedDocType();

        // XML Nodes
        $BillingReference = new BillingReference();
        $InvoiceDocumentReference = new InvoiceDocumentReference();
        $BillingReference
                ->setInvoiceDocumentReference($InvoiceDocumentReference
                        ->setID($affDocId)
                        ->setDocumentTypeCode($affDocType));

        // Add Node
        parent::setBillingReference($BillingReference);
    }

    private function addRequestedMonetaryTotal() {
        $dataMap         = $this->dataMap;
        $currencyID      = $this->getDocumentCurrencyCode(); // Tipo de moneda
        $totalAllowances = $dataMap->getTotalAllowances();   // Total descuentos
        $payableAmount   = $dataMap->getPayableAmount();     // Total a pagar
        $billableAmount  = $dataMap->getBillableValue();
        // RequestedMonetaryTotal
        $RequestedMonetaryTotal = new RequestedMonetaryTotal();
        $RequestedMonetaryTotal
                ->setCurrencyID($currencyID)
                ->setLineExtensionAmount($billableAmount)
                ->setTaxInclusiveAmount($payableAmount)
                ->setAllowanceTotalAmount($totalAllowances)
                ->setPayableAmount($payableAmount);

        parent::setRequestedMonetaryTotal($RequestedMonetaryTotal);
    }

}
