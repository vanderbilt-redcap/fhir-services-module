<?php

$questionnaireResponse = $module->getFHIRResourceForRecord($_GET['id']);

$resourceType = 'QuestionnaireResponse';

$url = $module->getProjectSetting('remote-fhir-server-url');
$response = file_get_contents("$url/$resourceType", false, stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $module->jsonSerialize($questionnaireResponse),
        'ignore_errors' => true
    ]
]));

$resource = $module->parse($response);
$responseResourceType = $resource->_getFHIRTypeName();
if($responseResourceType === $resourceType){
    echo 'success';
}
else{
    echo "A $resourceType response was expected, but the following was received instead: $response";
}