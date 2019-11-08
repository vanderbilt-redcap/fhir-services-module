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
        echo "A browser was detected.  The OperationOutcome will be prefixed with a human readable version of the error details:\n\n$diagnostics\n\n";
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

try{
    if($urlParts[1] === 'Composition' && $urlParts[3] === '$document'){
        $response = $module->buildBundle();
    }
    else if ($urlParts[1] === 'Questionnaire' && $_SERVER['REQUEST_METHOD'] === 'POST'){
        $response = $module->saveQuestionnaire();
    }
    else if ($urlParts[1] === 'QuestionnaireResponse'){
        $response = $module->getQuestionnaireResponse();
    }
    else{
        $sendErrorResponse("The specified FHIR URL is not supported: $fhirUrl");
    }

    $sendResponse($response);
}
catch(Exception $e){
    $sendErrorResponse("Exception: " . $e->getMessage(), $e->getTraceAsString());
}