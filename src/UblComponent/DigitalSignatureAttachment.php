<?php

/**
 * FACTURA ELECTRÓNICA SUNAT
 * UBL 2.1
 * Version 1.0
 * 
 * Copyright 2018, Jaime Cruz
 */

namespace F72X\UblComponent;

use Sabre\Xml\Writer;

class DigitalSignatureAttachment extends BaseComponent {

    /** @var ExternalReference */
    protected $ExternalReference;

    function xmlSerialize(Writer $writer) {
        $writer->write([
            SchemaNS::CAC . 'ExternalReference' => $this->ExternalReference
        ]);
    }

    public function getExternalReference() {
        return $this->ExternalReference;
    }

    public function setExternalReference(ExternalReference $ExternalReference) {
        $this->ExternalReference = $ExternalReference;
        return $this;
    }

}