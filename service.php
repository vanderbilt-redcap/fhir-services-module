<?php namespace Vanderbilt\FHIRServicesExternalModule;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROperationOutcome;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROperationOutcome\FHIROperationOutcomeIssue;

$sendResponse = function($o) use ($module){
    header('Content-type: application/fhir+json'); 
    echo $module->jsonSerialize($o);
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

$urlParts = explode('/', $fhirUrl);
$resourceId = $urlParts[2];
$idParts = explode('-', $resourceId);
$projectId = $idParts[0];
$recordId = $idParts[1];

if(
    empty($projectId)
    ||
    !ctype_digit($projectId)
    ||
    empty($recordId)
){
    $sendErrorResponse("The resource ID specified is not valid: $resourceId");
}

if($urlParts[1] === 'Composition' && $urlParts[3] === '$document'){
    $sendResponse($module->buildBundle($projectId, $recordId));
}
else if ($urlParts[1] === 'QuestionnaireResponse'){
    $sendResponse($module->getQuestionnaireResponse($projectId, $recordId));
}

$sendErrorResponse("The specified FHIR URL is not supported: $fhirUrl");