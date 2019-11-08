<?php namespace Vanderbilt\FHIRServicesExternalModule;

class FHIRBundle extends \DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRBundle{
    function jsonSerialize(){
        $o = parent::jsonSerialize();

        // This fixes an upstream bug that exludes the 'system'.
        // TODO - We should contribute a permanent upstream fix.
        $o['identifier'] = $this->getIdentifier()->jsonSerialize();
        
        return $o;
    }
}