<?php namespace Vanderbilt\FHIRServicesExternalModule;

$projectId = $module->getProjectId();
$recordId = $_GET['id'];

if($module->getProjectType() === 'composition'){
    $resource = $module->buildBundle($projectId, $recordId);
}
else if($module->getProjectType() === 'questionnaire'){
    $resource = $module->getFHIRResourceForRecord($projectId, $_GET['id']);
}
else{
    $resource = $module->getMappedFieldsAsBundle($projectId, $recordId);
}

$resource = $module->toArray($resource);

// Remove the ID since it is not allowed because it will be different on the remote system.
// The 'identifier' will still contain the id from this system.
unset($resource['id']);

if(isset($_GET['test'])){
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

$response = $module->sendToRemoteFHIRServer($resource);
echo json_encode([
    'status' => 'success',
    'remote-response' => $module->jsonSerialize($response)
]);