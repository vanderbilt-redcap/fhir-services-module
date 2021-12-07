<?php namespace Vanderbilt\FHIRServicesExternalModule;

$projectId = $module->getProjectId();
$recordId = $module->getRecordId();

if($module->getProjectType() === 'composition'){
    $resource = $module->buildBundle($projectId, $recordId);
}
else if($module->getProjectType() === 'questionnaire'){
    $resource = $module->getFHIRResourceForRecord($projectId, $recordId);
}
else{
    $resource = $module->getMappedFieldsAsBundle($projectId, $recordId);
}

$resource = $module->toArray($resource);

// Remove the ID since it is not allowed because it will be different on the remote system.
// The 'identifier' will still contain the id from this system.
unset($resource['id']);

$action = @$_GET['action'];
if($action === 'view'){
    header('Content-type: application/json'); 
    echo $module->jsonSerialize($resource);
    return;
}
else if($action == 'validate'){
    try{
        $module->validateInBrowserAndDisplay($resource);
    }
    catch(\Throwable $t){
        if($t instanceof StackFreeException){
            die($t->getMessage());
        }
        else{
            throw $t;
        }
    }

    return;
}

header('Content-type: application/json'); 
try{
    $response = $module->sendToRemoteFHIRServer($resource);
    echo json_encode([
        'message' => 'The remote FHIR server has confirmed that the data has been successfully received.',
        'remote-response' => $module->jsonSerialize($response)
    ]);
}
catch(\Throwable $t){
    error_log($t->__toString());
    echo json_encode([
        'message' => $t->getMessage()
    ]);
}