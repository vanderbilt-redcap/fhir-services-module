<?php

$log = $module->getReceivedResource($_GET['log-id']);
$content = $log['content'];
$resource = $module->parse($content);

if(isset($_GET['binary'])){
    $contentType = $module->getValue($resource->getContentType());
    $extension = $module->getExtensionForMIMEType($contentType);

    $filename = 'binary-resource';
    if($extension){
        $filename .= ".$extension";
    }

    $content = base64_decode($resource->getData());
}
else{
    $filename = 'resource.json';
}

header("Content-Disposition: attachment; filename=$filename");
echo $content;
