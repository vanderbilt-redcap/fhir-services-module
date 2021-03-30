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
    // This feature likely won't live here long term, but this is a good place for testing.
    try{

        $bundle = $module->getMappedFieldsAsBundle($projectId, $recordId);
        $module->validateInBrowserAndDisplay($bundle);
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

// Remove the ID since it is not allowed because it will be different on the remote system.
// The 'identifier' will still contain the id from this system.
$resource->setId(null);

if(isset($_GET['test'])){
    echo $module->jsonSerialize($resource);
    die();
}

$response = $module->sendToRemoteFHIRServer($resource);
echo json_encode([
    'status' => 'success',
    'remote-response' => $module->jsonSerialize($response)
]);