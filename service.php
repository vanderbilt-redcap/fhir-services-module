<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/FHIRUtil.php';

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROperationOutcome;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROperationOutcome\FHIROperationOutcomeIssue;

$sendResponse = function($o){
    header("Content-disposition: attachment; filename=\"bundle.json\""); 
    echo FHIRUtil::jsonSerialize($o);
    exit();
};

$sendErrorResponse = function($message) use (&$sendResponse){
    $sendResponse(new FHIROperationOutcome([
        'issue' => new FHIROperationOutcomeIssue([
            'severity' => 'error',
            'code' => 'processing',
            'diagnostics' => $message
        ])
    ]));
};

$fhirUrl = $_GET['fhir-url'];
if(empty($fhirUrl)){
    $sendErrorResponse("You must specify a 'fhir-url' parameter.");
}

$parts = explode('/', $fhirUrl);
if($parts[1] === 'Composition' && $parts[3] === '$document'){
    $compositionId = $parts[2];
    $sendResponse(FHIRUtil::buildBundle($compositionId));
}

$sendErrorResponse("The specified FHIR URL is not supported: $fhirUrl");