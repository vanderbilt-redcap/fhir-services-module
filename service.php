<?php namespace Vanderbilt\FHIRServicesExternalModule;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/FHIRUtil.php';

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROperationOutcome;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROperationOutcome\FHIROperationOutcomeIssue;

$sendResponse = function($o){
    header('Content-type: application/fhir+json'); 
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
$idParts = explode('-', $parts[2]);
$projectId = $idParts[0];
$recordId = $idParts[1];

if($parts[1] === 'Composition' && $parts[3] === '$document'){
    $sendResponse(FHIRUtil::buildBundle($projectId, $recordId));
}
else if ($parts[1] === 'QuestionnaireResponse'){
    $sendResponse(FHIRUtil::getQuestionnaireResponse($projectId, $recordId));
}

$sendErrorResponse("The specified FHIR URL is not supported: $fhirUrl");