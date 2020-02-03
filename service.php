<?php namespace Vanderbilt\FHIRServicesExternalModule;

use Exception;

use DCarbone\PHPFHIRGenerated\R4\FHIRResource\FHIRDomainResource\FHIROperationOutcome;
use DCarbone\PHPFHIRGenerated\R4\FHIRElement\FHIRBackboneElement\FHIROperationOutcome\FHIROperationOutcomeIssue;

$sendErrorResponse = function($message, $diagnostics=null) use ($module){
    if(strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === 0){
        echo "A browser was detected.  The OperationOutcome will be prefixed with a human readable version of the error details:\n\n$message\n\n$diagnostics\n\n";
    }

    $module->sendRespondAndExit(new FHIROperationOutcome([
        'issue' => [
            [
                'severity' => 'error',
                'code' => 'processing',
                'details' => [
                    'text' => $message
                ],
                'diagnostics' => $diagnostics
            ]
        ]
    ]));
};

$urlParts = $module->getFHIRUrlParts();
$type = $urlParts[1];

try{
    $method = $_SERVER['REQUEST_METHOD'];
    if($method === 'POST'){
        $response = $module->saveResource($type);
    }
    else if($method === 'GET'){
        list($projectId, $recordId) = $module->getProjectAndRecordIdsFromFHIRUrl();
        
        if($type === 'Composition'){
            if($urlParts[3] === '$document'){
                $response = $module->buildBundle($projectId, $recordId);
            }
            else{
                $sendErrorResponse("The only Composition request currently supported is \$document.");
            }
        }
        else{
            $response = $module->getFHIRResourceForRecord($projectId, $recordId);

            if(!$response){
                $sendErrorResponse("The specified FHIR URL is not supported for this request type: " . $module->getFHIRUrl());
            }
            else if($response->_getFHIRTypeName() !== $type){
                $sendErrorResponse("Expected type $type but found " . $response->_getFHIRTypeName());
            }
        }
    }
    else{
        throw new Exception("Request method not supported: $method");
    }

    $module->sendRespondAndExit($response);
}
catch(Exception $e){
    $sendErrorResponse("Exception: " . $e->getMessage(), $e->getTraceAsString());
}