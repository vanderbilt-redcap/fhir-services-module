<?php

$log = $module->getReceivedResource($_GET['log-id']);
$resource = $module->parse($log['content']);
$contentType = $module->getValue($resource->getContentType());
$extension = $module->getExtensionForMIMEType($contentType);

$filename = 'binary-resource';
if($extension){
    $filename .= ".$extension";
}

header("Content-Disposition: attachment; filename=$filename");
echo base64_decode($resource->getData());
