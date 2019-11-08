<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROperationOutcome;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROperationOutcome\FHIROperationOutcomeIssue;

$sendResponse = function($o) use ($module){
    header('Content-type: application/fhir+json'); 
    echo $module->jsonSerialize($o);
    exit();
};

$sendErrorResponse = function($message, $diagnostics=null) use (&$sendResponse){
    if(strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === 0){
        echo "A browser was detected.  The OperationOutcome will be prefixed with a human readable version of the error details:\n\n$message\n\n$diagnostics\n\n";
    }

    $sendResponse(new FHIROperationOutcome([
        'issue' => new FHIROperationOutcomeIssue([
            'severity' => 'error',
            'code' => 'processing',
            'details' => [
                'text' => $message
            ],
            'diagnostics' => $diagnostics
        ])
    ]));
};

$urlParts = $module->getFHIRUrlParts();
$type = $urlParts[1];

try{
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $response = $module->saveResource($type);
    }
    else if($type === 'Composition' && $urlParts[3] === '$document'){
        list($projectId, $recordId) = $module->getProjectAndRecordIdsFromFHIRUrl();
        $response = $module->buildBundle($projectId, $recordId);
    }
    else if ($type === 'QuestionnaireResponse'){
        $response = $module->getQuestionnaireResponse();
    }
    else{
        $sendErrorResponse("The specified FHIR URL is not supported for this request type: $fhirUrl");
    }

    $sendResponse($response);
}
catch(Exception $e){
    $sendErrorResponse("Exception: " . $e->getMessage(), $e->getTraceAsString());
}