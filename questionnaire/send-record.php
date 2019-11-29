<?php

$projectId = $module->getProjectId();
$recordId = $_GET['id'];

if($module->getProjectType() === 'composition'){
    $resource = $module->buildBundle($projectId, $recordId);
}
else{
    $resource = $module->getFHIRResourceForRecord($projectId, $_GET['id']);
}

// Remove the ID since it is not allowed because it will be different on the remote system.
// The 'identifier' will still contain the id from this system.
$resource->setId(null);

if(isset($_GET['test'])){
    header('Content-type: application/fhir+json'); 
    echo $module->jsonSerialize($resource);
    die();
}

$resourceType = $resource->_getFHIRTypeName();

$url = $module->getRemoteFHIRServerUrl();
$response = file_get_contents("$url/$resourceType", false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/fhir+json\r\n",
        'content' => $module->jsonSerialize($resource),
        'ignore_errors' => true
    ]
]));

$handleError = function() use ($resourceType, $response){
    echo "A $resourceType response was expected, but ";
    
    if(empty($response)){
        echo "an empty response was received.";
    }
    else{
        echo "the following was received instead: $response";
    }

    echo "\n";
};

try{
    $resource = $module->parse($response);
    $responseResourceType = $resource->_getFHIRTypeName();
    if($responseResourceType === $resourceType){
        echo 'success';
    }
    else{
        $handleError();
    }
}
catch(Exception $e){
    $handleError();
    throw $e;
}
